<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\NotRegex;
use Spatie\LaravelData\Data;

class StorefrontSettingsData extends Data
{
    public const int MAX_SITE_NAME = 80;

    public const int MAX_DESC = 255;

    public const int MAX_SEARCH = 80;

    public const int MAX_FOOTER = 300;

    public const string HTML_REGEX = '/[<>]/';

    public function __construct(
        #[Max(self::MAX_SITE_NAME)]
        #[NotRegex(self::HTML_REGEX)]
        public string $site_name,

        #[Max(self::MAX_DESC)]
        #[NotRegex(self::HTML_REGEX)]
        public string $default_meta_description,

        #[Max(self::MAX_SEARCH)]
        #[NotRegex(self::HTML_REGEX)]
        public string $search_placeholder,

        #[Max(self::MAX_FOOTER)]
        #[NotRegex(self::HTML_REGEX)]
        public string $footer_brand_text,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    /**
     * Normalizes inputs by trimming strings.
     * Note: This method must NOT be named starting with `from` (e.g. `fromInput`),
     * because `spatie/laravel-data` automatically discovers `from*` methods and triggers
     * infinite recursive loops during `validateAndCreate` if the payload is an array.
     *
     * @param  array<string, mixed>  $input
     */
    public static function prepareValidated(array $input): self
    {
        $trimmed = [];

        foreach ($input as $key => $value) {
            $trimmed[$key] = is_string($value) ? trim($value) : $value;
        }

        return self::validateAndCreate($trimmed);
    }
}
