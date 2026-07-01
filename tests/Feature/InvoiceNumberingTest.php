<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

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
}
