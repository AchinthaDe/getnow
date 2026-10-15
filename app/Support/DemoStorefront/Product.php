<?php

declare(strict_types=1);

namespace App\Support\DemoStorefront;

final readonly class Product
{
    /**
     * @param  array<int, array{id: string, label: string, color: string, stock: int, price_minor: int}>  $variants
     * @param  array<string, mixed>  $specifications
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $brand,
        public string $category,
        public int $priceMinor,
        public ?int $compareAtMinor,
        public string $image,
        public string $description,
        public array $variants,
        public array $specifications = [],
        public string $badge = '',
        public bool $featured = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawImage = isset($data['image']) && is_string($data['image']) ? $data['image'] : '/images/placeholder.svg';
        $image = '/images/placeholder.svg';

        if (str_starts_with($rawImage, '/') && ! str_starts_with($rawImage, '//')) {
            $image = $rawImage;
        } else {
            $parsedScheme = parse_url($rawImage, PHP_URL_SCHEME);
            if (filter_var($rawImage, FILTER_VALIDATE_URL) !== false && $parsedScheme === 'https') {
                $image = $rawImage;
            }
        }

        $priceMinor = isset($data['price_minor']) && is_numeric($data['price_minor'])
            ? max(0, (int) $data['price_minor'])
            : 0;

        $compareAtMinor = isset($data['compare_at_minor']) && is_numeric($data['compare_at_minor'])
            ? (int) $data['compare_at_minor']
            : null;

        $rawVariants = isset($data['variants']) && is_array($data['variants']) ? $data['variants'] : [];
        $variants = [];

        foreach ($rawVariants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $color = isset($variant['color']) && is_string($variant['color']) && preg_match('/^#[0-9a-f]{6}$/i', $variant['color']) === 1
                ? $variant['color']
                : '#222222';

            $stock = isset($variant['stock']) && is_numeric($variant['stock'])
                ? max(0, (int) $variant['stock'])
                : 0;

            $variantPrice = isset($variant['price_minor']) && is_numeric($variant['price_minor'])
                ? max(0, (int) $variant['price_minor'])
                : $priceMinor;

            $variants[] = [
                'id' => isset($variant['id']) ? (string) $variant['id'] : '',
                'label' => isset($variant['label']) ? (string) $variant['label'] : '',
                'color' => $color,
                'stock' => $stock,
                'price_minor' => $variantPrice,
            ];
        }

        /** @var array<string, mixed> $specifications */
        $specifications = isset($data['specifications']) && is_array($data['specifications'])
            ? $data['specifications']
            : [];

        return new self(
            id: isset($data['id']) ? (string) $data['id'] : '',
            slug: isset($data['slug']) ? (string) $data['slug'] : '',
            name: isset($data['name']) ? (string) $data['name'] : '',
            brand: isset($data['brand']) ? (string) $data['brand'] : '',
            category: isset($data['category']) ? (string) $data['category'] : '',
            priceMinor: $priceMinor,
            compareAtMinor: $compareAtMinor,
            image: $image,
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : '',
            variants: $variants,
            specifications: $specifications,
            badge: isset($data['badge']) && is_string($data['badge']) ? $data['badge'] : '',
            featured: isset($data['featured']) && (bool) $data['featured'],
        );
    }

    public static function money(int $minor): string
    {
        return 'Rs. '.number_format($minor / 100, $minor % 100 === 0 ? 0 : 2);
    }

    /**
     * @return array{id: string, label: string, color: string, stock: int, price_minor: int}|null
     */
    public function availableVariant(): ?array
    {
        foreach ($this->variants as $variant) {
            if ($variant['stock'] > 0) {
                return $variant;
            }
        }

        return null;
    }

    public function discount(): int
    {
        if ($this->compareAtMinor !== null && $this->compareAtMinor > $this->priceMinor && $this->compareAtMinor > 0) {
            return (int) round((1 - $this->priceMinor / $this->compareAtMinor) * 100);
        }

        return 0;
    }
}
