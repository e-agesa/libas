<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fabric code (AM01, IC9, RT01) was being written on the end of the
     * name, so it could not be searched, sorted or printed on its own. It gets
     * its own column.
     *
     * Existing codes are lifted out of the name where the name plainly ends in
     * one — a short letter-and-digit token such as AM01 — and the name is left
     * with just the fabric. Anything that does not clearly end in a code is
     * left completely alone, name included.
     */
    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id')->index();
        });

        foreach (DB::table('fabrics')->select('id', 'name')->get() as $fabric) {
            $name = trim((string) $fabric->name);

            // e.g. "AL MAS FABRIC AM01" -> code AM01, name "AL MAS FABRIC"
            if (preg_match('/^(.*?)[\s\-]+([A-Za-z]{1,5}\d{1,5})$/', $name, $m)) {
                $base = trim($m[1]);
                if ($base !== '') {
                    DB::table('fabrics')->where('id', $fabric->id)->update([
                        'code' => strtoupper($m[2]),
                        'name' => $base,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Put the code back on the end of the name before dropping the column,
        // so nothing is lost by rolling back.
        foreach (DB::table('fabrics')->whereNotNull('code')->select('id', 'name', 'code')->get() as $fabric) {
            DB::table('fabrics')->where('id', $fabric->id)
                ->update(['name' => trim($fabric->name . ' ' . $fabric->code)]);
        }

        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
