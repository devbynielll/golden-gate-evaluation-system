<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Class sections/blocks, like BSIT 1st Year - Block A
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->string('block', 10);
            $table->timestamps();

            $table->unique(['program_id', 'year_level', 'block']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sections');
    }
};
