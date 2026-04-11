/* TalentFlow - Theme Manager (Dark Mode + Preferences) */
(function() {
    'use strict';

    const STORAGE_KEY = 'tf-theme';

    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.classList.toggle('dark', theme === 'dark');

        // Update toggle icons
        document.querySelectorAll('.tf-theme-toggle').forEach(function(btn) {
            var sun = btn.querySelector('.icon-sun');
            var moon = btn.querySelector('.icon-moon');
            if (sun && moon) {
                sun.style.display = theme === 'dark' ? 'none' : '';
                moon.style.display = theme === 'dark' ? '' : 'none';
            }
            btn.setAttribute('aria-label', theme === 'dark' ? 'Passer au mode clair' : 'Passer au mode sombre');
        });
    }

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
        var next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next);
    }

    // Apply immediately (before DOM ready to avoid flash)
    applyTheme(getPreferredTheme());

    // Listen for system preference changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!localStorage.getItem(STORAGE_KEY)) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

    // Expose globally
    window.TFTheme = { toggle: toggleTheme, apply: applyTheme, get: getPreferredTheme };

    // Bind buttons after DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.tf-theme-toggle').forEach(function(btn) {
            btn.addEventListener('click', toggleTheme);
        });

        // Animate elements on scroll (intersection observer)
        var animItems = document.querySelectorAll('.tf-animate-in');
        if (animItems.length && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry, idx) {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = (idx * 0.05) + 's';
                        entry.target.classList.add('tf-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            animItems.forEach(function(el) { observer.observe(el); });
        }

        // Navbar scroll effect
        var navbar = document.querySelector('.tf-pub-navbar');
        if (navbar) {
            window.addEventListener('scroll', function() {
                navbar.classList.toggle('scrolled', window.scrollY > 10);
            }, { passive: true });
        }

        // Lazy load images
        document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
            if (img.complete) {
                img.classList.add('loaded');
            } else {
                img.addEventListener('load', function() { img.classList.add('loaded'); });
            }
        });
    });
})();
