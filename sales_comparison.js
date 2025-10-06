// Chart.js instances
let salesTrendChart = null;
let targetAchievementChart = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTodaySummary();
    loadTargets();
    initCharts();
    setupFilterButtons();
    setDefaultDates();
});

// Load today's summary
async function loadTodaySummary() {
    try {
        const response = await fetch('api/sales_comparison.php?action=today_summary');
        const data = await response.json();
        
        if (data.success !== false) {
            document.getElementById('todaySales').textContent = '₱' + formatNumber(data.sales);
            document.getElementById('todayTraffic').textContent = formatNumber(data.traffic);
            document.getElementById('todayTransactions').textContent = formatNumber(data.transactions);
            
            updateChange('salesChange', data.sales_change);
            updateChange('trafficChange', data.traffic_change);
            updateChange('transactionsChange', data.transactions_change);
        }
    } catch (error) {
        console.error('Error loading summary:', error);
    }
}

// Update change indicators
function updateChange(elementId, value) {
    const element = document.getElementById(elementId);
    const isPositive = value >= 0;
    element.textContent = (isPositive ? '+' : '') + value.toFixed(1) + '%';
    element.className = 'kpi-change ' + (isPositive ? 'positive' : 'negative');
}

// Load and display targets
async function loadTargets() {
    try {
        const response = await fetch('api/targets.php?action=list');
        const data = await response.json();
        
        if (data.targets) {
            displayTargets(data.targets);
            updateTargetKPI(data.targets);
            updateTargetChart(data.targets);
        }
    } catch (error) {
        console.error('Error loading targets:', error);
    }
}

// Display targets in table
function displayTargets(targets) {
    const tbody = document.getElementById('targetsTableBody');
    
    if (targets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No targets set</td></tr>';
        return;
    }
    
    tbody.innerHTML = targets.map(target => `
        <tr>
            <td>${target.target_name}</td>
            <td>${formatTargetType(target.target_type)}</td>
            <td>${formatDate(target.start_date)} - ${formatDate(target.end_date)}</td>
            <td>${formatValue(target.target_value, target.target_type)}</td>
            <td>${formatValue(target.current_value, target.target_type)}</td>
            <td>
                <div class="progress-bar">
                    <div class="progress-fill ${getProgressClass(target.progress)}" 
                         style="width: ${Math.min(target.progress, 100)}%"></div>
                </div>
                ${target.progress.toFixed(1)}%
            </td>
            <td><span class="status-badge status-${target.status}">${target.status}</span></td>
            <td>
                <button class="action-btn edit" onclick="editTarget(${target.id})">Edit</button>
                <button class="action-btn delete" onclick="deleteTarget(${target.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

// Update target KPI card
function updateTargetKPI(targets) {
    if (targets.length === 0) return;
    
    const avgProgress = targets.reduce((sum, t) => sum + t.progress, 0) / targets.length;
    const achieved = targets.filter(t => t.status === 'achieved').length;
    
    document.getElementById('targetProgress').textContent = avgProgress.toFixed(1) + '%';
    document.getElementById('targetStatus').textContent = `${achieved} of ${targets.length} achieved`;
}

// Run comparison
async function runComparison() {
    const currentDate = document.getElementById('currentDate').value;
    const compareDate = document.getElementById('compareDate').value;
    
    if (!currentDate || !compareDate) {
        alert('Please select both dates');
        return;
    }
    
    try {
        const response = await fetch(
            `api/sales_comparison.php?action=compare&current_date=${currentDate}&compare_date=${compareDate}`
        );
        const data = await response.json();
        
        if (data.success) {
            displayComparison(data.metrics);
            closeComparisonModal();
        }
    } catch (error) {
        console.error('Error running comparison:', error);
        alert('Failed to compare data');
    }
}

// Display comparison results
function displayComparison(metrics) {
    const tbody = document.getElementById('comparisonTableBody');
    
    tbody.innerHTML = metrics.map(m => `
        <tr>
            <td><strong>${m.metric}</strong></td>
            <td>${formatValue(m.current, m.format)}</td>
            <td>${formatValue(m.compare, m.format)}</td>
            <td>${formatValue(m.difference, m.format)}</td>
            <td class="${m.percentage >= 0 ? 'text-success' : 'text-danger'}">
                ${m.percentage >= 0 ? '+' : ''}${m.percentage}%
            </td>
            <td>
                <span class="trend-${m.trend}">
                    ${m.trend === 'up' ? '▲' : '▼'}
                </span>
            </td>
        </tr>
    `).join('');
}

// Save target
async function saveTarget() {
    const id = document.getElementById('targetId').value;
    const data = {
        action: 'save',
        id: id || null,
        target_name: document.getElementById('targetName').value,
        target_type: document.getElementById('targetType').value,
        target_value: document.getElementById('targetValue').value,
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value,
        store: document.getElementById('targetStore').value
    };
    
    try {
        const response = await fetch('api/targets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeTargetModal();
            loadTargets();
        } else {
            alert(result.error || 'Failed to save target');
        }
    } catch (error) {
        console.error('Error saving target:', error);
        alert('Failed to save target');
    }
}

// Edit target
async function editTarget(id) {
    try {
        const response = await fetch('api/targets.php?action=list');
        const data = await response.json();
        const target = data.targets.find(t => t.id == id);
        
        if (target) {
            document.getElementById('targetId').value = target.id;
            document.getElementById('targetName').value = target.target_name;
            document.getElementById('targetType').value = target.target_type;
            document.getElementById('targetValue').value = target.target_value;
            document.getElementById('startDate').value = target.start_date;
            document.getElementById('endDate').value = target.end_date;
            document.getElementById('targetStore').value = target.store || '';
            document.getElementById('targetModalTitle').textContent = 'Edit Target';
            openTargetModal();
        }
    } catch (error) {
        console.error('Error loading target:', error);
    }
}

// Delete target
async function deleteTarget(id) {
    if (!confirm('Are you sure you want to delete this target?')) return;
    
    try {
        const response = await fetch(`api/targets.php?action=delete&id=${id}`, {
            method: 'POST'
        });
        const result = await response.json();
        
        if (result.success) {
            loadTargets();
        }
    } catch (error) {
        console.error('Error deleting target:', error);
    }
}

// Initialize charts
async function initCharts() {
    try {
        const response = await fetch('api/sales_comparison.php?action=trend_data&days=7');
        const data = await response.json();
        
        if (data.data) {
            createSalesTrendChart(data.data);
        }
    } catch (error) {
        console.error('Error loading chart data:', error);
    }
}

// Create sales trend chart
function createSalesTrendChart(data) {
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    
    if (salesTrendChart) salesTrendChart.destroy();
    
    salesTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => formatDate(d.date)),
            datasets: [{
                label: 'Sales Volume',
                data: data.map(d => d.sales_volume),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// Update target achievement chart
function updateTargetChart(targets) {
    const ctx = document.getElementById('targetAchievementChart').getContext('2d');
    
    if (targetAchievementChart) targetAchievementChart.destroy();
    
    const achieved = targets.filter(t => t.status === 'achieved').length;
    const near = targets.filter(t => t.status === 'near').length;
    const below = targets.filter(t => t.status === 'below').length;
    
    targetAchievementChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Achieved', 'Near Target', 'Below Target'],
            datasets: [{
                data: [achieved, near, below],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Setup filter buttons
function setupFilterButtons() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            applyQuickFilter(filter);
        });
    });
}

// Apply quick filters
function applyQuickFilter(filter) {
    const today = new Date();
    let compareDate;
    
    switch(filter) {
        case 'today':
            compareDate = new Date(today);
            compareDate.setDate(today.getDate() - 1);
            break;
        case 'week':
            compareDate = new Date(today);
            compareDate.setDate(today.getDate() - 7);
            break;
        case 'month':
            compareDate = new Date(today);
            compareDate.setMonth(today.getMonth() - 1);
            break;
        case 'custom':
            openComparisonModal();
            return;
    }
    
    if (compareDate) {
        document.getElementById('currentDate').value = formatDateInput(today);
        document.getElementById('compareDate').value = formatDateInput(compareDate);
        runComparison();
    }
}

// Modal functions
function openComparisonModal() {
    document.getElementById('comparisonModal').style.display = 'block';
}

function closeComparisonModal() {
    document.getElementById('comparisonModal').style.display = 'none';
}

function openTargetModal() {
    document.getElementById('targetModalTitle').textContent = 'Set New Target';
    document.getElementById('targetId').value = '';
    document.getElementById('targetName').value = '';
    document.getElementById('targetValue').value = '';
    document.getElementById('targetStore').value = '';
    document.getElementById('targetModal').style.display = 'block';
}

function closeTargetModal() {
    document.getElementById('targetModal').style.display = 'none';
}

// Utility functions
function formatNumber(num) {
    return new Intl.NumberFormat('en-PH').format(num);
}

function formatValue(value, format) {
    if (format === 'currency') {
        return '₱' + formatNumber(value.toFixed(2));
    }
    return formatNumber(Math.round(value));
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatDateInput(date) {
    return date.toISOString().split('T')[0];
}

function formatTargetType(type) {
    const types = {
        'sales': 'Sales',
        'customers': 'Customers',
        'transactions': 'Transactions',
        'avg_transaction': 'Avg Transaction'
    };
    return types[type] || type;
}

function getProgressClass(progress) {
    if (progress >= 75) return 'high';
    if (progress >= 50) return 'medium';
    return 'low';
}

function setDefaultDates() {
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    
    document.getElementById('currentDate').value = formatDateInput(today);
    document.getElementById('compareDate').value = formatDateInput(yesterday);
    document.getElementById('startDate').value = formatDateInput(today);
    document.getElementById('endDate').value = formatDateInput(new Date(today.setMonth(today.getMonth() + 1)));
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}