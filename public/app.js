document.addEventListener('DOMContentLoaded',()=>{
 document.querySelector('[data-menu]')?.addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));
 document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!window.confirm(f.dataset.confirm))e.preventDefault()}));
});
