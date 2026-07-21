(function($){
'use strict';
let saveTimer=null;
function syncRange(root){
  $(root).find('[data-mlm-range]').each(function(){
    const range=$(this),number=range.siblings('[data-mlm-number]');
    range.off('.mlm').on('input.mlm change.mlm',function(){number.val(range.val()).trigger('input');});
    number.off('.mlm').on('input.mlm change.mlm',function(){range.val(number.val());scheduleDraft();});
  });
}
function formData(){
  const form=document.getElementById('mlm-settings-form');if(!form)return null;
  const data={action:'mlm_save_preview_draft',nonce:MLM_ADMIN.nonce};
  new FormData(form).forEach((v,k)=>{data[k]=v;});
  form.querySelectorAll('input[type=checkbox][name]').forEach(el=>{if(!el.checked)data[el.name]='';});
  return data;
}
function saveDraft(done){
  const data=formData();if(!data){if(done)done();return;}
  $.post(MLM_ADMIN.ajaxUrl,data).always(()=>{if(done)done();});
}
function scheduleDraft(){clearTimeout(saveTimer);saveTimer=setTimeout(()=>saveDraft(),180);}
$(function(){
  syncRange(document);
  $('#mlm-settings-form').on('input change',scheduleDraft);
  $(document).on('click','.mlm-media-select',function(e){
    e.preventDefault();
    const button=$(this),card=button.closest('.mlm-slider-card');
    const frame=wp.media({title:'スマホ専用画像を選択',button:{text:'この画像を使用'},multiple:false,library:{type:'image'}});
    window.UIC_ACTIVE_MEDIA_FRAME=frame;
    frame._mlmTargetCard=card.get(0);
    let cropApplied=false;
    function applyModel(model){
      if(!model)return;
      const item=model.toJSON?model.toJSON():model;if(!item||!item.id)return;
      card.find('.mlm-media-id').val(String(item.id)).trigger('input').trigger('change');
      const url=(item.sizes&&item.sizes.medium)?item.sizes.medium.url:item.url;
      card.find('.mlm-media-preview').html($('<img>',{src:url,alt:'', 'data-attachment-id':item.id}));
      card.find('.mlm-selected-id').text('画像ID: '+item.id);
      saveDraft();
    }
    window.UIC_CONTEXT={
      frame:frame,
      target:'mlm-slider',
      onCropped:function(model){cropApplied=true;applyModel(model);}
    };
    frame.on('uic:cropped',function(model){if(!cropApplied){cropApplied=true;applyModel(model);}});
    frame.on('select',function(){
      if(cropApplied)return;
      const sel=frame.state().get('selection'),first=sel&&sel.first();if(!first)return;
      applyModel(first);
    });
    frame.on('close',function(){setTimeout(function(){if(window.UIC_ACTIVE_MEDIA_FRAME===frame)window.UIC_ACTIVE_MEDIA_FRAME=null;if(window.UIC_CONTEXT&&window.UIC_CONTEXT.frame===frame)window.UIC_CONTEXT=null;},100);});
    frame.open();
  });
  $(document).on('click','.mlm-media-clear',function(e){
    e.preventDefault();const card=$(this).closest('.mlm-slider-card');card.find('.mlm-media-id').val('0').trigger('change');card.find('.mlm-media-preview').html('<span>未設定</span>');scheduleDraft();
  });
  $('[data-mlm-open-preview]').on('click',function(){
    const win=window.open('about:blank','mlmMobilePreview');
    if(win)win.document.write('<p style="font-family:sans-serif;padding:20px">プレビューを準備しています…</p>');
    saveDraft(()=>{const url=MLM_ADMIN.previewAdminUrl+(MLM_ADMIN.previewAdminUrl.indexOf('?')>-1?'&':'?')+'mlm_draft='+Date.now();if(win)win.location=url;else window.location=url;});
  });
  $('[data-mlm-rotate]').on('click',function(){const shell=$('.mlm-device-shell');shell.attr('data-orientation',shell.attr('data-orientation')==='portrait'?'landscape':'portrait');});
  $('[data-mlm-reload]').on('click',function(){const frame=document.getElementById('mlm-preview-frame');if(frame)frame.src=frame.src.replace(/([?&])_mlm_reload=\d+/,'$1')+(frame.src.includes('?')?'&':'?')+'_mlm_reload='+Date.now();});
});
})(jQuery);
