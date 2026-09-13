<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(['name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_templates');
    }
};
