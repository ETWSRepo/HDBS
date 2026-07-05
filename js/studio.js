// ── DESIGN STUDIO (storefront) ──
// Renders the commission-studio page from api/studio.php data. Static defaults live in
// index.php (#studio-page); this file fills the dynamic sections and applies admin copy
// overrides from the studio_config setting. Sections with no active content stay hidden.
var STUDIO_ITEMS=[],STUDIO_CFG=null,STUDIO_LOADED=false,STUDIO_PICKS=[];
var STUDIO_GROUP='All',STUDIO_GROUPLIST=[];

function loadStudio(cb){
  apiFetch('studio.php').then(function(d){
    if(d&&d.success){STUDIO_ITEMS=d.items||[];STUDIO_CFG=d.config||null;STUDIO_LOADED=true;}
    if(cb)cb();
  }).catch(function(){if(cb)cb();});
}
function renderStudioEntry(){if(STUDIO_LOADED)renderStudio();else loadStudio(renderStudio);}
function dsItems(section){return STUDIO_ITEMS.filter(function(it){return it.section===section&&it.active==1;});}
function dsFind(id){for(var i=0;i<STUDIO_ITEMS.length;i++)if(STUDIO_ITEMS[i].id===id)return STUDIO_ITEMS[i];return null;}
function dsEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function dsScrollTo(id){var el=document.getElementById(id);if(el)el.scrollIntoView({behavior:'smooth'});}
// "See the work" targets the gallery when it has content; falls back to Services (always populated)
// since galleries stay hidden until Suzi adds real photos.
function dsScrollToWork(){dsScrollTo(dsItems('gallery').length?'ds-inspo':'ds-services-sec');}
function dsVal(id){var el=document.getElementById(id);return el?el.value.trim():'';}

function renderStudio(){
  dsApplyConfig();
  dsRenderServices();
  dsRenderGallery();
  dsRenderProjects();
  dsRenderTestimonials();
  dsRenderFaqs();
  dsRenderTypeOptions();
  dsRenderPicks();
  dsInitFade();
}

// Apply admin copy overrides (studio_config) onto the static defaults in index.php
function dsApplyConfig(){
  var c=STUDIO_CFG;if(!c)return;
  function setTxt(id,v){if(v){var el=document.getElementById(id);if(el)el.textContent=v;}}
  if(c.hero){
    setTxt('ds-hero-headline',c.hero.headline);
    setTxt('ds-hero-sub',c.hero.sub);
    setTxt('ds-hero-cta',c.hero.cta);
    if(c.hero.image){var bg=document.getElementById('ds-hero-bg');if(bg)bg.style.backgroundImage='url("'+c.hero.image+'")';}
  }
  if(c.steps&&c.steps.length)for(var i=0;i<5;i++){var s=c.steps[i]||{};
    setTxt('ds-step-i-'+(i+1),s.icon);setTxt('ds-step-t-'+(i+1),s.title);setTxt('ds-step-c-'+(i+1),s.copy);
  }
  if(c.why&&c.why.length)for(var j=0;j<6;j++){var w=c.why[j]||{};
    setTxt('ds-why-i-'+(j+1),w.icon);setTxt('ds-why-t-'+(j+1),w.title);setTxt('ds-why-c-'+(j+1),w.copy);
  }
  if(c.final){setTxt('ds-final-h',c.final.headline);setTxt('ds-final-sub',c.final.sub);setTxt('ds-final-cta',c.final.cta);}
}

// "What We Create" service cards
function dsRenderServices(){
  var el=document.getElementById('ds-services'),sec=document.getElementById('ds-services-sec');
  if(!el||!sec)return;
  var items=dsItems('service');
  if(!items.length){sec.style.display='none';return;}
  sec.style.display='';
  var h='';
  items.forEach(function(it){
    var d=it.data||{};
    h+='<div class="ds-card ds-fade">'+
      (it.image?'<div class="ds-card-img"><img src="'+dsEsc(it.image)+'" alt="'+dsEsc(it.title)+'" loading="lazy"></div>':'')+
      '<div class="ds-card-body"><h3>'+dsEsc(it.title)+'</h3>'+
      (d.desc?'<p>'+dsEsc(d.desc)+'</p>':'')+
      (d.ideal?'<div class="ds-ideal"><span>Ideal for:</span> '+dsEsc(d.ideal)+'</div>':'')+
      (d.example?'<div class="ds-example">e.g. '+dsEsc(d.example)+'</div>':'')+
      '<button class="ds-card-cta" onclick="dsPickType('+it.id+')">Start this project →</button>'+
      '</div></div>';
  });
  el.innerHTML=h;
}
function dsPickType(id){
  var it=dsFind(id);
  var sel=document.getElementById('ds-type');
  if(it&&sel){for(var i=0;i<sel.options.length;i++)if(sel.options[i].value===it.title){sel.value=it.title;break;}}
  dsScrollTo('ds-inquire');
}

// "Start With Inspiration" gallery — group pills + masonry, with ♥ picks feeding the form
function dsRenderGallery(){
  var sec=document.getElementById('ds-inspo');if(!sec)return;
  var items=dsItems('gallery');
  if(!items.length){sec.style.display='none';return;}
  sec.style.display='';
  STUDIO_GROUPLIST=[];
  items.forEach(function(it){var g=(it.data&&it.data.group)||'More';if(STUDIO_GROUPLIST.indexOf(g)<0)STUDIO_GROUPLIST.push(g);});
  if(STUDIO_GROUP!=='All'&&STUDIO_GROUPLIST.indexOf(STUDIO_GROUP)<0)STUDIO_GROUP='All';
  var gh='<button class="ds-pill'+(STUDIO_GROUP==='All'?' on':'')+'" onclick="setStudioGroup(-1)">All</button>';
  STUDIO_GROUPLIST.forEach(function(g,i){
    gh+='<button class="ds-pill'+(g===STUDIO_GROUP?' on':'')+'" onclick="setStudioGroup('+i+')">'+dsEsc(g)+'</button>';
  });
  document.getElementById('ds-groups').innerHTML=gh;
  var filtered=items.filter(function(it){return STUDIO_GROUP==='All'||((it.data&&it.data.group)||'More')===STUDIO_GROUP;});
  var h='';
  filtered.forEach(function(it){
    if(!it.image)return;
    var picked=STUDIO_PICKS.indexOf(it.id)>=0;
    var alt=dsEsc((it.data&&it.data.alt)||it.title);
    h+='<div class="masonry-item ds-gitem">'+
      '<img src="'+dsEsc(it.image)+'" alt="'+alt+'" loading="lazy" onclick="dsOpenGalleryImg('+it.id+')">'+
      '<button class="ds-heart'+(picked?' on':'')+'" aria-pressed="'+picked+'" aria-label="Add to my inspiration" title="Add to my inspiration" onclick="toggleStudioPick('+it.id+')">♥</button>'+
      '</div>';
  });
  document.getElementById('ds-gallery').innerHTML=h;
}
function setStudioGroup(i){STUDIO_GROUP=(i<0)?'All':(STUDIO_GROUPLIST[i]||'All');dsRenderGallery();}
function dsOpenGalleryImg(id){
  var it=dsFind(id);
  if(it&&it.image&&typeof openLightbox==='function')openLightbox([it.image],0,(it.data&&it.data.alt)||it.title);
}
function toggleStudioPick(id){
  var i=STUDIO_PICKS.indexOf(id);
  if(i>=0)STUDIO_PICKS.splice(i,1);else STUDIO_PICKS.push(id);
  dsRenderGallery();dsRenderPicks();
}
function dsRenderPicks(){
  var el=document.getElementById('ds-picks');if(!el)return;
  if(!STUDIO_PICKS.length){
    el.innerHTML='<span class="ds-picks-hint">Tap the ♥ on any inspiration photo above and it will appear here with your message.</span>';
    return;
  }
  var h='';
  STUDIO_PICKS.forEach(function(id){
    var it=dsFind(id);if(!it)return;
    h+='<span class="ds-pick"><img src="'+dsEsc(it.image)+'" alt="'+dsEsc(it.title)+'"><button onclick="toggleStudioPick('+id+')" aria-label="Remove '+dsEsc(it.title)+'">×</button></span>';
  });
  el.innerHTML=h;
}

// "Recent Custom Projects" — cards opening a story modal
function dsRenderProjects(){
  var el=document.getElementById('ds-projects'),sec=document.getElementById('ds-projects-sec');
  if(!el||!sec)return;
  var items=dsItems('project');
  if(!items.length){sec.style.display='none';return;}
  sec.style.display='';
  var h='';
  items.forEach(function(it){
    var d=it.data||{};
    h+='<div class="ds-card ds-proj ds-fade" onclick="openStudioProject('+it.id+')">'+
      (it.image?'<div class="ds-card-img"><img src="'+dsEsc(it.image)+'" alt="'+dsEsc(it.title)+'" loading="lazy"></div>':'')+
      '<div class="ds-card-body"><h3>'+dsEsc(it.title)+'</h3>'+
      (d.problem?'<p>'+dsEsc(d.problem)+'</p>':'')+
      '<span class="ds-card-cta">Read the story →</span></div></div>';
  });
  el.innerHTML=h;
}
function openStudioProject(id){
  var it=dsFind(id);if(!it)return;
  var d=it.data||{};
  var h=(it.image?'<img src="'+dsEsc(it.image)+'" alt="'+dsEsc(it.title)+'" style="width:100%;border-radius:10px;margin-bottom:1rem">':'')+
    '<h3 style="font-family:var(--font-head);font-size:1.4rem;color:#2d2220;margin-bottom:.8rem">'+dsEsc(it.title)+'</h3>';
  if(d.problem)h+='<div class="ds-proj-block"><div class="ds-proj-label">The idea</div><p>'+dsEsc(d.problem)+'</p></div>';
  if(d.approach)h+='<div class="ds-proj-block"><div class="ds-proj-label">The creative approach</div><p>'+dsEsc(d.approach)+'</p></div>';
  if(d.result)h+='<div class="ds-proj-block"><div class="ds-proj-label">The finished piece</div><p>'+dsEsc(d.result)+'</p></div>';
  if(d.quote)h+='<div class="ds-proj-quote">&ldquo;'+dsEsc(d.quote)+'&rdquo;'+(d.quote_name?'<div class="ds-proj-quote-name">— '+dsEsc(d.quote_name)+'</div>':'')+'</div>';
  document.getElementById('ds-proj-body').innerHTML=h;
  openModal('ds-proj-modal');
}

// Testimonials — staggered cards (title = person's name)
function dsRenderTestimonials(){
  var el=document.getElementById('ds-testi'),sec=document.getElementById('ds-testi-sec');
  if(!el||!sec)return;
  var items=dsItems('testimonial');
  if(!items.length){sec.style.display='none';return;}
  sec.style.display='';
  var h='';
  items.forEach(function(it){
    var d=it.data||{};
    h+='<div class="ds-tcard ds-fade">'+
      (it.image?'<img class="ds-tphoto" src="'+dsEsc(it.image)+'" alt="'+dsEsc(it.title)+'" loading="lazy">':'')+
      '<p class="ds-tquote">&ldquo;'+dsEsc(d.quote||'')+'&rdquo;</p>'+
      '<div class="ds-tname">'+dsEsc(it.title)+(d.context?'<span> · '+dsEsc(d.context)+'</span>':'')+'</div>'+
      '</div>';
  });
  el.innerHTML=h;
}

// FAQ accordion
function dsRenderFaqs(){
  var el=document.getElementById('ds-faq'),sec=document.getElementById('ds-faq-sec');
  if(!el||!sec)return;
  var items=dsItems('faq');
  if(!items.length){sec.style.display='none';return;}
  sec.style.display='';
  var h='';
  items.forEach(function(it){
    var d=it.data||{};
    h+='<div class="ds-faq-item"><button class="ds-faq-q" aria-expanded="false" onclick="dsToggleFaq(this)">'+dsEsc(it.title)+'<span class="ds-faq-x">+</span></button>'+
      '<div class="ds-faq-a" style="display:none"><p>'+dsEsc(d.answer||'')+'</p></div></div>';
  });
  el.innerHTML=h;
}
function dsToggleFaq(btn){
  var open=btn.getAttribute('aria-expanded')==='true';
  btn.setAttribute('aria-expanded',open?'false':'true');
  btn.nextElementSibling.style.display=open?'none':'block';
  var x=btn.querySelector('.ds-faq-x');if(x)x.textContent=open?'+':'–';
}

// Inquiry form: project-type options from service titles
function dsRenderTypeOptions(){
  var sel=document.getElementById('ds-type');if(!sel)return;
  var cur=sel.value;
  var h='<option value="">— What kind of project? —</option>';
  dsItems('service').forEach(function(it){h+='<option>'+dsEsc(it.title)+'</option>';});
  h+='<option>Something else entirely</option>';
  sel.innerHTML=h;
  if(cur)sel.value=cur;
}
function submitStudioInquiry(){
  var name=dsVal('ds-name'),email=dsVal('ds-email'),desc=dsVal('ds-desc');
  var err=document.getElementById('ds-err');err.style.display='none';
  if(!name||!email||!desc){err.textContent='Please fill in your name, email, and a project description.';err.style.display='block';return;}
  if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){err.textContent='Please enter a valid email address.';err.style.display='block';return;}
  var picks=STUDIO_PICKS.map(function(id){var it=dsFind(id);return it?{id:it.id,title:it.title,image:it.image}:null;}).filter(Boolean);
  var btn=document.getElementById('ds-submit');btn.disabled=true;btn.textContent='Sending…';
  apiFetch('studio.php','POST',{
    action:'inquire',name:name,email:email,phone:dsVal('ds-phone'),
    project_type:dsVal('ds-type'),budget:dsVal('ds-budget'),timeline:dsVal('ds-timeline'),
    contact_pref:dsVal('ds-pref'),description:desc,
    inspiration:{picks:picks,links:dsVal('ds-links')}
  }).then(function(d){
    btn.disabled=false;btn.textContent='Send My Project Inquiry';
    if(d&&d.success){
      document.getElementById('ds-form-wrap').style.display='none';
      document.getElementById('ds-ok').style.display='block';
      STUDIO_PICKS=[];dsRenderGallery();
      dsScrollTo('ds-inquire');
    } else {
      err.textContent=(d&&d.error)||'Failed to send — please email us directly.';err.style.display='block';
    }
  }).catch(function(){
    btn.disabled=false;btn.textContent='Send My Project Inquiry';
    err.textContent='Network error. Please email '+(window.BIZ_EMAIL||'handmadedesignsbysuzi@yahoo.com')+' directly.';
    err.style.display='block';
  });
}

// Scroll fade-ups (skipped for prefers-reduced-motion) + sticky mobile CTA
var _dsObs=null;
function dsInitFade(){
  var els=document.querySelectorAll('#studio-page .ds-fade:not(.in)');
  var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(reduce||!('IntersectionObserver' in window)){
    els.forEach(function(e){e.classList.add('in');});
    return;
  }
  if(!_dsObs)_dsObs=new IntersectionObserver(function(entries){
    entries.forEach(function(en){if(en.isIntersecting){en.target.classList.add('in');_dsObs.unobserve(en.target);}});
  },{threshold:.12});
  els.forEach(function(e){_dsObs.observe(e);});
}
window.addEventListener('scroll',function(){
  var sp=document.getElementById('studio-page'),b=document.getElementById('ds-sticky-cta');
  if(!sp||!b)return;
  if(sp.style.display==='none'||!sp.style.display){b.style.display='none';return;}
  var form=document.getElementById('ds-inquire');
  var formVisible=form&&form.getBoundingClientRect().top<window.innerHeight;
  b.style.display=(window.scrollY>500&&!formVisible)?'block':'none';
},{passive:true});

// Deep link: ?studio=1 opens the Design Studio directly (mirrors checkProductParam)
document.addEventListener('DOMContentLoaded',function(){
  try{
    var params=new URLSearchParams(window.location.search);
    if(params.get('studio')){
      history.replaceState({},'',window.location.pathname);
      if(typeof goStudio==='function')goStudio();
    }
  }catch(e){}
});
