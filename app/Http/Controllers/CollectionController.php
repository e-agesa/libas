<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\CollectionImage;
use App\Models\CollectionVariant;
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

        // These columns do not accept null; the form sends blank for an unknown
        // cost, which was crashing product creation outright.
        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['stock_qty'] = $validated['stock_qty'] ?? 0;

        $collection = Collection::create($validated);

        // Every product owns at least one variation, so the Variations panel is
        // never empty and product stock always equals the sum of its variations.
        $collection->variants()->create([
            'size' => $collection->size,
            'color' => $collection->color,
            'stock_qty' => $collection->stock_qty,
            'low_stock_threshold' => $collection->low_stock_threshold,
            'status' => $collection->status,
        ]);

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

        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['stock_qty'] = $validated['stock_qty'] ?? $collection->stock_qty;

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
    /**
     * Everything a product's variations and photographs need, in one payload.
     * Loaded on demand so the collections list itself stays light.
     */
    public function variants(Collection $collection)
    {
        $collection->load(['variants', 'images.variant']);

        return response()->json([
            'collection' => $collection->only(['id', 'name', 'sku', 'price', 'stock_qty']),
            'variants' => $collection->variants,
            'images' => $collection->images,
        ]);
    }

    public function storeVariant(Request $request, Collection $collection)
    {
        $validated = $this->validateVariant($request);

        $variant = $collection->variants()->create($validated);
        $this->syncProductStock($collection);

        return response()->json($variant->fresh(), 201);
    }

    public function updateVariant(Request $request, CollectionVariant $variant)
    {
        $validated = $this->validateVariant($request, $variant->id);

        $variant->update($validated);
        $this->syncProductStock($variant->collection);

        return response()->json($variant->fresh());
    }

    public function destroyVariant(CollectionVariant $variant)
    {
        $collection = $variant->collection;
        $variant->delete();
        $this->syncProductStock($collection);

        return response()->json(['ok' => true]);
    }

    protected function validateVariant(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'size' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:100',
            'design' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:255|unique:collection_variants,sku' . ($ignoreId ? ",{$ignoreId}" : ''),
            // Blank price means "same as the product" — most variations match it.
            'price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
            'sort_order' => 'nullable|integer',
        ]);
    }

    /**
     * Selling still runs off the product-level stock figure, so keep it equal to
     * the sum of its variations. Until the tills read variant stock directly,
     * this is what stops the two disagreeing.
     */
    protected function syncProductStock(Collection $collection): void
    {
        if (!$collection) {
            return;
        }
        $collection->update(['stock_qty' => (int) $collection->variants()->sum('stock_qty')]);
    }

    /**
     * Several photographs per product; each may belong to one variation so a
     * design can show its own pictures.
     */
    public function storeImages(Request $request, Collection $collection)
    {
        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|max:5120',
            'collection_variant_id' => 'nullable|exists:collection_variants,id',
        ]);

        $variantId = $request->input('collection_variant_id') ?: null;
        $next = (int) $collection->images()->max('sort_order');
        $created = [];

        foreach ($request->file('images') as $file) {
            $created[] = $collection->images()->create([
                'collection_variant_id' => $variantId,
                'path' => $file->store('collections', 'public'),
                'alt' => $collection->name,
                'is_primary' => !$collection->images()->exists(),
                'sort_order' => ++$next,
            ]);
        }

        return response()->json($created, 201);
    }

    public function destroyImage(CollectionImage $image)
    {
        if ($image->path && !str_starts_with($image->path, 'http')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
        }
        $wasPrimary = $image->is_primary;
        $collection = $image->collection;
        $image->delete();

        // Never leave a product without a lead photograph.
        if ($wasPrimary && $collection) {
            $collection->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return response()->json(['ok' => true]);
    }

    public function setPrimaryImage(CollectionImage $image)
    {
        $image->collection->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return response()->json(['ok' => true]);
    }
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
