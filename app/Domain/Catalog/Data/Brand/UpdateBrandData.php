<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Brand;

use App\Support\Rules\NoHtml;
use Spatie\LaravelData\Data;

final class UpdateBrandData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?bool $show_in_nav = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (isset($properties['name']) && is_string($properties['name'])) {
            $properties['name'] = trim((string) preg_replace('/\s+/', ' ', $properties['name']));
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:1', 'max:80', 'regex:/^[^\p{C}]+$/u', new NoHtml],
            'show_in_nav' => ['nullable', 'boolean'],
        ];
    }
}
