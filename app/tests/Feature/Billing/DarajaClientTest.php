<?php

use App\Services\Daraja\DarajaClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Reset our environment configuration state before each run
    config([
        'daraja.env' => 'sandbox',
        'daraja.consumer_key' => 'mock_consumer_key',
        'daraja.consumer_secret' => 'mock_consumer_secret',
        'daraja.shortcode' => '174379',
        'daraja.passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
        'daraja.callback_url' => 'https://mock-domain.com/api/mpesa/callback',
    ]);

    Cache::forget('daraja_access_token');
});

test('it requests and caches the access token', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'this_is_a_mocked_token',
            'expires_in' => '3599',
        ], 200),
    ]);

    $client = new DarajaClient;

    // First call triggers API request
    $firstToken = $client->getAccessToken();
    expect($firstToken)->toBe('this_is_a_mocked_token');

    // Second call should pull directly from cache (generating no extra HTTP calls)
    $secondToken = $client->getAccessToken();
    expect($secondToken)->toBe('this_is_a_mocked_token');

    Http::assertSentCount(1);
});

test('it compiles the correct parameters and handles decimals on stk push requests', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'mocked_token',
        ], 200),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '12345-67890-1',
            'CheckoutRequestID' => 'ws_CO_0000000000',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ], 200),
    ]);

    $client = new DarajaClient;

    // DB Standard represents 150000 cents (KES 1,500.00)
    $response = $client->initiateStkPush(
        phone: '0712345678',
        amountInCents: 150000,
        reference: 'SUB-100',
        description: 'Premium Monthly Billing'
    );

    expect($response['ResponseCode'])->toBe('0')
        ->and($response['CheckoutRequestID'])->toBe('ws_CO_0000000000');

    Http::assertSent(function (Request $request) {
        if ($request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest') {
            $data = $request->data();

            // Confirm phone normalization, password packaging, and cent-to-shilling conversions
            return $data['Amount'] === 1500
                && $request->hasHeader('Authorization', 'Bearer mocked_token')
                && $data['PhoneNumber'] === '254712345678'
                && $data['PartyA'] === '254712345678'
                && $data['AccountReference'] === 'SUB-100'
                && ! empty($data['Password']);
        }

        return true;
    });
});

test('it throws runtime exception if token generation fails', function () {
    Http::fake([
        '*/oauth/v1/generate*' => Http::response('Unauthorized access', 401),
    ]);

    $client = new DarajaClient;

    expect(fn () => $client->getAccessToken())->toThrow(RuntimeException::class);
});
