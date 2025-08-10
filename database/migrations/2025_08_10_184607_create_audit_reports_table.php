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
        Schema::create('audit_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_id')->unique();
            $table->timestamp('audit_date');
            $table->json('parameters')->nullable();
            $table->json('summary');
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 50)->default('completed');
            $table->timestamps();

            $table->index('audit_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_reports');
    }
};
