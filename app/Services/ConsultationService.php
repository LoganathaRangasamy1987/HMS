<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationAmendment;
use App\Models\Encounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationService
{
    public function __construct(private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function saveDraft(Encounter $encounter, User $actor, array $data): Consultation
    {
        return DB::transaction(function () use ($encounter, $actor, $data): Consultation {
            $encounter = Encounter::query()->lockForUpdate()->findOrFail($encounter->id);
            if ($encounter->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['encounter' => 'Only an active encounter can be edited.']);
            }
            $consultation = Consultation::query()->firstOrCreate(['encounter_id' => $encounter->id], ['authored_by' => $actor->id, 'status' => 'DRAFT']);
            if ($consultation->status !== 'DRAFT') {
                throw ValidationException::withMessages(['consultation' => 'A finalized consultation requires an amendment.']);
            }
            $consultation->updateOrFail($data);
            $this->audit->record('consultations', 'draft_saved', $consultation, null, $consultation->toArray());

            return $consultation->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function finalize(Encounter $encounter, User $actor, array $data): Consultation
    {
        return DB::transaction(function () use ($encounter, $actor, $data): Consultation {
            $encounter = Encounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $consultation = $this->saveDraft($encounter, $actor, $data);
            if (! $consultation->chief_complaint || ! $consultation->clinical_notes) {
                throw ValidationException::withMessages(['consultation' => 'Chief complaint and clinical notes are required to finalize.']);
            }
            $consultation->updateOrFail(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $actor->id]);
            $encounter->updateOrFail(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $actor->id]);
            Appointment::query()->whereKey($encounter->appointment_id)->lockForUpdate()->firstOrFail()->updateOrFail(['status' => 'COMPLETED']);
            $this->audit->record('consultations', 'finalized', $consultation, null, $consultation->toArray());

            return $consultation->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function amend(Consultation $consultation, User $actor, array $data): ConsultationAmendment
    {
        return DB::transaction(function () use ($consultation, $actor, $data): ConsultationAmendment {
            $consultation = Consultation::query()->lockForUpdate()->findOrFail($consultation->id);
            if ($consultation->status !== 'FINALIZED') {
                throw ValidationException::withMessages(['consultation' => 'Only a finalized consultation can be amended.']);
            }
            $amendment = $consultation->amendments()->create([...$data, 'amended_by' => $actor->id, 'amended_at' => now()]);
            $this->audit->record('consultation_amendments', 'created', $amendment, null, $amendment->toArray());

            return $amendment;
        }, 3);
    }
}
