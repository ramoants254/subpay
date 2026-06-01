<?php

namespace App\Jobs;

use App\Models\BillingEvent;
use App\Models\Charge;
use App\Services\Daraja\DarajaClient;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePendingCharges implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DarajaClient $darajaClient): void
    {
        // Query charges created more than 3 minutes ago that are still pending
        $stuckCharges = Charge::with('subscription.plan')
            ->where('status', 'pending')
            ->whereNotNull('checkout_request_id')
            ->where('created_at', '<=', now()->subMinutes(3))
            ->get();

        foreach ($stuckCharges as $charge) {
            try {
                $status = $darajaClient->queryStkPushStatus($charge->checkout_request_id);
                $resultCode = isset($status['ResultCode']) ? (int) $status['ResultCode'] : null;
                $resultDesc = $status['ResultDesc'] ?? 'No description';

                if ($resultCode === null) {
                    // Safaricom may sometimes return a ResponseCode 0 but not yet have a processed ResultCode
                    Log::info("Reconciliation: Charge {$charge->id} is still being processed on handset.");

                    continue;
                }

                if ($resultCode === 0) {
                    // 1. Transaction was successful but our callback was missed. Resolve cleanly.
                    DB::transaction(function () use ($charge, $status) {
                        $now = now();
                        $subscription = $charge->subscription;
                        $plan = $subscription->plan;

                        $nextBillingDate = $plan->calculateNextBillingDate($now);

                        $charge->update([
                            'status' => 'completed',
                            // Fallback placeholder code satisfies unique constraints if webhook missed
                            'mpesa_receipt' => 'REC-'.$charge->checkout_request_id,
                        ]);

                        $subscription->update([
                            'status' => 'active',
                            'current_period_start' => $now,
                            'current_period_end' => $nextBillingDate,
                            'next_billing_at' => $nextBillingDate,
                        ]);

                        BillingEvent::create([
                            'charge_id' => $charge->id,
                            'event_type' => 'reconciled_success',
                            'payload' => $status,
                        ]);
                    });

                    Log::info("Reconciliation: Successfully reconciled and completed Charge ID: {$charge->id}");
                } else {
                    // 2. Transaction failed or was explicitly cancelled by user. Mark failed and run retry pipeline.
                    DB::transaction(function () use ($charge, $resultDesc, $status) {
                        $charge->update([
                            'status' => 'failed',
                            'failure_reason' => 'Reconciled Failure: '.$resultDesc,
                        ]);

                        BillingEvent::create([
                            'charge_id' => $charge->id,
                            'event_type' => 'reconciled_failed',
                            'payload' => $status,
                        ]);
                    });

                    // Dispatch after commit
                    SendReceiptEmail::dispatch($charge)->afterCommit();

                    RetryFailedCharge::dispatch($charge);
                    Log::info("Reconciliation: Reconciled failed transaction for Charge ID: {$charge->id}. Suffixing retry routing.");
                }

            } catch (Exception $e) {
                Log::error("Reconciliation: Query execution failed for Charge ID: {$charge->id}. Error: ".$e->getMessage());

                // Continue to the next charge rather than halting the entire batch run
                continue;
            }
        }
    }
}
