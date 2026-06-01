<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('idempotency_key')->unique();
            $table->integer('amount'); // Stored in cents
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('checkout_request_id')->nullable()->index(); // Daraja response key
            $table->string('mpesa_receipt')->nullable()->unique(); // Safaricom transaction code
            $table->text('failure_reason')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
