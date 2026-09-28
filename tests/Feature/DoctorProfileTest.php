<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_creates_and_updates_branch_consistent_profile_with_audit(): void
    {
        $this->signIn('admin@lotus.test');
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $branch = $this->branch();
        $department = Department::where('branch_id', $branch->id)->firstOrFail();
        $response = $this->postJson('/api/v1/doctors', $this->payload($doctor, $branch, $department))->assertCreated()->assertJsonPath('data.consultation_fee', '500.00');
        $profile = DoctorProfile::findOrFail($response->json('data.id'));
        $this->putJson('/api/v1/doctors/'.$profile->id, [...$this->payload($doctor, $branch, $department), 'specialization' => 'Internal Medicine', 'consultation_fee' => '650.50'])->assertOk()->assertJsonPath('data.specialization', 'Internal Medicine');
        $this->assertSame(2, AuditLog::where('module', 'doctor_profiles')->where('record_id', $profile->id)->count());
    }

    public function test_profile_rejects_non_doctor_membership_and_department_from_another_branch(): void
    {
        $this->signIn('admin@lotus.test');
        $reception = User::where('email', 'reception@lotus.test')->firstOrFail();
        $branch = $this->branch();
        $otherDepartment = Department::where('branch_id', $this->branch('CHN')->id)->firstOrFail();
        $this->postJson('/api/v1/doctors', $this->payload($reception, $branch, $otherDepartment))->assertUnprocessable();
        $this->assertSame(0, DoctorProfile::count());
    }

    public function test_registration_is_unique_per_hospital_and_foreign_inputs_are_rejected(): void
    {
        $this->signIn('admin@lotus.test');
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $branch = $this->branch();
        $department = Department::where('branch_id', $branch->id)->firstOrFail();
        $this->postJson('/api/v1/doctors', $this->payload($doctor, $branch, $department))->assertCreated();
        $this->postJson('/api/v1/doctors', $this->payload($doctor, $branch, $department))->assertUnprocessable()->assertJsonValidationErrors('registration_number');
        $foreignBranch = $this->branch('SLM', 'RIVER');
        $foreignDepartment = Department::where('branch_id', $foreignBranch->id)->firstOrFail();
        $this->postJson('/api/v1/doctors', $this->payload(User::where('email', 'admin@river.test')->firstOrFail(), $foreignBranch, $foreignDepartment))->assertUnprocessable();
    }

    public function test_all_roles_view_profiles_but_only_administrator_manages_them(): void
    {
        foreach (['reception@lotus.test', 'doctor@lotus.test'] as $email) {
            $this->signIn($email);
            $this->getJson('/api/v1/doctors')->assertOk();
            $this->get('/doctors/create')->assertForbidden();
            $this->postJson('/api/v1/doctors', [])->assertForbidden();
        }
    }

    public function test_doctor_directory_contains_only_the_signed_in_doctors_active_branch_profile(): void
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = $this->branch();
        $department = Department::where('branch_id', $branch->id)->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $ownProfile = DoctorProfile::forceCreate([...$this->profileAttributes($doctor, $branch, $department), 'registration_number' => 'OWN-DOCTOR']);
        $otherDoctor = User::factory()->create(['hospital_id' => $hospital->id]);
        Membership::create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $otherDoctor->id, 'role_id' => Role::where('name', 'DOCTOR')->value('id'), 'status' => 'active']);
        DoctorProfile::forceCreate([...$this->profileAttributes($otherDoctor, $branch, $department), 'registration_number' => 'OTHER-DOCTOR']);

        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownProfile->id)
            ->assertJsonMissing(['registration_number' => 'OTHER-DOCTOR']);
    }

    public function test_other_hospital_profile_is_not_listed_or_editable(): void
    {
        $river = Hospital::where('code', 'RIVER')->firstOrFail();
        $foreign = DoctorProfile::forceCreate(['hospital_id' => $river->id, 'branch_id' => $this->branch('SLM', 'RIVER')->id, 'user_id' => User::where('email', 'admin@river.test')->value('id'), 'department_id' => Department::where('hospital_id', $river->id)->value('id'), 'registration_number' => 'RIVER-1', 'qualification' => 'MBBS', 'specialization' => 'General', 'consultation_fee' => 100, 'status' => 'active']);
        $this->signIn('admin@lotus.test');
        $this->getJson('/api/v1/doctors')->assertOk()->assertJsonMissing(['registration_number' => 'RIVER-1']);
        $this->get('/doctors/'.$foreign->id.'/edit')->assertNotFound();
        $this->putJson('/api/v1/doctors/'.$foreign->id, [])->assertNotFound();
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($q) => $q->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }

    private function branch(string $code = 'CBE', string $hospital = 'LOTUS'): Branch
    {
        return Branch::where('hospital_id', Hospital::where('code', $hospital)->value('id'))->where('code', $code)->firstOrFail();
    }

    private function payload(User $doctor, Branch $branch, Department $department): array
    {
        return ['user_id' => $doctor->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'registration_number' => 'TNMC-12345', 'qualification' => 'MBBS, MD', 'specialization' => 'General Medicine', 'consultation_fee' => '500.00', 'status' => 'active'];
    }

    private function profileAttributes(User $doctor, Branch $branch, Department $department): array
    {
        return [...$this->payload($doctor, $branch, $department), 'hospital_id' => $branch->hospital_id];
    }
}
