<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\BrandStrip;

use App\Domain\Content\Data\BlockPayload;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class BrandStripItemData extends BlockPayload
{
    public const int LABEL_MAX_LENGTH = 40;

    public function __construct(
        #[Min(1), Max(self::LABEL_MAX_LENGTH), Rule(new NoHtml)]
        public string $label,

        #[Rule(new SafeLink)]
        public string $url,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $payload): array
    {
        if (isset($payload['label']) && is_string($payload['label'])) {
            $payload['label'] = trim($payload['label']);
        }

        if (isset($payload['url']) && is_string($payload['url'])) {
            $url = trim($payload['url']);
            if (stripos($url, 'https://') === 0) {
                $payload['url'] = 'https://'.substr($url, 8);
            } else {
                $payload['url'] = $url;
            }
        }

        return $payload;
    }
}
