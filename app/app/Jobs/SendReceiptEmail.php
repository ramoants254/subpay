<?php

namespace App\Jobs;

use App\Mail\PaymentReceiptMail;
use App\Models\Charge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReceiptEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Charge $charge) {}

    public function handle(): void
    {
        $user = $this->charge->subscription->user;
        Mail::to($user->email)->send(new PaymentReceiptMail($this->charge));
    }
}
