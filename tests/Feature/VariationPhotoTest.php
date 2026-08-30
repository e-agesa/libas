<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionImage;
use App\Models\CollectionVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Photographs, per variation.
 *
 * Two things have to hold. A variation must be able to carry its own picture,
 * so three designs of one product do not all show the same photo. And the
 * product's own picture must survive that: it used to live only in a column
 * while every image operation re-derived that column from the gallery, so the
 * first variation photo uploaded replaced it and deleting that photo again
 * erased it altogether.
 */
class VariationPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        foreach (['Admin', 'Manager', 'Tailor', 'Secretary', 'Cashier'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->staff = User::factory()->create();
        $this->staff->assignRole('Admin');
    }

    protected function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 800);
    }

    /**
     * Create a product through the form, exactly as staff do.
     */
    protected function createProductWithPhoto(string $file = 'product.jpg'): Collection
    {
        $this->actingAs($this->staff)->post('/collections', [
            'name' => 'White Topi',
            'price' => 1800,
            'stock_qty' => 5,
            'status' => 'active',
            'image' => $this->photo($file),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Collection::where('name', 'White Topi')->firstOrFail();
    }

    public function test_the_product_form_photo_is_filed_in_the_gallery(): void
    {
        $product = $this->createProductWithPhoto();

        $this->assertNotNull($product->image_path, 'the product should have a photo');
        $this->assertDatabaseHas('collection_images', [
            'collection_id' => $product->id,
            'collection_variant_id' => null,
            'path' => $product->image_path,
        ]);
    }

    public function test_a_variation_photo_does_not_replace_the_products_own(): void
    {
        $product = $this->createProductWithPhoto();
        $original = $product->image_path;

        $variant = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '21.5', 'color' => 'Navy', 'design' => 'Design 2',
            'stock_qty' => 3, 'status' => 'active',
        ]);

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/images", [
                'images' => [$this->photo('navy.jpg')],
                'collection_variant_id' => $variant->id,
            ])->assertSuccessful();

        $this->assertSame($original, $product->fresh()->image_path,
            "the product's own photo must not be replaced by a variation's");

        $variant->refresh();
        $this->assertNotNull($variant->image_path, 'the variation should now have its own photo');
        $this->assertNotSame($original, $variant->image_path, 'and it should be a different one');
    }

    public function test_deleting_a_variation_photo_does_not_erase_the_products_own(): void
    {
        $product = $this->createProductWithPhoto();
        $original = $product->image_path;

        $variant = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '21.5', 'color' => 'Navy',
            'stock_qty' => 3, 'status' => 'active',
        ]);

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/images", [
                'images' => [$this->photo('navy.jpg')],
                'collection_variant_id' => $variant->id,
            ])->assertSuccessful();

        $uploaded = CollectionImage::where('collection_variant_id', $variant->id)->firstOrFail();

        $this->actingAs($this->staff)
            ->delete("/collection-images/{$uploaded->id}")
            ->assertSuccessful();

        $this->assertSame($original, $product->fresh()->image_path,
            'deleting a variation photo must leave the product looking as it did');
        $this->assertNull($variant->fresh()->image_path,
            'and the variation should fall back to having none of its own');
    }

    public function test_each_variation_keeps_its_own_photo(): void
    {
        $product = $this->createProductWithPhoto();

        $paths = [];

        foreach ([['Navy', 'Design 1'], ['Maroon', 'Design 2'], ['Gold', 'Design 3']] as [$colour, $design]) {
            $variant = CollectionVariant::create([
                'collection_id' => $product->id,
                'size' => '21.5', 'color' => $colour, 'design' => $design,
                'stock_qty' => 2, 'status' => 'active',
            ]);

            $this->actingAs($this->staff)
                ->post("/collections/{$product->id}/images", [
                    'images' => [$this->photo(strtolower($colour) . '.jpg')],
                    'collection_variant_id' => $variant->id,
                ])->assertSuccessful();

            $paths[$colour] = $variant->fresh()->image_path;
        }

        $this->assertCount(3, array_filter($paths), 'every variation should have a photo');
        $this->assertCount(3, array_unique($paths), 'and no two of them the same');
        $this->assertNotContains($product->fresh()->image_path, $paths,
            "nor the product's own");
    }

    public function test_the_uploaded_photo_comes_back_tied_to_its_variation(): void
    {
        $product = $this->createProductWithPhoto();

        $variant = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '22', 'color' => 'Gold',
            'stock_qty' => 1, 'status' => 'active',
        ]);

        // The browser sends multipart, so the id arrives as text. The response
        // has to hand back a number, or the modal files the photo under nothing.
        $response = $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/images", [
                'images' => [$this->photo('gold.jpg')],
                'collection_variant_id' => (string) $variant->id,
            ])->assertSuccessful();

        $returned = $response->json();
        $this->assertSame($variant->id, $returned[0]['collection_variant_id']);
    }

    public function test_replacing_the_product_photo_keeps_the_gallery_pointing_somewhere_real(): void
    {
        $product = $this->createProductWithPhoto();
        $original = $product->image_path;

        $this->actingAs($this->staff)->put("/collections/{$product->id}", [
            'name' => 'White Topi',
            'price' => 1800,
            'stock_qty' => 5,
            'status' => 'active',
            'image' => $this->photo('replacement.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertNotSame($original, $product->image_path, 'the photo should have changed');
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('public')->assertMissing($original);

        $this->assertDatabaseHas('collection_images', [
            'collection_id' => $product->id,
            'collection_variant_id' => null,
            'path' => $product->image_path,
        ]);
        $this->assertDatabaseMissing('collection_images', ['path' => $original]);
    }

    public function test_a_variation_without_a_photo_reports_none_of_its_own(): void
    {
        $product = $this->createProductWithPhoto();

        $variant = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '21.5', 'color' => 'White',
            'stock_qty' => 2, 'status' => 'active',
        ]);

        // Loaded the way the till loads them — off the product, so the parent
        // relation is not attached to each variation.
        $listed = Collection::with('variants')->find($product->id)->variants->first();

        $this->assertNull($listed->image_url,
            'so the screen can fall back to the product photo it already holds');
    }
}
