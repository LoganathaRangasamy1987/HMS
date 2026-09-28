<?php

namespace App\Models;

use Database\Factories\MedicineBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class MedicineBatch extends Model
{
    /** @use HasFactory<MedicineBatchFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'medicine_id', 'batch_number', 'manufactured_on', 'expires_on', 'received_quantity', 'on_hand_quantity', 'unit_cost', 'sale_price', 'status'];

    protected function casts(): array
    {
        return ['manufactured_on' => 'immutable_date', 'expires_on' => 'immutable_date', 'received_quantity' => 'decimal:3', 'on_hand_quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'sale_price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $batch): void {
            if ($batch->isDirty(['hospital_id', 'branch_id', 'medicine_id', 'batch_number', 'manufactured_on', 'expires_on'])) {
                throw new LogicException('Batch identity and expiry cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Medicine batches cannot be deleted.'));
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function latestMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class)->ofMany('id', 'max');
    }
}
