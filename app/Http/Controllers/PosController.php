<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Collection;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $preloadInvoice = null;
        if ($invoiceId = $request->query('invoice_id')) {
            $preloadInvoice = Invoice::with([
                'client:id,name,phone',
                'lineItems.collection:id,name,price,stock_qty,sku,size,color,image_path',
                'lineItems.contact:id,name',
                'lineItems.measurement:id,garment_type,label',
                'lineItems.fabric:id,name',
            ])->find($invoiceId);
        }

        return Inertia::render('Pos/Index', [
            'collections' => Collection::where('status', 'active')
                ->where('stock_qty', '>', 0)
                ->with('category:id,name')
                ->orderBy('name')
                ->get(),
            'clients' => Client::where('status', 'active')
                ->select('id', 'name', 'phone', 'email')
                ->with('contacts:id,client_id,name')
                ->orderBy('name')
                ->get(),
            'preloadInvoice' => $preloadInvoice,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'walk_in_name' => 'nullable|string|max:255',
            'payment_method' => 'required|in:cash,mpesa,bank_transfer,credit',
            'items' => 'nullable|array',
            'items.*.collection_id' => 'required|exists:collections,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'include_invoices' => 'nullable|array',
            'include_invoices.*' => 'exists:invoices,id',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $items = $validated['items'] ?? [];
        $includeInvoices = $validated['include_invoices'] ?? [];

        // Must have cart items or included invoices
        if (empty($items) && empty($includeInvoices)) {
            return redirect()->back()->withErrors(['items' => 'Add cart items or include pending orders.']);
        }

        $invoice = DB::transaction(function () use ($validated, $items, $includeInvoices) {
            // Generate POS receipt number
            $prefix = 'POS';
            $lastNum = Invoice::where('invoice_number', 'like', $prefix . '-%')
                ->selectRaw("MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)) as last_num")
                ->value('last_num') ?? 0;
            $invoiceNumber = $prefix . '-' . str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'client_id' => $validated['client_id'],
                'invoice_number' => $invoiceNumber,
                'type' => 'invoice',
                'date' => now()->toDateString(),
                'status' => 'paid',
                'payment_method' => $validated['payment_method'],
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => 'flat',
                'tax' => 0,
                'notes' => trim(($validated['walk_in_name'] ? "Walk-in: {$validated['walk_in_name']}\n" : '') . ($validated['notes'] ?? '')),
            ]);

            $subtotal = 0;

            // Process cart items (new sales)
            foreach ($items as $item) {
                $collection = Collection::findOrFail($item['collection_id']);
                $lineTotal = $item['unit_price'] * $item['quantity'];
                $subtotal += $lineTotal;

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'collection',
                    'collection_id' => $item['collection_id'],
                    'description' => $collection->name,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'craftsmanship_fee' => 0,
                    'fabric_cost' => 0,
                    'line_total' => $lineTotal,
                ]);

                // Deduct stock and record movement
                $collection->decrement('stock_qty', $item['quantity']);
                $collection->refresh();

                StockMovement::record($collection, 'sale', -$item['quantity'], [
                    'invoice_id' => $invoice->id,
                    'reference' => $invoiceNumber,
                    'unit_cost' => $item['unit_price'],
                    'notes' => "POS sale: {$collection->name} x{$item['quantity']}",
                ]);
            }

            // Process included pending invoices — mark them as paid
            $includedTotal = 0;
            $includedNotes = [];
            foreach ($includeInvoices as $pendingId) {
                $pending = Invoice::findOrFail($pendingId);
                $balance = $pending->balance ?? ($pending->total - ($pending->amount_paid ?? 0));

                if ($balance <= 0) continue;

                $includedTotal += $balance;
                $includedNotes[] = "{$pending->invoice_number} (KES " . number_format($balance, 2) . ")";

                // Mark the pending invoice as paid
                $pending->update([
                    'status' => 'paid',
                    'amount_paid' => $pending->total,
                    'balance' => 0,
                    'payment_method' => $validated['payment_method'],
                ]);

                // Record payment on the pending invoice
                $pending->payments()->create([
                    'amount' => $balance,
                    'method' => $validated['payment_method'],
                    'date' => now()->toDateString(),
                    'reference' => $invoiceNumber, // Reference to the POS receipt
                ]);

                // Add a line item on the POS receipt referencing the settled order
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'service',
                    'description' => "Settled: {$pending->invoice_number}",
                    'unit_price' => $balance,
                    'quantity' => 1,
                    'craftsmanship_fee' => 0,
                    'fabric_cost' => 0,
                    'line_total' => $balance,
                ]);
            }

            $discount = $validated['discount'] ?? 0;
            $grandSubtotal = $subtotal + $includedTotal;
            $total = $grandSubtotal - $discount;

            // Append settled invoice info to notes
            $notes = $invoice->notes;
            if (!empty($includedNotes)) {
                $notes = trim($notes . "\nSettled orders: " . implode(', ', $includedNotes));
            }

            $invoice->update([
                'subtotal' => $grandSubtotal,
                'total' => $total,
                'amount_paid' => $total,
                'balance' => 0,
                'notes' => $notes,
            ]);

            // Record payment for the POS receipt
            $invoice->payments()->create([
                'amount' => $total,
                'method' => $validated['payment_method'],
                'date' => now()->toDateString(),
                'reference' => $invoiceNumber,
            ]);

            return $invoice;
        });

        return redirect()->back()->with('success', "Sale completed! Receipt: {$invoice->invoice_number}")
            ->with('pos_invoice_id', $invoice->id);
    }

    public function clientOrders(Client $client)
    {
        $orders = Invoice::where('client_id', $client->id)
            ->whereIn('status', ['issued', 'draft'])
            ->with(['lineItems' => function ($q) {
                $q->with('collection:id,name,price', 'measurement:id,garment_type,label', 'contact:id,name');
            }])
            ->select('id', 'client_id', 'invoice_number', 'date', 'total', 'balance', 'status')
            ->latest('date')
            ->limit(10)
            ->get();

        return response()->json($orders);
    }

    public function receipt(Invoice $invoice)
    {
        $invoice->load(['client', 'lineItems.collection', 'payments']);

        $company = \App\Models\CompanySetting::first() ?? new \App\Models\CompanySetting();

        $pdf = Pdf::loadView('pdf.pos-receipt', compact('invoice', 'company'));
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait'); // ~80mm thermal receipt width

        return $pdf->stream("receipt-{$invoice->invoice_number}.pdf");
    }
}
