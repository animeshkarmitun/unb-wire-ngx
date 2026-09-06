<div id="toastWrap" class="fixed bottom-5 right-5 z-[300] flex flex-col gap-2 pointer-events-none"></div>
<script>
window.unbToast = function(html){
  const w=document.getElementById('toastWrap');
  if(!w) return;
  const last = w.lastElementChild;
  if(last && last.innerHTML === html) return;
  const t=document.createElement('div');
  t.className='toast bg-navy-900 text-white rounded-[11px] px-4 py-3 text-[13px] font-medium shadow-xl pointer-events-auto';
  t.innerHTML=html;
  w.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300)},3200);
}
document.addEventListener('livewire:init',()=>{
  Livewire.on('toast',(e)=> unbToast(e.message ?? e[0]?.message ?? 'Done'));
});
window.addEventListener('toast', (e) => {
  unbToast(e.detail?.message ?? (typeof e.detail === 'string' ? e.detail : 'Done'));
});
</script>
