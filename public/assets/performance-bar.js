/**
 * Performance Bar Frontend JavaScript
 */

(function() {
    'use strict';

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializePerformanceBar();
        adjustBodyPadding();
    });

    /**
     * Initialize performance bar functionality
     */
    function initializePerformanceBar() {
        const detailsBtn = document.getElementById('mt-perf-details-btn');
        const detailsPanel = document.getElementById('mt-perf-details');

        if (detailsBtn && detailsPanel) {
            detailsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleDetails();
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.mt-performance-bar')) {
                    closeDetails();
                }
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDetails();
                }
            });
        }

        // Initialize tab functionality
        initializeTabs();

        // Add class to body for styling adjustments
        document.body.classList.add('mt-perf-active');
    }

    /**
     * Toggle details panel
     */
    function toggleDetails() {
        const detailsPanel = document.getElementById('mt-perf-details');
        const detailsBtn = document.getElementById('mt-perf-details-btn');

        if (detailsPanel && detailsBtn) {
            const isVisible = detailsPanel.style.display !== 'none';

            if (isVisible) {
                closeDetails();
            } else {
                openDetails();
            }
        }
    }

    /**
     * Open details panel
     */
    function openDetails() {
        const detailsPanel = document.getElementById('mt-perf-details');
        const detailsBtn = document.getElementById('mt-perf-details-btn');

        if (detailsPanel && detailsBtn) {
            detailsPanel.style.display = 'block';
            detailsBtn.setAttribute('aria-expanded', 'true');
            detailsBtn.classList.add('active');

            // Animate in
            detailsPanel.style.opacity = '0';
            detailsPanel.style.transform = 'translateY(10px)';

            requestAnimationFrame(function() {
                detailsPanel.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                detailsPanel.style.opacity = '1';
                detailsPanel.style.transform = 'translateY(0)';
            });
        }
    }

    /**
     * Close details panel
     */
    function closeDetails() {
        const detailsPanel = document.getElementById('mt-perf-details');
        const detailsBtn = document.getElementById('mt-perf-details-btn');

        if (detailsPanel && detailsBtn) {
            detailsPanel.style.opacity = '0';
            detailsPanel.style.transform = 'translateY(10px)';

            setTimeout(function() {
                detailsPanel.style.display = 'none';
                detailsBtn.setAttribute('aria-expanded', 'false');
                detailsBtn.classList.remove('active');
            }, 300);
        }
    }

    /**
     * Adjust body padding to accommodate performance bar
     */
    function adjustBodyPadding() {
        const perfBar = document.getElementById('mt-performance-bar');

        if (!perfBar) return;

        // Function to update padding
        function updatePadding() {
            const barHeight = perfBar.offsetHeight;
            const isAdminBar = document.body.classList.contains('admin-bar');

            if (!isAdminBar) {
                document.body.style.paddingBottom = barHeight + 'px';
            }
        }

        // Initial update
        updatePadding();

        // Update on window resize
        window.addEventListener('resize', updatePadding);

        // Update when details panel opens/closes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.id === 'mt-perf-details') {
                    setTimeout(updatePadding, 50);
                }
            });
        });

        const detailsPanel = document.getElementById('mt-perf-details');
        if (detailsPanel) {
            observer.observe(detailsPanel, {
                attributes: true,
                attributeFilter: ['style']
            });
        }
    }

    /**
     * Format numbers for display
     */
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    /**
     * Format bytes for display
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';

        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    /**
     * Format time for display
     */
    function formatTime(seconds) {
        if (seconds < 1) {
            return Math.round(seconds * 1000) + 'ms';
        }
        return seconds.toFixed(3) + 's';
    }

    /**
     * Update performance metrics (if real-time updates are needed)
     */
    function updateMetrics() {
        // This could be used for real-time updates via AJAX
        // Currently metrics are rendered server-side
    }

    /**
     * Initialize tab functionality
     */
    function initializeTabs() {
        const tabs = document.querySelectorAll('.mt-perf-tab');
        const tabContents = document.querySelectorAll('.mt-perf-tab-content');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                switchTab(this.getAttribute('data-tab'));
            });
        });
    }

    /**
     * Switch to specific tab
     */
    function switchTab(tabName) {
        // Remove active class from all tabs
        const tabs = document.querySelectorAll('.mt-perf-tab');
        const tabContents = document.querySelectorAll('.mt-perf-tab-content');

        tabs.forEach(function(tab) {
            tab.classList.remove('active');
        });

        tabContents.forEach(function(content) {
            content.classList.remove('active');
        });

        // Add active class to selected tab and content
        const selectedTab = document.querySelector('.mt-perf-tab[data-tab="' + tabName + '"]');
        const selectedContent = document.getElementById('mt-perf-tab-' + tabName);

        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        if (selectedContent) {
            selectedContent.classList.add('active');
        }
    }

    // Make functions available globally if needed
    window.MordenPerformanceBar = {
        toggleDetails: toggleDetails,
        openDetails: openDetails,
        closeDetails: closeDetails,
        switchTab: switchTab,
        formatNumber: formatNumber,
        formatBytes: formatBytes,
        formatTime: formatTime
    };

})();
