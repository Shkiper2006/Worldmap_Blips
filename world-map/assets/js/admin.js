jQuery(function($){
  $('#wm-select-all').on('change', function(){ $('input[name="marker_ids[]"]').prop('checked', $(this).is(':checked')); });

  const pickerRoot = $('#wm-map-picker');
  if (pickerRoot.length) {
    const viewport = $('#wm-map-viewport');
    const canvas = $('#wm-map-canvas');
    const image = $('#wm-map-image');
    const marker = $('#wm-map-marker');
    const coordX = $('#coord_x');
    const coordY = $('#coord_y');
    const zoomLabel = $('#wm-zoom-value');

    const state = {
      scale: 1,
      minScale: 0.5,
      maxScale: 5,
      tx: 0,
      ty: 0,
      canvasWidth: 0,
      canvasHeight: 0,
      markerX: null,
      markerY: null,
      panning: null,
      markerDragPointer: null,
      pinch: null
    };

    function clamp(v, min, max) { return Math.min(max, Math.max(min, v)); }

    function applyTransform() {
      canvas.css('transform', `translate(${state.tx}px, ${state.ty}px) scale(${state.scale})`);
      zoomLabel.text(`${Math.round(state.scale * 100)}%`);
    }

    function refreshCanvasSize() {
      state.canvasWidth = image[0].clientWidth;
      state.canvasHeight = image[0].clientHeight;
      canvas.css({ width: `${state.canvasWidth}px`, height: `${state.canvasHeight}px` });
    }

    function imagePointFromClient(clientX, clientY) {
      const rect = viewport[0].getBoundingClientRect();
      const x = (clientX - rect.left - state.tx) / state.scale;
      const y = (clientY - rect.top - state.ty) / state.scale;
      return { x: clamp(x, 0, state.canvasWidth), y: clamp(y, 0, state.canvasHeight) };
    }

    function setMarkerPercent(xPercent, yPercent) {
      state.markerX = clamp(xPercent, 0, 100);
      state.markerY = clamp(yPercent, 0, 100);
      marker.css({
        left: `${state.markerX}%`,
        top: `${state.markerY}%`,
        display: 'block'
      });
      coordX.val(state.markerX.toFixed(2));
      coordY.val(state.markerY.toFixed(2));
    }

    function setMarkerFromImagePoint(x, y) {
      if (!state.canvasWidth || !state.canvasHeight) return;
      const xPercent = (x / state.canvasWidth) * 100;
      const yPercent = (y / state.canvasHeight) * 100;
      setMarkerPercent(xPercent, yPercent);
    }

    function removeMarker() {
      state.markerX = null;
      state.markerY = null;
      marker.hide();
      coordX.val('');
      coordY.val('');
    }

    function zoomAt(newScale, clientX, clientY) {
      const nextScale = clamp(newScale, state.minScale, state.maxScale);
      const rect = viewport[0].getBoundingClientRect();
      const ox = clientX - rect.left;
      const oy = clientY - rect.top;
      const worldX = (ox - state.tx) / state.scale;
      const worldY = (oy - state.ty) / state.scale;
      state.scale = nextScale;
      state.tx = ox - worldX * state.scale;
      state.ty = oy - worldY * state.scale;
      applyTransform();
    }

    function resetView() {
      state.scale = 1;
      state.tx = 0;
      state.ty = 0;
      applyTransform();
    }

    function parseCoordInput(v) {
      const n = parseFloat(v);
      return Number.isFinite(n) ? clamp(n, 0, 100) : null;
    }

    viewport.on('pointerdown', function(e) {
      if ($(e.target).is('#wm-map-marker')) return;
      viewport.addClass('is-panning');
      state.panning = { id: e.pointerId, startX: e.clientX, startY: e.clientY, baseTx: state.tx, baseTy: state.ty, moved: false };
      this.setPointerCapture(e.pointerId);
    });

    viewport.on('pointermove', function(e) {
      if (!state.panning || state.panning.id !== e.pointerId) return;
      const dx = e.clientX - state.panning.startX;
      const dy = e.clientY - state.panning.startY;
      if (Math.abs(dx) > 3 || Math.abs(dy) > 3) state.panning.moved = true;
      state.tx = state.panning.baseTx + dx;
      state.ty = state.panning.baseTy + dy;
      applyTransform();
    });

    viewport.on('pointerup pointercancel', function(e) {
      if (!state.panning || state.panning.id !== e.pointerId) return;
      if (!state.panning.moved) {
        const p = imagePointFromClient(e.clientX, e.clientY);
        setMarkerFromImagePoint(p.x, p.y);
      }
      state.panning = null;
      viewport.removeClass('is-panning');
    });

    marker.on('pointerdown', function(e){
      e.stopPropagation();
      state.markerDragPointer = e.pointerId;
      marker.addClass('is-dragging');
      this.setPointerCapture(e.pointerId);
    });

    marker.on('pointermove', function(e){
      if (state.markerDragPointer !== e.pointerId) return;
      const p = imagePointFromClient(e.clientX, e.clientY);
      setMarkerFromImagePoint(p.x, p.y);
    });

    marker.on('pointerup pointercancel', function(e){
      if (state.markerDragPointer !== e.pointerId) return;
      state.markerDragPointer = null;
      marker.removeClass('is-dragging');
    });

    viewport.on('wheel', function(e){
      e.preventDefault();
      const delta = e.originalEvent.deltaY;
      const factor = delta > 0 ? 0.9 : 1.1;
      zoomAt(state.scale * factor, e.clientX, e.clientY);
    });

    viewport.on('touchstart', function(e){
      if (e.originalEvent.touches.length === 2) {
        const [a, b] = e.originalEvent.touches;
        const dist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
        state.pinch = { dist, scale: state.scale, centerX: (a.clientX + b.clientX) / 2, centerY: (a.clientY + b.clientY) / 2 };
      }
    });

    viewport.on('touchmove', function(e){
      if (!state.pinch || e.originalEvent.touches.length !== 2) return;
      e.preventDefault();
      const [a, b] = e.originalEvent.touches;
      const dist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
      zoomAt(state.pinch.scale * (dist / state.pinch.dist), state.pinch.centerX, state.pinch.centerY);
    });

    viewport.on('touchend touchcancel', function(){ state.pinch = null; });

    $('#wm-zoom-in').on('click', function(){
      const rect = viewport[0].getBoundingClientRect();
      zoomAt(state.scale * 1.2, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    $('#wm-zoom-out').on('click', function(){
      const rect = viewport[0].getBoundingClientRect();
      zoomAt(state.scale / 1.2, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    $('#wm-zoom-reset').on('click', resetView);
    $('#wm-marker-remove').on('click', removeMarker);

    coordX.add(coordY).on('input change', function(){
      const x = parseCoordInput(coordX.val());
      const y = parseCoordInput(coordY.val());
      if (x === null || y === null) return;
      setMarkerPercent(x, y);
    });

    image.on('load', function(){
      refreshCanvasSize();
      resetView();
      const x = parseCoordInput(coordX.val());
      const y = parseCoordInput(coordY.val());
      if (x !== null && y !== null) setMarkerPercent(x, y);
    });

    if (image[0].complete) image.trigger('load');
    $(window).on('resize', function(){ refreshCanvasSize(); applyTransform(); });
  }

  $('#wm-upload').on('click', function(e){
    e.preventDefault();
    const frame = wp.media({title: 'Select images', multiple: true});
    frame.on('select', function(){
      const ids = frame.state().get('selection').map(att => att.id);
      $('#wm-images').val(ids.join(','));
      $('#wm-images-preview').text(ids.length + ' selected');
    });
    frame.open();
  });
});
