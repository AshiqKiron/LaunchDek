/**
 * Private Agency Vault UI (Checklists → Templates tab).
 */
(function () {
	'use strict';

	if (typeof checklistdekAdmin === 'undefined' || typeof checklistdekAdminVault === 'undefined') {
		return;
	}

	var strings = checklistdekAdminVault.strings || {};

	function escHtml(value) {
		var div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	function getRestNonce() {
		return checklistdekAdmin.nonce || '';
	}

	function buildApiUrl(path) {
		var base = (checklistdekAdmin.restUrl || '').replace(/\/$/, '');
		return base + (path.charAt(0) === '/' ? path : '/' + path);
	}

	function request(method, path, body) {
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': getRestNonce(),
				Accept: 'application/json'
			}
		};
		if (body !== undefined) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return fetch(buildApiUrl(path), opts).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					throw new Error((data && data.message) || res.statusText || 'Request failed');
				}
				return data;
			});
		});
	}

	function get(path) {
		return request('GET', path);
	}

	function post(path, body) {
		return request('POST', path, body);
	}

	function notice(container, message, type) {
		if (!container) {
			return;
		}
		if (!message) {
			container.innerHTML = '';
			return;
		}
		container.innerHTML =
			'<div class="notice notice-' +
			escHtml(type === 'success' ? 'success' : 'error') +
			' is-dismissible"><p>' +
			escHtml(message) +
			'</p></div>';
	}

	function fillSelect(select, items, valueKey, labelKey, placeholder) {
		if (!select) {
			return;
		}
		var html = '';
		if (placeholder) {
			html += '<option value="">' + escHtml(placeholder) + '</option>';
		}
		(items || []).forEach(function (item) {
			html +=
				'<option value="' +
				escHtml(String(item[valueKey])) +
				'">' +
				escHtml(item[labelKey]) +
				'</option>';
		});
		select.innerHTML = html;
	}

	function fetchCustomChecklistSummaries() {
		if (checklistdekAdmin.customChecklists && checklistdekAdmin.customChecklists.preloaded && checklistdekAdmin.customChecklists.items) {
			return Promise.resolve(checklistdekAdmin.customChecklists.items);
		}
		return get('/checklists?is_template=0&summary=1');
	}

	function refreshVaultChecklistSelect() {
		return fetchCustomChecklistSummaries().then(function (items) {
			fillSelect(
				document.getElementById('checklistdek-vault-checklist'),
				items,
				'id',
				'title',
				strings.selectChecklist || 'Select checklist…'
			);
		});
	}

	function loadVaultTemplates() {
		var grid = document.getElementById('checklistdek-vault-list');
		if (!grid) {
			return;
		}

		get('/templates/vault').then(function (vault) {
			grid.innerHTML = '';
			if (!vault.length) {
				grid.innerHTML =
					'<p class="checklistdek-muted">' +
					escHtml(strings.noVaultTemplates || 'No vault templates yet.') +
					'</p>';
				return;
			}
			vault.forEach(function (tpl) {
				var card = document.createElement('article');
				card.className = 'checklistdek-template-card';
				card.innerHTML =
					'<div class="checklistdek-template-card-body">' +
					'<span class="checklistdek-badge">' +
					escHtml(strings.vaultBadge || 'Vault') +
					'</span>' +
					'<h3 class="checklistdek-template-card-title">' +
					escHtml(tpl.title || '') +
					'</h3>' +
					(tpl.description
						? '<p class="checklistdek-muted">' + escHtml(tpl.description) + '</p>'
						: '') +
					'</div>' +
					'<div class="checklistdek-template-card-actions">' +
					'<button type="button" class="button button-primary checklistdek-template-use-checklist">' +
					escHtml(checklistdekAdmin.strings.useChecklist || 'Use Checklist') +
					'</button>' +
					'</div>';
				var useBtn = card.querySelector('.checklistdek-template-use-checklist');
				if (useBtn) {
					useBtn.addEventListener('click', function () {
						get('/checklists/' + tpl.id)
							.then(function (source) {
								return post('/checklists', {
									title: (source.title || 'Checklist') + ' (Copy)',
									description: source.description || '',
									steps: source.steps || []
								});
							})
							.then(function (checklist) {
								document.dispatchEvent(new CustomEvent('checklistdek:checklists-changed'));
								if (checklist && checklist.id) {
									document.dispatchEvent(
										new CustomEvent('checklistdek:open-checklist-editor', {
											detail: { id: checklist.id }
										})
									);
								}
							});
					});
				}
				grid.appendChild(card);
			});
		});
	}

	function initAgencyVault() {
		if (!document.getElementById('checklistdek-vault-list')) {
			return;
		}

		refreshVaultChecklistSelect();
		loadVaultTemplates();

		var saveVaultBtn = document.getElementById('checklistdek-save-vault');
		if (saveVaultBtn) {
			saveVaultBtn.addEventListener('click', function () {
				var select = document.getElementById('checklistdek-vault-checklist');
				var noticeEl = document.getElementById('checklistdek-vault-notice');
				var id = select ? select.value : '';
				if (!id) {
					return;
				}
				post('/templates/vault', { checklist_id: parseInt(id, 10) })
					.then(function () {
						notice(noticeEl, strings.savedToVault || 'Saved to vault.', 'success');
						if (select) {
							select.value = '';
						}
						refreshVaultChecklistSelect();
						loadVaultTemplates();
						document.dispatchEvent(new CustomEvent('checklistdek:checklists-changed'));
					})
					.catch(function (err) {
						notice(noticeEl, err.message, 'error');
					});
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAgencyVault);
	} else {
		initAgencyVault();
	}
})();
