@if($block && !empty($block->data['brands']))
    @if($previewTarget ?? false)
        <section class="brand-strip container" aria-label="Shop by brand" data-preview-target="true" id="preview-target-block">
    @else
        <section class="brand-strip container" aria-label="Shop by brand">
    @endif
        <span>
            @if(!empty($block->data['eyebrow']))
                {{ $block->data['eyebrow'] }}<br>
            @endif
            <strong>{{ $block->data['title'] }}</strong>
        </span>
        @foreach($block->data['brands'] as $brand)
            <a href="{{ $brand['url'] }}">{{ $brand['label'] }}</a>
        @endforeach
    </section>
@endif
