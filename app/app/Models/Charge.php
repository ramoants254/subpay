<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscription_id',
        'idempotency_key',
        'amount',
        'status',
        'checkout_request_id',
        'mpesa_receipt',
        'failure_reason',
        'attempt_count',
        'next_retry_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'attempt_count' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return HasMany<BillingEvent, $this> */
    public function billingEvents(): HasMany
    {
        return $this->hasMany(BillingEvent::class);
    }
}
