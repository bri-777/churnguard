// ==================== ULTRA-ACCURATE SALES ANALYTICS DASHBOARD v2.0 ====================
'use strict';

// ==================== ENHANCED CONFIGURATION ====================
const CONFIG = {
    API_BASE: 'api/sales_comparison.php',
    REQUEST_TIMEOUT: 30000,
    MAX_RETRIES: 3,
    RETRY_DELAY: 1000,
    DECIMAL_PRECISION: 2,
    PERCENTAGE_PRECISION: 1,
    CHART_COLORS: {
        primary: '#6366f1',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#06b6d4',
        purple: '#8b5cf6',
        gradient: ['#6366f1', '#8b5cf6', '#06b6d4']
    },
    CHART_OPTIONS: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: { 
                display: true, 
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 15
                }
            },
            tooltip: { 
                backgroundColor: 'rgba(0,0,0,0.9)',
                padding: 16,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                cornerRadius: 8,
                displayColors: true,
                borderColor: '#6366f1',
                borderWidth: 1
            }
        }
    }
};

// ==================== ENHANCED STATE MANAGEMENT ====================
const AppState = {
    charts: {},
    currentTab: 'trend',
    activeRequests: new Map(),
    loadingCounter: 0,
    editingTargetId: null,
    lastDataUpdate: null,
    cache: new Map(),
    
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
            try { controller.abort(); } catch(e) {}
        });
        this.activeRequests.clear();
    },

    setCache(key, value, ttl = 300000) { // 5 min default TTL
        this.cache.set(key, {
            value,
            expires: Date.now() + ttl
        });
    },

    getCache(key) {
        const item = this.cache.get(key);
        if (!item) return null;
        if (Date.now() > item.expires) {
            this.cache.delete(key);
            return null;
        }
        return item.value;
    },

    clearCache() {
        this.cache.clear();
    }
};

// ==================== ENHANCED UTILITY FUNCTIONS ====================
const Utils = {
    // Precise currency formatting with validation
    formatCurrency(value) {
        const num = parseFloat(value);
        if (!isFinite(num) || isNaN(num)) return '₱0.00';
        
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    },

    // Precise number formatting
    formatNumber(value) {
        const num = parseFloat(value);
        if (!isFinite(num) || isNaN(num)) return '0';
        
        return new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(Math.round(num));
    },

    // Enhanced percentage formatting with bounds
    formatPercentage(value, precision = CONFIG.PERCENTAGE_PRECISION) {
        const num = parseFloat(value);
        if (!isFinite(num) || isNaN(num)) return '0.0%';
        
        const bounded = Math.max(-999.9, Math.min(999.9, num));
        return bounded.toFixed(precision) + '%';
    },

    // Enhanced date formatting with timezone awareness
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        try {
            const date = new Date(dateString + 'T00:00:00'); // Force local timezone
            if (isNaN(date.getTime())) return 'Invalid Date';
            
            return new Intl.DateTimeFormat('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                timeZone: 'Asia/Manila'
            }).format(date);
        } catch (error) {
            console.error('Date format error:', error);
            return 'Invalid Date';
        }
    },

    // ISO date with timezone handling
    getISODate(date) {
        try {
            const d = date instanceof Date ? date : new Date(date);
            if (isNaN(d.getTime())) {
                return new Date().toISOString().split('T')[0];
            }
            return d.toISOString().split('T')[0];
        } catch {
            return new Date().toISOString().split('T')[0];
        }
    },

    // Enhanced datetime formatting
    formatDateTime() {
        try {
            return new Intl.DateTimeFormat('en-PH', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Asia/Manila'
            }).format(new Date());
        } catch {
            return 'N/A';
        }
    },

    // Precise percentage change calculation
    calculateChange(current, previous) {
        const curr = parseFloat(current);
        const prev = parseFloat(previous);
        
        if (!isFinite(curr) || !isFinite(prev)) return 0;
        if (prev === 0) return curr > 0 ? 100 : 0;
        if (curr === prev) return 0;
        
        return ((curr - prev) / Math.abs(prev)) * 100;
    },

    // Enhanced HTML escaping
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text || '').replace(/[&<>"']/g, m => map[m]);
    },

    // Debounce with immediate option
    debounce(func, wait, immediate = false) {
        let timeout;
        return function executedFunction(...args) {
            const context = this;
            const later = () => {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    // Throttle function for performance
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // Safe DOM selectors
    $(selector) {
        try {
            return document.querySelector(selector);
        } catch (e) {
            console.error('Selector error:', selector, e);
            return null;
        }
    },

    $$(selector) {
        try {
            return document.querySelectorAll(selector);
        } catch (e) {
            console.error('Selector error:', selector, e);
            return [];
        }
    },

    // Validate number
    isValidNumber(value) {
        const num = parseFloat(value);
        return isFinite(num) && !isNaN(num);
    }
};

// ==================== ENHANCED UI MANAGER ====================
const UIManager = {
    showNotification(message, type = 'info', duration = 4000) {
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
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'polite');
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
            <span style="font-size: 20px;" aria-hidden="true">${icons[type] || icons.info}</span>
            <span>${Utils.escapeHtml(message)}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease';
            setTimeout(() => notification.remove(), 400);
        }, duration);
    },

    showLoader() {
        let loader = Utils.$('#globalLoader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'globalLoader';
            loader.setAttribute('role', 'status');
            loader.setAttribute('aria-label', 'Loading');
            loader.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
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
        if (loader) {
            loader.style.display = 'none';
        }
    },

    updateKPICard(id, value, change) {
        const valueEl = Utils.$(`#${id}`);
        const trendBadge = Utils.$(`#${id.replace('today', '')}TrendBadge`);
        
        if (valueEl) {
            let formattedValue;
            if (id.includes('Sales')) {
                formattedValue = Utils.formatCurrency(value);
            } else if (id.includes('target')) {
                formattedValue = Utils.formatPercentage(value);
            } else {
                formattedValue = Utils.formatNumber(value);
            }
            
            // Animate value change
            valueEl.style.opacity = '0';
            setTimeout(() => {
                valueEl.textContent = formattedValue;
                valueEl.style.opacity = '1';
            }, 150);
        }
        
        if (trendBadge && Utils.isValidNumber(change)) {
            const isPositive = change >= 0;
            const isZero = change === 0;
            
            trendBadge.className = `kpi-trend-badge ${isZero ? 'neutral' : (isPositive ? '' : 'down')}`;
            const span = trendBadge.querySelector('span');
            if (span) {
                span.textContent = isZero ? '0%' : Utils.formatPercentage(Math.abs(change));
            }
        }
    },

    updateDateTime: Utils.throttle(function() {
        const dateEl = Utils.$('#currentDateTime');
        if (dateEl) {
            dateEl.textContent = Utils.formatDateTime();
        }
    }, 1000)
};

// ==================== ENHANCED API SERVICE ====================
const APIService = {
    async fetch(action, params = {}, retryCount = 0) {
        // Check cache first
        const cacheKey = `${action}_${JSON.stringify(params)}`;
        const cached = AppState.getCache(cacheKey);
        if (cached) {
            console.log('Using cached data for:', action);
            return cached;
        }

        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.append(key, String(value));
            }
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), CONFIG.REQUEST_TIMEOUT);
        const requestId = `${action}-${Date.now()}`;
        
        AppState.activeRequests.set(requestId, controller);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                signal: controller.signal,
                credentials: 'same-origin'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            // Handle authentication errors
            if (response.status === 401) {
                UIManager.showNotification('Session expired. Redirecting to login...', 'warning');
                setTimeout(() => window.location.href = '/login.php', 2000);
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            // Cache successful responses
            AppState.setCache(cacheKey, data);
            
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', action);
                return null;
            }

            // Retry logic
            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                console.log(`Retrying ${action} (attempt ${retryCount + 1}/${CONFIG.MAX_RETRIES})`);
                await new Promise(resolve => setTimeout(resolve, CONFIG.RETRY_DELAY * (retryCount + 1)));
                return this.fetch(action, params, retryCount + 1);
            }

            console.error('API Error:', error);
            throw error;
        }
    },

    async post(action, body, retryCount = 0) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), CONFIG.REQUEST_TIMEOUT);
        const requestId = `${action}-post-${Date.now()}`;
        
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

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (response.status === 401) {
                UIManager.showNotification('Session expired. Redirecting...', 'warning');
                setTimeout(() => window.location.href = '/login.php', 2000);
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            // Clear cache on mutations
            AppState.clearCache();
            
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', action);
                return null;
            }

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                await new Promise(resolve => setTimeout(resolve, CONFIG.RETRY_DELAY * (retryCount + 1)));
                return this.post(action, body, retryCount + 1);
            }

            console.error('API Error:', error);
            throw error;
        }
    }
};

// ==================== ENHANCED CHART MANAGER ====================
const ChartManager = {
    destroyChart(chartName) {
        if (AppState.charts[chartName]) {
            try {
                AppState.charts[chartName].destroy();
                delete AppState.charts[chartName];
            } catch (e) {
                console.error('Chart destroy error:', e);
            }
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
                    backgroundColor: `${CONFIG.CHART_COLORS.primary}30`,
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
                            callback: value => Utils.formatCurrency(value)
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
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
                            label: (ctx) => {
                                return 'Revenue: ' + Utils.formatCurrency(ctx.parsed.y);
                            }
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
                        }
                    }
                },
                plugins: {
                    ...CONFIG.CHART_OPTIONS.plugins,
                    tooltip: {
                        ...CONFIG.CHART_OPTIONS.plugins.tooltip,
                        callbacks: {
                            label: (ctx) => {
                                const label = ctx.dataset.label || '';
                                const value = ctx.parsed.y;
                                const metric = ctx.label;
                                const formatted = metric.includes('Sales') || metric.includes('Value') ? 
                                    Utils.formatCurrency(value) : Utils.formatNumber(value);
                                return `${label}: ${formatted}`;
                            }
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

// ==================== DATA MANAGER (Continued in next message due to length) ====================
const DataManager = {
    async loadKPISummary() {
        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('kpi_summary');
            
            if (!data) return;

            // Update KPI cards with precise values
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
                miniProgress.style.width = progress.toFixed(1) + '%';
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
                const chartContainer = Utils.$('#salesTrendChart')?.parentElement;
                if (chartContainer) {
                    chartContainer.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No data available for the selected period</p>';
                }
                const tableBody = Utils.$('#salesTrendTableBody');
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="loading-cell">No data available</td></tr>';
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
                        ` : '<span style="color:#9ca3af;">—</span>'}
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

        if (currentDate === compareDate) {
            UIManager.showNotification('Please select different dates', 'warning');
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

// ==================== ENHANCED TARGET MANAGER ====================
const TargetManager = {
    async loadTargets(filter = 'all') {
        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('get_targets', { filter });
            
            if (!data) return;

            if (data.targets) {
                this.displayTargetsGrid(data.targets);
                this.displayTargetsTable(data.targets);
            } else {
                console.warn('No targets data received');
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
            grid.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;font-size:14px;">No targets found. Create one to get started!</p>';
            return;
        }

        grid.innerHTML = targets.map(target => {
            const progress = parseFloat(target.progress || 0);
            const cappedProgress = Math.min(progress, 100);
            const statusClass = target.status === 'achieved' ? 'achieved' : 
                              target.status === 'near' ? 'near' : 'below';
            const isCurrency = target.target_type === 'sales' || target.target_type === 'avg_transaction';

            return `
                <div class="target-card-pro" data-target-id="${target.id}">
                    <div class="target-header-row">
                        <div>
                            <h4 class="target-name-pro">${Utils.escapeHtml(target.target_name)}</h4>
                            <span class="target-type-badge">${this.formatTargetType(target.target_type)}</span>
                        </div>
                    </div>
                    <div class="target-progress-section">
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill ${statusClass}" style="width:${cappedProgress.toFixed(1)}%"></div>
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
                            <button class="btn-icon-small" onclick="TargetManager.editTarget(${target.id})" title="Edit Target" aria-label="Edit ${Utils.escapeHtml(target.target_name)}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="btn-icon-small delete" onclick="TargetManager.deleteTarget(${target.id})" title="Delete Target" aria-label="Delete ${Utils.escapeHtml(target.target_name)}">
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
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No targets available</td></tr>';
            return;
        }

        tbody.innerHTML = targets.map(target => {
            const progress = parseFloat(target.progress || 0);
            const cappedProgress = Math.min(progress, 100);
            const statusClass = target.status === 'achieved' ? 'achieved' : 
                              target.status === 'near' ? 'near' : 'below';
            const statusText = target.status === 'achieved' ? 'Achieved' : 
                             target.status === 'near' ? 'Near Target' : 'Below Target';
            const isCurrency = target.target_type === 'sales' || target.target_type === 'avg_transaction';

            return `
                <tr data-target-id="${target.id}">
                    <td><strong>${Utils.escapeHtml(target.target_name)}</strong></td>
                    <td>${this.formatTargetType(target.target_type)}</td>
                    <td>
                        ${isCurrency ? Utils.formatCurrency(target.current_value) : Utils.formatNumber(target.current_value)} / 
                        ${isCurrency ? Utils.formatCurrency(target.target_value) : Utils.formatNumber(target.target_value)}
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                                <div class="progress-bar-fill ${statusClass}" style="width:${cappedProgress.toFixed(1)}%"></div>
                            </div>
                            <span style="font-weight:600;min-width:55px;font-size:13px;">${progress.toFixed(1)}%</span>
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
            
            const modalTitle = Utils.$('#modalTitle');
            if (modalTitle) modalTitle.textContent = 'Edit Target';
            
            const fields = {
                '#targetName': target.target_name || '',
                '#targetType': target.target_type || '',
                '#targetValue': target.target_value || '',
                '#targetStartDate': target.start_date || '',
                '#targetEndDate': target.end_date || '',
                '#targetStore': target.store || ''
            };

            Object.entries(fields).forEach(([selector, value]) => {
                const el = Utils.$(selector);
                if (el) el.value = value;
            });
            
            ModalManager.open();
        } catch (error) {
            console.error('Edit Target Error:', error);
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
            const result = await APIService.fetch('delete_target', { id });
            
            if (!result) return;

            UIManager.showNotification('Target deleted successfully', 'success');
            
            // Reload data
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

// ==================== ENHANCED DATE MANAGER ====================
const DateManager = {
    setDefaultDates() {
        const today = new Date();
        const yesterday = new Date(today - 86400000);

        const fields = {
            '#currentDate': today,
            '#compareDate': yesterday
        };

        Object.entries(fields).forEach(([selector, date]) => {
            const el = Utils.$(selector);
            if (el) el.value = Utils.getISODate(date);
        });
    },

    updateComparisonDates() {
        const type = Utils.$('#comparisonType')?.value;
        const today = new Date();
        
        const dateOffsets = {
            'today_vs_yesterday': 86400000,
            'week_vs_week': 604800000,
            'month_vs_month': 2592000000,
            'custom': 86400000
        };

        const currentEl = Utils.$('#currentDate');
        const compareEl = Utils.$('#compareDate');

        if (currentEl) currentEl.value = Utils.getISODate(today);
        if (compareEl) {
            const offset = dateOffsets[type] || dateOffsets.custom;
            compareEl.value = Utils.getISODate(new Date(today - offset));
        }
    }
};

// ==================== ENHANCED TAB MANAGER ====================
const TabManager = {
    switchTab(tabName) {
        // Update tab buttons
        Utils.$$('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
        });
        
        // Update tab content
        Utils.$$('.tab-content').forEach(content => {
            content.classList.remove('active');
            content.setAttribute('aria-hidden', 'true');
        });

        const activeBtn = Utils.$(`[data-tab="${tabName}"]`);
        const activeContent = Utils.$(`#${tabName}-tab`);

        if (activeBtn) {
            activeBtn.classList.add('active');
            activeBtn.setAttribute('aria-selected', 'true');
        }
        
        if (activeContent) {
            activeContent.classList.add('active');
            activeContent.setAttribute('aria-hidden', 'false');
        }

        AppState.currentTab = tabName;
    }
};

// ==================== ENHANCED MODAL MANAGER ====================
const ModalManager = {
    open() {
        const modal = Utils.$('#targetModal');
        const form = Utils.$('#targetForm');
        
        if (modal) {
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            
            // Focus first input
            setTimeout(() => {
                const firstInput = modal.querySelector('input:not([type="hidden"])');
                if (firstInput) firstInput.focus();
            }, 100);
        }
        
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

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    },

    close(event) {
        if (event && event.target !== event.currentTarget && !event.target.classList.contains('modal-close-btn')) {
            return;
        }
        
        const modal = Utils.$('#targetModal');
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
        
        AppState.editingTargetId = null;
        
        // Restore body scroll
        document.body.style.overflow = '';
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

        // Validate
        if (!formData.name || formData.name.length < 3) {
            UIManager.showNotification('Target name must be at least 3 characters', 'warning');
            return;
        }

        if (!formData.type) {
            UIManager.showNotification('Please select a target type', 'warning');
            return;
        }

        if (!Utils.isValidNumber(formData.value) || formData.value <= 0) {
            UIManager.showNotification('Target value must be greater than 0', 'warning');
            return;
        }

        if (!formData.start_date || !formData.end_date) {
            UIManager.showNotification('Please select both start and end dates', 'warning');
            return;
        }

        if (new Date(formData.end_date) < new Date(formData.start_date)) {
            UIManager.showNotification('End date must be after or equal to start date', 'warning');
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
    if (grid) {
        grid.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">Select dates and click "Analyze" to compare periods</p>';
    }
    ChartManager.destroyChart('comparison');
};
window.refreshAllData = async () => {
    AppState.clearCache();
    await App.loadAllData();
    UIManager.showNotification('Data refreshed successfully', 'success');
};

// ==================== ENHANCED APP INITIALIZATION ====================
const App = {
    async init() {
        console.log('🚀 Initializing Ultra-Accurate Sales Dashboard v2.0...');

        try {
            // Check dependencies
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded!');
                UIManager.showNotification('Chart library not loaded. Please refresh the page.', 'error');
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

            // Setup auto-refresh
            this.setupAutoRefresh();

            console.log('✅ Dashboard initialized successfully');
            
        } catch (error) {
            console.error('❌ Initialization error:', error);
            UIManager.showNotification('Failed to initialize dashboard: ' + error.message, 'error');
        }
    },

    async loadAllData() {
        console.log('Loading all data...');
        
        const promises = [
            DataManager.loadKPISummary().catch(e => console.error('KPI load failed:', e)),
            DataManager.loadTrendData(30).catch(e => console.error('Trend load failed:', e)),
            TargetManager.loadTargets('all').catch(e => console.error('Targets load failed:', e))
        ];

        await Promise.allSettled(promises);
        
        console.log('Data loading complete');
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

        // Visibility change - refresh data when tab becomes visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && AppState.lastDataUpdate) {
                const timeSinceUpdate = Date.now() - AppState.lastDataUpdate.getTime();
                if (timeSinceUpdate > 300000) { // 5 minutes
                    console.log('Auto-refreshing data after visibility change');
                    this.loadAllData();
                }
            }
        });

        // Online/offline events
        window.addEventListener('online', () => {
            UIManager.showNotification('Connection restored', 'success');
            this.loadAllData();
        });

        window.addEventListener('offline', () => {
            UIManager.showNotification('No internet connection', 'warning', 6000);
        });

        // Prevent form submission on Enter key
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                }
            });
        });
    },

    setupAutoRefresh() {
        // Auto-refresh KPI every 2 minutes
        setInterval(() => {
            if (!document.hidden) {
                console.log('Auto-refreshing KPI...');
                DataManager.loadKPISummary();
            }
        }, 120000);
    }
};

// ==================== ENHANCED ANIMATIONS ====================
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
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Smooth transitions */
    .kpi-value-display, .progress-bar-fill {
        transition: all 0.3s ease;
    }
    
    /* Accessibility improvements */
    *:focus-visible {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }
`;
document.head.appendChild(style);

// ==================== INITIALIZE ====================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => App.init());
} else {
    App.init();
}

// ==================== ENHANCED ERROR HANDLERS ====================
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    if (event.error?.message !== 'ResizeObserver loop limit exceeded') {
        UIManager.showNotification('An error occurred. Please refresh if issues persist.', 'error');
    }
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    if (event.reason?.message && !event.reason.message.includes('Unauthorized')) {
        UIManager.showNotification('A network error occurred. Please check your connection.', 'error');
    }
});

// ==================== PERFORMANCE MONITORING ====================
if ('PerformanceObserver' in window) {
    try {
        const perfObserver = new PerformanceObserver((entryList) => {
            for (const entry of entryList.getEntries()) {
                if (entry.duration > 1000) {
                    console.warn(`Slow operation detected: ${entry.name} took ${entry.duration.toFixed(2)}ms`);
                }
            }
        });
        perfObserver.observe({ entryTypes: ['measure'] });
    } catch (e) {
        console.log('Performance monitoring not available');
    }
}

console.log('📊 Sales Analytics Dashboard v2.0 loaded successfully');

// ==================== EXPORT & REPORTING FEATURES ====================

// Add these functions to your sales_comparison.js file

// ==================== EXPORT MANAGER ====================
const ExportManager = {
    // Export targets to CSV
    async exportTargetsCSV() {
        UIManager.showLoader();
        try {
            const data = await APIService.fetch('get_targets', { filter: 'all' });
            
            if (!data.targets || data.targets.length === 0) {
                UIManager.showNotification('No targets to export', 'warning');
                return;
            }

            // Create CSV content
            const headers = ['Target Name', 'Type', 'Target Value', 'Current Value', 'Progress %', 'Status', 'Start Date', 'End Date', 'Store'];
            const rows = data.targets.map(target => [
                target.target_name || '',
                this.formatTargetType(target.target_type),
                target.target_value || 0,
                target.current_value || 0,
                (target.progress || 0).toFixed(2),
                target.status || '',
                target.start_date || '',
                target.end_date || '',
                target.store || 'All Stores'
            ]);

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
            ].join('\n');

            // Download file
            this.downloadFile(csvContent, `targets_${this.getDateString()}.csv`, 'text/csv');
            UIManager.showNotification('Targets exported successfully', 'success');
        } catch (error) {
            console.error('Export error:', error);
            UIManager.showNotification('Failed to export targets', 'error');
        } finally {
            UIManager.hideLoader();
        }
    },

    // Export sales data to CSV
    async exportSalesDataCSV(days = 30) {
        UIManager.showLoader();
        try {
            const data = await APIService.fetch('trend_data', { days });
            
            if (!data.trend_data || data.trend_data.length === 0) {
                UIManager.showNotification('No sales data to export', 'warning');
                return;
            }

            const headers = ['Date', 'Sales Revenue', 'Transactions', 'Customer Traffic', 'Avg Transaction Value'];
            const rows = data.trend_data.map(item => {
                const sales = parseFloat(item.sales_volume || 0);
                const receipts = parseInt(item.receipt_count || 0);
                const avgValue = receipts > 0 ? (sales / receipts).toFixed(2) : 0;
                
                return [
                    item.date || '',
                    sales.toFixed(2),
                    receipts,
                    item.customer_traffic || 0,
                    avgValue
                ];
            });

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.join(','))
            ].join('\n');

            this.downloadFile(csvContent, `sales_data_${this.getDateString()}.csv`, 'text/csv');
            UIManager.showNotification('Sales data exported successfully', 'success');
        } catch (error) {
            console.error('Export error:', error);
            UIManager.showNotification('Failed to export sales data', 'error');
        } finally {
            UIManager.hideLoader();
        }
    },

    // Export dashboard as PDF (using browser print)
    exportDashboardPDF() {
        // Hide non-essential elements
        const elementsToHide = ['.header-actions', '.btn', '.modal', '.tab-navigation'];
        elementsToHide.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => el.style.display = 'none');
        });

        // Trigger print dialog
        window.print();

        // Restore elements after print
        setTimeout(() => {
            elementsToHide.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => el.style.display = '');
            });
        }, 1000);

        UIManager.showNotification('Prepare to save as PDF from print dialog', 'info');
    },

    // Generate comprehensive report
    async generateReport(period = 'month') {
        UIManager.showLoader();
        try {
            const days = period === 'week' ? 7 : period === 'month' ? 30 : 90;
            
            const [kpiData, trendData, targetsData] = await Promise.all([
                APIService.fetch('kpi_summary'),
                APIService.fetch('trend_data', { days }),
                APIService.fetch('get_targets', { filter: 'all' })
            ]);

            const report = {
                generated_at: new Date().toISOString(),
                period: period,
                summary: {
                    total_sales: kpiData.today_sales || 0,
                    total_customers: kpiData.today_customers || 0,
                    total_transactions: kpiData.today_transactions || 0,
                    avg_transaction: kpiData.today_avg_transaction || 0
                },
                trends: trendData.trend_data || [],
                targets: targetsData.targets || []
            };

            // Create formatted report
            const reportText = this.formatReportText(report);
            this.downloadFile(reportText, `sales_report_${this.getDateString()}.txt`, 'text/plain');
            
            UIManager.showNotification('Report generated successfully', 'success');
        } catch (error) {
            console.error('Report generation error:', error);
            UIManager.showNotification('Failed to generate report', 'error');
        } finally {
            UIManager.hideLoader();
        }
    },

    // Format report as text
    formatReportText(report) {
        let text = `SALES ANALYTICS REPORT\n`;
        text += `Generated: ${new Date(report.generated_at).toLocaleString()}\n`;
        text += `Period: ${report.period}\n`;
        text += `${'='.repeat(60)}\n\n`;

        text += `SUMMARY\n`;
        text += `${'-'.repeat(60)}\n`;
        text += `Total Sales: ${Utils.formatCurrency(report.summary.total_sales)}\n`;
        text += `Total Customers: ${Utils.formatNumber(report.summary.total_customers)}\n`;
        text += `Total Transactions: ${Utils.formatNumber(report.summary.total_transactions)}\n`;
        text += `Avg Transaction Value: ${Utils.formatCurrency(report.summary.avg_transaction)}\n\n`;

        if (report.targets && report.targets.length > 0) {
            text += `TARGETS\n`;
            text += `${'-'.repeat(60)}\n`;
            report.targets.forEach(target => {
                text += `${target.target_name}: ${target.progress?.toFixed(1)}% (${target.status})\n`;
            });
            text += `\n`;
        }

        if (report.trends && report.trends.length > 0) {
            text += `SALES TREND (Last ${report.trends.length} days)\n`;
            text += `${'-'.repeat(60)}\n`;
            report.trends.forEach(day => {
                text += `${day.date}: ${Utils.formatCurrency(day.sales_volume)} (${day.receipt_count} transactions)\n`;
            });
        }

        text += `\n${'='.repeat(60)}\n`;
        text += `End of Report`;

        return text;
    },

    // Helper: Download file
    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    },

    // Helper: Get date string for filename
    getDateString() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    },

    // Helper: Format target type
    formatTargetType(type) {
        const types = {
            'sales': 'Sales Revenue',
            'customers': 'Customer Traffic',
            'transactions': 'Transactions',
            'avg_transaction': 'Avg Transaction Value'
        };
        return types[type] || type;
    }
};

// ==================== PRINT MANAGER ====================
const PrintManager = {
    // Print current view
    printCurrentView() {
        window.print();
    },

    // Print specific section
    printSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) {
            UIManager.showNotification('Section not found', 'error');
            return;
        }

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Print - ${sectionId}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                    th { background: #f3f4f6; font-weight: 600; }
                    h1, h2, h3 { color: #374151; }
                    @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <h1>Sales Analytics Report</h1>
                <p>Generated: ${new Date().toLocaleString()}</p>
                <hr>
                ${section.innerHTML}
                <script>window.print(); window.close();</script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
};

// ==================== UPDATE GLOBAL FUNCTIONS ====================

// Replace the existing exportTargets function
window.exportTargets = () => {
    const menu = document.createElement('div');
    menu.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        z-index: 10000;
        min-width: 300px;
    `;
    
    menu.innerHTML = `
        <h3 style="margin: 0 0 20px 0; color: #374151;">Export Options</h3>
        <button onclick="ExportManager.exportTargetsCSV(); this.closest('div').remove();" 
                style="width: 100%; padding: 12px; margin-bottom: 10px; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
            📊 Export Targets (CSV)
        </button>
        <button onclick="ExportManager.exportSalesDataCSV(30); this.closest('div').remove();" 
                style="width: 100%; padding: 12px; margin-bottom: 10px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
            📈 Export Sales Data (CSV)
        </button>
        <button onclick="ExportManager.generateReport('month'); this.closest('div').remove();" 
                style="width: 100%; padding: 12px; margin-bottom: 10px; background: #f59e0b; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
            📄 Generate Report (TXT)
        </button>
        <button onclick="ExportManager.exportDashboardPDF(); this.closest('div').remove();" 
                style="width: 100%; padding: 12px; margin-bottom: 10px; background: #8b5cf6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
            🖨️ Print/Save as PDF
        </button>
        <button onclick="this.closest('div').remove();" 
                style="width: 100%; padding: 12px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
            Cancel
        </button>
    `;
    
    document.body.appendChild(menu);
};

// ==================== EMAIL REPORT FEATURE ====================
const EmailManager = {
    async sendEmailReport(recipientEmail) {
        UIManager.showLoader();
        try {
            const report = await ExportManager.generateReport('month');
            
            // This would need a backend endpoint to send emails
            const response = await APIService.post('send_email_report', {
                recipient: recipientEmail,
                report: report
            });

            UIManager.showNotification('Report sent successfully', 'success');
        } catch (error) {
            console.error('Email send error:', error);
            UIManager.showNotification('Failed to send email report', 'error');
        } finally {
            UIManager.hideLoader();
        }
    },

    showEmailDialog() {
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            z-index: 10000;
            min-width: 400px;
        `;
        
        dialog.innerHTML = `
            <h3 style="margin: 0 0 20px 0; color: #374151;">Email Report</h3>
            <input type="email" id="emailRecipient" placeholder="recipient@example.com" 
                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            <div style="display: flex; gap: 12px;">
                <button onclick="EmailManager.sendEmailReport(document.getElementById('emailRecipient').value); this.closest('div').closest('div').remove();" 
                        style="flex: 1; padding: 12px; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Send Report
                </button>
                <button onclick="this.closest('div').closest('div').remove();" 
                        style="flex: 1; padding: 12px; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Cancel
                </button>
            </div>
        `;
        
        document.body.appendChild(dialog);
    }
};

// Add email button to export menu
window.showEmailDialog = () => EmailManager.showEmailDialog();

console.log('✅ Export & Reporting features loaded successfully');