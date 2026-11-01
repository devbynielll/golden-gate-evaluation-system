<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Review periods, from the "Manage Periods" modal
    public function up(): void
    {
        Schema::create('review_periods', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);
            $table->enum('semester', ['1st Semester', '2nd Semester', 'Summer']);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_submission_open')->default(false);
            $table->timestamps();

            $table->unique(['academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_periods');
    }
};
