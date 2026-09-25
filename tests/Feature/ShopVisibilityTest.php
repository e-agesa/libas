<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Whether a product reaches the website at all.
 *
 * show_on_shop defaults to false and the product form never set it, so every
 * product added through the back office was invisible to customers until
 * somebody found the per-product toggle in Settings. Whole categories were
 * missing from the shop — 35 entered, 19 showing.
 */
class ShopVisibilityTest extends TestCase
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

    protected function addProduct(array $extra = []): Collection
    {
        $this->actingAs($this->staff)->post('/collections', array_merge([
            'name' => 'Jhabla Izaar',
            'price' => 2500,
            'stock_qty' => 4,
            'status' => 'active',
        ], $extra))->assertRedirect()->assertSessionHasNoErrors();

        return Collection::where('name', 'Jhabla Izaar')->latest('id')->firstOrFail();
    }

    public function test_a_new_product_reaches_the_website(): void
    {
        $product = $this->addProduct();

        $this->assertTrue($product->show_on_shop, 'a product added in the back office should be visible to customers');

        $listed = collect($this->get('/shop')->viewData('page')['props']['collections']);
        $this->assertTrue($listed->contains('id', $product->id), 'and it should actually appear on the shop');
    }

    public function test_a_product_can_be_kept_off_the_website_on_purpose(): void
    {
        $product = $this->addProduct(['show_on_shop' => false]);

        $this->assertFalse($product->show_on_shop);

        $listed = collect($this->get('/shop')->viewData('page')['props']['collections']);
        $this->assertFalse($listed->contains('id', $product->id));
    }

    public function test_editing_a_product_can_take_it_off_the_website_and_put_it_back(): void
    {
        $product = $this->addProduct();

        $edit = fn (bool $show) => $this->actingAs($this->staff)->put("/collections/{$product->id}", [
            'name' => $product->name,
            'price' => 2500,
            'stock_qty' => 4,
            'status' => 'active',
            'show_on_shop' => $show,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $edit(false);
        $this->assertFalse($product->fresh()->show_on_shop);

        $edit(true);
        $this->assertTrue($product->fresh()->show_on_shop);
    }

    public function test_an_edit_that_says_nothing_about_the_website_leaves_it_alone(): void
    {
        $product = $this->addProduct(['show_on_shop' => false]);

        // A caller that never mentions the field — the value must survive.
        $this->actingAs($this->staff)->put("/collections/{$product->id}", [
            'name' => $product->name,
            'price' => 2600,
            'stock_qty' => 4,
            'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->show_on_shop, 'visibility should not change when the form did not send it');
    }

    public function test_a_product_whose_stock_lives_on_its_variations_still_reaches_the_website(): void
    {
        $product = Collection::factory()->create([
            'status' => 'active', 'show_on_shop' => true, 'price' => 1800, 'stock_qty' => 0,
        ]);

        CollectionVariant::create([
            'collection_id' => $product->id, 'size' => '21.5',
            'stock_qty' => 3, 'status' => 'active',
        ]);

        // Stock stranded on the variation: the product reads nought and so is
        // listed nowhere, which is what the roll-up repair exists to undo.
        $listed = collect($this->get('/shop')->viewData('page')['props']['collections']);
        $this->assertFalse($listed->contains('id', $product->id), 'a stale roll-up hides the product');

        $product->recalcStockFromVariants();

        $listed = collect($this->get('/shop')->viewData('page')['props']['collections']);
        $this->assertTrue($listed->contains('id', $product->id), 'recomputing the roll-up brings it back');
    }
}
