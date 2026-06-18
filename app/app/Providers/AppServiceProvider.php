<?php

namespace App\Providers;

use App\Models\Charge;
use App\Models\Subscription;
use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Facades\Prometheus;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SubPay Custom Business Telemetry Exporters
        |--------------------------------------------------------------------------
        | These gauges query our database in real-time when Prometheus scrapes
        | the /prometheus metrics endpoint.
        */

        Prometheus::addGauge('Active Subscriptions')
            ->value(fn () => Subscription::where('status', 'active')->count());

        Prometheus::addGauge('Past Due Subscriptions')
            ->value(fn () => Subscription::where('status', 'past_due')->count());

        Prometheus::addGauge('Failed Charges Total')
            ->value(fn () => Charge::where('status', 'failed')->count());

        Prometheus::addGauge('Pending Charges Total')
            ->value(fn () => Charge::where('status', 'pending')->count());
    }
}