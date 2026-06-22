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
        Schema::create('anonymous_visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('visit_token')->unique();
            $table->string('session_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->json('ip_data')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referrer')->nullable();
            $table->text('landing_page')->nullable();
            $table->text('exit_page')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamp('entered_at')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('exited_at')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_visits');
    }
};
