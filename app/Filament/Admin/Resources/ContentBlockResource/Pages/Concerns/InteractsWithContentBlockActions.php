<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns;

use App\Domain\Content\Data\UpdateContentBlockData;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

trait InteractsWithContentBlockActions
{
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
