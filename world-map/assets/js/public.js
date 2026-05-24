(function () {
    const MIN_SCALE = 1;
    const MAX_SCALE = 4;
    const SCALE_STEP = 0.25;

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

    function setScale(mapSurface, levelEl, scale) {
        mapSurface.style.transform = `scale(${scale.toFixed(2)})`;
        levelEl.textContent = `${scale.toFixed(2)}x`;
        mapSurface.dataset.scale = String(scale);
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
            <div class="wmb-map-surface">
                <img class="wmb-map-image" src="${window.worldMapBlipsApi?.worldMapImage || ''}" alt="World map">
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

        setScale(mapSurface, levelEl, MIN_SCALE);

        controls.addEventListener('click', (event) => {
            const target = event.target.closest('button');
            if (!target) return;
            const current = Number(mapSurface.dataset.scale || MIN_SCALE);
            const next = target.dataset.action === 'in'
                ? Math.min(MAX_SCALE, current + SCALE_STEP)
                : Math.max(MIN_SCALE, current - SCALE_STEP);
            setScale(mapSurface, levelEl, next);
        });

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

        points.forEach((point) => {
            const lat = Number(point.lat ?? point.coord_y ?? 0);
            const lng = Number(point.lng ?? point.coord_x ?? 0);
            const marker = document.createElement('button');
            marker.type = 'button';
            marker.className = 'wmb-map-marker';
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
