<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A subject taught in a class section, with its instructor
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_section_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('faculty_id')
                ->nullable()
                ->constrained('faculty')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_section_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
