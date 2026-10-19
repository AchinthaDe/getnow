@if(count($items ?? []) > 0)
    <div class="service-strip">
        @foreach($items as $item)
            @if(!empty($item->data['link_url']))
                @if(($previewTargetId ?? null) !== null && $item->id === $previewTargetId)
                    <a href="{{ $item->data['link_url'] }}" data-preview-target="true" id="preview-target-block">
                @else
                    <a href="{{ $item->data['link_url'] }}">
                @endif
                        <x-icon :name="$item->data['icon']" size="27"/>
                    <span><strong>{{ $item->data['title'] }}</strong><small>{{ $item->data['subtitle'] }}</small></span>
                </a>
            @else
                @if(($previewTargetId ?? null) !== null && $item->id === $previewTargetId)
                    <div data-preview-target="true" id="preview-target-block">
                @else
                    <div>
                        @endif<x-icon :name="$item->data['icon']" size="27"/>
                    <span><strong>{{ $item->data['title'] }}</strong><small>{{ $item->data['subtitle'] }}</small></span>
                </div>
            @endif
        @endforeach
    </div>
@endif
