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
        Schema::create('squid_ports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('port');
            $table->string('instance')->nullable();
            $table->string('host')->nullable();
            $table->unsignedBigInteger('ip_pool_id')->nullable();
            $table->string('output_ip_address')->unique()->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_renovation')->nullable();
            $table->timestamp('last_update_ip')->nullable();
            $table->boolean('in_use')->default(false);
            $table->timestamps();

            $table->foreign('ip_pool_id')->references('id')->on('ip_pools')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('squid_ports');
    }
};
