<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Fabric;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        // Fabrics with stock info
        $fabricQuery = Fabric::withCount('invoiceLineItems')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $fabricQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('supplier', 'like', "%{$search}%");
            });
        }

        $fabrics = $fabricQuery->get()->map(function ($f) {
            $f->available_qty = max(0, $f->stock_qty - $f->reserved_qty);
            $f->stock_value = $f->stock_qty * $f->price_per_unit;
            $f->is_low = $f->isLowStock();
            return $f;
        });

        // Collections with stock info
        $collectionQuery = Collection::with('category:id,name')
            ->withCount('invoiceLineItems')
            ->orderBy('name');

        if ($search) {
            $collectionQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $collections = $collectionQuery->get()->map(function ($c) {
            $c->stock_value = $c->stock_qty * $c->price;
            $c->is_low = $c->isLowStock();
            return $c;
        });

        // Recent movements
        $movements = StockMovement::with(['invoice:id,invoice_number', 'user:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($m) {
                $m->item_name = $m->movable?->name ?? 'Unknown';
                $m->item_type_label = $m->movable_type === 'App\\Models\\Fabric' ? 'Fabric' : 'Collection';
                return $m;
            });

        // Summary stats
        $totalFabricValue = $fabrics->sum('stock_value');
        $totalCollectionValue = $collections->sum('stock_value');
        $lowStockFabrics = $fabrics->where('is_low', true)->count();
        $lowStockCollections = $collections->where('is_low', true)->count();
        $totalReserved = $fabrics->sum('reserved_qty');

        return Inertia::render('Inventory/Index', [
            'fabrics' => $fabrics,
            'collections' => $collections,
            'movements' => $movements,
            'stats' => [
                'totalFabricValue' => $totalFabricValue,
                'totalCollectionValue' => $totalCollectionValue,
                'totalInventoryValue' => $totalFabricValue + $totalCollectionValue,
                'lowStockFabrics' => $lowStockFabrics,
                'lowStockCollections' => $lowStockCollections,
                'totalReserved' => $totalReserved,
                'fabricCount' => $fabrics->count(),
                'collectionCount' => $collections->count(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                // Open on a tab that actually holds stock. Staff reported the
                // module "shows nothing": it always opened on Fabrics, and this
                // shop keeps no fabrics, while hundreds of items sit under
                // Collections one click away.
                'tab' => $request->input('tab') ?: (
                    $fabrics->isNotEmpty() ? 'fabrics'
                        : ($collections->isNotEmpty() ? 'collections' : 'fabrics')
                ),
            ],
        ]);
    }

    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'item_type' => 'required|in:fabric,collection',
            'item_id' => 'required|integer',
            'type' => 'required|in:purchase,adjustment,return,waste',
            'quantity' => 'required|integer',
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $model = $validated['item_type'] === 'fabric'
            ? Fabric::findOrFail($validated['item_id'])
            : Collection::findOrFail($validated['item_id']);

        // For waste/adjustment out, quantity should be negative
        $qty = $validated['quantity'];
        if (in_array($validated['type'], ['waste']) && $qty > 0) {
            $qty = -$qty;
        }

        $model->increment('stock_qty', $qty);

        StockMovement::record($model, $validated['type'], $qty, [
            'unit_cost' => $validated['unit_cost'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Stock adjusted successfully.');
    }

    public function reserve(Request $request)
    {
        $validated = $request->validate([
            'fabric_id' => 'required|exists:fabrics,id',
            'quantity' => 'required|integer|min:1',
            'invoice_id' => 'nullable|exists:invoices,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $fabric = Fabric::findOrFail($validated['fabric_id']);

        if ($validated['quantity'] > $fabric->available_qty) {
            return redirect()->back()->with('error', 'Not enough available stock to reserve.');
        }

        $fabric->increment('reserved_qty', $validated['quantity']);

        StockMovement::record($fabric, 'reservation', -$validated['quantity'], [
            'invoice_id' => $validated['invoice_id'] ?? null,
            'notes' => $validated['notes'] ?? "Reserved {$validated['quantity']} units",
        ]);

        return redirect()->back()->with('success', "Reserved {$validated['quantity']} units of {$fabric->name}.");
    }

    public function release(Request $request)
    {
        $validated = $request->validate([
            'fabric_id' => 'required|exists:fabrics,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $fabric = Fabric::findOrFail($validated['fabric_id']);

        $releaseQty = min($validated['quantity'], $fabric->reserved_qty);
        $fabric->decrement('reserved_qty', $releaseQty);

        StockMovement::record($fabric, 'release', $releaseQty, [
            'notes' => $validated['notes'] ?? "Released {$releaseQty} units",
        ]);

        return redirect()->back()->with('success', "Released {$releaseQty} units of {$fabric->name}.");
    }

    public function movements(Request $request, string $type, int $id)
    {
        $modelClass = $type === 'fabric' ? Fabric::class : Collection::class;

        $movements = StockMovement::where('movable_type', $modelClass)
            ->where('movable_id', $id)
            ->with(['invoice:id,invoice_number', 'user:id,name'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($movements);
    }
}
