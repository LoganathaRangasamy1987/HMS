<?php

namespace App\Models;

use Database\Factories\LabTestVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LabTestVersion extends Model
{
    /** @use HasFactory<LabTestVersionFactory> */
    use HasFactory;

    protected $fillable = ['lab_test_id', 'version', 'category_id', 'sample_type_id', 'sample_volume', 'instructions', 'currency', 'price', 'status', 'created_by', 'activated_at'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'activated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (array_diff(array_keys($version->getDirty()), ['status', 'activated_at', 'updated_at'])) {
                throw new LogicException('Laboratory test version content is immutable; create a new version.');
            }
        });
        static::deleting(fn () => throw new LogicException('Laboratory test versions cannot be deleted.'));
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LabCategory::class);
    }

    public function sampleType(): BelongsTo
    {
        return $this->belongsTo(LabSampleType::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(LabTestParameter::class)->orderBy('sort_order');
    }
}
