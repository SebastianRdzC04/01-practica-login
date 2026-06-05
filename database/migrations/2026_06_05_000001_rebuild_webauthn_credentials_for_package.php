<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webauthn_credentials');

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->string('id', 510);
            $table->primary('id');
            $table->unsignedBigInteger('authenticatable_id');
            $table->string('authenticatable_type');
            $table->index(['authenticatable_id', 'authenticatable_type'], 'webauthn_user_index');
            $table->uuid('user_id');
            $table->string('alias')->nullable();
            $table->unsignedBigInteger('counter')->nullable();
            $table->string('rp_id');
            $table->string('origin');
            $table->json('transports')->nullable();
            $table->uuid('aaguid')->nullable();
            $table->text('public_key');
            $table->string('attestation_format')->default('none');
            $table->json('certificates')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name')->nullable();
            $table->text('credential_id')->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->json('transports')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
