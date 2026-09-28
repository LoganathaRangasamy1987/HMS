<?php

namespace App\Models;

use Database\Factories\LabOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LabOrderItem extends Model
{
    /** @use HasFactory<LabOrderItemFactory> */
    use HasFactory;

    protected $fillable = ['lab_order_id', 'lab_test_id', 'lab_test_version_id', 'invoice_line_id', 'test_code', 'test_name', 'version', 'currency', 'price', 'status'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if (array_diff(array_keys($item->getDirty()), ['status', 'updated_at'])) {
                throw new LogicException('Ordered laboratory test snapshots cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Laboratory order items cannot be deleted.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function versionDefinition(): BelongsTo
    {
        return $this->belongsTo(LabTestVersion::class, 'lab_test_version_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(LabSpecimen::class)->orderBy('attempt');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class)->orderBy('revision');
    }
}
