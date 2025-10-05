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