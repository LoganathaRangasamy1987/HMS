<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.lifetime' => 30,
        ]);

        $this->seed();
    }

    public function test_database_session_handler_rejects_payload_after_the_idle_lifetime(): void
    {
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'CareDesk@2026!',
        ])->assertRedirect('/dashboard');
        $sessionCookie = $login->getCookie(config('session.cookie'));

        $sessionId = DB::table('sessions')->where('user_id', $user->id)->value('id');

        $this->assertNotNull($sessionId);
        $this->assertNotNull($sessionCookie);

        DB::table('sessions')->where('id', $sessionId)->update([
            'last_activity' => now()->subMinutes(31)->timestamp,
        ]);
        $this->app['session']->forgetDrivers();
        Auth::forgetGuards();

        $handler = new DatabaseSessionHandler(
            DB::connection(config('session.connection')),
            config('session.table'),
            config('session.lifetime'),
            $this->app,
        );

        $this->assertSame('', $handler->read($sessionId));
    }

    public function test_remembered_user_is_restored_with_an_active_membership_after_session_expiry(): void
    {
        $user = User::where('email', 'admin@lotus.test')->firstOrFail();
        $membership = $user->activeMemberships()->orderBy('id')->firstOrFail();

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'CareDesk@2026!',
            'remember' => true,
        ])->assertRedirect('/dashboard');
        $sessionCookie = $login->getCookie(config('session.cookie'));
        $rememberCookie = $login->getCookie(Auth::guard()->getRecallerName());

        $sessionId = DB::table('sessions')->where('user_id', $user->id)->value('id');

        $this->assertNotNull($sessionId);
        $this->assertNotNull($sessionCookie);
        $this->assertNotNull($rememberCookie);

        DB::table('sessions')->where('id', $sessionId)->update([
            'last_activity' => now()->subMinutes(31)->timestamp,
        ]);
        $this->app['session']->forgetDrivers();
        Auth::forgetGuards();

        $this->withCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get('/dashboard')
            ->assertOk()
            ->assertSessionHas('membership_id', $membership->id)
            ->assertSee('Lotus Care Hospital');

        $this->assertAuthenticatedAs($user);
    }
}
