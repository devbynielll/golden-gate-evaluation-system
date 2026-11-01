<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive only: legacy accounts remain unassigned; never infer ownership.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            // Multiple NULLs are permitted; concurrent duplicate office claims are rejected by the database.
            $table->unique('office_id', 'users_office_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // MySQL must release the foreign key before its supporting index.
            $table->dropForeign(['office_id']);
            $table->dropUnique('users_office_assignment_unique');
            $table->dropColumn('office_id');
        });
    }
};
