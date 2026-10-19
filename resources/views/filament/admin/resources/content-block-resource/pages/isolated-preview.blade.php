<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'GetNow Store') }} — Isolated Preview</title>
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
</head>
<body class="bg-white text-gray-900 antialiased">
    @include('storefront.partials.preview-chrome', [
        'previewModeActive' => true,
        'usingFallbackPayload' => $preview->usingFallbackPayload,
        'isUnservable' => $preview->isUnservable,
    ])

    @if(! $preview->isUnservable && $preview->block !== null && $preview->placement !== null)
        @php
            $targetBlock = $preview->block;
            $placement = $preview->placement;
        @endphp

        @if($placement === \App\Domain\Content\Enums\Placement::ANNOUNCEMENT_BAR)
            @include('storefront.partials.blocks.announcement-bar', [
                'block' => $targetBlock,
                'previewTarget' => true,
            ])
        @elseif($placement === \App\Domain\Content\Enums\Placement::HOME_HERO_CAROUSEL)
            <div class="container">
                @include('storefront.partials.blocks.hero-carousel', [
                    'slides' => [$targetBlock],
                    'previewTargetId' => $targetBlock->id,
                ])
            </div>
        @elseif($placement === \App\Domain\Content\Enums\Placement::HOME_HERO_SIDE)
            <div class="container">
                @include('storefront.partials.blocks.feature-banner', [
                    'block' => $targetBlock,
                    'variant' => 'hero_side',
                    'previewTarget' => true,
                ])
            </div>
        @elseif($placement === \App\Domain\Content\Enums\Placement::HOME_SERVICE_STRIP)
            <div class="container">
                @include('storefront.partials.blocks.service-strip', [
                    'items' => [$targetBlock],
                    'previewTargetId' => $targetBlock->id,
                ])
            </div>
        @elseif(in_array($placement, [
            \App\Domain\Content\Enums\Placement::HOME_CATEGORIES_HEADER,
            \App\Domain\Content\Enums\Placement::HOME_FEATURED_HEADER,
            \App\Domain\Content\Enums\Placement::HOME_NEW_ARRIVALS_HEADER,
        ], true))
            <div class="container">
                @include('storefront.partials.blocks.section-header', [
                    'block' => $targetBlock,
                    'previewTarget' => true,
                ])
            </div>
        @elseif($placement === \App\Domain\Content\Enums\Placement::HOME_COLLECTION_BANNER)
            <div class="container">
                @include('storefront.partials.blocks.feature-banner', [
                    'block' => $targetBlock,
                    'variant' => 'collection',
                    'previewTarget' => true,
                ])
            </div>
        @elseif($placement === \App\Domain\Content\Enums\Placement::HOME_BRAND_STRIP)
            @include('storefront.partials.blocks.brand-strip', [
                'block' => $targetBlock,
                'previewTarget' => true,
            ])
        @endif
    @endif
</body>
</html>
