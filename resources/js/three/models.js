// Procedural jewellery: no model files to download, everything is built
// from geometry so it stays light and can be re-metalled instantly.
import * as THREE from 'three';

/** Metal tones, matching metal_tone() in app/Support/helpers.php. */
export const METALS = {
    gold: { color: 0xf2c46b, roughness: 0.16 },
    rose: { color: 0xeaa58c, roughness: 0.18 },
    white: { color: 0xe9ebef, roughness: 0.12 },
    platinum: { color: 0xd9dce1, roughness: 0.14 },
    silver: { color: 0xd4d7dc, roughness: 0.2 },
};

export function metalMaterial(tone = 'gold') {
    const m = METALS[tone] ?? METALS.gold;
    return new THREE.MeshPhysicalMaterial({
        color: m.color,
        metalness: 1,
        roughness: m.roughness,
        clearcoat: 0.6,
        clearcoatRoughness: 0.08,
        envMapIntensity: 1.35,
    });
}

/**
 * Brushed/satin finish of the same metal (signet and coin faces): it catches
 * soft light instead of mirroring the dark room. Linked to the polished
 * material so tweenMetal() changes both together.
 */
export function satinMaterial(polished, finish = 0.34) {
    const satin = polished.clone();
    satin.userData = { finish };
    satin.roughness = polished.roughness + satin.userData.finish;
    satin.clearcoat = 0.15;
    (polished.userData.linked ||= []).push(satin);
    return satin;
}

/** Smoothly move an existing metal material (and any linked finishes) to another tone. */
export function tweenMetal(material, tone, duration = 600) {
    const linked = material.userData.linked || [];
    if (linked.length) linked.forEach((m) => tweenMetal(m, tone, duration));

    const target = METALS[tone] ?? METALS.gold;
    const roughness = target.roughness + (material.userData.finish || 0);

    // No animation frames when the tab is hidden or motion is reduced: apply now.
    if (document.hidden || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        material.color.setHex(target.color);
        material.roughness = roughness;
        return Promise.resolve();
    }

    const from = material.color.clone();
    const to = new THREE.Color(target.color);
    const r0 = material.roughness;
    const start = performance.now();

    return new Promise((resolve) => {
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const e = 1 - Math.pow(1 - t, 3);
            material.color.copy(from).lerp(to, e);
            material.roughness = r0 + (roughness - r0) * e;
            if (t < 1) requestAnimationFrame(step);
            else resolve();
        };
        requestAnimationFrame(step);
    });
}

/**
 * A jeweller's light box: near-black room with a few bright softboxes.
 * High contrast is what makes metal glow and facets flash white/black.
 */
export function studioEnvironmentScene() {
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x060807);

    const panel = (w, h, intensity, color = 0xffffff) =>
        new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color(color).multiplyScalar(intensity), side: THREE.DoubleSide }));

    const boxes = [
        { m: panel(7, 2.6, 3.6, 0xfff4e0), p: [0, 6, 1], r: [Math.PI / 2, 0, 0] }, // overhead
        { m: panel(2.4, 6, 4.5), p: [-6, 1, 1], r: [0, Math.PI / 2, 0] }, // left strip
        { m: panel(2.4, 6, 4, 0xe6eeff), p: [6, 1, -1], r: [0, -Math.PI / 2, 0] }, // right strip (cool)
        { m: panel(5, 1.6, 3, 0xffe2b0), p: [0, 0.5, -6], r: [0, 0, 0] }, // back warm
        { m: panel(1.6, 4, 3.5), p: [-3.5, 0, 5.5], r: [0, Math.PI * 0.85, 0] }, // front-left strip
        { m: panel(1.6, 4, 3.5), p: [3.5, 0, 5.5], r: [0, -Math.PI * 0.85, 0] }, // front-right strip
        { m: panel(1.4, 1.4, 8), p: [2.5, 3, 5], r: [0, Math.PI, 0] }, // small kicker
        // Broad soft panel above and behind the camera, so flat gold faces
        // turned toward the viewer (signet, coin) read as polished gold, not black.
        { m: panel(6, 3, 2.6, 0xfff1d6), p: [0, 4.2, 7], r: [-Math.PI * 0.82, 0, 0] },
    ];
    boxes.forEach(({ m, p, r }) => {
        m.position.set(...p);
        m.rotation.set(...r);
        scene.add(m);
    });

    // A ring of narrow strips around the piece: neighbouring facets catch
    // alternately light and dark — the black/white "scintillation" of a diamond.
    for (let i = 0; i < 10; i++) {
        const a = (i / 10) * Math.PI * 2;
        const strip = panel(0.55, 7, i % 2 ? 2.2 : 5);
        strip.position.set(Math.cos(a) * 7.5, 0.5, Math.sin(a) * 7.5);
        strip.lookAt(0, 0.5, 0);
        scene.add(strip);
    }

    // Faint floor bounce so undersides aren't pure black.
    const floor = panel(20, 20, 0.12, 0xc3a572);
    floor.position.y = -5;
    floor.rotation.x = -Math.PI / 2;
    scene.add(floor);

    return scene;
}

/** Remember each part's resting transform so exploded views can move away and back. */
function rememberRest(objects) {
    objects.forEach((obj) => {
        if (obj) obj.userData.rest = { position: obj.position.clone(), rotation: obj.rotation.clone(), scale: obj.scale.clone() };
    });
}

/**
 * The ✦ four-point star used across the site, as a thin raised emblem
 * (extruded shape lying in the XY plane, facing +Z).
 */
function emblemGeometry(size = 1, depth = 0.04) {
    const shape = new THREE.Shape();
    const points = 8;
    for (let i = 0; i < points; i++) {
        const a = (i / points) * Math.PI * 2 + Math.PI / 2;
        const r = i % 2 === 0 ? size : size * 0.28;
        const x = Math.cos(a) * r;
        const y = Math.sin(a) * r;
        if (i === 0) shape.moveTo(x, y);
        else shape.lineTo(x, y);
    }
    shape.closePath();
    const geo = new THREE.ExtrudeGeometry(shape, {
        depth,
        bevelEnabled: true,
        bevelThickness: depth * 0.4,
        bevelSize: size * 0.04,
        bevelSegments: 2,
    });
    geo.center();
    return geo;
}

/** Polished signet ring: a rounded band with a raised oval face, rim and ✦ emblem. */
export function createRing({ tone = 'gold' } = {}) {
    const group = new THREE.Group();
    const metal = metalMaterial(tone);

    const band = new THREE.Mesh(new THREE.TorusGeometry(1, 0.1, 48, 220), metal);
    band.scale.set(1, 1, 1.4);
    group.add(band);

    const head = new THREE.Group();
    head.position.y = 1.02;

    // Raised oval face, slightly tapered like a real signet.
    const face = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.32, 0.2, 72), satinMaterial(metal));
    face.scale.set(1, 1, 0.8);
    face.position.y = 0.06;
    head.add(face);

    const rim = new THREE.Mesh(new THREE.TorusGeometry(0.4, 0.034, 16, 96), metal);
    rim.rotation.x = Math.PI / 2;
    rim.scale.set(1, 0.8, 1);
    rim.position.y = 0.17;
    head.add(rim);

    const emblem = new THREE.Mesh(emblemGeometry(0.2, 0.04), satinMaterial(metal, 0.12));
    emblem.rotation.x = -Math.PI / 2;
    emblem.position.y = 0.185;
    head.add(emblem);

    group.add(head);

    group.userData.metal = metal;
    group.userData.parts = { band, head, face, rim, emblem };
    rememberRest([band, head, face, rim, emblem]);
    return group;
}

/** Gold coin pendant with a polished rim and ✦ emblem, on a fine chain. */
export function createPendant({ tone = 'gold' } = {}) {
    const group = new THREE.Group();
    const metal = metalMaterial(tone);

    const points = [];
    for (let i = 0; i <= 40; i++) {
        const t = i / 40;
        const x = (t - 0.5) * 3.2;
        points.push(new THREE.Vector3(x, 1.5 - Math.cosh(x * 0.9) * 0.55 - 0.2, -Math.abs(x) * 0.25));
    }
    const chain = new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(points), 200, 0.018, 8, false), metal);
    group.add(chain);

    const bail = new THREE.Mesh(new THREE.TorusGeometry(0.08, 0.024, 12, 40), metal);
    bail.position.set(0, 0.7, 0);
    group.add(bail);

    const coin = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.4, 0.06, 96), satinMaterial(metal));
    coin.rotation.x = Math.PI / 2;
    coin.position.set(0, 0.22, 0);
    group.add(coin);

    const rim = new THREE.Mesh(new THREE.TorusGeometry(0.4, 0.04, 16, 96), metal);
    rim.position.set(0, 0.22, 0);
    group.add(rim);

    const emblem = new THREE.Mesh(emblemGeometry(0.2, 0.035), satinMaterial(metal, 0.12));
    emblem.position.set(0, 0.22, 0.05);
    group.add(emblem);

    group.userData.metal = metal;
    group.userData.parts = { chain, bail, coin, rim, emblem };
    rememberRest([chain, bail, coin, rim, emblem]);
    return group;
}

/**
 * A pair of jhumkas: a small domed stud, a gold bell, and a fringe of tiny
 * beads around the bell's rim with drops below.
 */
export function createStuds({ tone = 'gold' } = {}) {
    const group = new THREE.Group();
    const metal = metalMaterial(tone);
    metal.side = THREE.DoubleSide; // the bell is open underneath
    const jhumkas = [];

    // Bell profile: narrow at the top, flaring out to an open rim.
    const bellGeo = new THREE.LatheGeometry([
        new THREE.Vector2(0.06, 0.6),
        new THREE.Vector2(0.14, 0.56),
        new THREE.Vector2(0.24, 0.42),
        new THREE.Vector2(0.34, 0.22),
        new THREE.Vector2(0.42, 0.04),
        new THREE.Vector2(0.46, -0.02),
    ], 64);
    const beadGeo = new THREE.SphereGeometry(0.038, 12, 10);
    const rimBeads = 20;
    const drops = 10;

    [-0.62, 0.62].forEach((x, side) => {
        const jhumka = new THREE.Group();

        const stud = new THREE.Mesh(new THREE.SphereGeometry(0.15, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2), metal);
        stud.rotation.x = Math.PI / 2; // dome faces the viewer
        stud.position.y = 1.02;
        jhumka.add(stud);

        const link = new THREE.Mesh(new THREE.TorusGeometry(0.07, 0.02, 10, 32), metal);
        link.position.y = 0.76;
        jhumka.add(link);

        const bell = new THREE.Mesh(bellGeo, metal);
        jhumka.add(bell);

        // Fringe: a ring of beads on the rim, drops hanging below.
        const beads = new THREE.InstancedMesh(beadGeo, metal, rimBeads + drops);
        const m = new THREE.Matrix4();
        for (let i = 0; i < rimBeads; i++) {
            const a = (i / rimBeads) * Math.PI * 2;
            beads.setMatrixAt(i, m.makeTranslation(Math.cos(a) * 0.46, -0.06, Math.sin(a) * 0.46));
        }
        for (let i = 0; i < drops; i++) {
            const a = (i / drops) * Math.PI * 2 + 0.3;
            beads.setMatrixAt(rimBeads + i, m.makeTranslation(Math.cos(a) * 0.4, -0.2, Math.sin(a) * 0.4));
        }
        jhumka.add(beads);

        jhumka.position.x = x;
        jhumka.rotation.z = side ? -0.06 : 0.06;
        group.add(jhumka);
        jhumkas.push({ jhumka, stud, link, bell, beads });
        rememberRest([jhumka, stud, link, bell, beads]);
    });

    group.userData.metal = metal;
    group.userData.parts = { jhumkas };
    return group;
}

/** Three stacked bangles: polished, twisted rope, and beaded. */
export function createBangle({ tone = 'gold' } = {}) {
    const group = new THREE.Group();
    const metal = metalMaterial(tone);
    const R = 1.15;

    const plain = new THREE.Mesh(new THREE.TorusGeometry(R, 0.075, 32, 220), metal);
    plain.position.z = 0.2;

    // Twisted rope: two strands spiralling around the same circle.
    const rope = new THREE.Group();
    [0, Math.PI].forEach((phase) => {
        const pts = [];
        const turns = 48;
        for (let i = 0; i <= 720; i++) {
            const t = i / 720;
            const a = t * Math.PI * 2;
            const twist = t * turns * Math.PI * 2 + phase;
            const r = R + Math.cos(twist) * 0.045;
            pts.push(new THREE.Vector3(Math.cos(a) * r, Math.sin(a) * r, Math.sin(twist) * 0.045));
        }
        rope.add(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(pts, true), 720, 0.04, 8, true), metal));
    });

    const beadCount = 64;
    const beaded = new THREE.InstancedMesh(new THREE.SphereGeometry(0.075, 16, 12), metal, beadCount);
    const m = new THREE.Matrix4();
    for (let i = 0; i < beadCount; i++) {
        const a = (i / beadCount) * Math.PI * 2;
        beaded.setMatrixAt(i, m.makeTranslation(Math.cos(a) * R, Math.sin(a) * R, 0));
    }
    beaded.position.z = -0.2;

    group.add(plain, rope, beaded);
    group.userData.metal = metal;
    group.userData.parts = { plain, rope, beaded };
    rememberRest([plain, rope, beaded]);
    return group;
}

export function createModel(kind, tone) {
    switch (kind) {
        case 'pendant':
            return createPendant({ tone });
        case 'studs':
            return createStuds({ tone });
        case 'bangle':
            return createBangle({ tone });
        default:
            return createRing({ tone });
    }
}

/**
 * Four-point star glints that twinkle around a stone.
 * Additive points with a tiny shader; cheap enough for phones.
 */
export function createGlints(count = 70, spread = 2.6) {
    const geo = new THREE.BufferGeometry();
    const pos = new Float32Array(count * 3);
    const seed = new Float32Array(count);
    for (let i = 0; i < count; i++) {
        const r = spread * (0.35 + Math.random() * 0.65);
        const a = Math.random() * Math.PI * 2;
        const b = (Math.random() - 0.5) * Math.PI;
        pos[i * 3] = Math.cos(a) * Math.cos(b) * r;
        pos[i * 3 + 1] = Math.sin(b) * r * 0.8 + 0.4;
        pos[i * 3 + 2] = Math.sin(a) * Math.cos(b) * r;
        seed[i] = Math.random() * 100;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    geo.setAttribute('seed', new THREE.BufferAttribute(seed, 1));

    const material = new THREE.ShaderMaterial({
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        uniforms: {
            uTime: { value: 0 },
            uPixelRatio: { value: Math.min(window.devicePixelRatio, 2) },
            uColor: { value: new THREE.Color(0xfff1cf) },
        },
        vertexShader: /* glsl */ `
            attribute float seed;
            uniform float uTime;
            uniform float uPixelRatio;
            varying float vLife;
            void main() {
                vec4 mv = modelViewMatrix * vec4(position, 1.0);
                // Each glint flashes briefly, at its own rhythm.
                float t = fract(uTime * 0.18 + seed * 0.37);
                vLife = smoothstep(0.0, 0.08, t) * (1.0 - smoothstep(0.08, 0.22, t));
                gl_PointSize = (14.0 + 26.0 * fract(seed * 7.13)) * vLife * uPixelRatio * (6.0 / -mv.z);
                gl_Position = projectionMatrix * mv;
            }
        `,
        fragmentShader: /* glsl */ `
            uniform vec3 uColor;
            varying float vLife;
            void main() {
                vec2 p = gl_PointCoord - 0.5;
                // Thin cross + soft core = a four-point star.
                float cross = max(0.0, 1.0 - abs(p.x) * 26.0) * (1.0 - abs(p.y) * 2.0)
                            + max(0.0, 1.0 - abs(p.y) * 26.0) * (1.0 - abs(p.x) * 2.0);
                float core = exp(-dot(p, p) * 60.0);
                float a = clamp(cross + core, 0.0, 1.0) * vLife;
                if (a < 0.01) discard;
                gl_FragColor = vec4(uColor, a);
            }
        `,
    });

    const points = new THREE.Points(geo, material);
    points.userData.tick = (t) => {
        material.uniforms.uTime.value = t;
    };
    return points;
}

/** Radial falloff texture drawn on a canvas (no image file needed). */
export function radialTexture() {
    const c = document.createElement('canvas');
    c.width = c.height = 128;
    const g = c.getContext('2d');
    const grad = g.createRadialGradient(64, 64, 0, 64, 64, 64);
    grad.addColorStop(0, 'rgba(255,255,255,1)');
    grad.addColorStop(1, 'rgba(255,255,255,0)');
    g.fillStyle = grad;
    g.fillRect(0, 0, 128, 128);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
}
