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
                <x-promo-carousel :slides="$carouselSlides"/>
            @endif
            @if($heroSide)
                <article class="hero hero-side">
                    <div>
                        @if(!empty($heroSide->data['eyebrow']))
                            <span class="eyebrow">{{ $heroSide->data['eyebrow'] }}</span>
                        @endif
                        <h2>
                            {{ $heroSide->data['title_line_1'] }}
                            @if(!empty($heroSide->data['title_line_2']))
                                <br>{{ $heroSide->data['title_line_2'] }}
                            @endif
                        </h2>
                        @if(!empty($heroSide->data['description']))
                            <p>{{ $heroSide->data['description'] }}</p>
                        @endif
                        <a class="text-link banner-stretched-link" href="{{ $heroSide->data['cta_url'] }}">{{ $heroSide->data['cta_label'] }}</a>
                    </div>
                    <img src="{{ asset($heroSide->data['image_url']) }}" alt="{{ $heroSide->data['image_alt'] }}" width="900" height="852">
                    @if(!empty($heroSide->data['badge_text']))
                        <span class="side-caption">{{ $heroSide->data['badge_text'] }}</span>
                    @endif
                </article>
            @endif
        </section>
    @endif
    @if(count($serviceItems) > 0)
        <div class="service-strip">
            @foreach($serviceItems as $item)
                @if(!empty($item->data['link_url']))
                    <a href="{{ $item->data['link_url'] }}">
                        <x-icon :name="$item->data['icon']" size="27"/>
                        <span><strong>{{ $item->data['title'] }}</strong><small>{{ $item->data['subtitle'] }}</small></span>
                    </a>
                @else
                    <div>
                        <x-icon :name="$item->data['icon']" size="27"/>
                        <span><strong>{{ $item->data['title'] }}</strong><small>{{ $item->data['subtitle'] }}</small></span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
@endif
@php($catHeader = $layout->placements['home_categories_header'][0] ?? null)
<section class="container section category-section">
    @if($catHeader)
        <div class="section-heading">
            <div>
                @if(!empty($catHeader->data['eyebrow']))
                    <span class="eyebrow">{{ $catHeader->data['eyebrow'] }}</span>
                @endif
                <h2>{{ $catHeader->data['title'] }}</h2>
            </div>
            @if(!empty($catHeader->data['cta_url']) && !empty($catHeader->data['cta_label']))
                <a class="text-link" href="{{ $catHeader->data['cta_url'] }}">{{ $catHeader->data['cta_label'] }}</a>
            @endif
        </div>
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
        <div class="section-heading">
            <div>
                @if(!empty($featHeader->data['eyebrow']))
                    <span class="eyebrow">{{ $featHeader->data['eyebrow'] }}</span>
                @endif
                <h2>{{ $featHeader->data['title'] }}</h2>
            </div>
            @if(!empty($featHeader->data['cta_url']) && !empty($featHeader->data['cta_label']))
                <a class="text-link" href="{{ $featHeader->data['cta_url'] }}">{{ $featHeader->data['cta_label'] }}</a>
            @endif
        </div>
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
        <div class="collection-banner">
            <div class="collection-copy">
                @if(!empty($collectionBanner->data['eyebrow']))
                    <span class="eyebrow">{{ $collectionBanner->data['eyebrow'] }}</span>
                @endif
                <h2>
                    {{ $collectionBanner->data['title_line_1'] }}
                    @if(!empty($collectionBanner->data['title_line_2']))
                        <br>{{ $collectionBanner->data['title_line_2'] }}
                    @endif
                </h2>
                @if(!empty($collectionBanner->data['description']))
                    <p>{!! nl2br(e($collectionBanner->data['description'])) !!}</p>
                @endif
                <a class="button button-white banner-stretched-link" href="{{ $collectionBanner->data['cta_url'] }}">{{ $collectionBanner->data['cta_label'] }}</a>
            </div>
            <div class="collection-art">
                <img src="{{ asset($collectionBanner->data['image_url']) }}" alt="{{ $collectionBanner->data['image_alt'] }}" width="900" height="900" loading="lazy">
            </div>
            @if(!empty($collectionBanner->data['badge_text']))
                <span class="collection-number">{{ $collectionBanner->data['badge_text'] }}</span>
            @endif
        </div>
    </section>
@endif
@php($newHeader = $layout->placements['home_new_arrivals_header'][0] ?? null)
<section class="container section">
    @if($newHeader)
        <div class="section-heading">
            <div>
                @if(!empty($newHeader->data['eyebrow']))
                    <span class="eyebrow">{{ $newHeader->data['eyebrow'] }}</span>
                @endif
                <h2>{{ $newHeader->data['title'] }}</h2>
            </div>
            @if(!empty($newHeader->data['cta_url']) && !empty($newHeader->data['cta_label']))
                <a class="text-link" href="{{ $newHeader->data['cta_url'] }}">{{ $newHeader->data['cta_label'] }}</a>
            @endif
        </div>
    @endif
    <div class="product-grid">
        @foreach($newProducts as $product)
            <x-product-card :product="$product"/>
        @endforeach
    </div>
</section>
@php($brandStrip = $layout->placements['home_brand_strip'][0] ?? null)
@if($brandStrip && !empty($brandStrip->data['brands']))
    <section class="brand-strip container" aria-label="Shop by brand">
        <span>
            @if(!empty($brandStrip->data['eyebrow']))
                {{ $brandStrip->data['eyebrow'] }}<br>
            @endif
            <strong>{{ $brandStrip->data['title'] }}</strong>
        </span>
        @foreach($brandStrip->data['brands'] as $brand)
            <a href="{{ $brand['url'] }}">{{ $brand['label'] }}</a>
        @endforeach
    </section>
@endif
@endsection
