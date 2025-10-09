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