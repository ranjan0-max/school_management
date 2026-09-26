<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who opened a notice and when.
 *
 * @property int $notice_id
 * @property int $user_id
 */
#[Fillable([
    'notice_id',
    'user_id',
    'read_at',
])]
class NoticeRead extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
