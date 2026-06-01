<?php

namespace App\Services\Daraja;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class DarajaClient
{
    protected string $baseUrl;

    protected string $consumerKey;

    protected string $consumerSecret;

    protected string $shortcode;

    protected string $passkey;

    protected string $callbackUrl;

    public function __construct()
    {
        $this->consumerKey = config('daraja.consumer_key') ?? '';
        $this->consumerSecret = config('daraja.consumer_secret') ?? '';
        $this->shortcode = config('daraja.shortcode') ?? '174379';
        $this->passkey = config('daraja.passkey') ?? '';
        $this->callbackUrl = config('daraja.callback_url') ?? '';

        $this->baseUrl = config('daraja.env') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            throw new InvalidArgumentException('Missing Daraja Consumer Key or Secret configuration values.');
        }
    }

    /**
     * Retrieve the OAuth access token from Redis cache or request a fresh one.
     *
     * @throws RuntimeException
     */
    public function getAccessToken(): string
    {
        // Cache the token to mitigate duplicate API requests. Expiry is set slightly
        // ahead of Safaricom's 3600-second expiration to prevent edge-case race conditions.
        return Cache::remember('daraja_access_token', 3500, function () {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Failed to generate Daraja Access Token. Response: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Dispatch an M-Pesa Express (STK Push) request to a customer's phone.
     *
     * @param  string  $phone  Customer phone number (will be normalized)
     * @param  int  $amountInCents  The charge amount in cents (e.g., 50000 for KES 500.00)
     * @param  string  $reference  Explicit identifier (e.g., Subscription UUID)
     * @param  string  $description  Short description of the billing event
     *
     * @throws RuntimeException
     */
    public function initiateStkPush(string $phone, int $amountInCents, string $reference, string $description): array
    {
        $normalizedPhone = PhoneNormaliser::normalize($phone);
        $accessToken = $this->getAccessToken();

        // Safe conversion of integer cents back to main currency (KES Shillings) for Safaricom
        $amountInKsh = (int) round($amountInCents / 100);

        if ($amountInKsh <= 0) {
            throw new InvalidArgumentException('The billing amount converted to KES must be greater than zero.');
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amountInKsh,
            'PartyA' => $normalizedPhone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $normalizedPhone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $reference,
            'TransactionDesc' => $description,
        ];

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

        if ($response->failed()) {
            throw new RuntimeException('Daraja STK Push failed. Response: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Query Safaricom to verify the absolute ground-truth status of a processed STK Push transaction.
     *
     * @param  string  $checkoutRequestId  Unique key returned during initial dispatch
     *
     * @throws RuntimeException
     */
    public function queryStkPushStatus(string $checkoutRequestId): array
    {
        $accessToken = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", $payload);

        // Safaricom sandbox may return 404 if the checkout ID is expired or not found.
        if ($response->failed()) {
            throw new RuntimeException("Safaricom STK status query failed. Status Code: {$response->status()}. Response: ".$response->body());
        }

        return $response->json();
    }
}
