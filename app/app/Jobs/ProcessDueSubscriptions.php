<?php

namespace App\Jobs;

use App\Models\Charge;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessDueSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $dueSubscriptions = Subscription::where('next_billing_at', '<=', now())
            ->whereIn('status', ['active', 'trialing'])
            ->get();

        foreach ($dueSubscriptions as $subscription) {
            // Generate a strict, immutable idempotency key unique to this subscription and billing cycle timestamp
            $idempotencyKey = md5($subscription->id.$subscription->next_billing_at->toIso8601String());

            DB::transaction(function () use ($subscription, $idempotencyKey) {
                // Assert that a charge has not already been generated for this exact cycle interval
                $exists = Charge::where('idempotency_key', $idempotencyKey)->exists();

                if (! $exists) {
                    $charge = Charge::create([
                        'subscription_id' => $subscription->id,
                        'idempotency_key' => $idempotencyKey,
                        'amount' => $subscription->plan->amount,
                        'status' => 'pending',
                        'attempt_count' => 1,
                    ]);

                    ProcessBillingCharge::dispatch($charge)->onQueue('billing');
                }
            });
        }
    }
}
