<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class CreateCategoryData extends Data
{
    public function __construct(
        public string $name,
        public ?string $parent_public_id = null,
        public bool $show_in_nav = true,
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
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[^\p{C}]+$/u'],
            'parent_public_id' => ['nullable', 'string', 'exists:categories,public_id'],
            'show_in_nav' => ['boolean'],
        ];
    }
}
