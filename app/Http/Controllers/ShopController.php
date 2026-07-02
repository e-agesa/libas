<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Collection;
use App\Models\CollectionCategory;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Measurement;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShopController extends Controller
{
    private function companyData(): array
    {
        $company = CompanySetting::first() ?? new CompanySetting();
        return [
            'name' => $company->business_name ?? 'Libas TMS',
            'tagline' => $company->tagline ?? 'Tailoring Management System',
            'phone' => $company->whatsapp_number ?: $company->phone,
            'email' => $company->email,
            'address' => $company->address,
            'hero_title' => $company->hero_title ?? 'Quality Tailoring & Ready-Made Garments',
            'hero_subtitle' => $company->hero_subtitle ?? 'Custom-made garments crafted to perfection, plus off-the-shelf items ready to wear.',
            'hero_badge' => $company->hero_badge ?? 'Premium Quality Tailoring',
            'instagram' => $company->instagram,
            'facebook' => $company->facebook,
            'tiktok' => $company->tiktok,
            'about_text' => $company->about_text,
            'logo_url' => $company->logo_url,
        ];
    }

    public function index()
    {
        return Inertia::render('Shop/Index', [
            'collections' => Collection::where('status', 'active')
                ->where('stock_qty', '>', 0)
                ->where('show_on_shop', true)
                ->with('category:id,name')
                ->orderBy('name')
                // Explicit columns: never expose cost_price / low_stock_threshold on the public shop.
                ->get(['id', 'category_id', 'name', 'sku', 'description', 'image_path', 'size', 'color', 'price', 'stock_qty']),
            'categories' => CollectionCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'description']),
            'garmentTypes' => GarmentType::where('is_active', true)
                ->with('fields:id,garment_type_id,name,slug,sort_order')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'color', 'base_price', 'default_fabric_qty']),
            'fabrics' => Fabric::where('status', 'active')
                ->where('stock_qty', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'color', 'price_per_unit', 'stock_qty']),
            'company' => $this->companyData(),
        ]);
    }

    public function checkout(Request $request)
    {
        $cartIds = $request->input('items', []);

        $collections = [];
        if (!empty($cartIds)) {
            $ids = collect($cartIds)->pluck('id');
            $collections = Collection::whereIn('id', $ids)
                ->where('status', 'active')
                ->with('category:id,name')
                ->get()
                ->map(function ($item) use ($cartIds) {
                    $cartItem = collect($cartIds)->firstWhere('id', $item->id);
                    $item->cart_qty = $cartItem ? min($cartItem['qty'], $item->stock_qty) : 1;
                    return $item;
                });
        }

        return Inertia::render('Shop/Checkout', [
            'cartItems' => $collections,
            'garmentTypes' => GarmentType::where('is_active', true)
                ->get(['id', 'name', 'slug', 'color', 'base_price']),
            'fabrics' => Fabric::where('status', 'active')
                ->get(['id', 'name', 'type', 'color', 'price_per_unit']),
            'company' => $this->companyData(),
        ]);
    }

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:500',
            'preferred_date' => 'nullable|date|after_or_equal:today',
            'items' => 'nullable|array',
            'items.*.collection_id' => 'required|exists:collections,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'custom_items' => 'nullable|array',
            'custom_items.*.garment_type_slug' => 'required|string|exists:garment_types,slug',
            'custom_items.*.fabric_id' => 'required|exists:fabrics,id',
            'custom_items.*.fabric_qty' => 'required|numeric|min:0.1',
            'custom_items.*.measurements' => 'required|array',
            'custom_items.*.unit' => 'required|in:cm,inches',
            'custom_items.*.notes' => 'nullable|string|max:500',
        ]);

        $items = $validated['items'] ?? [];
        $customItems = $validated['custom_items'] ?? [];

        if (empty($items) && empty($customItems)) {
            return back()->withErrors(['items' => 'At least one item is required.']);
        }

        $invoice = Invoice::withUniqueNumber(function () use ($validated, $items, $customItems) {
            // Find or create client by phone
            $client = Client::where('phone', $validated['phone'])->first();
            if (!$client) {
                $client = Client::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'type' => 'individual',
                    'status' => 'active',
                ]);
            }

            // Create contact for custom items (reuse if exists)
            $contact = null;
            if (!empty($customItems)) {
                $contact = Contact::where('client_id', $client->id)
                    ->where('name', $validated['name'])
                    ->first();
                if (!$contact) {
                    $contact = Contact::create([
                        'client_id' => $client->id,
                        'name' => $validated['name'],
                        'relationship' => 'self',
                        'phone' => $validated['phone'],
                    ]);
                }
            }

            // Generate order number (atomic, prefix-scoped, race-safe)
            $invoiceNumber = Invoice::nextNumber('WEB', 5);

            $orderNotes = "Online order by {$validated['name']}";
            if ($validated['preferred_date'] ?? null) {
                $orderNotes .= "\nPreferred date: {$validated['preferred_date']}";
            }
            if ($validated['notes'] ?? null) {
                $orderNotes .= "\n{$validated['notes']}";
            }

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'invoice_number' => $invoiceNumber,
                'type' => 'invoice',
                'date' => now()->toDateString(),
                'due_date' => $validated['preferred_date'] ?? null,
                'status' => 'issued',
                'payment_method' => null,
                'discount' => 0,
                'discount_type' => 'flat',
                'tax' => 0,
                'notes' => $orderNotes,
            ]);

            $subtotal = 0;

            // --- Collection (off-the-shelf) items ---
            foreach ($items as $item) {
                $collection = Collection::findOrFail($item['collection_id']);

                if ($collection->stock_qty < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$collection->name}");
                }

                $unitPrice = $collection->price;
                $lineTotal = $unitPrice * $item['quantity'];
                $subtotal += $lineTotal;

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'collection',
                    'collection_id' => $item['collection_id'],
                    'description' => $collection->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'craftsmanship_fee' => 0,
                    'fabric_cost' => 0,
                    'line_total' => $lineTotal,
                ]);

                // Guarded atomic decrement closes the check-then-act race with a
                // concurrent POS/web sale of the same item.
                $sold = Collection::whereKey($collection->id)
                    ->where('stock_qty', '>=', $item['quantity'])
                    ->decrement('stock_qty', $item['quantity']);
                if (! $sold) {
                    throw new \Exception("Insufficient stock for {$collection->name}");
                }
                $collection->refresh();

                StockMovement::record($collection, 'sale', -$item['quantity'], [
                    'invoice_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'unit_cost' => $unitPrice,
                    'notes' => "Web order: {$collection->name} x{$item['quantity']}",
                ]);
            }

            // --- Custom tailored items ---
            foreach ($customItems as $customItem) {
                $garmentType = GarmentType::where('slug', $customItem['garment_type_slug'])->firstOrFail();
                $fabric = Fabric::findOrFail($customItem['fabric_id']);

                $fabricQty = $customItem['fabric_qty'];
                if ($fabric->stock_qty < $fabricQty) {
                    throw new \Exception("Insufficient fabric stock for {$fabric->name}");
                }

                // Create measurement record
                $measurement = Measurement::create([
                    'contact_id' => $contact->id,
                    'garment_type' => $garmentType->slug,
                    'label' => "Web order - {$garmentType->name}",
                    'date_taken' => now()->toDateString(),
                    'unit' => $customItem['unit'],
                    'values' => $customItem['measurements'],
                    'notes' => $customItem['notes'] ?? null,
                ]);

                // Server-side pricing
                $craftsmanshipFee = (float) $garmentType->base_price;
                $fabricCost = (float) $fabric->price_per_unit * $fabricQty;
                $lineTotal = $craftsmanshipFee + $fabricCost;
                $subtotal += $lineTotal;

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'custom',
                    'contact_id' => $contact->id,
                    'measurement_id' => $measurement->id,
                    'fabric_id' => $fabric->id,
                    'description' => "{$garmentType->name} (Custom Tailored)",
                    'unit_price' => $lineTotal,
                    'quantity' => 1,
                    'craftsmanship_fee' => $craftsmanshipFee,
                    'fabric_cost' => $fabricCost,
                    'line_total' => $lineTotal,
                ]);

                // Decrement fabric stock
                $fabric->decrement('stock_qty', $fabricQty);
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'amount_paid' => 0,
                'balance' => $subtotal,
            ]);

            return $invoice;
        });

        return redirect()->route('shop.confirmation', $invoice->id);
    }

    public function confirmation(Invoice $invoice)
    {
        $invoice->load(['client', 'lineItems.collection', 'lineItems.measurement', 'lineItems.fabric']);

        return Inertia::render('Shop/Confirmation', [
            'order' => $invoice,
            'company' => $this->companyData(),
        ]);
    }
}
