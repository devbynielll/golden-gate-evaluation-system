<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // login ID, sample: "CET259090"
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_number', 20)->nullable()->unique()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id_number');
        });
    }
};
