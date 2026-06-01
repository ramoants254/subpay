<?php

namespace App\Mail;

use App\Models\Charge;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Charge $charge) {}

    public function build(): self
    {
        $amountKsh = number_format($this->charge->amount / 100, 2);

        return $this->subject("Payment Receipt: {$this->charge->mpesa_receipt}")
            ->html("
                <h3>Payment Confirmation</h3>
                <p>Hello {$this->charge->subscription->user->name},</p>
                <p>Your payment for <strong>{$this->charge->subscription->plan->name}</strong> has been processed successfully.</p>
                <ul>
                    <li><strong>Amount:</strong> KES {$amountKsh}</li>
                    <li><strong>M-Pesa Receipt Code:</strong> {$this->charge->mpesa_receipt}</li>
                    <li><strong>Next Billing Date:</strong> {$this->charge->subscription->next_billing_at->toFormattedDateString()}</li>
                </ul>
                <p>Thank you for subscribing!</p>
            ");
    }
}
