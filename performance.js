// performance.js - Dashboard Functionality

class PerformanceDashboard {
    constructor() {
        this.apiUrl = 'api/performance_tracker.php';
        this.charts = {};
        this.currentData = null;
        this.currentYear = new Date().getFullYear();
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.loadDashboardData();
        this.initCharts();
    }
    
    setupEventListeners() {
        // Menu toggle
        document.getElementById('menuToggle').addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Navigation items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });
        
        // Year selector
        document.getElementById('yearSelector').addEventListener('change', (e) => {
            this.currentYear = e.target.value;
            this.loadDashboardData();
        });
        
        // Refresh button
        document.getElementById('refreshData').addEventListener('click', () => {
            this.loadDashboardData();
        });
        
        // Save target buttons
        document.querySelectorAll('.save-target-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetType = e.currentTarget.dataset.target;
                this.saveTarget(targetType);
            });
        });
        
        // Export button
        document.getElementById('exportData').addEventListener('click', () => {
            this.exportData();
        });
        
        // Chart view buttons
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.updateChartView(e.target.dataset.view);
            });
        });
    }
    
    async loadDashboardData() {
        this.showLoading(true);
        
        try {
            const response = await fetch(`${this.apiUrl}?action=dashboard`);
            const result = await response.json();
            
            if (result.status === 'success') {
                this.currentData = result.data;
                this.updateMetrics(result.data);
                this.updateCharts(result.data);
                this.updateTable(result.data);
                this.updateProgress(result.data);
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
            this.showToast('Failed to load dashboard data', 'error');
        } finally {
            this.showLoading(false);
        }
    }
    
    updateMetrics(data) {
        const current = data.current_year;
        const previous = data.previous_year;
        const growth = data.growth;
        
        // Update revenue
        document.getElementById('totalRevenue').textContent = 
            this.formatCurrency(current.total_sales);
        document.getElementById('revenueComparison').textContent = 
            this.formatCurrency(previous.total_sales);
        this.updateTrend('salesTrend', growth.sales);
        
        // Update customers
        document.getElementById('totalCustomers').textContent = 
            this.formatNumber(current.total_customers);
        document.getElementById('newCustomers').textContent = 
            this.formatNumber(current.new_customers);
        this.updateTrend('customersTrend', growth.customers);
        
        // Update transactions
        document.getElementById('totalTransactions').textContent = 
            this.formatNumber(current.total_transactions);
        document.getElementById('avgTransaction').textContent = 
            this.formatCurrency(current.avg_transaction);
        
        // Update growth rate
        const avgGrowth = (growth.sales + growth.customers) / 2;
        document.getElementById('growthRate').textContent = avgGrowth.toFixed(1) + '%';
        document.getElementById('yoyGrowth').textContent = avgGrowth.toFixed(1) + '%';
        this.updateTrend('growthTrend', avgGrowth);
    }
    
    updateTrend(elementId, value) {
        const element = document.getElementById(elementId);
        const val = parseFloat(value) || 0;
        
        element.classList.remove('positive', 'negative', 'neutral');
        
        if (val > 0) {
            element.classList.add('positive');
            element.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M7 17l5-5 5 5M12 12V3"/>
                </svg>
                <span>+${val.toFixed(1)}%</span>
            `;
        } else if (val < 0) {
            element.classList.add('negative');
            element.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M7 7l5 5 5-5M12 12v9"/>
                </svg>
                <span>${val.toFixed(1)}%</span>
            `;
        } else {
            element.classList.add('neutral');
            element.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M5 12h14"/>
                </svg>
                <span>0%</span>
            `;
        }
    }
    
    updateProgress(data) {
        const progress = data.progress;
        
        // Sales progress
        const salesProgress = progress.sales;
        document.getElementById('salesProgress').style.width = salesProgress + '%';
        document.getElementById('salesProgressText').textContent = salesProgress.toFixed(1) + '%';
        
        // Customers progress
        const customersProgress = progress.customers;
        document.getElementById('customersProgress').style.width = customersProgress + '%';
        document.getElementById('customersProgressText').textContent = customersProgress.toFixed(1) + '%';
    }
    
    updateTable(data) {
        const current = data.current_year;
        const previous = data.previous_year;
        
        const metrics = [
            {
                name: 'Total Revenue',
                prev: previous.total_sales,
                curr: current.total_sales,
                format: 'currency'
            },
            {
                name: 'Total Customers',
                prev: previous.total_customers,
                curr: current.total_customers,
                format: 'number'
            },
            {
                name: 'New Customers',
                prev: previous.new_customers,
                curr: current.new_customers,
                format: 'number'
            },
            {
                name: 'Returning Customers',
                prev: previous.returning_customers,
                curr: current.returning_customers,
                format: 'number'
            },
            {
                name: 'Total Transactions',
                prev: previous.total_transactions,
                curr: current.total_transactions,
                format: 'number'
            },
            {
                name: 'Avg Transaction Value',
                prev: previous.avg_transaction,
                curr: current.avg_transaction,
                format: 'currency'
            }
        ];
        
        const tbody = document.getElementById('comparisonTableBody');
        tbody.innerHTML = metrics.map(metric => {
            const change = metric.curr - metric.prev;
            const growthPct = metric.prev > 0 ? ((change / metric.prev) * 100) : 0;
            
            const prevFormatted = metric.format === 'currency' ? 
                this.formatCurrency(metric.prev) : this.formatNumber(metric.prev);
            const currFormatted = metric.format === 'currency' ? 
                this.formatCurrency(metric.curr) : this.formatNumber(metric.curr);
            const changeFormatted = metric.format === 'currency' ? 
                this.formatCurrency(Math.abs(change)) : this.formatNumber(Math.abs(change));
            
            const trendClass = growthPct > 0 ? 'up' : (growthPct < 0 ? 'down' : 'neutral');
            const trendIcon = growthPct > 0 ? 
                '<path d="M7 17l5-5 5 5M12 12V3"/>' : 
                (growthPct < 0 ? '<path d="M7 7l5 5 5-5M12 12v9"/>' : '<path d="M5 12h14"/>');
            
            return `
                <tr>
                    <td><strong>${metric.name}</strong></td>
                    <td class="text-right">${prevFormatted}</td>
                    <td class="text-right"><strong>${currFormatted}</strong></td>
                    <td class="text-right">${change >= 0 ? '+' : '-'}${changeFormatted}</td>
                    <td class="text-right">
                        <span style="color: ${growthPct > 0 ? 'var(--success)' : (growthPct < 0 ? 'var(--danger)' : 'var(--gray)')}">
                            ${growthPct >= 0 ? '+' : ''}${growthPct.toFixed(1)}%
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="trend-icon ${trendClass}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                ${trendIcon}
                            </svg>
                        </span>
                    </td>
                </tr>
            `;
        }).join('');
    }
    
    initCharts() {
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        this.charts.revenue = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: { size: 14 },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '₱' + this.formatNumber(value)
                        }
                    }
                }
            }
        });
        
        // Customer Chart
        const customerCtx = document.getElementById('customerChart').getContext('2d');
        this.charts.customer = new Chart(customerCtx, {
            type: 'doughnut',
            data: {
                labels: ['New Customers', 'Returning Customers'],
                datasets: [{
                    data: [0, 0],
                    backgroundColor: [
                        'rgba(94, 114, 228, 0.8)',
                        'rgba(45, 206, 137, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    updateCharts(data) {
        if (!data.monthly) return;
        
        // Process monthly data
        const currentYearData = new Array(12).fill(0);
        const previousYearData = new Array(12).fill(0);
        
        data.monthly.forEach(item => {
            const monthIndex = item.month - 1;
            if (item.year == this.currentYear) {
                currentYearData[monthIndex] = item.total_sales;
            } else {
                previousYearData[monthIndex] = item.total_sales;
            }
        });
        
        // Update revenue chart
        this.charts.revenue.data.datasets = [
            {
                label: this.currentYear,
                data: currentYearData,
                borderColor: 'rgba(94, 114, 228, 1)',
                backgroundColor: 'rgba(94, 114, 228, 0.1)',
                borderWidth: 2,
                tension: 0.4
            },
            {
                label: this.currentYear - 1,
                data: previousYearData,
                borderColor: 'rgba(136, 152, 170, 1)',
                backgroundColor: 'rgba(136, 152, 170, 0.1)',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.4
            }
        ];
        this.charts.revenue.update();
        
        // Update customer chart
        const current = data.current_year;
        this.charts.customer.data.datasets[0].data = [
            current.new_customers,
            current.returning_customers
        ];
        this.charts.customer.update();
    }
    
    async saveTarget(targetType) {
        let value;
        let inputId;
        
        switch(targetType) {
            case 'sales':
                inputId = 'revenueTarget';
                break;
            case 'customers':
                inputId = 'customerTarget';
                break;
            case 'growth_rate':
                inputId = 'growthTarget';
                break;
        }
        
        value = document.getElementById(inputId).value;
        
        if (!value) {
            this.showToast('Please enter a target value', 'warning');
            return;
        }
        
        try {
            const response = await fetch(`${this.apiUrl}?action=targets`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    year: this.currentYear,
                    type: targetType,
                    value: value,
                    period: 'yearly'
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                this.showToast('Target saved successfully', 'success');
                this.loadDashboardData();
            } else {
                this.showToast('Failed to save target', 'error');
            }
        } catch (error) {
            console.error('Error saving target:', error);
            this.showToast('Failed to save target', 'error');
        }
    }
    
    exportData() {
        if (!this.currentData) return;
        
        // Create CSV content
        let csv = 'Metric,Previous Year,Current Year,Change,Growth %\n';
        
        const metrics = [
            ['Total Revenue', this.currentData.previous_year.total_sales, this.currentData.current_year.total_sales],
            ['Total Customers', this.currentData.previous_year.total_customers, this.currentData.current_year.total_customers],
            ['New Customers', this.currentData.previous_year.new_customers, this.currentData.current_year.new_customers],
            ['Returning Customers', this.currentData.previous_year.returning_customers, this.currentData.current_year.returning_customers],
            ['Total Transactions', this.currentData.previous_year.total_transactions, this.currentData.current_year.total_transactions],
            ['Avg Transaction Value', this.currentData.previous_year.avg_transaction, this.currentData.current_year.avg_transaction]
        ];
        
        metrics.forEach(([name, prev, curr]) => {
            const change = curr - prev;
            const growth = prev > 0 ? ((change / prev) * 100).toFixed(2) : 0;
            csv += `"${name}",${prev},${curr},${change},${growth}\n`;
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', `performance_report_${this.currentYear}.csv`);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        this.showToast('Data exported successfully', 'success');
    }
    
    showLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (show) {
            overlay.classList.add('active');
        } else {
            overlay.classList.remove('active');
        }
    }
    
    showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toastMessage');
        
        toastMessage.textContent = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    
    formatCurrency(value) {
        return '₱' + new Intl.NumberFormat('en-US').format(value || 0);
    }
    
    formatNumber(value) {
        return new Intl.NumberFormat('en-US').format(value || 0);
    }
}

// Initialize dashboard on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new PerformanceDashboard();
});