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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id');
            $table->foreignId('wallet_id')->constrained();
            $table->decimal('amount', 19, 2);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('transaction_id')->on('transactions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
