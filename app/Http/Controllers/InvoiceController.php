<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Collection;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Fabric;
use App\Models\Invoice;
use App\Models\Measurement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('client:id,name')
            ->latest('date');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $invoices = $query->paginate(min((int) $request->input('per_page', 15), 100))
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status', 'type', 'per_page']),
        ]);
    }

    public function create(Request $request)
    {
        // If a client_id is passed (e.g. from client detail page)
        $selectedClientId = $request->input('client_id');
        $type = $request->input('type', 'invoice');

        // Props are closures so Inertia partial reloads (e.g. the wizard
        // re-syncing fabrics when entering the Items step) only run the
        // queries they actually ask for.
        return Inertia::render('Invoices/Create', [
            'clients' => fn () => Client::with([
                'contacts:id,client_id,name',
                'contacts.measurements:id,contact_id,garment_type,label',
                // phone/email are searched on and rendered in steps 1 and 3 —
                // without them, search-by-phone silently matched nothing.
            ])->select('id', 'name', 'phone', 'email')->orderBy('name')->get(),
            'fabrics' => fn () => Fabric::where('status', 'active')
                ->select('id', 'code', 'name', 'type', 'color', 'price_per_unit')
                ->orderBy('name')->get(),
            'collections' => fn () => Collection::active()->inStock()
                ->with('category:id,name')
                ->select('id', 'category_id', 'name', 'sku', 'size', 'color', 'price', 'stock_qty')
                ->orderBy('name')->get(),
            'invoiceNumber' => fn () => Invoice::generateInvoiceNumber(),
            'quoteNumber' => fn () => Invoice::generateQuoteNumber(),
            'selectedClientId' => $selectedClientId ? (int) $selectedClientId : null,
            'defaultType' => $type,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:invoice,quotation',
            'client_id' => 'required|exists:clients,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:flat,percentage',
            'tax' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,mpesa,bank_transfer,credit',
            'notes' => 'nullable|string|max:2000',
            'initial_payment' => 'nullable|numeric|min:0',
            'line_items' => 'required|array|min:1',
            'line_items.*.item_type' => 'nullable|in:custom,collection',
            'line_items.*.contact_id' => 'nullable|exists:contacts,id',
            'line_items.*.measurement_id' => 'nullable|exists:measurements,id',
            'line_items.*.fabric_id' => 'nullable|exists:fabrics,id',
            'line_items.*.collection_id' => 'nullable|exists:collections,id',
            'line_items.*.description' => 'nullable|string|max:255',
            'line_items.*.unit_price' => 'nullable|numeric|min:0',
            'line_items.*.quantity' => 'required|numeric|min:0.01',   // fabric sells by the metre
            'line_items.*.craftsmanship_fee' => 'nullable|numeric|min:0',
            'line_items.*.fabric_cost' => 'nullable|numeric|min:0',
            'line_items.*.ridhaa_name' => 'nullable|string|max:255',
            'line_items.*.ridhaa_qty' => 'nullable|numeric|min:0',
            'line_items.*.ridhaa_price' => 'nullable|numeric|min:0',
        ]);

        // Shelf items come out of counted stock, so they stay whole units.
        foreach ($validated['line_items'] as $i => $li) {
            if (($li['item_type'] ?? 'custom') === 'collection'
                && floor((float) $li['quantity']) != (float) $li['quantity']) {
                throw ValidationException::withMessages([
                    "line_items.{$i}.quantity" => 'Shelf items are sold in whole units.',
                ]);
            }
        }

        // A line item may only reference people — and their measurements —
        // belonging to this invoice's own client. Without this, a stale
        // selection (or a crafted request) could attach another client's
        // family member and their measurements to the invoice.
        $this->assertLineItemsBelongToClient($validated);

        // Calculate subtotal from line items
        $subtotal = 0;
        foreach ($validated['line_items'] as &$item) {
            $item['item_type'] = $item['item_type'] ?? 'custom';
            $item['craftsmanship_fee'] = $item['craftsmanship_fee'] ?? 0;
            $item['fabric_cost'] = $item['fabric_cost'] ?? 0;
            $item['unit_price'] = $item['unit_price'] ?? 0;
            $item['ridhaa_qty'] = $item['ridhaa_qty'] ?? 0;
            $item['ridhaa_price'] = $item['ridhaa_price'] ?? 0;
            $item['ridhaa_name'] = $item['ridhaa_name'] ?? null;
            $ridhaaTotal = $item['ridhaa_qty'] * $item['ridhaa_price'];

            if ($item['item_type'] === 'collection') {
                // For collection items: unit_price * quantity
                $item['line_total'] = $item['unit_price'] * $item['quantity'];
            } else {
                // For custom items: (craftsmanship + fabric) * quantity
                $item['line_total'] = ($item['craftsmanship_fee'] + $item['fabric_cost']) * $item['quantity'] + $ridhaaTotal;
            }
            $subtotal += $item['line_total'];
        }

        // Calculate discount
        $discount = $validated['discount'] ?? 0;
        $discountType = $validated['discount_type'] ?? 'flat';
        $discountAmount = $discountType === 'percentage'
            ? round($subtotal * $discount / 100, 2)
            : $discount;

        // Calculate tax and total
        $taxRate = $validated['tax'] ?? 0;
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = round($afterDiscount * $taxRate / 100, 2);
        $total = $afterDiscount + $taxAmount;

        // Initial payment
        $initialPayment = min($validated['initial_payment'] ?? 0, $total);
        $balance = $total - $initialPayment;

        // Determine status
        $status = 'draft';
        if ($initialPayment > 0 && $balance <= 0) {
            $status = 'paid';
        } elseif ($initialPayment > 0) {
            $status = 'issued';
        }

        $type = $validated['type'] ?? 'invoice';

        // Number generation + all dependent writes run in one transaction so the
        // pessimistic lock in Invoice::nextNumber() holds across the insert, and
        // the invoice/line-items/payment are created atomically (all or nothing).
        // withUniqueNumber() re-derives the number and retries if a concurrent
        // caller wins the same one (esp. the first insert of a prefix).
        $invoice = Invoice::withUniqueNumber(function () use (
            $validated, $type, $subtotal, $discountAmount, $discountType,
            $taxAmount, $total, $initialPayment, $balance, $status
        ) {
            $docNumber = $type === 'quotation'
                ? Invoice::generateQuoteNumber()
                : Invoice::generateInvoiceNumber();

            $invoice = Invoice::create([
                'client_id' => $validated['client_id'],
                'invoice_number' => $docNumber,
                'type' => $type,
                'date' => $validated['date'],
                // These are all nullable, so the key is simply absent when the
                // caller omits it — reading it directly would throw.
                'due_date' => $validated['due_date'] ?? null,
                'status' => $status,
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'discount_type' => $discountType,
                'tax' => $taxAmount,
                'total' => $total,
                'amount_paid' => $initialPayment,
                'balance' => $balance,
                'payment_method' => $validated['payment_method'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create line items and deduct collection stock
            foreach ($validated['line_items'] as $item) {
                $invoice->lineItems()->create($item);

                // Deduct stock for collection items.
                // Guarded atomic decrement, matching POS: the WHERE stock_qty >= qty
                // refuses to oversell rather than driving stock negative, and closes
                // the check-then-act race when two people bill the last unit at once.
                if (($item['item_type'] ?? 'custom') === 'collection' && !empty($item['collection_id'])) {
                    $sold = Collection::whereKey($item['collection_id'])
                        ->where('stock_qty', '>=', $item['quantity'])
                        ->decrement('stock_qty', $item['quantity']);

                    if (! $sold) {
                        $name = Collection::whereKey($item['collection_id'])->value('name') ?? 'that item';
                        throw ValidationException::withMessages([
                            'line_items' => "Not enough stock for {$name}. The invoice was not saved.",
                        ]);
                    }

                    Collection::find($item['collection_id'])?->syncSingleVariantStock();
                }
            }

            // Create initial payment record
            if ($initialPayment > 0) {
                $invoice->payments()->create([
                    'amount' => $initialPayment,
                    'method' => $validated['payment_method'] ?? 'cash',
                    'date' => $validated['date'],
                    'notes' => 'Initial payment',
                ]);
            }

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Guard cross-client leakage: every contact_id on a line item must belong
     * to the invoice's client, and every measurement_id to that same contact.
     */
    protected function assertLineItemsBelongToClient(array $validated): void
    {
        $contactIds = Contact::where('client_id', $validated['client_id'])
            ->pluck('id')
            ->all();

        foreach ($validated['line_items'] as $i => $item) {
            $contactId = $item['contact_id'] ?? null;
            $measurementId = $item['measurement_id'] ?? null;

            if ($contactId && !in_array((int) $contactId, $contactIds, true)) {
                throw ValidationException::withMessages([
                    "line_items.{$i}.contact_id" => 'That person does not belong to the selected client.',
                ]);
            }

            if ($measurementId) {
                $belongs = $contactId && Measurement::whereKey($measurementId)
                    ->where('contact_id', $contactId)
                    ->exists();

                if (!$belongs) {
                    throw ValidationException::withMessages([
                        "line_items.{$i}.measurement_id" => 'That measurement does not belong to the selected person.',
                    ]);
                }
            }
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'client',
            'lineItems.contact',
            'lineItems.measurement',
            'lineItems.fabric',
            'lineItems.collection',
            'payments' => fn ($q) => $q->latest('date'),
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,issued,paid,overdue,voided',
            'convert_to_invoice' => 'nullable|boolean',
        ]);

        if (!empty($validated['convert_to_invoice']) && $invoice->type === 'quotation') {
            // Re-load under a row lock inside the transaction and re-check the type,
            // so a concurrent double-submit converts (and burns an INV number) once.
            Invoice::withUniqueNumber(function () use ($invoice, $validated) {
                $fresh = Invoice::whereKey($invoice->getKey())->lockForUpdate()->first();
                if (!$fresh || $fresh->type !== 'quotation') {
                    return; // already converted by an earlier/concurrent request — no-op
                }
                $fresh->update([
                    'type' => 'invoice',
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'status' => $validated['status'],
                ]);
            });
            return redirect()->back()
                ->with('success', 'Quotation converted to invoice.');
        }

        $invoice->update(['status' => $validated['status']]);

        return redirect()->back()
            ->with('success', 'Status updated.');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return redirect()->back()
                ->with('error', 'Cannot delete a paid invoice.');
        }

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load([
            'client',
            'lineItems.contact',
            'lineItems.measurement',
            'lineItems.fabric',
            'lineItems.collection',
            'payments',
        ]);

        $company = CompanySetting::first() ?? new CompanySetting();

        $pdf = Pdf::loadView('pdf.receipt', compact('invoice', 'company'));

        $prefix = $invoice->type === 'quotation' ? 'Quotation' : 'Receipt';
        return $pdf->stream("{$prefix}-{$invoice->invoice_number}.pdf");
    }
}
