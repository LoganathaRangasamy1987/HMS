<?php

namespace App\Models;

use Database\Factories\PrescriptionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class PrescriptionItem extends Model
{
    /** @use HasFactory<PrescriptionItemFactory> */
    use HasFactory;

    protected $fillable = ['prescription_id', 'medicine_id', 'medicine_name', 'strength', 'dose', 'frequency', 'duration', 'route', 'timing', 'advice'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Prescription items cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Prescription items cannot be deleted.'));
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function pharmacySaleItem(): HasOne
    {
        return $this->hasOne(PharmacySaleItem::class);
    }
}
