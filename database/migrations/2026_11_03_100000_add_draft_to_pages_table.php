<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Brouillon de l'éditeur visuel : {title, summary, blocks, saved_at, user_id}. La page publiée n'est modifiée qu'au clic sur « Publier ». */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('draft')->nullable()->after('blocks');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('draft');
        });
    }
};
