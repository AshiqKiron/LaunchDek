(function () {
	'use strict';

	if (typeof launchdekClient === 'undefined') {
		return;
	}

	var root = document.getElementById('launchdek-client-panel-root');
	if (!root || !launchdekClient.run) {
		return;
	}

	var strings = launchdekClient.strings || {};
	var run = launchdekClient.run;
	var collapsed = false;
	var showAllSteps = false;
	var pendingAttachment = null;
	var STORAGE_KEY = 'launchdek_client_runner_prefs';

	function escHtml(value) {
		return String(value || '')
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
			if (prefs && typeof prefs.showAllSteps === 'boolean') {
				showAllSteps = prefs.showAllSteps;
			}
		} catch (e) {
			// Ignore invalid localStorage.
		}
	}

	function savePrefs() {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify({ showAllSteps: showAllSteps }));
		} catch (e) {
			// Ignore quota errors.
		}
	}

	function statusLabel(status) {
		if (status === 'completed') {
			return strings.completed || 'Completed';
		}
		if (status === 'awaiting_manual') {
			return strings.complete || 'Ready';
		}
		if (status === 'failed') {
			return strings.error || 'Failed';
		}
		if (status === 'running') {
			return strings.waiting || 'Waiting on agency';
		}
		return strings.pending || 'Pending';
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

	function renderNotes(notes) {
		if (!notes || !notes.length) {
			return '';
		}

		var items = notes.map(function (note) {
			var html = '<li class="launchdek-client-note">';
			if (note.user) {
				html += '<span class="launchdek-client-note-user">' + escHtml(note.user) + '</span>';
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

		return '<div class="launchdek-client-step-notes">' +
			'<h4 class="launchdek-client-step-notes-title">' + escHtml(strings.notesHeading || 'Notes') + '</h4>' +
			'<ul class="launchdek-client-note-list">' + items + '</ul>' +
		'</div>';
	}

	function renderNoteForm(step) {
		if (step.status !== 'awaiting_manual' || !step.can_complete) {
			return '';
		}

		var attachmentPreview = '';
		if (pendingAttachment && pendingAttachment.stepIndex === step.step_index) {
			attachmentPreview = '<div class="launchdek-client-note-attachment-preview">' +
				'<img src="' + escHtml(pendingAttachment.url) + '" alt="" />' +
				'<button type="button" class="button-link launchdek-client-remove-attachment" data-step="' + escHtml(step.step_index) + '">&times;</button>' +
			'</div>';
		}

		return '<div class="launchdek-client-note-form" data-step="' + escHtml(step.step_index) + '">' +
			'<label class="screen-reader-text" for="launchdek-client-note-' + escHtml(step.step_index) + '">' + escHtml(strings.addNote || 'Add note') + '</label>' +
			'<textarea id="launchdek-client-note-' + escHtml(step.step_index) + '" class="launchdek-client-note-input" rows="2" placeholder="' + escHtml(strings.notePlaceholder || 'Add a note about this step…') + '"></textarea>' +
			attachmentPreview +
			'<div class="launchdek-client-note-form-actions">' +
				'<button type="button" class="button button-small launchdek-client-attach-screenshot" data-step="' + escHtml(step.step_index) + '">' + escHtml(strings.attachScreenshot || 'Attach screenshot') + '</button>' +
				'<button type="button" class="button button-primary button-small launchdek-client-save-note" data-step="' + escHtml(step.step_index) + '">' + escHtml(strings.submitNote || 'Save note') + '</button>' +
			'</div>' +
		'</div>';
	}

	function shouldShowStep(step, index, steps, activeIndex) {
		if (showAllSteps) {
			return true;
		}
		if (step.status === 'awaiting_manual') {
			return true;
		}
		if (index === activeIndex && step.status !== 'completed') {
			return true;
		}
		return false;
	}

	function renderCollapsedStep(step) {
		return '<li class="launchdek-client-step is-collapsed-summary is-completed" data-step-index="' + escHtml(step.step_index) + '">' +
			'<span class="launchdek-client-step-summary-title">' + escHtml(step.title) + '</span>' +
			'<span class="launchdek-client-step-badge completed">' + escHtml(statusLabel('completed')) + '</span>' +
		'</li>';
	}

	function renderStep(step, isActive) {
		var classes = ['launchdek-client-step'];
		if (step.status === 'completed') {
			classes.push('is-completed');
		}
		if (step.status === 'awaiting_manual') {
			classes.push('is-active');
		}
		if (isActive) {
			classes.push('is-current');
		}

		var actions = '';
		if (step.deep_link && (isActive || step.status === 'awaiting_manual')) {
			actions += '<a class="button button-primary button-small launchdek-client-open-step" href="' + escHtml(step.deep_link) + '" target="_blank" rel="noopener">' + escHtml(strings.openStep || 'Open step') + '</a>';
		}
		if (step.status === 'awaiting_manual' && step.can_complete) {
			actions += '<button type="button" class="button button-small launchdek-client-complete-step" data-step="' + escHtml(step.step_index) + '">' + escHtml(strings.complete || 'Mark complete') + '</button>';
		}

		return '<li class="' + classes.join(' ') + '" data-step-index="' + escHtml(step.step_index) + '">' +
			'<div class="launchdek-client-step-head">' +
				'<h3 class="launchdek-client-step-title">' + escHtml(step.title) + '</h3>' +
				'<span class="launchdek-client-step-badge ' + escHtml(step.status) + '">' + escHtml(statusLabel(step.status)) + '</span>' +
			'</div>' +
			((isActive || step.status === 'awaiting_manual') && step.instructions ? '<p class="launchdek-client-step-instructions">' + escHtml(step.instructions) + '</p>' : '') +
			renderNotes(step.notes) +
			renderNoteForm(step) +
			(actions ? '<div class="launchdek-client-step-actions">' + actions + '</div>' : '') +
		'</li>';
	}

	function render() {
		var steps = run.steps || [];
		var done = completedCount(steps);
		var total = steps.length;
		var allDone = total > 0 && done === total;
		var activeIndex = activeStepIndex(steps);
		var percent = total > 0 ? Math.round((done / total) * 100) : 0;
		var currentDisplay = Math.min(activeIndex + 1, total);

		root.className = 'launchdek-client-panel-root' + (collapsed ? ' is-collapsed' : '');
		root.innerHTML =
			(collapsed
				? '<button type="button" class="launchdek-client-panel-tab" id="launchdek-client-panel-expand">' + escHtml(strings.expand || 'Expand') + '</button>'
				: '') +
			'<aside class="launchdek-client-panel' + (collapsed ? ' is-collapsed' : '') + '" aria-label="' + escHtml(strings.panelTitle || 'Agency Checklist') + '">' +
				'<div class="launchdek-client-panel-header">' +
					'<div>' +
						'<h2 class="launchdek-client-panel-title">' + escHtml(strings.panelTitle || 'Agency Checklist') + '</h2>' +
						'<p class="launchdek-client-panel-subtitle">' + escHtml(run.checklist_title || '') + '</p>' +
					'</div>' +
					'<button type="button" class="button button-small launchdek-client-panel-toggle" id="launchdek-client-panel-collapse">' + escHtml(strings.collapse || 'Collapse') + '</button>' +
				'</div>' +
				'<div class="launchdek-client-panel-progress-wrap">' +
					'<div class="launchdek-client-panel-progress-meta">' +
						'<span class="launchdek-client-panel-progress-label">' + escHtml(formatStepOf(currentDisplay, total)) + ' · ' + percent + '%</span>' +
						(!allDone ? '<button type="button" class="button-link launchdek-client-toggle-steps" id="launchdek-client-toggle-steps">' +
							escHtml(showAllSteps ? (strings.showFocused || 'Focus current step') : (strings.showAll || 'Show all steps')) +
						'</button>' : '') +
					'</div>' +
					'<div class="launchdek-client-progress-bar" role="progressbar" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100">' +
						'<span class="launchdek-client-progress-fill" style="width:' + percent + '%"></span>' +
					'</div>' +
				'</div>' +
				'<div class="launchdek-client-panel-body">' +
					(allDone
						? '<div class="launchdek-client-panel-notice success">' + escHtml(strings.runComplete || 'Checklist complete — great work!') + '</div>'
						: '<ol class="launchdek-client-step-list">' + steps.map(function (step, index) {
							if (!showAllSteps && step.status === 'completed') {
								return renderCollapsedStep(step);
							}
							if (!shouldShowStep(step, index, steps, activeIndex)) {
								return '';
							}
							return renderStep(step, index === activeIndex);
						}).join('') + '</ol>') +
					'<div id="launchdek-client-panel-notice"></div>' +
				'</div>' +
			'</aside>';

		bindActions();
	}

	function bindActions() {
		var collapseBtn = document.getElementById('launchdek-client-panel-collapse');
		var expandBtn = document.getElementById('launchdek-client-panel-expand');
		var toggleStepsBtn = document.getElementById('launchdek-client-toggle-steps');

		if (collapseBtn) {
			collapseBtn.addEventListener('click', function () {
				collapsed = true;
				render();
			});
		}

		if (expandBtn) {
			expandBtn.addEventListener('click', function () {
				collapsed = false;
				render();
			});
		}

		if (toggleStepsBtn) {
			toggleStepsBtn.addEventListener('click', function () {
				showAllSteps = !showAllSteps;
				savePrefs();
				render();
			});
		}

		root.querySelectorAll('.launchdek-client-complete-step').forEach(function (btn) {
			btn.addEventListener('click', function () {
				completeStep(parseInt(btn.getAttribute('data-step'), 10), btn);
			});
		});

		root.querySelectorAll('.launchdek-client-save-note').forEach(function (btn) {
			btn.addEventListener('click', function () {
				saveNote(parseInt(btn.getAttribute('data-step'), 10), btn);
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
	}

	function notice(message, type) {
		var el = document.getElementById('launchdek-client-panel-notice');
		if (!el) {
			return;
		}
		el.className = 'launchdek-client-panel-notice ' + (type || 'error');
		el.textContent = message || '';
	}

	function openMediaPicker(stepIndex) {
		if (typeof wp === 'undefined' || !wp.media) {
			notice(strings.error || 'Could not open media library.', 'error');
			return;
		}

		var frame = wp.media({
			title: strings.attachScreenshot || 'Attach screenshot',
			button: { text: strings.attachScreenshot || 'Attach screenshot' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			pendingAttachment = {
				stepIndex: stepIndex,
				id: attachment.id,
				url: attachment.url
			};
			render();
		});

		frame.open();
	}

	function saveNote(stepIndex, button) {
		var textarea = document.getElementById('launchdek-client-note-' + stepIndex);
		var text = textarea ? textarea.value.trim() : '';
		var attachmentId = pendingAttachment && pendingAttachment.stepIndex === stepIndex ? pendingAttachment.id : 0;

		if (!text && !attachmentId) {
			notice(strings.notePlaceholder || 'Add a note about this step…', 'error');
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
				attachment_id: attachmentId || 0
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
			run = response.run || run;
			launchdekClient.run = run;
			notice(strings.noteSaved || 'Note saved.', 'success');
			render();
		}).catch(function (error) {
			button.disabled = false;
			notice(error.message || strings.error || 'Could not save note.', 'error');
		});
	}

	function completeStep(stepIndex, button) {
		if (!stepIndex && stepIndex !== 0) {
			return;
		}

		button.disabled = true;

		var base = String(launchdekClient.restUrl || '').replace(/\/$/, '');
		fetch(base + '/client/run/steps/' + stepIndex + '/complete', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': launchdekClient.nonce
			},
			credentials: 'same-origin'
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
			button.disabled = false;
			notice(error.message || strings.error || 'Could not update this step.', 'error');
		});
	}

	loadPrefs();
	render();
})();
