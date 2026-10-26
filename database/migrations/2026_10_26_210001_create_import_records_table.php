<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mémoire de l'import WordPress : rend la commande idempotente (un contenu source = un contenu local).
        Schema::create('import_records', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);        // post, page, media, user, team, testimonial
            $table->string('source_id', 120);    // identifiant WordPress (ou clé stable)
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('hash', 64)->nullable(); // empreinte du contenu source : détecte les changements
            $table->string('source_url', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_id']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_records');
    }
};
