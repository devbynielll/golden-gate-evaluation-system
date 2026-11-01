<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // a term is a review period; offerings and enrollments belong to one
    public function up(): void
    {
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->foreignId('review_period_id')->nullable()->after('faculty_id')
                ->constrained()->cascadeOnDelete();
            // add the new unique first, the old one backs the foreign key
            $table->unique(['class_section_id', 'subject_id', 'review_period_id'], 'class_subjects_section_subject_period_unique');
        });
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropUnique(['class_section_id', 'subject_id']);
        });

        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->foreignId('review_period_id')->nullable()->after('class_section_id')
                ->constrained()->cascadeOnDelete();
            $table->unique(['student_id', 'subject_id', 'review_period_id'], 'student_subject_period_unique');
        });
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'subject_id']);
        });

        // old data goes to the active term, else the latest. with no periods the first one created adopts it
        $periodId = DB::table('review_periods')->where('is_active', true)->value('id')
            ?? DB::table('review_periods')->orderByDesc('start_date')->value('id');

        if ($periodId) {
            DB::table('class_subjects')->whereNull('review_period_id')->update(['review_period_id' => $periodId]);
            DB::table('student_subject_enrollments')->whereNull('review_period_id')->update(['review_period_id' => $periodId]);
        }
    }

    public function down(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->unique(['student_id', 'subject_id']);
        });
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->dropUnique('student_subject_period_unique');
            $table->dropConstrainedForeignId('review_period_id');
        });

        Schema::table('class_subjects', function (Blueprint $table) {
            $table->unique(['class_section_id', 'subject_id']);
        });
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropUnique('class_subjects_section_subject_period_unique');
            $table->dropConstrainedForeignId('review_period_id');
        });
    }
};
