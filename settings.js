/* -------------------- Settings Functions -------------------- */

// Helper function for API calls
async function api(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        ...options
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return await response.json();
}

// Helper to get element by ID
function $(id) {
    return document.getElementById(id);
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Update refresh interval
window.updateRefreshInterval = async function(val) {
    try {
        const response = await api('api/settings_update.php', {
            method: 'POST',
            body: JSON.stringify({ refresh_interval: String(val || '6') })
        });
        
        if (response.success) {
            showToast('Refresh interval updated successfully');
        } else {
            showToast(response.message || 'Failed to update refresh interval', 'error');
        }
    } catch (e) {
        console.error('[Settings refresh_interval]', e);
        showToast('Error updating refresh interval', 'error');
    }
};

// Toggle dark mode
window.toggleDarkMode = async function(checked) {
    try {
        const response = await api('api/settings_update.php', {
            method: 'POST',
            body: JSON.stringify({ dark_mode: checked ? 1 : 0 })
        });
        
        if (response.success) {
            document.documentElement.classList.toggle('dark', !!checked);
            localStorage.setItem('darkMode', checked ? '1' : '0');
            showToast(`Dark mode ${checked ? 'enabled' : 'disabled'}`);
        } else {
            showToast(response.message || 'Failed to update dark mode', 'error');
        }
    } catch (e) {
        console.error('[Settings dark_mode]', e);
        showToast('Error updating dark mode', 'error');
    }
};

// Load initial settings on page load
async function loadInitialSettings() {
    try {
        const response = await api('api/get_profile_settings.php?ts=' + Date.now());
        
        if (response.success !== false) {
            // Dark mode
            if (typeof response.dark_mode !== 'undefined') {
                const isDark = !!Number(response.dark_mode);
                const toggle = $('darkModeToggle');
                if (toggle) {
                    toggle.checked = isDark;
                }
                document.documentElement.classList.toggle('dark', isDark);
                localStorage.setItem('darkMode', isDark ? '1' : '0');
            }
            
            // Refresh interval
            if (typeof response.refresh_interval !== 'undefined') {
                const interval = $('refreshInterval');
                if (interval) {
                    interval.value = Number(response.refresh_interval || 6);
                }
            }
        }
    } catch (e) {
        console.warn('[Load settings]', e);
        // Apply localStorage dark mode as fallback
        const localDark = localStorage.getItem('darkMode') === '1';
        document.documentElement.classList.toggle('dark', localDark);
        const toggle = $('darkModeToggle');
        if (toggle) toggle.checked = localDark;
    }
}

// Initialize settings when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadInitialSettings);
} else {
    loadInitialSettings();
}