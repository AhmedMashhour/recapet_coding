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
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('snapshot_id')->unique();
            $table->timestamp('snapshot_time');

            $table->integer('total_wallets');
            $table->integer('active_wallets');
            $table->decimal('total_balance', 20, 2);
            $table->decimal('total_deposits', 20, 2);
            $table->decimal('total_withdrawals', 20, 2);
            $table->decimal('total_fees', 20, 2);
            $table->decimal('calculated_balance', 20, 2);
            $table->decimal('balance_discrepancy', 15, 2);

            $table->json('wallet_balances');
            $table->json('statistics')->nullable();
            $table->json('discrepancies')->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();


        });

        Schema::create('wallet_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('balance_snapshots')->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained();
            $table->string('wallet_number');
            $table->decimal('wallet_balance', 15, 2);
            $table->decimal('ledger_balance', 15, 2);
            $table->decimal('discrepancy', 15, 2);
            $table->integer('transaction_count');
            $table->timestamp('last_transaction_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_balance_snapshots');
        Schema::dropIfExists('balance_snapshots');
    }
};
