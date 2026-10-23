(function () {
	'use strict';

	if (typeof checklistdekClient === 'undefined') {
		return;
	}

	var footerRoot = document.getElementById('checklistdek-client-panel-root');
	if (!footerRoot || !checklistdekClient.run) {
		return;
	}

	var root = footerRoot;
	var strings = checklistdekClient.strings || {};
	var run = checklistdekClient.run;
	var clientNonce = checklistdekClient.nonce;

	function getClientNonce() {
		return checklistdekClient.nonce || clientNonce;
	}

	function setClientNonce(value) {
		if (!value) {
			return;
		}
		clientNonce = value;
		checklistdekClient.nonce = value;
	}
	var panelLayout = 'sidebar';
	var collapsed = false;
	var TOGGLE_LAYOUTS = [
		'live_topbar',
		'bottom_dock',
		'floating_pill',
		'toast',
		'admin_menu',
		'fullscreen'
	];
	var expandedSteps = {};
	var pendingAttachment = null;
	var noteDrafts = {};
	var openNoteComposers = {};
	var collapsedNoteSections = {};
	var STORAGE_KEY = 'checklistdek_client_runner_prefs_v2';
	var CLIENT_API = String(checklistdekClient.restUrl || '').replace(/\/$/, '');

	function buildClientApiUrl(path) {
		path = path.charAt(0) === '/' ? path : '/' + path;

		try {
			var url = new URL(CLIENT_API, window.location.href);
			if (url.searchParams.has('rest_route')) {
				var restRoute = (url.searchParams.get('rest_route') || '').replace(/\/$/, '') + path;
				url.searchParams.set('rest_route', restRoute);
				return url.toString();
			}
		} catch (e) {
			// Fall through.
		}

		return CLIENT_API + path;
	}

	function clientFetch(path, options) {
		options = options || {};
		var headers = options.headers ? Object.assign({}, options.headers) : {};
		if (!headers['X-WP-Nonce']) {
			headers['X-WP-Nonce'] = getClientNonce();
		}
		options = Object.assign({}, options, { headers: headers });

		var primaryUrl = buildClientApiUrl(path);
		return fetch(primaryUrl, options).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) {
					var err = new Error((body && body.message) || strings.error || 'Request failed.');
					err.response = response;
					err.body = body;
					throw err;
				}
				return body;
			});
		}).catch(function (err) {
			var match = CLIENT_API.match(/^(.*)\/wp-json\/([^?]+?)\/?$/);
			if (!match || !(err && err.body && err.body.code === 'rest_no_route')) {
				throw err;
			}

			try {
				var fallback = new URL(match[1] + '/');
				fallback.searchParams.set('rest_route', '/' + match[2].replace(/^\//, '') + path);
				return fetch(fallback.toString(), options).then(function (response) {
					return response.json().then(function (body) {
						if (!response.ok) {
							throw new Error((body && body.message) || strings.error || 'Request failed.');
						}
						return body;
					});
				});
			} catch (fallbackErr) {
				throw err;
			}
		});
	}

	function escHtml(value) {
		if (value === null || value === undefined) {
			value = '';
		}
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function loadPrefs() {
		try {
			var raw = localStorage.getItem(STORAGE_KEY);
			if (!raw) {
				return;
			}
			var prefs = JSON.parse(raw);
			if (prefs && prefs.expandedSteps && typeof prefs.expandedSteps === 'object') {
				expandedSteps = prefs.expandedSteps;
			}
			if (prefs && prefs.collapsedNoteSections && typeof prefs.collapsedNoteSections === 'object') {
				collapsedNoteSections = prefs.collapsedNoteSections;
			}
		} catch (e) {
			// Ignore invalid localStorage.
		}
	}

	function savePrefs() {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify({
				expandedSteps: expandedSteps,
				collapsedNoteSections: collapsedNoteSections
			}));
		} catch (e) {
			// Ignore quota errors.
		}
	}

	function stepExpandedKey(step) {
		return String(step.step_index);
	}

	function sameStepIndex(left, right) {
		return parseInt(left, 10) === parseInt(right, 10);
	}

	function getAttachmentPreviewUrl(attachment) {
		if (!attachment) {
			return '';
		}

		if (attachment.url) {
			return attachment.url;
		}

		var sizes = attachment.sizes || {};
		if (sizes.medium && sizes.medium.url) {
			return sizes.medium.url;
		}
		if (sizes.full && sizes.full.url) {
			return sizes.full.url;
		}
		if (sizes.thumbnail && sizes.thumbnail.url) {
			return sizes.thumbnail.url;
		}

		return '';
	}

	function captureNoteDrafts() {
		if (!root) {
			return;
		}

		root.querySelectorAll('.checklistdek-client-note-input').forEach(function (textarea) {
			var match = String(textarea.id || '').match(/^checklistdek-client-note-(\d+)$/);
			if (!match) {
				return;
			}

			var stepIndex = parseInt(match[1], 10);
			var value = String(textarea.value || '');
			if (value.trim()) {
				noteDrafts[stepIndex] = value;
				return;
			}
			delete noteDrafts[stepIndex];
		});
	}

	function restoreNoteDrafts() {
		if (!root) {
			return;
		}

		root.querySelectorAll('.checklistdek-client-note-input').forEach(function (textarea) {
			var match = String(textarea.id || '').match(/^checklistdek-client-note-(\d+)$/);
			if (!match) {
				return;
			}

			var stepIndex = parseInt(match[1], 10);
			if (noteDrafts.hasOwnProperty(stepIndex)) {
				textarea.value = noteDrafts[stepIndex];
			}
		});
	}

	function noteComposerOpen(stepIndex) {
		if (openNoteComposers[stepIndex]) {
			return true;
		}
		if (noteDrafts[stepIndex] && String(noteDrafts[stepIndex]).trim()) {
			return true;
		}
		if (pendingAttachment && sameStepIndex(pendingAttachment.stepIndex, stepIndex)) {
			return true;
		}
		return false;
	}

	function setNoteComposerOpen(stepIndex, open) {
		openNoteComposers[stepIndex] = !!open;
	}

	function isNoteSectionCollapsed(stepIndex) {
		return !!collapsedNoteSections[String(stepIndex)];
	}

	function setNoteSectionCollapsed(stepIndex, collapsed) {
		if (collapsed) {
			collapsedNoteSections[String(stepIndex)] = true;
		} else {
			delete collapsedNoteSections[String(stepIndex)];
		}
		savePrefs();
	}

	function stepHasSavedNotes(step) {
		return (step.notes || []).some(isValidNote);
	}

	function getNoteForm(stepIndex) {
		if (!root) {
			return null;
		}
		return root.querySelector('.checklistdek-client-note-form[data-step="' + stepIndex + '"]');
	}

	function updateNoteActionButton(stepIndex) {
		var form = getNoteForm(stepIndex);
		if (!form) {
			return;
		}

		var btn = form.querySelector('.checklistdek-client-note-action');
		var textarea = form.querySelector('.checklistdek-client-note-input');
		if (!btn || !textarea) {
			return;
		}

		var hasText = String(textarea.value || '').trim().length > 0;
		var addLabel = strings.addNotes || strings.addNote || 'Add notes';
		var saveLabel = strings.submitNote || 'Save note';
		var label = hasText ? saveLabel : addLabel;

		btn.classList.toggle('is-save-action', hasText);
		btn.classList.toggle('is-note-action', !hasText);
		btn.innerHTML = hasText ? renderSaveIcon() : renderNoteIcon();
		btn.setAttribute('data-tooltip', label);
		btn.setAttribute('aria-label', label);
	}

	function syncNoteComposerUi(stepIndex, open) {
		var form = getNoteForm(stepIndex);
		if (!form) {
			return;
		}

		var composer = form.querySelector('.checklistdek-client-note-composer');
		var attachBtn = form.querySelector('.checklistdek-client-attach-screenshot');
		if (composer) {
			composer.classList.toggle('is-open', open);
		}
		if (attachBtn) {
			attachBtn.hidden = !open;
		}
	}

	function resetNoteComposer(stepIndex) {
		delete noteDrafts[stepIndex];
		delete openNoteComposers[stepIndex];

		var textarea = document.getElementById('checklistdek-client-note-' + stepIndex);
		if (textarea) {
			textarea.value = '';
		}

		syncNoteComposerUi(stepIndex, false);
		updateNoteActionButton(stepIndex);
	}

	function isStepExpanded(step, index, activeIndex) {
		var key = stepExpandedKey(step);
		if (Object.prototype.hasOwnProperty.call(expandedSteps, key)) {
			return !!expandedSteps[key];
		}
		var steps = run.steps || [];
		var allDone = steps.length > 0 && completedCount(steps) === steps.length;
		if (allDone || run.run_status === 'completed') {
			return true;
		}
		// Default: expand only the current step; explicit prefs (including collapse) always win.
		return index === activeIndex;
	}

	function setStepExpanded(stepIndex, expanded) {
		expandedSteps[String(stepIndex)] = !!expanded;
		savePrefs();
	}

	function getStepContext(stepIndex) {
		var steps = run.steps || [];
		var activeIndex = activeStepIndex(steps);
		var step = null;
		var index = -1;

		for (var i = 0; i < steps.length; i++) {
			if (parseInt(steps[i].step_index, 10) === parseInt(stepIndex, 10)) {
				step = steps[i];
				index = i;
				break;
			}
		}

		return {
			step: step,
			index: index,
			activeIndex: activeIndex
		};
	}

	function toggleStepExpanded(stepIndex) {
		var context = getStepContext(stepIndex);

		if (!context.step) {
			return;
		}

		setStepExpanded(stepIndex, !isStepExpanded(context.step, context.index, context.activeIndex));
		render();
	}

	function stepShowsNoteField(step) {
		return step.show_note_field !== false;
	}

	function stepShowsScreenshotField(step) {
		return step.show_screenshot_field !== false && stepShowsNoteField(step);
	}

	function isManualStep(step) {
		return step.type === 'manual' || !step.type;
	}

	function isStepUpcoming(step, index, activeIndex) {
		if (step.status === 'completed' || step.status === 'failed') {
			return false;
		}
		return index > activeIndex;
	}

	function stepCanComplete(step, index, activeIndex) {
		if (index !== activeIndex) {
			return false;
		}
		if (!isManualStep(step) || !step.can_complete) {
			return false;
		}
		return step.status === 'pending' || step.status === 'awaiting_manual' || step.status === 'running';
	}

	function stepCanUndo(step) {
		return isManualStep(step) && step.can_complete && step.status === 'completed' && step.manual_checked;
	}

	function statusLabel(status) {
		if (status === 'completed') {
			return '';
		}
		if (status === 'failed') {
			return strings.error || 'Failed';
		}
		if (status === 'running') {
			return strings.waiting || 'Waiting on agency';
		}
		return '';
	}

	function completedCount(steps) {
		return steps.filter(function (step) {
			return step.status === 'completed';
		}).length;
	}

	function activeStepIndex(steps) {
		for (var i = 0; i < steps.length; i++) {
			if (steps[i].status === 'awaiting_manual') {
				return i;
			}
		}
		for (var j = 0; j < steps.length; j++) {
			if (steps[j].status !== 'completed' && steps[j].status !== 'failed') {
				return j;
			}
		}
		return steps.length > 0 ? steps.length - 1 : 0;
	}

	function formatStepOf(current, total) {
		var template = strings.stepOf || 'Step %1$s of %2$s';
		return template.replace('%1$s', current).replace('%2$s', total);
	}

	function formatChecklistFraction(done, total) {
		var template = strings.checklistProgress || 'Checklist %1$d/%2$d';
		return template
			.replace('%1$d', done)
			.replace('%2$d', total)
			.replace('%1$s', done)
			.replace('%2$s', total);
	}

	function formatCompactProgress(done, total, percent, currentDisplay, allDone) {
		if (allDone) {
			return formatChecklistFraction(done, total) + ' · 100%';
		}

		return formatStepOf(currentDisplay, total) + ' · ' + percent + '%';
	}

	function syncAdminBarProgress(done, total) {
		var link = document.querySelector('#wp-admin-bar-checklistdek-client-checklist > a');
		if (!link) {
			return;
		}

		link.textContent = formatChecklistFraction(done, total);
	}

	function formatRunTimestamp(value) {
		return formatCompletedAt(value);
	}

	function latestStepCompletedAt(steps) {
		var latest = '';

		steps.forEach(function (step) {
			if (step.status !== 'completed' || !step.completed_at) {
				return;
			}

			if (!latest || String(step.completed_at) > String(latest)) {
				latest = step.completed_at;
			}
		});

		return latest;
	}

	function renderRunCompleteSummary(steps) {
		var started = formatRunTimestamp(run.started_at || run.pushed_at);
		var completed = formatRunTimestamp(run.completed_at || latestStepCompletedAt(steps));
		var meta = '';

		if (started) {
			meta += '<p class="checklistdek-client-run-complete-meta">' +
				'<span class="checklistdek-client-run-complete-label">' + escHtml(strings.startedLabel || 'Started:') + '</span> ' +
				escHtml(started) +
			'</p>';
		}

		if (completed) {
			meta += '<p class="checklistdek-client-run-complete-meta">' +
				'<span class="checklistdek-client-run-complete-label">' + escHtml(strings.completedLabel || 'Completed:') + '</span> ' +
				escHtml(completed) +
			'</p>';
		}

		return '<div class="checklistdek-client-run-complete-summary">' +
			'<p class="checklistdek-client-run-complete-title">' + escHtml(strings.runComplete || 'Checklist complete!') + '</p>' +
			meta +
			'<button type="button" class="button button-secondary checklistdek-client-dismiss-run" id="checklistdek-client-dismiss-run">' +
				escHtml(strings.dismiss || 'Dismiss') +
			'</button>' +
		'</div>';
	}

	function formatCompletedAt(value) {
		if (!value) {
			return '';
		}

		var normalized = String(value).trim().replace(' ', 'T');
		if (!/Z$/i.test(normalized) && !/[+-]\d{2}:\d{2}$/.test(normalized)) {
			normalized += 'Z';
		}

		var date = new Date(normalized);
		if (isNaN(date.getTime())) {
			return String(value);
		}

		return date.toLocaleString();
	}

	function formatCompletedBy(step) {
		var completedBy = step.completed_by || {};
		var name = completedBy.name ? String(completedBy.name) : '';
		var email = completedBy.email ? String(completedBy.email) : '';

		if (name && email) {
			return name + ' (' + email + ')';
		}

		return name || email || '';
	}

	function renderCompletionMeta(step) {
		if (step.status !== 'completed') {
			return '';
		}

		var who = formatCompletedBy(step);
		var when = formatCompletedAt(step.completed_at);

		if (!who && !when) {
			return '';
		}

		if (!who) {
			who = strings.unknownUser || 'Unknown user';
		}

		var template = strings.completedBy || 'Completed by %1$s on %2$s';
		var text = when
			? template.replace('%1$s', who).replace('%2$s', when)
			: who;

		return '<p class="checklistdek-client-step-completed-meta">' + escHtml(text) + '</p>';
	}

	function renderChevron(expanded) {
		var path = expanded ? 'M2 6.5 5 3.5 8 6.5z' : 'M2 3.5 5 6.5 8 3.5z';
		return '<svg class="checklistdek-client-step-chevron" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path fill="currentColor" d="' + path + '"/></svg>';
	}

	function renderIconButton(className, label, iconMarkup, stepIndex, tooltipPosition) {
		var tooltipClass = '';

		if (tooltipPosition === 'left') {
			tooltipClass = ' checklistdek-client-tooltip-left';
		} else if (tooltipPosition === 'right') {
			tooltipClass = ' checklistdek-client-tooltip-right';
		}

		return '<button type="button" class="checklistdek-client-icon-btn checklistdek-client-has-tooltip' + tooltipClass + ' ' + className + '" data-step="' + escHtml(stepIndex) + '" data-tooltip="' + escHtml(label) + '" aria-label="' + escHtml(label) + '">' + iconMarkup + '</button>';
	}

	function renderTickIcon() {
		return '<svg class="checklistdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
	}

	function renderDoubleTickIcon() {
		var tick = '<svg class="checklistdek-client-icon checklistdek-client-icon-double-tick" width="10" height="10" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		return '<span class="checklistdek-client-double-tick-icons">' + tick + tick + '</span>';
	}

	function renderNoteIcon() {
		return '<svg class="checklistdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
	}

	function renderSaveIcon() {
		return '<svg class="checklistdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
	}

	function renderAttachIcon() {
		return '<svg class="checklistdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
	}

	function renderExternalIcon() {
		return '<svg class="checklistdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
	}

	function renderUndoIcon() {
		return '<svg class="checklistdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.5 8c-2.65 0-5.05 1.04-6.9 2.9L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.31 0 6 2.69 6 6s-2.69 6-6 6c-1.66 0-3.14-.69-4.24-1.76L5.52 18.5C7.05 20.36 9.62 21.5 12.5 21.5c4.69 0 8.5-3.81 8.5-8.5S17.19 8 12.5 8z"/></svg>';
	}

	function renderCompletedIndicator(step) {
		var tick = '<span class="checklistdek-client-step-done-icon" aria-hidden="true">' + renderDoubleTickIcon() + '</span>';

		if (!stepCanUndo(step)) {
			return '<span class="checklistdek-client-step-complete-state is-readonly" aria-label="' + escHtml(strings.completed || 'Completed') + '">' + tick + '</span>';
		}

		return '<div class="checklistdek-client-step-complete-state">' +
			tick +
			renderIconButton(
				'checklistdek-client-uncomplete-step is-undo-action',
				strings.undoComplete || 'Mark not complete',
				renderUndoIcon(),
				step.step_index,
				'left'
			) +
		'</div>';
	}

	function renderCompleteButton(step, index, activeIndex) {
		if (stepCanComplete(step, index, activeIndex)) {
			return renderIconButton(
				'checklistdek-client-complete-step is-complete-action',
				strings.markComplete || 'Mark complete',
				renderTickIcon(),
				step.step_index,
				'left'
			);
		}

		if (step.status === 'completed') {
			return renderCompletedIndicator(step);
		}

		return '';
	}

	function renderSettingsLink(step) {
		if (!step.deep_link) {
			return '';
		}

		var label = strings.goToSettings || 'Go to settings';

		return '<a class="checklistdek-client-step-settings-link" href="' + escHtml(step.deep_link) + '" target="_blank" rel="noopener">' + escHtml(label) + '</a>';
	}

	function noteFormWillRender(step, index, activeIndex) {
		return stepShowsNoteField(step) && stepCanComplete(step, index, activeIndex);
	}

	function isValidNote(note) {
		return !!(note && String(note.text || '').trim());
	}

	function formatNoteMeta(note) {
		var user = note.user ? String(note.user) : '';
		var when = formatCompletedAt(note.created_at);
		var template = strings.noteMeta || '%1$s · %2$s';

		if (user && when) {
			return template.replace('%1$s', user).replace('%2$s', when);
		}

		return user || when || '';
	}

	function renderNotes(notes, stepIndex) {
		var validNotes = (notes || []).filter(isValidNote).sort(function (left, right) {
			var leftTime = Date.parse(String(left.created_at || '').replace(' ', 'T') + 'Z');
			var rightTime = Date.parse(String(right.created_at || '').replace(' ', 'T') + 'Z');

			if (isNaN(leftTime) || isNaN(rightTime)) {
				return 0;
			}

			return leftTime - rightTime;
		});

		if (!validNotes.length) {
			return '';
		}

		var items = validNotes.map(function (note) {
			var meta = formatNoteMeta(note);
			var html = '<li class="checklistdek-client-note">';

			if (meta) {
				html += '<span class="checklistdek-client-note-meta">' + escHtml(meta) + '</span>';
			}
			if (note.text) {
				html += '<p class="checklistdek-client-note-text">' + escHtml(note.text) + '</p>';
			}
			if (note.attachment_url) {
				html += '<a class="checklistdek-client-note-attachment" href="' + escHtml(note.attachment_url) + '" target="_blank" rel="noopener">' +
					'<img src="' + escHtml(note.attachment_url) + '" alt="" loading="lazy" />' +
				'</a>';
			}
			html += '</li>';
			return html;
		}).join('');

		var collapsed = isNoteSectionCollapsed(stepIndex);
		var title = strings.notesHeading || 'Notes';
		var countLabel = strings.notesCount || '%1$s (%2$s)';

		return '<div class="checklistdek-client-step-notes' + (collapsed ? ' is-collapsed' : '') + '">' +
			'<button type="button" class="checklistdek-client-step-notes-toggle" data-step="' + escHtml(stepIndex) + '" aria-expanded="' + (collapsed ? 'false' : 'true') + '">' +
				'<span class="checklistdek-client-step-notes-title">' + escHtml(countLabel.replace('%1$s', title).replace('%2$s', String(validNotes.length))) + '</span>' +
				renderChevron(!collapsed) +
			'</button>' +
			'<ul class="checklistdek-client-note-list">' + items + '</ul>' +
		'</div>';
	}

	function renderNoteForm(step, index, activeIndex) {
		if (!stepShowsNoteField(step) || !stepCanComplete(step, index, activeIndex)) {
			return '';
		}

		var stepIndex = parseInt(step.step_index, 10);
		var isOpen = noteComposerOpen(stepIndex);
		var draftText = noteDrafts.hasOwnProperty(stepIndex) ? String(noteDrafts[stepIndex] || '') : '';
		var hasText = draftText.trim().length > 0;
		var addLabel = strings.addNotes || strings.addNote || 'Add notes';
		var saveLabel = strings.submitNote || 'Save note';
		var actionLabel = hasText ? saveLabel : addLabel;
		var actionClass = hasText ? 'is-save-action' : 'is-note-action';
		var actionIcon = hasText ? renderSaveIcon() : renderNoteIcon();
		var attachmentPreview = '';

		if (pendingAttachment && sameStepIndex(pendingAttachment.stepIndex, stepIndex)) {
			attachmentPreview = '<div class="checklistdek-client-note-attachment-preview">' +
				'<img src="' + escHtml(pendingAttachment.url) + '" alt="" />' +
				'<button type="button" class="button-link checklistdek-client-remove-attachment" data-step="' + escHtml(stepIndex) + '" title="' + escHtml(strings.removeAttachment || 'Remove screenshot') + '" aria-label="' + escHtml(strings.removeAttachment || 'Remove screenshot') + '">&times;</button>' +
			'</div>';
		}

		var settingsLink = renderSettingsLink(step);
		var settingsHtml = settingsLink
			? '<div class="checklistdek-client-note-form-settings">' + settingsLink + '</div>'
			: '';

		var screenshotBtn = stepShowsScreenshotField(step)
			? renderIconButton('checklistdek-client-attach-screenshot is-attach-action', strings.attachScreenshot || 'Attach screenshot', renderAttachIcon(), stepIndex, 'right')
			: '';

		return '<div class="checklistdek-client-note-form" data-step="' + escHtml(stepIndex) + '">' +
			settingsHtml +
			'<div class="checklistdek-client-note-form-actions">' +
				'<button type="button" class="checklistdek-client-icon-btn checklistdek-client-has-tooltip checklistdek-client-note-action ' + actionClass + '" data-step="' + escHtml(stepIndex) + '" data-tooltip="' + escHtml(actionLabel) + '" aria-label="' + escHtml(actionLabel) + '">' + actionIcon + '</button>' +
				screenshotBtn +
			'</div>' +
			'<div class="checklistdek-client-note-composer' + (isOpen ? ' is-open' : '') + '">' +
				'<div class="checklistdek-client-note-composer-inner">' +
					'<label class="screen-reader-text" for="checklistdek-client-note-' + escHtml(stepIndex) + '">' + escHtml(strings.addNote || 'Add note') + '</label>' +
					'<textarea id="checklistdek-client-note-' + escHtml(stepIndex) + '" class="checklistdek-client-note-input" rows="2" placeholder="' + escHtml(strings.notePlaceholder || 'Add a note about this step…') + '"></textarea>' +
					attachmentPreview +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function renderStepBody(step, index, activeIndex) {
		var body = '';

		if (step.instructions) {
			body += '<p class="checklistdek-client-step-instructions">' + escHtml(step.instructions) + '</p>';
		}
		body += renderCompletionMeta(step);
		body += renderNotes(step.notes, parseInt(step.step_index, 10));
		if (step.deep_link && !noteFormWillRender(step, index, activeIndex)) {
			body += '<div class="checklistdek-client-step-settings">' + renderSettingsLink(step) + '</div>';
		}
		body += renderNoteForm(step, index, activeIndex);

		return body;
	}

	function renderStep(step, isActive, index, activeIndex) {
		var expanded = isStepExpanded(step, index, activeIndex);
		var upcoming = isStepUpcoming(step, index, activeIndex);
		var classes = ['checklistdek-client-step'];

		if (step.status === 'completed') {
			classes.push('is-completed');
		}
		if (step.status === 'awaiting_manual' && !upcoming) {
			classes.push('is-active');
		}
		if (isActive) {
			classes.push('is-current');
		}
		if (upcoming) {
			classes.push('is-upcoming');
		}
		if (!expanded) {
			classes.push('is-collapsed');
		}

		var body = expanded ? renderStepBody(step, index, activeIndex) : '';
		var completeButton = renderCompleteButton(step, index, activeIndex);
		var badgeLabel = statusLabel(step.status);
		var badgeStatus = step.status === 'pending' ? 'awaiting_manual' : step.status;
		var badgeHtml = badgeLabel
			? '<span class="checklistdek-client-step-badge ' + escHtml(badgeStatus) + '">' + escHtml(badgeLabel) + '</span>'
			: '';

		return '<li class="' + classes.join(' ') + '" data-step-index="' + escHtml(step.step_index) + '"' + (upcoming ? ' aria-disabled="true"' : '') + '>' +
			'<div class="checklistdek-client-step-head">' +
				'<button type="button" class="checklistdek-client-step-toggle" data-step="' + escHtml(step.step_index) + '" aria-expanded="' + (expanded ? 'true' : 'false') + '" aria-label="' + escHtml(strings.toggleStep || 'Toggle step details') + '">' +
					renderChevron(expanded) +
				'</button>' +
				'<h3 class="checklistdek-client-step-title">' + escHtml(step.title) + '</h3>' +
				'<div class="checklistdek-client-step-head-actions">' +
					badgeHtml +
					completeButton +
				'</div>' +
			'</div>' +
			(body ? '<div class="checklistdek-client-step-body">' + body + '</div>' : '') +
		'</li>';
	}

	function resolvePanelLayout() {
		return String(
			(run && run.panel_layout) ||
			checklistdekClient.panelLayout ||
			'sidebar'
		);
	}

	function resolvePanelTitle() {
		return String(
			(run && run.panel_title) ||
			strings.panelTitle ||
			'Agency Checklist'
		);
	}

	function refreshLayoutState() {
		panelLayout = resolvePanelLayout();
	}

	function layoutCollapsesOnToggle(layout) {
		return TOGGLE_LAYOUTS.indexOf(layout) !== -1;
	}

	function layoutKeepsPanelChromeOnCollapse(layout) {
		return layout === 'inline_metabox';
	}

	function layoutStartsCollapsed(layout) {
		return layoutCollapsesOnToggle(layout);
	}

	function updateRootTarget() {
		if (panelLayout === 'inline_metabox') {
			var metabox = document.getElementById('checklistdek-client-metabox-root');
			if (metabox) {
				footerRoot.hidden = true;
				root = metabox;
				return;
			}
		}

		footerRoot.hidden = false;
		root = footerRoot;
	}

	function syncBodyClasses() {
		if (!document.body) {
			return;
		}

		document.body.classList.remove(
			'checklistdek-client-topbar-expanded',
			'checklistdek-client-topbar-collapsed',
			'checklistdek-client-dock-expanded',
			'checklistdek-client-dock-collapsed',
			'checklistdek-client-rail-open',
			'checklistdek-client-fullscreen-open'
		);
		document.body.style.removeProperty('--checklistdek-client-topbar-offset');
		document.body.style.removeProperty('--checklistdek-client-dock-offset');
		document.body.style.removeProperty('--checklistdek-client-rail-offset');

		if (panelLayout === 'live_topbar') {
			document.body.classList.add(
				collapsed ? 'checklistdek-client-topbar-collapsed' : 'checklistdek-client-topbar-expanded'
			);
			window.requestAnimationFrame(function () {
				var height = root ? root.offsetHeight : 0;
				document.body.style.setProperty(
					'--checklistdek-client-topbar-offset',
					(height > 0 ? height : 44) + 'px'
				);
			});
			return;
		}

		if (panelLayout === 'bottom_dock') {
			document.body.classList.add(
				collapsed ? 'checklistdek-client-dock-collapsed' : 'checklistdek-client-dock-expanded'
			);
			window.requestAnimationFrame(function () {
				var height = root ? root.offsetHeight : 0;
				document.body.style.setProperty(
					'--checklistdek-client-dock-offset',
					(height > 0 ? height : 44) + 'px'
				);
			});
			return;
		}

		if (panelLayout === 'fullscreen' && !collapsed) {
			document.body.classList.add('checklistdek-client-fullscreen-open');
		}
	}

	function renderProgressBar(percent) {
		return '<div class="checklistdek-client-progress-bar" role="progressbar" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100">' +
			'<span class="checklistdek-client-progress-fill" style="--checklistdek-progress-pct:' + percent + '%"></span>' +
		'</div>';
	}

	function renderPanelBrandMark() {
		return '<svg class="checklistdek-client-panel-brand-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
			'<rect x="1.25" y="1.25" width="11.5" height="11.5" rx="2" stroke="currentColor" stroke-width="1.5"/>' +
			'<path d="M4.25 4.75L6.1 6.6L9.75 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
			'<path d="M4.25 9.25h5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>' +
		'</svg>';
	}

	function renderPanelBrand() {
		var brandName = strings.brandName || 'ChecklistDek';

		return '<div class="checklistdek-client-panel-brand">' +
			'<span class="checklistdek-client-panel-brand-mark" aria-hidden="true">' + renderPanelBrandMark() + '</span>' +
			'<span class="checklistdek-client-panel-brand-name">' + escHtml(brandName) + '</span>' +
		'</div>';
	}

	function renderPanelBody(steps, allDone, activeIndex) {
		return (allDone ? renderRunCompleteSummary(steps) : '') +
			'<ol class="checklistdek-client-step-list">' + steps.map(function (step, index) {
				return renderStep(step, index === activeIndex, index, activeIndex);
			}).join('') + '</ol>' +
			'<div id="checklistdek-client-panel-notice" class="checklistdek-client-panel-notice" hidden></div>';
	}

	function getCurrentStepTitle(steps, activeIndex) {
		if (!steps.length || activeIndex < 0 || !steps[activeIndex]) {
			return '';
		}

		return steps[activeIndex].title || '';
	}

	function renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, toggleLabel) {
		return renderPanelBrand() +
			'<div class="checklistdek-client-panel-header">' +
				'<div>' +
					'<h2 class="checklistdek-client-panel-title">' + escHtml(resolvePanelTitle()) + '</h2>' +
					'<p class="checklistdek-client-panel-subtitle">' + escHtml(run.checklist_title || '') + '</p>' +
				'</div>' +
				'<button type="button" class="button button-small checklistdek-client-panel-toggle" id="checklistdek-client-panel-collapse">' + escHtml(toggleLabel || strings.collapse || 'Collapse') + '</button>' +
			'</div>' +
			'<div class="checklistdek-client-panel-progress-wrap">' +
				'<div class="checklistdek-client-panel-progress-meta">' +
					'<span class="checklistdek-client-panel-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
				'</div>' +
				renderProgressBar(percent) +
			'</div>' +
			'<div class="checklistdek-client-panel-body">' +
				renderPanelBody(steps, allDone, activeIndex) +
			'</div>';
	}

	function renderFocusBody(steps, allDone, activeIndex) {
		if (allDone) {
			return renderRunCompleteSummary(steps) +
				'<div id="checklistdek-client-panel-notice" class="checklistdek-client-panel-notice" hidden></div>';
		}

		var step = steps[activeIndex];
		if (!step) {
			return renderPanelBody(steps, allDone, activeIndex);
		}

		return '<ol class="checklistdek-client-step-list checklistdek-client-step-list-focus">' +
			renderStep(step, true, activeIndex, activeIndex) +
		'</ol>' +
		'<div id="checklistdek-client-panel-notice" class="checklistdek-client-panel-notice" hidden></div>';
	}

	function renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, options) {
		options = options || {};
		var variant = options.variant || 'default';
		var toggleLabel = options.toggleLabel || strings.collapse || 'Collapse';
		if (variant === 'metabox' && collapsed) {
			toggleLabel = strings.expand || 'Expand';
		}
		var panelClasses = 'checklistdek-client-panel' +
			(variant === 'metabox' ? ' is-metabox' : '') +
			(variant === 'focus' ? ' is-focus' : '') +
			(collapsed ? ' is-collapsed' : '');
		var body = variant === 'focus'
			? renderFocusBody(steps, allDone, activeIndex)
			: renderPanelBody(steps, allDone, activeIndex);

		return (collapsed && variant !== 'metabox'
			? '<button type="button" class="checklistdek-client-panel-tab" id="checklistdek-client-panel-expand">' + escHtml(formatCompactProgress(completedCount(steps), total, percent, currentDisplay, allDone)) + '</button>'
			: '') +
			'<aside class="' + panelClasses + '" aria-label="' + escHtml(resolvePanelTitle()) + '">' +
				renderPanelBrand() +
				'<div class="checklistdek-client-panel-header">' +
					'<div>' +
						'<h2 class="checklistdek-client-panel-title">' + escHtml(resolvePanelTitle()) + '</h2>' +
						'<p class="checklistdek-client-panel-subtitle">' + escHtml(run.checklist_title || '') + '</p>' +
						(variant === 'metabox' && collapsed
							? '<p class="checklistdek-client-panel-collapsed-meta">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</p>'
							: '') +
					'</div>' +
					'<button type="button" class="button button-small checklistdek-client-panel-toggle" id="checklistdek-client-panel-collapse">' + escHtml(toggleLabel) + '</button>' +
				'</div>' +
				'<div class="checklistdek-client-panel-progress-wrap">' +
					'<div class="checklistdek-client-panel-progress-meta">' +
						'<span class="checklistdek-client-panel-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
					'</div>' +
					renderProgressBar(percent) +
				'</div>' +
				'<div class="checklistdek-client-panel-body">' +
					body +
				'</div>' +
			'</aside>';
	}

	function renderBarLayout(position, steps, allDone, activeIndex, percent, currentDisplay, total) {
		var isBottom = position === 'bottom';
		var shellClass = isBottom ? 'checklistdek-client-bottom-dock' : 'checklistdek-client-topbar';
		var drawerClass = isBottom ? 'checklistdek-client-bottom-dock-drawer' : 'checklistdek-client-topbar-drawer';
		var currentTitle = getCurrentStepTitle(steps, activeIndex);
		var toggleLabel = collapsed
			? (strings.showSteps || 'Show steps')
			: (strings.hideSteps || 'Hide steps');

		return '<div class="' + shellClass + (collapsed ? ' is-collapsed' : ' is-expanded') + '" role="region" aria-label="' + escHtml(resolvePanelTitle()) + '">' +
			renderPanelBrand() +
			'<div class="' + shellClass + '-inner">' +
				'<div class="' + shellClass + '-copy">' +
					'<span class="' + shellClass + '-kicker">' + escHtml(resolvePanelTitle()) + '</span>' +
					'<strong class="' + shellClass + '-title">' + escHtml(run.checklist_title || '') + '</strong>' +
					'<span class="' + shellClass + '-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
				'</div>' +
				'<div class="' + shellClass + '-progress">' + renderProgressBar(percent) + '</div>' +
				(currentTitle && !allDone
					? '<span class="' + shellClass + '-current" title="' + escHtml(currentTitle) + '">' +
						escHtml(strings.currentStep || 'Current step') + ': ' + escHtml(currentTitle) +
					'</span>'
					: '') +
				'<button type="button" class="button button-small checklistdek-client-panel-toggle" id="checklistdek-client-panel-collapse">' + escHtml(toggleLabel) + '</button>' +
			'</div>' +
			'<div class="' + drawerClass + (collapsed ? ' is-collapsed' : '') + '">' +
				'<div class="checklistdek-client-panel-body">' +
					renderPanelBody(steps, allDone, activeIndex) +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function renderFloatingPillLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		if (collapsed) {
			return '<div class="checklistdek-client-floating-pill is-collapsed">' +
				'<button type="button" class="checklistdek-client-pill-trigger" id="checklistdek-client-panel-expand">' +
					escHtml(formatCompactProgress(completedCount(steps), total, percent, currentDisplay, allDone)) +
				'</button>' +
			'</div>';
		}

		return '<div class="checklistdek-client-floating-pill is-expanded">' +
			renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, {
				variant: 'default',
				toggleLabel: strings.collapse || 'Collapse'
			}) +
		'</div>';
	}

	function renderToastLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		var currentTitle = getCurrentStepTitle(steps, activeIndex);

		if (collapsed) {
			return '<div class="checklistdek-client-toast is-collapsed">' +
				'<button type="button" class="checklistdek-client-toast-trigger" id="checklistdek-client-panel-expand">' +
					escHtml(formatCompactProgress(completedCount(steps), total, percent, currentDisplay, allDone)) +
					(currentTitle && !allDone ? ' — ' + escHtml(currentTitle) : '') +
				'</button>' +
			'</div>';
		}

		return '<div class="checklistdek-client-toast is-expanded">' +
			'<div class="checklistdek-client-toast-panel">' +
				renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, strings.collapse || 'Collapse') +
			'</div>' +
		'</div>';
	}

	function renderAdminFlyoutLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		if (collapsed) {
			return '<button type="button" class="checklistdek-client-admin-menu-trigger" id="checklistdek-client-panel-expand" aria-label="' + escHtml(strings.openChecklist || 'Open checklist') + '">' +
				escHtml(formatCompactProgress(completedCount(steps), total, percent, currentDisplay, allDone)) +
			'</button>';
		}

		return '<div class="checklistdek-client-admin-flyout" role="dialog" aria-label="' + escHtml(resolvePanelTitle()) + '">' +
			renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, strings.collapse || 'Collapse') +
		'</div>';
	}

	function renderFullscreenLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		if (collapsed) {
			var collapsedLabel = allDone
				? (strings.runComplete || 'Checklist complete!')
				: formatCompactProgress(completedCount(steps), total, percent, currentDisplay, allDone);

			return '<button type="button" class="button checklistdek-client-fullscreen-open" id="checklistdek-client-panel-expand">' +
				escHtml(collapsedLabel) +
			'</button>';
		}

		return '<div class="checklistdek-client-fullscreen is-open">' +
			'<button type="button" class="checklistdek-client-fullscreen-backdrop" id="checklistdek-client-fullscreen-backdrop" aria-label="' + escHtml(strings.collapse || 'Collapse') + '"></button>' +
			'<div class="checklistdek-client-fullscreen-dialog" role="dialog" aria-label="' + escHtml(resolvePanelTitle()) + '">' +
				renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, strings.collapse || 'Collapse') +
			'</div>' +
		'</div>';
	}

	function renderLayoutMarkup(steps, allDone, activeIndex, percent, currentDisplay, total) {
		switch (panelLayout) {
			case 'live_topbar':
				return renderBarLayout('top', steps, allDone, activeIndex, percent, currentDisplay, total);
			case 'bottom_dock':
				return renderBarLayout('bottom', steps, allDone, activeIndex, percent, currentDisplay, total);
			case 'inline_metabox':
				return renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, { variant: 'metabox' });
			case 'focus_mode':
				return renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, { variant: 'focus' });
			case 'floating_pill':
				return renderFloatingPillLayout(steps, allDone, activeIndex, percent, currentDisplay, total);
			case 'toast':
				return renderToastLayout(steps, allDone, activeIndex, percent, currentDisplay, total);
			case 'admin_menu':
				return renderAdminFlyoutLayout(steps, allDone, activeIndex, percent, currentDisplay, total);
			case 'fullscreen':
				return renderFullscreenLayout(steps, allDone, activeIndex, percent, currentDisplay, total);
			default:
				return renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total);
		}
	}

	function render() {
		captureNoteDrafts();
		refreshLayoutState();
		updateRootTarget();

		var steps = run.steps || [];
		var done = completedCount(steps);
		var total = steps.length;
		var allDone = total > 0 && done === total;
		var activeIndex = activeStepIndex(steps);
		var percent = total > 0 ? Math.round((done / total) * 100) : 0;
		var currentDisplay = Math.min(activeIndex + 1, total);
		var layoutClass = 'checklistdek-client-layout-' + panelLayout;
		var rootClasses = 'checklistdek-client-panel-root ' + layoutClass;

		if (collapsed && !layoutCollapsesOnToggle(panelLayout) && !layoutKeepsPanelChromeOnCollapse(panelLayout)) {
			rootClasses += ' is-collapsed';
		}

		root.className = rootClasses;
		root.innerHTML = renderLayoutMarkup(steps, allDone, activeIndex, percent, currentDisplay, total);

		syncAdminBarProgress(done, total);
		syncBodyClasses();
		bindActions();
		restoreNoteDrafts();
		root.querySelectorAll('.checklistdek-client-note-form').forEach(function (form) {
			var stepIndex = parseInt(form.getAttribute('data-step'), 10);
			if (isNaN(stepIndex)) {
				return;
			}
			syncNoteComposerUi(stepIndex, noteComposerOpen(stepIndex));
			updateNoteActionButton(stepIndex);
		});
	}

	function bindActions() {
		var collapseBtn = document.getElementById('checklistdek-client-panel-collapse');
		var expandBtn = document.getElementById('checklistdek-client-panel-expand');

		if (collapseBtn) {
			collapseBtn.addEventListener('click', function () {
				collapsed = (layoutCollapsesOnToggle(panelLayout) || layoutKeepsPanelChromeOnCollapse(panelLayout))
					? !collapsed
					: true;
				render();
			});
		}

		if (expandBtn) {
			expandBtn.addEventListener('click', function () {
				collapsed = false;
				render();
			});
		}

		var adminBarLink = document.querySelector('#wp-admin-bar-checklistdek-client-checklist > a');
		if (adminBarLink) {
			adminBarLink.addEventListener('click', function (event) {
				event.preventDefault();
				collapsed = !collapsed;
				render();
			});
		}

		var fullscreenBackdrop = document.getElementById('checklistdek-client-fullscreen-backdrop');
		if (fullscreenBackdrop) {
			fullscreenBackdrop.addEventListener('click', function () {
				collapsed = true;
				render();
			});
		}

		root.querySelectorAll('.checklistdek-client-step-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				toggleStepExpanded(parseInt(btn.getAttribute('data-step'), 10));
			});
		});

		root.querySelectorAll('.checklistdek-client-step-notes-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var stepIndex = parseInt(btn.getAttribute('data-step'), 10);
				var collapsed = !isNoteSectionCollapsed(stepIndex);
				setNoteSectionCollapsed(stepIndex, collapsed);
				render();
			});
		});

		root.querySelectorAll('.checklistdek-client-complete-step').forEach(function (btn) {
			btn.addEventListener('click', function () {
				completeStep(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.checklistdek-client-uncomplete-step').forEach(function (btn) {
			btn.addEventListener('click', function () {
				uncompleteStep(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.checklistdek-client-note-action').forEach(function (btn) {
			btn.addEventListener('click', function () {
				handleNoteAction(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.checklistdek-client-note-input').forEach(function (textarea) {
			textarea.addEventListener('input', function () {
				var match = String(textarea.id || '').match(/^checklistdek-client-note-(\d+)$/);
				if (!match) {
					return;
				}

				var stepIndex = parseInt(match[1], 10);
				noteDrafts[stepIndex] = textarea.value;
				updateNoteActionButton(stepIndex);
			});
		});

		root.querySelectorAll('.checklistdek-client-attach-screenshot').forEach(function (btn) {
			btn.addEventListener('click', function () {
				openMediaPicker(parseInt(btn.getAttribute('data-step'), 10));
			});
		});

		root.querySelectorAll('.checklistdek-client-remove-attachment').forEach(function (btn) {
			btn.addEventListener('click', function () {
				pendingAttachment = null;
				render();
			});
		});

		var dismissBtn = document.getElementById('checklistdek-client-dismiss-run');
		if (dismissBtn) {
			dismissBtn.addEventListener('click', function () {
				dismissRun(dismissBtn);
			});
		}
	}

	function notice(message, type) {
		var el = document.getElementById('checklistdek-client-panel-notice');
		if (!el) {
			return;
		}
		if (!message) {
			el.className = 'checklistdek-client-panel-notice';
			el.textContent = '';
			el.hidden = true;
			return;
		}
		el.hidden = false;
		el.className = 'checklistdek-client-panel-notice ' + (type || 'error');
		el.textContent = message;
	}

	function handleNoteAction(stepIndex, button) {
		var form = getNoteForm(stepIndex);
		var composer = form ? form.querySelector('.checklistdek-client-note-composer') : null;
		var textarea = form ? form.querySelector('.checklistdek-client-note-input') : null;
		var isOpen = composer && composer.classList.contains('is-open');
		var hasText = textarea && String(textarea.value || '').trim().length > 0;

		if (!isOpen) {
			setNoteComposerOpen(stepIndex, true);
			syncNoteComposerUi(stepIndex, true);
			if (textarea) {
				window.requestAnimationFrame(function () {
					textarea.focus();
				});
			}
			return;
		}

		if (hasText) {
			saveNote(stepIndex, button);
			return;
		}

		if (textarea) {
			textarea.focus();
		}
	}

	function openMediaPicker(stepIndex) {
		if (typeof wp === 'undefined' || !wp.media) {
			notice(strings.mediaError || 'Could not open media library.', 'error');
			return;
		}

		var frame = wp.media({
			title: strings.attachScreenshot || 'Attach screenshot',
			button: { text: strings.attachScreenshot || 'Attach screenshot' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var selection = frame.state().get('selection');
			var model = selection && selection.first ? selection.first() : null;

			if (!model) {
				notice(strings.mediaError || 'Could not open media library.', 'error');
				return;
			}

			var attachment = model.toJSON();
			var previewUrl = getAttachmentPreviewUrl(attachment);

			if (!attachment.id || !previewUrl) {
				notice(strings.mediaError || 'Could not open media library.', 'error');
				return;
			}

			pendingAttachment = {
				stepIndex: parseInt(stepIndex, 10),
				id: attachment.id,
				url: previewUrl
			};
			setNoteComposerOpen(stepIndex, true);
			setStepExpanded(stepIndex, true);
			render();
		});

		frame.open();
	}

	function saveNote(stepIndex, button) {
		var textarea = document.getElementById('checklistdek-client-note-' + stepIndex);
		var text = textarea ? textarea.value.trim() : '';
		var attachmentId = pendingAttachment && sameStepIndex(pendingAttachment.stepIndex, stepIndex) ? pendingAttachment.id : 0;
		var createdAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

		if (!text) {
			notice(strings.noteRequired || 'Type a note before saving.', 'error');
			return;
		}

		button.disabled = true;

		clientFetch('/client/run/steps/' + stepIndex + '/notes', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				text: text,
				attachment_id: attachmentId || 0,
				created_at: createdAt
			})
		}).then(function (response) {
			pendingAttachment = null;
			resetNoteComposer(stepIndex);
			run = response.run || run;
			checklistdekClient.run = run;
			var message = response.hub_synced
				? (strings.noteSaved || 'Note saved.')
				: (strings.noteSavedLocal || 'Note saved on this site. Hub sync will retry on the next checklist update.');
			notice(message, 'success');
			render();
		}).catch(function (error) {
			button.disabled = false;
			notice(error.message || strings.error || 'Could not save note.', 'error');
		});
	}

	function cloneRun(source) {
		return JSON.parse(JSON.stringify(source || {}));
	}

	function applyOptimisticStepUpdate(stepIndex, action) {
		var steps = run.steps || [];
		var user = checklistdekClient.currentUser || {};
		var now = new Date().toISOString().slice(0, 19).replace('T', ' ');

		steps.forEach(function (step) {
			if (parseInt(step.step_index, 10) !== parseInt(stepIndex, 10)) {
				return;
			}

			if (action === 'complete') {
				step.status = 'completed';
				step.manual_checked = true;
				step.completed_at = now;
				step.completed_by = {
					name: user.name || '',
					email: user.email || ''
				};
				return;
			}

			step.status = 'awaiting_manual';
			step.manual_checked = false;
			step.completed_at = '';
			step.completed_by = null;
		});

		run.steps = steps;

		if (steps.length > 0 && completedCount(steps) === steps.length) {
			run.run_status = 'completed';
			if (!run.completed_at) {
				run.completed_at = now;
			}
		} else if (action === 'uncomplete') {
			run.run_status = 'running';
			run.completed_at = '';
		}
	}

	function updateStepStatus(stepIndex, button, action) {
		if (stepIndex === null || stepIndex === undefined || isNaN(stepIndex)) {
			return;
		}

		var rollbackRun = cloneRun(run);

		applyOptimisticStepUpdate(stepIndex, action);

		if (action === 'complete') {
			setStepExpanded(stepIndex, false);
		} else {
			setStepExpanded(stepIndex, true);
		}

		button.disabled = true;
		notice('', '');
		render();

		clientFetch('/client/run/steps/' + stepIndex + '/' + action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			credentials: 'same-origin',
			body: JSON.stringify({})
		}).then(function (response) {
			run = response.run || null;
			if (!run) {
				root.innerHTML = '';
				return;
			}
			checklistdekClient.run = run;
			render();
		}).catch(function (error) {
			run = rollbackRun;
			checklistdekClient.run = run;
			button.disabled = false;
			render();
			notice(error.message || strings.error || 'Could not update this step.', 'error');
		});
	}

	function completeStep(stepIndex, button) {
		updateStepStatus(stepIndex, button, 'complete');
	}

	function uncompleteStep(stepIndex, button) {
		updateStepStatus(stepIndex, button, 'uncomplete');
	}

	function dismissRun(button) {
		button.disabled = true;

		clientFetch('/client/run/dismiss', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			credentials: 'same-origin',
			body: JSON.stringify({})
		}).then(function () {
			run = null;
			checklistdekClient.run = null;
			root.innerHTML = '';
			if (document.body) {
				document.body.classList.remove(
					'checklistdek-client-topbar-expanded',
					'checklistdek-client-topbar-collapsed',
					'checklistdek-client-dock-expanded',
					'checklistdek-client-dock-collapsed',
					'checklistdek-client-rail-open',
					'checklistdek-client-fullscreen-open'
				);
				document.body.style.removeProperty('--checklistdek-client-topbar-offset');
				document.body.style.removeProperty('--checklistdek-client-dock-offset');
				document.body.style.removeProperty('--checklistdek-client-rail-offset');
			}
			if (footerRoot) {
				footerRoot.hidden = false;
			}
		}).catch(function (error) {
			button.disabled = false;
			notice(error.message || strings.dismissError || 'Could not dismiss this checklist.', 'error');
		});
	}

	function onClientHeartbeatRestNonce(event, data) {
		if (data && data.rest_nonce) {
			setClientNonce(data.rest_nonce);
		}
	}

	if (window.jQuery) {
		// Must listen on bare `heartbeat-tick` — jQuery namespaced handlers do not run for WP core's trigger.
		window.jQuery(document).off('heartbeat-tick', onClientHeartbeatRestNonce).on('heartbeat-tick', onClientHeartbeatRestNonce);
	}

	refreshLayoutState();
	collapsed = layoutStartsCollapsed(panelLayout);
	loadPrefs();
	render();
})();
