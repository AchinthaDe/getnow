<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Content\Actions\GetLayoutContent;
use App\Http\Controllers\Controller;
use App\Support\DemoStorefront\Product;
use Illuminate\Contracts\View\View;

final class DemoHomeController extends Controller
{
    public function __invoke(GetLayoutContent $getLayoutContent): View
    {
        $layoutResponse = $getLayoutContent();
        $layout = $layoutResponse->data;

        $featured = self::getFeaturedProducts();
        $newProducts = self::getNewProducts();
        $categories = self::getCategories();
        $cartCount = 0;

        return view('storefront.home', compact('layout', 'featured', 'newProducts', 'categories', 'cartCount'));
    }

    /**
     * @return array<int, Product>
     */
    public static function getFeaturedProducts(): array
    {
        return [
            Product::fromArray([
                'id' => '1',
                'slug' => 'soundcore-space-2',
                'name' => 'Soundcore Space 2',
                'brand' => 'Anker',
                'category' => 'audio',
                'price_minor' => 4499900,
                'compare_at_minor' => 4999900,
                'image' => '/images/products/soundcore-space-2.jpg',
                'description' => 'Anker Soundcore Space 2 ANC Over-Ear Headphones. A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'soundcore-space-2-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 4499900],
                    ['id' => 'soundcore-space-2-blue', 'label' => 'Blue', 'color' => '#5d718e', 'stock' => 3, 'price_minor' => 4599900],
                ],
                'badge' => 'Popular',
                'featured' => true,
            ]),
            Product::fromArray([
                'id' => '2',
                'slug' => 'airpods-pro-3',
                'name' => 'AirPods Pro 3',
                'brand' => 'Apple',
                'category' => 'audio',
                'price_minor' => 6999000,
                'compare_at_minor' => 7499000,
                'image' => '/images/products/airpods-pro-3.png',
                'description' => 'Apple AirPods Pro 3. A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'airpods-pro-3-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 6999000],
                ],
                'badge' => 'New arrival',
                'featured' => true,
            ]),
            Product::fromArray([
                'id' => '4',
                'slug' => 'jbl-xtreme-5',
                'name' => 'JBL Xtreme 5',
                'brand' => 'JBL',
                'category' => 'speakers',
                'price_minor' => 7499000,
                'compare_at_minor' => 7999000,
                'image' => '/images/products/jbl-xtreme-5.png',
                'description' => 'JBL Xtreme 5 Portable Bluetooth Speaker. A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'jbl-xtreme-5-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 7499000],
                ],
                'badge' => 'Popular',
                'featured' => true,
            ]),
            Product::fromArray([
                'id' => '7',
                'slug' => 'huawei-gt-6-pro',
                'name' => 'Watch GT 6 Pro',
                'brand' => 'Huawei',
                'category' => 'wearables',
                'price_minor' => 7999000,
                'compare_at_minor' => null,
                'image' => '/images/products/huawei-gt-6-pro.jpg',
                'description' => 'Huawei Watch GT 6 Pro. A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'huawei-gt-6-pro-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 7999000],
                ],
                'badge' => 'New arrival',
                'featured' => true,
            ]),
        ];
    }

    /**
     * @return array<int, Product>
     */
    public static function getNewProducts(): array
    {
        return [
            Product::fromArray([
                'id' => '12',
                'slug' => 'sharge-disk-pro',
                'name' => 'Sharge Disk Pro',
                'brand' => 'Sharge',
                'category' => 'accessories',
                'price_minor' => 1799000,
                'compare_at_minor' => null,
                'image' => '/images/products/sharge-disk-pro.jpg',
                'description' => 'Sharge Disk Pro Portable SSD Hub (SD003). A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'sharge-disk-pro-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 0, 'price_minor' => 1799000],
                ],
                'badge' => '',
                'featured' => false,
            ]),
            Product::fromArray([
                'id' => '11',
                'slug' => 'baseus-p1-camera',
                'name' => 'Baseus P1 Lite',
                'brand' => 'Baseus',
                'category' => 'smart-home',
                'price_minor' => 949000,
                'compare_at_minor' => null,
                'image' => '/images/products/baseus-p1-camera.webp',
                'description' => 'Baseus 2K P1 Lite Indoor Security Camera. A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'baseus-p1-camera-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 949000],
                ],
                'badge' => '',
                'featured' => false,
            ]),
            Product::fromArray([
                'id' => '10',
                'slug' => 'ps5-slim',
                'name' => 'PlayStation 5 Slim',
                'brand' => 'Sony',
                'category' => 'gaming',
                'price_minor' => 18999000,
                'compare_at_minor' => null,
                'image' => '/images/products/ps5-slim.jpg',
                'description' => 'Sony PlayStation 5 (PS5) - Slim Disk Edition (1TB). A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'ps5-slim-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 18999000],
                ],
                'badge' => '',
                'featured' => false,
            ]),
            Product::fromArray([
                'id' => '9',
                'slug' => 'shargeek-300',
                'name' => 'Shargeek 300',
                'brand' => 'Sharge',
                'category' => 'charging',
                'price_minor' => 4499000,
                'compare_at_minor' => 4999000,
                'image' => '/images/products/shargeek-300.jpg',
                'description' => 'Sharge Shargeek 300 24000mAh Power bank. A thoughtful addition to your everyday setup. Choose your finish and make it yours.',
                'variants' => [
                    ['id' => 'shargeek-300-standard', 'label' => 'Original finish', 'color' => '#242528', 'stock' => 8, 'price_minor' => 4499000],
                ],
                'badge' => 'New arrival',
                'featured' => false,
            ]),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function getCategories(): array
    {
        return [
            [
                'slug' => 'audio',
                'name' => 'Headphones & earbuds',
                'icon' => 'headphones',
                'image' => '/images/products/soundcore-space-2.jpg',
            ],
            [
                'slug' => 'phones',
                'name' => 'Smartphones',
                'icon' => 'phone',
                'image' => '/images/products/galaxy-s26-ultra.webp',
            ],
            [
                'slug' => 'wearables',
                'name' => 'Smartwatches',
                'icon' => 'watch',
                'image' => '/images/products/huawei-gt-6-pro.jpg',
            ],
            [
                'slug' => 'speakers',
                'name' => 'Speakers',
                'icon' => 'speaker',
                'image' => '/images/products/jbl-xtreme-5.png',
            ],
            [
                'slug' => 'charging',
                'name' => 'Power & charging',
                'icon' => 'battery',
                'image' => '/images/products/shargeek-300.jpg',
            ],
            [
                'slug' => 'gaming',
                'name' => 'Gaming',
                'icon' => 'gamepad',
                'image' => '/images/products/ps5-slim.jpg',
            ],
            [
                'slug' => 'smart-home',
                'name' => 'Smart home',
                'icon' => 'home',
                'image' => '/images/products/baseus-p1-camera.webp',
            ],
            [
                'slug' => 'accessories',
                'name' => 'Accessories',
                'icon' => 'laptop',
                'image' => '/images/products/sharge-disk-pro.jpg',
            ],
        ];
    }
}
