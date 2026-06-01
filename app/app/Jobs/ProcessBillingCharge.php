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
use Illuminate\Support\Facades\Log;

class ProcessBillingCharge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry configuration for physical network exceptions (e.g. DNS or Safaricom timeout)
    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(protected Charge $charge) {}

    public function handle(DarajaClient $darajaClient): void
    {
        // Safe check: Exit cleanly if another process updated the charge state in the interim
        if ($this->charge->status !== 'pending') {
            return;
        }

        $subscription = $this->charge->subscription;
        $user = $subscription->user;

        try {
            $response = $darajaClient->initiateStkPush(
                phone: $user->phone,
                amountInCents: $this->charge->amount,
                reference: 'SUB-'.substr($subscription->id, 0, 8),
                description: $subscription->plan->name
            );

            $checkoutRequestId = $response['CheckoutRequestID'] ?? null;

            if ($checkoutRequestId) {
                $this->charge->update([
                    'checkout_request_id' => $checkoutRequestId,
                ]);

                BillingEvent::create([
                    'charge_id' => $this->charge->id,
                    'event_type' => 'initiated',
                    'payload' => $response,
                ]);
            } else {
                throw new Exception('Safaricom API response was missing a valid CheckoutRequestID.');
            }

        } catch (Exception $e) {
            Log::error("Failed executing ProcessBillingCharge for Charge ID: {$this->charge->id}. Error: ".$e->getMessage());
            throw $e; // Throwing triggers Laravel's native job worker retry strategy
        }
    }
}
