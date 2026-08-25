<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grandfather existing accounts into the new email-verification flow so
     * they are not locked out of the dashboard after the `verified` middleware
     * is introduced.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update([
                'email_verified_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]);
    }

    public function down(): void
    {
        // No-op — verification status is intentionally not reversible.
    }
};
