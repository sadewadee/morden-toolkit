/**
 * Query Monitor Performance Bar JavaScript
 *
 * @package Morden_Toolkit
 * @since 1.2.16
 */

// Enhanced MT Hook monitoring JavaScript with real-time capability
document.addEventListener('DOMContentLoaded', function() {
    // MT Hook Monitor should already be initialized by wp_localize_script
    if (!window.mtHookMonitor) {
        console.warn('MT Hook Monitor configuration not found');
        return;
    }
	// Real-time monitoring functionality
	const toggleButton = document.getElementById('mt-toggle-realtime');
	const refreshButton = document.getElementById('mt-refresh-hooks');
	const statusText = document.getElementById('mt-status-text');
	const hooksCount = document.getElementById('hooks-count');
	const memoryUsage = document.getElementById('memory-usage');

	if (toggleButton) {
		toggleButton.addEventListener('click', function() {
			if (!window.mtHookMonitor) {
				console.warn('MT Hook Monitor not initialized');
				return;
			}

			if (window.mtHookMonitor.isActive) {
				stopRealTimeMonitoring();
			} else {
				startRealTimeMonitoring();
			}
		});
	}

	if (refreshButton) {
		refreshButton.addEventListener('click', function() {
			refreshHookData();
		});
	}

	function startRealTimeMonitoring() {
		if (!window.mtHookMonitor) return;

		window.mtHookMonitor.isActive = true;
		toggleButton.textContent = window.mtQueryMonitorL10n.stopRealTimeUpdates;
		toggleButton.classList.remove('button-primary');
		toggleButton.classList.add('button-secondary');
		statusText.textContent = window.mtQueryMonitorL10n.statusActive;
		statusText.classList.add('active');

		// Poll for updates every 5 seconds (reasonable for demo purposes)
		window.mtHookMonitor.interval = setInterval(function() {
			refreshHookData();
		}, 5000);

		console.log('Real-time hook monitoring started (every 5 seconds)');
	}

	function stopRealTimeMonitoring() {
		if (!window.mtHookMonitor) return;

		window.mtHookMonitor.isActive = false;

		if (window.mtHookMonitor.interval) {
			clearInterval(window.mtHookMonitor.interval);
			window.mtHookMonitor.interval = null;
		}

		toggleButton.textContent = window.mtQueryMonitorL10n.enableRealTimeUpdates;
		toggleButton.classList.add('button-primary');
		toggleButton.classList.remove('button-secondary');
		statusText.textContent = window.mtQueryMonitorL10n.statusStatic;
		statusText.classList.remove('active');

		console.log('Real-time hook monitoring stopped');
	}

	function refreshHookData() {
		if (!window.mtHookMonitor) return;

		const originalText = statusText.textContent;
		statusText.textContent = window.mtQueryMonitorL10n.statusRefreshing;

		// Send AJAX request for updated hook data
		const formData = new FormData();
		formData.append('action', 'mt_monitor_hooks');
		formData.append('nonce', window.mtHookMonitor.nonce);

		fetch(window.mtHookMonitor.ajaxUrl, {
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				updateHookDisplay(data);
				statusText.textContent = window.mtHookMonitor.isActive ? window.mtQueryMonitorL10n.statusActive : window.mtQueryMonitorL10n.statusUpdated;
				statusText.classList.remove('error');
			} else {
				console.error('Failed to fetch hook data:', data);
				statusText.textContent = window.mtQueryMonitorL10n.statusError;
				statusText.classList.add('error');
			}
		})
		.catch(error => {
			console.error('AJAX error:', error);
			statusText.textContent = window.mtQueryMonitorL10n.statusError;
			statusText.classList.add('error');
		});
	}

	function updateHookDisplay(data) {
		// Update summary statistics
		if (hooksCount && data.hooks_captured !== undefined) {
			hooksCount.textContent = data.hooks_captured;
		}

		if (memoryUsage && data.memory_usage) {
			memoryUsage.textContent = formatBytes(data.memory_usage);
		}

		// Log recent hooks for debugging
		if (data.recent_hooks && data.recent_hooks.length > 0) {
			console.log('Recent hooks:', data.recent_hooks.map(h => h.hook).join(', '));
		}

		if (data.domain_summary) {
			console.log('Domain summary:', data.domain_summary);
		}
	}

	function formatBytes(bytes) {
		if (bytes >= 1073741824) {
			return (bytes / 1073741824).toFixed(2) + 'GB';
		} else if (bytes >= 1048576) {
			return (bytes / 1048576).toFixed(2) + 'MB';
		} else if (bytes >= 1024) {
			return (bytes / 1024).toFixed(2) + 'KB';
		} else {
			return bytes + 'B';
		}
	}

	// Toggle bootstrap details
	document.querySelectorAll('.toggle-bootstrap-details').forEach(function(button) {
		button.addEventListener('click', function() {
			var phase = this.getAttribute('data-phase');
			var details = document.getElementById('bootstrap-details-' + phase);
			if (details) {
				details.style.display = details.style.display === 'none' ? 'block' : 'none';
				this.textContent = details.style.display === 'none' ? window.mtQueryMonitorL10n.viewDetails : window.mtQueryMonitorL10n.hideDetails;
			}
		});
	});

	// Toggle domain panels
	document.querySelectorAll('.toggle-domain-panel').forEach(function(button) {
		button.addEventListener('click', function() {
			var domain = this.getAttribute('data-domain');
			var content = document.getElementById('domain-content-' + domain);
			if (content) {
				content.style.display = content.style.display === 'none' ? 'block' : 'none';
				this.textContent = content.style.display === 'none' ? window.mtQueryMonitorL10n.toggle : window.mtQueryMonitorL10n.hide;
			}
		});
	});

	// Real-time hooks filtering
	var phaseFilter = document.getElementById('mt-realtime-phase-filter');
	var domainFilter = document.getElementById('mt-realtime-domain-filter');
	var limitSelect = document.getElementById('mt-realtime-limit');

	if (phaseFilter || domainFilter) {
		// Populate filter options from existing data
		var realtimeTable = document.querySelector('.mt-realtime-hooks-table tbody');
		if (realtimeTable) {
			var phases = new Set();
			var domains = new Set();

			realtimeTable.querySelectorAll('tr').forEach(function(row) {
				var phase = row.getAttribute('data-phase');
				var domain = row.getAttribute('data-domain');
				if (phase) phases.add(phase);
				if (domain && domain !== 'uncategorized') domains.add(domain);
			});

			// Populate phase filter
			if (phaseFilter) {
				phases.forEach(function(phase) {
					var option = document.createElement('option');
					option.value = phase;
					option.textContent = phase.replace(/-/g, ' ').toUpperCase();
					phaseFilter.appendChild(option);
				});
			}

			// Populate domain filter
			if (domainFilter) {
				domains.forEach(function(domain) {
					var option = document.createElement('option');
					option.value = domain;
					option.textContent = domain.toUpperCase();
					domainFilter.appendChild(option);
				});
			}
		}

		// Filter functionality
		function filterRealtimeHooks() {
			var selectedPhase = phaseFilter ? phaseFilter.value : '';
			var selectedDomain = domainFilter ? domainFilter.value : '';
			var limit = limitSelect ? parseInt(limitSelect.value) : 50;

			if (realtimeTable) {
				var rows = realtimeTable.querySelectorAll('tr');
				var visibleCount = 0;

				rows.forEach(function(row) {
					var rowPhase = row.getAttribute('data-phase');
					var rowDomain = row.getAttribute('data-domain');
					var show = true;

					if (selectedPhase && rowPhase !== selectedPhase) show = false;
					if (selectedDomain && rowDomain !== selectedDomain) show = false;
					if (limit > 0 && visibleCount >= limit) show = false;

					row.style.display = show ? '' : 'none';
					if (show) visibleCount++;
				});
			}
		}

		if (phaseFilter) phaseFilter.addEventListener('change', filterRealtimeHooks);
		if (domainFilter) domainFilter.addEventListener('change', filterRealtimeHooks);
		if (limitSelect) limitSelect.addEventListener('change', filterRealtimeHooks);
	}

	// Script initialization is now handled by performance-tabs.js
});
