<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMpesaIpAddress
{
    /**
     * Official Safaricom production IP addresses for incoming webhook callbacks.
     */
    protected array $safaricomIps = [
        '196.201.214.200', '196.201.214.206', '196.201.213.114',
        '196.201.214.207', '196.201.214.208', '196.201.213.44',
        '196.201.212.127', '196.201.212.138', '196.201.212.129',
        '196.201.212.136', '196.201.212.74', '196.201.212.69',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('daraja.env') === 'production') {
            $clientIp = $request->ip();

            if (! in_array($clientIp, $this->safaricomIps)) {
                abort(403, 'Access denied: Webhook source IP is unauthorized.');
            }
        }

        return $next($request);
    }
}
