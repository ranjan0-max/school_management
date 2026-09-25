<?php

namespace App\Support\Numbering;

use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Generates the next per-school number such as ADM-2026-0001 or TCH-0007.
 * Call inside a DB transaction: the school row is locked so two saves cannot
 * receive the same number (the unique index is the final guard).
 */
class SchoolNumber
{
    public static function next(School $school, string $table, string $column, string $prefix, int $pad = 4): string
    {
        School::query()->whereKey($school->getKey())->lockForUpdate()->first();

        $last = DB::table($table)
            ->where('school_id', $school->getKey())
            ->where($column, 'like', $prefix.'%')
            ->pluck($column)
            ->map(fn (string $value): int => (int) substr($value, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), $pad, '0', STR_PAD_LEFT);
    }
}
