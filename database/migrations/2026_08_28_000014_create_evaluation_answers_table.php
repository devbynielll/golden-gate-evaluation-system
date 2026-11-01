<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A single rating within an evaluation submission
    public function up(): void
    {
        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('criteria_question_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['evaluation_id', 'criteria_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
    }
};
