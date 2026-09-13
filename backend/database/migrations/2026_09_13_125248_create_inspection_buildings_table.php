<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained();
            $table->string('building_name_snapshot');
            $table->unsignedInteger('sort_order_snapshot')->default(0);
            $table->string('status')->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'building_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_buildings');
    }
};
