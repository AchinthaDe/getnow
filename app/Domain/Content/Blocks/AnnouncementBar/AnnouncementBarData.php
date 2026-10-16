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
    public const int TEXT_MAX_LENGTH = 120;

    public const int SECONDARY_TEXT_MAX_LENGTH = 120;

    public const int LOCALE_TEXT_MAX_LENGTH = 60;

    public function __construct(
        #[Min(1), Max(self::TEXT_MAX_LENGTH), Rule(new NoHtml)]
        public string $text,

        #[Max(self::SECONDARY_TEXT_MAX_LENGTH), Rule(new NoHtml)]
        public ?string $secondary_text = null,

        #[Max(self::LOCALE_TEXT_MAX_LENGTH), Rule(new NoHtml)]
        public ?string $locale_text = null,

        #[Rule(new SafeLink)]
        public ?string $link_url = null,

        public Tone $tone = Tone::INFO,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $payload): array
    {
        if (isset($payload['text']) && is_string($payload['text'])) {
            $payload['text'] = trim($payload['text']);
        }

        if (array_key_exists('secondary_text', $payload)) {
            if (is_string($payload['secondary_text'])) {
                $trimmed = trim($payload['secondary_text']);
                $payload['secondary_text'] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (array_key_exists('locale_text', $payload)) {
            if (is_string($payload['locale_text'])) {
                $trimmed = trim($payload['locale_text']);
                $payload['locale_text'] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (isset($payload['link_url']) && is_string($payload['link_url'])) {
            $url = trim($payload['link_url']);
            if ($url === '') {
                $payload['link_url'] = null;
            } elseif (stripos($url, 'https://') === 0) {
                // Lowercase the scheme only
                $payload['link_url'] = 'https://'.substr($url, 8);
            } else {
                $payload['link_url'] = $url;
            }
        }

        return $payload;
    }
}
