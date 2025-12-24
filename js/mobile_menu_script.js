// Mobile Menu Toggle for Dashboard Pages
document.addEventListener('DOMContentLoaded', function() {
    // Create menu toggle button if it doesn't exist
    if (!document.querySelector('.menu-toggle')) {
        const menuToggle = document.createElement('button');
        menuToggle.className = 'menu-toggle';
        menuToggle.innerHTML = '☰';
        menuToggle.setAttribute('aria-label', 'Toggle Menu');
        document.body.appendChild(menuToggle);
    }

    // Create sidebar overlay if it doesn't exist
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    // Toggle sidebar function
    function toggleSidebar() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Update button icon
        if (sidebar.classList.contains('active')) {
            menuToggle.innerHTML = '✕';
        } else {
            menuToggle.innerHTML = '☰';
        }
    }

    // Close sidebar function
    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        menuToggle.innerHTML = '☰';
    }

    // Toggle on button click
    if (menuToggle) {
        menuToggle.addEventListener('click', toggleSidebar);
    }

    // Close on overlay click
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close on menu link click (mobile)
    const menuLinks = document.querySelectorAll('.menu li a');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // Close on window resize if screen becomes larger
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // Prevent body scroll when sidebar is open on mobile
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        });
    });

    if (sidebar) {
        observer.observe(sidebar, { attributes: true });
    }
});

// Mobile Menu Toggle for Index Page
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('header');
    
    if (!header) return;

    // Create mobile menu toggle for index page
    const nav = document.querySelector('nav');
    const menuToggleBtn = document.createElement('button');
    menuToggleBtn.className = 'menu-toggle';
    menuToggleBtn.innerHTML = '<span></span><span></span><span></span>';
    menuToggleBtn.setAttribute('aria-label', 'Toggle Navigation');
    
    // Insert before nav
    if (nav) {
        nav.parentNode.insertBefore(menuToggleBtn, nav);
    }

    // Toggle navigation
    menuToggleBtn.addEventListener('click', function() {
        nav.classList.toggle('active');
        this.classList.toggle('active');
        
        // Animate hamburger
        if (this.classList.contains('active')) {
            this.querySelector('span:nth-child(1)').style.transform = 'rotate(45deg) translate(5px, 5px)';
            this.querySelector('span:nth-child(2)').style.opacity = '0';
            this.querySelector('span:nth-child(3)').style.transform = 'rotate(-45deg) translate(7px, -6px)';
        } else {
            this.querySelector('span:nth-child(1)').style.transform = '';
            this.querySelector('span:nth-child(2)').style.opacity = '1';
            this.querySelector('span:nth-child(3)').style.transform = '';
        }
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!header.contains(event.target) && nav.classList.contains('active')) {
            nav.classList.remove('active');
            menuToggleBtn.classList.remove('active');
            menuToggleBtn.querySelector('span:nth-child(1)').style.transform = '';
            menuToggleBtn.querySelector('span:nth-child(2)').style.opacity = '1';
            menuToggleBtn.querySelector('span:nth-child(3)').style.transform = '';
        }
    });

    // Close menu on link click (for smooth scrolling)
    const navLinks = document.querySelectorAll('nav ul li a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                nav.classList.remove('active');
                menuToggleBtn.classList.remove('active');
                menuToggleBtn.querySelector('span:nth-child(1)').style.transform = '';
                menuToggleBtn.querySelector('span:nth-child(2)').style.opacity = '1';
                menuToggleBtn.querySelector('span:nth-child(3)').style.transform = '';
            }
        });
    });
});