<?php

namespace App\Http\Controllers;

use App\Models\Fabric;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FabricController extends Controller
{
    public function index(Request $request)
    {
        $query = Fabric::query()
            ->withCount('invoiceLineItems')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%")
                  ->orWhere('supplier', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->input('low_stock')) {
            $query->where('stock_qty', '<', 10);
        }

        $fabrics = $query->paginate(min((int) $request->input('per_page', 20), 100))
            ->withQueryString();

        return Inertia::render('Fabrics/Index', [
            'fabrics' => $fabrics,
            'filters' => $request->only(['search', 'status', 'low_stock']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'price_per_unit' => 'required|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        Fabric::create($validated);

        return redirect()->back()
            ->with('success', 'Fabric added successfully.');
    }

    public function update(Request $request, Fabric $fabric)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'price_per_unit' => 'required|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'supplier' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|in:active,inactive',
        ]);

        $fabric->update($validated);

        return redirect()->back()
            ->with('success', 'Fabric updated successfully.');
    }

    public function destroy(Fabric $fabric)
    {
        if ($fabric->invoiceLineItems()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete fabric used in invoices.');
        }

        $fabric->delete();

        return redirect()->back()
            ->with('success', 'Fabric deleted successfully.');
    }
}
