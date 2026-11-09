// Shared WebGL stage: renderer + studio lighting + a render loop that
// sleeps when the canvas is off-screen or the tab is hidden.
import * as THREE from 'three';
import { studioEnvironmentScene } from './models.js';

export const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function webglAvailable() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGL2RenderingContext && c.getContext('webgl2'));
    } catch {
        return false;
    }
}

/**
 * @param {HTMLElement} host  element the canvas fills
 * @param {{ fov?: number, cameraZ?: number, exposure?: number }} opts
 */
export function createStage(host, { fov = 32, cameraZ = 6, exposure = 1.1 } = {}) {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = exposure;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.domElement.className = 'stage-canvas';
    host.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(studioEnvironmentScene(), 0.02).texture;
    pmrem.dispose();

    // Key + rim lights give the metal its highlights on top of the environment.
    const key = new THREE.DirectionalLight(0xfff3dd, 2.2);
    key.position.set(3, 4, 5);
    const rim = new THREE.DirectionalLight(0xd7e4ff, 1.4);
    rim.position.set(-4, 2, -3);
    scene.add(key, rim, new THREE.AmbientLight(0xffffff, 0.25));

    const camera = new THREE.PerspectiveCamera(fov, 1, 0.1, 100);
    camera.position.set(0, 0, cameraZ);

    const resize = () => {
        const w = host.clientWidth || 1;
        const h = host.clientHeight || 1;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        render();
    };

    const callbacks = new Set();
    const clock = new THREE.Clock();
    let visible = true;
    let running = false;

    function render() {
        renderer.render(scene, camera);
    }

    function frame() {
        if (!running) return;
        const t = clock.getElapsedTime();
        callbacks.forEach((cb) => cb(t));
        render();
        requestAnimationFrame(frame);
    }

    function start() {
        if (running || !visible || document.hidden) return;
        running = true;
        requestAnimationFrame(frame);
    }

    function stop() {
        running = false;
    }

    new ResizeObserver(resize).observe(host);
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        visible ? start() : stop();
    }, { rootMargin: '100px' }).observe(host);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));

    resize();

    return {
        renderer,
        scene,
        camera,
        render,
        start,
        stop,
        /**
         * Compile shaders off the main thread where supported, then start
         * rendering and reveal the canvas. Avoids a multi-second jank on load.
         */
        async go() {
            try {
                await renderer.compileAsync(scene, camera);
            } catch {
                // Older browsers: compile happens on first render instead.
            }
            resize();
            start();
            host.classList.add('is-live');
        },
        /** Run cb(elapsedSeconds) every frame while visible. */
        onFrame(cb) {
            callbacks.add(cb);
            return () => callbacks.delete(cb);
        },
    };
}

/**
 * Drag-to-turn with inertia (lighter than OrbitControls, and doesn't
 * fight page scrolling on touch: only horizontal drags rotate).
 */
export function dragToTurn(el, target, { autoSpin = 0.25 } = {}) {
    let dragging = false;
    let lastX = 0;
    let lastY = 0;
    let velocity = reducedMotion ? 0 : autoSpin;
    let tilt = 0;
    let idleSince = 0;

    el.style.touchAction = 'pan-y';
    el.addEventListener('pointerdown', (e) => {
        dragging = true;
        lastX = e.clientX;
        lastY = e.clientY;
        el.setPointerCapture(e.pointerId);
        el.classList.add('is-dragging');
    });
    el.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dx = e.clientX - lastX;
        const dy = e.clientY - lastY;
        lastX = e.clientX;
        lastY = e.clientY;
        velocity = dx * 0.012;
        tilt = Math.max(-0.5, Math.min(0.5, tilt + dy * 0.004));
        target.rotation.y += dx * 0.012;
    });
    const end = () => {
        dragging = false;
        idleSince = performance.now();
        el.classList.remove('is-dragging');
    };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);

    // Keyboard: arrows turn the piece.
    el.tabIndex = 0;
    el.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') velocity = -0.06;
        if (e.key === 'ArrowRight') velocity = 0.06;
    });

    let prev = 0;
    return (t) => {
        const dt = Math.min(0.05, t - prev || 0.016);
        prev = t;
        if (!dragging) {
            // Ease back toward a gentle idle spin after the user lets go.
            const idle = reducedMotion ? 0 : autoSpin;
            const since = (performance.now() - idleSince) / 1000;
            const goal = since > 1.5 ? idle : 0;
            velocity += (goal * dt - velocity) * 0.06;
            target.rotation.y += velocity;
            tilt *= 0.96;
        }
        target.rotation.x += (tilt - target.rotation.x) * 0.1;
    };
}
