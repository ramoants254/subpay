<?php

namespace App\Jobs;

use App\Models\BillingEvent;
use App\Models\Charge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedCharge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Charge $failedCharge) {}

    public function handle(): void
    {
        $subscription = $this->failedCharge->subscription;
        $currentAttempt = $this->failedCharge->attempt_count;

        // Max retries defined as 3 (representing 4 physical attempts total)
        if ($currentAttempt >= 4) {
            DB::transaction(function () use ($subscription) {
                $subscription->update([
                    'status' => 'past_due',
                ]);
            });

            Log::warning("Subscription {$subscription->id} updated to past_due. All payment attempts failed.");

            return;
        }

        $nextAttempt = $currentAttempt + 1;
        $delayInSeconds = $this->getBackoffDelay($nextAttempt);
        $nextRetryAt = now()->addSeconds($delayInSeconds);

        DB::transaction(function () use ($subscription, $nextAttempt, $nextRetryAt, $delayInSeconds) {
            // Update the record of our parent failed charge to log when we plan to try again
            $this->failedCharge->update([
                'next_retry_at' => $nextRetryAt,
            ]);

            // Form a new cycle idempotency key specifying the retry attempt suffix
            $idempotencyKey = md5($subscription->id.$subscription->next_billing_at->toIso8601String().'_retry_'.$nextAttempt);

            $newCharge = Charge::create([
                'subscription_id' => $subscription->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $this->failedCharge->amount,
                'status' => 'pending',
                'attempt_count' => $nextAttempt,
            ]);

            BillingEvent::create([
                'charge_id' => $this->failedCharge->id,
                'event_type' => 'retried',
                'payload' => [
                    'next_attempt' => $nextAttempt,
                    'scheduled_at' => $nextRetryAt->toIso8601String(),
                    'delay_seconds' => $delayInSeconds,
                ],
            ]);

            // Queue the charging task with the appropriate delay setting
            ProcessBillingCharge::dispatch($newCharge)
                ->delay($nextRetryAt)
                ->onQueue('billing');
        });
    }

    /**
     * Compute delay boundaries in seconds based on current attempt number:
     * - Attempt 2 (Retry 1): 1 hour (3600s)
     * - Attempt 3 (Retry 2): 6 hours (21600s)
     * - Attempt 4 (Retry 3): 24 hours (86400s)
     */
    protected function getBackoffDelay(int $attempt): int
    {
        return match ($attempt) {
            2 => 3600,  // 1 hour
            3 => 21600, // 6 hours
            4 => 86400, // 24 hours
            default => 3600,
        };
    }
}
