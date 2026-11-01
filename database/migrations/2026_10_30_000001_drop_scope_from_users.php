<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // there's one department admin and one office admin overall, so they aren't tied to a department/office
    public function up(): void
    {
        // keep the oldest account of each role, a second one would break the one-admin rule
        foreach (['department_admin', 'office_admin'] as $role) {
            $keep = DB::table('users')->where('role', $role)->orderBy('id')->value('id');
            DB::table('users')->where('role', $role)->where('id', '!=', $keep ?? 0)->delete();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->after('department_id')->constrained()->cascadeOnDelete();
        });
    }
};
