@if($block)
    @if(($variant ?? 'hero_side') === 'collection')
        @if($previewTarget ?? false)
            <div class="collection-banner" data-preview-target="true" id="preview-target-block">
        @else
            <div class="collection-banner">
        @endif
            <div class="collection-copy">
                @if(!empty($block->data['eyebrow']))
                    <span class="eyebrow">{{ $block->data['eyebrow'] }}</span>
                @endif
                <h2>
                    {{ $block->data['title_line_1'] }}
                    @if(!empty($block->data['title_line_2']))
                        <br>{{ $block->data['title_line_2'] }}
                    @endif
                </h2>
                @if(!empty($block->data['description']))
                    <p>{!! nl2br(e($block->data['description'])) !!}</p>
                @endif
                <a class="button button-white banner-stretched-link" href="{{ $block->data['cta_url'] }}">{{ $block->data['cta_label'] }}</a>
            </div>
            <div class="collection-art">
                <img src="{{ asset($block->data['image_url']) }}" alt="{{ $block->data['image_alt'] }}" width="900" height="900" loading="lazy">
            </div>
            @if(!empty($block->data['badge_text']))
                <span class="collection-number">{{ $block->data['badge_text'] }}</span>
            @endif
        </div>
    @else
        @if($previewTarget ?? false)
            <article class="hero hero-side" data-preview-target="true" id="preview-target-block">
        @else
            <article class="hero hero-side">
        @endif
            <div>
                @if(!empty($block->data['eyebrow']))
                    <span class="eyebrow">{{ $block->data['eyebrow'] }}</span>
                @endif
                <h2>
                    {{ $block->data['title_line_1'] }}
                    @if(!empty($block->data['title_line_2']))
                        <br>{{ $block->data['title_line_2'] }}
                    @endif
                </h2>
                @if(!empty($block->data['description']))
                    <p>{{ $block->data['description'] }}</p>
                @endif
                <a class="text-link banner-stretched-link" href="{{ $block->data['cta_url'] }}">{{ $block->data['cta_label'] }}</a>
            </div>
            <img src="{{ asset($block->data['image_url']) }}" alt="{{ $block->data['image_alt'] }}" width="900" height="852">
            @if(!empty($block->data['badge_text']))
                <span class="side-caption">{{ $block->data['badge_text'] }}</span>
            @endif
        </article>
    @endif
@endif
