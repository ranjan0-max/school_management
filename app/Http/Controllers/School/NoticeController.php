<?php

namespace App\Http\Controllers\School;

use App\Enums\NoticeStatus;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\NoticeRequest;
use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Notice board. Everyone with "view" sees live notices meant for them (pinned first);
 * people with "create" / "edit" also get the Manage tab. Notices are archived, never deleted.
 */
class NoticeController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('menu:notices', only: ['index', 'feed', 'preview']),
            new Middleware('menu:notices,create', only: ['create', 'store']),
            new Middleware('menu:notices,edit', only: ['edit', 'update', 'archive', 'restore']),
        ];
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canManage = $this->canManage($user);
        $manage = $canManage && $request->string('view')->toString() === 'manage';
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $state = in_array($request->string('state')->toString(), ['draft', 'scheduled', 'live', 'expired', 'archived'], true)
            ? $request->string('state')->toString()
            : '';
        $today = $this->today();

        $notices = Notice::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->when(! $manage, fn (Builder $query) => $query->visibleTo($user, $today))
            ->when($manage && $state !== '', fn (Builder $query) => $this->whereState($query, $state, $today))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->with(['creator:id,name', 'audiences'])
            ->withCount('reads')
            ->withExists(['reads as read_by_me' => fn (Builder $query) => $query->where('user_id', $user->getKey())])
            ->when($manage, fn (Builder $query) => $query->orderByDesc('id'), fn (Builder $query) => $query->boardOrder())
            ->paginate($manage ? 20 : 10)
            ->withQueryString();

        return view('school.notices.index', [
            'school' => $this->currentSchool(),
            'notices' => $notices,
            'manage' => $manage,
            'canManage' => $canManage,
            'search' => $search,
            'selectedState' => $state,
            'today' => $today,
            'roleNames' => $this->roles()->pluck('name', 'id'),
            'classNames' => $this->classes()->pluck('name', 'id'),
        ]);
    }

    /**
     * Top bar bell: the user's live notices, 10 at a time, with the unread count.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = $this->today();
        $timezone = $this->timezone();
        $visible = fn () => Notice::query()->where('school_id', $this->currentSchool()->getKey())->visibleTo($user, $today);

        $notices = $visible()
            ->withExists(['reads as read_by_me' => fn (Builder $query) => $query->where('user_id', $user->getKey())])
            ->boardOrder()
            ->simplePaginate(10, ['id', 'title', 'body', 'is_pinned', 'publish_at', 'created_at']);

        return response()->json([
            'unread' => $visible()->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $user->getKey()))->count(),
            'data' => $notices->getCollection()->map(fn (Notice $notice): array => [
                'id' => $notice->getKey(),
                'title' => $notice->title,
                'excerpt' => Str::limit((string) $notice->body, 90),
                'date' => ($notice->publish_at ?? $notice->created_at)->setTimezone($timezone)->format('d M Y'),
                'pinned' => $notice->is_pinned,
                'read' => (bool) $notice->read_by_me,
                'preview_url' => route('school.notices.preview', $notice),
            ]),
            'next_page_url' => $notices->nextPageUrl(),
        ]);
    }

    /**
     * The full notice for the modal; opening it counts as reading it.
     */
    public function preview(Request $request, Notice $notice): JsonResponse
    {
        $this->ensureCurrentSchool($notice);
        $user = $request->user();
        $isVisible = Notice::query()->whereKey($notice->getKey())->visibleTo($user, $this->today())->exists();

        abort_unless($isVisible || $this->canManage($user), 404);

        if ($isVisible) {
            NoticeRead::query()->firstOrCreate(['notice_id' => $notice->getKey(), 'user_id' => $user->getKey()], ['read_at' => now()]);
        }

        $notice->loadMissing('creator:id,name');

        return response()->json([
            'id' => $notice->getKey(),
            'title' => $notice->title,
            'body' => $notice->body,
            'pinned' => $notice->is_pinned,
            'meta' => implode(' · ', array_filter([
                $notice->creator?->name,
                ($notice->publish_at ?? $notice->created_at)->setTimezone($this->timezone())->format('d M Y, h:i A'),
                $notice->expires_on ? 'until '.$notice->expires_on->format('d M Y') : null,
            ])),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Notice(['audience' => Notice::AUDIENCE_SCHOOL, 'status' => NoticeStatus::Draft]));
    }

    public function store(NoticeRequest $request): RedirectResponse
    {
        $notice = DB::transaction(function () use ($request): Notice {
            $notice = Notice::query()->create([
                ...$this->attributes($request),
                'school_id' => $this->currentSchool()->getKey(),
                'created_by' => $request->user()?->getKey(),
            ]);
            $this->syncAudience($notice, $request);

            return $notice;
        });

        $this->record('notice.created', $request, $notice);

        return redirect()->route('school.notices.index', ['view' => 'manage'])->with('status', $this->savedMessage($notice));
    }

    public function edit(Notice $notice): View
    {
        $this->ensureCurrentSchool($notice);
        abort_if($notice->status === NoticeStatus::Archived, 404);

        return $this->form($notice->load('audiences'));
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $this->ensureCurrentSchool($notice);
        abort_if($notice->status === NoticeStatus::Archived, 404);

        DB::transaction(function () use ($request, $notice): void {
            $notice->update($this->attributes($request, $notice));
            $this->syncAudience($notice, $request);
        });

        $this->record('notice.updated', $request, $notice);

        return redirect()->route('school.notices.index', ['view' => 'manage'])->with('status', $this->savedMessage($notice));
    }

    public function archive(Request $request, Notice $notice): RedirectResponse
    {
        $this->ensureCurrentSchool($notice);

        $notice->update([
            'status' => NoticeStatus::Archived,
            'is_pinned' => false,
            'archived_by' => $request->user()?->getKey(),
            'archived_at' => now(),
        ]);
        $this->record('notice.archived', $request, $notice);

        return back()->with('status', "“{$notice->title}” archived. It is no longer shown to anyone.");
    }

    /**
     * Brings an archived notice back as a draft, to be checked and published again.
     */
    public function restore(Request $request, Notice $notice): RedirectResponse
    {
        $this->ensureCurrentSchool($notice);
        abort_unless($notice->status === NoticeStatus::Archived, 404);

        $notice->update(['status' => NoticeStatus::Draft, 'archived_by' => null, 'archived_at' => null]);
        $this->record('notice.restored', $request, $notice);

        return back()->with('status', "“{$notice->title}” restored as a draft.");
    }

    private function form(Notice $notice): View
    {
        return view('school.notices.form', [
            'school' => $this->currentSchool(),
            'notice' => $notice,
            'roles' => $this->roles(),
            'classes' => $this->classes(),
            'timezone' => $this->timezone(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(NoticeRequest $request, ?Notice $notice = null): array
    {
        $mode = $request->validated('publish_mode');

        return [
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'audience' => $request->validated('audience'),
            'is_pinned' => $request->validated('is_pinned'),
            'expires_on' => $request->validated('expires_on'),
            'status' => $mode === 'draft' ? NoticeStatus::Draft : NoticeStatus::Published,
            'publish_at' => match ($mode) {
                'schedule' => $request->scheduledAt(),
                // Keep the first publish time when a live notice is edited.
                'now' => $notice?->status === NoticeStatus::Published && $notice->publish_at?->isPast() ? $notice->publish_at : now(),
                default => null,
            },
        ];
    }

    private function syncAudience(Notice $notice, NoticeRequest $request): void
    {
        $notice->audiences()->delete();

        if ($request->validated('audience') !== Notice::AUDIENCE_SELECTED) {
            return;
        }

        $rows = [];

        foreach ([Notice::TARGET_ROLE => $request->ids('role_ids'), Notice::TARGET_CLASS => $request->ids('class_ids')] as $type => $ids) {
            foreach ($ids as $id) {
                $rows[] = ['school_id' => $notice->school_id, 'target_type' => $type, 'target_id' => $id];
            }
        }

        $notice->audiences()->createMany($rows);
    }

    /**
     * Mirrors Notice::state() in SQL for the Manage tab filter.
     *
     * @param  Builder<Notice>  $query
     */
    private function whereState(Builder $query, string $state, string $today): void
    {
        match ($state) {
            'draft' => $query->where('status', NoticeStatus::Draft),
            'archived' => $query->where('status', NoticeStatus::Archived),
            'scheduled' => $query->where('status', NoticeStatus::Published)->where('publish_at', '>', now()),
            'expired' => $query->where('status', NoticeStatus::Published)
                ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
                ->whereDate('expires_on', '<', $today),
            default => $query->where('status', NoticeStatus::Published)
                ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
                ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today)),
        };
    }

    private function canManage(?User $user): bool
    {
        return $user !== null && ($user->can('menu', ['notices', 'create']) || $user->can('menu', ['notices', 'edit']));
    }

    /**
     * Roles a notice can target: the school's roles and platform roles (such as Principal).
     *
     * @return Collection<int, Role>
     */
    private function roles(): Collection
    {
        return Role::query()
            ->where(fn (Builder $query) => $query
                ->where('school_id', $this->currentSchool()->getKey())
                ->orWhere('type', RoleType::Platform))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function classes(): Collection
    {
        return SchoolClass::query()
            ->where('school_id', $this->currentSchool()->getKey())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function savedMessage(Notice $notice): string
    {
        return match ($notice->state($this->today())) {
            'draft' => 'Notice saved as a draft. Nobody can see it yet.',
            'scheduled' => 'Notice scheduled for '.$notice->publish_at?->setTimezone($this->timezone())->format('d M Y, h:i A').'.',
            default => 'Notice published.',
        };
    }

    private function timezone(): string
    {
        return $this->currentSchool()->timezone ?: config('app.timezone');
    }

    /**
     * The school's date, as Y-m-d.
     */
    private function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    private function record(string $event, Request $request, Notice $notice): void
    {
        $this->audit->record(
            event: $event,
            actor: $request->user(),
            school: $this->currentSchool(),
            auditableType: Notice::class,
            auditableId: $notice->getKey(),
            newValues: [
                'title' => $notice->title,
                'status' => $notice->status->value,
                'audience' => $notice->audience,
                'is_pinned' => $notice->is_pinned,
            ],
        );
    }
}
