<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bunq_transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('kind', 16);                    // request | payment
            $table->unsignedBigInteger('remote_id');       // bunq request-inquiry or payment id
            $table->unsignedBigInteger('amount_minor');    // amount in cents
            $table->char('currency', 3);
            $table->string('status', 16)->default('pending')->index();
            $table->string('description');
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'remote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bunq_transactions');
    }
};
