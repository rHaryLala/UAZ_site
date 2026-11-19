<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lieu approximatif déduit de l'adresse IP (qui, elle, n'est jamais conservée), type d'appareil et navigateur (un mot chacun).
        Schema::table('site_visits', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('referrer');
            $table->string('country', 80)->nullable()->after('country_code');
            $table->string('region', 100)->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('region');
            $table->string('device', 10)->nullable()->after('city');
            $table->string('browser', 20)->nullable()->after('device');

            $table->index(['visited_on', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropIndex(['visited_on', 'city']);
            $table->dropColumn(['country_code', 'country', 'region', 'city', 'device', 'browser']);
        });
    }
};
