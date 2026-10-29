@if($block)
    @if(!empty($block->data['link_url']))
        @if($previewTarget ?? false)
            <a href="{{ $block->data['link_url'] }}" class="announcement announcement--{{ $block->data['tone'] ?? 'info' }} announcement--link" data-preview-target="true" id="preview-target-block">
        @else
            <a href="{{ $block->data['link_url'] }}" class="announcement announcement--{{ $block->data['tone'] ?? 'info' }} announcement--link">
        @endif
            <div class="container">
                <span>{{ $block->data['text'] }}</span>
                @if(!empty($block->data['secondary_text']))
                    <span>{{ $block->data['secondary_text'] }}</span>
                @endif
                @if(!empty($block->data['locale_text']))
                    <span>{{ $block->data['locale_text'] }}</span>
                @endif
            </div>
        </a>
    @else
        @if($previewTarget ?? false)
            <div class="announcement announcement--{{ $block->data['tone'] ?? 'info' }}" data-preview-target="true" id="preview-target-block">
        @else
            <div class="announcement announcement--{{ $block->data['tone'] ?? 'info' }}">
        @endif
            <div class="container">
                <span>{{ $block->data['text'] }}</span>
                @if(!empty($block->data['secondary_text']))
                    <span>{{ $block->data['secondary_text'] }}</span>
                @endif
                @if(!empty($block->data['locale_text']))
                    <span>{{ $block->data['locale_text'] }}</span>
                @endif
            </div>
        </div>
    @endif
@endif
