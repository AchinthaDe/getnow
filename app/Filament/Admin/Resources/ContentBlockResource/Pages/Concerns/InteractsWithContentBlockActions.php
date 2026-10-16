<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns;

use App\Domain\Content\Data\CreateContentBlockData;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\Placement;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

trait InteractsWithContentBlockActions
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CreateContentBlockData, 1: array<string, mixed>}
     *
     * @throws ValidationException
     */
    protected function prepareCreateData(array $data): array
    {
        $placementVal = $data['placement'] ?? null;
        $placement = is_string($placementVal) ? Placement::tryFrom($placementVal) : null;

        if ($placement === null) {
            $this->throwRemappedValidationException(
                ValidationException::withMessages([
                    'placement' => 'The selected placement is invalid.',
                ])
            );
        }

        $startsAt = $this->parseScheduleDatetime($data['starts_at'] ?? null);
        $endsAt = $this->parseScheduleDatetime($data['ends_at'] ?? null);

        $actionData = new CreateContentBlockData(
            placement: $placement,
            type: (string) ($data['type'] ?? ''),
            starts_at: $startsAt,
            ends_at: $endsAt,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        );

        $rawPayload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return [$actionData, $rawPayload];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: UpdateContentBlockData, 1: array<string, mixed>}
     */
    protected function prepareUpdateData(array $data): array
    {
        $startsAt = $this->parseScheduleDatetime($data['starts_at'] ?? null);
        $endsAt = $this->parseScheduleDatetime($data['ends_at'] ?? null);

        $actionData = new UpdateContentBlockData(
            starts_at: $startsAt,
            ends_at: $endsAt,
            sort_order: array_key_exists('sort_order', $data) && $data['sort_order'] !== null
                ? (int) $data['sort_order']
                : null,
        );

        $rawPayload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return [$actionData, $rawPayload];
    }

    protected function parseScheduleDatetime(mixed $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        // DateTimePicker dehydrates into the app timezone
        return Carbon::parse((string) $value, (string) config('app.timezone'));
    }

    /**
     * @throws ValidationException
     */
    protected function throwRemappedValidationException(ValidationException $exception): never
    {
        $remapped = [];
        foreach ($exception->errors() as $key => $messages) {
            $remappedKey = str_starts_with($key, 'data.') ? $key : "data.{$key}";
            $remapped[$remappedKey] = $messages;
        }

        throw ValidationException::withMessages($remapped);
    }
}
