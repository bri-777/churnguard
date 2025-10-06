/**
 * Fixed Customer Monitoring Dashboard with Date Filtering
 * Works with actual database data
 */

class CustomerMonitoringDashboard {
    constructor() {
        this.apiUrl = 'api/customer_monitoring.php';
        this.refreshTimer = null;
        this.isLoading = false;
        this.lastUpdate = null;
        this.chartInstance = null;
        this.dateRange = '14days';
        this.refreshInterval = 30000; // 30 seconds
        this.historicalData = [];
        this.currentFilterRange = '14days'; // Track current filter
        
        console.log('Dashboard initialized');
    }

    async init() {
        try {
            console.log('Starting dashboard...');
            this.showLoading();
            await this.loadData();
            this.initChart();
            this.startAutoRefresh();
            this.setupEvents();
            console.log('Dashboard ready');
        } catch (error) {
            console.error('Init failed:', error);
            this.showError(error.message);
        }
    }

    async loadData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        console.log('Loading data from API...');

        try {
            const response = await fetch(this.apiUrl + '?t=' + Date.now(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            });

            console.log('API Response status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('API Data received:', data);

            if (!data.success) {
                throw new Error(data.message || 'API returned error');
            }

            this.historicalData = data.historicalData || [];
            this.updateMetrics(data);
            // Apply current filter instead of showing all data
            this.filterHistoricalData(this.currentFilterRange);
            this.updateChart(data);
            
            this.lastUpdate = new Date();
            this.updateLastUpdateTime();
            
            console.log('Data loaded successfully, records:', this.historicalData.length);
            
        } catch (error) {
            console.error('Load data error:', error);
            this.showError(error.message);
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }

    updateMetrics(data) {
        console.log('Updating metrics with:', data);
        
        // Update metric values
        this.setElement('todayCustomerCount', this.formatNumber(data.todayTraffic || 0));
        this.setElement('yesterdayCustomerCount', this.formatNumber(data.yesterdayTraffic || 0));
        this.setElement('avgCustomerTraffic14Days', this.formatNumber(data.traffic14DayAvg || 0));
        this.setElement('todayRevenueAmount', this.formatCurrency(data.todayRevenue || 0));
        this.setElement('currentChurnRiskLevel', data.riskLevel || 'Low');
        this.setElement('atRiskCustomerCount', this.formatNumber(data.atRiskCustomers || 0));

        // Update trends
        this.updateTrend('todayTrafficTrend', data.trafficTrend || 0, 'vs 14-day avg');
        this.updateTrend('todayRevenueTrend', data.revenueTrend || 0, 'vs 14-day avg');
        
        // Update additional trend indicators
        this.updateSimpleTrend('yesterdayTrafficTrend', data.yesterdayTraffic, data.traffic14DayAvg, 'vs avg');
        this.updateSimpleTrend('avgTrafficTrend', data.traffic14DayAvg, null, 'baseline');
        this.updateSimpleTrend('atRiskTrend', data.atRiskCustomers, null, 'active');
        
        // Update risk badge
        this.updateRiskBadge(data.riskLevel, data.riskPercentage);
    }

    setElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        } else {
            console.warn('Element not found:', id);
        }
    }

    updateTrend(elementId, trendValue, suffix = '') {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn('Trend element not found:', elementId);
            return;
        }

        const trend = parseFloat(trendValue) || 0;
        const sign = trend >= 0 ? '+' : '';
        const trendText = `${sign}${trend.toFixed(1)}% ${suffix}`;

        // Remove existing classes
        element.classList.remove('trend-up', 'trend-down', 'trend-neutral');

        const iconSpan = element.querySelector('span:first-child');
        const textSpan = element.querySelector('span:last-child');

        if (trend > 5) {
            element.classList.add('trend-up');
            if (iconSpan) iconSpan.textContent = '↗';
            if (textSpan) textSpan.textContent = trendText;
        } else if (trend < -5) {
            element.classList.add('trend-down');
            if (iconSpan) iconSpan.textContent = '↘';
            if (textSpan) textSpan.textContent = trendText;
        } else {
            element.classList.add('trend-neutral');
            if (iconSpan) iconSpan.textContent = '→';
            if (textSpan) textSpan.textContent = `${trendText} (Stable)`;
        }
    }

    updateSimpleTrend(elementId, currentValue, compareValue, type) {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn('Simple trend element not found:', elementId);
            return;
        }

        const iconSpan = element.querySelector('span:first-child');
        const textSpan = element.querySelector('span:last-child');

        if (!iconSpan || !textSpan) {
            console.warn('Trend spans not found in element:', elementId);
            return;
        }

        if (type === 'baseline') {
            iconSpan.textContent = '—';
            textSpan.textContent = 'Baseline average';
        } else if (type === 'active') {
            iconSpan.textContent = '⚠';
            textSpan.textContent = 'Active monitoring';
        } else if (currentValue && compareValue) {
            const trend = ((currentValue - compareValue) / compareValue) * 100;
            const sign = trend >= 0 ? '+' : '';
            
            if (trend > 5) {
                iconSpan.textContent = '↗';
                textSpan.textContent = `${sign}${trend.toFixed(1)}% ${type}`;
                element.classList.remove('trend-down', 'trend-neutral');
                element.classList.add('trend-up');
            } else if (trend < -5) {
                iconSpan.textContent = '↘';
                textSpan.textContent = `${sign}${trend.toFixed(1)}% ${type}`;
                element.classList.remove('trend-up', 'trend-neutral');
                element.classList.add('trend-down');
            } else {
                iconSpan.textContent = '→';
                textSpan.textContent = `${sign}${trend.toFixed(1)}% ${type}`;
                element.classList.remove('trend-up', 'trend-down');
                element.classList.add('trend-neutral');
            }
        } else {
            iconSpan.textContent = '—';
            textSpan.textContent = 'No comparison data';
        }
    }

    updateRiskBadge(riskLevel, riskPercentage) {
        const badge = document.getElementById('churnRiskBadge');
        if (!badge) return;

        badge.classList.remove('status-low', 'status-medium', 'status-high');
        
        const percentage = parseFloat(riskPercentage) || 0;
        
        if (riskLevel === 'High' || percentage >= 70) {
            badge.classList.add('status-high');
            badge.textContent = 'High Risk';
        } else if (riskLevel === 'Medium' || percentage >= 40) {
            badge.classList.add('status-medium');
            badge.textContent = 'Medium Risk';
        } else {
            badge.classList.add('status-low');
            badge.textContent = 'Low Risk';
        }
    }

    // DATE FILTERING METHODS
    filterHistoricalData(dateRange) {
        this.currentFilterRange = dateRange; // Save current filter
        
        if (!this.historicalData || this.historicalData.length === 0) {
            console.warn('No historical data to filter');
            const tableBody = document.getElementById('historicalAnalysisTableBody');
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="6" class="no-data">No data available</td></tr>';
            }
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        let filteredData = [];
        let rangeText = '';

        switch(dateRange) {
            case 'today':
                const todayStr = today.toISOString().split('T')[0];
                filteredData = this.historicalData.filter(record => record.date === todayStr);
                rangeText = 'Today';
                break;
                
            case '7days':
                const sevenDaysAgo = new Date(today);
                sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 6);
                filteredData = this.historicalData.filter(record => {
                    const recordDate = new Date(record.date);
                    return recordDate >= sevenDaysAgo && recordDate <= today;
                });
                rangeText = 'Last 7 days';
                break;
                
            case '14days':
                const fourteenDaysAgo = new Date(today);
                fourteenDaysAgo.setDate(fourteenDaysAgo.getDate() - 13);
                filteredData = this.historicalData.filter(record => {
                    const recordDate = new Date(record.date);
                    return recordDate >= fourteenDaysAgo && recordDate <= today;
                });
                rangeText = 'Last 14 days';
                break;
                
            case '30days':
                const thirtyDaysAgo = new Date(today);
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 29);
                filteredData = this.historicalData.filter(record => {
                    const recordDate = new Date(record.date);
                    return recordDate >= thirtyDaysAgo && recordDate <= today;
                });
                rangeText = 'Last 30 days';
                break;
                
            default:
                filteredData = this.historicalData;
                rangeText = 'All data';
        }

        // Sort by date descending (newest first)
        filteredData.sort((a, b) => new Date(b.date) - new Date(a.date));

        // Update the display
        this.updateFilteredTable(filteredData);
        
        // Update range text
        const rangeDisplay = document.getElementById('currentAnalysisDataRange');
        if (rangeDisplay) {
            rangeDisplay.textContent = rangeText;
        }
        
        // Update active button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.range === dateRange) {
                btn.classList.add('active');
            }
        });
        
        console.log(`Filtered to ${dateRange}: ${filteredData.length} records`);
    }

    updateFilteredTable(filteredData) {
        const tableBody = document.getElementById('historicalAnalysisTableBody');
        if (!tableBody) return;

        if (!filteredData || filteredData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="no-data">No data available for selected date range</td></tr>';
            return;
        }

        let rows = '';
        
        filteredData.forEach((record, index) => {
            const date = new Date(record.date);
            const dateStr = date.toLocaleDateString('en-PH');
            
            const traffic = parseInt(record.customer_traffic) || 0;
            const revenue = parseFloat(record.sales_volume) || 0;
            const transactions = parseInt(record.receipt_count) || 0;
            
            let riskLevel = record.risk_level || 'Low';
            let riskPercentage = parseFloat(record.risk_percentage) || 0;
            let hasPrediction = record.has_real_prediction === true;
            
            riskLevel = riskLevel.charAt(0).toUpperCase() + riskLevel.slice(1).toLowerCase();
            
            // Calculate trend
            let trendClass = 'trend-neutral';
            let trendText = '→ Stable';
            
            if (index === 0) {
                if (riskLevel === 'High') {
                    trendClass = 'trend-down';
                    trendText = '🚨 High Risk';
                } else if (riskLevel === 'Medium') {
                    trendClass = 'trend-warning';
                    trendText = '⚠️ Medium Risk';
                } else {
                    trendClass = 'trend-up';
                    trendText = '✔️ Low Risk';
                }
            } else {
                const prevRecord = filteredData[index - 1];
                const prevRisk = (prevRecord.risk_level || 'Low').charAt(0).toUpperCase() + (prevRecord.risk_level || 'Low').slice(1).toLowerCase();
                
                const riskValues = { 'Low': 1, 'Medium': 2, 'High': 3 };
                const currentVal = riskValues[riskLevel] || 1;
                const prevVal = riskValues[prevRisk] || 1;
                
                if (currentVal > prevVal) {
                    trendClass = 'trend-down';
                    trendText = '↗️ Risk Increased';
                } else if (currentVal < prevVal) {
                    trendClass = 'trend-up';
                    trendText = '↘️ Risk Decreased';
                } else {
                    if (riskLevel === 'High') {
                        trendClass = 'trend-down';
                        trendText = '🚨 High Risk Ongoing';
                    } else if (riskLevel === 'Medium') {
                        trendClass = 'trend-warning';
                        trendText = '⚠️ Medium Risk Ongoing';
                    } else {
                        trendClass = 'trend-up';
                        trendText = '✔️ Low Risk Stable';
                    }
                }
            }
            
            const badgeClass = `status-badge status-${riskLevel.toLowerCase()}`;
            const displayText = hasPrediction ? riskLevel : `${riskLevel}*`;
            
            rows += `
                <tr class="table-row-${riskLevel.toLowerCase()}">
                    <td>${dateStr}</td>
                    <td>${this.formatNumber(traffic)}</td>
                    <td>${this.formatCurrency(revenue)}</td>
                    <td>${this.formatNumber(transactions)}</td>
                    <td><span class="${badgeClass}">${displayText}</span></td>
                    <td class="${trendClass}">${trendText}</td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = rows;
        this.addAccurateStyles();
    }

    applyCustomDateRange() {
        const startDateInput = document.getElementById('customStartDate');
        const endDateInput = document.getElementById('customEndDate');
        
        if (!startDateInput.value || !endDateInput.value) {
            alert('Please select both start and end dates');
            return;
        }
        
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        startDate.setHours(0, 0, 0, 0);
        endDate.setHours(23, 59, 59, 999);
        
        if (startDate > endDate) {
            alert('Start date must be before end date');
            return;
        }
        
        const filteredData = this.historicalData.filter(record => {
            const recordDate = new Date(record.date);
            return recordDate >= startDate && recordDate <= endDate;
        });
        
        // Sort by date descending
        filteredData.sort((a, b) => new Date(b.date) - new Date(a.date));
        
        this.updateFilteredTable(filteredData);
        
        const rangeDisplay = document.getElementById('currentAnalysisDataRange');
        if (rangeDisplay) {
            rangeDisplay.textContent = `${startDate.toLocaleDateString('en-PH')} - ${endDate.toLocaleDateString('en-PH')}`;
        }
        
        // Update active button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.range === 'custom') {
                btn.classList.add('active');
            }
        });
        
        // Save current filter
        this.currentFilterRange = 'custom';
        
        // Hide custom picker
        const picker = document.getElementById('customDateRangeSelector');
        if (picker) picker.style.display = 'none';
        
        console.log(`Custom range applied: ${filteredData.length} records`);
    }

    addAccurateStyles() {
        if (!document.getElementById('accurateRiskStyles')) {
            const style = document.createElement('style');
            style.id = 'accurateRiskStyles';
            style.textContent = `
                .status-badge {
                    padding: 4px 8px;
                    border-radius: 8px;
                    font-size: 11px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .status-high {
                    background: #fee2e2;
                    color: #dc2626;
                    border: 1px solid #fca5a5;
                }
                .status-medium {
                    background: #fef3c7;
                    color: #d97706;
                    border: 1px solid #fcd34d;
                }
                .status-low {
                    background: #dcfce7;
                    color: #16a34a;
                    border: 1px solid #86efac;
                }
                .trend-warning { color: #d97706; font-weight: bold; }
                .trend-down { color: #dc2626; font-weight: bold; }
                .trend-up { color: #16a34a; font-weight: bold; }
                .trend-neutral { color: #6b7280; }
                .table-row-high { background: rgba(239, 68, 68, 0.05); }
                .table-row-medium { background: rgba(245, 158, 11, 0.05); }
                .table-row-low { background: rgba(34, 197, 94, 0.03); }
            `;
            document.head.appendChild(style);
        }
    }

    initChart() {
        const canvas = document.getElementById('trafficChurnChart');
        if (!canvas) {
            console.warn('Chart canvas not found');
            return;
        }

        const ctx = canvas.getContext('2d');
        
        if (this.chartInstance) {
            this.chartInstance.destroy();
        }

        this.chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.getChartLabels(),
                datasets: [
                    {
                        label: 'Customer Traffic',
                        data: this.getTrafficData(),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue (₱)',
                        data: this.getRevenueData(),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        pointRadius: 5,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Customer Traffic & Revenue Analysis',
                        font: { size: 16, weight: 'bold' }
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: { display: true, text: 'Date' }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Customer Traffic' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Revenue (₱)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    getChartLabels() {
        if (this.dateRange === 'today') {
            return ['Morning', 'Swing', 'Graveyard'];
        }
        
        if (!this.historicalData || this.historicalData.length === 0) return [];
        
        let days;
        if (this.dateRange === '7days') {
            days = 7;
        } else {
            days = 14;
        }
        
        const dataToUse = this.historicalData.slice(-days);
        
        return dataToUse.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
        });
    }

    getTrafficData() {
        if (this.dateRange === 'today') {
            if (!this.historicalData || this.historicalData.length === 0) return [0, 0, 0];
            
            const today = new Date().toISOString().split('T')[0];
            let todayRecord = this.historicalData.find(record => record.date === today);
            
            if (!todayRecord) {
                todayRecord = this.historicalData[0];
            }
            
            if (!todayRecord) return [0, 0, 0];
            
            const morningTraffic = parseInt(todayRecord.morning_receipt_count) || 0;
            const swingTraffic = parseInt(todayRecord.swing_receipt_count) || 0;
            const graveyardTraffic = parseInt(todayRecord.graveyard_receipt_count) || 0;
            
            return [morningTraffic, swingTraffic, graveyardTraffic];
        }
        
        if (!this.historicalData || this.historicalData.length === 0) return [];
        
        let days = this.dateRange === '7days' ? 7 : 14;
        const dataToUse = this.historicalData.slice(-days);
        return dataToUse.map(item => parseInt(item.customer_traffic) || 0);
    }

    getRevenueData() {
        if (this.dateRange === 'today') {
            if (!this.historicalData || this.historicalData.length === 0) return [0, 0, 0];
            
            const today = new Date().toISOString().split('T')[0];
            let todayRecord = this.historicalData.find(record => record.date === today);
            
            if (!todayRecord) {
                todayRecord = this.historicalData[0];
            }
            
            if (!todayRecord) return [0, 0, 0];
            
            const morningRevenue = parseFloat(todayRecord.morning_sales_volume) || 0;
            const swingRevenue = parseFloat(todayRecord.swing_sales_volume) || 0;
            const graveyardRevenue = parseFloat(todayRecord.graveyard_sales_volume) || 0;
            
            return [morningRevenue, swingRevenue, graveyardRevenue];
        }
        
        if (!this.historicalData || this.historicalData.length === 0) return [];
        
        let days = this.dateRange === '7days' ? 7 : 14;
        const dataToUse = this.historicalData.slice(-days);
        return dataToUse.map(item => parseFloat(item.sales_volume) || 0);
    }

    updateChart(data) {
        if (!this.chartInstance) return;

        this.chartInstance.data.labels = this.getChartLabels();
        this.chartInstance.data.datasets[0].data = this.getTrafficData();
        this.chartInstance.data.datasets[1].data = this.getRevenueData();
        this.chartInstance.update();
    }

    updateLastUpdateTime() {
        const element = document.getElementById('lastUpdateTimeDisplay');
        if (element && this.lastUpdate) {
            element.textContent = this.lastUpdate.toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
    }

    formatNumber(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        return Number(num).toLocaleString('en-PH');
    }

    formatCurrency(amount) {
        if (amount === null || amount === undefined || isNaN(amount)) return '₱0';
        return `₱${Number(amount).toLocaleString('en-PH', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        })}`;
    }

    showLoading() {
        const loading = document.getElementById('chartLoadingIndicator');
        if (loading) loading.style.display = 'flex';
    }

    hideLoading() {
        const loading = document.getElementById('chartLoadingIndicator');
        if (loading) loading.style.display = 'none';
    }

    showError(message) {
        console.error('Dashboard error:', message);
        
        ['todayCustomerCount', 'todayRevenueAmount', 'avgCustomerTraffic14Days'].forEach(id => {
            this.setElement(id, 'Error');
        });

        const tableBody = document.getElementById('historicalAnalysisTableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="no-data" style="color: #e74c3c;">
                        Error: ${message}<br>
                        <small>Check console for details</small>
                    </td>
                </tr>
            `;
        }
    }

    setupEvents() {
        // Date picker events
        const dateOptions = document.querySelectorAll('.date-option');
        dateOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                this.changeDateRange(e.target.closest('.date-option').dataset.value);
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.date-picker')) {
                this.closeDatePicker();
            }
        });
    }

    changeDateRange(newRange) {
        this.dateRange = newRange;
        
        // Update active option
        document.querySelectorAll('.date-option').forEach(option => {
            option.classList.remove('active');
            if (option.dataset.value === newRange) {
                option.classList.add('active');
            }
        });

        // Update display text
        const selectedText = document.getElementById('selectedChartDateRange');
        if (selectedText) {
            const optionText = {
                'today': 'Today',
                '7days': 'Last 7 Days',
                '14days': 'Last 14 Days'
            };
            selectedText.textContent = optionText[newRange] || 'Last 14 Days';
        }

        this.closeDatePicker();
        this.updateChart();
    }

    closeDatePicker() {
        const dropdown = document.getElementById('chartDatePickerDropdown');
        if (dropdown) dropdown.classList.remove('show');
    }

    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshTimer = setInterval(() => {
            console.log('Auto-refreshing...');
            this.loadData();
        }, this.refreshInterval);
        console.log('Auto-refresh started');
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    async refresh() {
        console.log('Manual refresh');
        await this.loadData();
    }

    cleanup() {
        this.stopAutoRefresh();
        if (this.chartInstance) {
            this.chartInstance.destroy();
            this.chartInstance = null;
        }
    }
}

// Global instance
let dashboard = null;

// Global functions
function initializeMonitoringDashboard() {
    if (dashboard) {
        dashboard.cleanup();
    }
    dashboard = new CustomerMonitoringDashboard();
    dashboard.init();
}

function refreshDashboardData() {
    if (dashboard) {
        dashboard.refresh();
    }
}

function toggleChartDatePicker() {
    const dropdown = document.getElementById('chartDatePickerDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

function dismissRiskAlert() {
    const alert = document.getElementById('riskAlertBanner');
    if (alert) {
        alert.classList.remove('show');
    }
}

// NEW: Date filtering global functions
function filterHistoricalData(range) {
    if (dashboard) {
        dashboard.filterHistoricalData(range);
    }
}

function showCustomDatePicker() {
    const picker = document.getElementById('customDateRangeSelector');
    if (picker) {
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
        
        // Set default dates
        if (picker.style.display !== 'none') {
            const today = new Date().toISOString().split('T')[0];
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
            
            document.getElementById('customStartDate').value = sevenDaysAgo.toISOString().split('T')[0];
            document.getElementById('customEndDate').value = today;
        }
    }
}

function applyCustomDateRange() {
    if (dashboard) {
        dashboard.applyCustomDateRange();
    }
}

function cancelCustomDatePicker() {
    const picker = document.getElementById('customDateRangeSelector');
    if (picker) {
        picker.style.display = 'none';
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, starting dashboard...');
    initializeMonitoringDashboard();
});

// Handle visibility changes
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && dashboard) {
        dashboard.refresh();
    }
});

// Cleanup on unload
window.addEventListener('beforeunload', function() {
    if (dashboard) {
        dashboard.cleanup();
    }
});

// Debug function
window.debugDashboard = function() {
    console.log('=== Dashboard Debug ===');
    console.log('Instance:', !!dashboard);
    console.log('Historical data:', dashboard?.historicalData?.length || 0);
    console.log('Last update:', dashboard?.lastUpdate);
    console.log('Chart:', !!dashboard?.chartInstance);
    console.log('Current filter:', dashboard?.currentFilterRange);
    
    if (dashboard?.historicalData?.length > 0) {
        console.log('Sample data:', dashboard.historicalData[0]);
    }
    
    fetch('api/customer_monitoring.php?t=' + Date.now())
        .then(r => r.json())
        .then(d => console.log('Direct API test:', d))
        .catch(e => console.error('API test failed:', e));
};

// Export for access
window.dashboard = dashboard;
window.refreshDashboardData = refreshDashboardData;