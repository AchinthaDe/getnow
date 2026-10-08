<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\AnnouncementBar;

use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Enums\Tone;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class AnnouncementBarData extends BlockPayload
{
    public function __construct(
        #[Min(1), Max(120), Rule(new NoHtml)]
        public string $text,

        #[Rule(new SafeLink)]
        public ?string $link_url,

        public Tone $tone,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $payload): array
    {
        if (isset($payload['link_url']) && is_string($payload['link_url'])) {
            $url = trim($payload['link_url']);
            if ($url === '') {
                $payload['link_url'] = null;
            } elseif (stripos($url, 'https://') === 0) {
                // Lowercase the scheme only
                $payload['link_url'] = 'https://'.substr($url, 8);
            }
        }

        return $payload;
    }
}
