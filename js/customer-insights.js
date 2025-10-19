class CustomerInsights {
    constructor() {
        this.apiBase = 'api/customer-insights.php';
        this.init();
    }
    
    async init() {
        try {
            await this.loadAllData();
            this.setupEventListeners();
            console.log('✅ Customer Insights loaded');
        } catch (error) {
            console.error('❌ Error:', error);
            this.showError('Failed to load data');
        }
    }
    
    async loadAllData() {
        this.showLoading();
        
        try {
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
        const response = await fetch(`${this.apiBase}?action=${action}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
    }
    
    renderLoyalCustomers(customers) {
        const container = document.querySelector('.customer-intelligence-grid');
        if (!container || !customers || customers.length === 0) {
            if (container) container.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;">No loyal customers data</div>';
            return;
        }
        
        container.innerHTML = customers.map(customer => `
            <div class="customer-profile-item ${customer.rank <= 2 ? 'vip' : ''}">
                <div class="profile-rank">
                    <div class="rank-badge ${customer.rank === 1 ? 'gold' : customer.rank === 2 ? 'gold' : 'silver'}">${customer.rank}</div>
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
                            <polyline points="${customer.trend}" fill="none" stroke="${this.getChartColor(customer.rank)}" stroke-width="2"/>
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
    
    renderRetentionAnalytics(analytics) {
        if (!analytics) return;
        
        const weekEl = document.querySelector('[data-dropped-week]');
        const monthEl = document.querySelector('[data-dropped-month]');
        if (weekEl) weekEl.textContent = analytics.dropped_this_week || 0;
        if (monthEl) monthEl.textContent = analytics.dropped_this_month || 0;
        
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
        
        const riskList = document.querySelector('[data-risk-customers]');
        if (riskList) {
            if (analytics.at_risk_customers && analytics.at_risk_customers.length > 0) {
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
            } else {
                riskList.innerHTML = '<div style="text-align: center; padding: 20px; color: #64748b;">No high-value at-risk customers</div>';
            }
        }
        
        const alertValue = document.querySelector('[data-risk-total]');
        if (alertValue) {
            const totalAtRisk = analytics.health_segments.at_risk + analytics.health_segments.critical;
            alertValue.textContent = `${totalAtRisk} At Risk`;
        }
    }
    
    renderPurchaseIntelligence(intelligence) {
        if (!intelligence) return;
        
        const basketSize = document.querySelector('[data-basket-size]');
        const avgTrans = document.querySelector('[data-avg-transaction]');
        if (basketSize) basketSize.textContent = intelligence.avg_basket_size + ' items';
        if (avgTrans) avgTrans.textContent = '₱' + this.formatNumber(intelligence.avg_transaction);
        
        const topProductsList = document.querySelector('[data-top-products]');
        if (topProductsList && intelligence.top_products && intelligence.top_products.length > 0) {
            topProductsList.innerHTML = intelligence.top_products.map((product, index) => `
                <div class="combo-item">
                    <div class="combo-rank">${index + 1}</div>
                    <div class="combo-products"><span class="product">${product.product}</span></div>
                    <div class="combo-stats">
                        <span class="frequency">${product.order_count} orders</span>
                        <span class="revenue">₱${this.formatNumber(product.revenue)}</span>
                    </div>
                </div>
            `).join('');
        }
        
        const repeatList = document.querySelector('[data-repeat-products]');
        if (repeatList && intelligence.repeat_rate_products && intelligence.repeat_rate_products.length > 0) {
            repeatList.innerHTML = intelligence.repeat_rate_products.map((product, index) => `
                <div class="combo-item">
                    <div class="combo-rank">${index + 1}</div>
                    <div class="combo-products"><span class="product">${product.product}</span></div>
                    <div class="combo-stats">
                        <span class="frequency">Repeat Rate: ${product.repeat_rate}%</span>
                        <span class="revenue">${product.unique_customers} customers</span>
                    </div>
                </div>
            `).join('');
        }
    }
    
    renderChurnSegments(segments) {
        if (!segments) return;
        
        const genderEl = document.querySelector('[data-churn-gender]');
        if (genderEl && segments.by_gender && segments.by_gender.length > 0) {
            genderEl.innerHTML = segments.by_gender.map(seg => `
                <div class="detail-row">
                    <span>${seg.gender}</span>
                    <span style="color: #dc2626;">${seg.churn_rate}% churn</span>
                </div>
            `).join('');
        }
        
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
    
    renderExecutiveSummary(summary) {
        if (!summary) return;
        const customersEl = document.querySelector('[data-total-customers]');
        const revenueEl = document.querySelector('[data-monthly-revenue]');
        if (customersEl) customersEl.textContent = this.formatNumber(summary.total_customers);
        if (revenueEl) revenueEl.textContent = '₱' + this.formatNumber(summary.monthly_revenue);
    }
    
    viewCustomerDetails(customerName) {
        alert(`Viewing: ${customerName}\n\nWould open customer profile modal.`);
    }
    
    exportInsights(format) {
        alert(`Exporting as ${format.toUpperCase()}...`);
    }
    
    formatNumber(num) {
        return new Intl.NumberFormat('en-PH').format(Math.round(num));
    }
    
    getChartColor(rank) {
        return {1: '#05dfd7', 2: '#088395', 3: '#0a4d68'}[rank] || '#64748b';
    }
    
    showLoading() { console.log('Loading...'); }
    hideLoading() { console.log('Loaded'); }
    showError(msg) { alert('Error: ' + msg); }
    
    setupEventListeners() {
        document.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.exportInsights(e.currentTarget.getAttribute('data-export'));
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('cust-insight')) {
        window.customerInsights = new CustomerInsights();
    }
});

setInterval(() => {
    if (window.customerInsights) window.customerInsights.loadAllData();
}, 300000); // 5 minutes