// ai-chart-summary.js — AI Chart Summary Generator Module
// Integrates with ChurnGuard charts to provide AI-generated insights

const AIChartSummary = (function() {
  'use strict';

  // Configuration
  const CONFIG = {
    apiEndpoint: 'ai-summary.php',
    retryAttempts: 2,
    retryDelay: 1000,
    cacheEnabled: true,
    cacheDuration: 300000 // 5 minutes
  };

  // Cache for summaries
  const summaryCache = new Map();

  // Tooltip descriptions for each chart type
  const CHART_DESCRIPTIONS = {
    retention: 'Displays customer retention rates over time. Higher percentages indicate better customer loyalty.',
    behavior: 'Shows transaction volume patterns. Helps identify customer engagement trends and activity levels.',
    revenue: 'Tracks revenue trends over the selected period. Useful for identifying revenue fluctuations and growth patterns.',
    trends: 'Visualizes churn risk scores over time. Higher scores indicate increased risk of customer loss.'
  };

  /**
   * Generate AI summary for a chart
   */
  async function generateSummaryForChart(chartInstance, chartType, containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error(`[AI Summary] Container not found: ${containerId}`);
      return;
    }

    // Check if summary already exists
    if (container.querySelector('.ai-summary-content')) {
      console.log(`[AI Summary] Summary already exists for ${chartType}`);
      return;
    }

    // Show loading state
    showLoadingState(container);

    try {
      // Extract chart data
      const chartData = extractChartData(chartInstance, chartType);
      
      // Check cache first
      const cacheKey = generateCacheKey(chartType, chartData);
      if (CONFIG.cacheEnabled && summaryCache.has(cacheKey)) {
        const cached = summaryCache.get(cacheKey);
        if (Date.now() - cached.timestamp < CONFIG.cacheDuration) {
          console.log(`[AI Summary] Using cached summary for ${chartType}`);
          displaySummary(container, cached.summary, chartType);
          return;
        }
      }

      // Fetch AI summary
      const summary = await fetchAISummary(chartType, chartData);
      
      // Cache the result
      if (CONFIG.cacheEnabled) {
        summaryCache.set(cacheKey, {
          summary,
          timestamp: Date.now()
        });
      }

      // Display summary
      displaySummary(container, summary, chartType);

    } catch (error) {
      console.error(`[AI Summary] Error generating summary for ${chartType}:`, error);
      showErrorState(container, error.message);
    }
  }

  /**
   * Extract relevant data from chart instance
   */
  function extractChartData(chartInstance, chartType) {
    if (!chartInstance || !chartInstance.data) {
      throw new Error('Invalid chart instance');
    }

    const data = {
      labels: chartInstance.data.labels || [],
      datasets: chartInstance.data.datasets || []
    };

    // Add specific metrics based on chart type
    const metrics = {};
    
    switch (chartType) {
      case 'retention':
        metrics.currentRetention = document.getElementById('currentRetention')?.textContent || 'N/A';
        metrics.churnRate = document.getElementById('churnRate')?.textContent || 'N/A';
        break;
      case 'behavior':
        metrics.avgFrequency = document.getElementById('avgFrequency')?.textContent || 'N/A';
        metrics.avgValue = document.getElementById('avgValue')?.textContent || 'N/A';
        break;
      case 'revenue':
        metrics.potentialLoss = document.getElementById('potentialLoss')?.textContent || 'N/A';
        metrics.revenueSaved = document.getElementById('revenueSaved')?.textContent || 'N/A';
        break;
      case 'trends':
        metrics.riskLevel = document.getElementById('riskLevel')?.textContent || 'N/A';
        metrics.atRiskCount = document.getElementById('atRiskCount')?.textContent || 'N/A';
        break;
    }

    return { chartData: data, metrics };
  }

  /**
   * Fetch AI summary from backend
   */
  async function fetchAISummary(chartType, data, attempt = 1) {
    try {
      const response = await fetch(CONFIG.apiEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          chartType,
          chartData: data.chartData,
          metrics: data.metrics
        })
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const result = await response.json();
      
      if (result.status !== 'success' || !result.summary) {
        throw new Error(result.message || 'Invalid response format');
      }

      return result.summary;

    } catch (error) {
      // Retry logic
      if (attempt < CONFIG.retryAttempts) {
        console.log(`[AI Summary] Retry attempt ${attempt + 1} for ${chartType}`);
        await sleep(CONFIG.retryDelay);
        return fetchAISummary(chartType, data, attempt + 1);
      }
      throw error;
    }
  }

  /**
   * Display AI summary in container
   */
  function displaySummary(container, summary, chartType) {
    container.innerHTML = `
      <div class="ai-summary-content" style="
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
        border-left: 4px solid #5E72E4;
        border-radius: 0.5rem;
        position: relative;
        animation: fadeIn 0.4s ease-in;
      ">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem;">
          <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5E72E4" stroke-width="2">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
              </svg>
              <span style="font-size: 0.85rem; font-weight: 800; color: #5E72E4; text-transform: uppercase; letter-spacing: 0.5px;">
                AI Insights
              </span>
            </div>
            <p style="
              margin: 0;
              font-size: 0.95rem;
              line-height: 1.6;
              color: #374151;
              font-weight: 500;
            ">${summary}</p>
          </div>
          <div class="chart-tooltip-trigger" style="
            position: relative;
            cursor: help;
            flex-shrink: 0;
          ">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <div class="chart-tooltip" style="
              position: absolute;
              right: 0;
              top: 100%;
              margin-top: 0.5rem;
              background: rgba(0, 0, 0, 0.9);
              color: white;
              padding: 0.75rem 1rem;
              border-radius: 0.5rem;
              font-size: 0.85rem;
              line-height: 1.5;
              width: 220px;
              box-shadow: 0 4px 12px rgba(0,0,0,0.3);
              z-index: 1000;
              opacity: 0;
              visibility: hidden;
              transition: opacity 0.2s, visibility 0.2s;
              pointer-events: none;
            ">
              ${CHART_DESCRIPTIONS[chartType] || 'Chart analysis and insights.'}
            </div>
          </div>
        </div>
      </div>
    `;

    // Add tooltip hover functionality
    const trigger = container.querySelector('.chart-tooltip-trigger');
    const tooltip = container.querySelector('.chart-tooltip');
    
    if (trigger && tooltip) {
      trigger.addEventListener('mouseenter', () => {
        tooltip.style.opacity = '1';
        tooltip.style.visibility = 'visible';
      });
      trigger.addEventListener('mouseleave', () => {
        tooltip.style.opacity = '0';
        tooltip.style.visibility = 'hidden';
      });
    }
  }

  /**
   * Show loading state
   */
  function showLoadingState(container) {
    container.innerHTML = `
      <div class="ai-summary-loading" style="
        padding: 1rem 1.25rem;
        background: #F6F9FC;
        border-left: 4px solid #D1D5DB;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      ">
        <div style="
          width: 18px;
          height: 18px;
          border: 3px solid #E5E7EB;
          border-top-color: #5E72E4;
          border-radius: 50%;
          animation: spin 0.8s linear infinite;
        "></div>
        <span style="font-size: 0.9rem; color: #6b7280; font-weight: 600;">
          Generating AI insights...
        </span>
      </div>
    `;

    // Add spin animation if not already added
    if (!document.getElementById('ai-summary-styles')) {
      const style = document.createElement('style');
      style.id = 'ai-summary-styles';
      style.textContent = `
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(-10px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `;
      document.head.appendChild(style);
    }
  }

  /**
   * Show error state
   */
  function showErrorState(container, errorMessage) {
    container.innerHTML = `
      <div class="ai-summary-error" style="
        padding: 1rem 1.25rem;
        background: #FEF2F2;
        border-left: 4px solid #EF4444;
        border-radius: 0.5rem;
      ">
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span style="font-size: 0.85rem; font-weight: 700; color: #EF4444;">
            Unable to generate insights
          </span>
        </div>
        <p style="margin: 0; font-size: 0.85rem; color: #991B1B;">
          ${errorMessage || 'Please try again later'}
        </p>
      </div>
    `;
  }

  /**
   * Generate cache key
   */
  function generateCacheKey(chartType, data) {
    const dataStr = JSON.stringify(data);
    return `${chartType}_${simpleHash(dataStr)}`;
  }

  /**
   * Simple string hash function
   */
  function simpleHash(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    return Math.abs(hash).toString(36);
  }

  /**
   * Sleep helper
   */
  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  // Public API
  return {
    generateSummaryForChart,
    clearCache: () => summaryCache.clear()
  };
})();

// Make available globally
window.AIChartSummary = AIChartSummary;

console.log('[AI Chart Summary] Module loaded successfully');