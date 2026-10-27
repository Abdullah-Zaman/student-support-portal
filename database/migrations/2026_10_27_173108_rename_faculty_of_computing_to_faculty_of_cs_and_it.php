<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the seeded faculty (code FOC) in place, so its id and every
 * department/Dean link stay as they are. Only the old seeded names are
 * touched; a name a Super Admin has since changed is left alone.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rename('Faculty of Computing', 'Faculty of CS & IT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename('Faculty of CS & IT', 'Faculty of Computing');
    }

    private function rename(string $from, string $to): void
    {
        DB::table('faculties')
            ->where('code', 'FOC')
            ->where('name', $from)
            ->update(['name' => $to, 'updated_at' => now()]);

        DB::table('users')
            ->where('name', "Dean, {$from}")
            ->update(['name' => "Dean, {$to}", 'updated_at' => now()]);
    }
};
