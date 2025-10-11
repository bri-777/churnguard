// Export Modal Functions
function showExportModal() {
  document.getElementById('exportModal').style.display = 'block';
}

function closeExportModal() {
  document.getElementById('exportModal').style.display = 'none';
}

// Export to PDF
async function exportToPDF() {
  const includeAll = document.getElementById('includeAllTabs').checked;
  closeExportModal();
  
  // Show loading indicator
  const loadingDiv = document.createElement('div');
  loadingDiv.id = 'exportLoading';
  loadingDiv.style.cssText = `
    position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:2rem 3rem; border-radius:12px; box-shadow:0 0 50px rgba(0,0,0,.3);
    z-index:10000; text-align:center; font-weight:700;
  `;
  loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:2rem; color:#5E72E4;"></i><br><br>Generating PDF...';
  document.body.appendChild(loadingDiv);

  try {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    let yPosition = 20;

    // Add header
    pdf.setFontSize(20);
    pdf.setFont(undefined, 'bold');
    pdf.text('Churn Analysis Report', 20, yPosition);
    yPosition += 10;
    
    pdf.setFontSize(10);
    pdf.setFont(undefined, 'normal');
    pdf.text('Generated: ' + new Date().toLocaleString(), 20, yPosition);
    yPosition += 15;

    if (includeAll) {
      // Export all tabs
      const tabs = ['retention', 'behavior', 'revenue', 'trends'];
      for (let i = 0; i < tabs.length; i++) {
        const tab = tabs[i];
        
        // Switch to tab
        switchTab(tab);
        await new Promise(resolve => setTimeout(resolve, 500));

        // Capture tab content
        const tabElement = document.getElementById(`${tab}-tab`);
        const canvas = await html2canvas(tabElement, {
          scale: 2,
          logging: false,
          backgroundColor: '#ffffff'
        });

        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 170;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        // Add new page if needed
        if (i > 0) {
          pdf.addPage();
          yPosition = 20;
        }

        // Add tab title
        pdf.setFontSize(14);
        pdf.setFont(undefined, 'bold');
        pdf.text(tab.charAt(0).toUpperCase() + tab.slice(1) + ' Analysis', 20, yPosition);
        yPosition += 10;

        // Add image
        pdf.addImage(imgData, 'PNG', 20, yPosition, imgWidth, imgHeight);
      }
    } else {
      // Export current tab only
      const activeTab = document.querySelector('.tab-content.active');
      const canvas = await html2canvas(activeTab, {
        scale: 2,
        logging: false,
        backgroundColor: '#ffffff'
      });

      const imgData = canvas.toDataURL('image/png');
      const imgWidth = 170;
      const imgHeight = (canvas.height * imgWidth) / canvas.width;

      pdf.addImage(imgData, 'PNG', 20, yPosition, imgWidth, imgHeight);
    }

    // Save PDF
    pdf.save('churn-analysis-report.pdf');
  } catch (error) {
    console.error('PDF export error:', error);
    alert('Error generating PDF. Please try again.');
  } finally {
    document.getElementById('exportLoading').remove();
  }
}

// Export to Image
async function exportToImage() {
  closeExportModal();
  
  const loadingDiv = document.createElement('div');
  loadingDiv.id = 'exportLoading';
  loadingDiv.style.cssText = `
    position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#fff; padding:2rem 3rem; border-radius:12px; box-shadow:0 0 50px rgba(0,0,0,.3);
    z-index:10000; text-align:center; font-weight:700;
  `;
  loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:2rem; color:#10B981;"></i><br><br>Generating Image...';
  document.body.appendChild(loadingDiv);

  try {
    const element = document.getElementById('customer-insights');
    const canvas = await html2canvas(element, {
      scale: 2,
      logging: false,
      backgroundColor: '#F6F9FC'
    });

    // Convert to blob and download
    canvas.toBlob((blob) => {
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'churn-analysis-report.png';
      link.click();
      URL.revokeObjectURL(url);
    });
  } catch (error) {
    console.error('Image export error:', error);
    alert('Error generating image. Please try again.');
  } finally {
    document.getElementById('exportLoading').remove();
  }
}

// Print Report
function printReport() {
  closeExportModal();
  
  // Hide buttons for print
  const buttons = document.querySelectorAll('.btn-action, .date-btn');
  buttons.forEach(btn => btn.classList.add('no-print'));

  // Show all tabs for printing if option is checked
  const includeAll = document.getElementById('includeAllTabs').checked;
  if (includeAll) {
    document.querySelectorAll('.tab-content').forEach(tab => {
      tab.style.display = 'block';
      tab.classList.add('page-break');
    });
  }

  // Print
  window.print();

  // Restore after print
  setTimeout(() => {
    buttons.forEach(btn => btn.classList.remove('no-print'));
    if (includeAll) {
      document.querySelectorAll('.tab-content').forEach((tab, index) => {
        if (index === 0) {
          tab.style.display = 'block';
          tab.classList.add('active');
        } else {
          tab.style.display = 'none';
          tab.classList.remove('active');
        }
        tab.classList.remove('page-break');
      });
    }
  }, 1000);
}

// Tab Switching Function
function switchTab(tabName) {
  // Remove active class from all tabs
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
    btn.style.color = '#6b7280';
    btn.style.borderBottom = 'none';
  });
  
  // Hide all tab contents
  document.querySelectorAll('.tab-content').forEach(content => {
    content.classList.remove('active');
    content.style.display = 'none';
  });
  
  // Activate selected tab
  const selectedTab = document.querySelector(`[onclick="switchTab('${tabName}')"]`);
  if (selectedTab) {
    selectedTab.classList.add('active');
    selectedTab.style.color = '#5E72E4';
    selectedTab.style.borderBottom = '3px solid #5E72E4';
    selectedTab.style.marginBottom = '-2px';
  }
  
  // Show selected content
  const selectedContent = document.getElementById(`${tabName}-tab`);
  if (selectedContent) {
    selectedContent.classList.add('active');
    selectedContent.style.display = 'block';
  }
}

// Refresh Reports Function
function refreshReports() {
  // Update timestamp
  const now = new Date();
  const timeString = now.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
  document.getElementById('lastUpdated').textContent = timeString;
  
  // You can add your data refresh logic here
  console.log('Refreshing reports...');
}

// Custom Date Range Function
function applyCustomRange() {
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
  
  console.log('Applying custom range:', startDate, 'to', endDate);
  // Add your custom range logic here
}

// Date Range Button Handlers
document.addEventListener('DOMContentLoaded', function() {
  // Initialize last updated time
  refreshReports();
  
  // Date range buttons
  const dateButtons = document.querySelectorAll('.date-btn');
  const customInputs = document.querySelector('.custom-date-inputs');
  
  dateButtons.forEach(button => {
    button.addEventListener('click', function() {
      // Remove active class from all buttons
      dateButtons.forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = '#fff';
        btn.style.color = '#2f3640';
        btn.style.border = '2px solid #EAF0FF';
      });
      
      // Add active class to clicked button
      this.classList.add('active');
      this.style.background = 'linear-gradient(135deg,#667EEA 0%,#5E72E4 100%)';
      this.style.color = '#fff';
      this.style.border = '0';
      
      // Show/hide custom date inputs
      const range = this.getAttribute('data-range');
      if (range === 'custom') {
        customInputs.style.display = 'flex';
      } else {
        customInputs.style.display = 'none';
        console.log('Selected range:', range);
        // Add your date range logic here
      }
    });
  });
});

// Close Drill Down Modal
function closeDrillDown() {
  document.getElementById('drillDownModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const exportModal = document.getElementById('exportModal');
  const drillDownModal = document.getElementById('drillDownModal');
  
  if (event.target === exportModal) {
    closeExportModal();
  }
  
  if (event.target === drillDownModal) {
    closeDrillDown();
  }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
  // ESC key to close modals
  if (event.key === 'Escape') {
    closeExportModal();
    closeDrillDown();
  }
  
  // Ctrl/Cmd + P for print
  if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
    event.preventDefault();
    showExportModal();
  }
});

// ==================== AI CHART SUMMARY MODULE ====================
// ADD THIS ENTIRE SECTION TO THE END OF YOUR churn-report.js FILE

const AIChartSummary = {
    config: {
        apiEndpoint: 'openai_chart_summary.php',
        timeout: 45000,
        retryAttempts: 2,
        retryDelay: 2000
    },

    activeRequests: new Map(),

    async generateSummary(chartType, chartData, containerId) {
        console.log(`[AI] Generating summary for ${chartType}`);

        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`[AI] Container not found: ${containerId}`);
            return;
        }

        this.showLoadingState(container);

        try {
            const summary = await this.callSummaryAPI(chartType, chartData);
            if (summary) {
                this.showSuccessState(container, summary, chartType);
                console.log(`[AI] Summary generated for ${chartType}`);
            }
        } catch (error) {
            console.error('[AI] Error:', error);
            this.showErrorState(container, error.message, chartType, chartData, containerId);
        }
    },

    async callSummaryAPI(chartType, chartData, retryCount = 0) {
        const controller = new AbortController();
        const requestId = `${chartType}-${Date.now()}`;
        
        this.activeRequests.set(requestId, controller);

        const timeoutId = setTimeout(() => {
            controller.abort();
        }, this.config.timeout);

        try {
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    chartType: chartType,
                    chartData: chartData
                }),
                signal: controller.signal,
                credentials: 'same-origin'
            });

            clearTimeout(timeoutId);
            this.activeRequests.delete(requestId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'error') {
                throw new Error(data.message || 'Failed to generate summary');
            }

            return data.summary || '';

        } catch (error) {
            clearTimeout(timeoutId);
            this.activeRequests.delete(requestId);

            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }

            if (retryCount < this.config.retryAttempts) {
                console.log(`[AI] Retrying... (${retryCount + 1}/${this.config.retryAttempts})`);
                await new Promise(resolve => setTimeout(resolve, this.config.retryDelay));
                return this.callSummaryAPI(chartType, chartData, retryCount + 1);
            }

            throw error;
        }
    },

    showLoadingState(container) {
        container.style.display = 'block';
        container.className = 'ai-chart-summary-container';
        container.innerHTML = `
            <div class="ai-summary-loading">
                <div class="spinner-small"></div>
                <span>Generating AI-powered insights...</span>
            </div>
        `;
    },

    showSuccessState(container, summary, chartType) {
        container.className = 'ai-chart-summary-container ai-summary-success';
        const tooltipText = this.getTooltipText(chartType);
        
        container.innerHTML = `
            <div class="ai-summary-header">
                <div class="ai-summary-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    AI-Powered Insights
                </div>
                <div class="summary-info-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <div class="summary-tooltip">${this.escapeHtml(tooltipText)}</div>
                </div>
            </div>
            <p class="ai-summary-content">${this.escapeHtml(summary)}</p>
        `;
    },

    showErrorState(container, errorMessage, chartType, chartData, containerId) {
        container.className = 'ai-chart-summary-container ai-summary-error';
        container.innerHTML = `
            <div class="ai-summary-error-content">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <div>
                    <div style="font-weight:700; margin-bottom:4px;">Unable to generate AI summary</div>
                    <div style="font-size:0.85rem;">${this.escapeHtml(errorMessage)}</div>
                </div>
            </div>
            <button class="ai-summary-retry-btn" onclick="AIChartSummary.retryGeneration('${chartType}', '${containerId}')">
                Try Again
            </button>
        `;
    },

    retryGeneration(chartType, containerId) {
        console.log(`[AI] Retrying summary for ${chartType}`);
        // Get the chart data based on type
        const chartData = this.getChartDataByType(chartType);
        if (chartData) {
            this.generateSummary(chartType, chartData, containerId);
        }
    },

    getChartDataByType(chartType) {
        const chartMap = {
            'retention': window.retentionChartInstance,
            'behavior': window.behaviorChartInstance,
            'revenue': window.revenueChartInstance,
            'trends': window.trendsChartInstance
        };
        
        const chart = chartMap[chartType];
        if (chart && chart.data) {
            return this.prepareChartData(chart);
        }
        return null;
    },

    getTooltipText(chartType) {
        const tooltips = {
            'retention': 'This AI-generated summary analyzes your retention data to identify key trends, patterns, and actionable recommendations for improving customer retention.',
            'behavior': 'This AI analysis reveals customer behavior patterns, transaction trends, and purchasing habits to help optimize your sales strategy.',
            'revenue': 'AI-driven revenue analysis identifies financial trends, growth opportunities, and potential risks affecting your bottom line.',
            'trends': 'This AI assessment evaluates 30-day risk trends to highlight concerning patterns and suggest proactive retention strategies.'
        };
        return tooltips[chartType] || 'AI-generated insights based on your data patterns and trends.';
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    },

    prepareChartData(chart) {
        if (!chart || !chart.data) return [];

        const labels = chart.data.labels || [];
        const datasets = chart.data.datasets || [];

        return labels.map((label, index) => {
            const dataPoint = { label: label };
            datasets.forEach((dataset, datasetIndex) => {
                const value = dataset.data[index];
                const key = dataset.label || `dataset_${datasetIndex}`;
                dataPoint[key] = value;
            });
            return dataPoint;
        });
    },

    generateSummaryForChart(chartInstance, chartType, containerId) {
        if (!chartInstance || !chartInstance.data) {
            console.warn('[AI] Invalid chart instance');
            return;
        }

        const chartData = this.prepareChartData(chartInstance);
        
        if (chartData.length === 0) {
            console.warn('[AI] No data to analyze');
            return;
        }

        setTimeout(() => {
            this.generateSummary(chartType, chartData, containerId);
        }, 500);
    }
};

// Make it globally available
window.AIChartSummary = AIChartSummary;

// ==================== HOOK INTO EXISTING CHART FUNCTIONS ====================
// Find your existing chart creation code and add AI summary generation

// Listen for when charts are created
document.addEventListener('DOMContentLoaded', function() {
    console.log('[AI] AI Summary module loaded');
    
    // Set up observers for chart canvases
    const observeChart = (canvasId, chartType, containerId) => {
        const canvas = document.getElementById(canvasId);
        if (canvas) {
            // Create mutation observer to detect when chart is drawn
            const observer = new MutationObserver(() => {
                const chartInstance = window[`${chartType}ChartInstance`];
                if (chartInstance && chartInstance.data) {
                    console.log(`[AI] Chart detected: ${chartType}`);
                    AIChartSummary.generateSummaryForChart(
                        chartInstance,
                        chartType,
                        containerId
                    );
                    observer.disconnect();
                }
            });
            
            observer.observe(canvas.parentElement, {
                childList: true,
                subtree: true,
                attributes: true
            });
            
            // Also check immediately
            setTimeout(() => {
                const chartInstance = window[`${chartType}ChartInstance`];
                if (chartInstance && chartInstance.data) {
                    console.log(`[AI] Chart ready: ${chartType}`);
                    AIChartSummary.generateSummaryForChart(
                        chartInstance,
                        chartType,
                        containerId
                    );
                }
            }, 1000);
        }
    };
    
    // Observe all charts
    observeChart('retentionChart', 'retention', 'retention-ai-summary');
    observeChart('behaviorChart', 'behavior', 'behavior-ai-summary');
    observeChart('revenueChart', 'revenue', 'revenue-ai-summary');
    observeChart('trendsChart', 'trends', 'trends-ai-summary');
});

// Override the switchTab function to trigger AI summaries
const originalSwitchTab = window.switchTab;
if (originalSwitchTab) {
    window.switchTab = function(tabName) {
        originalSwitchTab(tabName);
        
        // Wait a bit for chart to render, then generate AI summary
        setTimeout(() => {
            const chartMap = {
                'retention': { instance: 'retentionChartInstance', container: 'retention-ai-summary' },
                'behavior': { instance: 'behaviorChartInstance', container: 'behavior-ai-summary' },
                'revenue': { instance: 'revenueChartInstance', container: 'revenue-ai-summary' },
                'trends': { instance: 'trendsChartInstance', container: 'trends-ai-summary' }
            };
            
            const config = chartMap[tabName];
            if (config) {
                const chartInstance = window[config.instance];
                const container = document.getElementById(config.container);
                
                if (chartInstance && chartInstance.data && container) {
                    // Check if summary already exists
                    const hasContent = container.querySelector('.ai-summary-content');
                    const hasLoadingState = container.querySelector('.ai-summary-loading');
                    
                    if (!hasContent && !hasLoadingState) {
                        console.log(`[AI] Generating summary for ${tabName}`);
                        AIChartSummary.generateSummaryForChart(
                            chartInstance,
                            tabName,
                            config.container
                        );
                    }
                }
            }
        }, 1500);
    };
}

console.log('[AI] AI Chart Summary Integration Complete');