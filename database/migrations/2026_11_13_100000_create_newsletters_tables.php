<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletters : la newsletter rédigée dans le back-office (brouillon, en cours d'envoi, envoyée) et, pour chaque newsletter, une ligne par
 * abonné destinataire. Le couple (newsletter, abonné) est unique : un abonné ne reçoit jamais deux fois la même newsletter, même si l'envoi est relancé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 190);
            $table->string('preheader', 190)->nullable();
            $table->json('blocks'); // la newsletter : une liste de blocs (titre, texte, image, bouton…), voir App\Support\NewsletterBlocks
            $table->json('theme')->nullable(); // couleurs et police du plan de travail
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | sent | failed | skipped
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['newsletter_id', 'newsletter_subscriber_id'], 'newsletter_delivery_unique'); // nom court : MariaDB limite les identifiants à 64 caractères
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
        Schema::dropIfExists('newsletters');
    }
};
