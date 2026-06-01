<?php

use App\Models\BillingEvent;
use App\Models\Charge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'phone' => '254712345678',
    ]);

    $this->plan = Plan::create([
        'name' => 'Monthly Standard',
        'amount' => 150000, // KES 1500.00
        'billing_cycle' => 'monthly',
        'trial_days' => 0,
        'grace_period_days' => 3,
        'is_active' => true,
    ]);

    $this->subscription = Subscription::create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'next_billing_at' => now()->addMonth(),
    ]);

    $this->charge = Charge::create([
        'subscription_id' => $this->subscription->id,
        'idempotency_key' => 'id_key_123',
        'amount' => 150000,
        'status' => 'pending',
        'checkout_request_id' => 'ws_CO_0000000000',
    ]);
});

test('it processes successful callback and advances subscription details', function () {
    $successPayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '12345-67890-1',
                'CheckoutRequestID' => 'ws_CO_0000000000',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 1500.00],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ1RT6S99'],
                        ['Name' => 'TransactionDate', 'Value' => 20260531120000],
                        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/mpesa/callback', $successPayload);

    $response->assertStatus(200)
        ->assertJson(['ResultCode' => 0, 'ResultDesc' => 'Success']);

    // Assert the charge has changed state and linked the transaction code
    $this->charge->refresh();
    expect($this->charge->status)->toBe('completed')
        ->and($this->charge->mpesa_receipt)->toBe('NLJ1RT6S99');

    // Assert subscription state has been advanced to next month's billing boundary
    $this->subscription->refresh();
    expect($this->subscription->status)->toBe('active')
        ->and($this->subscription->next_billing_at->toDateString())
        ->toBe(now()->addMonth()->toDateString());

    // Assert the audit trail contains the log of the event
    $event = BillingEvent::where('charge_id', $this->charge->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('completed')
        ->and($event->payload['Body']['stkCallback']['CheckoutRequestID'])->toBe('ws_CO_0000000000');
});

test('it enforces idempotency and ignores duplicate callbacks safely', function () {
    // Simulate an already completed payment state
    $this->charge->update([
        'status' => 'completed',
        'mpesa_receipt' => 'NLJ1RT6S99',
    ]);

    $duplicatePayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '12345-67890-1',
                'CheckoutRequestID' => 'ws_CO_0000000000',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 1500.00],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ1RT6S99'],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson('/api/mpesa/callback', $duplicatePayload);

    // Endpoint should return success cleanly to tell Safaricom to stop retrying, but execute no DB side-effects
    $response->assertStatus(200)
        ->assertJson(['ResultCode' => 0, 'ResultDesc' => 'Success (Duplicate Callback)']);

    // Ensure only the default database updates exist
    expect(BillingEvent::count())->toBe(0);
});

test('it transitions charges to failed and stores description on negative result code', function () {
    $failurePayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '12345-67890-1',
                'CheckoutRequestID' => 'ws_CO_0000000000',
                'ResultCode' => 1032, // Safaricom cancellation code
                'ResultDesc' => 'Request cancelled by user',
            ],
        ],
    ];

    $response = $this->postJson('/api/mpesa/callback', $failurePayload);

    $response->assertStatus(200);

    $this->charge->refresh();
    expect($this->charge->status)->toBe('failed')
        ->and($this->charge->failure_reason)->toBe('Request cancelled by user');

    $event = BillingEvent::where('charge_id', $this->charge->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('failed');
});
