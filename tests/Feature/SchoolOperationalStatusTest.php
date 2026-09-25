<?php

namespace Tests\Feature;

use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolOperationalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_with_future_end_date_is_operational(): void
    {
        $school = School::factory()->create([
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->assertTrue($school->fresh()?->isOperational());
    }

    public function test_expired_trial_is_not_operational(): void
    {
        $school = School::factory()->create([
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->subMinute(),
        ]);

        $this->assertFalse($school->fresh()?->isOperational());
    }

    public function test_user_of_trial_school_can_open_school_dashboard(): void
    {
        $school = School::factory()->create([
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->actingAs(User::factory()->create(['school_id' => $school->getKey()]))
            ->get(route('school.dashboard'))
            ->assertOk();
    }

    public function test_user_of_suspended_school_is_blocked(): void
    {
        $school = School::factory()->create(['status' => SchoolStatus::Suspended]);

        $this->actingAs(User::factory()->create(['school_id' => $school->getKey()]))
            ->get(route('school.dashboard'))
            ->assertForbidden();
    }

    public function test_user_without_school_is_blocked(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('school.dashboard'))
            ->assertForbidden();
    }
}
