/* performance.css - Professional Dashboard Styles */

:root {
    --primary: #5e72e4;
    --primary-dark: #4c63d2;
    --primary-light: #7b8ff5;
    --secondary: #f4f5f7;
    --success: #2dce89;
    --warning: #fb6340;
    --danger: #f5365c;
    --info: #11cdef;
    --dark: #172b4d;
    --gray: #8898aa;
    --light: #f6f9fc;
    --white: #ffffff;
    
    --sidebar-width: 260px;
    --header-height: 70px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', sans-serif;
    background: var(--light);
    color: var(--dark);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Dashboard Wrapper */
.dashboard-wrapper {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: var(--sidebar-width);
    background: var(--white);
    box-shadow: var(--shadow-lg);
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    z-index: 1000;
    transition: var(--transition);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--secondary);
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--primary);
    font-size: 1.25rem;
    font-weight: 700;
}

.logo svg {
    width: 32px;
    height: 32px;
    color: var(--primary);
}

.sidebar-nav {
    flex: 1;
    padding: 1.5rem 0;
    overflow-y: auto;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem 1.5rem;
    color: var(--gray);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
}

.nav-item:hover {
    color: var(--primary);
    background: rgba(94, 114, 228, 0.05);
}

.nav-item.active {
    color: var(--primary);
    background: rgba(94, 114, 228, 0.1);
}

.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--primary);
}

.nav-icon {
    width: 20px;
    height: 20px;
    stroke-width: 2;
}

.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--secondary);
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--dark);
}

.user-role {
    font-size: 0.75rem;
    color: var(--gray);
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    transition: var(--transition);
}

/* Top Header */
.top-header {
    height: var(--header-height);
    background: var(--white);
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.menu-toggle {
    display: none;
    background: none;
    border: none;
    color: var(--dark);
    cursor: pointer;
    padding: 0.5rem;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
}

.header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.date-range-selector {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--secondary);
    border-radius: 8px;
}

.date-range-selector .icon {
    width: 20px;
    height: 20px;
    color: var(--gray);
}

.year-select {
    background: none;
    border: none;
    color: var(--dark);
    font-weight: 500;
    cursor: pointer;
    outline: none;
}

.refresh-btn {
    padding: 0.5rem 1rem;
    background: var(--primary);
    color: var(--white);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
}

.refresh-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.refresh-btn svg {
    width: 20px;
    height: 20px;
}

/* Dashboard Content */
.dashboard-content {
    padding: 2rem;
}

/* KPI Grid */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.kpi-card {
    background: var(--white);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
}

.kpi-icon svg {
    width: 24px;
    height: 24px;
}

.kpi-icon.sales {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.kpi-icon.customers {
    background: linear-gradient(135deg, var(--success), #52e3a4);
}

.kpi-icon.transactions {
    background: linear-gradient(135deg, var(--info), #42d3ff);
}

.kpi-icon.growth {
    background: linear-gradient(135deg, var(--warning), #ff7f5c);
}

.kpi-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.kpi-trend svg {
    width: 16px;
    height: 16px;
}

.kpi-trend.positive {
    background: rgba(45, 206, 137, 0.1);
    color: var(--success);
}

.kpi-trend.negative {
    background: rgba(245, 54, 92, 0.1);
    color: var(--danger);
}

.kpi-trend.neutral {
    background: rgba(136, 152, 170, 0.1);
    color: var(--gray);
}

.kpi-body {
    margin-bottom: 1.5rem;
}

.kpi-label {
    font-size: 0.875rem;
    color: var(--gray);
    margin-bottom: 0.5rem;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.kpi-comparison {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.comparison-label {
    color: var(--gray);
}

.comparison-value {
    color: var(--dark);
    font-weight: 600;
}

.kpi-footer {
    margin-top: 1rem;
}

.progress-bar {
    height: 8px;
    background: var(--secondary);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease;
    position: relative;
    overflow: hidden;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.sales-progress {
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

.customers-progress {
    background: linear-gradient(90deg, var(--success), #52e3a4);
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--gray);
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: var(--white);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--dark);
}

.chart-controls {
    display: flex;
    gap: 0.5rem;
}

.chart-btn {
    padding: 0.375rem 0.875rem;
    background: var(--secondary);
    color: var(--gray);
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.chart-btn.active {
    background: var(--primary);
    color: var(--white);
}

.chart-options {
    background: none;
    border: none;
    color: var(--gray);
    cursor: pointer;
    padding: 0.25rem;
}

.chart-body {
    position: relative;
    height: 300px;
}

/* Comparison Table */
.table-card {
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-md);
    margin-bottom: 2rem;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--secondary);
}

.table-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--dark);
}

.export-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--secondary);
    color: var(--dark);
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.export-btn:hover {
    background: var(--primary);
    color: var(--white);
}

.table-body {
    overflow-x: auto;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
}

.comparison-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--gray);
    background: var(--light);
    border-bottom: 1px solid var(--secondary);
}

.comparison-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--secondary);
    font-size: 0.9375rem;
}

.comparison-table tbody tr:hover {
    background: var(--light);
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.trend-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
}

.trend-icon svg {
    width: 16px;
    height: 16px;
}

.trend-icon.up {
    background: rgba(45, 206, 137, 0.1);
    color: var(--success);
}

.trend-icon.down {
    background: rgba(245, 54, 92, 0.1);
    color: var(--danger);
}

.trend-icon.neutral {
    background: rgba(136, 152, 170, 0.1);
    color: var(--gray);
}

/* Targets Section */
.targets-section {
    background: var(--white);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
}

.section-header {
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--dark);
}

.targets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.target-card {
    padding: 1rem;
    background: var(--light);
    border-radius: 8px;
}

.target-label {
    display: block;
    font-size: 0.875rem;
    color: var(--gray);
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.target-input-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.target-input {
    flex: 1;
    padding: 0.75rem;
    padding-left: 2rem;
    border: 2px solid var(--secondary);
    border-radius: 8px;
    font-size: 1rem;
    transition: var(--transition);
    background: var(--white);
}

.target-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.1);
}

.currency-symbol,
.percent-symbol {
    position: absolute;
    color: var(--gray);
    font-weight: 500;
}

.currency-symbol {
    left: 0.75rem;
}

.percent-symbol {
    right: 3rem;
}

.save-target-btn {
    padding: 0.75rem;
    background: var(--primary);
    color: var(--white);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: var(--transition);
}

.save-target-btn:hover {
    background: var(--primary-dark);
}

.save-target-btn svg {
    width: 20px;
    height: 20px;
}

/* Toast Notification */
.toast {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: var(--white);
    box-shadow: var(--shadow-xl);
    border-radius: 8px;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    z-index: 9999;
}

.toast.show {
    transform: translateX(0);
}

.toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--success);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast-icon svg {
    width: 16px;
    height: 16px;
}

.toast-message {
    font-weight: 500;
    color: var(--dark);
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.active {
    display: flex;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--secondary);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .menu-toggle {
        display: block;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .targets-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-content {
        padding: 1rem;
    }
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.kpi-card,
.chart-card,
.table-card,
.targets-section {
    animation: fadeIn 0.5s ease forwards;
}

.kpi-card:nth-child(1) { animation-delay: 0.1s; }
.kpi-card:nth-child(2) { animation-delay: 0.2s; }
.kpi-card:nth-child(3) { animation-delay: 0.3s; }
.kpi-card:nth-child(4) { animation-delay: 0.4s; }