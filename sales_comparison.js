// Sales Comparison & Target Tracking - Fixed & Improved
'use strict';

// ==================== CONFIGURATION ====================
const CONFIG = {
    DATES: {
        ONE_DAY: 86400000,
        ONE_WEEK: 604800000,
        ONE_MONTH: 2592000000
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
            updateTarget: 'api/sales_comparison.php?action=update_target',
            deleteTarget: 'api/sales_comparison.php?action=delete_target',
            trendData: 'api/sales_comparison.php?action=trend_data'
        }
    },
    UI: {
        NOTIFICATION_DURATION: 4000,
        DEBOUNCE_DELAY: 300
    },
    VALIDATION: {
        MAX_TARGET_NAME_LENGTH: 100,
        MAX_TARGET_VALUE: 999999999,
        MIN_TARGET_VALUE: 0.01
    }
};

// ==================== STATE MANAGEMENT ====================
const AppState = {
    activeRequests: new Map(),
    isInitialized: false,
    loadingCounter: 0,
    currentFilter: 'all',
    editingTargetId: null,
    
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
        this.activeRequests.forEach(controller => {
            try { controller.abort(); } catch (e) {}
        });
        this.activeRequests.clear();
        this.loadingCounter = 0;
        this.editingTargetId = null;
    }
};

// ==================== UTILITY FUNCTIONS ====================
const Utils = {
    formatCurrency(value) {
        if (value === null || value === undefined || isNaN(value)) return '₱0.00';
        const num = parseFloat(value);
        if (!isFinite(num)) return '₱0.00';
        return '₱' + num.toLocaleString('en-PH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    },

    formatNumber(value) {
        if (value === null || value === undefined || isNaN(value)) return '0';
        const num = parseFloat(value);
        if (!isFinite(num)) return '0';
        return Math.round(num).toLocaleString('en-PH');
    },

    formatPercentage(value) {
        if (value === null || value === undefined || isNaN(value)) return '0.0%';
        const num = parseFloat(value);
        if (!isFinite(num)) return '0.0%';
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
        } catch (error) {
            console.error('Date formatting error:', error);
            return 'Invalid Date';
        }
    },

    getISODate(date) {
        if (!(date instanceof Date)) date = new Date(date);
        if (isNaN(date.getTime())) return '';
        return date.toISOString().split('T')[0];
    },

    formatTargetType(type) {
        const types = {
            'sales': 'Sales Revenue',
            'customers': 'Customer Traffic',
            'transactions': 'Transactions',
            'avg_transaction': 'Avg Transaction Value'
        };
        return types[type] || type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    debounce(func, wait = CONFIG.UI.DEBOUNCE_DELAY) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    },

    $(selector) {
        return document.querySelector(selector);
    },

    calculatePercentageChange(current, previous) {
        if (!previous || previous === 0) return 0;
        return ((current - previous) / previous) * 100;
    },

    validateDateRange(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (isNaN(start.getTime()) || isNaN(end.getTime())) {
            return { valid: false, error: 'Invalid date format' };
        }
        
        if (end < start) {
            return { valid: false, error: 'End date must be after start date' };
        }
        
        return { valid: true };
    },

    isValidDate(dateString) {
        const regex = /^\d{4}-\d{2}-\d{2}$/;
        if (!regex.test(dateString)) return false;
        const date = new Date(dateString);
        return !isNaN(date.getTime());
    },

    sanitizeInput(input, maxLength = null) {
        if (typeof input !== 'string') return '';
        let sanitized = input.trim();
        if (maxLength && sanitized.length > maxLength) {
            sanitized = sanitized.substring(0, maxLength);
        }
        return sanitized;
    }
};

// ==================== UI MANAGER ====================
const UIManager = {
    showNotification(message, type = 'info') {
        const existing = Utils.$('.notification-toast');
        if (existing) existing.remove();

        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#4f46e5'
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
            <span style="font-size:18px;font-weight:700;">${icons[type] || icons.info}</span>
            <span>${Utils.escapeHtml(message)}</span>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            min-width: 320px;
            max-width: 500px;
            padding: 16px 20px;
            background: ${colors[type] || colors.info};
            color: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 10001;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            animation: slideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.4s ease';
            setTimeout(() => notification.remove(), 400);
        }, CONFIG.UI.NOTIFICATION_DURATION);
    },

    updateLoadingState(isLoading) {
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
                background: rgba(0, 0, 0, 0.4);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                backdrop-filter: blur(4px);
            `;
            loader.innerHTML = `
                <div style="background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center;">
                    <div class="spinner" style="border: 5px solid #f3f4f6; border-top: 5px solid #4f46e5; border-radius: 50%; width: 60px; height: 60px; animation: spin 0.8s linear infinite; margin: 0 auto 16px;"></div>
                    <div style="color: #6b7280; font-size: 14px; font-weight: 500;">Loading data...</div>
                </div>
            `;
            document.body.appendChild(loader);
        }

        loader.style.display = isLoading ? 'flex' : 'none';
    },

    updateChangeIndicator(elementId, value) {
        const element = Utils.$(`#${elementId}`);
        if (!element) return;

        const numValue = parseFloat(value);
        if (isNaN(numValue) || !isFinite(numValue)) {
            element.textContent = '0.0%';
            element.className = 'kpi-change';
            return;
        }

        const sign = numValue >= 0 ? '+' : '';
        element.textContent = sign + numValue.toFixed(1) + '%';
        element.className = 'kpi-change ' + (numValue >= 0 ? 'positive' : 'negative');
    },

    updateTextContent(elementId, value) {
        const element = Utils.$(`#${elementId}`);
        if (element) element.textContent = value || '';
    }
};

// ==================== API SERVICE ====================
const APIService = {
    async fetchWithRetry(url, options = {}, retries = CONFIG.API.RETRY_ATTEMPTS) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), CONFIG.API.TIMEOUT);

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
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            return data;

        } catch (error) {
            clearTimeout(timeout);
            AppState.activeRequests.delete(url);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', url);
                return null;
            }

            if (retries > 0 && (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
                console.log(`Retrying request (${retries} attempts left):`, url);
                await new Promise(resolve => setTimeout(resolve, CONFIG.API.RETRY_DELAY));
                return this.fetchWithRetry(url, options, retries - 1);
            }

            throw error;
        }
    },

    get(url) {
        return this.fetchWithRetry(url);
    },

    post(url, body) {
        return this.fetchWithRetry(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }
};

// ==================== DATA MANAGERS ====================
const DateManager = {
    setDefaultDates() {
        const today = new Date();
        const yesterday = new Date(today - CONFIG.DATES.ONE_DAY);

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) compareDate.value = Utils.getISODate(yesterday);
    },

    updateComparisonDates() {
        const typeSelect = Utils.$('#comparisonType');
        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (!typeSelect || !currentDate || !compareDate) return;

        const today = new Date();
        currentDate.value = Utils.getISODate(today);

        const dateMap = {
            'today_vs_date': today - CONFIG.DATES.ONE_DAY,
            'week_vs_range': today - CONFIG.DATES.ONE_WEEK,
            'month_vs_period': today - CONFIG.DATES.ONE_MONTH,
            'custom': today - CONFIG.DATES.ONE_DAY
        };

        compareDate.value = Utils.getISODate(new Date(dateMap[typeSelect.value] || dateMap.custom));
    }
};

const KPIManager = {
    async loadSummary() {
        AppState.incrementLoading();
        try {
            const data = await APIService.get(CONFIG.API.ENDPOINTS.kpiSummary);

            if (!data) {
                throw new Error('No data received from server');
            }

            UIManager.updateTextContent('todaySales', Utils.formatCurrency(data.today_sales || 0));
            UIManager.updateChangeIndicator('salesChange', data.sales_change || 0);

            UIManager.updateTextContent('todayCustomers', Utils.formatNumber(data.today_customers || 0));
            UIManager.updateChangeIndicator('customersChange', data.customers_change || 0);

            UIManager.updateTextContent('todayTransactions', Utils.formatNumber(data.today_transactions || 0));
            UIManager.updateChangeIndicator('transactionsChange', data.transactions_change || 0);

            const achievement = parseFloat(data.target_achievement || 0);
            UIManager.updateTextContent('targetAchievement', Utils.formatPercentage(achievement));
            UIManager.updateTextContent('targetStatus', data.target_status || 'No active target');

        } catch (error) {
            console.error('KPI error:', error);
            UIManager.showNotification(error.message || 'Failed to load KPI data', 'error');
        } finally {
            AppState.decrementLoading();
        }
    }
};

const ComparisonManager = {
    async loadComparison() {
        const currentDate = Utils.$('#currentDate')?.value;
        const compareDate = Utils.$('#compareDate')?.value;

        if (!currentDate || !compareDate) {
            UIManager.showNotification('Please select both dates', 'warning');
            return;
        }

        if (!Utils.isValidDate(currentDate) || !Utils.isValidDate(compareDate)) {
            UIManager.showNotification('Invalid date format', 'warning');
            return;
        }

        AppState.incrementLoading();
        try {
            const url = `${CONFIG.API.ENDPOINTS.compare}&currentDate=${encodeURIComponent(currentDate)}&compareDate=${encodeURIComponent(compareDate)}`;
            const data = await APIService.get(url);

            if (!data) {
                throw new Error('No comparison data received');
            }

            this.displayResults(data.comparison || []);
            UIManager.showNotification('Comparison loaded successfully', 'success');

        } catch (error) {
            console.error('Comparison error:', error);
            UIManager.showNotification(error.message || 'Failed to load comparison', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    displayResults(comparison) {
        const tbody = Utils.$('#comparisonTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!comparison || comparison.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:#9ca3af;">
                        No comparison data available for selected dates
                    </td>
                </tr>
            `;
            return;
        }

        const fragment = document.createDocumentFragment();

        comparison.forEach(item => {
            const row = document.createElement('tr');
            const trendIcon = item.trend === 'up' ? '▲' : '▼';
            const trendClass = item.trend === 'up' ? 'trend-up' : 'trend-down';
            const isCurrency = item.metric.includes('Sales') || item.metric.includes('Value');
            const formatValue = isCurrency ? Utils.formatCurrency : Utils.formatNumber;

            const percentageValue = parseFloat(item.percentage);
            const percentageColor = percentageValue >= 0 ? '#10b981' : '#ef4444';
            const percentageText = percentageValue >= 0 ? '+' : '';

            row.innerHTML = `
                <td><strong>${Utils.escapeHtml(item.metric)}</strong></td>
                <td>${formatValue(item.current)}</td>
                <td>${formatValue(item.compare)}</td>
                <td>${formatValue(Math.abs(item.difference))}</td>
                <td style="font-weight:600;color:${percentageColor}">
                    ${percentageText}${percentageValue.toFixed(2)}%
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
            const url = `${CONFIG.API.ENDPOINTS.targets}&filter=${encodeURIComponent(filter)}`;
            const data = await APIService.get(url);

            if (!data) {
                throw new Error('No targets data received');
            }

            this.displayTargets(data.targets || []);
        } catch (error) {
            console.error('Targets error:', error);
            UIManager.showNotification(error.message || 'Failed to load targets', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    displayTargets(targets) {
        const tbody = Utils.$('#targetsTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!targets || targets.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;color:#9ca3af;">
                        No targets found. Create your first target to start tracking progress.
                    </td>
                </tr>
            `;
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
        const progress = Math.min(Math.max(parseFloat(target.progress) || 0, 0), 999.9);
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
            <td style="white-space:nowrap">${Utils.formatDate(target.start_date)} - ${Utils.formatDate(target.end_date)}</td>
            <td>${formatValue(target.target_value)}</td>
            <td>${formatValue(target.current_value)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="progress-container" style="flex:1">
                        <div class="progress-bar ${progressClass}" style="width:${Math.min(progress, 100)}%"></div>
                    </div>
                    <span style="font-weight:600;min-width:60px;font-size:13px">${progress.toFixed(1)}%</span>
                </div>
            </td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td style="white-space:nowrap">
                <button class="btn btn-secondary btn-edit" style="padding:6px 12px;font-size:12px;margin-right:6px" data-id="${target.id}">Edit</button>
                <button class="btn btn-secondary btn-delete" style="padding:6px 12px;font-size:12px;background:#ef4444;color:#fff" data-id="${target.id}">Delete</button>
            </td>
        `;

        row.querySelector('.btn-edit').addEventListener('click', () => this.editTarget(target));
        row.querySelector('.btn-delete').addEventListener('click', () => this.deleteTarget(target.id));

        return row;
    },

    openModal() {
        const modal = Utils.$('#targetModal');
        const form = Utils.$('#targetForm');
        const modalTitle = Utils.$('#modalTitle');

        if (modal) modal.classList.add('active');
        if (form) form.reset();
        if (modalTitle) modalTitle.textContent = 'Create New Target';

        AppState.editingTargetId = null;

        const today = new Date();
        const nextMonth = new Date(today.getTime() + CONFIG.DATES.ONE_MONTH);

        const startDate = Utils.$('#targetStartDate');
        const endDate = Utils.$('#targetEndDate');

        if (startDate) startDate.value = Utils.getISODate(today);
        if (endDate) endDate.value = Utils.getISODate(nextMonth);
    },

    closeModal() {
        const modal = Utils.$('#targetModal');
        if (modal) modal.classList.remove('active');
        AppState.editingTargetId = null;
    },

    async saveTarget(event) {
        event.preventDefault();

        const formData = {
            name: Utils.sanitizeInput(Utils.$('#targetName')?.value, CONFIG.VALIDATION.MAX_TARGET_NAME_LENGTH),
            type: Utils.$('#targetType')?.value,
            value: parseFloat(Utils.$('#targetValue')?.value),
            start_date: Utils.$('#targetStartDate')?.value,
            end_date: Utils.$('#targetEndDate')?.value,
            store: Utils.sanitizeInput(Utils.$('#targetStore')?.value, 100) || ''
        };

        if (!formData.name) {
            UIManager.showNotification('Please enter a target name', 'warning');
            return;
        }

        if (formData.name.length > CONFIG.VALIDATION.MAX_TARGET_NAME_LENGTH) {
            UIManager.showNotification(`Target name too long (max ${CONFIG.VALIDATION.MAX_TARGET_NAME_LENGTH} characters)`, 'warning');
            return;
        }

        const validTypes = ['sales', 'customers', 'transactions', 'avg_transaction'];
        if (!validTypes.includes(formData.type)) {
            UIManager.showNotification('Please select a valid target type', 'warning');
            return;
        }

        if (isNaN(formData.value) || formData.value < CONFIG.VALIDATION.MIN_TARGET_VALUE) {
            UIManager.showNotification(`Target value must be at least ${CONFIG.VALIDATION.MIN_TARGET_VALUE}`, 'warning');
            return;
        }

        if (formData.value > CONFIG.VALIDATION.MAX_TARGET_VALUE) {
            UIManager.showNotification('Target value is too large', 'warning');
            return;
        }

        if (!formData.start_date || !formData.end_date) {
            UIManager.showNotification('Please select both start and end dates', 'warning');
            return;
        }

        const dateValidation = Utils.validateDateRange(formData.start_date, formData.end_date);
        if (!dateValidation.valid) {
            UIManager.showNotification(dateValidation.error, 'warning');
            return;
        }

        AppState.incrementLoading();

        try {
            let data;
            if (AppState.editingTargetId) {
                formData.id = AppState.editingTargetId;
                data = await APIService.post(CONFIG.API.ENDPOINTS.updateTarget, formData);
            } else {
                data = await APIService.post(CONFIG.API.ENDPOINTS.saveTarget, formData);
            }

            if (!data) {
                throw new Error('No response from server');
            }

            this.closeModal();
            await Promise.all([
                this.loadTargets(AppState.currentFilter),
                TableLoaders.loadTargetProgressTable(),
                KPIManager.loadSummary()
            ]);
            UIManager.showNotification(data.message || 'Target saved successfully!', 'success');

        } catch (error) {
            console.error('Save error:', error);
            UIManager.showNotification(error.message || 'Failed to save target', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async deleteTarget(id) {
        if (!confirm('Are you sure you want to delete this target? This action cannot be undone.')) return;

        if (!id || id <= 0) {
            UIManager.showNotification('Invalid target ID', 'error');
            return;
        }

        AppState.incrementLoading();

        try {
            const url = `${CONFIG.API.ENDPOINTS.deleteTarget}&id=${encodeURIComponent(id)}`;
            const data = await APIService.get(url);

            if (!data) {
                throw new Error('No response from server');
            }

            await Promise.all([
                this.loadTargets(AppState.currentFilter),
                TableLoaders.loadTargetProgressTable(),
                KPIManager.loadSummary()
            ]);
            UIManager.showNotification(data.message || 'Target deleted successfully!', 'success');

        } catch (error) {
            console.error('Delete error:', error);
            UIManager.showNotification(error.message || 'Failed to delete target', 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    editTarget(target) {
        const modal = Utils.$('#targetModal');
        const modalTitle = Utils.$('#modalTitle');

        if (modalTitle) modalTitle.textContent = 'Edit Target';
        if (modal) modal.classList.add('active');

        AppState.editingTargetId = target.id;

        const nameInput = Utils.$('#targetName');
        const typeInput = Utils.$('#targetType');
        const valueInput = Utils.$('#targetValue');
        const startDateInput = Utils.$('#targetStartDate');
        const endDateInput = Utils.$('#targetEndDate');
        const storeInput = Utils.$('#targetStore');

        if (nameInput) nameInput.value = target.target_name || '';
        if (typeInput) typeInput.value = target.target_type || 'sales';
        if (valueInput) valueInput.value = target.target_value || '';
        if (startDateInput) startDateInput.value = target.start_date || '';
        if (endDateInput) endDateInput.value = target.end_date || '';
        if (storeInput) storeInput.value = target.store || '';
    },

    filterTargets() {
        const filter = Utils.$('#targetFilter')?.value || 'all';
        this.loadTargets(filter);
    }
};

// ==================== TABLE LOADERS ====================
const TableLoaders = {
    async loadSalesTrendTable() {
        try {
            const url = `${CONFIG.API.ENDPOINTS.trendData}&days=30`;
            const data = await APIService.get(url);

            if (!data || !data.trend_data || data.trend_data.length === 0) {
                console.log('No trend data available');
                this.displayEmptyTrendTable();
                return;
            }

            this.displaySalesTrendTable(data.trend_data);

        } catch (error) {
            console.error('Trend table error:', error);
            this.displayEmptyTrendTable();
        }
    },

    displaySalesTrendTable(trendData) {
        const tbody = Utils.$('#salesTrendTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!trendData || trendData.length === 0) {
            this.displayEmptyTrendTable();
            return;
        }

        const fragment = document.createDocumentFragment();
        const sortedData = [...trendData].sort((a, b) => new Date(b.date) - new Date(a.date));

        sortedData.forEach((item, index) => {
            const row = document.createElement('tr');
            const salesValue = parseFloat(item.sales_volume) || 0;
            
            let changePercent = 0;
            let changeClass = '';
            let changeIcon = '';
            
            if (index < sortedData.length - 1) {
                const previousValue = parseFloat(sortedData[index + 1].sales_volume) || 0;
                changePercent = Utils.calculatePercentageChange(salesValue, previousValue);
                changeClass = changePercent >= 0 ? 'positive' : 'negative';
                changeIcon = changePercent >= 0 ? '▲' : '▼';
            }

            row.innerHTML = `
                <td><strong>${Utils.formatDate(item.date)}</strong></td>
                <td style="font-weight:600">${Utils.formatCurrency(salesValue)}</td>
                <td>
                    ${index < sortedData.length - 1 ? `
                        <span class="kpi-change ${changeClass}" style="display:inline-flex;align-items:center;gap:4px;">
                            ${changeIcon} ${changePercent >= 0 ? '+' : ''}${changePercent.toFixed(1)}%
                        </span>
                    ` : '<span style="color:#9ca3af;">—</span>'}
                </td>
            `;

            fragment.appendChild(row);
        });

        tbody.appendChild(fragment);
    },

    displayEmptyTrendTable() {
        const tbody = Utils.$('#salesTrendTableBody');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align:center;padding:40px;color:#9ca3af;">
                    No sales trend data available
                </td>
            </tr>
        `;
    },

    async loadTargetProgressTable() {
        try {
            const url = `${CONFIG.API.ENDPOINTS.targets}&filter=active`;
            const data = await APIService.get(url);

            if (!data || !data.targets || data.targets.length === 0) {
                console.log('No active targets');
                this.displayEmptyTargetTable();
                return;
            }

            this.displayTargetProgressTable(data.targets);

        } catch (error) {
            console.error('Target table error:', error);
            this.displayEmptyTargetTable();
        }
    },

    displayTargetProgressTable(targets) {
        const tbody = Utils.$('#targetProgressTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!targets || targets.length === 0) {
            this.displayEmptyTargetTable();
            return;
        }

        const fragment = document.createDocumentFragment();

        targets.forEach(target => {
            const row = document.createElement('tr');
            const progress = Math.min(Math.max(parseFloat(target.progress) || 0, 0), 999.9);
            const progressClass = progress >= 100 ? 'progress-achieved' :
                                progress >= 80 ? 'progress-near' : 'progress-below';

            const statusClass = target.status === 'achieved' ? 'status-achieved' :
                              target.status === 'near' ? 'status-near' : 'status-below';

            const statusText = target.status === 'achieved' ? 'Achieved' :
                             target.status === 'near' ? 'Near Target' : 'Below Target';

            row.innerHTML = `
                <td><strong>${Utils.escapeHtml(target.target_name)}</strong></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="progress-container" style="flex:1">
                            <div class="progress-bar ${progressClass}" style="width:${Math.min(progress, 100)}%"></div>
                        </div>
                        <span style="font-weight:600;min-width:60px;font-size:13px">${progress.toFixed(1)}%</span>
                    </div>
                </td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            `;

            fragment.appendChild(row);
        });

        tbody.appendChild(fragment);
    },

    displayEmptyTargetTable() {
        const tbody = Utils.$('#targetProgressTableBody');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align:center;padding:40px;color:#9ca3af;">
                    No active targets available
                </td>
            </tr>
        `;
    }
};

// ==================== APPLICATION ====================
const App = {
    async init() {
        if (AppState.isInitialized) {
            console.warn('App already initialized');
            return;
        }

        console.log('Initializing Sales Analytics Dashboard...');

        try {
            this.injectStyles();
            DateManager.setDefaultDates();
            
            await this.loadAllData();
            this.setupEventListeners();

            AppState.isInitialized = true;
            console.log('✓ Dashboard initialized successfully');
        } catch (error) {
            console.error('App initialization error:', error);
            UIManager.showNotification('Failed to initialize application', 'error');
        }
    },

    async loadAllData() {
        const results = await Promise.allSettled([
            KPIManager.loadSummary(),
            TargetManager.loadTargets(),
            TableLoaders.loadSalesTrendTable(),
            TableLoaders.loadTargetProgressTable()
        ]);

        const failures = results.filter(r => r.status === 'rejected');
        if (failures.length > 0) {
            console.error('Some data failed to load:', failures);
        }
    },

    setupEventListeners() {
        window.addEventListener('beforeunload', () => {
            AppState.reset();
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && AppState.isInitialized) {
                this.refreshData();
            }
        });

        window.addEventListener('online', () => {
            UIManager.showNotification('Connection restored', 'success');
            this.refreshData();
        });

        window.addEventListener('offline', () => {
            UIManager.showNotification('Connection lost - Working offline', 'warning');
        });

        const modal = Utils.$('#targetModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    TargetManager.closeModal();
                }
            });
        }
    },

    refreshData: Utils.debounce(async function() {
        if (!AppState.isInitialized) return;
        
        AppState.incrementLoading();
        try {
            await App.loadAllData();
            UIManager.showNotification('Data refreshed successfully', 'success');
        } catch (error) {
            console.error('Refresh error:', error);
            UIManager.showNotification('Failed to refresh data', 'error');
        } finally {
            AppState.decrementLoading();
        }
    }, 500),

    exportReport() {
        UIManager.showNotification('Preparing report for export...', 'info');
        window.print();
    },

    injectStyles() {
        if (Utils.$('#appAnimations')) return;

        const style = document.createElement('style');
        style.id = 'appAnimations';
        style.textContent = `
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
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

// ==================== ERROR HANDLERS ====================
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    if (AppState.isInitialized) {
        UIManager.showNotification('An error occurred. Please refresh the page.', 'error');
    }
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    if (AppState.isInitialized) {
        UIManager.showNotification('A network error occurred. Please try again.', 'error');
    }
});