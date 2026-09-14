(function () {
	'use strict';

	if (typeof launchdekAdmin === 'undefined') {
		return;
	}

	var API = launchdekAdmin.restUrl.replace(/\/$/, '');
	var nonce = launchdekAdmin.nonce;
	var strings = launchdekAdmin.strings || {};

	/**
	 * Build a REST path that works with plain permalinks (?rest_route=…) and pretty permalinks.
	 */
	function resolveApiPath(path) {
		var qPos = path.indexOf('?');
		if (qPos === -1) {
			return path;
		}

		var routePath = path.substring(0, qPos);
		var query = path.substring(qPos + 1);
		if (!query) {
			return routePath;
		}

		if (API.indexOf('?') !== -1) {
			return routePath + '&' + query;
		}

		return path;
	}

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
		return fetch(API + resolveApiPath(path), opts).then(function (res) {
			return res.text().then(function (text) {
				var data = null;
				if (text) {
					try {
						data = JSON.parse(text);
					} catch (e) {
						if (res.status === 404) {
							throw new Error(strings.restUnavailable || 'REST API is unavailable. On the hub site, go to Settings → Permalinks, choose Post name, and save.');
						}
						throw new Error(strings.error || 'Something went wrong.');
					}
				}
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

	function escHtml(value) {
		return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

	// ─── Onboarding ──────────────────────────────────────────
	function parsePastedChecklist(text) {
		var lines = text.split(/\r?\n/).map(function (line) { return line.trim(); }).filter(Boolean);
		var title = '';
		var steps = [];
		var stepLine = /^(\[\s?[xX]?\s?\]|[-*•]|\d+[.)])\s*(.+)$/;

		lines.forEach(function (line) {
			var match = line.match(stepLine);
			if (match) {
				steps.push({ title: match[2].trim(), checked: false });
				return;
			}

			var heading = line.match(/^#+\s*(.+)$/);
			if (heading) {
				if (!title) {
					title = heading[1].trim();
				} else {
					steps.push({ title: heading[1].trim(), checked: false });
				}
				return;
			}

			if (!title) {
				title = line;
			} else {
				steps.push({ title: line, checked: false });
			}
		});

		return {
			title: title || 'Untitled Checklist',
			steps: steps
		};
	}

	function buildChecklistPayload(parsed) {
		return {
			title: parsed.title,
			description: '',
			steps: parsed.steps.map(function (step, index) {
				return {
					id: 'step_' + (index + 1),
					title: step.title,
					type: 'manual',
					instructions: '',
					target_roles: [],
					deep_link: ''
				};
			})
		};
	}

	function templateToPasteText(template) {
		var lines = [template.title || 'Untitled Checklist'];
		(template.steps || []).forEach(function (step) {
			lines.push('- ' + (step.title || 'Step'));
		});
		return lines.join('\n');
	}

	// ─── Template picker ─────────────────────────────────────
	var pickerTemplates = [];
	var pickerCategories = {};
	var pickerSelectedSlug = null;
	var pickerActiveCategory = 'all';
	var pickerLoaded = false;
	var pickerLoadingPromise = null;
	var pickerCallbacks = null;

	function loadPickerTemplates() {
		if (pickerLoaded) {
			return Promise.resolve();
		}
		if (pickerLoadingPromise) {
			return pickerLoadingPromise;
		}

		pickerLoadingPromise = get('/templates').then(function (data) {
			pickerTemplates = data.builtin || [];
			pickerCategories = data.categories || {};
			pickerLoaded = true;
		}).catch(function (err) {
			pickerLoadingPromise = null;
			throw err;
		});

		return pickerLoadingPromise;
	}

	function getPickerFilteredTemplates() {
		if (pickerActiveCategory === 'all') {
			return pickerTemplates.slice();
		}
		return pickerTemplates.filter(function (tpl) {
			return tpl.category === pickerActiveCategory;
		});
	}

	function getPickerTemplateBySlug(slug) {
		for (var i = 0; i < pickerTemplates.length; i++) {
			if (pickerTemplates[i].template_slug === slug) {
				return pickerTemplates[i];
			}
		}
		return null;
	}

	function renderPickerStepsPreview() {
		var stepsPanel = document.getElementById('launchdek-template-picker-steps');
		var emptyEl = stepsPanel ? stepsPanel.querySelector('.launchdek-template-picker-steps-empty') : null;
		var listEl = document.getElementById('launchdek-template-picker-steps-list');
		if (!stepsPanel || !emptyEl || !listEl) {
			return;
		}

		if (!pickerSelectedSlug) {
			emptyEl.hidden = false;
			emptyEl.textContent = strings.templateStepsEmpty || 'Select a template to preview its steps.';
			listEl.hidden = true;
			listEl.innerHTML = '';
			return;
		}

		var template = getPickerTemplateBySlug(pickerSelectedSlug);
		var steps = template ? (template.steps || []) : [];

		if (!steps.length) {
			emptyEl.hidden = false;
			emptyEl.textContent = strings.templateStepsNone || 'This template has no steps yet.';
			listEl.hidden = true;
			listEl.innerHTML = '';
			return;
		}

		emptyEl.hidden = true;
		listEl.hidden = false;
		listEl.innerHTML = '';
		steps.forEach(function (step) {
			listEl.appendChild(el('li', { text: step.title || 'Step' }));
		});
	}

	function updatePickerImportButton() {
		var importBtn = document.getElementById('launchdek-template-picker-import');
		if (importBtn) {
			importBtn.disabled = !pickerSelectedSlug;
		}
	}

	function renderPickerFilters() {
		var filtersEl = document.getElementById('launchdek-template-picker-filters');
		if (!filtersEl) return;

		filtersEl.innerHTML = '';

		var allBtn = el('button', {
			type: 'button',
			className: 'launchdek-template-picker-filter' + (pickerActiveCategory === 'all' ? ' is-active' : ''),
			text: (strings.allCategories || 'All') + ' (' + pickerTemplates.length + ')',
			'data-category': 'all',
			role: 'tab',
			'aria-selected': pickerActiveCategory === 'all' ? 'true' : 'false'
		});
		allBtn.onclick = function () {
			pickerActiveCategory = 'all';
			pickerSelectedSlug = null;
			renderPickerFilters();
			renderPickerList();
			renderPickerStepsPreview();
			updatePickerImportButton();
		};
		filtersEl.appendChild(allBtn);

		Object.keys(pickerCategories).forEach(function (slug) {
			var count = pickerTemplates.filter(function (tpl) {
				return tpl.category === slug;
			}).length;
			if (!count) {
				return;
			}

			var meta = pickerCategories[slug];
			var isActive = pickerActiveCategory === slug;
			var filterBtn = el('button', {
				type: 'button',
				className: 'launchdek-template-picker-filter' + (isActive ? ' is-active' : ''),
				text: (meta.label || slug) + ' (' + count + ')',
				'data-category': slug,
				role: 'tab',
				'aria-selected': isActive ? 'true' : 'false'
			});
			filterBtn.onclick = function () {
				pickerActiveCategory = slug;
				pickerSelectedSlug = null;
				renderPickerFilters();
				renderPickerList();
				renderPickerStepsPreview();
				updatePickerImportButton();
			};
			filtersEl.appendChild(filterBtn);
		});
	}

	function renderPickerList() {
		var listEl = document.getElementById('launchdek-template-picker-list');
		if (!listEl) return;

		var items = getPickerFilteredTemplates();
		listEl.innerHTML = '';

		if (!items.length) {
			listEl.innerHTML = '<p class="launchdek-muted launchdek-template-picker-empty">' + escHtml(strings.noCategoryTemplates || 'No templates in this category yet.') + '</p>';
			renderPickerStepsPreview();
			return;
		}

		items.forEach(function (tpl) {
			var slug = tpl.template_slug || '';
			var steps = tpl.steps || [];
			var isSelected = pickerSelectedSlug === slug;
			var item = el('button', {
				type: 'button',
				className: 'launchdek-template-picker-item' + (isSelected ? ' is-selected' : ''),
				role: 'option',
				'aria-selected': isSelected ? 'true' : 'false',
				'data-slug': slug
			});

			var body = el('div', { className: 'launchdek-template-picker-item-body' });
			body.appendChild(el('p', { className: 'launchdek-template-picker-item-title', text: tpl.title || slug }));
			body.appendChild(el('p', { className: 'launchdek-template-picker-item-desc', text: tpl.description || '' }));

			var meta = el('span', {
				className: 'launchdek-template-picker-item-meta',
				text: formatStepsCount(steps.length)
			});

			item.appendChild(body);
			item.appendChild(meta);

			item.onclick = function () {
				pickerSelectedSlug = slug;
				renderPickerList();
				renderPickerStepsPreview();
				updatePickerImportButton();
			};

			listEl.appendChild(item);
		});

		renderPickerStepsPreview();
	}

	function closeTemplatePicker() {
		var modal = document.getElementById('launchdek-template-picker-modal');
		if (modal) {
			modal.hidden = true;
		}
		var noticeEl = document.getElementById('launchdek-template-picker-notice');
		if (noticeEl) {
			noticeEl.innerHTML = '';
		}
		pickerCallbacks = null;
	}

	function openTemplatePicker(callbacks) {
		var modal = document.getElementById('launchdek-template-picker-modal');
		if (!modal) return;

		pickerCallbacks = callbacks || null;
		pickerSelectedSlug = null;
		pickerActiveCategory = 'all';
		renderPickerStepsPreview();

		var listEl = document.getElementById('launchdek-template-picker-list');
		if (listEl) {
			listEl.innerHTML = '<p class="launchdek-muted launchdek-template-picker-loading">' + escHtml(strings.loading || 'Loading…') + '</p>';
		}

		modal.hidden = false;
		updatePickerImportButton();

		loadPickerTemplates().then(function () {
			renderPickerFilters();
			renderPickerList();
			updatePickerImportButton();
		}).catch(function (err) {
			if (listEl) {
				listEl.innerHTML = '<p class="launchdek-muted launchdek-template-picker-empty">' + escHtml(err.message) + '</p>';
			}
		});
	}

	function importSelectedTemplate() {
		var noticeEl = document.getElementById('launchdek-template-picker-notice');
		var importBtn = document.getElementById('launchdek-template-picker-import');

		if (!pickerSelectedSlug) {
			notice(noticeEl, strings.selectTemplateFirst || 'Select a template to import.', 'error');
			return;
		}

		var callbacks = pickerCallbacks;
		var importMode = (callbacks && callbacks.importMode) || 'clone';

		if (importMode === 'populate') {
			var template = getPickerTemplateBySlug(pickerSelectedSlug);
			if (!template) {
				notice(noticeEl, strings.error || 'Something went wrong.', 'error');
				return;
			}
			closeTemplatePicker();
			if (callbacks && typeof callbacks.onImport === 'function') {
				callbacks.onImport(template);
			}
			return;
		}

		if (importBtn) {
			importBtn.disabled = true;
		}
		notice(noticeEl, '', '');

		post('/templates/' + pickerSelectedSlug + '/clone', {}).then(function (checklist) {
			var activeCallbacks = pickerCallbacks;
			closeTemplatePicker();
			if (activeCallbacks && typeof activeCallbacks.onImport === 'function') {
				activeCallbacks.onImport(checklist);
			}
		}).catch(function (err) {
			notice(noticeEl, err.message, 'error');
			updatePickerImportButton();
		}).finally(function () {
			if (importBtn) {
				importBtn.disabled = !pickerSelectedSlug;
			}
		});
	}

	function initTemplatePicker() {
		var modal = document.getElementById('launchdek-template-picker-modal');
		if (!modal) return;

		window.launchdekOpenTemplatePicker = openTemplatePicker;

		modal.querySelectorAll('.launchdek-template-picker-close, .launchdek-modal-backdrop').forEach(function (node) {
			node.addEventListener('click', closeTemplatePicker);
		});

		document.getElementById('launchdek-template-picker-cancel').addEventListener('click', closeTemplatePicker);

		document.getElementById('launchdek-template-picker-blank').addEventListener('click', function () {
			var callbacks = pickerCallbacks;
			closeTemplatePicker();
			if (callbacks && typeof callbacks.onBlank === 'function') {
				callbacks.onBlank();
			}
		});

		document.getElementById('launchdek-template-picker-import').addEventListener('click', importSelectedTemplate);
	}

	function initOnboarding() {
		var modal = document.getElementById('launchdek-onboarding-modal');
		if (!modal) return;

		var onboarding = launchdekAdmin.onboarding || {};
		var params = new URLSearchParams(window.location.search);
		var shouldShow = onboarding.show || params.get('onboarding') === '1';

		var step1 = document.getElementById('launchdek-onboarding-step-1');
		var step2 = document.getElementById('launchdek-onboarding-step-2');
		var pasteInput = document.getElementById('launchdek-onboarding-paste');
		var preview = document.getElementById('launchdek-onboarding-preview');
		var siteSelect = document.getElementById('launchdek-onboarding-site');
		var step1Notice = document.getElementById('launchdek-onboarding-step1-notice');
		var step2Notice = document.getElementById('launchdek-onboarding-step2-notice');
		var connectPanel = document.getElementById('launchdek-onboarding-connect-panel');
		var existingSiteNotice = document.getElementById('launchdek-onboarding-existing-site');
		var testStatus = document.getElementById('launchdek-onboarding-test-status');
		var launchBtn = document.getElementById('launchdek-onboarding-launch');
		var parsedChecklist = { title: '', steps: [] };
		var savedChecklistId = null;
		var selectedSiteId = '';
		var connectionVerified = false;

		function dismissOnboarding() {
			return post('/onboarding/dismiss', {}).catch(function () {});
		}

		function closeOnboarding() {
			modal.hidden = true;
		}

		function dismissAndCloseOnboarding() {
			closeOnboarding();
			dismissOnboarding();
		}

		function resetOnboardingModal() {
			step1.hidden = false;
			step2.hidden = true;
			step1Notice.innerHTML = '';
			step2Notice.innerHTML = '';
			pasteInput.value = '';
			siteSelect.value = '';
			document.getElementById('launchdek-onboarding-site-url').value = '';
			document.getElementById('launchdek-onboarding-site-username').value = '';
			document.getElementById('launchdek-onboarding-site-password').value = '';
			connectPanel.hidden = false;
			existingSiteNotice.hidden = true;
			existingSiteNotice.textContent = '';
			savedChecklistId = null;
			selectedSiteId = '';
			connectionVerified = false;
			setTestStatus('', '');
			updateOnboardingProgress(1);
			renderPreview();
		}

		function openOnboardingModal() {
			resetOnboardingModal();
			modal.hidden = false;
		}

		window.launchdekOpenOnboarding = openOnboardingModal;

		function getPasteText() {
			return pasteInput.value.trim() || pasteInput.placeholder || '';
		}

		function getPreviewSourceText() {
			if (pasteInput.value.trim()) {
				return { text: pasteInput.value, isSample: false };
			}

			if (pasteInput.placeholder) {
				return { text: pasteInput.placeholder, isSample: true };
			}

			return { text: '', isSample: false };
		}

		function renderPreview() {
			var source = getPreviewSourceText();
			parsedChecklist = parsePastedChecklist(source.text);
			if (!parsedChecklist.steps.length && !parsedChecklist.title) {
				preview.classList.remove('is-sample');
				preview.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.onboardingPreviewEmpty || 'Start typing to see your checklist preview.') + '</p>';
				return;
			}

			preview.classList.toggle('is-sample', source.isSample);
			var html = '<p class="launchdek-onboarding-preview-title"><strong>' + escHtml(parsedChecklist.title) + '</strong></p>';
			if (parsedChecklist.steps.length) {
				html += '<ul class="launchdek-onboarding-preview-list">';
				parsedChecklist.steps.forEach(function (step, index) {
					if (source.isSample) {
						html += '<li class="launchdek-onboarding-preview-item">' +
							'<input type="checkbox" id="launchdek-onboarding-preview-step-' + index + '" disabled />' +
							'<label for="launchdek-onboarding-preview-step-' + index + '">' + escHtml(step.title) + '</label>' +
							'</li>';
						return;
					}

					html += '<li class="launchdek-onboarding-preview-item">' +
						'<input type="checkbox" id="launchdek-onboarding-preview-step-' + index + '" data-preview-index="' + index + '"' + (step.checked ? ' checked' : '') + ' />' +
						'<label for="launchdek-onboarding-preview-step-' + index + '">' + escHtml(step.title) + '</label>' +
						'</li>';
				});
				html += '</ul>';
			}
			preview.innerHTML = html;

			if (source.isSample) {
				return;
			}

			preview.querySelectorAll('input[type="checkbox"][data-preview-index]').forEach(function (checkbox) {
				checkbox.addEventListener('change', function () {
					var idx = parseInt(checkbox.getAttribute('data-preview-index'), 10);
					if (parsedChecklist.steps[idx]) {
						parsedChecklist.steps[idx].checked = checkbox.checked;
					}
				});
			});
		}

		function setTestStatus(state, message) {
			testStatus.textContent = message || '';
			testStatus.className = 'launchdek-onboarding-test-status';
			if (state) {
				testStatus.classList.add(state);
			}
		}

		function deriveSiteName(url) {
			try {
				var hostname = new URL(url).hostname.replace(/^www\./, '');
				return hostname || url;
			} catch (e) {
				return url;
			}
		}

		function getConnectCredentials() {
			return {
				url: document.getElementById('launchdek-onboarding-site-url').value.trim(),
				admin_username: document.getElementById('launchdek-onboarding-site-username').value.trim(),
				app_password: document.getElementById('launchdek-onboarding-site-password').value.trim()
			};
		}

		function updateOnboardingProgress(step) {
			var progress = document.getElementById('launchdek-onboarding-progress');
			if (!progress) {
				return;
			}

			progress.setAttribute('aria-valuenow', String(step));
			progress.querySelectorAll('.launchdek-onboarding-progress-segment').forEach(function (segment) {
				var segmentStep = parseInt(segment.getAttribute('data-step'), 10);
				segment.classList.remove('is-active', 'is-complete');
				if (segmentStep < step) {
					segment.classList.add('is-complete');
				} else if (segmentStep === step) {
					segment.classList.add('is-active');
				}
			});
		}

		function updateStep2SiteState() {
			selectedSiteId = siteSelect.value;
			var hasExistingSite = !!selectedSiteId;
			connectPanel.hidden = hasExistingSite;
			existingSiteNotice.hidden = !hasExistingSite;

			if (hasExistingSite) {
				var option = siteSelect.options[siteSelect.selectedIndex];
				existingSiteNotice.textContent = (strings.onboardingExistingSiteReady || 'Using selected site:') + ' ' + (option ? option.textContent : '');
				connectionVerified = true;
				setTestStatus('success', strings.onboardingStatusConnected || 'Status: Connected & Verified (OK)');
				return;
			}

			connectionVerified = false;
			setTestStatus('', strings.onboardingStatusPending || 'Status: Not tested yet');
		}

		function showStep2() {
			step1.hidden = true;
			step2.hidden = false;
			updateOnboardingProgress(2);
			step2Notice.innerHTML = '';
			updateStep2SiteState();
		}

		function showStep1() {
			step2.hidden = true;
			step1.hidden = false;
			updateOnboardingProgress(1);
			step2Notice.innerHTML = '';
			setTestStatus('', '');
		}

		function finishOnboarding(runSiteId) {
			var tasks = [dismissOnboarding()];
			if (savedChecklistId && runSiteId) {
				tasks.push(post('/runs', {
					site_id: parseInt(runSiteId, 10),
					checklist_id: parseInt(savedChecklistId, 10)
				}));
			}

			return Promise.all(tasks).then(function (results) {
				closeOnboarding();
				if (results.length > 1 && onboarding.automationUrl) {
					window.location.href = onboarding.automationUrl;
				}
			});
		}

		get('/sites').then(function (sites) {
			fillSelect(siteSelect, sites, 'id', 'name', strings.onboardingSelectSite || 'Select site…');
		}).catch(function () {});

		pasteInput.addEventListener('input', renderPreview);
		siteSelect.addEventListener('change', updateStep2SiteState);
		renderPreview();
		updateOnboardingProgress(1);

		if (shouldShow) {
			openOnboardingModal();
		}

		modal.querySelectorAll('.launchdek-onboarding-close, .launchdek-modal-backdrop').forEach(function (node) {
			node.addEventListener('click', dismissAndCloseOnboarding);
		});

		document.getElementById('launchdek-onboarding-skip').addEventListener('click', dismissAndCloseOnboarding);

		document.getElementById('launchdek-onboarding-use-template').addEventListener('click', function () {
			openTemplatePicker({
				importMode: 'populate',
				onImport: function (template) {
					pasteInput.value = templateToPasteText(template);
					parsedChecklist = parsePastedChecklist(pasteInput.value);
					savedChecklistId = null;
					renderPreview();
					step1Notice.innerHTML = '';
				},
				onBlank: function () {}
			});
		});

		document.getElementById('launchdek-onboarding-next').addEventListener('click', function () {
			step1Notice.innerHTML = '';
			parsedChecklist = parsePastedChecklist(getPasteText());
			if (!parsedChecklist.steps.length) {
				notice(step1Notice, strings.onboardingNeedSteps || 'Add at least one checklist step before continuing.', 'error');
				return;
			}

			var payload = buildChecklistPayload(parsedChecklist);
			document.getElementById('launchdek-onboarding-next').disabled = true;

			post('/checklists', payload).then(function (checklist) {
				savedChecklistId = checklist.id;
				showStep2();
			}).catch(function (err) {
				notice(step1Notice, err.message, 'error');
			}).finally(function () {
				document.getElementById('launchdek-onboarding-next').disabled = false;
			});
		});

		document.getElementById('launchdek-onboarding-back').addEventListener('click', showStep1);

		document.getElementById('launchdek-onboarding-skip-step2').addEventListener('click', dismissAndCloseOnboarding);

		document.getElementById('launchdek-onboarding-test').addEventListener('click', function () {
			var creds = getConnectCredentials();
			step2Notice.innerHTML = '';

			if (!creds.url || !creds.admin_username || !creds.app_password) {
				notice(step2Notice, strings.error || 'Something went wrong.', 'error');
				return;
			}

			var testBtn = document.getElementById('launchdek-onboarding-test');
			testBtn.disabled = true;
			connectionVerified = false;
			setTestStatus('', strings.onboardingStatusTesting || 'Status: Testing…');

			post('/sites/test', creds).then(function (result) {
				if (result.success) {
					connectionVerified = true;
					setTestStatus('success', strings.onboardingStatusConnected || 'Status: Connected & Verified (OK)');
				} else {
					connectionVerified = false;
					setTestStatus('error', (strings.onboardingStatusFailed || 'Status: Connection failed') + (result.message ? ' — ' + result.message : ''));
				}
			}).catch(function (err) {
				connectionVerified = false;
				setTestStatus('error', (strings.onboardingStatusFailed || 'Status: Connection failed') + ' — ' + err.message);
			}).finally(function () {
				testBtn.disabled = false;
			});
		});

		['launchdek-onboarding-site-url', 'launchdek-onboarding-site-username', 'launchdek-onboarding-site-password'].forEach(function (id) {
			document.getElementById(id).addEventListener('input', function () {
				connectionVerified = false;
				setTestStatus('', strings.onboardingStatusPending || 'Status: Not tested yet');
			});
		});

		launchBtn.addEventListener('click', function () {
			step2Notice.innerHTML = '';

			if (!connectionVerified && !selectedSiteId) {
				notice(step2Notice, strings.onboardingNeedConnection || 'Test the connection successfully before launching.', 'error');
				return;
			}

			launchBtn.disabled = true;

			var launchPromise;
			if (selectedSiteId) {
				launchPromise = finishOnboarding(selectedSiteId);
			} else {
				var creds = getConnectCredentials();
				launchPromise = post('/sites', {
					name: deriveSiteName(creds.url),
					url: creds.url,
					admin_username: creds.admin_username,
					app_password: creds.app_password,
					tags: [],
					group_type: 'general'
				}).then(function (site) {
					return finishOnboarding(site.id);
				});
			}

			launchPromise.catch(function (err) {
				notice(step2Notice, err.message, 'error');
			}).finally(function () {
				launchBtn.disabled = false;
			});
		});
	}

	// ─── Dashboard ───────────────────────────────────────────
	function renderDashboardStats(page, stats) {
		if (!page || !stats) return;
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
	}

	function refreshDashboardStats(page) {
		return get('/dashboard/stats').then(function (stats) {
			renderDashboardStats(page, stats);
			return stats;
		});
	}

	function renderLogFeed(feedEl, logs) {
		if (!feedEl) return;
		if (!logs || !logs.length) {
			feedEl.innerHTML = '<p class="launchdek-muted">No activity yet.</p>';
			return;
		}
		feedEl.innerHTML = '';
		logs.forEach(function (log) {
			var itemClass = 'launchdek-log-item';
			if (log.action === 'client_step_completed' || log.action === 'client_step_note_added') {
				itemClass += ' is-client-activity';
			}
			var children = [
				el('time', { text: '[' + (log.created_at || '') + ']' })
			];
			if ((log.site_id || log.run_id) && log.site_name) {
				children.push(el('span', {
					className: 'launchdek-log-site',
					text: log.site_name,
					title: log.site_name
				}));
			}
			children.push(el('span', { className: 'launchdek-log-message', text: log.message || log.action }));
			feedEl.appendChild(el('div', { className: itemClass }, children));
		});
	}

	function refreshLogFeed() {
		var feedEl = document.getElementById('launchdek-log-feed');
		if (!feedEl) {
			return Promise.resolve([]);
		}
		return get('/logs/feed?limit=15').then(function (logs) {
			renderLogFeed(feedEl, logs);
			return logs;
		});
	}

	function initDashboard() {
		var page = document.querySelector('[data-launchdek-page="dashboard"]');
		if (!page) return;

		if (launchdekAdmin.dashboard && launchdekAdmin.dashboard.stats) {
			renderDashboardStats(page, launchdekAdmin.dashboard.stats);
		} else {
			refreshDashboardStats(page).catch(function () {});
		}

		initConnectionPanel(launchdekAdmin.dashboard && launchdekAdmin.dashboard.connections);

		var feedEl = document.getElementById('launchdek-log-feed');
		if (launchdekAdmin.dashboard && launchdekAdmin.dashboard.feed) {
			renderLogFeed(feedEl, launchdekAdmin.dashboard.feed);
		} else if (feedEl && feedEl.getAttribute('data-launchdek-preloaded') !== '1') {
			refreshLogFeed().catch(function () {});
		}

		Promise.all([get('/sites'), get('/checklists?is_template=0')]).then(function (results) {
			fillSelect(document.getElementById('launchdek-quick-site'), results[0], 'id', 'name', 'Select site…');
			fillSelect(document.getElementById('launchdek-quick-checklist'), results[1], 'id', 'title', 'Select checklist…');
		});

		document.getElementById('launchdek-quick-launch').addEventListener('click', function () {
			var siteId = document.getElementById('launchdek-quick-site').value;
			var wfId = document.getElementById('launchdek-quick-checklist').value;
			var result = document.getElementById('launchdek-quick-result');
			if (!siteId || !wfId) {
				notice(result, 'Select both a site and checklist.', 'error');
				return;
			}
			post('/runs', { site_id: parseInt(siteId, 10), checklist_id: parseInt(wfId, 10) })
				.then(function (data) {
					notice(result, 'Run #' + data.run_id + ' started. <a href="' + launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-automation">Open runner →</a>', 'success');
					refreshDashboardStats(page).catch(function () {});
					refreshLogFeed().catch(function () {});
				})
				.catch(function (err) { notice(result, err.message, 'error'); });
		});

	}

	// ─── Settings ────────────────────────────────────────────
	function initSettings() {
		var page = document.querySelector('[data-launchdek-page="settings"]');
		if (!page) return;

		var showBtn = document.getElementById('launchdek-show-onboarding');
		if (!showBtn) return;

		showBtn.addEventListener('click', function () {
			showBtn.disabled = true;
			post('/onboarding/reset', {}).catch(function () {}).finally(function () {
				showBtn.disabled = false;
				if (typeof window.launchdekOpenOnboarding === 'function') {
					window.launchdekOpenOnboarding();
				}
			});
		});
	}

	// ─── Sites ───────────────────────────────────────────────
	var currentSiteId = null;
	var pushChecklistSiteId = null;
	var pushChecklistCache = null;
	var siteTableColspan = 7;
	var siteConnectionStaleMs = 30 * 60 * 1000;

	function healthLabel(status) {
		if (status === 'healthy') return strings.healthOk || 'OK';
		if (status === 'unhealthy') return strings.healthFail || 'Fail';
		return strings.healthUnknown || 'Unknown';
	}

	function renderConnectionSummary(summaryEl, summary) {
		if (!summaryEl) return;
		summary = summary || {};

		function connectionStatCard(modifier, count, label) {
			return el('div', { className: 'launchdek-stat-card launchdek-connection-stat-card ' + modifier }, [
				el('span', { className: 'launchdek-stat-value', text: (count || 0).toLocaleString() }),
				el('span', { className: 'launchdek-stat-label', text: label })
			]);
		}

		summaryEl.innerHTML = '';
		summaryEl.appendChild(connectionStatCard('is-total', summary.total, strings.connectionAllStatuses || 'All Sites'));
		summaryEl.appendChild(connectionStatCard('is-healthy', summary.healthy, strings.connectionSummaryHealthy || 'Healthy'));
		summaryEl.appendChild(connectionStatCard('is-unhealthy', summary.unhealthy, strings.connectionSummaryUnhealthy || 'Issues'));
		summaryEl.appendChild(connectionStatCard('is-unknown', summary.unknown, strings.connectionSummaryUnknown || 'Unknown'));
	}

	function initConnectionPanel(preloadedSummary) {
		var summaryEl = document.getElementById('launchdek-connection-summary');
		if (!summaryEl) {
			return;
		}

		if (preloadedSummary) {
			renderConnectionSummary(summaryEl, preloadedSummary);
			return;
		}

		if (summaryEl.getAttribute('data-launchdek-preloaded') === '1') {
			return;
		}

		get('/connections/ticker').then(function (summary) {
			renderConnectionSummary(summaryEl, summary);
		}).catch(function () {
			summaryEl.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.error || 'Something went wrong.') + '</p>';
		});
	}

	function connectionStatusLabel(status, lastError) {
		if (status === 'healthy') return strings.connectionOk || 'Connection OK';
		if (status === 'checking') return strings.connectionChecking || 'Checking connection…';
		if (status === 'unhealthy') {
			var base = strings.connectionDisconnected || 'Connection not working';
			return lastError ? base + ': ' + lastError : base;
		}
		return strings.healthUnknown || 'Unknown';
	}

	function connectionStatusHtml(site, statusOverride) {
		var status = statusOverride || site.health_status || 'unknown';
		var label = connectionStatusLabel(status, site.last_error || '');
		var html = '<span class="launchdek-site-connection" title="' + escAttr(label) + '">';
		html += '<span class="launchdek-connection-dot ' + escAttr(status) + '" aria-hidden="true"></span>';
		if (status === 'unhealthy') {
			html += '<span class="dashicons dashicons-warning launchdek-connection-warning" aria-hidden="true"></span>';
		}
		html += '<span class="screen-reader-text">' + escHtml(label) + '</span></span>';
		return html;
	}

	function getSiteRow(siteId) {
		return document.querySelector('#launchdek-sites-table tr[data-site-id="' + siteId + '"]');
	}

	function updateSiteRowConnection(siteId, status, details) {
		var row = getSiteRow(siteId);
		if (!row) return;

		var connCell = row.querySelector('.launchdek-site-connection-cell');
		if (connCell) {
			connCell.innerHTML = connectionStatusHtml({
				health_status: status,
				last_error: details && details.message ? details.message : ''
			}, status);
		}

		var healthBadge = row.querySelector('.launchdek-site-health-badge');
		if (healthBadge && status !== 'checking') {
			healthBadge.className = 'launchdek-badge launchdek-site-health-badge ' + status;
			healthBadge.textContent = healthLabel(status);
		}

		if (details && details.wp_version) {
			var wpCell = row.querySelector('.launchdek-site-wp-version');
			if (wpCell) wpCell.textContent = details.wp_version;
		}
		if (details && details.php_version) {
			var phpCell = row.querySelector('.launchdek-site-php-version');
			if (phpCell) phpCell.textContent = details.php_version;
		}

		var pushBtn = row.querySelector('.launchdek-push-checklist');
		if (pushBtn) {
			pushBtn.disabled = status === 'unhealthy' || status === 'checking';
		}
	}

	function siteLastPingMs(site) {
		if (!site || !site.last_ping_at) {
			return null;
		}
		var parsed = Date.parse(String(site.last_ping_at).replace(' ', 'T') + 'Z');
		return isNaN(parsed) ? null : parsed;
	}

	function isSiteConnectionStale(site) {
		var lastPingMs = siteLastPingMs(site);
		if (lastPingMs === null) {
			return true;
		}
		return (Date.now() - lastPingMs) > siteConnectionStaleMs;
	}

	function testSiteConnection(siteId, options) {
		var silent = options && options.silent;
		var background = options && options.background;
		if (!background) {
			updateSiteRowConnection(siteId, 'checking');
		}

		return post('/sites/' + siteId + '/test', {}).then(function (r) {
			var status = r.success ? 'healthy' : 'unhealthy';
			updateSiteRowConnection(siteId, status, r);
			if (!silent) {
				alert(r.success ? (strings.connectionOk + ': ' + r.message) : (strings.connectionFail + ': ' + r.message));
			}
			if (r.client_agent) {
				var row = getSiteRow(siteId);
				if (row && !row.querySelector('.launchdek-client-agent-badge')) {
					var nameCell = row.querySelector('td');
					if (nameCell) {
						nameCell.insertAdjacentHTML('beforeend', ' <span class="launchdek-badge healthy launchdek-client-agent-badge">' + escHtml(strings.clientPanelBadge || 'Client panel') + '</span>');
					}
				}
			}
			return r;
		}).catch(function (e) {
			updateSiteRowConnection(siteId, 'unhealthy', { message: e.message });
			if (!silent) alert(e.message);
			throw e;
		});
	}

	function refreshStaleSiteConnectionsInBackground(sites) {
		if (!sites || !sites.length) return;
		sites.forEach(function (site) {
			if (!isSiteConnectionStale(site)) {
				return;
			}
			testSiteConnection(site.id, { silent: true, background: true });
		});
	}

	function sitesListHasFilters() {
		var tag = document.getElementById('launchdek-filter-tag');
		var group = document.getElementById('launchdek-filter-group');
		return Boolean((tag && tag.value) || (group && group.value));
	}

	function renderSitesTable(sites, options) {
		options = options || {};
		var tag = document.getElementById('launchdek-filter-tag');
		var group = document.getElementById('launchdek-filter-group');
		var tbody = document.querySelector('#launchdek-sites-table tbody');
		if (!tbody) {
			return;
		}

		tbody.innerHTML = '';
		if (!sites || !sites.length) {
			var emptyMsg = strings.noSites || 'No sites registered yet.';
			if (options.filtered || sitesListHasFilters()) {
				emptyMsg = strings.noSitesFiltered || 'No sites match the current filters. Try clearing tag or group filters.';
			}
			tbody.innerHTML = '<tr><td colspan="' + siteTableColspan + '" class="launchdek-muted">' + escHtml(emptyMsg) + '</td></tr>';
			return;
		}

		sites.forEach(function (site) {
			var tr = el('tr', { 'data-site-id': String(site.id) });
			var connectionBlocked = site.health_status === 'unhealthy';
			tr.innerHTML =
				'<td>' + escHtml(site.name) + (site.client_agent ? ' <span class="launchdek-badge healthy launchdek-client-agent-badge">' + escHtml(strings.clientPanelBadge || 'Client panel') + '</span>' : '') + '</td>' +
				'<td><a href="' + escAttr(site.url) + '" target="_blank" rel="noopener">' + escHtml(site.url) + '</a></td>' +
				'<td class="launchdek-site-connection-cell">' + connectionStatusHtml(site) + '</td>' +
				'<td class="launchdek-site-wp-version">' + escHtml(site.wp_version || '—') + '</td>' +
				'<td class="launchdek-site-php-version">' + escHtml(site.php_version || '—') + '</td>' +
				'<td><span class="launchdek-badge launchdek-site-health-badge ' + escAttr(site.health_status) + '">' + escHtml(healthLabel(site.health_status)) + '</span></td>' +
				'<td class="launchdek-actions">' +
				'<div class="launchdek-actions-wrap">' +
				'<button type="button" class="button button-small launchdek-push-checklist" data-id="' + escAttr(site.id) + '" data-name="' + escAttr(site.name) + '"' + (connectionBlocked ? ' disabled' : '') + '>' + escHtml(strings.pushChecklist || 'Push Checklist') + '</button>' +
				'<button type="button" class="button button-small launchdek-edit-site" data-id="' + escAttr(site.id) + '">Edit</button>' +
				'<button type="button" class="button button-small launchdek-test-site" data-id="' + escAttr(site.id) + '">Test</button>' +
				'</div></td>';
			tbody.appendChild(tr);
		});
		bindSiteActions();
		if (!options.skipStaleRefresh) {
			refreshStaleSiteConnectionsInBackground(sites);
		}
	}

	function loadSites(options) {
		options = options || {};
		var tag = document.getElementById('launchdek-filter-tag');
		var group = document.getElementById('launchdek-filter-group');
		var tbody = document.querySelector('#launchdek-sites-table tbody');
		if (!tbody) {
			return;
		}

		var hasFilters = sitesListHasFilters();
		if (!options.forceFetch && !hasFilters) {
			if (launchdekAdmin.sites && launchdekAdmin.sites.list) {
				renderSitesTable(launchdekAdmin.sites.list);
				return;
			}
			if (tbody.getAttribute('data-launchdek-preloaded') === '1' && tbody.querySelector('tr[data-site-id]')) {
				bindSiteActions();
				refreshStaleSiteConnectionsInBackground(launchdekAdmin.sites && launchdekAdmin.sites.list ? launchdekAdmin.sites.list : null);
				return;
			}
		}

		var params = [];
		if (tag && tag.value) params.push('tag=' + encodeURIComponent(tag.value));
		if (group && group.value) params.push('group_type=' + encodeURIComponent(group.value));
		var qs = params.length ? '?' + params.join('&') : '';

		tbody.removeAttribute('data-launchdek-preloaded');
		tbody.innerHTML = '<tr><td colspan="' + siteTableColspan + '" class="launchdek-muted">' + escHtml(strings.loading || 'Loading…') + '</td></tr>';

		get('/sites' + qs).then(function (sites) {
			renderSitesTable(sites, { filtered: hasFilters });
		}).catch(function (err) {
			tbody.innerHTML = '<tr><td colspan="' + siteTableColspan + '"><div class="notice-inline error">' + escHtml(err.message || strings.error || 'Could not load sites.') + '</div></td></tr>';
		});
	}

	function bindSiteActions() {
		document.querySelectorAll('.launchdek-edit-site').forEach(function (btn) {
			btn.onclick = function () { openSiteModal(parseInt(btn.dataset.id, 10)); };
		});
		document.querySelectorAll('.launchdek-test-site').forEach(function (btn) {
			btn.onclick = function () {
				testSiteConnection(parseInt(btn.dataset.id, 10), { silent: false });
			};
		});
		document.querySelectorAll('.launchdek-push-checklist').forEach(function (btn) {
			btn.onclick = function () {
				openPushChecklistModal(parseInt(btn.dataset.id, 10), btn.dataset.name || '');
			};
		});
	}

	function ensurePushChecklistsLoaded() {
		if (pushChecklistCache) {
			return Promise.resolve(pushChecklistCache);
		}
		return get('/checklists?is_template=0').then(function (checklists) {
			pushChecklistCache = checklists || [];
			return pushChecklistCache;
		});
	}

	function openPushChecklistModal(siteId, siteName) {
		pushChecklistSiteId = siteId;
		var modal = document.getElementById('launchdek-push-checklist-modal');
		var select = document.getElementById('launchdek-push-checklist-select');
		var result = document.getElementById('launchdek-push-checklist-result');
		document.getElementById('launchdek-push-site-name').textContent = siteName || ('Site #' + siteId);
		result.innerHTML = '';
		select.innerHTML = '';

		ensurePushChecklistsLoaded().then(function (checklists) {
			fillSelect(select, checklists, 'id', 'title', strings.pushChecklistSelect || 'Select checklist…');
			modal.hidden = false;
		}).catch(function (err) {
			notice(result, err.message, 'error');
			modal.hidden = false;
		});
	}

	function openSiteModal(id) {
		currentSiteId = id || null;
		var modal = document.getElementById('launchdek-site-modal');
		var deleteBtn = document.getElementById('launchdek-site-delete');
		document.getElementById('launchdek-site-modal-title').textContent = id ? 'Edit Remote Site' : 'Add Remote Site';
		document.getElementById('launchdek-site-id').value = id || '';
		document.getElementById('launchdek-site-test-result').innerHTML = '';
		togglePanelSetup(false);
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
				if (!site.client_agent) {
					togglePanelSetup(true);
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

	function togglePanelSetup(show) {
		var panel = document.getElementById('launchdek-panel-setup');
		if (!panel) {
			return;
		}
		panel.hidden = !show;
		if (!show) {
			var result = document.getElementById('launchdek-panel-setup-result');
			if (result) {
				result.innerHTML = '';
			}
		}
	}

	function panelStatusFromResponse(response) {
		if (response && response.client_panel) {
			return response.client_panel;
		}
		if (response && response.client_agent) {
			return { success: true };
		}
		return null;
	}

	function updatePanelSetupStatus(status) {
		var setupResult = document.getElementById('launchdek-panel-setup-result');
		if (!setupResult) {
			return;
		}

		if (status && status.success) {
			togglePanelSetup(false);
			var testResult = document.getElementById('launchdek-site-test-result');
			if (testResult) {
				notice(testResult, strings.panelSetupReady || 'Client panel is installed and ready.', 'success');
			}
			return;
		}

		togglePanelSetup(true);
		var message = (status && status.message) || strings.panelSetupNeeded || 'Client panel is not installed yet.';
		notice(setupResult, message, 'error');
	}

	function downloadPanelBootstrap() {
		var setupResult = document.getElementById('launchdek-panel-setup-result');
		return get('/panel/bootstrap').then(function (data) {
			var blob = new Blob([data.contents || ''], { type: 'application/x-php' });
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = data.filename || 'launchdek-client.php';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
			if (setupResult) {
				notice(setupResult, strings.panelBootstrapDownloaded || 'Bootstrap file downloaded.', 'success');
			}
		}).catch(function (err) {
			if (setupResult) {
				notice(setupResult, err.message, 'error');
			}
		});
	}

	function retryPanelInstall(siteId) {
		var setupResult = document.getElementById('launchdek-panel-setup-result');
		if (!siteId) {
			if (setupResult) {
				notice(setupResult, strings.panelRetryNeedsSave || 'Save this site first, then retry panel install.', 'error');
			}
			togglePanelSetup(true);
			return Promise.resolve();
		}

		return post('/sites/' + siteId + '/panel/install', {}).then(function (panel) {
			updatePanelSetupStatus(panel);
			if (panel && panel.success) {
				loadSites();
			}
		}).catch(function (err) {
			if (setupResult) {
				notice(setupResult, err.message, 'error');
			}
		});
	}

	function initSites() {
		if (!document.querySelector('[data-launchdek-page="sites"]')) return;

		var tbody = document.querySelector('#launchdek-sites-table tbody');
		if (launchdekAdmin.sites && launchdekAdmin.sites.list) {
			if (tbody && tbody.getAttribute('data-launchdek-preloaded') !== '1') {
				renderSitesTable(launchdekAdmin.sites.list);
			} else if (tbody && tbody.getAttribute('data-launchdek-preloaded') === '1') {
				bindSiteActions();
				refreshStaleSiteConnectionsInBackground(launchdekAdmin.sites.list);
			}
		} else {
			loadSites();
		}
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
		document.querySelectorAll('#launchdek-push-checklist-modal .launchdek-modal-close, #launchdek-push-checklist-modal .launchdek-modal-backdrop').forEach(function (n) {
			n.addEventListener('click', function () { closeModal(document.getElementById('launchdek-push-checklist-modal')); });
		});

		document.getElementById('launchdek-push-checklist-submit').addEventListener('click', function () {
			var select = document.getElementById('launchdek-push-checklist-select');
			var result = document.getElementById('launchdek-push-checklist-result');
			var checklistId = parseInt(select.value || '0', 10);
			if (!pushChecklistSiteId) return;
			if (!checklistId) {
				notice(result, strings.pushChecklistNeed || 'Select a checklist to push.', 'error');
				return;
			}

			var row = getSiteRow(pushChecklistSiteId);
			var connDot = row ? row.querySelector('.launchdek-connection-dot') : null;
			if (connDot && connDot.classList.contains('unhealthy')) {
				notice(result, strings.pushChecklistBlocked || 'Fix the connection before pushing a checklist.', 'error');
				return;
			}

			var submitBtn = document.getElementById('launchdek-push-checklist-submit');
			var pushToClientEl = document.getElementById('launchdek-push-to-client');
			var pushToClient = !pushToClientEl || pushToClientEl.checked;
			submitBtn.disabled = true;
			post('/runs', { site_id: pushChecklistSiteId, checklist_id: checklistId, push_to_client: pushToClient })
				.then(function (data) {
					var automationUrl = launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-automation';
					var message = strings.pushChecklistStarted || 'Checklist run started.';
					if (data.client_push) {
						if (data.client_push.success) {
							message += ' ' + (strings.clientPushOk || data.client_push.message);
						} else if (pushToClient) {
							message += ' ' + (strings.clientPushSkipped || data.client_push.message);
						} else {
							message += ' ' + (strings.clientPushFailed || data.client_push.message);
						}
					}
					notice(result, message + ' <a href="' + automationUrl + '">' + (strings.openRunner || 'Open runner →') + '</a>', 'success');
				})
				.catch(function (err) {
					notice(result, err.message, 'error');
				})
				.finally(function () {
					submitBtn.disabled = false;
				});
		});

		document.getElementById('launchdek-site-delete').addEventListener('click', function () {
			if (!currentSiteId || !confirm(strings.confirmDeleteSite || strings.confirmDelete)) return;
			del('/sites/' + currentSiteId).then(function () {
				closeModal(document.getElementById('launchdek-site-modal'));
				loadSites({ forceFetch: true });
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
				var message = r.success ? r.message : ('Failed: ' + r.message);
				if (r.success && r.client_panel && !r.client_panel.success) {
					message += ' ' + (r.client_panel.message || strings.panelSetupNeeded || '');
				}
				notice(result, message, r.success ? 'success' : 'error');
				updatePanelSetupStatus(panelStatusFromResponse(r));
			}).catch(function (e) { notice(result, e.message, 'error'); });
		});

		document.getElementById('launchdek-download-panel-bootstrap').addEventListener('click', function () {
			downloadPanelBootstrap();
		});

		document.getElementById('launchdek-retry-panel-install').addEventListener('click', function () {
			retryPanelInstall(currentSiteId);
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

			promise.then(function (site) {
				if (site && site.id) {
					currentSiteId = site.id;
					document.getElementById('launchdek-site-id').value = site.id;
					document.getElementById('launchdek-site-delete').hidden = false;
				}
				updatePanelSetupStatus(site ? site.client_panel : null);
				if (site && site.client_panel && site.client_panel.success) {
					closeModal(document.getElementById('launchdek-site-modal'));
				}
				loadSites({ forceFetch: true });
			}).catch(function (err) { alert(err.message); });
		});
	}

	// ─── Checklists ──────────────────────────────────────────
	var currentChecklistId = null;
	var currentSteps = [];
	var selectedStepIndex = null;
	var dragSrcIndex = null;

	function updateChecklistActions() {
		var deleteBtn = document.getElementById('launchdek-delete-checklist');
		var exportBtn = document.getElementById('launchdek-export-checklist');
		if (deleteBtn) deleteBtn.disabled = !currentChecklistId;
		if (exportBtn) exportBtn.disabled = !currentChecklistId;
	}

	function loadChecklistList() {
		get('/checklists?is_template=0').then(function (checklists) {
			var list = document.getElementById('launchdek-checklist-list');
			list.innerHTML = '';
			if (!checklists.length) {
				list.appendChild(el('li', {
					className: 'launchdek-muted',
					text: strings.noCustomChecklists || 'No custom checklists yet. Click New Checklist to create one.'
				}));
				return;
			}
			checklists.forEach(function (wf) {
				var li = el('li', { text: wf.title + ' (' + formatStepsCount((wf.steps || []).length) + ')' });
				li.dataset.id = wf.id;
				if (currentChecklistId === wf.id) li.className = 'active';
				li.onclick = function () { loadChecklistEditor(wf.id); };
				list.appendChild(li);
			});
		});
	}

	function loadChecklistEditor(id) {
		currentChecklistId = id;
		get('/checklists/' + id).then(function (wf) {
			document.getElementById('launchdek-checklist-editor').hidden = false;
			document.getElementById('launchdek-cl-title').value = wf.title;
			document.getElementById('launchdek-cl-description').value = wf.description || '';
			currentSteps = wf.steps || [];
			selectedStepIndex = null;
			notice(document.getElementById('launchdek-checklist-notice'), '', '');
			renderSteps();
			renderStepConfig();
			updateChecklistActions();
			loadChecklistList();
		});
	}

	function newChecklist() {
		currentChecklistId = null;
		currentSteps = [];
		selectedStepIndex = null;
		document.getElementById('launchdek-checklist-editor').hidden = false;
		document.getElementById('launchdek-cl-title').value = '';
		document.getElementById('launchdek-cl-description').value = '';
		notice(document.getElementById('launchdek-checklist-notice'), '', '');
		renderSteps();
		renderStepConfig();
		updateChecklistActions();
		document.getElementById('launchdek-cl-title').focus();
	}

	function renderSteps() {
		var canvas = document.getElementById('launchdek-canvas-steps');
		if (!canvas) return;
		canvas.innerHTML = '';

		if (!currentSteps.length) {
			canvas.appendChild(el('p', {
				className: 'launchdek-muted launchdek-canvas-empty',
				text: 'Add a step to begin building your checklist.'
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
				'<button type="button" class="launchdek-canvas-node-chevron" aria-label="' + escAttr(strings.stepSettings || 'Step settings') + '" aria-expanded="' + (selectedStepIndex === i ? 'true' : 'false') + '">' +
					'<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>' +
				'</button>' +
				'<span class="launchdek-canvas-node-num">' + (i + 1) + '</span>' +
				'<span class="launchdek-canvas-node-title">' + escHtml(step.title || 'Step ' + (i + 1)) + '</span>' +
				'<span class="launchdek-canvas-node-type">' + escHtml(step.type || 'manual') + '</span>' +
				(roles.length ? '<span class="launchdek-canvas-node-roles">' + escHtml(roles.join(', ')) + '</span>' : '');

			var chevronBtn = node.querySelector('.launchdek-canvas-node-chevron');
			if (chevronBtn) {
				chevronBtn.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					selectedStepIndex = i;
					renderSteps();
					renderStepConfig();
					var configSidebar = document.querySelector('.launchdek-step-config-sidebar');
					if (configSidebar) {
						configSidebar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
					}
				});
			}

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
		var showNoteField = step.show_note_field !== false;
		box.innerHTML =
			'<label>Step Title<input type="text" id="ld-step-title" value="' + escAttr(step.title || '') + '" /></label>' +
			'<label>Instructions<textarea id="ld-step-instructions" rows="3">' + escHtml(step.instructions || '') + '</textarea></label>' +
			renderRoleMapping(step) +
			'<label>Deep Link (admin path)<input type="text" id="ld-step-deeplink" value="' + escAttr(step.deep_link || '') + '" placeholder="options-permalink.php" /></label>' +
			'<label>Step Type<select id="ld-step-type"><option value="manual"' + (step.type === 'manual' ? ' selected' : '') + '>Manual</option><option value="api"' + (step.type === 'api' ? ' selected' : '') + '>API</option></select></label>' +
			'<label class="launchdek-step-note-field-option" id="ld-step-show-note-wrap"><input type="checkbox" id="ld-step-show-note"' + (showNoteField ? ' checked' : '') + ' /> ' + escHtml(strings.showNoteField || 'Show note field on client panel') + '</label>' +
			'<div id="ld-api-config"' + (step.type === 'api' ? '' : ' style="display:none"') + '>' +
			'<h4 class="launchdek-config-subheading">API Payload Mapper</h4>' +
			'<label>HTTP Method<select id="ld-api-method"><option' + sel(step.api && step.api.method, 'GET') + '>GET</option><option' + sel(step.api && step.api.method, 'POST') + '>POST</option><option' + sel(step.api && step.api.method, 'PUT') + '>PUT</option><option' + sel(step.api && step.api.method, 'PATCH') + '>PATCH</option><option' + sel(step.api && step.api.method, 'DELETE') + '>DELETE</option></select></label>' +
			'<label>REST Route<input type="text" id="ld-api-route" value="' + escAttr((step.api && step.api.route) || '') + '" placeholder="/wp/v2/settings" /></label>' +
			'<label>JSON Payload<textarea id="ld-api-payload" rows="4">' + escHtml(JSON.stringify((step.api && step.api.payload) || {}, null, 2)) + '</textarea></label>' +
			'<button type="button" class="button" id="ld-validate-api">Validate API Step</button>' +
			'</div>' +
			'<button type="button" class="button" id="ld-remove-step" style="margin-top:8px">Remove Step</button>';

		function syncStepTypeFields() {
			var isManual = document.getElementById('ld-step-type').value === 'manual';
			document.getElementById('ld-api-config').style.display = isManual ? 'none' : '';
			var noteWrap = document.getElementById('ld-step-show-note-wrap');
			if (noteWrap) {
				noteWrap.style.display = isManual ? '' : 'none';
			}
		}

		document.getElementById('ld-step-type').onchange = function () {
			syncStepTypeFields();
			saveStepFromForm();
		};
		syncStepTypeFields();
		['ld-step-title', 'ld-step-instructions', 'ld-step-deeplink', 'ld-api-method', 'ld-api-route', 'ld-api-payload'].forEach(function (id) {
			var node = document.getElementById(id);
			if (node) node.onchange = saveStepFromForm;
		});
		box.querySelectorAll('.ld-target-role').forEach(function (input) {
			input.onchange = saveStepFromForm;
		});
		var showNoteInput = document.getElementById('ld-step-show-note');
		if (showNoteInput) {
			showNoteInput.onchange = saveStepFromForm;
		}
		document.getElementById('ld-remove-step').onclick = function () {
			currentSteps.splice(selectedStepIndex, 1);
			selectedStepIndex = null;
			renderSteps();
			renderStepConfig();
		};
		document.getElementById('ld-validate-api').onclick = function () {
			saveStepFromForm();
			post('/checklists/0/validate-step', currentSteps[selectedStepIndex]).then(function (r) {
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
			step.show_note_field = false;
		} else {
			var showNoteInput = document.getElementById('ld-step-show-note');
			step.show_note_field = showNoteInput ? showNoteInput.checked : true;
		}
		renderSteps();
	}

	function initChecklistTabs() {
		var tabs = document.querySelectorAll('.launchdek-page-tab');
		var panels = document.querySelectorAll('[data-launchdek-tab-panel]');
		if (!tabs.length) return null;

		function showTab(tabId, updateUrl) {
			tabs.forEach(function (tab) {
				var active = tab.getAttribute('data-tab') === tabId;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			panels.forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-launchdek-tab-panel') !== tabId;
			});
			var newBtn = document.getElementById('launchdek-new-checklist');
			if (newBtn) {
				newBtn.hidden = tabId !== 'builder';
			}
			var captureBtn = document.getElementById('launchdek-auto-capture');
			if (captureBtn) {
				captureBtn.hidden = tabId !== 'builder';
			}
			if (updateUrl && window.history && window.history.replaceState) {
				var url = new URL(window.location.href);
				if (tabId === 'builder') {
					url.searchParams.delete('tab');
				} else {
					url.searchParams.set('tab', tabId);
				}
				window.history.replaceState(null, '', url.toString());
			}
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				showTab(tab.getAttribute('data-tab'), true);
			});
		});

		return showTab;
	}

	function initChecklists() {
		if (!document.querySelector('[data-launchdek-page="checklists"]')) return;

		var showTab = initChecklistTabs();
		var params = new URLSearchParams(window.location.search);
		var openId = parseInt(params.get('checklist_id') || '0', 10);
		var initialTab = openId ? 'builder' : (params.get('tab') === 'templates' ? 'templates' : 'builder');
		if (showTab) {
			showTab(initialTab, false);
		}

		loadChecklistList();
		updateChecklistActions();

		if (openId) {
			loadChecklistEditor(openId);
		}

		document.getElementById('launchdek-new-checklist').onclick = function () {
			openTemplatePicker({
				onImport: function (checklist) {
					loadChecklistEditor(checklist.id);
				},
				onBlank: newChecklist
			});
		};
		document.getElementById('launchdek-add-step').onclick = function () {
			currentSteps.push({
				id: 'step_' + (currentSteps.length + 1),
				title: 'New Step',
				instructions: '',
				target_roles: [],
				deep_link: '',
				type: 'manual',
				show_note_field: true,
				api: { method: 'GET', route: '', payload: {} }
			});
			selectedStepIndex = currentSteps.length - 1;
			renderSteps();
			renderStepConfig();
		};

		document.getElementById('launchdek-save-checklist').onclick = function () {
			var noticeEl = document.getElementById('launchdek-checklist-notice');
			var saveBtn = document.getElementById('launchdek-save-checklist');

			if (selectedStepIndex !== null) saveStepFromForm();

			var title = document.getElementById('launchdek-cl-title').value.trim();
			if (!title) {
				notice(noticeEl, strings.checklistTitleRequired || 'Checklist title is required.', 'error');
				document.getElementById('launchdek-cl-title').focus();
				return;
			}

			var payload = {
				title: title,
				description: document.getElementById('launchdek-cl-description').value,
				steps: currentSteps,
				is_template: false
			};

			saveBtn.disabled = true;
			var promise = currentChecklistId
				? put('/checklists/' + currentChecklistId, payload)
				: post('/checklists', payload);

			promise.then(function (wf) {
				currentChecklistId = wf.id;
				currentSteps = wf.steps || currentSteps;
				notice(noticeEl, strings.saved || 'Saved successfully.', 'success');
				loadChecklistList();
				updateChecklistActions();
			}).catch(function (e) {
				notice(noticeEl, e.message, 'error');
			}).finally(function () {
				saveBtn.disabled = false;
			});
		};

		document.getElementById('launchdek-delete-checklist').onclick = function () {
			var noticeEl = document.getElementById('launchdek-checklist-notice');
			if (!currentChecklistId || !confirm(strings.confirmDeleteChecklist || strings.confirmDelete)) return;
			del('/checklists/' + currentChecklistId).then(function () {
				currentChecklistId = null;
				document.getElementById('launchdek-checklist-editor').hidden = true;
				notice(noticeEl, '', '');
				loadChecklistList();
				updateChecklistActions();
			}).catch(function (e) {
				notice(noticeEl, e.message, 'error');
			});
		};

		document.getElementById('launchdek-export-checklist').onclick = function () {
			if (!currentChecklistId) return;
			get('/checklists/' + currentChecklistId + '/export').then(function (data) {
				var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
				var a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = (data.title || 'checklist') + '.json';
				a.click();
			});
		};

		document.getElementById('launchdek-import-checklist').onclick = function () {
			var fileInput = document.getElementById('launchdek-import-file');
			var urlInput = document.getElementById('launchdek-import-url');
			if (fileInput.files.length) {
				var reader = new FileReader();
				reader.onload = function () {
					try {
						var data = JSON.parse(reader.result);
						post('/checklists/import', data).then(function () { loadChecklistList(); alert('Imported.'); });
					} catch (e) { alert('Invalid JSON file.'); }
				};
				reader.readAsText(fileInput.files[0]);
			} else if (urlInput.value) {
				post('/checklists/import', { url: urlInput.value }).then(function () { loadChecklistList(); alert('Imported.'); });
			}
		};

		initAutoCapture();
	}

	var capturePollTimer = null;
	var captureSiteId = null;
	var capturedSteps = [];

	function stopCapturePolling() {
		if (capturePollTimer) {
			clearInterval(capturePollTimer);
			capturePollTimer = null;
		}
	}

	function renderCaptureStatus(data) {
		var statusEl = document.getElementById('launchdek-auto-capture-status');
		var stepsEl = document.getElementById('launchdek-auto-capture-steps');
		var startBtn = document.getElementById('launchdek-auto-capture-start');
		var stopBtn = document.getElementById('launchdek-auto-capture-stop');
		var importBtn = document.getElementById('launchdek-auto-capture-import');
		var clearBtn = document.getElementById('launchdek-auto-capture-clear');

		if (!statusEl || !stepsEl) {
			return;
		}

		capturedSteps = (data && data.steps) ? data.steps : [];
		var count = capturedSteps.length;
		var active = !!(data && data.active);

		statusEl.className = 'launchdek-auto-capture-status' + (active ? ' is-recording' : ' launchdek-muted');
		statusEl.textContent = active
			? (strings.captureRecording || 'Recording — configure the client site in wp-admin. Changes are captured automatically.')
			: (count
				? (strings.captureReady || 'Recording stopped. Review captured steps below.')
				: (strings.captureIdle || 'Choose a client site and start recording to capture configuration changes.'));

		if (count) {
			stepsEl.hidden = false;
			stepsEl.innerHTML = capturedSteps.map(function (step, index) {
				return '<li><strong>' + escHtml(step.title || ('Step ' + (index + 1))) + '</strong> <span class="launchdek-muted">(' + escHtml(step.type || 'manual') + ')</span></li>';
			}).join('');
		} else {
			stepsEl.hidden = true;
			stepsEl.innerHTML = '';
		}

		if (startBtn) startBtn.hidden = active;
		if (stopBtn) stopBtn.hidden = !active;
		if (importBtn) {
			importBtn.hidden = !count;
			importBtn.textContent = count === 1
				? (strings.captureImportOne || 'Import 1 Captured Step')
				: (strings.captureImportMany || 'Import %d Captured Steps').replace('%d', String(count));
		}
		if (clearBtn) clearBtn.hidden = !count && !active;
	}

	function refreshCaptureStatus() {
		if (!captureSiteId) {
			renderCaptureStatus({ active: false, steps: [] });
			return Promise.resolve();
		}

		return get('/sites/' + captureSiteId + '/capture').then(function (data) {
			renderCaptureStatus(data);
		}).catch(function (err) {
			notice(document.getElementById('launchdek-auto-capture-notice'), err.message, 'error');
		});
	}

	function openAutoCaptureModal() {
		var modal = document.getElementById('launchdek-auto-capture-modal');
		if (!modal) {
			return;
		}

		stopCapturePolling();
		captureSiteId = null;
		modal.hidden = false;
		notice(document.getElementById('launchdek-auto-capture-notice'), '', '');
		renderCaptureStatus({ active: false, steps: [] });

		get('/sites').then(function (sites) {
			var select = document.getElementById('launchdek-auto-capture-site');
			if (!select) {
				return;
			}

			var panelSites = (sites || []).filter(function (site) {
				return !!site.client_agent;
			});

			fillSelect(select, panelSites, 'id', 'name', strings.captureSelectSite || 'Select site…');
			if (!panelSites.length) {
				notice(
					document.getElementById('launchdek-auto-capture-notice'),
					strings.captureNeedPanel || 'Auto-capture requires the client checklist panel on at least one site.',
					'error'
				);
			}
		});
	}

	function closeAutoCaptureModal() {
		var modal = document.getElementById('launchdek-auto-capture-modal');
		if (modal) {
			modal.hidden = true;
		}
		stopCapturePolling();
	}

	function initAutoCapture() {
		var openBtn = document.getElementById('launchdek-auto-capture');
		var modal = document.getElementById('launchdek-auto-capture-modal');
		if (!openBtn || !modal) {
			return;
		}

		openBtn.addEventListener('click', openAutoCaptureModal);

		modal.querySelectorAll('[data-launchdek-close-capture]').forEach(function (node) {
			node.addEventListener('click', closeAutoCaptureModal);
		});

		var siteSelect = document.getElementById('launchdek-auto-capture-site');
		if (siteSelect) {
			siteSelect.addEventListener('change', function () {
				stopCapturePolling();
				captureSiteId = parseInt(siteSelect.value || '0', 10) || null;
				notice(document.getElementById('launchdek-auto-capture-notice'), '', '');
				refreshCaptureStatus();
			});
		}

		document.getElementById('launchdek-auto-capture-start').addEventListener('click', function () {
			var noticeEl = document.getElementById('launchdek-auto-capture-notice');
			if (!captureSiteId) {
				notice(noticeEl, strings.captureSelectSite || 'Select a client site first.', 'error');
				return;
			}

			post('/sites/' + captureSiteId + '/capture', { action: 'start' }).then(function (data) {
				renderCaptureStatus(data);
				stopCapturePolling();
				capturePollTimer = setInterval(refreshCaptureStatus, 5000);
			}).catch(function (err) {
				notice(noticeEl, err.message, 'error');
			});
		});

		document.getElementById('launchdek-auto-capture-stop').addEventListener('click', function () {
			if (!captureSiteId) {
				return;
			}

			post('/sites/' + captureSiteId + '/capture', { action: 'stop' }).then(function (data) {
				stopCapturePolling();
				renderCaptureStatus(data);
			}).catch(function (err) {
				notice(document.getElementById('launchdek-auto-capture-notice'), err.message, 'error');
			});
		});

		document.getElementById('launchdek-auto-capture-clear').addEventListener('click', function () {
			if (!captureSiteId) {
				return;
			}

			del('/sites/' + captureSiteId + '/capture').then(function (data) {
				renderCaptureStatus(data);
			}).catch(function (err) {
				notice(document.getElementById('launchdek-auto-capture-notice'), err.message, 'error');
			});
		});

		document.getElementById('launchdek-auto-capture-import').addEventListener('click', function () {
			if (!capturedSteps.length) {
				return;
			}

			if (selectedStepIndex !== null) {
				saveStepFromForm();
			}

			var importedSteps = capturedSteps.map(function (step, index) {
				var copy = Object.assign({}, step);
				copy.id = 'step_' + (index + 1);
				if (copy.api) {
					copy.api = Object.assign({}, copy.api);
				}
				return copy;
			});

			newChecklist();
			currentSteps = importedSteps;
			document.getElementById('launchdek-cl-title').value = strings.captureDefaultTitle || 'Captured Checklist';
			selectedStepIndex = null;
			renderSteps();
			renderStepConfig();
			closeAutoCaptureModal();
			notice(
				document.getElementById('launchdek-checklist-notice'),
				strings.captureImported || 'Captured steps imported into the checklist builder.',
				'success'
			);
		});
	}

	// ─── Automation ──────────────────────────────────────────
	var activeRunId = null;
	var batchQueue = [];
	var cachedSites = [];
	var cachedChecklists = [];

	function loadRunSelects() {
		return Promise.all([get('/sites'), get('/checklists?is_template=0')]).then(function (r) {
			cachedSites = r[0] || [];
			cachedChecklists = r[1] || [];

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
			fillSelect(document.getElementById('launchdek-run-checklist'), cachedChecklists, 'id', 'title', 'Select checklist…');
		});
	}

	function getSelectedSiteIds() {
		var select = document.getElementById('launchdek-run-site');
		if (!select) return [];
		return Array.prototype.slice.call(select.selectedOptions).map(function (opt) {
			return parseInt(opt.value, 10);
		}).filter(function (id) { return id > 0; });
	}

	function getSelectedChecklist() {
		var wfId = parseInt(document.getElementById('launchdek-run-checklist').value, 10);
		if (!wfId) return null;
		for (var i = 0; i < cachedChecklists.length; i++) {
			if (cachedChecklists[i].id === wfId) return cachedChecklists[i];
		}
		return { id: wfId, title: 'Checklist #' + wfId };
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
				'<td>' + escHtml(item.checklist_title) + '</td>' +
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
		var checklist = getSelectedChecklist();
		var siteIds = getSelectedSiteIds();
		if (!checklist || !siteIds.length) {
			alert('Select a checklist and at least one target site.');
			return;
		}

		siteIds.forEach(function (siteId) {
			var site = cachedSites.find(function (s) { return s.id === siteId; });
			var exists = batchQueue.some(function (item) {
				return item.site_id === siteId && item.checklist_id === checklist.id && item.status === 'queued';
			});
			if (exists) return;
			batchQueue.push({
				site_id: siteId,
				site_name: site ? (site.name || site.url) : ('Site #' + siteId),
				checklist_id: checklist.id,
				checklist_title: checklist.title,
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

		var checklistId = queued[0].checklist_id;
		var siteIds = queued.map(function (item) { return item.site_id; });

		queued.forEach(function (item) { item.status = 'running'; });
		renderBatchQueue();

		post('/runs/batch', { checklist_id: checklistId, site_ids: siteIds }).then(function (data) {
			(data.runs || []).forEach(function (result) {
				var run = result.run || {};
				batchQueue.forEach(function (item) {
					if (item.site_id === run.site_id && item.checklist_id === run.checklist_id) {
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
			escHtml(run.checklist_title) + ' on ' + escHtml(run.site_name) +
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

			if (step.notes && step.notes.length) {
				html += '<div class="launchdek-run-step-notes"><strong>Notes</strong><ul>';
				step.notes.forEach(function (note) {
					html += '<li class="launchdek-run-step-note">';
					if (note.user) {
						html += '<span class="launchdek-run-step-note-user">' + escHtml(note.user) + '</span>';
					}
					if (note.text) {
						html += '<p>' + escHtml(note.text) + '</p>';
					}
					if (note.attachment_url) {
						html += '<a href="' + escAttr(note.attachment_url) + '" target="_blank" rel="noopener" class="launchdek-run-step-note-attachment">' +
							'<img src="' + escAttr(note.attachment_url) + '" alt="" loading="lazy" />' +
						'</a>';
					}
					html += '</li>';
				});
				html += '</ul></div>';
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

	function renderAuditRows(tbody, logs, emptyMessage) {
		if (!tbody) return;
		tbody.innerHTML = '';
		if (!logs.length) {
			tbody.innerHTML = '<tr><td colspan="4" class="launchdek-muted">' + escHtml(emptyMessage || 'No audit entries match these filters.') + '</td></tr>';
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
			renderAuditRows(document.querySelector('#launchdek-audit-table tbody'), logs);
		});
	}

	function loadActivityLogsSiteFilter() {
		var select = document.getElementById('launchdek-activity-logs-site');
		if (!select) return Promise.resolve();
		return get('/sites').then(function (sites) {
			var current = select.value;
			select.innerHTML = '<option value="">All sites</option>';
			(sites || []).forEach(function (site) {
				select.appendChild(el('option', { value: String(site.id), text: site.name || ('Site #' + site.id) }));
			});
			if (current) select.value = current;
		}).catch(function () {});
	}

	function loadActivityLogs() {
		var searchEl = document.getElementById('launchdek-activity-logs-search');
		var statusEl = document.getElementById('launchdek-activity-logs-status');
		var siteEl = document.getElementById('launchdek-activity-logs-site');
		var dateFromEl = document.getElementById('launchdek-activity-logs-date-from');
		var dateToEl = document.getElementById('launchdek-activity-logs-date-to');
		var tbody = document.querySelector('#launchdek-activity-logs-table tbody');
		if (!tbody) return;

		var qs = '?detailed=1&limit=200';
		if (searchEl && searchEl.value) qs += '&search=' + encodeURIComponent(searchEl.value);
		if (statusEl && statusEl.value) qs += '&status=' + encodeURIComponent(statusEl.value);
		if (siteEl && siteEl.value) qs += '&site_id=' + encodeURIComponent(siteEl.value);
		if (dateFromEl && dateFromEl.value) qs += '&date_from=' + encodeURIComponent(dateFromEl.value);
		if (dateToEl && dateToEl.value) qs += '&date_to=' + encodeURIComponent(dateToEl.value);

		tbody.innerHTML = '<tr><td colspan="4" class="launchdek-muted">' + escHtml(strings.loading || 'Loading…') + '</td></tr>';
		get('/logs/feed' + qs).then(function (logs) {
			renderAuditRows(tbody, logs);
		}).catch(function () {
			tbody.innerHTML = '<tr><td colspan="4" class="launchdek-muted">' + escHtml(strings.error || 'Something went wrong.') + '</td></tr>';
		});
	}

	function initActivityLogs() {
		if (!document.querySelector('[data-launchdek-page="activity-logs"]')) return;

		var params = new URLSearchParams(window.location.search);
		var siteId = params.get('site_id') || '';

		loadActivityLogsSiteFilter().then(function () {
			if (siteId) {
				var siteSelect = document.getElementById('launchdek-activity-logs-site');
				if (siteSelect) siteSelect.value = siteId;
			}
			loadActivityLogs();
		});

		var filterBtn = document.getElementById('launchdek-activity-logs-filter');
		if (filterBtn) filterBtn.addEventListener('click', loadActivityLogs);

		var searchEl = document.getElementById('launchdek-activity-logs-search');
		if (searchEl) {
			searchEl.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					loadActivityLogs();
				}
			});
		}
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
			var checklist = getSelectedChecklist();
			if (!siteIds.length || !checklist) return alert('Select at least one site and a checklist.');
			if (siteIds.length > 1) {
				addToBatchQueue();
				processBatchQueue();
				return;
			}
			post('/runs', { site_id: siteIds[0], checklist_id: checklist.id })
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
	var builtinTemplates = [];
	var templateCategories = {};
	var activeTemplateCategory = '';

	function formatStepsCount(count) {
		var n = parseInt(count, 10) || 0;
		var template = n === 1
			? (strings.stepCount || '%d step')
			: (strings.stepsCount || '%d steps');
		return template.replace('%d', String(n));
	}

	function buildTemplateStepsList(steps) {
		var list = el('ol', { className: 'launchdek-template-steps-list' });
		(steps || []).forEach(function (step) {
			list.appendChild(el('li', { text: step.title || step.id || '' }));
		});
		return list;
	}

	function attachTemplateStepsPreview(contentEl, card, steps) {
		var preview = el('div', { className: 'launchdek-template-steps-preview' });
		var heading = el('p', {
			className: 'launchdek-template-steps-heading',
			text: (strings.checklistSteps || 'Checklist steps') + ' (' + (steps || []).length + ')'
		});
		preview.appendChild(heading);
		preview.appendChild(buildTemplateStepsList(steps));

		var toggle = el('button', {
			type: 'button',
			className: 'button button-link launchdek-template-steps-toggle',
			text: strings.viewSteps || 'View steps',
			'aria-expanded': 'false'
		});
		toggle.onclick = function (e) {
			e.preventDefault();
			e.stopPropagation();
			var open = card.classList.toggle('is-steps-open');
			if (open) {
				document.querySelectorAll('.launchdek-template-card.is-steps-open').forEach(function (other) {
					if (other === card) {
						return;
					}
					other.classList.remove('is-steps-open');
					var otherToggle = other.querySelector('.launchdek-template-steps-toggle');
					if (otherToggle) {
						otherToggle.setAttribute('aria-expanded', 'false');
						otherToggle.textContent = strings.viewSteps || 'View steps';
					}
				});
			}
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.textContent = open ? (strings.hideSteps || 'Hide steps') : (strings.viewSteps || 'View steps');
		};

		contentEl.appendChild(preview);

		var actions = card.querySelector('.launchdek-template-card-actions');
		if (actions) {
			actions.insertBefore(toggle, actions.firstChild);
		}

		return card;
	}

	function createTemplateCard(tpl, options) {
		options = options || {};
		var steps = tpl.steps || [];
		var card = el('div', { className: 'launchdek-template-card' });

		var content = el('div', { className: 'launchdek-template-card-content' });
		var main = el('div', { className: 'launchdek-template-card-main' });
		var html = '';
		if (options.badge) {
			html += '<span class="launchdek-badge healthy">' + escHtml(options.badge) + '</span>';
		}
		html +=
			'<h3>' + escHtml(tpl.title) + '</h3>' +
			'<p>' + escHtml(tpl.description || '') + '</p>' +
			'<p class="launchdek-template-step-count"><small>' + escHtml(formatStepsCount(steps.length)) + '</small></p>' +
			'<p class="launchdek-template-preview-hint"><small>' + escHtml(strings.stepsPreviewHint || 'Hover or click View steps to preview the checklist.') + '</small></p>';
		main.innerHTML = html;

		var actions = el('div', { className: 'launchdek-template-card-actions' });
		if (typeof options.onEdit === 'function') {
			var editBtn = el('button', {
				className: 'button',
				text: strings.editChecklist || 'Edit Checklist'
			});
			editBtn.onclick = function () {
				options.onEdit(tpl);
			};
			actions.appendChild(editBtn);
		}
		if (typeof options.onClone === 'function') {
			var cloneBtn = el('button', {
				className: 'button button-primary',
				text: strings.cloneToChecklist || 'Clone to Checklist'
			});
			cloneBtn.onclick = function () {
				options.onClone(tpl);
			};
			actions.appendChild(cloneBtn);
		}

		content.appendChild(main);
		card.appendChild(content);
		card.appendChild(actions);
		attachTemplateStepsPreview(content, card, steps);

		return card;
	}

	function cloneBuiltinTemplate(slug) {
		var noticeEl = document.getElementById('launchdek-builtin-notice');
		return post('/templates/' + slug + '/clone', {}).then(function () {
			notice(noticeEl, strings.templateCloned || 'Template cloned. Edit it under Checklists.', 'success');
		}).catch(function (err) {
			notice(noticeEl, err.message, 'error');
		});
	}

	function renderBuiltinTemplateCards(category) {
		var grid = document.getElementById('launchdek-builtin-templates');
		var desc = document.getElementById('launchdek-category-description');
		if (!grid) return;

		var items = builtinTemplates.filter(function (tpl) {
			return tpl.category === category;
		});

		if (desc && templateCategories[category]) {
			desc.textContent = templateCategories[category].description || '';
		}

		grid.innerHTML = '';
		if (!items.length) {
			grid.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.noCategoryTemplates || 'No templates in this category yet.') + '</p>';
			return;
		}

		items.forEach(function (tpl) {
			grid.appendChild(createTemplateCard(tpl, {
				badge: templateCategories[category].label || category,
				onClone: function () {
					cloneBuiltinTemplate(tpl.template_slug);
				}
			}));
		});
	}

	function selectTemplateCategory(category) {
		activeTemplateCategory = category;
		var tabs = document.getElementById('launchdek-category-tabs');
		if (tabs) {
			tabs.querySelectorAll('.launchdek-category-tab').forEach(function (node) {
				var isActive = node.dataset.category === category;
				node.classList.toggle('active', isActive);
				node.setAttribute('aria-selected', isActive ? 'true' : 'false');
			});
		}
		renderBuiltinTemplateCards(category);
	}

	function renderBuiltinCategories(builtin, categories) {
		builtinTemplates = builtin || [];
		templateCategories = categories || {};

		var tabs = document.getElementById('launchdek-category-tabs');
		if (!tabs) return;

		tabs.innerHTML = '';
		var categoryKeys = Object.keys(templateCategories);

		if (!categoryKeys.length) {
			tabs.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.loading || 'Loading…') + '</p>';
			return;
		}

		var firstCategory = null;

		categoryKeys.forEach(function (slug) {
			var meta = templateCategories[slug];
			var count = builtinTemplates.filter(function (tpl) {
				return tpl.category === slug;
			}).length;
			if (!count) {
				return;
			}

			var isFirst = !firstCategory;
			if (isFirst) {
				firstCategory = slug;
			}

			var tab = el('button', {
				type: 'button',
				className: 'launchdek-category-tab' + (isFirst ? ' active' : ''),
				text: meta.label + ' (' + count + ')',
				'data-category': slug,
				role: 'tab',
				'aria-selected': isFirst ? 'true' : 'false'
			});
			tab.onclick = function () {
				selectTemplateCategory(slug);
			};
			tabs.appendChild(tab);
		});

		if (firstCategory) {
			selectTemplateCategory(firstCategory);
		}
	}

	function openChecklistEditor(id) {
		window.location.href = launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-checklists&checklist_id=' + id;
	}

	function cloneCustomChecklist(tpl) {
		var noticeEl = document.getElementById('launchdek-custom-notice');
		return post('/checklists', {
			title: (tpl.title || 'Checklist') + ' (Copy)',
			description: tpl.description || '',
			steps: tpl.steps || [],
			is_template: false
		}).then(function () {
			notice(noticeEl, strings.checklistCloned || 'Checklist cloned. Edit it under Checklists.', 'success');
			loadCustomChecklists();
			refreshVaultChecklistSelect();
		}).catch(function (err) {
			notice(noticeEl, err.message, 'error');
		});
	}

	function refreshVaultChecklistSelect() {
		get('/checklists?is_template=0').then(function (wfs) {
			fillSelect(document.getElementById('launchdek-vault-checklist'), wfs, 'id', 'title', 'Select checklist…');
		});
	}

	function loadCustomChecklists() {
		get('/checklists?is_template=0').then(function (checklists) {
			var grid = document.getElementById('launchdek-custom-checklists');
			if (!grid) return;

			grid.innerHTML = '';
			if (!checklists.length) {
				grid.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.noCustomTemplates || 'No custom checklists saved yet. Create one under Checklists.') + '</p>';
				return;
			}

			checklists.forEach(function (tpl) {
				grid.appendChild(createTemplateCard(tpl, {
					badge: strings.customChecklistBadge || 'Custom',
					onEdit: function () {
						openChecklistEditor(tpl.id);
					},
					onClone: function () {
						cloneCustomChecklist(tpl);
					}
				}));
			});
		});
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
				grid.appendChild(createTemplateCard(tpl, {
					badge: 'Vault',
					onClone: function () {
						cloneCustomChecklist(tpl);
					}
				}));
			});
		});
	}

	function initTemplates() {
		if (!document.getElementById('launchdek-builtin-templates')) return;

		get('/templates').then(function (data) {
			renderBuiltinCategories(data.builtin || [], data.categories || {});
		});

		loadCustomChecklists();
		refreshVaultChecklistSelect();
		loadVaultTemplates();

		document.getElementById('launchdek-save-vault').onclick = function () {
			var id = document.getElementById('launchdek-vault-checklist').value;
			var noticeEl = document.getElementById('launchdek-vault-notice');
			if (!id) return;
			post('/templates/vault', { checklist_id: parseInt(id, 10) }).then(function () {
				notice(noticeEl, strings.savedToVault || 'Saved to vault.', 'success');
				document.getElementById('launchdek-vault-checklist').value = '';
				loadCustomChecklists();
				refreshVaultChecklistSelect();
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

		initTemplatePicker();
		initOnboarding();
		initDashboard();
		initSites();
		initActivityLogs();
		initChecklists();
		initAutomation();
		initTemplates();
		initIntegrations();
		initSettings();
	});
})();
