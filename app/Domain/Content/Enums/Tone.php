<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum Tone: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case SUCCESS = 'success';

}
