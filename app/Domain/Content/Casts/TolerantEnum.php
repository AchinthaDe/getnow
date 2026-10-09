<?php

declare(strict_types=1);

namespace App\Domain\Content\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TEnum of \BackedEnum
 *
 * @implements CastsAttributes<TEnum|null, TEnum|string|null>
 */
final class TolerantEnum implements CastsAttributes
{
    /**
     * @param  class-string<TEnum>  $enumClass
     */
    public function __construct(
        private readonly string $enumClass
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->enumClass::tryFrom($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
