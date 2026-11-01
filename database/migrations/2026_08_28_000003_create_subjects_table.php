<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Subjects offered within a program
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('description');
            $table->unsignedTinyInteger('year_level');
            $table->unsignedTinyInteger('credits')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
