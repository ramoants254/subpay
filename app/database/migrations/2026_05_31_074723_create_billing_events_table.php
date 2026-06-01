<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('charge_id')->constrained('charges')->onDelete('cascade');
            $table->string('event_type');
            $table->jsonb('payload'); // Raw response data from Safaricom callback/query
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
    }
};
