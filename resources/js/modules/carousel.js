// Progressive enhancement: the first offer and its link work without JavaScript.
document.querySelectorAll('[data-carousel]').forEach(carousel => {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const controls = carousel.querySelector('[data-carousel-controls]');
    if (slides.length < 2 || !controls) return;

    const dots = [...carousel.querySelectorAll('[data-carousel-go]')];
    const toggle = carousel.querySelector('[data-carousel-toggle]');
    const status = carousel.querySelector('[data-carousel-status]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const hover = window.matchMedia('(hover: hover)');
    const configuredInterval = Number(carousel.dataset.interval);
    const interval = Number.isFinite(configuredInterval) ? Math.max(5000, configuredInterval) : 6000;
    let index = 0;
    let timer;
    let touchStart;
    let userPaused = carousel.dataset.autoplay !== 'true' || motion.matches;

    const updatePlayback = () => {
        toggle.setAttribute('aria-label', userPaused ? 'Play automatic offers' : 'Pause automatic offers');
        carousel.querySelector('[data-carousel-toggle-text]').textContent = userPaused ? 'Play' : 'Pause';
        carousel.querySelector('[data-carousel-pause-icon]').toggleAttribute('hidden', userPaused);
        carousel.querySelector('[data-carousel-play-icon]').toggleAttribute('hidden', !userPaused);
    };

    const schedule = () => {
        window.clearTimeout(timer);
        // Reading a link or using controls must never cause focus to disappear.
        if (userPaused || document.hidden || carousel.contains(document.activeElement)
            || (hover.matches && carousel.matches(':hover'))) return;
        timer = window.setTimeout(() => show(index + 1), interval);
    };

    const show = (next, manual = false) => {
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, position) => {
            slide.hidden = position !== index;
            slide.inert = position !== index;
            // Fetch the newly visible image immediately, even if initially lazy.
            if (position === index) slide.querySelector('img').loading = 'eager';
        });
        dots.forEach((dot, position) => {
            if (position === index) dot.setAttribute('aria-current', 'true');
            else dot.removeAttribute('aria-current');
        });
        carousel.querySelector('[data-carousel-current]').textContent = String(index + 1).padStart(2, '0');
        if (manual) {
            // Manual navigation stops rotation until the shopper selects Play.
            userPaused = true;
            status.textContent = `Offer ${index + 1} of ${slides.length}: ${slides[index].dataset.label}`;
            updatePlayback();
        }
        schedule();
    };

    carousel.querySelector('[data-carousel-previous]').addEventListener('click', () => show(index - 1, true));
    carousel.querySelector('[data-carousel-next]').addEventListener('click', () => show(index + 1, true));
    dots.forEach((dot, position) => dot.addEventListener('click', () => show(position, true)));
    toggle.addEventListener('click', () => {
        userPaused = !userPaused;
        updatePlayback();
        schedule();
    });

    // Native buttons support Tab, Enter and Space; arrows also navigate the offers.
    carousel.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey) return;
        event.preventDefault();
        // If an outgoing slide's link has focus, move it to the matching visible dot.
        const focusWasInSlide = slides[index].contains(document.activeElement);
        show(index + (event.key === 'ArrowRight' ? 1 : -1), true);
        if (focusWasInSlide) dots[index].focus();
    });
    carousel.addEventListener('mouseenter', schedule);
    carousel.addEventListener('mouseleave', schedule);
    carousel.addEventListener('focusin', schedule);
    carousel.addEventListener('focusout', () => window.setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    motion.addEventListener('change', () => {
        if (motion.matches) userPaused = true;
        updatePlayback();
        schedule();
    });

    // Horizontal swipes on the artwork; normal vertical page scrolling is preserved.
    const viewport = carousel.querySelector('[data-carousel-slides]');
    viewport.addEventListener('touchstart', event => {
        touchStart = event.touches.length === 1 && !event.target.closest('a, button')
            ? { x: event.touches[0].clientX, y: event.touches[0].clientY }
            : null;
    }, { passive: true });
    viewport.addEventListener('touchend', event => {
        if (!touchStart || !event.changedTouches.length) return;
        const dx = event.changedTouches[0].clientX - touchStart.x;
        const dy = event.changedTouches[0].clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(dx) >= 50 && Math.abs(dx) > Math.abs(dy) * 1.5) show(index + (dx < 0 ? 1 : -1), true);
    }, { passive: true });
    viewport.addEventListener('touchcancel', () => { touchStart = null; }, { passive: true });

    controls.hidden = false;
    updatePlayback();
    schedule();
});
