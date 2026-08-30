<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrects the shop's telephone country code: the number is Kenyan.
     *
     * It went in as +234 (Nigeria) from the note it was given in. The earlier
     * migration that set it has already run, so it cannot be edited in place —
     * hence a second one. Only the exact wrong value is replaced, so a number
     * someone has since corrected by hand is left alone.
     */
    public function up(): void
    {
        DB::table('company_settings')
            ->where('phone', '+234752716818')
            ->update(['phone' => '+254752716818']);
    }

    public function down(): void
    {
        // Nothing to undo: this is a correction, and the phone number is an
        // ordinary setting the shop can edit.
    }
};
