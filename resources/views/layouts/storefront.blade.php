<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Everyday tech. Extraordinary possibilities.') · {{ $layout->settings->site_name ?? config('storefront.name', 'GetNow') }}</title>
    <meta name="description" content="@yield('description', $layout->settings->default_meta_description ?? 'Discover headphones, phones, smartwatches and everyday essentials at GetNow. Shop in Sri Lankan rupees.')">
    @if(config('storefront.driver') === 'demo')
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
</head>
<body>
<a href="#main-content" class="skip-link">Skip to content</a>
@php($announcement = $layout->placements['announcement_bar'][0] ?? null)
@if($announcement)
    @if(!empty($announcement->data['link_url']))
        <a href="{{ $announcement->data['link_url'] }}" class="announcement announcement--{{ $announcement->data['tone'] ?? 'info' }} announcement--link">
            <div class="container">
                <span>{{ $announcement->data['text'] }}</span>
                @if(!empty($announcement->data['secondary_text']))
                    <span>{{ $announcement->data['secondary_text'] }}</span>
                @endif
                @if(!empty($announcement->data['locale_text']))
                    <span>{{ $announcement->data['locale_text'] }}</span>
                @endif
            </div>
        </a>
    @else
        <div class="announcement announcement--{{ $announcement->data['tone'] ?? 'info' }}">
            <div class="container">
                <span>{{ $announcement->data['text'] }}</span>
                @if(!empty($announcement->data['secondary_text']))
                    <span>{{ $announcement->data['secondary_text'] }}</span>
                @endif
                @if(!empty($announcement->data['locale_text']))
                    <span>{{ $announcement->data['locale_text'] }}</span>
                @endif
            </div>
        </div>
    @endif
@endif
<header class="site-header">
    <div class="container header-main">
        <button type="button" class="icon-button mobile-menu-button" aria-label="Open menu" data-open-dialog="mobile-menu"><x-icon name="menu"/></button>
        <x-logo/>
        <form action="{{ route('shop') }}" method="get" role="search" class="search-bar">
            <x-icon name="search" size="20"/>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $layout->settings->search_placeholder ?? 'What are you looking for?' }}" aria-label="Search products" maxlength="100">
            <button aria-label="Search" type="submit"><span>Search</span><x-icon name="search" size="20"/></button>
        </form>
        <nav class="header-actions" aria-label="Account and bag">
            <a class="account-link" href="{{ route(session('customer') ? 'account' : 'login') }}">
                <x-icon name="user"/>
                <span>
                    <small>{{ session('customer') ? 'Welcome back' : 'Hello, sign in' }}</small>
                    <strong>{{ session('customer.name', 'My account') }}</strong>
                </span>
            </a>
            <a class="icon-button saved-link" href="{{ route('wishlist') }}" aria-label="Saved items"><x-icon name="heart"/></a>
            <a class="icon-button bag-link" href="{{ route('cart') }}" aria-label="Shopping bag, {{ $cartCount ?? 0 }} items"><x-icon name="bag"/><span class="count">{{ $cartCount ?? 0 }}</span></a>
        </nav>
    </div>
    <div class="nav-border"><nav class="container desktop-nav" aria-label="Main navigation">
        @foreach($layout->menus['main_nav']->items ?? [] as $item)
            @if(!empty($item->children))
                <details class="category-menu">
                    <summary><x-icon name="menu" size="18"/> {{ $item->label }} <x-icon name="down" size="14"/></summary>
                    <div class="category-dropdown">
                        @foreach($item->children as $child)
                            @php($catIcon = collect($categories)->first(fn ($c) => ($c['name'] === $child->label || str_contains($child->url, $c['slug'])))['icon'] ?? 'box')
                            <a href="{{ $child->url }}" @if($child->new_tab) target="_blank" rel="noopener noreferrer" @endif>
                                <x-icon :name="$catIcon" size="20"/>{{ $child->label }}
                            </a>
                        @endforeach
                    </div>
                </details>
            @elseif(str_contains($item->url, 'deals=1') || str_contains(strtolower($item->label), 'deal'))
                <a href="{{ $item->url }}" class="deal-link" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif>
                  <x-icon name="bolt" size="16"/> {{ $item->label }}
                </a>
            @elseif(str_contains($item->url, 'help') || str_contains(strtolower($item->label), 'hand'))
                <a href="{{ $item->url }}" class="nav-help" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif>
                    <x-icon name="headphones" size="18"/> {{ $item->label }}
                </a>
            @else
                <a href="{{ $item->url }}" @class(['active' => request()->routeIs('shop') && !request()->query() && $item->url === '/shop']) @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif>
                    {{ $item->label }}
                </a>
            @endif
        @endforeach
    </nav></div>
</header>
<dialog id="mobile-menu" class="drawer">
    <div class="drawer-head">
        <x-logo/>
        <button type="button" class="icon-button" data-close-dialog aria-label="Close menu"><x-icon name="close"/></button>
    </div>
    <nav aria-label="Mobile navigation">
        @foreach($layout->menus['mobile_nav']->items ?? [] as $item)
            @if(!empty($item->children))
                @foreach($item->children as $child)
                    @php($catIcon = collect($categories)->first(fn ($c) => ($c['name'] === $child->label || str_contains($child->url, $c['slug'])))['icon'] ?? 'box')
                    <a href="{{ $child->url }}" @if($child->new_tab) target="_blank" rel="noopener noreferrer" @endif>
                        <x-icon :name="$catIcon"/>{{ $child->label }}
                    </a>
                @endforeach
            @elseif(str_contains(strtolower($item->label), 'account'))
                <a href="{{ $item->url }}" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif><x-icon name="user"/>{{ $item->label }}</a>
            @elseif(str_contains(strtolower($item->label), 'saved'))
                <a href="{{ $item->url }}" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif><x-icon name="heart"/>{{ $item->label }}</a>
            @else
                <a href="{{ $item->url }}" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
            @endif
        @endforeach
    </nav>
</dialog>
@if(session('status'))
    <div class="notice container" role="status">
        <x-icon name="check" size="18"/>
        <span>{{ session('status') }}</span>
        @if(session('cart_added'))
            <a href="{{ route('cart') }}">View bag</a>
        @endif
        <button type="button" data-dismiss-notice class="icon-button" aria-label="Dismiss notification"><x-icon name="close" size="18"/></button>
    </div>
@endif
@if($errors->any())
    <div class="error-summary container" role="alert" tabindex="-1">
        <strong>Please check the following:</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<main id="main-content">
    @yield('content')
</main>
<footer class="site-footer">
    <div class="container footer-top">
        <div class="footer-brand">
            <x-logo/>
            <p>{!! nl2br(e($layout->settings->footer_brand_text ?? "Thoughtfully chosen tech.\nFor the way you live.")) !!}</p>
            <span class="country">{{ config('storefront.country_tagline', 'Sri Lanka · Prices in LKR') }}</span>
        </div>
        <div>
            <h3>{{ $layout->menus['footer_column_1']->title ?? 'Find your next favourite' }}</h3>
            @foreach($layout->menus['footer_column_1']->items ?? [] as $item)
                <a href="{{ $item->url }}" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
            @endforeach
        </div>
        <div>
            <h3>{{ $layout->menus['footer_column_2']->title ?? 'A little help' }}</h3>
            @foreach($layout->menus['footer_column_2']->items ?? [] as $item)
                <a href="{{ $item->url }}" @if($item->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
            @endforeach
        </div>
        <div class="footer-note">
            <span class="eyebrow">YOUR EVERYDAY, UPGRADED.</span>
            <h3>{!! nl2br(e($layout->menus['footer_column_3']->title ?? "Small upgrades.\nBig possibilities.")) !!}</h3>
            @php($col3Item = $layout->menus['footer_column_3']->items[0] ?? null)
            <a href="{{ $col3Item->url ?? '/shop?sort=newest' }}" class="button button-outline" @if($col3Item?->new_tab) target="_blank" rel="noopener noreferrer" @endif>
                {{ $col3Item->label ?? "Discover what's new" }}
            </a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© {{ date('Y') }} {{ $layout->settings->site_name ?? config('storefront.name', 'GetNow') }}. All rights reserved.</span>
        <span>Cash on delivery <span class="footer-divider">/</span> Made for your everyday</span>
    </div>
    @if(config('storefront.driver') === 'demo')
        <div class="demo-ribbon">Demo storefront · Sample prices and availability · No real payments or orders</div>
    @endif
</footer>
@stack('scripts')
</body>
</html>
