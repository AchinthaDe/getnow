<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\SectionHeader;

use App\Domain\Content\Data\BlockPayload;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class SectionHeaderData extends BlockPayload
{
    public const int EYEBROW_MAX_LENGTH = 60;

    public const int TITLE_MAX_LENGTH = 100;

    public const int CTA_LABEL_MAX_LENGTH = 50;

    public function __construct(
        #[Min(1), Max(self::EYEBROW_MAX_LENGTH), Rule(new NoHtml)]
        public string $eyebrow,

        #[Min(1), Max(self::TITLE_MAX_LENGTH), Rule(new NoHtml)]
        public string $title,

        #[Max(self::CTA_LABEL_MAX_LENGTH), Rule(new NoHtml)]
        public ?string $cta_label = null,

        #[Rule(new SafeLink)]
        public ?string $cta_url = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $payload): array
    {
        foreach (['eyebrow', 'title'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        if (array_key_exists('cta_label', $payload)) {
            if (is_string($payload['cta_label'])) {
                $trimmed = trim($payload['cta_label']);
                $payload['cta_label'] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (array_key_exists('cta_url', $payload)) {
            if (is_string($payload['cta_url'])) {
                $url = trim($payload['cta_url']);
                if ($url === '') {
                    $payload['cta_url'] = null;
                } elseif (stripos($url, 'https://') === 0) {
                    $payload['cta_url'] = 'https://'.substr($url, 8);
                } else {
                    $payload['cta_url'] = $url;
                }
            }
        }

        return $payload;
    }
}
