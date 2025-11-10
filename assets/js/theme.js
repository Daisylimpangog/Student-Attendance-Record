// Theme Management System
document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    attachThemeListeners();
});

// Apply theme immediately before page load to prevent flash
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.documentElement.style.colorScheme = savedTheme;
})();

function initTheme() {
    const themeSwitch = document.getElementById('themeSwitch');
    if (!themeSwitch) return;

    // Set initial state
    const currentTheme = localStorage.getItem('theme') || 'light';
    themeSwitch.checked = currentTheme === 'dark';
    setTheme(currentTheme, false); // Don't animate initial state
}

function attachThemeListeners() {
    // Listen for switch changes
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'themeSwitch') {
            const newTheme = e.target.checked ? 'dark' : 'light';
            setTheme(newTheme, true);
        }
    });

    // Listen for system theme changes
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) { // Only if user hasn't set preference
                setTheme(e.matches ? 'dark' : 'light', true);
            }
        });
    }
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    // Update any theme switches on the page
    const themeSwitches = document.querySelectorAll('#themeSwitch');
    themeSwitches.forEach(sw => sw.checked = theme === 'dark');

    // Dispatch event for other scripts
    window.dispatchEvent(new CustomEvent('themeChanged', { 
        detail: { theme: theme }
    }));
    
    // Update meta theme-color for mobile browsers
    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
        metaThemeColor.setAttribute('content', 
            theme === 'dark' ? '#1a1f2e' : '#ffffff'
        );
    }
}

// Export for other scripts
window.themeSystem = {
    setTheme,
    getTheme: () => localStorage.getItem('theme') || 'light',
    isDark: () => (localStorage.getItem('theme') || 'light') === 'dark'
};