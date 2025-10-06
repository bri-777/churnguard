// ==================== ULTRA-ENHANCED SALES ANALYTICS DASHBOARD v3.0 ====================
'use strict';

// ==================== ADVANCED CONFIGURATION ====================
const CONFIG = {
    API_BASE: 'api/sales_comparison.php',
    REQUEST_TIMEOUT: 30000,
    MAX_RETRIES: 3,
    RETRY_DELAY: 1000,
    DECIMAL_PRECISION: 2,
    PERCENTAGE_PRECISION: 1,
    CACHE_TTL: 300000, // 5 minutes
    DEBOUNCE_DELAY: 300,
    THROTTLE_DELAY: 1000,
    MAX_CONCURRENT_REQUESTS: 5,
    AUTO_REFRESH_INTERVAL: 120000, // 2 minutes
    
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
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { 
                display: true, 
                position: 'bottom',
                labels: { usePointStyle: true, padding: 15 }
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
    },
    
    VALIDATION: {
        MIN_NAME_LENGTH: 3,
        MAX_NAME_LENGTH: 100,
        MIN_VALUE: 0.01,
        MAX_VALUE: 999999999.99,
        DATE_PATTERN: /^\d{4}-\d{2}-\d{2}$/
    }
};

// ==================== CSRF TOKEN MANAGER ====================
const CSRFManager = {
    token: null,
    
    async fetchToken() {
        try {
            const response = await fetch('api/get_csrf_token.php', {
                credentials: 'same-origin'
            });
            const data = await response.json();
            this.token = data.token;
            return this.token;
        } catch (error) {
            console.error('CSRF token fetch failed:', error);
            return null;
        }
    },
    
    getToken() {
        return this.token;
    },
    
    async ensureToken() {
        if (!this.token) {
            await this.fetchToken();
        }
        return this.token;
    }
};

// ==================== ENHANCED STATE MANAGEMENT ====================
const AppState = {
    charts: new Map(),
    currentTab: 'trend',
    activeRequests: new Map(),
    loadingCounter: 0,
    editingTargetId: null,
    lastDataUpdate: null,
    cache: new Map(),
    requestQueue: [],
    concurrentRequests: 0,
    isOnline: navigator.onLine,
    errorCount: 0,
    performanceMetrics: new Map(),
    
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
        this.concurrentRequests = 0;
    },

    setCache(key, value, ttl = CONFIG.CACHE_TTL) {
        this.cache.set(key, {
            value,
            expires: Date.now() + ttl,
            size: JSON.stringify(value).length
        });
        this.pruneCache();
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

    clearCache(pattern = null) {
        if (!pattern) {
            this.cache.clear();
            return;
        }
        for (const key of this.cache.keys()) {
            if (key.includes(pattern)) {
                this.cache.delete(key);
            }
        }
    },
    
    pruneCache() {
        const MAX_CACHE_SIZE = 5 * 1024 * 1024; // 5MB
        let totalSize = 0;
        const entries = Array.from(this.cache.entries());
        
        entries.sort((a, b) => a[1].expires - b[1].expires);
        
        for (const [key, item] of entries) {
            totalSize += item.size;
            if (totalSize > MAX_CACHE_SIZE) {
                this.cache.delete(key);
            }
        }
    },
    
    recordPerformance(operation, duration) {
        if (!this.performanceMetrics.has(operation)) {
            this.performanceMetrics.set(operation, []);
        }
        const metrics = this.performanceMetrics.get(operation);
        metrics.push(duration);
        if (metrics.length > 100) metrics.shift();
    },
    
    getAveragePerformance(operation) {
        const metrics = this.performanceMetrics.get(operation);
        if (!metrics || metrics.length === 0) return 0;
        return metrics.reduce((a, b) => a + b, 0) / metrics.length;
    }
};

// ==================== ADVANCED UTILITY FUNCTIONS ====================
const Utils = {
    // Enhanced currency formatting with error boundaries
    formatCurrency(value) {
        try {
            const num = parseFloat(value);
            if (!isFinite(num) || isNaN(num)) return '₱0.00';
            
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(num);
        } catch (error) {
            console.error('Currency format error:', error);
            return '₱0.00';
        }
    },

    // Enhanced number formatting with abbreviation support
    formatNumber(value, abbreviate = false) {
        try {
            const num = parseFloat(value);
            if (!isFinite(num) || isNaN(num)) return '0';
            
            if (abbreviate && Math.abs(num) >= 1000) {
                const units = ['', 'K', 'M', 'B', 'T'];
                const order = Math.floor(Math.log10(Math.abs(num)) / 3);
                const unitIndex = Math.min(order, units.length - 1);
                const scaledNum = num / Math.pow(1000, unitIndex);
                return scaledNum.toFixed(1) + units[unitIndex];
            }
            
            return new Intl.NumberFormat('en-PH', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(num));
        } catch (error) {
            console.error('Number format error:', error);
            return '0';
        }
    },

    // Enhanced percentage with color coding
    formatPercentage(value, precision = CONFIG.PERCENTAGE_PRECISION, includeSign = false) {
        try {
            const num = parseFloat(value);
            if (!isFinite(num) || isNaN(num)) return '0.0%';
            
            const bounded = Math.max(-999.9, Math.min(999.9, num));
            const sign = includeSign && bounded > 0 ? '+' : '';
            return sign + bounded.toFixed(precision) + '%';
        } catch (error) {
            console.error('Percentage format error:', error);
            return '0.0%';
        }
    },

    // Enhanced date formatting with relative time
    formatDate(dateString, relative = false) {
        if (!dateString) return 'N/A';
        
        try {
            const date = new Date(dateString + 'T00:00:00');
            if (isNaN(date.getTime())) return 'Invalid Date';
            
            if (relative) {
                const now = new Date();
                const diffTime = now - date;
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays === 0) return 'Today';
                if (diffDays === 1) return 'Yesterday';
                if (diffDays < 7) return `${diffDays} days ago`;
            }
            
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

    // ISO date with validation
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

    // Enhanced datetime with timezone
    formatDateTime(includeSeconds = false) {
        try {
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Asia/Manila'
            };
            
            if (includeSeconds) {
                options.second = '2-digit';
            }
            
            return new Intl.DateTimeFormat('en-PH', options).format(new Date());
        } catch {
            return 'N/A';
        }
    },

    // Precise percentage change with edge cases
    calculateChange(current, previous) {
        const curr = parseFloat(current);
        const prev = parseFloat(previous);
        
        if (!isFinite(curr) || !isFinite(prev)) return 0;
        if (prev === 0) return curr > 0 ? 100 : 0;
        if (curr === prev) return 0;
        
        return ((curr - prev) / Math.abs(prev)) * 100;
    },

    // XSS protection
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
            '/': '&#x2F;'
        };
        return String(text || '').replace(/[&<>"'/]/g, m => map[m]);
    },

    // Advanced debounce with leading/trailing options
    debounce(func, wait, options = {}) {
        let timeout;
        const { leading = false, trailing = true } = options;
        
        return function executedFunction(...args) {
            const context = this;
            const later = () => {
                timeout = null;
                if (trailing) func.apply(context, args);
            };
            const callNow = leading && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    // Throttle with accurate timing
    throttle(func, limit) {
        let inThrottle;
        let lastRan;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                lastRan = Date.now();
                inThrottle = true;
                setTimeout(() => {
                    if (Date.now() - lastRan >= limit) {
                        func.apply(this, args);
                        lastRan = Date.now();
                    }
                    inThrottle = false;
                }, limit - (Date.now() - lastRan));
            }
        };
    },

    // Safe DOM selector with caching
    $(selector, useCache = true) {
        if (useCache && this._selectorCache) {
            const cached = this._selectorCache.get(selector);
            if (cached && document.contains(cached)) return cached;
        }
        
        try {
            const element = document.querySelector(selector);
            if (useCache) {
                if (!this._selectorCache) this._selectorCache = new Map();
                this._selectorCache.set(selector, element);
            }
            return element;
        } catch (e) {
            console.error('Selector error:', selector, e);
            return null;
        }
    },

    $$(selector) {
        try {
            return Array.from(document.querySelectorAll(selector));
        } catch (e) {
            console.error('Selector error:', selector, e);
            return [];
        }
    },

    // Comprehensive validation
    isValidNumber(value) {
        const num = parseFloat(value);
        return isFinite(num) && !isNaN(num);
    },
    
    isValidDate(dateString) {
        if (!CONFIG.VALIDATION.DATE_PATTERN.test(dateString)) return false;
        const date = new Date(dateString + 'T00:00:00');
        return !isNaN(date.getTime());
    },
    
    isValidString(str, minLength = 1, maxLength = 255) {
        if (typeof str !== 'string') return false;
        const trimmed = str.trim();
        return trimmed.length >= minLength && trimmed.length <= maxLength;
    },
    
    // Generate unique ID
    generateId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    },
    
    // Deep clone object
    deepClone(obj) {
        try {
            return JSON.parse(JSON.stringify(obj));
        } catch {
            return obj;
        }
    },
    
    // Retry with exponential backoff
    async retryWithBackoff(fn, maxRetries = 3, baseDelay = 1000) {
        for (let i = 0; i < maxRetries; i++) {
            try {
                return await fn();
            } catch (error) {
                if (i === maxRetries - 1) throw error;
                const delay = baseDelay * Math.pow(2, i);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }
    }
};

// ==================== ENHANCED UI MANAGER ====================
const UIManager = {
    notificationQueue: [],
    maxNotifications: 3,
    
    showNotification(message, type = 'info', duration = 4000) {
        // Limit concurrent notifications
        if (this.notificationQueue.length >= this.maxNotifications) {
            const oldest = this.notificationQueue.shift();
            if (oldest) oldest.remove();
        }
        
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
        notification.className = 'notification-toast';
        notification.style.cssText = `
            position: fixed;
            top: ${24 + (this.notificationQueue.length * 80)}px;
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
            transition: top 0.3s ease;
        `;
        
        notification.innerHTML = `
            <span style="font-size: 20px;" aria-hidden="true">${icons[type] || icons.info}</span>
            <span style="flex: 1;">${Utils.escapeHtml(message)}</span>
            <button style="background: transparent; border: none; color: white; cursor: pointer; padding: 4px; font-size: 18px;" 
                    onclick="this.parentElement.remove()" aria-label="Close notification">×</button>
        `;
        
        document.body.appendChild(notification);
        this.notificationQueue.push(notification);
        
        // Auto-dismiss
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.4s ease';
            setTimeout(() => {
                notification.remove();
                const index = this.notificationQueue.indexOf(notification);
                if (index > -1) this.notificationQueue.splice(index, 1);
            }, 400);
        }, duration);
        
        // Screen reader announcement
        this.announceToScreenReader(message);
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
        if (loader) loader.style.display = 'none';
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
            
            // Smooth value transition
            valueEl.style.transition = 'opacity 0.2s ease';
            valueEl.style.opacity = '0';
            setTimeout(() => {
                valueEl.textContent = formattedValue;
                valueEl.style.opacity = '1';
            }, 150);
        }
        
        if (trendBadge && Utils.isValidNumber(change)) {
            const isPositive = change >= 0;
            const isZero = Math.abs(change) < 0.01;
            
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
    }, 1000),
    
    announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('role', 'status');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.style.cssText = 'position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;';
        announcement.textContent = message;
        document.body.appendChild(announcement);
        setTimeout(() => announcement.remove(), 1000);
    },
    
    showConfirmDialog(message, onConfirm, onCancel) {
        const dialog = document.createElement('div');
        dialog.className = 'custom-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.innerHTML = `
            <div class="dialog-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:10002;">
                <div class="dialog-content" style="background:white;padding:32px;border-radius:16px;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <h3 style="margin:0 0 16px;font-size:18px;font-weight:600;">Confirm Action</h3>
                    <p style="margin:0 0 24px;color:#6b7280;">${Utils.escapeHtml(message)}</p>
                    <div style="display:flex;gap:12px;justify-content:flex-end;">
                        <button class="btn-cancel" style="padding:10px 20px;border:1px solid #e5e7eb;background:white;border-radius:8px;cursor:pointer;font-weight:500;">Cancel</button>
                        <button class="btn-confirm" style="padding:10px 20px;border:none;background:#ef4444;color:white;border-radius:8px;cursor:pointer;font-weight:500;">Confirm</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(dialog);
        
        const confirmBtn = dialog.querySelector('.btn-confirm');
        const cancelBtn = dialog.querySelector('.btn-cancel');
        
        const cleanup = () => dialog.remove();
        
        confirmBtn.addEventListener('click', () => {
            cleanup();
            if (onConfirm) onConfirm();
        });
        
        cancelBtn.addEventListener('click', () => {
            cleanup();
            if (onCancel) onCancel();
        });
        
        dialog.querySelector('.dialog-overlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                cleanup();
                if (onCancel) onCancel();
            }
        });
    }
};

// ==================== ADVANCED API SERVICE WITH REQUEST QUEUE ====================
const APIService = {
    requestQueue: [],
    processingQueue: false,
    
    async fetch(action, params = {}, options = {}) {
        const {
            skipCache = false,
            priority = 'normal',
            retryCount = 0
        } = options;
        
        // Check cache
        if (!skipCache) {
            const cacheKey = this.getCacheKey(action, params);
            const cached = AppState.getCache(cacheKey);
            if (cached) {
                console.log('✓ Using cached data:', action);
                return cached;
            }
        }
        
        // Queue management for concurrent requests
        if (AppState.concurrentRequests >= CONFIG.MAX_CONCURRENT_REQUESTS) {
            return new Promise((resolve, reject) => {
                this.requestQueue.push({ action, params, options, resolve, reject });
                this.processQueue();
            });
        }
        
        return this.executeRequest(action, params, retryCount);
    },
    
    async executeRequest(action, params, retryCount) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.append(key, String(value));
            }
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), CONFIG.REQUEST_TIMEOUT);
        const requestId = Utils.generateId('req');
        
        AppState.activeRequests.set(requestId, controller);
        AppState.concurrentRequests++;
        
        const startTime = performance.now();

        try {
            const csrfToken = await CSRFManager.ensureToken();
            
            const headers = { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            };
            
            if (csrfToken) {
                headers['X-CSRF-Token'] = csrfToken;
            }
            
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers,
                signal: controller.signal,
                credentials: 'same-origin'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);
            AppState.concurrentRequests--;
            this.processQueue();

            // Performance tracking
            const duration = performance.now() - startTime;
            AppState.recordPerformance(action, duration);

            // Auth check
            if (response.status === 401) {
                UIManager.showNotification('Session expired. Please log in again.', 'warning');
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

            // Cache successful response
            const cacheKey = this.getCacheKey(action, params);
            AppState.setCache(cacheKey, data);
            
            // Reset error counter on success
            AppState.errorCount = 0;
            
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);
            AppState.concurrentRequests--;
            this.processQueue();

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', action);
                return null;
            }

            // Retry logic with exponential backoff
            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                console.log(`Retrying ${action} (${retryCount + 1}/${CONFIG.MAX_RETRIES})`);
                await new Promise(resolve => 
                    setTimeout(resolve, CONFIG.RETRY_DELAY * Math.pow(2, retryCount))
                );
                return this.executeRequest(action, params, retryCount + 1);
            }

            AppState.errorCount++;
            console.error('API Error:', error);
            throw error;
        }
    },

    async post(action, body, options = {}) {
        const { retryCount = 0 } = options;
        
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), CONFIG.REQUEST_TIMEOUT);
        const requestId = Utils.generateId('post');
        
        AppState.activeRequests.set(requestId, controller);

        try {
            const csrfToken = await CSRFManager.ensureToken();
            
            const headers = { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            
            if (csrfToken) {
                headers['X-CSRF-Token'] = csrfToken;
            }
            
            const response = await fetch(url.toString(), {
                method: 'POST',
                headers,
                body: JSON.stringify(body),
                signal: controller.signal,
                credentials: 'same-origin'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (response.status === 401) {
                UIManager.showNotification('Session expired', 'warning');
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

            // Clear relevant cache on mutations
            AppState.clearCache(action.includes('target') ? 'target' : null);
            
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log('Request cancelled:', action);
                return null;
            }

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                await new Promise(resolve => 
                    setTimeout(resolve, CONFIG.RETRY_DELAY * Math.pow(2, retryCount))
                );
                return this.post(action, body, { retryCount: retryCount + 1 });
            }

            console.error('API Error:', error);
            throw error;
        }
    },
    
    processQueue() {
        if (this.processingQueue || this.requestQueue.length === 0) return;
        if (AppState.concurrentRequests >= CONFIG.MAX_CONCURRENT_REQUESTS) return;
        
        this.processingQueue = true;
        
        const request = this.requestQueue.shift();
        if (request) {
            this.executeRequest(request.action, request.params, 0)
                .then(request.resolve)
                .catch(request.reject)
                .finally(() => {
                    this.processingQueue = false;
                    this.processQueue();
                });
        } else {
            this.processingQueue = false;
        }
    },
    
    getCacheKey(action, params) {
        return `${action}_${JSON.stringify(params)}`;
    }
};

// ==================== ENHANCED DATA MANAGER ====================
const DataManager = {
    async loadKPISummary() {
        AppState.incrementLoading();
        try {
            const data = await APIService.fetch('kpi_summary');
            
            if (!data) return;

            // Optimistic UI update
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
                miniProgress.setAttribute('aria-valuenow', progress.toFixed(1));
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
        
        const rows = sorted.map((item, index) => {
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
        });
        
        tbody.innerHTML = rows.join('');
    },

    async loadComparison() {
        const currentDate = Utils.$('#currentDate')?.value;
        const compareDate = Utils.$('#compareDate')?.value;

        if (!currentDate || !compareDate) {
            UIManager.showNotification('Please select both dates', 'warning');
            return;
        }
        
        if (!Utils.isValidDate(currentDate) || !Utils.isValidDate(compareDate)) {
            UIManager.showNotification('Invalid date format', 'error');
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

        const cards = comparison.map(item => {
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
        });
        
        container.innerHTML = cards.join('');
    },
    
    // Export data to CSV
    exportToCSV(data, filename) {
        try {
            const csv = this.convertToCSV(data);
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${filename}_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            UIManager.showNotification('Data exported successfully', 'success');
        } catch (error) {
            console.error('Export error:', error);
            UIManager.showNotification('Failed to export data', 'error');
        }
    },
    
    convertToCSV(data) {
        if (!data || data.length === 0) return '';
        
        const headers = Object.keys(data[0]);
        const rows = data.map(row => 
            headers.map(header => {
                const value = row[header];
                return typeof value === 'string' && value.includes(',') 
                    ? `"${value}"` 
                    : value;
            }).join(',')
        );
        
        return [headers.join(','), ...rows].join('\n');
    }
};

// ==================== ENHANCED CHART MANAGER ====================
const ChartManager = {
    destroyChart(chartName) {
        const chart = AppState.charts.get(chartName);
        if (chart) {
            try {
                chart.destroy();
                AppState.charts.delete(chartName);
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
        
        const chart = new Chart(ctx, {
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
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: CONFIG.CHART_COLORS.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                ...CONFIG.CHART_OPTIONS,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => Utils.formatCurrency(value),
                            font: { size: 12 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                },
                plugins: {
                    ...CONFIG.CHART_OPTIONS.plugins,
                    tooltip: {
                        ...CONFIG.CHART_OPTIONS.plugins.tooltip,
                        callbacks: {
                            label: (ctx) => 'Revenue: ' + Utils.formatCurrency(ctx.parsed.y),
                            title: (ctx) => Utils.formatDate(sortedData[ctx[0].dataIndex].date, true)
                        }
                    }
                }
            }
        });
        
        AppState.charts.set('salesTrend', chart);
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

        const chart = new Chart(ctx, {
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
                            callback: value => Utils.formatNumber(value),
                            font: { size: 12 }
                        }
                    },
                    x: {
                        ticks: { font: { size: 11 } }
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
        
        AppState.charts.set('comparison', chart);
    },

    updateTrendChart() {
        const period = parseInt(Utils.$('#trendPeriod')?.value) || 30;
        DataManager.loadTrendData(period);
    },
    
    destroyAllCharts() {
        AppState.charts.forEach((chart, name) => this.destroyChart(name));
    }
};

// ==================== ENHANCED TARGET MANAGER ====================
const TargetManager = {
    validationRules: {
        name: {
            min: CONFIG.VALIDATION.MIN_NAME_LENGTH,
            max: CONFIG.VALIDATION.MAX_NAME_LENGTH,
            pattern: /^[a-zA-Z0-9\s\-_]+$/
        },
        value: {
            min: CONFIG.VALIDATION.MIN_VALUE,
            max: CONFIG.VALIDATION.MAX_VALUE
        }
    },
    
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

        const cards = targets.map(target => {
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
                            <div class="progress-bar-fill ${statusClass}" 
                                 style="width:${cappedProgress.toFixed(1)}%"
                                 role="progressbar"
                                 aria-valuenow="${progress.toFixed(1)}"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
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
                            <button class="btn-icon-small" 
                                    onclick="TargetManager.editTarget(${target.id})" 
                                    title="Edit Target" 
                                    aria-label="Edit ${Utils.escapeHtml(target.target_name)}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="btn-icon-small delete" 
                                    onclick="TargetManager.deleteTarget(${target.id})" 
                                    title="Delete Target" 
                                    aria-label="Delete ${Utils.escapeHtml(target.target_name)}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        grid.innerHTML = cards.join('');
    },

    displayTargetsTable(targets) {
        const tbody = Utils.$('#activeTargetsTableBody');
        if (!tbody) return;

        if (!targets || targets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No targets available</td></tr>';
            return;
        }

        const rows = targets.map(target => {
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
                                <div class="progress-bar-fill ${statusClass}" 
                                     style="width:${cappedProgress.toFixed(1)}%"
                                     role="progressbar"
                                     aria-valuenow="${progress.toFixed(1)}"></div>
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
        });
        
        tbody.innerHTML = rows.join('');
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
        UIManager.showConfirmDialog(
            'Are you sure you want to delete this target? This action cannot be undone.',
            async () => {
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
        );
    },
    
    validateTargetForm(formData) {
        const errors = [];
        
        if (!Utils.isValidString(formData.name, this.validationRules.name.min, this.validationRules.name.max)) {
            errors.push(`Target name must be between ${this.validationRules.name.min}-${this.validationRules.name.max} characters`);
        }
        
        if (!this.validationRules.name.pattern.test(formData.name)) {
            errors.push('Target name contains invalid characters');
        }
        
        if (!['sales', 'customers', 'transactions', 'avg_transaction'].includes(formData.type)) {
            errors.push('Invalid target type');
        }
        
        if (!Utils.isValidNumber(formData.value) || 
            formData.value < this.validationRules.value.min || 
            formData.value > this.validationRules.value.max) {
            errors.push(`Target value must be between ${this.validationRules.value.min} and ${Utils.formatNumber(this.validationRules.value.max)}`);
        }
        
        if (!Utils.isValidDate(formData.start_date) || !Utils.isValidDate(formData.end_date)) {
            errors.push('Invalid date format');
        }
        
        if (new Date(formData.end_date) < new Date(formData.start_date)) {
            errors.push('End date must be after start date');
        }
        
        return errors;
    }
};

// ==================== DATE MANAGER ====================
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

// ==================== TAB MANAGER ====================
const TabManager = {
    switchTab(tabName) {
        Utils.$$('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
        });
        
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
        
        // Track tab views
        this.trackTabView(tabName);
    },
    
    trackTabView(tabName) {
        if (!this.tabViews) this.tabViews = {};
        this.tabViews[tabName] = (this.tabViews[tabName] || 0) + 1;
    }
};

// ==================== MODAL MANAGER ====================
const ModalManager = {
    open() {
        const modal = Utils.$('#targetModal');
        const form = Utils.$('#targetForm');
        
        if (modal) {
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            
            // Trap focus
            this.trapFocus(modal);
            
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

        // Client-side validation
        const errors = TargetManager.validateTargetForm(formData);
        
        if (errors.length > 0) {
            UIManager.showNotification(errors[0], 'warning');
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
    },
    
    trapFocus(modal) {
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        const handleTabKey = (e) => {
            if (e.key !== 'Tab') return;
            
            if (e.shiftKey) {
                if (document.activeElement === firstElement) {
                    lastElement.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === lastElement) {
                    firstElement.focus();
                    e.preventDefault();
                }
            }
        };
        
        modal.addEventListener('keydown', handleTabKey);
    }
};

// ==================== GLOBAL FUNCTION EXPORTS ====================
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
window.exportTrendData = () => {
    const period = parseInt(Utils.$('#trendPeriod')?.value) || 30;
    APIService.fetch('trend_data', { days: period }).then(data => {
        if (data && data.trend_data) {
            DataManager.exportToCSV(data.trend_data, 'sales_trend');
        }
    });
};

// ==================== APP INITIALIZATION ====================
const App = {
    async init() {
        console.log('🚀 Initializing Ultra-Enhanced Dashboard v3.0...');

        try {
            // Dependency checks
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded!');
                UIManager.showNotification('Chart library not loaded. Please refresh.', 'error');
                return;
            }

            // Initialize CSRF token
            await CSRFManager.fetchToken();

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
            
            // Setup keyboard shortcuts
            this.setupKeyboardShortcuts();

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
                if (e.target === modal) ModalManager.close();
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') ModalManager.close();
        });

        // Visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && AppState.lastDataUpdate) {
                const timeSinceUpdate = Date.now() - AppState.lastDataUpdate.getTime();
                if (timeSinceUpdate > 300000) {
                    console.log('Auto-refresh after visibility change');
                    this.loadAllData();
                }
            }
        });

        // Online/offline events
        window.addEventListener('online', () => {
            AppState.isOnline = true;
            UIManager.showNotification('Connection restored', 'success');
            this.loadAllData();
        });

        window.addEventListener('offline', () => {
            AppState.isOnline = false;
            UIManager.showNotification('No internet connection', 'warning', 6000);
        });

        // Prevent form submission on Enter
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                }
            });
        });
        
        // Beforeunload warning for unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (AppState.editingTargetId && Utils.$('#targetModal')?.classList.contains('active')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    },

    setupAutoRefresh() {
        setInterval(() => {
            if (!document.hidden && AppState.isOnline) {
                console.log('Auto-refresh KPI...');
                DataManager.loadKPISummary();
            }
        }, CONFIG.AUTO_REFRESH_INTERVAL);
    },
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+R or Cmd+R: Refresh data
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.loadAllData();
            }
            
            // Ctrl+N or Cmd+N: New target
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                ModalManager.open();
            }
            
            // Ctrl+1/2/3/4: Switch tabs
            if ((e.ctrlKey || e.metaKey) && ['1','2','3','4'].includes(e.key)) {
                e.preventDefault();
                const tabs = ['trend', 'comparison', 'targets', 'settings'];
                TabManager.switchTab(tabs[parseInt(e.key) - 1]);
            }
        });
    }
};

// ==================== STYLES ====================
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .kpi-value-display, .progress-bar-fill {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    *:focus-visible {
        outline: 2px solid #6366f1;
        outline-offset: 2px;
    }
    
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border-width: 0;
    }
    
    .notification-toast {
        animation: slideInRight 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
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
                    console.warn(`⚠️ Slow operation: ${entry.name} took ${entry.duration.toFixed(2)}ms`);
                }
            }
        });
        perfObserver.observe({ entryTypes: ['measure'] });
    } catch (e) {
        console.log('Performance monitoring unavailable');
    }
}

// ==================== SERVICE WORKER (OPTIONAL) ====================
if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw.js')
        .then(() => console.log('Service Worker registered'))
        .catch(err => console.log('SW registration failed:', err));
}

console.log('📊 Ultra-Enhanced Sales Dashboard v3.0 loaded successfully');