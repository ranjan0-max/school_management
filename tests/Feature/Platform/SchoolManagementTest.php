<?php

namespace Tests\Feature\Platform;

use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('platform.schools.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_platform_school_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('platform.schools.index'))
            ->assertForbidden();
    }

    public function test_super_admin_bypasses_every_defined_laravel_ability(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Gate::define('restricted-test-feature', fn (User $user): bool => false);

        $this->assertTrue(Gate::forUser($admin)->allows('restricted-test-feature'));
    }

    public function test_super_admin_can_create_a_school_with_nullable_optional_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->withSession(['_token' => 'test-token'])->post(route('platform.schools.store'), [
            '_token' => 'test-token',
            'name' => 'Ranjan Public School',
            'status' => SchoolStatus::Trial->value,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'currency' => 'inr',
        ]);

        $school = School::query()->sole();

        $response->assertRedirect(route('platform.schools.edit', $school));
        $this->assertDatabaseHas('schools', [
            'name' => 'Ranjan Public School',
            'slug' => 'ranjan-public-school',
            'code' => null,
            'currency' => 'INR',
            'status' => SchoolStatus::Trial->value,
        ]);
    }

    public function test_super_admin_can_update_school_status_without_deleting_history(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $school = School::factory()->active()->create();

        $this->actingAs($admin)->withSession(['_token' => 'test-token'])->put(route('platform.schools.update', $school), [
            '_token' => 'test-token',
            'name' => $school->name,
            'slug' => $school->slug,
            'code' => $school->code,
            'status' => SchoolStatus::Suspended->value,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'currency' => 'INR',
        ])->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'id' => $school->getKey(),
            'status' => SchoolStatus::Suspended->value,
        ]);
    }

    public function test_school_code_must_be_unique(): void
    {
        $admin = User::factory()->superAdmin()->create();
        School::factory()->create(['code' => 'SCH-001']);

        $this->actingAs($admin)->withSession(['_token' => 'test-token'])->from(route('platform.schools.create'))->post(route('platform.schools.store'), [
            '_token' => 'test-token',
            'name' => 'Another School',
            'code' => 'sch-001',
            'status' => SchoolStatus::Active->value,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'currency' => 'INR',
        ])->assertRedirect(route('platform.schools.create'))
            ->assertSessionHasErrors('code');
    }
}
