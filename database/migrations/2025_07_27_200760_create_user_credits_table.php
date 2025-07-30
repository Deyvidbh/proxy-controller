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
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();

            $table->float('balance');
            $table->float('amount');

            $table->decimal('price', 8, 2);

            $table->enum('type', ['withdraw', 'credit'])->index();

            $table->string('external_reference')->index();
            $table->string('payment_id')->nullable()->index();
            $table->string('description');

            $table->enum('status', ['completed', 'pending', 'canceled'])->index();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_credits');
    }
};
