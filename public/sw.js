/*
 * Static-asset cache. Keeps scripts, styles, the 3D viewer and the opening
 * film's frames on the visitor's device after the first visit, so later page
 * views play the film instantly with no download.
 *
 * Only versioned files are cached, so nothing can go stale:
 *   - /build/assets/*        hashed by Vite (a new build = new file names)
 *   - /css/*, /js/*          with ?v=<modified time> (see asset_v())
 *   - /media/hero/*          film frames, with ?v=<newest frame time> (see hero_frames())
 * Pages, cart, checkout, admin and other images always come from the network.
 *
 * Registered as sw.js?build=<manifest hash>: each `npm run build` installs a
 * fresh cache and deletes the old one.
 */
const BUILD = new URL(self.location.href).searchParams.get('build') || 'dev';
const CACHE = 'lux-static-' + BUILD;
const SCOPE = new URL(self.registration.scope);

const scoped = (path) => new URL(path, SCOPE).pathname;
const BUILD_PATH = scoped('build/assets/');
const VERSIONED = [scoped('css/'), scoped('js/'), scoped('media/hero/')];

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE);
            try {
                // Precache every built file, the 3D chunks included.
                const res = await fetch(new URL('build/manifest.json', SCOPE), { cache: 'no-store' });
                const manifest = await res.json();
                const files = [...new Set(Object.values(manifest).map((c) => new URL('build/' + c.file, SCOPE).href))];
                await cache.addAll(files);
            } catch {
                // Offline or no build: assets are cached as they are fetched instead.
            }
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();
            await Promise.all(names.filter((n) => n.startsWith('lux-static-') && n !== CACHE).map((n) => caches.delete(n)));
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== SCOPE.origin) return;

    const isBuild = url.pathname.startsWith(BUILD_PATH);
    const isVersioned = url.searchParams.has('v') && VERSIONED.some((p) => url.pathname.startsWith(p));
    if (!isBuild && !isVersioned) return;

    event.respondWith(
        (async () => {
            const cache = await caches.open(CACHE);
            const hit = await cache.match(request);
            if (hit) return hit;

            const response = await fetch(request);
            if (response.ok) {
                await cache.put(request, response.clone());
                // A new ?v= of a file replaces the older copy.
                if (isVersioned) {
                    for (const key of await cache.keys()) {
                        const k = new URL(key.url);
                        if (k.pathname === url.pathname && k.search !== url.search) await cache.delete(key);
                    }
                }
            }
            return response;
        })(),
    );
});
