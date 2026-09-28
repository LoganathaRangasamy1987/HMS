<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;
use OverflowException;

class PatientIdentityService
{
    /**
     * Internal creation boundary. HTTP callers must supply authorized tenant context.
     *
     * @param  array<string, mixed>  $demographics
     */
    public function create(Hospital $hospital, Branch $registrationBranch, array $demographics, ?User $registeredBy = null): Patient
    {
        $validated = $this->validateDemographics($demographics);

        return DB::transaction(function () use ($hospital, $registrationBranch, $registeredBy, $validated): Patient {
            $owner = Hospital::query()->whereKey($hospital->getKey())->lockForUpdate()->first();
            if (! $owner || $owner->status !== 'active') {
                throw ValidationException::withMessages(['hospital_id' => 'An active hospital is required.']);
            }

            $branch = Branch::query()->whereKey($registrationBranch->getKey())
                ->where('hospital_id', $owner->id)->where('status', 'active')->first();
            if (! $branch) {
                throw ValidationException::withMessages(['branch_id' => 'Select an active branch in this hospital.']);
            }

            if ($registeredBy && ! User::query()->whereKey($registeredBy->getKey())
                ->where('hospital_id', $owner->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['registered_by' => 'The registering staff member must be active in this hospital.']);
            }

            $year = now('Asia/Kolkata')->year;
            $sequence = DB::table('patient_number_sequences')->where('hospital_id', $owner->id)->where('year', $year);
            $lastNumber = $sequence->lockForUpdate()->value('last_number');
            if ($lastNumber === null) {
                DB::table('patient_number_sequences')->insert(['hospital_id' => $owner->id, 'year' => $year, 'last_number' => 0]);
                $lastNumber = 0;
            }
            if ((int) $lastNumber >= PHP_INT_MAX) {
                throw new OverflowException('The hospital patient number sequence is exhausted.');
            }

            $number = (int) $lastNumber + 1;
            $sequence->update(['last_number' => $number]);

            $patient = new Patient($validated);
            $patient->forceFill([
                'hospital_id' => $owner->id,
                'branch_id' => $branch->id,
                'uhid' => sprintf('HSP-%d-%d-%06d', $owner->id, $year, $number),
                'registered_by' => $registeredBy?->getKey(),
            ]);
            if (! $patient->save()) {
                throw new LogicException('Patient creation was cancelled before it could be saved.');
            }

            return $patient;
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $demographics
     * @return array<string, mixed>
     */
    public function validateDemographics(array $demographics): array
    {
        $identityFields = array_intersect(['id', 'hospital_id', 'branch_id', 'uhid', 'registered_by'], array_keys($demographics));
        if ($identityFields !== []) {
            throw ValidationException::withMessages(array_fill_keys($identityFields, 'Patient identity is assigned by the application.'));
        }

        $validated = Validator::make($demographics, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth_unknown' => ['sometimes', 'boolean'],
            'date_of_birth' => ['required_unless:date_of_birth_unknown,true', 'prohibited_if:date_of_birth_unknown,true', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Kolkata')->toDateString()],
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'unknown'])],
            'mobile' => ['required_without_all:email,emergency_contact_mobile', 'nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_mobile' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ])->validate();

        return [...$validated, 'date_of_birth_unknown' => (bool) ($validated['date_of_birth_unknown'] ?? false), 'status' => $validated['status'] ?? 'active'];
    }
}
