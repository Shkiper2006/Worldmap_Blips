(function () {
    const MIN_ZOOM = 1;
    const MAX_ZOOM = 6;
    const START_ZOOM = 1.8;

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
            .map((img) => `<div class="swiper-slide"><img loading="lazy" src="${escapeHtml(img.url)}" alt="${escapeHtml(img.alt)}"><div class="swiper-lazy-preloader"></div></div>`)
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
                <div class="wmb-popup-gallery">
                    <div class="swiper">
                        <div class="swiper-wrapper"></div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            </div>`;

        root.appendChild(overlay);
        return overlay;
    }

    async function initMap(wrapper) {
        const mapContainer = wrapper.querySelector('.world-map-blips__map');
        if (!mapContainer || typeof L === 'undefined') {
            return;
        }

        const data = parseData(wrapper);
        let points = Array.isArray(data.points) ? data.points : [];
        if (!points.length && window.worldMapBlipsApi?.url) {
            try {
                const response = await fetch(window.worldMapBlipsApi.url, {
                    headers: { 'X-WP-Nonce': window.worldMapBlipsApi.nonce || '' }
                });
                points = await response.json();
            } catch (e) {
                points = [];
            }
        }

        const map = L.map(mapContainer, {
            minZoom: MIN_ZOOM,
            maxZoom: MAX_ZOOM,
            zoomControl: false,
            scrollWheelZoom: 'center',
            dragging: true,
            worldCopyJump: true,
            maxBoundsViscosity: 0.9
        }).setView([20, 0], START_ZOOM);

        map.setMaxBounds([[-85, -180], [85, 180]]);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            noWrap: true
        }).addTo(map);

        const controls = document.createElement('div');
        controls.className = 'wmb-zoom-controls';
        controls.innerHTML = `
            <button type="button" data-action="in" aria-label="Zoom in">+</button>
            <span class="wmb-zoom-level"></span>
            <button type="button" data-action="out" aria-label="Zoom out">−</button>`;
        mapContainer.appendChild(controls);

        const levelEl = controls.querySelector('.wmb-zoom-level');
        const syncZoom = () => {
            levelEl.textContent = map.getZoom().toFixed(1);
        };

        controls.addEventListener('click', (event) => {
            const target = event.target.closest('button');
            if (!target) return;
            const action = target.dataset.action;
            if (action === 'in') map.zoomIn();
            if (action === 'out') map.zoomOut();
        });

        map.on('zoomend', syncZoom);
        syncZoom();

        const popup = createPopup(wrapper);
        const closePopup = () => {
            popup.classList.remove('is-open');
            document.body.classList.remove('wmb-lock-scroll');
        };

        popup.addEventListener('click', (e) => {
            if (e.target === popup || e.target.closest('.wmb-popup-close')) {
                closePopup();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePopup();
        });

        let swiperInstance = null;

        points.forEach((point) => {
            const lat = Number(point.lat ?? point.coord_y ?? 0);
            const lng = Number(point.lng ?? point.coord_x ?? 0);
            const marker = L.circleMarker([lat, lng], {
                radius: 8,
                className: 'wmb-map-marker',
                color: point.icon_color || '#00d4ff',
                fillColor: point.icon_color || '#00d4ff',
                fillOpacity: 0.95,
                weight: 2
            }).addTo(map);

            marker.on('mouseover', () => {
                marker.setRadius(11);
            });
            marker.on('mouseout', () => {
                marker.setRadius(8);
            });

            marker.on('click', () => {
                const titleEl = popup.querySelector('.wmb-popup-title');
                const descriptionEl = popup.querySelector('.wmb-popup-description');
                const wrapperEl = popup.querySelector('.swiper-wrapper');
                const images = Array.isArray(point.images) ? point.images : [];

                titleEl.textContent = point.title || 'Location';
                descriptionEl.innerHTML = point.description || '';
                wrapperEl.innerHTML = buildSlides(images);

                popup.classList.add('is-open');
                document.body.classList.add('wmb-lock-scroll');

                if (swiperInstance) swiperInstance.destroy(true, true);
                swiperInstance = new Swiper(popup.querySelector('.swiper'), {
                    pagination: { el: popup.querySelector('.swiper-pagination') },
                    navigation: {
                        prevEl: popup.querySelector('.swiper-button-prev'),
                        nextEl: popup.querySelector('.swiper-button-next')
                    },
                    lazy: true,
                    spaceBetween: 12,
                    slidesPerView: 1,
                    breakpoints: {
                        700: { slidesPerView: 2 }
                    }
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.world-map-blips').forEach(initMap);
    });
})();
