<?php

namespace App\Models;

use Database\Factories\ConsultationAmendmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ConsultationAmendment extends Model
{
    /** @use HasFactory<ConsultationAmendmentFactory> */
    use HasFactory;

    protected $fillable = ['consultation_id', 'chief_complaint', 'history', 'examination', 'clinical_notes', 'follow_up_date', 'reason', 'amended_by', 'amended_at'];

    protected function casts(): array
    {
        return ['follow_up_date' => 'immutable_date', 'amended_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Consultation amendments cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Consultation amendments cannot be deleted.'));
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }
}
