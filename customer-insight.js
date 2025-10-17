
// Customer Insights Dashboard Controller
class CustomerInsightsDashboard {
    constructor() {
        this.apiEndpoint = 'api/customer-insights.php';
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.currentSort = { field: 'id', order: 'asc' };
        this.dateRange = 'today';
        this.data = {};
        this.charts = {};
        this.init();
    }
    
    async init() {
        this.showLoadingState();
        await this.loadDashboardData();
        this.initializeEventListeners();
        this.startRealTimeUpdates();
        this.animateTypingText();
        this.hideLoadingState();
    }
    
    showLoadingState() {
        document.querySelectorAll('.ci-stat-loading').forEach(el => {
            el.style.display = 'inline';
        });
        document.querySelectorAll('.ci-card-inner').forEach(el => {
            el.classList.add('ci-skeleton');
        });
    }
    
    hideLoadingState() {
        document.querySelectorAll('.ci-stat-loading').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.ci-card-inner').forEach(el => {
            el.classList.remove('ci-skeleton');
        });
    }
    
    async loadDashboardData() {
        try {
            const params = this.getDateRangeParams();
            
            // Fetch all data in parallel
            const [metrics, loyalty, behavior, segmentation, engagement, performance, traffic] = await Promise.all([
                this.fetchData('metrics', params),
                this.fetchData('loyalty', params),
                this.fetchData('behavior', params),
                this.fetchData('segmentation', params),
                this.fetchData('engagement', params),
                this.fetchData('performance', params),
                this.fetchData('traffic', params)
            ]);
            
            this.data = {
                metrics,
                loyalty,
                behavior,
                segmentation,
                engagement,
                performance,
                traffic
            };
            
            this.updateDashboard();
            
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showAlert('Failed to load dashboard data', 'error');
        }
    }
    
    async fetchData(action, params = {}) {
        const url = new URL(this.apiEndpoint, window.location.origin);
        url.searchParams.append('action', action);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    }
    
    getDateRangeParams() {
        const today = new Date();
        let startDate, endDate = today.toISOString().split('T')[0];
        
        switch(this.dateRange) {
            case 'today':
                startDate = endDate;
                break;
            case 'week':
                startDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
                break;
            case 'month':
                startDate = new Date(today.setMonth(today.getMonth() - 1)).toISOString().split('T')[0];
                break;
            case 'quarter':
                startDate = new Date(today.setMonth(today.getMonth() - 3)).toISOString().split('T')[0];
                break;
            case 'year':
                startDate = new Date(today.setFullYear(today.getFullYear() - 1)).toISOString().split('T')[0];
                break;
            case 'custom':
                startDate = document.getElementById('ciStartDate').value;
                endDate = document.getElementById('ciEndDate').value;
                break;
            default:
                startDate = endDate;
        }
        
        return { start_date: startDate, end_date: endDate };
    }
    
    updateDashboard() {
        // Update header stats
        this.updateHeaderStats();
        
        // Update metric cards
        this.updateLoyaltyCard();
        this.updateBehaviorCard();
        this.updateSegmentationCard();
        this.updateEngagementCard();
        this.updatePerformanceCard();
        this.updateTrafficCard();
        
        // Update table
        this.updateCustomerTable();
        
        // Update charts
        this.updateCharts();
        
        // Update insights
        this.updateInsightsBar();
    }
    
    updateHeaderStats() {
        const metrics = this.data.metrics;
        
        // Active users
        const activeUsers = document.getElementById('activeUsers');
        if (activeUsers) {
            activeUsers.innerHTML = `<span class="ci-animated-number">${metrics.total_customers || 0}</span>`;
        }
        
        // Today's revenue  
        const todayRevenue = document.getElementById('todayRevenue');
        if (todayRevenue) {
            todayRevenue.innerHTML = `₱<span class="ci-animated-number">${this.formatCurrency(metrics.total_revenue || 0)}</span>`;
        }
        
        // Growth rate
        const growthRate = document.getElementById('growthRate');
        if (growthRate) {
            const growth = metrics.revenue_growth || 0;
            growthRate.innerHTML = `<span class="ci-animated-number ${growth > 0 ? 'ci-positive' : 'ci-negative'}">${growth > 0 ? '+' : ''}${growth}%</span>`;
        }
        
        this.animateNumbers();
    }
    
    updateLoyaltyCard() {
        const loyalty = this.data.loyalty;
        const metrics = this.data.metrics;
        
        // Retention rate
        const retentionEl = document.querySelector('#retentionRateValue');
        if (retentionEl) {
            retentionEl.innerHTML = `${Math.round(metrics.retention_rate || 0)}`;
        }
        
        // Loyal customers
        document.querySelector('#loyalCustomers').textContent = 
            loyalty.distribution?.find(d => d.customer_type === 'Loyal')?.count || 0;
        
        // At risk customers
        document.querySelector('#atRiskCustomers').textContent = 
            loyalty.customers?.filter(c => c.risk_level === 'High').length || 0;
        
        // New customers
        document.querySelector('#newCustomers').textContent = 
            loyalty.distribution?.find(d => d.customer_type === 'New')?.count || 0;
        
        // Create loyalty chart
        this.createLoyaltyChart(loyalty.distribution);
    }
    
    updateBehaviorCard() {
        const behavior = this.data.behavior;
        const metrics = this.data.metrics;
        
        // Average order value
        document.querySelector('#avgOrderValue').textContent = 
            `₱${this.formatCurrency(metrics.avg_order_value || 0)}`;
        
        // Items per order
        document.querySelector('#itemsPerOrder').textContent = 
            (metrics.avg_items_per_order || 0).toFixed(1);
        
        // Top products
        const topProductsList = document.getElementById('topProductsList');
        if (topProductsList && behavior.top_products) {
            topProductsList.innerHTML = behavior.top_products.slice(0, 5).map((product, index) => `
                <div class="ci-product-item">
                    <div class="ci-product-rank ci-rank-${index + 1}">${index + 1}</div>
                    <div class="ci-product-info">
                        <div class="ci-product-name">${product.product || 'Unknown'}</div>
                        <div class="ci-product-count">${product.order_count} orders</div>
                    </div>
                    <div class="ci-product-bar">
                        <div class="ci-product-fill" style="width: ${(product.order_count / behavior.top_products[0].order_count) * 100}%"></div>
                    </div>
                </div>
            `).join('');
        }
        
        // Create heatmap
        this.createPeakHoursHeatmap(behavior.time_analysis);
    }
    
    updateCustomerTable() {
        const customers = this.data.loyalty?.customers || [];
        const tbody = document.getElementById('customerTableBody');
        
        if (!tbody) return;
        
        // Sort customers
        const sorted = this.sortCustomers(customers);
        
        // Paginate
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const paginated = sorted.slice(start, end);
        
        // Render rows
        tbody.innerHTML = paginated.map(customer => `
            <tr>
                <td><input type="checkbox" class="ci-row-select" data-id="${customer.user_id}"></td>
                <td>${customer.user_id}</td>
                <td>${customer.customer_name || 'Customer ' + customer.user_id}</td>
                <td>${customer.visit_count}</td>
                <td>₱${this.formatCurrency(customer.total_spent)}</td>
                <td>${this.formatDate(customer.last_visit)}</td>
                <td>
                    <span class="ci-risk-badge ci-risk-${customer.risk_level.toLowerCase()}">
                        ${customer.risk_level}
                    </span>
                </td>
                <td>
                    <div class="ci-ltv-score">${this.calculateLTV(customer)}</div>
                </td>
                <td>
                    <div class="ci-action-buttons">
                        <button class="ci-btn-icon" onclick="dashboard.viewCustomer(${customer.user_id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="ci-btn-icon" onclick="dashboard.sendMessage(${customer.user_id})">
                            <i class="fas fa-envelope"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Update pagination
        this.updatePagination(sorted.length);
    }
    
    createLoyaltyChart(distribution) {
        const canvas = document.getElementById('loyaltyChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.loyalty) {
            this.charts.loyalty.destroy();
        }
        
        this.charts.loyalty = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: distribution.map(d => d.customer_type),
                datasets: [{
                    data: distribution.map(d => d.count),
                    backgroundColor: [
                        'rgba(5, 223, 215, 0.8)',
                        'rgba(8, 131, 149, 0.8)',
                        'rgba(10, 77, 104, 0.8)',
                        'rgba(251, 146, 60, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#90caf9',
                            padding: 15
                        }
                    }
                }
            }
        });
    }
    
    initializeEventListeners() {
        // Date range buttons
        document.querySelectorAll('.ci-date-preset').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const range = e.target.dataset.range;
                this.setDateRange(range);
            });
        });
        
        // Table sorting
        document.querySelectorAll('.ci-sortable').forEach(th => {
            th.addEventListener('click', (e) => {
                const field = e.currentTarget.dataset.sort;
                this.sortTable(field);
            });
        });
        
        // Search
        const searchInput = document.getElementById('customerSearch');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(() => {
                this.searchCustomers(searchInput.value);
            }, 300));
        }
        
        // FAB menu
        document.querySelector('.ci-fab-main')?.addEventListener('click', () => {
            document.querySelector('.ci-fab-container').classList.toggle('active');
        });
    }
    
    setDateRange(range) {
        this.dateRange = range;
        
        // Update UI
        document.querySelectorAll('.ci-date-preset').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.range === range);
        });
        
        // Show/hide custom date inputs
        const customDates = document.querySelector('.ci-custom-dates');
        if (customDates) {
            customDates.style.display = range === 'custom' ? 'flex' : 'none';
        }
        
        // Reload data
        if (range !== 'custom') {
            this.loadDashboardData();
        }
    }
    
    animateTypingText() {
        const element = document.querySelector('.ci-typing-animation');
        if (!element) return;
        
        const texts = [
            'Real-time insights for exceptional business intelligence',
            'AI-powered analytics at your fingertips',
            'Transform data into actionable insights',
            'Your command center for customer intelligence'
        ];
        
        let textIndex = 0;
        let charIndex = 0;
        let isDeleting = false;
        
        function type() {
            const currentText = texts[textIndex];
            
            if (!isDeleting) {
                element.textContent = currentText.substring(0, charIndex + 1);
                charIndex++;
                
                if (charIndex === currentText.length) {
                    isDeleting = true;
                    setTimeout(type, 2000);
                } else {
                    setTimeout(type, 50);
                }
            } else {
                element.textContent = currentText.substring(0, charIndex - 1);
                charIndex--;
                
                if (charIndex === 0) {
                    isDeleting = false;
                    textIndex = (textIndex + 1) % texts.length;
                    setTimeout(type, 500);
                } else {
                    setTimeout(type, 30);
                }
            }
        }
        
        type();
    }
    
    animateNumbers() {
        document.querySelectorAll('.ci-animated-number').forEach(el => {
            const target = parseFloat(el.textContent.replace(/[^0-9.-]/g, ''));
            const duration = 1000;
            const step = target / (duration / 16);
            let current = 0;
            
            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (el.textContent.includes('₱')) {
                    el.textContent = this.formatCurrency(current);
                } else if (el.textContent.includes('%')) {
                    el.textContent = `${current > 0 ? '+' : ''}${current.toFixed(1)}%`;
                } else {
                    el.textContent = Math.round(current).toLocaleString();
                }
            }, 16);
        });
    }
    
    startRealTimeUpdates() {
        // Update every 30 seconds
        setInterval(() => {
            this.loadDashboardData();
        }, 30000);
        
        // Simulate real-time sparklines
        this.updateSparklines();
        setInterval(() => {
            this.updateSparklines();
        }, 5000);
    }
    
    updateSparklines() {
        ['activeSparkline', 'revenueSparkline', 'growthSparkline'].forEach(id => {
            const container = document.getElementById(id);
            if (!container) return;
            
            // Generate random data for sparkline
            const data = Array.from({ length: 10 }, () => Math.random() * 100);
            
            container.innerHTML = `
                <svg viewBox="0 0 100 30" width="100" height="30">
                    <polyline
                        points="${data.map((d, i) => `${i * 11},${30 - d * 0.3}`).join(' ')}"
                        fill="none"
                        stroke="rgba(5, 223, 215, 0.6)"
                        stroke-width="2"
                    />
                </svg>
            `;
        });
    }
    
    // Utility functions
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }
    
    formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    calculateLTV(customer) {
        const score = (customer.visit_count * 10) + (customer.total_spent / 100);
        return Math.min(100, Math.round(score));
    }
    
    sortCustomers(customers) {
        return customers.sort((a, b) => {
            const aVal = a[this.currentSort.field];
            const bVal = b[this.currentSort.field];
            
            if (this.currentSort.order === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });
    }
    
    showAlert(message, type = 'info') {
        const alertEl = document.querySelector('.ci-insight-alert');
        if (alertEl) {
            alertEl.className = `ci-insight-alert ci-alert-${type} active`;
            document.getElementById('alertMessage').textContent = message;
        }
    }
}

// Utility function for debouncing
function debounce(func, wait) {
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

// Initialize dashboard
let dashboard;
document.addEventListener('DOMContentLoaded', () => {
    dashboard = new CustomerInsightsDashboard();
});

// Global functions for onclick handlers
function refreshDashboard() {
    dashboard.loadDashboardData();
}

function exportReport() {
    // Implementation for export
    console.log('Exporting report...');
}

function toggleDarkMode() {
    document.querySelector('.ci-page').classList.toggle('ci-dark-mode');
}

function applyCustomDateRange() {
    dashboard.dateRange = 'custom';
    dashboard.loadDashboardData();
}

function viewCustomer(id) {
    console.log('Viewing customer:', id);
    // Open modal with customer details
}

function sendMessage(id) {
    console.log('Sending message to customer:', id);
    // Open messaging modal
}
