<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use App\Domain\Content\Enums\ServingHealthOutcome;
use Throwable;

final class UnservablePayloadException extends ContentException
{
    public function __construct(
        public readonly ServingHealthOutcome $outcome,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: 'Cannot perform this action because the payload cannot be safely served.',
            previous: $previous
        );
    }
}
