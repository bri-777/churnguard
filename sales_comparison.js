// Sales Comparison & Target Tracking JavaScript - Improved Version

// Constants
const DATE_CONSTANTS = {
    ONE_DAY: 86400000,
    ONE_WEEK: 604800000,
    ONE_MONTH: 2592000000
};

const CHART_COLORS = {
    primary: '#4f46e5',
    primaryLight: 'rgba(79, 70, 229, 0.1)',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    purple: '#8b5cf6'
};

const API_ENDPOINTS = {
    kpiSummary: 'api/sales_comparison.php?action=kpi_summary',
    compare: 'api/sales_comparison.php?action=compare',
    targets: 'api/sales_comparison.php?action=get_targets',
    saveTarget: 'api/sales_comparison.php?action=save_target',
    deleteTarget: 'api/sales_comparison.php?action=delete_target',
    trendData: 'api/sales_comparison.php?action=trend_data'
};

// State Management
const state = {
    charts: {
        comparison: null,
        target: null
    },
    activeRequests: new Map(),
    isLoading: false
};

// Utility Functions
const utils = {
    formatCurrency(value) {
        return '₱' + parseFloat(value).toLocaleString('en-PH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    },

    formatNumber(value) {
        return parseInt(value).toLocaleString('en-PH');
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-PH', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    },

    formatTargetType(type) {
        const types = {
            'sales': 'Sales Revenue',
            'customers': 'Customer Traffic',
            'transactions': 'Transactions',
            'avg_transaction': 'Avg Transaction Value'
        };
        return types[type] || type;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    getISODate(date) {
        return date.toISOString().split('T')[0];
    },

    showNotification(message, type = 'info') {
        // Replace alert() with better UI notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    setLoading(isLoading) {
        state.isLoading = isLoading;
        const loader = document.getElementById('globalLoader');
        if (loader) {
            loader.style.display = isLoading ? 'flex' : 'none';
        }
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// API Service
const apiService = {
    async fetchWithCancel(url, options = {}) {
        // Cancel previous request to same endpoint
        if (state.activeRequests.has(url)) {
            state.activeRequests.get(url).abort();
        }

        const controller = new AbortController();
        state.activeRequests.set(url, controller);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            state.activeRequests.delete(url);
            return data;
        } catch (error) {
            state.activeRequests.delete(url);
            if (error.name === 'AbortError') {
                console.log('Request cancelled:', url);
                return null;
            }
            throw error;
        }
    },

    async get(url) {
        return this.fetchWithCancel(url);
    },

    async post(url, body) {
        return this.fetchWithCancel(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }
};

// Chart Management
const chartManager = {
    createOrUpdateChart(ctx, config) {
        if (!ctx) {
            console.error('Chart context is null');
            return null;
        }

        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return null;
        }

        const canvasId = ctx.id;
        const existingChart = state.charts[canvasId];

        // Update existing chart data instead of destroying
        if (existingChart && existingChart.data) {
            existingChart.data.labels = config.data.labels;
            existingChart.data.datasets = config.data.datasets;
            existingChart.update('none'); // Update without animation for smoother experience
            return existingChart;
        }

        // Create new chart
        try {
            const newChart = new Chart(ctx, config);
            state.charts[canvasId] = newChart;
            return newChart;
        } catch (error) {
            console.error('Error creating chart:', error);
            return null;
        }
    },

    destroyChart(chartId) {
        if (state.charts[chartId]) {
            state.charts[chartId].destroy();
            state.charts[chartId] = null;
        }
    },

    destroyAllCharts() {
        Object.keys(state.charts).forEach(key => {
            this.destroyChart(key);
        });
    }
};

// Date Management
const dateManager = {
    setDefaultDates() {
        const today = new Date();
        const yesterday = new Date(today - DATE_CONSTANTS.ONE_DAY);
        
        const currentDateInput = document.getElementById('currentDate');
        const compareDateInput = document.getElementById('compareDate');
        
        if (currentDateInput) currentDateInput.value = utils.getISODate(today);
        if (compareDateInput) compareDateInput.value = utils.getISODate(yesterday);
    },

    updateComparisonDates() {
        const typeSelect = document.getElementById('comparisonType');
        const currentDateInput = document.getElementById('currentDate');
        const compareDateInput = document.getElementById('compareDate');
        
        if (!typeSelect || !currentDateInput || !compareDateInput) return;
        
        const type = typeSelect.value;
        const today = new Date();
        currentDateInput.value = utils.getISODate(today);
        
        const dateMap = {
            'today_vs_date': today - DATE_CONSTANTS.ONE_DAY,
            'week_vs_range': today - DATE_CONSTANTS.ONE_WEEK,
            'month_vs_period': today - DATE_CONSTANTS.ONE_MONTH
        };
        
        compareDateInput.value = utils.getISODate(new Date(dateMap[type] || dateMap['today_vs_date']));
    }
};

// KPI Management
const kpiManager = {
    async loadSummary() {
        try {
            const data = await apiService.get(API_ENDPOINTS.kpiSummary);
            
            if (!data || data.error) {
                console.error('Error loading KPI:', data?.error);
                return;
            }
            
            this.updateKPICards(data);
        } catch (error) {
            console.error('Error loading KPI summary:', error);
            utils.showNotification('Failed to load KPI summary', 'error');
        }
    },

    updateKPICards(data) {
        const updates = [
            { id: 'todaySales', value: utils.formatCurrency(data.today_sales), change: 'salesChange', changeValue: data.sales_change },
            { id: 'todayCustomers', value: utils.formatNumber(data.today_customers), change: 'customersChange', changeValue: data.customers_change },
            { id: 'todayTransactions', value: utils.formatNumber(data.today_transactions), change: 'transactionsChange', changeValue: data.transactions_change }
        ];

        updates.forEach(({ id, value, change, changeValue }) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
            if (change) this.updateChangeIndicator(change, changeValue);
        });

        const targetAchievement = document.getElementById('targetAchievement');
        const targetStatus = document.getElementById('targetStatus');
        
        if (targetAchievement) targetAchievement.textContent = data.target_achievement.toFixed(1) + '%';
        if (targetStatus) targetStatus.textContent = data.target_status;
    },

    updateChangeIndicator(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const sign = value >= 0 ? '+' : '';
        element.textContent = sign + value.toFixed(1) + '%';
        element.className = 'kpi-change ' + (value >= 0 ? 'positive' : 'negative');
    }
};

// Comparison Management
const comparisonManager = {
    async loadComparison() {
        const currentDateInput = document.getElementById('currentDate');
        const compareDateInput = document.getElementById('compareDate');
        
        if (!currentDateInput || !compareDateInput) return;
        
        const currentDate = currentDateInput.value;
        const compareDate = compareDateInput.value;
        
        if (!currentDate || !compareDate) {
            utils.showNotification('Please select both dates', 'error');
            return;
        }
        
        utils.setLoading(true);
        
        try {
            const url = `${API_ENDPOINTS.compare}&currentDate=${currentDate}&compareDate=${compareDate}`;
            const data = await apiService.get(url);
            
            if (!data || data.error) {
                utils.showNotification('Error: ' + (data?.error || 'Unknown error'), 'error');
                return;
            }
            
            this.displayResults(data.comparison);
        } catch (error) {
            console.error('Error loading comparison:', error);
            utils.showNotification('Failed to load comparison data', 'error');
        } finally {
            utils.setLoading(false);
        }
    },

    displayResults(comparison) {
        const tbody = document.getElementById('comparisonTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (!comparison || comparison.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;">No comparison data available</td></tr>';
            return;
        }
        
        comparison.forEach(item => {
            const row = document.createElement('tr');
            const trendIcon = item.trend === 'up' ? '▲' : '▼';
            const trendClass = item.trend === 'up' ? 'trend-up' : 'trend-down';
            const isCurrency = item.metric.includes('Sales') || item.metric.includes('Value');
            
            const formatValue = isCurrency ? utils.formatCurrency : utils.formatNumber;
            
            row.innerHTML = `
                <td><strong>${utils.escapeHtml(item.metric)}</strong></td>
                <td>${formatValue(item.current)}</td>
                <td>${formatValue(item.compare)}</td>
                <td>${formatValue(Math.abs(item.difference))}</td>
                <td>${item.percentage >= 0 ? '+' : ''}${item.percentage.toFixed(2)}%</td>
                <td>
                    <span class="trend-indicator ${trendClass}">${trendIcon}</span>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }
};

// Target Management
const targetManager = {
    async loadTargets(filter = 'all') {
        try {
            const url = `${API_ENDPOINTS.targets}&filter=${filter}`;
            const data = await apiService.get(url);
            
            if (!data || data.error) {
                console.error('Error loading targets:', data?.error);
                return;
            }
            
            this.displayTargets(data.targets);
        } catch (error) {
            console.error('Error loading targets:', error);
            utils.showNotification('Failed to load targets', 'error');
        }
    },

    displayTargets(targets) {
        const tbody = document.getElementById('targetsTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (!targets || targets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;">No targets found. Create your first target to start tracking progress.</td></tr>';
            return;
        }
        
        targets.forEach(target => {
            const row = this.createTargetRow(target);
            tbody.appendChild(row);
        });
    },

    createTargetRow(target) {
        const row = document.createElement('tr');
        
        const progressClass = target.progress >= 100 ? 'progress-achieved' : 
                            target.progress >= 80 ? 'progress-near' : 'progress-below';
        
        const statusClass = target.status === 'achieved' ? 'status-achieved' : 
                          target.status === 'near' ? 'status-near' : 'status-below';
        
        const statusText = target.status === 'achieved' ? 'Achieved' : 
                         target.status === 'near' ? 'Near Target' : 'Below Target';
        
        const isCurrencyType = target.target_type === 'sales' || target.target_type === 'avg_transaction';
        const formatValue = isCurrencyType ? utils.formatCurrency : utils.formatNumber;
        
        row.innerHTML = `
            <td><strong>${utils.escapeHtml(target.target_name)}</strong></td>
            <td>${utils.formatTargetType(target.target_type)}</td>
            <td>${utils.formatDate(target.start_date)} - ${utils.formatDate(target.end_date)}</td>
            <td>${formatValue(target.target_value)}</td>
            <td>${formatValue(target.current_value)}</td>
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
                <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="targetManager.editTarget(${target.id})">Edit</button>
                <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;background:#ef4444;color:#fff;" onclick="targetManager.deleteTarget(${target.id})">Delete</button>
            </td>
        `;
        
        return row;
    },

    filterTargets() {
        const filterSelect = document.getElementById('targetFilter');
        if (filterSelect) {
            this.loadTargets(filterSelect.value);
        }
    },

    openModal() {
        const modal = document.getElementById('targetModal');
        const form = document.getElementById('targetForm');
        
        if (modal) modal.classList.add('active');
        if (form) form.reset();
        
        const today = new Date();
        const nextMonth = new Date(today.getTime() + DATE_CONSTANTS.ONE_MONTH);
        
        const startDateInput = document.getElementById('targetStartDate');
        const endDateInput = document.getElementById('targetEndDate');
        
        if (startDateInput) startDateInput.value = utils.getISODate(today);
        if (endDateInput) endDateInput.value = utils.getISODate(nextMonth);
    },

    closeModal() {
        const modal = document.getElementById('targetModal');
        if (modal) modal.classList.remove('active');
    },

    async saveTarget(event) {
        event.preventDefault();
        
        const formData = {
            name: document.getElementById('targetName')?.value,
            type: document.getElementById('targetType')?.value,
            value: parseFloat(document.getElementById('targetValue')?.value),
            start_date: document.getElementById('targetStartDate')?.value,
            end_date: document.getElementById('targetEndDate')?.value,
            store: document.getElementById('targetStore')?.value
        };
        
        // Validation
        if (!formData.name || !formData.type || !formData.value || !formData.start_date || !formData.end_date) {
            utils.showNotification('Please fill in all required fields', 'error');
            return;
        }
        
        utils.setLoading(true);
        
        try {
            const data = await apiService.post(API_ENDPOINTS.saveTarget, formData);
            
            if (!data || data.error) {
                utils.showNotification('Error: ' + (data?.error || 'Unknown error'), 'error');
                return;
            }
            
            this.closeModal();
            this.loadTargets();
            utils.showNotification('Target saved successfully!', 'success');
        } catch (error) {
            console.error('Error saving target:', error);
            utils.showNotification('Failed to save target', 'error');
        } finally {
            utils.setLoading(false);
        }
    },

    async deleteTarget(id) {
        if (!confirm('Are you sure you want to delete this target?')) {
            return;
        }
        
        utils.setLoading(true);
        
        try {
            const url = `${API_ENDPOINTS.deleteTarget}&id=${id}`;
            const data = await apiService.get(url);
            
            if (!data || data.error) {
                utils.showNotification('Error: ' + (data?.error || 'Unknown error'), 'error');
                return;
            }
            
            this.loadTargets();
            utils.showNotification('Target deleted successfully!', 'success');
        } catch (error) {
            console.error('Error deleting target:', error);
            utils.showNotification('Failed to delete target', 'error');
        } finally {
            utils.setLoading(false);
        }
    },

    editTarget(id) {
        // Placeholder for edit functionality
        utils.showNotification('Edit functionality: Load target data for editing', 'info');
        // TODO: Implement actual edit logic
    }
};

// Chart Loaders
const chartLoaders = {
    async loadTrendChart() {
        try {
            const url = `${API_ENDPOINTS.trendData}&days=30`;
            const data = await apiService.get(url);
            
            if (!data || data.error || !data.trend_data) {
                console.error('No trend data available');
                return;
            }
            
            const ctx = document.getElementById('salesTrendChart');
            if (!ctx) return;
            
            const config = {
                type: 'line',
                data: {
                    labels: data.trend_data.map(d => utils.formatDate(d.date)),
                    datasets: [{
                        label: 'Sales Revenue',
                        data: data.trend_data.map(d => d.sales_volume),
                        borderColor: CHART_COLORS.primary,
                        backgroundColor: CHART_COLORS.primaryLight,
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
                                callback: value => '₱' + value.toLocaleString()
                            }
                        }
                    }
                }
            };
            
            chartManager.createOrUpdateChart(ctx, config);
        } catch (error) {
            console.error('Error loading trend chart:', error);
        }
    },

    async loadTargetChart() {
        try {
            const url = `${API_ENDPOINTS.targets}&filter=active`;
            const data = await apiService.get(url);
            
            if (!data || data.error || !data.targets || data.targets.length === 0) {
                console.log('No active targets for chart');
                return;
            }
            
            const ctx = document.getElementById('targetAchievementChart');
            if (!ctx) return;
            
            const config = {
                type: 'doughnut',
                data: {
                    labels: data.targets.map(t => t.target_name),
                    datasets: [{
                        data: data.targets.map(t => Math.min(t.progress, 100)),
                        backgroundColor: [
                            CHART_COLORS.primary,
                            CHART_COLORS.success,
                            CHART_COLORS.warning,
                            CHART_COLORS.danger,
                            CHART_COLORS.purple
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
            };
            
            chartManager.createOrUpdateChart(ctx, config);
        } catch (error) {
            console.error('Error loading target chart:', error);
        }
    }
};

// Main App Controller
const app = {
    async init() {
        dateManager.setDefaultDates();
        await this.loadAllData();
        this.setupEventListeners();
    },

    async loadAllData() {
        await Promise.allSettled([
            kpiManager.loadSummary(),
            targetManager.loadTargets(),
            chartLoaders.loadTrendChart(),
            chartLoaders.loadTargetChart()
        ]);
    },

    setupEventListeners() {
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            chartManager.destroyAllCharts();
        });
    },

    refreshData: utils.debounce(async function() {
        utils.setLoading(true);
        await app.loadAllData();
        await comparisonManager.loadComparison();
        utils.setLoading(false);
        utils.showNotification('Data refreshed successfully', 'success');
    }, 500),

    exportReport() {
        utils.showNotification('Preparing report for export...', 'info');
        // TODO: Implement actual export functionality
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => app.init());

// Global function exports (for inline onclick handlers)
window.updateComparisonDates = () => dateManager.updateComparisonDates();
window.loadComparison = () => comparisonManager.loadComparison();
window.filterTargets = () => targetManager.filterTargets();
window.openTargetModal = () => targetManager.openModal();
window.closeTargetModal = () => targetManager.closeModal();
window.saveTarget = (e) => targetManager.saveTarget(e);
window.refreshData = () => app.refreshData();
window.exportReport = () => app.exportReport();