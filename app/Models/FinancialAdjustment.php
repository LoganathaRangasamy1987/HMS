<?php

namespace App\Models;

use Database\Factories\FinancialAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FinancialAdjustment extends Model
{
    /** @use HasFactory<FinancialAdjustmentFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'invoice_id', 'payment_id', 'type', 'amount', 'reason', 'request_key', 'recorded_at', 'recorded_by'];

    protected $hidden = ['request_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'recorded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Financial adjustment entries cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Financial adjustment entries cannot be deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
