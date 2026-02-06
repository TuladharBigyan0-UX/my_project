/**Simple responsive menu toggle for dashboard + index pages.*/


(function() {
    'use strict';
  
    const MOBILE_BREAKPOINT = 768;
    
    const isMobile = () => window.innerWidth <= MOBILE_BREAKPOINT;
    
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
        }

         ensureOverlay() {
            const existingOverlay = document.querySelector('.sidebar-overlay');
            if (existingOverlay) {
                return existingOverlay;
            }

            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            return overlay;
        }

        ensureToggleButton() {
           const existingToggle = document.querySelector('.menu-toggle[data-menu="dashboard"]');
              if (existingToggle) {
                return existingToggle;
            }

            const button = document.createElement('button');
            button.className = 'menu-toggle';
            button.dataset.menu = 'dashboard';
            button.setAttribute('aria-label', 'Toggle Menu');
            button.setAttribute('aria-expanded', 'false');
            button.textContent = '☰';
            document.body.appendChild(button);
            return button;
        }

       bindEvents() {
            this.toggleButton.addEventListener('click', () => this.toggle());
            this.overlay.addEventListener('click', () => this.close());

            this.sidebar.querySelectorAll('.menu li a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        this.close();
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
             if (event.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

             window.addEventListener('resize', () => this.handleResize());
        }

        toggle() {
            if (this.isOpen) {
                this.close();
           return;
            }

            this.open();
        }

        open() {
            this.sidebar.classList.add('active');
            this.overlay.classList.add('active');
            this.toggleButton.textContent = '✕';
            this.toggleButton.setAttribute('aria-expanded', 'true');
            this.isOpen = true;
            document.body.classList.add('menu-open');  
        }

        close() {
            this.sidebar.classList.remove('active');
            this.overlay.classList.remove('active');
            this.toggleButton.textContent = '☰';
            this.toggleButton.setAttribute('aria-expanded', 'false');
            this.isOpen = false;
            document.body.classList.remove('menu-open');
        }

        handleResize() {
            if (!isMobile() && this.isOpen) {
                this.close();
            }
        }
    }
    
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
            const existingToggle = this.header.querySelector('.menu-toggle[data-menu="index"]');
            if (existingToggle) {
                return existingToggle;
            }

            const button = document.createElement('button');
            button.className = 'menu-toggle';
            button.dataset.menu = 'index';
            button.setAttribute('aria-label', 'Toggle Navigation');
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML = '<span></span><span></span><span></span>';
            this.nav.parentNode.insertBefore(button, this.nav);
            return button;
        }

        bindEvents() {
            this.toggleButton.addEventListener('click', () => this.toggle());

            this.nav.querySelectorAll('ul li a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        this.close();
                    }
                });
            });

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
            if (this.isOpen) {
                this.close();
                return;
            }

            this.open();
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
            if (!isMobile() && this.isOpen) {
                this.close();
            }
        }
    }

      const init = () => {
        new DashboardMenu();
        new IndexMenu();
    };
    
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();