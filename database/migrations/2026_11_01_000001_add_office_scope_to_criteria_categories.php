<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criteria_categories', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->constrained()->restrictOnDelete();
            // NULL means shared. A generated zero prevents MySQL from allowing
            // duplicate shared sections through a nullable composite unique index.
            $table->unsignedBigInteger('scope_key')->virtualAs('COALESCE(office_id, 0)');
            $table->unique(['evaluatee_type', 'scope_key', 'section_number'], 'criteria_categories_type_scope_section_unique');
        });
        Schema::table('criteria_categories', fn (Blueprint $table) => $table->dropUnique(['evaluatee_type', 'section_number']));
    }

    public function down(): void
    {
        if (DB::table('criteria_categories')->whereNotNull('office_id')->exists()) {
            throw new RuntimeException('Office-specific criteria exist. Preserve/export them before rolling back this migration.');
        }
        Schema::table('criteria_categories', function (Blueprint $table) {
            $table->unique(['evaluatee_type', 'section_number']);
            $table->dropUnique('criteria_categories_type_scope_section_unique');
        });
        Schema::table('criteria_categories', fn (Blueprint $table) => $table->dropColumn('scope_key'));
        Schema::table('criteria_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('office_id'));
    }
};
