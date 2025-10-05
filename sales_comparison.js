// Sales Comparison & Target Tracking JavaScript

let comparisonChart = null;
let targetChart = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setDefaultDates();
    loadKPISummary();
    loadTargets();
    initializeCharts();
});

// Set default dates
function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
    
    document.getElementById('currentDate').value = today;
    document.getElementById('compareDate').value = yesterday;
}

// Update comparison dates based on type
function updateComparisonDates() {
    const type = document.getElementById('comparisonType').value;
    const currentDate = document.getElementById('currentDate');
    const compareDate = document.getElementById('compareDate');
    
    const today = new Date();
    currentDate.value = today.toISOString().split('T')[0];
    
    switch(type) {
        case 'today_vs_date':
            compareDate.value = new Date(today - 86400000).toISOString().split('T')[0];
            break;
        case 'week_vs_range':
            compareDate.value = new Date(today - 604800000).toISOString().split('T')[0];
            break;
        case 'month_vs_period':
            compareDate.value = new Date(today - 2592000000).toISOString().split('T')[0];
            break;
    }
}

// Load KPI Summary
async function loadKPISummary() {
    try {
        const response = await fetch('api/sales_comparison.php?action=kpi_summary');
        const data = await response.json();
        
        if (data.error) {
            console.error('Error loading KPI:', data.error);
            return;
        }
        
        // Update KPI cards
        document.getElementById('todaySales').textContent = formatCurrency(data.today_sales);
        updateChangeIndicator('salesChange', data.sales_change);
        
        document.getElementById('todayCustomers').textContent = formatNumber(data.today_customers);
        updateChangeIndicator('customersChange', data.customers_change);
        
        document.getElementById('todayTransactions').textContent = formatNumber(data.today_transactions);
        updateChangeIndicator('transactionsChange', data.transactions_change);
        
        document.getElementById('targetAchievement').textContent = data.target_achievement.toFixed(1) + '%';
        document.getElementById('targetStatus').textContent = data.target_status;
        
    } catch (error) {
        console.error('Error loading KPI summary:', error);
    }
}

// Update change indicator
function updateChangeIndicator(elementId, value) {
    const element = document.getElementById(elementId);
    const sign = value >= 0 ? '+' : '';
    element.textContent = sign + value.toFixed(1) + '%';
    element.className = 'kpi-change ' + (value >= 0 ? 'positive' : 'negative');
}

// Load comparison data
async function loadComparison() {
    const currentDate = document.getElementById('currentDate').value;
    const compareDate = document.getElementById('compareDate').value;
    
    if (!currentDate || !compareDate) {
        alert('Please select both dates');
        return;
    }
    
    try {
        const response = await fetch(
            `api/sales_comparison.php?action=compare&currentDate=${currentDate}&compareDate=${compareDate}`
        );
        const data = await response.json();
        
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }
        
        displayComparisonResults(data.comparison);
        
    } catch (error) {
        console.error('Error loading comparison:', error);
        alert('Failed to load comparison data');
    }
}

// Display comparison results
function displayComparisonResults(comparison) {
    const tbody = document.getElementById('comparisonTableBody');
    tbody.innerHTML = '';
    
    comparison.forEach(item => {
        const row = document.createElement('tr');
        
        const trendIcon = item.trend === 'up' ? '▲' : '▼';
        const trendClass = item.trend === 'up' ? 'trend-up' : 'trend-down';
        
        const isCurrency = item.metric.includes('Sales') || item.metric.includes('Value');
        
        row.innerHTML = `
            <td><strong>${item.metric}</strong></td>
            <td>${isCurrency ? formatCurrency(item.current) : formatNumber(item.current)}</td>
            <td>${isCurrency ? formatCurrency(item.compare) : formatNumber(item.compare)}</td>
            <td>${isCurrency ? formatCurrency(Math.abs(item.difference)) : formatNumber(Math.abs(item.difference))}</td>
            <td>${item.percentage >= 0 ? '+' : ''}${item.percentage.toFixed(2)}%</td>
            <td>
                <span class="trend-indicator ${trendClass}">${trendIcon}</span>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

// Load targets
async function loadTargets(filter = 'all') {
    try {
        const response = await fetch(`api/sales_comparison.php?action=get_targets&filter=${filter}`);
        const data = await response.json();
        
        if (data.error) {
            console.error('Error loading targets:', data.error);
            return;
        }
        
        displayTargets(data.targets);
        
    } catch (error) {
        console.error('Error loading targets:', error);
    }
}

// Display targets
function displayTargets(targets) {
    const tbody = document.getElementById('targetsTableBody');
    tbody.innerHTML = '';
    
    if (targets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;">No targets found. Create your first target to start tracking progress.</td></tr>';
        return;
    }
    
    targets.forEach(target => {
        const row = document.createElement('tr');
        
        const progressClass = target.progress >= 100 ? 'progress-achieved' : 
                            target.progress >= 80 ? 'progress-near' : 'progress-below';
        
        const statusClass = target.status === 'achieved' ? 'status-achieved' : 
                          target.status === 'near' ? 'status-near' : 'status-below';
        
        const statusText = target.status === 'achieved' ? 'Achieved' : 
                         target.status === 'near' ? 'Near Target' : 'Below Target';
        
        const targetValueFormatted = target.target_type === 'sales' || target.target_type === 'avg_transaction' 
            ? formatCurrency(target.target_value) 
            : formatNumber(target.target_value);
        
        const currentValueFormatted = target.target_type === 'sales' || target.target_type === 'avg_transaction' 
            ? formatCurrency(target.current_value) 
            : formatNumber(target.current_value);
        
        row.innerHTML = `
            <td><strong>${escapeHtml(target.target_name)}</strong></td>
            <td>${formatTargetType(target.target_type)}</td>
            <td>${formatDate(target.start_date)} - ${formatDate(target.end_date)}</td>
            <td>${targetValueFormatted}</td>
            <td>${currentValueFormatted}</td>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="progress-container" style="flex:1;">
                        <div class="progress-bar ${progressClass}" style="width:${Math.min(target.progress, 100)}%"></div>
                    </div>
                    <span style="font-weight:600;min-width:50px;">${target.progress.toFixed(1)}%</span>
                </div>
            </td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td>
                <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="editTarget(${target.id})">Edit</button>
                <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;background:#ef4444;color:#fff;" onclick="deleteTarget(${target.id})">Delete</button>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

// Filter targets
function filterTargets() {
    const filter = document.getElementById('targetFilter').value;
    loadTargets(filter);
}

// Open target modal
function openTargetModal() {
    document.getElementById('targetModal').classList.add('active');
    document.getElementById('targetForm').reset();
    
    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    const nextMonth = new Date(Date.now() + 2592000000).toISOString().split('T')[0];
    document.getElementById('targetStartDate').value = today;
    document.getElementById('targetEndDate').value = nextMonth;
}

// Close target modal
function closeTargetModal() {
    document.getElementById('targetModal').classList.remove('active');
}

// Save target
async function saveTarget(event) {
    event.preventDefault();
    
    const formData = {
        name: document.getElementById('targetName').value,
        type: document.getElementById('targetType').value,
        value: parseFloat(document.getElementById('targetValue').value),
        start_date: document.getElementById('targetStartDate').value,
        end_date: document.getElementById('targetEndDate').value,
        store: document.getElementById('targetStore').value
    };
    
    try {
        const response = await fetch('api/sales_comparison.php?action=save_target', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }
        
        closeTargetModal();
        loadTargets();
        alert('Target saved successfully!');
        
    } catch (error) {
        console.error('Error saving target:', error);
        alert('Failed to save target');
    }
}

// Delete target
async function deleteTarget(id) {
    if (!confirm('Are you sure you want to delete this target?')) {
        return;
    }
    
    try {
        const response = await fetch(`api/sales_comparison.php?action=delete_target&id=${id}`);
        const data = await response.json();
        
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }
        
        loadTargets();
        alert('Target deleted successfully!');
        
    } catch (error) {
        console.error('Error deleting target:', error);
        alert('Failed to delete target');
    }
}

// Initialize charts
function initializeCharts() {
    loadTrendChart();
    loadTargetChart();
}

// Load trend chart
async function loadTrendChart() {
    try {
        const response = await fetch('api/sales_comparison.php?action=trend_data&days=30');
        const data = await response.json();
        
        if (data.error || !data.trend_data) {
            return;
        }
        
        const ctx = document.getElementById('salesTrendChart');
        
        if (comparisonChart) {
            comparisonChart.destroy();
        }
        
        comparisonChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.trend_data.map(d => formatDate(d.date)),
                datasets: [{
                    label: 'Sales Revenue',
                    data: data.trend_data.map(d => d.sales_volume),
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
    } catch (error) {
        console.error('Error loading trend chart:', error);
    }
}

// Load target achievement chart
async function loadTargetChart() {
    try {
        const response = await fetch('api/sales_comparison.php?action=get_targets&filter=active');
        const data = await response.json();
        
        if (data.error || !data.targets || data.targets.length === 0) {
            return;
        }
        
        const ctx = document.getElementById('targetAchievementChart');
        
        if (targetChart) {
            targetChart.destroy();
        }
        
        targetChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.targets.map(t => t.target_name),
                datasets: [{
                    data: data.targets.map(t => Math.min(t.progress, 100)),
                    backgroundColor: [
                        '#4f46e5',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
    } catch (error) {
        console.error('Error loading target chart:', error);
    }
}

// Refresh all data
function refreshData() {
    loadKPISummary();
    loadComparison();
    loadTargets();
    initializeCharts();
}

// Export report
function exportReport() {
    alert('Export functionality will generate a PDF/Excel report with comparison and target data.');
    // Implementation depends on your backend export library
}

// Utility functions
function formatCurrency(value) {
    return '₱' + parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(value) {
    return parseInt(value).toLocaleString('en-PH');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTargetType(type) {
    const types = {
        'sales': 'Sales Revenue',
        'customers': 'Customer Traffic',
        'transactions': 'Transactions',
        'avg_transaction': 'Avg Transaction Value'
    };
    return types[type] || type;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Edit target (placeholder)
function editTarget(id) {
    alert('Edit functionality: Load target data and populate the modal form for editing.');
    // You can extend this to actually load and edit the target
}