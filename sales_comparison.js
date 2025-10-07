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
            legend: { display: true, position: 'bottom' },
            tooltip: { 
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                cornerRadius: 8
            }
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
        UIManager.showLoader();
    },
    
    decrementLoading() {
        this.loadingCounter = Math.max(0, this.loadingCounter - 1);
        if (this.loadingCounter === 0) {
            UIManager.hideLoader();
        }
    },

    cancelPendingRequests() {
        this.activeRequests.forEach(controller => controller.abort());
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
        const capped = Math.min(Math.max(parseFloat(value), -999.9), 999.9);
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
            if (!(date instanceof Date)) date = new Date(date);
            if (isNaN(date.getTime())) return new Date().toISOString().split('T')[0];
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

        const notification = document.createElement('div');
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
                    <div style="color: #6b7280; font-size: 14px; font-weight: 500;">Loading...</div>
                </div>
            `;
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    },

    hideLoader() {
        const loader = Utils.$('#globalLoader');
        if (loader) loader.style.display = 'none';
    },

    updateKPICard(id, value, change) {
        const valueEl = Utils.$(`#${id}`);
        const trendBadge = Utils.$(`#${id.replace('today', '')}TrendBadge`);
        
        if (valueEl) {
            const formattedValue = id.includes('Sales') || id.includes('target') ? 
                (id.includes('target') ? Utils.formatPercentage(value) : Utils.formatCurrency(value)) : 
                Utils.formatNumber(value);
            valueEl.textContent = formattedValue;
        }
        
        if (trendBadge && change !== undefined) {
            const isPositive = change >= 0;
            trendBadge.className = `kpi-trend-badge ${isPositive ? '' : 'down'}`;
            const span = trendBadge.querySelector('span');
            if (span) {
                span.textContent = Utils.formatPercentage(Math.abs(change));
            }
        }
    },

    updateDateTime() {
        const dateEl = Utils.$('#currentDateTime');
        if (dateEl) {
            dateEl.textContent = Utils.formatDateTime();
        }
    }
};

// ==================== API SERVICE (FIXED) ====================
const APIService = {
    async fetch(action, params = {}, retryCount = 0) {
        // FIXED: Better URL construction
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                url.searchParams.append(key, value);
            }
        });

        console.log('API Request:', action, url.toString()); // DEBUG

        const controller = new AbortController();
        const requestId = `${action}-${Date.now()}`;
        AppState.activeRequests.set(requestId, controller);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal,
                credentials: 'same-origin'
            });

            AppState.activeRequests.delete(requestId);

            console.log('API Response Status:', response.status); // DEBUG

            if (response.status === 401) {
                window.location.href = '/login.php';
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText); // DEBUG
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error');
            }

            return data;
        } catch (error) {
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', action);
                return null;
            }

            // FIXED: Enhanced error logging
            console.error('API Error Details:', {
                action,
                message: error.message,
                url: url.toString(),
                retry: retryCount
            });

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                console.log(`Retrying request (${retryCount + 1}/${CONFIG.MAX_RETRIES})...`);
                await new Promise(resolve => setTimeout(resolve, CONFIG.RETRY_DELAY * (retryCount + 1)));
                return this.fetch(action, params, retryCount + 1);
            }

            console.error('API Error:', error);
            throw error;
        }
    },

    async post(action, body, retryCount = 0) {
        // FIXED: Better URL construction
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);

        console.log('API POST Request:', action, url.toString()); // DEBUG

        const controller = new AbortController();
        const requestId = `${action}-${Date.now()}`;
        AppState.activeRequests.set(requestId, controller);

        try {
            const response = await fetch(url.toString(), {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body),
                signal: controller.signal,
                credentials: 'same-origin'
            });

            AppState.activeRequests.delete(requestId);

            console.log('API POST Response Status:', response.status); // DEBUG

            if (response.status === 401) {
                window.location.href = '/login.php';
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API POST Error Response:', errorText); // DEBUG
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error');
            }

            return data;
        } catch (error) {
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', action);
                return null;
            }

            // FIXED: Enhanced error logging
            console.error('API POST Error Details:', {
                action,
                message: error.message,
                url: url.toString(),
                retry: retryCount
            });

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                console.log(`Retrying POST request (${retryCount + 1}/${CONFIG.MAX_RETRIES})...`);
                await new Promise(resolve => setTimeout(resolve, CONFIG.RETRY_DELAY * (retryCount + 1)));
                return this.post(action, body, retryCount + 1);
            }

            console.error('API Error:', error);
            throw error;
        }
    }
};

// ==================== CHART MANAGER ====================
const ChartManager = {
    destroyChart(chartName) {
        if (AppState.charts[chartName]) {
            AppState.charts[chartName].destroy();
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
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No trend data available</p>';
            return;
        }

        this.destroyChart('salesTrend');

        const sortedData = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));
        
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
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...CONFIG.CHART_OPTIONS,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => '₱' + value.toLocaleString()
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
    },

    createComparisonChart(comparisonData) {
        const ctx = Utils.$('#comparisonChart');
        if (!ctx) {
            console.error('Chart canvas not found: comparisonChart');
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

        AppState.charts.comparison = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: metrics,
                datasets: [
                    {
                        label: 'Current Period',
                        data: currentValues,
                        backgroundColor: CONFIG.CHART_COLORS.primary,
                        borderRadius: 8
                    },
                    {
                        label: 'Previous Period',
                        data: compareValues,
                        backgroundColor: CONFIG.CHART_COLORS.info,
                        borderRadius: 8
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
                        }
                    }
                }
            }
        });
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
            const data = await APIService.fetch('kpi_summary');
            
            if (!data) return;

            UIManager.updateKPICard('todaySales', data.today_sales, data.sales_change);
            UIManager.updateKPICard('todayCustomers', data.today_customers, data.customers_change);
            UIManager.updateKPICard('todayTransactions', data.today_transactions, data.transactions_change);
            UIManager.updateKPICard('targetAchievement', data.target_achievement);
            
            const targetStatus = Utils.$('#targetStatus');
            if (targetStatus) {
                targetStatus.textContent = data.target_status || 'No active target';
            }
            
            const miniProgress = Utils.$('#targetMiniProgress');
            if (miniProgress) {
                const progress = Math.min(parseFloat(data.target_achievement) || 0, 100);
                miniProgress.style.width = progress + '%';
            }

            AppState.lastDataUpdate = new Date();

        } catch (error) {
            console.error('KPI Summary Error:', error);
            UIManager.showNotification('Failed to load KPI data: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async loadTrendData(days = 30) {
        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('trend_data', { days });
            
            if (!data || !data.trend_data) {
                console.warn('No trend data received');
                return;
            }

            if (data.trend_data.length > 0) {
                ChartManager.createSalesTrendChart(data.trend_data);
                this.populateTrendTable(data.trend_data);
            } else {
                const chart = Utils.$('#salesTrendChart');
                if (chart) {
                    chart.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No data available for selected period</p>';
                }
            }
        } catch (error) {
            console.error('Trend Data Error:', error);
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
            if (index < sorted.length - 1) {
                const prevSales = parseFloat(sorted[index + 1].sales_volume || 0);
                change = Utils.calculateChange(sales, prevSales);
            }

            return `
                <tr>
                    <td><strong>${Utils.formatDate(item.date)}</strong></td>
                    <td>${Utils.formatCurrency(sales)}</td>
                    <td>${Utils.formatNumber(receipts)}</td>
                    <td>${Utils.formatNumber(customers)}</td>
                    <td>${Utils.formatCurrency(avgValue)}</td>
                    <td>
                        ${index < sorted.length - 1 ? `
                            <span class="metric-change ${change >= 0 ? 'positive' : 'negative'}">
                                ${change >= 0 ? '▲' : '▼'} ${Utils.formatPercentage(Math.abs(change))}
                            </span>
                        ` : '—'}
                    </td>
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

        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('compare', { currentDate, compareDate });
            
            if (!data) return;

            if (data.comparison && data.comparison.length > 0) {
                this.displayComparisonResults(data.comparison);
                ChartManager.createComparisonChart(data.comparison);
                UIManager.showNotification('Comparison loaded successfully', 'success');
            } else {
                UIManager.showNotification('No comparison data available', 'info');
            }
        } catch (error) {
            console.error('Comparison Error:', error);
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
            const isCurrency = item.metric.includes('Sales') || item.metric.includes('Value');
            
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
            const data = await APIService.fetch('get_targets', { filter });
            
            if (!data) return;

            if (data.targets) {
                this.displayTargetsGrid(data.targets);
                this.displayTargetsTable(data.targets);
            }
        } catch (error) {
            console.error('Load Targets Error:', error);
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
                                <div class="progress-bar-fill ${statusClass}" style="width:${Math.min(progress, 100)}%"></div>
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
            const data = await APIService.fetch('get_targets', { filter: 'all' });
            
            if (!data) return;

            const target = data.targets?.find(t => t.id === id);
            
            if (!target) {
                UIManager.showNotification('Target not found', 'error');
                return;
            }

            AppState.editingTargetId = id;
            
            Utils.$('#modalTitle').textContent = 'Edit Target';
            Utils.$('#targetName').value = target.target_name || '';
            Utils.$('#targetType').value = target.target_type || '';
            Utils.$('#targetValue').value = target.target_value || '';
            Utils.$('#targetStartDate').value = target.start_date || '';
            Utils.$('#targetEndDate').value = target.end_date || '';
            Utils.$('#targetStore').value = target.store || '';
            
            ModalManager.open();
        } catch (error) {
            console.error('Edit Target Error:', error);
            UIManager.showNotification('Failed to load target: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async deleteTarget(id) {
        if (!confirm('Are you sure you want to delete this target?')) return;

        AppState.incrementLoading();
        try {
            const result = await APIService.fetch('delete_target', { id });
            
            if (!result) return;

            UIManager.showNotification('Target deleted successfully', 'success');
            
            await Promise.all([
                this.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
        } catch (error) {
            console.error('Delete Target Error:', error);
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
        const yesterday = new Date(today - 86400000);

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) compareDate.value = Utils.getISODate(yesterday);
    },

    updateComparisonDates() {
        const type = Utils.$('#comparisonType')?.value;
        const today = new Date();
        
        const dateMap = {
            'today_vs_yesterday': 86400000,
            'week_vs_week': 604800000,
            'month_vs_month': 2592000000,
            'custom': 86400000
        };

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) {
            const offset = dateMap[type] || dateMap.custom;
            compareDate.value = Utils.getISODate(new Date(today - offset));
        }
    }
};

// ==================== TAB MANAGER ====================
const TabManager = {
    switchTab(tabName) {
        Utils.$$('.tab-btn').forEach(btn => btn.classList.remove('active'));
        Utils.$$('.tab-content').forEach(content => content.classList.remove('active'));

        const activeBtn = Utils.$(`[data-tab="${tabName}"]`);
        const activeContent = Utils.$(`#${tabName}-tab`);

        if (activeBtn) activeBtn.classList.add('active');
        if (activeContent) activeContent.classList.add('active');

        AppState.currentTab = tabName;
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
        if (modalTitle) modalTitle.textContent = 'Create New Target';
        
        AppState.editingTargetId = null;

        const today = new Date();
        const nextMonth = new Date(today.getTime() + 2592000000);

        const startDate = Utils.$('#targetStartDate');
        const endDate = Utils.$('#targetEndDate');

        if (startDate) startDate.value = Utils.getISODate(today);
        if (endDate) endDate.value = Utils.getISODate(nextMonth);
    },

    close(event) {
        if (event && event.target !== event.currentTarget && !event.target.classList.contains('modal-close-btn')) {
            return;
        }
        
        const modal = Utils.$('#targetModal');
        if (modal) modal.classList.remove('active');
        
        AppState.editingTargetId = null;
    },

    async saveTarget(event) {
        event.preventDefault();

        const formData = {
            name: Utils.$('#targetName')?.value.trim(),
            type: Utils.$('#targetType')?.value,
            value: parseFloat(Utils.$('#targetValue')?.value),
            start_date: Utils.$('#targetStartDate')?.value,
            end_date: Utils.$('#targetEndDate')?.value,
            store: Utils.$('#targetStore')?.value.trim() || ''
        };

        if (!formData.name || !formData.type || !formData.value || !formData.start_date || !formData.end_date) {
            UIManager.showNotification('Please fill in all required fields', 'warning');
            return;
        }

        if (isNaN(formData.value) || formData.value <= 0) {
            UIManager.showNotification('Target value must be greater than 0', 'warning');
            return;
        }

        if (new Date(formData.end_date) < new Date(formData.start_date)) {
            UIManager.showNotification('End date must be after start date', 'warning');
            return;
        }

        AppState.incrementLoading();
        try {
            const action = AppState.editingTargetId ? 'update_target' : 'save_target';
            
            if (AppState.editingTargetId) {
                formData.id = AppState.editingTargetId;
            }

            const result = await APIService.post(action, formData);
            
            if (!result) return;

            UIManager.showNotification('Target saved successfully', 'success');
            this.close();
            
            await Promise.all([
                TargetManager.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
        } catch (error) {
            console.error('Save Target Error:', error);
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
    if (grid) grid.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">Select dates and click "Load Comparison"</p>';
    ChartManager.destroyChart('comparison');
};
window.refreshAllData = async () => {
    await App.loadAllData();
    UIManager.showNotification('Data refreshed successfully', 'success');
};

// ==================== APP INITIALIZATION ====================
const App = {
    async init() {
        console.log('🚀 Initializing Professional Sales Dashboard...');

        try {
            // Check Chart.js
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded!');
                UIManager.showNotification('Chart library not loaded. Please refresh.', 'error');
                return;
            }

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
        } catch (error) {
            console.error('❌ Initialization error:', error);
            UIManager.showNotification('Failed to initialize dashboard: ' + error.message, 'error');
        }
    },

    async loadAllData() {
        const promises = [
            DataManager.loadKPISummary(),
            DataManager.loadTrendData(30),
            TargetManager.loadTargets('all')
        ];

        const results = await Promise.allSettled(promises);
        
        results.forEach((result, index) => {
            if (result.status === 'rejected') {
                console.error(`Failed to load data [${index}]:`, result.reason);
            }
        });
    },

    setupEventListeners() {
        // Modal backdrop click
        const modal = Utils.$('#targetModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    ModalManager.close();
                }
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
                    this.loadAllData();
                }
            }
        });

        // Handle network errors
        window.addEventListener('online', () => {
            UIManager.showNotification('Connection restored', 'success');
            this.loadAllData();
        });

        window.addEventListener('offline', () => {
            UIManager.showNotification('No internet connection', 'warning');
        });
    }
};

// ==================== ANIMATIONS ====================
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
`;
document.head.appendChild(style);

// ==================== INITIALIZE ====================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => App.init());
} else {
    App.init();
}

// ==================== ERROR HANDLERS ====================
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    // Error notification disabled - check console for details
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    // Error notification disabled - check console for details
});