<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guests_cannot_read_organization_data(): void
    {
        foreach (['branches', 'departments', 'staff', 'organization', 'audit'] as $resource) {
            $this->getJson('/api/v1/'.$resource)->assertUnauthorized();
        }
    }

    public function test_administrator_lists_and_searches_are_confined_to_their_hospital(): void
    {
        $this->signIn();
        $hospitalId = $this->hospital()->id;

        foreach (['branches', 'departments', 'staff'] as $resource) {
            $response = $this->getJson('/api/v1/'.$resource)->assertOk();
            $this->assertNotEmpty($response->json('data'), 'The seeded '.$resource.' list should not be empty.');
            foreach ($response->json('data') as $record) {
                $this->assertSame($hospitalId, $record['hospital_id']);
            }
        }

        $this->getJson('/api/v1/staff?q=admin%40river.test')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/organization')->assertOk()->assertJsonPath('data.id', $hospitalId);
    }

    public function test_foreign_record_ids_cannot_be_read_or_changed(): void
    {
        $this->signIn();
        $foreignHospital = $this->hospital('RIVER');
        $foreignBranch = Branch::where('hospital_id', $foreignHospital->id)->firstOrFail();
        $foreignDepartment = Department::where('hospital_id', $foreignHospital->id)->firstOrFail();
        $foreignStaff = User::where('email', 'admin@river.test')->firstOrFail();

        foreach (['branches' => $foreignBranch, 'departments' => $foreignDepartment, 'staff' => $foreignStaff] as $resource => $record) {
            $original = $record->getAttributes();
            $this->getJson('/api/v1/'.$resource.'/'.$record->id)->assertNotFound();
            $this->putJson('/api/v1/'.$resource.'/'.$record->id, ['name' => 'Changed outside tenant'])->assertNotFound();
            $this->assertSame($original, $record->fresh()->getAttributes());
        }
    }

    public function test_doctor_and_receptionist_cannot_manage_organization_even_with_direct_api_calls(): void
    {
        foreach (['doctor@lotus.test', 'reception@lotus.test'] as $email) {
            $this->signIn($email);
            foreach (['branches', 'departments', 'staff', 'organization', 'audit'] as $resource) {
                $this->getJson('/api/v1/'.$resource)->assertForbidden();
            }
            $this->postJson('/api/v1/branches', $this->branchPayload())->assertForbidden();
            $this->putJson('/api/v1/organization', ['name' => 'Unauthorized change'])->assertForbidden();
        }

        $this->assertDatabaseMissing('branches', ['code' => 'TEST']);
    }

    public function test_creating_and_editing_a_branch_uses_authenticated_hospital_and_records_changes(): void
    {
        $this->signIn();
        $payload = [...$this->branchPayload(), 'hospital_id' => $this->hospital('RIVER')->id];
        $response = $this->postJson('/api/v1/branches', $payload)->assertCreated();
        $branchId = $response->json('data.id');
        $response->assertJsonPath('data.hospital_id', $this->hospital()->id);

        $this->putJson('/api/v1/branches/'.$branchId, [...$payload, 'name' => 'Salem Centre'])
            ->assertOk()->assertJsonPath('data.name', 'Salem Centre');
        $log = AuditLog::where('hospital_id', $this->hospital()->id)->where('module', 'branches')
            ->where('record_id', $branchId)->where('action', 'updated')->firstOrFail();
        $this->assertSame('Test Branch', $log->old_values['name']);
        $this->assertSame('Salem Centre', $log->new_values['name']);
        $this->assertSame(User::where('email', 'admin@lotus.test')->value('id'), $log->user_id);
    }

    public function test_departments_reject_foreign_and_inactive_branches(): void
    {
        $this->signIn();
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        $inactiveBranch = $this->makeBranch(['code' => 'CLOSED', 'status' => 'inactive']);

        foreach ([$foreignBranch, $inactiveBranch] as $branch) {
            $this->postJson('/api/v1/departments', ['name' => 'Invalid department', 'branch_id' => $branch->id, 'status' => 'active'])
                ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        }
        $this->assertDatabaseMissing('departments', ['name' => 'Invalid department']);
    }

    public function test_staff_cannot_receive_foreign_branch_or_unsupported_role_memberships(): void
    {
        $this->signIn();
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        $payload = $this->staffPayload();
        $payload['memberships'][0]['branch_id'] = $foreignBranch->id;
        $this->postJson('/api/v1/staff', $payload)->assertUnprocessable()->assertJsonValidationErrors('memberships.0.branch_id');

        $unsupportedRole = Role::firstOrCreate(['name' => 'LAB_TECHNICIAN'], ['label' => 'Lab technician']);
        $payload = $this->staffPayload();
        $payload['memberships'][0]['role_id'] = $unsupportedRole->id;
        $this->postJson('/api/v1/staff', $payload)->assertUnprocessable()->assertJsonValidationErrors('memberships.0.role_id');

        $this->assertDatabaseMissing('users', ['email' => 'new.staff@lotus.test']);
    }

    public function test_staff_memberships_must_be_unique_and_active_staff_need_active_access(): void
    {
        $this->signIn();
        $payload = $this->staffPayload();
        $payload['memberships'][] = $payload['memberships'][0];
        $this->postJson('/api/v1/staff', $payload)->assertUnprocessable()->assertJsonValidationErrors('memberships.0.branch_id');

        $payload = $this->staffPayload();
        $payload['memberships'][0]['status'] = 'inactive';
        $this->postJson('/api/v1/staff', $payload)->assertUnprocessable()->assertJsonValidationErrors('memberships');
        $this->assertDatabaseMissing('users', ['email' => 'new.staff@lotus.test']);
    }

    public function test_staff_passwords_are_hashed_and_never_returned_or_written_to_audit_payloads(): void
    {
        $this->signIn();
        $payload = [...$this->staffPayload(), 'hospital_id' => $this->hospital('RIVER')->id];
        $response = $this->postJson('/api/v1/staff', $payload)->assertCreated()
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token')
            ->assertJsonPath('data.hospital_id', $this->hospital()->id);
        $staff = User::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check($payload['password'], $staff->password));
        $this->assertNotSame($payload['password'], $staff->password);

        $updatedPassword = 'Changed-Password-2026!';
        $this->putJson('/api/v1/staff/'.$staff->id, [...$payload, 'name' => 'Renamed Staff', 'password' => $updatedPassword, 'password_confirmation' => $updatedPassword])
            ->assertOk()->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check($updatedPassword, $staff->fresh()->password));

        $logs = AuditLog::where('module', 'staff')->where('record_id', $staff->id)->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            foreach ([$log->old_values, $log->new_values] as $values) {
                $this->assertArrayNotHasKey('password', $values ?? []);
                $this->assertArrayNotHasKey('password_confirmation', $values ?? []);
                $this->assertArrayNotHasKey('remember_token', $values ?? []);
            }
            $serialized = $log->toJson();
            $this->assertStringNotContainsString($payload['password'], $serialized);
            $this->assertStringNotContainsString($updatedPassword, $serialized);
            $this->assertStringNotContainsString($staff->fresh()->password, $serialized);
        }

        $this->getJson('/api/v1/staff/'.$staff->id)->assertOk()->assertJsonMissingPath('data.password');
        $list = $this->getJson('/api/v1/staff')->assertOk();
        foreach ($list->json('data') as $record) {
            $this->assertArrayNotHasKey('password', $record);
            $this->assertArrayNotHasKey('remember_token', $record);
        }
    }

    public function test_duplicate_email_from_another_hospital_returns_a_generic_validation_error(): void
    {
        $this->signIn();
        $this->postJson('/api/v1/staff', [...$this->staffPayload(), 'email' => 'admin@river.test'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'This email address is unavailable.');
        $this->assertSame(1, User::where('email', 'admin@river.test')->count());
    }

    public function test_administrator_cannot_disable_or_remove_their_current_administrator_access(): void
    {
        $admin = $this->signIn();
        $payload = $this->staffPayloadFor($admin);
        $this->putJson('/api/v1/staff/'.$admin->id, [...$payload, 'status' => 'inactive'])
            ->assertUnprocessable()->assertJsonValidationErrors('memberships');

        $payload['memberships'][0]['role_id'] = Role::where('name', 'DOCTOR')->value('id');
        $this->putJson('/api/v1/staff/'.$admin->id, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('memberships');

        $this->assertSame('active', $admin->fresh()->status);
        $this->assertSame('HOSPITAL_ADMIN', $admin->memberships()->where('branch_id', $this->branch()->id)->firstOrFail()->role->name);
    }

    public function test_current_branch_and_branches_with_active_dependencies_cannot_be_deactivated(): void
    {
        $this->signIn();
        $current = $this->branch();
        $this->putJson('/api/v1/branches/'.$current->id, [...$current->only(['name', 'code', 'city']), 'status' => 'inactive'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $hasDepartment = $this->makeBranch(['code' => 'DEPT']);
        Department::create(['hospital_id' => $this->hospital()->id, 'branch_id' => $hasDepartment->id, 'name' => 'Active Department', 'status' => 'active']);
        $this->putJson('/api/v1/branches/'.$hasDepartment->id, [...$hasDepartment->only(['name', 'code']), 'status' => 'inactive'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $hasStaff = $this->makeBranch(['code' => 'STAFF']);
        Membership::create(['hospital_id' => $this->hospital()->id, 'branch_id' => $hasStaff->id, 'user_id' => User::where('email', 'doctor@lotus.test')->value('id'), 'role_id' => Role::where('name', 'DOCTOR')->value('id'), 'status' => 'active']);
        $this->putJson('/api/v1/branches/'.$hasStaff->id, [...$hasStaff->only(['name', 'code']), 'status' => 'inactive'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        foreach ([$current, $hasDepartment, $hasStaff] as $branch) {
            $this->assertSame('active', $branch->fresh()->status);
        }
    }

    public function test_empty_noncurrent_branch_can_be_deactivated(): void
    {
        $this->signIn();
        $branch = $this->makeBranch(['code' => 'EMPTY']);
        $this->putJson('/api/v1/branches/'.$branch->id, [...$branch->only(['name', 'code']), 'status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'status' => 'inactive']);
    }

    public function test_removed_membership_is_inactive_and_cannot_be_used_on_the_next_request(): void
    {
        $this->signIn();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $oldMembership = $doctor->memberships()->where('branch_id', $this->branch()->id)->firstOrFail();
        $newBranch = $this->branch('CHN');
        $payload = $this->staffPayloadFor($doctor);
        $payload['memberships'][0]['branch_id'] = $newBranch->id;
        $this->putJson('/api/v1/staff/'.$doctor->id, $payload)->assertOk();
        $this->assertDatabaseHas('memberships', ['id' => $oldMembership->id, 'status' => 'inactive']);

        $this->actingAs($doctor)->withSession(['membership_id' => $oldMembership->id, 'password_hash_web' => $doctor->getAuthPassword()]);
        $this->getJson('/api/v1/dashboard')->assertForbidden()->assertJsonMissingPath('stats');
    }

    public function test_disabled_user_loses_access_on_the_next_request(): void
    {
        $doctor = $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/dashboard')->assertOk();
        User::whereKey($doctor->id)->update(['status' => 'inactive']);

        $this->getJson('/api/v1/dashboard')->assertUnauthorized()->assertJsonMissingPath('stats');
    }

    public function test_role_revocation_is_checked_again_on_the_next_request(): void
    {
        $admin = $this->signIn();
        $this->getJson('/api/v1/staff')->assertOk();
        $admin->memberships()->where('branch_id', $this->branch()->id)->update(['role_id' => Role::where('name', 'DOCTOR')->value('id')]);
        $this->getJson('/api/v1/staff')->assertForbidden();
    }

    public function test_dashboard_restricts_nonadministrators_to_their_current_branch_and_hides_audit_activity(): void
    {
        $this->signIn('doctor@lotus.test');
        $branch = $this->branch();
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('stats.branches', 1)
            ->assertJsonPath('stats.departments', Department::where('branch_id', $branch->id)->where('status', 'active')->count())
            ->assertJsonCount(1, 'branches')->assertJsonPath('branches.0.id', $branch->id)
            ->assertJsonCount(0, 'recentActivity');
    }

    public function test_audit_list_does_not_expose_events_from_another_hospital(): void
    {
        $this->signIn();
        $foreignHospital = $this->hospital('RIVER');
        $foreignLog = AuditLog::create([
            'hospital_id' => $foreignHospital->id,
            'branch_id' => Branch::where('hospital_id', $foreignHospital->id)->value('id'),
            'user_id' => User::where('email', 'admin@river.test')->value('id'),
            'module' => 'organization', 'action' => 'updated', 'record_type' => Hospital::class,
            'record_id' => $foreignHospital->id, 'old_values' => ['name' => 'Private hospital information'],
            'new_values' => ['name' => 'Foreign change'], 'ip_address' => '127.0.0.1', 'created_at' => now(),
        ]);
        $this->postJson('/api/v1/branches', $this->branchPayload())->assertCreated();
        $response = $this->getJson('/api/v1/audit')->assertOk();
        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $log) {
            $this->assertSame($this->hospital()->id, $log['hospital_id']);
            $this->assertNotSame($foreignLog->id, $log['id']);
        }
        $response->assertDontSee('Private hospital information');
    }

    public function test_failed_audit_write_rolls_back_staff_and_membership_creation(): void
    {
        $this->signIn();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });
        $membershipCount = Membership::count();
        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit unavailable');

        try {
            $this->postJson('/api/v1/staff', $this->staffPayload());
        } finally {
            $this->assertDatabaseMissing('users', ['email' => 'new.staff@lotus.test']);
            $this->assertSame($membershipCount, Membership::count());
        }
    }

    private function signIn(string $email = 'admin@lotus.test', string $branchCode = 'CBE'): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);

        return $user;
    }

    private function hospital(string $code = 'LOTUS'): Hospital
    {
        return Hospital::where('code', $code)->firstOrFail();
    }

    private function branch(string $code = 'CBE'): Branch
    {
        return Branch::where('hospital_id', $this->hospital()->id)->where('code', $code)->firstOrFail();
    }

    private function makeBranch(array $overrides = []): Branch
    {
        return Branch::create([...$this->branchPayload(), 'hospital_id' => $this->hospital()->id, ...$overrides]);
    }

    private function branchPayload(): array
    {
        return ['name' => 'Test Branch', 'code' => 'TEST', 'city' => 'Salem', 'status' => 'active'];
    }

    private function staffPayload(): array
    {
        return [
            'name' => 'New Staff', 'email' => 'new.staff@lotus.test', 'mobile' => '9000012345',
            'status' => 'active', 'password' => 'Test-Staff-Password-2026!', 'password_confirmation' => 'Test-Staff-Password-2026!',
            'memberships' => [['branch_id' => $this->branch()->id, 'role_id' => Role::where('name', 'RECEPTIONIST')->value('id'), 'status' => 'active']],
        ];
    }

    private function staffPayloadFor(User $user): array
    {
        $membership = $user->memberships()->where('branch_id', $this->branch()->id)->firstOrFail();

        return [
            ...$user->only(['name', 'email', 'mobile', 'status']),
            'memberships' => [['branch_id' => $membership->branch_id, 'role_id' => $membership->role_id, 'status' => 'active']],
        ];
    }
}
