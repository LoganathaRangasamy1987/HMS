<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\PrivateDocument;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_sign_in_creates_tenant_session_and_sign_out_revokes_it(): void
    {
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();
        $this->post('/login', ['email' => $user->email, 'password' => 'CareDesk@2026!'])->assertRedirect('/dashboard')->assertSessionHas('membership_id', $user->memberships()->first()->id);
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('Lotus Care Hospital')->assertDontSee('Riverbank Hospital');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'signed_in']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'signed_out']);
    }

    public function test_guest_cannot_read_the_api_and_json_is_forced(): void
    {
        $this->get('/api/v1/staff')->assertUnauthorized()->assertJsonMissingPath('data');
        $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_incorrect_and_disabled_credentials_are_rejected(): void
    {
        $this->post('/login', ['email' => 'admin@lotus.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        User::where('email', 'admin@lotus.test')->update(['status' => 'inactive']);
        $this->post('/login', ['email' => 'admin@lotus.test', 'password' => 'CareDesk@2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_an_active_membership(): void
    {
        $user = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $user->memberships()->update(['status' => 'inactive']);
        $this->post('/login', ['email' => $user->email, 'password' => 'CareDesk@2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_repeated_failed_login_is_throttled(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'admin@lotus.test', 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@lotus.test', 'password' => 'CareDesk@2026!'])->assertUnprocessable()->assertJsonPath('errors.email.0', 'Too many sign-in attempts. Try again in 60 seconds.');
        $this->assertGuest();
    }

    public function test_branch_switch_requires_own_active_membership(): void
    {
        $user = $this->signIn();
        $second = $user->memberships()->whereHas('branch', fn ($q) => $q->where('code', 'CHN'))->firstOrFail();
        $this->postJson('/api/v1/context', ['membership_id' => $second->id])->assertOk()->assertSessionHas('membership_id', $second->id);
        $foreign = User::where('email', 'admin@river.test')->firstOrFail()->memberships()->firstOrFail();
        $this->postJson('/api/v1/context', ['membership_id' => $foreign->id])->assertNotFound()->assertSessionHas('membership_id', $second->id);
        $second->update(['status' => 'inactive']);
        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_password_reset_sends_a_notification_without_exposing_account_existence(): void
    {
        Notification::fake();
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();
        $known = $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $message = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@lotus.test'])->assertSessionHas('status', $message);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'password_reset']);
    }

    public function test_password_reset_token_works_once_and_password_is_hashed(): void
    {
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();
        $token = Password::createToken($user);
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'Changed-Secure-2026!', 'password_confirmation' => 'Changed-Secure-2026!'];
        $this->post('/reset-password', $data)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset', 'user_id' => $user->id]);
        $log = AuditLog::where('action', 'password_reset')->firstOrFail()->toJson();
        $this->assertStringNotContainsString($token, $log);
        $this->assertStringNotContainsString($data['password'], $log);
    }

    public function test_password_reset_rejects_expired_tokens(): void
    {
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Changed-Secure-2026!', 'password_confirmation' => 'Changed-Secure-2026!'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('CareDesk@2026!', $user->fresh()->password));
    }

    public function test_private_document_download_is_scoped_to_hospital_and_branch(): void
    {
        Storage::fake('local');
        $user = $this->signIn();
        $upload = $this->postJson('/api/v1/documents', ['file' => UploadedFile::fake()->create('registration.pdf', 10, 'application/pdf')])->assertCreated()->assertJsonMissingPath('data.path');
        $id = $upload->json('data.id');
        $document = PrivateDocument::findOrFail($id);
        Storage::disk('local')->assertExists($document->path);
        $this->get('/documents/'.$id)->assertDownload('registration.pdf');
        $this->get('/storage/'.$document->path)->assertNotFound();
        $second = $user->memberships()->whereHas('branch', fn ($q) => $q->where('code', 'CHN'))->firstOrFail();
        $this->withSession(['membership_id' => $second->id])->get('/documents/'.$id)->assertNotFound();
        $this->signIn('admin@river.test');
        $this->get('/documents/'.$id)->assertNotFound();
    }

    public function test_private_upload_blocks_executable_files_and_nonadmins(): void
    {
        Storage::fake('local');
        $this->signIn();
        $this->postJson('/api/v1/documents', ['file' => UploadedFile::fake()->create('payload.php', 1, 'application/x-httpd-php')])->assertUnprocessable();
        $this->signIn('doctor@lotus.test');
        $this->postJson('/api/v1/documents', ['file' => UploadedFile::fake()->create('registration.pdf', 1, 'application/pdf')])->assertForbidden();
        $this->assertDatabaseCount('private_documents', 0);
    }

    public function test_database_rejects_cross_hospital_membership_even_outside_http(): void
    {
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();
        $foreignBranch = Branch::whereHas('hospital', fn ($q) => $q->where('code', 'RIVER'))->firstOrFail();
        $this->expectException(QueryException::class);
        Membership::create(['hospital_id' => $user->hospital_id, 'branch_id' => $foreignBranch->id, 'user_id' => $user->id, 'role_id' => $user->memberships()->first()->role_id, 'status' => 'active']);
    }

    private function signIn(string $email = 'admin@lotus.test'): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $user->activeMemberships()->firstOrFail()->id, 'password_hash_web' => $user->getAuthPassword()]);

        return $user;
    }
}
