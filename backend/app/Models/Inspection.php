<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_template_id', 'title', 'inspection_date', 'status', 'inspector_user_id', 'completed_at'])]
class Inspection extends Model
{
    use HasFactory;

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(InspectionBuilding::class)->orderBy('sort_order_snapshot');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
