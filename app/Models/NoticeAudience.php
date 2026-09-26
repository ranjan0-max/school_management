<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $notice_id
 * @property string $target_type role | class
 * @property int $target_id
 */
#[Fillable([
    'school_id',
    'notice_id',
    'target_type',
    'target_id',
])]
class NoticeAudience extends Model
{
    /**
     * @return BelongsTo<Notice, $this>
     */
    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }
}
