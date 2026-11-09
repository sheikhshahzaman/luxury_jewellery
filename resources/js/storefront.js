// Storefront motion, the opening film and the product 3D viewer. Plain page
// behaviour (cart, search, checkout…) lives in public/js/eluxee.js; this file
// only adds the experience layer, so the store works fully if it fails to load.
import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const hasWebGL = (() => {
    try {
        return !!document.createElement('canvas').getContext('webgl2');
    } catch {
        return false;
    }
})();

// ---------------------------------------------------------------------
// Smooth scrolling, driven by GSAP's ticker so ScrollTrigger stays in sync.
// ---------------------------------------------------------------------
if (!reducedMotion) {
    const lenis = new Lenis({ duration: 1.1, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
    window.eluxeeLenis = lenis;

    // In-page anchors scroll smoothly too.
    document.querySelectorAll('a[href^="#"]').forEach((a) => {
        a.addEventListener('click', (e) => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) {
                e.preventDefault();
                lenis.scrollTo(target, { offset: -90 });
            }
        });
    });
}

// ---------------------------------------------------------------------
// Opening story: the first section pins while a film of the collection
// plays with the scroll — from the signet ring out to every piece — and
// the chapter text beside it gives one reason to buy at a time.
// ---------------------------------------------------------------------
const story = document.querySelector('[data-story]');
if (story) {
    const chapters = [...story.querySelectorAll('[data-anx-chapter]')];
    const bar = story.querySelector('[data-anx-progress]');
    const stageEl = story.querySelector('[data-story-stage]');

    // The one orchestrated load moment: a light sweep across the headline.
    const title = story.querySelector('h1');
    if (title && !reducedMotion) title.classList.add('sweep');

    // Progress at which each chapter starts.
    const CHAPTER_STARTS = [0, 0.12, 0.31, 0.47, 0.63, 0.8];
    // The film holds on its first frame for a moment, then runs to the end just before the pin releases.
    const FILM = [0.03, 0.95];
    let film = null;
    let current = 0;

    const apply = (p) => {
        current = p;
        const active = CHAPTER_STARTS.reduce((found, start, i) => (p >= start ? i : found), 0);
        chapters.forEach((c, i) => c.classList.toggle('is-active', i === active));
        story.dataset.chapter = String(active);
        if (bar) bar.style.transform = `scaleX(${p})`;
        film?.setProgress((p - FILM[0]) / (FILM[1] - FILM[0]));
    };
    apply(0);

    // Reduced motion: no pinning or film — the hero, then the reasons as a list, over the first frame.
    if (reducedMotion) {
        story.classList.add('is-static');
    } else {
        const sets = stageEl?.dataset.frames ? JSON.parse(stageEl.dataset.frames) : null;
        if (sets) {
            import('./hero-frames.js').then(({ mountFrames }) => {
                film = mountFrames(stageEl, sets);
                apply(current);
            });
        }

        ScrollTrigger.create({
            trigger: story,
            start: 'top top',
            end: () => '+=' + window.innerHeight * 5.5,
            pin: true,
            scrub: true,
            anticipatePin: 1,
            onUpdate: (self) => apply(self.progress),
        });
    }
}

// ---------------------------------------------------------------------
// Product page: "View in 3D" swaps the photo for the live model.
// ---------------------------------------------------------------------
const viewerToggle = document.querySelector('[data-viewer-toggle]');
const viewerHost = document.querySelector('[data-product-viewer]');
if (viewerToggle && viewerHost && hasWebGL) {
    viewerToggle.hidden = false;
    let mounted = false;

    viewerToggle.addEventListener('click', () => {
        const on = viewerToggle.getAttribute('aria-pressed') !== 'true';
        viewerToggle.setAttribute('aria-pressed', String(on));
        viewerToggle.querySelector('[data-label]').textContent = on ? 'Show photos' : 'View in 3D';
        viewerHost.closest('.pd-gallery').classList.toggle('show-3d', on);

        if (on && !mounted) {
            mounted = true;
            // Start in the metal the shopper has already picked.
            const picked = document.querySelector('.pd-options input[name=metal]:checked');
            if (picked) viewerHost.dataset.tone = picked.dataset.tone;
            import('./three/product-viewer.js').then(({ mountProductViewer }) => mountProductViewer(viewerHost));
        }
    });
}

// ---------------------------------------------------------------------
// Scroll-linked parallax on editorial imagery (subtle, desktop only).
// ---------------------------------------------------------------------
if (!reducedMotion) {
    gsap.matchMedia().add('(min-width: 901px)', () => {
        gsap.utils.toArray('[data-parallax]').forEach((el) => {
            gsap.fromTo(el, { yPercent: -6 }, {
                yPercent: 6,
                ease: 'none',
                scrollTrigger: { trigger: el.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
            });
        });
    });
}
