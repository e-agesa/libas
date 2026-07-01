<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Collection;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Manager', 'Tailor', 'Secretary', 'Cashier'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Admin satisfies both the `auth` guard (invoices) and the POS role list.
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function makeInvoice(string $number, string $type = 'invoice'): Invoice
    {
        return Invoice::create([
            'client_id' => Client::firstOrCreate(['name' => 'Test'], ['phone' => '0700000000'])->id,
            'invoice_number' => $number,
            'type' => $type,
            'date' => '2026-07-01',
            'status' => 'issued',
        ]);
    }

    /**
     * The production bug: POS/WEB sales are stored as type='invoice', so the old
     * generator (latest('id') scoped by type) picked up a POS row and recomputed
     * an already-used INV number. nextNumber() must scope by prefix instead.
     */
    public function test_pos_and_web_rows_do_not_pollute_the_invoice_counter(): void
    {
        $this->makeInvoice('INV-0001');
        $this->makeInvoice('INV-0002');
        $this->makeInvoice('INV-0003');

        // POS/WEB sales created AFTER — these become the newest rows by id.
        $this->makeInvoice('POS-00001');
        $this->makeInvoice('WEB-00001');

        // Old code returned INV-0002 (POS/WEB suffix 1 + 1) → duplicate-key crash.
        $this->assertSame('INV-0004', Invoice::generateInvoiceNumber());
        $this->assertSame('POS-00002', Invoice::nextNumber('POS', 5));
        $this->assertSame('WEB-00002', Invoice::nextNumber('WEB', 5));
    }

    /**
     * Next number must come from MAX(suffix), not the newest row by id, so an
     * out-of-order insert (seed/import/manual edit) can never regenerate an
     * existing number.
     */
    public function test_next_number_uses_max_suffix_not_latest_id(): void
    {
        $this->makeInvoice('INV-0050');
        $this->makeInvoice('INV-0002'); // higher id, lower number

        $this->assertSame('INV-0051', Invoice::generateInvoiceNumber());
    }

    public function test_counters_are_independent_per_prefix(): void
    {
        $this->makeInvoice('QUO-0007', 'quotation');
        $this->makeInvoice('INV-0001');

        $this->assertSame('QUO-0008', Invoice::generateQuoteNumber());
        $this->assertSame('INV-0002', Invoice::generateInvoiceNumber());
    }

    public function test_first_number_for_an_empty_prefix(): void
    {
        $this->assertSame('INV-0001', Invoice::generateInvoiceNumber());
        $this->assertSame('POS-00001', Invoice::nextNumber('POS', 5));
    }

    /**
     * withUniqueNumber() must survive a duplicate-key collision (the empty-prefix
     * first-insert race) by rolling back and re-deriving the next number, instead
     * of surfacing the 1062 as a 500.
     */
    public function test_with_unique_number_retries_past_a_collision_and_self_heals(): void
    {
        $this->makeInvoice('INV-0001'); // the number the first attempt will collide with

        $attempts = 0;
        $invoice = Invoice::withUniqueNumber(function () use (&$attempts) {
            $attempts++;
            // First attempt forces a duplicate; retries use the real generator.
            $number = $attempts === 1 ? 'INV-0001' : Invoice::generateInvoiceNumber();

            return Invoice::create([
                'client_id' => Client::firstOrCreate(['name' => 'Test'], ['phone' => '0700000000'])->id,
                'invoice_number' => $number,
                'type' => 'invoice',
                'date' => '2026-07-01',
                'status' => 'issued',
            ]);
        });

        $this->assertSame(2, $attempts, 'should retry exactly once after the collision');
        $this->assertSame('INV-0002', $invoice->invoice_number);
        $this->assertSame(2, Invoice::where('invoice_number', 'like', 'INV-%')->count());
    }

    /**
     * End-to-end: the invoices.store route mints sequential, prefix-scoped INV
     * numbers. Pins the controller to nextNumber() so a refactor that re-inlines
     * a latest('id')/MAX generator fails CI instead of shipping green.
     */
    public function test_invoice_store_route_mints_sequential_numbers(): void
    {
        $client = Client::factory()->create();

        $payload = fn () => [
            'client_id' => $client->id,
            'date' => '2026-07-01',
            'due_date' => null,
            'payment_method' => null,
            'notes' => null,
            'line_items' => [
                ['item_type' => 'custom', 'quantity' => 1, 'craftsmanship_fee' => 1000, 'fabric_cost' => 0],
            ],
        ];

        $this->actingAs($this->admin)->post('/invoices', $payload())->assertRedirect();
        $this->actingAs($this->admin)->post('/invoices', $payload())->assertRedirect();

        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-0001']);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-0002']);
    }

    /**
     * End-to-end regression for the production bug: POS (POS-) and web-shop (WEB-)
     * sales are stored with type='invoice', but must NOT advance the INV- counter.
     * A custom invoice created afterwards must still start at INV-0001.
     */
    public function test_pos_and_web_sales_do_not_pollute_the_invoice_counter_end_to_end(): void
    {
        $collection = Collection::factory()->create([
            'status' => 'active', 'stock_qty' => 20, 'price' => 500,
        ]);

        $this->actingAs($this->admin)->post('/pos', [
            'client_id' => Client::factory()->create()->id,
            'walk_in_name' => null,
            'payment_method' => 'cash',
            'items' => [['collection_id' => $collection->id, 'quantity' => 1, 'unit_price' => 500]],
        ])->assertRedirect();

        $this->post('/shop/place-order', [
            'name' => 'Web Customer',
            'email' => null,
            'phone' => '0700111222',
            'items' => [['collection_id' => $collection->id, 'quantity' => 1, 'unit_price' => 500]],
        ])->assertRedirect();

        $this->assertDatabaseHas('invoices', ['invoice_number' => 'POS-00001']);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'WEB-00001']);

        // The next custom invoice must open its own INV- sequence, not INV-0002.
        $client = Client::factory()->create();
        $this->actingAs($this->admin)->post('/invoices', [
            'client_id' => $client->id,
            'date' => '2026-07-01',
            'due_date' => null,
            'payment_method' => null,
            'notes' => null,
            'line_items' => [
                ['item_type' => 'custom', 'quantity' => 1, 'craftsmanship_fee' => 1000, 'fabric_cost' => 0],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-0001']);
    }

    /**
     * End-to-end: converting a quotation mints a fresh, non-colliding INV number.
     */
    public function test_quotation_convert_route_mints_a_non_colliding_invoice_number(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin)->post('/invoices', [
            'type' => 'quotation',
            'client_id' => $client->id,
            'date' => '2026-07-01',
            'due_date' => null,
            'payment_method' => null,
            'notes' => null,
            'line_items' => [
                ['item_type' => 'custom', 'quantity' => 1, 'craftsmanship_fee' => 1000, 'fabric_cost' => 0],
            ],
        ])->assertRedirect();

        $quote = Invoice::where('type', 'quotation')->firstOrFail();
        $this->assertSame('QUO-0001', $quote->invoice_number);

        $this->actingAs($this->admin)->put("/invoices/{$quote->id}", [
            'status' => 'issued',
            'convert_to_invoice' => true,
        ])->assertRedirect();

        $quote->refresh();
        $this->assertSame('invoice', $quote->type);
        $this->assertSame('INV-0001', $quote->invoice_number);
    }
}
