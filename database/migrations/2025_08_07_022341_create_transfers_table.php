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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id');
            $table->foreignId('sender_wallet_id')->constrained('wallets');
            $table->foreignId('receiver_wallet_id')->constrained('wallets');
            $table->decimal('amount', 19, 2);
            $table->decimal('fee', 19, 2)->default(0.00);
            $table->timestamps('');

            $table->foreign('transaction_id')->references('transaction_id')->on('transactions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
