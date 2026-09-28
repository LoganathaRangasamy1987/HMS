<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleaseEngineeringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_liveness_and_readiness_endpoints_are_fast_and_report_dependencies(): void
    {
        $this->get('/up')->assertOk();
        $started = hrtime(true);
        $response = $this->getJson('/ready');
        $elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;

        $response->assertOk()->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database.ok', true)->assertJsonPath('checks.cache.ok', true)
            ->assertJsonPath('checks.queue.ok', true)->assertJsonPath('checks.storage.ok', true);
        $this->assertLessThan(2000, $elapsedMilliseconds, 'The local readiness check exceeded two seconds.');
    }

    public function test_release_check_passes_against_a_migrated_application(): void
    {
        $this->artisan('hms:release-check')->assertSuccessful()
            ->expectsOutputToContain('all migrations applied')
            ->expectsOutputToContain('write/read/delete round trip');
    }

    public function test_dashboard_performance_smoke_has_bounded_queries_and_latency(): void
    {
        $this->signIn('admin@lotus.test');
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $started = hrtime(true);
        $this->getJson('/api/v1/dashboard')->assertOk();
        $elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;

        $this->assertLessThanOrEqual(45, $queries, 'The administrator dashboard exceeded its query budget.');
        $this->assertLessThan(3000, $elapsedMilliseconds, 'The local administrator dashboard exceeded three seconds.');
    }

    public function test_backup_commands_reject_unsafe_or_nonpersistent_targets(): void
    {
        $this->artisan('hms:backup-sqlite', ['--name' => '../unsafe.sqlite'])->assertFailed();
        $this->artisan('hms:verify-sqlite-backup', ['name' => '../unsafe.sqlite'])->assertFailed();
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
