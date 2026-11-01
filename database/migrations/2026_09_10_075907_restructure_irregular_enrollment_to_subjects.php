<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // enrollment now targets the subject, not a specific class-section offering
    public function up(): void
    {
        Schema::dropIfExists('student_class_subjects');

        Schema::create('student_subject_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_enrollments');

        Schema::create('student_class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('class_subject_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'class_subject_id']);
        });
    }
};
