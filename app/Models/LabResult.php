<?php

namespace App\Models;

use Database\Factories\LabResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LabResult extends Model
{
    /** @use HasFactory<LabResultFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'lab_order_id', 'lab_order_item_id', 'lab_specimen_id', 'revision', 'status', 'supersedes_lab_result_id', 'correction_reason', 'entered_by', 'entered_at', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['entered_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $result): void {
            if ($result->getOriginal('status') !== 'DRAFT' || $result->isDirty(['hospital_id', 'branch_id', 'lab_order_id', 'lab_order_item_id', 'lab_specimen_id', 'revision', 'supersedes_lab_result_id', 'correction_reason', 'entered_by', 'entered_at'])) {
                throw new LogicException('Finalized results and result provenance cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Laboratory results cannot be deleted.'));
    }

    public function values(): HasMany
    {
        return $this->hasMany(LabResultValue::class)->orderBy('sort_order');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'lab_specimen_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_lab_result_id');
    }
}
