// Enhanced traffic loading with 14-day support and today's shift breakdown
async function loadTraffic(period) {
  try {
    const select = $('#trafficPeriod') || document.getElementById('trafficPeriod');
    const chosen = period || (select ? select.value : 'today') || 'today';
    
    console.log(`Loading traffic data for period: ${chosen}`);
    
    let apiUrl, labels, values, totalToday, peakTraffic, trendPct;
    
    // Always load today's data only
    console.log('Loading today data from latest entry...');
    
    const response = await api(`api/churn_data.php?action=latest&ts=${Date.now()}`);
    console.log('Latest data response:', response);
    
    if (response && response.item) {
      const data = response.item;
      
      // Extract shift data from latest entry
      const morning = parseInt(data.morning_receipt_count || 0);
      const swing = parseInt(data.swing_receipt_count || 0);  
      const graveyard = parseInt(data.graveyard_receipt_count || 0);
      const totalCustomerTraffic = parseInt(data.customer_traffic || 0);
      
      // Calculate other traffic (difference between total traffic and shift receipts)
      const totalShiftReceipts = morning + swing + graveyard;
      const other = Math.max(0, totalCustomerTraffic - totalShiftReceipts);
      
      labels = ['Morning', 'Swing', 'Graveyard'];
      values = [morning, swing, graveyard, other];
      totalToday = totalCustomerTraffic;
      peakTraffic = Math.max(morning, swing, graveyard);
      trendPct = parseFloat(data.transaction_drop_percentage || 0);
      
      console.log('Processed today data:', {
        morning, swing, graveyard, other,
        totalCustomerTraffic, totalShiftReceipts
      });
      
    } else {
      // If no data found, try the original traffic endpoint
      console.log('No latest data, trying original endpoint...');
      
      try {
        const fallbackResponse = await api(`api/traffic_data.php?period=today&ts=${Date.now()}`);
        
        if (fallbackResponse) {
          labels = fallbackResponse.labels || fallbackResponse.hours || ['Morning', 'Swing', 'Graveyard', ''];
          values = fallbackResponse.values || fallbackResponse.counts || fallbackResponse.data || [0, 0, 0, 0];
          totalToday = fallbackResponse.totalToday || fallbackResponse.total || values.reduce((a, b) => a + Number(b || 0), 0);
          peakTraffic = fallbackResponse.peakHourTraffic || fallbackResponse.peak || Math.max(...values);
          trendPct = fallbackResponse.trendPct || fallbackResponse.trend || 0;
        } else {
          throw new Error('No fallback data available');
        }
      } catch (fallbackError) {
        console.log('Fallback failed, using demo data');
        // Final fallback - demo data
        labels = ['Morning', 'Swing', 'Graveyard', ''];
        values = [0, 0, 0, 0];
        totalToday = 0;
        peakTraffic = 0;
        trendPct = 0;
      }
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

    // Update chart
    const ctx = $('#trafficChart') || document.getElementById('trafficChart');
    if (!ctx || !window.Chart) {
      console.warn('Chart canvas or Chart.js not available');
      return;
    }

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
    
    // Chart configuration for today only
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
                const shiftNames = ['Morning', 'Swing', 'Graveyard', ''];
                const shiftName = shiftNames[context.dataIndex] || 'Unknown';
                return ` ${value} receipts (${shiftName} shift)`;
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
              text: 'Number of Receipts'
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
    
  } catch (error) {
    console.error('[loadTraffic] Error:', error);
    
    // Show error state in UI
    const totalTodayEl = document.getElementById('totalCustomersToday');
    const peakEl = document.getElementById('peakHourTraffic');
    const trendEl = document.getElementById('trafficTrend');
    
    if (totalTodayEl) totalTodayEl.textContent = 'No Data';
    if (peakEl) peakEl.textContent = '0';
    if (trendEl) trendEl.textContent = '0%';
    
    // Create fallback chart with demo data
    createFallbackTrafficChart();
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
          
        ]
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
          title: { display: true, text: 'Number of Receipts' }
        },
        x: {
          title: { display: true, text: 'Shift Period' }
        }
      }
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