<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'invoice_id', 'amount', 'mode', 'reference', 'request_key', 'received_at', 'received_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment entries cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Payment entries cannot be deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class)->where('type', 'REFUND');
    }
}
