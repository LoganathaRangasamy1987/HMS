<?php

namespace App\Models;

use Database\Factories\LabSpecimenEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LabSpecimenEvent extends Model
{
    /** @use HasFactory<LabSpecimenEventFactory> */
    use HasFactory;

    protected $fillable = ['lab_specimen_id', 'from_status', 'to_status', 'reason', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Specimen history cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Specimen history cannot be deleted.'));
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'lab_specimen_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
