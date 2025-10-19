// =============================================
// CUSTOMER INSIGHTS - MAIN APPLICATION
// =============================================

class CustomerInsights {
    constructor() {
        this.apiBase = 'api/customer-insights.php';
        this.init();
    }
    
    async init() {
        try {
            await this.loadAllData();
            this.setupEventListeners();
            console.log('✅ Customer Insights loaded successfully');
        } catch (error) {
            console.error('❌ Error loading Customer Insights:', error);
            this.showError('Failed to load customer insights data');
        }
    }
    
    async loadAllData() {
        // Show loading state
        this.showLoading();
        
        try {
            // Load all data in parallel
            const [
                loyalCustomers,
                retentionAnalytics,
                purchaseIntelligence,
                churnSegments,
                executiveSummary
            ] = await Promise.all([
                this.fetchData('loyal_customers'),
                this.fetchData('retention_analytics'),
                this.fetchData('purchase_intelligence'),
                this.fetchData('churn_segments'),
                this.fetchData('executive_summary')
            ]);
            
            // Render each section
            this.renderLoyalCustomers(loyalCustomers);
            this.renderRetentionAnalytics(retentionAnalytics);
            this.renderPurchaseIntelligence(purchaseIntelligence);
            this.renderChurnSegments(churnSegments);
            this.renderExecutiveSummary(executiveSummary);
            
        } catch (error) {
            throw error;
        } finally {
            this.hideLoading();
        }
    }
    
    async fetchData(action) {
    try {
        const response = await fetch(`${this.apiBase}?action=${action}`);
        
        // Handle authentication errors
        if (response.status === 401) {
            alert('Your session has expired. Please log in again.');
            window.location.href = '/login.php'; // Adjust to your login page
            return null;
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        // Check for success
        if (result.success === false) {
            throw new Error(result.message || 'Failed to fetch data');
        }
        
        return result.data;
        
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}
    
    // =============================================
    // RENDER: Loyal Customers
    // =============================================
    renderLoyalCustomers(customers) {
        const container = document.querySelector('.customer-intelligence-grid');
        if (!container || !customers || customers.length === 0) {
            if (container) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;">No loyal customers data available</div>';
            }
            return;
        }
        
        container.innerHTML = customers.map(customer => `
            <div class="customer-profile-item ${customer.rank <= 2 ? 'vip' : ''}">
                <div class="profile-rank">
                    <div class="rank-badge ${customer.rank === 1 ? 'gold' : customer.rank === 2 ? 'gold' : 'silver'}">
                        ${customer.rank}
                    </div>
                </div>
                <div class="profile-identity">
                    <div class="profile-avatar">${customer.initials}</div>
                    <div class="profile-details">
                        <div class="profile-name">${customer.customer_name}</div>
                    </div>
                </div>
                <div class="profile-metrics">
                    <div class="metric-item">
                        <span class="label">Monthly Avg</span>
                        <span class="value">₱${this.formatNumber(customer.monthly_avg)}</span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Visit Frequency</span>
                        <span class="value">${customer.monthly_visits}x/month</span>
                    </div>
                    <div class="metric-item">
                        <span class="label">Last Visit</span>
                        <span class="value">${customer.last_visit_formatted}</span>
                    </div>
                </div>
                <div class="profile-behavior">
                    <div class="behavior-chart">
                        <svg viewBox="0 0 100 40" class="mini-chart">
                            <polyline points="${customer.trend}" 
                                      fill="none" stroke="${this.getChartColor(customer.rank)}" stroke-width="2"/>
                        </svg>
                    </div>
                </div>
                <div class="profile-actions">
                    <button class="btn-icon" title="View Details" onclick="window.customerInsights.viewCustomerDetails('${customer.customer_name}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    // =============================================
    // RENDER: Retention Analytics
    // =============================================
    renderRetentionAnalytics(analytics) {
        if (!analytics) return;
        
        // Update dropped visits using data attributes
        const weekEl = document.querySelector('[data-dropped-week]');
        const monthEl = document.querySelector('[data-dropped-month]');
        
        if (weekEl) weekEl.textContent = analytics.dropped_this_week || 0;
        if (monthEl) monthEl.textContent = analytics.dropped_this_month || 0;
        
        // Update health segments using data attributes
        const healthLow = document.querySelector('[data-health-low]');
        const healthLowPct = document.querySelector('[data-health-low-pct]');
        const healthMed = document.querySelector('[data-health-medium]');
        const healthMedPct = document.querySelector('[data-health-medium-pct]');
        const healthHigh = document.querySelector('[data-health-high]');
        const healthHighPct = document.querySelector('[data-health-high-pct]');
        
        if (healthLow) healthLow.textContent = this.formatNumber(analytics.health_segments.healthy);
        if (healthLowPct) healthLowPct.textContent = (analytics.health_percentages?.healthy || 0) + '%';
        if (healthMed) healthMed.textContent = this.formatNumber(analytics.health_segments.at_risk);
        if (healthMedPct) healthMedPct.textContent = (analytics.health_percentages?.at_risk || 0) + '%';
        if (healthHigh) healthHigh.textContent = this.formatNumber(analytics.health_segments.critical);
        if (healthHighPct) healthHighPct.textContent = (analytics.health_percentages?.critical || 0) + '%';
        
        // Update at-risk customers list
        const riskList = document.querySelector('[data-risk-customers]');
        if (riskList && analytics.at_risk_customers && analytics.at_risk_customers.length > 0) {
            riskList.innerHTML = analytics.at_risk_customers.map(customer => `
                <div class="risk-customer">
                    <div class="customer-info">
                        <div class="customer-avatar">${customer.initials}</div>
                        <div class="customer-details">
                            <div class="customer-name">${customer.customer_name}</div>
                            <div class="customer-meta">LTV: ${customer.ltv_formatted} | Last: ${customer.days_inactive} days ago</div>
                        </div>
                    </div>
                </div>
            `).join('');
        } else if (riskList) {
            riskList.innerHTML = '<div style="text-align: center; padding: 20px; color: #64748b;">No high-value at-risk customers</div>';
        }
        
        // Update total at risk indicator
        const alertValue = document.querySelector('[data-risk-total]');
        if (alertValue) {
            const totalAtRisk = analytics.health_segments.at_risk + analytics.health_segments.critical;
            alertValue.textContent = `${totalAtRisk} At Risk`;
        }
    }
    
    // =============================================
    // RENDER: Purchase Intelligence
    // =============================================
    renderPurchaseIntelligence(intelligence) {
        if (!intelligence) return;
        
        // Update overview stats using data attributes
        const basketSize = document.querySelector('[data-basket-size]');
        const avgTrans = document.querySelector('[data-avg-transaction]');
        
        if (basketSize) basketSize.textContent = intelligence.avg_basket_size + ' items';
        if (avgTrans) avgTrans.textContent = '₱' + this.formatNumber(intelligence.avg_transaction);
        
        // Update top products
        const topProductsList = document.querySelector('[data-top-products]');
        if (topProductsList && intelligence.top_products && intelligence.top_products.length > 0) {
            topProductsList.innerHTML = intelligence.top_products.map((product, index) => `
                <div class="combo-item">
                    <div class="combo-rank">${index + 1}</div>
                    <div class="combo-products">
                        <span class="product">${product.product}</span>
                    </div>
                    <div class="combo-stats">
                        <span class="frequency">${product.order_count} orders</span>
                        <span class="revenue">₱${this.formatNumber(product.revenue)}</span>
                    </div>
                </div>
            `).join('');
        }
        
        // Update repeat purchase rates
        const repeatList = document.querySelector('[data-repeat-products]');
        if (repeatList && intelligence.repeat_rate_products && intelligence.repeat_rate_products.length > 0) {
            repeatList.innerHTML = intelligence.repeat_rate_products.map((product, index) => `
                <div class="combo-item">
                    <div class="combo-rank">${index + 1}</div>
                    <div class="combo-products">
                        <span class="product">${product.product}</span>
                    </div>
                    <div class="combo-stats">
                        <span class="frequency">Repeat Rate: ${product.repeat_rate}%</span>
                        <span class="revenue">${product.unique_customers} customers</span>
                    </div>
                </div>
            `).join('');
        }
    }
    
    // =============================================
    // RENDER: Churn Segments
    // =============================================
    renderChurnSegments(segments) {
        if (!segments) return;
        
        // Update gender segments
        const genderEl = document.querySelector('[data-churn-gender]');
        if (genderEl && segments.by_gender && segments.by_gender.length > 0) {
            genderEl.innerHTML = segments.by_gender.map(seg => `
                <div class="detail-row">
                    <span>${seg.gender}</span>
                    <span style="color: #dc2626;">${seg.churn_rate}% churn</span>
                </div>
            `).join('');
        }
        
        // Update category segments
        const categoryEl = document.querySelector('[data-churn-category]');
        if (categoryEl && segments.by_category && segments.by_category.length > 0) {
            categoryEl.innerHTML = segments.by_category.map(seg => `
                <div class="detail-row">
                    <span>${seg.category}</span>
                    <span style="color: #dc2626;">${seg.churn_rate}% churn</span>
                </div>
            `).join('');
        }
    }
    
    // =============================================
    // RENDER: Executive Summary
    // =============================================
    renderExecutiveSummary(summary) {
        if (!summary) return;
        
        const customersEl = document.querySelector('[data-total-customers]');
        const revenueEl = document.querySelector('[data-monthly-revenue]');
        
        if (customersEl) customersEl.textContent = this.formatNumber(summary.total_customers);
        if (revenueEl) revenueEl.textContent = '₱' + this.formatNumber(summary.monthly_revenue);
    }
    
    // =============================================
    // PUBLIC METHODS
    // =============================================
    viewCustomerDetails(customerName) {
        alert(`Viewing details for: ${customerName}\n\nThis would open a detailed customer profile modal.`);
        // TODO: Implement modal or redirect to customer detail page
    }
    
    exportInsights(format) {
        if (format === 'pdf') {
            alert('Exporting Customer Insights Report as PDF...\n\nReport includes:\n- Loyal Customer Analysis\n- Retention & Risk Metrics\n- Purchase Intelligence\n- Churn Rate by Segment');
        } else if (format === 'excel') {
            alert('Exporting Customer Insights Report as Excel...\n\nSpreadsheet will include:\n- Customer Lists & Metrics\n- Segment Analysis Data\n- Product Performance Data');
        }
        // TODO: Implement actual export functionality using jsPDF or SheetJS
    }
    
    // =============================================
    // HELPER METHODS
    // =============================================
    formatNumber(num) {
        return new Intl.NumberFormat('en-PH').format(Math.round(num));
    }
    
    getChartColor(rank) {
        const colors = {
            1: '#05dfd7',
            2: '#088395',
            3: '#0a4d68'
        };
        return colors[rank] || '#64748b';
    }
    
    showLoading() {
        console.log('Loading data...');
        // TODO: Add loading spinner overlay if desired
    }
    
    hideLoading() {
        console.log('Data loaded');
    }
    
    showError(message) {
        console.error(message);
        alert('Error: ' + message);
    }
    
    setupEventListeners() {
        // Export buttons
        document.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const format = e.currentTarget.getAttribute('data-export');
                this.exportInsights(format);
            });
        });
        
        // Refresh button if exists
        const refreshBtn = document.querySelector('[data-refresh="insights"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadAllData());
        }
    }
}

// =============================================
// INITIALIZE ON PAGE LOAD
// =============================================

document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on the customer insights page
    if (document.getElementById('cust-insight')) {
        window.customerInsights = new CustomerInsights();
    }
});

// Auto-refresh every 5 minutes
setInterval(() => {
    if (window.customerInsights) {
        console.log('Auto-refreshing customer insights...');
        window.customerInsights.loadAllData();
    }
}, 3000); // 5 minutes