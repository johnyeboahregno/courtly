<?php
// Upload and visit once to create database tables
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

echo "Creating sessions table...<br>";
if (!Schema::hasTable('sessions')) {
    Schema::create('sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
    echo "✅ sessions created<br>";
} else { echo "sessions already exists<br>"; }

echo "Creating cache table...<br>";
if (!Schema::hasTable('cache')) {
    Schema::create('cache', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });
    echo "✅ cache created<br>";
} else { echo "cache already exists<br>"; }

echo "Creating cache_locks table...<br>";
if (!Schema::hasTable('cache_locks')) {
    Schema::create('cache_locks', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->string('owner');
        $table->integer('expiration');
    });
    echo "✅ cache_locks created<br>";
} else { echo "cache_locks already exists<br>"; }

echo "Creating jobs table...<br>";
if (!Schema::hasTable('jobs')) {
    Schema::create('jobs', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    echo "✅ jobs created<br>";
} else { echo "jobs already exists<br>"; }

echo "<br>Adding missing columns...<br>";
try { DB::statement("ALTER TABLE users ADD google_id VARCHAR(255) NULL UNIQUE AFTER email"); echo "✅ google_id<br>"; } catch (\Exception $e) { echo "google_id: ".$e->getMessage()."<br>"; }
try { DB::statement("ALTER TABLE users ADD facebook_id VARCHAR(255) NULL UNIQUE AFTER google_id"); echo "✅ facebook_id<br>"; } catch (\Exception $e) { echo "facebook_id: ".$e->getMessage()."<br>"; }
try { DB::statement("ALTER TABLE session_players ADD consecutive_games INT UNSIGNED DEFAULT 0 AFTER losses"); echo "✅ consecutive_games<br>"; } catch (\Exception $e) { echo "consecutive_games: ".$e->getMessage()."<br>"; }
try { DB::statement("ALTER TABLE session_players ADD last_result VARCHAR(10) NULL AFTER left_at"); echo "✅ last_result<br>"; } catch (\Exception $e) { echo "last_result: ".$e->getMessage()."<br>"; }

echo "<br>Fixing sessions table...<br>";
try { DB::statement("ALTER TABLE sessions MODIFY payload LONGTEXT NULL"); echo "✅ payload nullable<br>"; } catch (\Exception $e) { echo "payload: ".substr($e->getMessage(),0,50)."<br>"; }
try { DB::statement("ALTER TABLE sessions MODIFY last_activity INT NULL DEFAULT NULL"); echo "✅ last_activity nullable<br>"; } catch (\Exception $e) { echo "last_activity: ".substr($e->getMessage(),0,50)."<br>"; }

echo "<br><strong>Done!</strong> Visit the site now.";
