<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable()->after('location_label');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
            $table->string('geo_ip', 45)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geo_ip']);
        });
    }
};
