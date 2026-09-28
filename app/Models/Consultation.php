<?php

namespace App\Models;

use Database\Factories\ConsultationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Consultation extends Model
{
    /** @use HasFactory<ConsultationFactory> */
    use HasFactory;

    protected $fillable = ['encounter_id', 'chief_complaint', 'history', 'examination', 'clinical_notes', 'follow_up_date', 'status', 'authored_by', 'finalized_at', 'finalized_by'];

    protected function casts(): array
    {
        return ['follow_up_date' => 'immutable_date', 'finalized_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Consultation $consultation): void {
            if ($consultation->getOriginal('status') === 'FINALIZED' && $consultation->isDirty()) {
                throw new LogicException('Finalized consultations cannot be overwritten; create an amendment.');
            }
            if ($consultation->isDirty(['encounter_id', 'authored_by'])) {
                throw new LogicException('Consultation provenance cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Consultations cannot be deleted.'));
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ConsultationAmendment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }
}
