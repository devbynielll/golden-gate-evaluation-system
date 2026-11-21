<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // Enforce the immutable staff/office assignment at the database boundary too.
        Schema::table('office_staff', fn (Blueprint $table) => $table->unique(['id', 'office_id'], 'office_staff_assignment_identity_unique'));
        Schema::create('staff_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('office_staff_id');
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreign(['office_staff_id', 'office_id'], 'staff_evaluations_assignment_foreign')
                ->references(['id', 'office_id'])->on('office_staff')->restrictOnDelete()->restrictOnUpdate();
            $table->string('staff_name');
            $table->string('staff_position');
            $table->string('office_code', 20);
            $table->string('office_name');
            $table->string('academic_year', 20);
            $table->string('semester', 50);
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['student_id', 'review_period_id', 'office_staff_id'], 'staff_evaluations_student_period_assignment_unique');
            $table->index(['office_id', 'review_period_id']);
        });
        Schema::create('staff_evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_criteria_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_criteria_category_id')->constrained()->restrictOnDelete();
            $table->string('category_name');
            $table->unsignedSmallInteger('section_number');
            $table->boolean('is_office_specific');
            $table->text('question_text');
            $table->unsignedInteger('question_order');
            $table->unsignedTinyInteger('rating');
            $table->timestamps();
            $table->unique(['staff_evaluation_id', 'staff_criteria_question_id'], 'staff_answers_evaluation_question_unique');
        });
    }

    public function down(): void
    {
        foreach (['staff_evaluations', 'staff_evaluation_answers'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Staff evaluation history exists. Retain its tables and obtain an authorized recovery plan instead of deleting responses.');
            }
        }
        Schema::dropIfExists('staff_evaluation_answers');
        Schema::dropIfExists('staff_evaluations');
        Schema::table('office_staff', fn (Blueprint $table) => $table->dropUnique('office_staff_assignment_identity_unique'));
    }
};
