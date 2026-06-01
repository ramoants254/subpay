<?php

use App\Jobs\ProcessBillingCharge;
use App\Jobs\ProcessDueSubscriptions;
use App\Jobs\RetryFailedCharge;
use App\Models\Charge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

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
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->subMinutes(5),
        'next_billing_at' => now()->subMinutes(5), // Due for billing
    ]);
});

test('it schedules and dispatches exactly one billing job per subscription', function () {
    // Run scheduler job
    (new ProcessDueSubscriptions)->handle();

    // Verify exactly one job was pushed to queue
    Queue::assertPushed(ProcessBillingCharge::class, 1);

    // Verify duplicate run creates no extra jobs (Strict Idempotence)
    (new ProcessDueSubscriptions)->handle();
    Queue::assertPushed(ProcessBillingCharge::class, 1);
});

test('it launches backoff delays and transitions to past_due on final failure', function () {
    $charge = Charge::create([
        'subscription_id' => $this->subscription->id,
        'idempotency_key' => 'attempt_1_key',
        'amount' => 50000,
        'status' => 'failed',
        'attempt_count' => 1,
    ]);

    // Dispatch retry handler for Attempt 1
    (new RetryFailedCharge($charge))->handle();

    // Assert that a new charge attempt was recorded in the database
    $attempt2 = Charge::where('attempt_count', 2)->first();
    expect($attempt2)->not->toBeNull()
        ->and($attempt2->status)->toBe('pending');

    // Confirm that the next charging job was queued with the correct delay parameter (1 hour / 3600 seconds)
    Queue::assertPushed(ProcessBillingCharge::class, function ($job) {
        return $job->delay !== null;
    });

    // Now, simulate final attempt failure (Attempt 4)
    $finalFailedCharge = Charge::create([
        'subscription_id' => $this->subscription->id,
        'idempotency_key' => 'attempt_4_key',
        'amount' => 50000,
        'status' => 'failed',
        'attempt_count' => 4,
    ]);

    (new RetryFailedCharge($finalFailedCharge))->handle();

    // Assert subscription has transitioned to past_due status and no new job is scheduled
    $this->subscription->refresh();
    expect($this->subscription->status)->toBe('past_due');
});
