<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\ServiceItem;

use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Enums\ServiceIcon;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class ServiceItemData extends BlockPayload
{
    public const int TITLE_MAX_LENGTH = 60;

    public const int SUBTITLE_MAX_LENGTH = 100;

    public function __construct(
        public ServiceIcon $icon,

        #[Min(1), Max(self::TITLE_MAX_LENGTH), Rule(new NoHtml)]
        public string $title,

        #[Min(1), Max(self::SUBTITLE_MAX_LENGTH), Rule(new NoHtml)]
        public string $subtitle,

        #[Rule(new SafeLink)]
        public ?string $link_url = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $payload): array
    {
        foreach (['title', 'subtitle'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        if (array_key_exists('link_url', $payload)) {
            if (is_string($payload['link_url'])) {
                $url = trim($payload['link_url']);
                if ($url === '') {
                    $payload['link_url'] = null;
                } elseif (stripos($url, 'https://') === 0) {
                    $payload['link_url'] = 'https://'.substr($url, 8);
                } else {
                    $payload['link_url'] = $url;
                }
            }
        }

        return $payload;
    }
}
