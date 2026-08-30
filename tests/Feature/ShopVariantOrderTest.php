<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionVariant;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ordering a variation from the public website.
 *
 * The shop used to sell the product in general: a customer could not choose a
 * size, the order was billed at the product's price however much the size
 * actually cost, and the units came off whichever variation happened to sort
 * first. These cover the whole path — what the shop offers, what the checkout
 * screen is handed, and what a placed order does to the money and the stock.
 */
class ShopVariantOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Manager', 'Tailor', 'Secretary', 'Cashier'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }

    /**
     * @return array{0: Collection, 1: CollectionVariant, 2: CollectionVariant}
     */
    protected function shopProduct(): array
    {
        $product = Collection::factory()->create([
            'status' => 'active',
            'show_on_shop' => true,
            'price' => 1800,
            'stock_qty' => 0,
        ]);

        $small = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '21.5', 'color' => 'White',
            'stock_qty' => 5, 'status' => 'active', 'sort_order' => 1,
        ]);

        $large = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '22.5', 'color' => 'Gold',
            'price' => 2400,
            'stock_qty' => 3, 'status' => 'active', 'sort_order' => 2,
        ]);

        $product->recalcStockFromVariants();

        return [$product->fresh(), $small, $large];
    }

    public function test_the_shop_offers_each_variation_with_its_own_price_and_stock(): void
    {
        [$product, , $large] = $this->shopProduct();

        $response = $this->get('/shop');
        $response->assertOk();

        $listed = collect($response->viewData('page')['props']['collections'])
            ->firstWhere('id', $product->id);

        $this->assertNotNull($listed, 'the product should reach the shop');
        $this->assertCount(2, $listed['variants'], 'both options should travel with it');

        $dearer = collect($listed['variants'])->firstWhere('id', $large->id);
        $this->assertEquals(2400, $dearer['price'], 'the dearer size must carry its own price');
        $this->assertSame(3, $dearer['stock_qty']);
    }

    public function test_a_sold_out_variation_is_not_offered(): void
    {
        [$product, $small, $large] = $this->shopProduct();
        $large->update(['stock_qty' => 0]);
        $product->recalcStockFromVariants();

        $listed = collect($this->get('/shop')->viewData('page')['props']['collections'])
            ->firstWhere('id', $product->id);

        $ids = collect($listed['variants'])->pluck('id')->all();
        $this->assertContains($small->id, $ids);
        $this->assertNotContains($large->id, $ids, 'a size with none left should not be offered');
    }

    public function test_the_checkout_screen_keeps_two_sizes_apart(): void
    {
        [$product, $small, $large] = $this->shopProduct();

        $response = $this->post('/shop/checkout', [
            'items' => [
                ['id' => $product->id, 'variant_id' => $small->id, 'qty' => 2],
                ['id' => $product->id, 'variant_id' => $large->id, 'qty' => 1],
            ],
        ]);

        $cart = $response->viewData('page')['props']['cartItems'];

        $this->assertCount(2, $cart, 'the same product in two sizes is two lines');
        $this->assertEquals(1800, collect($cart)->firstWhere('variant_id', $small->id)['price']);
        $this->assertEquals(2400, collect($cart)->firstWhere('variant_id', $large->id)['price']);
        $this->assertSame('22.5 · Gold', collect($cart)->firstWhere('variant_id', $large->id)['variant_label']);
    }

    public function test_an_order_is_billed_at_the_variations_price_not_the_products(): void
    {
        [$product, , $large] = $this->shopProduct();

        $this->post('/shop/place-order', [
            'name' => 'Web Customer',
            'phone' => '0700111222',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'quantity' => 1,
                // A tampered price must not decide anything.
                'unit_price' => 10,
            ]],
        ])->assertRedirect();

        $line = Invoice::latest('id')->first()->lineItems()->first();

        $this->assertEquals(2400, $line->unit_price, 'the dearer size must be billed at its own price');
        $this->assertEquals(2400, $line->line_total);
        $this->assertSame($large->id, $line->collection_variant_id);
    }

    public function test_an_order_takes_the_units_off_the_variation_ordered(): void
    {
        [$product, $small, $large] = $this->shopProduct();

        $this->post('/shop/place-order', [
            'name' => 'Web Customer',
            'phone' => '0700111222',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'quantity' => 2,
                'unit_price' => 2400,
            ]],
        ])->assertRedirect();

        $this->assertSame(1, $large->fresh()->stock_qty, 'the size ordered should drop from 3 to 1');
        $this->assertSame(5, $small->fresh()->stock_qty, 'the other size must not move');
        $this->assertSame(6, $product->fresh()->stock_qty);
    }

    public function test_a_web_order_cannot_oversell_one_variation(): void
    {
        [$product, $small, $large] = $this->shopProduct();

        // 8 in total across the product, but only 3 of this size.
        $this->post('/shop/place-order', [
            'name' => 'Web Customer',
            'phone' => '0700111222',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'quantity' => 5,
                'unit_price' => 2400,
            ]],
        ]);

        $this->assertSame(3, $large->fresh()->stock_qty);
        $this->assertSame(5, $small->fresh()->stock_qty);
        $this->assertSame(0, Invoice::count(), 'nothing should have been ordered');
    }

    public function test_a_variation_of_another_product_cannot_be_ordered_against_this_one(): void
    {
        [$product, , $large] = $this->shopProduct();

        $other = Collection::factory()->create(['status' => 'active', 'price' => 100, 'stock_qty' => 0]);
        $foreign = CollectionVariant::create([
            'collection_id' => $other->id,
            'size' => 'M', 'stock_qty' => 9, 'status' => 'active',
        ]);
        $other->recalcStockFromVariants();

        $this->post('/shop/place-order', [
            'name' => 'Web Customer',
            'phone' => '0700111222',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $foreign->id,
                'quantity' => 1,
                'unit_price' => 1800,
            ]],
        ])->assertRedirect();

        $this->assertSame(9, $foreign->fresh()->stock_qty, "another product's stock must be untouchable");
        $this->assertSame(3, $large->fresh()->stock_qty);
    }
}
