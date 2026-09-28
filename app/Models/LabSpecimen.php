<?php

namespace App\Models;

use Database\Factories\LabSpecimenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LabSpecimen extends Model
{
    /** @use HasFactory<LabSpecimenFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'lab_order_id', 'lab_order_item_id', 'identifier', 'attempt', 'status', 'collected_by', 'collected_at', 'received_by', 'received_at', 'processing_by', 'processing_at', 'rejected_by', 'rejected_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['collected_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'processing_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $specimen): void {
            if ($specimen->isDirty(['hospital_id', 'branch_id', 'lab_order_id', 'lab_order_item_id', 'identifier', 'attempt', 'collected_by', 'collected_at'])) {
                throw new LogicException('Specimen identity and collection provenance cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Laboratory specimens cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LabSpecimenEvent::class)->orderBy('recorded_at')->orderBy('id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class)->orderBy('revision');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function processingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processing_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
