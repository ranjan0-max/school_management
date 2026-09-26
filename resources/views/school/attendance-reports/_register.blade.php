{{--
    Monthly register grid.
    $people: list of ['id' => int, 'lead' => ?string, 'name' => string, 'meta' => ?string]
--}}
@php($days = $register->days())

<div class="attendance-register-summary">
    <span><strong>{{ count($register->takenDays) }}</strong> day(s) attendance taken</span>
    <span><strong>{{ count($register->holidays) }}</strong> holiday(s)</span>
    <span class="attendance-legend">P Present · A Absent · L Late · HD Half day · LV Leave · H Holiday · % = (P + L + ½ HD) ÷ days marked</span>
</div>

<div class="table-responsive school-table-wrap attendance-register-wrap">
    <table class="table attendance-register mb-0">
        <thead>
            <tr>
                <th scope="col" class="attendance-register-person">{{ $leadHeading }}</th>
                @foreach ($days as $dayNumber => $day)
                    <th scope="col" class="{{ isset($register->holidays[$dayNumber]) ? 'is-holiday' : ($register->isOffDay($day) ? 'is-sunday' : '') }}" title="{{ $register->holidays[$dayNumber] ?? $day->format('l').($register->isOffDay($day) ? ' · weekly off' : '') }}">
                        {{ $dayNumber }}<small>{{ $day->format('D')[0] }}</small>
                    </th>
                @endforeach
                @foreach ($statuses as $statusOption)
                    <th scope="col" class="attendance-register-total" title="{{ $statusOption->label() }}">{{ $statusOption->code() }}</th>
                @endforeach
                <th scope="col" class="attendance-register-total">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($people as $person)
                @php($row = $rows[$person['id']] ?? $register->empty())
                <tr>
                    <th scope="row" class="attendance-register-person">
                        @if ($person['lead'] !== null)<span class="attendance-register-lead">{{ $person['lead'] }}</span>@endif
                        <strong>{{ $person['name'] }}</strong>
                        @if ($person['meta'])<small>{{ $person['meta'] }}</small>@endif
                    </th>
                    @foreach ($days as $dayNumber => $day)
                        @php($mark = $row->on($dayNumber))
                        @if ($mark !== null)
                            <td class="attendance-mark attendance-mark-{{ $mark->value }}" title="{{ $mark->label() }}">{{ $mark->code() }}</td>
                        @elseif (isset($register->holidays[$dayNumber]))
                            <td class="attendance-mark is-holiday" title="{{ $register->holidays[$dayNumber] }}">H</td>
                        @else
                            <td class="attendance-mark {{ $register->isOffDay($day) ? 'is-sunday' : '' }}"></td>
                        @endif
                    @endforeach
                    @foreach ($statuses as $statusOption)
                        <td class="attendance-register-total">{{ $row->count($statusOption) }}</td>
                    @endforeach
                    @php($percentage = $row->percentage())
                    <td class="attendance-register-total {{ $percentage !== null && $percentage < 75 ? 'is-low' : '' }}">{{ $percentage === null ? '—' : $percentage }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
