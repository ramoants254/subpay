<?php

use App\Jobs\ReconcilePendingCharges;
use App\Jobs\RetryFailedCharge;
use App\Models\BillingEvent;
use App\Models\Charge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Daraja\DarajaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create(['phone' => '254712345678']);
    $this->plan = Plan::create([
        'name' => 'Weekly Service',
        'amount' => 15000, // KES 150.00
        'billing_cycle' => 'weekly',
        'is_active' => true,
    ]);

    $this->subscription = Subscription::create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_start' => now()->subDays(7),
        'current_period_end' => now()->subMinutes(10),
        'next_billing_at' => now()->subMinutes(10),
    ]);

    // Create a pending charge stuck for more than 3 minutes (created 5 mins ago)
    $this->charge = Charge::create([
        'subscription_id' => $this->subscription->id,
        'idempotency_key' => 'stuck_key_123',
        'amount' => 15000,
        'status' => 'pending',
        'checkout_request_id' => 'ws_CO_reconciliation_target',
    ]);

    Charge::query()
        ->whereKey($this->charge->id)
        ->update([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
});

test('it reconciles stuck charges to completed if query returns success', function () {
    $darajaClient = Mockery::mock(DarajaClient::class);
    $darajaClient->shouldReceive('queryStkPushStatus')
        ->once()
        ->with('ws_CO_reconciliation_target')
        ->andReturn([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successsfully',
            'MerchantRequestID' => '1234-5678-9',
            'CheckoutRequestID' => 'ws_CO_reconciliation_target',
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully.',
        ]);

    (new ReconcilePendingCharges)->handle($darajaClient);

    $this->charge->refresh();
    expect($this->charge->status)->toBe('completed')
        ->and($this->charge->mpesa_receipt)->toBe('REC-ws_CO_reconciliation_target');

    $this->subscription->refresh();
    expect($this->subscription->status)->toBe('active')
        ->and($this->subscription->next_billing_at->toDateString())->toBe(now()->addWeek()->toDateString());

    $event = BillingEvent::where('charge_id', $this->charge->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('reconciled_success');
});

test('it transitions stuck charges to failed and schedules retries on user cancel query response', function () {
    $darajaClient = Mockery::mock(DarajaClient::class);
    $darajaClient->shouldReceive('queryStkPushStatus')
        ->once()
        ->with('ws_CO_reconciliation_target')
        ->andReturn([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successsfully',
            'MerchantRequestID' => '1234-5678-9',
            'CheckoutRequestID' => 'ws_CO_reconciliation_target',
            'ResultCode' => '1032',
            'ResultDesc' => 'Request cancelled by user.',
        ]);

    (new ReconcilePendingCharges)->handle($darajaClient);

    $this->charge->refresh();
    expect($this->charge->status)->toBe('failed')
        ->and($this->charge->failure_reason)->toContain('Request cancelled by user');

    // Confirm the missed failed transaction gets routed back into our Phase 4 retry scheduler
    Queue::assertPushed(RetryFailedCharge::class, 1);

    $event = BillingEvent::where('charge_id', $this->charge->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('reconciled_failed');
});

test('it ignores processing stuck charges to wait for next cron run', function () {
    $darajaClient = Mockery::mock(DarajaClient::class);
    $darajaClient->shouldReceive('queryStkPushStatus')
        ->once()
        ->with('ws_CO_reconciliation_target')
        ->andReturn([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successsfully',
            'MerchantRequestID' => '1234-5678-9',
            'CheckoutRequestID' => 'ws_CO_reconciliation_target',
            // ResultCode/ResultDesc absent means it's still active on user handset
        ]);

    (new ReconcilePendingCharges)->handle($darajaClient);

    $this->charge->refresh();
    // Must remain pending
    expect($this->charge->status)->toBe('pending');
});
