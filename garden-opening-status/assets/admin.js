let gosActualPreviewReloadTimer = null;
function scheduleActualPreviewReload(callback, delay){
  clearTimeout(gosActualPreviewReloadTimer);
  gosActualPreviewReloadTimer = setTimeout(function(){
    if(typeof callback === 'function') callback();
  }, typeof delay === 'number' ? delay : 900);
}

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
  let previewTarget='status',previewSeason='spring';
  const previewTargetBox=document.getElementById('gos3-preview-targets');
  const previewSeasonBox=document.getElementById('gos3-preview-season');
  const overviewPreview=document.getElementById('gos3-overview-preview');
  const overviewPreviewBody=document.getElementById('gos3-overview-preview-body');
  const overviewPageTitle=document.getElementById('gos3-overview-page-title');
  const previewActions=document.querySelector('[data-preview-tools="status"]');
  const eventOverviewActions=document.querySelector('[data-preview-tools="event_overview"]');
  const directEditor=document.getElementById('gos3-direct-editor');
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
    data.set('default_id',defaultLayoutId||'');
    return fetch(GOS_V3.ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(r=>r.json()).then(json=>{
      if(!json||!json.success)throw new Error((json&&json.data&&json.data.message)||'保存に失敗しました。');
      layoutTemplates=json.data.templates||{};defaultLayoutId=json.data.default_id||'';syncTemplatesHidden();renderLayoutTemplates(selectedId||'');layoutMessage(message||'保存しました。',false);return json;
    }).catch(err=>{layoutMessage(err.message||'保存に失敗しました。',true);throw err});
  }

  function eventInput(season,field){return form.querySelector('[name="events['+season+']['+field+']"]')}
  function eventValue(season,field){const el=eventInput(season,field);return el?String(el.value||''):''}
  function formatDateJaWithWeekday(value){
    if(!value)return '';
    const d=new Date(value+'T00:00:00');
    if(Number.isNaN(d.getTime()))return value;
    const week=['日','月','火','水','木','金','土'];
    return (d.getMonth()+1)+'月'+d.getDate()+'日（'+week[d.getDay()]+'）';
  }
  function overviewDateText(season){
    const mode=eventValue(season,'date_display_mode')||'usual';
    if(mode==='hidden'||mode==='none')return '';
    if(mode==='confirmed'){
      const start=eventValue(season,'start').trim(),end=eventValue(season,'end').trim();
      if(start&&end){
        if(start===end)return formatDateJaWithWeekday(start);
        return formatDateJaWithWeekday(start)+'～'+formatDateJaWithWeekday(end);
      }
      if(start||end)return formatDateJaWithWeekday(start||end);
      return '';
    }
    return eventValue(season,'usual_period').trim();
  }
  function renderOverviewPreview(){
    if(!overviewPreviewBody)return;
    const season=previewSeason;
    const label=eventValue(season,'label')||season;
    if(overviewPageTitle)overviewPageTitle.textContent=label;
    const rows=[];
    const date=overviewDateText(season);if(date)rows.push([eventValue(season,'date_label')||'開苑期間',date,false]);
    const open=eventValue(season,'open_time').replace(/^0/,'');const close=eventValue(season,'close_time').replace(/^0/,'');let time='';if(open||close)time=open+(open&&close?'～':'')+close;const closeLabel=eventValue(season,'close_time_label').trim();if(time&&closeLabel)time+='（'+closeLabel+'）';if(time)rows.push([eventValue(season,'time_label')||'開苑時間',time,false]);
    let price=eventValue(season,'price_details').trim()||eventValue(season,'price').trim();if(price)rows.push([eventValue(season,'admission_label')||'入苑料',price,true]);
    const notes=['time_note','price_note','overview_note'].map(f=>eventValue(season,f).trim()).filter(Boolean);
    if(!rows.length&&!notes.length){overviewPreviewBody.innerHTML='<div class="gos3-overview-empty">表示する内容がありません。</div>';return}
    let html='<section class="gos-event-info">';
    rows.forEach(row=>{html+='<div class="gos-event-info__row"><div class="gos-event-info__label">'+escapeHtml(row[0])+'：</div><div class="gos-event-info__value">';if(row[2])row[1].split(/\r?\n/).forEach(line=>html+='<p>'+escapeHtml(line)+'</p>');else html+=escapeHtml(row[1]);html+='</div></div>'});
    notes.forEach(note=>html+='<p class="gos-event-info__note">'+escapeHtml(note).replace(/\n/g,'<br>')+'</p>');
    overviewPreviewBody.innerHTML=html+'</section>';
  }
  function escapeHtml(value){return String(value||'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))}
  function setPreviewTarget(target){
    previewTarget=target;
    document.querySelectorAll('[data-preview-target]').forEach(b=>b.classList.toggle('active',b.dataset.previewTarget===target));
    if(previewSeasonBox)previewSeasonBox.hidden=target!=='event_overview';
    if(previewActions)previewActions.hidden=target!=='status';
    if(eventOverviewActions)eventOverviewActions.hidden=target!=='event_overview';
    if(directEditor)directEditor.hidden=target!=='status';
    if(frame)frame.hidden=target!=='status';
    if(overviewPreview)overviewPreview.hidden=target!=='event_overview';
    if(target==='event_overview')renderOverviewPreview();else queuePreview();
  }
  function setPreviewSeason(season){previewSeason=season;document.querySelectorAll('[data-preview-season]').forEach(b=>b.classList.toggle('active',b.dataset.previewSeason===season));renderOverviewPreview()}
  function queuePreview(){clearTimeout(timer);timer=setTimeout(submitPreview,250)}
  function submitPreview(){
    if(previewTarget!=='status')return;
    saveDesign();
    const data=new FormData();data.set('action','gos_v3_preview_save');data.set('nonce',GOS_V3.ajaxNonce);data.set('form',new URLSearchParams(new FormData(form)).toString());
    const current=++requestNo;status.textContent='プレビュー更新中…';
    fetch(GOS_V3.ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(r=>r.json()).then(json=>{
      if(current!==requestNo)return;if(!json||!json.success)throw new Error('プレビュー更新に失敗しました。');
      iframe.src=GOS_V3.previewUrl+'&gos_preview='+encodeURIComponent(json.data.token)+'&state='+encodeURIComponent(stateKey)+'&event='+encodeURIComponent(eventKey)+'&device='+encodeURIComponent(previewDevice);
    }).catch(err=>status.textContent=err.message||'プレビュー更新に失敗しました。');
  }
  function openPreview(kind){saveDesign();const target=GOS_V3.previewUrl+'&gos_preview='+encodeURIComponent(token)+'&state='+encodeURIComponent(stateKey)+'&event='+encodeURIComponent(eventKey)+'&device='+(kind==='mobile'?'mobile':'desktop');window.open(target,'_blank','noopener')}
  function openEventPagePreview(kind){
    const url=eventValue(previewSeason,'detail_url');if(!url){layoutMessage('詳細ページURLを設定してください。',true);return}
    const data=new FormData();data.set('action','gos_v3_preview_save');data.set('nonce',GOS_V3.ajaxNonce);data.set('form',new URLSearchParams(new FormData(form)).toString());
    fetch(GOS_V3.ajaxUrl,{method:'POST',credentials:'same-origin',body:data}).then(r=>r.json()).then(json=>{if(!json||!json.success)throw new Error('プレビュー準備に失敗しました。');let target=url+(url.indexOf('?')>=0?'&':'?')+'gos_preview='+encodeURIComponent(json.data.token)+'&gos_event_info_preview='+encodeURIComponent(previewSeason);if(kind==='mobile')target+='&gos_force_mobile=1';window.open(target,'_blank','noopener')}).catch(err=>layoutMessage(err.message||'プレビュー準備に失敗しました。',true));
  }
  function reloadEventOverview(){renderOverviewPreview()}
  function syncPublicSelection(){if(stateMode&&manualState&&stateSelect&&stateMode.value==='manual')showState(manualState.value);if(manualEvent)showEvent(manualEvent.value)}
  function designInput(key){return document.querySelector('[data-design-key="'+key+'"]')}
  function numberValue(key){const el=designInput(key);return el?Number(el.value||0):0}
  function setDesignValue(key,value){const el=designInput(key);if(el){el.value=Math.round(value);saveDesign()}}
  function selectElement(key){selectedElement=key;document.querySelectorAll('[data-gos3-edit-element]').forEach(b=>b.classList.toggle('active',b.dataset.gos3EditElement===key))}
  function bindDirectEditor(){}

  renderLayoutTemplates();
  const saveButton=document.getElementById('gos3-layout-save');
  if(saveButton)saveButton.addEventListener('click',()=>{const name=(layoutName&&layoutName.value||'').trim();if(!name){layoutMessage('保存名を入力してください。',true);return}saveDesign();const id='layout-'+Date.now();layoutTemplates[id]={name:name,updated_at:new Date().toISOString(),designs:clone(designs)};persistLayoutTemplates('レイアウトを保存しました。',id)});
  document.querySelectorAll('[data-gos3-preset]').forEach(b=>b.addEventListener('click',()=>applyPreset(b.dataset.gos3Preset)));
  iframe.addEventListener('load',()=>{if(previewTarget==='status')status.textContent='開催状況を実画面へ反映しました。';bindDirectEditor();});
  document.querySelectorAll('[data-event]').forEach(b=>b.addEventListener('click',()=>showEvent(b.dataset.event)));
  if(stateSelect)stateSelect.addEventListener('change',()=>showState(stateSelect.value));
  document.querySelectorAll('[data-device]').forEach(b=>b.addEventListener('click',()=>showDevice(b.dataset.device)));
  document.querySelectorAll('[data-preview-device]').forEach(b=>b.addEventListener('click',()=>{
    previewDevice=b.dataset.previewDevice; previewDeviceInput.value=previewDevice;
    document.querySelectorAll('[data-preview-device]').forEach(x=>x.classList.toggle('active',x===b));
    frame.classList.toggle('mobile',previewDevice==='mobile'); frame.classList.toggle('desktop',previewDevice==='desktop');
    if(overviewPreview){overviewPreview.classList.toggle('mobile',previewDevice==='mobile');overviewPreview.classList.toggle('desktop',previewDevice==='desktop')}
    if(previewTarget==='event_overview')renderOverviewPreview();else queuePreview();
  }));
  document.querySelectorAll('[data-preview-target]').forEach(b=>b.addEventListener('click',()=>setPreviewTarget(b.dataset.previewTarget)));
  document.querySelectorAll('[data-preview-season]').forEach(b=>b.addEventListener('click',()=>setPreviewSeason(b.dataset.previewSeason)));
  const openPc=document.getElementById('gos3-open-pc');if(openPc)openPc.addEventListener('click',()=>openPreview('pc'));
  const openMobile=document.getElementById('gos3-open-mobile');if(openMobile)openMobile.addEventListener('click',()=>openPreview('mobile'));
  const reloadPreview=document.getElementById('gos3-reload-preview');if(reloadPreview)reloadPreview.addEventListener('click',submitPreview);
  const openEventPc=document.getElementById('gos3-open-event-pc');if(openEventPc)openEventPc.addEventListener('click',()=>openEventPagePreview('desktop'));
  const openEventMobile=document.getElementById('gos3-open-event-mobile');if(openEventMobile)openEventMobile.addEventListener('click',()=>openEventPagePreview('mobile'));
  const reloadEvent=document.getElementById('gos3-reload-event-overview');if(reloadEvent)reloadEvent.addEventListener('click',reloadEventOverview);
  if(stateMode)stateMode.addEventListener('change',syncPublicSelection);
  if(manualState)manualState.addEventListener('change',syncPublicSelection);
  if(manualEvent)manualEvent.addEventListener('change',syncPublicSelection);
  document.querySelectorAll('[data-gos3-edit-element]').forEach(b=>b.addEventListener('click',()=>selectElement(b.dataset.gos3EditElement)));
  document.querySelectorAll('[data-gos3-align]').forEach(b=>b.addEventListener('click',()=>{const el=designInput(selectedElement+'_align');if(el){el.value=b.dataset.gos3Align;saveDesign();queuePreview()}}));
  const resetElement=document.getElementById('gos3-reset-element-position');if(resetElement)resetElement.addEventListener('click',()=>{setDesignValue(selectedElement+'_x',0);setDesignValue(selectedElement+'_y',0);queuePreview()});
  if(snapToggle)snapToggle.addEventListener('change',()=>{try{localStorage.setItem(snapStorageKey,snapToggle.checked?'1':'0')}catch(e){}});
  document.addEventListener('keydown',e=>{
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key))return;
    if(['INPUT','TEXTAREA','SELECT'].includes(document.activeElement&&document.activeElement.tagName))return;
    const step=e.shiftKey?10:1;
    let x=numberValue(selectedElement+'_x'),y=numberValue(selectedElement+'_y');
    if(e.key==='ArrowLeft')x-=step;if(e.key==='ArrowRight')x+=step;if(e.key==='ArrowUp')y-=step;if(e.key==='ArrowDown')y+=step;
    setDesignValue(selectedElement+'_x',x);setDesignValue(selectedElement+'_y',y);queuePreview();e.preventDefault();
  });
  form.addEventListener('input',e=>{if(e.target.matches('[data-design-key]'))saveDesign();if(previewTarget==='event_overview'&&e.target.name&&e.target.name.indexOf('events[')===0)renderOverviewPreview();else if(previewTarget==='status')queuePreview()});
  form.addEventListener('change',e=>{if(previewTarget==='event_overview'&&e.target.name&&e.target.name.indexOf('events[')===0)renderOverviewPreview();else if(previewTarget==='status'&&e.target!==stateSelect&&e.target!==stateMode&&e.target!==manualState&&e.target!==manualEvent)queuePreview()});
  form.addEventListener('submit',()=>{saveDesign();syncTemplatesHidden()});

  showEvent(eventKey);
  showState(stateKey);
  showDevice('desktop');
  designReady=true;
  setPreviewTarget('status');
});
})();

(function(){
  let timer = null;
  function isTextEditor(el){
    if(!el) return false;
    const tag=(el.tagName||'').toLowerCase();
    if(tag==='textarea') return true;
    if(tag!=='input') return false;
    const type=(el.type||'text').toLowerCase();
    return ['text','number','url','email','search','tel'].includes(type);
  }
  document.addEventListener('input', function(e){
    if(!isTextEditor(e.target)) return;
    const preview=document.getElementById('gos3-preview-frame');
    if(!preview) return;
    clearTimeout(timer);
    timer=setTimeout(function(){
      const btn=document.querySelector('[data-gos-preview-reload], #gos3-preview-reload, .gos3-preview-reload');
      if(btn) btn.click();
    }, 1000);
  }, true);
})();
