<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class MoveCategoryData extends Data
{
    public function __construct(
        public ?string $parent_public_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parent_public_id' => ['nullable', 'string', 'exists:categories,public_id'],
        ];
    }
}
