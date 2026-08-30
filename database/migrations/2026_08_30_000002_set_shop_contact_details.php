<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Puts the shop's address and telephone on record.
     *
     * The receipt prints them at the foot, and until now the settings row held
     * neither, so every receipt went out with no way to contact the shop. These
     * are ordinary settings — editable afterwards under Settings — so this only
     * fills them in where they are still blank and never overwrites a value
     * somebody has since corrected.
     */
    public function up(): void
    {
        $row = DB::table('company_settings')->orderBy('id')->first();

        if (! $row) {
            return;
        }

        $fill = [];

        if (blank($row->address ?? null)) {
            $fill['address'] = 'First Floor, Our Mall, Karen';
        }

        if (blank($row->phone ?? null)) {
            $fill['phone'] = '+254752716818';
        }

        if ($fill) {
            DB::table('company_settings')->where('id', $row->id)->update($fill);
        }
    }

    public function down(): void
    {
        // Nothing to undo: these are settings the shop can edit freely.
    }
};
