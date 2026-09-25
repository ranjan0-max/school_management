<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited(): void
    {
        $user = User::factory()->create();

        $this->withSession(['_token' => 'test-token'])->post(route('login.store'), [
            '_token' => 'test-token',
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.succeeded',
            'actor_id' => $user->getKey(),
        ]);
    }

    public function test_failed_login_is_audited_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->withSession(['_token' => 'test-token'])->post(route('login.store'), [
            '_token' => 'test-token',
            'email' => strtoupper($user->email),
            'password' => 'wrong-secret-value',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $log = AuditLog::query()->where('event', 'auth.login.failed')->sole();

        $this->assertSame($user->getKey(), $log->actor_id);
        $this->assertSame(strtolower($user->email), $log->metadata['email'] ?? null);
        $this->assertStringNotContainsString('wrong-secret-value', (string) json_encode($log->toArray()));
    }

    public function test_failed_login_for_unknown_email_has_no_actor(): void
    {
        $this->withSession(['_token' => 'test-token'])->post(route('login.store'), [
            '_token' => 'test-token',
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])->assertSessionHasErrors('email');

        $log = AuditLog::query()->where('event', 'auth.login.failed')->sole();

        $this->assertNull($log->actor_id);
        $this->assertSame('nobody@example.com', $log->metadata['email'] ?? null);
    }

    public function test_rate_limited_login_is_audited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $attempt) {
            $this->withSession(['_token' => 'test-token'])->post(route('login.store'), [
                '_token' => 'test-token',
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertSame(5, AuditLog::query()->where('event', 'auth.login.failed')->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.locked_out',
            'actor_id' => $user->getKey(),
        ]);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post(route('logout'), ['_token' => 'test-token'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.logout',
            'actor_id' => $user->getKey(),
        ]);
    }
}
