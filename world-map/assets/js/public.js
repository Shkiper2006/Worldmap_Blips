(function () {
    const MIN_SCALE = 1;
    const MAX_SCALE = 8;
    const SCALE_STEP = 0.35;
    const ZOOM_SMOOTHING = 0.2;
    const DRAG_FRICTION = 0.9;

    const LOD_LEVELS = [
        { key: 'continents', min: 1, max: 1.8, label: 'Continents' },
        { key: 'countries', min: 1.8, max: 3.2, label: 'Countries' },
        { key: 'cities', min: 3.2, max: 5.4, label: 'Cities' },
        { key: 'highways', min: 5.4, max: Infinity, label: 'Highways' }
    ];

    function parseData(el) {
        try {
            return JSON.parse(el.dataset.worldMap || '{}');
        } catch (e) {
            return {};
        }
    }

    function buildSlides(images) {
        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));

        return images
            .map((img) => `<img loading="lazy" src="${escapeHtml(img.url)}" alt="${escapeHtml(img.alt)}">`)
            .join('');
    }

    function createPopup(root) {
        const overlay = document.createElement('div');
        overlay.className = 'wmb-popup-overlay';
        overlay.innerHTML = `
            <div class="wmb-popup" role="dialog" aria-modal="true" aria-label="Marker details">
                <button class="wmb-popup-close" aria-label="Close popup">×</button>
                <h4 class="wmb-popup-title"></h4>
                <div class="wmb-popup-description"></div>
                <div class="wmb-popup-gallery"></div>
            </div>`;

        root.appendChild(overlay);
        return overlay;
    }

    function longitudeToPercent(lng) { return ((lng + 180) / 360) * 100; }
    function latitudeToPercent(lat) { return ((90 - lat) / 180) * 100; }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function getLOD(scale) {
        return LOD_LEVELS.find((level) => scale >= level.min && scale < level.max) || LOD_LEVELS[0];
    }

    function createRoads(points, pathCount) {
        if (points.length < 2) return '';
        return Array.from({ length: Math.min(pathCount, points.length - 1) }, (_, index) => {
            const start = points[index];
            const end = points[index + 1];
            const x1 = longitudeToPercent(Number(start.lng ?? start.coord_x ?? 0));
            const y1 = latitudeToPercent(Number(start.lat ?? start.coord_y ?? 0));
            const x2 = longitudeToPercent(Number(end.lng ?? end.coord_x ?? 0));
            const y2 = latitudeToPercent(Number(end.lat ?? end.coord_y ?? 0));
            return `<line x1="${x1}%" y1="${y1}%" x2="${x2}%" y2="${y2}%"></line>`;
        }).join('');
    }

    function setTransform(state, mapSurface, levelEl) {
        mapSurface.style.transform = `translate(${state.translateX.toFixed(1)}px, ${state.translateY.toFixed(1)}px) scale(${state.scale.toFixed(2)})`;
        const lod = getLOD(state.scale);
        mapSurface.dataset.scale = String(state.scale);
        mapSurface.dataset.lod = lod.key;
        levelEl.textContent = `${state.scale.toFixed(2)}x · ${lod.label}`;
    }

    async function initMap(wrapper) {
        const mapContainer = wrapper.querySelector('.world-map-blips__map');
        if (!mapContainer) return;

        const data = parseData(wrapper);
        let points = Array.isArray(data.points) ? data.points : [];
        if (!points.length && window.worldMapBlipsApi?.url) {
            try {
                const response = await fetch(window.worldMapBlipsApi.url, { headers: { 'X-WP-Nonce': window.worldMapBlipsApi.nonce || '' } });
                points = await response.json();
            } catch (e) {
                points = [];
            }
        }

        mapContainer.innerHTML = `
            <div class="wmb-map-surface" data-scale="1" data-lod="continents">
                <img class="wmb-map-image" src="${window.worldMapBlipsApi?.worldMapImage || ''}" alt="World map">
                <div class="wmb-map-layers">
                    <div class="wmb-lod-layer wmb-lod-continents"></div>
                    <div class="wmb-lod-layer wmb-lod-countries"></div>
                    <div class="wmb-lod-layer wmb-lod-cities"></div>
                    <svg class="wmb-lod-layer wmb-lod-highways" viewBox="0 0 100 100" preserveAspectRatio="none">${createRoads(points, 24)}</svg>
                </div>
                <div class="wmb-map-markers"></div>
            </div>`;

        const controls = document.createElement('div');
        controls.className = 'wmb-zoom-controls';
        controls.innerHTML = `
            <button type="button" data-action="in" aria-label="Zoom in">+</button>
            <span class="wmb-zoom-level"></span>
            <button type="button" data-action="out" aria-label="Zoom out">−</button>`;
        mapContainer.appendChild(controls);

        const mapSurface = mapContainer.querySelector('.wmb-map-surface');
        const markerLayer = mapContainer.querySelector('.wmb-map-markers');
        const levelEl = controls.querySelector('.wmb-zoom-level');

        const state = { scale: MIN_SCALE, translateX: 0, translateY: 0, targetScale: MIN_SCALE, velocityX: 0, velocityY: 0 };
        setTransform(state, mapSurface, levelEl);

        controls.addEventListener('click', (event) => {
            const target = event.target.closest('button');
            if (!target) return;
            state.targetScale = target.dataset.action === 'in'
                ? clamp(state.targetScale + SCALE_STEP, MIN_SCALE, MAX_SCALE)
                : clamp(state.targetScale - SCALE_STEP, MIN_SCALE, MAX_SCALE);
        });

        let isPointerDown = false;
        let lastX = 0;
        let lastY = 0;
        let rafId = null;
        let pinchDistance = null;

        const animate = () => {
            const ds = state.targetScale - state.scale;
            state.scale += ds * ZOOM_SMOOTHING;
            state.translateX += state.velocityX;
            state.translateY += state.velocityY;
            state.velocityX *= DRAG_FRICTION;
            state.velocityY *= DRAG_FRICTION;
            if (Math.abs(ds) < 0.002) state.scale = state.targetScale;
            if (Math.abs(state.velocityX) < 0.01) state.velocityX = 0;
            if (Math.abs(state.velocityY) < 0.01) state.velocityY = 0;
            setTransform(state, mapSurface, levelEl);
            if (Math.abs(ds) > 0.002 || state.velocityX !== 0 || state.velocityY !== 0) {
                rafId = requestAnimationFrame(animate);
            } else {
                rafId = null;
            }
        };

        const requestAnimate = () => {
            if (!rafId) rafId = requestAnimationFrame(animate);
        };

        mapContainer.addEventListener('pointerdown', (event) => {
            isPointerDown = true;
            lastX = event.clientX;
            lastY = event.clientY;
            mapContainer.setPointerCapture(event.pointerId);
        });

        mapContainer.addEventListener('pointermove', (event) => {
            if (!isPointerDown) return;
            const dx = event.clientX - lastX;
            const dy = event.clientY - lastY;
            lastX = event.clientX;
            lastY = event.clientY;
            state.translateX += dx;
            state.translateY += dy;
            state.velocityX = dx * 0.55;
            state.velocityY = dy * 0.55;
            setTransform(state, mapSurface, levelEl);
            requestAnimate();
        });

        mapContainer.addEventListener('pointerup', () => {
            isPointerDown = false;
            requestAnimate();
        });

        mapContainer.addEventListener('wheel', (event) => {
            event.preventDefault();
            if (!mapContainer.matches(':hover')) return;
            state.targetScale = clamp(state.targetScale - Math.sign(event.deltaY) * SCALE_STEP, MIN_SCALE, MAX_SCALE);
            requestAnimate();
        }, { passive: false });

        mapContainer.addEventListener('touchmove', (event) => {
            if (event.touches.length !== 2) {
                pinchDistance = null;
                return;
            }
            event.preventDefault();
            const [a, b] = event.touches;
            const currentDistance = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
            if (pinchDistance !== null) {
                const diff = currentDistance - pinchDistance;
                state.targetScale = clamp(state.targetScale + diff * 0.004, MIN_SCALE, MAX_SCALE);
                requestAnimate();
            }
            pinchDistance = currentDistance;
        }, { passive: false });

        const popup = createPopup(wrapper);
        const closePopup = () => {
            popup.classList.remove('is-open');
            document.body.classList.remove('wmb-lock-scroll');
        };

        popup.addEventListener('click', (e) => {
            if (e.target === popup || e.target.closest('.wmb-popup-close')) closePopup();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePopup();
        });

        points.forEach((point, index) => {
            const lat = Number(point.lat ?? point.coord_y ?? 0);
            const lng = Number(point.lng ?? point.coord_x ?? 0);
            const marker = document.createElement('button');
            marker.type = 'button';
            marker.className = 'wmb-map-marker';
            marker.dataset.kind = index % 3 === 0 ? 'country' : 'city';
            marker.style.left = `${longitudeToPercent(lng)}%`;
            marker.style.top = `${latitudeToPercent(lat)}%`;
            marker.style.backgroundColor = point.icon_color || '#00d4ff';
            marker.ariaLabel = point.title || 'Location';

            marker.addEventListener('click', () => {
                const titleEl = popup.querySelector('.wmb-popup-title');
                const descriptionEl = popup.querySelector('.wmb-popup-description');
                const galleryEl = popup.querySelector('.wmb-popup-gallery');
                const images = Array.isArray(point.images) ? point.images : [];

                titleEl.textContent = point.title || 'Location';
                descriptionEl.innerHTML = point.description || '';
                galleryEl.innerHTML = buildSlides(images);

                popup.classList.add('is-open');
                document.body.classList.add('wmb-lock-scroll');
            });

            markerLayer.appendChild(marker);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.world-map-blips').forEach(initMap);
    });
})();
