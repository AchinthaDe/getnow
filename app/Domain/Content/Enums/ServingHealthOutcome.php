<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum ServingHealthOutcome: string
{
    case Servable = 'servable';
    case UnknownType = 'unknown_type';
    case UnsupportedVersion = 'unsupported_version';
    case InvalidAfterUpgrade = 'invalid_after_upgrade';
    case UpgradeFailed = 'upgrade_failed';
}
