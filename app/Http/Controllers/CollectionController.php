<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\CollectionCategory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $query = Collection::with('category:id,name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category_id', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $collections = $query->latest()->paginate(min((int) $request->input('per_page', 15), 100))->withQueryString();
        $categories = CollectionCategory::orderBy('sort_order')->get();

        return Inertia::render('Collections/Index', [
            'collections' => $collections,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category', 'status', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:collections,sku',
            'category_id' => 'nullable|exists:collection_categories,id',
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|image|max:2048',
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('collections', 'public');
        }
        unset($validated['image']);

        Collection::create($validated);

        return redirect()->back()->with('success', 'Collection item added.');
    }

    public function update(Request $request, Collection $collection)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:collections,sku,' . $collection->id,
            'category_id' => 'nullable|exists:collection_categories,id',
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|image|max:2048',
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($collection->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($collection->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('collections', 'public');
        }
        unset($validated['image']);

        $collection->update($validated);

        return redirect()->back()->with('success', 'Collection item updated.');
    }

    public function destroy(Collection $collection)
    {
        $collection->delete();

        return redirect()->back()->with('success', 'Collection item deleted.');
    }

    public function adjustStock(Request $request, Collection $collection)
    {
        $validated = $request->validate([
            'adjustment' => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $newQty = $collection->stock_qty + $validated['adjustment'];
        if ($newQty < 0) {
            return redirect()->back()->with('error', 'Stock cannot go below zero.');
        }

        $collection->update(['stock_qty' => $newQty]);

        StockMovement::record($collection, 'adjustment', $validated['adjustment'], [
            'notes' => $validated['reason'] ?? 'Manual stock adjustment',
        ]);

        return redirect()->back()->with('success', 'Stock adjusted.');
    }

    // Category management
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        CollectionCategory::create($validated);

        return redirect()->back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, CollectionCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroyCategory(CollectionCategory $category)
    {
        $category->delete();

        return redirect()->back()->with('success', 'Category deleted.');
    }
}
