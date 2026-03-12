(function () {
    'use strict';

    var MOBILE_BP = 768;
    var isMobile = function () { return window.innerWidth <= MOBILE_BP; };

    /* ============================================
       DashboardMenu — for sidebar on dashboard pages
    ============================================ */
    function DashboardMenu() {
        this.sidebar = document.querySelector('.sidebar');
        if (!this.sidebar) return;

        this.overlay = this._ensureOverlay();
        this.toggleButton = this._ensureToggleButton();
        this.isOpen = false;
        this._bindEvents();
        this._handleResize();
    }

    DashboardMenu.prototype._ensureOverlay = function () {
        var el = document.querySelector('.sidebar-overlay');
        if (!el) {
            el = document.createElement('div');
            el.className = 'sidebar-overlay';
            document.body.appendChild(el);
        }
        return el;
    };

    DashboardMenu.prototype._ensureToggleButton = function () {
        var el = document.querySelector('.menu-toggle[data-menu="dashboard"]');
        if (!el) {
            el = document.createElement('button');
            el.className = 'menu-toggle';
            el.dataset.menu = 'dashboard';
            el.setAttribute('aria-label', 'Toggle Menu');
            el.innerHTML = '☰';
            document.body.appendChild(el);
        }
        return el;
    };

    DashboardMenu.prototype._bindEvents = function () {
        var self = this;
        this.toggleButton.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            self.toggle();
        });
        this.overlay.addEventListener('click', function (e) {
            if (e.target === self.overlay) self.close();
        });
        this.sidebar.querySelectorAll('.menu li a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) self.close();
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self.isOpen) self.close();
        });
        window.addEventListener('resize', function () { self._handleResize(); });
    };

    DashboardMenu.prototype.toggle = function () { this.isOpen ? this.close() : this.open(); };

    DashboardMenu.prototype.open = function () {
        this.sidebar.classList.add('active');
        this.overlay.classList.add('active');
        this.toggleButton.innerHTML = '✕';
        this.isOpen = true;
    };

    DashboardMenu.prototype.close = function () {
        this.sidebar.classList.remove('active');
        this.overlay.classList.remove('active');
        this.toggleButton.innerHTML = '☰';
        this.isOpen = false;
        document.body.style.overflow = '';
    };

    DashboardMenu.prototype._handleResize = function () {
        if (isMobile()) {
            this.toggleButton.style.display = 'inline-flex';
        } else {
            this.toggleButton.style.display = 'none';
            this.close();
        }
    };

    /* ============================================
       Init — only DashboardMenu here.
       Index page nav toggle is handled inline in index.html
    ============================================ */
    function init() {
        new DashboardMenu();
        // IndexMenu intentionally removed — index.html handles its own toggle
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();