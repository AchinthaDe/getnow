# GetNow Storefront: Homepage Migration & API Integration Specification

> **Purpose**: This specification details everything required to extract, transfer, and render **just the storefront homepage** (and its enclosing layout shell) from the `getnow-storefront` repository into `C:\dev\getnow-cms` and wire its layout shell (`resources/views/layouts/app.blade.php`) to the CMS's `GetLayoutContent` action (`GET /api/v1/content/layout`).
>
> All values, schemas, paths, and configurations below are extracted directly from the working storefront codebase without abbreviations or omissions.

---

## 1. Architectural Overview

The storefront frontend is a **Laravel 12 / PHP 8.2+** application with:
* **Templating**: Laravel Blade templates with anonymous Blade components (`<x-...>`).
* **Styling**: Pure Vanilla CSS organized into modular stylesheets. No Tailwind CSS, Bootstrap, or utility libraries are installed.
* **Scripting**: Lightweight Vanilla JavaScript ES modules. No React, Vue, Alpine.js, or Livewire are used.
* **Asset Bundler**: Vite via `laravel-vite-plugin`.
* **Data Flow**: The controller queries a repository contract (`CatalogRepository`). In local demo mode, data is loaded from JSON files (`resources/data/products.json`, `resources/data/categories.json`) and mapped into `Product` DTOs. In API mode, it queries backend REST endpoints.

---

## 2. File Transfer Manifest

To run the homepage and its layout shell in `C:\dev\getnow-cms`, copy the following exact files:

### A. Blade Views & Components
| Source Path (in `getnow-storefront`) | Target Path (in `getnow-cms`) | Role / Description |
| :--- | :--- | :--- |
| `resources/views/layouts/app.blade.php` | `resources/views/layouts/app.blade.php` | Main storefront shell (head, announcement bar, header, desktop nav, mobile drawer, flash messages, footer). |
| `resources/views/storefront/home.blade.php` | `resources/views/storefront/home.blade.php` | Homepage content (hero grid, service strip, category grid, featured products, collection banner, new arrivals, brand strip). |
| `resources/views/components/logo.blade.php` | `resources/views/components/logo.blade.php` | Brand logo linked to `route('home')`. |
| `resources/views/components/icon.blade.php` | `resources/views/components/icon.blade.php` | SVG icon dictionary with 27 inline SVGs. |
| `resources/views/components/promo-carousel.blade.php` | `resources/views/components/promo-carousel.blade.php` | Hero offer slider with autoplay, pause/play, swipe, and pagination. |
| `resources/views/components/product-card.blade.php` | `resources/views/components/product-card.blade.php` | Product card with discount badge, price formatting, wishlist button, and quick-add form. |

### B. Stylesheets (CSS)
All storefront stylesheets reside in `resources/css/storefront/` and are imported into `resources/css/app.css`:
* `resources/css/app.css` (Master import file)
* `resources/css/storefront/base.css` (Color variables, typography, reset, buttons, `.container`, `.sr-only`, `.skip-link`)
* `resources/css/storefront/layout.css` (`.announcement`, `.site-header`, `.search-bar`, `.desktop-nav`, `.drawer`, `.site-footer`)
* `resources/css/storefront/components.css` (`.logo`, `.icon`, `.product-card`, `.badge`, `.save-product`, `.quick-add`, `.notice`, `.error-summary`)
* `resources/css/storefront/pages.css` (`.home-intro`, `.hero-grid`, `.hero-side`, `.service-strip`, `.category-grid`, `.collection-banner`, `.brand-strip`)
* `resources/css/storefront/carousel.css` (`.promo-carousel`, `.promo-slide`, `.promo-copy`, `.promo-art`, `.promo-controls`)
* `resources/css/storefront/responsive.css` (Media queries for mobile, tablet, and desktop breakpoints)

### C. JavaScript (Modules)
Pure Vanilla ES modules loaded via `resources/js/app.js`:
* `resources/js/app.js` (Master entrypoint)
* `resources/js/modules/carousel.js` (Required for homepage hero carousel: timer, dots, pause/play, aria-live)
* `resources/js/modules/dialogs.js` (Required for mobile menu drawer `<dialog id="mobile-menu">` and category dropdown click-outside)
* `resources/js/modules/forms.js` (Required for notification dismissal `[data-dismiss-notice]` and broken image fallback to placeholder)

### D. Static Media & Assets
* `public/favicon.svg` (Brand favicon)
* `public/images/placeholder.svg` (Fallback image for missing product images)
* `public/images/products/marshall-acton-iii.png` (Static hero side banner on homepage)
* `public/images/products/shargeek-300.jpg` (Collection feature banner on homepage)
* `public/images/products/soundcore-space-2.jpg` (Hero carousel slide 1 image & audio category image)
* `public/images/products/galaxy-s26-ultra.webp` (Hero carousel slide 2 image & phones category image)
* `public/images/products/ugreen-uno-65w.png` (Hero carousel slide 3 image)
* `public/images/products/huawei-gt-6-pro.jpg` (Wearables category image)
* `public/images/products/jbl-xtreme-5.png` (Speakers category image)
* `public/images/products/ps5-slim.jpg` (Gaming category image)
* `public/images/products/baseus-p1-camera.webp` (Smart home category image)
* `public/images/products/sharge-disk-pro.jpg` (Accessories category image)
* `public/images/products/airpods-pro-3.png` (Featured product image)
* `public/images/products/soundcore-r50i-nc.jpg` (Catalog product image)

### E. Configuration
* `config/promotions.php` (Hero promotion slides configuration)
* `config/storefront.php` (Store settings, currency, free delivery minor units, API endpoints)

---

## 3. Data Contracts & Variable Schema

### 3.1 Full `Product` DTO Definition

**Class Location**: `App\Domain\Catalog\Data\Product`

```php
<?php

namespace App\Domain\Catalog\Data;

final readonly class Product
{
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

    public static function fromArray(array $data): self
    {
        $image = (string) ($data['image'] ?? '/images/placeholder.svg');
        if (!str_starts_with($image, '/') || str_starts_with($image, '//')) {
            $image = filter_var($image, FILTER_VALIDATE_URL) && parse_url($image, PHP_URL_SCHEME) === 'https'
                ? $image : '/images/placeholder.svg';
        }
        return new self(
            (string) $data['id'],
            (string) $data['slug'],
            (string) $data['name'],
            (string) $data['brand'],
            (string) $data['category'],
            max(0, (int) $data['price_minor']),
            isset($data['compare_at_minor']) ? (int) $data['compare_at_minor'] : null,
            $image,
            (string) ($data['description'] ?? ''),
            array_map(fn ($variant) => [
                'id' => (string) $variant['id'],
                'label' => (string) $variant['label'],
                'color' => preg_match('/^#[0-9a-f]{6}$/i', $variant['color'] ?? '') ? $variant['color'] : '#222222',
                'stock' => max(0, (int) $variant['stock']),
                'price_minor' => max(0, (int) ($variant['price_minor'] ?? $data['price_minor'])),
            ], $data['variants'] ?? []),
            $data['specifications'] ?? [],
            (string) ($data['badge'] ?? ''),
            (bool) ($data['featured'] ?? false),
        );
    }

    public static function money(int $minor): string
    {
        return 'Rs. '.number_format($minor / 100, $minor % 100 === 0 ? 0 : 2);
    }

    public function availableVariant(): ?array
    {
        return collect($this->variants)->first(fn ($variant) => $variant['stock'] > 0);
    }

    public function discount(): int
    {
        return $this->compareAtMinor > $this->priceMinor
            ? (int) round((1 - $this->priceMinor / $this->compareAtMinor) * 100) : 0;
    }
}
```

#### Properties and Methods Accessed by Blade
Across `storefront/home.blade.php` and `components/product-card.blade.php`, the following are accessed:
* `$product->id`: String ID of the product.
* `$product->slug`: String unique slug used in `route('product', $product->slug)`.
* `$product->name`: String product name displayed in card heading and image alt.
* `$product->brand`: String brand name displayed above product title (`$product->brand`).
* `$product->category`: String category slug, formatted in UI using `str_replace('-', ' ', $product->category)`.
* `$product->priceMinor`: Integer minor currency units (e.g. `4499900` = Rs. 44,999.00).
* `$product->compareAtMinor`: Nullable integer minor currency units used to render strikethrough original price `<del>{{ $product::money($product->compareAtMinor) }}</del>`.
* `$product->image`: String image path or HTTPS URL.
* `$product->badge`: String badge label (e.g. `'Popular'`, `'New arrival'`).
* `$product->availableVariant()`: Method returning the first variant array with `stock > 0`, or `null` if sold out.
* `$variant['id']`: String variant identifier sent in quick-add POST form.
* `$product->discount()`: Method returning integer percentage savings (e.g. `10` for `-10%`).
* `Product::money(int $minor)`: Static method formatting minor units to `'Rs. X,XXX'`. Also called in `layouts/app.blade.php` for `config('storefront.free_delivery_minor')`.

---

### 3.2 Full `Category` Data Structure

#### Structure & Key Names
Each category in the `$categories` array is an associative array with 4 keys:
```php
[
    'slug'  => 'audio',
    'name'  => 'Headphones & earbuds',
    'icon'  => 'headphones',
    'image' => '/images/products/soundcore-space-2.jpg',
]
```

#### Access in Views
* **In `layouts/app.blade.php` (Desktop Dropdown)**:
  ```blade
  @foreach($categories as $category)
      <a href="{{ route('shop', ['category'=>$category['slug']]) }}">
          <x-icon :name="$category['icon'] ?? 'box'" size="20"/>
          {{ $category['name'] }}
      </a>
  @endforeach
  ```
* **In `layouts/app.blade.php` (Mobile Menu Drawer)**:
  ```blade
  @foreach($categories as $category)
      <a href="{{ route('shop', ['category'=>$category['slug']]) }}">
          <x-icon :name="$category['icon'] ?? 'box'"/>
          {{ $category['name'] }}
      </a>
  @endforeach
  ```
* **In `storefront/home.blade.php` (Category Grid)**:
  ```blade
  @foreach($categories as $category)
      <a class="category-card" href="{{ route('shop',['category'=>$category['slug']]) }}">
          <span class="category-image">
              <img src="{{ $category['image'] }}" alt="" loading="lazy" width="180" height="180">
          </span>
          <span>{{ $category['name'] }}</span>
      </a>
  @endforeach
  ```

#### How `$categories` is Passed to `layouts/app.blade.php`
In `app/Providers/AppServiceProvider.php` (lines 27–30), a View Composer registers `$categories` globally for all storefront views:
```php
View::composer(['layouts.app', 'storefront.home', 'storefront.catalog'], function ($view) {
    $view->with('categories', app(CatalogRepository::class)->categories());
    $view->with('cartCount', array_sum(array_column(session('cart', []), 'quantity')));
});
```
When running demo mode, `app(CatalogRepository::class)->categories()` decodes `resources/data/categories.json`.

---

### 3.3 Full `Promotion` Data Structure

**Source**: `config/promotions.php`

```php
<?php

return [
    'enabled' => true,
    'autoplay' => true,
    'interval' => 6000,
    'slides' => [
        [
            'id' => 'audio-offers',
            'theme' => 'sky',
            'eyebrow' => 'FIND YOUR FREQUENCY',
            'title' => ['Less noise.', 'More you.'],
            'description' => 'Make room for better sound. Explore offers on everyday audio.',
            'label' => 'Audio offers',
            'image' => 'images/products/soundcore-space-2.jpg',
            'alt' => 'Soundcore Space 2 over-ear headphones',
            'cta' => 'Shop audio offers',
            'route' => 'shop',
            'parameters' => ['category' => 'audio', 'deals' => 1],
        ],
        [
            'id' => 'phone-edit',
            'theme' => 'lilac',
            'eyebrow' => 'THE NEXT CHAPTER',
            'title' => ['Your next', 'big thing.'],
            'description' => 'A fresh perspective in your pocket. Find your next everyday upgrade.',
            'label' => 'The phone edit',
            'image' => 'images/products/galaxy-s26-ultra.webp',
            'alt' => 'Samsung Galaxy S26 Ultra smartphone',
            'cta' => 'Explore phones',
            'route' => 'shop',
            'parameters' => ['category' => 'phones'],
        ],
        [
            'id' => 'charging-offers',
            'theme' => 'mint',
            'eyebrow' => 'READY FOR EVERY DAY',
            'title' => ['Small kit.', 'Big energy.'],
            'description' => 'Keep the good things going. Discover offers on charging essentials.',
            'label' => 'Charging offers',
            'image' => 'images/products/ugreen-uno-65w.png',
            'alt' => 'UGREEN Uno 65W charger',
            'cta' => 'Shop charging offers',
            'route' => 'shop',
            'parameters' => ['category' => 'charging', 'deals' => 1],
        ],
    ],
];
```

#### Keys Accessed in Views
* **In `storefront/home.blade.php`**:
  * `$promotions['enabled']`: Boolean toggling carousel visibility.
  * `$promotions['slides']`: Array of slide definitions.
  * `$promotions['autoplay']`: Boolean passed to `<x-promo-carousel :autoplay="...">`.
  * `$promotions['interval']`: Integer milliseconds passed to `<x-promo-carousel :interval="...">`.
* **In `components/promo-carousel.blade.php`**:
  * `$slide['id']`: Unique slug for DOM IDs (`home-offers-audio-offers`).
  * `$slide['theme']`: CSS modifier class `promo-slide--{{ $slide['theme'] }}` (`sky`, `lilac`, or `mint`).
  * `$slide['eyebrow']`: Small uppercase header tag above the title.
  * `$slide['title']`: Array of strings, looped as `@foreach($slide['title'] as $line)<span>{{ $line }}</span>@endforeach`.
  * `$slide['description']`: Subtitle paragraph copy.
  * `$slide['cta']`: Button label (e.g. `'Shop audio offers'`).
  * `$slide['route']`: Named route (e.g. `'shop'`).
  * `$slide['parameters']`: Query array passed to `route($slide['route'], $slide['parameters'] ?? [])`.
  * `$slide['image']`: Path passed to `asset($slide['image'])`.
  * `$slide['alt']`: Image alt attribute.
  * `$slide['label']`: Accessible slide label for `aria-label` and pagination dots.

---

### 3.4 Exact Mock/Sample Data Arrays for Local Homepage Rendering

These are the exact 4 featured products, 4 newest products, 8 categories, and 3 promotions currently used to render the homepage in demo mode without any database:

#### A. `$featured` Products (4 items)
```php
$featured = [
    \App\Domain\Catalog\Data\Product::fromArray([
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
    \App\Domain\Catalog\Data\Product::fromArray([
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
    \App\Domain\Catalog\Data\Product::fromArray([
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
    \App\Domain\Catalog\Data\Product::fromArray([
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
```

#### B. `$newProducts` (4 items)
```php
$newProducts = [
    \App\Domain\Catalog\Data\Product::fromArray([
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
    \App\Domain\Catalog\Data\Product::fromArray([
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
    \App\Domain\Catalog\Data\Product::fromArray([
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
    \App\Domain\Catalog\Data\Product::fromArray([
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
```

#### C. `$categories` (8 items)
```php
$categories = [
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
```

---

## 4. Exact Hardcoded Layout Content to Seed into the CMS (`P1-S1`)

The following literal strings and structures are currently hardcoded in `resources/views/layouts/app.blade.php` and `config/storefront.php`. Seed these directly into your CMS database or API mocks:

### 4.1 Site Settings
* `site_name`: `"GetNow"` (from `env('STOREFRONT_NAME', 'GetNow')`)
* `default_meta_description`: `"Discover headphones, phones, smartwatches and everyday essentials at GetNow. Shop in Sri Lankan rupees."` (from line 7 of `layouts/app.blade.php`)
* `search_placeholder`: `"What are you looking for?"` (from line 19 of `layouts/app.blade.php`)
* `footer_brand_text`: `"Thoughtfully chosen tech.\nFor the way you live."` (from line 40 of `layouts/app.blade.php`: `<p>Thoughtfully chosen tech.<br>For the way you live.</p>`)
* `currency`: `"LKR"`
* `country_tagline`: `"Sri Lanka · Prices in LKR"`
* `free_delivery_minor`: `2500000` (Rs. 25,000.00)

### 4.2 Announcement Bar (`announcement_bar` placement)
* **Item 1**: `"Good tech. Better everyday."`
* **Item 2**: `"Free delivery on orders over Rs. 25,000"` (dynamically calculated from `config('storefront.free_delivery_minor')`)
* **Item 3**: `"Sri Lanka / LKR"`
* **Link URL**: `null` (plain non-clickable spans)
* **Tone**: `"info"` (rendered on `--surface-ink` background)

### 4.3 Navigation Menus

#### Menu 1: `main_nav` (Desktop Header Bar)
| Order | Label | Route / URL | Icon | Notes |
| :--- | :--- | :--- | :--- | :--- |
| 1 | `Browse categories` | `null` | `menu` | Opens `.category-dropdown` containing all 8 categories mapped to `/shop?category={slug}` |
| 2 | `All products` | `/shop` | `none` | Has active class `@class(['active'=>request()->routeIs('shop') && !request()->query()])` |
| 3 | `New arrivals` | `/shop?sort=newest` | `none` | |
| 4 | `Trending now` | `/shop?sort=featured` | `none` | |
| 5 | `The good deals` | `/shop?deals=1` | `bolt` | Has class `deal-link` |
| 6 | `Need a hand?` | `/help` | `headphones` | Has class `nav-help` |

#### Menu 2: `mobile_nav` (Drawer `<dialog id="mobile-menu">`)
| Order | Label | Route / URL | Icon | Notes |
| :--- | :--- | :--- | :--- | :--- |
| 1 | `Shop everything` | `/shop` | `none` | First link in drawer |
| 2..9 | Category items | `/shop?category={slug}` | Category icon | Loops through `$categories` using `$category['icon'] ?? 'box'` |
| 10 | `My account` | `/login` | `user` | Links to `/account` if authenticated |
| 11 | `Saved items` | `/saved` | `heart` | |
| 12 | `Help & delivery` | `/help` | `none` | |

#### Menu 3: `footer_column_1`
* **Column Heading (`title`)**: `"Find your next favourite"`
* **Items**:
  1. Label: `"Headphones & earbuds"` | URL: `/shop?category=audio`
  2. Label: `"Smartphones"` | URL: `/shop?category=phones`
  3. Label: `"Smartwatches"` | URL: `/shop?category=wearables`
  4. Label: `"Shop all products"` | URL: `/shop`

#### Menu 4: `footer_column_2`
* **Column Heading (`title`)**: `"A little help"`
* **Items**:
  1. Label: `"Delivery information"` | URL: `/help#delivery`
  2. Label: `"Returns & warranty"` | URL: `/help#returns`
  3. Label: `"My account"` | URL: `/account`
  4. Label: `"Terms & privacy"` | URL: `/terms`

#### Menu 5: `footer_column_3` (Promotional Box)
* **Eyebrow**: `"YOUR EVERYDAY, UPGRADED."`
* **Column Heading (`title`)**: `"Small upgrades.<br>Big possibilities."`
* **Button Label**: `"Discover what's new"`
* **Button URL**: `/shop?sort=newest`

#### Footer Bottom Static Text
* **Copyright**: `"© 2026 GetNow. All rights reserved."`
* **Divider Tagline**: `"Cash on delivery / Made for your everyday"`
* **Demo Ribbon**: `"Demo storefront · Sample prices and availability · No real payments or orders"` (only visible when driver is `'demo'`)

### 4.4 URL Mapping & CMS `SafeLink` Compatibility
The CMS `SafeLink` validator requires all internal links to be valid relative paths starting with `/`. Here is the mapping from Blade helpers to safe paths:

| Blade Expression | Mapped CMS Safe URL |
| :--- | :--- |
| `route('home')` | `/` |
| `route('shop')` | `/shop` |
| `route('shop', ['category' => 'audio'])` | `/shop?category=audio` |
| `route('shop', ['category' => 'phones'])` | `/shop?category=phones` |
| `route('shop', ['category' => 'wearables'])` | `/shop?category=wearables` |
| `route('shop', ['category' => 'speakers'])` | `/shop?category=speakers` |
| `route('shop', ['category' => 'charging'])` | `/shop?category=charging` |
| `route('shop', ['sort' => 'newest'])` | `/shop?sort=newest` |
| `route('shop', ['sort' => 'featured'])` | `/shop?sort=featured` |
| `route('shop', ['deals' => 1])` | `/shop?deals=1` |
| `route('shop', ['brand' => $brand])` | `/shop?brand={brand}` |
| `route('product', $product->slug)` | `/products/{slug}` |
| `route('cart')` | `/bag` |
| `route('cart.store')` | `/bag` (POST) |
| `route('wishlist')` | `/saved` |
| `route('wishlist.toggle')` | `/saved` (POST) |
| `route('login')` | `/login` |
| `route('account')` | `/account` |
| `route('help')` | `/help` |
| `route('help') . '#delivery'` | `/help#delivery` |
| `route('help') . '#returns'` | `/help#returns` |
| `route('terms')` | `/terms` |

---

## 5. Named Routes, Config Keys, Session Helpers, and Vite Setup

### 5.1 Named Routes Called Across the 6 Blade Files

| Route Name | HTTP Method | Target URL in Storefront | Files Calling It |
| :--- | :--- | :--- | :--- |
| `home` | GET | `/` | `components/logo.blade.php` |
| `shop` | GET | `/shop` | `layouts/app.blade.php`, `storefront/home.blade.php`, `components/promo-carousel.blade.php` |
| `product` | GET | `/products/{slug}` | `components/product-card.blade.php` |
| `cart` | GET | `/bag` | `layouts/app.blade.php` |
| `cart.store` | POST | `/bag` | `components/product-card.blade.php` |
| `wishlist` | GET | `/saved` | `layouts/app.blade.php` |
| `wishlist.toggle` | POST | `/saved` | `components/product-card.blade.php` |
| `login` | GET | `/login` | `layouts/app.blade.php` |
| `account` | GET | `/account` | `layouts/app.blade.php` |
| `help` | GET | `/help` | `layouts/app.blade.php`, `storefront/home.blade.php` |
| `terms` | GET | `/terms` | `layouts/app.blade.php` |

### 5.2 Config Keys Read in Blade Files
* `config('storefront.name')`: Read in `layouts/app.blade.php` (lines 6, 41) and `components/logo.blade.php` (line 1).
* `config('storefront.driver')`: Read in `layouts/app.blade.php` (lines 8, 42). If `'demo'`, injects `<meta name="robots" content="noindex,nofollow">` and renders `.demo-ribbon`.
* `config('storefront.free_delivery_minor')`: Read in `layouts/app.blade.php` (line 14) to format threshold.

### 5.3 Session, Auth, and State Variables
* `csrf_token()`: Injected in `<meta name="csrf-token">` and `@csrf` forms.
* `session('customer')`: Evaluated as truthy/falsy in `layouts/app.blade.php` line 21. If present, header displays `'Welcome back'` and links to `account`; if null, displays `'Hello, sign in'` and links to `login`.
* `session('customer.name')`: Display name fallback string (`session('customer.name', 'My account')`).
* `$cartCount`: Total integer item count rendered in bag badge (`<span class="count">{{ $cartCount }}</span>`). Set via View Composer: `array_sum(array_column(session('cart', []), 'quantity'))`. Defaults to `0`.
* `session('wishlist', [])`: Array of product slug strings (e.g. `['soundcore-space-2']`). In `components/product-card.blade.php`, used to apply the `.is-saved` active heart class.
* `session('status')`: String flash message for `.notice` banner.
* `session('cart_added')`: Boolean indicating if a "View bag" link should accompany the notice.
* `$errors`: Laravel `ViewErrorBag`. `layouts/app.blade.php` checks `$errors->any()` and loops `$errors->all()` to render `.error-summary`.

### 5.4 Vite Setup

#### `vite.config.js`
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

#### `@vite` Directive in `layouts/app.blade.php` (line 10)
```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

---

## 6. Wiring Layout Shell to CMS `GetLayoutContent` API (`GET /api/v1/content/layout`)

When connecting the storefront shell to the CMS `GET /api/v1/content/layout` endpoint, the returned JSON payload can replace hardcoded strings and View Composers.

### CMS Expected JSON Structure
```json
{
  "data": {
    "settings": {
      "site_name": "GetNow",
      "default_meta_description": "Discover headphones, phones, smartwatches and everyday essentials at GetNow. Shop in Sri Lankan rupees.",
      "search_placeholder": "What are you looking for?",
      "footer_brand_text": "Thoughtfully chosen tech.\nFor the way you live.",
      "free_delivery_minor": 2500000
    },
    "placements": {
      "announcement_bar": {
        "text": "Good tech. Better everyday.",
        "threshold_text": "Free delivery on orders over Rs. 25,000",
        "locale_text": "Sri Lanka / LKR"
      }
    },
    "menus": {
      "main_nav": [
        {"label": "All products", "url": "/shop"},
        {"label": "New arrivals", "url": "/shop?sort=newest"},
        {"label": "Trending now", "url": "/shop?sort=featured"},
        {"label": "The good deals", "url": "/shop?deals=1", "icon": "bolt"},
        {"label": "Need a hand?", "url": "/help", "icon": "headphones"}
      ],
      "footer_column_1": {
        "title": "Find your next favourite",
        "items": [
          {"label": "Headphones & earbuds", "url": "/shop?category=audio"},
          {"label": "Smartphones", "url": "/shop?category=phones"},
          {"label": "Smartwatches", "url": "/shop?category=wearables"},
          {"label": "Shop all products", "url": "/shop"}
        ]
      },
      "footer_column_2": {
        "title": "A little help",
        "items": [
          {"label": "Delivery information", "url": "/help#delivery"},
          {"label": "Returns & warranty", "url": "/help#returns"},
          {"label": "My account", "url": "/account"},
          {"label": "Terms & privacy", "url": "/terms"}
        ]
      },
      "footer_column_3": {
        "eyebrow": "YOUR EVERYDAY, UPGRADED.",
        "title": "Small upgrades.<br>Big possibilities.",
        "button_label": "Discover what's new",
        "button_url": "/shop?sort=newest"
      }
    }
  }
}
```

---

## 7. Migration Verification Checklist

Before running UI integration tests, verify:
* [ ] **Vite compilation**: `npm run build` succeeds without unresolved CSS `@import` or JS module errors.
* [ ] **Hero carousel behavior**:
  * Slide 1 displays eagerly with `fetchpriority="high"`.
  * Controls appear only after JS initialization.
  * Pause/Play toggles rotation interval.
  * Dot pagination advances active slide.
* [ ] **Mobile dialog**: Clicking mobile burger opens native `<dialog id="mobile-menu">`; clicking close or outside closes it.
* [ ] **Product cards**:
  * Prices render formatted currency strings (`Rs. 44,999`).
  * Sale items render discount pill (`-10%`).
  * In-stock items render quick-add plus button; out-of-stock items render view magnifying glass.
* [ ] **Broken image fallback**: Any failed image URL gracefully replaces itself with `/images/placeholder.svg` via `modules/forms.js`.
