<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('position');
            $table->boolean('is_active')->default(true);
            $table->char('identity_key', 64);
            $table->timestamps();
            $table->unique(['office_id', 'identity_key']);
            $table->index(['office_id', 'is_active']);
        });
        Schema::create('staff_criteria_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('scope_key')->virtualAs('COALESCE(office_id, 0)');
            $table->unsignedSmallInteger('section_number');
            $table->string('name');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['scope_key', 'section_number'], 'staff_categories_scope_section_unique');
        });
        Schema::create('staff_criteria_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criteria_category_id')->constrained('staff_criteria_categories')->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['criteria_category_id', 'order'], 'staff_questions_category_order_unique');
        });
    }

    public function down(): void
    {
        // Never turn rollback into implicit deletion of staff, criteria or future answers.
        foreach (['office_staff', 'staff_criteria_categories', 'staff_criteria_questions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Phase 3 contains staff or criteria records. Preserve them and obtain an authorized recovery plan before rollback.');
            }
        }
        Schema::dropIfExists('staff_criteria_questions');
        Schema::dropIfExists('staff_criteria_categories');
        Schema::dropIfExists('office_staff');
    }
};
