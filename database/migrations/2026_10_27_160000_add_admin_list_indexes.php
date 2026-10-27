<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index des colonnes utilisées pour filtrer et trier les listes du back-office et du site public (actifs, ordre, messages non lus). */
return new class extends Migration
{
    private array $indexes = [
        'team_members' => [['is_active', 'sort_order']],
        'testimonials' => [['is_active', 'sort_order']],
        'partners' => [['is_active', 'sort_order']],
        'projects' => [['is_active', 'sort_order']],
        'contact_messages' => [['read_at'], ['created_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $sets) {
            Schema::table($table, function (Blueprint $blueprint) use ($sets) {
                foreach ($sets as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $sets) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $blueprint->dropIndex($table.'_'.implode('_', $columns).'_index');
                }
            });
        }
    }
};
