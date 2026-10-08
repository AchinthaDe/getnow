<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

use Filament\Support\Contracts\HasLabel;

enum Tone: string implements HasLabel
{
    case INFO = 'info';
    case WARNING = 'warning';
    case SUCCESS = 'success';

    public function getLabel(): string
    {
        return match ($this) {
            self::INFO => 'Info',
            self::WARNING => 'Warning',
            self::SUCCESS => 'Success',
        };
    }
}
