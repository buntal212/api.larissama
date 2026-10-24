<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warung_customer_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warung_id')->unique()->constrained('warungs')->restrictOnDelete();
            $table->foreignId('owner_user_id');
            $table->string('external_customer_id', 100)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('udev_customer_id')->nullable();
            $table->string('notice_version', 40);
            $table->timestamp('consented_at');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('last_enqueued_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_request_id', 100)->nullable();
            $table->string('last_error_code', 60)->nullable();
            $table->timestamps();
            $table->foreign(['warung_id', 'owner_user_id'])->references(['warung_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('warung_customer_sync_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warung_customer_sync_id')->constrained('warung_customer_syncs')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event', 30);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warung_customer_sync_events');
        Schema::dropIfExists('warung_customer_syncs');
    }
};
