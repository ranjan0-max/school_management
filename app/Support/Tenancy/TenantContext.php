<?php

namespace App\Support\Tenancy;

use App\Models\School;
use LogicException;

class TenantContext
{
    private ?School $school = null;

    public function setSchool(School $school): void
    {
        $this->school = $school;
    }

    public function clear(): void
    {
        $this->school = null;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function schoolId(): ?int
    {
        $id = $this->school?->getKey();

        return is_int($id) ? $id : null;
    }

    public function requireSchool(): School
    {
        return $this->school
            ?? throw new LogicException('A school context is required for this operation.');
    }
}
