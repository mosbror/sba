<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_item_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained();
            $table->string('section_title_snapshot');
            $table->text('item_text_snapshot');
            $table->unsignedInteger('section_sort_order_snapshot')->default(0);
            $table->unsignedInteger('item_sort_order_snapshot')->default(0);
            $table->string('status')->default('unchecked');
            $table->string('remark')->nullable();
            $table->text('comment')->nullable();
            $table->date('action_date')->nullable();
            $table->timestamps();

            $table->unique(['inspection_building_id', 'checklist_item_id'], 'inspection_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_item_results');
    }
};
