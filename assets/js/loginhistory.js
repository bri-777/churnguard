
/* Robust api() */
async function api(url, init) {
  const res = await fetch(url, {
    credentials: 'include',
    headers: { 'Accept': 'application/json' },
    ...init
  });
  const txt = await res.text();
  if (!res.ok) throw new Error(txt || ('HTTP ' + res.status));
  let data;
  try { data = JSON.parse(txt); } catch {
    throw new Error('Bad JSON: ' + txt.slice(0, 200));
  }
  if (data && (data.error || data.ok === false || data.success === false)) {
    throw new Error(data.error || data.message || 'Request failed');
  }
  return data;
}

/* -------- Login History (unchanged layout; fixed logic) -------- */
(function () {
  const state = {
    latestId: 0,
    nextBeforeId: 0,
    seenIds: new Set(),
    sse: null,
    pollTimer: null,
    tbody: null,
    scroller: null,
  };

  const fmt = new Intl.DateTimeFormat(undefined, {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });

  function escapeHTML(v) {
    return String(v ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
  }
  function formatWhen(raw) {
    if (!raw) return '';
    const d = new Date(raw.includes(' ') ? raw.replace(' ', 'T') : raw);
    return isNaN(d) ? escapeHTML(raw) : fmt.format(d);
  }
  function statusBadge(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'success')
      return '<span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:4px 8px;border-radius:999px;font-weight:700;font-size:.8rem;">Success</span>';
    if (s === 'failed' || s === 'failure' || s === 'error')
      return '<span style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:4px 8px;border-radius:999px;font-weight:700;font-size:.8rem;">Failed</span>';
    return `<span style="background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;padding:4px 8px;border-radius:999px;font-weight:700;font-size:.8rem;">${escapeHTML(status || '—')}</span>`;
  }
  function showErrorRow(msg) {
    if (!state.tbody) return;
    const safe = escapeHTML(msg).slice(0, 300);
    state.tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#b91c1c;padding:10px;">${safe}</td></tr>`;
  }
  function skeletonRows(n = 4) {
    const cells = '<td colspan="5" style="padding:12px;color:#9ca3af;text-align:center;">Loading…</td>';
    return Array.from({length: n}, () => `<tr>${cells}</tr>`).join('');
  }
  function render(rows, mode = 'replace') {
    if (!state.tbody) return;
    const frag = document.createDocumentFragment();
    for (const r of rows) {
      const id = Number(r.id || 0);
      if (id && state.seenIds.has(id) && mode !== 'replace') continue;
      if (id) state.seenIds.add(id);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="padding:10px;border-bottom:1px solid #f3f4f6;">${formatWhen(r.accessed_at || r.event_time || r.datetime)}</td>
        <td style="padding:10px;border-bottom:1px solid #f3f4f6;">${escapeHTML(r.location || '')}</td>
        <td style="padding:10px;border-bottom:1px solid #f3f4f6;">${escapeHTML(r.device || '')}</td>
        <td style="padding:10px;border-bottom:1px solid #f3f4f6;">${escapeHTML(r.ip || r.ip_address || '')}</td>
        <td style="padding:10px;border-bottom:1px solid #f3f4f6;">${statusBadge(r.status)}</td>
      `;
      frag.appendChild(tr);
    }
    if (mode === 'replace') {
      state.tbody.innerHTML = '';
      if (rows.length === 0) {
        state.tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No history yet</td></tr>';
      } else {
        state.tbody.appendChild(frag);
      }
    } else if (mode === 'prepend') {
      state.tbody.prepend(frag);
    } else {
      state.tbody.append(frag);
    }
  }

  async function fetchInitial() {
    try {
      state.tbody.innerHTML = skeletonRows();
      const r = await api('api/login_history.php?action=list&limit=50&ts=' + Date.now());
      const items = Array.isArray(r.items) ? r.items : (r.history || []);
      state.latestId = Number(r.latest_id || (items[0]?.id || 0));
      state.nextBeforeId = Number(r.next_before_id || 0);
      render(items, 'replace');
    } catch (e) {
      console.error('[Login history] initial load failed:', e);
      showErrorRow(e.message || 'Failed to load.');
    }
  }
  async function recordAccessOnce() {
    try { await api('api/login_history.php?action=record', { method: 'POST' }); }
    catch (e) { console.warn('[Login history] record access failed:', e.message); }
  }
  async function loadMore() {
    if (!state.nextBeforeId) return;
    try {
      const r = await api('api/login_history.php?action=list&limit=50&before_id=' + state.nextBeforeId + '&ts=' + Date.now());
      const items = Array.isArray(r.items) ? r.items : [];
      state.nextBeforeId = Number(r.next_before_id || 0);
      if (items.length) render(items, 'append');
    } catch (e) { console.error('[Login history] pagination error:', e); }
  }
  function attachInfiniteScroll() {
    if (!state.scroller) return;
    state.scroller.addEventListener('scroll', () => {
      const nearBottom = state.scroller.scrollTop + state.scroller.clientHeight >= state.scroller.scrollHeight - 24;
      if (nearBottom) loadMore();
    }, { passive: true });
  }
  function stopPolling() { if (state.pollTimer) clearInterval(state.pollTimer); state.pollTimer = null; }
  function fallbackPoll() {
    if (state.pollTimer) return;
    state.pollTimer = setInterval(async () => {
      try {
        const r = await api('api/login_history.php?action=list&limit=50&since_id=' + state.latestId + '&ts=' + Date.now());
        const items = Array.isArray(r.items) ? r.items : [];
        if (items.length) {
          state.latestId = Number(r.latest_id || items[items.length - 1]?.id || state.latestId);
          render(items, 'prepend');
        }
      } catch (e) { console.error('[Login history] poll error:', e); }
    }, 2500);
  }
  function connectSSE() {
    if (!('EventSource' in window)) { fallbackPoll(); return; }
    try {
      const url = 'api/login_history.php?action=stream&since_id=' + state.latestId + '&ts=' + Date.now();
      const es = new EventSource(url, { withCredentials: true });
      state.sse = es;
      es.addEventListener('batch', (ev) => {
        try {
          const data = JSON.parse(ev.data || '{}');
          const items = Array.isArray(data.items) ? data.items : [];
          if (items.length) {
            state.latestId = Number(data.latest_id || items[items.length - 1]?.id || state.latestId);
            render(items, 'prepend');
          }
        } catch (e) { console.error('[Login history] SSE parse error:', e); }
      });
      es.addEventListener('ping', () => {});
      es.addEventListener('bye', () => { es.close(); setTimeout(connectSSE, 1000); });
      es.onerror = () => { es.close(); fallbackPoll(); setTimeout(() => { stopPolling(); connectSSE(); }, 15000); };
    } catch (e) { console.error('[Login history] SSE failed, using poll:', e); fallbackPoll(); }
  }

  async function bootstrap() {
    state.tbody    = document.getElementById('loginHistoryTable');
    state.scroller = document.querySelector('.login-history-table');
    if (!state.tbody) return;

    await recordAccessOnce();
    await fetchInitial();
    attachInfiniteScroll();
    connectSSE();

    window.refreshLoginHistory = async function () {
      stopPolling();
      if (state.sse) try { state.sse.close(); } catch {}
      state.latestId = 0; state.nextBeforeId = 0; state.seenIds.clear();
      await fetchInitial();
      connectSSE();
    };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootstrap);
  else bootstrap();
})();
