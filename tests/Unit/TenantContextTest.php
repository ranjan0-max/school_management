<?php

namespace Tests\Unit;

use App\Models\School;
use App\Support\Tenancy\TenantContext;
use LogicException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    public function test_it_stores_and_clears_the_active_school(): void
    {
        $school = new School(['name' => 'Example School']);
        $school->setAttribute('id', 42);

        $context = new TenantContext;
        $context->setSchool($school);

        $this->assertSame($school, $context->school());
        $this->assertSame(42, $context->schoolId());
        $this->assertSame($school, $context->requireSchool());

        $context->clear();

        $this->assertNull($context->school());
        $this->assertNull($context->schoolId());
    }

    public function test_it_fails_closed_when_a_school_is_required_without_context(): void
    {
        $this->expectException(LogicException::class);

        (new TenantContext)->requireSchool();
    }
}
