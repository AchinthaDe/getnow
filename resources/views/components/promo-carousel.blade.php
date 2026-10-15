@props(['slides', 'autoplay' => true, 'interval' => 6000, 'id' => 'home-offers'])

@if(count($slides))
<section
    id="{{ $id }}"
    class="hero promo-carousel"
    aria-label="Featured offers"
    aria-roledescription="carousel"
    data-carousel
    data-autoplay="{{ $autoplay ? 'true' : 'false' }}"
    data-interval="{{ max(5000, (int) $interval) }}"
>
    <div class="promo-slides" data-carousel-slides>
        @foreach($slides as $slide)
            <article
                id="{{ $id }}-{{ $slide['id'] }}"
                class="promo-slide promo-slide--{{ $slide['theme'] }}"
                role="group"
                aria-roledescription="slide"
                aria-label="{{ $loop->iteration }} of {{ $loop->count }}: {{ $slide['label'] }}"
                data-carousel-slide
                data-label="{{ $slide['label'] }}"
                @if(!$loop->first) hidden inert @endif
            >
                <div class="promo-copy">
                    <span class="eyebrow"><span aria-hidden="true"></span>{{ $slide['eyebrow'] }}</span>
                    <h2>@foreach($slide['title'] as $line)<span>{{ $line }}</span>@endforeach</h2>
                    <p>{{ $slide['description'] }}</p>
                    <a href="{{ route($slide['route'], $slide['parameters'] ?? []) }}" class="button">{{ $slide['cta'] }}</a>
                </div>
                <div class="promo-art">
                    <img
                        src="{{ asset($slide['image']) }}"
                        alt="{{ $slide['alt'] }}"
                        width="900"
                        height="900"
                        @if($loop->first) fetchpriority="high" @else loading="lazy" @endif
                    >
                </div>
                <span class="promo-label">{{ $slide['label'] }}</span>
            </article>
        @endforeach
    </div>

    @if(count($slides) > 1)
        {{-- Hidden until JavaScript is ready. Without JS the first promotion remains usable. --}}
        <div class="promo-controls" data-carousel-controls hidden>
            <button type="button" class="promo-playback" data-carousel-toggle aria-label="Pause automatic offers">
                <x-icon name="pause" size="16" data-carousel-pause-icon/>
                <x-icon name="play" size="16" data-carousel-play-icon hidden/>
                <span data-carousel-toggle-text>Pause</span>
            </button>
            <div class="promo-pagination" role="group" aria-label="Choose an offer">
                @foreach($slides as $slide)
                    <button
                        type="button"
                        class="promo-dot"
                        data-carousel-go="{{ $loop->index }}"
                        aria-label="Show offer {{ $loop->iteration }}: {{ $slide['label'] }}"
                        aria-controls="{{ $id }}-{{ $slide['id'] }}"
                        @if($loop->first) aria-current="true" @endif
                    ><span></span></button>
                @endforeach
            </div>
            <div class="promo-arrows">
                <span class="promo-count" aria-hidden="true"><span data-carousel-current>01</span> / {{ str_pad((string) count($slides), 2, '0', STR_PAD_LEFT) }}</span>
                <button type="button" class="promo-arrow promo-arrow--previous" data-carousel-previous aria-label="Previous offer"><x-icon name="chevron" size="17"/></button>
                <button type="button" class="promo-arrow" data-carousel-next aria-label="Next offer"><x-icon name="chevron" size="17"/></button>
            </div>
        </div>
        <p class="sr-only" data-carousel-status role="status" aria-live="polite" aria-atomic="true"></p>
    @endif
</section>
@endif
