<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Records the logo where it actually is, and stops the receipt calling
     * itself a piece of software.
     *
     * The logo was uploaded to the public disk as `logos/x.jpg` but recorded as
     * `public/logos/x.jpg`. Storage::url() forgives that prefix, which is why it
     * showed on screen, but exists() does not — so every PDF concluded there was
     * no logo and printed none. The reader now normalises the prefix either way;
     * this straightens the stored value so the two agree.
     *
     * The tagline is cleared only when it is still the seeded default. It prints
     * beneath the shop's name on every customer receipt, and "Tailor Management
     * System" is the name of the software, not of the shop.
     */
    public function up(): void
    {
        $row = DB::table('company_settings')->orderBy('id')->first();

        if (! $row) {
            return;
        }

        $update = [];

        if (! empty($row->logo_path) && str_starts_with($row->logo_path, 'public/')) {
            $update['logo_path'] = ltrim(substr($row->logo_path, 7), '/');
        }

        if (($row->tagline ?? null) === 'Tailor Management System') {
            $update['tagline'] = null;
        }

        if ($update) {
            DB::table('company_settings')->where('id', $row->id)->update($update);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the path now matches the file, and the tagline is
        // an ordinary setting the shop can type back in.
    }
};
