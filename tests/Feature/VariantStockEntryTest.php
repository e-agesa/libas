<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Getting stock INTO a product that is sold by size.
 *
 * Such a product holds no stock of its own — its figure is only the sum of its
 * variations, recomputed on every sale. Two screens ignored that and wrote to
 * the product row directly, which was worse than useless: a delivery of ten
 * landed where nothing could sell it and the next sale erased it, and saving
 * the edit form with a stock of nought made the whole product vanish from sale
 * while every size underneath it was still full.
 */
class VariantStockEntryTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Manager', 'Tailor', 'Secretary', 'Cashier'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->staff = User::factory()->create();
        $this->staff->assignRole('Admin');
    }

    /**
     * @return array{0: Collection, 1: CollectionVariant, 2: CollectionVariant}
     */
    protected function productSoldBySize(): array
    {
        $product = Collection::factory()->create([
            'status' => 'active', 'price' => 1800, 'stock_qty' => 0,
        ]);

        $a = CollectionVariant::create([
            'collection_id' => $product->id, 'size' => '21.5',
            'stock_qty' => 4, 'status' => 'active', 'sort_order' => 1,
        ]);
        $b = CollectionVariant::create([
            'collection_id' => $product->id, 'size' => '22.5',
            'stock_qty' => 3, 'status' => 'active', 'sort_order' => 2,
        ]);

        $product->recalcStockFromVariants();

        return [$product->fresh(), $a, $b];
    }

    public function test_stock_added_to_a_variation_lands_on_that_variation(): void
    {
        [$product, $a, $b] = $this->productSoldBySize();

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/adjust-stock", [
                'adjustment' => 10,
                'reason' => 'New shipment',
                'collection_variant_id' => $a->id,
            ])->assertRedirect();

        $this->assertSame(14, $a->fresh()->stock_qty, 'the size restocked should hold the units');
        $this->assertSame(3, $b->fresh()->stock_qty, 'the other size must not move');
        $this->assertSame(17, $product->fresh()->stock_qty, 'and the product total should follow');
    }

    public function test_a_delivery_that_names_no_variation_is_refused_rather_than_lost(): void
    {
        [$product, $a, $b] = $this->productSoldBySize();

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/adjust-stock", [
                'adjustment' => 10,
                'reason' => 'New shipment',
            ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(4, $a->fresh()->stock_qty);
        $this->assertSame(3, $b->fresh()->stock_qty);
        $this->assertSame(7, $product->fresh()->stock_qty,
            'the units must not be parked on the product where nothing can sell them');
    }

    public function test_a_product_with_one_variation_still_takes_stock_the_simple_way(): void
    {
        $product = Collection::factory()->create(['status' => 'active', 'price' => 500, 'stock_qty' => 2]);
        $only = CollectionVariant::create([
            'collection_id' => $product->id, 'stock_qty' => 2, 'status' => 'active',
        ]);

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/adjust-stock", ['adjustment' => 5])
            ->assertRedirect();

        $this->assertSame(7, $only->fresh()->stock_qty);
        $this->assertSame(7, $product->fresh()->stock_qty);
    }

    public function test_stock_cannot_be_driven_below_zero_on_a_variation(): void
    {
        [$product, $a, ] = $this->productSoldBySize();

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/adjust-stock", [
                'adjustment' => -10,
                'collection_variant_id' => $a->id,
            ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(4, $a->fresh()->stock_qty);
    }

    public function test_a_variation_of_another_product_cannot_be_restocked_through_this_one(): void
    {
        [$product, , ] = $this->productSoldBySize();

        $other = Collection::factory()->create(['status' => 'active', 'price' => 100, 'stock_qty' => 0]);
        $foreign = CollectionVariant::create([
            'collection_id' => $other->id, 'size' => 'M', 'stock_qty' => 9, 'status' => 'active',
        ]);

        $this->actingAs($this->staff)
            ->post("/collections/{$product->id}/adjust-stock", [
                'adjustment' => 10,
                'collection_variant_id' => $foreign->id,
            ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(9, $foreign->fresh()->stock_qty);
    }

    public function test_saving_the_product_form_cannot_wipe_out_a_products_stock(): void
    {
        [$product, $a, $b] = $this->productSoldBySize();

        // Exactly what happens when someone edits the price and saves: the form
        // posts its Stock Quantity box, which for this product means nothing.
        $this->actingAs($this->staff)->put("/collections/{$product->id}", [
            'name' => $product->name,
            'price' => 1900,
            'stock_qty' => 0,
            'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame(7, $product->stock_qty,
            'the roll-up must stay equal to its variations, or the product disappears from sale');
        $this->assertSame(4, $a->fresh()->stock_qty);
        $this->assertSame(3, $b->fresh()->stock_qty);
        $this->assertEquals(1900, $product->price, 'the edit itself should still have applied');
    }

    public function test_the_inventory_screen_refuses_rather_than_losing_the_delivery(): void
    {
        [$product, $a, $b] = $this->productSoldBySize();

        $this->actingAs($this->staff)->post('/inventory/adjust', [
            'item_type' => 'collection',
            'item_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 10,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(4, $a->fresh()->stock_qty);
        $this->assertSame(3, $b->fresh()->stock_qty);
        $this->assertSame(7, $product->fresh()->stock_qty);
    }
}
