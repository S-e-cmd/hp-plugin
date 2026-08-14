
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
  let previewTarget='status',previewSeason='spring';
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
  function escHtml(value){return String(value==null?'':value).replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]))}
  function eventField(season,key){return form.querySelector('[name="events['+season+']['+key+']"]')}
  function eventValue(season,key){const el=eventField(season,key);return el?(el.type==='checkbox'?(el.checked?'1':''):el.value):''}
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
  function formatDateJaWithWeekday(value){
    if(!value)return '';
    const parts=String(value).split('-').map(Number);
    if(parts.length!==3||!parts[0]||!parts[1]||!parts[2])return String(value);
    const d=new Date(parts[0],parts[1]-1,parts[2]);
    const weekdays=['日','月','火','水','木','金','土'];
    return parts[0]+'年'+parts[1]+'月'+parts[2]+'日（'+weekdays[d.getDay()]+'）';
  }

  function formatTimeJa(value){if(!/^\d{2}:\d{2}$/.test(value||''))return '';const [h,m]=value.split(':').map(Number);return m===0?h+'時':h+'時'+String(m).padStart(2,'0')+'分'}
  function renderOverviewPreview(){
    if(!overviewPreviewBody)return;
    const s=previewSeason;
    const label=eventValue(s,'label').trim();
    if(overviewPageTitle)overviewPageTitle.textContent=label||({spring:'春',autumn:'秋',winter:'冬'}[s]+'の催し');
    const rows=[];
    const date=overviewDateText(s);if(date)rows.push([eventValue(s,'date_label').trim()||'開苑期間',escHtml(date)]);
    const open=formatTimeJa(eventValue(s,'open_time')),close=formatTimeJa(eventValue(s,'close_time'));
    if(open||close){let time=escHtml(open+(open&&close?'～':'')+close);const closeLabel=eventValue(s,'close_time_label').trim();if(close&&closeLabel)time+='（'+escHtml(closeLabel)+'）';const note=eventValue(s,'time_note').trim();if(note)time+='<br><small>'+escHtml(note)+'</small>';rows.push([eventValue(s,'time_label').trim()||'開苑時間',time])}
    const details=(eventValue(s,'price_details').trim()||eventValue(s,'price').trim());if(details){let ph=details.split(/\r?\n/).map(x=>x.trim()).filter(Boolean).map(x=>'<p>'+escHtml(x)+'</p>').join('');const pn=eventValue(s,'price_note').trim();if(pn)ph+='<p><small>'+escHtml(pn).replace(/\n/g,'<br>')+'</small></p>';rows.push([eventValue(s,'admission_label').trim()||'入苑料',ph])}
    const heading=eventValue(s,'overview_heading').trim();let html='<section class="gos-event-info">';if(heading)html+='<h2 class="gos-event-info__heading">'+escHtml(heading)+'</h2>';rows.forEach(row=>html+='<div class="gos-event-info__row"><div class="gos-event-info__label">'+escHtml(row[0])+'</div><div class="gos-event-info__value">'+row[1]+'</div></div>');const overviewNote=eventValue(s,'overview_note').trim();if(overviewNote)html+='<div class="gos-event-info__note">'+escHtml(overviewNote).replace(/\n/g,'<br>')+'</div>';html+='</section>';overviewPreviewBody.innerHTML=html;
  }
  const previewTargets={
    status:{
      label:'開催状況',
      context:'status',
      panel:frame,
      tools:previewActions,
      editor:directEditor,
      statusText:'開催状況の編集内容をトップページ実画面で表示します。',
      render:()=>submitPreview()
    },
    event_overview:{
      label:'会期ページ',
      context:'event_overview',
      panel:overviewPreview,
      tools:eventOverviewActions,
      editor:null,
      statusText:'会期ページ内の表示見本です。PC／スマホ実画面では現在の固定ページ上で確認できます。',
      render:()=>renderOverviewPreview()
    }
  };
  function setPreviewTarget(target){
    if(!previewTargets[target])target='status';
    previewTarget=target;
    document.querySelectorAll('[data-preview-target]').forEach(b=>b.classList.toggle('active',b.dataset.previewTarget===target));
    document.querySelectorAll('[data-preview-context]').forEach(el=>{el.hidden=el.dataset.previewContext!==target});
    Object.keys(previewTargets).forEach(key=>{
      const cfg=previewTargets[key];
      if(cfg.panel)cfg.panel.hidden=key!==target;
      if(cfg.tools)cfg.tools.hidden=key!==target;
      if(cfg.editor)cfg.editor.hidden=key!==target;
    });
    const cfg=previewTargets[target];
    status.textContent=cfg.statusText;
    cfg.render();
  }
  function setPreviewSeason(season){previewSeason=season;document.querySelectorAll('[data-preview-season]').forEach(b=>b.classList.toggle('active',b.dataset.previewSeason===season));renderOverviewPreview()}
  function eventPagePreviewUrl(deviceName){
    const raw=eventValue(previewSeason,'detail_url').trim();
    if(!raw)throw new Error('選択中の季節に会期ページURLが設定されていません。');
    const u=new URL(raw,window.location.href);
    u.searchParams.set('gos_event_info_preview',previewSeason);
    u.searchParams.set('gos_preview_token',token);
    u.searchParams.set('gos_preview_device',deviceName);
    u.searchParams.set('_gos',String(Date.now()));
    return u.toString();
  }
  function liveEventPreviewHtml(season){
    const rows=[];
    const date=overviewDateText(season);
    if(date)rows.push([eventValue(season,'date_label').trim()||'開苑期間',date,'']);
    const open=formatTimeJa(eventValue(season,'open_time')),close=formatTimeJa(eventValue(season,'close_time'));
    if(open||close){
      let value=open+(open&&close?'～':'')+close;
      const closeLabel=eventValue(season,'close_time_label').trim();
      if(close&&closeLabel)value+='（'+closeLabel+'）';
      rows.push([eventValue(season,'time_label').trim()||'開苑時間',value,eventValue(season,'time_note').trim()]);
    }
    const details=eventValue(season,'price_details').trim()||eventValue(season,'price').trim();
    if(details)rows.push([eventValue(season,'admission_label').trim()||'入苑料',details,eventValue(season,'price_note').trim()]);
    let html='<section id="gos-event-info-live-preview" class="gos-event-page-info" data-gos-event-season="'+escHtml(season)+'">';
    html+='<div class="gos-event-page-info__rows">';
    rows.forEach(row=>{
      html+='<div class="gos-event-page-info__row"><span class="gos-event-page-info__label">'+escHtml(row[0])+'：</span><span class="gos-event-page-info__value">'+escHtml(row[1]).replace(/\\n/g,'<br>');
      if(row[2])html+='<small class="gos-event-page-info__note">'+escHtml(row[2]).replace(/\\n/g,'<br>')+'</small>';
      html+='</span></div>';
    });
    html+='</div>';
    const overviewNote=eventValue(season,'overview_note').trim();
    if(overviewNote)html+='<p class="gos-event-page-info__footer-note">'+escHtml(overviewNote).replace(/\\n/g,'<br>')+'</p>';
    html+='</section>';
    return html;
  }
  function injectEventPagePreview(win,season){
    let stopped=false,observer=null,keepAliveTimer=null,scrollDone=false;
    function ensurePreview(){
      if(stopped||!win||win.closed){cleanup();return false}
      let doc;
      try{doc=win.document}catch(e){return false}
      if(!doc||doc.readyState==='loading')return false;
      let style=doc.getElementById('gos-event-info-live-preview-style');
      if(!style){
        style=doc.createElement('style');
        style.id='gos-event-info-live-preview-style';
        style.textContent='.gos-event-page-info{box-sizing:border-box!important;font-family:inherit!important;font-size:14px!important;font-weight:400!important;line-height:2!important;color:inherit!important}.gos-event-page-info__rows{display:block!important}.gos-event-page-info__row{display:grid!important;grid-template-columns:6.4em minmax(0,1fr)!important;column-gap:0!important;margin:0 0 .55em!important;font:inherit!important;line-height:inherit!important}.gos-event-page-info__label{display:block!important;white-space:nowrap!important;font:inherit!important;text-align:justify!important;text-align-last:justify!important;padding-right:.45em!important}.gos-event-page-info__value{display:block!important;min-width:0!important;white-space:pre-line!important;font:inherit!important;line-height:inherit!important}.gos-event-page-info__note{display:block!important;margin:0!important;font:inherit!important;line-height:inherit!important;white-space:pre-line!important}.gos-event-page-info__footer-note{margin:.55em 0 0!important;font:inherit!important;line-height:inherit!important;white-space:pre-line!important}@media(max-width:782px){.gos-event-page-info{font-size:12px!important;line-height:1.9!important}.gos-event-page-info__row{grid-template-columns:6.4em minmax(0,1fr)!important;column-gap:0!important;margin-bottom:.5em!important}}';
        (doc.head||doc.documentElement).appendChild(style);
      }
      let block=doc.getElementById('gos-event-info-live-preview');
      if(!block){
        const holder=doc.createElement('div');
        holder.innerHTML=liveEventPreviewHtml(season);
        block=holder.firstElementChild;
        if(!block)return false;
        const contentRoot=doc.querySelector('main article,.entry-content,.page-content,article,main,#primary,.site-main')||doc.body;
        const excluded='footer,header,nav,aside,#wpadminbar,.calendar,.tribe-events,.widget';
        const candidates=Array.from(contentRoot.querySelectorAll('p,li,dd,dt,tr,div')).filter(el=>{
          if(el.closest(excluded))return false;
          const text=(el.textContent||'').replace(/\s+/g,'');
          if(!text||text.length>320)return false;
          const blocks=Array.from(el.children).filter(c=>/^(DIV|SECTION|ARTICLE|TABLE|UL|OL)$/i.test(c.tagName));
          return !blocks.length&&(text.includes('開苑期間')||text.includes('開催期間'));
        });
        const anchor=candidates[0]||null;
        if(anchor&&anchor.parentNode){
          anchor.parentNode.insertBefore(block,anchor);
        }else{
          const heading=Array.from(contentRoot.querySelectorAll('h1,h2,h3,h4')).find(el=>!el.closest(excluded)&&(el.textContent||'').replace(/\s+/g,'').includes('会期情報'));
          if(heading&&heading.parentNode)heading.parentNode.insertBefore(block,heading.nextSibling);
          else contentRoot.insertBefore(block,contentRoot.firstChild);
        }
      }else{
        const holder=doc.createElement('div');
        holder.innerHTML=liveEventPreviewHtml(season);
        const fresh=holder.firstElementChild;
        if(fresh&&block.innerHTML!==fresh.innerHTML)block.replaceWith(fresh);
        block=fresh||block;
      }
      const contentRoot=doc.querySelector('main article,.entry-content,.page-content,article,main,#primary,.site-main')||doc.body;
      const excluded='footer,header,nav,aside,#wpadminbar,.calendar,.tribe-events,.widget';
      Array.from(contentRoot.querySelectorAll('p,li,dd,dt,tr,div')).forEach(el=>{
        if(el===block||el.closest('#gos-event-info-live-preview')||el.closest(excluded))return;
        const text=(el.textContent||'').replace(/\s+/g,'');
        if(!text||text.length>420)return;
        const blocks=Array.from(el.children).filter(c=>/^(DIV|SECTION|ARTICLE|TABLE|UL|OL)$/i.test(c.tagName));
        if(!blocks.length&&(text.includes('開苑期間')||text.includes('開催期間')||text.includes('開苑時間')||text.includes('入苑料'))){
          el.style.setProperty('display','none','important');
        }
      });
      if(!scrollDone&&block){
        block.scrollIntoView({behavior:'smooth',block:'center'});
        scrollDone=true;
      }
      status.textContent='会期ページ実画面へ編集中の内容を差し込みました。';
      return true;
    }
    function cleanup(){
      stopped=true;
      if(observer){observer.disconnect();observer=null}
      if(keepAliveTimer){window.clearInterval(keepAliveTimer);keepAliveTimer=null}
    }
    let attempts=0;
    const waitTimer=window.setInterval(()=>{
      attempts++;
      if(!win||win.closed){window.clearInterval(waitTimer);cleanup();return}
      if(ensurePreview()){
        window.clearInterval(waitTimer);
        let doc;
        try{doc=win.document}catch(e){return}
        if(doc&&doc.documentElement){
          observer=new win.MutationObserver(()=>{window.setTimeout(ensurePreview,0)});
          observer.observe(doc.documentElement,{childList:true,subtree:true});
        }
        keepAliveTimer=window.setInterval(ensurePreview,1000);
      }else if(attempts>200){
        window.clearInterval(waitTimer);
        status.textContent='会期ページ実画面への差し込みに失敗しました。';
      }
    },100);
  }
  function openEventPagePreview(deviceName){
    let url;
    try{url=eventPagePreviewUrl(deviceName)}catch(err){layoutMessage(err.message,true);status.textContent=err.message;return}
    const mobile=deviceName==='mobile';
    const win=window.open('about:blank',mobile?'gos_event_mobile_preview':'gos_event_pc_preview',mobile?'width=430,height=900,resizable=yes,scrollbars=yes':'');
    if(!win){status.textContent='ポップアップがブロックされました。';return}
    win.document.write('<!doctype html><meta charset="utf-8"><title>会期ページプレビュー準備中</title><p style="font-family:sans-serif;padding:20px">会期ページ実画面プレビュー準備中…</p>');
    savePreviewData().then(()=>{
      win.location.replace(url);
      injectEventPagePreview(win,previewSeason);
    }).catch(err=>{win.close();status.textContent='会期ページプレビュー失敗：'+err.message});
  }
  function reloadEventOverview(){
    renderOverviewPreview();
    status.textContent='会期ページの表示見本を更新しました。実画面は各実画面ボタンで開き直してください。';
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
  function findTemplateIdByName(name,excludeId){
    const target=String(name||'').trim();
    return Object.keys(layoutTemplates).find(id=>id!==excludeId&&String((layoutTemplates[id]||{}).name||'').trim()===target)||'';
  }
  const saveNewButton=document.getElementById('gos3-layout-save-new');
  if(saveNewButton)saveNewButton.addEventListener('click',(event)=>{event.preventDefault();event.stopPropagation();
    const name=(layoutName&&layoutName.value||'').trim().slice(0,80);
    if(!name){layoutMessage('レイアウト名を入力してください。',true);if(layoutName)layoutName.focus();return}
    if(findTemplateIdByName(name,'')){layoutMessage('同じ名前のレイアウトがすでにあります。既存レイアウトを選択して「上書き」を使用してください。',true);return}
    const pair=currentDesignPair();
    const id=makeLayoutId();
    layoutTemplates[id]={name,desktop:clone(pair.desktop),mobile:clone(pair.mobile)};
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
    const tpl=layoutTemplates[id];
    if(!window.confirm('「'+tpl.name+'」を現在のレイアウトで上書きしますか？'))return;
    const before=clone(tpl);
    const pair=currentDesignPair();
    tpl.desktop=clone(pair.desktop);tpl.mobile=clone(pair.mobile);
    overwriteButton.disabled=true;
    persistLayoutTemplates('「'+tpl.name+'」を上書きしました。',id)
      .catch(()=>{layoutTemplates[id]=before;syncTemplatesHidden();renderLayoutTemplates(id)})
      .finally(()=>{overwriteButton.disabled=false});
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
    const name=window.prompt('新しいレイアウト名',tpl.name||'');if(name===null)return;
    const clean=name.trim().slice(0,80);if(!clean){layoutMessage('名前を入力してください。',true);return}
    if(findTemplateIdByName(clean,id)){layoutMessage('同じ名前のレイアウトがすでにあります。別の名前を入力してください。',true);return}
    const before=String(tpl.name||'');
    if(clean===before.trim()){layoutMessage('名前は変更されていません。',false);return}
    tpl.name=clean;syncTemplatesHidden();renderLayoutTemplates(id);renameButton.disabled=true;
    persistLayoutTemplates('「'+before+'」を「'+clean+'」へ変更しました。',id)
      .catch(()=>{tpl.name=before;syncTemplatesHidden();renderLayoutTemplates(id)})
      .finally(()=>{renameButton.disabled=false});
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


  document.querySelectorAll('[data-gos-event-shortcode]').forEach(button=>button.addEventListener('click',()=>{
    const shortcode=button.dataset.gosEventShortcode||'';
    if(!shortcode)return;
    if(navigator.clipboard&&navigator.clipboard.writeText){
      navigator.clipboard.writeText(shortcode).then(()=>layoutMessage('ショートコードをコピーしました。',false));
    }else{
      window.prompt('このショートコードをコピーしてください',shortcode);
    }
  }));

  iframe.addEventListener('load',()=>{if(previewTarget==='status')status.textContent='開催状況を実画面へ反映しました。';bindDirectEditor();});
  document.querySelectorAll('[data-event]').forEach(b=>b.addEventListener('click',()=>showEvent(b.dataset.event)));
  stateSelect.addEventListener('change',()=>showState(stateSelect.value));
  document.querySelectorAll('[data-device]').forEach(b=>b.addEventListener('click',()=>showDevice(b.dataset.device)));
  document.querySelectorAll('[data-gos3-preset]').forEach(b=>b.addEventListener('click',()=>applyPreset(b.dataset.gos3Preset)));
  document.querySelectorAll('[data-preview-device]').forEach(b=>b.addEventListener('click',()=>{
    previewDevice=b.dataset.previewDevice; previewDeviceInput.value=previewDevice;
    document.querySelectorAll('[data-preview-device]').forEach(x=>x.classList.toggle('active',x===b));
    frame.classList.toggle('mobile',previewDevice==='mobile'); frame.classList.toggle('desktop',previewDevice==='desktop');
    if(overviewPreview){overviewPreview.classList.toggle('mobile',previewDevice==='mobile');overviewPreview.classList.toggle('desktop',previewDevice==='desktop')}
    previewTargets[previewTarget].render();
  }));
  document.querySelectorAll('[data-preview-target]').forEach(b=>b.addEventListener('click',()=>setPreviewTarget(b.dataset.previewTarget)));
  document.querySelectorAll('[data-preview-season]').forEach(b=>b.addEventListener('click',()=>setPreviewSeason(b.dataset.previewSeason)));
  document.getElementById('gos3-open-pc').addEventListener('click',()=>openPreview('pc'));
  document.getElementById('gos3-open-mobile').addEventListener('click',()=>openPreview('mobile'));
  document.getElementById('gos3-reload-preview').addEventListener('click',submitPreview);
  document.getElementById('gos3-open-event-pc').addEventListener('click',()=>openEventPagePreview('desktop'));
  document.getElementById('gos3-open-event-mobile').addEventListener('click',()=>openEventPagePreview('mobile'));
  document.getElementById('gos3-reload-event-overview').addEventListener('click',reloadEventOverview);
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