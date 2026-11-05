<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class UpdateCategoryData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?bool $show_in_nav = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (isset($properties['name']) && is_string($properties['name'])) {
            $properties['name'] = trim($properties['name']);
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:2', 'max:80', 'regex:/^[^\p{C}]+$/u'],
            'show_in_nav' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
