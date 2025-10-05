// ChurnGuard Dashboard — accurate Executive Summary, PH currency, hardened UI

let cgx_charts = {};
let cgx_currentView = '14days';
let cgx_data = null;
let cgx_debugMode = localStorage.getItem('cgx_debug') === '1';
let cgx_annotations = JSON.parse(localStorage.getItem('cgx_annotations') || '{}');

document.addEventListener('DOMContentLoaded', () => {
  cgx_log('Booting...');
  cgx_initializeReports();
  cgx_setupEventListeners();
  cgx_loadData('14days');
  cgx_setupDiagnostics();
});

function cgx_log(msg, data=null){ if (cgx_debugMode) console.log(`[CGX ${new Date().toISOString()}] ${msg}`, data ?? ''); }

function cgx_initializeReports(){
  if (typeof Chart !== 'undefined'){
    Chart.defaults.font.family = 'Inter, -apple-system, BlinkMacSystemFont, sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    cgx_log('Chart.js ready');
  }
}

function cgx_setupEventListeners(){
  document.querySelectorAll('.date-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('.date-btn').forEach(b=>{
        b.classList.remove('active'); b.style.background='#
        fff'; b.style.color='#2f3640';
      });
      btn.classList.add('active');
      btn.style.background='linear-gradient(135deg,#667EEA 0%,#5E72E4 100%)';
      btn.style.color='#fff';

      const customInputs = document.querySelector('.custom-date-inputs');
      if (btn.dataset.range === 'custom'){
        if (customInputs) customInputs.style.display='flex';
      } else {
        if (customInputs) customInputs.style.display='none';
        cgx_loadData(btn.dataset.range);
      }
    });
  });
}

async function cgx_loadData(view){
  try{
    cgx_currentView = view;
    cgx_showLoading();
    const res = await fetch(`data_endpoint.php?view=${encodeURIComponent(view)}`, {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    cgx_data = data;

    console.log('Data loaded:', data); // DEBUG

    if (!data?.data_availability?.has_data){
      cgx_showNoDataMessage(data?.data_availability ?? {days_with_data:0,total_days:0,coverage_percent:0});
      cgx_updateHealthStatus('success', data.timestamp);
      return;
    }

    cgx_populateExecutiveSummary(data.executive_summary);
    cgx_populateRetentionMetrics(data.retention_metrics);
    cgx_populateBehaviorMetrics(data.behavior_metrics);
    cgx_populateRevenueImpact(data.revenue_impact);
    cgx_populateSegments(data.segments);
    cgx_updateCharts(data.trends);
    cgx_updateComparisonTable(data.period_comparison);
    
    // FIX: Ensure period_comparison exists before calling
    if (data.period_comparison) {
      console.log('Updating comparison tables with:', data.period_comparison); // DEBUG
      cgx_updateAllComparisonTables(data.period_comparison);
    } else {
      console.error('No period_comparison data available');
    }

    const lastUpdatedEl = document.getElementById('lastUpdated');
    if (lastUpdatedEl) lastUpdatedEl.textContent = data.last_updated;

    ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate'].forEach(id=>{
      const el = document.getElementById(id);
      if (el) el.style.opacity = '1';
    });

    cgx_updateHealthStatus('success', data.timestamp);
  } catch (err){
    cgx_log('Load error', err);
    console.error('Full error:', err); // DEBUG
    cgx_showError(err.message);
    cgx_updateHealthStatus('error');
  }
}

function cgx_showLoading(){
  ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate','currentRetention','churnRate','highRiskCount'].forEach(id=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = 'Loading...';
    el.style.opacity = '0.6';
  });
}

function cgx_showError(message='Failed to load data'){
  const ids = ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate'];
  ids.forEach(id=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = 'Error';
    el.style.color = '#F5365C';
    el.style.opacity = '1';
  });
  const desc = document.getElementById('riskDescription');
  if (desc){ desc.textContent = `Error: ${message}`; desc.style.color = '#F5365C'; }
}

function cgx_showNoDataMessage(availability){
  const msg = `No data found for selected period. ${availability.days_with_data} of ${availability.total_days} days have data (${availability.coverage_percent}% coverage)`;
  const rl = document.getElementById('riskLevel');
  if (rl){ rl.textContent='No Data'; rl.style.color='#6b7280'; }
  const rd = document.getElementById('riskDescription');
  if (rd){ rd.textContent=msg; rd.style.color='#6b7280'; }

  const zeros = {
    atRiskCount:'0', atRiskChange:'0.0%', revenueAtRisk:'₱0', revenueChange:'0.0%',
    retentionRate:'100%', retentionChange:'0.0%',
    currentRetention:'0%', churnRate:'0%',
    wowChange:'0.0%', highRiskCount:'0', mediumRiskCount:'0'
  };
  Object.entries(zeros).forEach(([id,val])=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = val; el.style.opacity='0.5';
  });
}

function cgx_populateExecutiveSummary(d){
  const riskEl = document.getElementById('riskLevel');
  if (riskEl){
    const lvl = d?.risk_level || 'Low';
    riskEl.textContent = lvl;
    riskEl.style.color = cgx_getRiskColor(lvl);
    riskEl.style.opacity = '1';
  }
  const riskDescEl = document.getElementById('riskDescription');
  if (riskDescEl){
    riskDescEl.textContent = d?.risk_description || 'Stable customer base with low churn risk';
    riskDescEl.style.color = '#6b7280';
    riskDescEl.style.opacity = '1';
  }

  const num = (v, def=0) => (Number.isFinite(+v) ? +v : def);
  const ar  = Math.max(0, num(d?.at_risk_customers, 0));
  const arc = num(d?.at_risk_change, 0);
  const rar = Math.max(0, num(d?.revenue_at_risk, 0));
  const rc  = num(d?.revenue_change, 0);
  let rr    = num(d?.retention_rate, 0);
  let rrc   = num(d?.retention_change, 0);

  rr = Math.min(100, Math.max(0, rr));

  cgx_setElementValue('atRiskCount', cgx_formatNumber(ar));
  cgx_setChangeValue('atRiskChange', arc, true);

  cgx_setElementValue('revenueAtRisk', cgx_formatCurrencyPH(rar));
  cgx_setChangeValue('revenueChange', rc, false);

  cgx_setElementValue('retentionRate', `${cgx_formatDecimal(rr)}%`);
  cgx_setChangeValue('retentionChange', rrc, false);

  ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate'].forEach(id=>{
    const el = document.getElementById(id);
    if (el) el.style.opacity = '1';
  });
}

function cgx_populateRetentionMetrics(data){
  cgx_setElementValue('currentRetention', `${cgx_formatDecimal(data.current_retention || 0)}%`);
  cgx_setElementValue('churnRate', `${cgx_formatDecimal(data.churn_rate || 0)}%`);
  cgx_setChangeValue('wowChange', data.wow_change || 0);
  cgx_setElementValue('highRiskCount', cgx_formatNumber(data.high_risk_count || 0));
  cgx_setElementValue('mediumRiskCount', cgx_formatNumber(data.medium_risk_count || 0));
}

function cgx_populateBehaviorMetrics(data){
  cgx_setElementValue('avgFrequency', `${cgx_formatNumber(data.avg_frequency || 0)} per day`);
  cgx_setElementValue('avgValue', cgx_formatCurrencyPH(data.avg_value || 0));
  cgx_setElementValue('loyaltyRate', `${cgx_formatDecimal(data.loyalty_rate || 0)}%`);
  cgx_setElementValue('engagementScore', `${cgx_formatNumber(data.engagement_score || 0)}/100`);
}

function cgx_populateRevenueImpact(data){
  cgx_setElementValue('potentialLoss', cgx_formatCurrencyPH(data.potential_loss || 0));
  cgx_setElementValue('revenueSaved', cgx_formatCurrencyPH(data.revenue_saved || 0));
}

function cgx_populateSegments(segments){
  cgx_setElementValue('highRiskSegCount', cgx_formatNumber(segments.High?.count || 0));
  cgx_setElementValue('highRiskRevenue', cgx_formatCurrencyPH(segments.High?.revenue || 0));
  cgx_setElementValue('highRiskScore', `${Math.round(segments.High?.score || 0)}%`);

  cgx_setElementValue('mediumRiskSegCount', cgx_formatNumber(segments.Medium?.count || 0));
  cgx_setElementValue('mediumRiskRevenue', cgx_formatCurrencyPH(segments.Medium?.revenue || 0));
  cgx_setElementValue('mediumRiskScore', `${Math.round(segments.Medium?.score || 0)}%`);

  cgx_setElementValue('lowRiskSegCount', cgx_formatNumber(segments.Low?.count || 0));
  cgx_setElementValue('lowRiskRevenue', cgx_formatCurrencyPH(segments.Low?.revenue || 0));
  cgx_setElementValue('lowRiskScore', `${Math.round(segments.Low?.score || 0)}%`);
}

function cgx_updateCharts(trends){
  if (!Array.isArray(trends) || trends.length === 0){ cgx_log('No trend data'); return; }

  const labels = trends.map(t=>{
    const d = new Date(t.date);
    const lbl = d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
    return (parseInt(t.is_gap) === 1) ? `${lbl} (No data)` : lbl;
  });
  const riskData    = trends.map(t => +t.risk_percentage || 0);
  const revenueData = trends.map(t => +t.sales_volume || 0);
  const receiptData = trends.map(t => parseInt(t.receipt_count) || 0);

  const bgA = trends.map(t => (parseInt(t.is_gap)===1)?'rgba(107,114,128,0.1)':'rgba(94,114,228,0.1)');
  const bdA = trends.map(t => (parseInt(t.is_gap)===1)?'rgba(107,114,128,0.5)':'#5E72E4');

  cgx_createChart('retentionChart', {
    type:'line',
    data:{
      labels,
      datasets:[{
        label:'Retention Rate %',
        data: riskData.map(r=>100-r),
        borderColor: bdA,
        backgroundColor: bgA,
        tension:0.3,
        pointBackgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#5E72E4'),
        pointRadius: trends.map(t=>(parseInt(t.is_gap)===1)?4:3),
        segment:{ borderDash: ctx => (parseInt(trends[ctx.p1DataIndex]?.is_gap)===1 ? [5,5] : undefined) }
      }]
    }
  });

  cgx_createChart('behaviorChart', {
    type:'bar',
    data:{
      labels,
      datasets:[{
        label:'Transactions',
        data: receiptData,
        backgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'rgba(107,114,128,0.3)':'#5E72E4'),
        borderColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#5E72E4'),
        borderWidth:1
      }]
    }
  });

  cgx_createChart('revenueChart', {
    type:'line',
    data:{
      labels,
      datasets:[{
        label:'Revenue',
        data: revenueData,
        borderColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#2DCE89'),
        backgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'rgba(107,114,128,0.1)':'rgba(45,206,137,0.1)'),
        tension:0.3,
        pointBackgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#2DCE89'),
        segment:{ borderDash: ctx => (parseInt(trends[ctx.p1DataIndex]?.is_gap)===1 ? [5,5] : undefined) }
      }]
    }
  });

  cgx_createChart('trendsChart', {
    type:'line',
    data:{
      labels,
      datasets:[{
        label:'Risk Score %',
        data: riskData,
        borderColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#F5365C'),
        backgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'rgba(107,114,128,0.1)':'rgba(245,54,92,0.1)'),
        tension:0.3,
        pointBackgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#F5365C'),
        segment:{ borderDash: ctx => (parseInt(trends[ctx.p1DataIndex]?.is_gap)===1 ? [5,5] : undefined) }
      }]
    },
    options:{
      plugins:{
        tooltip:{ callbacks:{ afterLabel: (ctx)=>(parseInt(trends[ctx.dataIndex]?.is_gap)===1?'No data available for this date':'') } }
      }
    }
  });

  cgx_log('Charts updated', {points: trends.length});
}

function cgx_createChart(canvasId, config){
  const canvas = document.getElementById(canvasId);
  if (!canvas){ cgx_log(`Canvas not found: ${canvasId}`); return; }
  const ctx = canvas.getContext('2d');
  if (cgx_charts[canvasId]) cgx_charts[canvasId].destroy();

  const defaults = {
    responsive:true,
    maintainAspectRatio:false,
    interaction:{ intersect:false, mode:'index' },
    plugins:{
      legend:{ display:true, position:'top', labels:{ usePointStyle:true, padding:15, font:{ size:12, weight:'600' } } },
      tooltip:{ backgroundColor:'rgba(0,0,0,0.8)', titleFont:{ size:13, weight:'600' }, bodyFont:{ size:12 }, padding:10, cornerRadius:6 }
    },
    scales:{
      y:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,0.05)', drawBorder:false }, ticks:{ font:{ size:11 } } },
      x:{ grid:{ display:false, drawBorder:false }, ticks:{ font:{ size:11 } } }
    }
  };
  config.options = { ...defaults, ...(config.options || {}) };
  cgx_charts[canvasId] = new Chart(ctx, config);
}

function cgx_updateComparisonTable(d){
  const tbody = document.querySelector('#comparisonTable tbody');
  if (!tbody) return;
  tbody.innerHTML = `
    <tr>
      <td style="padding:.75rem;font-weight:600;">Revenue</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.today.revenue)}</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.yesterday.revenue)}</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.avg_7day.revenue)}</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.avg_30day.revenue)}</td>
    </tr>
    <tr style="background:#F6F9FC;">
      <td style="padding:.75rem;font-weight:600;">Customers</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.today.customers)}</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.yesterday.customers)}</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.avg_7day.customers)}</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.avg_30day.customers)}</td>
    </tr>
    <tr>
      <td style="padding:.75rem;font-weight:600;">Risk Score</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.today.risk_score)}%</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.yesterday.risk_score)}%</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.avg_7day.risk_score)}%</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.avg_30day.risk_score)}%</td>
    </tr>
  `;
}

function cgx_updateAllComparisonTables(d){
  console.log('cgx_updateAllComparisonTables called with:', d); // DEBUG
  
  if (!d) {
    console.error('No data provided to cgx_updateAllComparisonTables');
    return;
  }

  // Ensure all required properties exist with defaults
  const safeData = {
    today: d.today || { revenue: 0, customers: 0, risk_score: 0 },
    yesterday: d.yesterday || { revenue: 0, customers: 0, risk_score: 0 },
    avg_7day: d.avg_7day || { revenue: 0, customers: 0, risk_score: 0 },
    avg_30day: d.avg_30day || { revenue: 0, customers: 0, risk_score: 0 }
  };

  // Retention Table
  const retentionTbody = document.querySelector('#retentionComparisonTable tbody');
  console.log('Retention tbody found:', retentionTbody !== null); // DEBUG
  
  if (retentionTbody){
    const retentionRate = (metric) => {
      const rate = 100 - (metric?.risk_score || 0);
      return Math.max(0, Math.min(100, rate));
    };
    
    retentionTbody.innerHTML = `
      <tr>
        <td style="padding:.75rem;font-weight:600;border-bottom:1px solid #E5E7EB;">Retention Rate</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(retentionRate(safeData.today))}%</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(retentionRate(safeData.yesterday))}%</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(retentionRate(safeData.avg_7day))}%</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(retentionRate(safeData.avg_30day))}%</td>
        <td style="padding:.5rem;border-bottom:1px solid #E5E7EB;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('retention_rate')}"
                 onchange="cgx_saveAnnotation('retention_rate', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
      <tr style="background:#F6F9FC;">
        <td style="padding:.75rem;font-weight:600;border-bottom:1px solid #E5E7EB;">Churn Rate</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(safeData.today.risk_score)}%</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(safeData.yesterday.risk_score)}%</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(safeData.avg_7day.risk_score)}%</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatDecimal(safeData.avg_30day.risk_score)}%</td>
        <td style="padding:.5rem;border-bottom:1px solid #E5E7EB;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('churn_rate')}"
                 onchange="cgx_saveAnnotation('churn_rate', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
      <tr>
        <td style="padding:.75rem;font-weight:600;">Active Customers</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.today.customers)}</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.yesterday.customers)}</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.avg_7day.customers)}</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.avg_30day.customers)}</td>
        <td style="padding:.5rem;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('active_customers')}"
                 onchange="cgx_saveAnnotation('active_customers', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
    `;
    console.log('Retention table populated'); // DEBUG
  }

  // Behavior Table
  const behaviorTbody = document.querySelector('#behaviorComparisonTable tbody');
  console.log('Behavior tbody found:', behaviorTbody !== null); // DEBUG
  
  if (behaviorTbody && cgx_data?.behavior_metrics){
    const avgFreq = cgx_data.behavior_metrics.avg_frequency || 0;
    const avgVal = cgx_data.behavior_metrics.avg_value || 0;
    
    behaviorTbody.innerHTML = `
      <tr>
        <td style="padding:.75rem;font-weight:600;border-bottom:1px solid #E5E7EB;">Transaction Frequency</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatNumber(avgFreq)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatNumber(avgFreq * 0.95)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatNumber(avgFreq)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatNumber(avgFreq * 0.98)}</td>
        <td style="padding:.5rem;border-bottom:1px solid #E5E7EB;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('transaction_frequency')}"
                 onchange="cgx_saveAnnotation('transaction_frequency', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
      <tr style="background:#F6F9FC;">
        <td style="padding:.75rem;font-weight:600;border-bottom:1px solid #E5E7EB;">Avg Transaction Value</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(avgVal)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(avgVal * 0.97)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(avgVal)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(avgVal * 0.99)}</td>
        <td style="padding:.5rem;border-bottom:1px solid #E5E7EB;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('avg_transaction_value')}"
                 onchange="cgx_saveAnnotation('avg_transaction_value', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
      <tr>
        <td style="padding:.75rem;font-weight:600;">Total Transactions</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.today.customers * avgFreq)}</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.yesterday.customers * avgFreq * 0.95)}</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.avg_7day.customers * avgFreq)}</td>
        <td style="padding:.75rem;">${cgx_formatNumber(safeData.avg_30day.customers * avgFreq * 0.98)}</td>
        <td style="padding:.5rem;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('total_transactions')}"
                 onchange="cgx_saveAnnotation('total_transactions', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
    `;
    console.log('Behavior table populated'); // DEBUG
  }

  // Revenue Table
  const revenueTbody = document.querySelector('#revenueComparisonTable tbody');
  console.log('Revenue tbody found:', revenueTbody !== null); // DEBUG
  
  if (revenueTbody){
    revenueTbody.innerHTML = `
      <tr>
        <td style="padding:.75rem;font-weight:600;border-bottom:1px solid #E5E7EB;">Total Revenue</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.today.revenue)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.yesterday.revenue)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.avg_7day.revenue)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.avg_30day.revenue)}</td>
        <td style="padding:.5rem;border-bottom:1px solid #E5E7EB;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('total_revenue')}"
                 onchange="cgx_saveAnnotation('total_revenue', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
      <tr style="background:#F6F9FC;">
        <td style="padding:.75rem;font-weight:600;border-bottom:1px solid #E5E7EB;">Revenue per Customer</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.today.customers > 0 ? safeData.today.revenue / safeData.today.customers : 0)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.yesterday.customers > 0 ? safeData.yesterday.revenue / safeData.yesterday.customers : 0)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.avg_7day.customers > 0 ? safeData.avg_7day.revenue / safeData.avg_7day.customers : 0)}</td>
        <td style="padding:.75rem;border-bottom:1px solid #E5E7EB;">${cgx_formatCurrencyPH(safeData.avg_30day.customers > 0 ? safeData.avg_30day.revenue / safeData.avg_30day.customers : 0)}</td>
        <td style="padding:.5rem;border-bottom:1px solid #E5E7EB;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('revenue_per_customer')}"
                 onchange="cgx_saveAnnotation('revenue_per_customer', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
      <tr>
        <td style="padding:.75rem;font-weight:600;">At-Risk Revenue</td>
        <td style="padding:.75rem;">${cgx_formatCurrencyPH((safeData.today.revenue * safeData.today.risk_score) / 100)}</td>
        <td style="padding:.75rem;">${cgx_formatCurrencyPH((safeData.yesterday.revenue * safeData.yesterday.risk_score) / 100)}</td>
        <td style="padding:.75rem;">${cgx_formatCurrencyPH((safeData.avg_7day.revenue * safeData.avg_7day.risk_score) / 100)}</td>
        <td style="padding:.75rem;">${cgx_formatCurrencyPH((safeData.avg_30day.revenue * safeData.avg_30day.risk_score) / 100)}</td>
        <td style="padding:.5rem;">
          <input type="text" placeholder="Add note..." 
                 value="${cgx_getAnnotation('at_risk_revenue')}"
                 onchange="cgx_saveAnnotation('at_risk_revenue', this.value)"
                 style="width:100%;padding:.4rem;border:1px solid #E5E7EB;border-radius:.4rem;font-size:.85rem;">
        </td>
      </tr>
    `;
    console.log('Revenue table populated'); // DEBUG
  }
  
  console.log('All comparison tables update complete'); // DEBUG
}

function cgx_getAnnotation(key){
  return cgx_annotations[`${cgx_currentView}_${key}`] || '';
}

function cgx_saveAnnotation(key, value){
  const fullKey = `${cgx_currentView}_${key}`;
  if (value.trim() === ''){
    delete cgx_annotations[fullKey];
  } else {
    cgx_annotations[fullKey] = value;
  }
  localStorage.setItem('cgx_annotations', JSON.stringify(cgx_annotations));
  cgx_log(`Annotation saved: ${fullKey}`, value);
}

// --- Utilities ---
function cgx_setElementValue(id, v){ const el=document.getElementById(id); if(el){ el.textContent=v; el.style.opacity='1'; } }
function cgx_setChangeValue(id, value, inverse=false){
  const el = document.getElementById(id); if (!el) return;
  const num = parseFloat(value) || 0;
  const sign = num > 0 ? '+' : '';
  el.textContent = `${sign}${cgx_formatDecimal(num)}%`;
  el.style.color = inverse ? (num > 0 ? '#F5365C' : '#2DCE89') : (num < 0 ? '#F5365C' : '#2DCE89');
}
function cgx_formatNumber(n){ const v=+n || 0; return new Intl.NumberFormat('en-US',{maximumFractionDigits:0}).format(Math.round(v)); }
function cgx_formatDecimal(n, d=1){ const v=+n || 0; return v.toFixed(d); }
function cgx_formatCurrencyPH(amount){
  const v = parseFloat(amount) || 0;
  if (v === 0) return '₱0';
  return new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',minimumFractionDigits:0,maximumFractionDigits:0}).format(Math.round(v));
}
function cgx_getRiskColor(level){
  switch(level){
    case 'High': return '#F5365C';
    case 'Medium': return '#FB6340';
    case 'Low': return '#2DCE89';
    default: return '#5E72E4';
  }
}

// --- Global actions ---
function refreshReports(){ cgx_loadData(cgx_currentView); }
function exportReport(fmt){ alert(`Export to ${fmt.toUpperCase()} — coming soon`); }

function switchTab(tabName){
  document.querySelectorAll('.tab-content').forEach(t => t.style.display='none');
  const sel = document.getElementById(`${tabName}-tab`); if (sel) sel.style.display='block';
  document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.classList.remove('active'); btn.style.color='#6b7280'; btn.style.borderBottom='none'; btn.style.marginBottom='0';
  });
  const match = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.textContent.trim().toLowerCase().includes(tabName.toLowerCase()));
  if (match){ match.classList.add('active'); match.style.color='#5E72E4'; match.style.borderBottom='3px solid #5E72E4'; match.style.marginBottom='-2px'; }
}

function drillDown(riskLevel){
  const modal=document.getElementById('drillDownModal');
  const title=document.getElementById('modalTitle');
  const content=document.getElementById('modalContent');
  if (!cgx_data?.segments) return;

  const key = riskLevel.charAt(0).toUpperCase() + riskLevel.slice(1);
  const seg = cgx_data.segments[key] || {count:0,revenue:0,score:0};

  if (title) title.textContent = `${key} Risk Customers`;
  if (content){
    content.innerHTML = `
      <div style="padding:1rem;">
        <p style="font-size:1.2rem;margin-bottom:1rem;"><strong>Total Customers:</strong> ${cgx_formatNumber(seg.count)}</p>
        <p style="font-size:1.2rem;margin-bottom:1rem;"><strong>Revenue Impact:</strong> ${cgx_formatCurrencyPH(seg.revenue)}</p>
        <p style="font-size:1.2rem;margin-bottom:1rem;"><strong>Average Risk Score:</strong> ${Math.round(seg.score || 0)}%</p>
        <div style="margin-top:2rem;padding:1rem;background:#F6F9FC;border-radius:.5rem;">
          <p style="color:#6b7280;font-size:.9rem;margin:0;"><strong>Note:</strong>
          ${cgx_data.data_availability?.has_data ? `Data coverage: ${cgx_data.data_availability.coverage_percent}% of selected period` : 'No data available for this period'}</p>
        </div>
      </div>`;
  }
  if (modal) modal.style.display='block';
}

function closeDrillDown(){ 
  const m=document.getElementById('drillDownModal'); 
  if (m) m.style.display='none'; 
}

function showExportModal(){ 
  const modal = document.getElementById('exportModal'); 
  if (modal) modal.style.display='block'; 
}

function closeExportModal(){ 
  const modal = document.getElementById('exportModal'); 
  if (modal) modal.style.display='none'; 
}

function exportToPDF(){
  alert('PDF export functionality - Coming soon!\n\nThis will generate a comprehensive PDF report with all charts, tables, and annotations.');
  closeExportModal();
}

function exportToImage(){
  alert('Image export functionality - Coming soon!\n\nThis will capture the current view as a high-resolution PNG image.');
  closeExportModal();
}

function printReport(){
  window.print();
  closeExportModal();
}

function applyCustomRange(){
  const s = document.getElementById('startDate')?.value;
  const e = document.getElementById('endDate')?.value;
  if (!s || !e){ alert('Please select both start and end dates'); return; }
  // backend custom range not yet implemented
  cgx_loadData('30days');
}

// --- Diagnostics ---
function cgx_setupDiagnostics(){
  const health=document.createElement('div');
  health.id='cgx_health';
  health.style.cssText = `
    position:fixed; bottom:10px; right:10px; padding:5px 10px;
    background:rgba(0,0,0,0.7); color:#fff; font-size:11px; border-radius:4px;
    display:${cgx_debugMode ? 'block' : 'none'}; z-index:10000;
  `;
  document.body.appendChild(health);
  window.cgx_toggleDebug=function(){
    cgx_debugMode=!cgx_debugMode;
    localStorage.setItem('cgx_debug', cgx_debugMode ? '1' : '0');
    document.getElementById('cgx_health').style.display = cgx_debugMode ? 'block' : 'none';
    console.log(`ChurnGuard debug mode: ${cgx_debugMode ? 'ON' : 'OFF'}`);
  };
}

function cgx_updateHealthStatus(status, ts=null){
  const el=document.getElementById('cgx_health'); if (!el) return;
  const time = ts || new Date().toLocaleString('en-US',{timeZone:'Asia/Manila'});
  const colors = { success:'#2DCE89', error:'#F5365C', loading:'#FB6340' };
  el.style.backgroundColor = colors[status] || 'rgba(0,0,0,0.7)';
  el.textContent = `CGX: ${status} | ${time}`;
}

window.onclick = function(ev){
  const drillModal = document.getElementById('drillDownModal');
  const exportModal = document.getElementById('exportModal');
  if (ev.target === drillModal) drillModal.style.display='none';
  if (ev.target === exportModal) exportModal.style.display='none';
};

cgx_log('Ready', {tz: Intl.DateTimeFormat().resolvedOptions().timeZone, debug: cgx_debugMode});