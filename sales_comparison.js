// ==================== PROFESSIONAL SALES ANALYTICS DASHBOARD ====================
'use strict';

// ==================== CONFIGURATION ====================
const CONFIG = {
    API_BASE: 'api/sales_comparison.php',
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
    
    incrementLoading() {
        this.loadingCounter++;
        UIManager.showLoader();
    },
    
    decrementLoading() {
        this.loadingCounter = Math.max(0, this.loadingCounter - 1);
        if (this.loadingCounter === 0) {
            UIManager.hideLoader();
        }
    }
};

// ==================== UTILITY FUNCTIONS ====================
const Utils = {
    formatCurrency(value) {
        if (value == null || isNaN(value)) return '₱0.00';
        return '₱' + parseFloat(value).toLocaleString('en-PH', { 
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
        const date = new Date(dateString);
        return date.toLocaleDateString('en-PH', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    },

    getISODate(date) {
        if (!(date instanceof Date)) date = new Date(date);
        return date.toISOString().split('T')[0];
    },

    formatDateTime() {
        const now = new Date();
        return now.toLocaleDateString('en-PH', { 
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    calculateChange(current, previous) {
        if (!previous || previous === 0) return 0;
        return ((current - previous) / previous) * 100;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
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
            <span style="font-size: 20px;">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
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
            valueEl.textContent = id.includes('Sales') || id.includes('target') ? 
                (id.includes('target') ? Utils.formatPercentage(value) : Utils.formatCurrency(value)) : 
                Utils.formatNumber(value);
        }
        
        if (trendBadge && change !== undefined) {
            const isPositive = change >= 0;
            trendBadge.className = `kpi-trend-badge ${isPositive ? '' : 'down'}`;
            trendBadge.querySelector('span').textContent = Utils.formatPercentage(Math.abs(change));
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
    async fetch(action, params = {}) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined) {
                url.searchParams.append(key, value);
            }
        });

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    async post(action, body) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);

        try {
            const response = await fetch(url.toString(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
};

// ==================== CHART MANAGER ====================
const ChartManager = {
    createSalesTrendChart(data) {
        const ctx = Utils.$('#salesTrendChart');
        if (!ctx) return;

        if (AppState.charts.salesTrend) {
            AppState.charts.salesTrend.destroy();
        }

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
        if (!ctx) return;

        if (AppState.charts.comparison) {
            AppState.charts.comparison.destroy();
        }

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
                        beginAtZero: true
                    }
                }
            }
        });
    },

    updateTrendChart() {
        const period = Utils.$('#trendPeriod')?.value || 30;
        DataManager.loadTrendData(period);
    },

    updateComparisonChart() {
        // Will be implemented with comparison data
    }
};

// ==================== DATA MANAGER ====================
const DataManager = {
    async loadKPISummary() {
        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('kpi_summary');
            
            UIManager.updateKPICard('todaySales', data.today_sales, data.sales_change);
            UIManager.updateKPICard('todayCustomers', data.today_customers, data.customers_change);
            UIManager.updateKPICard('todayTransactions', data.today_transactions, data.transactions_change);
            UIManager.updateKPICard('targetAchievement', data.target_achievement);
            
            const targetStatus = Utils.$('#targetStatus');
            if (targetStatus) {
                targetStatus.textContent = data.target_status;
            }
            
            const miniProgress = Utils.$('#targetMiniProgress');
            if (miniProgress) {
                miniProgress.style.width = Math.min(data.target_achievement, 100) + '%';
            }

        } catch (error) {
            UIManager.showNotification('Failed to load KPI data', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async loadTrendData(days = 30) {
        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('trend_data', { days });
            
            if (data.trend_data && data.trend_data.length > 0) {
                ChartManager.createSalesTrendChart(data.trend_data);
                this.populateTrendTable(data.trend_data);
            }
        } catch (error) {
            UIManager.showNotification('Failed to load trend data', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    populateTrendTable(trendData) {
        const tbody = Utils.$('#salesTrendTableBody');
        if (!tbody) return;

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
            
            if (data.comparison) {
                this.displayComparisonResults(data.comparison);
                ChartManager.createComparisonChart(data.comparison);
                UIManager.showNotification('Comparison loaded successfully', 'success');
            }
        } catch (error) {
            UIManager.showNotification('Failed to load comparison', 'error');
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
            
            return `
                <div class="comparison-metric-card">
                    <div class="metric-name">${Utils.escapeHtml(item.metric)}</div>
                    <div class="metric-values">
                        <span class="metric-current">${item.metric.includes('Sales') || item.metric.includes('Value') ? 
                            Utils.formatCurrency(item.current) : 
                            Utils.formatNumber(item.current)
                        }</span>
                        <span class="metric-previous">vs ${item.metric.includes('Sales') || item.metric.includes('Value') ? 
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
            
            if (data.targets) {
                this.displayTargetsGrid(data.targets);
                this.displayTargetsTable(data.targets);
            }
        } catch (error) {
            UIManager.showNotification('Failed to load targets', 'error');
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
                        <button class="btn-icon-small" onclick="TargetManager.editTarget(${target.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="btn-icon-small delete" onclick="TargetManager.deleteTarget(${target.id})">
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
            
            openTargetModal();
        } catch (error) {
            UIManager.showNotification('Failed to load target', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async deleteTarget(id) {
        if (!confirm('Are you sure you want to delete this target?')) return;

        AppState.incrementLoading();
        try {
            await APIService.fetch('delete_target', { id });
            
            UIManager.showNotification('Target deleted successfully', 'success');
            await Promise.all([
                this.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
        } catch (error) {
            UIManager.showNotification('Failed to delete target', 'error');
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
            'today_vs_yesterday': today - 86400000,
            'week_vs_week': today - 604800000,
            'month_vs_month': today - 2592000000,
            'custom': today - 86400000
        };

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) compareDate.value = Utils.getISODate(new Date(dateMap[type] || dateMap.custom));
    }
};

// ==================== TAB MANAGER ====================
const TabManager = {
    switchTab(tabName) {
        Utils.$$('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        Utils.$$('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

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
        
        if (modal) {
            modal.classList.add('active');
        }
        
        if (form) form.reset();
        
        Utils.$('#modalTitle').textContent = 'Create New Target';
        AppState.editingTargetId = null;

        const today = new Date();
        const nextMonth = new Date(today.getTime() + 2592000000);

        Utils.$('#targetStartDate').value = Utils.getISODate(today);
        Utils.$('#targetEndDate').value = Utils.getISODate(nextMonth);
    },

    close(event) {
        if (event && event.target !== event.currentTarget && !event.target.classList.contains('modal-close-btn')) {
            return;
        }
        
        const modal = Utils.$('#targetModal');
        if (modal) {
            modal.classList.remove('active');
        }
        
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

        if (formData.value <= 0) {
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

            await APIService.post(action, formData);
            
            UIManager.showNotification('Target saved successfully', 'success');
            ModalManager.close();
            
            await Promise.all([
                TargetManager.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
        } catch (error) {
            UIManager.showNotification('Failed to save target', 'error');
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
window.updateComparisonChart = () => ChartManager.updateComparisonChart();
window.resetComparison = () => {
    DateManager.setDefaultDates();
    Utils.$('.comparison-grid').innerHTML = '';
};
window.exportTargets = () => {
    UIManager.showNotification('Export feature coming soon', 'info');
};
window.toggleNotifications = () => {
    UIManager.showNotification('Notifications feature coming soon', 'info');
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
            UIManager.showNotification('Failed to initialize dashboard', 'error');
        }
    },

    async loadAllData() {
        await Promise.allSettled([
            DataManager.loadKPISummary(),
            DataManager.loadTrendData(30),
            TargetManager.loadTargets('all')
        ]);
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
            if (!document.hidden) {
                this.loadAllData();
            }
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
    UIManager.showNotification('An error occurred. Please refresh the page.', 'error');
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    UIManager.showNotification('A network error occurred.', 'error');
});