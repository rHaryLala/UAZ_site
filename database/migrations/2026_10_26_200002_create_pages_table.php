<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            // Clé technique des pages « système » (home, about, admissions…) ; null pour les pages libres.
            $table->string('key', 60)->nullable()->unique();
            $table->string('title');
            $table->string('slug');
            // Chemin complet (ex. admissions/licence), recalculé à l'enregistrement.
            $table->string('path')->unique();
            $table->text('summary')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->json('blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
