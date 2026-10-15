@extends('layouts.storefront')
@section('content')
<h1 class="sr-only">GetNow — electronics for your everyday</h1>
<div class="container home-intro">
<section class="hero-grid {{ !$promotions['enabled'] || !count($promotions['slides']) ? 'hero-grid--single' : '' }}" aria-label="Featured collections">
    @if($promotions['enabled'] && count($promotions['slides']))
        <x-promo-carousel :slides="$promotions['slides']" :autoplay="$promotions['autoplay']" :interval="$promotions['interval']"/>
    @endif
    <article class="hero hero-side">
        <div>
            <span class="eyebrow">MAKE ROOM FOR GREAT SOUND</span>
            <h2>Big sound.<br>Small footprint.</h2>
            <a class="text-link" href="{{ route('shop', ['category' => 'speakers']) }}">Explore speakers</a>
        </div>
        <img src="{{ asset('images/products/marshall-acton-iii.png') }}" alt="Marshall Acton III speaker" width="900" height="852">
        <span class="side-caption">MARSHALL ACTON III</span>
    </article>
</section>
<div class="service-strip">
    <div>
        <x-icon name="truck" size="27"/>
        <span><strong>Islandwide delivery</strong><small>From our store to your door</small></span>
    </div>
    <div>
        <x-icon name="box" size="27"/>
        <span><strong>Cash on delivery</strong><small>Pay when your order arrives</small></span>
    </div>
    <div>
        <x-icon name="shield" size="27"/>
        <span><strong>Shop with confidence</strong><small>Warranty details on every item</small></span>
    </div>
    <a href="{{ route('help') }}">
        <x-icon name="headphones" size="27"/>
        <span><strong>Here to help</strong><small>A little guidance goes a long way</small></span>
    </a>
</div>
</div>
<section class="container section category-section">
    <div class="section-heading">
        <div><span class="eyebrow">FIND YOUR THING</span><h2>A little something for every day.</h2></div>
        <a class="text-link" href="{{ route('shop') }}">Explore all categories</a>
    </div>
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
<section class="container section">
    <div class="section-heading">
        <div><span class="eyebrow">THE ONES TO KNOW</span><h2>Your next favourites.</h2></div>
        <a class="text-link" href="{{ route('shop', ['sort' => 'featured']) }}">Shop the collection</a>
    </div>
    <div class="product-grid">
        @foreach($featured as $product)
            <x-product-card :product="$product"/>
        @endforeach
    </div>
</section>
<section class="container section">
    <div class="collection-banner">
        <div class="collection-copy">
            <span class="eyebrow">POWER YOUR EVERYDAY</span>
            <h2>Low battery?<br>Not your energy.</h2>
            <p>Power banks, chargers, and clever little essentials.<br>Ready for wherever the day takes you.</p>
            <a class="button button-white" href="{{ route('shop', ['category' => 'charging']) }}">Stay charged</a>
        </div>
        <div class="collection-art">
            <img src="{{ asset('images/products/shargeek-300.jpg') }}" alt="Shargeek 300 power bank" width="900" height="900" loading="lazy">
        </div>
        <span class="collection-number">24/7</span>
    </div>
</section>
<section class="container section">
    <div class="section-heading">
        <div><span class="eyebrow">FRESH ON THE SHELF</span><h2>New things. New possibilities.</h2></div>
        <a class="text-link" href="{{ route('shop', ['sort' => 'newest']) }}">See what's new</a>
    </div>
    <div class="product-grid">
        @foreach($newProducts as $product)
            <x-product-card :product="$product"/>
        @endforeach
    </div>
</section>
<section class="brand-strip container" aria-label="Shop by brand">
    <span>GOOD COMPANY<br><strong>Great brands.</strong></span>
    @foreach(['Apple', 'Samsung', 'Anker', 'JBL', 'Marshall', 'Sony'] as $brand)
        <a href="{{ route('shop', ['brand' => $brand]) }}">{{ $brand }}</a>
    @endforeach
</section>
@endsection
