<?php

namespace Tests\Feature\Platform;

use App\Enums\SchoolStatus;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_view_audit_logs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('platform.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_school_creation_is_recorded_and_visible_to_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create(['name' => 'Platform Owner']);

        $this->actingAs($admin)->withSession(['_token' => 'test-token'])->post(route('platform.schools.store'), [
            '_token' => 'test-token',
            'name' => 'Audit Public School',
            'status' => SchoolStatus::Active->value,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'currency' => 'INR',
        ])->assertRedirect();

        $school = School::query()->sole();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'school.created',
            'actor_id' => $admin->getKey(),
            'school_id' => $school->getKey(),
        ]);

        $this->actingAs($admin)
            ->get(route('platform.audit-logs.index', ['search' => 'Audit Public']))
            ->assertOk()
            ->assertSee('Platform Owner')
            ->assertSee('Audit Public School');
    }

    public function test_invalid_request_id_header_does_not_break_audited_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-Request-ID', str_repeat('x', 500))
            ->post(route('platform.schools.store'), [
                '_token' => 'test-token',
                'name' => 'Header Test School',
                'status' => SchoolStatus::Active->value,
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'currency' => 'INR',
            ])->assertRedirect();

        $this->assertNull(AuditLog::query()->where('event', 'school.created')->sole()->request_id);
    }

    public function test_audit_logs_cannot_be_updated_or_deleted(): void
    {
        $log = AuditLog::query()->create(['event' => 'test.event', 'created_at' => now()]);

        try {
            $log->update(['event' => 'tampered']);
            $this->fail('Audit log update was not blocked.');
        } catch (LogicException) {
        }

        try {
            $log->delete();
            $this->fail('Audit log delete was not blocked.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->getKey(), 'event' => 'test.event']);
    }
}
