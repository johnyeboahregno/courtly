<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matchmaking cost totals can exceed the decimal(5,2) ceiling (999.99)
     * once rotation/relationship penalties are summed, so widen the three
     * summed cost columns.
     */
    public function up(): void
    {
        Schema::table('matchmaking_logs', function (Blueprint $table) {
            $table->decimal('group_cost', 10, 2)->default(0)->change();
            $table->decimal('pairing_cost', 10, 2)->default(0)->change();
            $table->decimal('total_cost', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('matchmaking_logs', function (Blueprint $table) {
            $table->decimal('group_cost', 5, 2)->default(0)->change();
            $table->decimal('pairing_cost', 5, 2)->default(0)->change();
            $table->decimal('total_cost', 5, 2)->default(0)->change();
        });
    }
};
