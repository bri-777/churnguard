<?php
session_start();
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require __DIR__ . '/connection/config.php';

// Block access if not logged in
if (empty($_SESSION['user_id'])) {
  header('Location: auth/login.php');
  exit;
}

$stmt = $pdo->prepare("SELECT user_id, firstname, lastname, email, username, icon FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
  // Session is stale
  session_destroy();
  header('Location: auth/login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ChurnGuard Pro - XGBoost-Powered Customer Retention Analytics</title>
<link rel="stylesheet" href="styles.css"><!-- use YOUR provided CSS file -->
<link rel="stylesheet" href="styles.css"><!-- use YOUR provided CSS file -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<link rel="stylesheet" href="recomm.css"><!-- use YOUR provided CSS file -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
<meta name="csrf-token" content="<?=htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES)?>">
<!-- NEW: help the JS persist prediction state per user -->
<meta name="user-id" content="<?= (int)($me['user_id'] ?? 0) ?>">
</head>
<body>
<div class="app-container">
  <!-- Professional Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand">
        
 <!-- Outline, inherits text color -->
<span style="color:#f59e0b">
  <svg class="icon icon-bolt" viewBox="0 0 24 24" role="img" aria-label="Lightning">
    <path class="bolt" d="M13 2 L3 14 h7 l-1 8 L21 9 h-7 L13 2 Z"></path>
  </svg>
</span>

<!-- Solid -->
<svg class="icon icon-bolt icon-bolt--solid" viewBox="0 0 24 24" role="img" aria-label="Lightning">
  <path class="bolt" d="M13 2 L3 14 h7 l-1 8 L21 9 h-7 L13 2 Z"></path>
</svg>


        <div class="brand-text">
          <div class="brand-name">ChurnGuard Pro</div>
          <div class="brand-subtitle">XGBoost Analytics</div>
        </div>
      </div>
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <nav class="sidebar-menu">
      <div class="menu-section">
        <div class="menu-title">Analytics Dashboard</div>
        <a href="#" class="menu-item active" onclick="showPage('dashboard')">
          <i class="fas fa-chart-line"></i> <span>Analytics Overview</span>
        </a>
        <a href="#" class="menu-item" onclick="showPage('churn-prediction')">
          <i class="fas fa-brain"></i> <span>Churn Prediction</span>
        </a>
        <a href="#" class="menu-item" onclick="showPage('customer-insights')">
          <i class="fas fa-users-cog"></i> <span>Churn Analysis</span>
        </a>
		 
		 <a href="#" class="menu-item" onclick="showPage('customer-monitoring')">
          <i class="fas fa-eye"></i> <span>Customer Monitoring</span>
        </a>
     <a href="#" class="menu-item" onclick="showPage('dashboard-container')">
          <i class="fas fa-bullseye"></i> <span>Analytics Target</span>
        </a>
         <a href="#" class="menu-item" onclick="showPage('cust-insight')">
          <i class="fas fa-bullseye"></i> <span>Custmer Insight</span>
        </a>
      </div>

      <div class="menu-section">
        <div class="menu-title">Data Management</div>
        <a href="#" class="menu-item" onclick="showPage('data-input')">
          <i class="fas fa-database"></i> <span>Store Data Input</span>
        </a>
       
      </div>

      <div class="menu-section">
        <div class="menu-title">AI Recommendation</div>
      

        <a href="#" class="menu-item" onclick="showPage('recommendations')">
          <i class="fas fa-lightbulb"></i> <span>AI Recommendations</span>
        </a>
      </div>

      <div class="menu-section">
        <div class="menu-title">Account</div>
        <a href="#" class="menu-item" onclick="showPage('profile')">
          <i class="fas fa-user-circle"></i> <span>User Profile</span>
        </a>
       
      </div>
    </nav>

    <div class="sidebar-footer">
      <div class="system-status">
        <div class="status-indicator">
          <i class="fas fa-circle status-online"></i> <span>System Online</span>
        </div>
        <div class="last-update">Last updated: <span id="lastUpdate">Just now</span></div>
      </div>
    <!-- Logout Button -->
<button class="logout-btn" onclick="openLogoutModal()">
  <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
</button>

<!-- Modal -->
<div id="logoutModal" class="modal">
  <div class="modal-content">
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to log out of your account?</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
      <button class="btn-logout" onclick="doLogout()">Yes, Logout</button>
    </div>
  </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
  const params = new URLSearchParams(window.location.search);
  const page = params.get("page");
  if (page === "data-input") {
    showPage("data-input");
  }
});
</script>


<style>

.date-filter-container {
  display: flex;
  align-items: center;
  gap: 10px;
}

.date-filter-select {
  padding: 6px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
  font-size: 14px;
  cursor: pointer;
  outline: none;
  transition: border-color 0.2s;
}

.date-filter-select:hover {
  border-color: #3b82f6;
}

.date-filter-select:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.date-input {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
}

.date-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-apply-filter {
  padding: 6px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  font-weight: 500;
  transition: background 0.2s;
}

.btn-apply-filter:hover {
  background: #2563eb;
}

#customDateInputs {
  display: flex;
  align-items: center;
}




.data-view-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: 0.4s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.4s;
  border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
  background-color: #3b82f6;
}

.toggle-switch input:checked + .toggle-slider:before {
  transform: translateX(26px);
}

.toggle-label {
  font-size: 14px;
  font-weight: 500;
  color: #4b5563;
}













/* Recommendations Page Styles */
.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

/* Category Filters */
.category-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
  padding: 15px;
  background: rgba(0,0,0,0.02);
  border-radius: 12px;
}

.filter-btn {
  padding: 8px 18px;
  border: 2px solid #ddd;
  background: white;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  color: #555;
  cursor: pointer;
  transition: all 0.3s ease;
}

.filter-btn:hover {
  border-color: #3498db;
  color: #3498db;
  transform: translateY(-2px);
}

.filter-btn.active {
  background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
  color: white;
  border-color: #3498db;
  box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

/* Recommendations Grid */
.recommendations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 24px;
  margin-top: 20px;
}

.recommendation-item {
  background: white;
  border-radius: 16px;
  padding: 28px;
  border-left: 5px solid #ddd;
  box-shadow: 0 3px 12px rgba(0,0,0,0.08);
  transition: all 0.3s ease;
  position: relative;
  animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.recommendation-item:hover {
  transform: translateY(-6px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.recommendation-item.priority-high {
  border-left-color: #e74c3c;
  background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
}

.recommendation-item.priority-medium {
  border-left-color: #f39c12;
  background: linear-gradient(135deg, #fff 0%, #fffbf0 100%);
}

.recommendation-item.priority-low {
  border-left-color: #3498db;
  background: linear-gradient(135deg, #fff 0%, #f0f9ff 100%);
}

.recommendation-item.implemented {
  opacity: 0.7;
  border-left-color: #27ae60;
  background: linear-gradient(135deg, #fff 0%, #f0fff4 100%);
}

.rec-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 10px;
}

.rec-header-left,
.rec-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.rec-header i.fa-bolt {
  font-size: 18px;
}

.priority-high .rec-header i.fa-bolt {
  color: #e74c3c;
}

.priority-medium .rec-header i.fa-bolt {
  color: #f39c12;
}

.priority-low .rec-header i.fa-bolt {
  color: #3498db;
}

.rec-priority {
  font-weight: 700;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.priority-high .rec-priority {
  color: #e74c3c;
}

.priority-medium .rec-priority {
  color: #f39c12;
}

.priority-low .rec-priority {
  color: #3498db;
}

/* Category Badge */
.category-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(52, 152, 219, 0.1);
  color: #2980b9;
  border-radius: 16px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.category-badge i {
  font-size: 12px;
}

/* Title and Description */
.recommendation-item h4 {
  font-size: 19px;
  font-weight: 700;
  margin: 0 0 14px 0;
  color: #2c3e50;
  line-height: 1.4;
}

.recommendation-item p {
  font-size: 14px;
  line-height: 1.7;
  color: #555;
  margin: 0 0 20px 0;
}

/* Effectiveness Score */
.rec-effectiveness {
  margin: 20px 0;
  padding: 16px;
  background: rgba(0,0,0,0.03);
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,0.05);
}

.eff-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
  font-size: 13px;
}

.eff-label span {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #666;
  font-weight: 600;
}

.eff-label span i {
  color: #3498db;
}

.eff-label strong {
  font-size: 18px;
  font-weight: 800;
}

.eff-label strong.eff-high {
  color: #27ae60;
}

.eff-label strong.eff-medium {
  color: #f39c12;
}

.eff-label strong.eff-low {
  color: #e74c3c;
}

.eff-bar {
  height: 10px;
  background: rgba(0,0,0,0.08);
  border-radius: 5px;
  overflow: hidden;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.eff-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.eff-fill.eff-high {
  background: linear-gradient(90deg, #27ae60 0%, #2ecc71 100%);
  box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
}

.eff-fill.eff-medium {
  background: linear-gradient(90deg, #f39c12 0%, #f1c40f 100%);
  box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.eff-fill.eff-low {
  background: linear-gradient(90deg, #e74c3c 0%, #e67e73 100%);
  box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

/* Reasoning */
.rec-reasoning {
  font-size: 13px;
  color: #555;
  margin: 16px 0;
  padding: 12px 14px;
  background: rgba(52, 152, 219, 0.08);
  border-left: 4px solid #3498db;
  border-radius: 6px;
  line-height: 1.6;
}

.rec-reasoning i {
  margin-right: 8px;
  color: #3498db;
  font-size: 14px;
}

/* Metrics */
.rec-metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
  padding-top: 18px;
  border-top: 2px solid rgba(0,0,0,0.06);
}

.rec-metrics span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  padding: 8px 14px;
  background: rgba(0,0,0,0.05);
  border-radius: 20px;
  color: #555;
  font-weight: 600;
}

.rec-metrics span i {
  color: #27ae60;
  font-size: 11px;
}

/* Actions */
.rec-actions {
  margin-top: 20px;
  padding-top: 18px;
  border-top: 2px solid rgba(0,0,0,0.06);
}

.btn-implement {
  width: 100%;
  padding: 12px 20px;
  background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-implement:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(39, 174, 96, 0.3);
}

.btn-implement:disabled {
  background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
  cursor: not-allowed;
  opacity: 0.8;
}

/* AI Badges */
.ai-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 8px 16px;
  border-radius: 24px;
  font-size: 13px;
  font-weight: 700;
  animation: shimmer 2s infinite;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.ai-badge i {
  font-size: 15px;
}

@keyframes shimmer {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.85; }
}

.ai-mini-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 50%;
  font-size: 13px;
  animation: pulse 2s infinite;
  box-shadow: 0 3px 10px rgba(102, 126, 234, 0.4);
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}

/* Empty/Error States */
.no-recommendations,
.error-recommendations {
  grid-column: 1 / -1;
  text-align: center;
  padding: 80px 30px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 3px 12px rgba(0,0,0,0.08);
}

.no-recommendations i {
  font-size: 72px;
  color: #27ae60;
  margin-bottom: 24px;
  animation: bounce 2s infinite;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.error-recommendations i {
  font-size: 72px;
  color: #e74c3c;
  margin-bottom: 24px;
}

.no-recommendations h3,
.error-recommendations h3 {
  font-size: 26px;
  margin: 0 0 14px 0;
  color: #2c3e50;
  font-weight: 700;
}

.no-recommendations p,
.error-recommendations p {
  font-size: 16px;
  color: #7f8c8d;
  max-width: 600px;
  margin: 0 auto 12px;
  line-height: 1.6;
}

.no-recommendations small {
  display: block;
  margin-top: 16px;
  color: #95a5a6;
  font-size: 14px;
}

.error-recommendations small {
  display: block;
  margin: 16px auto 20px;
  color: #95a5a6;
  font-size: 13px;
  max-width: 500px;
}

.btn-retry {
  margin-top: 20px;
  padding: 12px 24px;
  background: #3498db;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-retry:hover {
  background: #2980b9;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

/* Toast Notifications */
.toast {
  position: fixed;
  bottom: 30px;
  right: 30px;
  padding: 16px 24px;
  background: #2c3e50;
  color: white;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 6px 20px rgba(0,0,0,0.3);
  z-index: 10000;
  opacity: 0;
  transform: translateY(20px);
  transition: all 0.3s ease;
}

.toast.show {
  opacity: 1;
  transform: translateY(0);
}

.toast.toast-success {
  background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
}

.toast.toast-error {
  background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .recommendations-grid {
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
  }
}

@media (max-width: 768px) {
  .recommendations-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  
  .recommendation-item {
    padding: 24px;
  }
  
  .category-filters {
    gap: 8px;
  }
  
  .filter-btn {
    padding: 6px 14px;
    font-size: 12px;
  }
  
  .ai-badge {
    font-size: 11px;
    padding: 6px 12px;
  }
  
  .rec-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .rec-header-right {
    width: 100%;
    justify-content: space-between;
  }
  
  .toast {
    right: 15px;
    bottom: 15px;
    left: 15px;
    text-align: center;
  }
}

/* Print Styles */
@media print {
  .category-filters,
  .rec-actions,
  .ai-badge,
  .ai-mini-badge {
    display: none !important;
  }
  
  .recommendation-item {
    break-inside: avoid;
    page-break-inside: avoid;
  }
}




  .date-wrap {
    position: relative;
    display: inline-block;
  }

  /* Make the real input fully usable, but visually transparent */
  .date-wrap > input[type="date"] {
    position: relative;
    z-index: 2;               /* stays on top for clicks/keyboard */
    background: transparent;  /* show what's underneath */
    color: transparent;       /* hide text, keep control visible */
    caret-color: transparent; /* hide caret */
    /* Keep native borders/sizing so it still looks like an input */
  }

  /* Fake visible text lives underneath the input */
  .date-wrap > #dateFake {
    position: absolute;
    z-index: 1;               /* under the input */
    top: 0; left: 0; right: 0; bottom: 0;
    display: flex;
    align-items: center;
    padding: 0 10px;          /* roughly matches common input padding */
    font: inherit;
    color: #1f2937;           /* adjust to your theme */
    pointer-events: none;     /* clicks go to the real input above */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Optional: ensure the input has a border so it still looks normal */
  .date-wrap > input[type="date"] {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px 10px;
  }
</style>


<style>
/* Base Styles */
:root {
  --bg: #f7f8fa; /* Background */
  --surface: #ffffff; /* Surface Color */
  --surface-2: #fbfcfe; /* Lighter Surface */
  --ink: #0f172a; /* Dark Ink Color */
  --text: #1f2937; /* Text Color */
  --muted: #6b7280; /* Muted Text */
  --hairline: rgba(15, 23, 42, 0.12); /* Subtle borders */
  --hairline-2: rgba(15, 23, 42, 0.08); /* Even subtler borders */
  --success: #16a34a; /* Success Color */
  --warn: #f59e0b; /* Warning Color */
  --danger: #ef4444; /* Danger Color */
  
  /* Elevation/Shadow */
  --shadow-xs: 0 2px 6px rgba(2, 6, 23, 0.1); 
  --shadow-sm: 0 4px 16px rgba(2, 6, 23, 0.1); 
  --shadow-md: 0 6px 24px rgba(2, 6, 23, 0.15); 
  --shadow-lg: 0 12px 40px rgba(2, 6, 23, 0.2); 
  
  /* Radii & Spacing */
  --r: 14px; 
  --r-lg: 18px; 
  --s-1: 8px; --s-2: 12px; --s-3: 16px; --s-4: 20px;
  
  /* Typography */
  --fs-body: 0.96rem; /* Body font size */
  --fs-h1: clamp(1.125rem, 1rem + 1vw, 1.5rem); /* Header 1 font size */
  --fs-kpi: clamp(1.5rem, 1.2rem + 1.2vw, 1.85rem); 
  --fs-risk: clamp(1.6rem, 1.3rem + 1.4vw, 2rem); 
  --w-regular: 400; 
  --w-medium: 500; 
  --w-semibold: 600;
  
  /* Transition Timing */
  --t-fast: 150ms ease-out;
  --t-med: 250ms cubic-bezier(.2, .7, .2, 1);
  --t-smooth: 0.4s ease-in-out; /* More fluid transitions */
  --t-long: 0.6s ease-in-out; /* For elements with greater interaction */
}

/* Global Reset */
* {
  box-sizing: border-box;
}

html, body {
  height: 100%;
  background: var(--bg);
  color: var(--text);
  font-family: "Inter var", Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans";
  font-size: var(--fs-body);
  line-height: 1.55;
  letter-spacing: .06px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.85); /* Darker backdrop for better focus */
  justify-content: center;
  align-items: center;
  transition: opacity var(--t-long);
}
.modal.show {
  display: flex;
  opacity: 1;
}
.modal-content {
  background: var(--surface);
  padding: var(--s-4);
  border-radius: var(--r-lg);
  max-width: 420px;
  text-align: center;
  box-shadow: var(--shadow-lg);
  transform: scale(0.9);
  transition: transform var(--t-smooth), box-shadow var(--t-smooth);
}
.modal-content.show {
  transform: scale(1);
}
.modal-actions {
  margin-top: var(--s-2);
  display: flex;
  justify-content: space-between;
  gap: var(--s-1);
}
.btn-cancel, .btn-logout {
  padding: var(--s-1) var(--s-2);
  border-radius: var(--r);
  cursor: pointer;
  transition: background 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
}
.btn-cancel {
  background: #ddd;
  border: none;
}
.btn-logout {
  background: #e63946;
  color: #fff;
  border: none;
}
.btn-cancel:hover, .btn-logout:hover {
  background: rgba(0, 0, 0, 0.15);
  transform: translateY(-5px); /* Strong hover effect */
  box-shadow: var(--shadow-lg);
}
.btn-cancel:active, .btn-logout:active {
  transform: translateY(2px);
  box-shadow: var(--shadow-xs);
}
/* Confidence level indicators */
.confidence-high {
    border-left: 3px solid #16a34a !important;
}

.confidence-medium {
    border-left: 3px solid #d97706 !important;
}

.confidence-low {
    border-left: 3px solid #dc2626 !important;
}

/* Data quality indicator */
.quality-indicator {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 4px;
    display: inline-block;
}

.quality-high {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #86efac;
}

.quality-medium {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fcd34d;
}

.quality-low {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fca5a5;
}
/* Buttons */
.btn-quiet {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--hairline);
  background: var(--surface);
  border-radius: var(--r-lg);
  padding: var(--s-1) var(--s-2);
  font-weight: var(--w-medium);
  color: var(--ink);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  transition: transform var(--t-fast), box-shadow var(--t-med), background var(--t-fast), border-color var(--t-fast);
}
@media (hover:hover) {
  .btn-quiet:hover {
    transform: translateY(-3px); /* Smooth hover effect */
    box-shadow: var(--shadow-md);
    background: var(--surface-2);
    border-color: var(--hairline);
  }
}
.btn-quiet:active {
  transform: translateY(0);
  box-shadow: var(--shadow-xs);
}
.btn-quiet:focus-visible {
  outline: none;
  box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.14), var(--shadow-sm);
  border-color: var(--hairline);
}

/* Page Header */
.page-header {
  background: linear-gradient(180deg, var(--surface), rgba(255, 255, 255, 0.92));
  border: 1px solid var(--hairline);
  border-radius: var(--r-lg);
  padding: var(--s-4);
  margin-bottom: var(--s-3);
  box-shadow: var(--shadow-lg);
  transition: box-shadow 0.3s ease-in-out, transform 0.2s ease-out;
}
.page-header h1 {
  margin: 0 0 6px;
  color: var(--ink);
  font-size: var(--fs-h1);
  font-weight: var(--w-semibold);
  line-height: 1.2;
}
.page-header p {
  margin: 0;
  color: var(--muted);
  font-weight: var(--w-regular);
}
.page-header .fas.fa-chart-line {
  background: linear-gradient(135deg, #eef1f7, #f5f7fb);
  color: #1f2937;
  padding: 14px;
  border-radius: 16px;
  margin-right: 10px;
  box-shadow: inset 0 0 0 2px var(--hairline-2);
  transition: transform 0.4s ease-out;
}

/* Hover Effect for Header Icon */
.page-header .fas.fa-chart-line:hover {
  transform: scale(1.08); /* Slight zoom effect for interactive feel */
}

/* Card (KPI, etc.) */
.card {
  background: var(--surface);
  border: 1px solid var(--hairline);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-md);
  transition: background var(--t-fast), box-shadow var(--t-med), transform 0.3s ease;
}
@media (hover:hover) {
  .card:hover {
    transform: translateY(-6px); /* Stronger floating effect */
    border-color: var(--hairline);
    box-shadow: var(--shadow-lg);
  }
}
.elevated {
  box-shadow: var(--shadow-lg);
}
.kpi-content .kpi-value {
  color: var(--ink);
  font-size: var(--fs-kpi);
  font-weight: var(--w-semibold);
  line-height: 1.08;
  transition: color 0.4s ease;
}
.kpi-content .kpi-label {
  color: var(--muted);
  font-weight: var(--w-medium);
}

/* Select (Dropdown) */
.select-clean {
  appearance: none;
  border: 1px solid var(--hairline);
  background: var(--surface);
  color: var(--ink);
  font-weight: var(--w-medium);
  border-radius: 10px;
  padding: var(--s-1) var(--s-2);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--t-fast), border-color var(--t-fast), background var(--t-fast);
}
.select-clean:hover {
  background: var(--surface-2);
}
.select-clean:focus {
  outline: none;
  box-shadow: 0 0 0 5px rgba(15, 23, 42, 0.12);
  border-color: var(--hairline);
}




/* Enhanced risk factor tag styles */
.risk-factor-tag {
    display: inline-block;
    padding: 6px 10px;
    margin: 3px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    transition: all 0.2s ease;
}

.risk-factor-tag.critical-urgent {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border: 2px solid #dc2626;
    font-weight: 700;
    animation: pulse-urgent 1.5s infinite;
}

.risk-factor-tag.critical {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fca5a5;
    font-weight: 600;
}

.risk-factor-tag.warning {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fcd34d;
    font-weight: 600;
}

.risk-factor-tag.positive {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #86efac;
    font-weight: 600;
}

.risk-factor-tag.neutral {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.risk-factor-tag.info {
    background: #dbeafe;
    color: #2563eb;
    border: 1px solid #93c5fd;
}

.risk-factor-tag.insight {
    background: #f3e8ff;
    color: #7c3aed;
    border: 1px solid #c4b5fd;
}

.risk-factor-tag.error {
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
    font-weight: 600;
}

/* Quality indicators */
.risk-factor-tag.quality-high {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.risk-factor-tag.quality-medium {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}

.risk-factor-tag.quality-low {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Confidence indicators */
.confidence-high { color: #16a34a; font-weight: 600; }
.confidence-medium { color: #d97706; font-weight: 600; }
.confidence-low { color: #dc2626; font-weight: 600; }

/* Animations */
@keyframes pulse-urgent {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.9; }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Risk circle enhancements */
#riskCircleDash {
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}


/* Dark Mode */
.dark body {
  background: #0a0f1b;
  color: #cfd5e1;
}
.dark .page-header, .dark .kpi-card, .dark .chart-card, .dark .card {
  background: #0b1220;
  border-color: rgba(255, 255, 255, 0.14);
}
.dark .kpi-content .kpi-value, .dark .page-header h1, .dark .risk-percentage, .dark .risk-details h4 {
  color: #e5e7eb;
}
.dark .page-header p, .dark .kpi-content .kpi-label {
  color: #9ca3af;
}
.dark .btn-quiet {
  background: #0f172a;
  color: #e5e7eb;
}
.dark .btn-quiet:hover {
  background: #111827;
}
.dark .select-clean {
  background: #0f172a;
  color: #e5e7eb;
}



.risk-factor-tag.critical {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fca5a5;
}

.risk-factor-tag.warning {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fcd34d;
}

.risk-factor-tag.positive {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #86efac;
}

.risk-factor-tag.neutral {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.risk-factor-tag.error {
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
}



/* ===== Customer Insights — minimal, cleaner, same color theme ===== */
#customer-insights {
  /* Inherit your existing theme tokens if defined */
  --bg: var(--page-bg, transparent);
  --card: var(--card-bg, #fff);
  --text: var(--text, #111);
  --muted: var(--text-muted, #6b7280);
  --line: var(--border, rgba(0,0,0,.08));
  --ring: var(--ring, rgba(0,0,0,.12));
  --chip-bg: var(--chip-bg, rgba(0,0,0,.04));
  --chip-line: var(--chip-border, rgba(0,0,0,.08));
  --good: var(--good, #10b981);
  --warn: var(--warn, #f59e0b);
  --bad:  var(--bad,  #ef4444);

  --radius: 14px;
  --pad: 14px;
  --gap: 14px;
  --shadow: 0 6px 20px var(--ring);

  color: var(--text);
  background: var(--bg);
}

/* Page header */
#customer-insights .page-header {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: end;
  gap: .75rem 1rem;
  padding-bottom: .5rem;
  border-bottom: 1px solid var(--line);
}
#customer-insights .page-header h1 {
  margin: 0;
  line-height: 1.1;
  letter-spacing: .2px;
}
#customer-insights .page-header .subtle {
  color: var(--muted);
  margin: .25rem 0 0;
  font-size: .95rem;
}
#customer-insights .page-tools {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
}
#customer-insights .btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .45rem .7rem;
  border: 1px solid var(--line);
  background: var(--card);
  border-radius: calc(var(--radius) - 4px);
  transition: border-color .15s ease, transform .06s ease, box-shadow .15s ease;
}
#customer-insights .btn-secondary:hover { border-color: rgba(0,0,0,.18); box-shadow: 0 6px 16px var(--ring); }
#customer-insights .btn-secondary:active { transform: translateY(1px); }
#customer-insights .updated { color: var(--muted); }

/* Grid */
#customer-insights .insights-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--gap);
  margin-top: var(--gap);
}
#customer-insights .insight-card {
  grid-column: span 6;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
#customer-insights .insight-card.full-width { grid-column: 1 / -1; }

/* Card header */
#customer-insights .insight-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--pad);
  border-bottom: 1px solid var(--line);
}
#customer-insights .insight-header .lh {
  display: inline-flex;
  align-items: center;
  gap: .6rem;
}
#customer-insights .insight-header h3 { margin: 0; font-weight: 600; }
#customer-insights .tooltip { cursor: help; opacity: .9; }

/* Card body */
#customer-insights .insight-content {
  padding: var(--pad);
  display: grid;
  gap: var(--gap);
}

/* Primary metric */
#customer-insights .insight-metric {
  display: grid;
  align-items: baseline;
  gap: .25rem;
}
#customer-insights .metric-value {
  font-size: clamp(22px, 3.2vw, 34px);
  font-weight: 700;
  letter-spacing: .2px;
}
#customer-insights .metric-label {
  color: var(--muted);
  font-size: .95rem;
}

/* Mini metrics — compact cards */
#customer-insights .mini-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
#customer-insights .mini {
  display: grid;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: calc(var(--radius) - 6px);
  background: linear-gradient(180deg, rgba(0,0,0,.02), rgba(0,0,0,.01));
}
#customer-insights .mini-label {
  font-size: 11px;
  color: var(--muted);
  letter-spacing: .2px;
  text-transform: uppercase;
}
#customer-insights .mini-value { font-weight: 600; }

/* Deltas (minimalist) */
#customer-insights .delta { font-variant-numeric: tabular-nums; }
#customer-insights .delta-up { color: var(--good); }
#customer-insights .delta-down { color: var(--bad); }
#customer-insights .delta-neutral { color: var(--muted); }

/* Patterns / chips */
#customer-insights .patterns-block { display: grid; gap: 10px; }
#customer-insights .patterns-head {
  display: flex; align-items: baseline; justify-content: space-between;
}
#customer-insights .patterns-title { font-weight: 600; letter-spacing: .2px; }
#customer-insights .behavior-patterns {
  display: flex; flex-wrap: wrap; gap: 8px;
}
#customer-insights .risk-factor-tag {
  display: inline-flex; align-items: center; gap: .4ch;
  padding: .32rem .56rem;
  border: 1px solid var(--chip-line);
  background: var(--chip-bg);
  border-radius: 999px;
  font-size: 12px; line-height: 1;
}

/* Risk badge (keeps theme, simplifies fill) */
#customer-insights [data-risk-level] {
  display: inline-flex; align-items: center;
  padding: .22rem .55rem;
  border-radius: 999px;
  border: 1px solid var(--line);
  font-weight: 600;
}
#customer-insights [data-risk-level="low"]    { box-shadow: inset 0 0 0 9999px rgba(16,185,129,.09); }
#customer-insights [data-risk-level="medium"] { box-shadow: inset 0 0 0 9999px rgba(245,158,11,.10); }
#customer-insights [data-risk-level="high"]   { box-shadow: inset 0 0 0 9999px rgba(239,68,68,.10); }

/* Risk rows */
#customer-insights .pattern-item {
  display: grid;
  grid-template-columns: 22px 1fr;
  gap: 8px;
  align-items: start;
}
#customer-insights .pattern-icon { color: var(--muted); display: grid; place-items: center; }
#customer-insights .pattern-text { line-height: 1.35; }

/* Optional tiny charts */
#customer-insights .sparkline-row {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
}
#customer-insights .sparkline {
  padding: 8px 10px 10px;
  border: 1px dashed var(--line);
  border-radius: calc(var(--radius) - 6px);
}
#customer-insights .spark-label { color: var(--muted); font-size: 11px; }

/* Loading shimmer (reduced motion aware) */
#customer-insights.is-loading .insight-card { position: relative; }
#customer-insights.is-loading .insight-card::after {
  content: "";
  position: absolute; inset: 0;
  border-radius: var(--radius);
  background: linear-gradient(90deg, transparent, rgba(0,0,0,.04), transparent);
  animation: ci-shimmer 1.1s linear infinite;
}
@keyframes ci-shimmer { 0% { transform: translateX(-28%); } 100% { transform: translateX(28%); } }
@media (prefers-reduced-motion: reduce) {
  #customer-insights.is-loading .insight-card::after { animation: none; }
}

/* Focus styles */
#customer-insights .btn-secondary:focus-visible,
#customer-insights .insight-card:focus-within {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

/* Responsive refinements */
@media (max-width: 1080px) {
  #customer-insights .insight-card { grid-column: 1 / -1; }
  #customer-insights .sparkline-row { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  #customer-insights .page-header {
    grid-template-columns: 1fr;
    align-items: start;
  }
  #customer-insights .mini-metrics { grid-template-columns: 1fr 1fr; }
  #customer-insights .page-tools { justify-content: flex-start; }
}
</style>



<!-- Optional Tooltip Styling -->
<style>
.kpi-tooltip {
  display: inline-block;
  margin-left: 5px;
  color: #6b7280; /* gray */
  cursor: pointer;
}
.kpi-tooltip i {
  font-size: 0.9em;
}
.kpi-tooltip:hover::after {
  content: attr(title);
  position: absolute;
  background: #1a1a1a;
  color: #fff;
  padding: 6px 10px;
  border-radius: 5px;
  font-size: 0.85em;
  white-space: nowrap;
  transform: translate(-50%, -120%);
  z-index: 999;
}

.subtle { color:#6b7280 }
.delta { margin-left:.5rem; font-size:.8em }
.delta.pos { color:#065f46 }
.delta.neg { color:#991b1b }
.compact-list { margin:.25rem 0 0; padding-left:1rem }


.page-header {
  background: linear-gradient(135deg, #0a4d68 0%, #088395 50%, #05dfd7 100%);
  border-radius: var(--radius-2xl);
  padding: var(--space-2xl);
  position: relative;
  overflow: hidden;
}

.page-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    repeating-linear-gradient(
      0deg,
      rgba(255, 255, 255, 0.03) 0px,
      transparent 1px,
      transparent 40px,
      rgba(255, 255, 255, 0.03) 41px
    ),
    repeating-linear-gradient(
      90deg,
      rgba(255, 255, 255, 0.03) 0px,
      transparent 1px,
      transparent 40px,
      rgba(255, 255, 255, 0.03) 41px
    );
  pointer-events: none;
  z-index: 0;
}



.page-header h1,
.page-header p {
  position: relative;
  z-index: 1;
}
/* Page Header Text Styling - Pure White */
.page-header h1 {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  letter-spacing: -0.025em;
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 1rem;
  color: #ffffff;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.page-header h1 i {
  font-size: 2rem;
  color: #ffffff;
  opacity: 1;
  filter: drop-shadow(0 2px 8px rgba(5, 223, 215, 0.4));
}

.page-header p {
  font-size: 1.125rem;
  position: relative;
  z-index: 1;
  letter-spacing: 0.025em;
  font-weight: 400;
  color: #ffffff;
  opacity: 1;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  line-height: 1.6;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .page-header h1 {
    font-size: 1.75rem;
    flex-direction: column;
    align-items: flex-start;
  }
  
  .page-header h1 i {
    font-size: 1.5rem;
  }
  
  .page-header p {
    font-size: 1rem;
  }
}

@media (max-width: 480px) {
  .page-header h1 {
    font-size: 1.5rem;
  }
  
  .page-header p {
    font-size: 0.875rem;
  }
}






.kpi-content{

background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 50%, var(--color-primary-light) 100%);

}







</style>












<script>
function openLogoutModal() {
  document.getElementById("logoutModal").style.display = "flex";
}
function closeLogoutModal() {
  document.getElementById("logoutModal").style.display = "none";
}
function doLogout() {
  window.location.href = "auth/logout.php";
}
</script>

    </div>
  </aside>

  <!-- Main Content Area -->
  <main class="main-content">
    <!-- Analytics Dashboard Page -->
    <div id="dashboard" class="page active">
      <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
        <p>Real-time customer retention analytics powered by XGBoost machine learning</p>
      </div>

      <!-- KPIs -->
<div class="kpi-grid">

  <!-- Total Revenue -->
  <div class="kpi-card revenue">
    <div class="kpi-icon"><i class="fas fa-peso-sign"></i></div>
    <div class="kpi-content">
      <div class="kpi-value" id="totalRevenue">₱0</div>
      <div class="kpi-label">
        Total Revenue 
        <span class="kpi-tooltip" title="Total income generated today from all sales transactions. Hover for details.">
          <i class="fas fa-info-circle"></i>
        </span>
      </div>
      <div class="kpi-change positive" id="revenueChange"><i class="fas fa-arrow-up"></i> 0%</div>
    </div>
  </div>

  <!-- Customers Today -->
  <div class="kpi-card customers">
    <div class="kpi-icon"><i class="fas fa-users"></i></div>
    <div class="kpi-content">
      <div class="kpi-value" id="activeCustomers">0</div>
      <div class="kpi-label">
        Customers Today
        <span class="kpi-tooltip" title="Number of unique customers visiting today. Differentiates between new and returning customers.">
          <i class="fas fa-info-circle"></i>
        </span>
      </div>
      <div class="kpi-change positive" id="customersChange"><i class="fas fa-arrow-up"></i> 0%</div>
    </div>
  </div>

  <!-- Retention Rate -->
  <div class="kpi-card retention">
    <div class="kpi-icon"><i class="fas fa-heart"></i></div>
    <div class="kpi-content">
      <div class="kpi-value" id="retentionRate">0%</div>
      <div class="kpi-label">
        Retention Rate
        <span class="kpi-tooltip" title="Percentage of returning customers versus total customers. A higher rate indicates better customer loyalty.">
          <i class="fas fa-info-circle"></i>
        </span>
      </div>
      <div class="kpi-change positive" id="retentionChange"><i class="fas fa-arrow-up"></i> 0%</div>
    </div>
  </div>

  <!-- Churn Risk -->
  <div class="kpi-card risk">
    <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="kpi-content">
      <div class="kpi-value" id="churnRisk">0%</div>
      <div class="kpi-label">
        Churn Risk
        <span class="kpi-tooltip" title="Estimated percentage of customers at risk of leaving. Calculated from predictive churn models. Hover to see top 5 at-risk users and average risk score.">
          <i class="fas fa-info-circle"></i>
        </span>
      </div>
      <div class="kpi-change negative" id="riskChange"><i class="fas fa-arrow-down"></i> 0%</div>
    </div>
  </div>

</div>



    

      <!-- CHARTSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS -->
     <!-- Charts -->
<div class="charts-grid">
  <div class="chart-card large">
    <div class="chart-header">
      <h3><i class="fas fa-chart-area"></i> Customer Visit Pattern
        <i class="fas fa-info-circle tooltip" title="Shows customer counts with period selection"></i>
      </h3>
      <div class="chart-controls">
      <h3 class="traffic-period-title">Today's Traffic</h3>
      
      </div>
    </div>
    <div class="chart-container"><canvas id="trafficChart"></canvas></div>
   <div class="chart-stats" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.1); background: linear-gradient(90deg, rgba(0,123,255,0.02) 0%, rgba(108,117,125,0.02) 100%);">
 
  
</div>
  </div>
  <div class="chart-card medium">
    <div class="chart-header">
      <h3><i class="fas fa-chart-pie"></i> Customer Churn Distribution
        <i class="fas fa-info-circle tooltip" title="Risk distribution from today analysis"></i>
      </h3>
    </div>
    <div class="chart-container"><canvas id="churnChart"></canvas></div>
  </div>
  <div class="chart-card medium">
    <div class="chart-header">
      <h3><i class="fas fa-chart-bar"></i> Purchase Behavior
        <i class="fas fa-info-circle tooltip" title="Business metrics based on 14-day averages"></i>
      </h3>
    </div>
    <div class="chart-container"><canvas id="purchaseBehaviorChart"></canvas></div>
  </div>
</div>

</div>








    <div id="data-input" class="page">
      <div class="page-header">
        <h1><i class="fas fa-store"></i>  Churn Prediction Data Input</h1>
        <p>Real-time transaction data collection for customer disengagement pattern detection</p>
      </div>

      <div class="content-grid">
        <!-- Transaction Data Collection -->
        <div class="data-card">
          <div class="card-header">
            <i class="fas fa-info-circle tooltip" title="Transaction Metrics for Churn Detection"></i>
            <div class="header-content">
              <i class="fas fa-receipt"></i>
              <div>
                <h3>📊 Transaction Data Collection</h3>
                <p>Aggregate transaction data for churn pattern detection</p>
              </div>
            </div>
            <div class="data-quality high">Critical for Churn Prediction</div>
          </div>
          <div class="card-body">
            <div class="form-grid">
            <div class="form-group">
  <label for="date">Date <span class="required">*</span></label>
  <input id="date" name="date" type="date" required />
</div>

<script>
(function () {
  const el = document.getElementById('date');
  if (!el) return;

  // Compute today's date in local time as YYYY-MM-DD
  const d = new Date();
  const iso = [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0')
  ].join('-');

  // Force the value to today and lock the range
  el.value = iso;
  el.min = iso;
  el.max = iso;

  // Make it read-only and unfocusable to prevent the calendar from opening
  el.readOnly = true;
  el.setAttribute('aria-readonly', 'true');
  el.setAttribute('inputmode', 'none');   // hints mobile keyboards not to show
  el.tabIndex = -1;

  // Belt & suspenders: block all interaction paths
  const block = e => { e.preventDefault(); e.stopPropagation(); };
  ['keydown','keyup','keypress','input','change','paste','cut','wheel',
   'mousedown','mouseup','click','dblclick','focus','touchstart','touchend','contextmenu']
   .forEach(evt => el.addEventListener(evt, function(e){
     // Always keep today, even if something slips through
     if (el.value !== iso) el.value = iso;
     // Block any editing/opening attempts
     block(e);
     // Immediately blur to close any OS picker UI
     try { this.blur(); } catch(_) {}
   }), { passive:false });

  // Extra safety: if the browser tries to open the picker after layout
  setTimeout(() => { try { el.blur(); } catch(_) {} }, 0);
})();
</script>


              <div class="form-group">
                <label for="receiptCount"> Receipt Count <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="Total number of receipts/transactions - key churn indicator"></i>
                </label>
                <input type="number" id="receiptCount" placeholder="e.g., 280" min="0" required>
              </div>

              <div class="form-group">
                <label for="salesVolume"> Sales Volume (₱) <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="Total sales volume - primary churn detection metric"></i>
                </label>
                <input type="number" id="salesVolume" placeholder="e.g., 45000" min="0" step="0.01" required>
              </div>

              <div class="form-group">
                <label for="customerTraffic"> Customer Traffic <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="Total customer count - essential for churn pattern analysis"></i>
                </label>
                <input type="number" id="customerTraffic" placeholder="e.g., 320" min="0" required>
              </div>
            </div>
          </div>
        </div>

        <!-- Shift-Based Performance -->
        <div class="data-card">
          <div class="card-header">
            <i class="fas fa-info-circle tooltip" title="Shift Performance for Churn Analysis"></i>
            <div class="header-content">
              <i class="fas fa-clock"></i>
              <div>
                <h3>⏰ Shift-Based Performance</h3>
                <p>Shift-level transaction logs for customer behavior simulation</p>
              </div>
            </div>
            <div class="data-quality high">Essential for Pattern Detection</div>
          </div>
          <div class="card-body">
            <div class="form-grid">
              <div class="form-group">
                <label for="morningReceiptCount"> Morning Receipt Count <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="6:00 AM to 2:00 PM - Morning shift transaction count"></i>
                </label>
                <input type="number" id="morningReceiptCount" placeholder="e.g., 95" min="0" required>
              </div>
			    <div class="form-group">
                <label for="morningSalesVolume"> Morning Sales Volume (₱) <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="6:00 AM to 2:00 PM - Morning shift sales performance"></i>
                </label>
                <input type="number" id="morningSalesVolume" placeholder="e.g., 15,000" min="0" step="0.01" required>
              </div>
              <div class="form-group">
                <label for="swingReceiptCount"> Midday Receipt Count <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="2:00 PM to 10:00 PM - Swing shift transaction count"></i>
                </label>
                <input type="number" id="swingReceiptCount" placeholder="e.g., 130" min="0" required>
              </div>
			      <div class="form-group">
                <label for="swingSalesVolume"> Midday Sales Volume (₱) <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="2:00 PM to 10:00 PM - Swing shift sales performance"></i>
                </label>
                <input type="number" id="swingSalesVolume" placeholder="e.g., 10,000" min="0" step="0.01" required>
              </div>
              <div class="form-group">
                <label for="graveyardReceiptCount"> Evening Receipt Count <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="10:00 PM to 6:00 AM - Graveyard shift transaction count"></i>
                </label>
                <input type="number" id="graveyardReceiptCount" placeholder="e.g., 95" min="0" required>
              </div>
              <div class="form-group">
                <label for="graveyardSalesVolume"> Evening Sales Volume (₱) <span class="required">*</span>
                  <i class="fas fa-info-circle tooltip" title="10:00 PM to 6:00 AM - Graveyard shift sales performance"></i>
                </label>
                <input type="number" id="graveyardSalesVolume" placeholder="e.g., 20,000" min="0" step="0.01" required>
              </div>
            </div>
          </div>
        </div>
      </div>


      <div class="action-section">
<!-- Add this button in the action-section, before or after existing buttons -->
<button type="button" class="btn-primary" onclick="document.getElementById('csvFileInput').click()">
  <i class="fas fa-upload"></i> Upload CSV/Excel
</button>
<input type="file" id="csvFileInput" accept=".csv,.xlsx,.xls" style="display: none;" onchange="handleFileUpload(event)">




        <button type="button" class="btn-primary" onclick="saveChurnData()">
          <i class="fas fa-save"></i> Save Churn Data
        </button>
      
        <button type="button" class="btn-secondary" onclick="clearForm()">
          <i class="fas fa-eraser"></i> Clear All Fields
        </button>
        <button type="button" id="runChurnPredictionBtn" class="btn-primary" onclick="runChurnPrediction()">
          <i class="fas fa-brain"></i> Run Churn Prediction
        </button>

      </div>
    </div>


<!-- SheetJS Library for Excel support -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
function handleFileUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  const fileName = file.name.toLowerCase();
  const isExcel = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
  
  const reader = new FileReader();
  
  if (isExcel) {
    reader.onload = function(e) {
      const data = new Uint8Array(e.target.result);
      const workbook = XLSX.read(data, { type: 'array' });
      const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
      const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
      processData(jsonData);
    };
    reader.readAsArrayBuffer(file);
  } else {
    reader.onload = function(e) {
      const text = e.target.result;
      const lines = text.split('\n');
      const jsonData = lines.map(line => line.split(',').map(cell => cell.trim()));
      processData(jsonData);
    };
    reader.readAsText(file);
  }
}

function processData(data) {
  if (data.length < 2) {
    alert('No data found in file!');
    return;
  }
  
  // Get headers (first row)
  const headers = data[0].map(h => String(h).trim().toLowerCase());
  
  // Find column indices
  const shopNameIndex = headers.findIndex(h => h.includes('shop name'));
  const receiptCountIndex = headers.findIndex(h => h.includes('receipt count'));
  const customerNameIndex = headers.findIndex(h => h.includes('customer name'));
  const quantityIndex = headers.findIndex(h => h.includes('quantity'));
  const drinkTypeIndex = headers.findIndex(h => h.includes('type of drink'));
  const dateIndex = headers.findIndex(h => h.includes('date'));
  const dayIndex = headers.findIndex(h => h.includes('day'));
  const timeOfDayIndex = headers.findIndex(h => h.includes('time of day'));
  const totalAmountIndex = headers.findIndex(h => h.includes('total amount'));
  const paymentMethodIndex = headers.findIndex(h => h.includes('payment method'));
  
  if (receiptCountIndex === -1 || timeOfDayIndex === -1 || totalAmountIndex === -1) {
    alert('Required columns not found! Please ensure your file has:\n- Receipt Count\n- Time of Day\n- Total Amount (₱)');
    return;
  }
  
  // Arrays to store transactions for database
  const transactions = [];
  
  // Initialize counters for TOTALS
  let totalReceipts = 0;
  let totalSales = 0;
  let totalCustomerTraffic = 0;
  
  // Initialize counters for SHIFTS
  let morningReceipts = 0;
  let morningSales = 0;
  let middayReceipts = 0;
  let middaySales = 0;
  let eveningReceipts = 0;
  let eveningSales = 0;
  
  // Process data rows (skip header)
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row || row.length === 0) continue;
    
    const receiptCount = parseInt(row[receiptCountIndex]) || 0;
    const timeOfDay = String(row[timeOfDayIndex] || '').trim().toLowerCase();
    const totalAmountStr = String(row[totalAmountIndex] || '').replace(/[₱,\s]/g, '');
    const totalAmount = parseFloat(totalAmountStr) || 0;
    
    // Skip empty rows
    if (!timeOfDay || totalAmount === 0) continue;
    
    // Prepare transaction object for database
    const dateStr = String(row[dateIndex] || '').trim();
    const dateVisited = parseExcelDate(dateStr);
    
    transactions.push({
      shop_name: String(row[shopNameIndex] || '').trim(),
      receipt_count: receiptCount,
      customer_name: String(row[customerNameIndex] || '').trim(),
      quantity_of_drinks: parseInt(row[quantityIndex]) || 0,
      type_of_drink: String(row[drinkTypeIndex] || '').trim(),
      date_visited: dateVisited,
      day: String(row[dayIndex] || '').trim(),
      time_of_day: String(row[timeOfDayIndex] || '').trim(),
      total_amount: totalAmount,
      payment_method: String(row[paymentMethodIndex] || '').trim()
    });
    
    // Add to TOTALS
    totalReceipts += receiptCount;
    totalSales += totalAmount;
    totalCustomerTraffic += 1;
    
    // Map to SHIFTS
    if (timeOfDay.includes('morning')) {
      morningReceipts += receiptCount;
      morningSales += totalAmount;
    } else if (timeOfDay.includes('lunch') || timeOfDay.includes('midday')) {
      middayReceipts += receiptCount;
      middaySales += totalAmount;
    } else if (timeOfDay.includes('evening') || timeOfDay.includes('night')) {
      eveningReceipts += receiptCount;
      eveningSales += totalAmount;
    }
  }
  
  // Save to database
  saveToDatabase(transactions);
  
  // Populate TOTAL fields
  document.getElementById('receiptCount').value = totalReceipts;
  document.getElementById('salesVolume').value = totalSales.toFixed(2);
  document.getElementById('customerTraffic').value = totalCustomerTraffic;
  
  // Populate SHIFT fields
  document.getElementById('morningReceiptCount').value = morningReceipts;
  document.getElementById('morningSalesVolume').value = morningSales.toFixed(2);
  document.getElementById('swingReceiptCount').value = middayReceipts;
  document.getElementById('swingSalesVolume').value = middaySales.toFixed(2);
  document.getElementById('graveyardReceiptCount').value = eveningReceipts;
  document.getElementById('graveyardSalesVolume').value = eveningSales.toFixed(2);
  
  // Show success message
  alert('File loaded successfully!\n\n' +
        'TOTALS:\n' +
        'Total Receipts: ' + totalReceipts + '\n' +
        'Total Sales: ₱' + totalSales.toFixed(2) + '\n' +
        'Customer Traffic: ' + totalCustomerTraffic + '\n\n' +
        'SHIFTS:\n' +
        'Morning: ' + morningReceipts + ' receipts, ₱' + morningSales.toFixed(2) + '\n' +
        'Midday: ' + middayReceipts + ' receipts, ₱' + middaySales.toFixed(2) + '\n' +
        'Evening: ' + eveningReceipts + ' receipts, ₱' + eveningSales.toFixed(2));
  
  // Reset file input
  document.getElementById('csvFileInput').value = '';
}

function parseExcelDate(dateStr) {
  // Handle Excel serial date numbers
  if (!isNaN(dateStr) && dateStr.length > 4) {
    const excelEpoch = new Date(1899, 11, 30);
    const date = new Date(excelEpoch.getTime() + parseInt(dateStr) * 86400000);
    return date.toISOString().split('T')[0];
  }
  
  // Handle regular date strings
  if (dateStr.includes('/') || dateStr.includes('-')) {
    const date = new Date(dateStr);
    if (!isNaN(date.getTime())) {
      return date.toISOString().split('T')[0];
    }
  }
  
  // Return current date if parsing fails
  return new Date().toISOString().split('T')[0];
}

function saveToDatabase(transactions) {
  console.log('=== SAVING TO DATABASE ===');
  console.log('Total transactions:', transactions.length);
  
  if (transactions.length === 0) {
    alert('No transactions to save!');
    return;
  }
  
  console.log('Sample transaction:', transactions[0]);
  
  // Send to PHP
  fetch('upload_transactions.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ transactions: transactions })
  })
  .then(response => {
    console.log('Response status:', response.status);
    console.log('Response OK:', response.ok);
    return response.text();
  })
  .then(text => {
    console.log('=== RAW RESPONSE ===');
    console.log(text);
    console.log('===================');
    
    try {
      const data = JSON.parse(text);
      console.log('Parsed:', data);
      
      if (data.success) {
        alert('✓ SUCCESS! Saved ' + data.count + ' transactions to database!');
        console.log('✓ Saved:', data.count);
      } else {
        alert('✗ ERROR: ' + data.message);
        console.error('Error:', data.message);
      }
    } catch (e) {
      console.error('JSON parse failed:', e);
      alert('Server error. Check console.');
    }
  })
  .catch(error => {
    console.error('Fetch error:', error);
    alert('Network error: ' + error.message);
  });
}
</script>


















    



    <!-- Churn Prediction Page -->
    <div id="churn-prediction" class="page">
      <div class="page-header">
        <h1><i class="fas fa-brain"></i> XGBoost Churn Prediction</h1>
        <p>Advanced machine learning-powered customer churn risk assessment</p>
      </div>

      <div class="risk-assessment-card">
        <div class="card-header">
          <div class="header-content">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <h3>Real-Time Churn Risk Assessment</h3>
              <p>Current customer churn risk analysis based on XGBoost predictions</p>
            </div>
          </div>
        </div>

        <div class="risk-content">
          <div class="risk-score-display">
            <div class="risk-circle" id="riskCircle">
              <div class="risk-percentage" id="riskPercentage">23%</div>
            </div>
            <div class="risk-details">
              <h4 id="riskLevel">Medium Risk</h4>
              <p id="riskDescription">Current churn risk is within acceptable range but requires monitoring.</p>
              <div class="risk-factors" id="riskFactors">
                <span class="risk-factor-tag">Decreased visit frequency</span>
                <span class="risk-factor-tag">Lower transaction values</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  <div id = "dashboard-container" class = "page">
 <!-- Main Dashboard Container -->
    <div class="dashboard-wrapper">
        
        <!-- Top Navigation Bar -->
        <nav class="top-navbar">
            <div class="navbar-left">
                <h1 class="brand-title">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <circle cx="12" cy="12" r="10"></circle>
  <circle cx="12" cy="12" r="6"></circle>
  <circle cx="12" cy="12" r="2" fill="currentColor"></circle>
</svg>

                   Analytics Target
                </h1>
            </div>
            <div class="navbar-center">
                <div class="date-display">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <span id="currentDateTime">Loading...</span>
                </div>
            </div>
            <div class="navbar-right">
               
                <button class="icon-btn" onclick="refreshAllData()" title="Refresh Data">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                    </svg>
                </button>
                <button class="btn-primary-small" onclick="openTargetModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v8M8 12h8"/>
                    </svg>
                    New Target
                </button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="dashboard-content">
            
            <!-- KPI Summary Cards -->
            <section class="kpi-section">
                <h2 class="section-title-main">Today's Analytical Overview</h2>
                <div class="kpi-grid">
                    <div class="kpi-card-pro" data-metric="sales">
                        <div class="kpi-header">
                            <div class="kpi-icon-pro sales-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                            </div>
                          
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label-pro">Today's Revenue</span>
                            <h3 class="kpi-value-pro" id="todaySales">₱0.00</h3>
                            <div class="kpi-footer">
                                <span class="kpi-comparison" id="salesComparison"></span>
                                <span class="kpi-sparkline" id="salesSparkline">●●●●●●●</span>
                            </div>
                        </div>
                    </div>

                    <div class="kpi-card-pro" data-metric="customers">
                        <div class="kpi-header">
                            <div class="kpi-icon-pro customers-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                           
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label-pro">Customer Traffic</span>
                            <h3 class="kpi-value-pro" id="todayCustomers">0</h3>
                            <div class="kpi-footer">
                                <span class="kpi-comparison" id="customersComparison"></span>
                                <span class="kpi-sparkline" id="customersSparkline">●●●●●●●</span>
                            </div>
                        </div>
                    </div>

                    <div class="kpi-card-pro" data-metric="transactions">
                        <div class="kpi-header">
                            <div class="kpi-icon-pro transactions-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                                    <path d="M2 10h20"/>
                                </svg>
                            </div>
                            
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label-pro">Transactions</span>
                            <h3 class="kpi-value-pro" id="todayTransactions">0</h3>
                            <div class="kpi-footer">
                                <span class="kpi-comparison" id="transactionsComparison"></span>
                                <span class="kpi-sparkline" id="transactionsSparkline">●●●●●●●</span>
                            </div>
                        </div>
                    </div>

                    <div class="kpi-card-pro" data-metric="target">
                        <div class="kpi-header">
                            <div class="kpi-icon-pro target-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <circle cx="12" cy="12" r="6"/>
                                    <circle cx="12" cy="12" r="2"/>
                                </svg>
                            </div>
                            <div class="kpi-trend-badge success" id="targetTrendBadge">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7 14l5-5 5 5H7z"/>
                                </svg>
                                <span>Active</span>
                            </div>
                        </div>
                        <div class="kpi-body">
                            <span class="kpi-label-pro">Target Progress</span>
                            <h3 class="kpi-value-pro" id="targetAchievement">0%</h3>
                            <div class="kpi-footer">
                                <span class="kpi-comparison" id="targetStatus">No active target</span>
                                <div class="mini-progress">
                                    <div class="mini-progress-bar" id="targetMiniProgress" style="width:0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            
            

           

            <!-- Targets Management -->
            <section class="targets-section-pro">
                <div class="section-header-pro">
                    <h2 class="section-title-main">Target Management</h2>
                    <div class="filter-controls">
                        <select id="targetFilter" class="form-select-pro" onchange="filterTargets()">
                            <option value="all">All Targets</option>
                            <option value="active">Active</option>
                            <option value="achieved">Achieved</option>
                            <option value="near">Near Target</option>
                            <option value="below">Below Target</option>
                        </select>
                        <button class="btn-outline-small" onclick="exportTargets()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                            </svg>
                            Export
                        </button>
                    </div>
                </div>

                <div class="targets-grid" id="targetsGrid">
                    <!-- Targets will be populated here -->
                </div>
            </section>

            <!-- Data Tables -->
            <section class="tables-section-pro">
                <div class="table-tabs">
                    <button class="tab-btn active" onclick="switchTab('trend')" data-tab="trend">Sales Trend</button>
                    <button class="tab-btn" onclick="switchTab('active-targets')" data-tab="active-targets">Active Targets</button>
                
                </div>

                <div class="tab-content active" id="trend-tab">
                    <div class="table-wrapper-pro">
                        <table class="data-table-pro">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Revenue</th>
                                    <th>Transactions</th>
                                    <th>Customers</th>
                                    <th>Avg. Value</th>
                                    <th>Change</th>
                                </tr>
                            </thead>
                            <tbody id="salesTrendTableBody">
                                <tr><td colspan="6" class="loading-cell">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-content" id="active-targets-tab">
                    <div class="table-wrapper-pro">
                        <table class="data-table-pro">
                            <thead>
                                <tr>
                                    <th>Target Name</th>
                                    <th>Type</th>
                                    <th>Current / Target</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="activeTargetsTableBody">
                                <tr><td colspan="6" class="loading-cell">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

              
            </section>

        </main>

        <!-- Target Modal -->
        <div class="modal-overlay" id="targetModal" onclick="closeTargetModal(event)">
            <div class="modal-container" onclick="event.stopPropagation()">
                <div class="modal-header-pro">
                    <h3 id="modalTitle">Create New Target</h3>
                    <button class="modal-close-btn" onclick="closeTargetModal()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form id="targetForm" onsubmit="saveTarget(event)">
                    <div class="modal-body-pro">
                        <div class="form-grid">
                            <div class="form-group-pro">
                                <label>Target Name</label>
                                <input type="text" id="targetName" class="form-input-pro" required maxlength="100" placeholder="e.g., Q4 Sales Goal">
                            </div>
                            <div class="form-group-pro">
                                <label>Target Type</label>
                                <select id="targetType" class="form-select-pro" required>
                                    <option value="">Select type...</option>
                                    <option value="sales">Sales Revenue</option>
                                    <option value="customers">Customer Traffic</option>
                                    <option value="transactions">Transactions</option>
                                    <option value="avg_transaction">Avg Transaction Value</option>
                                </select>
                            </div>
                            <div class="form-group-pro full-width">
                                <label>Target Value</label>
                                <input type="number" id="targetValue" class="form-input-pro" required min="0.01" step="0.01" placeholder="Enter target amount">
                            </div>
                            <div class="form-group-pro">
                                <label>Start Date</label>
                                <input type="date" id="targetStartDate" class="form-input-pro" required>
                            </div>
                            <div class="form-group-pro">
                                <label>End Date</label>
                                <input type="date" id="targetEndDate" class="form-input-pro" required>
                            </div>
                            <div class="form-group-pro full-width">
                                <label>Store/Branch (Optional)</label>
                                <input type="text" id="targetStore" class="form-input-pro" maxlength="100" placeholder="Leave empty for all stores">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer-pro">
                        <button type="button" class="btn-secondary-pro" onclick="closeTargetModal()">Cancel</button>
                        <button type="submit" class="btn-primary-pro">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Target
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<script>

// ==================== PROFESSIONAL SALES ANALYTICS DASHBOARD ====================
'use strict';

// ==================== CONFIGURATION ====================
const CONFIG = {
    API_BASE: 'sales_comparison.php',
    REQUEST_TIMEOUT: 30000,
    MAX_RETRIES: 3,
    RETRY_DELAY: 1000,
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
            legend: { 
                display: true, 
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 12 }
                }
            },
            tooltip: { 
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                cornerRadius: 8,
                displayColors: true
            }
        },
        interaction: {
            mode: 'index',
            intersect: false
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
    lastDataUpdate: null,
    
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
            try {
                controller.abort();
            } catch (e) {
                console.warn('Error aborting request:', e);
            }
        });
        this.activeRequests.clear();
    }
};

// ==================== UTILITY FUNCTIONS ====================
const Utils = {
    formatCurrency(value) {
        if (value == null || isNaN(value)) return '₱0.00';
        const num = parseFloat(value);
        return '₱' + num.toLocaleString('en-PH', { 
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
        const num = parseFloat(value);
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
        } catch {
            return 'Invalid Date';
        }
    },

    getISODate(date) {
        try {
            if (!(date instanceof Date)) {
                date = new Date(date);
            }
            if (isNaN(date.getTime())) {
                return new Date().toISOString().split('T')[0];
            }
            return date.toISOString().split('T')[0];
        } catch {
            return new Date().toISOString().split('T')[0];
        }
    },

    formatDateTime() {
        try {
            const now = new Date();
            return now.toLocaleDateString('en-PH', { 
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch {
            return 'N/A';
        }
    },

    calculateChange(current, previous) {
        if (!previous || previous === 0) {
            return current > 0 ? 100 : 0;
        }
        return ((current - previous) / previous) * 100;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
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

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        // Remove existing notifications
        const existing = Utils.$$('.notification-toast');
        existing.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = 'notification-toast';
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
            <span style="font-size: 20px;">${icons[type] || icons.info}</span>
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
                    <div style="color: #6b7280; font-size: 14px; font-weight: 500;">Loading data...</div>
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
        const baseId = id.replace('today', '').replace('target', '');
        const trendBadge = Utils.$(`#${baseId}TrendBadge`);
        const comparison = Utils.$(`#${baseId}Comparison`);
        
        if (valueEl) {
            let formattedValue;
            if (id.includes('Sales') || id.includes('target')) {
                formattedValue = id.includes('target') && !id.includes('Sales') ? 
                    Utils.formatPercentage(value) : 
                    Utils.formatCurrency(value);
            } else {
                formattedValue = Utils.formatNumber(value);
            }
            valueEl.textContent = formattedValue;
        }
        
        if (trendBadge && change !== undefined && !isNaN(change)) {
            const isPositive = change >= 0;
            trendBadge.className = `kpi-trend-badge ${isPositive ? '' : 'down'}`;
            const span = trendBadge.querySelector('span');
            if (span) {
                span.textContent = Utils.formatPercentage(Math.abs(change));
            }
            
            const svg = trendBadge.querySelector('svg path');
            if (svg) {
                svg.setAttribute('d', isPositive ? 'M7 14l5-5 5 5H7z' : 'M7 10l5 5 5-5H7z');
            }
        }
        
        if (comparison && change !== undefined && !isNaN(change)) {
            comparison.textContent = `${change >= 0 ? '+' : ''}${Utils.formatPercentage(change)} vs yesterday`;
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
    async fetch(action, params = {}, retryCount = 0) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.append(key, String(value));
            }
        });

        console.log(`[API] ${action}:`, url.toString());

        const controller = new AbortController();
        const requestId = `${action}-${Date.now()}-${Math.random()}`;
        AppState.activeRequests.set(requestId, controller);

        const timeoutId = setTimeout(() => {
            controller.abort();
        }, CONFIG.REQUEST_TIMEOUT);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal,
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            console.log(`[API] ${action} Response:`, response.status);

            if (response.status === 401) {
                UIManager.showNotification('Session expired. Redirecting to login...', 'error');
                setTimeout(() => {
                    window.location.href = '/login.php';
                }, 2000);
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`[API] ${action} Error:`, errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Invalid response format. Expected JSON.');
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            console.log(`[API] ${action} Success:`, data);
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log(`[API] ${action} Cancelled`);
                return null;
            }

            console.error(`[API] ${action} Error:`, error);

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                const delay = CONFIG.RETRY_DELAY * (retryCount + 1);
                console.log(`[API] Retrying ${action} in ${delay}ms (attempt ${retryCount + 1}/${CONFIG.MAX_RETRIES})`);
                await new Promise(resolve => setTimeout(resolve, delay));
                return this.fetch(action, params, retryCount + 1);
            }

            throw error;
        }
    },

    async post(action, body, retryCount = 0) {
        const url = new URL(CONFIG.API_BASE, window.location.origin);
        url.searchParams.append('action', action);

        console.log(`[API] POST ${action}:`, body);

        const controller = new AbortController();
        const requestId = `${action}-${Date.now()}-${Math.random()}`;
        AppState.activeRequests.set(requestId, controller);

        const timeoutId = setTimeout(() => {
            controller.abort();
        }, CONFIG.REQUEST_TIMEOUT);

        try {
            const response = await fetch(url.toString(), {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body),
                signal: controller.signal,
                credentials: 'same-origin',
                cache: 'no-cache'
            });

            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            console.log(`[API] POST ${action} Response:`, response.status);

            if (response.status === 401) {
                UIManager.showNotification('Session expired. Redirecting to login...', 'error');
                setTimeout(() => {
                    window.location.href = '/login.php';
                }, 2000);
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`[API] POST ${action} Error:`, errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Invalid response format. Expected JSON.');
            }

            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API error occurred');
            }

            console.log(`[API] POST ${action} Success:`, data);
            return data;

        } catch (error) {
            clearTimeout(timeoutId);
            AppState.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                console.log(`[API] POST ${action} Cancelled`);
                return null;
            }

            console.error(`[API] POST ${action} Error:`, error);

            if (retryCount < CONFIG.MAX_RETRIES && !error.message.includes('Unauthorized')) {
                const delay = CONFIG.RETRY_DELAY * (retryCount + 1);
                console.log(`[API] Retrying POST ${action} in ${delay}ms (attempt ${retryCount + 1}/${CONFIG.MAX_RETRIES})`);
                await new Promise(resolve => setTimeout(resolve, delay));
                return this.post(action, body, retryCount + 1);
            }

            throw error;
        }
    }
};

// ==================== CHART MANAGER ====================
const ChartManager = {
    destroyChart(chartName) {
        if (AppState.charts[chartName]) {
            try {
                AppState.charts[chartName].destroy();
            } catch (e) {
                console.warn('Error destroying chart:', e);
            }
            delete AppState.charts[chartName];
        }
    },

    createSalesTrendChart(data) {
        const ctx = Utils.$('#salesTrendChart');
        if (!ctx) {
            console.error('Chart canvas not found: salesTrendChart');
            return;
        }

        if (!data || data.length === 0) {
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No trend data available for the selected period</p>';
            return;
        }

        this.destroyChart('salesTrend');

        const sortedData = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));
        
        try {
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
                                callback: value => '₱' + value.toLocaleString('en-PH')
                            },
                            grid: {
                                color: '#f3f4f6'
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
                                label: ctx => 'Revenue: ' + Utils.formatCurrency(ctx.parsed.y)
                            }
                        }
                    }
                }
            });
            console.log('[CHART] Sales trend chart created successfully');
        } catch (error) {
            console.error('[CHART] Error creating sales trend chart:', error);
            ctx.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#ef4444;">Error loading chart</p>';
        }
    },

    createComparisonChart(comparisonData) {
        const ctx = Utils.$('#comparisonChart');
        if (!ctx) {
            console.warn('[CHART] Comparison chart canvas not found');
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

        try {
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
                            },
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            console.log('[CHART] Comparison chart created successfully');
        } catch (error) {
            console.error('[CHART] Error creating comparison chart:', error);
        }
    },

    updateTrendChart() {
        const period = parseInt(Utils.$('#trendPeriod')?.value) || 30;
        DataManager.loadTrendData(period);
    }
};

// ==================== DATA MANAGER ====================
const DataManager = {
    async loadKPISummary() {
        AppState.incrementLoading();
        try {
            console.log('[DATA] Loading KPI Summary...');
            const data = await APIService.fetch('kpi_summary');
            
            if (!data) {
                console.warn('[DATA] No KPI data received');
                return;
            }

            // Update KPI cards
            UIManager.updateKPICard('todaySales', data.today_sales || 0, data.sales_change || 0);
            UIManager.updateKPICard('todayCustomers', data.today_customers || 0, data.customers_change || 0);
            UIManager.updateKPICard('todayTransactions', data.today_transactions || 0, data.transactions_change || 0);
            UIManager.updateKPICard('targetAchievement', data.target_achievement || 0);
            
            // Update target status
            const targetStatus = Utils.$('#targetStatus');
            if (targetStatus) {
                targetStatus.textContent = data.target_status || 'No active target';
            }
            
            // Update mini progress bar
            const miniProgress = Utils.$('#targetMiniProgress');
            if (miniProgress) {
                const progress = Math.min(parseFloat(data.target_achievement) || 0, 100);
                miniProgress.style.width = progress + '%';
            }

            AppState.lastDataUpdate = new Date();
            console.log('[DATA] KPI Summary loaded successfully');

        } catch (error) {
            console.error('[DATA] KPI Summary Error:', error);
            UIManager.showNotification('Failed to load KPI data: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    },

    async loadTrendData(days = 30) {
        AppState.incrementLoading();
        try {
            console.log(`[DATA] Loading Trend Data (${days} days)...`);
            const data = await APIService.fetch('trend_data', { days });
            
            if (!data) {
                console.warn('[DATA] No trend data received');
                return;
            }

            if (data.trend_data && data.trend_data.length > 0) {
                ChartManager.createSalesTrendChart(data.trend_data);
                this.populateTrendTable(data.trend_data);
                console.log('[DATA] Trend data loaded successfully');
            } else {
                const chart = Utils.$('#salesTrendChart');
                if (chart) {
                    chart.parentElement.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">No data available for selected period</p>';
                }
                
                const tbody = Utils.$('#salesTrendTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No trend data available</td></tr>';
                }
                console.warn('[DATA] No trend data available');
            }
        } catch (error) {
            console.error('[DATA] Trend Data Error:', error);
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
            let changeHtml = '—';
            
            if (index < sorted.length - 1) {
                const prevSales = parseFloat(sorted[index + 1].sales_volume || 0);
                change = Utils.calculateChange(sales, prevSales);
                const isPositive = change >= 0;
                changeHtml = `
                    <span class="metric-change ${isPositive ? 'positive' : 'negative'}">
                        ${isPositive ? '▲' : '▼'} ${Utils.formatPercentage(Math.abs(change))}
                    </span>
                `;
            }

            return `
                <tr>
                    <td><strong>${Utils.formatDate(item.date)}</strong></td>
                    <td>${Utils.formatCurrency(sales)}</td>
                    <td>${Utils.formatNumber(receipts)}</td>
                    <td>${Utils.formatNumber(customers)}</td>
                    <td>${Utils.formatCurrency(avgValue)}</td>
                    <td>${changeHtml}</td>
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
            UIManager.showNotification('Please select different dates for comparison', 'warning');
            return;
        }

        AppState.incrementLoading();
        try {
            console.log('[DATA] Loading Comparison...');
            const data = await APIService.fetch('compare', { currentDate, compareDate });
            
            if (!data) return;

            if (data.comparison && data.comparison.length > 0) {
                this.displayComparisonResults(data.comparison);
                ChartManager.createComparisonChart(data.comparison);
                UIManager.showNotification('Comparison loaded successfully', 'success');
                console.log('[DATA] Comparison loaded successfully');
            } else {
                UIManager.showNotification('No comparison data available', 'info');
            }
        } catch (error) {
            console.error('[DATA] Comparison Error:', error);
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
            const isCurrency = item.metric.toLowerCase().includes('sales') || 
                             item.metric.toLowerCase().includes('value');
            
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

// ==================== TARGET MANAGER ====================
const TargetManager = {
    async loadTargets(filter = 'all') {
        AppState.incrementLoading();
        try {
            console.log(`[TARGET] Loading targets with filter: ${filter}`);
            const data = await APIService.fetch('get_targets', { filter });
            
            if (!data) return;

            if (data.targets) {
                this.displayTargetsGrid(data.targets);
                this.displayTargetsTable(data.targets);
                console.log(`[TARGET] Loaded ${data.targets.length} targets`);
            }
        } catch (error) {
            console.error('[TARGET] Load Targets Error:', error);
            UIManager.showNotification('Failed to load targets: ' + error.message, 'error');
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
                                <div class="progress-bar-fill ${statusClass}" style="width:${Math.min(progress, 100)}%;height:100%;border-radius:3px;transition:width 0.3s ease;"></div>
                            </div>
                            <span style="font-weight:600;min-width:50px;font-size:13px;">${progress.toFixed(1)}%</span>
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
            console.log(`[TARGET] Editing target ID: ${id}`);
            const data = await APIService.fetch('get_targets', { filter: 'all' });
            
            if (!data || !data.targets) {
                UIManager.showNotification('Failed to load target data', 'error');
                return;
            }

            const target = data.targets.find(t => t.id === id);
            
            if (!target) {
                UIManager.showNotification('Target not found', 'error');
                return;
            }

            AppState.editingTargetId = id;
            
            // Populate form
            Utils.$('#modalTitle').textContent = 'Edit Target';
            Utils.$('#targetName').value = target.target_name || '';
            Utils.$('#targetType').value = target.target_type || '';
            Utils.$('#targetValue').value = target.target_value || '';
            Utils.$('#targetStartDate').value = target.start_date || '';
            Utils.$('#targetEndDate').value = target.end_date || '';
            Utils.$('#targetStore').value = target.store || '';
            
            ModalManager.open();
            console.log('[TARGET] Edit form populated successfully');
        } catch (error) {
            console.error('[TARGET] Edit Target Error:', error);
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
            console.log(`[TARGET] Deleting target ID: ${id}`);
            const result = await APIService.fetch('delete_target', { id });
            
            if (!result) return;

            UIManager.showNotification('Target deleted successfully', 'success');
            console.log('[TARGET] Target deleted successfully');
            
            // Reload data
            await Promise.all([
                this.loadTargets(Utils.$('#targetFilter')?.value || 'all'),
                DataManager.loadKPISummary()
            ]);
        } catch (error) {
            console.error('[TARGET] Delete Target Error:', error);
            UIManager.showNotification('Failed to delete target: ' + error.message, 'error');
        } finally {
            AppState.decrementLoading();
        }
    }
};

// ==================== DATE MANAGER ====================
const DateManager = {
    setDefaultDates() {
        const today = new Date();
        const yesterday = new Date(today.getTime() - 86400000);

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) compareDate.value = Utils.getISODate(yesterday);
        
        console.log('[DATE] Default dates set');
    },

    updateComparisonDates() {
        const type = Utils.$('#comparisonType')?.value;
        const today = new Date();
        
        const dateMap = {
            'today_vs_yesterday': 86400000, // 1 day
            'week_vs_week': 604800000, // 7 days
            'month_vs_month': 2592000000, // 30 days
            'custom': 86400000
        };

        const currentDate = Utils.$('#currentDate');
        const compareDate = Utils.$('#compareDate');

        if (currentDate) currentDate.value = Utils.getISODate(today);
        if (compareDate) {
            const offset = dateMap[type] || dateMap.custom;
            compareDate.value = Utils.getISODate(new Date(today.getTime() - offset));
        }
        
        console.log(`[DATE] Comparison dates updated for type: ${type}`);
    }
};

// ==================== TAB MANAGER ====================
const TabManager = {
    switchTab(tabName) {
        // Remove active class from all tabs
        Utils.$('.tab-btn').forEach(btn => btn.classList.remove('active'));
        Utils.$('.tab-content').forEach(content => content.classList.remove('active'));

        // Add active class to selected tab
        const activeBtn = Utils.$(`[data-tab="${tabName}"]`);
        const activeContent = Utils.$(`#${tabName}-tab`);

        if (activeBtn) activeBtn.classList.add('active');
        if (activeContent) activeContent.classList.add('active');

        AppState.currentTab = tabName;
        console.log(`[TAB] Switched to: ${tabName}`);
    }
};

// ==================== MODAL MANAGER ====================
const ModalManager = {
    open() {
        const modal = Utils.$('#targetModal');
        const form = Utils.$('#targetForm');
        
        if (modal) modal.classList.add('active');
        if (form) form.reset();
        
        const modalTitle = Utils.$('#modalTitle');
        if (modalTitle && !AppState.editingTargetId) {
            modalTitle.textContent = 'Create New Target';
        }

        // Set default dates if creating new target
        if (!AppState.editingTargetId) {
            const today = new Date();
            const nextMonth = new Date(today.getTime() + 2592000000); // 30 days

            const startDate = Utils.$('#targetStartDate');
            const endDate = Utils.$('#targetEndDate');

            if (startDate) startDate.value = Utils.getISODate(today);
            if (endDate) endDate.value = Utils.getISODate(nextMonth);
        }
        
        console.log('[MODAL] Target modal opened');
    },

    close(event) {
        if (event && event.target !== event.currentTarget && !event.target.classList.contains('modal-close-btn')) {
            return;
        }
        
        const modal = Utils.$('#targetModal');
        if (modal) modal.classList.remove('active');
        
        AppState.editingTargetId = null;
        console.log('[MODAL] Target modal closed');
    },

    async saveTarget(event) {
        event.preventDefault();

        // Collect form data
        const formData = {
            name: Utils.$('#targetName')?.value.trim(),
            type: Utils.$('#targetType')?.value,
            value: parseFloat(Utils.$('#targetValue')?.value),
            start_date: Utils.$('#targetStartDate')?.value,
            end_date: Utils.$('#targetEndDate')?.value,
            store: Utils.$('#targetStore')?.value.trim() || ''
        };

        // Validation
        if (!formData.name) {
            UIManager.showNotification('Please enter a target name', 'warning');
            Utils.$('#targetName')?.focus();
            return;
        }

        if (!formData.type) {
            UIManager.showNotification('Please select a target type', 'warning');
            Utils.$('#targetType')?.focus();
            return;
        }

        if (isNaN(formData.value) || formData.value <= 0) {
            UIManager.showNotification('Please enter a valid target value greater than 0', 'warning');
            Utils.$('#targetValue')?.focus();
            return;
        }

        if (formData.value > 999999999) {
            UIManager.showNotification('Target value is too large. Maximum is 999,999,999', 'warning');
            Utils.$('#targetValue')?.focus();
            return;
        }

        if (!formData.start_date || !formData.end_date) {
            UIManager.showNotification('Please select both start and end dates', 'warning');
            return;
        }

        if (new Date(formData.end_date) < new Date(formData.start_date)) {
            UIManager.showNotification('End date must be after start date', 'warning');
            Utils.$('#targetEndDate')?.focus();
            return;
        }

        AppState.incrementLoading();
        try {
            const action = AppState.editingTargetId ? 'update_target' : 'save_target';
            
            if (AppState.editingTargetId) {
                formData.id = AppState.editingTargetId;
            }

            console.log(`[MODAL] Saving target with action: ${action}`, formData);
            
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
            
            console.log('[MODAL] Target saved successfully');
        } catch (error) {
            console.error('[MODAL] Save Target Error:', error);
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
        grid.innerHTML = '<p style="text-align:center;padding:40px;color:#9ca3af;">Select dates and click "Analyze" to compare data</p>';
    }
    ChartManager.destroyChart('comparison');
    console.log('[COMPARISON] Reset completed');
};
window.refreshAllData = async () => {
    console.log('[APP] Refreshing all data...');
    await App.loadAllData();
    UIManager.showNotification('Data refreshed successfully', 'success');
};
window.toggleNotifications = () => {
    UIManager.showNotification('Notifications feature coming soon!', 'info');
};
window.exportTargets = () => {
    UIManager.showNotification('Export feature coming soon!', 'info');
};

// ==================== APP INITIALIZATION ====================
const App = {
    async init() {
        console.log('🚀 Initializing Professional Sales Dashboard...');

        try {
            // Check Chart.js
            if (typeof Chart === 'undefined') {
                console.error('❌ Chart.js not loaded!');
                UIManager.showNotification('Chart library not loaded. Please refresh the page.', 'error');
                return;
            }

            console.log('✓ Chart.js loaded');

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
            UIManager.showNotification('Dashboard loaded successfully', 'success');
        } catch (error) {
            console.error('❌ Initialization error:', error);
            UIManager.showNotification('Failed to initialize dashboard: ' + error.message, 'error');
        }
    },

    async loadAllData() {
        console.log('[APP] Loading all data...');
        
        const promises = [
            DataManager.loadKPISummary(),
            DataManager.loadTrendData(30),
            TargetManager.loadTargets('all')
        ];

        const results = await Promise.allSettled(promises);
        
        results.forEach((result, index) => {
            if (result.status === 'rejected') {
                console.error(`[APP] Failed to load data [${index}]:`, result.reason);
            }
        });
        
        console.log('[APP] All data loaded');
    },

    setupEventListeners() {
        console.log('[APP] Setting up event listeners...');
        
        // Modal backdrop click
        const modal = Utils.$('#targetModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    ModalManager.close();
                }
            });
        }

        // Form submission
        const targetForm = Utils.$('#targetForm');
        if (targetForm) {
            targetForm.addEventListener('submit', (e) => {
                e.preventDefault();
                ModalManager.saveTarget(e);
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
            if (!document.hidden && AppState.lastDataUpdate) {
                const timeSinceUpdate = Date.now() - AppState.lastDataUpdate;
                if (timeSinceUpdate > 300000) { // 5 minutes
                    console.log('[APP] Auto-refreshing data after visibility change');
                    this.loadAllData();
                }
            }
        });

        // Handle network errors
        window.addEventListener('online', () => {
            console.log('[APP] Connection restored');
            UIManager.showNotification('Connection restored', 'success');
            this.loadAllData();
        });

        window.addEventListener('offline', () => {
            console.log('[APP] Connection lost');
            UIManager.showNotification('No internet connection. Some features may not work.', 'warning');
        });

        // Handle unload
        window.addEventListener('beforeunload', () => {
            AppState.cancelPendingRequests();
        });
        
        console.log('[APP] Event listeners setup complete');
    }
};

// ==================== PERFORMANCE TABLE LOADER ====================
async function loadPerformanceTable() {
    const tbody = Utils.$('#performanceTableBody');
    if (!tbody) return;

    try {
        const today = new Date();
        const yesterday = new Date(today.getTime() - 86400000);
        const lastWeek = new Date(today.getTime() - 604800000);
        const lastMonth = new Date(today.getTime() - 2592000000);

        const [todayData, yesterdayData, weekData, monthData] = await Promise.all([
            APIService.fetch('kpi_summary'),
            APIService.fetch('compare', { 
                currentDate: Utils.getISODate(today), 
                compareDate: Utils.getISODate(yesterday) 
            }),
            APIService.fetch('compare', { 
                currentDate: Utils.getISODate(today), 
                compareDate: Utils.getISODate(lastWeek) 
            }),
            APIService.fetch('compare', { 
                currentDate: Utils.getISODate(today), 
                compareDate: Utils.getISODate(lastMonth) 
            })
        ]);

        const metrics = [
            {
                name: 'Sales Revenue',
                today: todayData?.today_sales || 0,
                yesterday: yesterdayData?.comparison?.[0]?.compare || 0,
                lastWeek: weekData?.comparison?.[0]?.compare || 0,
                lastMonth: monthData?.comparison?.[0]?.compare || 0,
                isCurrency: true
            },
            {
                name: 'Transactions',
                today: todayData?.today_transactions || 0,
                yesterday: yesterdayData?.comparison?.[1]?.compare || 0,
                lastWeek: weekData?.comparison?.[1]?.compare || 0,
                lastMonth: monthData?.comparison?.[1]?.compare || 0,
                isCurrency: false
            },
            {
                name: 'Customer Traffic',
                today: todayData?.today_customers || 0,
                yesterday: yesterdayData?.comparison?.[2]?.compare || 0,
                lastWeek: weekData?.comparison?.[2]?.compare || 0,
                lastMonth: monthData?.comparison?.[2]?.compare || 0,
                isCurrency: false
            }
        ];

        tbody.innerHTML = metrics.map(metric => {
            const weekChange = Utils.calculateChange(metric.today, metric.lastWeek);
            const isPositive = weekChange >= 0;
            
            return `
                <tr>
                    <td><strong>${metric.name}</strong></td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.today) : Utils.formatNumber(metric.today)}</td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.yesterday) : Utils.formatNumber(metric.yesterday)}</td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.lastWeek) : Utils.formatNumber(metric.lastWeek)}</td>
                    <td>${metric.isCurrency ? Utils.formatCurrency(metric.lastMonth) : Utils.formatNumber(metric.lastMonth)}</td>
                    <td>
                        <span class="metric-change ${isPositive ? 'positive' : 'negative'}">
                            ${isPositive ? '▲' : '▼'} ${Utils.formatPercentage(Math.abs(weekChange))}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (error) {
        console.error('[PERFORMANCE] Error loading performance table:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">Error loading performance data</td></tr>';
    }
}

// Load performance table when switching to that tab
const originalSwitchTab = TabManager.switchTab;
TabManager.switchTab = function(tabName) {
    originalSwitchTab.call(this, tabName);
    if (tabName === 'performance') {
        loadPerformanceTable();
    }
};

// ==================== ANIMATIONS & STYLES ====================
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
    .notification-toast {
        pointer-events: all;
    }
`;
document.head.appendChild(style);

// ==================== INITIALIZE APPLICATION ====================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[APP] DOM Content Loaded');
        App.init();
    });
} else {
    console.log('[APP] DOM Already Loaded');
    App.init();
}

// Export for debugging
window.AppState = AppState;
window.App = App;
window.DataManager = DataManager;
window.TargetManager = TargetManager;

console.log('[APP] Sales Analytics Dashboard Script Loaded')

</script>
<style>
.history-section {
  position: absolute;
  top: 655px;
  left: 60%;
  transform: translateX(-50%);
  width: 80%; /* wide, but still leaves some margin */
}




  /* ==================== PROFESSIONAL SALES ANALYTICS DASHBOARD CSS ==================== */

/* CSS Variables */
:root {
    /* Primary Colors - Professional Blue/Indigo Palette */
    --primary-50: #eef2ff;
    --primary-100: #e0e7ff;
    --primary-200: #c7d2fe;
    --primary-300: #a5b4fc;
    --primary-400: #818cf8;
    --primary-500: #6366f1;
    --primary-600: #4f46e5;
    --primary-700: #4338ca;
    --primary-800: #3730a3;
    --primary-900: #312e81;
    
    /* Success Colors */
    --success-50: #ecfdf5;
    --success-100: #d1fae5;
    --success-500: #10b981;
    --success-600: #059669;
    --success-700: #047857;
    
    /* Warning Colors */
    --warning-50: #fffbeb;
    --warning-100: #fef3c7;
    --warning-500: #f59e0b;
    --warning-600: #d97706;
    
    /* Danger Colors */
    --danger-50: #fef2f2;
    --danger-100: #fee2e2;
    --danger-500: #ef4444;
    --danger-600: #dc2626;
    
    /* Neutral Colors */
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    
    /* Background */
    --bg-primary: #ffffff;
    --bg-secondary: #f9fafb;
    --bg-tertiary: #f3f4f6;
    
    /* Shadows */
    --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    
    /* Gradients */
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    
    /* Border Radius */
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-2xl: 24px;
    
    /* Transitions */
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* Reset & Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
    background: var(--bg-secondary);
    color: var(--gray-900);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Dashboard Wrapper */
.dashboard-wrapper {
    min-height: 100vh;
    background: var(--bg-secondary);
}

/* Top Navigation Bar */
.top-navbar {
  background: #ffffff;
  border-bottom: 1px solid #f1f1f1;
  padding: 28px 60px; /* increased padding */
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
  transition: all 0.3s ease;
}

/* Brand Title (Left Section) */
.navbar-left .brand-title {
  display: flex;
  align-items: center;
  gap: 14px;
  font-size: 26px; /* larger font */
  font-weight: 700;
  color: #111827;
  letter-spacing: 0.6px;
}

.navbar-left .brand-title svg {
  width: 30px;
  height: 30px;

}

/* Date Display (Center Section) */
.navbar-center .date-display {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  padding: 10px 22px; /* bigger size */
  font-size: 16px;
  color: #374151;
  font-weight: 600;
}

.navbar-center .date-display svg {
  width: 22px;
  height: 22px;
  color: #f59e0b;
}

/* Right Section (Icons / Profile Buttons) */
.navbar-right {
  display: flex;
  align-items: center;
  gap: 18px;
}

/* Circular Icon Buttons */
.navbar-right .icon-btn {
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  border-radius: 50%;
  width: 46px; /* larger buttons */
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.25s ease;
  color: #6b7280;
  font-size: 18px;
}

.navbar-right .icon-btn:hover {
  background: #f59e0b;
  color: #fff;
  border-color: #f59e0b;
  transform: translateY(-2px);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
  .top-navbar {
    padding: 18px 24px;
    flex-direction: column;
    align-items: flex-start;
    gap: 14px;
  }

  .navbar-left .brand-title {
    font-size: 22px;
  }

  .navbar-center .date-display {
    font-size: 14px;
    padding: 8px 16px;
  }

  .navbar-right {
    justify-content: flex-end;
    width: 100%;
  }
}


.icon-btn {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
    background: var(--bg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all var(--transition-base);
}

.icon-btn:hover {
    background: var(--gray-50);
    border-color: var(--primary-300);
}

.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--danger-500);
    color: white;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-primary-small {
    padding: 8px 16px;
    background: var(--primary-600);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all var(--transition-base);
}

.btn-primary-small:hover {
    background: var(--primary-700);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

/* Main Content */
.dashboard-content {
    max-width: 1600px;
    margin: 0 auto;
    padding: 32px;
}

/* Section Titles */
.section-title-main {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 20px;
}

/* KPI Section */
.kpi-section {
  margin-bottom: 40px;
}

/* Grid Layout */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}

/* KPI Card */
.kpi-card-pro {
  background: #fff;
  border-radius: 16px;
  padding: 28px 24px;
  border: 1px solid #f1f1f1;
  transition: all 0.3s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.kpi-card-pro:hover {
  transform: translateY(-6px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  border-color: #f59e0b; /* matches your orange theme */
}

/* KPI Header */
.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

/* KPI Icon */
.kpi-icon-pro {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f59e0b;
  color: #fff;
  font-size: 22px;
  box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3);
}

/* KPI Title & Value */
.kpi-title {
  font-size: 15px;
  color: #6b7280;
  font-weight: 500;
  margin-bottom: 6px;
}

.kpi-value {
  font-size: 26px;
  font-weight: 700;
  color: #111827;
}

/* Optional subtle growth text */
.kpi-change {
  font-size: 13px;
  color: #10b981; /* green for positive change */
  margin-top: 4px;
}

.kpi-change.negative {
  color: #ef4444; /* red for negative change */
}


.sales-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.customers-gradient { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.transactions-gradient { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.target-gradient { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }

.kpi-trend-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: var(--success-50);
    color: var(--success-700);
}

.kpi-trend-badge.down {
    background: var(--danger-50);
    color: var(--danger-700);
}

.kpi-trend-badge.down svg {
    transform: rotate(180deg);
}

.kpi-trend-badge.success {
    background: var(--success-50);
    color: var(--success-700);
}

.kpi-body {
    margin-top: 16px;
}

.kpi-label-pro {
    display: block;
    font-size: 13px;
    color: var(--gray-500);
    font-weight: 500;
    margin-bottom: 8px;
}

.kpi-value-pro {
    font-size: 32px;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    line-height: 1;
}

.kpi-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.kpi-comparison {
    font-size: 12px;
    color: var(--gray-500);
}

.kpi-sparkline {
    font-size: 8px;
    color: var(--primary-400);
    letter-spacing: 2px;
}

.mini-progress {
    width: 100%;
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 8px;
}

.mini-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
    transition: width var(--transition-slow);
}

/* Charts Section */
.charts-section {
    margin-bottom: 32px;
}

.chart-container-wrapper {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
}

.chart-card {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 24px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}

.chart-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 4px 0;
}

.chart-subtitle {
    font-size: 13px;
    color: var(--gray-500);
    margin: 0;
}

.chart-select {
    padding: 6px 12px;
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-md);
    font-size: 13px;
    background: white;
    color: var(--gray-700);
    cursor: pointer;
    transition: all var(--transition-base);
}

.chart-select:hover {
    border-color: var(--primary-400);
}

.chart-select:focus {
    outline: none;
    border-color: var(--primary-500);
    box-shadow: 0 0 0 3px var(--primary-100);
}

.chart-body {
    position: relative;
}

/* Comparison Section */
.comparison-section-pro {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 24px;
    border: 1px solid var(--gray-200);
    margin-bottom: 32px;
}

.section-header-pro {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.btn-outline-small {
    padding: 6px 12px;
    border: 1px solid var(--gray-300);
    background: transparent;
    color: var(--gray-700);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-base);
}

.btn-outline-small:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

.comparison-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.control-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 6px;
}

.form-select-pro,
.form-input-pro {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-md);
    font-size: 14px;
    background: white;
    color: var(--gray-900);
    transition: all var(--transition-base);
}

.form-select-pro:focus,
.form-input-pro:focus {
    outline: none;
    border-color: var(--primary-500);
    box-shadow: 0 0 0 3px var(--primary-100);
}

.btn-primary-pro {
    width: 100%;
    padding: 10px 20px;
    background: var(--primary-600);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all var(--transition-base);
    margin-top: 24px;
}

.btn-primary-pro:hover {
    background: var(--primary-700);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.comparison-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.comparison-metric-card {
    padding: 20px;
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
}

.metric-name {
    font-size: 13px;
    color: var(--gray-600);
    font-weight: 500;
    margin-bottom: 8px;
}

.metric-values {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 12px;
}

.metric-current {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-900);
}

.metric-previous {
    font-size: 14px;
    color: var(--gray-500);
}

.metric-change {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
}

.metric-change.positive {
    color: var(--success-600);
}

.metric-change.negative {
    color: var(--danger-600);
}

/* Targets Section */
.targets-section-pro {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 24px;
    border: 1px solid var(--gray-200);
    margin-bottom: 32px;
}

.filter-controls {
    display: flex;
    gap: 12px;
}

.targets-grid {
    display: grid;
    gap: 16px;
}

.target-card-pro {
    padding: 20px;
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
    transition: all var(--transition-base);
}

.target-card-pro:hover {
    border-color: var(--primary-300);
    box-shadow: var(--shadow-md);
}

.target-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.target-name-pro {
    font-size: 16px;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 4px 0;
}

.target-type-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--primary-100);
    color: var(--primary-700);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.target-progress-section {
    margin-bottom: 16px;
}

.progress-bar-container {
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-bar-fill {
    height: 100%;
    transition: width var(--transition-slow);
}

.progress-bar-fill.achieved {
    background: linear-gradient(90deg, var(--success-500), var(--success-600));
}

.progress-bar-fill.near {
    background: linear-gradient(90deg, var(--warning-500), var(--warning-600));
}

.progress-bar-fill.below {
    background: linear-gradient(90deg, var(--danger-400), var(--danger-500));
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.progress-percentage {
    font-weight: 700;
    color: var(--gray-900);
}

.progress-values {
    color: var(--gray-600);
}

.target-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.target-dates {
    font-size: 12px;
    color: var(--gray-500);
}

.target-actions {
    display: flex;
    gap: 8px;
}

.btn-icon-small {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-300);
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-base);
}

.btn-icon-small:hover {
    background: var(--gray-50);
    border-color: var(--primary-400);
}

.btn-icon-small.delete:hover {
    background: var(--danger-50);
    border-color: var(--danger-400);
    color: var(--danger-600);
}

/* Tables Section */
.tables-section-pro {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.table-tabs {
    display: flex;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
}

.tab-btn {
    flex: 1;
    padding: 16px;
    border: none;
    background: transparent;
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-600);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all var(--transition-base);
}

.tab-btn:hover {
    background: var(--gray-100);
}

.tab-btn.active {
    color: var(--primary-600);
    border-bottom-color: var(--primary-600);
    background: white;
}

.tab-content {
    display: none;
    padding: 24px;
}

.tab-content.active {
    display: block;
}

.table-wrapper-pro {
    overflow-x: auto;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
}

.data-table-pro {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.data-table-pro thead {
    background: var(--gray-50);
}

.data-table-pro th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}

.data-table-pro td {
    padding: 14px 16px;
    border-top: 1px solid var(--gray-200);
    font-size: 14px;
    color: var(--gray-700);
}

.data-table-pro tbody tr:hover {
    background: var(--gray-50);
}

.loading-cell {
    text-align: center;
    padding: 40px;
    color: var(--gray-400);
    font-style: italic;
}

.status-badge-pro {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge-pro.achieved {
    background: var(--success-100);
    color: var(--success-700);
}

.status-badge-pro.near {
    background: var(--warning-100);
    color: var(--warning-700);
}

.status-badge-pro.below {
    background: var(--danger-100);
    color: var(--danger-600);
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity var(--transition-base);
}

.modal-overlay.active {
    display: flex;
    opacity: 1;
}

.modal-container {
    background: white;
    border-radius: var(--radius-2xl);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-2xl);
    animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-header-pro {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid var(--gray-200);
}

.modal-header-pro h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
}

.modal-close-btn {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    border: none;
    background: var(--gray-100);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-base);
}

.modal-close-btn:hover {
    background: var(--gray-200);
    transform: rotate(90deg);
}

.modal-body-pro {
    padding: 24px 32px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group-pro {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group-pro.full-width {
    grid-column: 1 / -1;
}

.form-group-pro label {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-700);
}

.modal-footer-pro {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 24px 32px;
    border-top: 1px solid var(--gray-200);
}

.btn-secondary-pro {
    padding: 10px 20px;
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-base);
}

.btn-secondary-pro:hover {
    background: var(--gray-50);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .chart-container-wrapper {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-content {
        padding: 20px;
    }
    
    .top-navbar {
        flex-wrap: wrap;
        gap: 16px;
        padding: 16px;
    }
    
    .navbar-center {
        order: 3;
        width: 100%;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .comparison-controls {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .table-tabs {
        overflow-x: auto;
    }
}
  </style>


  <style>
    /* ========================================
   CUSTOMER INTELLIGENCE PLATFORM v3.0
   Premium Dashboard Design System
   ======================================== */

/* === DESIGN SYSTEM FOUNDATION === */
:root {
  /* Primary Color Palette - Deep Ocean Blue Theme */
  --color-primary: #0a4d68;
  --color-primary-light: #088395;
  --color-primary-dark: #05364d;
  --color-accent: #05dfd7;
  --color-accent-glow: rgba(5, 223, 215, 0.3);
  
  /* Neutral Palette */
  --color-bg-main: #f8fafc;
  --color-bg-card: #ffffff;
  --color-bg-elevated: #ffffff;
  --color-surface: #f1f5f9;
  --color-border: #e2e8f0;
  --color-border-light: #f1f5f9;
  
  /* Text Colors */
  --color-text-primary: #1e293b;
  --color-text-secondary: #64748b;
  --color-text-muted: #94a3b8;
  --color-text-inverse: #ffffff;
  
  /* Status Colors */
  --color-success: #10b981;
  --color-success-bg: #d1fae5;
  --color-warning: #f59e0b;
  --color-warning-bg: #fef3c7;
  --color-danger: #ef4444;
  --color-danger-bg: #fee2e2;
  --color-info: #3b82f6;
  
  /* Tier Colors */
  --color-gold: #fbbf24;
  --color-silver: #94a3b8;
  --color-platinum: #a78bfa;
  
  /* Spacing System */
  --space-xs: 0.25rem;
  --space-sm: 0.5rem;
  --space-md: 1rem;
  --space-lg: 1.5rem;
  --space-xl: 2rem;
  --space-2xl: 3rem;
  --space-3xl: 4rem;
  
  /* Border Radius */
  --radius-sm: 0.375rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 1rem;
  --radius-2xl: 1.5rem;
  
  /* Shadows */
  --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  
  /* Typography */
  --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  --font-mono: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, monospace;
  
  /* Transitions */
  --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
  --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* === GLOBAL RESET === */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: var(--font-sans);
  background: var(--color-bg-main);
  color: var(--color-text-primary);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* === CONTAINER === */
.page {
  max-width: 1800px;
  margin: 0 auto;
  padding: var(--space-xl);
  min-height: 100vh;
}

/* === EXECUTIVE DASHBOARD HEADER === */
.executive-dashboard-header {
  background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 50%, var(--color-primary-light) 100%);
  border-radius: var(--radius-2xl);
  padding: var(--space-2xl);
  margin-bottom: var(--space-2xl);
  box-shadow: var(--shadow-xl);
  position: relative;
  overflow: hidden;
  color: var(--color-text-inverse);
}

.header-matrix {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: repeating-linear-gradient(
    0deg,
    rgba(255, 255, 255, 0.03) 0px,
    transparent 1px,
    transparent 40px,
    rgba(255, 255, 255, 0.03) 41px
  ),
  repeating-linear-gradient(
    90deg,
    rgba(255, 255, 255, 0.03) 0px,
    transparent 1px,
    transparent 40px,
    rgba(255, 255, 255, 0.03) 41px
  );
  pointer-events: none;
}

.platform-identifier {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
  position: relative;
  z-index: 1;
}

.platform-logo svg {
  filter: drop-shadow(0 2px 8px var(--color-accent-glow));
}

.platform-status {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: 0.875rem;
  font-weight: 500;
  letter-spacing: 0.025em;
}

.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-accent);
  box-shadow: 0 0 12px var(--color-accent);
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.platform-title {
  margin-bottom: var(--space-xl);
  position: relative;
  z-index: 1;
}

.title-primary {
  display: block;
  font-size: 2.5rem;
  font-weight: 700;
  letter-spacing: -0.025em;
  margin-bottom: var(--space-xs);
}

.title-secondary {
  display: block;
  font-size: 1rem;
  font-weight: 400;
  opacity: 0.9;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* === GLOBAL METRICS BAR === */
.global-metrics-bar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-lg);
  margin-bottom: var(--space-xl);
  position: relative;
  z-index: 1;
}

.metric-block {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  padding: var(--space-lg);
  border-radius: var(--radius-lg);
  border: 1px solid rgba(255, 255, 255, 0.15);
  transition: all var(--transition-base);
}

.metric-block:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-2px);
}

.metric-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-md);
  flex-shrink: 0;
}

.metric-data {
  flex: 1;
}

.metric-value {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: var(--space-xs);
}

.metric-label {
  font-size: 0.875rem;
  opacity: 0.9;
  margin-bottom: var(--space-xs);
}

.metric-delta {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  display: inline-block;
}

.metric-delta.positive {
  background: rgba(16, 185, 129, 0.2);
  color: #6ee7b7;
}

.metric-delta.negative {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}

.data-freshness {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: 0.75rem;
  opacity: 0.8;
  position: relative;
  z-index: 1;
}

.separator {
  opacity: 0.5;
}

/* === ANALYTICS GRID === */
.analytics-grid-advanced {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: var(--space-xl);
}

/* === INTELLIGENCE CARD BASE === */
.intelligence-card {
  background: var(--color-bg-card);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--color-border-light);
  overflow: hidden;
  transition: all var(--transition-base);
  position: relative;
}

.intelligence-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}

.card-matrix-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 200px;
  background: linear-gradient(180deg, var(--color-surface) 0%, transparent 100%);
  pointer-events: none;
  opacity: 0.5;
}

/* === CARD LAYOUTS === */
.customer-profiles-card {
  grid-column: span 12;
}

.retention-analytics-card {
  grid-column: span 12;
}

.purchase-intelligence-card {
  grid-column: span 12;
}

.segmentation-engine-card {
  grid-column: span 12;
}

.journey-mapping-card {
  grid-column: span 6;
}

.ai-predictive-card {
  grid-column: span 6;
}

.engagement-heatmap-card {
  grid-column: span 12;
}

.activity-feed-card {
  grid-column: span 12;
}

/* === CARD HEADER === */
.card-header-advanced {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-xl);
  border-bottom: 1px solid var(--color-border-light);
  background: var(--color-bg-elevated);
}

.header-group {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.header-icon-wrapper {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  border-radius: var(--radius-md);
  color: white;
  flex-shrink: 0;
}

.header-icon-wrapper.critical {
  background: linear-gradient(135deg, var(--color-danger), #dc2626);
}

.header-icon-wrapper.ai {
  background: linear-gradient(135deg, var(--color-accent), var(--color-primary-light));
}

.card-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-text-primary);
  letter-spacing: -0.025em;
}

/* === ACTION BUTTONS === */
.header-actions {
  display: flex;
  gap: var(--space-sm);
}

.action-btn {
  padding: var(--space-sm) var(--space-lg);
  border: 1px solid var(--color-border);
  background: white;
  color: var(--color-text-secondary);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.action-btn:hover {
  background: var(--color-surface);
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.action-btn.primary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.action-btn.primary:hover {
  background: var(--color-primary-dark);
}

/* === CUSTOMER PROFILES === */
.customer-intelligence-grid {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.customer-profile-item {
  display: grid;
  grid-template-columns: auto 1fr auto auto auto;
  gap: var(--space-xl);
  align-items: center;
  padding: var(--space-xl);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.customer-profile-item:hover {
  border-color: var(--color-primary-light);
  box-shadow: var(--shadow-md);
  transform: translateX(4px);
}

.customer-profile-item.vip {
  background: linear-gradient(135deg, rgba(10, 77, 104, 0.05) 0%, transparent 100%);
  border-color: var(--color-primary-light);
}

/* Profile Rank */
.profile-rank {
  display: flex;
  align-items: center;
}

.rank-badge {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  font-weight: 700;
  font-size: 1.125rem;
  background: var(--color-surface);
  color: var(--color-text-secondary);
}

.rank-badge.gold {
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  color: white;
  box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}

.rank-badge.silver {
  background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
  color: var(--color-text-primary);
}

/* Profile Identity */
.profile-identity {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.profile-avatar {
  width: 56px;
  height: 56px;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.125rem;
  flex-shrink: 0;
}

.profile-details {
  flex: 1;
}

.profile-name {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-xs);
}

.profile-id {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  font-family: var(--font-mono);
  margin-bottom: var(--space-sm);
}

.profile-tags {
  display: flex;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.tag {
  padding: 2px 10px;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 500;
  background: var(--color-surface);
  color: var(--color-text-secondary);
}

.tag.vip {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  color: white;
}

.tag.loyal {
  background: var(--color-success-bg);
  color: var(--color-success);
}

.tag.gold {
  background: rgba(251, 191, 36, 0.15);
  color: #f59e0b;
}

.tag.silver {
  background: rgba(148, 163, 184, 0.15);
  color: #64748b;
}

/* Profile Metrics */
.profile-metrics {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-md) var(--space-xl);
  min-width: 320px;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.metric-item .label {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.metric-item .value {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text-primary);
}

/* Profile Behavior */
.profile-behavior {
  display: flex;
  align-items: center;
  gap: var(--space-lg);
  min-width: 200px;
}

.behavior-chart {
  flex: 1;
  height: 40px;
}

.mini-chart {
  width: 100%;
  height: 100%;
}

.behavior-score {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-xs);
}

.score-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-primary);
}

.score-label {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  text-transform: uppercase;
}

/* Profile Actions */
.profile-actions {
  display: flex;
  gap: var(--space-sm);
}

.btn-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
  background: white;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  color: var(--color-text-secondary);
}

.btn-icon:hover {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: white;
  transform: scale(1.1);
}

/* === RETENTION ANALYTICS === */
.retention-matrix {
  padding: var(--space-xl);
}

.matrix-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-xl);
}

.matrix-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
}

.matrix-legend {
  display: flex;
  gap: var(--space-lg);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.legend-item::before {
  content: '';
  width: 12px;
  height: 12px;
  border-radius: var(--radius-sm);
}

.legend-item.healthy::before {
  background: var(--color-success);
}

.legend-item.at-risk::before {
  background: var(--color-warning);
}

.legend-item.critical::before {
  background: var(--color-danger);
}

/* Health Segments */
.health-segments {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: var(--space-lg);
  margin-bottom: var(--space-2xl);
}

.segment-card {
  padding: var(--space-xl);
  border-radius: var(--radius-lg);
  border: 2px solid var(--color-border-light);
  transition: all var(--transition-base);
}

.segment-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
}

.segment-card.healthy {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
  border-color: var(--color-success);
}

.segment-card.at-risk {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%);
  border-color: var(--color-warning);
}

.segment-card.critical {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, transparent 100%);
  border-color: var(--color-danger);
}

.segment-header {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin-bottom: var(--space-lg);
}

.segment-title {
  font-weight: 600;
  color: var(--color-text-primary);
}

.segment-stats {
  display: flex;
  align-items: baseline;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.stat-value {
  font-size: 2.25rem;
  font-weight: 700;
  color: var(--color-text-primary);
  line-height: 1;
}

.stat-percentage {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.segment-details {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.detail-row span:last-child {
  font-weight: 600;
  color: var(--color-text-primary);
}

/* At Risk Customers */
.at-risk-customers h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.risk-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.risk-customer {
  display: flex;
  align-items: center;
  gap: var(--space-lg);
  padding: var(--space-lg);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.risk-customer:hover {
  border-color: var(--color-warning);
  box-shadow: var(--shadow-md);
}

.customer-info {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex: 1;
}

.customer-avatar {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.customer-details {
  flex: 1;
}

.customer-name {
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-xs);
}

.customer-meta {
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.risk-level {
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
}

.risk-level.high {
  background: var(--color-danger-bg);
  color: var(--color-danger);
}

.risk-level.medium {
  background: var(--color-warning-bg);
  color: var(--color-warning);
}

.action-button {
  padding: var(--space-sm) var(--space-lg);
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.action-button:hover {
  background: var(--color-primary-dark);
  transform: scale(1.05);
}

/* === PURCHASE INTELLIGENCE === */
.purchase-analytics {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.purchase-overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: var(--space-lg);
}

.overview-stat {
  display: flex;
  align-items: flex-start;
  gap: var(--space-md);
  padding: var(--space-xl);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.overview-stat:hover {
  background: white;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.stat-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  border-radius: var(--radius-md);
  color: white;
  flex-shrink: 0;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  margin-bottom: var(--space-sm);
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-text-primary);
  margin-bottom: var(--space-sm);
}

.stat-change {
  font-size: 0.875rem;
  font-weight: 500;
}

.stat-change.positive {
  color: var(--color-success);
}

/* Product Performance */
.product-performance h3,
.category-breakdown h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.combo-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.combo-item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-lg);
  align-items: center;
  padding: var(--space-lg);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.combo-item:hover {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.combo-rank {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary);
  color: white;
  border-radius: var(--radius-md);
  font-weight: 700;
}

.combo-products {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  font-size: 0.9375rem;
}

.combo-products .product {
  font-weight: 500;
  color: var(--color-text-primary);
}

.combo-products .plus {
  color: var(--color-text-muted);
  font-weight: 300;
}

.combo-stats {
  display: flex;
  gap: var(--space-xl);
  font-size: 0.875rem;
}

.combo-stats .frequency {
  color: var(--color-text-secondary);
}

.combo-stats .revenue {
  font-weight: 700;
  color: var(--color-primary);
}

/* Category Grid */
.category-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-lg);
}

.category-cell {
  padding: var(--space-xl);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  border: 2px solid transparent;
  transition: all var(--transition-base);
}

.category-cell:hover {
  background: white;
  border-color: var(--color-primary);
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
}

.category-name {
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.category-metrics {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.category-metrics .metric {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-text-primary);
}

.category-metrics .metric-label {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.category-metrics .metric-trend {
  font-size: 0.875rem;
  font-weight: 600;
}

.category-metrics .metric-trend.positive {
  color: var(--color-success);
}

/* === SEGMENTATION ENGINE === */
.segment-controls {
  display: flex;
  gap: var(--space-sm);
}

.control-btn {
  padding: var(--space-sm) var(--space-lg);
  border: 1px solid var(--color-border);
  background: white;
  color: var(--color-text-secondary);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.control-btn:hover {
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.control-btn.active {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.segmentation-analysis {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.segment-distribution {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
}

.distribution-chart {
  width: 100%;
  height: auto;
}

.segment-insights {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--space-lg);
}

.insight-card {
  padding: var(--space-xl);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.insight-card:hover {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-md);
  transform: translateY(-4px);
}

.insight-header {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.insight-icon {
  font-size: 1.5rem;
}

.insight-title {
  font-weight: 600;
  color: var(--color-text-primary);
}

.insight-metrics {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  margin-bottom: var(--space-lg);
}

.metric-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.metric-row .value {
  font-weight: 700;
  color: var(--color-text-primary);
}

.insight-action button {
  width: 100%;
  padding: var(--space-sm) var(--space-md);
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.insight-action button:hover {
  background: var(--color-primary-dark);
  transform: scale(1.02);
}

/* === JOURNEY MAPPING === */
.journey-visualization {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.journey-stages {
  display: flex;
  align-items: center;
  gap: 0;
  overflow-x: auto;
  padding: var(--space-lg) 0;
}

.stage {
  flex: 1;
  min-width: 200px;
  padding: var(--space-xl);
  background: var(--color-surface);
  border: 2px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.stage:hover {
  background: white;
  border-color: var(--color-primary);
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
  z-index: 1;
}

.stage.active {
  background: linear-gradient(135deg, rgba(10, 77, 104, 0.05) 0%, transparent 100%);
  border-color: var(--color-primary);
}

.stage-connector {
  width: 40px;
  height: 2px;
  background: var(--color-border);
  position: relative;
  flex-shrink: 0;
}

.stage-connector::after {
  content: '';
  position: absolute;
  right: -6px;
  top: -4px;
  width: 0;
  height: 0;
  border-left: 6px solid var(--color-border);
  border-top: 5px solid transparent;
  border-bottom: 5px solid transparent;
}

.stage-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  border-radius: var(--radius-md);
  color: white;
  margin-bottom: var(--space-md);
}

.stage-info {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.stage-name {
  font-weight: 600;
  color: var(--color-text-primary);
  font-size: 1rem;
}

.stage-stats {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  font-size: 0.875rem;
}

.stage-stats .stat {
  color: var(--color-text-secondary);
}

.stage-stats .conversion {
  color: var(--color-primary);
  font-weight: 600;
}

/* Touchpoint Analysis */
.touchpoint-analysis h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.touchpoint-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--space-lg);
}

.touchpoint {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-xl);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.touchpoint:hover {
  background: white;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.touchpoint-channel {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  text-align: center;
}

.touchpoint-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-primary);
}

/* === AI PREDICTIVE ANALYTICS === */
.ai-status {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-success);
  box-shadow: 0 0 8px var(--color-success);
  animation: pulse 2s infinite;
}

.ai-predictions {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.prediction-panel {
  padding: var(--space-xl);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
}

.prediction-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-xl);
}

.prediction-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
}

.confidence-badge {
  padding: var(--space-sm) var(--space-md);
  background: var(--color-success-bg);
  color: var(--color-success);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
}

.prediction-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--space-lg);
}

.pred-card {
  display: flex;
  align-items: flex-start;
  gap: var(--space-md);
  padding: var(--space-xl);
  background: white;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border-light);
  transition: all var(--transition-base);
}

.pred-card:hover {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.pred-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  flex-shrink: 0;
}

.pred-icon.revenue {
  background: linear-gradient(135deg, var(--color-success), #059669);
  color: white;
}

.pred-icon.customers {
  background: linear-gradient(135deg, var(--color-info), #2563eb);
  color: white;
}

.pred-icon.warning {
  background: linear-gradient(135deg, var(--color-warning), #d97706);
  color: white;
}

.pred-content {
  flex: 1;
}

.pred-label {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  margin-bottom: var(--space-sm);
}

.pred-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--color-text-primary);
  margin-bottom: var(--space-sm);
}

.pred-change {
  font-size: 0.875rem;
  font-weight: 500;
}

.pred-change.positive {
  color: var(--color-success);
}

.pred-change.negative {
  color: var(--color-danger);
}

/* AI Recommendations */
.ai-recommendations h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.recommendation-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.recommendation {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-lg);
  align-items: center;
  padding: var(--space-lg);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-left: 4px solid var(--color-border);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.recommendation:hover {
  box-shadow: var(--shadow-md);
  transform: translateX(4px);
}

.recommendation.urgent {
  border-left-color: var(--color-danger);
  background: linear-gradient(90deg, rgba(239, 68, 68, 0.05) 0%, transparent 100%);
}

.recommendation.high {
  border-left-color: var(--color-warning);
  background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%);
}

.recommendation.medium {
  border-left-color: var(--color-info);
  background: linear-gradient(90deg, rgba(59, 130, 246, 0.05) 0%, transparent 100%);
}

.rec-priority {
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-md);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.recommendation.urgent .rec-priority {
  background: var(--color-danger-bg);
  color: var(--color-danger);
}

.recommendation.high .rec-priority {
  background: var(--color-warning-bg);
  color: var(--color-warning);
}

.recommendation.medium .rec-priority {
  background: rgba(59, 130, 246, 0.15);
  color: var(--color-info);
}

.rec-content {
  flex: 1;
}

.rec-title {
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-xs);
}

.rec-impact {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.rec-action {
  padding: var(--space-sm) var(--space-xl);
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.rec-action:hover {
  background: var(--color-primary-dark);
  transform: scale(1.05);
}

/* Anomaly Alerts */
.anomaly-alerts h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.anomaly-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.anomaly-item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-md);
  padding: var(--space-lg);
  background: var(--color-warning-bg);
  border: 1px solid var(--color-warning);
  border-radius: var(--radius-lg);
  font-size: 0.875rem;
  color: var(--color-text-primary);
}

.anomaly-icon {
  flex-shrink: 0;
  color: var(--color-warning);
}

/* === ENGAGEMENT HEATMAP === */
.heatmap-container {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.time-heatmap-advanced h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: var(--space-lg);
}

.heatmap-grid {
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-rows: 1fr auto;
  gap: var(--space-md);
}

.heatmap-labels-y {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  gap: var(--space-sm);
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  padding-right: var(--space-md);
}

.heatmap-labels-y span {
  height: 40px;
  display: flex;
  align-items: center;
}

.heatmap-content {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.heatmap-row {
  display: flex;
  gap: var(--space-sm);
}

.heat-cell {
  width: 100%;
  height: 40px;
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  transition: all var(--transition-fast);
  cursor: pointer;
  position: relative;
}

.heat-cell:hover::after {
  content: attr(data-value) '%';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 0.75rem;
  font-weight: 600;
  color: white;
}

.heat-cell[data-value="12"],
.heat-cell[data-value="18"],
.heat-cell[data-value="24"] {
  background: rgba(10, 77, 104, 0.1);
}

.heat-cell[data-value="28"],
.heat-cell[data-value="32"],
.heat-cell[data-value="38"] {
  background: rgba(10, 77, 104, 0.2);
}

.heat-cell[data-value="42"],
.heat-cell[data-value="45"],
.heat-cell[data-value="48"] {
  background: rgba(10, 77, 104, 0.3);
}

.heat-cell[data-value="52"],
.heat-cell[data-value="58"] {
  background: rgba(10, 77, 104, 0.5);
}

.heat-cell[data-value="65"] {
  background: rgba(10, 77, 104, 0.6);
}

.heat-cell.high,
.heat-cell[data-value="72"],
.heat-cell[data-value="78"],
.heat-cell[data-value="85"] {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
}

.heatmap-labels-x {
  grid-column: 2;
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  padding-top: var(--space-sm);
}

.heatmap-legend {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-top: var(--space-lg);
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.legend-gradient {
  flex: 1;
  height: 12px;
  background: linear-gradient(90deg, 
    rgba(10, 77, 104, 0.1) 0%, 
    rgba(10, 77, 104, 0.3) 25%,
    rgba(10, 77, 104, 0.5) 50%,
    rgba(10, 77, 104, 0.7) 75%,
    var(--color-primary) 100%
  );
  border-radius: var(--radius-sm);
}

/* Engagement Metrics */
.engagement-metrics {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-xl);
}

.engagement-score-card {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--space-2xl);
  padding: var(--space-xl);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
}

.score-ring-container {
  position: relative;
  width: 200px;
  height: 200px;
}

.engagement-ring {
  width: 100%;
  height: 100%;
}

.score-display {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
}

.score-number {
  font-size: 3rem;
  font-weight: 700;
  color: var(--color-primary);
  line-height: 1;
}

.score-label {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  margin-top: var(--space-sm);
}

.engagement-breakdown {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: var(--space-xl);
}

.breakdown-item {
  display: grid;
  grid-template-columns: 120px 1fr auto;
  gap: var(--space-md);
  align-items: center;
}

.breakdown-label {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.breakdown-bar {
  height: 12px;
  background: var(--color-border-light);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
  border-radius: var(--radius-sm);
  transition: width var(--transition-slow);
}

.breakdown-value {
  font-weight: 700;
  color: var(--color-text-primary);
  min-width: 60px;
  text-align: right;
}

/* === ACTIVITY FEED === */
.feed-status {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-danger);
}

.pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-danger);
  box-shadow: 0 0 0 0 var(--color-danger);
  animation: pulse-ring 2s infinite;
}

@keyframes pulse-ring {
  0% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
  }
}

.activity-feed {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  max-height: 600px;
  overflow-y: auto;
}

.feed-item {
  display: grid;
  grid-template-columns: 100px 1fr;
  gap: var(--space-lg);
  padding: var(--space-lg);
  background: var(--color-bg-elevated);
  border: 1px solid var(--color-border-light);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.feed-item:hover {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-sm);
  transform: translateX(4px);
}

.feed-item.new {
  background: linear-gradient(90deg, rgba(5, 223, 215, 0.05) 0%, transparent 100%);
  border-color: var(--color-accent);
}

.feed-time {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  font-weight: 500;
}

.feed-content {
  display: flex;
  align-items: flex-start;
  gap: var(--space-md);
}

.feed-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  flex-shrink: 0;
}

.feed-icon.purchase {
  background: rgba(16, 185, 129, 0.15);
  color: var(--color-success);
}

.feed-icon.milestone {
  background: rgba(251, 191, 36, 0.15);
  color: var(--color-gold);
}

.feed-icon.alert {
  background: rgba(239, 68, 68, 0.15);
  color: var(--color-danger);
}

.feed-icon.review {
  background: rgba(59, 130, 246, 0.15);
  color: var(--color-info);
}

.feed-details {
  flex: 1;
  font-size: 0.9375rem;
  color: var(--color-text-secondary);
  line-height: 1.6;
}

.feed-details .customer-name {
  font-weight: 600;
  color: var(--color-text-primary);
}

.feed-details .amount {
  font-weight: 700;
  color: var(--color-primary);
}

.feed-details .achievement {
  font-weight: 500;
  color: var(--color-gold);
}

.feed-details .alert-type {
  font-weight: 600;
  color: var(--color-danger);
}

.feed-details .review {
  font-style: italic;
  color: var(--color-text-primary);
}

/* === EXECUTIVE SUMMARY === */
.executive-summary {
  margin-top: var(--space-2xl);
  padding: var(--space-2xl);
  background: var(--color-bg-card);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--color-border-light);
}

.summary-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-text-primary);
  margin-bottom: var(--space-xl);
  letter-spacing: -0.025em;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--space-xl);
}

.summary-card {
  display: flex;
  align-items: flex-start;
  gap: var(--space-lg);
  padding: var(--space-xl);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  border: 2px solid transparent;
  transition: all var(--transition-base);
}

.summary-card:hover {
  background: white;
  border-color: var(--color-primary);
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
}

.summary-icon {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
  border-radius: var(--radius-lg);
  color: white;
  flex-shrink: 0;
}

.summary-content {
  flex: 1;
}

.summary-label {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: var(--space-sm);
}

.summary-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text-primary);
  margin-bottom: var(--space-sm);
  line-height: 1;
}

.summary-change {
  font-size: 0.875rem;
  font-weight: 600;
}

.summary-change.positive {
  color: var(--color-success);
}

/* === RISK INDICATOR === */
.risk-indicator {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  background: var(--color-danger-bg);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-danger);
}

.indicator-label {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.indicator-value {
  font-weight: 700;
  font-size: 0.875rem;
}

.indicator-value.critical {
  color: var(--color-danger);
}

/* === RESPONSIVE DESIGN === */
@media (max-width: 1400px) {
  .analytics-grid-advanced {
    grid-template-columns: repeat(6, 1fr);
  }
  
  .customer-profiles-card,
  .retention-analytics-card,
  .purchase-intelligence-card,
  .segmentation-engine-card,
  .engagement-heatmap-card,
  .activity-feed-card {
    grid-column: span 6;
  }
  
  .journey-mapping-card,
  .ai-predictive-card {
    grid-column: span 6;
  }
}

@media (max-width: 1024px) {
  .page {
    padding: var(--space-lg);
  }
  
  .title-primary {
    font-size: 2rem;
  }
  
  .global-metrics-bar {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }
  
  .customer-profile-item {
    grid-template-columns: auto 1fr;
    gap: var(--space-lg);
  }
  
  .profile-metrics {
    grid-column: 1 / -1;
    grid-template-columns: repeat(4, 1fr);
  }
  
  .profile-behavior {
    grid-column: 1 / -1;
  }
  
  .profile-actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }
  
  .journey-stages {
    flex-direction: column;
    align-items: stretch;
  }
  
  .stage-connector {
    width: 2px;
    height: 40px;
    align-self: center;
  }
  
  .stage-connector::after {
    right: -5px;
    top: auto;
    bottom: -6px;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 6px solid var(--color-border);
  }
  
  .engagement-score-card {
    grid-template-columns: 1fr;
    justify-items: center;
  }
}

@media (max-width: 768px) {
  .page {
    padding: var(--space-md);
  }
  
  .executive-dashboard-header {
    padding: var(--space-xl) var(--space-lg);
  }
  
  .title-primary {
    font-size: 1.75rem;
  }
  
  .title-secondary {
    font-size: 0.875rem;
  }
  
  .global-metrics-bar {
    grid-template-columns: 1fr;
  }
  
  .metric-block {
    padding: var(--space-md);
  }
  
  .analytics-grid-advanced {
    grid-template-columns: 1fr;
    gap: var(--space-lg);
  }
  
  .customer-profiles-card,
  .retention-analytics-card,
  .purchase-intelligence-card,
  .segmentation-engine-card,
  .journey-mapping-card,
  .ai-predictive-card,
  .engagement-heatmap-card,
  .activity-feed-card {
    grid-column: span 1;
  }
  
  .card-header-advanced {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-md);
    padding: var(--space-lg);
  }
  
  .header-actions {
    width: 100%;
    flex-direction: column;
  }
  
  .action-btn {
    width: 100%;
    text-align: center;
  }
  
  .customer-profile-item {
    grid-template-columns: 1fr;
    padding: var(--space-lg);
  }
  
  .profile-metrics {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .health-segments {
    grid-template-columns: 1fr;
  }
  
  .purchase-overview {
    grid-template-columns: 1fr;
  }
  
  .category-grid {
    grid-template-columns: 1fr;
  }
  
  .segment-insights {
    grid-template-columns: 1fr;
  }
  
  .prediction-cards {
    grid-template-columns: 1fr;
  }
  
  .recommendation {
    grid-template-columns: 1fr;
    gap: var(--space-md);
  }
  
  .rec-action {
    width: 100%;
  }
  
  .heatmap-grid {
    overflow-x: auto;
  }
  
  .heatmap-content {
    min-width: 600px;
  }
  
  .touchpoint-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .feed-item {
    grid-template-columns: 1fr;
    gap: var(--space-sm);
  }
}

@media (max-width: 480px) {
  .title-primary {
    font-size: 1.5rem;
  }
  
  .metric-value {
    font-size: 1.5rem;
  }
  
  .card-title {
    font-size: 1.125rem;
  }
  
  .profile-avatar {
    width: 48px;
    height: 48px;
    font-size: 1rem;
  }
  
  .profile-name {
    font-size: 1rem;
  }
  
  .profile-metrics {
    grid-template-columns: 1fr;
  }
  
  .stat-value {
    font-size: 1.75rem;
  }
  
  .summary-value {
    font-size: 1.5rem;
  }
  
  .combo-item {
    grid-template-columns: 1fr;
  }
  
  .combo-products {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .combo-stats {
    flex-direction: column;
    gap: var(--space-sm);
  }
  
  .segment-controls {
    flex-direction: column;
    width: 100%;
  }
  
  .control-btn {
    width: 100%;
  }
  
  .touchpoint-grid {
    grid-template-columns: 1fr;
  }
  
  .breakdown-item {
    grid-template-columns: 1fr;
    gap: var(--space-sm);
  }
  
  .breakdown-value {
    text-align: left;
  }
}

/* === UTILITY CLASSES === */
.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}

.font-bold {
  font-weight: 700;
}

.font-semibold {
  font-weight: 600;
}

.uppercase {
  text-transform: uppercase;
}

.mt-1 { margin-top: var(--space-sm); }
.mt-2 { margin-top: var(--space-md); }
.mt-3 { margin-top: var(--space-lg); }
.mt-4 { margin-top: var(--space-xl); }

.mb-1 { margin-bottom: var(--space-sm); }
.mb-2 { margin-bottom: var(--space-md); }
.mb-3 { margin-bottom: var(--space-lg); }
.mb-4 { margin-bottom: var(--space-xl); }

.p-1 { padding: var(--space-sm); }
.p-2 { padding: var(--space-md); }
.p-3 { padding: var(--space-lg); }
.p-4 { padding: var(--space-xl); }

/* === SCROLLBAR STYLING === */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: var(--color-surface);
  border-radius: var(--radius-sm);
}

::-webkit-scrollbar-thumb {
  background: var(--color-border);
  border-radius: var(--radius-sm);
  transition: background var(--transition-fast);
}

::-webkit-scrollbar-thumb:hover {
  background: var(--color-primary-light);
}

/* === PRINT STYLES === */
@media print {
  .page {
    padding: 0;
  }
  
  .header-actions,
  .action-btn,
  .btn-icon,
  .action-button,
  .rec-action {
    display: none;
  }
  
  .intelligence-card {
    page-break-inside: avoid;
    box-shadow: none;
    border: 1px solid var(--color-border);
  }
  
  .executive-dashboard-header {
    background: white;
    color: var(--color-text-primary);
    border: 1px solid var(--color-border);
  }
  
  .platform-logo svg,
  .header-icon-wrapper {
    color: var(--color-primary);
  }
}

/* === FOCUS STYLES FOR ACCESSIBILITY === */
button:focus,
.action-btn:focus,
.btn-icon:focus,
.action-button:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* === LOADING STATES === */
@keyframes shimmer {
  0% {
    background-position: -1000px 0;
  }
  100% {
    background-position: 1000px 0;
  }
}

.loading {
  background: linear-gradient(
    90deg,
    var(--color-surface) 0%,
    var(--color-border-light) 50%,
    var(--color-surface) 100%
  );
  background-size: 1000px 100%;
  animation: shimmer 2s infinite;
}

/* === DARK MODE SUPPORT (Optional) === */
@media (prefers-color-scheme: dark) {
  :root {
    --color-bg-main: #0f172a;
    --color-bg-card: #1e293b;
    --color-bg-elevated: #1e293b;
    --color-surface: #334155;
    --color-border: #475569;
    --color-border-light: #334155;
    --color-text-primary: #f1f5f9;
    --color-text-secondary: #cbd5e1;
    --color-text-muted: #94a3b8;
  }
  
  .executive-dashboard-header {
    background: linear-gradient(135deg, #0c3a4f 0%, #0a4d68 50%, #088395 100%);
  }
  
  .intelligence-card {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -2px rgba(0, 0, 0, 0.3);
  }
  
  .heat-cell:hover {
    box-shadow: 0 0 0 2px var(--color-primary);
  }
}

/* === ANIMATION UTILITIES === */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in {
  animation: fadeIn var(--transition-base) ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.slide-in {
  animation: slideIn var(--transition-base) ease-out;
}

/* === PERFORMANCE OPTIMIZATIONS === */
.intelligence-card,
.customer-profile-item,
.segment-card {
  will-change: transform;
}

/* === END OF STYLESHEET === */
    </style>
  
  
  <div id="cust-insight" class="page">
    <!-- Enhanced Header with Real-time Monitoring -->
    <header class="executive-dashboard-header">
      <div class="header-matrix"></div>
      <div class="platform-identifier">
        <div class="platform-logo">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            <circle cx="12" cy="12" r="10" opacity="0.3"/>
          </svg>
        </div>
        <div class="platform-status">
          <span class="status-indicator live"></span>
          <span>Real-Time Analytics Engine</span>
        </div>
      </div>
      
      <h1 class="platform-title">
        <span class="title-primary">Customer Intelligence Platform</span>
        <span class="title-secondary">Enterprise Analytics Suite v3.0</span>
      </h1>
      
      <div class="global-metrics-bar">
        <div class="metric-block">
          <div class="metric-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
            </svg>
          </div>
          <div class="metric-data">
            <div class="metric-value">3,847</div>
            <div class="metric-label">Total Customers</div>
            <div class="metric-delta positive">+18.2%</div>
          </div>
        </div>
        
        <div class="metric-block">
          <div class="metric-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
          <div class="metric-data">
            <div class="metric-value">₱2.48M</div>
            <div class="metric-label">Monthly Revenue</div>
            <div class="metric-delta positive">+24.5%</div>
          </div>
        </div>
        
        <div class="metric-block">
          <div class="metric-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
          </div>
          <div class="metric-data">
            <div class="metric-value">₱645</div>
            <div class="metric-label">Avg Transaction</div>
            <div class="metric-delta positive">+8.7%</div>
          </div>
        </div>
        
        <div class="metric-block">
          <div class="metric-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
          </div>
          <div class="metric-data">
            <div class="metric-value">92.8%</div>
            <div class="metric-label">Satisfaction Score</div>
            <div class="metric-delta positive">+3.4%</div>
          </div>
        </div>
      </div>
      
      <div class="data-freshness">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
        <span>Last Updated: 2 seconds ago</span>
        <span class="separator">|</span>
        <span>Next Refresh: 58s</span>
      </div>
    </header>

    <!-- Premium Customer List Section -->
    <div class="analytics-grid-advanced">
      
      <!-- VIP Customer Intelligence Panel -->
      <div class="intelligence-card customer-profiles-card">
        <div class="card-matrix-overlay"></div>
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
            </div>
            <h2 class="card-title">Premium Customer Profiles</h2>
          </div>
          <div class="header-actions">
            <button class="action-btn">Export List</button>
            <button class="action-btn primary">Manage Segments</button>
          </div>
        </div>
        
        <div class="customer-intelligence-grid">
          <!-- Top Customers with Detailed Profiles -->
          <div class="customer-profile-item vip">
            <div class="profile-rank">
              <div class="rank-badge gold">1</div>
            </div>
            <div class="profile-identity">
              <div class="profile-avatar">MC</div>
              <div class="profile-details">
                <div class="profile-name">Maria Chen</div>
                <div class="profile-id">ID: #CUS-48271</div>
                <div class="profile-tags">
                  <span class="tag vip">VIP Diamond</span>
                  <span class="tag loyal">5+ Years</span>
                </div>
              </div>
            </div>
            <div class="profile-metrics">
              <div class="metric-item">
                <span class="label">Lifetime Value</span>
                <span class="value">₱487,250</span>
              </div>
              <div class="metric-item">
                <span class="label">Monthly Avg</span>
                <span class="value">₱8,450</span>
              </div>
              <div class="metric-item">
                <span class="label">Visit Frequency</span>
                <span class="value">18x/month</span>
              </div>
              <div class="metric-item">
                <span class="label">Last Visit</span>
                <span class="value">2 hours ago</span>
              </div>
            </div>
            <div class="profile-behavior">
              <div class="behavior-chart">
                <svg viewBox="0 0 100 40" class="mini-chart">
                  <polyline points="0,35 10,30 20,25 30,20 40,18 50,15 60,12 70,10 80,8 90,5 100,3" 
                            fill="none" stroke="#05dfd7" stroke-width="2"/>
                </svg>
              </div>
              <div class="behavior-score">
                <span class="score-value">98</span>
                <span class="score-label">Engagement</span>
              </div>
            </div>
            <div class="profile-actions">
              <button class="btn-icon" title="View Details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button class="btn-icon" title="Contact">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="customer-profile-item vip">
            <div class="profile-rank">
              <div class="rank-badge gold">2</div>
            </div>
            <div class="profile-identity">
              <div class="profile-avatar">JR</div>
              <div class="profile-details">
                <div class="profile-name">Jonathan Rodriguez</div>
                <div class="profile-id">ID: #CUS-39482</div>
                <div class="profile-tags">
                  <span class="tag vip">VIP Platinum</span>
                  <span class="tag loyal">3+ Years</span>
                </div>
              </div>
            </div>
            <div class="profile-metrics">
              <div class="metric-item">
                <span class="label">Lifetime Value</span>
                <span class="value">₱398,420</span>
              </div>
              <div class="metric-item">
                <span class="label">Monthly Avg</span>
                <span class="value">₱6,820</span>
              </div>
              <div class="metric-item">
                <span class="label">Visit Frequency</span>
                <span class="value">22x/month</span>
              </div>
              <div class="metric-item">
                <span class="label">Last Visit</span>
                <span class="value">Yesterday</span>
              </div>
            </div>
            <div class="profile-behavior">
              <div class="behavior-chart">
                <svg viewBox="0 0 100 40" class="mini-chart">
                  <polyline points="0,30 15,28 25,22 35,25 45,20 55,18 65,15 75,12 85,10 95,8 100,5" 
                            fill="none" stroke="#088395" stroke-width="2"/>
                </svg>
              </div>
              <div class="behavior-score">
                <span class="score-value">95</span>
                <span class="score-label">Engagement</span>
              </div>
            </div>
            <div class="profile-actions">
              <button class="btn-icon" title="View Details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button class="btn-icon" title="Contact">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="customer-profile-item">
            <div class="profile-rank">
              <div class="rank-badge silver">3</div>
            </div>
            <div class="profile-identity">
              <div class="profile-avatar">SL</div>
              <div class="profile-details">
                <div class="profile-name">Sarah Lim</div>
                <div class="profile-id">ID: #CUS-52819</div>
                <div class="profile-tags">
                  <span class="tag gold">Gold Member</span>
                  <span class="tag">Rising Star</span>
                </div>
              </div>
            </div>
            <div class="profile-metrics">
              <div class="metric-item">
                <span class="label">Lifetime Value</span>
                <span class="value">₱287,150</span>
              </div>
              <div class="metric-item">
                <span class="label">Monthly Avg</span>
                <span class="value">₱4,250</span>
              </div>
              <div class="metric-item">
                <span class="label">Visit Frequency</span>
                <span class="value">15x/month</span>
              </div>
              <div class="metric-item">
                <span class="label">Last Visit</span>
                <span class="value">3 days ago</span>
              </div>
            </div>
            <div class="profile-behavior">
              <div class="behavior-chart">
                <svg viewBox="0 0 100 40" class="mini-chart">
                  <polyline points="0,25 10,22 20,20 30,18 40,20 50,15 60,12 70,10 80,12 90,8 100,5" 
                            fill="none" stroke="#0a4d68" stroke-width="2"/>
                </svg>
              </div>
              <div class="behavior-score">
                <span class="score-value">88</span>
                <span class="score-label">Engagement</span>
              </div>
            </div>
            <div class="profile-actions">
              <button class="btn-icon" title="View Details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button class="btn-icon" title="Contact">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="customer-profile-item">
            <div class="profile-rank">
              <div class="rank-badge">4</div>
            </div>
            <div class="profile-identity">
              <div class="profile-avatar">DT</div>
              <div class="profile-details">
                <div class="profile-name">David Tan</div>
                <div class="profile-id">ID: #CUS-61923</div>
                <div class="profile-tags">
                  <span class="tag gold">Gold Member</span>
                </div>
              </div>
            </div>
            <div class="profile-metrics">
              <div class="metric-item">
                <span class="label">Lifetime Value</span>
                <span class="value">₱198,320</span>
              </div>
              <div class="metric-item">
                <span class="label">Monthly Avg</span>
                <span class="value">₱3,150</span>
              </div>
              <div class="metric-item">
                <span class="label">Visit Frequency</span>
                <span class="value">12x/month</span>
              </div>
              <div class="metric-item">
                <span class="label">Last Visit</span>
                <span class="value">Today</span>
              </div>
            </div>
            <div class="profile-behavior">
              <div class="behavior-chart">
                <svg viewBox="0 0 100 40" class="mini-chart">
                  <polyline points="0,30 10,28 20,25 30,22 40,20 50,18 60,15 70,18 80,12 90,10 100,8" 
                            fill="none" stroke="#05dfd7" stroke-width="2"/>
                </svg>
              </div>
              <div class="behavior-score">
                <span class="score-value">82</span>
                <span class="score-label">Engagement</span>
              </div>
            </div>
            <div class="profile-actions">
              <button class="btn-icon" title="View Details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button class="btn-icon" title="Contact">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="customer-profile-item">
            <div class="profile-rank">
              <div class="rank-badge">5</div>
            </div>
            <div class="profile-identity">
              <div class="profile-avatar">AR</div>
              <div class="profile-details">
                <div class="profile-name">Angela Reyes</div>
                <div class="profile-id">ID: #CUS-71834</div>
                <div class="profile-tags">
                  <span class="tag silver">Silver Member</span>
                </div>
              </div>
            </div>
            <div class="profile-metrics">
              <div class="metric-item">
                <span class="label">Lifetime Value</span>
                <span class="value">₱156,890</span>
              </div>
              <div class="metric-item">
                <span class="label">Monthly Avg</span>
                <span class="value">₱2,840</span>
              </div>
              <div class="metric-item">
                <span class="label">Visit Frequency</span>
                <span class="value">10x/month</span>
              </div>
              <div class="metric-item">
                <span class="label">Last Visit</span>
                <span class="value">5 days ago</span>
              </div>
            </div>
            <div class="profile-behavior">
              <div class="behavior-chart">
                <svg viewBox="0 0 100 40" class="mini-chart">
                  <polyline points="0,35 10,32 20,30 30,28 40,25 50,22 60,20 70,22 80,18 90,15 100,12" 
                            fill="none" stroke="#088395" stroke-width="2"/>
                </svg>
              </div>
              <div class="behavior-score">
                <span class="score-value">75</span>
                <span class="score-label">Engagement</span>
              </div>
            </div>
            <div class="profile-actions">
              <button class="btn-icon" title="View Details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button class="btn-icon" title="Contact">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Advanced Retention & Churn Analytics -->
      <div class="intelligence-card retention-analytics-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper critical">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="8.5" cy="7" r="4"/>
                <line x1="20" y1="8" x2="20" y2="14"/>
                <line x1="23" y1="11" x2="17" y2="11"/>
              </svg>
            </div>
            <h2 class="card-title">Retention & Risk Analytics</h2>
          </div>
          <div class="risk-indicator">
            <span class="indicator-label">System Alert</span>
            <span class="indicator-value critical">47 At Risk</span>
          </div>
        </div>
        
        <div class="retention-matrix">
          <div class="matrix-header">
            <h3>Customer Health Matrix</h3>
            <div class="matrix-legend">
              <span class="legend-item healthy">Healthy</span>
              <span class="legend-item at-risk">At Risk</span>
              <span class="legend-item critical">Critical</span>
            </div>
          </div>
          
          <div class="health-segments">
            <div class="segment-card healthy">
              <div class="segment-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                  <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <span class="segment-title">Healthy Customers</span>
              </div>
              <div class="segment-stats">
                <div class="stat-value">2,847</div>
                <div class="stat-percentage">74%</div>
              </div>
              <div class="segment-details">
                <div class="detail-row">
                  <span>Avg Retention</span>
                  <span>94.2%</span>
                </div>
                <div class="detail-row">
                  <span>NPS Score</span>
                  <span>78</span>
                </div>
              </div>
            </div>
            
            <div class="segment-card at-risk">
              <div class="segment-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span class="segment-title">At Risk</span>
              </div>
              <div class="segment-stats">
                <div class="stat-value">758</div>
                <div class="stat-percentage">20%</div>
              </div>
              <div class="segment-details">
                <div class="detail-row">
                  <span>Churn Probability</span>
                  <span>42%</span>
                </div>
                <div class="detail-row">
                  <span>Last Active</span>
                  <span>15+ days</span>
                </div>
              </div>
            </div>
            
            <div class="segment-card critical">
              <div class="segment-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="8" x2="12" y2="16"/>
                  <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span class="segment-title">Critical</span>
              </div>
              <div class="segment-stats">
                <div class="stat-value">242</div>
                <div class="stat-percentage">6%</div>
              </div>
              <div class="segment-details">
                <div class="detail-row">
                  <span>Churn Probability</span>
                  <span>87%</span>
                </div>
                <div class="detail-row">
                  <span>Immediate Action</span>
                  <span>Required</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="at-risk-customers">
            <h3>High-Value Customers at Risk</h3>
            <div class="risk-list">
              <div class="risk-customer">
                <div class="customer-info">
                  <div class="customer-avatar">RG</div>
                  <div class="customer-details">
                    <div class="customer-name">Robert Garcia</div>
                    <div class="customer-meta">LTV: ₱124,500 | Last: 18 days ago</div>
                  </div>
                </div>
                <div class="risk-level high">87% Risk</div>
                <button class="action-button">Engage</button>
              </div>
              <div class="risk-customer">
                <div class="customer-info">
                  <div class="customer-avatar">LP</div>
                  <div class="customer-details">
                    <div class="customer-name">Lisa Park</div>
                    <div class="customer-meta">LTV: ₱98,200 | Last: 22 days ago</div>
                  </div>
                </div>
                <div class="risk-level high">82% Risk</div>
                <button class="action-button">Engage</button>
              </div>
              <div class="risk-customer">
                <div class="customer-info">
                  <div class="customer-avatar">MK</div>
                  <div class="customer-details">
                    <div class="customer-name">Michael King</div>
                    <div class="customer-meta">LTV: ₱87,600 | Last: 25 days ago</div>
                  </div>
                </div>
                <div class="risk-level medium">68% Risk</div>
                <button class="action-button">Engage</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Purchase Behavior Deep Dive -->
      <div class="intelligence-card purchase-intelligence-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
              </svg>
            </div>
            <h2 class="card-title">Purchase Intelligence & Patterns</h2>
          </div>
        </div>
        
        <div class="purchase-analytics">
          <div class="purchase-overview">
            <div class="overview-stat">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  <line x1="12" y1="8" x2="12" y2="16"/>
                  <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-label">Avg Basket Size</div>
                <div class="stat-value">4.8 items</div>
                <div class="stat-change positive">+12% vs last month</div>
              </div>
            </div>
            
            <div class="overview-stat">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="12" y1="1" x2="12" y2="23"/>
                  <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-label">Avg Transaction</div>
                <div class="stat-value">₱645</div>
                <div class="stat-change positive">+8.7% vs last month</div>
              </div>
            </div>
            
            <div class="overview-stat">
              <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                  <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
              </div>
              <div class="stat-content">
                <div class="stat-label">Upsell Rate</div>
                <div class="stat-value">34.2%</div>
                <div class="stat-change positive">+5.4% vs last month</div>
              </div>
            </div>
          </div>
          
          <div class="product-performance">
            <h3>Top Product Combinations</h3>
            <div class="combo-list">
              <div class="combo-item">
                <div class="combo-rank">1</div>
                <div class="combo-products">
                  <span class="product">Iced Americano</span>
                  <span class="plus">+</span>
                  <span class="product">Chocolate Croissant</span>
                </div>
                <div class="combo-stats">
                  <span class="frequency">847 orders</span>
                  <span class="revenue">₱127,050</span>
                </div>
              </div>
              <div class="combo-item">
                <div class="combo-rank">2</div>
                <div class="combo-products">
                  <span class="product">Cappuccino</span>
                  <span class="plus">+</span>
                  <span class="product">Blueberry Muffin</span>
                </div>
                <div class="combo-stats">
                  <span class="frequency">692 orders</span>
                  <span class="revenue">₱96,880</span>
                </div>
              </div>
              <div class="combo-item">
                <div class="combo-rank">3</div>
                <div class="combo-products">
                  <span class="product">Matcha Latte</span>
                  <span class="plus">+</span>
                  <span class="product">Cheese Danish</span>
                </div>
                <div class="combo-stats">
                  <span class="frequency">534 orders</span>
                  <span class="revenue">₱85,440</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="category-breakdown">
            <h3>Category Performance Matrix</h3>
            <div class="category-grid">
              <div class="category-cell">
                <div class="category-name">Hot Beverages</div>
                <div class="category-metrics">
                  <div class="metric">₱842K</div>
                  <div class="metric-label">Revenue</div>
                  <div class="metric-trend positive">+18%</div>
                </div>
              </div>
              <div class="category-cell">
                <div class="category-name">Cold Beverages</div>
                <div class="category-metrics">
                  <div class="metric">₱756K</div>
                  <div class="metric-label">Revenue</div>
                  <div class="metric-trend positive">+22%</div>
                </div>
              </div>
              <div class="category-cell">
                <div class="category-name">Pastries</div>
                <div class="category-metrics">
                  <div class="metric">₱524K</div>
                  <div class="metric-label">Revenue</div>
                  <div class="metric-trend positive">+15%</div>
                </div>
              </div>
              <div class="category-cell">
                <div class="category-name">Sandwiches</div>
                <div class="category-metrics">
                  <div class="metric">₱358K</div>
                  <div class="metric-label">Revenue</div>
                  <div class="metric-trend positive">+8%</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Advanced Segmentation Engine -->
      <div class="intelligence-card segmentation-engine-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
              </svg>
            </div>
            <h2 class="card-title">Dynamic Customer Segmentation</h2>
          </div>
          <div class="segment-controls">
            <button class="control-btn active">Behavioral</button>
            <button class="control-btn">Value-Based</button>
            <button class="control-btn">Lifecycle</button>
          </div>
        </div>
        
        <div class="segmentation-analysis">
          <div class="segment-distribution">
            <svg viewBox="0 0 400 300" class="distribution-chart">
              <!-- Champions Segment -->
              <rect x="20" y="220" width="60" height="60" rx="4" fill="#05dfd7" opacity="0.8"/>
              <text x="50" y="245" text-anchor="middle" fill="white" font-size="10" font-weight="bold">Champions</text>
              <text x="50" y="260" text-anchor="middle" fill="white" font-size="8">487 (12.6%)</text>
              
              <!-- Loyal Customers -->
              <rect x="90" y="180" width="60" height="100" rx="4" fill="#088395" opacity="0.8"/>
              <text x="120" y="225" text-anchor="middle" fill="white" font-size="10" font-weight="bold">Loyal</text>
              <text x="120" y="240" text-anchor="middle" fill="white" font-size="8">892 (23.2%)</text>
              
              <!-- Potential Loyalists -->
              <rect x="160" y="150" width="60" height="130" rx="4" fill="#0a4d68" opacity="0.8"/>
              <text x="190" y="210" text-anchor="middle" fill="white" font-size="10" font-weight="bold">Potential</text>
              <text x="190" y="225" text-anchor="middle" fill="white" font-size="8">1,245 (32.3%)</text>
              
              <!-- New Customers -->
              <rect x="230" y="200" width="60" height="80" rx="4" fill="#05dfd7" opacity="0.6"/>
              <text x="260" y="235" text-anchor="middle" fill="white" font-size="10" font-weight="bold">New</text>
              <text x="260" y="250" text-anchor="middle" fill="white" font-size="8">674 (17.5%)</text>
              
              <!-- At Risk -->
              <rect x="300" y="210" width="60" height="70" rx="4" fill="#ffb347" opacity="0.8"/>
              <text x="330" y="240" text-anchor="middle" fill="black" font-size="10" font-weight="bold">At Risk</text>
              <text x="330" y="255" text-anchor="middle" fill="black" font-size="8">549 (14.3%)</text>
            </svg>
          </div>
          
          <div class="segment-insights">
            <div class="insight-card">
              <div class="insight-header">
                <span class="insight-icon">💎</span>
                <span class="insight-title">Champions</span>
              </div>
              <div class="insight-metrics">
                <div class="metric-row">
                  <span>Avg LTV</span>
                  <span class="value">₱285,400</span>
                </div>
                <div class="metric-row">
                  <span>Purchase Freq</span>
                  <span class="value">24x/month</span>
                </div>
                <div class="metric-row">
                  <span>Retention</span>
                  <span class="value">98.2%</span>
                </div>
              </div>
              <div class="insight-action">
                <button>VIP Program →</button>
              </div>
            </div>
            
            <div class="insight-card">
              <div class="insight-header">
                <span class="insight-icon">⭐</span>
                <span class="insight-title">Loyal Customers</span>
              </div>
              <div class="insight-metrics">
                <div class="metric-row">
                  <span>Avg LTV</span>
                  <span class="value">₱148,200</span>
                </div>
                <div class="metric-row">
                  <span>Purchase Freq</span>
                  <span class="value">18x/month</span>
                </div>
                <div class="metric-row">
                  <span>Retention</span>
                  <span class="value">92.5%</span>
                </div>
              </div>
              <div class="insight-action">
                <button>Upsell Campaign →</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Customer Journey Mapping -->
      <div class="intelligence-card journey-mapping-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
              </svg>
            </div>
            <h2 class="card-title">Customer Journey Analytics</h2>
          </div>
        </div>
        
        <div class="journey-visualization">
          <div class="journey-stages">
            <div class="stage active">
              <div class="stage-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </div>
              <div class="stage-info">
                <div class="stage-name">Discovery</div>
                <div class="stage-stats">
                  <span class="stat">8,472 visitors</span>
                  <span class="conversion">42% → </span>
                </div>
              </div>
            </div>
            
            <div class="stage-connector"></div>
            
            <div class="stage">
              <div class="stage-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </div>
              <div class="stage-info">
                <div class="stage-name">First Purchase</div>
                <div class="stage-stats">
                  <span class="stat">3,558 customers</span>
                  <span class="conversion">68% →</span>
                </div>
              </div>
            </div>
            
            <div class="stage-connector"></div>
            
            <div class="stage">
              <div class="stage-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                  <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
              </div>
              <div class="stage-info">
                <div class="stage-name">Repeat Customer</div>
                <div class="stage-stats">
                  <span class="stat">2,419 customers</span>
                  <span class="conversion">52% →</span>
                </div>
              </div>
            </div>
            
            <div class="stage-connector"></div>
            
            <div class="stage">
              <div class="stage-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
              </div>
              <div class="stage-info">
                <div class="stage-name">Loyal Advocate</div>
                <div class="stage-stats">
                  <span class="stat">1,258 customers</span>
                  <span class="conversion">Success</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="touchpoint-analysis">
            <h3>Key Touchpoints Performance</h3>
            <div class="touchpoint-grid">
              <div class="touchpoint">
                <span class="touchpoint-channel">In-Store</span>
                <span class="touchpoint-value">78%</span>
              </div>
              <div class="touchpoint">
                <span class="touchpoint-channel">Mobile App</span>
                <span class="touchpoint-value">15%</span>
              </div>
              <div class="touchpoint">
                <span class="touchpoint-channel">Website</span>
                <span class="touchpoint-value">5%</span>
              </div>
              <div class="touchpoint">
                <span class="touchpoint-channel">Social Media</span>
                <span class="touchpoint-value">2%</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- AI-Powered Predictive Analytics -->
      <div class="intelligence-card ai-predictive-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper ai">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2a10 10 0 0 1 10 10c0 5.52-4.48 10-10 10S2 17.52 2 12 6.48 2 12 2z"/>
                <path d="M12 8v8"/>
                <path d="M8 12h8"/>
                <circle cx="12" cy="12" r="2"/>
              </svg>
            </div>
            <h2 class="card-title">AI-Powered Predictions & Insights</h2>
          </div>
          <div class="ai-status">
            <span class="status-dot active"></span>
            <span>ML Models Active</span>
          </div>
        </div>
        
        <div class="ai-predictions">
          <div class="prediction-panel">
            <div class="prediction-header">
              <h3>Next 30 Days Forecast</h3>
              <div class="confidence-badge">94% Confidence</div>
            </div>
            
            <div class="prediction-cards">
              <div class="pred-card">
                <div class="pred-icon revenue">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                  </svg>
                </div>
                <div class="pred-content">
                  <div class="pred-label">Expected Revenue</div>
                  <div class="pred-value">₱2.84M</div>
                  <div class="pred-change positive">+16.2% growth</div>
                </div>
              </div>
              
              <div class="pred-card">
                <div class="pred-icon customers">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                </div>
                <div class="pred-content">
                  <div class="pred-label">New Customers</div>
                  <div class="pred-value">428</div>
                  <div class="pred-change positive">+12.4% acquisition</div>
                </div>
              </div>
              
              <div class="pred-card">
                <div class="pred-icon warning">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                  </svg>
                </div>
                <div class="pred-content">
                  <div class="pred-label">Churn Risk</div>
                  <div class="pred-value">87</div>
                  <div class="pred-change negative">High probability</div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="ai-recommendations">
            <h3>AI Recommendations</h3>
            <div class="recommendation-list">
              <div class="recommendation urgent">
                <div class="rec-priority">Urgent</div>
                <div class="rec-content">
                  <div class="rec-title">Launch retention campaign for 87 high-value customers</div>
                  <div class="rec-impact">Potential save: ₱384,000 in revenue</div>
                </div>
                <button class="rec-action">Execute</button>
              </div>
              
              <div class="recommendation high">
                <div class="rec-priority">High</div>
                <div class="rec-content">
                  <div class="rec-title">Optimize Saturday peak hours staffing (8-10 AM)</div>
                  <div class="rec-impact">Reduce wait time by 35%, increase sales by ₱42K</div>
                </div>
                <button class="rec-action">Review</button>
              </div>
              
              <div class="recommendation medium">
                <div class="rec-priority">Medium</div>
                <div class="rec-content">
                  <div class="rec-title">Introduce combo deal for top 3 product pairs</div>
                  <div class="rec-impact">Projected increase: ₱127K monthly revenue</div>
                </div>
                <button class="rec-action">Analyze</button>
              </div>
            </div>
          </div>
          
          <div class="anomaly-alerts">
            <h3>Detected Anomalies</h3>
            <div class="anomaly-list">
              <div class="anomaly-item">
                <div class="anomaly-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                  </svg>
                </div>
                <span>Unusual 32% drop in Tuesday afternoon traffic vs. historical average</span>
              </div>
              <div class="anomaly-item">
                <div class="anomaly-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                  </svg>
                </div>
                <span>Matcha Latte sales spike 145% above normal - potential viral trend</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Engagement & Activity Heatmap -->
      <div class="intelligence-card engagement-heatmap-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
              </svg>
            </div>
            <h2 class="card-title">Engagement Activity Matrix</h2>
          </div>
        </div>
        
        <div class="heatmap-container">
          <div class="time-heatmap-advanced">
            <h3>Weekly Activity Pattern</h3>
            <div class="heatmap-grid">
              <div class="heatmap-labels-y">
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
                <span>Sun</span>
              </div>
              <div class="heatmap-content">
                <!-- Monday -->
                <div class="heatmap-row">
                  <div class="heat-cell" data-value="12"></div>
                  <div class="heat-cell" data-value="18"></div>
                  <div class="heat-cell" data-value="24"></div>
                  <div class="heat-cell" data-value="32"></div>
                  <div class="heat-cell" data-value="45"></div>
                  <div class="heat-cell" data-value="58"></div>
                  <div class="heat-cell" data-value="72" class="high"></div>
                  <div class="heat-cell" data-value="85" class="high"></div>
                  <div class="heat-cell" data-value="78" class="high"></div>
                  <div class="heat-cell" data-value="65"></div>
                  <div class="heat-cell" data-value="52"></div>
                  <div class="heat-cell" data-value="48"></div>
                  <div class="heat-cell" data-value="42"></div>
                  <div class="heat-cell" data-value="38"></div>
                  <div class="heat-cell" data-value="32"></div>
                  <div class="heat-cell" data-value="28"></div>
                  <div class="heat-cell" data-value="24"></div>
                  <div class="heat-cell" data-value="18"></div>
                </div>
                <!-- Additional rows for other days would follow similar pattern -->
              </div>
              <div class="heatmap-labels-x">
                <span>6AM</span>
                <span>9AM</span>
                <span>12PM</span>
                <span>3PM</span>
                <span>6PM</span>
                <span>9PM</span>
              </div>
            </div>
            <div class="heatmap-legend">
              <span>Low</span>
              <div class="legend-gradient"></div>
              <span>High</span>
            </div>
          </div>
          
          <div class="engagement-metrics">
            <div class="engagement-score-card">
              <div class="score-ring-container">
                <svg viewBox="0 0 200 200" class="engagement-ring">
                  <circle cx="100" cy="100" r="90" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="12"/>
                  <circle cx="100" cy="100" r="90" fill="none" stroke="url(#engagementGradient)" stroke-width="12"
                          stroke-dasharray="565.49" stroke-dashoffset="113.1" stroke-linecap="round" 
                          transform="rotate(-90 100 100)"/>
                  <defs>
                    <linearGradient id="engagementGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                      <stop offset="0%" style="stop-color:#05dfd7"/>
                      <stop offset="100%" style="stop-color:#088395"/>
                    </linearGradient>
                  </defs>
                </svg>
                <div class="score-display">
                  <div class="score-number">82</div>
                  <div class="score-label">Overall Score</div>
                </div>
              </div>
              
              <div class="engagement-breakdown">
                <div class="breakdown-item">
                  <span class="breakdown-label">Daily Active</span>
                  <div class="breakdown-bar">
                    <div class="bar-fill" style="width: 78%"></div>
                  </div>
                  <span class="breakdown-value">1,247</span>
                </div>
                <div class="breakdown-item">
                  <span class="breakdown-label">Weekly Active</span>
                  <div class="breakdown-bar">
                    <div class="bar-fill" style="width: 85%"></div>
                  </div>
                  <span class="breakdown-value">2,854</span>
                </div>
                <div class="breakdown-item">
                  <span class="breakdown-label">Monthly Active</span>
                  <div class="breakdown-bar">
                    <div class="bar-fill" style="width: 92%"></div>
                  </div>
                  <span class="breakdown-value">3,542</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity Feed -->
      <div class="intelligence-card activity-feed-card">
        <div class="card-header-advanced">
          <div class="header-group">
            <div class="header-icon-wrapper">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
            </div>
            <h2 class="card-title">Real-Time Activity Feed</h2>
          </div>
          <div class="feed-status">
            <span class="pulse"></span>
            <span>Live</span>
          </div>
        </div>
        
        <div class="activity-feed">
          <div class="feed-item new">
            <div class="feed-time">Just now</div>
            <div class="feed-content">
              <div class="feed-icon purchase">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="9" cy="21" r="1"/>
                  <circle cx="20" cy="21" r="1"/>
                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
              </div>
              <div class="feed-details">
                <span class="customer-name">Carlos Santos</span> completed purchase - 
                <span class="amount">₱485</span> (Matcha Latte Bundle)
              </div>
            </div>
          </div>
          
          <div class="feed-item">
            <div class="feed-time">2 min ago</div>
            <div class="feed-content">
              <div class="feed-icon milestone">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
              </div>
              <div class="feed-details">
                <span class="customer-name">Jennifer Wu</span> reached VIP Platinum status - 
                <span class="achievement">100th purchase milestone</span>
              </div>
            </div>
          </div>
          
          <div class="feed-item">
            <div class="feed-time">5 min ago</div>
            <div class="feed-content">
              <div class="feed-icon alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                </svg>
              </div>
              <div class="feed-details">
                <span class="alert-type">Churn Alert:</span>
                <span class="customer-name">Patricia Lee</span> - No activity for 21 days
              </div>
            </div>
          </div>
          
          <div class="feed-item">
            <div class="feed-time">8 min ago</div>
            <div class="feed-content">
              <div class="feed-icon review">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                </svg>
              </div>
              <div class="feed-details">
                <span class="customer-name">Mark Rivera</span> left 5-star review - 
                <span class="review">"Best coffee in town!"</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Executive Summary Dashboard -->
    <div class="executive-summary">
      <h2 class="summary-title">Executive Performance Summary</h2>
      <div class="summary-grid">
        <div class="summary-card">
          <div class="summary-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <line x1="3" y1="9" x2="21" y2="9"/>
              <line x1="9" y1="21" x2="9" y2="9"/>
            </svg>
          </div>
          <div class="summary-content">
            <div class="summary-label">Month to Date</div>
            <div class="summary-value">₱1.82M</div>
            <div class="summary-change positive">+22.4% vs target</div>
          </div>
        </div>
        
        <div class="summary-card">
          <div class="summary-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
          </div>
          <div class="summary-content">
            <div class="summary-label">Customer Satisfaction</div>
            <div class="summary-value">4.8/5.0</div>
            <div class="summary-change positive">+0.3 pts</div>
          </div>
        </div>
        
        <div class="summary-card">
          <div class="summary-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
            </svg>
          </div>
          <div class="summary-content">
            <div class="summary-label">Active Members</div>
            <div class="summary-value">3,847</div>
            <div class="summary-change positive">+428 this month</div>
          </div>
        </div>
        
        <div class="summary-card">
          <div class="summary-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
              <polyline points="17 6 23 6 23 12"/>
            </svg>
          </div>
          <div class="summary-content">
            <div class="summary-label">Growth Rate</div>
            <div class="summary-value">24.5%</div>
            <div class="summary-change positive">Above industry avg</div>
          </div>
        </div>
      </div>
    </div>

  </div>

 
  
  
  
  
  
  
  
  

<!-- Customer Insights - Left-aligned, cleaned layout -->
<div id="customer-insights" class="page main-content cgx-scope" aria-labelledby="ci-title" data-module="customer-insights" style="
  font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;
  background:#f6f9fc; color:#1f2937; line-height:1.6;
  min-height:100vh; padding:1.5rem 2rem; margin:0;
">
  <!-- LEFT-ALIGNED CONTAINER -->
  <div style="width:100%; max-width:1800px; margin:0;">

    <!-- Header -->
    <div class="report-header" style="
      display:flex; justify-content:space-between; align-items:center; gap:1rem;
      flex-wrap:wrap;
      margin:0 0 2rem 0; padding:2rem; background:linear-gradient(135deg, #083c52 0%, #0a4d68 50%, #088395 100%); border-radius:1.25rem;
      box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); position:relative; overflow:hidden; color:#ffffff;
    ">
      <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:repeating-linear-gradient(0deg, rgba(255,255,255,0.03) 0px, transparent 1px, transparent 40px, rgba(255,255,255,0.03) 41px), repeating-linear-gradient(90deg, rgba(255,255,255,0.03) 0px, transparent 1px, transparent 40px, rgba(255,255,255,0.03) 41px); pointer-events:none;"></div>
      <div class="header-left" style="flex:1; min-width:260px; position:relative; z-index:1;">
        <h1 class="page-title" id="ci-title" style="font-size:2.5rem; font-weight:700; letter-spacing:-0.025em; margin:0 0 0.25rem 0;">
          Churn Analysis Report
        </h1>
        <p class="last-updated" style="font-size:1rem; font-weight:400; opacity:0.9; letter-spacing:0.05em; margin:0;">
          Last updated: <span id="lastUpdated" style="color:#05dfd7; font-weight:700;">Loading...</span>
        </p>
      </div>
      <div class="header-right" style="display:flex; gap:0.5rem; align-items:center; position:relative; z-index:1;">
        <button class="btn-action" onclick="refreshReports()" style="
          padding:0.75rem 1.5rem; border:none; border-radius:0.5rem; font-size:0.9rem; font-weight:600; cursor:pointer;
          display:inline-flex; align-items:center; gap:0.5rem; letter-spacing:0.025em;
          background:linear-gradient(135deg,#667EEA 0%,#5E72E4 100%); color:#fff; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)';">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <button class="btn-action" onclick="showExportModal()" style="
          padding:0.75rem 1.5rem; border:none; border-radius:0.5rem; font-size:0.9rem; font-weight:600; cursor:pointer;
          display:inline-flex; align-items:center; gap:0.5rem; letter-spacing:0.025em;
          background:linear-gradient(135deg,#10B981 0%,#059669 100%); color:#fff; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)';"
           onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)';">
          <i class="fas fa-download"></i> Export
        </button>
      </div>
    </div>

    <!-- Date Range Selector -->
    <div class="date-controls" style="
      margin:0 0 1.5rem 0; padding:1.5rem; background:#fff; border-radius:1rem;
      box-shadow:0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1); border:1px solid #f0f1f3;
    ">
      <div class="date-range-selector" style="display:flex; flex-wrap:wrap; gap:0.5rem; margin:0 0 1rem 0;">
        <button class="date-btn active" data-range="today" style="
          padding:0.5rem 1rem; border:2px solid transparent; border-radius:0.5rem; font-size:0.9rem; font-weight:600; cursor:pointer;
          background:linear-gradient(135deg,#667EEA 0%,#5E72E4 100%); color:#fff; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        ">Today</button>
        <button class="date-btn" data-range="yesterday" style="
          padding:0.5rem 1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
          font-size:0.9rem; font-weight:600; color:#6b7280; cursor:pointer; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.borderColor='#0a4d68'; this.style.color='#0a4d68';"
           onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#6b7280';">Yesterday</button>
        <button class="date-btn" data-range="7days" style="
          padding:0.5rem 1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
          font-size:0.9rem; font-weight:600; color:#6b7280; cursor:pointer; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.borderColor='#0a4d68'; this.style.color='#0a4d68';"
           onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#6b7280';">Last 7 Days</button>
        <button class="date-btn" data-range="14days" style="
          padding:0.5rem 1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
          font-size:0.9rem; font-weight:600; color:#6b7280; cursor:pointer; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.borderColor='#0a4d68'; this.style.color='#0a4d68';"
           onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#6b7280';">Last 14 Days</button>
        <button class="date-btn" data-range="30days" style="
          padding:0.5rem 1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
          font-size:0.9rem; font-weight:600; color:#6b7280; cursor:pointer; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.borderColor='#0a4d68'; this.style.color='#0a4d68';"
           onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#6b7280';">Last 30 Days</button>
        <button class="date-btn" data-range="custom" style="
          padding:0.5rem 1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
          font-size:0.9rem; font-weight:600; color:#6b7280; cursor:pointer; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.borderColor='#0a4d68'; this.style.color='#0a4d68';"
           onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#6b7280';">Custom Range</button>
      </div>
      <div class="custom-date-inputs" style="
        display:none; align-items:center; gap:1rem; padding:1rem; background:#f3f4f6; border-radius:0.5rem;
      ">
        <input type="date" id="startDate" class="date-input" style="padding:0.5rem 0.75rem; border:2px solid #e5e7eb; border-radius:0.5rem; font-size:0.9rem; font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;">
        <span style="color:#6b7280;">to</span>
        <input type="date" id="endDate" class="date-input" style="padding:0.5rem 0.75rem; border:2px solid #e5e7eb; border-radius:0.5rem; font-size:0.9rem; font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;">
        <button class="btn-apply" onclick="applyCustomRange()" style="
          padding:0.5rem 1.2rem; background:linear-gradient(135deg,#667EEA 0%,#5E72E4 100%);
          color:#fff; border:none; border-radius:0.5rem; font-weight:600; cursor:pointer; letter-spacing:0.025em;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        ">Apply</button>
      </div>
    </div>

   

    <!-- Tabs + Content -->
    <div class="report-section" style="
      background:#fff; border-radius:1rem; padding:1.5rem; margin:0 0 1.5rem 0;
      box-shadow:0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1); border:1px solid #f0f1f3;
    ">
      <div class="tabs" style="
        display:flex; flex-wrap:wrap; gap:0.5rem; margin:0 0 1.5rem 0; border-bottom:2px solid #f0f1f3; padding-bottom:0.5rem;
      ">
        <button class="tab-btn active" onclick="switchTab('retention')" style="
          padding:0.75rem 1.5rem; background:none; border:none; font-weight:600; cursor:pointer;
          color:#0a4d68; border-bottom:3px solid #0a4d68; margin-bottom:-2px; font-size:0.95rem;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        ">Retention Analysis</button>
        <button class="tab-btn" onclick="switchTab('behavior')" style="
          padding:0.75rem 1.5rem; background:none; border:none; font-weight:600; cursor:pointer; color:#6b7280;
          border-bottom:3px solid transparent; margin-bottom:-2px; font-size:0.95rem;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.color='#0a4d68';" onmouseout="this.style.color='#6b7280';">Customer Behavior</button>
        <button class="tab-btn" onclick="switchTab('revenue')" style="
          padding:0.75rem 1.5rem; background:none; border:none; font-weight:600; cursor:pointer; color:#6b7280;
          border-bottom:3px solid transparent; margin-bottom:-2px; font-size:0.95rem;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.color='#0a4d68';" onmouseout="this.style.color='#6b7280';">Revenue Impact</button>
      
        <button class="tab-btn" onclick="switchTab('trends')" style="
          padding:0.75rem 1.5rem; background:none; border:none; font-weight:600; cursor:pointer; color:#6b7280;
          border-bottom:3px solid transparent; margin-bottom:-2px; font-size:0.95rem;
          transition:all 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.color='#0a4d68';" onmouseout="this.style.color='#6b7280';">Risk Level Trends</button>
      </div>

      <!-- Retention -->
      <div class="tab-content active" id="retention-tab" style="display:block;">
        <div class="analysis-grid" style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem; align-items:start;">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="chart-container" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
              <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Retention Trend</h3>
              <canvas id="retentionChart" height="240"></canvas>
            </div>
            <!-- *** NEW: AI SUMMARY CONTAINER *** -->
            <div id="retention-ai-summary" style="background:linear-gradient(135deg, rgba(10,77,104,0.05) 0%, transparent 100%); border:2px solid #088395; border-radius:0.75rem; padding:1.5rem;">
              <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                <div style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #05dfd7, #088395); border-radius:0.5rem; color:#fff;">
                  <i class="fas fa-brain"></i>
                </div>
                <h4 style="font-size:1.125rem; font-weight:700; color:#1f2937; margin:0;">AI Insights</h4>
              </div>
              <div style="font-size:0.95rem; line-height:1.7; color:#6b7280;">
                <p style="margin:0;">Analysis shows a 12% improvement in retention over the last 30 days. Key drivers include increased engagement in loyalty programs and personalized communication strategies.</p>
              </div>
            </div>
          </div>
          <div class="metrics-panel" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
            <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Key Metrics</h3>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0; border-bottom:1px solid #f0f1f3;">
              <span style="font-size:0.9rem; color:#6b7280;">Retention Rate</span>
              <span style="font-size:1.125rem; font-weight:700; color:#1f2937;">87.5%</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0;">
              <span style="font-size:0.9rem; color:#6b7280;">Churn Rate</span>
              <span style="font-size:1.125rem; font-weight:700; color:#1f2937;">12.5%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Behavior -->
      <div class="tab-content" id="behavior-tab" style="display:none;">
        <div class="analysis-grid" style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem;">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="chart-container" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
              <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Transaction Patterns</h3>
              <canvas id="behaviorChart" height="240"></canvas>
            </div>
            <!-- *** NEW: AI SUMMARY CONTAINER *** -->
            <div id="behavior-ai-summary" style="background:linear-gradient(135deg, rgba(10,77,104,0.05) 0%, transparent 100%); border:2px solid #088395; border-radius:0.75rem; padding:1.5rem;">
              <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                <div style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #05dfd7, #088395); border-radius:0.5rem; color:#fff;">
                  <i class="fas fa-brain"></i>
                </div>
                <h4 style="font-size:1.125rem; font-weight:700; color:#1f2937; margin:0;">AI Insights</h4>
              </div>
              <div style="font-size:0.95rem; line-height:1.7; color:#6b7280;">
                <p style="margin:0;">Customer transaction frequency has increased by 18% this month. Peak activity occurs during weekends. Consider targeted promotions during high-engagement periods.</p>
              </div>
            </div>
          </div>
          <div class="metrics-panel" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
            <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Behavior Metrics</h3>
            <div style="display:flex; justify-content:space-between; padding:0.75rem 0; border-bottom:1px solid #f0f1f3;">
              <span style="font-size:0.9rem; color:#6b7280;">Avg Transaction Frequency</span>
              <span id="avgFrequency" style="font-size:1.125rem; font-weight:700; color:#1f2937;">4.2/week</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:0.75rem 0;">
              <span style="font-size:0.9rem; color:#6b7280;">Avg Transaction Value</span>
              <span id="avgValue" style="font-size:1.125rem; font-weight:700; color:#1f2937;">₱1,250</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Revenue -->
      <div class="tab-content" id="revenue-tab" style="display:none;">
        <div class="analysis-grid" style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem;">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="chart-container" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
              <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Revenue Impact Analysis</h3>
              <canvas id="revenueChart" height="240"></canvas>
            </div>
            <!-- *** NEW: AI SUMMARY CONTAINER *** -->
            <div id="revenue-ai-summary" style="background:linear-gradient(135deg, rgba(10,77,104,0.05) 0%, transparent 100%); border:2px solid #088395; border-radius:0.75rem; padding:1.5rem;">
              <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                <div style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #05dfd7, #088395); border-radius:0.5rem; color:#fff;">
                  <i class="fas fa-brain"></i>
                </div>
                <h4 style="font-size:1.125rem; font-weight:700; color:#1f2937; margin:0;">AI Insights</h4>
              </div>
              <div style="font-size:0.95rem; line-height:1.7; color:#6b7280;">
                <p style="margin:0;">Revenue from retained customers is up 24% compared to last quarter. Focus on premium segment customers showing early churn signals to protect high-value revenue streams.</p>
              </div>
            </div>
          </div>
          <div class="metrics-panel" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
            <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Revenue Metrics</h3>
            <div style="display:flex; justify-content:space-between; padding:0.75rem 0; border-bottom:1px solid #f0f1f3;">
              <span style="font-size:0.9rem; color:#6b7280;">Total Revenue</span>
              <span style="font-size:1.125rem; font-weight:700; color:#1f2937;">₱2.4M</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:0.75rem 0;">
              <span style="font-size:0.9rem; color:#6b7280;">At-Risk Revenue</span>
              <span style="font-size:1.125rem; font-weight:700; color:#1f2937;">₱320K</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Trends -->
      <div class="tab-content" id="trends-tab" style="display:none;">
        <div class="trends-container">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="chart-container full-width" style="background:#f3f4f6; padding:1.5rem; border-radius:0.75rem; border:1px solid #f0f1f3;">
              <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">30-Day Churn Risk Trend</h3>
              <canvas id="trendsChart" height="250"></canvas>
            </div>
            <!-- *** NEW: AI SUMMARY CONTAINER *** -->
            <div id="trends-ai-summary" style="background:linear-gradient(135deg, rgba(10,77,104,0.05) 0%, transparent 100%); border:2px solid #088395; border-radius:0.75rem; padding:1.5rem;">
              <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                <div style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #05dfd7, #088395); border-radius:0.5rem; color:#fff;">
                  <i class="fas fa-brain"></i>
                </div>
                <h4 style="font-size:1.125rem; font-weight:700; color:#1f2937; margin:0;">AI Insights</h4>
              </div>
              <div style="font-size:0.95rem; line-height:1.7; color:#6b7280;">
                <p style="margin:0;">Risk trends show stabilization after recent retention campaign. High-risk customer count decreased by 15%. Maintain proactive engagement strategies to sustain this positive trend.</p>
              </div>
            </div>
          </div>
          <div class="comparison-table" style="margin-top:1.5rem;">
            <h3 style="font-size:1.125rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Period Comparison</h3>
            <table id="comparisonTable" style="width:100%; border-collapse:collapse; background:#fff; border-radius:0.75rem; overflow:hidden; box-shadow:0 1px 2px 0 rgba(0,0,0,0.05);">
              <thead style="background:#f3f4f6;">
                <tr>
                  <th style="padding:0.75rem; text-align:left; font-size:0.85rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Metric</th>
                  <th style="padding:0.75rem; text-align:left; font-size:0.85rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Today</th>
                  <th style="padding:0.75rem; text-align:left; font-size:0.85rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Yesterday</th>
                  <th style="padding:0.75rem; text-align:left; font-size:0.85rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">7-Day Avg</th>
                  <th style="padding:0.75rem; text-align:left; font-size:0.85rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">30-Day Avg</th>
                </tr>
              </thead>
              <tbody>
                <tr style="border-bottom:1px solid #f0f1f3; transition:background 150ms cubic-bezier(0.4,0,0.2,1);" onmouseover="this.style.background='#f3f4f6';" onmouseout="this.style.background='';">
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">Active Customers</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">1,245</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">1,238</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">1,220</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">1,195</td>
                </tr>
                <tr style="transition:background 150ms cubic-bezier(0.4,0,0.2,1);" onmouseover="this.style.background='#f3f4f6';" onmouseout="this.style.background='';">
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">High Risk</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">48</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">52</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">55</td>
                  <td style="padding:0.75rem; font-size:0.9rem; color:#1f2937;">62</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>


   

    <!-- Modal -->
    <div class="modal" id="drillDownModal" style="
      display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%;
      background:rgba(0,0,0,.5); backdrop-filter:blur(5px);
    ">
      <div class="modal-content" style="
        position:relative; background:#fff; margin:5% auto; padding:1.4rem; width:92%; max-width:860px;
        border-radius:1rem; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
      ">
        <span class="close" onclick="closeDrillDown()" style="
          position:absolute; right:1rem; top:1rem; font-size:2rem; font-weight:700; color:#9ca3af; cursor:pointer;
          transition:color 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.color='#1f2937';" onmouseout="this.style.color='#9ca3af';">&times;</span>
        <h2 id="modalTitle" style="font-size:1.35rem; font-weight:700; margin:0 0 1rem 0; color:#1f2937;">Risk Segment Details</h2>
        <div id="modalContent"></div>
      </div>
    </div>

    <!-- Export Modal -->
    <div class="modal" id="exportModal" style="
      display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%;
      background:rgba(0,0,0,.5); backdrop-filter:blur(5px);
    ">
      <div class="modal-content" style="
        position:relative; background:#fff; margin:8% auto; padding:2rem; width:90%; max-width:500px;
        border-radius:1rem; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
      ">
        <span class="close" onclick="closeExportModal()" style="
          position:absolute; right:1.2rem; top:1.2rem; font-size:2rem; font-weight:700; color:#9ca3af; cursor:pointer;
          transition:color 150ms cubic-bezier(0.4,0,0.2,1);
        " onmouseover="this.style.color='#1f2937';" onmouseout="this.style.color='#9ca3af';">&times;</span>
        <h2 style="font-size:1.5rem; font-weight:700; margin:0 0 1.5rem 0; color:#1f2937;">Export Report</h2>
        
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; font-weight:700; margin-bottom:.5rem; color:#6b7280; font-size:.9rem;">Export Format</label>
          <div style="display:flex; flex-direction:column; gap:.75rem;">
            <button onclick="exportToPDF()" style="
              padding:1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
              font-size:.95rem; font-weight:700; cursor:pointer; text-align:left;
              display:flex; align-items:center; gap:.8rem; transition:all 250ms cubic-bezier(0.4,0,0.2,1);
            " onmouseover="this.style.borderColor='#0a4d68'; this.style.background='#f3f4f6';"
               onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#fff';">
              <i class="fas fa-file-pdf" style="font-size:1.3rem; color:#DC2626;"></i>
              <div>
                <div style="font-weight:700; color:#1f2937;">PDF Document</div>
                <div style="font-size:.8rem; color:#6b7280;">Export all charts and data as PDF</div>
              </div>
            </button>
            
            <button onclick="exportToImage()" style="
              padding:1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
              font-size:.95rem; font-weight:700; cursor:pointer; text-align:left;
              display:flex; align-items:center; gap:.8rem; transition:all 250ms cubic-bezier(0.4,0,0.2,1);
            " onmouseover="this.style.borderColor='#0a4d68'; this.style.background='#f3f4f6';"
               onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#fff';">
              <i class="fas fa-image" style="font-size:1.3rem; color:#10B981;"></i>
              <div>
                <div style="font-weight:700; color:#1f2937;">PNG Image</div>
                <div style="font-size:.8rem; color:#6b7280;">Export current view as image</div>
              </div>
            </button>
            
            <button onclick="printReport()" style="
              padding:1rem; border:2px solid #e5e7eb; background:#fff; border-radius:0.5rem;
              font-size:.95rem; font-weight:700; cursor:pointer; text-align:left;
              display:flex; align-items:center; gap:.8rem; transition:all 250ms cubic-bezier(0.4,0,0.2,1);
            " onmouseover="this.style.borderColor='#0a4d68'; this.style.background='#f3f4f6';"
               onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#fff';">
              <i class="fas fa-print" style="font-size:1.3rem; color:#5E72E4;"></i>
              <div>
                <div style="font-weight:700; color:#1f2937;">Print Report</div>
                <div style="font-size:.8rem; color:#6b7280;">Print-friendly version</div>
              </div>
            </button>
          </div>
        </div>

        <div style="padding-top:1rem; border-top:1px solid #e5e7eb;">
          <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer; font-size:.9rem; color:#6b7280;">
            <input type="checkbox" id="includeAllTabs" checked style="width:18px; height:18px; cursor:pointer;">
            <span>Include all tabs in export</span>
          </label>
        </div>
      </div>
    </div>
  </div>



    <!-- Required Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="churn-report.js"></script>
    
    <!-- *** NEW: AI SUMMARY SCRIPT *** -->
    <script src="ai-chart-summary.js"></script>

  </div>
  
  <!-- Print Styles -->
  <style>
    @media print {
      body * { visibility: hidden; }
      .print-container, .print-container * { visibility: visible; }
      .print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
      .no-print { display: none !important; }
      .page-break { page-break-after: always; }
    }
  </style>
  




<script>
// ChurnGuard Dashboard — accurate Executive Summary, PH currency, hardened UI
// WITH AI SUMMARY INTEGRATION

let cgx_charts = {};
let cgx_currentView = '14days';
let cgx_data = null;
let cgx_debugMode = localStorage.getItem('cgx_debug') === '1';

document.addEventListener('DOMContentLoaded', () => {
  cgx_log('Booting...');
  cgx_initializeReports();
  cgx_setupEventListeners();
  cgx_loadData('14days');
  cgx_setupDiagnostics();
});

function cgx_log(msg, data=null){ if (cgx_debugMode) console.log(`[CGX ${new Date().toISOString()}] ${msg}`, data ?? ''); }

function cgx_initializeReports(){
  if (typeof Chart !== 'undefined'){
    Chart.defaults.font.family = 'Inter, -apple-system, BlinkMacSystemFont, sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    cgx_log('Chart.js ready');
  }
}

function cgx_setupEventListeners(){
  document.querySelectorAll('.date-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('.date-btn').forEach(b=>{
        b.classList.remove('active'); b.style.background='#fff'; b.style.color='#2f3640';
      });
      btn.classList.add('active');
      btn.style.background='linear-gradient(135deg,#667EEA 0%,#5E72E4 100%)';
      btn.style.color='#fff';

      const customInputs = document.querySelector('.custom-date-inputs');
      if (btn.dataset.range === 'custom'){
        if (customInputs) customInputs.style.display='flex';
      } else {
        if (customInputs) customInputs.style.display='none';
        cgx_loadData(btn.dataset.range);
      }
    });
  });
}

async function cgx_loadData(view){
  try{
    cgx_currentView = view;
    cgx_showLoading();
    const res = await fetch(`data_endpoint.php?view=${encodeURIComponent(view)}`, {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    cgx_data = data;

    if (!data?.data_availability?.has_data){
      cgx_showNoDataMessage(data?.data_availability ?? {days_with_data:0,total_days:0,coverage_percent:0});
      cgx_updateHealthStatus('success', data.timestamp);
      return;
    }

    cgx_populateExecutiveSummary(data.executive_summary);
    cgx_populateRetentionMetrics(data.retention_metrics);
    cgx_populateBehaviorMetrics(data.behavior_metrics);
    cgx_populateRevenueImpact(data.revenue_impact);
    cgx_populateSegments(data.segments);
    cgx_updateCharts(data.trends);
    cgx_updateComparisonTable(data.period_comparison);

    const lastUpdatedEl = document.getElementById('lastUpdated');
    if (lastUpdatedEl) lastUpdatedEl.textContent = data.last_updated;

    // Clear any leftover "Loading..." opacity
    ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate'].forEach(id=>{
      const el = document.getElementById(id);
      if (el) el.style.opacity = '1';
    });

    cgx_updateHealthStatus('success', data.timestamp);
  } catch (err){
    cgx_log('Load error', err);
    cgx_showError(err.message);
    cgx_updateHealthStatus('error');
  }
}

function cgx_showLoading(){
  ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate','currentRetention','churnRate','highRiskCount'].forEach(id=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = 'Loading...';
    el.style.opacity = '0.6';
  });
}

function cgx_showError(message='Failed to load data'){
  const ids = ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate'];
  ids.forEach(id=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = 'Error';
    el.style.color = '#F5365C';
    el.style.opacity = '1';
  });
  const desc = document.getElementById('riskDescription');
  if (desc){ desc.textContent = `Error: ${message}`; desc.style.color = '#F5365C'; }
}

function cgx_showNoDataMessage(availability){
  const msg = `No data found for selected period. ${availability.days_with_data} of ${availability.total_days} days have data (${availability.coverage_percent}% coverage)`;
  const rl = document.getElementById('riskLevel');
  if (rl){ rl.textContent='No Data'; rl.style.color='#6b7280'; }
  const rd = document.getElementById('riskDescription');
  if (rd){ rd.textContent=msg; rd.style.color='#6b7280'; }

  const zeros = {
    atRiskCount:'0', atRiskChange:'0.0%', revenueAtRisk:'₱0', revenueChange:'0.0%',
    retentionRate:'100%', retentionChange:'0.0%',
    currentRetention:'0%', churnRate:'0%',
    wowChange:'0.0%', highRiskCount:'0', mediumRiskCount:'0'
  };
  Object.entries(zeros).forEach(([id,val])=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = val; el.style.opacity='0.5';
  });
}

function cgx_populateExecutiveSummary(d){
  const riskEl = document.getElementById('riskLevel');
  if (riskEl){
    const lvl = d?.risk_level || 'Low';
    riskEl.textContent = lvl;
    riskEl.style.color = cgx_getRiskColor(lvl);
    riskEl.style.opacity = '1';
  }
  const riskDescEl = document.getElementById('riskDescription');
  if (riskDescEl){
    riskDescEl.textContent = d?.risk_description || 'Stable customer base with low churn risk';
    riskDescEl.style.color = '#6b7280';
    riskDescEl.style.opacity = '1';
  }

  const num = (v, def=0) => (Number.isFinite(+v) ? +v : def);
  const ar  = Math.max(0, num(d?.at_risk_customers, 0));
  const arc = num(d?.at_risk_change, 0);
  const rar = Math.max(0, num(d?.revenue_at_risk, 0));
  const rc  = num(d?.revenue_change, 0);
  let rr    = num(d?.retention_rate, 0);
  let rrc   = num(d?.retention_change, 0);

  rr = Math.min(100, Math.max(0, rr));

  cgx_setElementValue('atRiskCount', cgx_formatNumber(ar));
  cgx_setChangeValue('atRiskChange', arc, true);

  cgx_setElementValue('revenueAtRisk', cgx_formatCurrencyPH(rar));
  cgx_setChangeValue('revenueChange', rc, false);

  cgx_setElementValue('retentionRate', `${cgx_formatDecimal(rr)}%`);
  cgx_setChangeValue('retentionChange', rrc, false);

  ['riskLevel','riskDescription','atRiskCount','revenueAtRisk','retentionRate'].forEach(id=>{
    const el = document.getElementById(id);
    if (el) el.style.opacity = '1';
  });
}

function cgx_populateRetentionMetrics(data){
  cgx_setElementValue('currentRetention', `${cgx_formatDecimal(data.current_retention || 0)}%`);
  cgx_setElementValue('churnRate', `${cgx_formatDecimal(data.churn_rate || 0)}%`);
  cgx_setChangeValue('wowChange', data.wow_change || 0);
  cgx_setElementValue('highRiskCount', cgx_formatNumber(data.high_risk_count || 0));
  cgx_setElementValue('mediumRiskCount', cgx_formatNumber(data.medium_risk_count || 0));
}

function cgx_populateBehaviorMetrics(data){
  cgx_setElementValue('avgFrequency', `${cgx_formatNumber(data.avg_frequency || 0)} per day`);
  cgx_setElementValue('avgValue', cgx_formatCurrencyPH(data.avg_value || 0));
  cgx_setElementValue('loyaltyRate', `${cgx_formatDecimal(data.loyalty_rate || 0)}%`);
  cgx_setElementValue('engagementScore', `${cgx_formatNumber(data.engagement_score || 0)}/100`);
}

function cgx_populateRevenueImpact(data){
  cgx_setElementValue('potentialLoss', cgx_formatCurrencyPH(data.potential_loss || 0));
  cgx_setElementValue('revenueSaved', cgx_formatCurrencyPH(data.revenue_saved || 0));
}

function cgx_populateSegments(segments){
  cgx_setElementValue('highRiskSegCount', cgx_formatNumber(segments.High?.count || 0));
  cgx_setElementValue('highRiskRevenue', cgx_formatCurrencyPH(segments.High?.revenue || 0));
  cgx_setElementValue('highRiskScore', `${Math.round(segments.High?.score || 0)}%`);

  cgx_setElementValue('mediumRiskSegCount', cgx_formatNumber(segments.Medium?.count || 0));
  cgx_setElementValue('mediumRiskRevenue', cgx_formatCurrencyPH(segments.Medium?.revenue || 0));
  cgx_setElementValue('mediumRiskScore', `${Math.round(segments.Medium?.score || 0)}%`);

  cgx_setElementValue('lowRiskSegCount', cgx_formatNumber(segments.Low?.count || 0));
  cgx_setElementValue('lowRiskRevenue', cgx_formatCurrencyPH(segments.Low?.revenue || 0));
  cgx_setElementValue('lowRiskScore', `${Math.round(segments.Low?.score || 0)}%`);
}

function cgx_updateCharts(trends){
  if (!Array.isArray(trends) || trends.length === 0){ cgx_log('No trend data'); return; }

  const labels = trends.map(t=>{
    const d = new Date(t.date);
    const lbl = d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
    return (parseInt(t.is_gap) === 1) ? `${lbl} (No data)` : lbl;
  });
  const riskData    = trends.map(t => +t.risk_percentage || 0);
  const revenueData = trends.map(t => +t.sales_volume || 0);
  const receiptData = trends.map(t => parseInt(t.receipt_count) || 0);

  const bgA = trends.map(t => (parseInt(t.is_gap)===1)?'rgba(107,114,128,0.1)':'rgba(94,114,228,0.1)');
  const bdA = trends.map(t => (parseInt(t.is_gap)===1)?'rgba(107,114,128,0.5)':'#5E72E4');

  // *** RETENTION CHART ***
  cgx_createChart('retentionChart', {
    type:'line',
    data:{
      labels,
      datasets:[{
        label:'Retention Rate %',
        data: riskData.map(r=>100-r),
        borderColor: bdA,
        backgroundColor: bgA,
        tension:0.3,
        pointBackgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#5E72E4'),
        pointRadius: trends.map(t=>(parseInt(t.is_gap)===1)?4:3),
        segment:{ borderDash: ctx => (parseInt(trends[ctx.p1DataIndex]?.is_gap)===1 ? [5,5] : undefined) }
      }]
    }
  }, 'retention'); // ← ADD THIS PARAMETER

  // *** BEHAVIOR CHART ***
  cgx_createChart('behaviorChart', {
    type:'bar',
    data:{
      labels,
      datasets:[{
        label:'Transactions',
        data: receiptData,
        backgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'rgba(107,114,128,0.3)':'#5E72E4'),
        borderColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#5E72E4'),
        borderWidth:1
      }]
    }
  }, 'behavior'); // ← ADD THIS PARAMETER

  // *** REVENUE CHART ***
  cgx_createChart('revenueChart', {
    type:'line',
    data:{
      labels,
      datasets:[{
        label:'Revenue',
        data: revenueData,
        borderColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#2DCE89'),
        backgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'rgba(107,114,128,0.1)':'rgba(45,206,137,0.1)'),
        tension:0.3,
        pointBackgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#2DCE89'),
        segment:{ borderDash: ctx => (parseInt(trends[ctx.p1DataIndex]?.is_gap)===1 ? [5,5] : undefined) }
      }]
    }
  }, 'revenue'); // ← ADD THIS PARAMETER

  // *** TRENDS CHART ***
  cgx_createChart('trendsChart', {
    type:'line',
    data:{
      labels,
      datasets:[{
        label:'Risk Score %',
        data: riskData,
        borderColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#F5365C'),
        backgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'rgba(107,114,128,0.1)':'rgba(245,54,92,0.1)'),
        tension:0.3,
        pointBackgroundColor: trends.map(t=>(parseInt(t.is_gap)===1)?'#6b7280':'#F5365C'),
        segment:{ borderDash: ctx => (parseInt(trends[ctx.p1DataIndex]?.is_gap)===1 ? [5,5] : undefined) }
      }]
    },
    options:{
      plugins:{
        tooltip:{ callbacks:{ afterLabel: (ctx)=>(parseInt(trends[ctx.dataIndex]?.is_gap)===1?'No data available for this date':'') } }
      }
    }
  }, 'trends'); // ← ADD THIS PARAMETER

  cgx_log('Charts updated', {points: trends.length});
}

// *** MODIFIED cgx_createChart FUNCTION - THIS IS THE KEY FIX ***
// *** REPLACE YOUR EXISTING cgx_createChart FUNCTION WITH THIS ***
function cgx_createChart(canvasId, config, chartType){
  const canvas = document.getElementById(canvasId);
  if (!canvas){ 
    cgx_log(`Canvas not found: ${canvasId}`); 
    return; 
  }
  const ctx = canvas.getContext('2d');
  
  // Destroy old chart
  if (cgx_charts[canvasId]) {
    cgx_charts[canvasId].destroy();
  }

  const defaults = {
    responsive:true,
    maintainAspectRatio:false,
    interaction:{ intersect:false, mode:'index' },
    plugins:{
      legend:{ 
        display:true, 
        position:'top', 
        labels:{ 
          usePointStyle:true, 
          padding:15, 
          font:{ size:12, weight:'600' } 
        } 
      },
      tooltip:{ 
        backgroundColor:'rgba(0,0,0,0.8)', 
        titleFont:{ size:13, weight:'600' }, 
        bodyFont:{ size:12 }, 
        padding:10, 
        cornerRadius:6 
      }
    },
    scales:{
      y:{ 
        beginAtZero:true, 
        grid:{ color:'rgba(0,0,0,0.05)', drawBorder:false }, 
        ticks:{ font:{ size:11 } } 
      },
      x:{ 
        grid:{ display:false, drawBorder:false }, 
        ticks:{ font:{ size:11 } } 
      }
    }
  };
  
  config.options = { ...defaults, ...(config.options || {}) };
  
  // Create chart
  const chartInstance = new Chart(ctx, config);
  cgx_charts[canvasId] = chartInstance;
  
  cgx_log(`Chart created: ${canvasId}`, { type: chartType || 'unknown' });
  
  // *** KEY FIX: Store chart in global scope for AI module ***
  if (chartType) {
    window[`${chartType}ChartInstance`] = chartInstance;
    cgx_log(`Chart stored globally: ${chartType}ChartInstance`);
    
    // *** Trigger AI Summary Generation ***
    if (typeof AIChartSummary !== 'undefined') {
      setTimeout(() => {
        cgx_log(`Triggering AI summary for: ${chartType}`);
        const container = document.getElementById(`${chartType}-ai-summary`);
        if (container) {
          AIChartSummary.generateSummaryForChart(
            chartInstance,
            chartType,
            `${chartType}-ai-summary`
          );
        } else {
          cgx_log(`AI summary container not found: ${chartType}-ai-summary`);
        }
      }, 1000);
    } else {
      cgx_log('AIChartSummary module not loaded yet');
    }
  }
}

function cgx_updateComparisonTable(d){
  const tbody = document.querySelector('#comparisonTable tbody');
  if (!tbody) return;
  tbody.innerHTML = `
    <tr>
      <td style="padding:.75rem;font-weight:600;">Revenue</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.today.revenue)}</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.yesterday.revenue)}</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.avg_7day.revenue)}</td>
      <td style="padding:.75rem;">${cgx_formatCurrencyPH(d.avg_30day.revenue)}</td>
    </tr>
    <tr style="background:#F6F9FC;">
      <td style="padding:.75rem;font-weight:600;">Customers</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.today.customers)}</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.yesterday.customers)}</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.avg_7day.customers)}</td>
      <td style="padding:.75rem;">${cgx_formatNumber(d.avg_30day.customers)}</td>
    </tr>
    <tr>
      <td style="padding:.75rem;font-weight:600;">Risk Score</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.today.risk_score)}%</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.yesterday.risk_score)}%</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.avg_7day.risk_score)}%</td>
      <td style="padding:.75rem;">${cgx_formatDecimal(d.avg_30day.risk_score)}%</td>
    </tr>
  `;
}

// --- Utilities ---
function cgx_setElementValue(id, v){ const el=document.getElementById(id); if(el){ el.textContent=v; el.style.opacity='1'; } }
function cgx_setChangeValue(id, value, inverse=false){
  const el = document.getElementById(id); if (!el) return;
  const num = parseFloat(value) || 0;
  const sign = num > 0 ? '+' : '';
  el.textContent = `${sign}${cgx_formatDecimal(num)}%`;
  el.style.color = inverse ? (num > 0 ? '#F5365C' : '#2DCE89') : (num < 0 ? '#F5365C' : '#2DCE89');
}
function cgx_formatNumber(n){ const v=+n || 0; return new Intl.NumberFormat('en-US',{maximumFractionDigits:0}).format(Math.round(v)); }
function cgx_formatDecimal(n, d=1){ const v=+n || 0; return v.toFixed(d); }
function cgx_formatCurrencyPH(amount){
  const v = parseFloat(amount) || 0;
  if (v === 0) return '₱0';
  return new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',minimumFractionDigits:0,maximumFractionDigits:0}).format(Math.round(v));
}
function cgx_getRiskColor(level){
  switch(level){
    case 'High': return '#F5365C';
    case 'Medium': return '#FB6340';
    case 'Low': return '#2DCE89';
    default: return '#5E72E4';
  }
}

// --- Global actions ---
function refreshReports(){ cgx_loadData(cgx_currentView); }
function exportReport(fmt){ alert(`Export to ${fmt.toUpperCase()} — coming soon`); }

function switchTab(tabName){
  document.querySelectorAll('.tab-content').forEach(t => t.style.display='none');
  const sel = document.getElementById(`${tabName}-tab`); if (sel) sel.style.display='block';
  document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.classList.remove('active'); btn.style.color='#6b7280'; btn.style.borderBottom='none'; btn.style.marginBottom='0';
  });
  const match = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.textContent.trim().toLowerCase().includes(tabName.toLowerCase()));
  if (match){ match.classList.add('active'); match.style.color='#5E72E4'; match.style.borderBottom='3px solid #5E72E4'; match.style.marginBottom='-2px'; }
  
  // *** Trigger AI summary when switching tabs ***
  if (typeof AIChartSummary !== 'undefined') {
    setTimeout(() => {
      const chartInstance = window[`${tabName}ChartInstance`];
      const container = document.getElementById(`${tabName}-ai-summary`);
      
      if (chartInstance && chartInstance.data && container) {
        // Check if summary already generated
        const hasContent = container.querySelector('.ai-summary-content');
        const isLoading = container.querySelector('.ai-summary-loading');
        
        if (!hasContent || isLoading) {
          cgx_log(`Generating AI summary for tab: ${tabName}`);
          AIChartSummary.generateSummaryForChart(
            chartInstance,
            tabName,
            `${tabName}-ai-summary`
          );
        }
      }
    }, 1000);
  }
}

function drillDown(riskLevel){
  const modal=document.getElementById('drillDownModal');
  const title=document.getElementById('modalTitle');
  const content=document.getElementById('modalContent');
  if (!cgx_data?.segments) return;

  const key = riskLevel.charAt(0).toUpperCase() + riskLevel.slice(1);
  const seg = cgx_data.segments[key] || {count:0,revenue:0,score:0};

  if (title) title.textContent = `${key} Risk Customers`;
  if (content){
    content.innerHTML = `
      <div style="padding:1rem;">
        <p style="font-size:1.2rem;margin-bottom:1rem;"><strong>Total Customers:</strong> ${cgx_formatNumber(seg.count)}</p>
        <p style="font-size:1.2rem;margin-bottom:1rem;"><strong>Revenue Impact:</strong> ${cgx_formatCurrencyPH(seg.revenue)}</p>
        <p style="font-size:1.2rem;margin-bottom:1rem;"><strong>Average Risk Score:</strong> ${Math.round(seg.score || 0)}%</p>
        <div style="margin-top:2rem;padding:1rem;background:#F6F9FC;border-radius:.5rem;">
          <p style="color:#6b7280;font-size:.9rem;margin:0;"><strong>Note:</strong>
          ${cgx_data.data_availability?.has_data ? `Data coverage: ${cgx_data.data_availability.coverage_percent}% of selected period` : 'No data available for this period'}</p>
        </div>
      </div>`;
  }
  if (modal) modal.style.display='block';
}
function closeDrillDown(){ const m=document.getElementById('drillDownModal'); if (m) m.style.display='none'; }

function applyCustomRange(){
  const s = document.getElementById('startDate')?.value;
  const e = document.getElementById('endDate')?.value;
  if (!s || !e){ alert('Please select both start and end dates'); return; }
  cgx_loadData('30days');
}

// --- Diagnostics ---
function cgx_setupDiagnostics(){
  const health=document.createElement('div');
  health.id='cgx_health';
  health.style.cssText = `
    position:fixed; bottom:10px; right:10px; padding:5px 10px;
    background:rgba(0,0,0,0.7); color:#fff; font-size:11px; border-radius:4px;
    display:${cgx_debugMode ? 'block' : 'none'}; z-index:10000;
  `;
  document.body.appendChild(health);
  window.cgx_toggleDebug=function(){
    cgx_debugMode=!cgx_debugMode;
    localStorage.setItem('cgx_debug', cgx_debugMode ? '1' : '0');
    document.getElementById('cgx_health').style.display = cgx_debugMode ? 'block' : 'none';
    console.log(`ChurnGuard debug mode: ${cgx_debugMode ? 'ON' : 'OFF'}`);
  };
}

function cgx_updateHealthStatus(status, ts=null){
  const el=document.getElementById('cgx_health'); if (!el) return;
  const time = ts || new Date().toLocaleString('en-US',{timeZone:'Asia/Manila'});
  const colors = { success:'#2DCE89', error:'#F5365C', loading:'#FB6340' };
  el.style.backgroundColor = colors[status] || 'rgba(0,0,0,0.7)';
  el.textContent = `CGX: ${status} | ${time}`;
}

window.onclick = function(ev){
  const m = document.getElementById('drillDownModal');
  if (ev.target === m) m.style.display='none';
};

cgx_log('Ready', {tz: Intl.DateTimeFormat().resolvedOptions().timeZone, debug: cgx_debugMode});
</script>




 
 


<div id="customer-monitoring" class="page">
  <div class="alert-banner" id="riskAlertBanner">
    <div class="alert-icon">⚠️</div>
    <span id="riskAlertMessage">High churn risk detected! Immediate action recommended.</span>
    <button class="alert-close" onclick="dismissRiskAlert()">×</button>
  </div>

  <div class="dashboard-header">
    <div class="header-content">
      <div>
        <div class="header-title">
          <div class="header-icon">📊</div>
          <div>
            <h1>Customer Monitoring Dashboard</h1>
            <div class="header-subtitle">Real-time churn prediction and customer retention analytics</div>
          </div>
        </div>
      </div>
      <div class="header-controls">
        <div class="status-indicator">
          <div class="status-dot"></div>

        </div>
      </div>
    </div>
  </div>

  <!-- Make content flow vertically so metrics sit BELOW the chart -->
  <div class="main-content" style="display:block; max-width:1400px; margin:0 auto; padding:0 1.25rem 1.25rem;">
    <div class="chart-section">
      <div class="chart-header">
        <div class="chart-title">📈 Customer Traffic & Churn Analytics</div>
        <div class="date-picker-container">
          <div class="date-picker">
            <div class="date-picker-input" onclick="toggleChartDatePicker()">
              <span id="selectedChartDateRange">Last 14 Days</span>
              <span>▼</span>
            </div>
            <div class="date-picker-dropdown" id="chartDatePickerDropdown">
              <div class="date-option" data-value="today">
                <span>Today</span>
                <span class="date-option-range">Current day</span>
              </div>
              <div class="date-option" data-value="7days">
                <span>Last 7 Days</span>
                <span class="date-option-range">Week overview</span>
              </div>
              <div class="date-option active" data-value="14days">
                <span>Last 14 Days</span>
                <span class="date-option-range">2-week trend</span>
              </div>
            </div>
          </div>
          <button class="refresh-btn" onclick="refreshDashboardData()">
            <span>🔄</span>
            <span>Refresh</span>
          </button>
        </div>
      </div>
      <div class="chart-container">
        <div class="chart-loading" id="chartLoadingIndicator">
          <div class="loading-spinner"></div>
        </div>
        <div class="chart-canvas">
          <canvas id="trafficChurnChart" width="800" height="400"></canvas>
          <div class="chart-tooltip" id="chartTooltipDisplay"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="history-section">
    <div class="history-header">
      <div class="history-title">📋Historical Data</div>
      
      <!-- Toggle Switch -->
      <div class="data-view-toggle">
        <label class="toggle-switch">
          <input type="checkbox" id="dataViewToggle" onchange="toggleDataView()">
          <span class="toggle-slider"></span>
        </label>
        <span id="dataViewLabel" class="toggle-label">Transaction Logs View</span>
      </div>
      
      <!-- NEW: Date Filter Dropdown (only shows for transaction logs) -->
      <div id="transactionDateFilter" class="date-filter-container" style="display: none;">
        <select id="dateFilterSelect" class="date-filter-select" onchange="applyDateFilter()">
          <option value="all">All Time</option>
          <option value="7">Past 7 Days</option>
          <option value="14">Past 14 Days</option>
          <option value="30" selected>Past 30 Days</option>
          <option value="custom">Custom Range</option>
        </select>
        
        <!-- Custom date inputs (hidden by default) -->
        <div id="customDateInputs" style="display: none; margin-left: 10px;">
          <input type="date" id="startDate" class="date-input">
          <span style="margin: 0 5px;">to</span>
          <input type="date" id="endDate" class="date-input">
          <button onclick="applyCustomDateFilter()" class="btn-apply-filter">Apply</button>
        </div>
      </div>
      
      <div class="last-updated">
         <span id="currentAnalysisDataRange"></span>
      </div>
    </div>
    
    <div class="history-table-container">
      <table class="history-table">
        <thead id="historyTableHead">
          <tr>
            <th>Date</th>
            <th>Customer Traffic</th>
            <th>Revenue</th>
            <th>Transactions</th>
            <th>Risk Level</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="historicalAnalysisTableBody">
          <tr>
            <td colspan="6" class="no-data">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>
</div>
  
  
</div>


		 <!-- Include the monitoring JavaScript -->
    <script src="customer-monitoring-dashboard.js"></script>
	<link rel="stylesheet" href="assets/monitoring.css"><!-- use YOUR provided CSS file -->
 

   

   
  <script>
// ========== TRANSACTION LOGS WITH DATE FILTER ==========

let currentDataView = 'transactions';
let originalTableHeaders = null;
let currentDateFilter = '30'; // Default: Past 30 days

// Toggle between views
function toggleDataView() {
  const toggle = document.getElementById('dataViewToggle');
  const label = document.getElementById('dataViewLabel');
  const filterContainer = document.getElementById('transactionDateFilter');
  
  if (toggle.checked) {
    // Aggregated View
    currentDataView = 'aggregated';
    label.textContent = 'Aggregated View';
    filterContainer.style.display = 'none'; // Hide filter
    restoreAggregatedView();
  } else {
    // Transaction Logs View
    currentDataView = 'transactions';
    label.textContent = 'Transaction Logs View';
    filterContainer.style.display = 'flex'; // Show filter
    loadTransactionLogs();
  }
}

// Apply date filter
function applyDateFilter() {
  const select = document.getElementById('dateFilterSelect');
  const customInputs = document.getElementById('customDateInputs');
  
  currentDateFilter = select.value;
  
  if (currentDateFilter === 'custom') {
    // Show custom date inputs
    customInputs.style.display = 'flex';
    
    // Set default dates
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    
    document.getElementById('endDate').valueAsDate = endDate;
    document.getElementById('startDate').valueAsDate = startDate;
  } else {
    // Hide custom inputs and reload
    customInputs.style.display = 'none';
    loadTransactionLogs();
  }
}

// Apply custom date filter
function applyCustomDateFilter() {
  const startDate = document.getElementById('startDate').value;
  const endDate = document.getElementById('endDate').value;
  
  if (!startDate || !endDate) {
    alert('Please select both start and end dates');
    return;
  }
  
  if (new Date(startDate) > new Date(endDate)) {
    alert('Start date must be before end date');
    return;
  }
  
  loadTransactionLogs(startDate, endDate);
}

// Restore aggregated view
function restoreAggregatedView() {
  const tableHead = document.getElementById('historyTableHead');
  const tableBody = document.getElementById('historicalAnalysisTableBody');
  
  tableHead.innerHTML = `
    <tr>
      <th>Date</th>
      <th>Customer Traffic</th>
      <th>Revenue</th>
      <th>Transactions</th>
      <th>Risk Level</th>
      <th>Status</th>
    </tr>
  `;
  
  tableBody.innerHTML = '<tr><td colspan="6" class="no-data">Loading...</td></tr>';
  
  if (dashboard) {
    dashboard.startAutoRefresh();
    dashboard.loadData();
  }
}

// Load transaction logs with filter
async function loadTransactionLogs(customStart = null, customEnd = null) {
  const tableHead = document.getElementById('historyTableHead');
  const tableBody = document.getElementById('historicalAnalysisTableBody');
  
  console.log('=== LOADING TRANSACTION LOGS ===');
  console.log('Filter:', currentDateFilter);
  
  if (dashboard) {
    dashboard.stopAutoRefresh();
  }
  
  tableBody.innerHTML = '<tr><td colspan="11" class="no-data">Loading transaction logs...</td></tr>';
  
  try {
    // Build URL with filter parameters
    let url = 'api/get_transaction_logs.php?t=' + Date.now();
    
    if (customStart && customEnd) {
      url += `&filter=custom&start_date=${customStart}&end_date=${customEnd}`;
    } else {
      url += `&filter=${currentDateFilter}`;
    }
    
    console.log('Fetching:', url);
    
    const response = await fetch(url, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    console.log('Data received:', data);
    
    if (!data.success) {
      throw new Error(data.message || 'Failed to load');
    }
    
    // Update headers
    tableHead.innerHTML = `
      <tr>
        
        <th>Date</th>
       
        <th>Customer</th>
        <th>Drink</th>
        <th>Qty</th>
        <th>Receipts</th>
        <th>Time</th>
        <th>Amount</th>
        <th>Payment</th>
      
      </tr>
    `;
    
    if (!data.transactions || data.transactions.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="11" class="no-data">No transactions found for selected date range</td></tr>';
      
      const label = document.getElementById('currentAnalysisDataRange');
      if (label) {
        label.textContent = `${data.date_range_text || 'No data'}`;
      }
      return;
    }
    
    // Build rows
    let rows = '';
    data.transactions.forEach(trans => {
      const date = new Date(trans.date_visited);
      const dateStr = date.toLocaleDateString('en-PH', { 
        year: 'numeric', month: 'short', day: 'numeric'
      });
      const amount = parseFloat(trans.total_amount || 0);
      const amountStr = '₱' + amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      
      rows += `
        <tr>
         
          <td>${dateStr}</td>
       
          <td>${trans.customer_name || '-'}</td>
          <td>${trans.type_of_drink || '-'}</td>
          <td>${trans.quantity_of_drinks || 0}</td>
          <td>${trans.receipt_count || 0}</td>
          <td>${trans.time_of_day || '-'}</td>
          <td>${amountStr}</td>
          <td>${trans.payment_method || '-'}</td>
        
        </tr>
      `;
    });
    
    tableBody.innerHTML = rows;
    
    // Update count label
    const label = document.getElementById('currentAnalysisDataRange');
    if (label) {
      label.textContent = `${data.date_range_text} - ${data.showing_count} of ${data.total_count} transactions`;
    }
    
    console.log('✓ Loaded', data.transactions.length, 'transactions');
    
  } catch (error) {
    console.error('✗ Error:', error);
    
    tableHead.innerHTML = `
      <tr>
        <th>Date</th>
        <th>Customer Traffic</th>
        <th>Revenue</th>
        <th>Transactions</th>
        <th>Risk Level</th>
        <th>Status</th>
      </tr>
    `;
    
    tableBody.innerHTML = `
      <tr>
        <td colspan="6" class="no-data" style="color: #e74c3c;">
          Error: ${error.message}
        </td>
      </tr>
    `;
  }
}

// Auto-load transaction logs on page load
document.addEventListener('DOMContentLoaded', function() {
  console.log('Page loaded - initializing transaction logs');
  
  setTimeout(() => {
    const filterContainer = document.getElementById('transactionDateFilter');
    if (filterContainer) {
      filterContainer.style.display = 'flex'; // Show filter
    }
    loadTransactionLogs();
  }, 1500);
});

window.toggleDataView = toggleDataView;
window.applyDateFilter = applyDateFilter;
window.applyCustomDateFilter = applyCustomDateFilter;
window.loadTransactionLogs = loadTransactionLogs;
window.currentDataView = currentDataView;

console.log('✓ Transaction logs with date filter loaded');
</script>

  


 




    <!-- AI Recommendations Page -->
<div id="recommendations" class="page">
  <div class="page-header">
    <h1><i class="fas fa-lightbulb"></i> Real-Time Strategic Recommendations</h1>
    <p>Data-driven recommendations to improve customer retention</p>
  </div>

  <div class="recommendations-grid"><!-- Populated by refreshRecommendations() --></div>
</div>

	
	
<!-- User Profile Page -->
<div id="profile" class="page">
  <div class="page-header">
    <h1><i class="fas fa-user-circle"></i> User Profile</h1>
    <p>Manage your account settings and preferences</p>
  </div>

  <div class="profile-grid">
    <!-- Personal Information (NO company) -->
    <div class="profile-card">
      <div class="card-header">
        <div class="header-content">
          <i class="fas fa-user"></i>
          <div>
            <h3>Personal Information</h3>
            <p>Update your personal details</p>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="profile-avatar">
          <div class="avatar-container">
            <img src="uploads/avatars/default-icon.png" alt="Profile Avatar" id="profileAvatar">
            <button class="avatar-upload" onclick="uploadAvatar()"><i class="fas fa-camera"></i></button>
          </div>
          <div class="avatar-info">
            <h4 id="profileName">—</h4>
            <p id="profileRole">—</p>
          </div>
        </div>

        <form class="profile-form" onsubmit="return false;">
          <div class="form-group">
            <label for="profileFirstName">First Name</label>
            <input type="text" id="profileFirstName" placeholder="First name">
          </div>
          <div class="form-group">
            <label for="profileLastName">Last Name</label>
            <input type="text" id="profileLastName" placeholder="Last name">
          </div>
          <div class="form-group">
            <label for="profileEmail">Email Address</label>
            <input type="email" id="profileEmail" placeholder="you@example.com">
          </div>

          <button type="button" class="btn-primary" onclick="updateProfile()">
            <i class="fas fa-save"></i> Update Profile
          </button>
        </form>
      </div>
    </div>

    <!-- Security Settings (unchanged) -->
    <div class="profile-card">
      <div class="card-header">
        <div class="header-content">
          <i class="fas fa-shield-alt"></i>
          <div>
            <h3>Security Settings</h3>
            <p>Manage your account security</p>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form class="security-form" onsubmit="return false;">
          <div class="form-group">
            <label for="currentPassword">Current Password</label>
            <input type="password" id="currentPassword" placeholder="Enter current password">
          </div>
          <div class="form-group">
            <label for="newPassword">New Password</label>
            <input type="password" id="newPassword" placeholder="Enter new password">
          </div>
          <div class="form-group">
            <label for="confirmNewPassword">Confirm New Password</label>
            <input type="password" id="confirmNewPassword" placeholder="Confirm new password">
          </div>
          <button type="button" class="btn-primary" onclick="changePassword()">
            <i class="fas fa-key"></i> Change Password
          </button>
        </form>
      </div>
    </div>



  </div>
</div>



    <!-- Settings Page -->
    <div id="settings" class="page">
      <div class="page-header">
        <h1><i class="fas fa-cog"></i> System Settings</h1>
        <p>Configure system preferences and analytics settings</p>
      </div>

   

        <div class="settings-card">
          <div class="settings-header"><i class="fas fa-palette"></i><h3>System Preferences</h3></div>
          <div class="settings-content">
            <div class="toggle-group">
              <div class="toggle-item">
                <div><strong>Dark Mode</strong><p>Switch to dark theme</p></div>
                <div class="toggle-switch">
                  <input type="checkbox" id="darkModeToggle" onchange="toggleDarkMode(this.checked)">
                  <span class="slider"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>



<script>
/* ============================================================
   ChurnGuard Frontend — api/-locked, fixed & hardened
   - Every endpoint is called as api/...
   - No auto-detect; no silent fallbacks to root
   - No-cache reads for fresh prediction/dashboard data
   - Schema normalization across mixed backends
   - Won’t overwrite UI with zeros on transient errors
   - Optional debug: localStorage.setItem('cg_debug','1')
============================================================ */
(function () {
  'use strict';

  /* -------------------- config & utils -------------------- */
  // All calls must go to api/... (enforced via explicit paths below).
  // apiPath keeps support for absolute URLs, but we pass "api/..." everywhere.
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  const csrf  = () => $('meta[name="csrf-token"]')?.content || '';
  const peso  = (n, dp = 0) => '₱' + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: dp });
  const pct   = (n, dp = 2) => (n == null || Number.isNaN(Number(n))) ? '—' : `${Number(n).toFixed(dp)}%`;
  const clamp = (n, lo, hi) => Math.max(lo, Math.min(hi, Number(n || 0)));

  function apiPath(p) {
    const clean = String(p || '').replace(/^\/+/, '');
    if (/^https?:\/\//.test(p) || p.startsWith('/')) return p;
    if (clean.startsWith('api/')) return clean;     // we pass api/... explicitly
    return 'api/' + clean;
  }

  async function api(url, options = {}) {
    const u = apiPath(url);
    const isForm = (options.body instanceof FormData);
    const headers = {
      'Accept': 'application/json',
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0',
      'X-CSRF-Token': csrf(),
      ...(options.headers || {})
    };
    if (!isForm && options.method && options.method.toUpperCase() === 'POST' && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(u, { credentials: 'same-origin', cache: 'no-store', ...options, headers });
    let text = '';
    try { text = await res.text(); } catch {}
    let data;
    try { data = text ? JSON.parse(text) : {}; }
    catch {
      const snippet = String(text).replace(/<[^>]*>/g, ' ').slice(0, 200).trim();
      throw new Error(`Invalid JSON from ${u}${snippet ? ' — ' + snippet : ''}`);
    }
    if (!res.ok || data?.success === false) {
      throw new Error(data?.message || data?.error || `HTTP ${res.status}`);
    }
    return data;
  }

  async function apiTry(urls, options) {
  const list = Array.isArray(urls) ? urls : [urls];
  let err;
  for (let i = 0; i < list.length; i++) {
    try {
      const url = list[i] + (list[i].includes('?') ? '&' : '?') + 'ts=' + Date.now(); // bust caches
      return await api(url, options);
    } catch (e) {
      err = e;
    }
  }
  throw err || new Error('All endpoints failed');
}


  function diag(context, obj) {
    try {
      if (window.localStorage.getItem('cg_debug') === '1') {
        console.groupCollapsed(`[CG] ${context}`);
        console.table(obj);
        console.groupEnd();
      }
    } catch {}
  }

  /* -------------------- navigation -------------------- */
  function showPage(id) {
    $$('.page').forEach(p => p.classList.remove('active'));
    $('#' + id)?.classList.add('active');

    $$('.sidebar-menu .menu-item').forEach(a => {
      a.classList.remove('active');
      const oc = a.getAttribute('onclick') || '';
      const m = oc.match(/showPage\('([^']+)'\)/);
      if (m && m[1] === id) a.classList.add('active');
    });

    if (id === 'dashboard') { loadDashboard(); loadCharts(); }
    if (id === 'churn-prediction') { loadChurnRisk(); }
    if (id === 'customer-insights') { loadCustomerInsights(); }
    if (id === 'customer-monitoring') { loadCustomerMonitoring(); }
  }
  function wireSidebarClicks() {
    $$('.sidebar-menu .menu-item').forEach(a => {
      a.addEventListener('click', (ev) => {
        ev.preventDefault();
        const m = (a.getAttribute('onclick') || '').match(/showPage\('([^']+)'\)/);
        showPage(m && m[1] ? m[1] : 'dashboard');
      }, { passive: false });
    });
  }
  window.showPage = showPage;
  window.toggleSidebar = () => document.body.classList.toggle('sidebar-collapsed');

  /* -------------------- schema helpers -------------------- */
  function normalizePrediction(resp) {
    const src = (resp && (resp.prediction || resp.latest || resp.data)) || resp || {};
    const out = { has: false, percent: null, level: '', description: '', factors: [] };

    if (src.has_prediction === false) return out;

    let p = src.percentage;
    const coerce = (v) => (v == null ? null : (Number(v) <= 1 ? Number(v) * 100 : Number(v)));
    if (p == null && src.risk_score != null)      p = coerce(src.risk_score);
    if (p == null && src.score != null)           p = coerce(src.score);
    if (p == null && src.probability != null)     p = coerce(src.probability);
    if (p == null && src.riskProbability != null) p = coerce(src.riskProbability);

    let lvl = src.level || src.risk_level || '';
    if (!lvl && p != null) {
      const n = clamp(p, 0, 100);
      lvl = n >= 67 ? 'High' : n >= 34 ? 'Medium' : 'Low';
    }

    if (p != null && Number.isFinite(Number(p))) {
      out.has = true;
      out.percent = clamp(p, 0, 100);
      out.level = lvl || '—';
      out.description = src.description || src.note || '';
      try {
        out.factors = Array.isArray(src.factors) ? src.factors : (src.factors ? JSON.parse(src.factors) : []);
      } catch { out.factors = []; }
    }
    diag('normalizePrediction()', { input: resp, normalized: out });
    return out;
  }

  function setRiskCircle(el, pctVal) {
    if (!el) return;
    const n = pctVal == null ? null : clamp(pctVal, 0, 100);
    el.classList.remove('low-risk', 'medium-risk', 'high-risk');
    if (n == null) { el.style.setProperty('--pct', '0%'); return; }
    if (n >= 67) el.classList.add('high-risk');
    else if (n >= 34) el.classList.add('medium-risk');
    else el.classList.add('low-risk');
    el.style.setProperty('--pct', n.toFixed(0) + '%');
  }

  /* -------------------- Dashboard (api/, prediction-aware) -------------------- */
  async function loadDashboard() {
    const el = {
      revenue: $('#totalRevenue'),
      customers: $('#activeCustomers'),
      retention: $('#retentionRate'),
      churn: $('#churnRisk'),
      dRev: $('#revenueChange'),
      dCust: $('#customersChange'),
      dRet: $('#retentionChange'),
      dRisk: $('#riskChange'),
      updated: $('#lastUpdate'),
    };
    const needAny = Object.values(el).some(Boolean);
    if (!needAny) return;

    const controller = new AbortController();
    const t = setTimeout(() => controller.abort('timeout'), 10000);

    const peso2 = (n, dp = 0) =>
      (n == null || isNaN(n)) ? '—' : '₱' + Number(n).toLocaleString('en-PH', { maximumFractionDigits: dp });
    const pct2 = (n, dp = 2) =>
      (n == null || isNaN(n)) ? '—' : `${Number(n).toFixed(dp)}%`;
    const signed = (n, dp = 2) =>
      (n == null || isNaN(n)) ? '—' : `${n >= 0 ? '+' : ''}${Number(n).toFixed(dp)}%`;

    function setChange(el, val, tooltip) {
      if (!el) return;
      const positive = (val != null && !isNaN(val) && val > 0);
      const negative = (val != null && !isNaN(val) && val < 0);
      el.classList.remove('positive', 'negative');
      if (positive) el.classList.add('positive');
      if (negative) el.classList.add('negative');
      
      // keep the icon as-is, replace trailing text node safely
      const parts = Array.from(el.childNodes);
      const textNode = parts.find(n => n.nodeType === Node.TEXT_NODE);
      const text = ' ' + signed(val);
      if (textNode) {
        textNode.nodeValue = text;
      } else {
        el.append(document.createTextNode(text));
      }
      if (tooltip) el.setAttribute('title', tooltip); // native tooltip
    }

    // Enhanced tooltip function with confidence indicators
    function setValueWithConfidence(el, value, confidence, source, isPercentage = true) {
      if (!el) return;
      
      el.textContent = value;
      
      // Add confidence indicator to tooltip and visual styling
      if (confidence !== undefined && source) {
        const confidenceText = confidence >= 80 ? 'High' : (confidence >= 60 ? 'Medium' : 'Low');
        const tooltip = `${source} • Confidence: ${confidenceText} (${confidence}%)`;
        el.setAttribute('title', tooltip);
        
        // Visual confidence indicator classes
        el.classList.remove('confidence-high', 'confidence-medium', 'confidence-low');
        if (confidence >= 80) {
          el.classList.add('confidence-high');
        } else if (confidence >= 60) {
          el.classList.add('confidence-medium');
        } else {
          el.classList.add('confidence-low');
        }
      }
      
      // Add percentage-specific styling for retention and churn
      if (isPercentage && value !== '—') {
        const numValue = parseFloat(value);
        if (!isNaN(numValue)) {
          if (el === el.retention || el.id === 'retentionRate') {
            // Retention rate styling (higher is better)
            el.classList.remove('rate-excellent', 'rate-good', 'rate-warning', 'rate-critical');
            if (numValue >= 85) {
              el.classList.add('rate-excellent');
            } else if (numValue >= 70) {
              el.classList.add('rate-good');
            } else if (numValue >= 50) {
              el.classList.add('rate-warning');
            } else {
              el.classList.add('rate-critical');
            }
          } else if (el === el.churn || el.id === 'churnRisk') {
            // Churn risk styling (lower is better)
            el.classList.remove('risk-low', 'risk-medium', 'risk-high', 'risk-critical');
            if (numValue <= 25) {
              el.classList.add('risk-low');
            } else if (numValue <= 50) {
              el.classList.add('risk-medium');
            } else if (numValue <= 75) {
              el.classList.add('risk-high');
            } else {
              el.classList.add('risk-critical');
            }
          }
        }
      }
    }

    // Enhanced prediction normalization
    function normalizePrediction(data) {
      if (!data || typeof data !== 'object') {
        return { has: false };
      }
      
      return {
        has: Boolean(data.has),
        percent: parseFloat(data.risk_percentage || 0),
        level: String(data.risk_level || data.level || ''),
        description: String(data.description || ''),
        factors: Array.isArray(data.factors) ? data.factors : [],
        confidence: parseFloat(data.model_confidence || 1) * 100,
        quality: String(data.analysis_quality || 'unknown')
      };
    }

    // Enhanced data quality assessment
    function assessDataQuality(core, pred) {
      let qualityScore = 0;
      let qualityFactors = [];

      // Check prediction confidence
      if (core?.provenance?.retention_confidence >= 80 && core?.provenance?.risk_confidence >= 80) {
        qualityScore += 40;
        qualityFactors.push('High prediction confidence');
      } else if (core?.provenance?.retention_confidence >= 60 && core?.provenance?.risk_confidence >= 60) {
        qualityScore += 25;
        qualityFactors.push('Medium prediction confidence');
      } else {
        qualityScore += 10;
        qualityFactors.push('Low prediction confidence');
      }

      // Check data completeness
      if (core?.todays_sales > 0 && core?.todays_customers > 0) {
        qualityScore += 30;
        qualityFactors.push('Complete business data');
      } else if (core?.todays_sales > 0 || core?.todays_customers > 0) {
        qualityScore += 15;
        qualityFactors.push('Partial business data');
      } else {
        qualityScore += 5;
        qualityFactors.push('Limited business data');
      }

      // Check prediction availability
      if (core?.prediction_context?.has_current_day_prediction) {
        qualityScore += 20;
        qualityFactors.push('Current day prediction');
      } else if (core?.churn_risk !== null) {
        qualityScore += 10;
        qualityFactors.push('Recent prediction');
      } else {
        qualityScore += 0;
        qualityFactors.push('No recent predictions');
      }

      // Check trend data
      if (core?.retention_change !== null && core?.risk_change !== null) {
        qualityScore += 10;
        qualityFactors.push('Trend analysis available');
      }

      return {
        score: qualityScore,
        level: qualityScore >= 80 ? 'high' : (qualityScore >= 60 ? 'medium' : 'low'),
        factors: qualityFactors
      };
    }

    try {
      const [core, pred] = await Promise.all([
        apiTry(['api/dashboard.php', 'api/get_dashboard.php'], { signal: controller.signal }),
        apiTry(['api/churn_risk.php?action=latest', 'api/churn_risk.php']).catch(() => null)
      ]);

      const N = (v) => (v == null || isNaN(Number(v)) ? null : Number(v));

      const todaysSales     = N(core?.todays_sales);
      const todaysCustomers = N(core?.todays_customers);
      let   retentionRate   = N(core?.retention_rate);
      let   churnRiskPct    = N(core?.churn_risk);

      // Enhanced fallback logic with prediction normalization
      if (churnRiskPct == null && pred) {
        const n = normalizePrediction(pred);
        if (n.has) {
          churnRiskPct = N(n.percent);
          console.log('[Dashboard] Using normalized prediction fallback for churn risk:', churnRiskPct);
        }
      }

      // Enhanced retention rate fallback with business logic
      if (retentionRate == null && churnRiskPct != null) {
        // Use inverse relationship but with business context
        retentionRate = Math.max(0, Math.min(100, 100 - churnRiskPct));
        
        // Apply business performance adjustments if available
        if (todaysSales !== null && todaysCustomers !== null) {
          // Boost retention if we have active business
          if (todaysSales > 1000 && todaysCustomers > 10) {
            retentionRate = Math.min(100, retentionRate + 2);
          }
          // Penalize if very low activity
          if (todaysSales < 100 && todaysCustomers < 3) {
            retentionRate = Math.max(0, retentionRate - 5);
          }
        }
        
        console.log('[Dashboard] Calculated retention from churn risk with adjustments:', retentionRate);
      }

      // Apply values to UI with standard formatting (unchanged for sales/customers)
      if (el.revenue)   el.revenue.textContent   = peso2(todaysSales, 0);
      if (el.customers) el.customers.textContent = (todaysCustomers == null ? '—' : String(todaysCustomers));
      
      // Enhanced retention rate display with confidence and styling
      if (el.retention) {
        const retentionConfidence = core?.provenance?.retention_confidence;
        const retentionSource = core?.provenance?.retention_rate_source;
        setValueWithConfidence(
          el.retention, 
          pct2(retentionRate, 1), 
          retentionConfidence, 
          retentionSource,
          true
        );
      }
      
      // Enhanced churn risk display with confidence and styling
      if (el.churn) {
        const riskConfidence = core?.provenance?.risk_confidence;
        const riskSource = core?.provenance?.churn_risk_source;
        setValueWithConfidence(
          el.churn, 
          pct2(churnRiskPct, 1), 
          riskConfidence, 
          riskSource,
          true
        );
      }

      // Enhanced deltas with improved tooltips (unchanged logic)
      setChange(el.dRev,  N(core?.revenue_change),   null);
      setChange(el.dCust, N(core?.customers_change), null);
      setChange(el.dRet,  N(core?.retention_change), core?.tooltips?.retention_change || null);
      setChange(el.dRisk, N(core?.risk_change),      core?.tooltips?.risk_change || null);

      // Enhanced data quality assessment and display
      const dataQuality = assessDataQuality(core, pred);
      const qualityEl = document.getElementById('dataQualityIndicator');
      if (qualityEl) {
        qualityEl.textContent = `Data Quality: ${dataQuality.level.toUpperCase()} (${dataQuality.score}%)`;
        qualityEl.className = `quality-indicator quality-${dataQuality.level}`;
        qualityEl.setAttribute('title', `Quality factors: ${dataQuality.factors.join(', ')}`);
      }

      // Add prediction context to KPI cards
      if (core?.prediction_context) {
        const context = core.prediction_context;
        
        // Add risk level indicator to churn risk card
        if (context.risk_level && el.churn) {
          const riskCard = el.churn.closest('.kpi-card');
          if (riskCard) {
            riskCard.classList.remove('risk-level-low', 'risk-level-medium', 'risk-level-high');
            riskCard.classList.add(`risk-level-${context.risk_level.toLowerCase()}`);
          }
        }
        
        // Add factors count indicator
        if (context.risk_factors_count > 0) {
          const factorsEl = document.getElementById('riskFactorsCount');
          if (factorsEl) {
            factorsEl.textContent = `${context.risk_factors_count} risk factors`;
            factorsEl.className = context.risk_factors_count >= 3 ? 'factors-high' : 
                                 (context.risk_factors_count >= 2 ? 'factors-medium' : 'factors-low');
          }
        }
      }

      if (el.updated) el.updated.textContent = new Date().toLocaleString();

      // Enhanced dashboard risk visualization sync
      if (typeof loadChurnAssessmentForDashboard === 'function') {
        loadChurnAssessmentForDashboard();
      }

      // Enhanced logging for debugging
      console.log('[Dashboard] Enhanced accuracy load completed:', {
        retention: {
          rate: retentionRate,
          confidence: core?.provenance?.retention_confidence,
          source: core?.provenance?.retention_rate_source
        },
        churn: {
          risk: churnRiskPct,
          confidence: core?.provenance?.risk_confidence,
          source: core?.provenance?.churn_risk_source,
          level: core?.prediction_context?.risk_level
        },
        dataQuality: dataQuality.level,
        hasCurrentPrediction: core?.prediction_context?.has_current_day_prediction,
        referenceDate: core?.reference_date
      });

      // Success notification for high-quality data
      if (dataQuality.level === 'high') {
        console.log('[Dashboard] High quality prediction data available');
      } else if (dataQuality.level === 'low') {
        console.warn('[Dashboard] Low quality data - consider adding more business data');
      }

    } catch (e) {
      console.error('[Dashboard] Enhanced load failed:', e.message || e);
      
      // Enhanced error handling with user-friendly fallbacks
      if (el.retention) {
        el.retention.textContent = '—';
        el.retention.setAttribute('title', 'Unable to calculate retention rate - check prediction data');
      }
      if (el.churn) {
        el.churn.textContent = '—';
        el.churn.setAttribute('title', 'Unable to calculate churn risk - check prediction data');
      }
      if (el.revenue) {
        el.revenue.textContent = '—';
        el.revenue.setAttribute('title', 'Unable to load revenue data');
      }
      if (el.customers) {
        el.customers.textContent = '—';
        el.customers.setAttribute('title', 'Unable to load customer data');
      }

      // Show error indicator
      const qualityEl = document.getElementById('dataQualityIndicator');
      if (qualityEl) {
        qualityEl.textContent = 'Data Quality: ERROR';
        qualityEl.className = 'quality-indicator quality-error';
        qualityEl.setAttribute('title', `Failed to load dashboard data: ${e.message}`);
      }
    } finally {
      clearTimeout(t);
    }
}


  /* -------------------- Churn prediction views (api/) -------------------- */
  async function loadChurnRisk() {
    try {
      const r = await apiTry(['api/churn_risk.php?action=latest', 'api/churn_risk.php']);
      const n = normalizePrediction(r);

      const circle = $('#riskCircle');
      const pctEl  = $('#riskPercentage');
      const lvlEl  = $('#riskLevel');
      const descEl = $('#riskDescription');
      const facEl  = $('#riskFactors');

      if (!n.has) {
        pctEl  && (pctEl.textContent  = '—');
        lvlEl  && (lvlEl.textContent  = 'No prediction yet');
        descEl && (descEl.textContent = 'Click “Run Churn Prediction” to generate a risk score.');
        setRiskCircle(circle, null);
        facEl && (facEl.innerHTML = '<span class="risk-factor-tag">No risk factors</span>');
        return;
      }

      pctEl  && (pctEl.textContent  = `${Math.round(n.percent)}%`);
      lvlEl  && (lvlEl.textContent  = `${n.level} Risk`);
      descEl && (descEl.textContent = n.description || '—');
      setRiskCircle(circle, n.percent);

      if (facEl) {
        facEl.innerHTML = '';
        (n.factors?.length ? n.factors : ['No risk factors']).forEach(t => {
          const s = document.createElement('span');
          s.className = 'risk-factor-tag';
          s.textContent = String(t);
          facEl.appendChild(s);
        });
      }
    } catch (e) {
      console.warn('[Churn risk]', e.message);
      $('#riskPercentage')  && ($('#riskPercentage').textContent = '—');
      $('#riskLevel')       && ($('#riskLevel').textContent = 'No prediction yet');
      $('#riskDescription') && ($('#riskDescription').textContent = 'Unable to load prediction at the moment.');
      const wrap = $('#riskFactors'); if (wrap) wrap.innerHTML = '<span class="risk-factor-tag">No risk factors</span>';
      setRiskCircle($('#riskCircle'), null);
    }
  }
  window.loadChurnRisk = loadChurnRisk;

  async function runChurnPrediction() {
    const btn = $('#runChurnPredictionBtn');
    const orig = btn ? btn.innerHTML : '';
    try {
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running…'; }
      await api('api/churn_risk.php?action=run&ts=' + Date.now(), { method: 'POST' });
      await loadChurnRisk();
      await loadDashboard();
      await refreshRecommendations(); // ensure updated recs
      showPage('churn-prediction');
      alert('Churn prediction completed.');
    } catch (e) {
      alert('Prediction error: ' + e.message);
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
  }
  window.runChurnPrediction = runChurnPrediction;

 async function loadChurnAssessmentForDashboard() {
    const pctEl  = $('#riskPercentageDash');
    const lvlEl  = $('#riskLevelDash');
    const descEl = $('#riskDescriptionDash');
    const factEl = $('#riskFactorsDash');
    const circle = $('#riskCircleDash');
    
    if (!pctEl && !lvlEl && !descEl && !factEl && !circle) return;

    try {
        const resp = await apiTry(['api/churn_risk.php?action=latest', 'api/churn_risk.php']);
        const n = normalizePrediction(resp);
        
        if (!n.has) {
            pctEl  && (pctEl.textContent = '—');
            lvlEl  && (lvlEl.textContent = 'No Assessment');
            descEl && (descEl.textContent = 'Add your daily transaction data in Data Input to generate intelligent churn risk assessment.');
            factEl && (factEl.innerHTML = '<span class="risk-factor-tag neutral">📊 Awaiting business data</span>');
            setRiskCircle(circle, null);
            return;
        }

        // Enhanced percentage display with confidence indicator
        const riskPct = Math.round(n.percent);
        pctEl && (pctEl.textContent = riskPct + '%');
        
        // Enhanced level display with emoji indicators
        const levelEmoji = {
            'High': '🔴',
            'Medium': '🟡', 
            'Low': '🟢'
        };
        const levelText = n.level || 'Unknown';
        lvlEl && (lvlEl.textContent = `${levelEmoji[levelText] || '⚪'} ${levelText}`);
        
        // Enhanced description with better formatting
        descEl && (descEl.textContent = n.description || 'Assessment pending...');
        
        if (factEl) {
            factEl.innerHTML = '';
            const factors = n.factors && n.factors.length ? n.factors : ['📊 No specific risk factors identified'];
            
            factors.forEach((factor, index) => {
                const span = document.createElement('span');
                
                // Enhanced factor categorization with more precise matching
                let factorClass = 'risk-factor-tag';
                
                if (factor.includes('🚨') || factor.includes('URGENT') || factor.includes('crisis') || factor.includes('collapse') || factor.includes('exodus')) {
                    factorClass = 'risk-factor-tag critical-urgent';
                } else if (factor.includes('🔴') || factor.includes('Critical') || factor.includes('Severe') || factor.includes('Zero sales')) {
                    factorClass = 'risk-factor-tag critical';
                } else if (factor.includes('🟡') || factor.includes('⚠️') || factor.includes('Major') || factor.includes('High') || factor.includes('Significant') || factor.includes('Poor')) {
                    factorClass = 'risk-factor-tag warning';
                } else if (factor.includes('✅') || factor.includes('📈') || factor.includes('💹') || factor.includes('🎯') || factor.includes('💎') || factor.includes('Excellent') || factor.includes('Strong')) {
                    factorClass = 'risk-factor-tag positive';
                } else if (factor.includes('📊') && (factor.includes('No') || factor.includes('stable') || factor.includes('Baseline'))) {
                    factorClass = 'risk-factor-tag neutral';
                } else if (factor.includes('⏳') || factor.includes('Awaiting') || factor.includes('Add')) {
                    factorClass = 'risk-factor-tag info';
                } else if (factor.includes('🏪') || factor.includes('💰') && factor.includes('daily')) {
                    factorClass = 'risk-factor-tag insight';
                } else {
                    // Smart classification based on content
                    if (factor.includes('down') || factor.includes('decline') || factor.includes('drop') || factor.includes('low') || factor.includes('weak')) {
                        factorClass = 'risk-factor-tag warning';
                    } else if (factor.includes('growth') || factor.includes('above') || factor.includes('high') || factor.includes('good')) {
                        factorClass = 'risk-factor-tag positive';
                    }
                }
                
                span.className = factorClass;
                span.textContent = String(factor);
                
                // Add priority indicator for critical factors
                if (factorClass.includes('critical')) {
                    span.style.fontWeight = '600';
                    span.style.border = '2px solid';
                }
                
                factEl.appendChild(span);
                
                // Add spacing between factors
                if (index < factors.length - 1) {
                    factEl.appendChild(document.createTextNode(' '));
                }
            });
            
            // Add data quality indicator if available
            if (n.analysis_quality) {
                const qualitySpan = document.createElement('span');
                qualitySpan.className = `risk-factor-tag quality-${n.analysis_quality}`;
                qualitySpan.textContent = `📊 Analysis: ${n.analysis_quality} quality`;
                qualitySpan.style.fontSize = '10px';
                qualitySpan.style.opacity = '0.8';
                factEl.appendChild(document.createTextNode(' '));
                factEl.appendChild(qualitySpan);
            }
        }
        
        // Enhanced risk circle with level-based colors
        setRiskCircle(circle, n.percent, n.level);
        
        // Add confidence indicator if available
        if (n.model_confidence !== undefined && n.model_confidence < 0.8) {
            const confidenceEl = $('#riskConfidenceDash');
            if (confidenceEl) {
                const confidencePct = Math.round(n.model_confidence * 100);
                confidenceEl.textContent = `${confidencePct}% confidence`;
                confidenceEl.className = confidencePct >= 80 ? 'confidence-high' : (confidencePct >= 60 ? 'confidence-medium' : 'confidence-low');
            }
        }
        
    } catch (e) {
        pctEl  && (pctEl.textContent = '—');
        lvlEl  && (lvlEl.textContent = 'Error');
        descEl && (descEl.textContent = 'Unable to load churn risk assessment. Please check your data input and try again.');
        factEl && (factEl.innerHTML = '<span class="risk-factor-tag error">⚠️ Analysis failed - check data quality</span>');
        setRiskCircle(circle, null);
        console.error('[Dashboard Churn Assessment]', e.message, e);
    }
}

// Enhanced risk circle function
function setRiskCircle(circleEl, percentage, level = null) {
    if (!circleEl) return;
    
    if (percentage === null || percentage === undefined) {
        circleEl.style.background = '#f1f5f9';
        circleEl.style.borderColor = '#cbd5e1';
        return;
    }
    
    // Level-based color scheme
    let color;
    switch (level) {
        case 'High':
            color = percentage >= 80 ? '#dc2626' : '#ef4444';
            break;
        case 'Medium':
            color = percentage >= 50 ? '#d97706' : '#f59e0b';
            break;
        case 'Low':
            color = '#16a34a';
            break;
        default:
            color = percentage >= 70 ? '#dc2626' : (percentage >= 40 ? '#d97706' : '#16a34a');
    }
    
    // Create gradient effect
    const gradient = `conic-gradient(${color} ${percentage * 3.6}deg, #e5e7eb ${percentage * 3.6}deg)`;
    circleEl.style.background = gradient;
    circleEl.style.borderColor = color;
    
    // Add pulsing animation for high risk
    if (percentage >= 70) {
        circleEl.style.animation = 'pulse 2s infinite';
    } else {
        circleEl.style.animation = 'none';
    }
}

// Helper function to normalize prediction data
function normalizePrediction(data) {
    if (!data || typeof data !== 'object') {
        return { has: false };
    }
    
    return {
        has: Boolean(data.has),
        percent: parseFloat(data.risk_percentage || 0),
        level: String(data.risk_level || data.level || ''),
        description: String(data.description || ''),
        factors: Array.isArray(data.factors) ? data.factors : [],
        model_confidence: parseFloat(data.model_confidence || 1),
        analysis_quality: String(data.analysis_quality || 'unknown')
    };
}

  /* -------------------- Data input (api/) -------------------- */
  function v(id) { return $('#' + id)?.value || ''; }
  async function saveChurnData() {
    const payload = {
      date: v('date'),
      receipt_count: v('receiptCount'),
      sales_volume: v('salesVolume'),
      customer_traffic: v('customerTraffic'),
      morning_receipt_count: v('morningReceiptCount'),
      swing_receipt_count: v('swingReceiptCount'),
      graveyard_receipt_count: v('graveyardReceiptCount'),
      morning_sales_volume: v('morningSalesVolume'),
      swing_sales_volume: v('swingSalesVolume'),
      graveyard_sales_volume: v('graveyardSalesVolume'),
      previous_day_receipt_count: v('previousDayReceiptCount'),
      previous_day_sales_volume: v('previousDaySalesVolume'),
      weekly_average_receipts: v('weeklyAverageReceipts'),
      weekly_average_sales: v('weeklyAverageSales'),
      transaction_drop_percentage: v('transactionDropPercentage'),
      sales_drop_percentage: v('salesDropPercentage')
    };
    try {
      await api('api/churn_data.php?action=save', { method: 'POST', body: JSON.stringify(payload) });
      alert('Churn data saved.');
      await loadDashboard();
      await loadCharts();
    } catch (e) { alert('Save error: ' + e.message); }
  }
  function clearForm() {
    [
      'date','receiptCount','salesVolume','customerTraffic',
      'morningReceiptCount','swingReceiptCount','graveyardReceiptCount',
      'morningSalesVolume','swingSalesVolume','graveyardSalesVolume',
      'previousDayReceiptCount','previousDaySalesVolume',
      'weeklyAverageReceipts','weeklyAverageSales',
      'transactionDropPercentage','salesDropPercentage'
    ].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
    alert('All fields cleared.');
  }
  window.saveChurnData = saveChurnData;
  window.clearForm = clearForm;

  /* -------------------- Charts (api/, Chart.js) -------------------- */
  /* -------------------- Enhanced Charts with Real Prediction Context -------------------- */
let charts = { traffic: null, churn: null, revenue: null };
let currentMetrics = {};

function destroyChart(c) { 
  try { 
    c && typeof c.destroy === 'function' && c.destroy(); 
  } catch {} 
}

function ensureCanvasMinH(id) { 
  const c = $('#' + id) || document.getElementById(id); 
  if (c && c.clientHeight < 180) c.style.minHeight = '300px'; 
}

function v(id) { return document.getElementById(id)?.value || $('#' + id)?.value || ''; }



// Enhanced traffic loading with 14-day support
// Enhanced traffic loading with 14-day support and today's shift breakdown
// Enhanced traffic loading with 14-day support and accurate customer visit patterns
async function loadTraffic(period) {
  try {
    const select = $('#trafficPeriod') || document.getElementById('trafficPeriod');
    const chosen = period || (select ? select.value : 'today') || 'today';
    
    console.log(`Loading traffic data for period: ${chosen}`);
    
    let labels, values, totalToday, peakTraffic, trendPct;
    let visitPatternLabels = [];
    let visitPatternValues = [];
    
    // Get comprehensive data like purchase behavior does
    const trafficData = await apiTry([
      'api/churn_data.php?action=recent&limit=30&ts=' + Date.now(),
      'api/traffic_data.php?period=today&ts=' + Date.now(),
      'api/churn_data.php?action=latest&ts=' + Date.now()
    ]);
    
    console.log('Traffic data response:', trafficData);
    
    if (trafficData && (trafficData.item || trafficData.data)) {
      const dataSource = trafficData.item || (Array.isArray(trafficData.data) ? trafficData.data[0] : trafficData);
      
      // Extract shift data
      const morning = parseInt(dataSource.morning_receipt_count || 0);
      const swing = parseInt(dataSource.swing_receipt_count || 0);  
      const graveyard = parseInt(dataSource.graveyard_receipt_count || 0);
      const totalCustomerTraffic = parseInt(dataSource.customer_traffic || 0);
      
      // Calculate other traffic (difference between total traffic and shift receipts)
      const totalShiftReceipts = morning + swing + graveyard;
      const other = Math.max(0, totalCustomerTraffic - totalShiftReceipts);
      
      // For today's bar chart
      labels = ['Morning', 'Mid Day', 'Evening'];
      values = [morning, swing, graveyard, other];
      totalToday = totalCustomerTraffic;
      peakTraffic = Math.max(morning, swing, graveyard);
      trendPct = parseFloat(dataSource.transaction_drop_percentage || 0);
      
      // For customer visit pattern - calculate percentages like purchase behavior
      const visitTotal = morning + swing + graveyard;
      if (visitTotal > 0) {
        visitPatternLabels = [
          'Morning Shift',
          'Swing Shift',
          'Graveyard Shift'
        ];
        visitPatternValues = [
          (morning / visitTotal) * 100,
          (swing / visitTotal) * 100,
          (graveyard / visitTotal) * 100
        ];
      } else {
        visitPatternLabels = ['Morning Shift', 'Swing Shift', 'Graveyard Shift'];
        visitPatternValues = [0, 0, 0];
      }
      
      console.log('Processed traffic data:', {
        morning, swing, graveyard, other,
        totalCustomerTraffic, totalShiftReceipts,
        visitPatternLabels, visitPatternValues
      });
      
    } else {
      // Fallback
      console.log('No traffic data, using defaults');
      labels = ['Morning', 'Swing', 'Graveyard', ''];
      values = [0, 0, 0, 0];
      totalToday = 0;
      peakTraffic = 0;
      trendPct = 0;
      visitPatternLabels = ['Morning Shift', 'Swing Shift', 'Graveyard Shift'];
      visitPatternValues = [0, 0, 0];
    }

    // Update UI elements
    const totalTodayEl = $('#totalCustomersToday') || document.getElementById('totalCustomersToday');
    const peakEl = $('#peakHourTraffic') || document.getElementById('peakHourTraffic');
    const trendEl = $('#trafficTrend') || document.getElementById('trafficTrend');
    
    if (totalTodayEl) {
      totalTodayEl.textContent = String(totalToday);
    }
    if (peakEl) {
      peakEl.textContent = String(peakTraffic);
    }
    if (trendEl) {
      const sign = trendPct >= 0 ? '+' : '';
      trendEl.textContent = `${sign}${trendPct.toFixed(1)}% (vs prev)`;
    }

    // Update traffic chart (bar chart for today's shift counts)
    const ctx = $('#trafficChart') || document.getElementById('trafficChart');
    if (ctx && window.Chart) {
      ensureCanvasMinH('trafficChart');
      destroyChart(charts.traffic);
      
      // Chart colors for today's shifts
      const todayColors = {
        backgroundColor: [
          'rgba(255, 206, 86, 0.8)',   // Morning - Yellow
          'rgba(54, 162, 235, 0.8)',   // Swing - Blue  
          'rgba(153, 102, 255, 0.8)',  // Graveyard - Purple
          'rgba(201, 203, 207, 0.8)'   // Other - Gray
        ],
        borderColor: [
          'rgba(255, 206, 86, 1)',
          'rgba(54, 162, 235, 1)', 
          'rgba(153, 102, 255, 1)',
          'rgba(201, 203, 207, 1)'
        ]
      };
      
      // Chart configuration for today's absolute counts
      const chartConfig = {
        type: 'bar',
        data: { 
          labels, 
          datasets: [{ 
            label: 'Shift Traffic',
            data: values, 
            backgroundColor: todayColors.backgroundColor,
            borderColor: todayColors.borderColor,
            borderWidth: 2,
            borderRadius: 4
          }] 
        },
        options: { 
          responsive: true, 
          maintainAspectRatio: false, 
          plugins: { 
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: (context) => {
                  const label = context[0].label;
                  return `Shift: ${label}`;
                },
                label: (context) => {
                  const value = context.parsed.y;
                  const shiftNames = ['Morning', 'Swing', 'Graveyard', 'Other'];
                  const shiftName = shiftNames[context.dataIndex] || 'Unknown';
                  return ` ${value} customers (${shiftName} shift)`;
                }
              }
            }
          }, 
          scales: { 
            y: { 
              beginAtZero: true, 
              ticks: { precision: 0 },
              title: {
                display: true,
                text: 'Customer Count'
              }
            },
            x: {
              title: {
                display: true,
                text: 'Shift Period'
              }
            }
          },
          animation: {
            duration: 1000,
            easing: 'easeOutQuart'
          }
        }
      };
      
      charts.traffic = new Chart(ctx, chartConfig);
      
      console.log(`Traffic chart loaded successfully for today:`, {
        dataPoints: values.length,
        total: totalToday,
        peak: peakTraffic,
        trend: trendPct,
        shifts: {
          morning: values[0] || 0,
          swing: values[1] || 0, 
          graveyard: values[2] || 0,
          other: values[3] || 0
        }
      });
    }

    // Update customer visit pattern chart (doughnut for percentages)
    const visitPatternCtx = document.getElementById('customerVisitPatternChart');
    if (visitPatternCtx && window.Chart) {
      ensureCanvasMinH('customerVisitPatternChart');
      destroyChart(charts.visitPattern || null);
      
      const visitPatternColors = {
        backgroundColor: [
          'rgba(255, 206, 86, 0.8)',   // Morning - Yellow
          'rgba(54, 162, 235, 0.8)',   // Swing - Blue
          'rgba(153, 102, 255, 0.8)'   // Graveyard - Purple
        ],
        borderColor: [
          'rgba(255, 206, 86, 1)',
          'rgba(54, 162, 235, 1)',
          'rgba(153, 102, 255, 1)'
        ]
      };
      
      charts.visitPattern = new Chart(visitPatternCtx, {
        type: 'doughnut',
        data: {
          labels: visitPatternLabels.map((label, idx) => {
            const percentage = visitPatternValues[idx] || 0;
            return `${label} (${percentage.toFixed(1)}%)`;
          }),
          datasets: [{
            data: visitPatternValues,
            backgroundColor: visitPatternColors.backgroundColor,
            borderColor: visitPatternColors.borderColor,
            borderWidth: 2,
            hoverOffset: 8,
            hoverBackgroundColor: [
              'rgba(255, 206, 86, 0.9)',
              'rgba(54, 162, 235, 0.9)',
              'rgba(153, 102, 255, 0.9)'
            ]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 11 }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.9)',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: '#333',
              borderWidth: 1,
              cornerRadius: 8,
              callbacks: {
                title: (context) => {
                  const labels = ['Morning Shift', 'Swing Shift', 'Graveyard Shift'];
                  return labels[context[0].dataIndex];
                },
                label: (context) => {
                  const value = context.parsed;
                  return ` Distribution: ${value.toFixed(1)}%`;
                },
                afterLabel: (context) => {
                  const index = context.dataIndex;
                  const counts = [values[0], values[1], values[2]];
                  return ` Customers: ${counts[index]}`;
                }
              }
            }
          },
          cutout: '60%',
          animation: {
            animateRotate: true,
            duration: 1200,
            easing: 'easeOutQuart'
          }
        }
      });
      
      console.log('Customer visit pattern chart created:', {
        labels: visitPatternLabels,
        percentages: visitPatternValues,
        counts: [values[0], values[1], values[2]]
      });
    }
    
  } catch (error) {
    console.error('[loadTraffic] Error:', error);
    
    // Show error state in UI
    const totalTodayEl = document.getElementById('totalCustomersToday');
    const peakEl = document.getElementById('peakHourTraffic');
    const trendEl = document.getElementById('trafficTrend');
    
    if (totalTodayEl) totalTodayEl.textContent = 'No Data';
    if (peakEl) peakEl.textContent = '0';
    if (trendEl) trendEl.textContent = '0%';
    
    // Create fallback charts
    createFallbackTrafficChart();
    createFallbackVisitPatternChart();
  }
}

// Fallback chart when data fails to load
function createFallbackTrafficChart() {
  const ctx = document.getElementById('trafficChart');
  if (!ctx || !window.Chart) return;
  
  ensureCanvasMinH('trafficChart');
  destroyChart(charts.traffic);
  
  charts.traffic = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Morning', 'Swing', 'Graveyard', ''],
      datasets: [{
        label: 'Demo Traffic',
        data: [0, 0, 0, 0],
        backgroundColor: [
          'rgba(255, 206, 86, 0.8)',
          'rgba(54, 162, 235, 0.8)', 
          'rgba(153, 102, 255, 0.8)',
          'rgba(201, 203, 207, 0.8)'
        ],
        borderColor: [
          'rgba(255, 206, 86, 1)',
          'rgba(54, 162, 235, 1)',
          'rgba(153, 102, 255, 1)',
          'rgba(201, 203, 207, 1)'
        ],
        borderWidth: 2,
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { 
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (context) => ` No data available`
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: { display: true, text: 'Customer Count' }
        },
        x: {
          title: { display: true, text: 'Shift Period' }
        }
      }
    }
  });
}

// Fallback chart for customer visit pattern
function createFallbackVisitPatternChart() {
  const ctx = document.getElementById('customerVisitPatternChart');
  if (!ctx || !window.Chart) return;
  
  ensureCanvasMinH('customerVisitPatternChart');
  destroyChart(charts.visitPattern || null);
  
  charts.visitPattern = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Morning (0%)', 'Swing (0%)', 'Graveyard (0%)'],
      datasets: [{
        data: [0, 0, 0],
        backgroundColor: [
          'rgba(255, 206, 86, 0.6)',
          'rgba(54, 162, 235, 0.6)',
          'rgba(153, 102, 255, 0.6)'
        ],
        borderColor: [
          'rgba(255, 206, 86, 1)',
          'rgba(54, 162, 235, 1)',
          'rgba(153, 102, 255, 1)'
        ],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: () => 'No data available'
          }
        }
      },
      cutout: '60%'
    }
  });
}

// Enhanced refresh function
async function refreshTrafficChart() {
  const select = document.getElementById('trafficPeriod');
  const currentPeriod = select ? select.value : 'today';
  console.log(`Refreshing traffic chart for ${currentPeriod}...`);
  await loadTraffic(currentPeriod);
}

async function loadChurnDistribution() {
  try {
    // Call the correct API endpoint to get the latest risk prediction
    const riskData = await apiTry([
      'api/churn_risk.php?action=latest',
      'api/churn_predictions.php?action=recent_predictions',
      'api/churn_distribution.php'
    ]);
    
    let churnLowRisk = 0, churnMediumRisk = 0, churnHighRisk = 0;
    let churnRiskDescription = 'No risk data available';
    let churnAvgRiskScore = 0;
    let churnDetailedFactors = [];
    
    console.log('Risk API Response:', riskData);
    
    // Check if we have valid risk data
    if (riskData.has && riskData.risk_percentage && riskData.risk_level) {
      const actualRiskScore = parseFloat(riskData.risk_percentage);
      const riskLevel = riskData.risk_level.toLowerCase();
      
      console.log('Found risk data:', { actualRiskScore, riskLevel });
      
      // ACCURATE DISTRIBUTION LOGIC:
      // The risk score represents the probability of that specific risk level
      if (riskLevel === 'high') {
        // High risk: 79% means 79% chance of high risk
        churnHighRisk = Math.round(actualRiskScore);
        // Remaining 21% distributed intelligently
        const remaining = 100 - churnHighRisk;
        churnLowRisk = Math.round(remaining * 0.8);  // Most of remaining goes to low
        churnMediumRisk = remaining - churnLowRisk;   // Small portion to medium
        
      } else if (riskLevel === 'medium') {
        // Medium risk: 45% means 45% chance of medium risk
        churnMediumRisk = Math.round(actualRiskScore);
        // Remaining 55% distributed intelligently
        const remaining = 100 - churnMediumRisk;
        churnLowRisk = Math.round(remaining * 0.75);  // Most of remaining goes to low
        churnHighRisk = remaining - churnLowRisk;      // Some to high
        
      } else if (riskLevel === 'low') {
        // Low risk: 15% means 15% risk, so 85% safe
        churnLowRisk = Math.round(100 - actualRiskScore);
        // The risk portion (15%) split between medium and high
        const riskPortion = 100 - churnLowRisk;
        churnMediumRisk = Math.round(riskPortion * 0.7);
        churnHighRisk = riskPortion - churnMediumRisk;
      }
      
      // Ensure perfect 100% total
      const total = churnLowRisk + churnMediumRisk + churnHighRisk;
      if (total !== 100) {
        const diff = 100 - total;
        // Add difference to the dominant category
        if (riskLevel === 'high') churnHighRisk += diff;
        else if (riskLevel === 'medium') churnMediumRisk += diff;
        else churnLowRisk += diff;
      }
      
      churnAvgRiskScore = actualRiskScore;
      churnRiskDescription = riskData.description || `${riskLevel} Risk: ${actualRiskScore}% probability`;
      churnDetailedFactors = riskData.factors || [];
      
    } else {
      // Fallback for when no risk data is available
      console.warn('No valid risk data found, using defaults');
      churnLowRisk = 70;
      churnMediumRisk = 20; 
      churnHighRisk = 10;
      churnRiskDescription = 'No recent risk analysis available';
    }
    
    // Ensure all values are positive integers
    churnLowRisk = Math.max(0, Math.round(churnLowRisk));
    churnMediumRisk = Math.max(0, Math.round(churnMediumRisk));
    churnHighRisk = Math.max(0, Math.round(churnHighRisk));
    
    console.log('Final calculated distribution:', {
      low: churnLowRisk,
      medium: churnMediumRisk, 
      high: churnHighRisk,
      total: churnLowRisk + churnMediumRisk + churnHighRisk
    });
    
    // Store metrics
    currentMetrics.churn = {
      low: churnLowRisk,
      medium: churnMediumRisk,
      high: churnHighRisk,
      totalCustomers: 100,
      loyalCustomers: churnLowRisk,
      avgRiskScore: churnAvgRiskScore,
      riskDescription: churnRiskDescription,
      predictions: riskData.has ? [riskData] : [],
      detailedFactors: churnDetailedFactors
    };
    
    // Update UI
    updateChurnUI(currentMetrics.churn);
    
    // Create the chart
    const churnChartCtx = document.getElementById('churnChart');
    if (!churnChartCtx || !window.Chart) return;
    
    ensureCanvasMinH('churnChart');
    destroyChart(charts.churn);
    
    charts.churn = new Chart(churnChartCtx, {
      type: 'doughnut',
      data: {
        labels: [
          `Low Risk (${churnLowRisk}%)`,
          `Medium Risk (${churnMediumRisk}%)`, 
          `High Risk (${churnHighRisk}%)`
        ],
        datasets: [{
          data: [churnLowRisk, churnMediumRisk, churnHighRisk],
          backgroundColor: [
            'rgba(40, 167, 69, 0.8)',  // Green for Low
            'rgba(255, 193, 7, 0.8)',  // Yellow for Medium  
            'rgba(220, 53, 69, 0.8)'   // Red for High
          ],
          borderColor: [
            'rgba(40, 167, 69, 1)',
            'rgba(255, 193, 7, 1)', 
            'rgba(220, 53, 69, 1)'
          ],
          borderWidth: 2,
          hoverOffset: 8,
          hoverBackgroundColor: [
            'rgba(40, 167, 69, 0.9)',
            'rgba(255, 193, 7, 0.9)',
            'rgba(220, 53, 69, 0.9)'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              usePointStyle: true,
              padding: 15,
              font: { size: 11 }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.9)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: '#333',
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: true,
            callbacks: {
              title: function(context) {
                const levels = ['Low Risk', 'Medium Risk', 'High Risk'];
                return levels[context[0].dataIndex];
              },
              label: function(context) {
                return `Probability: ${context.parsed}%`;
              },
              afterLabel: function(context) {
                const index = context.dataIndex;
                if (index === 0) return ['✓ Stable patterns', '✓ Low churn probability'];
                if (index === 1) return ['⚠ Moderate risk', '⚠ Monitor closely'];
                return ['🚨 High risk detected', '🚨 Take action immediately'];
              }
            }
          }
        },
        cutout: '60%',
        animation: {
          animateRotate: true,
          duration: 1200,
          easing: 'easeOutQuart'
        }
      }
    });
    
    console.log('Chart created successfully with distribution:', {
      'Green (Low)': `${churnLowRisk}%`,
      'Yellow (Medium)': `${churnMediumRisk}%`, 
      'Red (High)': `${churnHighRisk}%`
    });
    
  } catch (error) {
    console.error('Churn distribution error:', error);
    createFallbackChurnChart();
  }
}

// Fallback chart when API fails
function createFallbackChurnChart() {
  const ctx = document.getElementById('churnChart');
  if (!ctx || !window.Chart) return;
  
  ensureCanvasMinH('churnChart');
  destroyChart(charts.churn);
  
  charts.churn = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Low Risk (70%)', 'Medium Risk (20%)', 'High Risk (10%)'],
      datasets: [{
        data: [70, 20, 10],
        backgroundColor: [
          'rgba(40, 167, 69, 0.6)',
          'rgba(255, 193, 7, 0.6)', 
          'rgba(220, 53, 69, 0.6)'
        ],
        borderColor: [
          'rgba(40, 167, 69, 0.8)',
          'rgba(255, 193, 7, 0.8)',
          'rgba(220, 53, 69, 0.8)'
        ],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: () => 'No data available - using defaults'
          }
        }
      },
      cutout: '60%'
    }
  });
}

// Fixed and Enhanced Purchase Behavior Analysis
async function loadPurchaseBehavior() {
  try {
    console.log('Loading purchase behavior data...');
    
    // Get comprehensive business data
    const data = await apiTry([
      'api/churn_data.php?action=recent&limit=30&ts=' + Date.now(),
      'api/purchase_behavior.php?ts=' + Date.now(),
      'api/churn_data.php?action=analytics'
    ]);

    console.log('Purchase behavior raw data:', data);

    let labels = [];
    let values = [];
    let insights = {};
    
    // Process churn data for comprehensive business analysis
    if (data.data && Array.isArray(data.data) && data.data.length > 0) {
      const businessData = data.data;
      const latest = businessData[0]; // Most recent day
      console.log('Latest business data:', latest);
      
      // Calculate comprehensive business metrics
      const totals = businessData.reduce((acc, row) => {
        const receipts = parseInt(row.receipt_count || 0);
        const sales = parseFloat(row.sales_volume || 0);
        const traffic = parseInt(row.customer_traffic || 0);
        
        return {
          receipts: acc.receipts + receipts,
          sales: acc.sales + sales,
          traffic: acc.traffic + traffic,
          morningReceipts: acc.morningReceipts + parseInt(row.morning_receipt_count || 0),
          swingReceipts: acc.swingReceipts + parseInt(row.swing_receipt_count || 0),
          graveyardReceipts: acc.graveyardReceipts + parseInt(row.graveyard_receipt_count || 0),
          morningSales: acc.morningSales + parseFloat(row.morning_sales_volume || 0),
          swingSales: acc.swingSales + parseFloat(row.swing_sales_volume || 0),
          graveyardSales: acc.graveyardSales + parseFloat(row.graveyard_sales_volume || 0),
          days: acc.days + 1
        };
      }, { 
        receipts: 0, sales: 0, traffic: 0, days: 0,
        morningReceipts: 0, swingReceipts: 0, graveyardReceipts: 0,
        morningSales: 0, swingSales: 0, graveyardSales: 0
      });

      console.log('Calculated totals:', totals);
      
      // Key business metrics
      const avgDailyReceipts = totals.days > 0 ? totals.receipts / totals.days : 0;
      const avgTransactionValue = totals.receipts > 0 ? totals.sales / totals.receipts : 0;
      const avgDailySales = totals.days > 0 ? totals.sales / totals.days : 0;
      const revenuePerCustomer = totals.traffic > 0 ? totals.sales / totals.traffic : 0;
      
      // Shift analysis
      const totalShiftReceipts = totals.morningReceipts + totals.swingReceipts + totals.graveyardReceipts;
      const morningPercentage = totalShiftReceipts > 0 ? (totals.morningReceipts / totalShiftReceipts) * 100 : 0;
      const swingPercentage = totalShiftReceipts > 0 ? (totals.swingReceipts / totalShiftReceipts) * 100 : 0;
      const graveyardPercentage = totalShiftReceipts > 0 ? (totals.graveyardReceipts / totalShiftReceipts) * 100 : 0;
      
      // Conversion and efficiency metrics
      const conversionRate = totals.traffic > 0 ? (totals.receipts / totals.traffic) * 100 : 0;
      const basketSize = avgTransactionValue; // Alias for clarity
      
      // Today's performance (from latest entry)
      const todayReceipts = parseInt(latest.receipt_count || 0);
      const todaySales = parseFloat(latest.sales_volume || 0);
      const todayTraffic = parseInt(latest.customer_traffic || 0);
      const todayAvgTicket = todayReceipts > 0 ? todaySales / todayReceipts : 0;
      
      // Store comprehensive insights
      insights = {
        avgDailyReceipts, avgTransactionValue, avgDailySales, revenuePerCustomer,
        morningPercentage, swingPercentage, graveyardPercentage, conversionRate,
        basketSize, todayReceipts, todaySales, todayTraffic, todayAvgTicket,
        totalDays: totals.days, totalReceipts: totals.receipts, totalSales: totals.sales
      };
      
      // Chart data for visualization
      labels = [
        'Daily Receipts (Avg)',
        'Transaction Value (₱)', 
        'Revenue per Customer (₱)',
        'Morning Shift %',
        'Swing Shift %', 
        'Graveyard Shift %',
        'Conversion Rate %'
      ];
      
      values = [
        avgDailyReceipts,
        avgTransactionValue,
        revenuePerCustomer,
        morningPercentage,
        swingPercentage,
        graveyardPercentage,
        conversionRate
      ];
      
    } else if (data.categories && data.values) {
      // Use provided structured data
      labels = data.categories;
      values = data.values;
      insights = data.insights || {};
    } else {
      // Default demo data for development
      labels = ['Daily Receipts', 'Avg Transaction (₱)', 'Conversion Rate %', 'Morning %', 'Swing %', 'Graveyard %'];
      values = [280, 160, 85, 35, 45, 20];
      insights = { avgTransactionValue: 160, todayReceipts: 280, conversionRate: 85 };
    }

    console.log('Final chart data:', { labels, values, insights });
    
    // Store metrics globally
    currentMetrics.behavior = insights;
    
    // Update UI with business insights
    updateBehaviorUI(insights);

    // Create the chart
    const canvas = document.getElementById('purchaseBehaviorChart');
    if (!canvas) {
      console.warn('Purchase behavior chart canvas not found');
      return;
    }
    
    if (!window.Chart) {
      console.warn('Chart.js not loaded');
      return;
    }

    const ctx = canvas.getContext('2d');
    ensureCanvasMinH('purchaseBehaviorChart');
    destroyChart(charts.revenue);
    
    // Enhanced chart with professional styling
    charts.revenue = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Business Metrics',
          data: values,
          backgroundColor: [
            'rgba(54, 162, 235, 0.8)',   // Daily Receipts
            'rgba(255, 99, 132, 0.8)',   // Transaction Value  
            'rgba(75, 192, 192, 0.8)',   // Revenue per Customer
            'rgba(255, 205, 86, 0.8)',   // Morning
            'rgba(153, 102, 255, 0.8)',  // Swing
            'rgba(255, 159, 64, 0.8)',   // Graveyard
            'rgba(199, 199, 199, 0.8)'   // Conversion
          ],
          borderColor: [
            'rgba(54, 162, 235, 1)',
            'rgba(255, 99, 132, 1)', 
            'rgba(75, 192, 192, 1)',
            'rgba(255, 205, 86, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)',
            'rgba(199, 199, 199, 1)'
          ],
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            callbacks: {
              title: (items) => items[0]?.label || '',
              label: (ctx) => {
                const label = ctx.label || '';
                const value = ctx.parsed.y;
                
                if (label.includes('₱') || label.includes('Transaction') || label.includes('Revenue')) {
                  return ` ₱${new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value)}`;
                } else if (label.includes('%')) {
                  return ` ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(value)}%`;
                } else {
                  return ` ${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(value)}`;
                }
              },
              afterLabel: (ctx) => {
                const descriptions = {
                  'Daily Receipts': 'Average transactions per day',
                  'Transaction Value': 'Average amount per receipt', 
                  'Revenue per Customer': 'Sales divided by traffic',
                  'Morning %': 'Morning shift performance',
                  'Swing %': 'Peak hours performance',
                  'Graveyard %': 'Late shift performance',
                  'Conversion Rate': 'Traffic to sales conversion'
                };
                return descriptions[ctx.label.replace(' (Avg)', '').replace(' (₱)', '').replace(' %', '')] || '';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
            ticks: {
              callback: function(value) {
                if (value >= 1000) return `₱${(value/1000).toFixed(1)}k`;
                if (value >= 100) return `₱${value.toFixed(0)}`;
                return value.toFixed(1);
              }
            }
          },
          x: {
            grid: { display: false },
            ticks: { maxRotation: 45, font: { size: 10 } }
          }
        },
        animation: { duration: 1200, easing: 'easeOutQuart' }
      }
    });
    
    console.log('Purchase behavior chart created successfully');
    
  } catch (e) {
    console.error('[purchase behavior] Error:', e);
    currentMetrics.behavior = {};
    
    // Show fallback chart with demo data
    createFallbackBehaviorChart();
  }
}

// Fallback chart for development/testing
function createFallbackBehaviorChart() {
  const canvas = document.getElementById('purchaseBehaviorChart');
  if (!canvas || !window.Chart) return;
  
  const ctx = canvas.getContext('2d');
  destroyChart(charts.revenue);
  
  charts.revenue = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Daily Receipts', 'Avg Transaction', 'Conversion Rate', 'Morning Shift', 'Swing Shift', 'Graveyard'],
      datasets: [{
        label: 'Demo Data',
        data: [280, 160.71, 87.5, 33.9, 42.9, 23.2],
        backgroundColor: 'rgba(54, 162, 235, 0.7)'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } }
    }
  });
}

// UI update functions
function updateChurnUI(metrics) {
  const elements = {
    lowRiskCount: metrics.low || 0,
    mediumRiskCount: metrics.medium || 0,
    highRiskCount: metrics.high || 0,
    loyalCustomersCount: metrics.loyalCustomers || 0,
    totalCustomersAnalyzed: metrics.totalCustomers || 0
  };
  
  Object.entries(elements).forEach(([id, value]) => {
    const el = document.getElementById(id);
    if (el) el.textContent = new Intl.NumberFormat().format(value);
  });
  
  // Update timestamps
  const timestamp = new Date().toLocaleTimeString();
  const updateEls = ['churnLastUpdated', 'riskAnalysisTime'];
  updateEls.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = `Last updated: ${timestamp}`;
  });
}

function updateBehaviorUI(insights) {
  const pesoFmt = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 });
  const numFmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 });
  
  const elements = {
    avgTransactionValue: pesoFmt.format(insights.avgTransactionValue || 0),
    avgDailyReceipts: numFmt.format(insights.avgDailyReceipts || insights.todayReceipts || 0),
    conversionRate: numFmt.format(insights.conversionRate || 0) + '%',
    revenuePerCustomer: pesoFmt.format(insights.revenuePerCustomer || 0),
    receiptsToday: numFmt.format(insights.todayReceipts || 0),
    salesToday: pesoFmt.format(insights.todaySales || 0)
  };
  
  Object.entries(elements).forEach(([id, value]) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  });
  
  // Update timestamps
  const timestamp = new Date().toLocaleTimeString();
  const updateEls = ['behaviorLastUpdated', 'behaviorAnalysisTime'];
  updateEls.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = `Last updated: ${timestamp}`;
  });
}

// Main loading functions
async function loadCharts() {
  console.log('Loading all charts...');
  const results = await Promise.allSettled([
    loadTraffic().catch(e => console.warn('[traffic chart]', e.message)),
    loadChurnDistribution().catch(e => console.warn('[churn chart]', e.message)),
    loadPurchaseBehavior().catch(e => console.warn('[purchase behavior chart]', e.message))
  ]);
  console.log('Charts loading completed:', results);
}

async function updateTrafficChart() { 
  try { await loadTraffic(); } catch (e) { console.warn('[updateTrafficChart]', e.message); } 
}

// Individual refresh functions
async function refreshChurnChart() {
  console.log('Refreshing churn chart...');
  await loadChurnDistribution();
}

async function refreshBehaviorChart() {
  console.log('Refreshing behavior chart...');
  await loadPurchaseBehavior();
}

// Utility to get current business insights
function getBusinessInsights() {
  return {
    churn: currentMetrics.churn || {},
    behavior: currentMetrics.behavior || {},
    summary: {
      totalCustomers: (currentMetrics.churn?.totalCustomers || 0),
      loyalCustomers: (currentMetrics.churn?.loyalCustomers || 0),
      avgTransaction: (currentMetrics.behavior?.avgTransactionValue || 0),
      conversionRate: (currentMetrics.behavior?.conversionRate || 0)
    },
    timestamp: new Date().toISOString()
  };
}

// Expose all functions globally
window.updateTrafficChart = updateTrafficChart;
window.loadChurnDistribution = loadChurnDistribution;
window.loadPurchaseBehavior = loadPurchaseBehavior;
window.loadCharts = loadCharts;
window.refreshChurnChart = refreshChurnChart;
window.refreshBehaviorChart = refreshBehaviorChart;
window.getBusinessInsights = getBusinessInsights;
window.currentMetrics = currentMetrics;
  
  
  
 /* -------------------- Churn Report (api/) -------------------- */
async function loadChurnReport() {
  try {
    const j = await apiTry(['api/churn_report.php', 'api/reports/churn_report.php']);
    console.log('Churn Report Data:', j); // Check if the data is correctly returned

    // Check if elements are found
    console.log('Churn Today:', j.churn_rate_today);
    console.log('Retention 30d:', j.retention_rate_30d);
    console.log('Risk Level:', j.risk_level);
    console.log('Revenue at Risk:', j.revenue_at_risk);

    // Elements to display the churn report data
    const churnTodayEl = $('#churnToday');
    const retention30dEl = $('#retention30d');
    const riskLevelEl = $('#riskLevel');
    const revenueAtRiskEl = $('#revenueAtRisk');
    const atRiskCustomersEl = $('#atRiskCustomers');
    const avgBasketEl = $('#avgBasket');
    const retentionLiftEl = $('#retentionLift');
    const saveRateEl = $('#saveRate');
    const savedCustomersEl = $('#savedCustomers');
    const revenueSavedEl = $('#revenueSaved');
    const riskLowEl = $('#riskLow');
    const riskMediumEl = $('#riskMedium');
    const riskHighEl = $('#riskHigh');
    const modelAccuracyEl = $('#modelAccuracy');
    const modelPrecisionEl = $('#modelPrecision');
    const modelF1ScoreEl = $('#modelF1Score');
    
    // Churn Summary: Display churn and retention values
    churnTodayEl && (churnTodayEl.textContent = pct(j.churn_rate_today ?? 0, 2));
    retention30dEl && (retention30dEl.textContent = pct(j.retention_rate_30d ?? 0, 2));
    riskLevelEl && (riskLevelEl.textContent = j.risk_level || '—');
    
    // Revenue at Risk
    revenueAtRiskEl && (revenueAtRiskEl.textContent = peso(j.revenue_at_risk ?? 0));

    // At-Risk Customers
    atRiskCustomersEl && (atRiskCustomersEl.textContent = String(j.at_risk_customers ?? 0));

    // Average Basket (Average Basket Value)
    avgBasketEl && (avgBasketEl.textContent = peso(j.avg_basket ?? 0));

    // Retention Lift (Projected retention improvement with interventions)
    retentionLiftEl && (retentionLiftEl.textContent = pct(j.lift?.revenue_saved ?? 0));

    // Save Rate: Projected success rate of retention efforts
    saveRateEl && (saveRateEl.textContent = pct(j.lift?.save_rate ?? 0));

    // Saved Customers
    savedCustomersEl && (savedCustomersEl.textContent = String(j.lift?.saved_customers ?? 0));

    // Revenue Saved
    revenueSavedEl && (revenueSavedEl.textContent = peso(j.lift?.revenue_saved ?? 0));

    // Risk Distribution (Low/Medium/High counts for the next 30 days)
    riskLowEl && (riskLowEl.textContent = String(j.distribution_30d?.low ?? 0));
    riskMediumEl && (riskMediumEl.textContent = String(j.distribution_30d?.medium ?? 0));
    riskHighEl && (riskHighEl.textContent = String(j.distribution_30d?.high ?? 0));

    // Model Snapshot: Display model evaluation metrics (Accuracy, Precision, F1-Score)
    modelAccuracyEl && (modelAccuracyEl.textContent = pct(j.model?.accuracy ?? 0, 2));
    modelPrecisionEl && (modelPrecisionEl.textContent = pct(j.model?.precision ?? 0, 2));
    modelF1ScoreEl && (modelF1ScoreEl.textContent = pct(j.model?.f1 ?? 0, 2));

  } catch (e) {
    console.error('[Churn Report]', e.message);
    alert('Error loading churn report: ' + e.message);
  }
}


  
  /* -------------------- Enhanced Churn prediction views (api/) -------------------- */
async function loadChurnRisk() {
  try {
    const r = await apiTry(['api/churn_risk.php?action=latest&ts=' + Date.now(), 'api/churn_risk.php?ts=' + Date.now()]);
    const n = normalizePrediction(r);

    const circle = $('#riskCircle');
    const pctEl  = $('#riskPercentage');
    const lvlEl  = $('#riskLevel');
    const descEl = $('#riskDescription');
    const facEl  = $('#riskFactors');

    if (!n.has) {
      pctEl  && (pctEl.textContent  = '—');
      lvlEl  && (lvlEl.textContent  = 'No prediction yet');
      descEl && (descEl.textContent = 'Run Churn Prediction to generate a risk assessment based on your business data.');
      setRiskCircle(circle, null);
      facEl && (facEl.innerHTML = '<span class="risk-factor-tag">No risk factors</span>');
      return;
    }

    // Display accurate percentage and level
    pctEl  && (pctEl.textContent  = `${Math.round(n.percent)}%`);
    lvlEl  && (lvlEl.textContent  = `${n.level} Risk`);
    descEl && (descEl.textContent = n.description || 'Risk assessment based on your business patterns.');
    setRiskCircle(circle, n.percent);

    // Display risk factors with proper handling
    if (facEl) {
      facEl.innerHTML = '';
      const factorsToShow = (n.factors && n.factors.length) ? n.factors : ['No specific risk factors identified'];
      factorsToShow.forEach(factor => {
        const span = document.createElement('span');
        span.className = 'risk-factor-tag';
        span.textContent = String(factor);
        facEl.appendChild(span);
      });
    }

    console.log('Churn risk loaded:', { level: n.level, percentage: n.percent, factors: n.factors });
  } catch (e) {
    console.warn('[Churn risk error]', e.message);
    
    // Set error states
    const pctEl = $('#riskPercentage');
    const lvlEl = $('#riskLevel');
    const descEl = $('#riskDescription');
    const facEl = $('#riskFactors');
    
    pctEl  && (pctEl.textContent = '—');
    lvlEl  && (lvlEl.textContent = 'Error loading prediction');
    descEl && (descEl.textContent = 'Unable to load prediction. Please try running a new prediction.');
    facEl  && (facEl.innerHTML = '<span class="risk-factor-tag">Error loading factors</span>');
    setRiskCircle($('#riskCircle'), null);
  }
}

async function runChurnPrediction() {
  const btn = $('#runChurnPredictionBtn') || document.getElementById('runChurnPredictionBtn');
  const orig = btn ? btn.innerHTML : '';
  
  try {
    // Update button state
    if (btn) { 
      btn.disabled = true; 
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Prediction...'; 
    }
    
    console.log('Starting churn prediction...');
    
    // Run prediction with proper API call - Fixed the syntax error
    const result = await api('api/churn_risk.php?action=run', { 
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    console.log('Prediction completed successfully:', result);
    
    // Update all components with fresh data
    await Promise.all([
      loadChurnRisk().catch(e => console.warn('Failed to reload churn risk:', e)),
      loadDashboard().catch(e => console.warn('Failed to reload dashboard:', e)),
      loadCharts().catch(e => console.warn('Failed to reload charts:', e)),
      refreshRecommendations().catch(e => console.warn('Failed to refresh recommendations:', e))
    ]);
    
    // Navigate to prediction page
    if (typeof showPage === 'function') {
      showPage('churn-prediction');
    }
    
    // Show success message with details
    const riskLevel = result.risk_level || 'Unknown';
    const riskPct = result.risk_percentage || 0;
    const isNewUser = result.is_new_user || false;
    
    let message = `✅ Churn prediction completed!\n\nRisk Level: ${riskLevel}\nRisk Score: ${riskPct.toFixed(1)}%`;
    
    if (isNewUser) {
      message += `\n\n💡 New user detected. Add more business data for more accurate predictions.`;
    } else {
      message += `\n\n${result.description || 'Analysis complete based on your business data.'}`;
    }
    
    alert(message);
    
  } catch (e) {
    console.error('Churn prediction error:', e);
    
    // Enhanced error handling
    let errorMessage = 'Unknown error occurred';
    
    if (e.message.includes('No churn_data')) {
      errorMessage = 'No business data found. Please save some churn data first before running predictions.';
    } else if (e.message.includes('Prediction run failed')) {
      errorMessage = 'Prediction calculation failed. Please check your data and try again.';
    } else {
      errorMessage = e.message;
    }
    
    alert(`❌ Prediction Error\n\n${errorMessage}\n\nTip: Ensure you have saved recent business data in the Data Input section.`);
    
  } finally {
    // Always restore button state
    if (btn) { 
      btn.disabled = false; 
      btn.innerHTML = orig; 
    }
    
    console.log('Churn prediction process completed');
  }
}

async function loadChurnAssessmentForDashboard() {
  const pctEl  = $('#riskPercentageDash');
  const lvlEl  = $('#riskLevelDash');
  const descEl = $('#riskDescriptionDash');
  const factEl = $('#riskFactorsDash');
  const circle = $('#riskCircleDash');
  
  // Only proceed if at least one element exists
  if (!pctEl && !lvlEl && !descEl && !factEl && !circle) return;

  try {
    const resp = await apiTry([
      'api/churn_risk.php?action=latest&ts=' + Date.now(), 
      'api/churn_risk.php?ts=' + Date.now()
    ]);
    const n = normalizePrediction(resp);
    
    if (!n.has) {
      // No prediction available
      pctEl  && (pctEl.textContent = '—');
      lvlEl  && (lvlEl.textContent = 'No prediction yet');
      descEl && (descEl.textContent = 'Run Churn Prediction in Data Input to generate risk assessment.');
      factEl && (factEl.innerHTML = '<span class="risk-factor-tag">No prediction data</span>');
      setRiskCircle(circle, null);
      return;
    }
    
    // Update dashboard elements with prediction data
    pctEl  && (pctEl.textContent = `${Math.round(n.percent)}%`);
    lvlEl  && (lvlEl.textContent = n.level || 'Unknown');
    descEl && (descEl.textContent = n.description || 'Risk assessment based on business data analysis.');
    
    // Update risk factors for dashboard
    if (factEl) {
      factEl.innerHTML = '';
      const factors = (n.factors && n.factors.length) ? n.factors : ['No specific risk factors'];
      factors.forEach(factor => {
        const span = document.createElement('span');
        span.className = 'risk-factor-tag';
        span.textContent = String(factor);
        factEl.appendChild(span);
      });
    }
    
    setRiskCircle(circle, n.percent);
    
    console.log('Dashboard churn assessment updated:', { level: n.level, percentage: n.percent });
    
  } catch (e) {
    console.warn('[Dashboard assessment error]', e.message);
    
    // Set error states for dashboard
    pctEl  && (pctEl.textContent = '—');
    lvlEl  && (lvlEl.textContent = 'Error');
    descEl && (descEl.textContent = 'Unable to load prediction data.');
    factEl && (factEl.innerHTML = '<span class="risk-factor-tag">Error loading data</span>');
    setRiskCircle(circle, null);
  }
}

// Enhanced refresh function for real-time updates
async function refreshChurnPrediction() {
  console.log('Refreshing churn prediction data...');
  try {
    await Promise.all([
      loadChurnRisk(),
      loadChurnAssessmentForDashboard()
    ]);
    console.log('Churn prediction data refreshed successfully');
  } catch (error) {
    console.warn('Failed to refresh churn prediction:', error);
  }
}

// Auto-refresh churn data every 2 minutes when prediction page is active
function startChurnPredictionAutoRefresh() {
  return setInterval(() => {
    const currentPage = document.querySelector('.page.active');
    if (currentPage && currentPage.id === 'churn-prediction') {
      refreshChurnPrediction();
    }
  }, 120000); // 2 minutes
}

// Export functions
window.loadChurnRisk = loadChurnRisk;
window.runChurnPrediction = runChurnPrediction;
window.loadChurnAssessmentForDashboard = loadChurnAssessmentForDashboard;
window.refreshChurnPrediction = refreshChurnPrediction;
window.startChurnPredictionAutoRefresh = startChurnPredictionAutoRefresh;
  
  

 /* CUSTOER INSIGHT JSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS*/
// Enhanced Customer Insights JavaScript - 14-day comparative analysis
// Updated to use single API endpoint with 14-day data analysis
// Enhanced Customer Insights JavaScript - Complete Fixed Version
// Based on all provided code and fixes for 14-day analysis

async function loadCustomerInsightsData() {
  try {
    console.log('Loading customer insights data with 14-day analysis...');
    
    // Get DOM elements with unique selectors
    const insightsContainer = document.getElementById('customer-insights');
    if (insightsContainer) {
      insightsContainer.classList.add('insights-loading');
    }
    
    // Fetch 14-day comparative data from enhanced endpoint
    const insightsData = await customerInsightsApiCall('api/customer_insights.php');
    
    // Process and update insights with enhanced 14-day analysis
    updateCustomerInsightsDisplay(insightsData);
    
    console.log('Customer insights loaded successfully with 14-day analysis');
    
  } catch (insightsError) {
    console.error('Customer insights error:', insightsError);
    showCustomerInsightsError(insightsError.message);
  } finally {
    const insightsContainer = document.getElementById('customer-insights');
    if (insightsContainer) {
      insightsContainer.classList.remove('insights-loading');
    }
  }
}

// Process and display all insights data with 14-day comparative analysis
function updateCustomerInsightsDisplay(insightsData) {
  // Extract data with safe defaults from enhanced API response
  const data = insightsData || {};
  
  console.log('Updating customer insights display with data:', data);
  
  // Update customer segmentation with 14-day trends
  updateCustomerSegmentationInsights(data);
  
  // Update purchase behavior with comparative analysis
  updatePurchaseBehaviorInsights(data);
  
  // Update churn risk intelligence with trend data
  updateChurnRiskInsights(data);
  
  // Update trend analysis section
  updateTrendAnalysisInsights(data);
}

// Update customer segmentation section with 14-day analysis
function updateCustomerSegmentationInsights(data) {
  const loyalCustomers = Number(data.loyalCustomers) || 0;
  const loyaltyRate = Number(data.loyaltyRate) || 0;
  const segmentationPatterns = data.segmentation || [];
  
  console.log('Updating segmentation - Loyal customers:', loyalCustomers, 'Loyalty rate:', loyaltyRate);
  
  // Update DOM elements
  updateInsightsElement('insightsLoyalCustomers', loyalCustomers.toLocaleString());
  updateInsightsElement('insightsLoyaltyRate', `${loyaltyRate.toFixed(1)}%`);
  
  // Update segmentation patterns
  updateInsightsPatterns('insightsSegmentationPatterns', segmentationPatterns);
  updateInsightsElement('insightsSegmentUpdated', new Date().toLocaleTimeString());
}

// Update purchase behavior section with comparative analysis
function updatePurchaseBehaviorInsights(data) {
  const avgPurchaseValue = Number(data.avgPurchaseValue) || 0;
  const purchasePatterns = data.purchasePatterns || [];
  
  console.log('Updating purchase behavior - Avg value:', avgPurchaseValue);
  
  // Update DOM elements
  updateInsightsElement('insightsAvgPurchaseValue', formatInsightsPesos(avgPurchaseValue));
  
  // Update purchase patterns with 14-day insights
  updateInsightsPatterns('insightsPurchasePatterns', purchasePatterns);
  updateInsightsElement('insightsPurchaseUpdated', new Date().toLocaleTimeString());
}

// Update churn risk intelligence section with enhanced analysis
function updateChurnRiskInsights(data) {
  const riskLevel = data.riskLevel || 'Unknown';
  const riskPercentage = Number(data.riskPercentage) || 0;
  const riskIndicators = data.riskIndicators || [];
  
  console.log('Updating risk insights - Level:', riskLevel, 'Percentage:', riskPercentage);
  
  // Update risk badge with enhanced styling
  const riskBadgeElement = document.getElementById('insightsRiskBadge');
  if (riskBadgeElement) {
    riskBadgeElement.textContent = `${riskLevel} Risk (${riskPercentage.toFixed(1)}%)`;
    riskBadgeElement.className = `insights-risk-badge insights-${riskLevel.toLowerCase()}-risk`;
  }
  
  // Update risk level element
  updateInsightsElement('insightsRiskLevel', riskLevel);
  updateInsightsElement('insightsRiskPercentage', `${riskPercentage.toFixed(1)}%`);
  
  // Update risk indicators with 14-day analysis
  updateInsightsRiskFactors('insightsRiskFactors', riskIndicators);
  updateInsightsElement('insightsRiskUpdated', new Date().toLocaleTimeString());
}

// Update trend analysis section with all elements properly handled
function updateTrendAnalysisInsights(data) {
  const trendDirection = data.trendDirection || 'No Data';
  const trendPatterns = data.trendPatterns || [];
  const focusArea = generateFocusAreaFromTrends(data);
  const opportunityScore = calculateOpportunityScoreFromData(data);
  
  console.log('Updating trend analysis - Direction:', trendDirection);
  
  // Update main trend direction in the dedicated trend card
  updateInsightsElement('insightsTrendDirectionMain', trendDirection);
  updateInsightsElement('insightsFocusAreaMain', focusArea);
  updateInsightsElement('insightsPerformanceTrendMain', trendDirection);
  updateInsightsElement('insightsOpportunityScoreMain', opportunityScore);
  
  // Update the shared elements in other cards (for backward compatibility)
  updateInsightsElement('insightsTrendDirection', trendDirection);
  updateInsightsElement('insightsPerformanceTrend', trendDirection);
  updateInsightsElement('insightsFocusArea', focusArea);
  updateInsightsElement('insightsOpportunityScore', opportunityScore);
  
  // Update trend patterns
  updateInsightsPatterns('insightsTrendPatterns', trendPatterns);
}

// Enhanced helper function to update DOM elements safely
function updateInsightsElement(elementId, valueText) {
  const targetElement = document.getElementById(elementId);
  if (targetElement) {
    targetElement.textContent = valueText || '—';
  } else {
    console.warn(`Element with ID '${elementId}' not found`);
  }
}

// Enhanced helper function to update delta values with styling
function updateInsightsDelta(elementId, deltaValue) {
  const deltaElement = document.getElementById(elementId);
  if (deltaElement) {
    deltaElement.classList.remove('insights-delta-up', 'insights-delta-down', 'insights-delta-neutral');
    
    if (deltaValue === null || deltaValue === undefined || isNaN(deltaValue)) {
      deltaElement.textContent = '—';
      deltaElement.classList.add('insights-delta-neutral');
    } else {
      const deltaNum = Number(deltaValue);
      const arrow = deltaNum > 0 ? '▲ ' : deltaNum < 0 ? '▼ ' : '';
      deltaElement.textContent = `${arrow}${Math.abs(deltaNum).toFixed(1)}%`;
      deltaElement.classList.add(deltaNum > 0 ? 'insights-delta-up' : deltaNum < 0 ? 'insights-delta-down' : 'insights-delta-neutral');
    }
  }
}

// Enhanced helper function to format peso amounts
function formatInsightsPesos(amount) {
  const numAmount = Number(amount) || 0;
  return `₱${numAmount.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}

// Update patterns with styled tags (enhanced for 14-day data)
function updateInsightsPatterns(elementId, patternsArray) {
  const patternsElement = document.getElementById(elementId);
  if (patternsElement && Array.isArray(patternsArray)) {
    if (patternsArray.length === 0) {
      patternsElement.innerHTML = '<span class="insights-risk-factor-tag">No patterns identified</span>';
    } else {
      patternsElement.innerHTML = patternsArray.map(pattern => 
        `<span class="insights-risk-factor-tag">${escapeHtml(pattern)}</span>`
      ).join('');
    }
  } else if (patternsElement) {
    patternsElement.innerHTML = '<span class="insights-risk-factor-tag">Loading patterns...</span>';
  }
}

// Update risk factors with severity styling (enhanced)
function updateInsightsRiskFactors(elementId, factorsArray) {
  const factorsElement = document.getElementById(elementId);
  if (factorsElement && Array.isArray(factorsArray)) {
    if (factorsArray.length === 0) {
      factorsElement.innerHTML = '<span class="insights-risk-factor-tag">No risk factors detected</span>';
    } else {
      factorsElement.innerHTML = factorsArray.map(factor => {
        const severityClass = getRiskFactorSeverity(factor);
        return `<span class="insights-risk-factor-tag ${severityClass}">${escapeHtml(factor)}</span>`;
      }).join('');
    }
  } else if (factorsElement) {
    factorsElement.innerHTML = '<span class="insights-risk-factor-tag">Loading risk factors...</span>';
  }
}

// Generate focus area from trend data
function generateFocusAreaFromTrends(data) {
  const riskLevel = data.riskLevel || 'Low';
  const trendDirection = data.trendDirection || 'Stable';
  const loyaltyRate = Number(data.loyaltyRate) || 0;
  
  if (riskLevel === 'High') {
    return 'Immediate Retention';
  } else if (riskLevel === 'Medium') {
    return 'Customer Engagement';
  } else if (trendDirection === 'Needs Attention') {
    return 'Recovery Strategy';
  } else if (trendDirection === 'Positive Growth') {
    return 'Growth Acceleration';
  } else if (loyaltyRate < 70) {
    return 'Loyalty Building';
  } else {
    return 'Maintenance & Growth';
  }
}

// Calculate opportunity score from 14-day data
function calculateOpportunityScoreFromData(data) {
  const riskPercentage = Number(data.riskPercentage) || 0;
  const loyaltyRate = Number(data.loyaltyRate) || 0;
  const trendDirection = data.trendDirection || 'Stable';
  
  let baseScore = loyaltyRate;
  
  // Adjust based on trend
  if (trendDirection === 'Positive Growth') {
    baseScore += 15;
  } else if (trendDirection === 'Needs Attention') {
    baseScore -= 20;
  } else if (trendDirection === 'Mixed Signals') {
    baseScore -= 5;
  }
  
  // Adjust based on risk
  baseScore -= (riskPercentage * 0.5);
  
  // Ensure score is within bounds
  baseScore = Math.max(0, Math.min(100, baseScore));
  
  if (baseScore >= 80) {
    return 'High (A+)';
  } else if (baseScore >= 60) {
    return 'Good (B+)';
  } else if (baseScore >= 40) {
    return 'Fair (C+)';
  } else {
    return 'Needs Focus';
  }
}

// Enhanced risk factor severity detection
function getRiskFactorSeverity(factor) {
  const factorText = factor.toLowerCase();
  
  // High severity indicators
  if (factorText.includes('high churn') || factorText.includes('significant') || 
      factorText.includes('major') || factorText.includes('critical') ||
      factorText.includes('urgent') || factorText.includes('declining') ||
      factorText.includes('intervention recommended')) {
    return 'insights-high-severity';
  }
  
  // Medium severity indicators  
  if (factorText.includes('moderate') || factorText.includes('medium') ||
      factorText.includes('attention needed') || factorText.includes('concern') || 
      factorText.includes('drop') || factorText.includes('decline') ||
      factorText.includes('revenue attention')) {
    return 'insights-medium-severity';
  }
  
  return '';
}

// Enhanced error handling
function showCustomerInsightsError(errorMessage) {
  console.error('Customer Insights Error:', errorMessage);
  
  const errorElements = [
    'insightsLoyalCustomers', 'insightsAvgPurchaseValue', 
    'insightsRiskLevel', 'insightsTrendDirection',
    'insightsLoyaltyRate', 'insightsRiskPercentage',
    'insightsPerformanceTrend', 'insightsFocusArea', 
    'insightsOpportunityScore', 'insightsTrendDirectionMain',
    'insightsFocusAreaMain', 'insightsPerformanceTrendMain',
    'insightsOpportunityScoreMain'
  ];
  
  errorElements.forEach(elementId => {
    updateInsightsElement(elementId, 'Error');
  });
  
  // Update risk badge with error state
  const riskBadgeElement = document.getElementById('insightsRiskBadge');
  if (riskBadgeElement) {
    riskBadgeElement.textContent = 'Error Loading Risk Data';
    riskBadgeElement.className = 'insights-risk-badge insights-high-severity';
  }
  
  // Update pattern displays with error
  const patternElements = [
    'insightsSegmentationPatterns', 'insightsPurchasePatterns', 
    'insightsRiskFactors', 'insightsTrendPatterns'
  ];
  
  patternElements.forEach(elementId => {
    const element = document.getElementById(elementId);
    if (element) {
      element.innerHTML = `<span class="insights-risk-factor-tag insights-high-severity">Error: ${escapeHtml(errorMessage)}</span>`;
    }
  });
}

// Enhanced API call function
async function customerInsightsApiCall(endpointUrl) {
  try {
    console.log('Making API call to:', endpointUrl);
    
    const apiResponse = await fetch(endpointUrl, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'same-origin'
    });
    
    if (!apiResponse.ok) {
      throw new Error(`HTTP ${apiResponse.status}: ${apiResponse.statusText}`);
    }
    
    const responseData = await apiResponse.json();
    console.log('API response received:', responseData);
    
    if (!responseData.success) {
      throw new Error(responseData.message || 'API returned error');
    }
    
    return responseData.data || responseData;
    
  } catch (apiError) {
    console.error('Customer insights API error:', apiError);
    throw new Error(`Failed to fetch insights data: ${apiError.message}`);
  }
}

// Enhanced auto-refresh with better interval management
function startCustomerInsightsAutoRefresh() {
  // Clear any existing interval
  if (window.customerInsightsRefreshInterval) {
    clearInterval(window.customerInsightsRefreshInterval);
  }
  
  return setInterval(() => {
    console.log('Auto-refreshing customer insights with 14-day analysis...');
    loadCustomerInsightsData();
  }, 120000); // Refresh every 2 minutes for better performance
}

// Enhanced initialization
function initializeCustomerInsights() {
  try {
    console.log('Initializing enhanced customer insights with 14-day analysis...');
    
    // Load initial data
    loadCustomerInsightsData();
    
    // Start auto-refresh
    const refreshInterval = startCustomerInsightsAutoRefresh();
    window.customerInsightsRefreshInterval = refreshInterval;
    
    console.log('Enhanced customer insights initialized successfully');
    
  } catch (error) {
    console.error('Failed to initialize customer insights:', error);
    showCustomerInsightsError('Initialization failed: ' + error.message);
  }
}

// Enhanced cleanup function
function cleanupCustomerInsights() {
  if (window.customerInsightsRefreshInterval) {
    clearInterval(window.customerInsightsRefreshInterval);
    window.customerInsightsRefreshInterval = null;
    console.log('Customer insights auto-refresh stopped');
  }
}

// Manual refresh function
function refreshCustomerInsights() {
  console.log('Manual refresh triggered for customer insights');
  loadCustomerInsightsData();
}

// Utility function to escape HTML
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text.toString();
  return div.innerHTML;
}

// Export functions for global access
window.loadCustomerInsights = loadCustomerInsightsData;
window.initializeCustomerInsights = initializeCustomerInsights;
window.cleanupCustomerInsights = cleanupCustomerInsights;
window.refreshCustomerInsights = refreshCustomerInsights;

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, checking for customer insights container...');
  // Check if customer insights container exists before initializing
  if (document.getElementById('customer-insights')) {
    console.log('Customer insights container found, initializing...');
    initializeCustomerInsights();
  } else {
    console.log('Customer insights container not found on this page');
  }
});

// Cleanup on page unload
window.addEventListener('beforeunload', cleanupCustomerInsights);

// Additional debugging helper
window.debugCustomerInsights = function() {
  console.log('=== Customer Insights Debug Info ===');
  console.log('Container exists:', !!document.getElementById('customer-insights'));
  console.log('Refresh interval active:', !!window.customerInsightsRefreshInterval);
  console.log('Element check:');
  
  const elementsToCheck = [
    'insightsLoyalCustomers', 'insightsAvgPurchaseValue', 
    'insightsRiskLevel', 'insightsTrendDirection', 'insightsRiskBadge'
  ];
  
  elementsToCheck.forEach(id => {
    const element = document.getElementById(id);
    console.log(`- ${id}:`, element ? 'Found' : 'Missing', element?.textContent || '');
  });
};



  

/* Enhanced Recommendations Display with Categories */
async function refreshRecommendations() {
  try {
    const j = await apiTry([
      'api/reports/strategic_recommendation.php',
      'api/reports/stratigic_recommendation.php'
    ]);

    const items = Array.isArray(j.recommendations) ? j.recommendations : [];
    if (!items.length) {
      showNoRecommendations();
      return;
    }

    const grid = document.querySelector('#recommendations .recommendations-grid');
    if (!grid) return;

    // Show AI badge if powered by AI
    const aiPowered = j.ai_powered;
    const headerBadge = aiPowered 
      ? '<span class="ai-badge"><i class="fas fa-brain"></i> AI-Powered</span>' 
      : '';

    // Update header
    const pageHeader = document.querySelector('#recommendations .page-header h1');
    if (pageHeader && aiPowered) {
      pageHeader.innerHTML = `
        <i class="fas fa-lightbulb"></i> Strategic Store Recommendations 
        ${headerBadge}
      `;
    }

    // Category icons mapping
    const categoryIcons = {
      'Operations': 'fa-cogs',
      'Merchandising': 'fa-store',
      'Promotions': 'fa-tags',
      'Inventory': 'fa-boxes',
      'Experience': 'fa-smile',
      'Traffic': 'fa-chart-line'
    };

    grid.innerHTML = items.map(it => {
      const pri = String(it.priority || 'medium').toLowerCase();
      const cl = pri === 'high' ? 'priority-high' 
               : pri === 'low' ? 'priority-low' 
               : 'priority-medium';
      const head = pri === 'high' ? 'High Priority' 
                 : pri === 'low' ? 'Low Priority' 
                 : 'Medium Priority';

      // Effectiveness score and bar
      const effectiveness = parseInt(it.effectiveness || 75);
      const effClass = effectiveness >= 80 ? 'eff-high' 
                     : effectiveness >= 60 ? 'eff-medium' 
                     : 'eff-low';

      // Category badge
      const category = it.category || 'General';
      const categoryIcon = categoryIcons[category] || 'fa-lightbulb';
      const categoryBadge = `
        <span class="category-badge">
          <i class="fas ${categoryIcon}"></i> ${category}
        </span>
      `;

      // Metrics display
      const metrics = Array.isArray(it.metrics) && it.metrics.length
        ? it.metrics.filter(m => m && m.trim())
        : [
            it.impact ? `Impact: ${it.impact}` : null,
            it.eta ? `Timeline: ${it.eta}` : null,
            it.cost ? `Cost: ${it.cost}` : null
          ].filter(Boolean);

      // AI badge on card
      const aiCardBadge = it.ai_generated 
        ? '<span class="ai-mini-badge" title="AI Generated"><i class="fas fa-sparkles"></i></span>' 
        : '';

      // Reasoning tooltip
      const reasoning = it.reasoning 
        ? `<div class="rec-reasoning">
             <i class="fas fa-info-circle"></i> 
             <span>${escapeHtml(it.reasoning)}</span>
           </div>` 
        : '';

      return `
        <div class="recommendation-item ${cl}" data-category="${category}">
          <div class="rec-header">
            <div class="rec-header-left">
              <i class="fas fa-bolt"></i>
              <span class="rec-priority">${head}</span>
            </div>
            <div class="rec-header-right">
              ${categoryBadge}
              ${aiCardBadge}
            </div>
          </div>
          
          <h4>${escapeHtml(String(it.title || 'Recommendation'))}</h4>
          <p>${escapeHtml(String(it.description || '').trim())}</p>
          
          <div class="rec-effectiveness">
            <div class="eff-label">
              <span><i class="fas fa-chart-bar"></i> Success Probability</span>
              <strong class="${effClass}">${effectiveness}%</strong>
            </div>
            <div class="eff-bar">
              <div class="eff-fill ${effClass}" style="width: ${effectiveness}%"></div>
            </div>
          </div>
          
          ${reasoning}
          
          <div class="rec-metrics">
            ${metrics.map(m => `<span><i class="fas fa-check-circle"></i> ${escapeHtml(String(m))}</span>`).join('')}
          </div>

          <div class="rec-actions">
            <button class="btn-implement" onclick="markAsImplemented(this)" data-title="${escapeHtml(it.title)}">
              <i class="fas fa-check"></i> Mark as Implemented
            </button>
          </div>
        </div>
      `;
    }).join('');

    // Add filter buttons if not already present
    addCategoryFilters();

  } catch (e) {
    console.warn('[Recommendations]', e.message);
    showErrorRecommendations(e.message);
  }
}

// Add category filter buttons
function addCategoryFilters() {
  const pageHeader = document.querySelector('#recommendations .page-header');
  if (!pageHeader || document.querySelector('.category-filters')) return;

  const categories = ['All', 'Operations', 'Merchandising', 'Promotions', 'Inventory', 'Experience', 'Traffic'];
  const filterHtml = `
    <div class="category-filters">
      ${categories.map(cat => 
        `<button class="filter-btn ${cat === 'All' ? 'active' : ''}" 
                onclick="filterByCategory('${cat}')" 
                data-category="${cat}">
          ${cat}
        </button>`
      ).join('')}
    </div>
  `;
  
  pageHeader.insertAdjacentHTML('beforeend', filterHtml);
}

// Filter recommendations by category
function filterByCategory(category) {
  const items = document.querySelectorAll('.recommendation-item');
  const buttons = document.querySelectorAll('.filter-btn');
  
  // Update active button
  buttons.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.category === category);
  });

  // Show/hide items
  items.forEach(item => {
    if (category === 'All') {
      item.style.display = '';
      item.style.animation = 'fadeIn 0.3s ease';
    } else {
      const matches = item.dataset.category === category;
      item.style.display = matches ? '' : 'none';
      if (matches) item.style.animation = 'fadeIn 0.3s ease';
    }
  });
}

// Mark recommendation as implemented
function markAsImplemented(button) {
  const card = button.closest('.recommendation-item');
  const title = button.dataset.title;
  
  if (confirm(`Mark "${title}" as implemented?`)) {
    card.classList.add('implemented');
    button.innerHTML = '<i class="fas fa-check-double"></i> Implemented';
    button.disabled = true;
    
    // Optional: Send to backend to track
    // fetch('api/track_implementation.php', { method: 'POST', body: JSON.stringify({ title }) });
    
    // Show success message
    showToast('✅ Recommendation marked as implemented!', 'success');
  }
}

// Toast notification
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  
  setTimeout(() => toast.classList.add('show'), 100);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function showNoRecommendations() {
  const grid = document.querySelector('#recommendations .recommendations-grid');
  if (!grid) return;
  grid.innerHTML = `
    <div class="no-recommendations">
      <i class="fas fa-check-circle"></i>
      <h3>All Systems Operating Smoothly!</h3>
      <p>Your store metrics look healthy. Continue monitoring your daily performance and maintaining current operations.</p>
      <small>Recommendations will appear here when optimization opportunities are detected.</small>
    </div>
  `;
}

function showErrorRecommendations(error) {
  const grid = document.querySelector('#recommendations .recommendations-grid');
  if (!grid) return;
  grid.innerHTML = `
    <div class="error-recommendations">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Unable to Generate Recommendations</h3>
      <p>Please refresh the page or check your data inputs. If the issue persists, contact support.</p>
      <small>${escapeHtml(error)}</small>
      <button onclick="refreshRecommendations()" class="btn-retry">
        <i class="fas fa-sync"></i> Retry
      </button>
    </div>
  `;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Auto-refresh every 10 minutes (reduced frequency)
setInterval(refreshRecommendations, 600000);

// Export for external use
window.filterByCategory = filterByCategory;
window.markAsImplemented = markAsImplemented;

  
 

  
  
  
  
  
  
  
  
  
  
  

  /* -------------------- Reports (api/) -------------------- */
  async function generateRetentionReport() {
    try {
      const j = await apiTry(['api/reports/retention_report.php', 'api/reports/retention_report.php']);
      const set = (id,val) => { const el = $('#'+id); if (el) el.textContent = val; };
      set('previewRetention', pct(j.retentionRate || 0, 2));
      set('previewChurn',     pct(j.churnRate     || 0, 2));
      set('previewAtRisk',    String(j.atRiskCount || 0));
      alert('Retention report ready.');
    } catch (e) { alert('Retention report error: ' + e.message); }
  }
  async function generateRevenueReport() {
    try {
      const j = await apiTry(['api/reports/revenue_report.php', 'api/reports/revenue_report.php']);
      const set = (id,val) => { const el = $('#'+id); if (el) el.textContent = val; };
      set('previewRevenueSaved', peso(j.revenueSaved || 0));
      set('previewCLV',          peso(j.clvImpact    || 0));
      set('previewROI',          pct(j.roi || 0, 2));
      alert('Revenue report ready.');
    } catch (e) { alert('Revenue report error: ' + e.message); }
  }
  async function generateBehaviorReport() {
    try {
      const j = await apiTry(['api/reports/behavior_report.php', 'api/reports/behavior_report.php']);
      const set = (id,val) => { const el = $('#'+id); if (el) el.textContent = val; };
      set('previewFrequency', String(Number(j.avgFrequency || 0).toFixed(0)));
      set('previewValue',     peso(j.avgValue || 0));
      set('previewLoyalty',   pct(j.loyaltyRate || 0, 2));
      alert('Behavior report ready.');
    } catch (e) { alert('Behavior report error: ' + e.message); }
  }
  window.generateRetentionReport = generateRetentionReport;
  window.generateRevenueReport   = generateRevenueReport;
  window.generateBehaviorReport  = generateBehaviorReport;
  
  

/* -------------------- Profile & settings (api/) -------------------- */
/* -------------------- Profile & settings -------------------- */
async function loadProfile() {
  try {
    const r = await api('api/profile.php?action=me&ts=' + Date.now());
    const u = r.user || {};
    
    // Set avatar (use icon field from database)
    const avatarUrl = u.avatar_url || u.icon || 'uploads/avatars/default-icon.png';
    const avatarEl = document.getElementById('profileAvatar');
    if (avatarEl) avatarEl.src = avatarUrl;
    
    // Set display name (use display_name from API or build from firstname/lastname)
    const display = u.display_name || u.username || 'User';
    const nameEl = document.getElementById('profileName');
    if (nameEl) nameEl.textContent = display;
    
    // Set role
    const role = u.role || 'User';
    const roleEl = document.getElementById('profileRole');
    if (roleEl) roleEl.textContent = role;
    
    // Fill form fields
    const fn = document.getElementById('profileFirstName');
    const ln = document.getElementById('profileLastName');
    const em = document.getElementById('profileEmail');
    if (fn) fn.value = u.firstname || '';
    if (ln) ln.value = u.lastname  || '';
    if (em) em.value = u.email     || '';
    
    // Two-factor toggle
    const tf = document.getElementById('twoFactorToggle');
    if (tf) tf.checked = parseInt(u.two_factor_enabled ?? 0, 10) === 1;
    
    // Refresh login history if function exists
    if (typeof refreshLoginHistory === 'function') {
      await refreshLoginHistory();
    }
    
    console.log('Profile loaded:', display);
  } catch (e) {
    console.error('Profile error:', e.message);
    alert('Failed to load profile: ' + e.message);
  }
}


// When profile page becomes active, load the profile
document.addEventListener('click', function(e) {
  // If user clicks on profile link/button
  const profileLink = e.target.closest('[data-page="profile"], [onclick*="showPage(\'profile\')"]');
  if (profileLink) {
    setTimeout(() => loadProfile(), 100);
  }
});

// Also load on page load if profile is already active
document.addEventListener('DOMContentLoaded', function() {
  const profilePage = document.getElementById('profile');
  if (profilePage && profilePage.classList.contains('active')) {
    loadProfile();
  }
});

window.updateProfile = async function () {
  try {
    const payload = {
      firstname: (document.getElementById('profileFirstName')?.value || '').trim(),
      lastname:  (document.getElementById('profileLastName')?.value  || '').trim(),
      email:     (document.getElementById('profileEmail')?.value     || '').trim()
      // company removed on purpose
    };
    await api('api/profile.php?action=update_profile', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    alert('Profile updated');
    await loadProfile();
  } catch (e) {
    alert(e.message);
  }
};

window.changePassword = async function () {
  try {
    await api('api/profile.php?action=change_password', {
      method: 'POST',
      body: JSON.stringify({
        current_password: document.getElementById('currentPassword')?.value || '',
        new_password:     document.getElementById('newPassword')?.value     || '',
        confirm_password: document.getElementById('confirmNewPassword')?.value || ''
      })
    });
    alert('Password changed');
    document.querySelector('.security-form')?.reset();
  } catch (e) {
    alert(e.message);
  }
};



window.uploadAvatar = function () {
  const inp = document.createElement('input');
  inp.type = 'file';
  inp.accept = 'image/png,image/jpeg,image/webp';
  inp.onchange = async () => {
    if (!inp.files || !inp.files[0]) return;
    const fd = new FormData();
    fd.append('avatar', inp.files[0]);
    try {
      const res = await fetch(apiPath('api/profile.php?action=upload_avatar'), {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]')?.content || '') }
      });
      const j = await res.json();
      if (!j.success) throw new Error(j.message || 'Upload failed');
      
      // Update avatar with cache-busting timestamp
      const avatarEl = document.getElementById('profileAvatar');
      if (avatarEl) avatarEl.src = j.avatar_url + '?t=' + Date.now();
      
      alert('Avatar updated successfully!');
    } catch (e) { 
      alert('Failed to upload avatar: ' + e.message); 
    }
  };
  inp.click();
};




  /* -------------------- Settings (api/) -------------------- */
  window.updateRefreshInterval = async function (val) {
    try { await api('api/settings_update.php', { method: 'POST', body: JSON.stringify({ refresh_interval: String(val || '6') }) }); }
    catch (e) { console.warn('[Settings refresh_interval]', e.message); }
  };
  window.toggleDarkMode = async function (checked) {
    try {
      await api('api/settings_update.php', { method: 'POST', body: JSON.stringify({ dark_mode: checked ? 1 : 0 }) });
      document.documentElement.classList.toggle('dark', !!checked);
    } catch (e) { console.warn('[Settings dark_mode]', e.message); }
  };
  async function loadInitialSettings() {
    try {
      const j = await api('api/get_profile_settings.php?ts=' + Date.now());
      if (typeof j.dark_mode !== 'undefined') {
        const dm = !!Number(j.dark_mode);
        $('#darkModeToggle') && ($('#darkModeToggle').checked = dm);
        document.documentElement.classList.toggle('dark', dm);
      }
      if (typeof j.refresh_interval !== 'undefined') {
        $('#refreshInterval') && ($('#refreshInterval').value = Number(j.refresh_interval || 6));
      }
    } catch { /* non-blocking */ }
  }

  /* -------------------- boot -------------------- */
  document.addEventListener('DOMContentLoaded', async () => {
    wireSidebarClicks();
    $('#trafficPeriod') && $('#trafficPeriod').addEventListener('change', updateTrafficChart);
    showPage('dashboard');

   await Promise.allSettled([
  loadDashboard(),
  loadCharts(),
  loadChurnRisk(),
  loadCustomerInsights(),
  loadCustomerMonitoring(),
  refreshRecommendations(),
  loadProfile(),
  loadInitialSettings(),
  generateChurnReport()      // ← add this
]);

  });

  // expose for inline HTML
  window.updateTrafficChart = updateTrafficChart;
  window.loadCustomerInsights = loadCustomerInsights;
  window.loadCustomerMonitoring = loadCustomerMonitoring;
})();
</script>



<script>
window.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const page = params.get('page');

  // If login redirected with ?page=data-input → open that section
  if (page === 'data-input') {
    showPage('data-input');
  } else {
    // Default fallback
    showPage('dashboard');
  }
});
</script>


<script>
// Auto-calculation functionality for receipt counts, customer traffic, and sales volume
document.addEventListener('DOMContentLoaded', function() {
    // Get all the input elements
    const morningReceiptCount = document.getElementById('morningReceiptCount');
    const swingReceiptCount = document.getElementById('swingReceiptCount');
    const graveyardReceiptCount = document.getElementById('graveyardReceiptCount');
    const receiptCount = document.getElementById('receiptCount');
    const customerTraffic = document.getElementById('customerTraffic');
    
    const morningSalesVolume = document.getElementById('morningSalesVolume');
    const swingSalesVolume = document.getElementById('swingSalesVolume');
    const graveyardSalesVolume = document.getElementById('graveyardSalesVolume');
    const salesVolume = document.getElementById('salesVolume');

    // Function to calculate receipt count total and update customer traffic
    function calculateReceiptTotal() {
        const morning = parseInt(morningReceiptCount.value) || 0;
        const swing = parseInt(swingReceiptCount.value) || 0;
        const graveyard = parseInt(graveyardReceiptCount.value) || 0;
        
        const total = morning + swing + graveyard;
        
        // Update receipt count
        receiptCount.value = total;
        
        // Update customer traffic (same as receipt count based on your requirement)
        customerTraffic.value = total;
        
        // Trigger change events to ensure any other listeners are notified
        receiptCount.dispatchEvent(new Event('input'));
        customerTraffic.dispatchEvent(new Event('input'));
    }

    // Function to calculate sales volume total
    function calculateSalesTotal() {
        const morning = parseFloat(morningSalesVolume.value) || 0;
        const swing = parseFloat(swingSalesVolume.value) || 0;
        const graveyard = parseFloat(graveyardSalesVolume.value) || 0;
        
        const total = morning + swing + graveyard;
        
        // Update sales volume
        salesVolume.value = total.toFixed(2);
        
        // Trigger change event
        salesVolume.dispatchEvent(new Event('input'));
    }

    // Add event listeners for receipt count calculations
    if (morningReceiptCount && swingReceiptCount && graveyardReceiptCount) {
        morningReceiptCount.addEventListener('input', calculateReceiptTotal);
        swingReceiptCount.addEventListener('input', calculateReceiptTotal);
        graveyardReceiptCount.addEventListener('input', calculateReceiptTotal);
    }

    // Add event listeners for sales volume calculations
    if (morningSalesVolume && swingSalesVolume && graveyardSalesVolume) {
        morningSalesVolume.addEventListener('input', calculateSalesTotal);
        swingSalesVolume.addEventListener('input', calculateSalesTotal);
        graveyardSalesVolume.addEventListener('input', calculateSalesTotal);
    }

    // Make the total fields readonly to prevent manual editing
    if (receiptCount) {
        receiptCount.setAttribute('readonly', true);
        receiptCount.style.backgroundColor = '#f5f5f5';
        receiptCount.style.cursor = 'not-allowed';
    }
    
    if (customerTraffic) {
        customerTraffic.setAttribute('readonly', true);
        customerTraffic.style.backgroundColor = '#f5f5f5';
        customerTraffic.style.cursor = 'not-allowed';
    }
    
    if (salesVolume) {
        salesVolume.setAttribute('readonly', true);
        salesVolume.style.backgroundColor = '#f5f5f5';
        salesVolume.style.cursor = 'not-allowed';
    }

    // Calculate initial totals if any values are already present
    calculateReceiptTotal();
    calculateSalesTotal();
});
</script>
<script src="assets/js/loginhistory.js"></script>
</body>
</html>
