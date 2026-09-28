<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function book(int $hospitalId, int $branchId, Patient $patient, DoctorProfile $doctor, User $actor, array $data): Appointment
    {
        return DB::transaction(function () use ($hospitalId, $branchId, $patient, $doctor, $actor, $data): Appointment {
            $lockedDoctor = DoctorProfile::whereKey($doctor->id)->lockForUpdate()->firstOrFail();
            if ($data['request_key'] ?? null) {
                $existing = Appointment::where('hospital_id', $hospitalId)->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    if ($existing->branch_id !== $branchId || $existing->patient_id !== $patient->id || $existing->doctor_profile_id !== $doctor->id || $existing->appointment_date->format('Y-m-d') !== $data['appointment_date'] || substr($existing->starts_at, 0, 5) !== $data['starts_at'] || $existing->type !== $data['type'] || $existing->reason !== ($data['reason'] ?? null)) {
                        throw ValidationException::withMessages(['request_key' => 'This request key belongs to a different appointment.']);
                    }

                    return $existing;
                }
            }
            [$endsAt, $capacity] = $this->validateSlot($lockedDoctor, $data['appointment_date'], $data['starts_at']);
            $active = Appointment::where('doctor_profile_id', $doctor->id)->whereDate('appointment_date', $data['appointment_date'])->where('starts_at', $data['starts_at'])->whereNotIn('status', ['CANCELLED'])->lockForUpdate()->count();
            if ($active >= $capacity) {
                throw ValidationException::withMessages(['starts_at' => 'This appointment slot is full.']);
            }
            $token = ((int) Appointment::where('branch_id', $branchId)->where('doctor_profile_id', $doctor->id)->whereDate('appointment_date', $data['appointment_date'])->lockForUpdate()->max('token_number')) + 1;
            $appointment = Appointment::create([...$data, 'hospital_id' => $hospitalId, 'branch_id' => $branchId, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'ends_at' => $endsAt, 'token_number' => $token, 'status' => 'BOOKED', 'created_by' => $actor->id]);
            $this->audit->record('appointments', 'created', $appointment, null, $appointment->toArray());

            return $appointment;
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function reschedule(Appointment $appointment, DoctorProfile $doctor, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $doctor, $data): Appointment {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $lockedDoctor = DoctorProfile::whereKey($doctor->id)->lockForUpdate()->firstOrFail();
            if (! in_array($appointment->status, ['BOOKED', 'CONFIRMED'], true)) {
                throw ValidationException::withMessages(['status' => 'Only booked or confirmed appointments can be rescheduled.']);
            }
            [$endsAt, $capacity] = $this->validateSlot($lockedDoctor, $data['appointment_date'], $data['starts_at']);
            $active = Appointment::where('doctor_profile_id', $doctor->id)->whereDate('appointment_date', $data['appointment_date'])->where('starts_at', $data['starts_at'])->whereNotIn('status', ['CANCELLED'])->whereKeyNot($appointment->id)->lockForUpdate()->count();
            if ($active >= $capacity) {
                throw ValidationException::withMessages(['starts_at' => 'This appointment slot is full.']);
            }
            $old = $appointment->toArray();
            $token = ((int) Appointment::where('branch_id', $appointment->branch_id)->where('doctor_profile_id', $doctor->id)->whereDate('appointment_date', $data['appointment_date'])->whereKeyNot($appointment->id)->lockForUpdate()->max('token_number')) + 1;
            $appointment->updateOrFail([...$data, 'doctor_profile_id' => $doctor->id, 'ends_at' => $endsAt, 'token_number' => $token]);
            $this->audit->record('appointments', 'rescheduled', $appointment, $old, $appointment->fresh()->toArray());

            return $appointment->fresh();
        }, attempts: 5);
    }

    public function cancel(Appointment $appointment, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $reason): Appointment {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if (! in_array($appointment->status, ['BOOKED', 'CONFIRMED', 'CHECKED_IN', 'WAITING'], true)) {
                throw ValidationException::withMessages(['status' => 'This appointment can no longer be cancelled.']);
            }
            $old = $appointment->toArray();
            $appointment->updateOrFail(['status' => 'CANCELLED', 'cancellation_reason' => $reason]);
            $this->audit->record('appointments', 'cancelled', $appointment, $old, $appointment->fresh()->toArray());

            return $appointment->fresh();
        }, attempts: 5);
    }

    public function transition(Appointment $appointment, string $nextStatus, bool $doctorActor): Appointment
    {
        return DB::transaction(function () use ($appointment, $nextStatus, $doctorActor): Appointment {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $allowed = $doctorActor
                ? ['WAITING' => ['CONSULTING'], 'CONSULTING' => ['COMPLETED']]
                : ['BOOKED' => ['CONFIRMED', 'CHECKED_IN', 'NO_SHOW'], 'CONFIRMED' => ['CHECKED_IN', 'NO_SHOW'], 'CHECKED_IN' => ['WAITING']];
            if (! in_array($nextStatus, $allowed[$appointment->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "{$appointment->status} cannot move to {$nextStatus}."]);
            }
            if ($nextStatus === 'NO_SHOW') {
                $timezone = $appointment->branch->timezone;
                $scheduled = CarbonImmutable::createFromFormat('Y-m-d H:i', $appointment->appointment_date->format('Y-m-d').' '.substr($appointment->starts_at, 0, 5), $timezone);
                if ($scheduled->isFuture()) {
                    throw ValidationException::withMessages(['status' => 'A future appointment cannot be marked as no-show.']);
                }
            }
            $old = $appointment->toArray();
            $appointment->updateOrFail(['status' => $nextStatus]);
            $this->audit->record('appointments', 'status_changed', $appointment, $old, $appointment->fresh()->toArray());

            return $appointment->fresh();
        }, attempts: 5);
    }

    /** @return array<int, array{date: string, label: string, slots: array<int, array{time: string, ends_at: string, remaining: int, capacity: int}>}> */
    public function availableSlots(DoctorProfile $doctor, string $from, int $days): array
    {
        $doctor->loadMissing(['branch', 'schedules', 'unavailabilities', 'scheduleExceptions']);
        $timezone = $doctor->branch->timezone;
        $today = now($timezone)->toImmutable()->startOfDay();
        $firstDate = CarbonImmutable::createFromFormat('Y-m-d', $from, $timezone)->startOfDay();
        if ($firstDate->isBefore($today)) {
            $firstDate = $today;
        }
        $lastDate = $firstDate->addDays($days - 1);
        $appointments = Appointment::where('doctor_profile_id', $doctor->id)
            ->whereDate('appointment_date', '>=', $firstDate->toDateString())
            ->whereDate('appointment_date', '<=', $lastDate->toDateString())
            ->whereNotIn('status', ['CANCELLED'])
            ->get(['appointment_date', 'starts_at'])
            ->countBy(fn (Appointment $appointment): string => $appointment->appointment_date->format('Y-m-d').'|'.substr($appointment->starts_at, 0, 5));
        $dates = [];

        for ($date = $firstDate; $date->lte($lastDate); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $exception = $doctor->scheduleExceptions->first(fn ($item): bool => $item->date->format('Y-m-d') === $dateString);
            if ($exception?->availability === 'unavailable') {
                continue;
            }
            if (! $exception && $doctor->unavailabilities->contains(fn ($item): bool => $item->starts_on->format('Y-m-d') <= $dateString && $item->ends_on->format('Y-m-d') >= $dateString)) {
                continue;
            }
            $windows = $exception
                ? collect([$exception])
                : $doctor->schedules->where('day_of_week', $date->dayOfWeek)->where('status', 'active')->values();
            $slots = [];
            foreach ($windows as $window) {
                if (! $window->starts_at || ! $window->ends_at || ! $window->slot_duration_minutes || ! $window->capacity_per_slot) {
                    continue;
                }
                $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $dateString.' '.substr($window->starts_at, 0, 5), $timezone);
                $windowEnd = CarbonImmutable::createFromFormat('Y-m-d H:i', $dateString.' '.substr($window->ends_at, 0, 5), $timezone);
                while ($start->addMinutes($window->slot_duration_minutes)->lte($windowEnd)) {
                    $end = $start->addMinutes($window->slot_duration_minutes);
                    $time = $start->format('H:i');
                    $capacity = (int) $window->capacity_per_slot;
                    $remaining = $capacity - (int) $appointments->get("{$dateString}|{$time}", 0);
                    if ($remaining > 0 && $start->isFuture()) {
                        $slots[] = ['time' => $time, 'ends_at' => $end->format('H:i'), 'remaining' => $remaining, 'capacity' => $capacity];
                    }
                    $start = $end;
                }
            }
            if ($slots !== []) {
                $dates[] = ['date' => $dateString, 'label' => $date->format('D, d M Y'), 'slots' => $slots];
            }
        }

        return $dates;
    }

    /** @return array{string, int} */
    private function validateSlot(DoctorProfile $doctor, string $date, string $startsAt): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $doctor->branch->timezone)->startOfDay();
        if ($day->isBefore(now($doctor->branch->timezone)->startOfDay())) {
            throw ValidationException::withMessages(['appointment_date' => 'Appointments cannot be booked in the past.']);
        }
        $chosenDateTime = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$startsAt}", $doctor->branch->timezone);
        if (! $chosenDateTime->isFuture()) {
            throw ValidationException::withMessages(['starts_at' => 'Appointments must use a future time slot.']);
        }
        $exception = $doctor->scheduleExceptions()->whereDate('date', $date)->first();
        if ($exception?->availability === 'unavailable') {
            throw ValidationException::withMessages(['appointment_date' => 'The doctor is unavailable on this date.']);
        }
        if (! $exception && $doctor->unavailabilities()->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['appointment_date' => 'The doctor is on holiday or leave.']);
        }
        $window = $exception ?: $doctor->schedules()->where('day_of_week', $day->dayOfWeek)->where('status', 'active')->where('starts_at', '<=', $startsAt)->where('ends_at', '>', $startsAt)->first();
        if (! $window || ! $window->starts_at || ! $window->ends_at) {
            throw ValidationException::withMessages(['starts_at' => 'The selected time is outside the doctor’s availability.']);
        }
        $start = CarbonImmutable::createFromFormat('H:i', substr($window->starts_at, 0, 5));
        $chosen = CarbonImmutable::createFromFormat('H:i', $startsAt);
        if ($start->diffInMinutes($chosen) % $window->slot_duration_minutes !== 0) {
            throw ValidationException::withMessages(['starts_at' => 'Select a valid slot boundary.']);
        }
        $ends = $chosen->addMinutes($window->slot_duration_minutes);
        if ($ends->format('H:i:s') > $window->ends_at) {
            throw ValidationException::withMessages(['starts_at' => 'The slot extends beyond the doctor’s availability.']);
        }

        return [$ends->format('H:i'), (int) $window->capacity_per_slot];
    }
}
