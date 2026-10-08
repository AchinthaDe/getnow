<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

use Filament\Support\Contracts\HasLabel;

enum Placement: string implements HasLabel
{
    case ANNOUNCEMENT_BAR = 'announcement_bar';

    /**
     * @return array<int, string>
     */
    public function allowedBlockTypes(): array
    {
        return match ($this) {
            self::ANNOUNCEMENT_BAR => ['announcement_bar'],
        };
    }

    public function maxCount(): int
    {
        return match ($this) {
            self::ANNOUNCEMENT_BAR => 1,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ANNOUNCEMENT_BAR => 'Announcement Bar',
        };
    }
}
