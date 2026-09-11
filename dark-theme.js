/**
 * Dark Theme Toggle System
 * Manages light/dark theme switching with localStorage persistence
 */

(function() {
    const THEME_KEY = 'donate-theme';
    const LIGHT = 'light';
    const DARK = 'dark';

    // Initialize theme on page load
    function initTheme() {
        // Check localStorage or system preference
        let theme = localStorage.getItem(THEME_KEY);
        
        if (!theme) {
            // Use system preference if available
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? DARK : LIGHT;
        }
        
        applyTheme(theme);
    }

    // Apply theme to document
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        
        // Update toggle button if it exists
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = theme === DARK ? '☀️ Light' : '🌙 Dark';
            toggleBtn.setAttribute('aria-label', `Switch to ${theme === DARK ? 'light' : 'dark'} theme`);
        }
    }

    // Toggle theme
    window.toggleTheme = function() {
        const current = document.documentElement.getAttribute('data-theme') || LIGHT;
        const next = current === LIGHT ? DARK : LIGHT;
        applyTheme(next);
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
