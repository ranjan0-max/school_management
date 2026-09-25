<?php

namespace App\Models;

use App\Enums\SubjectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $school_id
 * @property string $name
 * @property string|null $code
 * @property SubjectType $type
 */
#[Fillable([
    'school_id',
    'name',
    'code',
    'type',
])]
class Subject extends Model
{
    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsToMany<SchoolClass, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subjects', 'subject_id', 'class_id')
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubjectType::class,
        ];
    }
}
