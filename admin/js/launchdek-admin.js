(function () {
	'use strict';

	if (typeof launchdekAdmin === 'undefined') {
		return;
	}

	var API = launchdekAdmin.restUrl.replace(/\/$/, '');
	var nonce = launchdekAdmin.nonce;
	var strings = launchdekAdmin.strings || {};

	function request(method, path, body) {
		var opts = {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce
			},
			credentials: 'same-origin'
		};
		if (body) {
			opts.body = JSON.stringify(body);
		}
		return fetch(API + path, opts).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					var msg = (data && data.message) ? data.message : strings.error;
					throw new Error(msg);
				}
				return data;
			});
		});
	}

	function get(path) { return request('GET', path); }
	function post(path, body) { return request('POST', path, body); }
	function put(path, body) { return request('PUT', path, body); }
	function del(path) { return request('DELETE', path); }

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'className') node.className = attrs[k];
				else if (k === 'text') node.textContent = attrs[k];
				else if (k === 'html') node.innerHTML = attrs[k];
				else node.setAttribute(k, attrs[k]);
			});
		}
		if (children) {
			(children instanceof Array ? children : [children]).forEach(function (c) {
				if (c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
			});
		}
		return node;
	}

	function notice(container, message, type) {
		if (!container) return;
		container.innerHTML = '<div class="notice-inline ' + (type || '') + '">' + message + '</div>';
	}

	function fillSelect(select, items, valueKey, labelKey, placeholder) {
		if (!select) return;
		select.innerHTML = '';
		if (placeholder) {
			select.appendChild(el('option', { value: '', text: placeholder }));
		}
		items.forEach(function (item) {
			select.appendChild(el('option', {
				value: String(item[valueKey]),
				text: item[labelKey]
			}));
		});
	}

	// ─── Dashboard ───────────────────────────────────────────
	function initDashboard() {
		var page = document.querySelector('[data-launchdek-page="dashboard"]');
		if (!page) return;

		get('/dashboard/stats').then(function (stats) {
			Object.keys(stats).forEach(function (key) {
				var node = page.querySelector('[data-stat="' + key + '"]');
				if (!node) return;
				if (key === 'completion_rate') {
					node.textContent = stats[key] + '%';
				} else if (typeof stats[key] === 'number') {
					node.textContent = stats[key].toLocaleString();
				} else {
					node.textContent = stats[key];
				}
			});
		}).catch(function () {});

		get('/connections/ticker').then(function (sites) {
			var ticker = document.getElementById('launchdek-ticker');
			if (!sites.length) {
				ticker.innerHTML = '<p class="launchdek-muted">No sites registered yet.</p>';
				return;
			}
			ticker.innerHTML = '';
			sites.forEach(function (site, index) {
				if (index > 0) {
					ticker.appendChild(el('span', { className: 'launchdek-ticker-sep', text: '|', 'aria-hidden': 'true' }));
				}
				ticker.appendChild(el('span', { className: 'launchdek-ticker-chip ' + (site.status || 'unknown') }, [
					el('span', { className: 'launchdek-ticker-dot ' + (site.status || 'unknown') }),
					el('span', { text: (site.name || 'Unknown') + ' (' + (site.label || 'Unknown') + ')' })
				]));
			});
		}).catch(function () {});

		get('/logs/feed?limit=15').then(function (logs) {
			var feed = document.getElementById('launchdek-log-feed');
			if (!logs.length) {
				feed.innerHTML = '<p class="launchdek-muted">No activity yet.</p>';
				return;
			}
			feed.innerHTML = '';
			logs.forEach(function (log) {
				feed.appendChild(el('div', { className: 'launchdek-log-item' }, [
					el('time', { text: '[' + (log.created_at || '') + ']' }),
					el('span', { text: ' ' + (log.message || log.action) })
				]));
			});
		}).catch(function () {});

		Promise.all([get('/sites'), get('/workflows?is_template=0')]).then(function (results) {
			fillSelect(document.getElementById('launchdek-quick-site'), results[0], 'id', 'name', 'Select site…');
			fillSelect(document.getElementById('launchdek-quick-workflow'), results[1], 'id', 'title', 'Select workflow…');
		});

		document.getElementById('launchdek-quick-launch').addEventListener('click', function () {
			var siteId = document.getElementById('launchdek-quick-site').value;
			var wfId = document.getElementById('launchdek-quick-workflow').value;
			var result = document.getElementById('launchdek-quick-result');
			if (!siteId || !wfId) {
				notice(result, 'Select both a site and workflow.', 'error');
				return;
			}
			post('/runs', { site_id: parseInt(siteId, 10), workflow_id: parseInt(wfId, 10) })
				.then(function (data) {
					notice(result, 'Run #' + data.run_id + ' started. <a href="' + launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-automation">Open runner →</a>', 'success');
				})
				.catch(function (err) { notice(result, err.message, 'error'); });
		});
	}

	// ─── Sites ───────────────────────────────────────────────
	var currentSiteId = null;

	function healthLabel(status) {
		if (status === 'healthy') return strings.healthOk || 'OK';
		if (status === 'unhealthy') return strings.healthFail || 'Fail';
		return strings.healthUnknown || 'Unknown';
	}

	function loadSites() {
		var tag = document.getElementById('launchdek-filter-tag');
		var group = document.getElementById('launchdek-filter-group');
		var qs = '?';
		if (tag && tag.value) qs += 'tag=' + encodeURIComponent(tag.value) + '&';
		if (group && group.value) qs += 'group_type=' + encodeURIComponent(group.value) + '&';

		get('/sites' + qs).then(function (sites) {
			var tbody = document.querySelector('#launchdek-sites-table tbody');
			tbody.innerHTML = '';
			if (!sites.length) {
				tbody.innerHTML = '<tr><td colspan="6" class="launchdek-muted">No sites registered yet.</td></tr>';
				return;
			}
			sites.forEach(function (site) {
				var tr = el('tr');
				tr.innerHTML =
					'<td>' + site.name + '</td>' +
					'<td><a href="' + site.url + '" target="_blank" rel="noopener">' + site.url + '</a></td>' +
					'<td>' + (site.wp_version || '—') + '</td>' +
					'<td>' + (site.php_version || '—') + '</td>' +
					'<td><span class="launchdek-badge ' + site.health_status + '">' + healthLabel(site.health_status) + '</span></td>' +
					'<td class="launchdek-actions">' +
					'<button type="button" class="button button-small launchdek-edit-site" data-id="' + site.id + '">Edit</button> ' +
					'<button type="button" class="button button-small launchdek-test-site" data-id="' + site.id + '">Test</button>' +
					'</td>';
				tbody.appendChild(tr);
			});
			bindSiteActions();
		});
	}

	function bindSiteActions() {
		document.querySelectorAll('.launchdek-edit-site').forEach(function (btn) {
			btn.onclick = function () { openSiteModal(parseInt(btn.dataset.id, 10)); };
		});
		document.querySelectorAll('.launchdek-test-site').forEach(function (btn) {
			btn.onclick = function () {
				post('/sites/' + btn.dataset.id + '/test', {}).then(function (r) {
					alert(r.success ? (strings.connectionOk + ': ' + r.message) : (strings.connectionFail + ': ' + r.message));
					loadSites();
				}).catch(function (e) { alert(e.message); });
			};
		});
	}

	function openSiteModal(id) {
		currentSiteId = id || null;
		var modal = document.getElementById('launchdek-site-modal');
		var deleteBtn = document.getElementById('launchdek-site-delete');
		document.getElementById('launchdek-site-modal-title').textContent = id ? 'Edit Remote Site' : 'Add Remote Site';
		document.getElementById('launchdek-site-id').value = id || '';
		document.getElementById('launchdek-site-test-result').innerHTML = '';
		deleteBtn.hidden = !id;

		if (id) {
			get('/sites/' + id).then(function (site) {
				document.getElementById('launchdek-site-name').value = site.name;
				document.getElementById('launchdek-site-url').value = site.url;
				document.getElementById('launchdek-site-username').value = site.admin_username;
				document.getElementById('launchdek-site-password').value = '';
				document.getElementById('launchdek-site-tags').value = (site.tags || []).map(function (t) { return t.tag; }).join(', ');
				if (site.tags && site.tags[0]) {
					document.getElementById('launchdek-site-group').value = site.tags[0].group_type || 'general';
				}
			});
		} else {
			document.getElementById('launchdek-site-form').reset();
		}
		modal.hidden = false;
	}

	function openConnectionTesterModal() {
		var modal = document.getElementById('launchdek-connection-tester-modal');
		document.getElementById('launchdek-connection-tester-form').reset();
		document.getElementById('launchdek-tester-result').innerHTML = '';
		modal.hidden = false;
	}

	function closeModal(modal) { modal.hidden = true; }

	function parseTagsInput() {
		var raw = document.getElementById('launchdek-site-tags').value;
		var group = document.getElementById('launchdek-site-group').value;
		return raw.split(',').map(function (t) { return t.trim(); }).filter(Boolean).map(function (t) {
			return { tag: t, group_type: group };
		});
	}

	function initSites() {
		if (!document.querySelector('[data-launchdek-page="sites"]')) return;

		loadSites();
		get('/sites/tags').then(function (tags) {
			var sel = document.getElementById('launchdek-filter-tag');
			tags.forEach(function (t) {
				sel.appendChild(el('option', { value: t.tag, text: t.tag + ' (' + t.group_type + ')' }));
			});
		});

		document.getElementById('launchdek-add-site').addEventListener('click', function () { openSiteModal(null); });
		document.getElementById('launchdek-connection-tester').addEventListener('click', openConnectionTesterModal);
		document.getElementById('launchdek-filter-tag').addEventListener('change', loadSites);
		document.getElementById('launchdek-filter-group').addEventListener('change', loadSites);
		document.querySelectorAll('#launchdek-site-modal .launchdek-modal-close, #launchdek-site-modal .launchdek-modal-backdrop').forEach(function (n) {
			n.addEventListener('click', function () { closeModal(document.getElementById('launchdek-site-modal')); });
		});
		document.querySelectorAll('#launchdek-connection-tester-modal .launchdek-modal-close, #launchdek-connection-tester-modal .launchdek-modal-backdrop').forEach(function (n) {
			n.addEventListener('click', function () { closeModal(document.getElementById('launchdek-connection-tester-modal')); });
		});

		document.getElementById('launchdek-site-delete').addEventListener('click', function () {
			if (!currentSiteId || !confirm(strings.confirmDeleteSite || strings.confirmDelete)) return;
			del('/sites/' + currentSiteId).then(function () {
				closeModal(document.getElementById('launchdek-site-modal'));
				loadSites();
			}).catch(function (e) { alert(e.message); });
		});

		document.getElementById('launchdek-connection-tester-form').addEventListener('submit', function (e) {
			e.preventDefault();
			var result = document.getElementById('launchdek-tester-result');
			var payload = {
				url: document.getElementById('launchdek-tester-url').value,
				admin_username: document.getElementById('launchdek-tester-username').value,
				app_password: document.getElementById('launchdek-tester-password').value
			};
			post('/sites/test', payload).then(function (r) {
				notice(result, r.success ? r.message : (strings.connectionFail + ': ' + r.message), r.success ? 'success' : 'error');
			}).catch(function (err) { notice(result, err.message, 'error'); });
		});

		document.getElementById('launchdek-site-test').addEventListener('click', function () {
			var result = document.getElementById('launchdek-site-test-result');
			var payload = {
				url: document.getElementById('launchdek-site-url').value,
				admin_username: document.getElementById('launchdek-site-username').value,
				app_password: document.getElementById('launchdek-site-password').value
			};
			var promise = currentSiteId && !payload.app_password
				? post('/sites/' + currentSiteId + '/test', {})
				: post('/sites/test', payload);
			promise.then(function (r) {
				notice(result, r.success ? r.message : ('Failed: ' + r.message), r.success ? 'success' : 'error');
			}).catch(function (e) { notice(result, e.message, 'error'); });
		});

		document.getElementById('launchdek-site-form').addEventListener('submit', function (e) {
			e.preventDefault();
			var payload = {
				name: document.getElementById('launchdek-site-name').value,
				url: document.getElementById('launchdek-site-url').value,
				admin_username: document.getElementById('launchdek-site-username').value,
				tags: parseTagsInput()
			};
			var pw = document.getElementById('launchdek-site-password').value;
			if (pw) payload.app_password = pw;

			var promise = currentSiteId
				? put('/sites/' + currentSiteId, payload)
				: post('/sites', payload);

			promise.then(function () {
				closeModal(document.getElementById('launchdek-site-modal'));
				loadSites();
			}).catch(function (err) { alert(err.message); });
		});
	}

	// ─── Workflows ───────────────────────────────────────────
	var currentWorkflowId = null;
	var currentSteps = [];
	var selectedStepIndex = null;
	var dragSrcIndex = null;

	function loadWorkflowList() {
		get('/workflows?is_template=0').then(function (workflows) {
			var list = document.getElementById('launchdek-workflow-list');
			list.innerHTML = '';
			workflows.forEach(function (wf) {
				var li = el('li', { text: wf.title + ' (' + (wf.steps || []).length + ' steps)' });
				li.dataset.id = wf.id;
				if (currentWorkflowId === wf.id) li.className = 'active';
				li.onclick = function () { loadWorkflowEditor(wf.id); };
				list.appendChild(li);
			});
		});
	}

	function loadWorkflowEditor(id) {
		currentWorkflowId = id;
		get('/workflows/' + id).then(function (wf) {
			document.getElementById('launchdek-workflow-editor').hidden = false;
			document.getElementById('launchdek-wf-title').value = wf.title;
			document.getElementById('launchdek-wf-description').value = wf.description || '';
			currentSteps = wf.steps || [];
			selectedStepIndex = null;
			renderSteps();
			renderStepConfig();
			loadWorkflowList();
		});
	}

	function newWorkflow() {
		currentWorkflowId = null;
		currentSteps = [];
		selectedStepIndex = null;
		document.getElementById('launchdek-workflow-editor').hidden = false;
		document.getElementById('launchdek-wf-title').value = '';
		document.getElementById('launchdek-wf-description').value = '';
		renderSteps();
		renderStepConfig();
	}

	function renderSteps() {
		var canvas = document.getElementById('launchdek-canvas-steps');
		if (!canvas) return;
		canvas.innerHTML = '';

		if (!currentSteps.length) {
			canvas.appendChild(el('p', {
				className: 'launchdek-muted launchdek-canvas-empty',
				text: 'Add a step to begin building your workflow.'
			}));
			return;
		}

		var flow = el('div', { className: 'launchdek-canvas-flow' });
		currentSteps.forEach(function (step, i) {
			if (i > 0) {
				flow.appendChild(el('div', { className: 'launchdek-canvas-connector', 'aria-hidden': 'true', text: '→' }));
			}

			var node = el('div', {
				className: 'launchdek-canvas-node' + (selectedStepIndex === i ? ' active' : ''),
				draggable: 'true',
				'data-index': String(i),
				role: 'button',
				tabIndex: '0',
				'aria-label': (step.title || 'Step ' + (i + 1)) + ' (' + (step.type || 'manual') + ')'
			});

			var roles = (step.target_roles || []).map(function (slug) {
				return (launchdekAdmin.roles && launchdekAdmin.roles[slug]) || slug;
			});

			node.innerHTML =
				'<span class="launchdek-canvas-node-num">' + (i + 1) + '</span>' +
				'<span class="launchdek-canvas-node-title">' + escHtml(step.title || 'Step ' + (i + 1)) + '</span>' +
				'<span class="launchdek-canvas-node-type">' + escHtml(step.type || 'manual') + '</span>' +
				(roles.length ? '<span class="launchdek-canvas-node-roles">' + escHtml(roles.join(', ')) + '</span>' : '');

			node.addEventListener('click', function () { selectedStepIndex = i; renderSteps(); renderStepConfig(); });
			node.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					selectedStepIndex = i;
					renderSteps();
					renderStepConfig();
				}
			});
			node.addEventListener('dragstart', function (e) {
				dragSrcIndex = i;
				node.classList.add('dragging');
				e.dataTransfer.effectAllowed = 'move';
			});
			node.addEventListener('dragend', function () { node.classList.remove('dragging'); });
			node.addEventListener('dragover', function (e) { e.preventDefault(); });
			node.addEventListener('drop', function (e) {
				e.preventDefault();
				var target = i;
				if (dragSrcIndex === null || dragSrcIndex === target) return;
				var moved = currentSteps.splice(dragSrcIndex, 1)[0];
				currentSteps.splice(target, 0, moved);
				selectedStepIndex = target;
				renderSteps();
				renderStepConfig();
			});
			flow.appendChild(node);
		});
		canvas.appendChild(flow);
	}

	function renderRoleMapping(step) {
		var roles = launchdekAdmin.roles || {};
		var selected = step.target_roles || [];
		var html = '<fieldset class="launchdek-role-mapping"><legend>Role Target Mapping</legend>';
		Object.keys(roles).forEach(function (slug) {
			var checked = selected.indexOf(slug) >= 0 ? ' checked' : '';
			html += '<label class="launchdek-role-option"><input type="checkbox" class="ld-target-role" value="' + escAttr(slug) + '"' + checked + ' /> ' + escHtml(roles[slug]) + '</label>';
		});
		html += '</fieldset>';
		return html;
	}

	function renderStepConfig() {
		var box = document.getElementById('launchdek-step-config');
		if (selectedStepIndex === null || !currentSteps[selectedStepIndex]) {
			box.innerHTML = '<p class="launchdek-muted">Select a step on the canvas to configure.</p>';
			return;
		}
		var step = currentSteps[selectedStepIndex];
		box.innerHTML =
			'<label>Step Title<input type="text" id="ld-step-title" value="' + escAttr(step.title || '') + '" /></label>' +
			'<label>Instructions<textarea id="ld-step-instructions" rows="3">' + escHtml(step.instructions || '') + '</textarea></label>' +
			renderRoleMapping(step) +
			'<label>Deep Link (admin path)<input type="text" id="ld-step-deeplink" value="' + escAttr(step.deep_link || '') + '" placeholder="options-permalink.php" /></label>' +
			'<label>Step Type<select id="ld-step-type"><option value="manual"' + (step.type === 'manual' ? ' selected' : '') + '>Manual</option><option value="api"' + (step.type === 'api' ? ' selected' : '') + '>API</option></select></label>' +
			'<div id="ld-api-config"' + (step.type === 'api' ? '' : ' style="display:none"') + '>' +
			'<h4 class="launchdek-config-subheading">API Payload Mapper</h4>' +
			'<label>HTTP Method<select id="ld-api-method"><option' + sel(step.api && step.api.method, 'GET') + '>GET</option><option' + sel(step.api && step.api.method, 'POST') + '>POST</option><option' + sel(step.api && step.api.method, 'PUT') + '>PUT</option><option' + sel(step.api && step.api.method, 'PATCH') + '>PATCH</option><option' + sel(step.api && step.api.method, 'DELETE') + '>DELETE</option></select></label>' +
			'<label>REST Route<input type="text" id="ld-api-route" value="' + escAttr((step.api && step.api.route) || '') + '" placeholder="/wp/v2/settings" /></label>' +
			'<label>JSON Payload<textarea id="ld-api-payload" rows="4">' + escHtml(JSON.stringify((step.api && step.api.payload) || {}, null, 2)) + '</textarea></label>' +
			'<button type="button" class="button" id="ld-validate-api">Validate API Step</button>' +
			'</div>' +
			'<button type="button" class="button" id="ld-remove-step" style="margin-top:8px">Remove Step</button>';

		document.getElementById('ld-step-type').onchange = function () {
			document.getElementById('ld-api-config').style.display = this.value === 'api' ? '' : 'none';
			saveStepFromForm();
		};
		['ld-step-title', 'ld-step-instructions', 'ld-step-deeplink', 'ld-api-method', 'ld-api-route', 'ld-api-payload'].forEach(function (id) {
			var node = document.getElementById(id);
			if (node) node.onchange = saveStepFromForm;
		});
		box.querySelectorAll('.ld-target-role').forEach(function (input) {
			input.onchange = saveStepFromForm;
		});
		document.getElementById('ld-remove-step').onclick = function () {
			currentSteps.splice(selectedStepIndex, 1);
			selectedStepIndex = null;
			renderSteps();
			renderStepConfig();
		};
		document.getElementById('ld-validate-api').onclick = function () {
			saveStepFromForm();
			post('/workflows/0/validate-step', currentSteps[selectedStepIndex]).then(function (r) {
				alert(r.valid ? 'API step is valid.' : r.errors.join('\n'));
			}).catch(function (e) { alert(e.message); });
		};
	}

	function sel(val, expected) { return val === expected ? ' selected' : ''; }
	function escAttr(s) { return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
	function escHtml(s) { return String(s).replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

	function saveStepFromForm() {
		if (selectedStepIndex === null) return;
		var step = currentSteps[selectedStepIndex];
		step.title = document.getElementById('ld-step-title').value;
		step.instructions = document.getElementById('ld-step-instructions').value;
		step.deep_link = document.getElementById('ld-step-deeplink').value;
		step.type = document.getElementById('ld-step-type').value;
		step.target_roles = [];
		document.querySelectorAll('.ld-target-role:checked').forEach(function (input) {
			step.target_roles.push(input.value);
		});
		if (step.type === 'api') {
			var payload = {};
			try { payload = JSON.parse(document.getElementById('ld-api-payload').value || '{}'); } catch (e) {}
			step.api = {
				method: document.getElementById('ld-api-method').value,
				route: document.getElementById('ld-api-route').value,
				payload: payload
			};
		}
		renderSteps();
	}

	function initWorkflows() {
		if (!document.querySelector('[data-launchdek-page="workflows"]')) return;

		loadWorkflowList();

		document.getElementById('launchdek-new-workflow').onclick = newWorkflow;
		document.getElementById('launchdek-add-step').onclick = function () {
			currentSteps.push({
				id: 'step_' + (currentSteps.length + 1),
				title: 'New Step',
				instructions: '',
				target_roles: [],
				deep_link: '',
				type: 'manual',
				api: { method: 'GET', route: '', payload: {} }
			});
			selectedStepIndex = currentSteps.length - 1;
			renderSteps();
			renderStepConfig();
		};

		document.getElementById('launchdek-save-workflow').onclick = function () {
			if (selectedStepIndex !== null) saveStepFromForm();
			var payload = {
				title: document.getElementById('launchdek-wf-title').value,
				description: document.getElementById('launchdek-wf-description').value,
				steps: currentSteps
			};
			var promise = currentWorkflowId
				? put('/workflows/' + currentWorkflowId, payload)
				: post('/workflows', payload);
			promise.then(function (wf) {
				currentWorkflowId = wf.id;
				alert(strings.saved || 'Saved.');
				loadWorkflowList();
			}).catch(function (e) { alert(e.message); });
		};

		document.getElementById('launchdek-delete-workflow').onclick = function () {
			if (!currentWorkflowId || !confirm(strings.confirmDelete)) return;
			del('/workflows/' + currentWorkflowId).then(function () {
				currentWorkflowId = null;
				document.getElementById('launchdek-workflow-editor').hidden = true;
				loadWorkflowList();
			});
		};

		document.getElementById('launchdek-export-workflow').onclick = function () {
			if (!currentWorkflowId) return;
			get('/workflows/' + currentWorkflowId + '/export').then(function (data) {
				var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
				var a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = (data.title || 'workflow') + '.json';
				a.click();
			});
		};

		document.getElementById('launchdek-import-workflow').onclick = function () {
			var fileInput = document.getElementById('launchdek-import-file');
			var urlInput = document.getElementById('launchdek-import-url');
			if (fileInput.files.length) {
				var reader = new FileReader();
				reader.onload = function () {
					try {
						var data = JSON.parse(reader.result);
						post('/workflows/import', data).then(function () { loadWorkflowList(); alert('Imported.'); });
					} catch (e) { alert('Invalid JSON file.'); }
				};
				reader.readAsText(fileInput.files[0]);
			} else if (urlInput.value) {
				post('/workflows/import', { url: urlInput.value }).then(function () { loadWorkflowList(); alert('Imported.'); });
			}
		};
	}

	// ─── Automation ──────────────────────────────────────────
	var activeRunId = null;
	var batchQueue = [];
	var cachedSites = [];
	var cachedWorkflows = [];

	function loadRunSelects() {
		return Promise.all([get('/sites'), get('/workflows?is_template=0')]).then(function (r) {
			cachedSites = r[0] || [];
			cachedWorkflows = r[1] || [];

			var siteSelect = document.getElementById('launchdek-run-site');
			if (siteSelect) {
				siteSelect.innerHTML = '';
				cachedSites.forEach(function (site) {
					siteSelect.appendChild(el('option', {
						value: String(site.id),
						text: site.name || site.url
					}));
				});
			}

			fillSelect(document.getElementById('launchdek-drift-site'), cachedSites, 'id', 'name', 'All sites');
			fillSelect(document.getElementById('launchdek-run-workflow'), cachedWorkflows, 'id', 'title', 'Select workflow…');
		});
	}

	function getSelectedSiteIds() {
		var select = document.getElementById('launchdek-run-site');
		if (!select) return [];
		return Array.prototype.slice.call(select.selectedOptions).map(function (opt) {
			return parseInt(opt.value, 10);
		}).filter(function (id) { return id > 0; });
	}

	function getSelectedWorkflow() {
		var wfId = parseInt(document.getElementById('launchdek-run-workflow').value, 10);
		if (!wfId) return null;
		for (var i = 0; i < cachedWorkflows.length; i++) {
			if (cachedWorkflows[i].id === wfId) return cachedWorkflows[i];
		}
		return { id: wfId, title: 'Workflow #' + wfId };
	}

	function renderBatchQueue() {
		var tbody = document.querySelector('#launchdek-batch-table tbody');
		if (!tbody) return;
		tbody.innerHTML = '';

		if (!batchQueue.length) {
			var empty = el('tr', { className: 'launchdek-batch-empty' });
			empty.innerHTML = '<td colspan="5" class="launchdek-muted">No queued runs. Select sites and add to the batch queue.</td>';
			tbody.appendChild(empty);
			return;
		}

		batchQueue.forEach(function (item, index) {
			var tr = el('tr');
			var badgeClass = item.status === 'done' ? 'healthy' : (item.status === 'failed' ? 'unhealthy' : (item.status === 'running' ? 'active' : 'unknown'));
			tr.innerHTML =
				'<td>' + escHtml(item.site_name) + '</td>' +
				'<td>' + escHtml(item.workflow_title) + '</td>' +
				'<td><span class="launchdek-badge ' + badgeClass + '">' + escHtml(item.status) + '</span></td>' +
				'<td>' + (item.run_id ? ('#' + item.run_id) : '—') + '</td>' +
				'<td><button type="button" class="button button-link-delete" data-index="' + index + '">Remove</button></td>';
			tbody.appendChild(tr);
		});

		tbody.querySelectorAll('button[data-index]').forEach(function (btn) {
			btn.onclick = function () {
				batchQueue.splice(parseInt(btn.dataset.index, 10), 1);
				renderBatchQueue();
			};
		});
	}

	function addToBatchQueue() {
		var workflow = getSelectedWorkflow();
		var siteIds = getSelectedSiteIds();
		if (!workflow || !siteIds.length) {
			alert('Select a workflow and at least one target site.');
			return;
		}

		siteIds.forEach(function (siteId) {
			var site = cachedSites.find(function (s) { return s.id === siteId; });
			var exists = batchQueue.some(function (item) {
				return item.site_id === siteId && item.workflow_id === workflow.id && item.status === 'queued';
			});
			if (exists) return;
			batchQueue.push({
				site_id: siteId,
				site_name: site ? (site.name || site.url) : ('Site #' + siteId),
				workflow_id: workflow.id,
				workflow_title: workflow.title,
				status: 'queued',
				run_id: null
			});
		});

		renderBatchQueue();
	}

	function processBatchQueue() {
		var queued = batchQueue.filter(function (item) { return item.status === 'queued'; });
		if (!queued.length) {
			alert('No queued items to process.');
			return;
		}

		var workflowId = queued[0].workflow_id;
		var siteIds = queued.map(function (item) { return item.site_id; });

		queued.forEach(function (item) { item.status = 'running'; });
		renderBatchQueue();

		post('/runs/batch', { workflow_id: workflowId, site_ids: siteIds }).then(function (data) {
			(data.runs || []).forEach(function (result) {
				var run = result.run || {};
				batchQueue.forEach(function (item) {
					if (item.site_id === run.site_id && item.workflow_id === run.workflow_id) {
						item.status = 'done';
						item.run_id = run.id;
					}
				});
			});
			(data.errors || []).forEach(function (err) {
				batchQueue.forEach(function (item) {
					if (item.site_id === err.site_id) {
						item.status = 'failed';
					}
				});
			});
			renderBatchQueue();
			if (data.runs && data.runs.length) {
				renderRun(data.runs[0].run);
			}
			loadAudit();
			notice(document.getElementById('launchdek-run-notice'), 'Batch queue processed.', 'success');
		}).catch(function (e) {
			queued.forEach(function (item) { item.status = 'failed'; });
			renderBatchQueue();
			notice(document.getElementById('launchdek-run-notice'), e.message, 'error');
		});
	}

	function renderRun(run) {
		if (!run) return;
		activeRunId = run.id;
		document.getElementById('launchdek-run-info').innerHTML =
			'<strong>Run #' + run.id + '</strong><br>' +
			escHtml(run.workflow_title) + ' on ' + escHtml(run.site_name) +
			' <span class="launchdek-badge ' + escHtml(run.status) + '">' + escHtml(run.status) + '</span>';

		var list = document.getElementById('launchdek-run-steps');
		list.innerHTML = '';
		(run.steps || []).forEach(function (step) {
			var li = el('li', { className: 'launchdek-run-step status-' + step.status });
			var html = '<strong>' + (step.step_index + 1) + '. ' + escHtml(step.title) + '</strong>';
			html += '<div class="launchdek-run-step-meta">' + escHtml(step.step_type) + ' — ' + escHtml(step.status);
			if (step.manual_checked) html += ' ✓';
			html += '</div>';

			if (step.error_message) {
				html += '<div class="launchdek-run-step-error">' + escHtml(step.error_message) + '</div>';
			}

			if (step.response && step.response.deep_link) {
				html += '<div class="launchdek-run-step-meta"><a href="' + escAttr(step.response.deep_link) + '" target="_blank" rel="noopener">Open remote admin →</a></div>';
			}

			if (step.response && step.step_type === 'api') {
				var responseBody = step.response.body || step.response;
				html += '<details class="launchdek-run-step-response"><summary>API response</summary><pre>' +
					escHtml(JSON.stringify(responseBody, null, 2)) + '</pre></details>';
			}

			if (step.status === 'awaiting_manual') {
				html += '<button type="button" class="button button-small launchdek-complete-step" data-index="' + step.step_index + '">Mark Complete</button>';
			}

			li.innerHTML = html;
			list.appendChild(li);
		});

		document.querySelectorAll('.launchdek-complete-step').forEach(function (btn) {
			btn.onclick = function () {
				post('/runs/' + activeRunId + '/steps/' + btn.dataset.index + '/complete', {}).then(function (r) {
					renderRun(r.run);
					loadAudit();
				});
			};
		});
	}

	function formatAuditDiff(log) {
		var summary = log.details_summary || '';
		if (summary) return escHtml(summary);
		return escHtml(JSON.stringify(log.details || {}, null, 2));
	}

	function loadAuditMeta() {
		return get('/audit/meta').then(function (meta) {
			var userSelect = document.getElementById('launchdek-audit-user');
			if (!userSelect) return;
			fillSelect(userSelect, meta.users || [], 'id', 'name', 'All users');
		});
	}

	function loadAudit() {
		var search = document.getElementById('launchdek-audit-search').value;
		var status = document.getElementById('launchdek-audit-status').value;
		var userId = document.getElementById('launchdek-audit-user').value;
		var dateFrom = document.getElementById('launchdek-audit-date-from').value;
		var dateTo = document.getElementById('launchdek-audit-date-to').value;
		var qs = '?limit=50';
		if (search) qs += '&search=' + encodeURIComponent(search);
		if (status) qs += '&status=' + encodeURIComponent(status);
		if (userId) qs += '&user_id=' + encodeURIComponent(userId);
		if (dateFrom) qs += '&date_from=' + encodeURIComponent(dateFrom);
		if (dateTo) qs += '&date_to=' + encodeURIComponent(dateTo);

		get('/audit' + qs).then(function (logs) {
			var tbody = document.querySelector('#launchdek-audit-table tbody');
			tbody.innerHTML = '';
			if (!logs.length) {
				tbody.innerHTML = '<tr><td colspan="4" class="launchdek-muted">No audit entries match these filters.</td></tr>';
				return;
			}
			logs.forEach(function (log) {
				var tr = el('tr');
				tr.innerHTML =
					'<td>' + escHtml(log.created_at) + '</td>' +
					'<td>' + escHtml(log.user_name) + '</td>' +
					'<td>' + escHtml(log.site_name || '—') + '</td>' +
					'<td>' +
						'<div class="launchdek-audit-action"><span class="launchdek-badge ' + escHtml(log.outcome_class || 'unknown') + '">' + escHtml(log.action_label || log.action) + '</span></div>' +
						'<pre class="launchdek-audit-diff">' + formatAuditDiff(log) + '</pre>' +
					'</td>';
				tbody.appendChild(tr);
			});
		});
	}

	function loadDriftStatus() {
		get('/drift/status').then(function (status) {
			var box = document.getElementById('launchdek-drift-status');
			if (!box) return;

			var html = '';
			if (!status.enabled) {
				html += '<p>Background drift verification is disabled in Settings.</p>';
			} else {
				html += '<p><strong>Monitored checks:</strong> ' + escHtml((status.checks || []).join(', ')) + '</p>';
				html += '<p>Last run: ' + escHtml(status.last_run || 'Never') + ' · Next scheduled: ' + escHtml(status.next_scheduled || 'Not scheduled') + '</p>';
			}

			if (status.sites && status.sites.length) {
				html += '<div class="launchdek-drift-sites">';
				status.sites.forEach(function (site) {
					var badge = site.has_drift ? 'unhealthy' : (site.checked_at ? 'healthy' : 'unknown');
					var label = site.has_drift ? ('Drift detected (' + site.drift_count + ')') : (site.checked_at ? 'In sync' : 'Not checked');
					html += '<div class="launchdek-drift-site-row">' +
						'<span>' + escHtml(site.site_name) + '</span>' +
						'<span class="launchdek-badge ' + badge + '">' + escHtml(label) + '</span>' +
					'</div>';
				});
				html += '</div>';
			}

			box.innerHTML = html;
		});
	}

	function renderDriftResults(results) {
		var box = document.getElementById('launchdek-drift-results');
		if (!box) return;

		if (Array.isArray(results)) {
			box.innerHTML = '<pre style="background:#f6f7f7;padding:12px;overflow:auto;font-size:12px">' + escHtml(JSON.stringify(results, null, 2)) + '</pre>';
			return;
		}

		var html = '';
		Object.keys(results).forEach(function (siteId) {
			var result = results[siteId];
			if (!result || !result.success) return;
			html += '<div class="launchdek-drift-site-row">' +
				'<span>' + escHtml(result.site_name || ('Site #' + siteId)) + '</span>' +
				'<span class="launchdek-badge ' + (result.has_drift ? 'unhealthy' : 'healthy') + '">' +
				(result.has_drift ? 'Drift detected' : 'In sync') +
				'</span></div>';
			if (result.drifts && result.drifts.length) {
				html += '<pre class="launchdek-audit-diff">' + escHtml(JSON.stringify(result.drifts, null, 2)) + '</pre>';
			}
		});
		box.innerHTML = html || '<p class="launchdek-muted">No drift results returned.</p>';
	}

	function initAutomation() {
		if (!document.querySelector('[data-launchdek-page="automation"]')) return;

		loadRunSelects();
		loadAuditMeta().then(loadAudit);
		loadDriftStatus();

		document.getElementById('launchdek-queue-add').onclick = addToBatchQueue;
		document.getElementById('launchdek-queue-clear').onclick = function () {
			batchQueue = [];
			renderBatchQueue();
		};
		document.getElementById('launchdek-queue-process').onclick = processBatchQueue;

		document.getElementById('launchdek-start-run').onclick = function () {
			var siteIds = getSelectedSiteIds();
			var workflow = getSelectedWorkflow();
			if (!siteIds.length || !workflow) return alert('Select at least one site and a workflow.');
			if (siteIds.length > 1) {
				addToBatchQueue();
				processBatchQueue();
				return;
			}
			post('/runs', { site_id: siteIds[0], workflow_id: workflow.id })
				.then(function (data) {
					renderRun(data.run);
					loadAudit();
					notice(document.getElementById('launchdek-run-notice'), 'Run #' + data.run_id + ' started.', 'success');
				})
				.catch(function (e) {
					notice(document.getElementById('launchdek-run-notice'), e.message, 'error');
				});
		};

		document.getElementById('launchdek-run-next').onclick = function () {
			if (!activeRunId) return alert('Start a run first.');
			post('/runs/' + activeRunId + '/next', {}).then(function (r) { renderRun(r.run); loadAudit(); });
		};

		document.getElementById('launchdek-run-auto').onclick = function () {
			if (!activeRunId) return alert('Start a run first.');
			post('/runs/' + activeRunId + '/auto', {}).then(function (r) { renderRun(r.run); loadAudit(); });
		};

		document.getElementById('launchdek-verify-drift').onclick = function () {
			var siteId = document.getElementById('launchdek-drift-site').value;
			var path = siteId ? '/drift/verify/' + siteId : '/drift/verify';
			post(path, {}).then(function (results) {
				renderDriftResults(results);
				loadDriftStatus();
				loadAudit();
			});
		};

		document.getElementById('launchdek-audit-filter').onclick = loadAudit;
		renderBatchQueue();
	}

	// ─── Templates ───────────────────────────────────────────
	function formatStepsCount(count) {
		var template = strings.stepsCount || '%d steps';
		return template.replace('%d', count);
	}

	function renderBuiltinDetail(tpl) {
		var detail = document.getElementById('launchdek-builtin-detail');
		if (!detail || !tpl) return;

		detail.innerHTML =
			'<p class="launchdek-muted">' + escHtml(tpl.description || '') + '</p>' +
			'<p><small>' + escHtml(formatStepsCount((tpl.steps || []).length)) + '</small></p>';

		var btn = el('button', {
			className: 'button button-primary',
			text: strings.cloneToWorkflow || 'Clone to Workflow'
		});
		btn.onclick = function () {
			var noticeEl = document.getElementById('launchdek-builtin-notice');
			post('/templates/' + tpl.template_slug + '/clone', {}).then(function () {
				notice(noticeEl, strings.templateCloned || 'Template cloned. Edit it under Workflows.', 'success');
			}).catch(function (err) {
				notice(noticeEl, err.message, 'error');
			});
		};
		detail.appendChild(btn);
	}

	function renderBuiltinStacks(builtin) {
		var list = document.getElementById('launchdek-builtin-stacks');
		if (!list) return;

		list.innerHTML = '';
		if (!builtin.length) {
			list.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.loading || 'Loading…') + '</p>';
			return;
		}

		builtin.forEach(function (tpl, index) {
			if (index > 0) {
				list.appendChild(el('span', { className: 'launchdek-stack-sep', text: '|' }));
			}
			var chip = el('button', {
				type: 'button',
				className: 'launchdek-stack-chip' + (index === 0 ? ' active' : ''),
				text: tpl.title,
				'data-slug': tpl.template_slug,
				role: 'tab',
				'aria-selected': index === 0 ? 'true' : 'false'
			});
			chip.onclick = function () {
				list.querySelectorAll('.launchdek-stack-chip').forEach(function (node) {
					node.classList.remove('active');
					node.setAttribute('aria-selected', 'false');
				});
				chip.classList.add('active');
				chip.setAttribute('aria-selected', 'true');
				renderBuiltinDetail(tpl);
			};
			list.appendChild(chip);
		});

		renderBuiltinDetail(builtin[0]);
	}

	function loadVaultTemplates() {
		get('/templates/vault').then(function (vault) {
			var grid = document.getElementById('launchdek-vault-list');
			grid.innerHTML = '';
			if (!vault.length) {
				grid.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.noVaultTemplates || 'No vault templates yet.') + '</p>';
				return;
			}
			vault.forEach(function (tpl) {
				var card = el('div', { className: 'launchdek-template-card' });
				card.innerHTML =
					'<span class="launchdek-badge healthy">Vault</span>' +
					'<h3>' + escHtml(tpl.title) + '</h3>' +
					'<p>' + escHtml(tpl.description || '') + '</p>' +
					'<p><small>' + escHtml(formatStepsCount((tpl.steps || []).length)) + '</small></p>';
				grid.appendChild(card);
			});
		});
	}

	function initTemplates() {
		if (!document.querySelector('[data-launchdek-page="templates"]')) return;

		get('/templates').then(function (data) {
			renderBuiltinStacks(data.builtin || []);
		});

		get('/workflows?is_template=0').then(function (wfs) {
			fillSelect(document.getElementById('launchdek-vault-workflow'), wfs, 'id', 'title', 'Select workflow…');
		});

		loadVaultTemplates();

		document.getElementById('launchdek-save-vault').onclick = function () {
			var id = document.getElementById('launchdek-vault-workflow').value;
			var noticeEl = document.getElementById('launchdek-vault-notice');
			if (!id) return;
			post('/templates/vault', { workflow_id: parseInt(id, 10) }).then(function () {
				notice(noticeEl, strings.savedToVault || 'Saved to vault.', 'success');
				document.getElementById('launchdek-vault-workflow').value = '';
				loadVaultTemplates();
			}).catch(function (err) {
				notice(noticeEl, err.message, 'error');
			});
		};
	}

	// ─── Integrations ────────────────────────────────────────
	function initIntegrations() {
		var page = document.querySelector('[data-launchdek-page="integrations"]');
		if (!page) return;

		var connectorList = document.getElementById('launchdek-connectors-list');
		var telemetryBody = document.querySelector('#launchdek-telemetry-rules-table tbody');
		var telemetryNotice = document.getElementById('launchdek-telemetry-notice');
		var connectorModal = document.getElementById('launchdek-connector-modal');
		var connectorTitle = document.getElementById('launchdek-connector-modal-title');
		var connectorDescription = document.getElementById('launchdek-connector-modal-description');
		var connectorNotice = document.getElementById('launchdek-connector-modal-notice');
		var connectorDocs = document.getElementById('launchdek-connector-docs');
		var connectorPush = document.getElementById('launchdek-connector-push');
		var activeConnector = null;
		var fieldLabels = {};

		function openConnectorModal(item) {
			activeConnector = item;
			connectorTitle.textContent = item.name;
			connectorDescription.textContent = item.description || '';
			connectorNotice.innerHTML = '';
			connectorPush.disabled = !item.available;
			if (item.docs_url) {
				connectorDocs.href = item.docs_url;
				connectorDocs.hidden = false;
			} else {
				connectorDocs.hidden = true;
			}
			connectorModal.hidden = false;
		}

		document.querySelectorAll('#launchdek-connector-modal .launchdek-modal-close, #launchdek-connector-modal .launchdek-modal-backdrop').forEach(function (node) {
			node.addEventListener('click', function () { closeModal(connectorModal); });
		});

		connectorPush.addEventListener('click', function () {
			if (!activeConnector) return;
			connectorPush.disabled = true;
			post('/integrations/' + activeConnector.slug + '/push', {}).then(function (r) {
				var msg = r.message || r.error || strings.saved;
				notice(connectorNotice, msg, r.error ? 'error' : 'success');
			}).catch(function (e) {
				notice(connectorNotice, e.message, 'error');
			}).finally(function () {
				connectorPush.disabled = !activeConnector || !activeConnector.available;
			});
		});

		get('/integrations').then(function (items) {
			connectorList.innerHTML = '';
			items.forEach(function (item) {
				var row = el('li', { className: 'launchdek-connector-row ' + (item.available ? 'available' : 'unavailable') });
				var actions = el('div', { className: 'launchdek-connector-actions' });
				actions.appendChild(el('span', {
					className: 'launchdek-badge ' + (item.available ? 'healthy' : 'unknown'),
					text: item.available ? (strings.connected || 'Connected') : (strings.notDetected || 'Not Detected')
				}));
				var setupBtn = el('button', { className: 'button', text: strings.setup || 'Setup' });
				setupBtn.addEventListener('click', function () { openConnectorModal(item); });
				actions.appendChild(setupBtn);
				row.appendChild(el('span', { className: 'launchdek-connector-name', text: item.name }));
				row.appendChild(actions);
				connectorList.appendChild(row);
			});
		});

		get('/integrations/telemetry-rules').then(function (data) {
			fieldLabels = data.fields || {};
			telemetryBody.innerHTML = '';
			(data.rules || []).forEach(function (rule) {
				var tr = el('tr');
				var checkbox = el('input', { type: 'checkbox', 'data-rule-id': rule.id });
				checkbox.checked = !!rule.enabled;
				tr.appendChild(el('td', { className: 'check-column' }, [checkbox]));
				tr.appendChild(el('td', { text: rule.integration_name || rule.integration }));
				tr.appendChild(el('td', { text: rule.platform_field }));
				tr.appendChild(el('td', { text: fieldLabels[rule.launchdek_field] || rule.launchdek_field }));
				telemetryBody.appendChild(tr);
			});
		});

		document.getElementById('launchdek-save-telemetry-rules').addEventListener('click', function () {
			var rules = [];
			telemetryBody.querySelectorAll('input[type="checkbox"][data-rule-id]').forEach(function (checkbox) {
				rules.push({
					id: checkbox.getAttribute('data-rule-id'),
					enabled: checkbox.checked
				});
			});
			put('/integrations/telemetry-rules', { rules: rules }).then(function () {
				notice(telemetryNotice, strings.saved, 'success');
			}).catch(function (e) {
				notice(telemetryNotice, e.message, 'error');
			});
		});
	}

	// ─── Init ────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('.launchdek-admin');
		if (root) root.setAttribute('data-launchdek-ready', 'true');

		initDashboard();
		initSites();
		initWorkflows();
		initAutomation();
		initTemplates();
		initIntegrations();
	});
})();
