(function () {
	'use strict';

	if (typeof launchdekClient === 'undefined') {
		return;
	}

	var footerRoot = document.getElementById('launchdek-client-panel-root');
	if (!footerRoot || !launchdekClient.run) {
		return;
	}

	var root = footerRoot;
	var strings = launchdekClient.strings || {};
	var run = launchdekClient.run;
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
	var notesGloballyMinimized = false;
	var STORAGE_KEY = 'launchdek_client_runner_prefs_v2';

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

		root.querySelectorAll('.launchdek-client-note-input').forEach(function (textarea) {
			var match = String(textarea.id || '').match(/^launchdek-client-note-(\d+)$/);
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

		root.querySelectorAll('.launchdek-client-note-input').forEach(function (textarea) {
			var match = String(textarea.id || '').match(/^launchdek-client-note-(\d+)$/);
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
		if (notesGloballyMinimized) {
			return false;
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

	function hasOpenComposerState() {
		if (notesGloballyMinimized) {
			return false;
		}

		var steps = run.steps || [];
		var i;

		for (i = 0; i < steps.length; i++) {
			if (noteComposerOpen(parseInt(steps[i].step_index, 10))) {
				return true;
			}
		}

		return false;
	}

	function hasAnyNotesOrOpenComposers() {
		var steps = run.steps || [];
		var i;
		var stepIndex;

		for (i = 0; i < steps.length; i++) {
			if (stepHasSavedNotes(steps[i])) {
				return true;
			}
			stepIndex = parseInt(steps[i].step_index, 10);
			if (noteDrafts[stepIndex] && String(noteDrafts[stepIndex]).trim()) {
				return true;
			}
		}

		if (pendingAttachment) {
			return true;
		}

		return hasOpenComposerState();
	}

	function allNotesMinimized() {
		if (!hasAnyNotesOrOpenComposers()) {
			return true;
		}

		var steps = run.steps || [];
		var i;
		var stepIndex;

		for (i = 0; i < steps.length; i++) {
			stepIndex = parseInt(steps[i].step_index, 10);
			if (stepHasSavedNotes(steps[i]) && !isNoteSectionCollapsed(stepIndex)) {
				return false;
			}
		}

		return notesGloballyMinimized;
	}

	function minimizeAllNotes() {
		var steps = run.steps || [];

		captureNoteDrafts();
		notesGloballyMinimized = true;
		steps.forEach(function (step) {
			var stepIndex = parseInt(step.step_index, 10);
			if (stepHasSavedNotes(step)) {
				collapsedNoteSections[String(stepIndex)] = true;
			}
			setNoteComposerOpen(stepIndex, false);
		});
		savePrefs();
		render();
	}

	function expandAllNotes() {
		notesGloballyMinimized = false;
		collapsedNoteSections = {};
		savePrefs();
		render();
	}

	function toggleAllNotes() {
		if (allNotesMinimized()) {
			expandAllNotes();
			return;
		}
		minimizeAllNotes();
	}

	function getNoteForm(stepIndex) {
		if (!root) {
			return null;
		}
		return root.querySelector('.launchdek-client-note-form[data-step="' + stepIndex + '"]');
	}

	function updateNoteActionButton(stepIndex) {
		var form = getNoteForm(stepIndex);
		if (!form) {
			return;
		}

		var btn = form.querySelector('.launchdek-client-note-action');
		var textarea = form.querySelector('.launchdek-client-note-input');
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

		var composer = form.querySelector('.launchdek-client-note-composer');
		var attachBtn = form.querySelector('.launchdek-client-attach-screenshot');
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

		var textarea = document.getElementById('launchdek-client-note-' + stepIndex);
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
			meta += '<p class="launchdek-client-run-complete-meta">' +
				'<span class="launchdek-client-run-complete-label">' + escHtml(strings.startedLabel || 'Started:') + '</span> ' +
				escHtml(started) +
			'</p>';
		}

		if (completed) {
			meta += '<p class="launchdek-client-run-complete-meta">' +
				'<span class="launchdek-client-run-complete-label">' + escHtml(strings.completedLabel || 'Completed:') + '</span> ' +
				escHtml(completed) +
			'</p>';
		}

		return '<div class="launchdek-client-run-complete-summary">' +
			'<p class="launchdek-client-run-complete-title">' + escHtml(strings.runComplete || 'Checklist complete!') + '</p>' +
			meta +
			'<button type="button" class="button button-secondary launchdek-client-dismiss-run" id="launchdek-client-dismiss-run">' +
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

		return '<p class="launchdek-client-step-completed-meta">' + escHtml(text) + '</p>';
	}

	function renderChevron(expanded) {
		var path = expanded ? 'M2 6.5 5 3.5 8 6.5z' : 'M2 3.5 5 6.5 8 3.5z';
		return '<svg class="launchdek-client-step-chevron" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path fill="currentColor" d="' + path + '"/></svg>';
	}

	function renderIconButton(className, label, iconMarkup, stepIndex, tooltipPosition) {
		var tooltipClass = '';

		if (tooltipPosition === 'left') {
			tooltipClass = ' launchdek-client-tooltip-left';
		} else if (tooltipPosition === 'right') {
			tooltipClass = ' launchdek-client-tooltip-right';
		}

		return '<button type="button" class="launchdek-client-icon-btn launchdek-client-has-tooltip' + tooltipClass + ' ' + className + '" data-step="' + escHtml(stepIndex) + '" data-tooltip="' + escHtml(label) + '" aria-label="' + escHtml(label) + '">' + iconMarkup + '</button>';
	}

	function renderTickIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
	}

	function renderNoteIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
	}

	function renderSaveIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
	}

	function renderAttachIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
	}

	function renderExternalIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
	}

	function renderUndoIcon() {
		return '<svg class="launchdek-client-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.5 8c-2.65 0-5.05 1.04-6.9 2.9L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.31 0 6 2.69 6 6s-2.69 6-6 6c-1.66 0-3.14-.69-4.24-1.76L5.52 18.5C7.05 20.36 9.62 21.5 12.5 21.5c4.69 0 8.5-3.81 8.5-8.5S17.19 8 12.5 8z"/></svg>';
	}

	function renderCompletedIndicator(step) {
		var tick = '<span class="launchdek-client-step-done-icon" aria-hidden="true">' + renderTickIcon() + '</span>';

		if (!stepCanUndo(step)) {
			return '<span class="launchdek-client-step-complete-state is-readonly" aria-label="' + escHtml(strings.completed || 'Completed') + '">' + tick + '</span>';
		}

		return '<div class="launchdek-client-step-complete-state">' +
			tick +
			renderIconButton(
				'launchdek-client-uncomplete-step is-undo-action',
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
				'launchdek-client-complete-step is-complete-action',
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

		return '<a class="launchdek-client-icon-btn launchdek-client-has-tooltip launchdek-client-tooltip-left launchdek-client-step-settings-link" href="' + escHtml(step.deep_link) + '" target="_blank" rel="noopener" data-tooltip="' + escHtml(label) + '" aria-label="' + escHtml(label) + '">' + renderExternalIcon() + '</a>';
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
			var html = '<li class="launchdek-client-note">';

			if (meta) {
				html += '<span class="launchdek-client-note-meta">' + escHtml(meta) + '</span>';
			}
			if (note.text) {
				html += '<p class="launchdek-client-note-text">' + escHtml(note.text) + '</p>';
			}
			if (note.attachment_url) {
				html += '<a class="launchdek-client-note-attachment" href="' + escHtml(note.attachment_url) + '" target="_blank" rel="noopener">' +
					'<img src="' + escHtml(note.attachment_url) + '" alt="" loading="lazy" />' +
				'</a>';
			}
			html += '</li>';
			return html;
		}).join('');

		var collapsed = isNoteSectionCollapsed(stepIndex);
		var title = strings.notesHeading || 'Notes';
		var countLabel = strings.notesCount || '%1$s (%2$s)';

		return '<div class="launchdek-client-step-notes' + (collapsed ? ' is-collapsed' : '') + '">' +
			'<button type="button" class="launchdek-client-step-notes-toggle" data-step="' + escHtml(stepIndex) + '" aria-expanded="' + (collapsed ? 'false' : 'true') + '">' +
				'<span class="launchdek-client-step-notes-title">' + escHtml(countLabel.replace('%1$s', title).replace('%2$s', String(validNotes.length))) + '</span>' +
				renderChevron(!collapsed) +
			'</button>' +
			'<ul class="launchdek-client-note-list">' + items + '</ul>' +
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
			attachmentPreview = '<div class="launchdek-client-note-attachment-preview">' +
				'<img src="' + escHtml(pendingAttachment.url) + '" alt="" />' +
				'<button type="button" class="button-link launchdek-client-remove-attachment" data-step="' + escHtml(stepIndex) + '" title="' + escHtml(strings.removeAttachment || 'Remove screenshot') + '" aria-label="' + escHtml(strings.removeAttachment || 'Remove screenshot') + '">&times;</button>' +
			'</div>';
		}

		return '<div class="launchdek-client-note-form" data-step="' + escHtml(stepIndex) + '">' +
			'<div class="launchdek-client-note-form-actions">' +
				'<button type="button" class="launchdek-client-icon-btn launchdek-client-has-tooltip launchdek-client-note-action ' + actionClass + '" data-step="' + escHtml(stepIndex) + '" data-tooltip="' + escHtml(actionLabel) + '" aria-label="' + escHtml(actionLabel) + '">' + actionIcon + '</button>' +
				renderIconButton('launchdek-client-attach-screenshot is-attach-action', strings.attachScreenshot || 'Attach screenshot', renderAttachIcon(), stepIndex, 'right') +
			'</div>' +
			'<div class="launchdek-client-note-composer' + (isOpen ? ' is-open' : '') + '">' +
				'<div class="launchdek-client-note-composer-inner">' +
					'<label class="screen-reader-text" for="launchdek-client-note-' + escHtml(stepIndex) + '">' + escHtml(strings.addNote || 'Add note') + '</label>' +
					'<textarea id="launchdek-client-note-' + escHtml(stepIndex) + '" class="launchdek-client-note-input" rows="2" placeholder="' + escHtml(strings.notePlaceholder || 'Add a note about this step…') + '"></textarea>' +
					attachmentPreview +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function renderStepBody(step, index, activeIndex) {
		var body = '';

		if (step.instructions) {
			body += '<p class="launchdek-client-step-instructions">' + escHtml(step.instructions) + '</p>';
		}
		body += renderCompletionMeta(step);
		body += renderNotes(step.notes, parseInt(step.step_index, 10));
		body += renderNoteForm(step, index, activeIndex);

		return body;
	}

	function renderStep(step, isActive, index, activeIndex) {
		var expanded = isStepExpanded(step, index, activeIndex);
		var upcoming = isStepUpcoming(step, index, activeIndex);
		var classes = ['launchdek-client-step'];

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
		var settingsLink = renderSettingsLink(step);
		var badgeLabel = statusLabel(step.status);
		var badgeStatus = step.status === 'pending' ? 'awaiting_manual' : step.status;
		var badgeHtml = badgeLabel
			? '<span class="launchdek-client-step-badge ' + escHtml(badgeStatus) + '">' + escHtml(badgeLabel) + '</span>'
			: '';

		return '<li class="' + classes.join(' ') + '" data-step-index="' + escHtml(step.step_index) + '"' + (upcoming ? ' aria-disabled="true"' : '') + '>' +
			'<div class="launchdek-client-step-head">' +
				'<button type="button" class="launchdek-client-step-toggle" data-step="' + escHtml(step.step_index) + '" aria-expanded="' + (expanded ? 'true' : 'false') + '" aria-label="' + escHtml(strings.toggleStep || 'Toggle step details') + '">' +
					renderChevron(expanded) +
				'</button>' +
				'<h3 class="launchdek-client-step-title">' + escHtml(step.title) + '</h3>' +
				'<div class="launchdek-client-step-head-actions">' +
					settingsLink +
					badgeHtml +
					completeButton +
				'</div>' +
			'</div>' +
			(body ? '<div class="launchdek-client-step-body">' + body + '</div>' : '') +
		'</li>';
	}

	function resolvePanelLayout() {
		return String(
			(run && run.panel_layout) ||
			launchdekClient.panelLayout ||
			'sidebar'
		);
	}

	function refreshLayoutState() {
		panelLayout = resolvePanelLayout();
	}

	function layoutCollapsesOnToggle(layout) {
		return TOGGLE_LAYOUTS.indexOf(layout) !== -1;
	}

	function layoutStartsCollapsed(layout) {
		return layoutCollapsesOnToggle(layout);
	}

	function updateRootTarget() {
		if (panelLayout === 'inline_metabox') {
			var metabox = document.getElementById('launchdek-client-metabox-root');
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
			'launchdek-client-topbar-expanded',
			'launchdek-client-topbar-collapsed',
			'launchdek-client-dock-expanded',
			'launchdek-client-dock-collapsed',
			'launchdek-client-rail-open',
			'launchdek-client-fullscreen-open'
		);
		document.body.style.removeProperty('--launchdek-client-topbar-offset');
		document.body.style.removeProperty('--launchdek-client-dock-offset');
		document.body.style.removeProperty('--launchdek-client-rail-offset');

		if (panelLayout === 'live_topbar') {
			document.body.classList.add(
				collapsed ? 'launchdek-client-topbar-collapsed' : 'launchdek-client-topbar-expanded'
			);
			window.requestAnimationFrame(function () {
				var height = root ? root.offsetHeight : 0;
				document.body.style.setProperty(
					'--launchdek-client-topbar-offset',
					(height > 0 ? height : 44) + 'px'
				);
			});
			return;
		}

		if (panelLayout === 'bottom_dock') {
			document.body.classList.add(
				collapsed ? 'launchdek-client-dock-collapsed' : 'launchdek-client-dock-expanded'
			);
			window.requestAnimationFrame(function () {
				var height = root ? root.offsetHeight : 0;
				document.body.style.setProperty(
					'--launchdek-client-dock-offset',
					(height > 0 ? height : 44) + 'px'
				);
			});
			return;
		}

		if ((panelLayout === 'left_sidebar' || panelLayout === 'split_panel') && !collapsed) {
			document.body.classList.add('launchdek-client-rail-open');
			document.body.style.setProperty(
				'--launchdek-client-rail-offset',
				panelLayout === 'split_panel' ? '400px' : '320px'
			);
		}

		if (panelLayout === 'fullscreen' && !collapsed) {
			document.body.classList.add('launchdek-client-fullscreen-open');
		}
	}

	function renderProgressBar(percent) {
		return '<div class="launchdek-client-progress-bar" role="progressbar" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100">' +
			'<span class="launchdek-client-progress-fill" style="width:' + percent + '%"></span>' +
		'</div>';
	}

	function renderPanelBody(steps, allDone, activeIndex) {
		return (allDone ? renderRunCompleteSummary(steps) : '') +
			(allNotesMinimized()
				? '<div class="launchdek-client-panel-notes-toolbar">' +
					'<button type="button" class="button-link launchdek-client-toggle-all-notes" id="launchdek-client-toggle-all-notes">' +
						escHtml(strings.expandAllNotes || 'Show all notes') +
					'</button>' +
				'</div>'
				: '') +
			'<ol class="launchdek-client-step-list">' + steps.map(function (step, index) {
				return renderStep(step, index === activeIndex, index, activeIndex);
			}).join('') + '</ol>' +
			'<div id="launchdek-client-panel-notice" class="launchdek-client-panel-notice" hidden></div>';
	}

	function getCurrentStepTitle(steps, activeIndex) {
		if (!steps.length || activeIndex < 0 || !steps[activeIndex]) {
			return '';
		}

		return steps[activeIndex].title || '';
	}

	function renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, toggleLabel) {
		return '<div class="launchdek-client-panel-header">' +
				'<div>' +
					'<h2 class="launchdek-client-panel-title">' + escHtml(strings.panelTitle || 'Agency Checklist') + '</h2>' +
					'<p class="launchdek-client-panel-subtitle">' + escHtml(run.checklist_title || '') + '</p>' +
				'</div>' +
				'<button type="button" class="button button-small launchdek-client-panel-toggle" id="launchdek-client-panel-collapse">' + escHtml(toggleLabel || strings.collapse || 'Collapse') + '</button>' +
			'</div>' +
			'<div class="launchdek-client-panel-progress-wrap">' +
				'<div class="launchdek-client-panel-progress-meta">' +
					'<span class="launchdek-client-panel-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
				'</div>' +
				renderProgressBar(percent) +
			'</div>' +
			'<div class="launchdek-client-panel-body">' +
				renderPanelBody(steps, allDone, activeIndex) +
			'</div>';
	}

	function renderFocusBody(steps, allDone, activeIndex) {
		if (allDone) {
			return renderRunCompleteSummary(steps) +
				'<div id="launchdek-client-panel-notice" class="launchdek-client-panel-notice" hidden></div>';
		}

		var step = steps[activeIndex];
		if (!step) {
			return renderPanelBody(steps, allDone, activeIndex);
		}

		return '<ol class="launchdek-client-step-list launchdek-client-step-list-focus">' +
			renderStep(step, true, activeIndex, activeIndex) +
		'</ol>' +
		'<div id="launchdek-client-panel-notice" class="launchdek-client-panel-notice" hidden></div>';
	}

	function renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, options) {
		options = options || {};
		var side = options.side || 'right';
		var variant = options.variant || 'default';
		var toggleLabel = options.toggleLabel || strings.collapse || 'Collapse';
		var tabSide = side === 'left' ? ' is-left-tab' : '';
		var panelClasses = 'launchdek-client-panel' +
			(side === 'left' ? ' is-left' : '') +
			(variant === 'split' ? ' is-split' : '') +
			(variant === 'metabox' ? ' is-metabox' : '') +
			(variant === 'focus' ? ' is-focus' : '') +
			(collapsed ? ' is-collapsed' : '');
		var body = variant === 'focus'
			? renderFocusBody(steps, allDone, activeIndex)
			: renderPanelBody(steps, allDone, activeIndex);

		return (collapsed && variant !== 'metabox'
			? '<button type="button" class="launchdek-client-panel-tab' + tabSide + '" id="launchdek-client-panel-expand">' + escHtml(strings.expand || 'Expand') + '</button>'
			: '') +
			'<aside class="' + panelClasses + '" aria-label="' + escHtml(strings.panelTitle || 'Agency Checklist') + '">' +
				'<div class="launchdek-client-panel-header">' +
					'<div>' +
						'<h2 class="launchdek-client-panel-title">' + escHtml(strings.panelTitle || 'Agency Checklist') + '</h2>' +
						'<p class="launchdek-client-panel-subtitle">' + escHtml(run.checklist_title || '') + '</p>' +
					'</div>' +
					'<button type="button" class="button button-small launchdek-client-panel-toggle" id="launchdek-client-panel-collapse">' + escHtml(toggleLabel) + '</button>' +
				'</div>' +
				'<div class="launchdek-client-panel-progress-wrap">' +
					'<div class="launchdek-client-panel-progress-meta">' +
						'<span class="launchdek-client-panel-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
					'</div>' +
					renderProgressBar(percent) +
				'</div>' +
				'<div class="launchdek-client-panel-body">' +
					body +
				'</div>' +
			'</aside>';
	}

	function renderBarLayout(position, steps, allDone, activeIndex, percent, currentDisplay, total) {
		var isBottom = position === 'bottom';
		var shellClass = isBottom ? 'launchdek-client-bottom-dock' : 'launchdek-client-topbar';
		var drawerClass = isBottom ? 'launchdek-client-bottom-dock-drawer' : 'launchdek-client-topbar-drawer';
		var currentTitle = getCurrentStepTitle(steps, activeIndex);
		var toggleLabel = collapsed
			? (strings.showSteps || 'Show steps')
			: (strings.hideSteps || 'Hide steps');

		return '<div class="' + shellClass + (collapsed ? ' is-collapsed' : ' is-expanded') + '" role="region" aria-label="' + escHtml(strings.panelTitle || 'Agency Checklist') + '">' +
			'<div class="' + shellClass + '-inner">' +
				'<div class="' + shellClass + '-copy">' +
					'<span class="' + shellClass + '-kicker">' + escHtml(strings.panelTitle || 'Agency Checklist') + '</span>' +
					'<strong class="' + shellClass + '-title">' + escHtml(run.checklist_title || '') + '</strong>' +
					'<span class="' + shellClass + '-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
				'</div>' +
				'<div class="' + shellClass + '-progress">' + renderProgressBar(percent) + '</div>' +
				(currentTitle && !allDone
					? '<span class="' + shellClass + '-current" title="' + escHtml(currentTitle) + '">' +
						escHtml(strings.currentStep || 'Current step') + ': ' + escHtml(currentTitle) +
					'</span>'
					: '') +
				'<button type="button" class="button button-small launchdek-client-panel-toggle" id="launchdek-client-panel-collapse">' + escHtml(toggleLabel) + '</button>' +
			'</div>' +
			'<div class="' + drawerClass + (collapsed ? ' is-collapsed' : '') + '">' +
				'<div class="launchdek-client-panel-body">' +
					renderPanelBody(steps, allDone, activeIndex) +
				'</div>' +
			'</div>' +
		'</div>';
	}

	function renderFloatingPillLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		if (collapsed) {
			return '<div class="launchdek-client-floating-pill is-collapsed">' +
				'<button type="button" class="launchdek-client-pill-trigger" id="launchdek-client-panel-expand">' +
					escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%' +
				'</button>' +
			'</div>';
		}

		return '<div class="launchdek-client-floating-pill is-expanded">' +
			renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, {
				variant: 'default',
				toggleLabel: strings.collapse || 'Collapse'
			}) +
		'</div>';
	}

	function renderToastLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		var currentTitle = getCurrentStepTitle(steps, activeIndex);

		if (collapsed) {
			return '<div class="launchdek-client-toast is-collapsed">' +
				'<button type="button" class="launchdek-client-toast-trigger" id="launchdek-client-panel-expand">' +
					escHtml(formatStepOf(currentDisplay, total)) +
					(currentTitle ? ' — ' + escHtml(currentTitle) : '') +
				'</button>' +
			'</div>';
		}

		return '<div class="launchdek-client-toast is-expanded">' +
			'<div class="launchdek-client-toast-panel">' +
				renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, strings.collapse || 'Collapse') +
			'</div>' +
		'</div>';
	}

	function renderAdminFlyoutLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		if (collapsed) {
			return '';
		}

		return '<div class="launchdek-client-admin-flyout" role="dialog" aria-label="' + escHtml(strings.panelTitle || 'Agency Checklist') + '">' +
			renderPanelChrome(steps, allDone, activeIndex, percent, currentDisplay, total, strings.collapse || 'Collapse') +
		'</div>';
	}

	function renderFullscreenLayout(steps, allDone, activeIndex, percent, currentDisplay, total) {
		if (collapsed) {
			return '<button type="button" class="button launchdek-client-fullscreen-open" id="launchdek-client-panel-expand">' +
				escHtml(strings.openChecklist || 'Open checklist') +
			'</button>';
		}

		return '<div class="launchdek-client-fullscreen is-open">' +
			'<button type="button" class="launchdek-client-fullscreen-backdrop" id="launchdek-client-fullscreen-backdrop" aria-label="' + escHtml(strings.collapse || 'Collapse') + '"></button>' +
			'<div class="launchdek-client-fullscreen-dialog" role="dialog" aria-label="' + escHtml(strings.panelTitle || 'Agency Checklist') + '">' +
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
			case 'left_sidebar':
				return renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, { side: 'left' });
			case 'split_panel':
				return renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, { side: 'left', variant: 'split' });
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
				return renderSidebarShell(steps, allDone, activeIndex, percent, currentDisplay, total, { side: 'right' });
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
		var layoutClass = 'launchdek-client-layout-' + panelLayout;
		var rootClasses = 'launchdek-client-panel-root ' + layoutClass;

		if (collapsed && !layoutCollapsesOnToggle(panelLayout)) {
			rootClasses += ' is-collapsed';
		}
		if (panelLayout === 'admin_menu' && collapsed) {
			rootClasses += ' is-admin-hidden';
		}

		root.className = rootClasses;
		root.innerHTML = renderLayoutMarkup(steps, allDone, activeIndex, percent, currentDisplay, total);

		syncBodyClasses();
		bindActions();
		restoreNoteDrafts();
		root.querySelectorAll('.launchdek-client-note-form').forEach(function (form) {
			var stepIndex = parseInt(form.getAttribute('data-step'), 10);
			if (isNaN(stepIndex)) {
				return;
			}
			syncNoteComposerUi(stepIndex, noteComposerOpen(stepIndex));
			updateNoteActionButton(stepIndex);
		});
	}

	function bindActions() {
		var collapseBtn = document.getElementById('launchdek-client-panel-collapse');
		var expandBtn = document.getElementById('launchdek-client-panel-expand');

		if (collapseBtn) {
			collapseBtn.addEventListener('click', function () {
				collapsed = layoutCollapsesOnToggle(panelLayout) ? !collapsed : true;
				render();
			});
		}

		if (expandBtn) {
			expandBtn.addEventListener('click', function () {
				collapsed = false;
				render();
			});
		}

		var adminBarLink = document.querySelector('#wp-admin-bar-launchdek-client-checklist > a');
		if (adminBarLink) {
			adminBarLink.addEventListener('click', function (event) {
				event.preventDefault();
				collapsed = !collapsed;
				render();
			});
		}

		var fullscreenBackdrop = document.getElementById('launchdek-client-fullscreen-backdrop');
		if (fullscreenBackdrop) {
			fullscreenBackdrop.addEventListener('click', function () {
				collapsed = true;
				render();
			});
		}

		root.querySelectorAll('.launchdek-client-step-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				toggleStepExpanded(parseInt(btn.getAttribute('data-step'), 10));
			});
		});

		root.querySelectorAll('.launchdek-client-step-notes-toggle').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var stepIndex = parseInt(btn.getAttribute('data-step'), 10);
				var collapsed = !isNoteSectionCollapsed(stepIndex);
				setNoteSectionCollapsed(stepIndex, collapsed);
				if (!collapsed) {
					notesGloballyMinimized = false;
				}
				render();
			});
		});

		var toggleAllNotesBtn = document.getElementById('launchdek-client-toggle-all-notes');
		if (toggleAllNotesBtn) {
			toggleAllNotesBtn.addEventListener('click', function () {
				toggleAllNotes();
			});
		}

		root.querySelectorAll('.launchdek-client-complete-step').forEach(function (btn) {
			btn.addEventListener('click', function () {
				completeStep(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.launchdek-client-uncomplete-step').forEach(function (btn) {
			btn.addEventListener('click', function () {
				uncompleteStep(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.launchdek-client-note-action').forEach(function (btn) {
			btn.addEventListener('click', function () {
				handleNoteAction(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.launchdek-client-note-input').forEach(function (textarea) {
			textarea.addEventListener('input', function () {
				var match = String(textarea.id || '').match(/^launchdek-client-note-(\d+)$/);
				if (!match) {
					return;
				}

				var stepIndex = parseInt(match[1], 10);
				noteDrafts[stepIndex] = textarea.value;
				updateNoteActionButton(stepIndex);
			});
		});

		root.querySelectorAll('.launchdek-client-attach-screenshot').forEach(function (btn) {
			btn.addEventListener('click', function () {
				openMediaPicker(parseInt(btn.getAttribute('data-step'), 10));
			});
		});

		root.querySelectorAll('.launchdek-client-remove-attachment').forEach(function (btn) {
			btn.addEventListener('click', function () {
				pendingAttachment = null;
				render();
			});
		});

		var dismissBtn = document.getElementById('launchdek-client-dismiss-run');
		if (dismissBtn) {
			dismissBtn.addEventListener('click', function () {
				dismissRun(dismissBtn);
			});
		}
	}

	function notice(message, type) {
		var el = document.getElementById('launchdek-client-panel-notice');
		if (!el) {
			return;
		}
		if (!message) {
			el.className = 'launchdek-client-panel-notice';
			el.textContent = '';
			el.hidden = true;
			return;
		}
		el.hidden = false;
		el.className = 'launchdek-client-panel-notice ' + (type || 'error');
		el.textContent = message;
	}

	function handleNoteAction(stepIndex, button) {
		var form = getNoteForm(stepIndex);
		var composer = form ? form.querySelector('.launchdek-client-note-composer') : null;
		var textarea = form ? form.querySelector('.launchdek-client-note-input') : null;
		var isOpen = composer && composer.classList.contains('is-open');
		var hasText = textarea && String(textarea.value || '').trim().length > 0;

		if (!isOpen) {
			notesGloballyMinimized = false;
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
		var textarea = document.getElementById('launchdek-client-note-' + stepIndex);
		var text = textarea ? textarea.value.trim() : '';
		var attachmentId = pendingAttachment && sameStepIndex(pendingAttachment.stepIndex, stepIndex) ? pendingAttachment.id : 0;
		var createdAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

		if (!text) {
			notice(strings.noteRequired || 'Type a note before saving.', 'error');
			return;
		}

		button.disabled = true;

		var base = String(launchdekClient.restUrl || '').replace(/\/$/, '');
		fetch(base + '/client/run/steps/' + stepIndex + '/notes', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': launchdekClient.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				text: text,
				attachment_id: attachmentId || 0,
				created_at: createdAt
			})
		}).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) {
					throw new Error((body && body.message) || strings.error || 'Could not save note.');
				}
				return body;
			});
		}).then(function (response) {
			pendingAttachment = null;
			resetNoteComposer(stepIndex);
			run = response.run || run;
			launchdekClient.run = run;
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
		var user = launchdekClient.currentUser || {};
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

		var base = String(launchdekClient.restUrl || '').replace(/\/$/, '');
		fetch(base + '/client/run/steps/' + stepIndex + '/' + action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': launchdekClient.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify({})
		}).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) {
					throw new Error((body && body.message) || strings.error || 'Could not update this step.');
				}
				return body;
			});
		}).then(function (response) {
			run = response.run || null;
			if (!run) {
				root.innerHTML = '';
				return;
			}
			launchdekClient.run = run;
			render();
		}).catch(function (error) {
			run = rollbackRun;
			launchdekClient.run = run;
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

		var base = String(launchdekClient.restUrl || '').replace(/\/$/, '');
		fetch(base + '/client/run/dismiss', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': launchdekClient.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify({})
		}).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) {
					throw new Error((body && body.message) || strings.dismissError || 'Could not dismiss this checklist.');
				}
				return body;
			});
		}).then(function () {
			run = null;
			launchdekClient.run = null;
			root.innerHTML = '';
			if (document.body) {
				document.body.classList.remove(
					'launchdek-client-topbar-expanded',
					'launchdek-client-topbar-collapsed',
					'launchdek-client-dock-expanded',
					'launchdek-client-dock-collapsed',
					'launchdek-client-rail-open',
					'launchdek-client-fullscreen-open'
				);
				document.body.style.removeProperty('--launchdek-client-topbar-offset');
				document.body.style.removeProperty('--launchdek-client-dock-offset');
				document.body.style.removeProperty('--launchdek-client-rail-offset');
			}
			if (footerRoot) {
				footerRoot.hidden = false;
			}
		}).catch(function (error) {
			button.disabled = false;
			notice(error.message || strings.dismissError || 'Could not dismiss this checklist.', 'error');
		});
	}

	refreshLayoutState();
	collapsed = layoutStartsCollapsed(panelLayout);
	loadPrefs();
	render();
})();
