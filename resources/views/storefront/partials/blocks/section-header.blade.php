@if($block)
    @if($previewTarget ?? false)
        <div class="section-heading" data-preview-target="true" id="preview-target-block">
    @else
        <div class="section-heading">
    @endif
        <div>
            @if(!empty($block->data['eyebrow']))
                <span class="eyebrow">{{ $block->data['eyebrow'] }}</span>
            @endif
            <h2>{{ $block->data['title'] }}</h2>
        </div>
        @if(!empty($block->data['cta_url']) && !empty($block->data['cta_label']))
            <a class="text-link" href="{{ $block->data['cta_url'] }}">{{ $block->data['cta_label'] }}</a>
        @endif
    </div>
@endif
