<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum PublishStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

}
