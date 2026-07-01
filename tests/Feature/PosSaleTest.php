<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Collection;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Manager', 'Tailor', 'Secretary', 'Cashier'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('Cashier');
    }

    /**
     * A walk-in sale with no client account must succeed — invoices.client_id is now
     * nullable. Previously this threw "1048 Column 'client_id' cannot be null".
     */
    public function test_walk_in_sale_without_a_client_succeeds(): void
    {
        $collection = Collection::factory()->create(['status' => 'active', 'stock_qty' => 5, 'price' => 500]);

        $this->actingAs($this->cashier)->post('/pos', [
            'client_id' => null,
            'walk_in_name' => 'Jane Walk-in',
            'payment_method' => 'cash',
            'items' => [['collection_id' => $collection->id, 'quantity' => 1, 'unit_price' => 500]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', ['invoice_number' => 'POS-00001', 'client_id' => null]);
    }

    /**
     * The cart must not be allowed to oversell into negative stock. The request is
     * rejected with a friendly error and nothing is written.
     */
    public function test_sale_that_would_oversell_is_rejected(): void
    {
        $collection = Collection::factory()->create(['status' => 'active', 'stock_qty' => 2, 'price' => 500]);

        $this->actingAs($this->cashier)->post('/pos', [
            'client_id' => Client::factory()->create()->id,
            'walk_in_name' => null,
            'payment_method' => 'cash',
            'items' => [['collection_id' => $collection->id, 'quantity' => 5, 'unit_price' => 500]],
        ])->assertRedirect()->assertSessionHasErrors('items');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(2, $collection->fresh()->stock_qty, 'stock must be untouched on a rejected sale');
    }

    /**
     * A valid sale decrements stock by the sold quantity (and never goes negative).
     */
    public function test_valid_sale_decrements_stock(): void
    {
        $collection = Collection::factory()->create(['status' => 'active', 'stock_qty' => 10, 'price' => 500]);

        $this->actingAs($this->cashier)->post('/pos', [
            'client_id' => Client::factory()->create()->id,
            'walk_in_name' => null,
            'payment_method' => 'cash',
            'items' => [['collection_id' => $collection->id, 'quantity' => 3, 'unit_price' => 500]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(7, $collection->fresh()->stock_qty);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'POS-00001']);
    }
}
