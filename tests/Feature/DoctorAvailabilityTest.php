<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_adds_weekly_hours_closure_and_exception(): void
    {
        $this->signIn('admin@lotus.test');
        $profile = $this->profile();
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/schedules", $this->schedule())->assertCreated()->assertJsonPath('data.capacity_per_slot', 2);
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/closures", ['type' => 'leave', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-03', 'reason' => 'Approved leave'])->assertCreated();
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/exceptions", ['date' => '2026-10-05', 'availability' => 'available', 'starts_at' => '14:00', 'ends_at' => '16:00', 'slot_duration_minutes' => 20, 'capacity_per_slot' => 3, 'reason' => 'Replacement clinic'])->assertCreated();
        $this->getJson("/api/v1/doctors/{$profile->id}/availability")->assertOk()->assertJsonCount(1, 'data.schedules')->assertJsonPath('data.branch.timezone', 'Asia/Kolkata');
    }

    public function test_overlapping_weekly_hours_and_invalid_ranges_are_rejected(): void
    {
        $this->signIn('admin@lotus.test');
        $profile = $this->profile();
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/schedules", $this->schedule())->assertCreated();
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/schedules", [...$this->schedule(), 'starts_at' => '10:00', 'ends_at' => '11:00'])->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/closures", ['type' => 'leave', 'starts_on' => '2026-10-03', 'ends_on' => '2026-10-01', 'reason' => 'Bad'])->assertUnprocessable()->assertJsonValidationErrors('ends_on');
    }

    public function test_doctor_manages_only_own_profile_while_reception_is_read_only(): void
    {
        $profile = $this->profile();
        $this->signIn('doctor@lotus.test');
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/schedules", $this->schedule())->assertCreated();
        $other = DoctorProfile::factory()->create(['hospital_id' => $profile->hospital_id, 'branch_id' => $profile->branch_id, 'department_id' => $profile->department_id, 'registration_number' => 'OTHER-1']);
        $this->getJson("/api/v1/doctors/{$other->id}/availability")->assertForbidden();
        $this->postJson("/api/v1/doctors/{$other->id}/availability/schedules", $this->schedule())->assertForbidden();
        $this->signIn('reception@lotus.test');
        $this->getJson("/api/v1/doctors/{$profile->id}/availability")->assertOk();
        $this->postJson("/api/v1/doctors/{$profile->id}/availability/schedules", $this->schedule())->assertForbidden();
    }

    public function test_unavailable_exception_clears_slot_fields_and_upserts_by_date(): void
    {
        $this->signIn('admin@lotus.test');
        $profile = $this->profile();
        $path = "/api/v1/doctors/{$profile->id}/availability/exceptions";
        $this->postJson($path, ['date' => '2026-12-25', 'availability' => 'unavailable', 'reason' => 'Holiday'])->assertCreated()->assertJsonPath('data.starts_at', null);
        $this->postJson($path, ['date' => '2026-12-25', 'availability' => 'unavailable', 'reason' => 'Hospital holiday'])->assertOk();
        $this->assertDatabaseCount('doctor_schedule_exceptions', 1);
        $this->assertDatabaseHas('doctor_schedule_exceptions', ['reason' => 'Hospital holiday', 'starts_at' => null]);
    }

    public function test_foreign_hospital_profile_is_not_visible(): void
    {
        $this->signIn('admin@lotus.test');
        $foreign = DoctorProfile::factory()->create();
        $this->getJson("/api/v1/doctors/{$foreign->id}/availability")->assertNotFound();
    }

    private function profile(): DoctorProfile
    {
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $branch = Branch::where('hospital_id', $doctor->hospital_id)->where('code', 'CBE')->firstOrFail();

        return DoctorProfile::create(['hospital_id' => $doctor->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctor->id, 'department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'TNMC-SCHEDULE', 'qualification' => 'MBBS', 'specialization' => 'General', 'consultation_fee' => 500, 'status' => 'active']);
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($q) => $q->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }

    private function schedule(): array
    {
        return ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => 2, 'status' => 'active'];
    }
}
