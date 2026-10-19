@extends('layouts.storefront')
@section('content')
<h1 class="sr-only">GetNow — electronics for your everyday</h1>
@php
    $carouselSlides = $layout->placements['home_hero_carousel'] ?? [];
    $heroSide = $layout->placements['home_hero_side'][0] ?? null;
    $hasCarousel = count($carouselSlides) > 0;
    $serviceItems = $layout->placements['home_service_strip'] ?? [];
@endphp
@if($hasCarousel || $heroSide || count($serviceItems) > 0)
<div class="container home-intro">
    @if($hasCarousel || $heroSide)
        <section class="hero-grid {{ ! $hasCarousel ? 'hero-grid--single' : '' }}" aria-label="Featured collections">
            @if($hasCarousel)
                @include('storefront.partials.blocks.hero-carousel', [
                    'slides' => $carouselSlides,
                    'previewTargetId' => $previewTargetId ?? null,
                ])
            @endif
            @if($heroSide)
                @include('storefront.partials.blocks.feature-banner', [
                    'block' => $heroSide,
                    'variant' => 'hero_side',
                    'previewTarget' => ($previewTargetId ?? null) === $heroSide->id,
                ])
            @endif
        </section>
    @endif
    @if(count($serviceItems) > 0)
        @include('storefront.partials.blocks.service-strip', [
            'items' => $serviceItems,
            'previewTargetId' => $previewTargetId ?? null,
        ])
    @endif
</div>
@endif
@php($catHeader = $layout->placements['home_categories_header'][0] ?? null)
<section class="container section category-section">
    @if($catHeader)
        @include('storefront.partials.blocks.section-header', [
            'block' => $catHeader,
            'previewTarget' => ($previewTargetId ?? null) === $catHeader->id,
        ])
    @endif
    <div class="category-grid">
        @foreach($categories as $category)
            <a class="category-card" href="{{ route('shop', ['category' => $category['slug']]) }}">
                <span class="category-image">
                    <img src="{{ $category['image'] }}" alt="" loading="lazy" width="180" height="180">
                </span>
                <span>{{ $category['name'] }}</span>
            </a>
        @endforeach
    </div>
</section>
@php($featHeader = $layout->placements['home_featured_header'][0] ?? null)
<section class="container section">
    @if($featHeader)
        @include('storefront.partials.blocks.section-header', [
            'block' => $featHeader,
            'previewTarget' => ($previewTargetId ?? null) === $featHeader->id,
        ])
    @endif
    <div class="product-grid">
        @foreach($featured as $product)
            <x-product-card :product="$product"/>
        @endforeach
    </div>
</section>
@php($collectionBanner = $layout->placements['home_collection_banner'][0] ?? null)
@if($collectionBanner)
    <section class="container section">
        @include('storefront.partials.blocks.feature-banner', [
            'block' => $collectionBanner,
            'variant' => 'collection',
            'previewTarget' => ($previewTargetId ?? null) === $collectionBanner->id,
        ])
    </section>
@endif
@php($newHeader = $layout->placements['home_new_arrivals_header'][0] ?? null)
<section class="container section">
    @if($newHeader)
        @include('storefront.partials.blocks.section-header', [
            'block' => $newHeader,
            'previewTarget' => ($previewTargetId ?? null) === $newHeader->id,
        ])
    @endif
    <div class="product-grid">
        @foreach($newProducts as $product)
            <x-product-card :product="$product"/>
        @endforeach
    </div>
</section>
@php($brandStrip = $layout->placements['home_brand_strip'][0] ?? null)
@if($brandStrip && !empty($brandStrip->data['brands']))
    @include('storefront.partials.blocks.brand-strip', [
        'block' => $brandStrip,
        'previewTarget' => ($previewTargetId ?? null) === $brandStrip->id,
    ])
@endif
@endsection

