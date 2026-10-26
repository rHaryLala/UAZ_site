<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une seule ligne : les réglages globaux du site.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('Université Adventiste Zurcher');
            $table->string('tagline')->nullable();
            $table->foreignId('logo_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('default_og_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('social_links')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('footer_legal')->nullable();
            $table->string('home_hero_title')->nullable();
            $table->text('home_hero_subtitle')->nullable();
            $table->foreignId('home_hero_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->json('key_figures')->nullable();
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('handle', 40)->unique();
            // [{label, type: page|url|route, page_id?, url?, new_tab?, children: [...]}]
            $table->json('items')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('site_settings');
    }
};
