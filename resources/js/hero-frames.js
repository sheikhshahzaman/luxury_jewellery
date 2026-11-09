// The opening video, played by scroll. The clip is stored as a numbered
// sequence of WebP frames (public/media/hero/<set>/NNN.webp) and drawn on a
// canvas, which scrubs smoothly in every browser (seeking a <video> doesn't).
//
// Frames load coarse-to-fine — every 16th, then every 8th, … — so scrolling
// works within a second and only gets smoother as the rest arrive. Until a
// frame is in, its nearest loaded neighbour is drawn instead.

const STRIDES = [16, 8, 4, 2, 1];
const PARALLEL = 6;

/**
 * @param {HTMLElement} host  element the canvas fills
 * @param {Record<string, {base: string, count: number, v: string}>} sets  "wide" (landscape) and "tall" (portrait)
 * @returns {{ setProgress(p: number): void }}
 */
export function mountFrames(host, sets) {
    const canvas = document.createElement('canvas');
    canvas.className = 'anx-canvas';
    host.appendChild(canvas);
    const ctx = canvas.getContext('2d');

    const portrait = window.matchMedia('(max-aspect-ratio: 9/10)');
    let set = null;
    let frames = [];
    let target = 0;
    let shown = 0;
    let drawn = -1;
    let raf = 0;

    const url = (s, i) => `${s.base}/${String(i + 1).padStart(3, '0')}.webp?v=${s.v}`;

    // Load one set, coarse frames first, a few at a time.
    const load = (s) => {
        const order = [];
        const seen = new Set();
        STRIDES.forEach((step) => {
            for (let i = 0; i < s.count; i += step) if (!seen.has(i)) seen.add(i), order.push(i);
        });
        if (!seen.has(s.count - 1)) order.push(s.count - 1);

        let next = 0;
        const pump = () => {
            if (set !== s || next >= order.length) return;
            const i = order[next++];
            const img = new Image();
            img.decoding = 'async';
            img.src = url(s, i);
            img.decode()
                .then(() => {
                    if (set !== s) return;
                    frames[i] = img;
                    // Redraw if this frame is closer to where we are than what's on screen.
                    drawn = -1;
                    tick();
                })
                .catch(() => {})
                .finally(pump);
        };
        for (let k = 0; k < PARALLEL; k++) pump();
    };

    const pick = () => {
        const s = portrait.matches && sets.tall?.count ? sets.tall : sets.wide;
        if (!s?.count || s === set) return;
        set = s;
        frames = new Array(s.count);
        drawn = -1;
        load(s);
    };

    // Nearest loaded frame to i.
    const nearest = (i) => {
        for (let d = 0; d < frames.length; d++) {
            if (frames[i - d]) return frames[i - d];
            if (frames[i + d]) return frames[i + d];
        }
        return null;
    };

    const resize = () => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.round(host.clientWidth * dpr);
        canvas.height = Math.round(host.clientHeight * dpr);
        drawn = -1;
        tick();
    };

    // Cover-fit, like object-fit: cover. On landscape screens the film sits
    // INSET from the left and fades into the velvet there, so the pieces stay
    // clear of the chapter text (the poster does the same in experience.css).
    const INSET = 0.16;
    const FEATHER = 0.1;
    const draw = (img) => {
        const cw = canvas.width;
        const ch = canvas.height;
        const left = set === sets.wide && cw / ch > 1.1 ? cw * INSET : 0;
        const areaW = cw - left;
        const scale = Math.max(areaW / img.naturalWidth, ch / img.naturalHeight);
        const w = img.naturalWidth * scale;
        const h = img.naturalHeight * scale;
        ctx.clearRect(0, 0, cw, ch);
        ctx.drawImage(img, left + (areaW - w) / 2, (ch - h) / 2, w, h);
        if (left) {
            const fade = ctx.createLinearGradient(left, 0, left + cw * FEATHER, 0);
            fade.addColorStop(0, '#000');
            fade.addColorStop(1, 'transparent');
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillStyle = fade;
            ctx.fillRect(left, 0, cw * FEATHER, ch);
            ctx.globalCompositeOperation = 'source-over';
        }
    };

    const frame = () => {
        raf = 0;
        if (!set) return;
        // Ease towards the scroll position so fast scrolls still glide.
        shown += (target - shown) * 0.18;
        if (Math.abs(target - shown) < 0.0004) shown = target;

        const i = Math.round(shown * (set.count - 1));
        const img = nearest(i);
        if (img && (i !== drawn || drawn === -1)) {
            draw(img);
            drawn = i;
            host.classList.add('is-playing');
        }
        if (shown !== target) tick();
    };
    const tick = () => {
        if (!raf) raf = requestAnimationFrame(frame);
    };

    pick();
    resize();
    portrait.addEventListener('change', pick);
    new ResizeObserver(resize).observe(host);

    return {
        setProgress(p) {
            target = Math.min(1, Math.max(0, p));
            tick();
        },
    };
}
