<?php

namespace App\Http\Controllers;

use App\Jobs\RetryFailedCharge;
use App\Jobs\SendReceiptEmail;
use App\Models\BillingEvent;
use App\Models\Charge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    /**
     * Handle the incoming M-Pesa Express (STK Push) callback from Safaricom.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $payload = $request->all();
        $stkCallback = $payload['Body']['stkCallback'] ?? null;

        if (! $stkCallback) {
            Log::error('M-Pesa Callback: Received invalid payload structure.', ['payload' => $payload]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid Payload'], 400);
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);
        $resultDesc = $stkCallback['ResultDesc'] ?? 'No description provided';

        // Retrieve the matching charge record using Safaricom's unique key
        $charge = Charge::with('subscription.plan')->where('checkout_request_id', $checkoutRequestId)->first();

        if (! $charge) {
            Log::warning("M-Pesa Callback: Charge reference not found for CheckoutRequestID: {$checkoutRequestId}");

            // Return 200 OK with ResultCode 0 so Safaricom stops retrying this callback
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success (Reference Missing)']);
        }

        // 1. Handle Successful Payments
        if ($resultCode === 0) {
            $metadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
            $mpesaReceiptNumber = null;

            foreach ($metadata as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $mpesaReceiptNumber = $item['Value'] ?? null;
                    break;
                }
            }

            // Idempotency check: Guard against processing duplicate transactions
            if ($charge->status === 'completed' ||
                ($mpesaReceiptNumber && Charge::where('mpesa_receipt', $mpesaReceiptNumber)->exists())) {
                Log::info("M-Pesa Callback: Duplicate callback ignored for checkout ID: {$checkoutRequestId}");

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success (Duplicate Callback)']);
            }

            // Process database updates inside an atomic transaction
            DB::transaction(function () use ($charge, $mpesaReceiptNumber, $payload) {
                $now = now();
                $subscription = $charge->subscription;
                $plan = $subscription->plan;

                // Advance the subscription's active dates based on the configured plan billing cycle
                $nextBillingDate = $plan->calculateNextBillingDate($now);

                $charge->update([
                    'status' => 'completed',
                    'mpesa_receipt' => $mpesaReceiptNumber,
                ]);

                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => $now,
                    'current_period_end' => $nextBillingDate,
                    'next_billing_at' => $nextBillingDate,
                ]);

                BillingEvent::create([
                    'charge_id' => $charge->id,
                    'event_type' => 'completed',
                    'payload' => $payload,
                ]);
            });

            SendReceiptEmail::dispatch($charge)->afterCommit();
            Log::info("M-Pesa Callback: Charge successfully completed for subscription: {$charge->subscription_id}");

            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        // 2. Handle Failed/Cancelled Payments
        DB::transaction(function () use ($charge, $resultDesc, $payload) {
            $charge->update([
                'status' => 'failed',
                'failure_reason' => $resultDesc,
            ]);

            BillingEvent::create([
                'charge_id' => $charge->id,
                'event_type' => 'failed',
                'payload' => $payload,
            ]);
        });

        // Dispatch ONLY after the database commits successfully
        SendReceiptEmail::dispatch($charge)->afterCommit();
        RetryFailedCharge::dispatch($charge);

        Log::info("M-Pesa Callback: Charge failed/cancelled for subscription: {$charge->subscription_id}. Reason: {$resultDesc}");

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success (Failed Status Handled)']);
    }
}
