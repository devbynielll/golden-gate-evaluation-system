<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's database notification format, with indexed, server-owned aggregate scope.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->foreignId('review_period_id')->constrained()->restrictOnDelete();
            $table->string('evaluation_kind', 10);
            $table->foreignId('office_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_id', 'notifiable_type', 'review_period_id'], 'notifications_recipient_period_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && DB::table('notifications')->exists()) {
            throw new RuntimeException('Notification history exists. Retain it until an authorized retention plan is approved.');
        }
        Schema::dropIfExists('notifications');
    }
};
