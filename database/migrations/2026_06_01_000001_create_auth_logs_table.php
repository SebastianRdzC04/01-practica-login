<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->default('info');
            $table->text('message')->nullable();
            $table->string('event', 100)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('role', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('succeeded')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();

            $table->index('event');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_logs');
    }
};
