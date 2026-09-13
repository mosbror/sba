<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inspection_building_id', 'checklist_item_id', 'section_title_snapshot', 'item_text_snapshot', 'section_sort_order_snapshot', 'item_sort_order_snapshot', 'status', 'remark', 'comment', 'action_date'])]
class InspectionItemResult extends Model
{
    use HasFactory;

    public function inspectionBuilding(): BelongsTo
    {
        return $this->belongsTo(InspectionBuilding::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_date' => 'date',
        ];
    }
}
