<?php

namespace App\Models;

use App\Enums\TeamCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['faculty_id', 'name', 'job_title', 'category', 'bio', 'quote', 'email', 'phone', 'photo_id', 'sort_order', 'is_active'])]
class TeamMember extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['category' => TeamCategory::class, 'is_active' => 'boolean'];
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_id');
    }
}
