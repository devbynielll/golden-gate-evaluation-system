<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One evaluation submission
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_period_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('student_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('evaluatee_type', ['faculty', 'office']);
            $table->foreignId('class_subject_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('office_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'review_period_id', 'class_subject_id'],
                'evaluations_student_period_class_subject_unique',
            );
            $table->unique(
                ['student_id', 'review_period_id', 'office_id'],
                'evaluations_student_period_office_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
