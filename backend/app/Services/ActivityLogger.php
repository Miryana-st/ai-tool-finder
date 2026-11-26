<?php

namespace App\Services;

use App\Enums\Activity\ActivityEvent;
use App\Models\Activity\ActivityLog;
use App\Models\User\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records what users do (see ActivityLog). Call it inside the same transaction
 * as the change itself, so an action is never saved without its entry.
 *
 * Only values the admin needs to understand the change are stored.
 */
class ActivityLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * Add an entry. The user is the signed-in one unless given.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ActivityEvent $event,
        ?Model $subject = null,
        array $changes = [],
        array $properties = [],
        ?User $user = null,
    ): ActivityLog {
        $user ??= $this->auth->guard()->user();

        return ActivityLog::create([
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
            'event' => $event,
            'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? Str::limit($this->label($subject), 250) : null,
            'changes' => $changes ?: null,
            'properties' => $properties ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, '') ?: null,
        ]);
    }

    /**
     * Compare the values before and after a change: `{field: {old, new}}` for
     * every field that differs. Lists (e.g. tag names) are compared regardless
     * of their order.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_keys($before + $after) as $field) {
            $old = $this->normalize($before[$field] ?? null);
            $new = $this->normalize($after[$field] ?? null);

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * Describe a new record: every filled-in value as a change from nothing.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function created(array $values): array
    {
        return $this->diff([], $values);
    }

    /**
     * Describe a deleted record: every value it had as a change to nothing.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function deleted(array $values): array
    {
        return $this->diff($values, []);
    }

    /**
     * Sorted lists, and null for empty values, so that equal data compares equal.
     */
    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            sort($value);

            return $value === [] ? null : $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value === '' ? null : $value;
    }

    /**
     * The name the subject is shown by, kept in the entry in case it is deleted.
     */
    private function label(Model $subject): string
    {
        return (string) ($subject->getAttribute('name') ?? '#'.$subject->getKey());
    }
}
