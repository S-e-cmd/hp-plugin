(function(){
'use strict';
document.addEventListener('DOMContentLoaded',function(){
  const root=document.querySelector('.gos3-admin'); if(!root)return;
  const form=document.getElementById('gos3-form');
  const token=root.dataset.previewToken;
  const stateSelect=document.getElementById('gos3-state-select');
  const stateMode=document.getElementById('gos3-state-mode');
  const manualState=document.getElementById('gos3-manual-state');
  const manualEvent=document.querySelector('[name="manual_event"]');
  const iframe=document.getElementById('gos3-preview-iframe');
  const frame=document.getElementById('gos3-preview-frame');
  const status=document.getElementById('gos3-preview-status');
  const designsInput=document.getElementById('gos3-designs-json');
  const previewStateInput=document.getElementById('gos3-preview-state');
  const previewEventInput=document.getElementById('gos3-preview-event');
  const previewDeviceInput=document.getElementById('gos3-preview-device-input');
  let designs={}; try{designs=JSON.parse(designsInput.value||'{}')}catch(e){}
  const layoutTemplatesInput=document.getElementById('gos3-layout-templates-json');
  let layoutTemplates={};
  try{
    layoutTemplates=JSON.parse((layoutTemplatesInput&&layoutTemplatesInput.value)||'{}');
    // PHPの空配列 [] は、文字列キーをJSON化すると消えるため必ず通常オブジェクトへ正規化する。
    if(Array.isArray(layoutTemplates)||!layoutTemplates||typeof layoutTemplates!=='object')layoutTemplates={};
  }catch(e){layoutTemplates={}}
  const layoutName=document.getElementById('gos3-layout-name');
  const layoutSelect=document.getElementById('gos3-layout-select');
  const layoutStatus=document.getElementById('gos3-layout-status');
  const defaultLayoutInput=document.getElementById('gos3-default-layout-template');
  let defaultLayoutId=(defaultLayoutInput&&defaultLayoutInput.value)||'';
  let eventKey=(manualEvent&&manualEvent.value)||'spring';
  let stateKey=(stateMode&&stateMode.value==='manual'&&manualState)?manualState.value:(root.dataset.currentState||'closed');
  let device='desktop',previewDevice='desktop',timer=null,requestNo=0,designReady=false,selectedElement='eyebrow',dragState=null;
  const snapToggle=document.getElementById('gos3-snap-center');
  const snapStorageKey='gos3-center-snap-enabled';
  const snapThreshold=10;
  try{if(snapToggle&&localStorage.getItem(snapStorageKey)==='0')snapToggle.checked=false}catch(e){}

  function showEvent(key){
    saveDesign();
    eventKey=key; previewEventInput.value=key;
    document.querySelectorAll('[data-event]').forEach(b=>b.classList.toggle('active',b.dataset.event===key));
    document.querySelectorAll('[data-event-panel]').forEach(p=>p.classList.toggle('active',p.dataset.eventPanel===key));
    loadDesign();
    queuePreview();
  }
  function showState(key){
    saveDesign(); stateKey=key; previewStateInput.value=key; stateSelect.value=key;
    document.querySelectorAll('[data-text-panel]').forEach(p=>p.classList.toggle('active',p.dataset.textPanel===key));
    loadDesign(); queuePreview();
  }
  function showDevice(key){
    saveDesign(); device=key;
    document.querySelectorAll('[data-device]').forEach(b=>b.classList.toggle('active',b.dataset.device===key));
    loadDesign();
  }
  function designControls(){return Array.from(document.querySelectorAll('[data-design-key]'))}
  const presets={
    desktop:{
      compact:{layout:'circle',width:360,height:360,radius:180,padding_x:22,padding_y:22,eyebrow_size:13,title_size:22,event_size:30,detail_size:14,price_size:14,button_size:12,eyebrow_line_height:120,title_line_height:110,event_line_height:105,detail_line_height:118,price_line_height:118,eyebrow_margin:4,detail_margin:5,price_margin:4,actions_margin:10,button_min_width:108,button_radius:999,button_background:'#ffffff',button_text_color:'#303030',button_border_color:'#555555',shadow_strength:18},
      standard:{layout:'circle',width:420,height:420,radius:210,padding_x:26,padding_y:26,eyebrow_size:15,title_size:25,event_size:34,detail_size:15,price_size:15,button_size:13,eyebrow_line_height:120,title_line_height:110,event_line_height:105,detail_line_height:118,price_line_height:118,eyebrow_margin:5,detail_margin:6,price_margin:5,actions_margin:12,button_min_width:120,button_radius:999,button_background:'#ffffff',button_text_color:'#303030',button_border_color:'#555555',shadow_strength:18},
      large:{layout:'circle',width:500,height:500,radius:250,padding_x:32,padding_y:32,eyebrow_size:17,title_size:29,event_size:40,detail_size:17,price_size:17,button_size:14,eyebrow_line_height:120,title_line_height:110,event_line_height:105,detail_line_height:118,price_line_height:118,eyebrow_margin:6,detail_margin:7,price_margin:6,actions_margin:14,button_min_width:132,button_radius:999,button_background:'#ffffff',button_text_color:'#303030',button_border_color:'#555555',shadow_strength:22}
    },
    mobile:{
      compact:{layout:'circle',width:280,height:280,radius:140,padding_x:18,padding_y:18,eyebrow_size:12,title_size:19,event_size:25,detail_size:12,price_size:12,button_size:11,eyebrow_line_height:120,title_line_height:110,event_line_height:105,detail_line_height:118,price_line_height:118,eyebrow_margin:3,detail_margin:4,price_margin:3,actions_margin:9,button_min_width:94,button_radius:999,button_background:'#ffffff',button_text_color:'#303030',button_border_color:'#555555',shadow_strength:14},
      standard:{layout:'circle',width:330,height:330,radius:165,padding_x:22,padding_y:22,eyebrow_size:13,title_size:22,event_size:30,detail_size:14,price_size:14,button_size:13,eyebrow_line_height:120,title_line_height:110,event_line_height:105,detail_line_height:118,price_line_height:118,eyebrow_margin:4,detail_margin:5,price_margin:4,actions_margin:11,button_min_width:108,button_radius:999,button_background:'#ffffff',button_text_color:'#303030',button_border_color:'#555555',shadow_strength:18},
      large:{layout:'circle',width:370,height:370,radius:185,padding_x:26,padding_y:26,eyebrow_size:14,title_size:25,event_size:34,detail_size:15,price_size:15,button_size:13,eyebrow_line_height:120,title_line_height:110,event_line_height:105,detail_line_height:118,price_line_height:118,eyebrow_margin:5,detail_margin:6,price_margin:5,actions_margin:13,button_min_width:116,button_radius:999,button_background:'#ffffff',button_text_color:'#303030',button_border_color:'#555555',shadow_strength:18}
    }
  };
  function applyPreset(name){
    const values=((presets[device]||{})[name]);if(!values)return;
    Object.keys(values).forEach(key=>{const el=document.querySelector('[data-design-key="'+key+'"]');if(el)el.value=values[key]});
    saveDesign();queuePreview();
  }
  function saveDesign(){
    if(!designReady||!eventKey||!stateKey||!device)return;
    designs[eventKey]=designs[eventKey]||{};
    designs[eventKey][stateKey]=designs[eventKey][stateKey]||{};
    designs[eventKey][stateKey][device]=designs[eventKey][stateKey][device]||{};
    designControls().forEach(el=>designs[eventKey][stateKey][device][el.dataset.designKey]=el.value);
    designsInput.value=JSON.stringify(designs);
  }
  function loadDesign(){
    const obj=((((designs[eventKey]||{})[stateKey]||{})[device])||{});
    designControls().forEach(el=>{if(Object.prototype.hasOwnProperty.call(obj,el.dataset.designKey))el.value=obj[el.dataset.designKey]});
  }
  function clone(value){return JSON.parse(JSON.stringify(value||{}))}
  function currentDesignPair(){
    saveDesign();
    designs[eventKey]=designs[eventKey]||{};
    designs[eventKey][stateKey]=designs[eventKey][stateKey]||{};
    return designs[eventKey][stateKey];
  }
  function syncTemplatesHidden(){
    if(layoutTemplatesInput)layoutTemplatesInput.value=JSON.stringify(layoutTemplates);
    if(defaultLayoutInput)defaultLayoutInput.value=defaultLayoutId||'';
  }
  function renderLayoutTemplates(selectedId){
    if(!layoutSelect)return;
    const current=(selectedId!==undefined&&selectedId!==null)?String(selectedId):String(layoutSelect.value||'');
    layoutSelect.innerHTML='';
    const empty=document.createElement('option');empty.value='';empty.textContent='選択してください';layoutSelect.appendChild(empty);
    Object.keys(layoutTemplates).sort((a,b)=>String(layoutTemplates[a].name||'').localeCompare(String(layoutTemplates[b].name||''),'ja')).forEach(id=>{
      const option=document.createElement('option');option.value=id;option.textContent=(id===defaultLayoutId?'★ ':'')+(layoutTemplates[id].name||id);layoutSelect.appendChild(option);
    });
    if(current&&layoutTemplates[current])layoutSelect.value=current;
    else layoutSelect.value='';
  }
  function layoutMessage(message,error){if(!layoutStatus)return;layoutStatus.textContent=message||'';layoutStatus.classList.toggle('is-error',!!error)}
  function persistLayoutTemplates(message,selectedId){
    syncTemplatesHidden();
    layoutMessage('保存中…',false);
    const data=new FormData();
    data.set('action','gos_v3_layout_templates_save');
    data.set('nonce',GOS_V3.ajaxNonce);
    data.set('templates_json',JSON.stringify(layoutTemplates));
    data.set('default_layout_template',defaultLayoutId||'');
    return fetch(GOS_V3.ajaxUrl,{method:'POST',body:data,credentials:'same-origin',cache:'no-store'})
      .then(async r=>{
        const text=await r.text();let json;
        try{json=JSON.parse(text)}catch(e){throw new Error('保存先から正しい応答が返りませんでした。')}
        if(!r.ok||!json.success)throw new Error((json.data&&json.data.message)||'保存できませんでした。');
        if(json.data&&Object.prototype.hasOwnProperty.call(json.data,'templates')){
          layoutTemplates=json.data.templates;
          if(Array.isArray(layoutTemplates)||!layoutTemplates||typeof layoutTemplates!=='object')layoutTemplates={};
        }
        if(json.data&&Object.prototype.hasOwnProperty.call(json.data,'default_layout_template'))defaultLayoutId=json.data.default_layout_template||'';
        syncTemplatesHidden();
        renderLayoutTemplates(selectedId!==undefined?selectedId:(layoutSelect?layoutSelect.value:''));
        layoutMessage(message||'レイアウトを保存しました。',false);
        return json;
      })
      .catch(err=>{layoutMessage('レイアウト保存失敗：'+err.message,true);throw err});
  }
  function makeLayoutId(){return 'layout_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,8)}
  function selectedTemplateId(){return layoutSelect?layoutSelect.value:''}
  function selectedLoadDevices(){
    const list=[];
    const pc=document.getElementById('gos3-layout-load-desktop');
    const sp=document.getElementById('gos3-layout-load-mobile');
    if(pc&&pc.checked)list.push('desktop');if(sp&&sp.checked)list.push('mobile');return list;
  }
  function currentPreviewState(){
    return stateKey;
  }
  function currentPreviewEvent(){
    return eventKey;
  }
  function previewUrl(deviceName){
    const u=new URL(GOS_V3.homeUrl,window.location.href);
    u.searchParams.set('garden_status_preview','1');
    u.searchParams.set('gos_preview_token',token);
    u.searchParams.set('gos_preview_device',deviceName);
    u.searchParams.set('gos_force_state',currentPreviewState());
    u.searchParams.set('gos_force_event',currentPreviewEvent());
    u.searchParams.set('_gos',String(Date.now()));
    return u.toString();
  }
  function savePreviewData(){
    saveDesign();
    const data=new FormData(form);
    // 通常保存用のフラグが残ると admin_init の保存処理が先に動き、
    // admin-ajax.php が JSON ではなく管理画面HTMLを返してしまう。
    data.delete('gos_v3_action');
    data.delete('preview_nonce');
    data.set('action','gos_v3_preview_save');
    data.set('nonce',GOS_V3.ajaxNonce);
    data.set('preview_token',token);
    data.set('preview_state',currentPreviewState());
    data.set('preview_event',currentPreviewEvent());
    data.set('preview_device',previewDevice);
    return fetch(GOS_V3.ajaxUrl,{method:'POST',body:data,credentials:'same-origin',cache:'no-store'})
      .then(async r=>{
        const text=await r.text();
        let json; try{json=JSON.parse(text)}catch(e){throw new Error('プレビュー保存先からHTMLが返されました。')}
        if(!r.ok||!json.success)throw new Error((json.data&&json.data.message)||'プレビュー情報を保存できませんでした。');
        return json;
      });
  }
  function submitPreview(){
    const n=++requestNo; status.textContent='プレビュー更新中…';
    savePreviewData().then(()=>{
      if(n!==requestNo)return;
      iframe.src=previewUrl(previewDevice);
    }).catch(err=>{if(n===requestNo)status.textContent='プレビュー更新失敗：'+err.message});
  }
  function queuePreview(){clearTimeout(timer);timer=setTimeout(submitPreview,350)}
  function openPreview(kind){
    const mobile=kind==='mobile';
    const win=window.open('about:blank',mobile?'gos_mobile_preview':'gos_pc_preview',mobile?'width=430,height=900,resizable=yes,scrollbars=yes':'');
    if(!win){status.textContent='ポップアップがブロックされました。';return}
    win.document.write('<!doctype html><meta charset="utf-8"><title>プレビュー準備中</title><p style="font-family:sans-serif;padding:20px">プレビュー準備中…</p>');
    savePreviewData().then(()=>{win.location.replace(previewUrl(mobile?'mobile':'desktop'))}).catch(err=>{
      win.document.body.innerHTML='<p style="font-family:sans-serif;padding:20px">プレビュー更新失敗：'+String(err.message).replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]))+'</p>';
    });
  }
  function syncPublicSelection(){
    queuePreview();
  }

  const elementSelectors={
    eyebrow:'.gos3-eyebrow',
    title_before:'.gos3-title-before',
    event:'.gos3-event',
    title_after:'.gos3-title-after',
    detail:'.gos3-detail',
    price:'.gos3-price',
    actions:'.gos3-actions'
  };
  function designInput(key){return document.querySelector('[data-design-key="'+key+'"]')}
  function numberValue(key){const el=designInput(key);return el?Number(el.value||0):0}
  function setDesignValue(key,value){const el=designInput(key);if(!el)return;el.value=String(Math.round(value));saveDesign()}
  function selectElement(key){
    selectedElement=key;
    document.querySelectorAll('[data-gos3-edit-element]').forEach(b=>b.classList.toggle('active',b.dataset.gos3EditElement===key));
    highlightSelected();
  }
  function highlightSelected(){
    let doc;try{doc=iframe.contentDocument}catch(e){return}
    if(!doc)return;
    doc.querySelectorAll('.gos3-admin-selected').forEach(el=>el.classList.remove('gos3-admin-selected'));
    const el=doc.querySelector(elementSelectors[selectedElement]||'');
    if(el)el.classList.add('gos3-admin-selected');
  }
  function injectEditorStyles(doc){
    if(doc.getElementById('gos3-admin-editor-style'))return;
    const st=doc.createElement('style');st.id='gos3-admin-editor-style';
    st.textContent='#gos3-overlay .gos3-admin-editable{cursor:move!important;touch-action:none!important;outline:1px dashed transparent!important;outline-offset:4px!important}#gos3-overlay .gos3-admin-editable:hover{outline-color:#72aee6!important}#gos3-overlay .gos3-admin-selected{outline:2px solid #2271b1!important;background:rgba(34,113,177,.08)!important}#gos3-overlay .gos3-admin-guide-v,#gos3-overlay .gos3-admin-guide-h{position:absolute!important;display:block!important;pointer-events:none!important;z-index:9999!important;background:#2271b1!important;opacity:.65!important;margin:0!important;padding:0!important}#gos3-overlay .gos3-admin-guide-v{left:50%!important;top:0!important;width:1px!important;height:100%!important}#gos3-overlay .gos3-admin-guide-h{left:0!important;top:50%!important;width:100%!important;height:1px!important}#gos3-overlay .gos3-admin-guide-v.is-snapped,#gos3-overlay .gos3-admin-guide-h.is-snapped{opacity:1!important;width:2px!important;background:#d63638!important}#gos3-overlay .gos3-admin-guide-h.is-snapped{width:100%!important;height:2px!important}';
    doc.head.appendChild(st);
    const overlay=doc.getElementById('gos3-overlay');
    if(overlay){
      if(!overlay.querySelector('.gos3-admin-guide-v')){const v=doc.createElement('i');v.className='gos3-admin-guide-v';overlay.appendChild(v)}
      if(!overlay.querySelector('.gos3-admin-guide-h')){const h=doc.createElement('i');h.className='gos3-admin-guide-h';overlay.appendChild(h)}
    }
  }
  function bindDirectEditor(){
    let doc;try{doc=iframe.contentDocument}catch(e){return}
    if(!doc)return;
    injectEditorStyles(doc);
    Object.entries(elementSelectors).forEach(([key,selector])=>{
      const el=doc.querySelector(selector);if(!el)return;
      el.classList.add('gos3-admin-editable');el.dataset.gos3Element=key;
      el.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();selectElement(key)});
      el.addEventListener('pointerdown',e=>{
        if(e.button!==0)return;e.preventDefault();e.stopPropagation();selectElement(key);
        const overlay=el.closest('#gos3-overlay');
        dragState={
          id:e.pointerId,key,startX:e.clientX,startY:e.clientY,
          baseX:numberValue(key+'_x'),baseY:numberValue(key+'_y'),el,
          startRect:el.getBoundingClientRect(),
          overlayRect:overlay?overlay.getBoundingClientRect():null,
          guideV:overlay?overlay.querySelector('.gos3-admin-guide-v'):null,
          guideH:overlay?overlay.querySelector('.gos3-admin-guide-h'):null
        };
        try{el.setPointerCapture(e.pointerId)}catch(err){}
      });
      el.addEventListener('pointermove',e=>{
        if(!dragState||dragState.id!==e.pointerId||dragState.key!==key)return;
        const dx=e.clientX-dragState.startX,dy=e.clientY-dragState.startY;
        let x=dragState.baseX+dx,y=dragState.baseY+dy,snappedX=false,snappedY=false;
        const useSnap=!!(snapToggle&&snapToggle.checked&&!e.altKey&&dragState.overlayRect&&dragState.startRect);
        if(useSnap){
          const elementCenterX=dragState.startRect.left+(dragState.startRect.width/2)+dx;
          const elementCenterY=dragState.startRect.top+(dragState.startRect.height/2)+dy;
          const overlayCenterX=dragState.overlayRect.left+(dragState.overlayRect.width/2);
          const overlayCenterY=dragState.overlayRect.top+(dragState.overlayRect.height/2);
          const gapX=overlayCenterX-elementCenterX,gapY=overlayCenterY-elementCenterY;
          if(Math.abs(gapX)<=snapThreshold){x+=gapX;snappedX=true}
          if(Math.abs(gapY)<=snapThreshold){y+=gapY;snappedY=true}
        }
        if(dragState.guideV)dragState.guideV.classList.toggle('is-snapped',snappedX);
        if(dragState.guideH)dragState.guideH.classList.toggle('is-snapped',snappedY);
        setDesignValue(key+'_x',x);setDesignValue(key+'_y',y);
        el.style.setProperty('transform','translate('+Math.round(x)+'px,'+Math.round(y)+'px)','important');
      });
      const finish=e=>{
        if(!dragState||dragState.id!==e.pointerId)return;
        if(dragState.guideV)dragState.guideV.classList.remove('is-snapped');
        if(dragState.guideH)dragState.guideH.classList.remove('is-snapped');
        dragState=null;queuePreview()
      };
      el.addEventListener('pointerup',finish);el.addEventListener('pointercancel',finish);
    });
    highlightSelected();
  }

  renderLayoutTemplates(root.dataset.selectedLayout||'');
  const saveNewButton=document.getElementById('gos3-layout-save-new');
  if(saveNewButton)saveNewButton.addEventListener('click',(event)=>{event.preventDefault();event.stopPropagation();
    const name=(layoutName&&layoutName.value||'').trim();
    if(!name){layoutMessage('レイアウト名を入力してください。',true);if(layoutName)layoutName.focus();return}
    const pair=currentDesignPair();
    const id=makeLayoutId();
    layoutTemplates[id]={name:name.slice(0,80),desktop:clone(pair.desktop),mobile:clone(pair.mobile)};
    syncTemplatesHidden();
    saveNewButton.disabled=true;
    persistLayoutTemplates('「'+layoutTemplates[id].name+'」を新規保存しました。',id)
      .then(()=>{if(layoutName)layoutName.value=''})
      .catch(()=>{delete layoutTemplates[id];syncTemplatesHidden();renderLayoutTemplates('')})
      .finally(()=>{saveNewButton.disabled=false});
  });
  const overwriteButton=document.getElementById('gos3-layout-overwrite');
  if(overwriteButton)overwriteButton.addEventListener('click',()=>{
    const id=selectedTemplateId();if(!id||!layoutTemplates[id]){layoutMessage('上書きするレイアウトを選択してください。',true);return}
    const pair=currentDesignPair();layoutTemplates[id].desktop=clone(pair.desktop);layoutTemplates[id].mobile=clone(pair.mobile);
    persistLayoutTemplates('「'+layoutTemplates[id].name+'」を上書きしました。',id).catch(()=>{});
  });
  const loadButton=document.getElementById('gos3-layout-load');
  if(loadButton)loadButton.addEventListener('click',()=>{
    const id=selectedTemplateId(),tpl=layoutTemplates[id];if(!tpl){layoutMessage('読み込むレイアウトを選択してください。',true);return}
    const devices=selectedLoadDevices();if(!devices.length){layoutMessage('PCまたはスマホを選択してください。',true);return}
    saveDesign();designs[eventKey]=designs[eventKey]||{};designs[eventKey][stateKey]=designs[eventKey][stateKey]||{};
    devices.forEach(dev=>{designs[eventKey][stateKey][dev]=clone(tpl[dev])});designsInput.value=JSON.stringify(designs);loadDesign();queuePreview();
    layoutMessage('「'+tpl.name+'」を現在の季節・状態へ読み込みました。下の「設定を保存」で確定します。',false);
  });
  const renameButton=document.getElementById('gos3-layout-rename');
  if(renameButton)renameButton.addEventListener('click',()=>{
    const id=selectedTemplateId(),tpl=layoutTemplates[id];if(!tpl){layoutMessage('名前を変更するレイアウトを選択してください。',true);return}
    const name=window.prompt('新しいレイアウト名',tpl.name||'');if(name===null)return;const clean=name.trim();if(!clean){layoutMessage('名前を入力してください。',true);return}
    tpl.name=clean.slice(0,80);syncTemplatesHidden();renderLayoutTemplates(id);persistLayoutTemplates('レイアウト名を変更しました。',id).catch(()=>{});
  });
  const deleteButton=document.getElementById('gos3-layout-delete');
  if(deleteButton)deleteButton.addEventListener('click',()=>{
    const id=selectedTemplateId(),tpl=layoutTemplates[id];if(!tpl){layoutMessage('削除するレイアウトを選択してください。',true);return}
    if(!window.confirm('「'+tpl.name+'」を削除しますか？'))return;
    delete layoutTemplates[id];if(defaultLayoutId===id)defaultLayoutId='';
    syncTemplatesHidden();renderLayoutTemplates('');persistLayoutTemplates('レイアウトを削除しました。','').catch(()=>{});
  });
  const setDefaultButton=document.getElementById('gos3-layout-set-default');
  if(setDefaultButton)setDefaultButton.addEventListener('click',()=>{
    const id=selectedTemplateId(),tpl=layoutTemplates[id];
    if(!tpl){layoutMessage('初期レイアウトにするものを選択してください。',true);return}
    defaultLayoutId=id;syncTemplatesHidden();renderLayoutTemplates(id);
    persistLayoutTemplates('「'+tpl.name+'」を初期レイアウトに設定しました。',id).catch(()=>{});
  });
  const loadDefaultButton=document.getElementById('gos3-layout-load-default');
  if(loadDefaultButton)loadDefaultButton.addEventListener('click',()=>{
    const tpl=layoutTemplates[defaultLayoutId];
    if(!tpl){layoutMessage('初期レイアウトが設定されていません。',true);return}
    const devices=selectedLoadDevices();if(!devices.length){layoutMessage('PCまたはスマホを選択してください。',true);return}
    saveDesign();designs[eventKey]=designs[eventKey]||{};designs[eventKey][stateKey]=designs[eventKey][stateKey]||{};
    devices.forEach(dev=>{designs[eventKey][stateKey][dev]=clone(tpl[dev])});
    designsInput.value=JSON.stringify(designs);loadDesign();queuePreview();
    renderLayoutTemplates(defaultLayoutId);
    layoutMessage('初期レイアウト「'+tpl.name+'」を読み込みました。下の「設定を保存」で確定します。',false);
  });
  document.querySelectorAll('[data-copy-all]').forEach(button=>button.addEventListener('click',()=>{
    const kind=button.dataset.copyAll;const boxes=Array.from(document.querySelectorAll(kind==='event'?'[data-copy-event]':'[data-copy-state]'));const allChecked=boxes.length&&boxes.every(x=>x.checked);boxes.forEach(x=>x.checked=!allChecked);button.textContent=allChecked?'すべて選択':'選択解除';
  }));
  const copyButton=document.getElementById('gos3-copy-layout');
  if(copyButton)copyButton.addEventListener('click',()=>{
    saveDesign();
    const events=Array.from(document.querySelectorAll('[data-copy-event]:checked')).map(x=>x.dataset.copyEvent);
    const states=Array.from(document.querySelectorAll('[data-copy-state]:checked')).map(x=>x.dataset.copyState);
    const devices=Array.from(document.querySelectorAll('[data-copy-device]:checked')).map(x=>x.dataset.copyDevice);
    if(!events.length||!states.length||!devices.length){layoutMessage('コピー先の季節・状態・端末を選択してください。',true);return}
    const source=currentDesignPair();let count=0;
    events.forEach(ev=>{designs[ev]=designs[ev]||{};states.forEach(st=>{designs[ev][st]=designs[ev][st]||{};devices.forEach(dev=>{if(source[dev]){designs[ev][st][dev]=clone(source[dev]);count++}})})});
    designsInput.value=JSON.stringify(designs);loadDesign();queuePreview();layoutMessage(count+'件へコピーしました。下の「設定を保存」で確定します。',false);
  });

  iframe.addEventListener('load',()=>{status.textContent='編集中の内容を実画面へ反映しました。';bindDirectEditor();});
  document.querySelectorAll('[data-event]').forEach(b=>b.addEventListener('click',()=>showEvent(b.dataset.event)));
  stateSelect.addEventListener('change',()=>showState(stateSelect.value));
  document.querySelectorAll('[data-device]').forEach(b=>b.addEventListener('click',()=>showDevice(b.dataset.device)));
  document.querySelectorAll('[data-gos3-preset]').forEach(b=>b.addEventListener('click',()=>applyPreset(b.dataset.gos3Preset)));
  document.querySelectorAll('[data-preview-device]').forEach(b=>b.addEventListener('click',()=>{
    previewDevice=b.dataset.previewDevice; previewDeviceInput.value=previewDevice;
    document.querySelectorAll('[data-preview-device]').forEach(x=>x.classList.toggle('active',x===b));
    frame.classList.toggle('mobile',previewDevice==='mobile'); frame.classList.toggle('desktop',previewDevice==='desktop');
    submitPreview();
  }));
  document.getElementById('gos3-open-pc').addEventListener('click',()=>openPreview('pc'));
  document.getElementById('gos3-open-mobile').addEventListener('click',()=>openPreview('mobile'));
  document.getElementById('gos3-reload-preview').addEventListener('click',submitPreview);
  if(stateMode)stateMode.addEventListener('change',syncPublicSelection);
  if(manualState)manualState.addEventListener('change',syncPublicSelection);
  if(manualEvent)manualEvent.addEventListener('change',syncPublicSelection);
  document.querySelectorAll('[data-gos3-edit-element]').forEach(b=>b.addEventListener('click',()=>selectElement(b.dataset.gos3EditElement)));
  document.querySelectorAll('[data-gos3-align]').forEach(b=>b.addEventListener('click',()=>{const el=designInput(selectedElement+'_align');if(el){el.value=b.dataset.gos3Align;saveDesign();queuePreview()}}));
  document.getElementById('gos3-reset-element-position').addEventListener('click',()=>{setDesignValue(selectedElement+'_x',0);setDesignValue(selectedElement+'_y',0);queuePreview()});
  if(snapToggle)snapToggle.addEventListener('change',()=>{try{localStorage.setItem(snapStorageKey,snapToggle.checked?'1':'0')}catch(e){}});
  document.addEventListener('keydown',e=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key))return;
    if(['INPUT','TEXTAREA','SELECT'].includes(document.activeElement&&document.activeElement.tagName))return;
    const step=e.shiftKey?10:1;
    let x=numberValue(selectedElement+'_x'),y=numberValue(selectedElement+'_y');
    if(e.key==='ArrowLeft')x-=step;if(e.key==='ArrowRight')x+=step;if(e.key==='ArrowUp')y-=step;if(e.key==='ArrowDown')y+=step;
    setDesignValue(selectedElement+'_x',x);setDesignValue(selectedElement+'_y',y);queuePreview();e.preventDefault();
  });
  form.addEventListener('input',e=>{if(e.target.matches('[data-design-key]'))saveDesign();queuePreview()});
  form.addEventListener('change',e=>{if(e.target!==stateSelect&&e.target!==stateMode&&e.target!==manualState&&e.target!==manualEvent)queuePreview()});
  form.addEventListener('submit',()=>{saveDesign();syncTemplatesHidden()});

  showEvent(eventKey);
  showState(stateKey);
  showDevice('desktop');
  designReady=true;
  submitPreview();
});
})();
