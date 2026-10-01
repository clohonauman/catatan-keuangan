(() => {
  const app = document.getElementById('planningApp');
  const state = { snapshot: window.PLANNING_INITIAL || {}, dirty: false, saving: false };
  const el = id => document.getElementById(id);
  const money = n => 'Rp' + new Intl.NumberFormat('id-ID').format(Math.max(0, Number(n || 0)));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const csrf = () => app?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
  const api = async (payload) => {
    const r = await fetch('../api.php', {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: {'Content-Type':'application/json','X-CSRF-Token':csrf()},
      body: JSON.stringify(payload)
    });
    const text = await r.text(); let j;
    try { j = JSON.parse(text); } catch (_) { throw new Error('Respons server tidak valid.'); }
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Permintaan gagal.');
    return j;
  };
  const toast = message => {
    let t = document.querySelector('.planning-toast');
    if (!t) { t=document.createElement('div'); t.className='planning-toast'; document.body.appendChild(t); }
    t.textContent=message; t.classList.add('show'); clearTimeout(t._tm); t._tm=setTimeout(()=>t.classList.remove('show'),2800);
  };
  const fmtDate = value => {
    const m=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/); return m ? `${m[3]}/${m[2]}/${m[1]}` : String(value||'');
  };
  function itemRows(type){ return (state.snapshot.items||[]).filter(x => (x.type||'variable')===type); }
  function renderKpis(){
    const s=state.snapshot, box=el('planningKpis'); if(!box)return;
    const cards=[
      ['Saldo awal diproyeksikan',money(s.projected_opening_balance),'setelah kewajiban sebelum bulan target'],
      ['Pemasukan untuk rencana',money(s.income_for_plan),'estimasi yang bisa Anda ubah'],
      ['Total rencana',money(s.planned_spending_total),'belanja + kewajiban aktif'],
      ['Tambahan dana',money(s.additional_funds_needed),s.additional_funds_needed>0?'perkiraan kekurangan':'tidak diperlukan saat ini']
    ];
    box.innerHTML=cards.map((c,i)=>`<div class="planning-kpi"><small>${esc(c[0])}</small><b>${esc(c[1])}</b><span>${esc(c[2])}</span></div>`).join('');
  }
  function renderAlert(){
    const s=state.snapshot, box=el('planningAlert'); if(!box)return;
    if(s.status==='warning') box.innerHTML=`<div class="alert-box warning"><b>Rencana melebihi dana yang diproyeksikan</b><span>Perlu tambahan sekitar <strong>${esc(money(s.additional_funds_needed))}</strong> agar seluruh rencana, kewajiban, dan target dana disisihkan dapat terpenuhi.</span></div>`;
    else if(s.status==='tight') box.innerHTML=`<div class="alert-box tight"><b>Masih muat, tetapi ruangnya cukup tipis</b><span>Setelah rencana dijalankan, perkiraan sisa dana sekitar <strong>${esc(money(s.projected_remaining))}</strong>. Pertimbangkan menyisakan ruang untuk kebutuhan tak terduga.</span></div>`;
    else box.innerHTML=`<div class="alert-box safe"><b>Rencana masih berada dalam ruang dana yang diproyeksikan</b><span>Perkiraan sisa setelah rencana sekitar <strong>${esc(money(s.projected_remaining))}</strong>. Hasil ini tetap merupakan estimasi, bukan jaminan.</span></div>`;
  }
  function renderPlanLists(){
    const vars=itemRows('variable'), obs=itemRows('obligation');
    const render=(items,type)=>items.map((x,idx)=>`<div class="plan-row ${x.enabled===false?'disabled':''}" data-plan-row data-id="${esc(x.id)}" data-type="${type}">
      <input class="plan-check" type="checkbox" data-plan-enabled ${x.enabled===false?'':'checked'} aria-label="Aktifkan ${esc(x.label)}">
      <div class="plan-copy"><b>${esc(x.label)}</b><small>${esc(x.source==='history'?`Riwayat ${x.history_months||''} bulan`:x.source==='bill'?`Tagihan · jatuh tempo ${fmtDate(x.due_date)}`:x.source==='recurring'?`Berulang · ${fmtDate(x.due_date)}`:'Rencana manual')}</small></div>
      <input class="plan-amount" data-plan-amount type="number" min="0" step="5000" value="${Number(x.amount||0)}" aria-label="Nominal ${esc(x.label)}">
      <button type="button" class="plan-remove" data-plan-remove aria-label="Hapus ${esc(x.label)}">×</button>
    </div>`).join('');
    el('variablePlanList').innerHTML=render(vars,'variable') || '<div class="empty-plan">Belum ada saran belanja variabel dari riwayat.</div>';
    el('obligationPlanList').innerHTML=render(obs,'obligation') || '<div class="empty-plan">Belum ada kewajiban terjadwal untuk bulan ini.</div>';
    document.querySelectorAll('[data-plan-enabled]').forEach(i=>i.addEventListener('change',()=>{i.closest('[data-plan-row]')?.classList.toggle('disabled',!i.checked); recalcFromUi();}));
    document.querySelectorAll('[data-plan-amount]').forEach(i=>i.addEventListener('input',recalcFromUi));
    document.querySelectorAll('[data-plan-remove]').forEach(b=>b.addEventListener('click',()=>{const row=b.closest('[data-plan-row]');state.snapshot.items=(state.snapshot.items||[]).filter(x=>String(x.id)!==String(row?.dataset.id||''));renderPlanLists();recalcFromUi();}));
  }
  function syncInputs(){
    const s=state.snapshot, override=s.saved_plan?.income_override;
    el('incomeOverride').value = override===null || override===undefined ? '' : Number(override);
    el('targetSavings').value = Number(s.saved_plan?.target_savings || 0);
    el('planNotes').value = s.saved_plan?.notes || '';
  }
  function renderSide(){
    const s=state.snapshot;
    el('monthLabel').textContent=s.month_label||s.month||'—';
    el('currentBalance').textContent=money(s.current_available_balance);
    el('openingBalance').textContent=money(s.projected_opening_balance);
    el('scheduledIncome').textContent=money(s.scheduled_income);
    el('historicalIncome').textContent=money(s.historical_income_estimate);
    el('variableTotal').textContent=money(s.variable_total);
    el('obligationTotal').textContent=money(s.planned_obligations_total);
    el('requiredTotal').textContent=money(s.planned_spending_total + Number(s.target_savings||0));
    el('remainingTotal').textContent=money(s.projected_remaining);
    const obligations=(s.obligations||[]).slice(0,8);
    el('obligationSummary').innerHTML=obligations.length?obligations.map(o=>`<div class="obligation-chip"><span>${esc(o.label)} · ${esc(fmtDate(o.due_date))}</span><b>${esc(money(o.amount))}</b></div>`).join(''):'<div class="empty-plan">Tidak ada kewajiban terjadwal yang ditemukan.</div>';
    el('creditSummary').innerHTML=(s.credit_cards||[]).length?(s.credit_cards||[]).map(c=>`<div class="credit-summary-row"><span>${esc(c.name)} · tersedia</span><b>${esc(money(c.available))}</b></div>`).join('')+'<div class="planning-disclaimer">Limit kartu kredit bukan saldo tunai. Tagihan yang sudah terjadwal tetap dihitung sebagai kewajiban.</div>':'<div class="empty-plan">Belum ada kartu kredit aktif.</div>';
  }
  function collectItems(){
    const map=new Map((state.snapshot.items||[]).map(x=>[String(x.id),{...x}]));
    document.querySelectorAll('[data-plan-row]').forEach(row=>{
      const item=map.get(String(row.dataset.id)); if(!item)return;
      item.enabled=row.querySelector('[data-plan-enabled]')?.checked!==false;
      item.amount=Math.max(0,Number(row.querySelector('[data-plan-amount]')?.value||0));
    });
    return Array.from(map.values()).filter(x=>x.label&&x.amount>0);
  }
  function recalcFromUi(){
    const items=collectItems(); state.snapshot.items=items;
    let variable=0, obligation=0; items.forEach(x=>{if(x.enabled===false)return;if((x.type||'variable')==='variable')variable+=Number(x.amount||0);else obligation+=Number(x.amount||0);});
    const savings=Math.max(0,Number(el('targetSavings')?.value||0));
    const override=el('incomeOverride')?.value.trim();
    const income=override===''?Number(state.snapshot.expected_income||0):Math.max(0,Number(override));
    const funds=Number(state.snapshot.projected_opening_balance||0)+income;
    const required=variable+obligation+savings;
    state.snapshot.variable_total=variable; state.snapshot.planned_obligations_total=obligation; state.snapshot.planned_spending_total=variable+obligation; state.snapshot.income_for_plan=income; state.snapshot.target_savings=savings; state.snapshot.available_funds=funds; state.snapshot.additional_funds_needed=Math.max(0,required-funds); state.snapshot.projected_remaining=funds-required; state.snapshot.status=state.snapshot.additional_funds_needed>0?'warning':(state.snapshot.projected_remaining<Math.max(100000,Number(state.snapshot.bridge_daily_estimate||0)*3)?'tight':'safe');
    state.dirty=true; renderKpis();renderAlert();renderSide();
  }
  function addItem(){
    const id='custom:'+Date.now()+Math.random().toString(16).slice(2,8);
    state.snapshot.items=[...(state.snapshot.items||[]),{id,type:'variable',label:'Kebutuhan baru',category:'Lainnya',amount:0,source:'custom',enabled:true}];
    renderPlanLists(); const row=document.querySelector(`[data-plan-row][data-id="${CSS.escape(id)}"]`); row?.querySelector('[data-plan-amount]')?.focus(); recalcFromUi();
  }
  async function loadMonth(month){
    if(!/^\d{4}-\d{2}$/.test(month))return;
    try{const j=await api({action:'snapshot',month});state.snapshot=j.snapshot;state.dirty=false;syncInputs();renderAll();}catch(e){toast(e.message||'Gagal memuat rencana.');}
  }
  function renderAll(){renderKpis();renderAlert();renderPlanLists();renderSide();syncInputs();}
  async function save(){
    if(state.saving)return; state.saving=true; collectItems();
    const payload={action:'save',month:state.snapshot.month,income_override:el('incomeOverride').value.trim()===''?null:Number(el('incomeOverride').value||0),target_savings:Number(el('targetSavings').value||0),notes:el('planNotes').value||'',items:state.snapshot.items};
    try{const j=await api(payload);state.snapshot=j.snapshot;state.dirty=false;syncInputs();renderAll();toast('Rencana belanja berhasil disimpan.');}catch(e){toast(e.message||'Gagal menyimpan rencana.');}finally{state.saving=false;}
  }
  async function reset(){
    if(!confirm('Kembalikan rencana bulan ini ke saran awal berdasarkan data Catatan Keuangan?'))return;
    try{const j=await api({action:'clear',month:state.snapshot.month});state.snapshot=j.snapshot;state.dirty=false;renderAll();toast('Saran awal sudah dipulihkan.');}catch(e){toast(e.message||'Gagal memulihkan saran.');}
  }
  document.addEventListener('DOMContentLoaded',()=>{
    const next=new Date();next.setDate(1);next.setMonth(next.getMonth()+1);const min=`${next.getFullYear()}-${String(next.getMonth()+1).padStart(2,'0')}`;el('planMonth').min=min;
    el('planMonth')?.addEventListener('change',e=>loadMonth(e.target.value));
    el('addPlanItem')?.addEventListener('click',addItem);el('savePlan')?.addEventListener('click',save);el('resetPlan')?.addEventListener('click',reset);
    el('incomeOverride')?.addEventListener('input',recalcFromUi);el('targetSavings')?.addEventListener('input',recalcFromUi);el('planNotes')?.addEventListener('input',()=>state.dirty=true);
    renderAll();
  });
  window.__financeAndroidBack=window.__financeAndroidBack||function(){return false;};
})();
