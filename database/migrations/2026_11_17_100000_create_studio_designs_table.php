<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Studio UAZ : les visuels enregistrés (brouillons et exports à refaire) de chaque personne du back-office. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('template', 40); // identifiant du modèle (public/js/admin/studio/modeles.js)
            $table->string('format', 8)->default('16:9'); // 16:9, 1:1, 4:5 ou 9:16
            $table->json('data'); // champs saisis, et pour chaque photo : { media, focus, zoom }
            $table->mediumText('thumb')->nullable(); // vignette JPEG en adresse « data: »
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_designs');
    }
};
