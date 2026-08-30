<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\CollectionImage;
use App\Models\CollectionVariant;
use App\Support\ProductImage;
use App\Models\CollectionCategory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $query = Collection::with('category:id,name')->withCount('variants');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  // A size or colour only exists on the variations, so without
                  // this, searching "21.5" finds nothing at all.
                  ->orWhereHas('variants', function ($v) use ($search) {
                      $v->where('size', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%")
                        ->orWhere('design', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
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
            'image' => 'nullable|image|max:12288',   // 12 MB; larger photos are shrunk on upload
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ], [
            'image.max' => 'That photo is too large (the limit is 12 MB). Please send a smaller one.',
            'image.image' => 'That file is not a picture the system can read. Use JPG, PNG or WEBP.',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = ProductImage::store($request->file('image'));
        }
        unset($validated['image']);

        // These columns do not accept null; the form sends blank for an unknown
        // cost, which was crashing product creation outright.
        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['stock_qty'] = $validated['stock_qty'] ?? 0;

        $collection = Collection::create($validated);

        // The photo from this form has to become a gallery row too. Every image
        // operation re-derives image_path from the gallery, so a photo that
        // exists only in the column is overwritten by the first variation photo
        // uploaded and erased entirely when that one is deleted.
        if (! empty($validated['image_path'])) {
            $this->rememberProductPhoto($collection, $validated['image_path']);
        }

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
            'image' => 'nullable|image|max:12288',   // 12 MB; larger photos are shrunk on upload
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ], [
            'image.max' => 'That photo is too large (the limit is 12 MB). Please send a smaller one.',
            'image.image' => 'That file is not a picture the system can read. Use JPG, PNG or WEBP.',
        ]);

        $replacedPath = null;

        if ($request->hasFile('image')) {
            // Store the new photo BEFORE parting with the old one, so a failed
            // upload cannot leave the product with no picture at all.
            $validated['image_path'] = ProductImage::store($request->file('image'));
            $replacedPath = $collection->image_path;
        }
        unset($validated['image']);

        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['stock_qty'] = $validated['stock_qty'] ?? $collection->stock_qty;

        $collection->update($validated);

        if ($request->hasFile('image')) {
            $this->rememberProductPhoto($collection, $validated['image_path'], $replacedPath);
        }

        return redirect()->back()->with('success', 'Collection item updated.');
    }

    /**
     * File the product form's own photo in the gallery, as the product's
     * headline picture.
     *
     * The gallery is what every image operation re-derives image_path from, so
     * a photo that never gets a row here is not really the product's photo —
     * it is a value waiting to be overwritten. The old file is deleted only
     * once nothing points at it any more, which is why the replacement is
     * stored first and the old path passed in.
     */
    protected function rememberProductPhoto(Collection $collection, string $path, ?string $replacedPath = null): void
    {
        $row = $collection->images()->whereNull('collection_variant_id')->orderBy('sort_order')->first();

        if ($row) {
            $row->update(['path' => $path, 'alt' => $collection->name]);
        } else {
            $collection->images()->create([
                'collection_variant_id' => null,
                'path' => $path,
                'alt' => $collection->name,
                // Only claim the headline slot if no variation already holds it.
                'is_primary' => ! $collection->images()->where('is_primary', true)->exists(),
                'sort_order' => 0,
            ]);
        }

        $collection->syncImagePathFromGallery();

        // Now, and only now, is the old file certainly unreferenced.
        if ($replacedPath
            && $replacedPath !== $path
            && ! str_starts_with($replacedPath, 'http')
            && ! CollectionImage::where('path', $replacedPath)->exists()) {
            Storage::disk('public')->delete($replacedPath);
        }
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
        $collection->syncSingleVariantStock();

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

        // The photographs belonged to this variation, not to the product. The
        // foreign key only nulls the link, so without this a deleted Navy
        // variation leaves its navy photo behind as a photo of the product —
        // and, if it happened to be the primary, as the product's headline.
        foreach ($variant->images()->get() as $image) {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('public')->delete($image->path);
            }
            $image->delete();
        }

        $variant->delete();
        $this->syncProductStock($collection);

        if ($collection) {
            // Never leave the product with no lead photograph.
            if (! $collection->images()->where('is_primary', true)->exists()) {
                $collection->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }
            $collection->syncImagePathFromGallery();
        }

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
     * The tills take stock off the variation sold, but every listing and report
     * still reads the product-level figure, so keep it equal to the sum of its
     * variations. This is what stops the two disagreeing.
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
            'images.*' => 'image|max:12288',
            'collection_variant_id' => 'nullable|exists:collection_variants,id',
        ], [
            'images.*.max' => 'One of those photos is too large (the limit is 12 MB each). Please send a smaller one.',
            'images.*.image' => 'One of those files is not a picture the system can read. Use JPG, PNG or WEBP.',
            'images.max' => 'Please upload up to 10 photos at a time.',
        ]);

        $variantId = $request->input('collection_variant_id') ?: null;
        $next = (int) $collection->images()->max('sort_order');
        $created = [];

        foreach ($request->file('images') as $file) {
            $created[] = $collection->images()->create([
                'collection_variant_id' => $variantId,
                'path' => ProductImage::store($file),
                'alt' => $collection->name,
                'is_primary' => !$collection->images()->exists(),
                'sort_order' => ++$next,
            ]);
        }

        $collection->syncImagePathFromGallery();
        if ($variantId) {
            CollectionVariant::find($variantId)?->syncImagePathFromGallery();
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
        $variant = $image->variant;
        $image->delete();

        // Never leave a product without a lead photograph.
        if ($wasPrimary && $collection) {
            $collection->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        $collection?->syncImagePathFromGallery();
        $variant?->syncImagePathFromGallery();

        return response()->json(['ok' => true]);
    }

    public function setPrimaryImage(CollectionImage $image)
    {
        // Exclude the row being promoted from the clear. Clearing it first and
        // setting it again through the stale route-bound model let Eloquent
        // decide nothing had changed and skip the write — leaving the product
        // with no primary photograph at all.
        $image->collection->images()->whereKeyNot($image->getKey())->update(['is_primary' => false]);
        $image->forceFill(['is_primary' => true])->save();
        $image->collection->syncImagePathFromGallery();
        $image->variant?->syncImagePathFromGallery();

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
