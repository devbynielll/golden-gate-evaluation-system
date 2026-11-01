<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->unsignedInteger('skipped_count')->default(0)->after('failure_count');
            $table->unsignedInteger('enrollments_count')->default(0)->after('skipped_count');
            $table->json('skipped')->nullable()->after('errors');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['skipped_count', 'enrollments_count', 'skipped']);
        });
    }
};
