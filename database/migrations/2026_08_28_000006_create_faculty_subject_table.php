<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which subjects a faculty member is qualified to teach
    public function up(): void
    {
        Schema::create('faculty_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')
                ->constrained('faculty')
                ->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['faculty_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_subject');
    }
};
