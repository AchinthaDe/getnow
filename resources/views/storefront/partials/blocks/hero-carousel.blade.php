@if(count($slides ?? []))
    <x-promo-carousel :slides="$slides" :preview-target-id="$previewTargetId ?? null"/>
@endif
