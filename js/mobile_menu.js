/**
 * ============================================
 * COMPLETE RESPONSIVE MOBILE MENU SYSTEM
 * Library Management System
 * ============================================
 * This handles all responsive menu functionality
 * for both dashboard pages and index page
 */

(function() {
    'use strict';

    // ============================================
    // CONFIGURATION
    // ============================================
    const CONFIG = {
        breakpoints: {
            mobile: 480,
            tablet: 768,
            desktop: 1024
        },
        animationDuration: 300,
        debounceDelay: 150
    };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    
    /**
     * Debounce function to limit function calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Get current viewport width
     */
    function getViewportWidth() {
        return Math.max(
            document.documentElement.clientWidth || 0,
            window.innerWidth || 0
        );
    }

    /**
     * Check if device is mobile
     */
    function isMobile() {
        return getViewportWidth() <= CONFIG.breakpoints.tablet;
    }

    /**
     * Prevent body scroll
     */
    function preventBodyScroll(prevent) {
        if (prevent) {
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
        } else {
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
        }
    }

    // ============================================
    // DASHBOARD SIDEBAR MENU
    // ============================================
    
    class DashboardMenu {
        constructor() {
            this.sidebar = document.querySelector('.sidebar');
            this.overlay = null;
            this.toggleButton = null;
            this.isOpen = false;
            
            if (this.sidebar) {
                this.init();
            }
        }

        init() {
            this.createElements();
            this.attachEventListeners();
            this.handleResize();
        }

        createElements() {
            // Create overlay if it doesn't exist
            if (!document.querySelector('.sidebar-overlay')) {
                this.overlay = document.createElement('div');
                this.overlay.className = 'sidebar-overlay';
                document.body.appendChild(this.overlay);
            } else {
                this.overlay = document.querySelector('.sidebar-overlay');
            }

            // Create toggle button if it doesn't exist
            if (!document.querySelector('.menu-toggle')) {
                this.toggleButton = document.createElement('button');
                this.toggleButton.className = 'menu-toggle';
                this.toggleButton.innerHTML = '☰';
                this.toggleButton.setAttribute('aria-label', 'Toggle Menu');
                this.toggleButton.setAttribute('aria-expanded', 'false');
                document.body.appendChild(this.toggleButton);
            } else {
                this.toggleButton = document.querySelector('.menu-toggle');
            }
        }

        attachEventListeners() {
            // Toggle button click
            this.toggleButton.addEventListener('click', () => this.toggle());

            // Overlay click
            this.overlay.addEventListener('click', () => this.close());

            // Menu link clicks
            const menuLinks = this.sidebar.querySelectorAll('.menu li a');
            menuLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        this.close();
                    }
                });
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Window resize
            window.addEventListener('resize', debounce(() => {
                this.handleResize();
            }, CONFIG.debounceDelay));
        }

        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        open() {
            this.sidebar.classList.add('active');
            this.overlay.classList.add('active');
            this.toggleButton.innerHTML = '✕';
            this.toggleButton.setAttribute('aria-expanded', 'true');
            this.isOpen = true;
            
            if (isMobile()) {
                preventBodyScroll(true);
            }
        }

        close() {
            this.sidebar.classList.remove('active');
            this.overlay.classList.remove('active');
            this.toggleButton.innerHTML = '☰';
            this.toggleButton.setAttribute('aria-expanded', 'false');
            this.isOpen = false;
            
            preventBodyScroll(false);
        }

        handleResize() {
            // Close menu on resize to desktop
            if (!isMobile() && this.isOpen) {
                this.close();
            }
        }
    }

    // ============================================
    // INDEX PAGE HEADER MENU
    // ============================================
    
    class IndexMenu {
        constructor() {
            this.header = document.querySelector('header');
            this.nav = document.querySelector('nav');
            this.toggleButton = null;
            this.isOpen = false;
            
            if (this.header && this.nav) {
                this.init();
            }
        }

        init() {
            this.createToggleButton();
            this.attachEventListeners();
            this.handleResize();
        }

        createToggleButton() {
            // Check if toggle button already exists
            let existingToggle = this.header.querySelector('.menu-toggle');
            
            if (!existingToggle) {
                this.toggleButton = document.createElement('button');
                this.toggleButton.className = 'menu-toggle';
                this.toggleButton.setAttribute('aria-label', 'Toggle Navigation');
                this.toggleButton.setAttribute('aria-expanded', 'false');
                this.toggleButton.innerHTML = `
                    <span></span>
                    <span></span>
                    <span></span>
                `;
                
                // Insert before nav
                this.nav.parentNode.insertBefore(this.toggleButton, this.nav);
            } else {
                this.toggleButton = existingToggle;
            }
        }

        attachEventListeners() {
            // Toggle button click
            this.toggleButton.addEventListener('click', () => this.toggle());

            // Navigation link clicks
            const navLinks = this.nav.querySelectorAll('ul li a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        this.close();
                    }
                });
            });

            // Click outside to close
            document.addEventListener('click', (e) => {
                if (this.isOpen && 
                    !this.header.contains(e.target)) {
                    this.close();
                }
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Window resize
            window.addEventListener('resize', debounce(() => {
                this.handleResize();
            }, CONFIG.debounceDelay));
        }

        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        open() {
            this.nav.classList.add('active');
            this.toggleButton.classList.add('active');
            this.toggleButton.setAttribute('aria-expanded', 'true');
            this.isOpen = true;
            
            // Animate hamburger
            this.animateHamburger(true);
            
            if (isMobile()) {
                preventBodyScroll(true);
            }
        }

        close() {
            this.nav.classList.remove('active');
            this.toggleButton.classList.remove('active');
            this.toggleButton.setAttribute('aria-expanded', 'false');
            this.isOpen = false;
            
            // Animate hamburger
            this.animateHamburger(false);
            
            preventBodyScroll(false);
        }

        animateHamburger(isOpen) {
            const spans = this.toggleButton.querySelectorAll('span');
            if (spans.length === 3) {
                if (isOpen) {
                    spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                    spans[1].style.opacity = '0';
                    spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
                } else {
                    spans[0].style.transform = '';
                    spans[1].style.opacity = '1';
                    spans[2].style.transform = '';
                }
            }
        }

        handleResize() {
            // Close menu on resize to desktop
            if (!isMobile() && this.isOpen) {
                this.close();
            }
        }
    }

    // ============================================
    // TABLE RESPONSIVENESS
    // ============================================
    
    class ResponsiveTables {
        constructor() {
            this.tables = document.querySelectorAll('table');
            if (this.tables.length > 0) {
                this.init();
            }
        }

        init() {
            this.tables.forEach(table => {
                this.makeTableResponsive(table);
            });
        }

        makeTableResponsive(table) {
            // Check if table is already wrapped
            if (table.parentElement.classList.contains('table-wrapper')) {
                return;
            }

            // Wrap table in responsive container
            const wrapper = document.createElement('div');
            wrapper.className = 'table-wrapper';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);

            // Add scroll indicator on mobile
            if (isMobile()) {
                this.addScrollIndicator(wrapper);
            }
        }

        addScrollIndicator(wrapper) {
            const indicator = document.createElement('div');
            indicator.className = 'table-scroll-indicator';
            indicator.innerHTML = '← Scroll to see more →';
            indicator.style.cssText = `
                text-align: center;
                padding: 10px;
                background: rgba(10, 224, 100, 0.1);
                color: #0ae064;
                font-size: 12px;
                border-radius: 4px;
                margin-bottom: 10px;
            `;
            wrapper.parentNode.insertBefore(indicator, wrapper);

            // Remove indicator when scrolled
            wrapper.addEventListener('scroll', () => {
                if (wrapper.scrollLeft > 10) {
                    indicator.style.display = 'none';
                }
            }, { once: true });
        }
    }

    // ============================================
    // TOUCH SWIPE HANDLER (for mobile gestures)
    // ============================================
    
    class SwipeHandler {
        constructor() {
            this.startX = 0;
            this.startY = 0;
            this.threshold = 50; // minimum distance for swipe
            
            this.init();
        }

        init() {
            document.addEventListener('touchstart', (e) => {
                this.startX = e.touches[0].clientX;
                this.startY = e.touches[0].clientY;
            }, { passive: true });

            document.addEventListener('touchend', (e) => {
                const endX = e.changedTouches[0].clientX;
                const endY = e.changedTouches[0].clientY;
                
                const diffX = endX - this.startX;
                const diffY = endY - this.startY;
                
                // Check if horizontal swipe
                if (Math.abs(diffX) > Math.abs(diffY)) {
                    if (diffX > this.threshold) {
                        // Swipe right - open sidebar if closed
                        const dashboardMenu = window.dashboardMenuInstance;
                        if (dashboardMenu && !dashboardMenu.isOpen && isMobile()) {
                            dashboardMenu.open();
                        }
                    } else if (diffX < -this.threshold) {
                        // Swipe left - close sidebar if open
                        const dashboardMenu = window.dashboardMenuInstance;
                        if (dashboardMenu && dashboardMenu.isOpen) {
                            dashboardMenu.close();
                        }
                    }
                }
            }, { passive: true });
        }
    }

    // ============================================
    // VIEWPORT HEIGHT FIX (for mobile browsers)
    // ============================================
    
    class ViewportFix {
        constructor() {
            this.init();
        }

        init() {
            // Set CSS variable for actual viewport height
            this.setViewportHeight();
            
            // Update on resize
            window.addEventListener('resize', debounce(() => {
                this.setViewportHeight();
            }, CONFIG.debounceDelay));
        }

        setViewportHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
    }

    // ============================================
    // ORIENTATION CHANGE HANDLER
    // ============================================
    
    class OrientationHandler {
        constructor() {
            this.init();
        }

        init() {
            window.addEventListener('orientationchange', () => {
                // Close any open menus on orientation change
                setTimeout(() => {
                    const dashboardMenu = window.dashboardMenuInstance;
                    const indexMenu = window.indexMenuInstance;
                    
                    if (dashboardMenu && dashboardMenu.isOpen) {
                        dashboardMenu.close();
                    }
                    
                    if (indexMenu && indexMenu.isOpen) {
                        indexMenu.close();
                    }
                }, 300);
            });
        }
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    
    function init() {
        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeComponents);
        } else {
            initializeComponents();
        }
    }

    function initializeComponents() {
        // Initialize dashboard menu
        window.dashboardMenuInstance = new DashboardMenu();
        
        // Initialize index page menu
        window.indexMenuInstance = new IndexMenu();
        
        // Initialize responsive tables
        new ResponsiveTables();
        
        // Initialize swipe handler for mobile
        if (isMobile()) {
            new SwipeHandler();
        }
        
        // Initialize viewport fix
        new ViewportFix();
        
        // Initialize orientation handler
        new OrientationHandler();

        console.log('✅ Responsive menu system initialized');
    }

    // Start initialization
    init();

})();