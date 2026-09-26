<?php

namespace App\Enums;

/**
 * Stored status. A published notice can still be "scheduled" (publish_at in the future)
 * or "expired" (expires_on passed); see Notice::state().
 */
enum NoticeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
