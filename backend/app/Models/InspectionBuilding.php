<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['inspection_id', 'building_id', 'building_name_snapshot', 'sort_order_snapshot', 'status', 'started_at', 'completed_at'])]
class InspectionBuilding extends Model
{
    use HasFactory;

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionItemResult::class)
            ->orderBy('section_sort_order_snapshot')
            ->orderBy('item_sort_order_snapshot');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
