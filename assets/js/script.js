/* ============================================================
   ChurnGuard Pro — script.js (single source of truth)
   Works with PHP endpoints under /api
   - Fixes customer monitoring to use latest churn prediction
   - Keeps dashboard/insights/reports in sync
   - Robust fetch + schema normalization
=========================================================== */
(function () {
  'use strict';

  /* -------------------- config & utils -------------------- */
  const API_BASE = 'api/'; // your PHP files live in /api
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const peso = (n, dp = 0) => '₱' + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: dp });
  const pct  = (n, dp = 2) => (n == null ? '—' : `${Number(n).toFixed(dp)}%`);
  const clamp = (n, lo, hi) => Math.max(lo, Math.min(hi, Number(n || 0)));

  function apiPath(p) {
    return (/^https?:\/\//.test(p) || p.startsWith('/')) ? p : (API_BASE + p.replace(/^\/+/, ''));
  }

  async function api(url, options = {}) {
    const u = apiPath(url);
    const isForm = (options.body instanceof FormData);
    const headers = {
      'Accept': 'application/json',
      'X-CSRF-Token': csrf(),
      ...(options.headers || {})
    };
    if (!isForm && options.method && options.method.toUpperCase() === 'POST' && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(u, { credentials: 'same-origin', ...options, headers });
    let text = '';
    try { text = await res.text(); } catch {}
    let data;
    try { data = text ? JSON.parse(text) : {}; }
    catch {
      const snippet = String(text).replace(/<[^>]*>/g, ' ').slice(0, 160).trim();
      throw new Error(`Invalid JSON from ${u}${snippet ? ' — ' + snippet : ''}`);
    }
    if (!res.ok || data?.success === false) {
      throw new Error(data?.message || data?.error || `HTTP ${res.status}`);
    }
    return data;
  }

  async function apiTry(urls, options) {
    const list = Array.isArray(urls) ? urls : [urls];
    let err;
    for (let i = 0; i < list.length; i++) {
      try { return await api(list[i], options); }
      catch (e) { err = e; }
    }
    throw err || new Error('All endpoints failed');
  }

  /* -------------------- navigation -------------------- */
  function showPage(id) {
    $$('.page').forEach(p => p.classList.remove('active'));
    $('#' + id)?.classList.add('active');

    $$('.sidebar-menu .menu-item').forEach(a => {
      a.classList.remove('active');
      const oc = a.getAttribute('onclick') || '';
      const m = oc.match(/showPage\('([^']+)'\)/);
      if (m && m[1] === id) a.classList.add('active');
    });

    if (id === 'dashboard') { loadDashboard(); loadCharts(); }
    if (id === 'churn-prediction') { loadChurnRisk(); }
    if (id === 'customer-insights') { loadCustomerInsights(); }
    if (id === 'customer-monitoring') { loadCustomerMonitoring(); }
  }
  function wireSidebarClicks() {
    $$('.sidebar-menu .menu-item').forEach(a => {
      a.addEventListener('click', (ev) => {
        ev.preventDefault();
        const m = (a.getAttribute('onclick') || '').match(/showPage\('([^']+)'\)/);
        showPage(m && m[1] ? m[1] : 'dashboard');
      }, { passive: false });
    });
  }
  window.showPage = showPage;
  window.toggleSidebar = () => document.body.classList.toggle('sidebar-collapsed');

  /* -------------------- dashboard KPIs -------------------- */
  async function loadDashboard() {
    try {
      const d = await apiTry(['dashboard.php', 'get_dashboard.php']);

      $('#totalRevenue')    && ($('#totalRevenue').textContent    = peso(d.todays_sales ?? d.totalRevenue ?? 0, 0));
      $('#activeCustomers') && ($('#activeCustomers').textContent = String(d.todays_customers ?? d.customersToday ?? 0));
      $('#retentionRate')   && ($('#retentionRate').textContent   = pct(d.retention_rate ?? d.retentionRate ?? 0, 2));
      $('#churnRisk')       && ($('#churnRisk').textContent       = pct(d.churn_risk ?? d.churnRisk ?? 0, 2));

      $('#revenueChange')   && ($('#revenueChange').lastChild.nodeValue   = ' ' + pct(Math.abs(d.revenue_change ?? 0)));
      $('#customersChange') && ($('#customersChange').lastChild.nodeValue = ' ' + pct(Math.abs(d.customers_change ?? 0)));
      $('#retentionChange') && ($('#retentionChange').lastChild.nodeValue = ' ' + pct(Math.abs(d.retention_change ?? 0)));
      $('#riskChange')      && ($('#riskChange').lastChild.nodeValue      = ' ' + pct(Math.abs(d.risk_change ?? 0)));

      $('#lastUpdate') && ($('#lastUpdate').textContent = new Date().toLocaleString());

      // mini churn widget if present
      loadChurnAssessmentForDashboard();
    } catch (e) {
      console.warn('[Dashboard]', e.message);
    }
  }

  /* -------------------- churn prediction -------------------- */
  function normalizePrediction(resp) {
    const src = (resp && (resp.prediction || resp.latest || resp.data)) || resp || {};
    const out = { has: false, percent: null, level: '', description: '', factors: [] };

    if (src.has_prediction === false) return out;

    let p = src.percentage;
    if (p == null && src.risk_score != null) p = Number(src.risk_score) <= 1 ? Number(src.risk_score) * 100 : Number(src.risk_score);
    if (p == null && src.score != null)      p = Number(src.score)      <= 1 ? Number(src.score) * 100      : Number(src.score);
    if (p == null && src.probability != null) p = Number(src.probability) <= 1 ? Number(src.probability) * 100 : Number(src.probability);
    if (p == null && src.riskProbability != null) p = Number(src.riskProbability) <= 1 ? Number(src.riskProbability) * 100 : Number(src.riskProbability);

    let lvl = src.level || src.risk_level || '';
    if (!lvl && p != null) {
      const n = clamp(p, 0, 100);
      lvl = n >= 67 ? 'High' : n >= 34 ? 'Medium' : 'Low';
    }

    if (p != null) {
      out.has = true;
      out.percent = clamp(p, 0, 100);
      out.level = lvl || '—';
      out.description = src.description || src.note || '';
      try {
        out.factors = Array.isArray(src.factors) ? src.factors : (src.factors ? JSON.parse(src.factors) : []);
      } catch { out.factors = []; }
    }
    return out;
  }

  function setRiskCircle(el, pct) {
    if (!el) return;
    const n = pct == null ? null : clamp(pct, 0, 100);
    el.classList.remove('low-risk', 'medium-risk', 'high-risk');
    if (n == null) {
      el.style.setProperty('--pct', '0%');
      return;
    }
    if (n >= 67) el.classList.add('high-risk');
    else if (n >= 34) el.classList.add('medium-risk');
    else el.classList.add('low-risk');
    el.style.setProperty('--pct', n.toFixed(0) + '%');
  }

  async function loadChurnRisk() {
    try {
      const r = await api('churn_risk.php');
      const n = normalizePrediction(r);

      const circle = $('#riskCircle');
      const pctEl  = $('#riskPercentage');
      const lvlEl  = $('#riskLevel');
      const descEl = $('#riskDescription');
      const facEl  = $('#riskFactors');

      pctEl  && (pctEl.textContent  = n.has ? `${Math.round(n.percent)}%` : '—');
      lvlEl  && (lvlEl.textContent  = n.has ? `${n.level} Risk` : 'No prediction yet');
      descEl && (descEl.textContent = n.has ? (n.description || '—') : 'Click “Run Churn Prediction” to generate a risk score.');
      setRiskCircle(circle, n.has ? n.percent : null);

      if (facEl) {
        facEl.innerHTML = '';
        (n.factors && n.factors.length ? n.factors : ['No risk factors']).forEach(t => {
          const s = document.createElement('span');
          s.className = 'risk-factor-tag';
          s.textContent = String(t);
          facEl.appendChild(s);
        });
      }
    } catch (e) {
      console.warn('[Churn risk]', e.message);
      $('#riskPercentage')  && ($('#riskPercentage').textContent = '—');
      $('#riskLevel')       && ($('#riskLevel').textContent = 'No prediction yet');
      $('#riskDescription') && ($('#riskDescription').textContent = 'Unable to load prediction at the moment.');
      const wrap = $('#riskFactors'); if (wrap) wrap.innerHTML = '<span class="risk-factor-tag">No risk factors</span>';
      setRiskCircle($('#riskCircle'), null);
    }
  }
  window.loadChurnRisk = loadChurnRisk;

  async function runChurnPrediction() {
    const btn = $('#runChurnPredictionBtn');
    const orig = btn ? btn.innerHTML : '';
    try {
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running…'; }
      await api('churn_risk.php?action=run', { method: 'POST' });
      await loadChurnRisk();
      await loadDashboard();
      showPage('churn-prediction');
      alert('Churn prediction completed.');
    } catch (e) {
      alert('Prediction error: ' + e.message);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
  }
  window.runChurnPrediction = runChurnPrediction;

  // Mini widget on dashboard (only if those nodes exist in your HTML)
  async function loadChurnAssessmentForDashboard() {
    const pctEl  = $('#riskPercentageDash');
    const lvlEl  = $('#riskLevelDash');
    const descEl = $('#riskDescriptionDash');
    const factEl = $('#riskFactorsDash');
    const circle = $('#riskCircleDash');
    if (!pctEl && !lvlEl && !descEl && !factEl && !circle) return;

    try {
      const resp = await apiTry(['churn_risk.php', 'churn_risk.php?action=latest']);
      const n = normalizePrediction(resp);
      if (!n.has) {
        pctEl  && (pctEl.textContent = '—');
        lvlEl  && (lvlEl.textContent = 'No prediction yet');
        descEl && (descEl.textContent = 'Click “Run Churn Prediction” in Data Input to generate a score.');
        factEl && (factEl.innerHTML = '<span class="risk-factor-tag">No risk factors</span>');
        setRiskCircle(circle, null);
        return;
      }
      pctEl  && (pctEl.textContent = Math.round(n.percent) + '%');
      lvlEl  && (lvlEl.textContent = n.level || '—');
      descEl && (descEl.textContent = n.description || '—');
      if (factEl) {
        factEl.innerHTML = '';
        (n.factors && n.factors.length ? n.factors : ['No risk factors']).forEach(t => {
          const s = document.createElement('span');
          s.className = 'risk-factor-tag';
          s.textContent = String(t);
          factEl.appendChild(s);
        });
      }
      setRiskCircle(circle, n.percent);
    } catch (e) {
      pctEl  && (pctEl.textContent = '—');
      lvlEl  && (lvlEl.textContent = 'No prediction yet');
      descEl && (descEl.textContent = 'Unable to load prediction.');
      factEl && (factEl.innerHTML = '<span class="risk-factor-tag">No risk factors</span>');
      setRiskCircle(circle, null);
      console.warn('[Dashboard assessment]', e.message);
    }
  }

  /* -------------------- data input -------------------- */
  function v(id) { return $('#' + id)?.value || ''; }
  async function saveChurnData() {
    const payload = {
      date: v('date'),
      receipt_count: v('receiptCount'),
      sales_volume: v('salesVolume'),
      customer_traffic: v('customerTraffic'),
      morning_receipt_count: v('morningReceiptCount'),
      swing_receipt_count: v('swingReceiptCount'),
      graveyard_receipt_count: v('graveyardReceiptCount'),
      morning_sales_volume: v('morningSalesVolume'),
      swing_sales_volume: v('swingSalesVolume'),
      graveyard_sales_volume: v('graveyardSalesVolume'),
      previous_day_receipt_count: v('previousDayReceiptCount'),
      previous_day_sales_volume: v('previousDaySalesVolume'),
      weekly_average_receipts: v('weeklyAverageReceipts'),
      weekly_average_sales: v('weeklyAverageSales'),
      transaction_drop_percentage: v('transactionDropPercentage'),
      sales_drop_percentage: v('salesDropPercentage')
    };
    try {
      await api('churn_data.php?action=save', { method: 'POST', body: JSON.stringify(payload) });
      alert('Churn data saved.');
      await loadDashboard();
      await loadCharts();
    } catch (e) {
      alert('Save error: ' + e.message);
    }
  }
  function clearForm() {
    [
      'date', 'receiptCount', 'salesVolume', 'customerTraffic',
      'morningReceiptCount', 'swingReceiptCount', 'graveyardReceiptCount',
      'morningSalesVolume', 'swingSalesVolume', 'graveyardSalesVolume',
      'previousDayReceiptCount', 'previousDaySalesVolume',
      'weeklyAverageReceipts', 'weeklyAverageSales',
      'transactionDropPercentage', 'salesDropPercentage'
    ].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    alert('All fields cleared.');
  }
  window.saveChurnData = saveChurnData;
  window.clearForm = clearForm;

  /* -------------------- charts (Chart.js) -------------------- */
  let charts = { traffic: null, churn: null, revenue: null };
  function destroyChart(c) { try { c && typeof c.destroy === 'function' && c.destroy(); } catch {} }
  function ensureCanvasMinH(id) { const c = $('#' + id); if (c && c.clientHeight < 180) c.style.minHeight = '300px'; }

  async function loadTraffic(period) {
    const select = $('#trafficPeriod');
    const chosen = period || (select ? select.value : 'today') || 'today';
    const j = await api(`traffic_data.php?period=${encodeURIComponent(chosen)}`);
    const labels = j.labels || j.hours || [];
    const values = j.values || j.counts || j.data || [];

    // also hydrate monitoring numbers if present
    const totalTodayEl = $('#totalCustomersToday');
    const peakEl       = $('#peakHourTraffic');
    const trendEl      = $('#trafficTrend');
    if (totalTodayEl) totalTodayEl.textContent = String(j.totalToday ?? j.total ?? values.reduce((a, b) => a + Number(b || 0), 0));
    if (peakEl)       peakEl.textContent       = String(j.peakHourTraffic ?? j.peak ?? 0);
    if (trendEl)      trendEl.textContent      = `${(j.trendPct ?? j.trend ?? 0) >= 0 ? '+' : ''}${String(j.trendPct ?? j.trend ?? 0)}%`;

    const ctx = $('#trafficChart');
    if (!ctx || !window.Chart) return;

    ensureCanvasMinH('trafficChart');
    destroyChart(charts.traffic);
    charts.traffic = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Customers', data: values, fill: true, tension: 0.35 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  }
  async function loadChurnDistribution() {
    const data = await apiTry(['churn_distribution.php', 'churn_risk.php?action=distribution']);
    const low    = Number(data.low    ?? data.LOW    ?? 0);
    const medium = Number(data.medium ?? data.MEDIUM ?? 0);
    const high   = Number(data.high   ?? data.HIGH   ?? 0);

    const ctx = $('#churnChart');
    if (!ctx || !window.Chart) return;

    ensureCanvasMinH('churnChart');
    destroyChart(charts.churn);
    charts.churn = new Chart(ctx, {
      type: 'doughnut',
      data: { labels: ['Low', 'Medium', 'High'], datasets: [{ data: [low, medium, high] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
    });
  }
  async function loadRevenueByCategory() {
    const data = await apiTry(['revenue_by_category.php', 'dashboard.php?action=revenue_by_category']);
    const cats = data.categories || data.labels || [];
    const vals = data.values || data.revenue || data.data || [];

    const ctx = $('#revenueChart');
    if (!ctx || !window.Chart) return;

    ensureCanvasMinH('revenueChart');
    destroyChart(charts.revenue);
    charts.revenue = new Chart(ctx, {
      type: 'bar',
      data: { labels: cats, datasets: [{ label: 'Revenue', data: vals }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  }
  async function loadCharts() {
    try { await loadTraffic(); } catch (e) { console.warn('[traffic chart]', e.message); }
    try { await loadChurnDistribution(); } catch (e) { console.warn('[churn chart]', e.message); }
    try { await loadRevenueByCategory(); } catch (e) { console.warn('[revenue chart]', e.message); }
  }
  async function updateTrafficChart() { try { await loadTraffic(); } catch (e) { console.warn('[updateTrafficChart]', e.message); } }
  window.updateTrafficChart = updateTrafficChart;

  /* -------------------- customer insights -------------------- */
  async function loadCustomerInsights() {
    try {
      const j = await apiTry(['customer_insights.php', 'reports/behavior_report.php']);
      const loyal = $('#loyalCustomers');
      const avgPv = $('#avgPurchaseValue');
      const segP  = $('#segmentationPatterns');
      const purP  = $('#purchasePatterns');
      const riskI = $('#riskIndicators');

      loyal && (loyal.textContent = String(j.loyalCustomers ?? j.loyal ?? 0));
      avgPv && (avgPv.textContent = peso(j.avgPurchaseValue ?? j.avgValue ?? 0));

      if (segP) {
        const items = j.segmentation || j.segments || [];
        segP.innerHTML = (Array.isArray(items) && items.length)
          ? items.map(t => `<span class="risk-factor-tag">${String(t)}</span>`).join('')
          : '<span class="risk-factor-tag">No segments available</span>';
      }
      if (purP) {
        const items = j.purchasePatterns || j.behavior || [];
        purP.innerHTML = (Array.isArray(items) && items.length)
          ? items.map(t => `<span class="risk-factor-tag">${String(t)}</span>`).join('')
          : '<span class="risk-factor-tag">No behavior patterns</span>';
      }
      if (riskI) {
        // If backend returns risk summarized from churn predictions
        const items = j.riskIndicators || j.indicators || [];
        const header = (j.riskLevel || j.level || '—') + ' risk';
        const perc   = j.riskPercentage ?? j.percentage ?? null;
        const summaryRow = `<div class="pattern-item"><div class="pattern-icon"><i class="fas fa-thermometer-half"></i></div><div class="pattern-text"><strong>${header}${perc!=null ? ' ('+Math.round(Number(perc))+ '%)' : ''}</strong>${j.riskSummary ? ' — ' + j.riskSummary : ''}</div></div>`;
        riskI.innerHTML = summaryRow + ((Array.isArray(items) && items.length)
          ? `<div class="pattern-item"><div class="pattern-icon"><i class="fas fa-list-ul"></i></div><div class="pattern-text"><strong>Top factors:</strong> ${items.map(String).join(', ')}</div></div>`
          : '');
      }
    } catch (e) {
      console.warn('[Customer insights]', e.message);
    }
  }
  window.loadCustomerInsights = loadCustomerInsights;

  /* -------------------- customer monitoring (FIXED) -------------------- */
  async function loadCustomerMonitoring() {
    try {
      // Your provided endpoint computes: customersToday, peakHour{label,value}, trafficTrendPercent, atRiskCustomers
      const j = await api('customer_monitoring.php');

      // Customers Today
      const today = Number(j.customersToday ?? j.totalCustomersToday ?? j.total ?? 0);
      $('#totalCustomersToday') && ($('#totalCustomersToday').textContent = String(today));

      // Peak Hour
      const peakVal = (j.peakHour && typeof j.peakHour === 'object') ? (j.peakHour.value ?? 0) : (j.peakHourTraffic ?? j.peak ?? 0);
      $('#peakHourTraffic') && ($('#peakHourTraffic').textContent = String(peakVal || 0));

      // Trend vs yesterday
      const trend = Number(j.trafficTrendPercent ?? j.trendPct ?? j.trend ?? 0);
      $('#trafficTrend') && ($('#trafficTrend').textContent = `${trend >= 0 ? '+' : ''}${trend.toFixed(2)}%`);

      // At-Risk Customers (derived from latest risk_score percentage)
      const atRisk = Number(j.atRiskCustomers ?? 0);
      $('#atRiskCustomers') && ($('#atRiskCustomers').textContent = String(atRisk));
    } catch (e) {
      console.warn('[Customer monitoring]', e.message);
      // Keep UI stable (don’t overwrite with zeros on error)
    }
  }
  window.loadCustomerMonitoring = loadCustomerMonitoring;

  /* -------------------- reports -------------------- */
  async function generateRetentionReport() {
    try {
      const j = await apiTry(['reports/retention_report.php', 'reports/retention_report.php']);
      const set = (id, val) => { const el = $('#' + id); if (el) el.textContent = val; };
      set('previewRetention', pct(j.retentionRate || 0, 2));
      set('previewChurn', pct(j.churnRate || 0, 2));
      set('previewAtRisk', String(j.atRiskCount || 0));
      alert('Retention report ready.');
    } catch (e) {
      alert('Retention report error: ' + e.message);
    }
  }
  async function generateRevenueReport() {
    try {
      const j = await apiTry(['reports/revenue_report.php', 'reports/revenue_report.php']);
      const set = (id, val) => { const el = $('#' + id); if (el) el.textContent = val; };
      set('previewRevenueSaved', peso(j.revenueSaved || 0));
      set('previewCLV', peso(j.clvImpact || 0));
      set('previewROI', pct(j.roi || 0, 2));
      alert('Revenue report ready.');
    } catch (e) {
      alert('Revenue report error: ' + e.message);
    }
  }
  async function generateBehaviorReport() {
    try {
      const j = await apiTry(['reports/behavior_report.php', 'reports/behavior_report.php']);
      const set = (id, val) => { const el = $('#' + id); if (el) el.textContent = val; };
      set('previewFrequency', String(Number(j.avgFrequency || 0).toFixed(0)));
      set('previewValue', peso(j.avgValue || 0));
      set('previewLoyalty', pct(j.loyaltyRate || 0, 2));
      alert('Behavior report ready.');
    } catch (e) {
      alert('Behavior report error: ' + e.message);
    }
  }
  window.generateRetentionReport = generateRetentionReport;
  window.generateRevenueReport   = generateRevenueReport;
  window.generateBehaviorReport  = generateBehaviorReport;

  /* -------------------- profile & auth history -------------------- */
  async function loadProfile() {
    try {
      const r = await api('profile.php?action=me');
      const u = r.user || {};
      $('#profileAvatar') && ($('#profileAvatar').src = u.avatar_url || u.icon || 'uploads/avatars/default-icon.png');
      $('#profileName')   && ($('#profileName').textContent = `${u.firstname || ''} ${u.lastname || ''}`.trim() || '—');
      $('#profileRole')   && ($('#profileRole').textContent = u.role || 'Store Manager');

      $('#profileFirstName') && ($('#profileFirstName').value = u.firstname || '');
      $('#profileLastName')  && ($('#profileLastName').value  = u.lastname  || '');
      $('#profileEmail')     && ($('#profileEmail').value     = u.email     || '');
      $('#profileCompany')   && ($('#profileCompany').value   = u.company   || '');

      const tf = $('#twoFactorToggle'); if (tf) tf.checked = (parseInt(u.two_factor_enabled, 10) === 1);

      await refreshLoginHistory();
    } catch (e) {
      console.error('[Profile]', e.message);
    }
  }
  async function refreshLoginHistory() {
    try {
      const r = await api('login_history.php');
      const list = r.items || r.history || [];
      const tbody = $('#loginHistoryTable'); if (!tbody) return;
      if (!Array.isArray(list) || !list.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No history yet</td></tr>';
        return;
      }
      tbody.innerHTML = list.map(row => {
        const when = row.accessed_at || row.event_time || row.datetime || '';
        const ok   = String(row.status || '').toLowerCase() === 'success';
        const badge = ok
          ? '<span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:4px 8px;border-radius:999px;font-weight:700;font-size:.8rem;">Success</span>'
          : '<span style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:4px 8px;border-radius:999px;font-weight:700;font-size:.8rem;">Failed</span>';
        return `<tr>
          <td>${when || ''}</td>
          <td>${row.location || ''}</td>
          <td>${row.device || ''}</td>
          <td>${row.ip || row.ip_address || ''}</td>
          <td>${badge}</td>
        </tr>`;
      }).join('');
    } catch (e) {
      console.error('[Login history]', e.message);
    }
  }
  window.refreshLoginHistory = refreshLoginHistory;
  window.updateProfile = async function () {
    try {
      const payload = {
        firstname: $('#profileFirstName')?.value?.trim() || '',
        lastname:  $('#profileLastName')?.value?.trim()  || '',
        email:     $('#profileEmail')?.value?.trim()     || '',
        company:   $('#profileCompany')?.value?.trim()   || ''
      };
      await api('profile.php?action=update_profile', { method: 'POST', body: JSON.stringify(payload) });
      alert('Profile updated');
      await loadProfile();
    } catch (e) { alert(e.message); }
  };
  window.changePassword = async function () {
    try {
      await api('profile.php?action=change_password', {
        method: 'POST',
        body: JSON.stringify({
          current_password: $('#currentPassword')?.value || '',
          new_password:     $('#newPassword')?.value     || '',
          confirm_password: $('#confirmNewPassword')?.value || ''
        })
      });
      alert('Password changed');
      $('.security-form')?.reset();
    } catch (e) { alert(e.message); }
  };
  window.toggle2FA = async function (enabled) {
    try {
      const fd = new FormData();
      fd.append('enabled', enabled ? '1' : '0');
      await api('profile.php?action=toggle_2fa', { method: 'POST', body: fd });
    } catch (e) {
      alert(e.message);
      const tf = $('#twoFactorToggle'); if (tf) tf.checked = !enabled;
    }
  };
  window.uploadAvatar = function () {
    const inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/png,image/jpeg,image/webp';
    inp.onchange = async () => {
      if (!inp.files || !inp.files[0]) return;
      const fd = new FormData(); fd.append('avatar', inp.files[0]);
      try {
        const res = await fetch(apiPath('profile.php?action=upload_avatar'), {
          method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() }
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.message || 'Upload failed');
        $('#profileAvatar') && ($('#profileAvatar').src = j.avatar_url);
        alert('Avatar updated');
      } catch (e) { alert(e.message); }
    };
    inp.click();
  };

  /* -------------------- settings -------------------- */
  window.updateRefreshInterval = async function (val) {
    try { await api('settings_update.php', { method: 'POST', body: JSON.stringify({ refresh_interval: String(val || '6') }) }); }
    catch (e) { console.warn('[Settings refresh_interval]', e.message); }
  };
  window.toggleDarkMode = async function (checked) {
    try {
      await api('settings_update.php', { method: 'POST', body: JSON.stringify({ dark_mode: checked ? 1 : 0 }) });
      document.documentElement.classList.toggle('dark', !!checked);
    } catch (e) { console.warn('[Settings dark_mode]', e.message); }
  };
  async function loadInitialSettings() {
    try {
      const j = await api('get_profile_settings.php');
      if (typeof j.dark_mode !== 'undefined') {
        const dm = !!Number(j.dark_mode);
        $('#darkModeToggle') && ($('#darkModeToggle').checked = dm);
        document.documentElement.classList.toggle('dark', dm);
      }
      if (typeof j.refresh_interval !== 'undefined') {
        $('#refreshInterval') && ($('#refreshInterval').value = Number(j.refresh_interval || 6));
      }
    } catch { /* non-blocking */ }
  }

  /* -------------------- boot -------------------- */
  document.addEventListener('DOMContentLoaded', async () => {
    wireSidebarClicks();
    $('#trafficPeriod') && $('#trafficPeriod').addEventListener('change', updateTrafficChart);
    showPage('dashboard');

    await Promise.allSettled([
      loadDashboard(),
      loadCharts(),
      loadChurnRisk(),
      loadCustomerInsights(),
      loadCustomerMonitoring(),
      loadProfile(),
      loadInitialSettings()
    ]);
  });
})();
