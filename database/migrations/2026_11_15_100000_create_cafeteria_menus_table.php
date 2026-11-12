<?php

use App\Models\CafeteriaMenu;
use App\Support\CafeteriaMenuData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menus de la cafétéria, une ligne par semaine (sept jours de trois repas), modifiables dans le back-office. Les trois premiers menus
 * fournis par l'UAZ (semaines du 8, du 15 et du 22 novembre 2026) sont installés ici ; une semaine déjà saisie n'est jamais écrasée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafeteria_menus', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on')->unique(); // premier jour de la semaine (le dimanche : la semaine va du dimanche au samedi)
            $table->json('meals'); // sept jours, chacun : { morning: [plats], noon: [plats], evening: [plats] }
            $table->string('note', 300)->nullable(); // mention facultative sous le tableau
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        foreach (CafeteriaMenuData::weeks() as $week) {
            CafeteriaMenu::query()->firstOrCreate(['starts_on' => $week['starts_on']], ['meals' => $week['meals'], 'is_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cafeteria_menus');
    }
};
