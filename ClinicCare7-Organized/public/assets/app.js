// Read the HTML attribute: a control named "action" shadows form.action in browsers.
function submissionUrl(form, base=location.href) {
 return new URL(form.getAttribute('action') || base, base).href;
}
const timers = document.querySelectorAll('time[data-assigned]');
function updateTimers() {
    const now = Math.floor(Date.now() / 1000);
    for (const timer of timers) {
        const minutes = Math.max(0, Math.floor((now - Number(timer.dataset.assigned)) / 60));
        const hours = Math.floor(minutes / 60);
        timer.textContent = hours ? `${hours}h ${minutes % 60}m elapsed` : `${minutes}m elapsed`;
    }
}
if (timers.length) {
    updateTimers();
    setInterval(updateTimers, 15000);
}
for (const search of document.querySelectorAll('.student-search')) {
    const select = search.nextElementSibling;
    const options = Array.from(select.options);
    search.addEventListener('input', () => {
        const query = search.value.toLowerCase().trim();
        for (const option of options) {
            option.hidden = Boolean(option.value && !option.selected && !option.text.toLowerCase().includes(query));
        }
    });
}

const password = document.querySelector('#signin-password');
if (password) {
  const toggle = document.querySelector('#toggle-password');
  toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide password' : 'Show password';
    toggle.setAttribute('aria-pressed', String(show));
  });
  const caps = document.querySelector('#caps-lock');
  const updateCaps = event => { caps.hidden = !event.getModifierState('CapsLock'); };
  password.addEventListener('keydown', updateCaps);
  password.addEventListener('keyup', updateCaps);
  password.addEventListener('blur', () => { caps.hidden = true; });
}
let draggedEvent = null;
document.querySelectorAll('[draggable][data-event]').forEach(card => {
  card.addEventListener('dragstart', event => {
    draggedEvent = card.dataset.event;
    event.dataTransfer.setData('text/plain', draggedEvent);
  });
  card.addEventListener('dragend', () => { draggedEvent = null; });
});
document.querySelectorAll('.time-slot').forEach(slot => {
  slot.addEventListener('dragover', event => {
    if (!draggedEvent) return;
    event.preventDefault();
    slot.classList.add('drag-over');
  });
  slot.addEventListener('dragleave', () => slot.classList.remove('drag-over'));
  slot.addEventListener('drop', event => {
    event.preventDefault();
    slot.classList.remove('drag-over');
    if (!draggedEvent) return;
    const form = document.querySelector('input[name="action"][value="reschedule"]').form;
    form.elements.id.value = draggedEvent;
    form.elements.starts.value = slot.dataset.time;
    form.requestSubmit();
  });
});

const inbox = document.querySelector('.alert-list')?.closest('.workflow-grid');
if (inbox) {
  inbox.addEventListener('submit', async event => {
    const form = event.target;
    if (form.method.toLowerCase() !== 'post') return;
    event.preventDefault();
    const button = event.submitter;
    if (button) button.disabled = true;
    try {
      const response = await fetch(submissionUrl(form), {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin'
      });
      if (!response.ok) throw new Error('Unable to confirm the result.');
      const next = new DOMParser().parseFromString(await response.text(), 'text/html');
      const updated = next.querySelector('.workflow-grid');
      if (!updated) {
        location.assign(response.url);
        return;
      }
      const list = inbox.querySelector('.alert-list');
      const scroll = list.scrollTop;
      inbox.replaceChildren(...updated.childNodes);
      inbox.querySelector('.alert-list').scrollTop = scroll;
      const main = inbox.closest('main');
      main.querySelector(':scope > .flash, :scope > .error')?.remove();
      const message = next.querySelector('main > .flash, main > .error');
      if (message) {
        message.tabIndex = -1;
        inbox.before(message);
        message.focus();
      }
      if (response.redirected) history.replaceState(null, '', response.url);
    } catch {
      const message = document.createElement('p');
      message.className = 'error';
      message.setAttribute('role', 'alert');
      message.textContent = 'The result could not be confirmed. Refresh the inbox before retrying.';
      inbox.before(message);
    } finally {
      if (button) button.disabled = false;
    }
  });
}

document.querySelector('.date-jump input[type=date]')?.addEventListener('change', event => {
  if (event.target.validity.valid && event.target.value) event.target.form.requestSubmit();
});
function selectCalendarDay(cell) {
  const date = cell.dataset.date;
  document.querySelectorAll('.calendar-grid .day').forEach(day => {
    const selected = day === cell;
    day.classList.toggle('selected-day', selected);
    day.setAttribute('aria-pressed', String(selected));
  });
  document.querySelector('.agenda-date').textContent = cell.dataset.label;
  document.querySelector('.page-head p').textContent = cell.dataset.label;
  const items = document.querySelector('.agenda-items');
  const template = document.getElementById('agenda-' + date);
  items.replaceChildren();
  if (template) items.append(template.content.cloneNode(true));
  else {
    const empty = document.createElement('p');
    empty.textContent = 'No events scheduled.';
    items.append(empty);
  }
  document.querySelector('.agenda-detail').replaceChildren();
  document.querySelector('.agenda-items').hidden=false;
  document.querySelector('.date-jump input[type=date]').value = date;
  document.querySelectorAll('.view-switch, .add-event a').forEach(link => {
    const url = new URL(link.href);
    url.searchParams.set('date', date);
    link.href = url;
  });
  const url = new URL(location.href);
  url.searchParams.set('date', date);
  url.searchParams.delete('event');
  history.replaceState(null, '', url);
}
document.querySelectorAll('.calendar-grid button[data-date]').forEach(cell => {
  cell.addEventListener('click', () => selectCalendarDay(cell));
  cell.addEventListener('dblclick', () => {
    location.assign('?p=calendar&mode=day&date=' + cell.dataset.date);
  });
  cell.addEventListener('keydown', event => {
    if (event.key === 'Enter' && event.shiftKey) {
      location.assign('?p=calendar&mode=day&date=' + cell.dataset.date);
    }
  });
});

// Version 6: delegated interaction remains active after modal/inbox refreshes.
const searchSvg = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/></svg>';
function enhance(root=document) {
 root.querySelectorAll('form').forEach(form=>{
  if(!form.getAttribute('action'))form.setAttribute('action',location.href);
  form.querySelectorAll('button').forEach(b=>{
   if(!b.classList.contains('icon-button')&&['Search','Filter'].includes(b.textContent.trim())){b.title='Search and filter';b.setAttribute('aria-label','Search and filter');b.classList.add('icon-button');b.innerHTML=searchSvg;}
  });
 });
 const reason=root.querySelector('select[name=reason]');
 if(reason){const other=root.querySelector('[data-other-reason]');if(other){const show=reason.value==='Other';other.hidden=!show;other.querySelector('textarea').required=show;}}
}
enhance();
const modal=document.createElement('dialog');modal.className='cc-modal';modal.setAttribute('aria-label','ClinicCare action');modal.innerHTML='<div class="modal-toolbar"><button type="button" data-close-modal aria-label="Close dialog">×</button></div><div class="modal-body"></div>';document.body.append(modal);
const modalBody=modal.querySelector('.modal-body');let modalUrl='',dirty=false,returnFocus=null;
function closeModal(){if(dirty&&!confirm('Discard your unsaved changes?'))return;modal.close();dirty=false;returnFocus?.focus();}
modal.addEventListener('cancel',e=>{e.preventDefault();closeModal();});
modal.addEventListener('click',e=>{if(e.target===modal||e.target.closest('[data-close-modal]'))closeModal();});
modal.addEventListener('input',()=>dirty=true);
modal.addEventListener('change',()=>{dirty=true;enhance(modalBody);});
function loadModalStyles(doc,url){
 doc.querySelectorAll('link[rel="stylesheet"]').forEach(source=>{
  const href=new URL(source.getAttribute('href'),url).href;
  if(!Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some(link=>link.href===href)){
   const link=document.createElement('link');link.rel='stylesheet';link.href=href;document.head.append(link);
  }
 });
}
function prepareModal(){
 const head=modalBody.querySelector('.page-head');
 if(head)modalBody.prepend(head);
 modalBody.querySelectorAll('.page-head a').forEach(a=>{if(a.textContent.trim()==='Cancel'||a.textContent.trim().startsWith('Back to'))a.remove();});
 modalBody.querySelectorAll('form').forEach(f=>{f.setAttribute('action',submissionUrl(f,modalUrl));});
 enhance(modalBody);modal.scrollTop=0;
}
function rememberScroll(){sessionStorage.setItem('cc-scroll:'+location.pathname+location.search,String(scrollY));const list=document.querySelector('.alert-list');if(list)sessionStorage.setItem('cc-alert-scroll:'+location.pathname+location.search,String(list.scrollTop));}
const savedScroll=sessionStorage.getItem('cc-scroll:'+location.pathname+location.search);if(savedScroll!==null){requestAnimationFrame(()=>scrollTo(0,Number(savedScroll)));sessionStorage.removeItem('cc-scroll:'+location.pathname+location.search);}
async function openModal(url,trigger){
 returnFocus=trigger;modalUrl=url;dirty=false;modalBody.textContent='Loading…';if(!modal.open)modal.showModal();
 try{const response=await fetch(url,{credentials:'same-origin'});const doc=new DOMParser().parseFromString(await response.text(),'text/html');const main=doc.querySelector('main.workspace');loadModalStyles(doc,url);if(!main)throw new Error('Your session may have expired. Refresh and sign in again.');modalBody.dataset.page=Array.from(main.classList).find(c=>c.startsWith('page-'))?.slice(5)||'form';modalBody.replaceChildren(...main.childNodes);modalBody.querySelectorAll('form').forEach(f=>{if(!f.getAttribute('action'))f.setAttribute('action',url);});prepareModal();modalBody.querySelector('input:not([type=hidden]),select,textarea,button,a')?.focus();}
 catch(error){modalBody.textContent=error.message;}
}
document.addEventListener('click',e=>{
 const link=e.target.closest('a[data-modal]');
 if(link&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey){e.preventDefault();if(modal.open&&dirty&&!confirm('Discard your unsaved changes?'))return;openModal(link.href,link);return;}
 const row=e.target.closest('[data-href]');if(row&&!e.target.closest('a,button,input,select,textarea,form')){rememberScroll();location.assign(row.dataset.href);return;}
 if(e.target.closest('a'))rememberScroll();
});
document.addEventListener('keydown',e=>{const row=e.target.closest('[data-href]');if(row&&e.target===row&&['Enter',' '].includes(e.key)){e.preventDefault();rememberScroll();location.assign(row.dataset.href);}});
document.addEventListener('input',e=>{if(e.target.matches('.student-search')){const select=e.target.nextElementSibling;for(const o of select.options)o.hidden=Boolean(o.value&&!o.selected&&!o.text.toLowerCase().includes(e.target.value.toLowerCase().trim()));}});
modal.addEventListener('submit',async e=>{
 const form=e.target;if(form.method.toLowerCase()!=='post')return;e.preventDefault();e.stopPropagation();if(form.dataset.busy)return;const data=new FormData(form);form.dataset.busy='1';const buttons=form.querySelectorAll('button[type=submit],button:not([type])');buttons.forEach(b=>b.disabled=true);
 try{
  const response=await fetch(submissionUrl(form,modalUrl),{method:'POST',body:data,credentials:'same-origin',headers:{'X-ClinicCare-Modal':'1','Accept':'application/json'}});
  if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('The server did not return a save confirmation. Check the record before retrying; keep this form open to retry the same submission safely.');
  const result=await response.json();
  if(!response.ok||!result.ok)throw new Error(result.message||'The change could not be saved.');
  dirty=false;rememberScroll();sessionStorage.setItem('cc-success',result.message||'Changes saved.');
  const destination=new URL(result.destination,location.href);
  const current=new URL(location.href);
  if(current.searchParams.get('p')==='form'||current.searchParams.get('p')==='overview'||current.searchParams.get('p')==='incidents')location.assign(destination.href);
  else location.reload();
 }catch(error){modalBody.querySelector('.error')?.remove();const p=document.createElement('p');p.className='error';p.setAttribute('role','alert');p.textContent=error.message;p.tabIndex=-1;const heading=modalBody.querySelector('.page-head');if(heading)heading.after(p);else modalBody.prepend(p);p.focus();}
 finally{delete form.dataset.busy;buttons.forEach(b=>b.disabled=false);}
});
// Disable regular POST submit controls after FormData has been consumed by inbox handlers.
document.addEventListener('submit',e=>{
 const f=e.target;if(f.method.toLowerCase()!=='post'||modal.contains(f))return;
 if(f.dataset.sending){e.preventDefault();return;}f.dataset.sending='1';rememberScroll();
 setTimeout(()=>{f.querySelectorAll('button').forEach(b=>b.disabled=true);if(e.defaultPrevented){delete f.dataset.sending;f.querySelectorAll('button').forEach(b=>b.disabled=false);}},0);
});
const success=sessionStorage.getItem('cc-success');if(success){const p=document.createElement('p');p.className='flash';p.setAttribute('role','status');p.textContent=success;document.querySelector('main')?.prepend(p);sessionStorage.removeItem('cc-success');}
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
new MutationObserver(()=>enhance()).observe(document.querySelector('main')||document.body,{childList:true,subtree:true});

// Keep the list location when following a record, including its filters.
const currentPage=new URL(location.href).searchParams;
if(['students','visits','inventory'].includes(currentPage.get('p'))&&!currentPage.has('id')&&!currentPage.has('medicine'))sessionStorage.setItem('cc-list:'+currentPage.get('p'),location.href);
document.querySelectorAll('a').forEach(a=>{
 const u=new URL(a.href);const page=u.searchParams.get('p');
 if(['Back to Students','Back to Visits','Back to Inventory'].includes(a.textContent.trim())){const remembered=sessionStorage.getItem('cc-list:'+page);if(remembered)a.href=remembered;}
});

modal.addEventListener('change',e=>{
 if(e.target.matches('select[name=student_id],select[name=medicine_id],select[name=batch_id]')){
  let context=modalBody.querySelector('.selection-context');if(!context){context=document.createElement('p');context.className='selection-context context';modalBody.prepend(context);}
  context.textContent=e.target.value?'Selected: '+e.target.selectedOptions[0].textContent:'';
 }
});

const savedAlerts=sessionStorage.getItem('cc-alert-scroll:'+location.pathname+location.search);
if(savedAlerts!==null&&document.querySelector('.alert-list')){document.querySelector('.alert-list').scrollTop=Number(savedAlerts);sessionStorage.removeItem('cc-alert-scroll:'+location.pathname+location.search);}
