<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\BrandStrip;

use App\Domain\Content\Data\BlockPayload;
use App\Support\Rules\NoHtml;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class BrandStripData extends BlockPayload
{
    public const int EYEBROW_MAX_LENGTH = 60;

    public const int TITLE_MAX_LENGTH = 60;

    public const int MAX_BRANDS = 10;

    public function __construct(
        #[Min(1), Max(self::EYEBROW_MAX_LENGTH), Rule(new NoHtml)]
        public string $eyebrow,

        #[Min(1), Max(self::TITLE_MAX_LENGTH), Rule(new NoHtml)]
        public string $title,

        /** @var array<int, BrandStripItemData> */
        #[DataCollectionOf(BrandStripItemData::class), Max(self::MAX_BRANDS)]
        public array $brands = [],
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

        if (isset($payload['brands']) && is_array($payload['brands'])) {
            $brands = array_values($payload['brands']);
            foreach ($brands as &$item) {
                if (is_array($item)) {
                    if (isset($item['label']) && is_string($item['label'])) {
                        $item['label'] = trim($item['label']);
                    }
                    if (isset($item['url']) && is_string($item['url'])) {
                        $url = trim($item['url']);
                        if (stripos($url, 'https://') === 0) {
                            $item['url'] = 'https://'.substr($url, 8);
                        } else {
                            $item['url'] = $url;
                        }
                    }
                }
            }
            unset($item);
            $payload['brands'] = $brands;
        }

        return $payload;
    }
}
