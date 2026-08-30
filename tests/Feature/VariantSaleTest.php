<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Collection;
use App\Models\CollectionVariant;
use App\Models\Invoice;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Selling a variation.
 *
 * A product with sizes, colours or designs does not hold its stock on the
 * product row — each variation holds its own, and a variation may cost more
 * than the product it belongs to. These cover the whole path: the till and the
 * invoice must charge the variation's price, take the units off that variation
 * and no other, refuse to oversell it even while the product as a whole still
 * shows stock, and put the units back on the same variation when the sale is
 * cancelled.
 */
class VariantSaleTest extends TestCase
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
     * A product and its variations: 21.5 at the product's price, 22.0 dearer.
     *
     * @return array{0: Collection, 1: CollectionVariant, 2: CollectionVariant}
     */
    protected function productWithVariants(): array
    {
        $product = Collection::factory()->create([
            'status' => 'active',
            'price' => 500,
            'stock_qty' => 0,
        ]);

        $small = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '21.5', 'color' => 'White',
            'stock_qty' => 4, 'status' => 'active', 'sort_order' => 1,
        ]);

        $large = CollectionVariant::create([
            'collection_id' => $product->id,
            'size' => '22.0', 'color' => 'White', 'design' => 'Design 2',
            'price' => 750,
            'stock_qty' => 3, 'status' => 'active', 'sort_order' => 2,
        ]);

        $product->recalcStockFromVariants();

        return [$product->fresh(), $small, $large];
    }

    public function test_till_takes_stock_off_the_variation_sold_and_leaves_the_others_alone(): void
    {
        [$product, $small, $large] = $this->productWithVariants();

        $this->actingAs($this->staff)->post('/pos', [
            'client_id' => null,
            'walk_in_name' => 'Jane Walk-in',
            'payment_method' => 'cash',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'quantity' => 2,
                'unit_price' => 750,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $large->fresh()->stock_qty, 'the variation sold should drop from 3 to 1');
        $this->assertSame(4, $small->fresh()->stock_qty, 'the other variation must not move');
        $this->assertSame(5, $product->fresh()->stock_qty, 'the product roll-up should follow its variations');
    }

    public function test_the_sale_records_which_variation_left_the_shelf(): void
    {
        [$product, , $large] = $this->productWithVariants();

        $this->actingAs($this->staff)->post('/pos', [
            'payment_method' => 'cash',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'quantity' => 1,
                'unit_price' => 750,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $line = Invoice::latest('id')->first()->lineItems()->first();
        $this->assertSame($large->id, $line->collection_variant_id);
        $this->assertStringContainsString('22.0 · White · Design 2', $line->description);

        $movement = StockMovement::where('type', 'sale')->latest('id')->first();
        $this->assertSame(-1, (int) $movement->quantity);
        $this->assertStringContainsString('22.0 · White · Design 2', $movement->notes);
    }

    public function test_a_variation_cannot_be_oversold_even_while_the_product_still_shows_stock(): void
    {
        [$product, $small, $large] = $this->productWithVariants();

        // The product has 7 in total, but only 3 of this variation.
        $this->actingAs($this->staff)->post('/pos', [
            'payment_method' => 'cash',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'quantity' => 5,
                'unit_price' => 750,
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(3, $large->fresh()->stock_qty);
        $this->assertSame(4, $small->fresh()->stock_qty);
        $this->assertSame(0, Invoice::count(), 'nothing should have been billed');
    }

    public function test_a_variation_from_another_product_is_ignored_rather_than_drained(): void
    {
        [$product, , $large] = $this->productWithVariants();

        $other = Collection::factory()->create(['status' => 'active', 'price' => 100, 'stock_qty' => 0]);
        $foreign = CollectionVariant::create([
            'collection_id' => $other->id,
            'size' => 'M', 'stock_qty' => 9, 'status' => 'active',
        ]);
        $other->recalcStockFromVariants();

        $this->actingAs($this->staff)->post('/pos', [
            'payment_method' => 'cash',
            'items' => [[
                'collection_id' => $product->id,
                'collection_variant_id' => $foreign->id,
                'quantity' => 1,
                'unit_price' => 500,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(9, $foreign->fresh()->stock_qty, 'another product\'s stock must be untouchable');
        $this->assertSame(3, $large->fresh()->stock_qty);
    }

    public function test_an_invoice_bills_the_variation_and_deducts_its_stock(): void
    {
        [$product, $small, $large] = $this->productWithVariants();
        $client = Client::factory()->create(['status' => 'active']);

        $this->actingAs($this->staff)->post('/invoices', [
            'client_id' => $client->id,
            'type' => 'invoice',
            'date' => now()->toDateString(),
            'line_items' => [[
                'item_type' => 'collection',
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'description' => 'Topi — 22.0 · White · Design 2',
                'unit_price' => 750,
                'quantity' => 2,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $large->fresh()->stock_qty);
        $this->assertSame(4, $small->fresh()->stock_qty);
        $this->assertSame(5, $product->fresh()->stock_qty);
    }

    public function test_voiding_an_invoice_puts_the_units_back_on_the_same_variation(): void
    {
        [$product, $small, $large] = $this->productWithVariants();
        $client = Client::factory()->create(['status' => 'active']);

        $this->actingAs($this->staff)->post('/invoices', [
            'client_id' => $client->id,
            'type' => 'invoice',
            'date' => now()->toDateString(),
            'line_items' => [[
                'item_type' => 'collection',
                'collection_id' => $product->id,
                'collection_variant_id' => $large->id,
                'description' => 'Topi — 22.0 · White · Design 2',
                'unit_price' => 750,
                'quantity' => 2,
            ]],
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::latest('id')->first();

        $this->actingAs($this->staff)
            ->put("/invoices/{$invoice->id}", ['status' => 'voided'])
            ->assertRedirect();

        $this->assertSame(3, $large->fresh()->stock_qty, 'the variation should be whole again');
        $this->assertSame(4, $small->fresh()->stock_qty);
        $this->assertSame(7, $product->fresh()->stock_qty);

        // Voiding then deleting must not return the same units twice.
        $this->actingAs($this->staff)->delete("/invoices/{$invoice->id}");
        $this->assertSame(3, $large->fresh()->stock_qty);
    }

    public function test_a_product_without_variations_still_sells_the_old_way(): void
    {
        $plain = Collection::factory()->create(['status' => 'active', 'stock_qty' => 5, 'price' => 100]);

        $this->actingAs($this->staff)->post('/pos', [
            'payment_method' => 'cash',
            'items' => [['collection_id' => $plain->id, 'quantity' => 2, 'unit_price' => 100]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $plain->fresh()->stock_qty);
    }

    public function test_the_till_offers_variations_with_their_own_price_and_stock(): void
    {
        [$product, , $large] = $this->productWithVariants();

        $response = $this->actingAs($this->staff)->get('/pos');
        $response->assertOk();

        $collections = $response->viewData('page')['props']['collections'];
        $listed = collect($collections)->firstWhere('id', $product->id);

        $this->assertNotNull($listed, 'the product should reach the till');
        $this->assertCount(2, $listed['variants'], 'both variations should travel with it');

        $dearer = collect($listed['variants'])->firstWhere('id', $large->id);
        $this->assertEquals(750, $dearer['effective_price'], 'a dearer variation must carry its own price');
        $this->assertSame(3, $dearer['stock_qty']);
    }
}
