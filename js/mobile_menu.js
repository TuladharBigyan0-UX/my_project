
(function() {
    'use strict';
  
    const MOBILE_BREAKPOINT = 768;
    
    const isMobile = () => window.innerWidth <= MOBILE_BREAKPOINT;
    
    /**
     * Dashboard Sidebar Menu Controller
     * Handles sidebar navigation for admin, librarian, and member dashboards
     */
    class DashboardMenu {
        constructor() {
            this.sidebar = document.querySelector('.sidebar');
            if (!this.sidebar) {
                return;
            }

            this.overlay = this.ensureOverlay();
            this.toggleButton = this.ensureToggleButton();
            this.isOpen = false;
            this.bindEvents();
            this.handleResize();
            
            // Show toggle button on mobile immediately
            if (isMobile()) {
                this.toggleButton.style.display = 'inline-flex';
            }
        }

        ensureOverlay() {
            let overlay = document.querySelector('.sidebar-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                document.body.appendChild(overlay);
            }
            return overlay;
        }

        ensureToggleButton() {
            let button = document.querySelector('.menu-toggle[data-menu="dashboard"]');
            if (!button) {
                button = document.createElement('button');
                button.className = 'menu-toggle';
                button.dataset.menu = 'dashboard';
                button.setAttribute('aria-label', 'Toggle Menu');
                button.setAttribute('aria-expanded', 'false');
                button.innerHTML = '☰';
                document.body.appendChild(button);
            }
            return button;
        }

        bindEvents() {
            this.toggleButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });
            
            // Close menu when clicking overlay (not content)
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.close();
                }
            });

            // Close menu when clicking menu items
            this.sidebar.querySelectorAll('.menu li a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        this.close();
                    }
                });
            });

            // Close menu with ESC key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            window.addEventListener('resize', () => this.handleResize());
        }

        toggle() {
            this.isOpen ? this.close() : this.open();
        }

        open() {
            this.sidebar.classList.add('active');
            this.overlay.classList.add('active');
            this.toggleButton.innerHTML = '✕';
            this.toggleButton.setAttribute('aria-expanded', 'true');
            this.isOpen = true;
            document.body.classList.add('menu-open');
            
            // Don't prevent scrolling - let users interact with content
            // document.body.style.overflow = 'hidden'; // REMOVED
        }

        close() {
            this.sidebar.classList.remove('active');
            this.overlay.classList.remove('active');
            this.toggleButton.innerHTML = '☰';
            this.toggleButton.setAttribute('aria-expanded', 'false');
            this.isOpen = false;
            document.body.classList.remove('menu-open');
            document.body.style.overflow = '';
        }

        handleResize() {
            if (isMobile()) {
                this.toggleButton.style.display = 'inline-flex';
                if (!this.isOpen) {
                    this.sidebar.classList.remove('active');
                }
            } else {
                this.toggleButton.style.display = 'none';
                this.close();
                this.sidebar.classList.remove('active');
            }
        }
    }
    
    /**
     * Index Page Header Navigation Controller
     * Handles responsive navigation for public-facing pages
     */
    class IndexMenu {
        constructor() {
            this.header = document.querySelector('header');
            this.nav = document.querySelector('header nav');
            if (!this.header || !this.nav) {
                return;
            }

            this.toggleButton = this.ensureToggleButton();
            this.isOpen = false;
            this.bindEvents();
            this.handleResize();
        }

        ensureToggleButton() {
            let button = this.header.querySelector('.menu-toggle[data-menu="index"]');
            if (!button) {
                button = document.createElement('button');
                button.className = 'menu-toggle';
                button.dataset.menu = 'index';
                button.setAttribute('aria-label', 'Toggle Navigation');
                button.setAttribute('aria-expanded', 'false');
                button.innerHTML = '<span></span><span></span><span></span>';
                
                const headerContainer = this.header.querySelector('.header-container');
                if (headerContainer) {
                    headerContainer.appendChild(button);
                }
            }
            return button;
        }

        bindEvents() {
            this.toggleButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });

            this.nav.querySelectorAll('ul li a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        this.close();
                    }
                });
            });

            // Close when clicking outside header
            document.addEventListener('click', (event) => {
                if (this.isOpen && !this.header.contains(event.target)) {
                    this.close();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            window.addEventListener('resize', () => this.handleResize());
        }

        toggle() {
            this.isOpen ? this.close() : this.open();
        }

        open() {
            this.nav.classList.add('active');
            this.toggleButton.classList.add('active');
            this.toggleButton.setAttribute('aria-expanded', 'true');
            this.isOpen = true;
        }

        close() {
            this.nav.classList.remove('active');
            this.toggleButton.classList.remove('active');
            this.toggleButton.setAttribute('aria-expanded', 'false');
            this.isOpen = false;
        }

        handleResize() {
            if (isMobile()) {
                this.toggleButton.style.display = 'flex';
            } else {
                this.toggleButton.style.display = 'none';
                this.close();
            }
        }
    }

    /**
     * Initialize both menu systems
     * Safe to call on any page - will only initialize what's needed
     */
    const init = () => {
        new DashboardMenu();
        new IndexMenu();
    };
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();