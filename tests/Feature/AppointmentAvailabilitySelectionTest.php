<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentAvailabilitySelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reception_receives_dates_and_only_slots_with_remaining_capacity(): void
    {
        $profile = $this->profile();
        $profile->schedules()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'slot_duration_minutes' => 30, 'capacity_per_slot' => 1, 'status' => 'active']);
        Appointment::factory()->create([
            'hospital_id' => $profile->hospital_id, 'branch_id' => $profile->branch_id,
            'patient_id' => Patient::where('hospital_id', $profile->hospital_id)->value('id'), 'doctor_profile_id' => $profile->id,
            'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'ends_at' => '09:30', 'status' => 'BOOKED',
        ]);
        $this->signIn('reception@lotus.test');

        $this->getJson("/api/v1/appointments/available-slots?doctor_profile_id={$profile->id}&from=2026-10-05&days=1")
            ->assertOk()->assertJsonPath('data.doctor.id', $profile->id)->assertJsonPath('data.timezone', 'Asia/Kolkata')
            ->assertJsonCount(1, 'data.dates')->assertJsonPath('data.dates.0.date', '2026-10-05')
            ->assertJsonCount(1, 'data.dates.0.slots')->assertJsonPath('data.dates.0.slots.0.time', '09:30')
            ->assertJsonPath('data.dates.0.slots.0.remaining', 1);
    }

    public function test_exceptions_and_leave_control_the_dates_shown_to_reception(): void
    {
        $profile = $this->profile();
        $profile->schedules()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'slot_duration_minutes' => 30, 'capacity_per_slot' => 2, 'status' => 'active']);
        $profile->unavailabilities()->create(['type' => 'leave', 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-05', 'reason' => 'Leave']);
        $profile->scheduleExceptions()->create(['date' => '2026-10-06', 'availability' => 'available', 'starts_at' => '14:00', 'ends_at' => '15:00', 'slot_duration_minutes' => 30, 'capacity_per_slot' => 1, 'reason' => 'Special clinic']);
        $this->signIn('reception@lotus.test');

        $this->getJson("/api/v1/appointments/available-slots?doctor_profile_id={$profile->id}&from=2026-10-05&days=2")
            ->assertOk()->assertJsonCount(1, 'data.dates')->assertJsonPath('data.dates.0.date', '2026-10-06')
            ->assertJsonPath('data.dates.0.slots.0.time', '14:00')->assertJsonPath('data.dates.0.slots.1.time', '14:30');
    }

    public function test_slot_picker_is_tenant_scoped_and_requires_booking_permission(): void
    {
        $profile = $this->profile();
        $foreign = DoctorProfile::factory()->create();
        $this->signIn('reception@lotus.test');
        $this->get('/appointments')->assertOk()->assertSee('data-appointment-booking', false)->assertSee('data-appointment-doctor', false)->assertSee('data-appointment-date', false);
        $this->getJson("/api/v1/appointments/available-slots?doctor_profile_id={$foreign->id}")->assertNotFound();
        $this->signIn('doctor@lotus.test');
        $this->getJson("/api/v1/appointments/available-slots?doctor_profile_id={$profile->id}")->assertForbidden();
    }

    private function profile(): DoctorProfile
    {
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $branch = Branch::where('hospital_id', $doctor->hospital_id)->where('code', 'CBE')->firstOrFail();

        $profile = DoctorProfile::firstOrCreate(
            ['hospital_id' => $doctor->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctor->id],
            ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'SLOT-PICKER', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'],
        );
        $profile->schedules()->delete();
        $profile->unavailabilities()->delete();
        $profile->scheduleExceptions()->delete();

        return $profile;
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
