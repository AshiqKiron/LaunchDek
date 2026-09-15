(function () {
	'use strict';

	if (typeof launchdekAdmin === 'undefined') {
		return;
	}

	var API = launchdekAdmin.restUrl.replace(/\/$/, '');
	var nonce = launchdekAdmin.nonce;
	var strings = launchdekAdmin.strings || {};

	/**
	 * Split a REST path into route suffix and optional query string.
	 *
	 * @param {string} path Path such as "/sites" or "/checklists?is_template=0".
	 * @return {{ routeSuffix: string, extraQuery: string }}
	 */
	function splitApiPath(path) {
		path = path.charAt(0) === '/' ? path : '/' + path;
		var qPos = path.indexOf('?');
		if (qPos === -1) {
			return { routeSuffix: path, extraQuery: '' };
		}

		var extraQuery = path.substring(qPos + 1);
		return {
			routeSuffix: path.substring(0, qPos),
			extraQuery: extraQuery
		};
	}

	/**
	 * Merge extra query params into a URL object.
	 *
	 * @param {URL} url         Target URL.
	 * @param {string} extraQuery Raw query string (no leading "?").
	 */
	function mergeExtraQuery(url, extraQuery) {
		if (!extraQuery) {
			return;
		}

		extraQuery.split('&').forEach(function (pair) {
			if (!pair) {
				return;
			}
			var eq = pair.indexOf('=');
			if (eq === -1) {
				url.searchParams.set(decodeURIComponent(pair), '');
				return;
			}
			url.searchParams.set(
				decodeURIComponent(pair.substring(0, eq)),
				decodeURIComponent(pair.substring(eq + 1))
			);
		});
	}

	/**
	 * Build a REST URL for plain (?rest_route=) and pretty (/wp-json/) permalinks.
	 *
	 * @param {string} path Path relative to the LaunchDek REST namespace.
	 * @return {string}
	 */
	function buildApiUrl(path) {
		var parts = splitApiPath(path);

		try {
			var url = new URL(API, window.location.href);
			if (url.searchParams.has('rest_route')) {
				var restRoute = (url.searchParams.get('rest_route') || '').replace(/\/$/, '') + parts.routeSuffix;
				url.searchParams.set('rest_route', restRoute);
				mergeExtraQuery(url, parts.extraQuery);
				return url.toString();
			}
		} catch (e) {
			// Fall through to string concat.
		}

		if (API.indexOf('?') !== -1 && parts.extraQuery) {
			return API + parts.routeSuffix + '&' + parts.extraQuery;
		}

		return API + parts.routeSuffix + (parts.extraQuery ? '?' + parts.extraQuery : '');
	}

	/**
	 * When permalinks are set to "pretty" but rewrites are broken (common on local MAMP),
	 * retry the same route via ?rest_route=.
	 *
	 * @param {string} path Original REST path.
	 * @return {string|null}
	 */
	function buildPlainPermalinkFallbackUrl(path) {
		var parts = splitApiPath(path);
		var match = API.match(/^(.*)\/wp-json\/([^?]+?)\/?$/);
		if (!match) {
			return null;
		}

		try {
			var url = new URL(match[1] + '/');
			url.searchParams.set('rest_route', '/' + match[2].replace(/^\//, '') + parts.routeSuffix);
			mergeExtraQuery(url, parts.extraQuery);
			return url.toString();
		} catch (e) {
			return null;
		}
	}

	function parseResponse(res, text) {
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
			var err = new Error(msg);
			err.status = res.status;
			err.data = data;
			throw err;
		}
		return data;
	}

	function fetchApi(method, url, body) {
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
		return fetch(url, opts).then(function (res) {
			return res.text().then(function (text) {
				return parseResponse(res, text);
			});
		});
	}

	function request(method, path, body) {
		var primaryUrl = buildApiUrl(path);
		return fetchApi(method, primaryUrl, body).catch(function (err) {
			var fallbackUrl = buildPlainPermalinkFallbackUrl(path);
			if (
				!fallbackUrl ||
				fallbackUrl === primaryUrl ||
				!(err && err.data && err.data.code === 'rest_no_route')
			) {
				throw err;
			}
			return fetchApi(method, fallbackUrl, body);
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
		if (!container) {
			return;
		}
		if (!message) {
			container.innerHTML = '';
			return;
		}
		var classes = ['notice'];
		if (type === 'error') {
			classes.push('notice-error');
		} else if (type === 'success') {
			classes.push('notice-success');
		}
		container.innerHTML = '<div class="' + classes.join(' ') + '"><p>' + message + '</p></div>';
	}

	function escHtml(value) {
		return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function renderDoubleTickIcon() {
		var tick = '<svg class="launchdek-double-tick-icon" width="10" height="10" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		return '<span class="launchdek-double-tick-icons">' + tick + tick + '</span>';
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

	function applyTemplatesPayload(data) {
		data = data || {};
		pickerTemplates = data.builtin || [];
		pickerCategories = data.categories || {};
		builtinTemplates = pickerTemplates;
		templateCategories = pickerCategories;
		pickerLoaded = true;
	}

	function loadTemplatesData() {
		if (pickerLoaded) {
			return Promise.resolve({ builtin: pickerTemplates, categories: pickerCategories });
		}
		if (pickerLoadingPromise) {
			return pickerLoadingPromise;
		}

		if (launchdekAdmin.templates && launchdekAdmin.templates.preloaded) {
			applyTemplatesPayload(launchdekAdmin.templates);
			return Promise.resolve(launchdekAdmin.templates);
		}

		pickerLoadingPromise = get('/templates').then(function (data) {
			applyTemplatesPayload(data);
			return data;
		}).catch(function (err) {
			pickerLoadingPromise = null;
			throw err;
		});

		return pickerLoadingPromise;
	}

	function loadPickerTemplates() {
		return loadTemplatesData();
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
		if (listEl && !pickerLoaded) {
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
		var importMode = (callbacks && callbacks.importMode) || 'checklist';

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
		var pageRoot = document.querySelector('[data-launchdek-page]');
		var pageId = pageRoot ? pageRoot.getAttribute('data-launchdek-page') : '';
		var shouldShow = (pageId === 'dashboard' && onboarding.show) || params.get('onboarding') === '1';

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
			var showSiteLabel = log.show_site_label || ((log.site_id || log.run_id) && log.site_name);
			if (showSiteLabel && log.site_name) {
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

		var quickSiteSelect = document.getElementById('launchdek-quick-site');
		var quickChecklistSelect = document.getElementById('launchdek-quick-checklist');
		var quickLaunch = launchdekAdmin.dashboard && launchdekAdmin.dashboard.quickLaunch;

		if (quickLaunch) {
			if (quickSiteSelect && quickSiteSelect.getAttribute('data-launchdek-preloaded') !== '1') {
				fillSelect(quickSiteSelect, quickLaunch.sites || [], 'id', 'name', strings.onboardingSelectSite || 'Select site…');
			}
			if (quickChecklistSelect && quickChecklistSelect.getAttribute('data-launchdek-preloaded') !== '1') {
				fillSelect(quickChecklistSelect, quickLaunch.checklists || [], 'id', 'title', 'Select checklist…');
			}
		} else if (quickSiteSelect && quickChecklistSelect) {
			Promise.all([get('/sites'), fetchCustomChecklistSummaries()]).then(function (results) {
				fillSelect(quickSiteSelect, results[0], 'id', 'name', strings.onboardingSelectSite || 'Select site…');
				fillSelect(quickChecklistSelect, results[1], 'id', 'title', 'Select checklist…');
			});
		}

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
	function initPanelLayoutPreview() {
		var select = document.getElementById('launchdek-panel-layout');
		var preview = document.getElementById('launchdek-panel-layout-preview');
		var description = document.getElementById('launchdek-panel-layout-description');

		if (!select || !preview) {
			return;
		}

		function updatePreview() {
			var option = select.options[select.selectedIndex];
			var layout = select.value || 'sidebar';

			preview.setAttribute('data-layout', layout);

			if (description && option) {
				description.textContent = option.getAttribute('data-description') || '';
			}
		}

		select.addEventListener('change', updatePreview);
		updatePreview();
	}

	function initSettings() {
		var page = document.querySelector('[data-launchdek-page="settings"]');
		if (!page) return;

		initPanelLayoutPreview();

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
	var customChecklistSummaries = null;
	var customChecklistSummariesLoading = null;
	var siteRunsCache = {};
	var siteRunsPending = {};
	var siteRunsMorePending = {};
	var siteRunsHistoryLimit = 25;
	var siteTableColspan = 6;
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
		var html = '<div class="launchdek-site-status" title="' + escAttr(label) + '">';
		html += '<span class="launchdek-site-connection" aria-hidden="true">';
		html += '<span class="launchdek-connection-dot ' + escAttr(status) + '"></span>';
		if (status === 'unhealthy') {
			html += '<span class="dashicons dashicons-warning launchdek-connection-warning"></span>';
		}
		html += '</span>';
		html += '<span class="launchdek-badge launchdek-site-health-badge ' + escAttr(status) + '">' + escHtml(healthLabel(status)) + '</span>';
		html += '<span class="screen-reader-text">' + escHtml(label) + '</span></div>';
		return html;
	}

	function siteActionsHtml(site) {
		var siteId = String(site.id);
		var connectionBlocked = site.health_status === 'unhealthy';
		var menuLabel = strings.siteActionsMenu || 'More site actions';
		var html = '<div class="launchdek-site-actions-dropdown">';
		html += '<div class="launchdek-site-actions-split">';
		html += '<button type="button" class="button button-small launchdek-edit-site" data-id="' + escAttr(site.id) + '">' + escHtml(strings.editSite || 'Edit') + '</button>';
		html += '<button type="button" class="button button-small launchdek-site-actions-toggle" data-id="' + escAttr(site.id) + '" aria-haspopup="true" aria-expanded="false" aria-label="' + escAttr(menuLabel) + '">';
		html += '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
		html += '</button>';
		html += '</div>';
		html += '<div class="launchdek-site-actions-menu" role="menu" hidden>';
		html += '<button type="button" role="menuitem" class="launchdek-push-checklist" data-id="' + escAttr(site.id) + '" data-name="' + escAttr(site.name) + '"' + (connectionBlocked ? ' disabled' : '') + '>' + escHtml(strings.pushChecklist || 'Push Checklist') + '</button>';
		html += '<button type="button" role="menuitem" class="launchdek-test-site" data-id="' + escAttr(site.id) + '">' + escHtml(strings.testSite || 'Test') + '</button>';
		html += '</div></div>';
		return html;
	}

	function closeAllSiteActionMenus() {
		document.querySelectorAll('.launchdek-site-actions-menu:not([hidden]), .launchdek-run-actions-menu:not([hidden])').forEach(function (menu) {
			menu.setAttribute('hidden', 'hidden');
			var toggle = menu.closest('.launchdek-site-actions-dropdown, .launchdek-run-actions-dropdown');
			if (toggle) {
				var btn = toggle.querySelector('.launchdek-site-actions-toggle, .launchdek-run-actions-toggle');
				if (btn) {
					btn.setAttribute('aria-expanded', 'false');
				}
			}
		});
	}

	function toggleActionMenu(toggleBtn, menuSelector) {
		if (!toggleBtn) {
			return;
		}
		var dropdown = toggleBtn.closest('.launchdek-site-actions-dropdown, .launchdek-run-actions-dropdown');
		var menu = dropdown ? dropdown.querySelector(menuSelector) : null;
		if (!menu) {
			return;
		}
		var open = menu.hasAttribute('hidden');
		closeAllSiteActionMenus();
		if (open) {
			menu.removeAttribute('hidden');
			toggleBtn.setAttribute('aria-expanded', 'true');
		}
	}

	function toggleSiteActionMenu(toggleBtn) {
		toggleActionMenu(toggleBtn, '.launchdek-site-actions-menu');
	}

	function toggleRunActionMenu(toggleBtn) {
		toggleActionMenu(toggleBtn, '.launchdek-run-actions-menu');
	}

	function getSiteRow(siteId) {
		return document.querySelector('#launchdek-sites-table tr.launchdek-site-row[data-site-id="' + siteId + '"]');
	}

	function getSiteRunsRow(siteId) {
		return document.querySelector('#launchdek-sites-table tr.launchdek-site-runs-row[data-site-id="' + siteId + '"]');
	}

	function siteHistoryToggleHtml(siteId) {
		var label = strings.siteChecklistHistoryShow || 'Show checklist history';
		return '<button type="button" class="button-link launchdek-site-history-toggle" data-site-id="' + escAttr(siteId) + '" aria-expanded="false" title="' + escAttr(label) + '">' +
			'<span class="dashicons dashicons-arrow-right-alt2 launchdek-site-history-icon" aria-hidden="true"></span>' +
			'<span class="screen-reader-text">' + escHtml(label) + '</span>' +
			'</button> ';
	}

	function runStatusLabel(status) {
		if (status === 'completed') return strings.runStatusCompleted || 'Completed';
		if (status === 'running') return strings.runStatusRunning || 'Running';
		if (status === 'failed') return strings.runStatusFailed || 'Failed';
		if (status === 'cancelled') return strings.runStatusCancelled || 'Cancelled';
		return status || '—';
	}

	function formatRunTimestamp(value) {
		if (!value) {
			return '—';
		}
		var parsed = Date.parse(String(value).replace(' ', 'T') + 'Z');
		if (isNaN(parsed)) {
			return escHtml(String(value));
		}
		return escHtml(new Date(parsed).toLocaleString());
	}

	function runStepsProgressHtml(run) {
		var total = parseInt(run.steps_total, 10) || 0;
		var completed = parseInt(run.steps_completed, 10) || 0;
		var percent = total > 0 ? Math.round((completed / total) * 100) : 0;
		var status = run.status || 'unknown';
		var progressLabel = (strings.runStepsProgress || '%1$d of %2$d steps completed')
			.replace('%1$d', String(completed))
			.replace('%2$d', String(total));
		var statusLabel = runStatusLabel(status);

		return '<div class="launchdek-run-progress">' +
			'<div class="launchdek-run-progress-meta">' +
				'<span class="launchdek-run-progress-label">' + escHtml(completed + '/' + total) + '</span>' +
				'<span class="launchdek-badge ' + escAttr(status) + '">' + escHtml(statusLabel) + '</span>' +
			'</div>' +
			'<div class="launchdek-run-progress-bar" role="progressbar" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100" aria-label="' + escAttr(progressLabel) + '">' +
				'<span class="launchdek-run-progress-fill is-' + escAttr(status) + '" style="width:' + percent + '%"></span>' +
			'</div>' +
			'</div>';
	}

	function automationRunUrl(runId) {
		return launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-automation&run_id=' + encodeURIComponent(String(runId));
	}

	function checklistEditorUrl(checklistId) {
		return launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-checklists&checklist_id=' + encodeURIComponent(String(checklistId));
	}

	function isBuiltinTemplateRun(run) {
		return !!(run.checklist_is_template && run.checklist_template_slug);
	}

	function runActionsHtml(run) {
		var runId = String(run.id);
		var checklistId = String(run.checklist_id || '');
		var isBuiltin = isBuiltinTemplateRun(run);
		var menuLabel = strings.runActionsMenu || 'More run actions';
		var editDisabled = isBuiltin ? ' disabled title="' + escAttr(strings.templateEditBlocked || 'Built-in templates cannot be edited. Duplicate instead.') + '"' : '';
		var html = '<div class="launchdek-run-actions-dropdown">';
		html += '<div class="launchdek-site-actions-split">';
		html += '<a class="button button-small launchdek-view-run" href="' + escAttr(automationRunUrl(run.id)) + '">' + escHtml(strings.viewRun || 'View run') + '</a>';
		html += '<button type="button" class="button button-small launchdek-run-actions-toggle" data-run-id="' + escAttr(runId) + '" data-site-id="' + escAttr(String(run.site_id || '')) + '" data-checklist-id="' + escAttr(checklistId) + '" data-template-slug="' + escAttr(run.checklist_template_slug || '') + '" data-builtin-template="' + (isBuiltin ? '1' : '0') + '" aria-haspopup="true" aria-expanded="false" aria-label="' + escAttr(menuLabel) + '">';
		html += '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
		html += '</button>';
		html += '</div>';
		html += '<div class="launchdek-run-actions-menu launchdek-site-actions-menu" role="menu" hidden>';
		html += '<button type="button" role="menuitem" class="launchdek-run-action-edit" data-checklist-id="' + escAttr(checklistId) + '"' + editDisabled + '>' + escHtml(strings.editChecklist || strings.editSite || 'Edit') + '</button>';
		html += '<button type="button" role="menuitem" class="launchdek-run-action-duplicate" data-run-id="' + escAttr(runId) + '" data-checklist-id="' + escAttr(checklistId) + '" data-template-slug="' + escAttr(run.checklist_template_slug || '') + '" data-builtin-template="' + (isBuiltin ? '1' : '0') + '">' + escHtml(strings.duplicate || 'Duplicate') + '</button>';
		html += '<button type="button" role="menuitem" class="launchdek-run-action-export" data-checklist-id="' + escAttr(checklistId) + '">' + escHtml(strings.export || 'Export') + '</button>';
		html += '<button type="button" role="menuitem" class="launchdek-run-action-archive" data-run-id="' + escAttr(runId) + '" data-site-id="' + escAttr(String(run.site_id || '')) + '">' + escHtml(strings.archive || 'Archive') + '</button>';
		html += '<button type="button" role="menuitem" class="launchdek-run-action-delete" data-run-id="' + escAttr(runId) + '" data-site-id="' + escAttr(String(run.site_id || '')) + '">' + escHtml(strings.delete || 'Delete') + '</button>';
		html += '</div></div>';
		return html;
	}

	function duplicateRunChecklist(run) {
		if (isBuiltinTemplateRun(run)) {
			return post('/templates/' + run.checklist_template_slug + '/clone', {});
		}
		return get('/checklists/' + run.checklist_id).then(function (checklist) {
			return post('/checklists', {
				title: (checklist.title || 'Checklist') + ' (Copy)',
				description: checklist.description || '',
				steps: checklist.steps || [],
				is_template: false
			});
		});
	}

	function exportRunChecklist(checklistId) {
		return get('/checklists/' + checklistId + '/export').then(function (data) {
			var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = (data.title || 'checklist') + '.json';
			a.click();
			URL.revokeObjectURL(a.href);
		});
	}

	function bindRunActions(panel) {
		if (!panel) {
			return;
		}

		panel.querySelectorAll('.launchdek-run-actions-toggle').forEach(function (btn) {
			btn.onclick = function (e) {
				e.preventDefault();
				e.stopPropagation();
				toggleRunActionMenu(btn);
			};
		});

		panel.querySelectorAll('.launchdek-run-action-edit').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				if (btn.disabled) {
					return;
				}
				var checklistId = parseInt(btn.dataset.checklistId, 10);
				if (checklistId) {
					window.location.href = checklistEditorUrl(checklistId);
				}
			};
		});

		panel.querySelectorAll('.launchdek-run-action-duplicate').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				var run = {
					id: parseInt(btn.dataset.runId, 10),
					checklist_id: parseInt(btn.dataset.checklistId, 10),
					checklist_template_slug: btn.dataset.templateSlug || '',
					checklist_is_template: btn.dataset.builtinTemplate === '1'
				};
				duplicateRunChecklist(run).then(function (checklist) {
					if (checklist && checklist.id) {
						window.location.href = checklistEditorUrl(checklist.id);
					}
				}).catch(function (err) {
					window.alert(err.message || strings.error || 'Something went wrong.');
				});
			};
		});

		panel.querySelectorAll('.launchdek-run-action-export').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				var checklistId = parseInt(btn.dataset.checklistId, 10);
				if (!checklistId) {
					return;
				}
				exportRunChecklist(checklistId).catch(function (err) {
					window.alert(err.message || strings.error || 'Something went wrong.');
				});
			};
		});

		panel.querySelectorAll('.launchdek-run-action-archive').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				var runId = parseInt(btn.dataset.runId, 10);
				var siteId = parseInt(btn.dataset.siteId, 10);
				if (!runId) {
					return;
				}
				openConfirmModal({
					title: strings.archive || 'Archive',
					message: strings.confirmArchiveRun || 'Archive this run? It will be hidden from checklist history.',
					confirmText: strings.archive || 'Archive',
					onConfirm: function () {
						return post('/runs/' + runId + '/archive', {}).then(function () {
							invalidateSiteRunsCache(siteId);
						});
					}
				});
			};
		});

		panel.querySelectorAll('.launchdek-run-action-delete').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				var runId = parseInt(btn.dataset.runId, 10);
				var siteId = parseInt(btn.dataset.siteId, 10);
				if (!runId) {
					return;
				}
				openConfirmModal({
					title: strings.delete || 'Delete',
					message: strings.confirmDeleteRun || 'Delete this checklist run permanently?',
					confirmText: strings.deletePermanently || strings.delete || 'Delete permanently',
					destructive: true,
					onConfirm: function () {
						return del('/runs/' + runId).then(function () {
							invalidateSiteRunsCache(siteId);
						});
					}
				});
			};
		});
	}

	function normalizeSiteRunsResponse(data) {
		if (Array.isArray(data)) {
			return {
				runs: data,
				hasMore: false
			};
		}

		return {
			runs: Array.isArray(data && data.runs) ? data.runs : [],
			hasMore: !!(data && data.has_more)
		};
	}

	function siteRunRowHtml(run) {
		return '<tr>' +
			'<td>' + escHtml(run.checklist_title || ('#' + run.checklist_id)) + '</td>' +
			'<td>' + runStepsProgressHtml(run) + '</td>' +
			'<td>' + formatRunTimestamp(run.started_at) + '</td>' +
			'<td>' + formatRunTimestamp(run.completed_at) + '</td>' +
			'<td>' + escHtml(run.started_by_name || '—') + '</td>' +
			'<td class="launchdek-actions">' + runActionsHtml(run) + '</td>' +
			'</tr>';
	}

	function siteRunsLoadMoreHtml(siteId) {
		return '<div class="launchdek-site-runs-more">' +
			'<button type="button" class="button button-link launchdek-site-runs-load-more" data-site-id="' + escAttr(String(siteId)) + '">' +
			escHtml(strings.siteRunsLoadMore || 'Load more') +
			'</button></div>';
	}

	function bindSiteRunsLoadMore(panel) {
		if (!panel) {
			return;
		}

		panel.querySelectorAll('.launchdek-site-runs-load-more').forEach(function (btn) {
			btn.onclick = function () {
				loadMoreSiteRuns(parseInt(btn.dataset.siteId, 10), panel, btn);
			};
		});
	}

	function updateSiteRunsLoadMore(panel, siteId, hasMore) {
		if (!panel) {
			return;
		}

		var moreWrap = panel.querySelector('.launchdek-site-runs-more');
		if (hasMore) {
			if (!moreWrap) {
				panel.insertAdjacentHTML('beforeend', siteRunsLoadMoreHtml(siteId));
				bindSiteRunsLoadMore(panel);
			} else {
				var btn = moreWrap.querySelector('.launchdek-site-runs-load-more');
				if (btn) {
					btn.disabled = false;
					btn.textContent = strings.siteRunsLoadMore || 'Load more';
				}
			}
			return;
		}

		if (moreWrap) {
			moreWrap.remove();
		}
	}

	function appendSiteRunsRows(panel, runs, siteId, hasMore) {
		var tbody = panel.querySelector('.launchdek-site-runs-table tbody');
		if (!tbody || !runs || !runs.length) {
			updateSiteRunsLoadMore(panel, siteId, hasMore);
			return;
		}

		var html = '';
		runs.forEach(function (run) {
			html += siteRunRowHtml(run);
		});
		tbody.insertAdjacentHTML('beforeend', html);
		bindRunActions(panel);
		updateSiteRunsLoadMore(panel, siteId, hasMore);
	}

	function renderSiteRunsPanel(panel, cacheEntry) {
		if (!panel) {
			return;
		}

		cacheEntry = normalizeSiteRunsResponse(cacheEntry);
		var runs = cacheEntry.runs;
		var siteId = panel.getAttribute('data-site-id') || '';

		if (!runs.length) {
			panel.innerHTML = '<p class="launchdek-muted launchdek-site-runs-empty">' + escHtml(strings.siteNoChecklistRuns || 'No checklist runs recorded for this site yet.') + '</p>';
			return;
		}

		var html = '<div class="launchdek-site-runs-heading">' + escHtml(strings.siteChecklistHistory || 'Checklist history') + '</div>';
		html += '<table class="launchdek-site-runs-table"><thead><tr>';
		html += '<th scope="col">' + escHtml(strings.runChecklist || 'Checklist') + '</th>';
		html += '<th scope="col">' + escHtml(strings.runSteps || 'Steps') + '</th>';
		html += '<th scope="col">' + escHtml(strings.runStartedAt || 'Started') + '</th>';
		html += '<th scope="col">' + escHtml(strings.runCompletedAt || 'Completed') + '</th>';
		html += '<th scope="col">' + escHtml(strings.runStartedBy || 'By') + '</th>';
		html += '<th scope="col"><span class="screen-reader-text">' + escHtml(strings.viewRun || 'View run') + '</span></th>';
		html += '</tr></thead><tbody>';

		runs.forEach(function (run) {
			html += siteRunRowHtml(run);
		});

		html += '</tbody></table>';
		if (cacheEntry.hasMore) {
			html += siteRunsLoadMoreHtml(siteId);
		}
		panel.innerHTML = html;
		bindRunActions(panel);
		bindSiteRunsLoadMore(panel);
	}

	function loadSiteRuns(siteId, panel) {
		if (!panel) {
			panel = document.querySelector('.launchdek-site-runs-panel[data-site-id="' + siteId + '"]');
		}
		if (!panel) {
			return Promise.resolve({ runs: [], hasMore: false });
		}

		if (siteRunsCache[siteId]) {
			renderSiteRunsPanel(panel, siteRunsCache[siteId]);
			return Promise.resolve(siteRunsCache[siteId]);
		}

		if (siteRunsPending[siteId]) {
			return siteRunsPending[siteId].then(function (entry) {
				renderSiteRunsPanel(panel, entry);
				return entry;
			});
		}

		panel.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.loading || 'Loading…') + '</p>';

		siteRunsPending[siteId] = get('/sites/' + siteId + '/runs?limit=' + siteRunsHistoryLimit + '&offset=0').then(function (data) {
			siteRunsCache[siteId] = normalizeSiteRunsResponse(data);
			renderSiteRunsPanel(panel, siteRunsCache[siteId]);
			return siteRunsCache[siteId];
		}).catch(function (err) {
			panel.innerHTML = '<div class="notice notice-error"><p>' + escHtml(err.message || strings.error || 'Something went wrong.') + '</p></div>';
			throw err;
		}).finally(function () {
			delete siteRunsPending[siteId];
		});

		return siteRunsPending[siteId];
	}

	function loadMoreSiteRuns(siteId, panel, btn) {
		if (!panel) {
			panel = document.querySelector('.launchdek-site-runs-panel[data-site-id="' + siteId + '"]');
		}
		if (!panel || !siteRunsCache[siteId] || !siteRunsCache[siteId].hasMore || siteRunsMorePending[siteId]) {
			return Promise.resolve(siteRunsCache[siteId] || { runs: [], hasMore: false });
		}

		if (btn) {
			btn.disabled = true;
			btn.textContent = strings.siteRunsLoadingMore || 'Loading more…';
		}

		var offset = siteRunsCache[siteId].runs.length;

		siteRunsMorePending[siteId] = get('/sites/' + siteId + '/runs?limit=' + siteRunsHistoryLimit + '&offset=' + offset).then(function (data) {
			var entry = normalizeSiteRunsResponse(data);
			siteRunsCache[siteId].runs = siteRunsCache[siteId].runs.concat(entry.runs);
			siteRunsCache[siteId].hasMore = entry.hasMore;
			appendSiteRunsRows(panel, entry.runs, siteId, entry.hasMore);
			return siteRunsCache[siteId];
		}).catch(function (err) {
			window.alert(err.message || strings.error || 'Something went wrong.');
			if (btn) {
				btn.disabled = false;
				btn.textContent = strings.siteRunsLoadMore || 'Load more';
			}
			throw err;
		}).finally(function () {
			delete siteRunsMorePending[siteId];
		});

		return siteRunsMorePending[siteId];
	}

	function setSiteHistoryOpen(siteId, open) {
		var row = getSiteRow(siteId);
		var runsRow = getSiteRunsRow(siteId);
		var toggle = row ? row.querySelector('.launchdek-site-history-toggle') : null;

		if (runsRow) {
			if (open) {
				runsRow.removeAttribute('hidden');
			} else {
				runsRow.setAttribute('hidden', 'hidden');
			}
		}

		if (row) {
			row.classList.toggle('is-history-open', open);
		}

		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.title = open ? (strings.siteChecklistHistoryHide || 'Hide checklist history') : (strings.siteChecklistHistoryShow || 'Show checklist history');
			var sr = toggle.querySelector('.screen-reader-text');
			if (sr) {
				sr.textContent = open ? (strings.siteChecklistHistoryHide || 'Hide checklist history') : (strings.siteChecklistHistoryShow || 'Show checklist history');
			}
		}
	}

	function toggleSiteRuns(siteId) {
		var runsRow = getSiteRunsRow(siteId);
		if (!runsRow) {
			return;
		}

		var open = runsRow.hasAttribute('hidden');
		setSiteHistoryOpen(siteId, open);

		if (open) {
			var panel = runsRow.querySelector('.launchdek-site-runs-panel');
			if (siteRunsCache[siteId]) {
				renderSiteRunsPanel(panel, siteRunsCache[siteId]);
			} else {
				loadSiteRuns(siteId, panel);
			}
		}
	}

	function invalidateSiteRunsCache(siteId) {
		delete siteRunsCache[siteId];
		delete siteRunsPending[siteId];
		delete siteRunsMorePending[siteId];
		var runsRow = getSiteRunsRow(siteId);
		if (runsRow && !runsRow.hasAttribute('hidden')) {
			loadSiteRuns(siteId, runsRow.querySelector('.launchdek-site-runs-panel'));
		}
	}

	function appendSiteRowPair(tbody, site) {
		var siteId = String(site.id);
		var tr = el('tr', { className: 'launchdek-site-row', 'data-site-id': siteId });
		tr.innerHTML =
			'<td>' + siteHistoryToggleHtml(siteId) + escHtml(site.name) + (site.client_agent ? ' <span class="launchdek-badge healthy launchdek-client-agent-badge">' + escHtml(strings.clientPanelBadge || 'Client panel') + '</span>' : '') + '</td>' +
			'<td><a href="' + escAttr(site.url) + '" target="_blank" rel="noopener">' + escHtml(site.url) + '</a></td>' +
			'<td class="launchdek-site-status-cell">' + connectionStatusHtml(site) + '</td>' +
			'<td class="launchdek-site-wp-version">' + escHtml(site.wp_version || '—') + '</td>' +
			'<td class="launchdek-site-php-version">' + escHtml(site.php_version || '—') + '</td>' +
			'<td class="launchdek-actions">' + siteActionsHtml(site) + '</td>';
		tbody.appendChild(tr);

		var runsRow = el('tr', { className: 'launchdek-site-runs-row', 'data-site-id': siteId, hidden: 'hidden' });
		runsRow.innerHTML = '<td colspan="' + siteTableColspan + '"><div class="launchdek-site-runs-panel" data-site-id="' + escAttr(siteId) + '"></div></td>';
		tbody.appendChild(runsRow);
	}

	function updateSiteRowConnection(siteId, status, details) {
		var row = getSiteRow(siteId);
		if (!row) return;

		var statusCell = row.querySelector('.launchdek-site-status-cell');
		if (statusCell) {
			if (status === 'checking') {
				var dot = statusCell.querySelector('.launchdek-connection-dot');
				if (dot) {
					dot.className = 'launchdek-connection-dot checking';
				}
				var warning = statusCell.querySelector('.launchdek-connection-warning');
				if (warning) {
					warning.remove();
				}
				statusCell.setAttribute('title', connectionStatusLabel('checking'));
			} else {
				statusCell.innerHTML = connectionStatusHtml({
					health_status: status,
					last_error: details && details.message ? details.message : ''
				}, status);
			}
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

		siteRunsCache = {};
		siteRunsPending = {};
		siteRunsMorePending = {};
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
			appendSiteRowPair(tbody, site);
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
			tbody.innerHTML = '<tr><td colspan="' + siteTableColspan + '"><div class="notice notice-error"><p>' + escHtml(err.message || strings.error || 'Could not load sites.') + '</p></div></td></tr>';
		});
	}

	function bindSiteActions() {
		document.querySelectorAll('.launchdek-site-history-toggle').forEach(function (btn) {
			btn.onclick = function (e) {
				e.preventDefault();
				toggleSiteRuns(parseInt(btn.dataset.siteId, 10));
			};
		});
		document.querySelectorAll('.launchdek-edit-site').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				openSiteModal(parseInt(btn.dataset.id, 10));
			};
		});
		document.querySelectorAll('.launchdek-site-actions-toggle').forEach(function (btn) {
			btn.onclick = function (e) {
				e.preventDefault();
				e.stopPropagation();
				toggleSiteActionMenu(btn);
			};
		});
		document.querySelectorAll('.launchdek-test-site').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				testSiteConnection(parseInt(btn.dataset.id, 10), { silent: false });
			};
		});
		document.querySelectorAll('.launchdek-push-checklist').forEach(function (btn) {
			btn.onclick = function () {
				closeAllSiteActionMenus();
				openPushChecklistModal(parseInt(btn.dataset.id, 10), btn.dataset.name || '');
			};
		});
	}

	function ensurePushChecklistsLoaded() {
		return fetchCustomChecklistSummaries();
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

	var confirmModalState = {
		onConfirm: null,
		previousFocus: null
	};

	function resetConfirmModal(modal) {
		var okBtn = document.getElementById('launchdek-confirm-ok');
		if (!modal || !okBtn) {
			return;
		}

		okBtn.disabled = false;
		okBtn.classList.remove('button-link-delete');
		okBtn.classList.add('button-primary');
		confirmModalState.onConfirm = null;
	}

	function closeConfirmModal() {
		var modal = document.getElementById('launchdek-confirm-modal');
		if (!modal) {
			return;
		}

		modal.hidden = true;
		resetConfirmModal(modal);

		if (confirmModalState.previousFocus && typeof confirmModalState.previousFocus.focus === 'function') {
			confirmModalState.previousFocus.focus();
		}
		confirmModalState.previousFocus = null;
	}

	/**
	 * Open the shared WordPress-style confirmation modal.
	 *
	 * @param {Object} options
	 * @param {string} options.message
	 * @param {string} [options.title]
	 * @param {string} [options.confirmText]
	 * @param {boolean} [options.destructive]
	 * @param {Function} options.onConfirm Called when confirmed; may return a Promise.
	 */
	function openConfirmModal(options) {
		var modal = document.getElementById('launchdek-confirm-modal');
		if (!modal) {
			return;
		}

		options = options || {};
		var titleEl = document.getElementById('launchdek-confirm-title');
		var messageEl = document.getElementById('launchdek-confirm-message');
		var okBtn = document.getElementById('launchdek-confirm-ok');
		if (!titleEl || !messageEl || !okBtn) {
			return;
		}

		titleEl.textContent = options.title || strings.confirmActionTitle || 'Confirm action';
		messageEl.textContent = options.message || '';
		okBtn.textContent = options.confirmText || strings.confirm || 'Confirm';

		if (options.destructive) {
			okBtn.classList.remove('button-primary');
			okBtn.classList.add('button-link-delete');
		} else {
			okBtn.classList.remove('button-link-delete');
			okBtn.classList.add('button-primary');
		}

		confirmModalState.previousFocus = document.activeElement;
		confirmModalState.onConfirm = typeof options.onConfirm === 'function' ? options.onConfirm : null;
		modal.hidden = false;
		document.getElementById('launchdek-confirm-cancel').focus();
	}

	function initConfirmModal() {
		var modal = document.getElementById('launchdek-confirm-modal');
		if (!modal) {
			return;
		}

		var okBtn = document.getElementById('launchdek-confirm-ok');
		var cancelBtn = document.getElementById('launchdek-confirm-cancel');
		if (!okBtn || !cancelBtn) {
			return;
		}

		modal.querySelectorAll('[data-launchdek-confirm-close], #launchdek-confirm-cancel').forEach(function (node) {
			node.addEventListener('click', closeConfirmModal);
		});

		okBtn.addEventListener('click', function () {
			if (!confirmModalState.onConfirm) {
				closeConfirmModal();
				return;
			}

			okBtn.disabled = true;
			var result;
			try {
				result = confirmModalState.onConfirm();
			} catch (err) {
				okBtn.disabled = false;
				window.alert(err.message || strings.error || 'Something went wrong.');
				return;
			}

			if (result && typeof result.then === 'function') {
				result.then(function () {
					closeConfirmModal();
				}).catch(function (err) {
					okBtn.disabled = false;
					window.alert(err.message || strings.error || 'Something went wrong.');
				});
				return;
			}

			closeConfirmModal();
		});

		modal.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !modal.hidden) {
				e.preventDefault();
				closeConfirmModal();
			}
		});
	}

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

		if (!window.launchdekSiteActionsMenuBound) {
			window.launchdekSiteActionsMenuBound = true;
			document.addEventListener('click', function (e) {
				if (!e.target.closest('.launchdek-site-actions-dropdown') && !e.target.closest('.launchdek-run-actions-dropdown')) {
					closeAllSiteActionMenus();
				}
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					closeAllSiteActionMenus();
				}
			});
		}

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
					invalidateSiteRunsCache(pushChecklistSiteId);
				})
				.catch(function (err) {
					notice(result, err.message, 'error');
				})
				.finally(function () {
					submitBtn.disabled = false;
				});
		});

		document.getElementById('launchdek-site-delete').addEventListener('click', function () {
			if (!currentSiteId) {
				return;
			}
			openConfirmModal({
				title: strings.delete || 'Delete',
				message: strings.confirmDeleteSite || strings.confirmDelete || 'Are you sure you want to delete this site?',
				confirmText: strings.deletePermanently || strings.delete || 'Delete permanently',
				destructive: true,
				onConfirm: function () {
					return del('/sites/' + currentSiteId).then(function () {
						closeModal(document.getElementById('launchdek-site-modal'));
						loadSites({ forceFetch: true });
					});
				}
			});
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
	var showChecklistTab = null;
	var deepLinkInferTimer = null;
	var multicheckDropdownBound = false;

	function updateChecklistActions() {
		var deleteBtn = document.getElementById('launchdek-delete-checklist');
		var exportBtn = document.getElementById('launchdek-export-checklist');
		if (deleteBtn) deleteBtn.disabled = !currentChecklistId;
		if (exportBtn) exportBtn.disabled = !currentChecklistId;
	}

	function getCustomChecklistStepCount(item) {
		if (item && typeof item.steps_count === 'number') {
			return item.steps_count;
		}
		return (item && item.steps) ? item.steps.length : 0;
	}

	function invalidateCustomChecklistSummaries() {
		customChecklistSummaries = null;
		customChecklistSummariesLoading = null;
		if (launchdekAdmin.customChecklists) {
			launchdekAdmin.customChecklists.preloaded = false;
		}
	}

	function fetchCustomChecklistSummaries(forceRefresh) {
		if (forceRefresh) {
			invalidateCustomChecklistSummaries();
		}
		if (customChecklistSummaries) {
			return Promise.resolve(customChecklistSummaries);
		}
		if (customChecklistSummariesLoading) {
			return customChecklistSummariesLoading;
		}
		if (!forceRefresh && launchdekAdmin.customChecklists && launchdekAdmin.customChecklists.preloaded) {
			customChecklistSummaries = launchdekAdmin.customChecklists.items || [];
			return Promise.resolve(customChecklistSummaries);
		}

		customChecklistSummariesLoading = get('/checklists?is_template=0&summary=1').then(function (items) {
			customChecklistSummaries = items || [];
			customChecklistSummariesLoading = null;
			return customChecklistSummaries;
		}).catch(function (err) {
			customChecklistSummariesLoading = null;
			throw err;
		});

		return customChecklistSummariesLoading;
	}

	function fillCustomChecklistSelect(select, checklists) {
		if (!select) {
			return;
		}

		var items = (checklists || []).map(function (wf) {
			return {
				id: wf.id,
				title: wf.title + ' (' + formatStepsCount(getCustomChecklistStepCount(wf)) + ')'
			};
		});

		fillSelect(select, items, 'id', 'title', strings.pushChecklistSelect || 'Select checklist…');
		select.value = currentChecklistId ? String(currentChecklistId) : '';
	}

	function refreshCustomChecklistUi(forceRefresh) {
		return fetchCustomChecklistSummaries(forceRefresh).then(function (checklists) {
			fillCustomChecklistSelect(document.getElementById('launchdek-checklist-select'), checklists);
			fillSelect(
				document.getElementById('launchdek-vault-checklist'),
				checklists,
				'id',
				'title',
				strings.pushChecklistSelect || 'Select checklist…'
			);
			return checklists;
		});
	}

	function loadChecklistList() {
		refreshCustomChecklistUi();
	}

	function loadChecklistEditor(id) {
		currentChecklistId = id;
		get('/checklists/' + id).then(function (wf) {
			document.getElementById('launchdek-checklist-editor').hidden = false;
			document.getElementById('launchdek-cl-title').value = wf.title;
			document.getElementById('launchdek-cl-description').value = wf.description || '';
			currentSteps = hydrateStepDeepLinks(wf.steps || []);
			selectedStepIndex = null;
			renderSteps();
			renderStepConfig();
			renderChecklistPreview();
			updateChecklistActions();
			loadChecklistList();
		});
	}

	function newChecklist() {
		currentChecklistId = null;
		currentSteps = [];
		selectedStepIndex = null;
		var select = document.getElementById('launchdek-checklist-select');
		if (select) {
			select.value = '';
		}
		document.getElementById('launchdek-checklist-editor').hidden = false;
		document.getElementById('launchdek-cl-title').value = '';
		document.getElementById('launchdek-cl-description').value = '';
		renderSteps();
		renderStepConfig();
		renderChecklistPreview();
		updateChecklistActions();
		document.getElementById('launchdek-cl-title').focus();
	}

	function getClientPreviewConfig() {
		return launchdekAdmin.clientPreview || {};
	}

	function getClientPreviewStrings() {
		var config = getClientPreviewConfig();
		return config.strings || {};
	}

	function getDefaultClientPanelTitle() {
		var config = getClientPreviewConfig();
		return config.defaultPanelTitle || 'Agency Checklist';
	}

	function getClientPanelTitle() {
		var input = document.getElementById('launchdek-cl-panel-title');
		if (input) {
			var value = input.value.trim();
			if (value) {
				return value.length > 80 ? value.slice(0, 80) : value;
			}
		}

		var config = getClientPreviewConfig();
		return config.panelTitle || getDefaultClientPanelTitle();
	}

	var clientPanelTitleSaveTimer = null;
	var clientPanelTitleSavePending = false;

	function scheduleClientPanelTitleSave() {
		if (clientPanelTitleSaveTimer) {
			clearTimeout(clientPanelTitleSaveTimer);
		}
		clientPanelTitleSaveTimer = setTimeout(saveClientPanelTitle, 600);
	}

	function saveClientPanelTitle() {
		var input = document.getElementById('launchdek-cl-panel-title');
		if (!input || clientPanelTitleSavePending) {
			return;
		}

		var title = getClientPanelTitle();
		if (input.value !== title) {
			input.value = title;
		}

		var config = getClientPreviewConfig();
		if (title === (config.panelTitle || getDefaultClientPanelTitle())) {
			return;
		}

		clientPanelTitleSavePending = true;
		post('/settings/client-panel-title', { title: title })
			.then(function (data) {
				config.panelTitle = data.title || title;
				clientPanelTitleSavePending = false;
			})
			.catch(function () {
				clientPanelTitleSavePending = false;
			});
	}

	function renderClientPreviewChevron(expanded) {
		var path = expanded ? 'M2 6.5 5 3.5 8 6.5z' : 'M2 3.5 5 6.5 8 3.5z';
		return '<svg class="launchdek-client-step-chevron" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path fill="currentColor" d="' + path + '"/></svg>';
	}

	function renderClientPreviewTickIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
	}

	function renderClientPreviewNoteIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
	}

	function renderClientPreviewAttachIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
	}

	function buildClientPreviewSteps(activeIndex) {
		return currentSteps.map(function (step, index) {
			var isManual = !step.type || step.type === 'manual';
			var status = 'pending';

			if (index === activeIndex) {
				status = isManual ? 'awaiting_manual' : 'running';
			}

			return {
				step_index: index,
				title: step.title || ('Step ' + (index + 1)),
				type: step.type || 'manual',
				status: status,
				instructions: step.instructions || '',
				deep_link: (step.deep_link || '').trim() ? '#' : '',
				show_note_field: step.show_note_field !== false,
				show_screenshot_field: step.show_screenshot_field !== false && step.show_note_field !== false,
				can_complete: isManual,
				notes: []
			};
		});
	}

	function previewStepShowsNoteField(step) {
		return step.show_note_field !== false;
	}

	function previewStepShowsScreenshotField(step) {
		return step.show_screenshot_field !== false && previewStepShowsNoteField(step);
	}

	function previewStepIsManual(step) {
		return step.type === 'manual' || !step.type;
	}

	function previewStepIsUpcoming(step, index, activeIndex) {
		if (step.status === 'completed' || step.status === 'failed') {
			return false;
		}
		return index > activeIndex;
	}

	function previewStepCanComplete(step, index, activeIndex) {
		if (index !== activeIndex) {
			return false;
		}
		if (!previewStepIsManual(step) || !step.can_complete) {
			return false;
		}
		return step.status === 'pending' || step.status === 'awaiting_manual' || step.status === 'running';
	}

	function previewStatusLabel(status, previewStrings) {
		if (status === 'failed') {
			return strings.error || 'Failed';
		}
		if (status === 'running') {
			return previewStrings.waiting || 'Waiting on agency';
		}
		return '';
	}

	function renderClientPreviewSettingsLink(step, previewStrings) {
		if (!step.deep_link) {
			return '';
		}

		return '<a class="launchdek-client-step-settings-link" href="#">' + escHtml(previewStrings.goToSettings || 'Go to settings') + '</a>';
	}

	function renderClientPreviewNoteForm(step, index, activeIndex, previewStrings) {
		if (!previewStepShowsNoteField(step) || !previewStepCanComplete(step, index, activeIndex)) {
			return '';
		}

		var addLabel = previewStrings.addNotes || 'Add notes';
		var settingsLink = renderClientPreviewSettingsLink(step, previewStrings);
		var settingsHtml = settingsLink
			? '<div class="launchdek-client-note-form-settings">' + settingsLink + '</div>'
			: '';
		var screenshotBtn = previewStepShowsScreenshotField(step)
			? '<button type="button" class="launchdek-client-icon-btn launchdek-client-has-tooltip launchdek-client-attach-screenshot is-attach-action" data-tooltip="' + escHtml(previewStrings.attachScreenshot || 'Attach screenshot') + '" aria-label="' + escHtml(previewStrings.attachScreenshot || 'Attach screenshot') + '">' + renderClientPreviewAttachIcon() + '</button>'
			: '';

		return '<div class="launchdek-client-note-form" data-step="' + escHtml(index) + '">' +
			settingsHtml +
			'<div class="launchdek-client-note-form-actions">' +
				'<button type="button" class="launchdek-client-icon-btn launchdek-client-has-tooltip launchdek-client-note-action is-note-action" data-tooltip="' + escHtml(addLabel) + '" aria-label="' + escHtml(addLabel) + '">' + renderClientPreviewNoteIcon() + '</button>' +
				screenshotBtn +
			'</div>' +
			'<div class="launchdek-client-note-composer">' +
				'<div class="launchdek-client-note-composer-inner">' +
					'<textarea class="launchdek-client-note-input" rows="2" placeholder="' + escHtml(previewStrings.notePlaceholder || 'Add a note about this step…') + '" readonly></textarea>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function renderClientPreviewStepBody(step, index, activeIndex, previewStrings) {
		var body = '';

		if (step.instructions) {
			body += '<p class="launchdek-client-step-instructions">' + escHtml(step.instructions) + '</p>';
		}
		if (step.deep_link && (!previewStepShowsNoteField(step) || !previewStepCanComplete(step, index, activeIndex))) {
			body += '<div class="launchdek-client-step-settings">' + renderClientPreviewSettingsLink(step, previewStrings) + '</div>';
		}
		body += renderClientPreviewNoteForm(step, index, activeIndex, previewStrings);

		return body;
	}

	function renderClientPreviewStep(step, index, activeIndex, previewStrings) {
		var expanded = index === activeIndex;
		var upcoming = previewStepIsUpcoming(step, index, activeIndex);
		var classes = ['launchdek-client-step'];

		if (step.status === 'awaiting_manual' && !upcoming) {
			classes.push('is-active');
		}
		if (index === activeIndex) {
			classes.push('is-current');
		}
		if (upcoming) {
			classes.push('is-upcoming');
		}
		if (!expanded) {
			classes.push('is-collapsed');
		}

		var completeButton = '';
		if (previewStepCanComplete(step, index, activeIndex)) {
			var markLabel = previewStrings.markComplete || 'Mark complete';
			completeButton = '<button type="button" class="launchdek-client-icon-btn launchdek-client-has-tooltip launchdek-client-complete-step is-complete-action" data-tooltip="' + escHtml(markLabel) + '" aria-label="' + escHtml(markLabel) + '">' + renderClientPreviewTickIcon() + '</button>';
		}

		var badgeLabel = previewStatusLabel(step.status, previewStrings);
		var badgeHtml = badgeLabel
			? '<span class="launchdek-client-step-badge ' + escHtml(step.status === 'pending' ? 'awaiting_manual' : step.status) + '">' + escHtml(badgeLabel) + '</span>'
			: '';
		var body = expanded ? renderClientPreviewStepBody(step, index, activeIndex, previewStrings) : '';
		var toggleLabel = previewStrings.toggleStep || 'Toggle step details';

		return '<li class="' + classes.join(' ') + '" data-step-index="' + escHtml(index) + '"' + (upcoming ? ' aria-disabled="true"' : '') + '>' +
			'<div class="launchdek-client-step-head">' +
				'<button type="button" class="launchdek-client-step-toggle" aria-expanded="' + (expanded ? 'true' : 'false') + '" aria-label="' + escHtml(toggleLabel) + '">' +
					renderClientPreviewChevron(expanded) +
				'</button>' +
				'<h3 class="launchdek-client-step-title">' + escHtml(step.title) + '</h3>' +
				'<div class="launchdek-client-step-head-actions">' +
					badgeHtml +
					completeButton +
				'</div>' +
			'</div>' +
			(body ? '<div class="launchdek-client-step-body">' + body + '</div>' : '') +
		'</li>';
	}

	function renderClientPreviewPanel(checklistTitle, steps, activeIndex) {
		var previewStrings = getClientPreviewStrings();
		var panelTitle = getClientPanelTitle();
		var stepList = steps.map(function (step, index) {
			return renderClientPreviewStep(step, index, activeIndex, previewStrings);
		}).join('');

		return '<div class="launchdek-checklist-preview-shell">' +
			'<div class="launchdek-client-panel-root launchdek-client-layout-sidebar">' +
				'<aside class="launchdek-client-panel" aria-label="' + escHtml(panelTitle) + '">' +
					'<div class="launchdek-client-panel-header">' +
						'<div>' +
							'<h2 class="launchdek-client-panel-title">' + escHtml(panelTitle) + '</h2>' +
							'<p class="launchdek-client-panel-subtitle">' + escHtml(checklistTitle) + '</p>' +
						'</div>' +
						'<button type="button" class="button button-small launchdek-client-panel-toggle" disabled>' + escHtml(previewStrings.collapse || 'Collapse') + '</button>' +
					'</div>' +
					'<div class="launchdek-client-panel-body">' +
						'<ol class="launchdek-client-step-list">' + stepList + '</ol>' +
					'</div>' +
				'</aside>' +
			'</div>' +
		'</div>';
	}

	function renderChecklistPreview() {
		var box = document.getElementById('launchdek-checklist-preview');
		if (!box) {
			return;
		}

		var titleInput = document.getElementById('launchdek-cl-title');
		var title = titleInput ? titleInput.value.trim() : '';
		var previewStrings = getClientPreviewStrings();

		if (!title && !currentSteps.length) {
			box.innerHTML = '<p class="launchdek-muted">' + escHtml(previewStrings.previewEmpty || strings.onboardingPreviewEmpty || 'Add a title and steps to preview the client panel.') + '</p>';
			return;
		}

		var activeIndex = selectedStepIndex !== null ? selectedStepIndex : 0;
		if (!currentSteps.length) {
			activeIndex = 0;
		} else if (activeIndex >= currentSteps.length) {
			activeIndex = currentSteps.length - 1;
		}

		var checklistTitle = title || strings.checklistTitleRequired || 'Checklist';
		var steps = buildClientPreviewSteps(activeIndex);

		box.innerHTML = renderClientPreviewPanel(checklistTitle, steps, activeIndex);
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
			renderChecklistPreview();
			return;
		}

		var flow = el('div', { className: 'launchdek-canvas-flow' });
		currentSteps.forEach(function (step, i) {
			if (i > 0) {
				flow.appendChild(el('div', { className: 'launchdek-canvas-connector', 'aria-hidden': 'true', text: '↓' }));
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
		renderChecklistPreview();
	}

	function renderFieldInfo(tooltipText, extraClass) {
		if (!tooltipText) {
			return '';
		}
		var infoClass = 'launchdek-field-info launchdek-has-tooltip';
		if (extraClass) {
			infoClass += ' ' + extraClass;
		}
		return '<button type="button" class="' + infoClass + '" data-tooltip="' + escAttr(tooltipText) + '" aria-label="' + escAttr(tooltipText) + '"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></button>';
	}

	function renderFieldLabel(text, tooltipText, infoClass) {
		return '<span class="launchdek-field-label-row">' + renderFieldInfo(tooltipText, infoClass) + '<span>' + escHtml(text) + '</span></span>';
	}

	function getStepTypeHelp(type) {
		if (type === 'api') {
			return strings.stepTypeApiHelp || 'API steps run automatically when the checklist reaches this step. LaunchDek sends an authenticated REST request to the remote WordPress site using its saved Application Password—no client panel action required.';
		}
		if (type === 'manual') {
			return strings.stepTypeManualHelp || 'Manual steps pause the run until someone marks them complete in the hub run tracker or the client checklist panel. Use for tasks that need human verification.';
		}
		return strings.stepTypeHelp || 'Choose how this step completes during a run. Manual steps wait for a person; API steps run automatically on the remote site.';
	}

	function updateStepTypeHelpTooltip(type) {
		var helpText = getStepTypeHelp(type);
		var infoBtn = document.querySelector('#launchdek-step-config .launchdek-step-type-help');
		if (!infoBtn) {
			return;
		}
		infoBtn.setAttribute('data-tooltip', helpText);
		infoBtn.setAttribute('aria-label', helpText);
	}

	function getDeepLinkInferenceRules() {
		return [
			{ path: 'options-permalink.php', patterns: [/settings\s*(?:→|>|\/|-)\s*permalinks?/i, /\bpermalinks?\b/i, /\bpost name\b/i, /\/%postname%/i, /\brewrite rules?\b/i] },
			{ path: 'options-reading.php', patterns: [/settings\s*(?:→|>|\/|-)\s*reading/i, /\bsearch engine visibility\b/i, /\bhomepage displays\b/i, /\bposts page\b/i, /\bblog public\b/i] },
			{ path: 'options-discussion.php', patterns: [/settings\s*(?:→|>|\/|-)\s*discussion/i, /\bcomment settings\b/i, /\bdefault comment status\b/i] },
			{ path: 'options-media.php', patterns: [/settings\s*(?:→|>|\/|-)\s*media/i, /\bmedia settings\b/i, /\bthumbnail size\b/i] },
			{ path: 'options-privacy.php', patterns: [/settings\s*(?:→|>|\/|-)\s*privacy/i, /\bprivacy policy page\b/i] },
			{ path: 'options-general.php', patterns: [/settings\s*(?:→|>|\/|-)\s*general/i, /\bsite title\b/i, /\btagline\b/i, /\badmin(?:istration)? email\b/i, /\btimezone\b/i, /\bdate format\b/i, /\btime format\b/i, /\bsite language\b/i, /\bwp_lang\b/i] },
			{ path: 'plugins.php', patterns: [/\bplugins?\b/i, /\bactivate (?:the )?plugin\b/i, /\bdeactivate (?:the )?plugin\b/i] },
			{ path: 'plugin-install.php', patterns: [/\binstall (?:a )?plugin\b/i, /\badd new plugin\b/i, /\bplugin install\b/i] },
			{ path: 'users.php', patterns: [/\busers?\b/i, /\buser roles?\b/i, /\badmin user\b/i] },
			{ path: 'profile.php', patterns: [/\bprofile\b/i, /\baccount settings\b/i] },
			{ path: 'upload.php', patterns: [/\bmedia library\b/i, /\bupload(?:s|ed)? media\b/i, /\bbroken images?\b/i] },
			{ path: 'nav-menus.php', patterns: [/\bnav(?:igation)? menus?\b/i, /\bmenu items?\b/i] },
			{ path: 'edit-comments.php', patterns: [/\bcomments?\b/i, /\bcomment spam\b/i, /\bmoderate comments\b/i] },
			{ path: 'themes.php', patterns: [/\bthemes?\b/i, /\bswitch theme\b/i] },
			{ path: 'customize.php', patterns: [/\bcustomizer\b/i, /\bcustomize\b/i, /\bsite identity\b/i] },
			{ path: 'site-health.php', patterns: [/\bsite health\b/i] },
			{ path: 'update-core.php', patterns: [/\bupdate core\b/i, /\bwordpress updates?\b/i, /\bcore updates?\b/i] },
			{ path: 'edit.php', patterns: [/\b(?:edit|manage|create|add|new) (?:blog )?posts?\b/i, /\bposts? list\b/i] },
			{ path: 'edit.php?post_type=page', patterns: [/\b(?:edit|manage|create|add|new) pages?\b/i, /\bpages? list\b/i] }
		];
	}

	function inferDeepLinkFromText(title, instructions) {
		var haystack = String(title || '') + ' ' + String(instructions || '');
		haystack = haystack.replace(/<[^>]*>/g, '').toLowerCase();
		if (!haystack.trim()) {
			return '';
		}

		var rules = getDeepLinkInferenceRules();
		for (var i = 0; i < rules.length; i++) {
			for (var j = 0; j < rules[i].patterns.length; j++) {
				if (rules[i].patterns[j].test(haystack)) {
					return rules[i].path;
				}
			}
		}
		return '';
	}

	function inferDeepLinkFromApiStep(api) {
		if (!api || !api.route) {
			return '';
		}

		var route = '/' + String(api.route).replace(/^\/+/, '');
		if (route.indexOf('/wp-json') === 0) {
			route = route.substring('/wp-json'.length);
		}
		route = '/' + route.replace(/^\/+/, '');

		if (route.indexOf('/wp/v2/settings') === 0) {
			return inferDeepLinkFromText('', JSON.stringify(api.payload || {}));
		}

		var routeMap = [
			{ prefix: '/wp/v2/plugins', path: 'plugins.php' },
			{ prefix: '/wp/v2/users', path: 'users.php' },
			{ prefix: '/wp/v2/media', path: 'upload.php' },
			{ prefix: '/wp/v2/comments', path: 'edit-comments.php' },
			{ prefix: '/wp/v2/themes', path: 'themes.php' },
			{ prefix: '/wp/v2/navigation', path: 'nav-menus.php' },
			{ prefix: '/wp/v2/menu-items', path: 'nav-menus.php' },
			{ prefix: '/wp/v2/pages', path: 'edit.php?post_type=page' },
			{ prefix: '/wp/v2/posts', path: 'edit.php' }
		];

		for (var i = 0; i < routeMap.length; i++) {
			if (route.indexOf(routeMap[i].prefix) === 0) {
				return routeMap[i].path;
			}
		}

		return '';
	}

	function inferDeepLinkFromStepData(payload) {
		var path = inferDeepLinkFromText(payload.title, payload.instructions);
		if (payload.type === 'api' && payload.api) {
			path = inferDeepLinkFromApiStep(payload.api) || path;
		}
		return path;
	}

	function hydrateStepDeepLinks(steps) {
		return (steps || []).map(function (step) {
			if (!step || step.deep_link_manual || (step.deep_link && String(step.deep_link).trim())) {
				return step;
			}
			var inferred = inferDeepLinkFromStepData({
				title: step.title || '',
				instructions: step.instructions || '',
				type: step.type || 'manual',
				api: step.api || {}
			});
			if (inferred) {
				step.deep_link = inferred;
				step.deep_link_auto = true;
			}
			return step;
		});
	}

	function buildDeepLinkInferencePayload(step) {
		var titleInput = document.getElementById('ld-step-title');
		var instructionsInput = document.getElementById('ld-step-instructions');
		var typeInput = document.getElementById('ld-step-type');
		var payload = {
			title: titleInput ? titleInput.value : (step.title || ''),
			instructions: instructionsInput ? instructionsInput.value : (step.instructions || ''),
			type: typeInput ? typeInput.value : (step.type || 'manual'),
			api: step.api || {}
		};

		if (payload.type === 'api') {
			var methodEl = document.getElementById('ld-api-method');
			var routeEl = document.getElementById('ld-api-route');
			var payloadEl = document.getElementById('ld-api-payload');
			var apiPayload = {};
			try {
				apiPayload = JSON.parse(payloadEl ? payloadEl.value || '{}' : '{}');
			} catch (e) {}
			payload.api = {
				method: methodEl ? methodEl.value : 'GET',
				route: routeEl ? routeEl.value : '',
				payload: apiPayload
			};
		}

		return payload;
	}

	function applyInferredDeepLink(path) {
		if (selectedStepIndex === null) {
			return;
		}

		var current = currentSteps[selectedStepIndex];
		if (!current || current.deep_link_manual) {
			return;
		}

		var deeplinkInput = document.getElementById('ld-step-deeplink');
		var existing = (current.deep_link || '').trim();
		path = path ? String(path).trim() : '';

		if (!path) {
			if (current.deep_link_auto) {
				current.deep_link = '';
				current.deep_link_auto = false;
				if (deeplinkInput) {
					deeplinkInput.value = '';
					deeplinkInput.removeAttribute('data-auto-filled');
				}
				setDeepLinkHintVisible(false);
				renderSteps();
			}
			return;
		}

		if (!existing || current.deep_link_auto) {
			current.deep_link = path;
			current.deep_link_auto = true;
			if (deeplinkInput) {
				deeplinkInput.value = path;
				deeplinkInput.setAttribute('data-auto-filled', '1');
			}
			setDeepLinkHintVisible(true);
			renderSteps();
			return;
		}

		if (path === existing) {
			current.deep_link_auto = true;
			if (deeplinkInput) {
				deeplinkInput.setAttribute('data-auto-filled', '1');
			}
			setDeepLinkHintVisible(true);
		}
	}

	function formatRoleMappingSummary(selectedSlugs) {
		var roles = launchdekAdmin.roles || {};
		if (!selectedSlugs || !selectedSlugs.length) {
			return strings.allRoles || 'All roles';
		}

		var labels = selectedSlugs.map(function (slug) {
			return roles[slug] || slug;
		});

		if (labels.length <= 2) {
			return labels.join(', ');
		}

		return (strings.rolesSelected || '%d roles selected').replace('%d', String(labels.length));
	}

	function updateRoleMappingSummary(container) {
		if (!container) {
			return;
		}

		var summary = container.querySelector('.launchdek-multicheck-summary');
		if (!summary) {
			return;
		}

		var selected = [];
		container.querySelectorAll('.ld-target-role:checked').forEach(function (input) {
			selected.push(input.value);
		});
		summary.textContent = formatRoleMappingSummary(selected);
	}

	function closeMulticheckDropdowns(except) {
		document.querySelectorAll('.launchdek-multicheck-dropdown.is-open').forEach(function (dropdown) {
			if (except && dropdown === except) {
				return;
			}
			dropdown.classList.remove('is-open');
			var toggle = dropdown.querySelector('.launchdek-multicheck-toggle');
			var menu = dropdown.querySelector('.launchdek-multicheck-menu');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
			if (menu) {
				menu.hidden = true;
			}
		});
	}

	function bindMulticheckDropdown(container, options) {
		if (!container) {
			return;
		}

		options = options || {};
		var checkboxSelector = options.checkboxSelector || '.ld-target-role';
		var updateSummary = options.updateSummary || updateRoleMappingSummary;

		var toggle = container.querySelector('.launchdek-multicheck-toggle');
		var menu = container.querySelector('.launchdek-multicheck-menu');
		if (!toggle || !menu) {
			return;
		}

		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var open = container.classList.contains('is-open');
			closeMulticheckDropdowns(container);
			if (!open) {
				container.classList.add('is-open');
				menu.hidden = false;
				toggle.setAttribute('aria-expanded', 'true');
			}
		});

		container.querySelectorAll(checkboxSelector).forEach(function (input) {
			input.addEventListener('change', function () {
				updateSummary(container);
				if (options.onChange) {
					options.onChange();
				} else if (checkboxSelector === '.ld-target-role') {
					saveStepFromForm();
				}
			});
		});

		updateSummary(container);
	}

	function ensureMulticheckDropdownCloseHandler() {
		if (multicheckDropdownBound) {
			return;
		}
		multicheckDropdownBound = true;
		document.addEventListener('click', function (e) {
			if (!e.target.closest('.launchdek-multicheck-dropdown')) {
				closeMulticheckDropdowns();
			}
		});
	}

	function renderRoleMapping(step) {
		var roles = launchdekAdmin.roles || {};
		var selected = step.target_roles || [];
		var summary = formatRoleMappingSummary(selected);
		var html = '<div class="launchdek-multicheck-dropdown" id="ld-step-role-mapping">';
		html += '<span class="launchdek-multicheck-label launchdek-field-label-row">' + renderFieldInfo(strings.roleTargetMappingHelp || 'Limit which WordPress roles can complete this step on the client panel. Leave empty to allow all logged-in users.') + '<span>' + escHtml(strings.roleTargetMapping || 'Role Target Mapping') + '</span></span>';
		html += '<button type="button" class="launchdek-multicheck-toggle" aria-haspopup="listbox" aria-expanded="false">';
		html += '<span class="launchdek-multicheck-summary">' + escHtml(summary) + '</span>';
		html += '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
		html += '</button>';
		html += '<div class="launchdek-multicheck-menu" role="listbox" hidden>';
		Object.keys(roles).forEach(function (slug) {
			var checked = selected.indexOf(slug) >= 0 ? ' checked' : '';
			html += '<label class="launchdek-multicheck-option" role="option"><input type="checkbox" class="ld-target-role" value="' + escAttr(slug) + '"' + checked + ' /> ' + escHtml(roles[slug]) + '</label>';
		});
		html += '</div></div>';
		return html;
	}

	function scheduleDeepLinkInference() {
		if (selectedStepIndex === null) {
			return;
		}
		if (deepLinkInferTimer) {
			clearTimeout(deepLinkInferTimer);
		}
		deepLinkInferTimer = setTimeout(runDeepLinkInference, 300);
	}

	function setDeepLinkHintVisible(visible) {
		var hint = document.getElementById('ld-step-deeplink-hint');
		if (hint) {
			hint.hidden = !visible;
		}
	}

	function runDeepLinkInference() {
		if (selectedStepIndex === null) {
			return;
		}

		var step = currentSteps[selectedStepIndex];
		if (step.deep_link_manual) {
			return;
		}

		var payload = buildDeepLinkInferencePayload(step);
		applyInferredDeepLink(inferDeepLinkFromStepData(payload));

		post('/checklists/infer-deep-link', payload).then(function (response) {
			if (selectedStepIndex === null) {
				return;
			}
			applyInferredDeepLink(response && response.path ? String(response.path) : '');
		}).catch(function () {});
	}

	function renderStepConfig() {
		var box = document.getElementById('launchdek-step-config');
		if (selectedStepIndex === null || !currentSteps[selectedStepIndex]) {
			box.innerHTML = '<p class="launchdek-muted">Select a step on the canvas to configure.</p>';
			renderChecklistPreview();
			return;
		}
		var step = currentSteps[selectedStepIndex];
		var showNoteField = step.show_note_field !== false;
		var showScreenshotField = step.show_screenshot_field !== false;
		var showDeepLinkHint = !!step.deep_link_auto;
		box.innerHTML =
			'<label>' + renderFieldLabel('Step Title', '') + '<input type="text" id="ld-step-title" value="' + escAttr(step.title || '') + '" /></label>' +
			'<label>' + renderFieldLabel('Instructions', '') + '<textarea id="ld-step-instructions" rows="3">' + escHtml(step.instructions || '') + '</textarea></label>' +
			renderRoleMapping(step) +
			'<label>' + renderFieldLabel('Deep Link (admin path)', strings.deepLinkHelp || 'Optional wp-admin path (e.g. options-permalink.php). LaunchDek auto-fills this from the step title, instructions, or API route when possible.') + '<input type="text" id="ld-step-deeplink" value="' + escAttr(step.deep_link || '') + '" placeholder="options-permalink.php"' + (showDeepLinkHint ? ' data-auto-filled="1"' : '') + ' /><span class="launchdek-muted launchdek-deeplink-hint" id="ld-step-deeplink-hint"' + (showDeepLinkHint ? '' : ' hidden') + '>' + escHtml(strings.deepLinkAuto || 'Auto-detected from step content.') + '</span></label>' +
			'<label>' + renderFieldLabel('Step Type', getStepTypeHelp(step.type || 'manual'), 'launchdek-step-type-help') + '<select id="ld-step-type"><option value="manual"' + (step.type === 'manual' ? ' selected' : '') + '>Manual</option><option value="api"' + (step.type === 'api' ? ' selected' : '') + '>API</option></select></label>' +
			'<div class="launchdek-step-client-options" id="ld-step-client-options">' +
			'<label class="launchdek-step-note-field-option" id="ld-step-show-note-wrap"><input type="checkbox" id="ld-step-show-note"' + (showNoteField ? ' checked' : '') + ' /><span class="launchdek-step-option-label">' + renderFieldInfo(strings.showNoteFieldHelp || 'When enabled, clients can add text notes as evidence when completing this manual step.') + escHtml(strings.showNoteField || 'Show note field on client panel') + '</span></label>' +
			'<label class="launchdek-step-note-field-option" id="ld-step-show-screenshot-wrap"><input type="checkbox" id="ld-step-show-screenshot"' + (showScreenshotField ? ' checked' : '') + ' /><span class="launchdek-step-option-label">' + renderFieldInfo(strings.showScreenshotFieldHelp || 'When enabled, clients can attach a screenshot from the media library when saving a step note.') + escHtml(strings.showScreenshotField || 'Allow screenshot attachment on client panel') + '</span></label>' +
			'</div>' +
			'<div id="ld-api-config"' + (step.type === 'api' ? '' : ' style="display:none"') + '>' +
			'<div class="launchdek-card-heading-row launchdek-config-subheading-row"><h4 class="launchdek-config-subheading">' + escHtml('API Payload Mapper') + '</h4>' + renderFieldInfo(strings.apiMapperHelp || 'Configure the REST call LaunchDek makes on the client site. Use Validate API Step to dry-run checks before saving. Fields listed under Settings → Exclude Options are stripped from /wp/v2/settings payloads.') + '</div>' +
			'<label>' + renderFieldLabel('HTTP Method', strings.apiMethodHelp || 'HTTP verb for the request. GET reads data without changes. POST, PUT, PATCH, and DELETE send the JSON payload to create, update, or remove resources.') + '<select id="ld-api-method"><option' + sel(step.api && step.api.method, 'GET') + '>GET</option><option' + sel(step.api && step.api.method, 'POST') + '>POST</option><option' + sel(step.api && step.api.method, 'PUT') + '>PUT</option><option' + sel(step.api && step.api.method, 'PATCH') + '>PATCH</option><option' + sel(step.api && step.api.method, 'DELETE') + '>DELETE</option></select></label>' +
			'<label>' + renderFieldLabel('REST Route', strings.apiRouteHelp || 'WordPress REST API path on the remote site. Must start with / (for example, /wp/v2/settings for site options).') + '<input type="text" id="ld-api-route" value="' + escAttr((step.api && step.api.route) || '') + '" placeholder="/wp/v2/settings" /></label>' +
			'<label>' + renderFieldLabel('JSON Payload', strings.apiPayloadHelp || 'Request body for mutating methods. For /wp/v2/settings, use WordPress setting field names (for example, {"title":"My Site"}). Use {} for GET requests.') + '<textarea id="ld-api-payload" rows="4">' + escHtml(JSON.stringify((step.api && step.api.payload) || {}, null, 2)) + '</textarea></label>' +
			'<button type="button" class="button" id="ld-validate-api">Validate API Step</button>' +
			'</div>' +
			'<button type="button" class="button" id="ld-remove-step" style="margin-top:8px">Remove Step</button>';

		function syncStepTypeFields() {
			var stepType = document.getElementById('ld-step-type').value;
			var isManual = stepType === 'manual';
			document.getElementById('ld-api-config').style.display = isManual ? 'none' : '';
			var clientOptions = document.getElementById('ld-step-client-options');
			if (clientOptions) {
				clientOptions.style.display = isManual ? '' : 'none';
			}
			updateStepTypeHelpTooltip(stepType);
		}

		function syncScreenshotFieldState() {
			var noteInput = document.getElementById('ld-step-show-note');
			var screenshotWrap = document.getElementById('ld-step-show-screenshot-wrap');
			var screenshotInput = document.getElementById('ld-step-show-screenshot');
			if (!noteInput || !screenshotWrap || !screenshotInput) {
				return;
			}
			var notesEnabled = noteInput.checked;
			screenshotWrap.style.display = notesEnabled ? '' : 'none';
			if (!notesEnabled) {
				screenshotInput.checked = false;
			}
		}

		document.getElementById('ld-step-type').onchange = function () {
			syncStepTypeFields();
			saveStepFromForm();
			scheduleDeepLinkInference();
		};
		syncStepTypeFields();
		syncScreenshotFieldState();
		['ld-step-title', 'ld-step-instructions'].forEach(function (id) {
			var node = document.getElementById(id);
			if (!node) {
				return;
			}
			node.addEventListener('input', function () {
				saveStepFromForm();
				scheduleDeepLinkInference();
			});
		});
		['ld-api-method', 'ld-api-route', 'ld-api-payload'].forEach(function (id) {
			var node = document.getElementById(id);
			if (!node) {
				return;
			}
			node.addEventListener('change', function () {
				saveStepFromForm();
				scheduleDeepLinkInference();
			});
		});
		var deeplinkInput = document.getElementById('ld-step-deeplink');
		if (deeplinkInput) {
			deeplinkInput.addEventListener('input', function () {
				var value = deeplinkInput.value.trim();
				step.deep_link_manual = value !== '';
				step.deep_link_auto = false;
				if (!value) {
					step.deep_link_manual = false;
					deeplinkInput.removeAttribute('data-auto-filled');
					setDeepLinkHintVisible(false);
					scheduleDeepLinkInference();
				} else {
					deeplinkInput.removeAttribute('data-auto-filled');
					setDeepLinkHintVisible(false);
				}
				saveStepFromForm();
			});
		}
		ensureMulticheckDropdownCloseHandler();
		bindMulticheckDropdown(document.getElementById('ld-step-role-mapping'));
		var showNoteInput = document.getElementById('ld-step-show-note');
		if (showNoteInput) {
			showNoteInput.onchange = function () {
				syncScreenshotFieldState();
				saveStepFromForm();
			};
		}
		var showScreenshotInput = document.getElementById('ld-step-show-screenshot');
		if (showScreenshotInput) {
			showScreenshotInput.onchange = saveStepFromForm;
		}
		runDeepLinkInference();
		document.getElementById('ld-remove-step').onclick = function () {
			currentSteps.splice(selectedStepIndex, 1);
			selectedStepIndex = null;
			renderSteps();
			renderStepConfig();
		};
		document.getElementById('ld-validate-api').onclick = function () {
			saveStepFromForm();
			post('/checklists/0/validate-step', currentSteps[selectedStepIndex]).then(function (r) {
				if (!r.valid) {
					alert(r.errors.join('\n'));
					return;
				}
				var message = 'API step is valid.';
				if (r.warnings && r.warnings.length) {
					message += '\n\n' + r.warnings.join('\n');
				}
				alert(message);
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
			step.show_screenshot_field = false;
		} else {
			var showNoteInput = document.getElementById('ld-step-show-note');
			var showScreenshotInput = document.getElementById('ld-step-show-screenshot');
			step.show_note_field = showNoteInput ? showNoteInput.checked : true;
			step.show_screenshot_field = showScreenshotInput && step.show_note_field ? showScreenshotInput.checked : false;
		}
		renderSteps();
	}

	function normalizeChecklistTabId(tabId) {
		if (tabId === 'builder') {
			return 'my-checklists';
		}
		if (tabId === 'new-checklist') {
			return 'templates';
		}
		return tabId;
	}

	function initChecklistTabs() {
		var panels = document.querySelectorAll('[data-launchdek-tab-panel]');
		var tabBtns = document.querySelectorAll('[data-launchdek-tab]');
		if (!panels.length || !tabBtns.length) return null;

		function showTab(tabId, updateUrl) {
			tabId = normalizeChecklistTabId(tabId);
			panels.forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-launchdek-tab-panel') !== tabId;
			});
			tabBtns.forEach(function (btn) {
				var active = btn.getAttribute('data-launchdek-tab') === tabId;
				btn.classList.toggle('nav-tab-active', active);
				btn.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			if (tabId === 'auto-capture') {
				initAutoCapturePanel();
			}
			if (updateUrl && window.history && window.history.replaceState) {
				var url = new URL(window.location.href);
				if (tabId === 'templates') {
					url.searchParams.delete('tab');
				} else {
					url.searchParams.set('tab', tabId);
				}
				if (tabId !== 'my-checklists') {
					url.searchParams.delete('checklist_id');
				}
				window.history.replaceState(null, '', url.toString());
			}
		}

		tabBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				showTab(btn.getAttribute('data-launchdek-tab'), true);
			});
		});

		return showTab;
	}

	function initChecklists() {
		if (!document.querySelector('[data-launchdek-page="checklists"]')) return;

		ensureMulticheckDropdownCloseHandler();
		showChecklistTab = initChecklistTabs();
		var params = new URLSearchParams(window.location.search);
		var openId = parseInt(params.get('checklist_id') || '0', 10);
		var tabParam = normalizeChecklistTabId(params.get('tab') || '');
		var initialTab = openId ? 'my-checklists' : (tabParam || 'templates');
		if (showChecklistTab) {
			showChecklistTab(initialTab, false);
		}

		loadChecklistList();
		updateChecklistActions();

		['launchdek-cl-title', 'launchdek-cl-description'].forEach(function (id) {
			var field = document.getElementById(id);
			if (field) {
				field.addEventListener('input', renderChecklistPreview);
			}
		});

		var panelTitleInput = document.getElementById('launchdek-cl-panel-title');
		if (panelTitleInput) {
			panelTitleInput.addEventListener('input', function () {
				renderChecklistPreview();
				scheduleClientPanelTitleSave();
			});
			panelTitleInput.addEventListener('blur', saveClientPanelTitle);
		}

		var checklistSelect = document.getElementById('launchdek-checklist-select');
		if (checklistSelect) {
			checklistSelect.addEventListener('change', function () {
				var id = parseInt(checklistSelect.value, 10);
				if (!id) {
					currentChecklistId = null;
					document.getElementById('launchdek-checklist-editor').hidden = true;
					updateChecklistActions();
					return;
				}
				loadChecklistEditor(id);
			});
		}

		if (openId) {
			loadChecklistEditor(openId);
		}

		var canvasStartBlankBtn = document.getElementById('launchdek-canvas-start-blank');
		if (canvasStartBlankBtn) {
			canvasStartBlankBtn.onclick = function () {
				newChecklist();
			};
		}

		document.getElementById('launchdek-add-step').onclick = function () {
			currentSteps.push({
				id: 'step_' + (currentSteps.length + 1),
				title: 'New Step',
				instructions: '',
				target_roles: [],
				deep_link: '',
				type: 'manual',
				show_note_field: true,
				show_screenshot_field: true,
				api: { method: 'GET', route: '', payload: {} }
			});
			selectedStepIndex = currentSteps.length - 1;
			renderSteps();
			renderStepConfig();
		};

		document.getElementById('launchdek-save-checklist').onclick = function () {
			var saveBtn = document.getElementById('launchdek-save-checklist');

			if (selectedStepIndex !== null) saveStepFromForm();

			var title = document.getElementById('launchdek-cl-title').value.trim();
			if (!title) {
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
				refreshCustomChecklistUi(true);
				updateChecklistActions();
			}).finally(function () {
				saveBtn.disabled = false;
			});
		};

		document.getElementById('launchdek-delete-checklist').onclick = function () {
			if (!currentChecklistId) {
				return;
			}
			openConfirmModal({
				title: strings.delete || 'Delete',
				message: strings.confirmDeleteChecklist || strings.confirmDelete || 'Are you sure you want to delete this checklist?',
				confirmText: strings.deletePermanently || strings.delete || 'Delete permanently',
				destructive: true,
				onConfirm: function () {
					return del('/checklists/' + currentChecklistId).then(function () {
						currentChecklistId = null;
						document.getElementById('launchdek-checklist-editor').hidden = true;
						refreshCustomChecklistUi(true);
						updateChecklistActions();
					});
				}
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
						post('/checklists/import', data).then(function () { refreshCustomChecklistUi(true); alert('Imported.'); });
					} catch (e) { alert('Invalid JSON file.'); }
				};
				reader.readAsText(fileInput.files[0]);
			} else if (urlInput.value) {
				post('/checklists/import', { url: urlInput.value }).then(function () { refreshCustomChecklistUi(true); alert('Imported.'); });
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

	var capturePanelInitialized = false;

	function initAutoCapturePanel() {
		if (capturePanelInitialized) {
			return;
		}

		var select = document.getElementById('launchdek-auto-capture-site');
		if (!select) {
			return;
		}

		capturePanelInitialized = true;
		notice(document.getElementById('launchdek-auto-capture-notice'), '', '');
		renderCaptureStatus({ active: false, steps: [] });

		get('/sites').then(function (sites) {
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

	function initAutoCapture() {
		if (!document.getElementById('launchdek-auto-capture-site')) {
			return;
		}

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
			if (showChecklistTab) {
				showChecklistTab('my-checklists', true);
			}
		});
	}

	// ─── Automation ──────────────────────────────────────────
	var activeRunId = null;
	var batchQueue = [];
	var cachedSites = [];
	var cachedChecklists = [];
	var showAutomationTab = null;
	var currentAutomationStep = 1;

	function formatTargetSitesSummary(selectedIds) {
		if (!selectedIds || !selectedIds.length) {
			return strings.selectTargetSites || 'Select sites…';
		}

		var labels = selectedIds.map(function (siteId) {
			var site = cachedSites.find(function (s) { return s.id === siteId; });
			return site ? (site.name || site.url) : ('Site #' + siteId);
		});

		if (labels.length <= 2) {
			return labels.join(', ');
		}

		return (strings.targetSitesSelected || '%d sites selected').replace('%d', String(labels.length));
	}

	function updateTargetSitesSummary(container) {
		if (!container) {
			return;
		}

		var summary = container.querySelector('.launchdek-multicheck-summary');
		if (!summary) {
			return;
		}

		var selected = [];
		container.querySelectorAll('.ld-target-site:checked').forEach(function (input) {
			selected.push(parseInt(input.value, 10));
		});
		summary.textContent = formatTargetSitesSummary(selected);
	}

	function getSelectedSiteIds(pickerId) {
		var picker = document.getElementById(pickerId || 'launchdek-run-site-picker');
		if (!picker) {
			return [];
		}

		var selected = [];
		picker.querySelectorAll('.ld-target-site:checked').forEach(function (input) {
			var id = parseInt(input.value, 10);
			if (id > 0) {
				selected.push(id);
			}
		});
		return selected;
	}

	function getSelectedChecklist(selectId) {
		var select = document.getElementById(selectId || 'launchdek-run-checklist');
		if (!select) {
			return null;
		}
		var wfId = parseInt(select.value, 10);
		if (!wfId) return null;
		for (var i = 0; i < cachedChecklists.length; i++) {
			if (cachedChecklists[i].id === wfId) return cachedChecklists[i];
		}
		return { id: wfId, title: 'Checklist #' + wfId };
	}

	function renderTargetSitesPicker(pickerId, preserveSelected, singleSelect) {
		var container = document.getElementById(pickerId);
		if (!container) {
			return;
		}

		var selected = preserveSelected ? getSelectedSiteIds(pickerId) : [];
		if (singleSelect && selected.length > 1) {
			selected = [selected[0]];
		}
		var summary = formatTargetSitesSummary(selected);
		var html = '<button type="button" class="launchdek-multicheck-toggle" aria-haspopup="listbox" aria-expanded="false">';
		html += '<span class="launchdek-multicheck-summary">' + escHtml(summary) + '</span>';
		html += '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
		html += '</button>';
		html += '<div class="launchdek-multicheck-menu" role="listbox" hidden>';

		if (!cachedSites.length) {
			html += '<p class="launchdek-muted launchdek-multicheck-empty">' + escHtml(strings.noSites || 'No sites registered yet.') + '</p>';
		} else {
			cachedSites.forEach(function (site) {
				var checked = selected.indexOf(site.id) >= 0 ? ' checked' : '';
				html += '<label class="launchdek-multicheck-option" role="option"><input type="checkbox" class="ld-target-site" value="' + escAttr(String(site.id)) + '"' + checked + ' /> ' + escHtml(site.name || site.url) + '</label>';
			});
		}

		html += '</div>';
		container.innerHTML = html;
		ensureMulticheckDropdownCloseHandler();
		bindMulticheckDropdown(container, {
			checkboxSelector: '.ld-target-site',
			updateSummary: updateTargetSitesSummary,
			onChange: singleSelect ? function () {
				var checked = container.querySelector('.ld-target-site:checked');
				if (checked) {
					container.querySelectorAll('.ld-target-site').forEach(function (other) {
						if (other !== checked) {
							other.checked = false;
						}
					});
					updateTargetSitesSummary(container);
				}
				updateAutomationStepButtons();
			} : null
		});
	}

	function loadRunSelects() {
		return Promise.all([get('/sites'), fetchCustomChecklistSummaries()]).then(function (r) {
			cachedSites = r[0] || [];
			cachedChecklists = r[1] || [];

			renderTargetSitesPicker('launchdek-run-site-picker', true, true);
			renderTargetSitesPicker('launchdek-batch-site-picker', true, false);

			fillSelect(document.getElementById('launchdek-drift-site'), cachedSites, 'id', 'name', 'All sites');
			fillSelect(document.getElementById('launchdek-run-checklist'), cachedChecklists, 'id', 'title', 'Select checklist…');
			fillSelect(document.getElementById('launchdek-batch-checklist'), cachedChecklists, 'id', 'title', 'Select checklist…');
		});
	}

	function runNotice(message, type) {
		notice(document.getElementById('launchdek-run-notice'), message, type);
	}

	function batchNotice(message, type) {
		notice(document.getElementById('launchdek-batch-notice'), message, type);
	}

	function updateAutomationProgress(step) {
		var progress = document.getElementById('launchdek-automation-progress');
		if (!progress) {
			return;
		}
		currentAutomationStep = step;
		progress.setAttribute('aria-valuenow', String(step));
		progress.querySelectorAll('.launchdek-automation-progress-segment').forEach(function (segment) {
			var segmentStep = parseInt(segment.getAttribute('data-step') || '0', 10);
			segment.classList.toggle('is-active', segmentStep === step);
			segment.classList.toggle('is-complete', segmentStep < step);
		});
	}

	function goToAutomationStep(step) {
		step = parseInt(step, 10) || 1;
		[1, 2, 3].forEach(function (n) {
			var panel = document.getElementById('launchdek-automation-step-' + n);
			if (panel) {
				panel.hidden = n !== step;
			}
		});
		updateAutomationProgress(step);
		updateAutomationStepButtons();
		if (step === 3 && !activeRunId) {
			renderRunEmptyState();
		}
	}

	function updateAutomationStepButtons() {
		var step1Next = document.getElementById('launchdek-automation-step1-next');
		var startRunBtn = document.getElementById('launchdek-start-run');
		var checklist = getSelectedChecklist('launchdek-run-checklist');
		var siteIds = getSelectedSiteIds('launchdek-run-site-picker');

		if (step1Next) {
			step1Next.disabled = !checklist;
		}
		if (startRunBtn) {
			startRunBtn.disabled = !checklist || siteIds.length !== 1;
		}
	}

	function canStartSingleRun() {
		var checklist = getSelectedChecklist('launchdek-run-checklist');
		var siteIds = getSelectedSiteIds('launchdek-run-site-picker');
		return checklist && siteIds.length === 1;
	}

	function renderRunEmptyState() {
		var list = document.getElementById('launchdek-run-steps');
		var info = document.getElementById('launchdek-run-info');
		var progressWrap = document.getElementById('launchdek-run-progress-wrap');
		if (info) {
			info.innerHTML = escHtml(strings.automationExecuteEmpty || 'Complete steps 1–2 to start a run, or open an existing run from Sites.');
			info.classList.add('launchdek-muted');
		}
		if (progressWrap) {
			progressWrap.innerHTML = '';
		}
		if (list) {
			list.innerHTML = '';
		}
		var pushClientBtn = document.getElementById('launchdek-run-push-client');
		if (pushClientBtn) {
			pushClientBtn.hidden = true;
		}
	}

	function getRunProgressPayload(run) {
		var steps = run.steps || [];
		var total = typeof run.steps_total === 'number' ? run.steps_total : steps.length;
		var completed = typeof run.steps_completed === 'number'
			? run.steps_completed
			: steps.filter(function (step) { return step.status === 'completed'; }).length;
		return {
			steps_total: total,
			steps_completed: completed,
			status: run.status || 'unknown'
		};
	}

	function initAutomationTabs() {
		var panels = document.querySelectorAll('[data-launchdek-automation-panel]');
		var tabBtns = document.querySelectorAll('[data-launchdek-automation-tab]');
		if (!panels.length || !tabBtns.length) {
			return null;
		}

		function showTab(tabId, updateUrl) {
			tabId = tabId || 'run';
			panels.forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-launchdek-automation-panel') !== tabId;
			});
			tabBtns.forEach(function (btn) {
				var active = btn.getAttribute('data-launchdek-automation-tab') === tabId;
				btn.classList.toggle('nav-tab-active', active);
				btn.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			if (updateUrl && window.history && window.history.replaceState) {
				var url = new URL(window.location.href);
				if (tabId === 'run') {
					url.searchParams.delete('tab');
				} else {
					url.searchParams.set('tab', tabId);
				}
				window.history.replaceState(null, '', url.toString());
			}
		}

		tabBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				showTab(btn.getAttribute('data-launchdek-automation-tab'), true);
			});
		});

		return showTab;
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
		var checklist = getSelectedChecklist('launchdek-batch-checklist');
		var siteIds = getSelectedSiteIds('launchdek-batch-site-picker');
		if (!checklist || !siteIds.length) {
			batchNotice(strings.automationNeedSiteChecklist || 'Select at least one site and a checklist.', 'error');
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
			batchNotice(strings.automationBatchQueueEmpty || 'No queued items to process.', 'error');
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
				if (showAutomationTab) {
					showAutomationTab('run', false);
				}
				renderRun(data.runs[0].run);
			}
			loadAudit();
			batchNotice(strings.automationBatchProcessed || 'Batch queue processed.', 'success');
		}).catch(function (e) {
			queued.forEach(function (item) { item.status = 'failed'; });
			renderBatchQueue();
			batchNotice(e.message, 'error');
		});
	}

	function renderRun(run) {
		if (!run) return;
		activeRunId = run.id;
		goToAutomationStep(3);
		if (showAutomationTab) {
			showAutomationTab('run', false);
		}
		var pushClientBtn = document.getElementById('launchdek-run-push-client');
		if (pushClientBtn) {
			pushClientBtn.hidden = false;
		}
		var info = document.getElementById('launchdek-run-info');
		if (info) {
			info.classList.remove('launchdek-muted');
			info.innerHTML =
				'<strong>Run #' + run.id + '</strong><br>' +
				escHtml(run.checklist_title) + ' on ' + escHtml(run.site_name);
		}
		var progressWrap = document.getElementById('launchdek-run-progress-wrap');
		if (progressWrap) {
			progressWrap.innerHTML = runStepsProgressHtml(getRunProgressPayload(run));
		}

		var list = document.getElementById('launchdek-run-steps');
		list.innerHTML = '';
		(run.steps || []).forEach(function (step) {
			var li = el('li', { className: 'launchdek-run-step status-' + step.status });
			var html = '<strong>' + (step.step_index + 1) + '. ' + escHtml(step.title) + '</strong>';
			html += '<div class="launchdek-run-step-meta">' + escHtml(step.step_type);
			if (step.status && step.status !== 'pending') {
				html += ' — ' + escHtml(step.status);
			}
			if (step.manual_checked) {
				html += ' <span class="launchdek-double-tick" aria-hidden="true">' + renderDoubleTickIcon() + '</span>';
			}
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
					if (!note || !String(note.text || '').trim()) {
						return;
					}
					var metaParts = [];
					if (note.user) {
						metaParts.push(note.user);
					}
					if (note.created_at) {
						metaParts.push(note.created_at);
					}
					html += '<li class="launchdek-run-step-note">';
					if (metaParts.length) {
						html += '<span class="launchdek-run-step-note-meta">' + escHtml(metaParts.join(' · ')) + '</span>';
					}
					html += '<p>' + escHtml(note.text) + '</p>';
					if (note.attachment_url) {
						html += '<a href="' + escAttr(note.attachment_url) + '" target="_blank" rel="noopener" class="launchdek-run-step-note-attachment">' +
							'<img src="' + escAttr(note.attachment_url) + '" alt="" loading="lazy" />' +
						'</a>';
					}
					html += '</li>';
				});
				html += '</ul></div>';
			}

			if (step.step_type === 'manual' && step.status !== 'completed' && step.status !== 'failed') {
				html += '<button type="button" class="button button-small launchdek-complete-step" data-index="' + step.step_index + '">Mark Complete</button>';
			}
			if (step.step_type === 'manual' && step.status === 'completed' && step.manual_checked) {
				html += '<button type="button" class="button button-small launchdek-uncomplete-step" data-index="' + step.step_index + '">Mark Not Complete</button>';
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

		document.querySelectorAll('.launchdek-uncomplete-step').forEach(function (btn) {
			btn.onclick = function () {
				post('/runs/' + activeRunId + '/steps/' + btn.dataset.index + '/uncomplete', {}).then(function (r) {
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

		showAutomationTab = initAutomationTabs();
		var params = new URLSearchParams(window.location.search);
		var runIdParam = parseInt(params.get('run_id') || '0', 10);
		var tabParam = params.get('tab') || 'run';
		if (runIdParam) {
			tabParam = 'run';
		}
		if (showAutomationTab) {
			showAutomationTab(tabParam, false);
		}

		loadRunSelects().then(function () {
			updateAutomationStepButtons();
		});
		loadAuditMeta().then(loadAudit);
		loadDriftStatus();

		goToAutomationStep(runIdParam ? 3 : 1);

		if (runIdParam) {
			get('/runs/' + runIdParam).then(function (run) {
				renderRun(run);
			}).catch(function (err) {
				runNotice(err.message, 'error');
			});
		}

		var runChecklist = document.getElementById('launchdek-run-checklist');
		if (runChecklist) {
			runChecklist.addEventListener('change', updateAutomationStepButtons);
		}

		var step1Next = document.getElementById('launchdek-automation-step1-next');
		if (step1Next) {
			step1Next.addEventListener('click', function () {
				if (!getSelectedChecklist('launchdek-run-checklist')) {
					runNotice(strings.automationNeedSiteChecklist || 'Select at least one site and a checklist.', 'error');
					return;
				}
				goToAutomationStep(2);
			});
		}

		var step2Back = document.getElementById('launchdek-automation-step2-back');
		if (step2Back) {
			step2Back.addEventListener('click', function () {
				goToAutomationStep(1);
			});
		}

		var backToSetup = document.getElementById('launchdek-automation-back-to-setup');
		if (backToSetup) {
			backToSetup.addEventListener('click', function () {
				goToAutomationStep(1);
			});
		}

		var progress = document.getElementById('launchdek-automation-progress');
		if (progress) {
			progress.querySelectorAll('.launchdek-automation-progress-segment').forEach(function (segment) {
				segment.addEventListener('click', function () {
					var step = parseInt(segment.getAttribute('data-step') || '0', 10);
					if (step === 3 && activeRunId) {
						goToAutomationStep(3);
						return;
					}
					if (step === 1 || step === 2) {
						goToAutomationStep(step);
					}
				});
			});
		}

		var queueAdd = document.getElementById('launchdek-queue-add');
		if (queueAdd) queueAdd.onclick = addToBatchQueue;
		var queueClear = document.getElementById('launchdek-queue-clear');
		if (queueClear) {
			queueClear.onclick = function () {
				batchQueue = [];
				renderBatchQueue();
			};
		}
		var queueProcess = document.getElementById('launchdek-queue-process');
		if (queueProcess) queueProcess.onclick = processBatchQueue;

		var startRunBtn = document.getElementById('launchdek-start-run');
		if (startRunBtn) {
			startRunBtn.onclick = function () {
				if (!canStartSingleRun()) {
					runNotice(strings.automationNeedOneSite || 'Select exactly one site to start a run.', 'error');
					return;
				}
				var siteIds = getSelectedSiteIds('launchdek-run-site-picker');
				var checklist = getSelectedChecklist('launchdek-run-checklist');
				post('/runs', { site_id: siteIds[0], checklist_id: checklist.id })
					.then(function (data) {
						renderRun(data.run);
						loadAudit();
						runNotice((strings.automationRunStarted || 'Run #%d started.').replace('%d', String(data.run_id)), 'success');
					})
					.catch(function (e) {
						runNotice(e.message, 'error');
					});
			};
		}

		document.getElementById('launchdek-run-next').onclick = function () {
			if (!activeRunId) {
				runNotice(strings.automationStartRunFirst || 'Start a run first.', 'error');
				return;
			}
			post('/runs/' + activeRunId + '/next', {}).then(function (r) { renderRun(r.run); loadAudit(); });
		};

		document.getElementById('launchdek-run-auto').onclick = function () {
			if (!activeRunId) {
				runNotice(strings.automationStartRunFirst || 'Start a run first.', 'error');
				return;
			}
			post('/runs/' + activeRunId + '/auto', {}).then(function (r) { renderRun(r.run); loadAudit(); });
		};

		document.getElementById('launchdek-run-push-client').onclick = function () {
			if (!activeRunId) {
				runNotice(strings.automationStartRunFirst || 'Start a run first.', 'error');
				return;
			}
			post('/runs/' + activeRunId + '/push-client', {})
				.then(function (data) {
					if (data.run) {
						renderRun(data.run);
					}
					loadAudit();
					runNotice(data.message || (strings.clientPushOk || 'Checklist pushed to client admin panel.'), 'success');
				})
				.catch(function (e) {
					runNotice(e.message, 'error');
				});
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
			className: 'button launchdek-template-steps-toggle',
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
			var checklistBtn = actions.querySelector('.launchdek-template-use-checklist');
			if (checklistBtn) {
				actions.insertBefore(toggle, checklistBtn);
			} else {
				actions.appendChild(toggle);
			}
		}

		return card;
	}

	function createTemplateCard(tpl, options) {
		options = options || {};
		var steps = tpl.steps || [];
		var cardClass = 'launchdek-template-card';
		if (options.cardClass) {
			cardClass += ' ' + options.cardClass;
		}
		var card = el('div', { className: cardClass });

		var content = el('div', { className: 'launchdek-template-card-content' });
		var main = el('div', { className: 'launchdek-template-card-main' });
		var html = '';
		if (options.badge) {
			html += '<span class="launchdek-badge healthy">' + escHtml(options.badge) + '</span>';
		}
		html +=
			'<h3>' + escHtml(tpl.title) + '</h3>' +
			'<p>' + escHtml(tpl.description || '') + '</p>' +
			'<p class="launchdek-template-step-count"><small>' + escHtml(formatStepsCount(getCustomChecklistStepCount(tpl))) + '</small></p>';
		main.innerHTML = html;

		var actions = el('div', { className: 'launchdek-template-card-actions' });
		if (typeof options.onEdit === 'function') {
			var editBtn = el('button', {
				className: 'button launchdek-template-edit-checklist',
				text: strings.editChecklist || 'Edit Checklist'
			});
			editBtn.onclick = function () {
				options.onEdit(tpl);
			};
			actions.appendChild(editBtn);
		}
		if (typeof options.onChecklist === 'function') {
			var checklistBtn = el('button', {
				className: 'button launchdek-template-use-checklist',
				text: strings.useChecklist || 'Use Checklist'
			});
			checklistBtn.onclick = function () {
				options.onChecklist(tpl);
			};
			actions.appendChild(checklistBtn);
		}

		content.appendChild(main);
		card.appendChild(content);
		card.appendChild(actions);
		if (options.showStepsPreview !== false) {
			attachTemplateStepsPreview(content, card, steps);
		}

		return card;
	}

	function createChecklistFromTemplate(slug) {
		var noticeEl = document.getElementById('launchdek-builtin-notice');
		return post('/templates/' + slug + '/clone', {}).then(function (checklist) {
			if (checklist && checklist.id) {
				openChecklistEditor(checklist.id);
				return;
			}
			notice(noticeEl, strings.checklistCreated || 'Checklist created.', 'success');
		}).catch(function (err) {
			notice(noticeEl, err.message, 'error');
		});
	}

	function renderBuiltinTemplateCards(category) {
		var grid = document.getElementById('launchdek-builtin-templates');
		if (!grid) return;

		var items = builtinTemplates.filter(function (tpl) {
			return tpl.category === category;
		});

		grid.innerHTML = '';
		if (!items.length) {
			grid.innerHTML = '<p class="launchdek-muted">' + escHtml(strings.noCategoryTemplates || 'No templates in this category yet.') + '</p>';
			return;
		}

		items.forEach(function (tpl) {
			grid.appendChild(createTemplateCard(tpl, {
				onChecklist: function () {
					createChecklistFromTemplate(tpl.template_slug);
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
		if (showChecklistTab) {
			showChecklistTab('my-checklists', true);
			if (window.history && window.history.replaceState) {
				var url = new URL(window.location.href);
				url.searchParams.set('tab', 'my-checklists');
				url.searchParams.set('checklist_id', String(id));
				window.history.replaceState(null, '', url.toString());
			}
			loadChecklistEditor(id);
			return;
		}
		window.location.href = launchdekAdmin.adminUrl + '?page=' + launchdekAdmin.pageSlug + '-checklists&tab=my-checklists&checklist_id=' + id;
	}

	function duplicateChecklist(tpl) {
		var noticeEl = document.getElementById('launchdek-custom-notice');
		return get('/checklists/' + tpl.id).then(function (source) {
			return post('/checklists', {
				title: (source.title || 'Checklist') + ' (Copy)',
				description: source.description || '',
				steps: source.steps || [],
				is_template: false
			});
		}).then(function (checklist) {
			refreshCustomChecklistUi(true);
			if (checklist && checklist.id) {
				openChecklistEditor(checklist.id);
				return;
			}
			notice(noticeEl, strings.checklistDuplicated || 'Checklist duplicated.', 'success');
		}).catch(function (err) {
			notice(noticeEl, err.message, 'error');
		});
	}

	function refreshVaultChecklistSelect() {
		fetchCustomChecklistSummaries().then(function (wfs) {
			fillSelect(document.getElementById('launchdek-vault-checklist'), wfs, 'id', 'title', 'Select checklist…');
		});
	}

	function loadCustomChecklists() {
		refreshCustomChecklistUi();
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
					onChecklist: function () {
						duplicateChecklist(tpl);
					}
				}));
			});
		});
	}

	function initTemplates() {
		if (!document.getElementById('launchdek-builtin-templates')) return;

		loadTemplatesData().then(function (data) {
			renderBuiltinCategories(data.builtin || [], data.categories || {});
		});

		refreshCustomChecklistUi();
		loadVaultTemplates();

		document.getElementById('launchdek-save-vault').onclick = function () {
			var id = document.getElementById('launchdek-vault-checklist').value;
			var noticeEl = document.getElementById('launchdek-vault-notice');
			if (!id) return;
			post('/templates/vault', { checklist_id: parseInt(id, 10) }).then(function () {
				notice(noticeEl, strings.savedToVault || 'Saved to vault.', 'success');
				document.getElementById('launchdek-vault-checklist').value = '';
				refreshCustomChecklistUi(true);
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
		initConfirmModal();
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
