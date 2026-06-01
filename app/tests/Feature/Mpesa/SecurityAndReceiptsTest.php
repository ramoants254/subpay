<?php

use App\Jobs\SendReceiptEmail;
use App\Mail\PaymentReceiptMail;
use App\Models\Charge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['phone' => '254712345678']);
    $this->plan = Plan::create([
        'name' => 'Monthly Plan',
        'amount' => 50000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);

    $this->subscription = Subscription::create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'next_billing_at' => now()->addMonth(),
    ]);

    $this->charge = Charge::create([
        'subscription_id' => $this->subscription->id,
        'idempotency_key' => 'security_id_123',
        'amount' => 50000,
        'status' => 'pending',
        'checkout_request_id' => 'ws_CO_security_target',
    ]);
});

test('it blocks callback access from non-authorized IPs in production mode', function () {
    config(['daraja.env' => 'production']);

    $payload = [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_security_target',
                'ResultCode' => 1032,
                'ResultDesc' => 'User Cancelled',
            ],
        ],
    ];

    // Request from non-whitelisted IP (127.0.0.1)
    $response = $this->postJson('/api/mpesa/callback', $payload);
    $response->assertStatus(403);
});

test('it allows callback access from authorized Safaricom IPs in production mode', function () {
    config(['daraja.env' => 'production']);
    Queue::fake();

    $payload = [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => 'ws_CO_security_target',
                'ResultCode' => 1032,
                'ResultDesc' => 'User Cancelled',
            ],
        ],
    ];

    // Simulating call originating from Safaricom's official production IP address block
    $response = $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
        ->postJson('/api/mpesa/callback', $payload);

    $response->assertStatus(200);
});

test('it dispatches SendReceiptEmail job and mails a receipt', function () {
    Queue::fake();
    Mail::fake();

    $successPayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '12345-67890-1',
                'CheckoutRequestID' => 'ws_CO_security_target',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ1RT6S99'],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson('/api/mpesa/callback', $successPayload);

    // Assert receipt queue job is dispatched
    Queue::assertPushed(SendReceiptEmail::class, 1);

    // Manually run the mailer job to verify receipt delivery
    $chargeCompleted = Charge::where('checkout_request_id', 'ws_CO_security_target')->first();
    (new SendReceiptEmail($chargeCompleted))->handle();

    Mail::assertSent(PaymentReceiptMail::class, function ($mail) {
        return $mail->hasTo($this->user->email)
            && $mail->charge->mpesa_receipt === 'NLJ1RT6S99';
    });
});

test('it allows authenticated users to fetch subscription logs', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/subscriptions');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => [
                'id',
                'status',
                'plan' => ['id', 'name', 'amount'],
                'charges' => [
                    '*' => ['id', 'status', 'amount', 'checkout_request_id'],
                ],
            ],
        ]);
});
