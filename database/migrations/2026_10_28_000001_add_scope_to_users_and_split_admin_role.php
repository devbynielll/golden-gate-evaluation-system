<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // dept/office admins belong to one department/office
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->after('department_id')
                ->constrained()->cascadeOnDelete();
        });

        // old admins become super admins
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['department_admin', 'office_admin'])->delete();
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
