{{-- Who took the sheet, whether it is approved, and the approve / reopen buttons. --}}
@php($canApprove = auth()->user()?->can('menu', [$menuKey, 'approve']) ?? false)

<div class="attendance-sheet-bar {{ $sheet?->isApproved() ? 'is-approved' : ($sheet ? 'is-submitted' : '') }}">
    <div>
        @if ($sheet === null)
            <strong>Not taken yet</strong>
            <small>Everyone starts as Present. Change only the exceptions and save.</small>
        @elseif ($sheet->isApproved())
            <strong>Approved &amp; locked</strong>
            <small>Approved by {{ $sheet->approvedBy?->name ?? 'a removed user' }} on {{ $sheet->approved_at?->format('d M Y, h:i A') }}. Reopen it to make changes.</small>
        @else
            <strong>{{ $sheet->status->label() }}</strong>
            <small>Taken by {{ $sheet->takenBy?->name ?? 'a removed user' }}, last saved {{ $sheet->updated_at?->format('d M Y, h:i A') }}. It can be edited until it is approved.</small>
        @endif
    </div>

    @if ($sheet !== null && $canApprove)
        <form method="POST" action="{{ route($sheet->isApproved() ? $reopenRoute : $approveRoute, $sheet) }}" @if ($sheet->isApproved()) data-confirm="Reopen this attendance for changes?" @endif>
            @csrf
            @method('PATCH')
            <button class="btn {{ $sheet->isApproved() ? 'school-secondary-button' : 'btn-primary app-btn-primary' }}" type="submit">
                {{ $sheet->isApproved() ? 'Reopen' : 'Approve & lock' }}
            </button>
        </form>
    @endif
</div>
