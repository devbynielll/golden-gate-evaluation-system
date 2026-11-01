<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void
    {
        if (DB::table('users')->where('role','department_admin')->count()>1) {
            throw new RuntimeException('Multiple Department Admin accounts exist. Obtain an authorized account-resolution plan before applying this migration; no accounts were modified.');
        }
        Schema::table('users',function(Blueprint $table){
            $table->unsignedTinyInteger('department_admin_slot')->nullable()->virtualAs("CASE WHEN role = 'department_admin' THEN 1 ELSE NULL END");
            $table->unique('department_admin_slot','users_department_admin_singleton_unique');
        });
    }
    public function down(): void
    {
        Schema::table('users',function(Blueprint $table){$table->dropUnique('users_department_admin_singleton_unique');$table->dropColumn('department_admin_slot');});
    }
};
