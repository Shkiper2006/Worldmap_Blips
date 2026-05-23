jQuery(function($){
  $('#wm-select-all').on('change', function(){ $('input[name="marker_ids[]"]').prop('checked', $(this).is(':checked')); });

  const picker = $('#wm-map-picker img');
  picker.on('click', function(e){
    const o = $(this).offset();
    const x = (e.pageX - o.left) / $(this).width();
    const y = (e.pageY - o.top) / $(this).height();
    $('#coord_x').val((x * 100).toFixed(2));
    $('#coord_y').val((y * 100).toFixed(2));
  });

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
