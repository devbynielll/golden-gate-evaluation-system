<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            // Count only new successful accounts; existing historical batches stay at zero.
            $table->unsignedInteger('regular_count')->default(0);
            $table->unsignedInteger('irregular_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['regular_count', 'irregular_count']);
        });
    }
};
