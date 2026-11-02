<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables dont le contenu se traduit (App\Models\Concerns\HasTranslations). */
    private const TABLES = [
        'pages', 'formations', 'news_posts', 'events', 'photo_albums', 'faculties', 'projects',
        'team_members', 'testimonials', 'news_categories', 'site_settings', 'media_assets',
    ];

    /** Traductions : {"en": {"title": "…", "summary": "…"}, "mg": {…}}. Les colonnes d'origine restent le français, langue source. */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('translations')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
    }
};
