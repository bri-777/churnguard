// Sales Comparison & Target Tracking - Enterprise Grade Version
'use strict';

// ==================== CONFIGURATION ====================
const CONFIG = {
    DATES: {
        ONE_DAY: 86400000,
        ONE_WEEK: 604800000,
        ONE_MONTH: 2592000000
    },
    CHART: {
        COLORS: {
            primary: '#4f46e5',
            primaryLight: 'rgba(79, 70, 229, 0.1)',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            purple: '#8b5cf6',
            gradient1: 'rgba(79, 70, 229, 0.8)',
            gradient2: 'rgba(79, 70, 229, 0.2)'
        },
        OPTIONS: {
            animation: {
                duration: 750,
                easing: 'easeInOutQuart'
            },
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 0
        },
        DEFAULT_HEIGHT: 300,
        DEFAULT_ASPECT_RATIO: 2
    },
    API: {
        TIMEOUT: 15000,
        RETRY_ATTEMPTS: 3,
        RETRY_DELAY: 1000,
        ENDPOINTS: {
            kpiSummary: 'api/sales_comparison.php?action=kpi_summary',
            compare: 'api/sales_comparison.php?action=compare',
            targets: 'api/sales_comparison.php?action=get_targets',
            saveTarget: 'api/sales_comparison.php?action=save_target',
            deleteTarget: 'api/sales_comparison.php?action=delete_target',
            trendData: 'api/sales_comparison.php?action=trend_data'
        }
    },
    UI: {
        NOTIFICATION_DURATION: 4000,
        DEBOUNCE_DELAY: 300,
        LOADING_MIN_DISPLAY: 500
    }
};

// ==================== STATE MANAGEMENT ====================
const AppState = {
    charts: {},
    activeRequests: new Map(),
    resizeObservers: new Map(),
    isInitialized: false,
    loadingCounter: 0,
    currentFilter: 'all',
    
    incrementLoading() {
        this.loadingCounter++;
        UIManager.updateLoadingState(true);
    },
    
    decrementLoading() {
        this.loadingCounter = Math.max(0, this.loadingCounter - 1);
        if (this.loadingCounter === 0) {
            UIManager.updateLoadingState(false);
        }
    },
    
    reset() {
        this.charts = {};
        this.activeRequests.clear();
        this.resizeObservers.forEach(observer => observer.disconnect());
        this.resizeObservers.clear();
        this.loadingCounter = 0;
    }
};

// ==================== UTILITY FUNCTIONS ====================
const Utils = {
    /**
     * Format value as Philippine Peso currency
     */
    formatCurrency(value) {
        if (value === null || value === undefined || isNaN(value)) return '₱0.00';
        const num = parseFloat(value);
        return '₱' + num.toLocaleString('en-PH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    },

    /**
     * Format value as number with proper locale
     */
    formatNumber(value) {
        if (value === null || value === undefined || isNaN(value)) return '0';
        return parseInt(value).toLocaleString('en-PH');
    },

    /**
     * Format date string to readable format
     */
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
        } catch (error) {
            console.error('Date formatting error:', error);
            return 'Invalid Date';
        }
    },

    /**
     * Get ISO date string from Date object
     */
    getISODate(date) {
        if (!(date instanceof Date)) date = new Date(date);
        return date.toISOString().split('T')[0];
    },

    /**
     * Format target type to readable string
     */
    formatTargetType(type) {
        const types = {
            'sales': 'Sales Revenue',
            'customers': 'Customer Traffic',
            'transactions': 'Transaction Count',
            'avg_transaction': 'Avg Transaction Value'
        };
        return types[type] || type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Debounce function execution
     */
    debounce(func, wait = CONFIG.UI.DEBOUNCE_DELAY) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function execution
     */
    throttle(func, limit = 100) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Deep clone object
     */
    deepClone(obj) {
        if (obj === null || typeof obj !== 'object') return obj;
        return JSON.parse(JSON.stringify(obj));
    },

    /**
     * Validate required form fields
     */
    validateFormData(formData, requiredFields) {
        const errors = [];
        requiredFields.forEach(field => {
            if (!formData[field] || (typeof formData[field] === 'string' && !formData[field].trim())) {
                errors.push(`${field.replace(/_/g, ' ')} is required`);
            }
        });
        return errors;
    },

    /**
     * Safe element selector
     */
    $(selector) {
        return document.querySelector(selector);
    },

    /**
     * Safe multiple element selector
     */
    $$(selector) {
        return document.querySelectorAll(selector);
    }
};

// ==================== UI MANAGER ====================
const UIManager = {
    /**
     * Show notification toast
     */
    showNotification(message, type = 'info', duration = CONFIG.UI.NOTIFICATION_DURATION) {
        const existingNotification = Utils.$('.notification-toast');
        if (existingNotification) {
            existingNotification.remove();
        }

        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        const notification = document.createElement('div');
        notification.className = 'notification-toast';
        notification.innerHTML = `
            <span class="notification-icon">${icons[type] || icons.info}</span>
            <span class="notification-message">${Utils.escapeHtml(message)}</span>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            min-width: 300px;
            max-width: 500px;
            padding: 16px 20px;
            background: ${colors[type] || colors.info};
            color: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            animation: slideInRight 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-in-out';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    },

    /**
     * Update global loading state
     */
    updateLoadingState(isLoading) {
        const loader = Utils.$('#globalLoader') || this.createLoader();
        
        if (isLoading) {
            loader.style.display = 'flex';
            document.body.style.cursor = 'wait';
        } else {
            setTimeout(() => {
                loader.style.display = 'none';
                document.body.style.cursor = 'default';
            }, CONFIG.UI.LOADING_MIN_DISPLAY);
        }
    },

    /**
     * Create global loader element
     */
    createLoader() {
        let loader = Utils.$('#globalLoader');
        if (loader) return loader;

        loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(2px);
        `;
        loader.innerHTML = `
            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                <div class="spinner" style="border: 4px solid #f3f4f6; border-top: 4px solid #4f46e5; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite;"></div>
            </div>
        `;
        document.body.appendChild(loader);
        return loader;
    },

    /**
     * Update change indicator with animation
     */
    updateChangeIndicator(elementId, value) {
        const element = Utils.$(`#${elementId}`);
        if (!element) return;

        const sign = value >= 0 ? '+' : '';
        const displayValue = sign + value.toFixed(1) + '%';
        const className = 'kpi-change ' + (value >= 0 ? 'positive' : 'negative');

        if (element.textContent !== displayValue) {
            element.style.opacity = '0';
            setTimeout(() => {
                element.textContent = displayValue;
                element.className = className;
                element.style.opacity = '1';
                element.style.transition = 'opacity 0.3s ease';
            }, 150);
        }
    },

    /**
     * Safe update text content
     */
    updateTextContent(elementId, value) {
        const element = Utils.$(`#${elementId}`);
        if (element && element.textContent !== value) {
            element.textContent = value;
        }
    }
};

// ==================== API SERVICE ====================
const APIService = {
    /**
     * Fetch with timeout and retry logic
     */
    async fetchWithRetry(url, options = {}, retries = CONFIG.API.RETRY_ATTEMPTS) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), CONFIG.API.TIMEOUT);

        // Cancel previous request to same endpoint
        if (AppState.activeRequests.has(url)) {
            AppState.activeRequests.get(url).abort();
        }
        AppState.activeRequests.set(url, controller);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });

            clearTimeout(timeout);
            AppState.activeRequests.delete(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            return data;

        } catch (error) {
            clearTimeout(timeout);
            AppState.activeRequests.delete(url);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', url);
                return null;
            }

            if (retries > 0 && error.message.includes('Failed to fetch')) {
                console.log(`Retrying request (${retries} attempts left):`, url);
                await new Promise(resolve => setTimeout(resolve, CONFIG.API.RETRY_DELAY));
                return this.fetchWithRetry(url, options, retries - 1);
            }

            throw error;
        }
    },

    /**
     * GET request
     */
    async get(url) {
        return this.fetchWithRetry(url);
    },

    /**
     * POST request
     */
    async post(url, body) {
        return this.fetchWithRetry(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }
};

// ==================== CHART MANAGER ====================
const ChartManager = {
    /**
     * Initialize chart with proper sizing and responsiveness
     */
    initChart(canvasId, config) {
        const canvas = Utils.$(`#${canvasId}`);
        if (!canvas) {
            console.warn(`Canvas not found: ${canvasId}`);
            return null;
        }

        // Ensure Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js library not loaded');
            return null;
        }

        // Setup proper canvas container
        this.setupCanvasContainer(canvas);

        // Destroy existing chart
        this.destroyChart(canvasId);

        try {
            // Create chart with enhanced config
            const enhancedConfig = this.enhanceConfig(config);
            const chart = new Chart(canvas, enhancedConfig);
            AppState.charts[canvasId] = chart;

            // Setup resize observer for responsive behavior
            this.setupResizeObserver(canvasId, canvas);

            return chart;

        } catch (error) {
            console.error(`Error creating chart ${canvasId}:`, error);
            return null;
        }
    },

    /**
     * Setup canvas container for proper sizing
     */
    setupCanvasContainer(canvas) {
        const container = canvas.parentElement;
        if (!container) return;

        // Set container position if not set
        const position = window.getComputedStyle(container).position;
        if (position === 'static') {
            container.style.position = 'relative';
        }

        // Set container height if not set
        if (!container.style.height) {
            container.style.height = CONFIG.CHART.DEFAULT_HEIGHT + 'px';
        }

        // Set canvas to fill container
        canvas.style.maxWidth = '100%';
        canvas.style.maxHeight = '100%';
    },

    /**
     * Enhance chart config with defaults and optimizations
     */
    enhanceConfig(config) {
        const enhanced = Utils.deepClone(config);

        // Merge default options
        enhanced.options = {
            ...CONFIG.CHART.OPTIONS,
            ...enhanced.options
        };

        // Ensure plugins exist
        enhanced.options.plugins = enhanced.options.plugins || {};

        // Add default tooltip
        enhanced.options.plugins.tooltip = {
            enabled: true,
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: '#4f46e5',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            ...enhanced.options.plugins.tooltip
        };

        return enhanced;
    },

    /**
     * Setup resize observer for chart responsiveness
     */
    setupResizeObserver(chartId, canvas) {
        // Disconnect existing observer
        if (AppState.resizeObservers.has(chartId)) {
            AppState.resizeObservers.get(chartId).disconnect();
        }

        const resizeHandler = Utils.throttle(() => {
            const chart = AppState.charts[chartId];
            if (chart) {
                chart.resize();
            }
        }, 250);

        const observer = new ResizeObserver(resizeHandler);
        observer.observe(canvas.parentElement);
        AppState.resizeObservers.set(chartId, observer);
    },

    /**
     * Update existing chart data
     */
    updateChart(chartId, newData) {
        const chart = AppState.charts[chartId];
        if (!chart) return false;

        try {
            chart.data.labels = newData.labels;
            chart.data.datasets = newData.datasets;
            chart.update('active');
            return true;
        } catch (error) {
            console.error(`Error updating chart ${chartId}:`, error);
            return false;
        }
    },

    /**
     * Destroy chart and cleanup
     */
    destroyChart(chartId) {
        if (AppState.charts[chartId]) {
            AppState.charts[chartId].destroy();
            delete AppState.charts[chartId];
        }

        if (AppState.resizeObservers.has(chartId)) {
            AppState.resizeObservers.get(chartId).disconnect();
            AppState.resizeObservers.delete(chartId);
        }
    },

    /**
     * Destroy all charts
     */
    destroyAll() {
        Object.keys(AppState.charts).forEach(chartId => {
            this.destroyChart(chartId);
        });
    }
};

// ==================== DATA MANAGERS ====================
const DateManager = {
    setDefaultDates() {
        const today = new Date();
        const yesterday = new Date(today - CONFIG.DATES.ONE_DAY);

        const currentDateInput = Utils.$('#currentDate');
        const compareDateInput = Utils.$('#compareDate');

        if (currentDateInput) currentDateInput.value = Utils.getISODate(today);
        if (compareDateInput) compareDateInput.value = Utils.getISODate(yesterday);
    },

    updateComparisonDates() {
        const typeSelect = Utils.$('#comparisonType');
        const currentDateInput = Utils.$('#currentDate');
        const compareDateInput = Utils.$('#compareDate');

        if (!typeSelect || !currentDateInput || !compareDateInput) return;

        const type = typeSelect.value;
        const today = new Date();
        currentDateInput.value = Utils.getISODate(today);

        const dateMap = {
            'today_vs_date': today - CONFIG.DATES.ONE_DAY,
            'week_vs_range': today - CONFIG.DATES.ONE_WEEK,
            'month_vs_period': today - CONFIG.DATES.ONE_MONTH
        };

        compareDateInput.value = Utils.getISODate(new Date(dateMap[type] || dateMap['today_vs_date']));
    }
};

const KPIManager = {
    async loadSummary() {
        AppState.incrementLoading();
        try {
            const data = await APIService.get(CONFIG.API.ENDPOINTS.kpiSummary);

            if (!data || data.error) {
                throw new Error(data?.error || 'Failed to load KPI data');
            }

            this.updateKPICards(data);
        } catch (error) {
            console.error('KPI loading error:', error);
            UIManager.showNotification('Failed to load KPI summary', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    updateKPICards(data) {
        const updates = [
            { id: 'todaySales', value: Utils.formatCurrency(data.today_sales || 0), change: 'salesChange', changeValue: data.sales_change || 0 },
            { id: 'todayCustomers', value: Utils.formatNumber(data.today_customers || 0), change: 'customersChange', changeValue: data.customers_change || 0 },
            { id: 'todayTransactions', value: Utils.formatNumber(data.today_transactions || 0), change: 'transactionsChange', changeValue: data.transactions_change || 0 }
        ];

        updates.forEach(({ id, value, change, changeValue }) => {
            UIManager.updateTextContent(id, value);
            if (change) UIManager.updateChangeIndicator(change, changeValue);
        });

        UIManager.updateTextContent('targetAchievement', (data.target_achievement || 0).toFixed(1) + '%');
        UIManager.updateTextContent('targetStatus', data.target_status || 'N/A');
    }
};

const ComparisonManager = {
    async loadComparison() {
        const currentDateInput = Utils.$('#currentDate');
        const compareDateInput = Utils.$('#compareDate');

        if (!currentDateInput || !compareDateInput) return;

        const currentDate = currentDateInput.value;
        const compareDate = compareDateInput.value;

        if (!currentDate || !compareDate) {
            UIManager.showNotification('Please select both dates', 'warning');
            return;
        }

        AppState.incrementLoading();
        try {
            const url = `${CONFIG.API.ENDPOINTS.compare}&currentDate=${currentDate}&compareDate=${compareDate}`;
            const data = await APIService.get(url);

            if (!data || data.error) {
                throw new Error(data?.error || 'Comparison failed');
            }

            this.displayResults(data.comparison || []);
        } catch (error) {
            console.error('Comparison error:', error);
            UIManager.showNotification('Failed to load comparison data', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    displayResults(comparison) {
        const tbody = Utils.$('#comparisonTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!comparison || comparison.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#6b7280;">No comparison data available for selected dates</td></tr>';
            return;
        }

        const fragment = document.createDocumentFragment();

        comparison.forEach(item => {
            const row = document.createElement('tr');
            const trendIcon = item.trend === 'up' ? '▲' : '▼';
            const trendClass = item.trend === 'up' ? 'trend-up' : 'trend-down';
            const isCurrency = item.metric.includes('Sales') || item.metric.includes('Value');
            const formatValue = isCurrency ? Utils.formatCurrency : Utils.formatNumber;

            row.innerHTML = `
                <td><strong>${Utils.escapeHtml(item.metric)}</strong></td>
                <td>${formatValue(item.current)}</td>
                <td>${formatValue(item.compare)}</td>
                <td>${formatValue(Math.abs(item.difference))}</td>
                <td style="font-weight: 600; color: ${item.percentage >= 0 ? '#10b981' : '#ef4444'};">
                    ${item.percentage >= 0 ? '+' : ''}${item.percentage.toFixed(2)}%
                </td>
                <td>
                    <span class="trend-indicator ${trendClass}">${trendIcon}</span>
                </td>
            `;

            fragment.appendChild(row);
        });

        tbody.appendChild(fragment);
    }
};

const TargetManager = {
    async loadTargets(filter = 'all') {
        AppState.currentFilter = filter;
        AppState.incrementLoading();
        
        try {
            const url = `${CONFIG.API.ENDPOINTS.targets}&filter=${filter}`;
            const data = await APIService.get(url);

            if (!data || data.error) {
                throw new Error(data?.error || 'Failed to load targets');
            }

            this.displayTargets(data.targets || []);
        } catch (error) {
            console.error('Targets loading error:', error);
            UIManager.showNotification('Failed to load targets', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    displayTargets(targets) {
        const tbody = Utils.$('#targetsTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!targets || targets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#6b7280;">No targets found. Create your first target to start tracking progress.</td></tr>';
            return;
        }

        const fragment = document.createDocumentFragment();

        targets.forEach(target => {
            const row = this.createTargetRow(target);
            fragment.appendChild(row);
        });

        tbody.appendChild(fragment);
    },

    createTargetRow(target) {
        const row = document.createElement('tr');

        const progress = Math.min(Math.max(target.progress || 0, 0), 100);
        const progressClass = progress >= 100 ? 'progress-achieved' :
                            progress >= 80 ? 'progress-near' : 'progress-below';

        const statusClass = target.status === 'achieved' ? 'status-achieved' :
                          target.status === 'near' ? 'status-near' : 'status-below';

        const statusText = target.status === 'achieved' ? 'Achieved' :
                         target.status === 'near' ? 'Near Target' : 'Below Target';

        const isCurrencyType = target.target_type === 'sales' || target.target_type === 'avg_transaction';
        const formatValue = isCurrencyType ? Utils.formatCurrency : Utils.formatNumber;

        row.innerHTML = `
            <td><strong>${Utils.escapeHtml(target.target_name)}</strong></td>
            <td>${Utils.formatTargetType(target.target_type)}</td>
            <td style="white-space: nowrap;">${Utils.formatDate(target.start_date)} - ${Utils.formatDate(target.end_date)}</td>
            <td>${formatValue(target.target_value)}</td>
            <td>${formatValue(target.current_value)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="progress-container" style="flex:1;background:#e5e7eb;height:8px;border-radius:4px;overflow:hidden;">
                        <div class="progress-bar ${progressClass}" style="width:${progress}%;height:100%;transition:width 0.6s ease;"></div>
                    </div>
                    <span style="font-weight:600;min-width:50px;font-size:13px;">${progress.toFixed(1)}%</span>
                </div>
            </td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td style="white-space: nowrap;">
                <button class="btn btn-secondary btn-edit" style="padding:6px 12px;font-size:12px;margin-right:4px;" data-target-id="${target.id}">Edit</button>
                <button class="btn btn-secondary btn-delete" style="padding:6px 12px;font-size:12px;background:#ef4444;color:#fff;" data-target-id="${target.id}">Delete</button>
            </td>
        `;

        // Event delegation for buttons
        row.querySelector('.btn-edit').addEventListener('click', () => this.editTarget(target.id));
        row.querySelector('.btn-delete').addEventListener('click', () => this.deleteTarget(target.id));

        return row;
    },

    openModal() {
        const modal = Utils.$('#targetModal');
        const form = Utils.$('#targetForm');

        if (modal) modal.classList.add('active');
        if (form) form.reset();

        const today = new Date();
        const nextMonth = new Date(today.getTime() + CONFIG.DATES.ONE_MONTH);

        const startDateInput = Utils.$('#targetStartDate');
        const endDateInput = Utils.$('#targetEndDate');

        if (startDateInput) startDateInput.value = Utils.getISODate(today);
        if (endDateInput) endDateInput.value = Utils.getISODate(nextMonth);
    },

    closeModal() {
        const modal = Utils.$('#targetModal');
        if (modal) modal.classList.remove('active');
    },

    async saveTarget(event) {
        event.preventDefault();

        const formData = {
            name: Utils.$('#targetName')?.value,
            type: Utils.$('#targetType')?.value,
            value: parseFloat(Utils.$('#targetValue')?.value),
            start_date: Utils.$('#targetStartDate')?.value,
            end_date: Utils.$('#targetEndDate')?.value,
            store: Utils.$('#targetStore')?.value
        };

        // Validation
        const errors = Utils.validateFormData(formData, ['name', 'type', 'value', 'start_date', 'end_date']);
        if (errors.length > 0) {
            UIManager.showNotification(errors[0], 'warning');
            return;
        }

        if (isNaN(formData.value) || formData.value <= 0) {
            UIManager.showNotification('Target value must be a positive number', 'warning');
            return;
        }

        AppState.incrementLoading();

        try {
            const data = await APIService.post(CONFIG.API.ENDPOINTS.saveTarget, formData);

            if (!data || data.error) {
                throw new Error(data?.error || 'Failed to save target');
            }

            this.closeModal();
            await this.loadTargets(AppState.currentFilter);
            await ChartLoaders.loadTargetChart();
            UIManager.showNotification('Target saved successfully!', 'success');

        } catch (error) {
            console.error('Target save error:', error);
            UIManager.showNotification('Failed to save target', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async deleteTarget(id) {
        if (!confirm('Are you sure you want to delete this target?')) {
            return;
        }

        AppState.incrementLoading();

        try {
            const url = `${CONFIG.API.ENDPOINTS.deleteTarget}&id=${id}`;
            const data = await APIService.get(url);

            if (!data || data.error) {
                throw new Error(data?.error || 'Failed to delete target');
            }

            await this.loadTargets(AppState.currentFilter);
            await ChartLoaders.loadTargetChart();
            UIManager.showNotification('Target deleted successfully!', 'success');

        } catch (error) {
            console.error('Target delete error:', error);
            UIManager.showNotification('Failed to delete target', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    editTarget(id) {
        UIManager.showNotification('Edit functionality: Load target #' + id + ' for editing', 'info');
        // TODO: Implement actual edit logic
    },

    filterTargets() {
        const filterSelect = Utils.$('#targetFilter');
        if (filterSelect) {
            this.loadTargets(filterSelect.value);
        }
    }
};

// ==================== CHART LOADERS ====================
const ChartLoaders = {
    async loadTrendChart() {
        try {
            const url = `${CONFIG.API.ENDPOINTS.trendData}&days=30`;
            const data = await APIService.get(url);

            if (!data || data.error || !data.trend_data || data.trend_data.length === 0) {
                console.log('No trend data available');
                return;
            }

            const config = {
                type: 'line',
                data: {
                    labels: data.trend_data.map(d => Utils.formatDate(d.date)),
                    datasets: [{
                        label: 'Sales Revenue',
                        data: data.trend_data.map(d => parseFloat(d.sales_volume) || 0),
                        borderColor: CONFIG.CHART.COLORS.primary,
                        backgroundColor: CONFIG.CHART.COLORS.primaryLight,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: CONFIG.CHART.COLORS.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => 'Sales: ' + Utils.formatCurrency(context.parsed.y)
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => Utils.formatCurrency(value)
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            };

            ChartManager.initChart('salesTrendChart', config);

        } catch (error) {
            console.error('Trend chart error:', error);
        }
    },

    async loadTargetChart() {
        try {
            const url = `${CONFIG.API.ENDPOINTS.targets}&filter=active`;
            const data = await APIService.get(url);

            if (!data || data.error || !data.targets || data.targets.length === 0) {
                console.log('No active targets for chart');
                return;
            }

            const config = {
                type: 'doughnut',
                data: {
                    labels: data.targets.map(t => t.target_name),
                    datasets: [{
                        data: data.targets.map(t => Math.min(parseFloat(t.progress) || 0, 100)),
                        backgroundColor: [
                            CONFIG.CHART.COLORS.primary,
                            CONFIG.CHART.COLORS.success,
                            CONFIG.CHART.COLORS.warning,
                            CONFIG.CHART.COLORS.danger,
                            CONFIG.CHART.COLORS.purple
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return `${label}: ${value.toFixed(1)}%`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            };

            ChartManager.initChart('targetAchievementChart', config);

        } catch (error) {
            console.error('Target chart error:', error);
        }
    }
};

// ==================== APPLICATION CONTROLLER ====================
const App = {
    async init() {
        if (AppState.isInitialized) {
            console.warn('App already initialized');
            return;
        }

        console.log('Initializing Sales Tracking App...');

        // Initialize UI
        UIManager.createLoader();
        DateManager.setDefaultDates();

        // Load all data
        await this.loadAllData();

        // Setup event listeners
        this.setupEventListeners();

        // Add CSS animations
        this.injectStyles();

        AppState.isInitialized = true;
        console.log('App initialized successfully');
    },

    async loadAllData() {
        const tasks = [
            KPIManager.loadSummary(),
            TargetManager.loadTargets(),
            ChartLoaders.loadTrendChart(),
            ChartLoaders.loadTargetChart()
        ];

        await Promise.allSettled(tasks);
    },

    setupEventListeners() {
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            ChartManager.destroyAll();
            AppState.reset();
        });

        // Handle visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && AppState.isInitialized) {
                this.refreshData();
            }
        });
    },

    refreshData: Utils.debounce(async function() {
        if (!AppState.isInitialized) return;
        
        AppState.incrementLoading();
        await App.loadAllData();
        await ComparisonManager.loadComparison();
        AppState.decrementLoading();
        UIManager.showNotification('Data refreshed successfully', 'success');
    }, 500),

    exportReport() {
        UIManager.showNotification('Preparing report for export...', 'info');
        // TODO: Implement export functionality
    },

    injectStyles() {
        if (Utils.$('#appCustomStyles')) return;

        const style = document.createElement('style');
        style.id = 'appCustomStyles';
        style.textContent = `
            @keyframes spin { to { transform: rotate(360deg); } }
            @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
            
            .progress-achieved { background: linear-gradient(90deg, #10b981, #059669); }
            .progress-near { background: linear-gradient(90deg, #f59e0b, #d97706); }
            .progress-below { background: linear-gradient(90deg, #ef4444, #dc2626); }
            
            .btn { transition: all 0.2s ease; }
            .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
            .btn:active { transform: translateY(0); }
        `;
        document.head.appendChild(style);
    }
};

// ==================== GLOBAL EXPORTS ====================
window.updateComparisonDates = () => DateManager.updateComparisonDates();
window.loadComparison = () => ComparisonManager.loadComparison();
window.filterTargets = () => TargetManager.filterTargets();
window.openTargetModal = () => TargetManager.openModal();
window.closeTargetModal = () => TargetManager.closeModal();
window.saveTarget = (e) => TargetManager.saveTarget(e);
window.refreshData = () => App.refreshData();
window.exportReport = () => App.exportReport();

// ==================== INITIALIZATION ====================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => App.init());
} else {
    App.init();
}