@php
    $audienceParts = [];

    if ($notice->audience === \App\Models\Notice::AUDIENCE_SCHOOL) {
        $audienceParts[] = 'Whole school';
    } else {
        $roleList = collect($notice->targetIds(\App\Models\Notice::TARGET_ROLE))->map(fn ($id) => $roleNames[$id] ?? null)->filter()->implode(', ');
        $classList = collect($notice->targetIds(\App\Models\Notice::TARGET_CLASS))->map(fn ($id) => $classNames[$id] ?? null)->filter()->implode(', ');

        if ($roleList !== '') {
            $audienceParts[] = $roleList;
        }

        if ($classList !== '') {
            $audienceParts[] = 'Parents of '.$classList;
        }
    }
@endphp
{{ implode(' · ', $audienceParts) ?: '—' }}
