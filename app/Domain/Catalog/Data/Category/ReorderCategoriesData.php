<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class ReorderCategoriesData extends Data
{
    /**
     * @param  array<int, string>  $ordered_public_ids
     */
    public function __construct(
        public array $ordered_public_ids,
        public ?string $parent_public_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parent_public_id' => ['nullable', 'string', 'exists:categories,public_id'],
            'ordered_public_ids' => ['required', 'array'],
            'ordered_public_ids.*' => ['required', 'string'],
        ];
    }
}
