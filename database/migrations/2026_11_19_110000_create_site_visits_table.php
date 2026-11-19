<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une ligne par page vue sur le site public. Aucune adresse IP, aucun cookie : « visitor » est une empreinte qui change chaque jour (voir TrackVisit).
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->date('visited_on');
            $table->string('visitor', 32);
            $table->string('path', 255);
            $table->string('locale', 5)->nullable();
            $table->string('referrer', 100)->nullable();
            $table->timestamp('visited_at');

            $table->index(['visited_on', 'visitor']);
            $table->index(['visited_on', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
