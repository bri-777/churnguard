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
   * Display AI summary in container - ENHANCED PROFESSIONAL DESIGN
   */
  function displaySummary(container, summary, chartType) {
    container.innerHTML = `
      <div class="ai-summary-content" style="
        position: relative;
        padding: 0;
        background: linear-gradient(to bottom, #ffffff 0%, #fafbfc 100%);
        border-radius: 0.875rem;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
        animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid rgba(94, 114, 228, 0.12);
        transition: all 0.3s ease;
      " onmouseover="this.style.boxShadow='0 4px 16px rgba(94, 114, 228, 0.15), 0 2px 6px rgba(0, 0, 0, 0.08)'; this.style.transform='translateY(-2px)';" 
         onmouseout="this.style.boxShadow='0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05)'; this.style.transform='translateY(0)';">
        
        <!-- Decorative Top Border -->
        <div style="
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          height: 3px;
          background: linear-gradient(90deg, #667EEA 0%, #5E72E4 50%, #764BA2 100%);
          box-shadow: 0 2px 12px rgba(94, 114, 228, 0.4);
        "></div>
        
        <!-- Header Section -->
        <div style="
          padding: 1.125rem 1.375rem 1rem 1.375rem;
          background: linear-gradient(135deg, rgba(102, 126, 234, 0.03) 0%, rgba(94, 114, 228, 0.05) 100%);
          border-bottom: 1px solid rgba(94, 114, 228, 0.08);
        ">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <!-- Left: AI Badge -->
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <!-- AI Icon with Glow -->
              <div style="
                position: relative;
                width: 32px;
                height: 32px;
                border-radius: 0.5rem;
                background: linear-gradient(135deg, #667EEA 0%, #5E72E4 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(94, 114, 228, 0.35);
              ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              
              <!-- Title -->
              <div>
                <div style="
                  font-size: 0.8125rem;
                  font-weight: 800;
                  color: #5E72E4;
                  text-transform: uppercase;
                  letter-spacing: 0.08em;
                  line-height: 1;
                  margin-bottom: 0.25rem;
                ">AI Insights</div>
                <div style="
                  font-size: 0.6875rem;
                  color: #9CA3AF;
                  font-weight: 600;
                  letter-spacing: 0.02em;
                ">Powered by OpenAI GPT-3.5</div>
              </div>
            </div>
            
            <!-- Right: Live Status & Tooltip -->
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <!-- Live Indicator -->
              <div style="
                display: flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.375rem 0.625rem;
                background: rgba(16, 185, 129, 0.08);
                border-radius: 999px;
                border: 1px solid rgba(16, 185, 129, 0.2);
              ">
                <div style="
                  width: 6px;
                  height: 6px;
                  border-radius: 50%;
                  background: #10B981;
                  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
                  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
                "></div>
                <span style="
                  font-size: 0.6875rem;
                  color: #059669;
                  font-weight: 700;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                ">Live</span>
              </div>
              
              <!-- Tooltip Trigger -->
              <div class="chart-tooltip-trigger" style="
                cursor: help;
                position: relative;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: rgba(94, 114, 228, 0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
              " onmouseover="this.style.background='rgba(94, 114, 228, 0.2)'; this.style.transform='scale(1.1)';" 
                 onmouseout="this.style.background='rgba(94, 114, 228, 0.1)'; this.style.transform='scale(1)';">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5E72E4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                
                <!-- Tooltip Content -->
                <div class="chart-tooltip" style="
                  position: absolute;
                  right: 0;
                  top: calc(100% + 0.625rem);
                  background: linear-gradient(135deg, rgba(17, 24, 39, 0.97) 0%, rgba(31, 41, 55, 0.97) 100%);
                  backdrop-filter: blur(12px);
                  color: white;
                  padding: 1rem 1.125rem;
                  border-radius: 0.625rem;
                  font-size: 0.8125rem;
                  line-height: 1.6;
                  width: 260px;
                  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.1);
                  z-index: 1000;
                  opacity: 0;
                  visibility: hidden;
                  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                  pointer-events: none;
                  font-weight: 500;
                  transform: translateY(-4px);
                ">
                  <div style="
                    font-weight: 700;
                    margin-bottom: 0.375rem;
                    color: #E0E7FF;
                    font-size: 0.875rem;
                  ">About This Chart</div>
                  ${CHART_DESCRIPTIONS[chartType] || 'AI-powered chart analysis and insights.'}
                  <div style="
                    position: absolute;
                    top: -4px;
                    right: 14px;
                    width: 8px;
                    height: 8px;
                    background: rgba(17, 24, 39, 0.97);
                    transform: rotate(45deg);
                  "></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Content Section -->
        <div style="padding: 1.375rem 1.375rem 1.25rem 1.375rem;">
          <div style="display: flex; gap: 1rem; align-items: start;">
            <!-- Left Icon -->
            <div style="
              flex-shrink: 0;
              width: 42px;
              height: 42px;
              border-radius: 0.625rem;
              background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
              display: flex;
              align-items: center;
              justify-content: center;
              box-shadow: 0 2px 8px rgba(94, 114, 228, 0.15);
              position: relative;
            ">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5E72E4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
              <!-- Small pulse indicator -->
              <div style="
                position: absolute;
                top: -2px;
                right: -2px;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #10B981;
                border: 2px solid #fff;
              "></div>
            </div>
            
            <!-- Summary Text -->
            <div style="flex: 1; min-width: 0;">
              <p style="
                margin: 0;
                font-size: 0.9375rem;
                line-height: 1.75;
                color: #374151;
                font-weight: 500;
                letter-spacing: 0.005em;
              ">${summary}</p>
            </div>
          </div>
        </div>
        
        <!-- Footer Section -->
        <div style="
          padding: 0.875rem 1.375rem;
          background: linear-gradient(to bottom, rgba(249, 250, 251, 0.5) 0%, rgba(243, 244, 246, 0.8) 100%);
          border-top: 1px solid rgba(229, 231, 235, 0.8);
          display: flex;
          align-items: center;
          justify-content: space-between;
        ">
          <!-- Timestamp -->
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
            <span style="
              font-size: 0.75rem;
              color: #6B7280;
              font-weight: 600;
            ">Updated ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
          </div>
          
          <!-- Confidence Badge -->
          <div style="
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.08) 100%);
            border-radius: 999px;
            border: 1px solid rgba(16, 185, 129, 0.2);
          ">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span style="
              font-size: 0.6875rem;
              color: #059669;
              font-weight: 700;
              letter-spacing: 0.02em;
            ">High Confidence</span>
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
        tooltip.style.transform = 'translateY(0)';
      });
      trigger.addEventListener('mouseleave', () => {
        tooltip.style.opacity = '0';
        tooltip.style.visibility = 'hidden';
        tooltip.style.transform = 'translateY(-4px)';
      });
    }

    // Add required animations
    if (!document.getElementById('ai-summary-styles')) {
      const style = document.createElement('style');
      style.id = 'ai-summary-styles';
      style.textContent = `
        @keyframes fadeInUp {
          from { 
            opacity: 0; 
            transform: translateY(20px); 
          }
          to { 
            opacity: 1; 
            transform: translateY(0); 
          }
        }
        @keyframes pulse {
          0%, 100% { 
            opacity: 1; 
            transform: scale(1);
          }
          50% { 
            opacity: 0.8; 
            transform: scale(1.1);
          }
        }
        @keyframes shimmer {
          0% { background-position: -200% 0; }
          100% { background-position: 200% 0; }
        }
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    }
  }

  /**
   * Show loading state - ENHANCED DESIGN
   */
  function showLoadingState(container) {
    container.innerHTML = `
      <div class="ai-summary-loading" style="
        position: relative;
        padding: 0;
        background: linear-gradient(to bottom, #ffffff 0%, #fafbfc 100%);
        border-radius: 0.875rem;
        overflow: hidden;
        border: 1px solid rgba(156, 163, 175, 0.2);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      ">
        <!-- Top Border -->
        <div style="
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          height: 3px;
          background: linear-gradient(90deg, #9CA3AF 0%, #6B7280 50%, #9CA3AF 100%);
          background-size: 200% 100%;
          animation: shimmer 2s ease-in-out infinite;
        "></div>
        
        <!-- Header -->
        <div style="
          padding: 1.125rem 1.375rem 1rem 1.375rem;
          background: linear-gradient(135deg, rgba(156, 163, 175, 0.03) 0%, rgba(107, 114, 128, 0.05) 100%);
          border-bottom: 1px solid rgba(156, 163, 175, 0.1);
        ">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <!-- Spinning Icon -->
            <div style="
              width: 32px;
              height: 32px;
              border-radius: 0.5rem;
              background: linear-gradient(135deg, #E5E7EB 0%, #D1D5DB 100%);
              display: flex;
              align-items: center;
              justify-content: center;
            ">
              <div style="
                width: 16px;
                height: 16px;
                border: 2.5px solid #9CA3AF;
                border-top-color: transparent;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
              "></div>
            </div>
            
            <div>
              <div style="
                font-size: 0.8125rem;
                font-weight: 800;
                color: #6B7280;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                line-height: 1;
                margin-bottom: 0.25rem;
              ">Analyzing Data</div>
              <div style="
                font-size: 0.6875rem;
                color: #9CA3AF;
                font-weight: 600;
              ">Generating AI insights...</div>
            </div>
          </div>
        </div>
        
        <!-- Content Skeleton -->
        <div style="padding: 1.375rem;">
          <div style="display: flex; gap: 1rem; align-items: start;">
            <!-- Icon Skeleton -->
            <div style="
              flex-shrink: 0;
              width: 42px;
              height: 42px;
              border-radius: 0.625rem;
              background: linear-gradient(90deg, #F3F4F6 0%, #E5E7EB 50%, #F3F4F6 100%);
              background-size: 200% 100%;
              animation: shimmer 1.5s ease-in-out infinite;
            "></div>
            
            <!-- Text Skeleton -->
            <div style="flex: 1;">
              <div style="
                height: 14px;
                background: linear-gradient(90deg, #F3F4F6 0%, #E5E7EB 50%, #F3F4F6 100%);
                background-size: 200% 100%;
                border-radius: 7px;
                margin-bottom: 0.625rem;
                animation: shimmer 1.5s ease-in-out infinite;
              "></div>
              <div style="
                height: 14px;
                background: linear-gradient(90deg, #F3F4F6 0%, #E5E7EB 50%, #F3F4F6 100%);
                background-size: 200% 100%;
                border-radius: 7px;
                width: 90%;
                margin-bottom: 0.625rem;
                animation: shimmer 1.5s ease-in-out infinite;
                animation-delay: 0.1s;
              "></div>
              <div style="
                height: 14px;
                background: linear-gradient(90deg, #F3F4F6 0%, #E5E7EB 50%, #F3F4F6 100%);
                background-size: 200% 100%;
                border-radius: 7px;
                width: 75%;
                animation: shimmer 1.5s ease-in-out infinite;
                animation-delay: 0.2s;
              "></div>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <div style="
          padding: 0.875rem 1.375rem;
          background: linear-gradient(to bottom, rgba(249, 250, 251, 0.5) 0%, rgba(243, 244, 246, 0.8) 100%);
          border-top: 1px solid rgba(229, 231, 235, 0.8);
        ">
          <div style="
            height: 12px;
            background: linear-gradient(90deg, #F3F4F6 0%, #E5E7EB 50%, #F3F4F6 100%);
            background-size: 200% 100%;
            border-radius: 6px;
            width: 40%;
            animation: shimmer 1.5s ease-in-out infinite;
            animation-delay: 0.3s;
          "></div>
        </div>
      </div>
    `;
  }

  /**
   * Show error state - ENHANCED DESIGN
   */
  function showErrorState(container, errorMessage) {
    container.innerHTML = `
      <div class="ai-summary-error" style="
        position: relative;
        padding: 0;
        background: linear-gradient(to bottom, #ffffff 0%, #fef9f9 100%);
        border-radius: 0.875rem;
        overflow: hidden;
        border: 1px solid rgba(239, 68, 68, 0.2);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
      ">
        <!-- Top Border -->
        <div style="
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          height: 3px;
          background: linear-gradient(90deg, #EF4444 0%, #DC2626 50%, #B91C1C 100%);
        "></div>
        
        <!-- Header -->
        <div style="
          padding: 1.125rem 1.375rem 1rem 1.375rem;
          background: linear-gradient(135deg, rgba(239, 68, 68, 0.03) 0%, rgba(220, 38, 38, 0.05) 100%);
          border-bottom: 1px solid rgba(239, 68, 68, 0.1);
        ">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="
              width: 32px;
              height: 32px;
              border-radius: 0.5rem;
              background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
              display: flex;
              align-items: center;
              justify-content: center;
            ">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
            </div>
            
            <div>
              <div style="
                font-size: 0.8125rem;
                font-weight: 800;
                color: #DC2626;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                line-height: 1;
                margin-bottom: 0.25rem;
              ">Analysis Unavailable</div>
              <div style="
                font-size: 0.6875rem;
                color: #EF4444;
                font-weight: 600;
              ">Unable to generate insights</div>
            </div>
          </div>
        </div>
        
        <!-- Content -->
        <div style="padding: 1.375rem;">
          <div style="display: flex; gap: 1rem; align-items: start;">
            <div style="
              flex-shrink: 0;
              width: 42px;
              height: 42px;
              border-radius: 0.625rem;
              background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
              display: flex;
              align-items: center;
              justify-content: center;
            ">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            
            <div style="flex: 1;">
              <p style="
                margin: 0 0 0.625rem 0;
                font-size: 0.9375rem;
                font-weight: 700;
                color: #991B1B;
                line-height: 1.5;
              ">Cannot Generate AI Insights</p>
              <p style="
                margin: 0;
                font-size: 0.875rem;
                color: #DC2626;
                line-height: 1.65;
                font-weight: 500;
              ">${errorMessage || 'Please try refreshing the page. If the issue persists, contact support.'}</p>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <div style="
          padding: 0.875rem 1.375rem;
          background: linear-gradient(to bottom, rgba(254, 242, 242, 0.5) 0%, rgba(254, 226, 226, 0.3) 100%);
          border-top: 1px solid rgba(239, 68, 68, 0.1);
          display: flex;
          align-items: center;
          gap: 0.5rem;
        ">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
          </svg>
          <span style="
            font-size: 0.75rem;
            color: #B91C1C;
            font-weight: 600;
          ">Error occurred at ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
        </div>
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