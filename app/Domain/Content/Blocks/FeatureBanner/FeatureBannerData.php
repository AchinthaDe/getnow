<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\FeatureBanner;

use App\Domain\Content\Data\BlockPayload;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class FeatureBannerData extends BlockPayload
{
    public const int EYEBROW_MAX_LENGTH = 60;

    public const int TITLE_LINE_1_MAX_LENGTH = 60;

    public const int TITLE_LINE_2_MAX_LENGTH = 60;

    public const int DESCRIPTION_MAX_LENGTH = 240;

    public const int CTA_LABEL_MAX_LENGTH = 50;

    public const int IMAGE_ALT_MAX_LENGTH = 120;

    public const int BADGE_TEXT_MAX_LENGTH = 40;

    public function __construct(
        #[Min(1), Max(self::EYEBROW_MAX_LENGTH), Rule(new NoHtml)]
        public string $eyebrow,

        #[Min(1), Max(self::TITLE_LINE_1_MAX_LENGTH), Rule(new NoHtml)]
        public string $title_line_1,

        #[Max(self::TITLE_LINE_2_MAX_LENGTH), Rule(new NoHtml)]
        public ?string $title_line_2,

        #[Max(self::DESCRIPTION_MAX_LENGTH), Rule(new NoHtml)]
        public ?string $description,

        #[Min(1), Max(self::CTA_LABEL_MAX_LENGTH), Rule(new NoHtml)]
        public string $cta_label,

        #[Rule(new SafeLink)]
        public string $cta_url,

        #[Rule(new SafeLink)]
        public string $image_url,

        #[Min(1), Max(self::IMAGE_ALT_MAX_LENGTH), Rule(new NoHtml)]
        public string $image_alt,

        #[Max(self::BADGE_TEXT_MAX_LENGTH), Rule(new NoHtml)]
        public ?string $badge_text = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $payload): array
    {
        $stringFields = ['eyebrow', 'title_line_1', 'cta_label', 'image_alt'];
        foreach ($stringFields as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        $nullableFields = ['title_line_2', 'description', 'badge_text'];
        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $payload)) {
                if (is_string($payload[$field])) {
                    $trimmed = trim($payload[$field]);
                    $payload[$field] = $trimmed === '' ? null : $trimmed;
                }
            }
        }

        foreach (['cta_url', 'image_url'] as $urlField) {
            if (isset($payload[$urlField]) && is_string($payload[$urlField])) {
                $url = trim($payload[$urlField]);
                if (stripos($url, 'https://') === 0) {
                    $payload[$urlField] = 'https://'.substr($url, 8);
                } else {
                    $payload[$urlField] = $url;
                }
            }
        }

        return $payload;
    }
}
