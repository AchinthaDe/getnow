<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum ServingStatus: string
{
    case Live = 'live';
    case NotLive = 'not_live';
    case PayloadError = 'payload_error';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Live',
            self::NotLive => 'Not live',
            self::PayloadError => 'Payload error',
        };
    }
}
