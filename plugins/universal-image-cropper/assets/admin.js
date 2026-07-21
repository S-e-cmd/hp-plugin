(function($){
'use strict';

let activeFrame=null, activeAttachment=null, overlay=null, stage=null, image=null;
let zoomInput=null, zoomValue=null, outputW=null, outputH=null, ratioSelect=null, message=null;
let naturalW=0,naturalH=0,baseScale=1,scale=1,x=0,y=0;
let dragging=false,startX=0,startY=0,originX=0,originY=0;
let injectTimer=null;

const presets={
  '16:9':{ratio:16/9,w:1920,h:1080,suffix:'16x9'},
  '4:3':{ratio:4/3,w:1600,h:1200,suffix:'4x3'},
  '1:1':{ratio:1,w:1200,h:1200,suffix:'square'},
  '3:4':{ratio:3/4,w:1200,h:1600,suffix:'3x4'},
  '9:16':{ratio:9/16,w:1080,h:1920,suffix:'9x16'},
  'free':{ratio:null,w:1600,h:1000,suffix:'custom'}
};

function currentMediaFrame(){
  if(window.UIC_ACTIVE_MEDIA_FRAME && window.UIC_ACTIVE_MEDIA_FRAME.state) return window.UIC_ACTIVE_MEDIA_FRAME;
  if(wp.media && wp.media.frame && wp.media.frame.state) return wp.media.frame;
  return activeFrame;
}
function selectedAttachment(){
  const frame=currentMediaFrame();
  if(!frame||!frame.state)return null;
  try{const selection=frame.state().get('selection');if(selection&&selection.first())return selection.first();}catch(e){}
  return null;
}
function addTriggerToView(view){
  if(!view||!view.$el||!view.model)return;
  const type=String(view.model.get('type')||'');
  view.$el.find('.uic-trigger-wrap').remove();
  if(type!=='image')return;

  const $wrap=$('<div class="uic-trigger-wrap"><button type="button" class="button button-primary uic-trigger">トリミングして使用</button></div>');
  const $settings=view.$el.find('.settings').first();
  const $actions=view.$el.find('.attachment-actions').first();
  if($settings.length){
    $settings.append($wrap);
  }else if($actions.length){
    $actions.before($wrap);
  }else{
    view.$el.append($wrap);
  }
  $wrap.on('click.uic','.uic-trigger',function(e){
    e.preventDefault();
    e.stopPropagation();
    activeFrame=currentMediaFrame();
    openEditor(view.model);
  });
}
function patchDetailsView(ViewClass){
  if(!ViewClass||!ViewClass.prototype||ViewClass.prototype._uicPatched)return;
  ViewClass.prototype._uicPatched=true;
  const originalRender=ViewClass.prototype.render;
  ViewClass.prototype.render=function(){
    const result=originalRender.apply(this,arguments);
    addTriggerToView(this);
    return result;
  };
}
function patchMediaViews(){
  if(!window.wp||!wp.media||!wp.media.view)return;
  patchDetailsView(wp.media.view.Attachment&&wp.media.view.Attachment.Details);
  patchDetailsView(wp.media.view.Attachment&&wp.media.view.Attachment.Details&&wp.media.view.Attachment.Details.TwoColumn);

  // 既に開いている詳細表示にも反映する。
  $('.media-modal:visible .attachment-details').each(function(){
    const $details=$(this);
    if($details.find('.uic-trigger-wrap').length)return;
    const model=selectedAttachment();
    if(!model||String(model.get('type')||'')!=='image')return;
    const fakeView={$el:$details,model:model};
    addTriggerToView(fakeView);
  });
}
function initMediaIntegration(){
  patchMediaViews();
  $(document).on('click.uicMedia keyup.uicMedia','.media-modal, .media-frame',function(){setTimeout(patchMediaViews,0);});
  const observer=new MutationObserver(function(mutations){
    let relevant=false;
    for(const m of mutations){
      if(m.addedNodes&&m.addedNodes.length){relevant=true;break;}
    }
    if(relevant)setTimeout(patchMediaViews,0);
  });
  observer.observe(document.body,{childList:true,subtree:true});
}
function setMessage(text,type){
  if(!message)return;message.textContent=text||'';
  message.classList.toggle('is-error',type==='error');message.classList.toggle('is-success',type==='success');
}
function clamp(){
  if(!stage||!naturalW)return;
  const sw=naturalW*scale,sh=naturalH*scale,vw=stage.clientWidth,vh=stage.clientHeight;
  x=Math.min(0,Math.max(vw-sw,x));y=Math.min(0,Math.max(vh-sh,y));
}
function render(){
  if(!image||!naturalW)return;clamp();
  image.style.width=(naturalW*scale)+'px';image.style.height=(naturalH*scale)+'px';
  image.style.transform='translate('+x+'px,'+y+'px)';zoomValue.textContent=zoomInput.value+'%';
}
function resetImage(){
  if(!naturalW||!stage)return;
  const vw=stage.clientWidth,vh=stage.clientHeight;
  baseScale=Math.max(vw/naturalW,vh/naturalH);scale=baseScale*(Number(zoomInput.value||100)/100);
  x=(vw-naturalW*scale)/2;y=(vh-naturalH*scale)/2;render();
}
function stageSizeForRatio(ratio){
  const shell=overlay.querySelector('.uic-stage-shell');
  const maxW=Math.max(260,shell.clientWidth-40),maxH=Math.max(260,shell.clientHeight-40);
  if(!ratio)ratio=Math.max(.25,Math.min(4,Number(outputW.value||1600)/Number(outputH.value||1000)));
  let w=maxW,h=w/ratio;if(h>maxH){h=maxH;w=h*ratio;}
  stage.style.width=Math.round(w)+'px';stage.style.height=Math.round(h)+'px';setTimeout(resetImage,0);
}
function applyPreset(key){const p=presets[key]||presets['16:9'];outputW.value=p.w;outputH.value=p.h;stageSizeForRatio(p.ratio);}
function cropData(){return{x:-x/scale,y:-y/scale,w:stage.clientWidth/scale,h:stage.clientHeight/scale};}
function buildOverlay(){
  overlay=document.createElement('div');overlay.className='uic-overlay';
  overlay.innerHTML=`<div class="uic-dialog" role="dialog" aria-modal="true" aria-label="画像をトリミング">
  <div class="uic-header"><h2>画像をトリミングして使用</h2><button type="button" class="uic-close" aria-label="閉じる">×</button></div>
  <div class="uic-body"><div class="uic-stage-wrap"><div class="uic-stage-shell"><div class="uic-stage"><img class="uic-image" alt=""><div class="uic-grid"></div></div></div><p class="uic-help">画像をドラッグして位置を調整。拡大率で切り抜く範囲を調整します。</p></div>
  <div class="uic-controls"><label>比率<select class="uic-ratio"><option value="16:9">横長 16:9</option><option value="4:3">横長 4:3</option><option value="1:1">正方形 1:1</option><option value="3:4">縦長 3:4</option><option value="9:16">スマホ縦長 9:16</option><option value="free">自由（出力サイズ比率）</option></select></label>
  <label>拡大率 <input type="range" class="uic-zoom" min="100" max="400" step="1" value="100"><span class="uic-zoom-value">100%</span></label>
  <label>出力サイズ<span class="uic-size-row"><input type="number" class="uic-output-w" min="1" max="5000" value="1920"><span>×</span><input type="number" class="uic-output-h" min="1" max="5000" value="1080"></span></label>
  <label>画質 <input type="range" class="uic-quality" min="60" max="100" step="1" value="88"><span class="uic-quality-value">88%</span></label>
  <div class="uic-actions"><button type="button" class="button uic-reset">中央に戻す</button><button type="button" class="button button-primary uic-save">新しい画像として保存して使用</button></div><div class="uic-message" aria-live="polite"></div><div class="uic-preview-meta"></div></div></div></div>`;
  document.body.appendChild(overlay);
  stage=overlay.querySelector('.uic-stage');image=overlay.querySelector('.uic-image');zoomInput=overlay.querySelector('.uic-zoom');zoomValue=overlay.querySelector('.uic-zoom-value');outputW=overlay.querySelector('.uic-output-w');outputH=overlay.querySelector('.uic-output-h');ratioSelect=overlay.querySelector('.uic-ratio');message=overlay.querySelector('.uic-message');
  overlay.querySelector('.uic-close').addEventListener('click',closeEditor);overlay.addEventListener('click',e=>{if(e.target===overlay)closeEditor();});
  ratioSelect.addEventListener('change',()=>applyPreset(ratioSelect.value));
  zoomInput.addEventListener('input',()=>{if(!naturalW)return;const cx=(stage.clientWidth/2-x)/scale,cy=(stage.clientHeight/2-y)/scale;scale=baseScale*(Number(zoomInput.value)/100);x=stage.clientWidth/2-cx*scale;y=stage.clientHeight/2-cy*scale;render();});
  overlay.querySelector('.uic-quality').addEventListener('input',e=>overlay.querySelector('.uic-quality-value').textContent=e.target.value+'%');
  overlay.querySelector('.uic-reset').addEventListener('click',()=>{zoomInput.value=100;resetImage();});
  outputW.addEventListener('change',()=>{if(ratioSelect.value==='free')stageSizeForRatio(null);});outputH.addEventListener('change',()=>{if(ratioSelect.value==='free')stageSizeForRatio(null);});
  stage.addEventListener('pointerdown',e=>{if(!naturalW)return;dragging=true;startX=e.clientX;startY=e.clientY;originX=x;originY=y;stage.setPointerCapture(e.pointerId);});
  stage.addEventListener('pointermove',e=>{if(!dragging)return;x=originX+(e.clientX-startX);y=originY+(e.clientY-startY);render();});
  stage.addEventListener('pointerup',()=>dragging=false);stage.addEventListener('pointercancel',()=>dragging=false);
  overlay.querySelector('.uic-save').addEventListener('click',saveCrop);
}
function openEditor(model){
  activeFrame=currentMediaFrame();activeAttachment=model||selectedAttachment();
  if(!activeAttachment){alert('画像を選択してください。');return;}
  const data=activeAttachment.toJSON();if(data.type!=='image'){alert('画像ファイルを選択してください。');return;}
  buildOverlay();const full=(data.sizes&&data.sizes.full)||data;naturalW=Number(full.width||data.width||0);naturalH=Number(full.height||data.height||0);
  overlay.querySelector('.uic-preview-meta').textContent=(data.filename||data.title||'')+' / '+naturalW+' × '+naturalH+'px';
  image.onload=()=>{naturalW=naturalW||image.naturalWidth;naturalH=naturalH||image.naturalHeight;applyPreset('16:9');};image.src=full.url||data.url;
}
function closeEditor(){if(overlay){overlay.remove();overlay=null;}naturalW=naturalH=0;}
function finishUse(model){
  const frame=activeFrame||currentMediaFrame();
  if(frame&&frame.state){
    try{const sel=frame.state().get('selection');if(sel)sel.reset([model]);}catch(e){}
    try{frame.trigger('select');}catch(e){}
    try{frame.close();}catch(e){}
  }
  window.UIC_ACTIVE_MEDIA_FRAME=null;
}
function saveCrop(){
  const c=cropData(),button=overlay.querySelector('.uic-save');const dw=Number(outputW.value),dh=Number(outputH.value),quality=Number(overlay.querySelector('.uic-quality').value);
  if(!dw||!dh||dw>UIC_DATA.maxDimension||dh>UIC_DATA.maxDimension){setMessage('出力サイズは1～5000pxで指定してください。','error');return;}
  button.disabled=true;button.textContent='保存中…';setMessage('',null);
  $.post(UIC_DATA.ajaxUrl,{action:'uic_crop_image',nonce:UIC_DATA.nonce,attachment_id:activeAttachment.id,crop_x:c.x,crop_y:c.y,crop_w:c.w,crop_h:c.h,dest_w:dw,dest_h:dh,quality:quality,suffix:presets[ratioSelect.value].suffix})
  .done(res=>{
    if(!res||!res.success){setMessage(res&&res.data&&res.data.message?res.data.message:'保存に失敗しました。','error');return;}
    const a=res.data.attachment,model=wp.media.attachment(a.id);model.set(a);
    try{const collection=activeFrame&&activeFrame.content&&activeFrame.content.get()?activeFrame.content.get().collection:null;if(collection)collection.add(model,{at:0});}catch(e){}
    setMessage('保存しました。選択欄へ反映します。','success');
    setTimeout(()=>{closeEditor();finishUse(model);},250);
  }).fail(xhr=>{setMessage(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'保存に失敗しました。','error');})
  .always(()=>{if(button&&document.body.contains(button)){button.disabled=false;button.textContent='新しい画像として保存して使用';}});
}
document.addEventListener('DOMContentLoaded',initMediaIntegration);
})(jQuery);
