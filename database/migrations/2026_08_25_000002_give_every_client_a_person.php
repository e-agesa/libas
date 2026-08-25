<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every client is also a person.
     *
     * Billing requires a Person on each custom line, but creating a client
     * never created one — so the Person list opened empty and staff had to
     * re-enter the customer's own name through "Add New Person" before they
     * could raise a bill. This gives each existing client that has nobody on
     * file a "self" record carrying their own name.
     *
     * Clients who already have people are left completely alone.
     */
    public function up(): void
    {
        $now = now();

        DB::table('clients')
            ->select('clients.id', 'clients.name', 'clients.phone')
            ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
            ->whereNull('contacts.id')
            ->orderBy('clients.id')
            ->chunk(200, function ($clients) use ($now) {
                $rows = [];
                foreach ($clients as $client) {
                    $rows[] = [
                        'client_id' => $client->id,
                        'name' => $client->name,
                        'relationship' => 'self',
                        'phone' => $client->phone,
                        'gender' => 'male',
                        'age_group' => 'adult',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    DB::table('contacts')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // These are real customer records now; deleting them on a rollback
        // would take measurements and invoice history with them.
    }
};
