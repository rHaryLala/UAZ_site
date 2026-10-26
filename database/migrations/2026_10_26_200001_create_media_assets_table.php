<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une « ressource » de la médiathèque = un fichier (via Media Library) + ses métadonnées éditoriales.
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('alt', 300)->default('');
            $table->string('caption', 500)->nullable();
            $table->string('credit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
