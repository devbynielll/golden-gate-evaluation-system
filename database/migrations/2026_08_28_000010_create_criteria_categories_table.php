<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Criteria categories, faculty and office share this table by evaluatee_type
    public function up(): void
    {
        Schema::create('criteria_categories', function (Blueprint $table) {
            $table->id();
            $table->enum('evaluatee_type', ['faculty', 'office']);
            $table->unsignedSmallInteger('section_number');
            $table->string('name');
            $table->timestamps();

            $table->unique(['evaluatee_type', 'section_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria_categories');
    }
};
