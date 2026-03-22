/**
 * AI Gateway Admin Dashboard v0.3.0
 */
(function(window) {
	'use strict';

	const D = {
		nonce: aiGatewayConfig.nonce,
		apiRoot: aiGatewayConfig.api_root,
		currentTab: 'overview',
		isLoading: false,

		/* ─── Bootstrap ─── */
		init: function() {
			document.querySelectorAll('.ai-gateway-tab-btn').forEach(btn => {
				btn.addEventListener('click', e => {
					e.preventDefault();
					this.switchTab(btn.getAttribute('data-tab'));
				});
			});
			this.loadTab('overview');
		},

		switchTab: function(tab) {
			if (this.isLoading) return;
			document.querySelectorAll('.ai-gateway-tab-btn').forEach(b => {
				const active = b.getAttribute('data-tab') === tab;
				b.classList.toggle('nav-tab-active', active);
				b.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			document.querySelectorAll('.ai-gateway-tab-content').forEach(c => {
				c.classList.remove('ai-gateway-tab-content-active');
			});
			const el = document.getElementById('tab-' + tab);
			if (el) el.classList.add('ai-gateway-tab-content-active');
			this.currentTab = tab;
			this.loadTab(tab);
		},

		loadTab: function(tab) {
			const el = document.getElementById('tab-' + tab);
			if (!el) return;
			el.innerHTML = '<div class="ai-gateway-loading"><span class="spinner is-active"></span></div>';
			this.isLoading = true;

			const map = {
				'overview':         'loadOverview',
				'api-reference':    'loadApiReference',
				'audit-log':        'loadAuditLog',
				'health':           'loadHealth',
				'endpoint-tester':  'loadEndpointTester',
				'settings':         'loadSettings'
			};
			if (map[tab]) this[map[tab]]();
			else { el.innerHTML = '<p>Tab not implemented.</p>'; this.isLoading = false; }
		},

		/* ─── Overview ─── */
		loadOverview: function() {
			const el = document.getElementById('tab-overview');

			Promise.all([
				this.api('/rate-limit/status').catch(() => ({})),
				this.api('/audit?per_page=5').catch(() => ({})),
				this.api('/health').catch(() => ({}))
			]).then(([rl, audit, health]) => {
				const req = rl.requests_this_minute || 0;
				const lim = rl.limit || 60;
				const pct = Math.min(100, Math.round((req / lim) * 100));
				const rateClass = pct > 80 ? 'rate-high' : pct > 50 ? 'rate-medium' : '';

				const hs = health.status || 'unknown';
				const hClass = hs === 'healthy' ? 'health-healthy' : hs === 'degraded' ? 'health-degraded' : 'health-unhealthy';

				const entries = this.extractAuditEntries(audit);

				let h = '<div class="ai-gateway-overview-grid">';

				// Stat cards row
				h += '<div class="ai-gateway-stat-card">';
				h += '<div class="dashicons dashicons-performance stat-icon"></div>';
				h += '<div class="stat-value">' + req + '/' + lim + '</div>';
				h += '<div class="stat-label">Requests / min</div>';
				h += '<div class="ai-gateway-rate-bar"><div class="ai-gateway-rate-fill ' + rateClass + '" style="width:' + pct + '%"></div></div>';
				h += '</div>';

				h += '<div class="ai-gateway-stat-card">';
				h += '<div class="dashicons dashicons-heart stat-icon"></div>';
				h += '<div class="ai-gateway-health-overview ' + hClass + '">' + this.esc(hs.toUpperCase()) + '</div>';
				h += '<div class="stat-label" style="margin-top:8px">System Status</div>';
				h += '</div>';

				// Activity
				h += '<div class="ai-gateway-card ai-gateway-overview-full">';
				h += '<h3>Recent Activity</h3>';
				if (entries.length > 0) {
					entries.forEach(e => {
						h += '<div class="ai-gateway-timeline-item">';
						h += '<div class="ai-gateway-timeline-time">' + this.esc(this.relTime(new Date(e.timestamp))) + '</div>';
						h += '<div class="ai-gateway-timeline-content">';
						h += '<span class="ai-gateway-method method-' + (e.method||'GET').toLowerCase() + '">' + this.esc(e.method) + '</span> ';
						h += '<code>' + this.esc(e.endpoint) + '</code> ';
						h += '<span class="ai-gateway-badge ' + (e.status_code >= 400 ? 'badge-error' : 'badge-success') + '">' + e.status_code + '</span>';
						h += '</div></div>';
					});
				} else {
					h += '<div class="ai-gateway-empty"><div class="dashicons dashicons-clock"></div><p>No recent activity</p></div>';
				}
				h += '</div></div>';

				el.innerHTML = h;
				this.isLoading = false;
			}).catch(err => {
				el.innerHTML = '<div class="ai-gateway-error"><p>Error loading overview</p></div>';
				this.isLoading = false;
			});
		},

		/* ─── API Reference ─── */
		loadApiReference: function() {
			const el = document.getElementById('tab-api-reference');
			const base = '/wp-json/ai-gateway/v1';

			const groups = [
				{
					name: 'Code Snippets',
					endpoints: [
						{ m: 'GET',    p: '/code-snippets',                  d: 'List code snippets with pagination' },
						{ m: 'POST',   p: '/code-snippets',                  d: 'Create code snippet (PHP lint)' },
						{ m: 'GET',    p: '/code-snippets/{id}',             d: 'Read single code snippet' },
						{ m: 'PATCH',  p: '/code-snippets/{id}',             d: 'Update code snippet' },
						{ m: 'DELETE', p: '/code-snippets/{id}',             d: 'Delete code snippet' },
						{ m: 'DELETE', p: '/code-snippets/cleanup-duplicates', d: 'Clean up inactive duplicate snippets' }
					]
				},
				{
					name: 'Templates DB',
					endpoints: [
						{ m: 'GET',    p: '/templates-db',      d: 'List database templates' },
						{ m: 'POST',   p: '/templates-db',      d: 'Create database template' },
						{ m: 'GET',    p: '/templates-db/{id}',  d: 'Read single database template' },
						{ m: 'PATCH',  p: '/templates-db/{id}',  d: 'Update database template' },
						{ m: 'DELETE', p: '/templates-db/{id}',  d: 'Delete database template' }
					]
				},
				{
					name: 'Global Styles (Site Editor)',
					endpoints: [
						{ m: 'GET',   p: '/global-styles/css', d: 'Read Site Editor Additional CSS' },
						{ m: 'PATCH', p: '/global-styles/css', d: 'Update Site Editor Additional CSS' },
						{ m: 'GET',   p: '/global-styles',     d: 'Read full Global Styles JSON' },
						{ m: 'PATCH', p: '/global-styles',     d: 'Update full Global Styles JSON' },
						{ m: 'GET',   p: '/global-styles/json', d: 'Read JSON without CSS (lightweight)' },
						{ m: 'PATCH', p: '/global-styles/json', d: 'Deep-merge partial JSON (CSS preserved)' }
					]
				},
				{
					name: 'Posts & Content',
					endpoints: [
						{ m: 'GET',   p: '/posts',                d: 'List posts (pagination, filter by status/category/search)' },
						{ m: 'GET',   p: '/posts/{id}',           d: 'Read post with content, meta, ACF fields' },
						{ m: 'PATCH', p: '/posts/{id}',           d: 'Update post title/content/excerpt/status' },
						{ m: 'GET',   p: '/posts/search-content', d: 'Search posts by content substring' }
					]
				},
				{
					name: 'ACF Pro',
					endpoints: [
						{ m: 'GET',   p: '/acf/field-groups',          d: 'List all ACF field groups' },
						{ m: 'PATCH', p: '/posts/{id}/acf/{field}',    d: 'Update ACF field value' }
					]
				},
				{
					name: 'Observability',
					endpoints: [
						{ m: 'GET', p: '/health',       d: 'System health check (no auth required)' },
						{ m: 'GET', p: '/system-info',   d: 'WordPress/PHP/server versions' },
						{ m: 'GET', p: '/plugins',       d: 'List installed plugins with status' },
						{ m: 'GET', p: '/themes',        d: 'List installed themes with status' },
						{ m: 'GET', p: '/logs',          d: 'Read debug.log (filter by level/lines)' },
						{ m: 'GET', p: '/audit',         d: 'Query audit trail entries' },
						{ m: 'GET', p: '/settings',      d: 'Read WordPress settings (safe subset)' }
					]
				},
				{
					name: 'System',
					endpoints: [
						{ m: 'POST', p: '/system/flush-cache', d: 'Flush all caches (SpeedyCache + WP)' }
					]
				},
				{
					name: 'Admin Settings',
					endpoints: [
						{ m: 'GET',   p: '/admin/settings', d: 'Get plugin settings (admin only)' },
						{ m: 'PATCH', p: '/admin/settings', d: 'Update plugin settings (admin only)' }
					]
				}
			];

			let h = '<div style="margin-bottom:16px"><p style="color:#787c82">Base URL: <code>' + this.esc(base) + '</code> &mdash; All endpoints require WP Application Password (Basic Auth) unless noted.</p></div>';

			groups.forEach(g => {
				h += '<div class="ai-gateway-api-group">';
				h += '<h3>' + this.esc(g.name) + ' <span class="ai-gateway-badge badge-ok" style="font-size:10px;vertical-align:middle">' + g.endpoints.length + '</span></h3>';
				g.endpoints.forEach(ep => {
					h += '<div class="ai-gateway-endpoint-row">';
					h += '<span class="ai-gateway-method method-' + ep.m.toLowerCase() + '">' + ep.m + '</span>';
					h += '<span class="ai-gateway-endpoint-path">' + this.esc(ep.p) + '</span>';
					h += '<span class="ai-gateway-endpoint-desc">' + this.esc(ep.d) + '</span>';
					h += '</div>';
				});
				h += '</div>';
			});

			el.innerHTML = h;
			this.isLoading = false;
		},

		/* ─── Audit Log ─── */
		loadAuditLog: function() {
			const el = document.getElementById('tab-audit-log');

			let h = '<div class="ai-gateway-filters">';
			h += '<input type="text" id="filter-endpoint" placeholder="Filter by endpoint..." class="regular-text">';
			h += '<select id="filter-status"><option value="">All statuses</option><option value="success">Success (2xx)</option><option value="error">Error (4xx+)</option></select>';
			h += '<button class="button" id="btn-apply-filter">Apply</button>';
			h += '</div>';
			h += '<div id="audit-table-wrap"></div>';
			h += '<div class="ai-gateway-pagination" id="audit-pagination"></div>';

			el.innerHTML = h;
			this.fetchAuditPage(1, 25);

			document.getElementById('btn-apply-filter').addEventListener('click', () => this.fetchAuditPage(1, 25));
		},

		fetchAuditPage: function(page, perPage) {
			const ep = (document.getElementById('filter-endpoint') || {}).value || '';
			let url = '/audit?page=' + page + '&per_page=' + perPage;
			if (ep) url += '&search=' + encodeURIComponent(ep);

			this.api(url).then(response => {
				const entries = this.extractAuditEntries(response);
				const wrap = document.getElementById('audit-table-wrap');
				const pag = document.getElementById('audit-pagination');

				if (entries.length > 0) {
					let h = '<table class="ai-gateway-table"><thead><tr><th>Endpoint</th><th>Method</th><th>Status</th><th>Timestamp</th><th>Action</th></tr></thead><tbody>';
					entries.forEach(e => {
						const sc = e.status_code || 0;
						h += '<tr>';
						h += '<td><code>' + this.esc(e.endpoint || '') + '</code></td>';
						h += '<td><span class="ai-gateway-method method-' + (e.method||'GET').toLowerCase() + '">' + this.esc(e.method||'') + '</span></td>';
						h += '<td><span class="ai-gateway-badge ' + (sc >= 400 ? 'badge-error' : 'badge-success') + '">' + sc + '</span></td>';
						h += '<td>' + this.fmtDate(new Date(e.timestamp)) + '</td>';
						h += '<td>' + this.esc(e.action_type || e.action || '') + '</td>';
						h += '</tr>';
					});
					h += '</tbody></table>';
					wrap.innerHTML = h;
				} else {
					wrap.innerHTML = '<div class="ai-gateway-empty"><div class="dashicons dashicons-list-view"></div><p>No audit entries found</p></div>';
				}

				const pagination = (response && response.data && response.data.pagination) ? response.data.pagination : {};
				const totalPages = pagination.total_pages || '?';
				pag.innerHTML = '<span>Page ' + (pagination.page || page) + ' / ' + totalPages + '</span>' +
					'<div class="ai-gateway-page-nav">' +
					'<button class="button" id="btn-prev">&larr; Prev</button>' +
					'<button class="button" id="btn-next">Next &rarr;</button></div>';

				document.getElementById('btn-prev').addEventListener('click', () => { if (page > 1) this.fetchAuditPage(page - 1, perPage); });
				document.getElementById('btn-next').addEventListener('click', () => this.fetchAuditPage(page + 1, perPage));

				this.isLoading = false;
			}).catch(() => {
				document.getElementById('audit-table-wrap').innerHTML = '<div class="ai-gateway-error"><p>Error loading audit log</p></div>';
				this.isLoading = false;
			});
		},

		/* ─── Health ─── */
		loadHealth: function() {
			const el = document.getElementById('tab-health');

			this.api('/health').then(data => {
				const checks = data.checks || {};

				// Build status grid from ACTUAL nested check results
				const items = [
					{ name: 'PHP Version',  st: this.checkStatus(checks.php_version) },
					{ name: 'WordPress',    st: data.wordpress_version ? 'ok' : 'warning' },
					{ name: 'REST API',     st: this.checkStatus(checks.rest_api) },
					{ name: 'Filesystem',   st: this.checkStatus(checks.filesystem) },
					{ name: 'Database',     st: this.checkStatus(checks.wp_options) },
					{ name: 'ACF Plugin',   st: data.acf_active ? 'ok' : 'warning' }
				];

				let h = '';

				// Overall status
				const overall = data.status || 'unknown';
				const oClass = overall === 'healthy' ? 'health-healthy' : overall === 'degraded' ? 'health-degraded' : 'health-unhealthy';
				h += '<div style="margin-bottom:20px"><span class="ai-gateway-health-overview ' + oClass + '">';
				h += '<span class="dashicons dashicons-' + (overall === 'healthy' ? 'yes-alt' : 'warning') + '"></span> ';
				h += this.esc(overall.toUpperCase());
				h += '</span></div>';

				// Status grid
				h += '<div class="ai-gateway-health-grid">';
				items.forEach(item => {
					const dotClass = item.st === 'ok' ? 'dot-ok' : item.st === 'warning' ? 'dot-warning' : 'dot-error';
					h += '<div class="ai-gateway-health-item">';
					h += '<span class="health-dot ' + dotClass + '"></span>';
					h += '<h4>' + this.esc(item.name) + '</h4>';
					h += '<span class="ai-gateway-badge badge-' + item.st + '">' + item.st.toUpperCase() + '</span>';
					h += '</div>';
				});
				h += '</div>';

				// Version info table
				h += '<div class="ai-gateway-card"><h3>Version Information</h3>';
				h += '<table class="ai-gateway-table"><thead><tr><th>Component</th><th>Value</th></tr></thead><tbody>';
				const info = [
					['Gateway',           data.gateway_version || 'Unknown'],
					['PHP',               data.php_version || 'Unknown'],
					['WordPress',         data.wordpress_version || 'Unknown'],
					['Active Theme',      data.active_theme || 'Unknown'],
					['Installed Plugins',  String(data.plugin_count || 0)]
				];
				info.forEach(([label, val]) => {
					h += '<tr><td>' + this.esc(label) + '</td><td><code>' + this.esc(val) + '</code></td></tr>';
				});
				h += '</tbody></table></div>';

				// Check details
				if (Object.keys(checks).length > 0) {
					h += '<div class="ai-gateway-card"><h3>Check Details</h3>';
					h += '<table class="ai-gateway-table"><thead><tr><th>Check</th><th>Status</th><th>Message</th></tr></thead><tbody>';
					Object.keys(checks).forEach(key => {
						const c = checks[key];
						h += '<tr>';
						h += '<td><code>' + this.esc(key) + '</code></td>';
						h += '<td><span class="ai-gateway-badge badge-' + (c.status || 'error') + '">' + (c.status || 'unknown').toUpperCase() + '</span></td>';
						h += '<td>' + this.esc(c.message || '') + '</td>';
						h += '</tr>';
					});
					h += '</tbody></table></div>';
				}

				el.innerHTML = h;
				this.isLoading = false;
			}).catch(err => {
				el.innerHTML = '<div class="ai-gateway-error"><p>Error loading health data</p></div>';
				this.isLoading = false;
			});
		},

		/* ─── Settings ─── */
		loadSettings: function() {
			const el = document.getElementById('tab-settings');

			this.api('/admin/settings').then(data => {
				let h = '<div class="ai-gateway-settings-section">';
				h += '<div class="ai-gateway-card"><h3>Plugin Settings</h3>';

				h += '<div class="ai-gateway-form-row">';
				h += '<label><input type="checkbox" id="cfg-debug" ' + (data.enable_debug_logging ? 'checked' : '') + '> Enable Debug Logging</label>';
				h += '<span class="description">Log detailed API request/response info for troubleshooting</span>';
				h += '</div>';

				h += '<div class="ai-gateway-form-actions">';
				h += '<button class="button button-primary" id="btn-save-settings">Save Settings</button>';
				h += '</div>';

				h += '</div></div>';

				el.innerHTML = h;
				this.isLoading = false;

				document.getElementById('btn-save-settings').addEventListener('click', () => {
					this.api('/admin/settings', {
						method: 'PATCH',
						body: JSON.stringify({
							enable_debug_logging: document.getElementById('cfg-debug').checked
						})
					}).then(() => {
						this.notice('Settings saved', 'success');
					}).catch(() => this.notice('Failed to save settings', 'error'));
				});
			}).catch(() => {
				el.innerHTML = '<div class="ai-gateway-error"><p>Error loading settings</p></div>';
				this.isLoading = false;
			});
		},

		/* ─── Endpoint Tester ─── */
		testLog: [],

		loadEndpointTester: function() {
			const el = document.getElementById('tab-endpoint-tester');

			const testGroups = [
				{
					name: 'Global Styles CSS',
					tests: [
						{ id: 'gs-css-get',   label: 'GET Global Styles CSS',    method: 'GET',    path: '/global-styles/css' },
						{ id: 'gs-get',        label: 'GET Full Global Styles',   method: 'GET',    path: '/global-styles' }
					]
				},
				{
					name: 'Custom CSS (Customizer)',
					tests: [
						{ id: 'css-get',   label: 'GET Custom CSS',    method: 'GET',    path: '/custom-css' }
					]
				},
				{
					name: 'Code Snippets (Database)',
					tests: [
						{ id: 'cs-list',   label: 'List Snippets',           method: 'GET',    path: '/code-snippets?per_page=5' },
						{ id: 'cs-get',    label: 'Get Snippet (ID=1)',      method: 'GET',    path: '/code-snippets/1' },
						{ id: 'cs-dupes',  label: 'Cleanup Duplicates (dry)', method: 'GET',   path: '/code-snippets?per_page=100', note: 'GET list to check for duplicates' }
					]
				},
				{
					name: 'Templates DB',
					tests: [
						{ id: 'tpl-list',  label: 'List Templates',     method: 'GET', path: '/templates-db?type=wp_template' },
						{ id: 'tpl-parts', label: 'List Template Parts', method: 'GET', path: '/templates-db?type=wp_template_part' }
					]
				},
				{
					name: 'Observability',
					tests: [
						{ id: 'health',    label: 'Health Check',     method: 'GET', path: '/health' },
						{ id: 'sysinfo',   label: 'System Info',      method: 'GET', path: '/system-info' },
						{ id: 'plugins',   label: 'List Plugins',     method: 'GET', path: '/plugins' },
						{ id: 'themes',    label: 'List Themes',      method: 'GET', path: '/themes' },
						{ id: 'audit',     label: 'Audit Log',        method: 'GET', path: '/audit?per_page=3' },
						{ id: 'rate',      label: 'Rate Limit Status', method: 'GET', path: '/rate-limit/status' }
					]
				},
				{
					name: 'Posts & Content',
					tests: [
						{ id: 'posts-list', label: 'List Posts',  method: 'GET', path: '/posts?per_page=3' },
						{ id: 'settings',   label: 'WP Settings', method: 'GET', path: '/settings' }
					]
				}
			];

			let h = '<div class="ai-gateway-toolbar">';
			h += '<div><strong>Endpoint Tester</strong> — Click "Test" to call each endpoint and see the response</div>';
			h += '<div>';
			h += '<button class="button" id="btn-run-all">Run All Tests</button> ';
			h += '<button class="button" id="btn-copy-log">Copy Log</button> ';
			h += '<button class="button" id="btn-clear-log">Clear Log</button>';
			h += '</div>';
			h += '</div>';

			testGroups.forEach(g => {
				h += '<div class="ai-gateway-card">';
				h += '<h3>' + this.esc(g.name) + '</h3>';
				g.tests.forEach(t => {
					h += '<div class="ai-gateway-test-row" data-test-id="' + t.id + '">';
					h += '<span class="ai-gateway-method method-' + t.method.toLowerCase() + '">' + t.method + '</span>';
					h += '<code class="ai-gateway-test-path">' + this.esc(t.path) + '</code>';
					h += '<span class="ai-gateway-test-label">' + this.esc(t.label) + '</span>';
					if (t.note) h += '<span class="ai-gateway-test-note">(' + this.esc(t.note) + ')</span>';
					h += '<span class="ai-gateway-test-status" id="status-' + t.id + '"></span>';
					h += '<button class="button button-small ai-gateway-test-btn" data-path="' + this.esc(t.path) + '" data-method="' + t.method + '" data-id="' + t.id + '">Test</button>';
					h += '</div>';
				});
				h += '</div>';
			});

			h += '<div class="ai-gateway-card">';
			h += '<h3>Response Output</h3>';
			h += '<pre id="test-response-output" class="ai-gateway-test-output">Click a "Test" button above to see the response here.</pre>';
			h += '</div>';

			h += '<div class="ai-gateway-card">';
			h += '<h3>Test Log</h3>';
			h += '<pre id="test-log-output" class="ai-gateway-test-output ai-gateway-test-log"></pre>';
			h += '</div>';

			el.innerHTML = h;
			this.isLoading = false;
			this.testLog = [];

			// Bind test buttons
			el.querySelectorAll('.ai-gateway-test-btn').forEach(btn => {
				btn.addEventListener('click', () => {
					this.runEndpointTest(btn.dataset.path, btn.dataset.method, btn.dataset.id);
				});
			});

			document.getElementById('btn-run-all').addEventListener('click', () => this.runAllTests(testGroups));
			document.getElementById('btn-copy-log').addEventListener('click', () => {
				const logEl = document.getElementById('test-log-output');
				navigator.clipboard.writeText(logEl.textContent)
					.then(() => this.notice('Log copied to clipboard', 'success'))
					.catch(() => this.notice('Copy failed', 'error'));
			});
			document.getElementById('btn-clear-log').addEventListener('click', () => {
				this.testLog = [];
				document.getElementById('test-log-output').textContent = '';
				document.getElementById('test-response-output').textContent = 'Click a "Test" button above to see the response here.';
				el.querySelectorAll('.ai-gateway-test-status').forEach(s => { s.textContent = ''; s.className = 'ai-gateway-test-status'; });
			});
		},

		runEndpointTest: function(path, method, testId) {
			const statusEl = document.getElementById('status-' + testId);
			const outputEl = document.getElementById('test-response-output');

			if (statusEl) {
				statusEl.textContent = '...';
				statusEl.className = 'ai-gateway-test-status';
			}

			const startTime = Date.now();

			const opts = {
				method: method,
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.nonce }
			};

			fetch(this.apiRoot + path, opts).then(r => {
				const elapsed = Date.now() - startTime;
				const status = r.status;

				return r.text().then(text => {
					let formatted;
					try {
						const json = JSON.parse(text);
						formatted = JSON.stringify(json, null, 2);
					} catch (e) {
						formatted = text;
					}

					// Update status badge
					if (statusEl) {
						const ok = status >= 200 && status < 400;
						statusEl.textContent = status + ' (' + elapsed + 'ms)';
						statusEl.className = 'ai-gateway-test-status ai-gateway-badge ' + (ok ? 'badge-success' : 'badge-error');
					}

					// Update response output
					if (outputEl) {
						outputEl.textContent = method + ' ' + path + '\nStatus: ' + status + ' (' + elapsed + 'ms)\n\n' + formatted;
					}

					// Add to log
					const logEntry = '[' + new Date().toISOString() + '] ' + method + ' ' + path + ' => ' + status + ' (' + elapsed + 'ms)';
					this.testLog.push(logEntry);
					const logEl = document.getElementById('test-log-output');
					if (logEl) logEl.textContent = this.testLog.join('\n');
				});
			}).catch(err => {
				const elapsed = Date.now() - startTime;

				if (statusEl) {
					statusEl.textContent = 'ERR';
					statusEl.className = 'ai-gateway-test-status ai-gateway-badge badge-error';
				}

				if (outputEl) {
					outputEl.textContent = method + ' ' + path + '\nError: ' + err.message + ' (' + elapsed + 'ms)';
				}

				const logEntry = '[' + new Date().toISOString() + '] ' + method + ' ' + path + ' => ERROR: ' + err.message + ' (' + elapsed + 'ms)';
				this.testLog.push(logEntry);
				const logEl = document.getElementById('test-log-output');
				if (logEl) logEl.textContent = this.testLog.join('\n');
			});
		},

		runAllTests: async function(testGroups) {
			const allTests = [];
			testGroups.forEach(g => {
				g.tests.forEach(t => allTests.push(t));
			});

			for (const t of allTests) {
				this.runEndpointTest(t.path, t.method, t.id);
				// Small delay between requests to avoid overwhelming
				await new Promise(r => setTimeout(r, 300));
			}
		},

		/* ─── Helpers ─── */
		checkStatus: function(check) {
			if (!check || typeof check !== 'object') return 'error';
			return check.status || 'error';
		},

		extractAuditEntries: function(response) {
			if (response && response.data && response.data.entries) return response.data.entries;
			if (Array.isArray(response)) return response;
			return [];
		},

		api: function(endpoint, opts) {
			const o = Object.assign({
				method: 'GET',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.nonce }
			}, opts || {});
			return fetch(this.apiRoot + endpoint, o).then(r => {
				if (!r.ok) throw new Error('API ' + r.status);
				return r.json();
			});
		},

		notice: function(msg, type) {
			const c = document.getElementById('ai-gateway-notice-container');
			if (!c) return;
			const n = document.createElement('div');
			n.className = 'notice notice-' + type + ' is-dismissible';
			n.innerHTML = '<p>' + this.esc(msg) + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>';
			c.appendChild(n);
			n.querySelector('.notice-dismiss').addEventListener('click', () => n.remove());
			setTimeout(() => n.remove(), 4000);
		},

		relTime: function(d) {
			const s = Math.floor((new Date() - d) / 1000);
			if (s < 60) return 'Just now';
			if (s < 3600) { const m = Math.floor(s / 60); return m + (m === 1 ? ' min' : ' mins') + ' ago'; }
			if (s < 86400) { const h = Math.floor(s / 3600); return h + (h === 1 ? ' hour' : ' hours') + ' ago'; }
			const days = Math.floor(s / 86400);
			return days + (days === 1 ? ' day' : ' days') + ' ago';
		},

		fmtDate: function(d) { return d.toLocaleString(); },

		esc: function(t) {
			if (t == null) return '';
			const s = String(t);
			const m = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
			return s.replace(/[&<>"']/g, c => m[c]);
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => D.init());
	} else {
		D.init();
	}

	window.aiGatewayDashboard = D;
})(window);
