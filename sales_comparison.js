// ==================== PROFESSIONAL SALES ANALYTICS DASHBOARD ====================
'use strict';

// ==================== CONFIGURATION ====================
const CONFIG = {
    API_BASE: 'sales_comparison.php',
    REQUEST_TIMEOUT: 30000,
    MAX_RETRIES: 3,
    RETRY_DELAY: 1000,
    CHART_COLORS: {
        primary: '#6366f1',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#06b6d4',
        purple: '#8b5cf6'
    },
    CHART_OPTIONS: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { 
                display: true, 
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: { 
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                cornerRadius: 8,
                displayColors: true
            }
        },
        interaction: {
            mode: 'index',
            intersect: false
        }
    }
};

// ==================== STATE MANAGEMENT ====================
const AppState = {
    charts: {},
    currentTab: 'trend',
    activeRequests: new Map(),
    loadingCounter: 0,
    editingTargetId: null,
    lastDataUpdate: null,
    
    incrementLoading() {
        this.loadingCounter++;
        if (this.loadingCounter === 1) {
            UIManager.showLoader();
        }
    },
    
    decrementLoading() {
        this.loadingCounter = Math.max(0, this.loadingCounter - 1);
        if (this.loadingCounter === 0) {
            UIManager.hideLoader();
        }
    },

    cancelPendingRequests() {
        this.activeRequests.forEach(controller => {
            try {
                controller.abort();
            } catch (e) {
                console.warn('Error aborting request:', e);
            }
        });
        this.activeRequests.clear();
    }
};

// ==================== UTILITY FUNCTIONS ====================
const Utils = {
    formatCurrency(value) {
        if (value == null || isNaN(value)) return '₱0.00';
        const num = parseFloat(value);
        return '₱' + num.toLocaleString('en-PH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    },

    formatNumber(value) {
        if (value == null || isNaN(value)) return '0';
        return Math.round(parseFloat(value)).toLocaleString('en-PH');
    },

    formatPercentage(value) {
        if (value == null || isNaN(value)) return '0.0%';
        const num = parseFloat(value);
        const capped = Math.min(Math.max(num, -999.9), 999.9);
        return capped.toFixed(1) + '%';
    },

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Invalid Date';
            return date.toLocaleDateString('en-PH', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        } catch {
            return 'Invalid Date';
        }
    },

    getISODate(date) {
        try {
            if (!(date instanceof Date)) {
                date = new Date(date);
            }
            if (isNaN(date.getTime())) {
                return new Date().toISOString().split('T')[0];
            }
            return date.toISOString().split('T')[0];
        } catch {
            return new Date().toISOString().split('T')[0];
        }
    },

    formatDateTime() {
        try {
            const now = new Date();
            return now.toLocaleDateString('en-PH', { 
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch {
            return 'N/A';
        }
    },

    calculateChange(current, previous) {
        if (!previous || previous === 0) {
            return current > 0 ? 100 : 0;
        }
        return ((current - previous) / previous) * 100;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
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
    },

    $(selector) {
        return document.querySelector(selector);
    },

    $$(selector) {
        return document.querySelectorAll(selector);
    }
};

// ==================== UI MANAGER ====================
const UIManager = {
    showNotification(message, type = 'info') {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#6366f1'
        };

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        // Remove existing notifications
        const existing = Utils.$$('.notification-toast');
        existing.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = 'notification-toast';
        notification.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            min-width: 320px;
            max-width: 500px;
            padding: 16px 20px;
            background: ${colors[type] || colors.info};
            color: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            z-index: 10001;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        `;
        
        notification.innerHTML = `
            <span style="font-size: 20px;">${icons[type] || icons.info}</span>
            <span>${Utils.escapeHtml(message)}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease';
            setTimeout(() => notification.remove(), 400);
        }, 4000);
    },

    showLoader() {
        let loader = Utils.$('#globalLoader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'globalLoader';
            loader.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.4);
                backdrop-filter: blur(4px);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
            `;
            loader.innerHTML = `
                <div style="background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center;">
                    <div class="spinner" style="border: 5px solid #f3f4f6; border-top: 5px solid #6366f1; border-radius: 50%; width: 60px; height: 60px; animation: spin 0.8s linear infinite; margin: 0 auto 16px;"></div>
                    <div style="color: #6b7280; font-size: 14px; font-weight: 500;">Loading data...</div>
                </div>
            `;
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    },

    hideLoader() {
        const loader = Utils.$('#globalLoader');
        if (loader) {
            loader.style.display = 'none';
        }
    },

    updateKPICard(id, value, change) {
        const valueEl = Utils.$(`#${id}`);
        const baseId = id.replace('today', '').replace('target', '');
        const trendBadge = Utils.$(`#${baseId}TrendBadge`);
        const comparison = Utils.$(`#${baseId}Comparison`);
        
        if (valueEl) {
            let formattedValue;
            if (id.includes('Sales') || id.includes('target')) {
                formattedValue = id.includes('target') && !id.includes('Sales') ? 
                    Utils.formatPercentage(value) : 
                    Utils.formatCurrency(value);
            } else {
                formattedValue = Utils.formatNumber(value);
            }
            valueEl.textContent = formattedValue;
        }
        
        if (trendBadge && change !== undefined && !isNaN(change)) {
            const isPositive = change >= 0;
            trendBadge.className = `kpi-trend-badge ${isPositive ? '' : 'down'}`;
            const span = trendBadge.querySelector('span');
            if (span) {
                span.textContent = Utils.formatPercentage(Math.abs(change));
            }
            
            const svg = trendBadge.querySelector('svg path');
            if (svg) {
                svg.setAttribute('d', isPositive ? 'M7 14l5-5 5 5H7z' : 'M7 10l5 5 5-5H7z');
            }
        }
        
        if (comparison && change !== undefined && !isNaN(change)) {
            comparison.textContent = `${change >= 0 ? '+' : ''}${Utils.formatPercentage(change)} vs yesterday`;
        }
    },

    updateDateTime() {
        const dateEl = Utils.$('#currentDateTime');
        if (dateEl) {
            dateEl.textContent = Utils.formatDateTime();
        }
    }
};

// ==================== API SERVICE ====================
const APIService = {
    async fetch(action, params = {}, retryCount = 0) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.append(key, String(value));
            }
        });

        console.log(`[API] ${action}:`, url.toString());

        const controller = new AbortController();
        const requestId = `${action}-${Date.now()}-${Math.random()}`;
        AppState.activeRequests.set(requestId, controller);

        const timeoutId = setTimeout(() => {
            controller.abort();
        }, CONFIG.REQUEST_TIMEOUT);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal,
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            console.log(`[API] ${action} Response:`, response.status);

            if (response.status === 401) {
                UIManager.showNotification('Session expired. Redirecting to login...', 'error');
                setTimeout(() => {
                    window.location.href = '/login.php';
                }, 2000);
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`[API] ${action} Error:`, errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Invalid response format. Expected JSON.');
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            console.log(`[API] ${action} Success:`, data);
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log(`[API] ${action} Cancelled`);
                return null;
            }

            console.error(`[API] ${action} Error:`, error);

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                const delay = CONFIG.RETRY_DELAY * (retryCount + 1);
                console.log(`[API] Retrying ${action} in ${delay}ms (attempt ${retryCount + 1}/${CONFIG.MAX_RETRIES})`);
                await new Promise(resolve => setTimeout(resolve, delay));
                return this.fetch(action, params, retryCount + 1);
            }

            throw error;
        }
    },

    async post(action, body, retryCount = 0) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);

        console.log(`[API] POST ${action}:`, body);

        const controller = new AbortController();
        const requestId = `${action}-${Date.now()}-${Math.random()}`;
        AppState.activeRequests.set(requestId, controller);

        const timeoutId = setTimeout(() => {
            controller.abort();
        }, CONFIG.REQUEST_TIMEOUT);

        try {
            const response = await fetch(url.toString(), {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body),
                signal: controller.signal,
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            console.log(`[API] POST ${action} Response:`, response.status);

            if (response.status === 401) {
                UIManager.showNotification('Session expired. Redirecting to login...', 'error');
                setTimeout(() => {
                    window.location.href = '/login.php';
                }, 2000);
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`[API] POST ${action} Error:`, errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Invalid response format. Expected JSON.');
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            console.log(`[API] POST ${action} Success:`, data);
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log(`[API] POST ${action} Cancelled`);
                return null;
            }

            console.error(`[API] POST ${action} Error:`, error);

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                const delay = CONFIG.RETRY_DELAY * (retryCount + 1);
                console.log(`[API] Retrying POST ${action} in ${delay}ms (attempt ${retryCount + 1}/${CONFIG.MAX_RETRIES})`);
                await new Promise(resolve => setTimeout(resolve, delay));
                return this.post(action, body, retryCount + 1);
            }

            throw error;
        }
    }
};

// ==================== CHART MANAGER ====================
const ChartManager = {
    destroyChart(chartName) {
        if (AppState.charts[chartName]) {
            try {
                AppState.charts[chartName].destroy();
            } catch (e) {
                console.warn('Error destroying chart:', e);
            }
            delete AppState.charts[chartName];
        }
    },

    createSalesTrendChart(data) {
        const ctx = Utils.$('#salesTrendChart');
        if (!ctx) {
            console.error('Chart canvas not found: salesTrendChart');
            return;
        }

        if (!data || data.length === 0) {
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No trend data available for the selected period</p>';
            return;
        }

        this.destroyChart('salesTrend');

        const sortedData = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));
        
        try {
            AppState.charts.salesTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: sortedData.map(d => Utils.formatDate(d.date)),
                    datasets: [{
                        label: 'Sales Revenue',
                        data: sortedData.map(d => parseFloat(d.sales_volume || 0)),
                        borderColor: CONFIG.CHART_COLORS.primary,
                        backgroundColor: `${CONFIG.CHART_COLORS.primary}20`,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: CONFIG.CHART_COLORS.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    ...CONFIG.CHART_OPTIONS,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => '₱' + value.toLocaleString('en-PH')
                            },
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        ...CONFIG.CHART_OPTIONS.plugins,
                        tooltip: {
                            ...CONFIG.CHART_OPTIONS.plugins.tooltip,
                            callbacks: {
                                label: ctx => 'Revenue: ' + Utils.formatCurrency(ctx.parsed.y)
                            }
                        }
                    }
                }
            });
            console.log('[CHART] Sales trend chart created successfully');
        } catch (error) {
            console.error('[CHART] Error creating sales trend chart:', error);
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#ef4444;">Error loading chart</p>';
        }
    },

    createComparisonChart(comparisonData) {
        const ctx = Utils.$('#comparisonChart');
        if (!ctx) {
            console.warn('[CHART] Comparison chart canvas not found');
            return;
        }

        if (!comparisonData || comparisonData.length === 0) {
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No comparison data available</p>';
            return;
        }

        this.destroyChart('comparison');

        const metrics = comparisonData.map(d => d.metric);
        const currentValues = comparisonData.map(d => parseFloat(d.current || 0));
        const compareValues = comparisonData.map(d => parseFloat(d.compare || 0));

        try {
            AppState.charts.comparison = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: metrics,
                    datasets: [
                        {
                            label: 'Current Period',
                            data: currentValues,
                            backgroundColor: CONFIG.CHART_COLORS.primary,
                            borderRadius: 8,
                            borderWidth: 0
                        },
                        {
                            label: 'Previous Period',
                            data: compareValues,
                            backgroundColor: CONFIG.CHART_COLORS.info,
                            borderRadius: 8,
                            borderWidth: 0
                        }
                    ]
                },
                options: {
                    ...CONFIG.CHART_OPTIONS,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => Utils.formatNumber(value)
                            },
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            console.log('[CHART] Comparison chart created successfully');
        } catch (error) {
            console.error('[CHART] Error creating comparison chart:', error);
        }
    },

    updateTrendChart() {
        const period = parseInt(Utils.$('#trendPeriod')?.value) || 30;
        DataManager.loadTrendData(period);
    }
};

// ==================== DATA MANAGER ====================
const DataManager = {
    async loadKPISummary() {
        AppState.incrementLoading();
        try {
            console.log('[DATA] Loading KPI Summary...');
            const data = await APIService.fetch('kpi_summary');
            
            if (!data) {
                console.warn('[DATA] No KPI data received');
                return;
            }

            // Update KPI cards
            UIManager.updateKPICard('todaySales', data.today_sales || 0, data.sales_change || 0);
            UIManager.updateKPICard('todayCustomers', data.today_customers || 0, data.customers_change || 0);
            UIManager.updateKPICard('todayTransactions', data.today_transactions || 0, data.transactions_change || 0);
            UIManager.updateKPICard('targetAchievement', data.target_achievement || 0);
            
            // Update target status
            const targetStatus = Utils.$('#targetStatus');
            if (targetStatus) {
                targetStatus.textContent = data.target_status || 'No active target';
            }
            
            // Update mini progress bar
            const miniProgress = Utils.$('#targetMiniProgress');
            if (miniProgress) {
                const progress = Math.min(parseFloat(data.target_achievement) || 0, 100);
                miniProgress.style.width = progress + '%';
            }

            AppState.lastDataUpdate = new Date();
            console.log('[DATA] KPI Summary loaded successfully');

        } catch (error) {
            console.error('[DATA] KPI Summary Error:', error);
            UIManager.showNotification('Failed to load KPI data: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async loadTrendData(days = 30) {
        AppState.incrementLoading();
        try {
            console.log(`[DATA] Loading Trend Data (${days} days)...`);
            const data = await APIService.fetch('trend_data', { days });
            
            if (!data) {
                console.warn('[DATA] No trend data received');
                return;
            }

            if (data.trend_data && data.trend_data.length > 0) {
                ChartManager.createSalesTrendChart(data.trend_data);
                this.populateTrendTable(data.trend_data);
                console.log('[DATA] Trend data loaded successfully');
            } else {
                const chart = Utils.$('#salesTrendChart');
                if (chart) {
                    chart.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No data available for selected period</p>';
                }
                
                const tbody = Utils.$('#salesTrendTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No trend data available</td></tr>';
                }
                console.warn('[DATA] No trend data available');
            }
        } catch (error) {
            console.error('[DATA] Trend Data Error:', error);
            UIManager.showNotification('Failed to load trend data: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    populateTrendTable(trendData) {
        const tbody = Utils.$('#salesTrendTableBody');
        if (!tbody) return;

        if (!trendData || trendData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No trend data available</td></tr>';
            return;
        }

        const sorted = [...trendData].sort((a, b) => new Date(b.date) - new Date(a.date));
        
        tbody.innerHTML = sorted.map((item, index) => {
            const sales = parseFloat(item.sales_volume || 0);
            const receipts = parseInt(item.receipt_count || 0);
            const customers = parseInt(item.customer_traffic || 0);
            const avgValue = receipts > 0 ? sales / receipts : 0;
            
            let change = 0;
            let changeHtml = '—';
            
            if (index < sorted.length - 1) {
                const prevSales = parseFloat(sorted[index + 1].sales_volume || 0);
                change = Utils.calculateChange(sales, prevSales);
                const isPositive = change >= 0;
                changeHtml = `
                    <span class="metric-change ${isPositive ? 'positive' : 'negative'}">
                        ${isPositive ? '▲' : '▼'} ${Utils.formatPercentage(Math.abs(change))}
                    </span>
                `;
            }

            return `
                <tr>
                    <td><strong>${Utils.formatDate(item.date)}</strong></td>
                    <td>${Utils.formatCurrency(sales)}</td>
                    <td>${Utils.formatNumber(receipts)}</td>
                    <td>${Utils.formatNumber(customers)}</td>
                    <td>${Utils.formatCurrency(avgValue)}</td>
                    <td>${changeHtml}</td>
                </tr>
            `;
        }).join('');
    },

    async loadComparison() {
        const currentDate = Utils.$('#currentDate')?.value;
        const compareDate = Utils.$('#compareDate')?.value;

        if (!currentDate || !compareDate) {
            UIManager.showNotification('Please select both dates', 'warning');
            return;
        }

        if (currentDate === compareDate) {
            UIManager.showNotification('Please select different dates for comparison', 'warning');
            return;
        }

        AppState.incrementLoading();
        try {
            console.log('[DATA] Loading Comparison...');
            const data = await APIService.fetch('compare', { currentDate, compareDate });
            
            if (!data) return;

            if (data.comparison && data.comparison.length > 0) {
                this.displayComparisonResults(data.comparison);
                ChartManager.createComparisonChart(data.comparison);
                UIManager.showNotification('Comparison loaded successfully', 'success');
                console.log('[DATA] Comparison loaded successfully');
            } else {
                UIManager.showNotification('No comparison data available', 'info');
            }
        } catch (error) {
            console.error('[DATA] Comparison Error:', error);
            UIManager.showNotification('Failed to load comparison: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    displayComparisonResults(comparison) {
        const container = Utils.$('.comparison-grid');
        if (!container) return;

        container.innerHTML = comparison.map(item => {
            const change = parseFloat(item.percentage || 0);
            const isPositive = change >= 0;
            const isCurrency = item.metric.toLowerCase().includes('sales') || 
                             item.metric.toLowerCase().includes('value');
            
            return `
                <div class="comparison-metric-card">
                    <div class="metric-name">${Utils.escapeHtml(item.metric)}</div>
                    <div class="metric-values">
                        <span class="metric-current">${isCurrency ? 
                            Utils.formatCurrency(item.current) : 
                            Utils.formatNumber(item.current)
                        }</span>
                        <span class="metric-previous">vs ${isCurrency ? 
                            Utils.formatCurrency(item.compare) : 
                            Utils.formatNumber(item.compare)
                        }</span>
                    </div>
                    <div class="metric-change ${isPositive ? 'positive' : 'negative'}">
                        <span>${isPositive ? '▲' : '▼'}</span>
                        <span>${Utils.formatPercentage(Math.abs(change))}</span>
                    </div>
                </div>
            `;
        }).join('');
    }
};

// ==================== TARGET MANAGER ====================
const TargetManager = {
    async loadTargets(filter = 'all') {
        AppState.incrementLoading();
        try {
            console.log(`[TARGET] Loading targets with filter: ${filter}`);
            const data = await APIService.fetch('get_targets', { filter });
            
            if (!data) return;

            if (data.targets) {
                this.displayTargetsGrid(data.targets);
                this.displayTargetsTable(data.targets);
                console.log(`[TARGET] Loaded ${data.targets.length} targets`);
            }
        } catch (error) {
            console.error('[TARGET] Load Targets Error:', error);
            UIManager.showNotification('Failed to load targets: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    displayTargetsGrid(targets) {
        const grid = Utils.$('#targetsGrid');
        if (!grid) return;

        if (!targets || targets.length === 0) {
            grid.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No targets found</p>';
            return;
        }

        grid.innerHTML = targets.map(target => {
            const progress = Math.min(parseFloat(target.progress || 0), 999.9);
            const statusClass = target.status === 'achieved' ? 'achieved' : 
                              target.status === 'near' ? 'near' : 'below';
            const isCurrency = target.target_type === 'sales' || target.target_type === 'avg_transaction';

            return `
                <div class="target-card-pro">
                    <div class="target-header-row">
                        <div>
                            <h4 class="target-name-pro">${Utils.escapeHtml(target.target_name)}</h4>
                            <span class="target-type-badge">${this.formatTargetType(target.target_type)}</span>
                        </div>
                    </div>
                    <div class="target-progress-section">
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill ${statusClass}" style="width:${Math.min(progress, 100)}%"></div>
                        </div>
                        <div class="progress-stats">
                            <span class="progress-percentage">${progress.toFixed(1)}%</span>
                            <span class="progress-values">
                                ${isCurrency ? Utils.formatCurrency(target.current_value) : Utils.formatNumber(target.current_value)} / 
                                ${isCurrency ? Utils.formatCurrency(target.target_value) : Utils.formatNumber(target.target_value)}
                            </span>
                        </div>
                    </div>
                    <div class="target-footer-row">
                        <span class="target-dates">${Utils.formatDate(target.start_date)} - ${Utils.formatDate(target.end_date)}</span>
                        <div class="target-actions">
                            <button class="btn-icon-small" onclick="TargetManager.editTarget(${target.id})" title="Edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="btn-icon-small delete" onclick="TargetManager.deleteTarget(${target.id})" title="Delete">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    displayTargetsTable(targets) {
        const tbody = Utils.$('#activeTargetsTableBody');
        if (!tbody) return;

        if (!targets || targets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No active targets</td></tr>';
            return;
        }

        tbody.innerHTML = targets.map(target => {
            const progress = Math.min(parseFloat(target.progress || 0), 999.9);
            const statusClass = target.status === 'achieved' ? 'achieved' : 
                              target.status === 'near' ? 'near' : 'below';
            const statusText = target.status === 'achieved' ? 'Achieved' : 
                             target.status === 'near' ? 'Near Target' : 'Below Target';
            const isCurrency = target.target_type === 'sales' || target.target_type === 'avg_transaction';

            return `
                <tr>
                    <td><strong>${Utils.escapeHtml(target.target_name)}</strong></td>
                    <td>${this.formatTargetType(target.target_type)}</td>
                    <td>
                        ${isCurrency ? Utils.formatCurrency(target.current_value) : Utils.formatNumber(target.current_value)} / 
                        ${isCurrency ? Utils.formatCurrency(target.target_value) : Utils.formatNumber(target.target_value)}
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                                <div class="progress-bar-fill ${statusClass}" style="width:${Math.min(progress, 100)}%;height:100%;border-radius:3px;transition:width 0.3s ease;"></div>
                            </div>
                            <span style="font-weight:600;min-width:50px;font-size:13px;">${progress.toFixed(1)}%</span>
                        </div>
                    </td>
                    <td><span class="status-badge-pro ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="btn-icon-small" onclick="TargetManager.editTarget(${target.id})" title="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="btn-icon-small delete" onclick="TargetManager.deleteTarget(${target.id})" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
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

    async editTarget(id) {
        AppState.incrementLoading();
        try {
            console.log(`[TARGET] Editing target ID: ${id}`);
            const data = await APIService.fetch('get_targets', { filter: 'all' });
            
            if (!data || !data.targets) {
                UIManager.showNotification('Failed to load target data', 'error');
                return;
            }

            const target = data.targets.find(t => t.id === id);
            
            if (!target) {
                UIManager.showNotification('Target not found', 'error');
                return;
            }

            AppState.editingTargetId = id;
            
            // Populate form
            Utils.$('#modalTitle').textContent = 'Edit Target';
            Utils.$('#targetName').value = target.target_name || '';
            Utils.$('#targetType').value = target.target_type || '';
            Utils.$('#targetValue').value = target.target_value || '';
            Utils.$('#targetStartDate').value = target.start_date || '';
            Utils.$('#targetEndDate').value = target.end_date || '';
            Utils.$('#targetStore').value = target.store || '';
            
            ModalManager.open();
            console.log('[TARGET] Edit form populated successfully');
        } catch (error) {
            console.error('[TARGET] Edit Target Error:', error);
            UIManager.showNotification('Failed to load target: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async deleteTarget(id) {
        if (!confirm('Are you sure you want to delete this target? This action cannot be undone.')) {
            return;
        }

        AppState.incrementLoading();
        try {
            console.log(`[TARGET] Deleting target ID: ${id}`);
            const result = await APIService.fetch('delete_target', { id });
            
            if (!result) return;

            UIManager.showNotification('Target deleted successfully', 'success');
            console.log('[TARGET] Target deleted successfully');
            
            // Reload data
            await Promise.all([
                this.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
        } catch (error) {
            console.error('[TARGET] Delete Target Error:', error);
            UIManager.showNotification('Failed to delete target: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    }
};

// ==================== DATE MANAGER ====================
const DateManager = {
    setDefaultDates() {
        const today = new Date();
        const yesterday = new Date(today.getTime() - 86400000);

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) compareDate.value = Utils.getISODate(yesterday);
        
        console.log('[DATE] Default dates set');
    },

    updateComparisonDates() {
        const type = Utils.$('#comparisonType')?.value;
        const today = new Date();
        
        const dateMap = {
            'today_vs_yesterday': 86400000, // 1 day
            'week_vs_week': 604800000, // 7 days
            'month_vs_month': 2592000000, // 30 days
            'custom': 86400000
        };

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) {
            const offset = dateMap[type] || dateMap.custom;
            compareDate.value = Utils.getISODate(new Date(today.getTime() - offset));
        }
        
        console.log(`[DATE] Comparison dates updated for type: ${type}`);
    }
};

// ==================== TAB MANAGER ====================
const TabManager = {
    switchTab(tabName) {
        // Remove active class from all tabs
        Utils.$('.tab-btn').forEach(btn => btn.classList.remove('active'));
        Utils.$('.tab-content').forEach(content => content.classList.remove('active'));

        // Add active class to selected tab
        const activeBtn = Utils.$(`[data-tab="${tabName}"]`);
        const activeContent = Utils.$(`#${tabName}-tab`);

        if (activeBtn) activeBtn.classList.add('active');
        if (activeContent) activeContent.classList.add('active');

        AppState.currentTab = tabName;
        console.log(`[TAB] Switched to: ${tabName}`);
    }
};

// ==================== MODAL MANAGER ====================
const ModalManager = {
    open() {
        const modal = Utils.$('#targetModal');
        const form = Utils.$('#targetForm');
        
        if (modal) modal.classList.add('active');
        if (form) form.reset();
        
        const modalTitle = Utils.$('#modalTitle');
        if (modalTitle && !AppState.editingTargetId) {
            modalTitle.textContent = 'Create New Target';
        }

        // Set default dates if creating new target
        if (!AppState.editingTargetId) {
            const today = new Date();
            const nextMonth = new Date(today.getTime() + 2592000000); // 30 days

            const startDate = Utils.$('#targetStartDate');
            const endDate = Utils.$('#targetEndDate');

            if (startDate) startDate.value = Utils.getISODate(today);
            if (endDate) endDate.value = Utils.getISODate(nextMonth);
        }
        
        console.log('[MODAL] Target modal opened');
    },

    close(event) {
        if (event && event.target !== event.currentTarget && !event.target.classList.contains('modal-close-btn')) {
            return;
        }
        
        const modal = Utils.$('#targetModal');
        if (modal) modal.classList.remove('active');
        
        AppState.editingTargetId = null;
        console.log('[MODAL] Target modal closed');
    },

    async saveTarget(event) {
        event.preventDefault();

        // Collect form data
        const formData = {
            name: Utils.$('#targetName')?.value.trim(),
            type: Utils.$('#targetType')?.value,
            value: parseFloat(Utils.$('#targetValue')?.value),
            start_date: Utils.$('#targetStartDate')?.value,
            end_date: Utils.$('#targetEndDate')?.value,
            store: Utils.$('#targetStore')?.value.trim() || ''
        };

        // Validation
        if (!formData.name) {
            UIManager.showNotification('Please enter a target name', 'warning');
            Utils.$('#targetName')?.focus();
            return;
        }

        if (!formData.type) {
            UIManager.showNotification('Please select a target type', 'warning');
            Utils.$('#targetType')?.focus();
            return;
        }

        if (isNaN(formData.value) || formData.value <= 0) {
            UIManager.showNotification('Please enter a valid target value greater than 0', 'warning');
            Utils.$('#targetValue')?.focus();
            return;
        }

        if (formData.value > 999999999) {
            UIManager.showNotification('Target value is too large. Maximum is 999,999,999', 'warning');
            Utils.$('#targetValue')?.focus();
            return;
        }

        if (!formData.start_date || !formData.end_date) {
            UIManager.showNotification('Please select both start and end dates', 'warning');
            return;
        }

        if (new Date(formData.end_date) < new Date(formData.start_date)) {
            UIManager.showNotification('End date must be after start date', 'warning');
            Utils.$('#targetEndDate')?.focus();
            return;
        }

        AppState.incrementLoading();
        try {
            const action = AppState.editingTargetId ? 'update_target' : 'save_target';
            
            if (AppState.editingTargetId) {
                formData.id = AppState.editingTargetId;
            }

            console.log(`[MODAL] Saving target with action: ${action}`, formData);
            
            const result = await APIService.post(action, formData);
            
            if (!result) return;

            UIManager.showNotification(
                AppState.editingTargetId ? 'Target updated successfully' : 'Target created successfully', 
                'success'
            );
            
            this.close();
            
            // Reload data
            await Promise.all([
                TargetManager.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
            
            console.log('[MODAL] Target saved successfully');
        } catch (error) {
            console.error('[MODAL] Save Target Error:', error);
            UIManager.showNotification(error.message || 'Failed to save target', 'error');
        } finally {
            AppState.decrementLoading();
        }
    }
};

// ==================== GLOBAL FUNCTIONS ====================
window.openTargetModal = () => ModalManager.open();
window.closeTargetModal = (event) => ModalManager.close(event);
window.saveTarget = (event) => ModalManager.saveTarget(event);
window.filterTargets = () => TargetManager.loadTargets(Utils.$('#targetFilter')?.value || 'all');
window.loadComparison = () => DataManager.loadComparison();
window.updateComparisonDates = () => DateManager.updateComparisonDates();
window.switchTab = (tabName) => TabManager.switchTab(tabName);
window.updateTrendChart = () => ChartManager.updateTrendChart();
window.resetComparison = () => {
    DateManager.setDefaultDates();
    const grid = Utils.$('.comparison-grid');
    if (grid) {
        grid.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">Select dates and click "Analyze" to compare data</p>';
    }
    ChartManager.destroyChart('comparison');
    console.log('[COMPARISON] Reset completed');
};
window.refreshAllData = async () => {
    console.log('[APP] Refreshing all data...');
    await App.loadAllData();
    UIManager.showNotification('Data refreshed successfully', 'success');
};
window.toggleNotifications = () => {
    UIManager.showNotification('Notifications feature coming soon!', 'info');
};
window.exportTargets = () => {
    UIManager.showNotification('Export feature coming soon!', 'info');
};

// ==================== APP INITIALIZATION ====================
const App = {
    async init() {
        console.log('🚀 Initializing Professional Sales Dashboard...');

        try {
            // Check Chart.js
            if (typeof Chart === 'undefined') {
                console.error('❌ Chart.js not loaded!');
                UIManager.showNotification('Chart library not loaded. Please refresh the page.', 'error');
                return;
            }

            console.log('✓ Chart.js loaded');

            // Update date/time
            UIManager.updateDateTime();
            setInterval(() => UIManager.updateDateTime(), 60000);

            // Set default dates
            DateManager.setDefaultDates();

            // Load all data
            await this.loadAllData();

            // Setup event listeners
            this.setupEventListeners();

            console.log('✅ Dashboard initialized successfully');
            UIManager.showNotification('Dashboard loaded successfully', 'success');
        } catch (error) {
            console.error('❌ Initialization error:', error);
            UIManager.showNotification('Failed to initialize dashboard: ' + error.message, 'error');
        }
    },

    async loadAllData() {
        console.log('[APP] Loading all data...');
        
        const promises = [
            DataManager.loadKPISummary(),
            DataManager.loadTrendData(30),
            TargetManager.loadTargets('all')
        ];

        const results = await Promise.allSettled(promises);
        
        results.forEach((result, index) => {
            if (result.status === 'rejected') {
                console.error(`[APP] Failed to load data [${index}]:`, result.reason);
            }
        });
        
        console.log('[APP] All data loaded');
    },

    setupEventListeners() {
        console.log('[APP] Setting up event listeners...');
        
        // Modal backdrop click
        const modal = Utils.$('#targetModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    ModalManager.close();
                }
            });
        }

        // Form submission
        const targetForm = Utils.$('#targetForm');
        if (targetForm) {
            targetForm.addEventListener('submit', (e) => {
                e.preventDefault();
                ModalManager.saveTarget(e);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                ModalManager.close();
            }
        });

        // Refresh on visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && AppState.lastDataUpdate) {
                const timeSinceUpdate = Date.now() - AppState.lastDataUpdate;
                if (timeSinceUpdate > 300000) { // 5 minutes
                    console.log('[APP] Auto-refreshing data after visibility change');
                    this.loadAllData();
                }
            }
        });

        // Handle network errors
        window.addEventListener('online', () => {
            console.log('[APP] Connection restored');
            UIManager.showNotification('Connection restored', 'success');
            this.loadAllData();
        });

        window.addEventListener('offline', () => {
            console.log('[APP] Connection lost');
            UIManager.showNotification('No internet connection. Some features may not work.', 'warning');
        });

        // Handle unload
        window.addEventListener('beforeunload', () => {
            AppState.cancelPendingRequests();
        });
        
        console.log('[APP] Event listeners setup complete');
    }
};

// ==================== PERFORMANCE TABLE LOADER ====================
async function loadPerformanceTable() {
    const tbody = Utils.$('#performanceTableBody');
    if (!tbody) return;

    try {
        const today = new Date();
        const yesterday = new Date(today.getTime() - 86400000);
        const lastWeek = new Date(today.getTime() - 604800000);
        const lastMonth = new Date(today.getTime() - 2592000000);

        const [todayData, yesterdayData, weekData, monthData] = await Promise.all([
            APIService.fetch('kpi_summary'),
            APIService.fetch('compare', { 
                currentDate: Utils.getISODate(today), 
                compareDate: Utils.getISODate(yesterday) 
            }),
            APIService.fetch('compare', { 
                currentDate: Utils.getISODate(today), 
                compareDate: Utils.getISODate(lastWeek) 
            }),
            APIService.fetch('compare', { 
                currentDate: Utils.getISODate(today), 
                compareDate: Utils.getISODate(lastMonth) 
            })
        ]);

        const metrics = [
            {
                name: 'Sales Revenue',
                today: todayData?.today_sales || 0,
                yesterday: yesterdayData?.comparison?.[0]?.compare || 0,
                lastWeek: weekData?.comparison?.[0]?.compare || 0,
                lastMonth: monthData?.comparison?.[0]?.compare || 0,
                isCurrency: true
            },
            {
                name: 'Transactions',
                today: todayData?.today_transactions || 0,
                yesterday: yesterdayData?.comparison?.[1]?.compare || 0,
                lastWeek: weekData?.comparison?.[1]?.compare || 0,
                lastMonth: monthData?.comparison?.[1]?.compare || 0,
                isCurrency: false
            },
            {
                name: 'Customer Traffic',
                today: todayData?.today_customers || 0,
                yesterday: yesterdayData?.comparison?.[2]?.compare || 0,
                lastWeek: weekData?.comparison?.[2]?.compare || 0,
                lastMonth: monthData?.comparison?.[2]?.compare || 0,
                isCurrency: false
            }
        ];

        tbody.innerHTML = metrics.map(metric => {
            const weekChange = Utils.calculateChange(metric.today, metric.lastWeek);
            const isPositive = weekChange >= 0;
            
            return `
                <tr>
                    <td><strong>${metric.name}</strong></td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.today) : Utils.formatNumber(metric.today)}</td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.yesterday) : Utils.formatNumber(metric.yesterday)}</td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.lastWeek) : Utils.formatNumber(metric.lastWeek)}</td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.lastMonth) : Utils.formatNumber(metric.lastMonth)}</td>
                    <td>
                        <span class="metric-change ${isPositive ? 'positive' : 'negative'}">
                            ${isPositive ? '▲' : '▼'} ${Utils.formatPercentage(Math.abs(weekChange))}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (error) {
        console.error('[PERFORMANCE] Error loading performance table:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">Error loading performance data</td></tr>';
    }
}

// Load performance table when switching to that tab
const originalSwitchTab = TabManager.switchTab;
TabManager.switchTab = function(tabName) {
    originalSwitchTab.call(this, tabName);
    if (tabName === 'performance') {
        loadPerformanceTable();
    }
};

// ==================== ANIMATIONS & STYLES ====================
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    .notification-toast {
        pointer-events: all;
    }
`;
document.head.appendChild(style);

// ==================== INITIALIZE APPLICATION ====================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[APP] DOM Content Loaded');
        App.init();
    });
} else {
    console.log('[APP] DOM Already Loaded');
    App.init();
}

// Export for debugging
window.AppState = AppState;
window.App = App;
window.DataManager = DataManager;
window.TargetManager = TargetManager;

console.log('[APP] Sales Analytics Dashboard Script Loaded')