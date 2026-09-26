<?php

namespace App\Http\Requests\School;

use App\Enums\RoleType;
use App\Models\Notice;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * publish_mode: "draft" keeps it hidden, "now" publishes at once, "schedule" publishes
 * at publish_at (entered in the school's timezone).
 */
class NoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->requireSchool()->getKey();

        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'audience' => ['required', Rule::in([Notice::AUDIENCE_SCHOOL, Notice::AUDIENCE_SELECTED])],
            'role_ids' => ['nullable', 'array', 'max:100'],
            'role_ids.*' => [
                'integer', 'distinct',
                // Staff roles of this school, or platform roles (such as Principal) used in it.
                Rule::exists('roles', 'id')->where(fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->where('school_id', $schoolId)
                    ->orWhere('type', RoleType::Platform->value))),
            ],
            'class_ids' => ['nullable', 'array', 'max:200'],
            'class_ids.*' => ['integer', 'distinct', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
            'is_pinned' => ['required', 'boolean'],
            'publish_mode' => ['required', Rule::in(['draft', 'now', 'schedule'])],
            'publish_at' => ['nullable', 'required_if:publish_mode,schedule', 'date_format:Y-m-d\TH:i'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'publish_at.required_if' => 'Choose when the notice should be published.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('audience') === Notice::AUDIENCE_SELECTED && $this->ids('role_ids') === [] && $this->ids('class_ids') === []) {
                    $validator->errors()->add('audience', 'Choose at least one role or class, or send the notice to the whole school.');
                }

                if ($validator->errors()->hasAny(['publish_at', 'expires_on'])) {
                    return;
                }

                $timezone = $this->schoolTimezone();
                $today = CarbonImmutable::now($timezone)->toDateString();
                $expiresOn = $this->input('expires_on');

                if ($this->input('publish_mode') === 'schedule' && is_string($this->input('publish_at'))) {
                    $publishAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->input('publish_at'), $timezone);

                    if ($publishAt !== false && $publishAt->isPast()) {
                        $validator->errors()->add('publish_at', 'The publish time is in the past. Choose "Publish now" instead.');
                    }

                    if ($publishAt !== false && is_string($expiresOn) && $expiresOn < $publishAt->toDateString()) {
                        $validator->errors()->add('expires_on', 'The notice would expire before it is published.');
                    }
                }

                if (is_string($expiresOn) && $expiresOn < $today && $this->input('publish_mode') !== 'draft') {
                    $validator->errors()->add('expires_on', 'The expiry date has already passed.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = trim((string) $this->input('body'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'body' => $body === '' ? null : $body,
            'is_pinned' => $this->boolean('is_pinned'),
            'publish_at' => $this->filled('publish_at') ? substr((string) $this->input('publish_at'), 0, 16) : null,
            'expires_on' => $this->filled('expires_on') ? $this->input('expires_on') : null,
        ]);
    }

    /**
     * publish_at in the app's timezone, or null for "now" / drafts handled by the controller.
     */
    public function scheduledAt(): ?CarbonImmutable
    {
        if ($this->validated('publish_mode') !== 'schedule') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->validated('publish_at'), $this->schoolTimezone())
            ->setTimezone(config('app.timezone'));
    }

    /**
     * @return array<int, int>
     */
    public function ids(string $field): array
    {
        $values = $this->input($field);

        return is_array($values) ? array_values(array_unique(array_map('intval', array_filter($values, 'is_scalar')))) : [];
    }

    private function schoolTimezone(): string
    {
        return app(TenantContext::class)->requireSchool()->timezone ?: config('app.timezone');
    }
}
