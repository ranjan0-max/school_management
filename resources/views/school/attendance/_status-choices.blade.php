{{-- One row's status buttons (P / A / L / HD / LV). --}}
<div class="attendance-choices" role="radiogroup" aria-label="Attendance of {{ $label }}">
    @foreach ($statuses as $statusOption)
        @php($inputId = 'att-'.$personId.'-'.$statusOption->value)
        <input
            class="btn-check"
            type="radio"
            id="{{ $inputId }}"
            name="attendance[{{ $personId }}][status]"
            value="{{ $statusOption->value }}"
            data-attendance-status="{{ $statusOption->value }}"
            @checked($current === $statusOption->value)
            @disabled(! $editable)
        >
        <label class="attendance-choice attendance-choice-{{ $statusOption->value }}" for="{{ $inputId }}" title="{{ $statusOption->label() }}">{{ $statusOption->code() }}</label>
    @endforeach
</div>
