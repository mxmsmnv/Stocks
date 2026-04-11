/**
 * Stocks Admin — Company Manager UI
 * UIkit-styled. Handles add / edit / remove / toggle / bulk import.
 */

(function () {
	'use strict';

	// =========================================================
	// JSON state
	// =========================================================

	function getField()     { return document.getElementById('stocks_companies_json'); }
	function getCompanies() {
		var f = getField();
		if (!f || !f.value.trim()) return [];
		try { return JSON.parse(f.value) || []; } catch (e) { return []; }
	}
	function setCompanies(list) {
		var f = getField();
		if (f) f.value = JSON.stringify(list, null, 2);
	}
	function findIdx(list, ticker) {
		ticker = ticker.toUpperCase();
		for (var i = 0; i < list.length; i++) {
			if ((list[i].ticker || '').toUpperCase() === ticker) return i;
		}
		return -1;
	}

	// =========================================================
	// Normalize
	// =========================================================

	function normalizeEntry(e) {
		return {
			ticker:   (e.ticker || '').toUpperCase().trim(),
			name:     (e.name || '').trim(),
			aliases:  parseAliases(e.aliases),
			parse:    e.parse !== undefined ? !!e.parse : true,
			enabled:  e.enabled !== undefined ? !!e.enabled : true,
			currency: (e.currency || '').trim(),
			note:     (e.note || '').trim(),
		};
	}
	function parseAliases(raw) {
		if (Array.isArray(raw)) return raw.map(function (a) { return a.trim(); }).filter(Boolean);
		if (typeof raw === 'string') return raw.split(',').map(function (a) { return a.trim(); }).filter(Boolean);
		return [];
	}

	var TICKER_RE = /^[A-Z0-9]{1,6}(-[A-Z]{2,4})?(\.[A-Z]{2})?$/;
	function isValidTicker(t) { return TICKER_RE.test(t); }

	// =========================================================
	// Add Company
	// =========================================================

	function addCompany() {
		var ticker  = (val('sa_ticker') || '').toUpperCase().trim();
		var name    = val('sa_name').trim();
		var aliases = val('sa_aliases').trim();
		var parse   = checked('sa_parse');
		var errBox  = document.getElementById('sa_error');

		if (!ticker) { showError(errBox, 'Ticker is required (e.g. AAPL, TSLA)'); return; }
		if (!isValidTicker(ticker)) { showError(errBox, '"' + ticker + '" is not a valid ticker symbol'); return; }

		var list = getCompanies();
		if (findIdx(list, ticker) !== -1) { showError(errBox, ticker + ' is already tracked'); return; }

		var entry = normalizeEntry({ ticker: ticker, name: name || ticker, aliases: aliases, parse: parse });
		list.push(entry);
		setCompanies(list);
		injectTableRow(entry);
		updateCounter(list.length);

		set('sa_ticker', '');
		set('sa_name', '');
		set('sa_aliases', '');
		var parseEl = document.getElementById('sa_parse');
		if (parseEl) parseEl.checked = true;
		if (errBox) errBox.style.display = 'none';

		showToast(ticker + ' added');
	}

	// =========================================================
	// Remove Company
	// =========================================================

	function removeCompany(ticker) {
		if (!confirm('Remove ' + ticker + ' from tracked companies?')) return;

		var list = getCompanies();
		var idx  = findIdx(list, ticker);
		if (idx === -1) return;

		list.splice(idx, 1);
		setCompanies(list);

		var row = document.getElementById('stocks-row-' + ticker);
		if (row) {
			row.style.transition = 'opacity .2s,transform .2s';
			row.style.opacity    = '0';
			row.style.transform  = 'translateX(20px)';
			setTimeout(function () { row.remove(); }, 220);
		}

		updateCounter(list.length);
		showToast(ticker + ' removed');
	}

	// =========================================================
	// Inline edit — FIX: prevent button default submit
	// =========================================================

	function editRow(ticker, evt) {
		if (evt) { evt.preventDefault(); evt.stopPropagation(); }

		var row = document.getElementById('stocks-row-' + ticker);
		if (!row) return;

		qsa(row, '.sa-view-name,.sa-view-aliases').forEach(function (el) { el.style.display = 'none'; });
		qsa(row, '.sa-edit-name,.sa-edit-aliases').forEach(function (el) { el.style.display = ''; });

		var editBtn = row.querySelector('.sa-edit-btn');
		var saveBtn = row.querySelector('.sa-save-btn');
		if (editBtn) editBtn.style.display = 'none';
		if (saveBtn) saveBtn.style.display = '';

		var nameInput = row.querySelector('.sa-edit-name');
		if (nameInput) { setTimeout(function () { nameInput.focus(); nameInput.select(); }, 50); }
	}

	function saveRow(ticker, evt) {
		if (evt) { evt.preventDefault(); evt.stopPropagation(); }

		var row = document.getElementById('stocks-row-' + ticker);
		if (!row) return;

		var newName    = (row.querySelector('.sa-edit-name') ? row.querySelector('.sa-edit-name').value : '').trim();
		var newAliases = (row.querySelector('.sa-edit-aliases') ? row.querySelector('.sa-edit-aliases').value : '').trim();

		var list = getCompanies();
		var idx  = findIdx(list, ticker);
		if (idx !== -1) {
			list[idx].name    = newName;
			list[idx].aliases = parseAliases(newAliases);
			setCompanies(list);
		}

		var viewName    = row.querySelector('.sa-view-name');
		var viewAliases = row.querySelector('.sa-view-aliases');
		if (viewName)    viewName.textContent    = newName;
		if (viewAliases) viewAliases.textContent = newAliases;

		qsa(row, '.sa-view-name,.sa-view-aliases').forEach(function (el) { el.style.display = ''; });
		qsa(row, '.sa-edit-name,.sa-edit-aliases').forEach(function (el) { el.style.display = 'none'; });

		var editBtn = row.querySelector('.sa-edit-btn');
		var saveBtn = row.querySelector('.sa-save-btn');
		if (editBtn) editBtn.style.display = '';
		if (saveBtn) saveBtn.style.display = 'none';

		showToast(ticker + ' updated');
	}

	// =========================================================
	// Toggle listeners
	// =========================================================

	function initToggles() {
		document.addEventListener('change', function (e) {
			var el = e.target;
			if (!el.classList.contains('sa-toggle-parse') && !el.classList.contains('sa-toggle-enabled')) return;

			var ticker = el.getAttribute('data-ticker');
			var list   = getCompanies();
			var idx    = findIdx(list, ticker);
			if (idx === -1) return;

			if (el.classList.contains('sa-toggle-parse'))   list[idx].parse   = el.checked;
			if (el.classList.contains('sa-toggle-enabled')) list[idx].enabled = el.checked;

			setCompanies(list);
			updateRowStatus(ticker, list[idx]);
		});
	}

	function updateRowStatus(ticker, company) {
		var row = document.getElementById('stocks-row-' + ticker);
		if (!row) return;

		// Update label badge
		var label = row.querySelector('td:first-child .uk-label');
		if (label) {
			if (company.enabled) {
				label.className = 'uk-label uk-label-success';
				label.textContent = 'Active';
			} else {
				label.className = 'uk-label';
				label.style.background = 'var(--pw-muted-color)';
				label.textContent = 'Off';
			}
		}

		// Dim row if disabled
		if (company.enabled) {
			row.classList.remove('uk-text-muted');
		} else {
			row.classList.add('uk-text-muted');
		}
	}

	// =========================================================
	// Bulk import
	// =========================================================

	function bulkImport() {
		var textarea = document.getElementById('sa_bulk_input');
		var merge    = (document.getElementById('sa_bulk_merge') || {}).checked !== false;
		var resultEl = document.getElementById('sa_bulk_result');
		var text     = (textarea ? textarea.value : '').trim();

		if (!text) { showBulkResult(resultEl, 'error', 'Nothing to import'); return; }

		var imported = [], errors = [];
		text.split('\n').forEach(function (line, li) {
			line = line.trim();
			if (!line || line[0] === '#') return;
			var parts  = line.split('=');
			var ticker = (parts[0] || '').toUpperCase().trim();
			if (!ticker || !isValidTicker(ticker)) {
				errors.push('Line ' + (li + 1) + ': invalid "' + (parts[0] || '') + '"');
				return;
			}
			imported.push(normalizeEntry({
				ticker:  ticker,
				name:    (parts[1] || ticker).trim(),
				aliases: parts[2] ? parts[2].split(',').map(function (a) { return a.trim(); }) : [],
				parse:   true,
				enabled: true,
			}));
		});

		if (!imported.length) {
			showBulkResult(resultEl, 'error', 'No valid entries. ' + errors.join('; '));
			return;
		}

		var list = merge ? getCompanies() : [];
		var added = 0, updated = 0;
		imported.forEach(function (entry) {
			var idx = findIdx(list, entry.ticker);
			if (idx === -1) { list.push(entry); added++; }
			else { list[idx] = Object.assign({}, list[idx], entry); updated++; }
		});

		setCompanies(list);
		reloadTable(list);

		var msg = 'Imported: ' + added + ' added, ' + updated + ' updated'
			+ (errors.length ? '. ' + errors.length + ' skipped: ' + errors.join('; ') : '');
		showBulkResult(resultEl, errors.length ? 'warning' : 'success', msg);
		if (!errors.length && textarea) textarea.value = '';
	}

	// =========================================================
	// DOM helpers — UIkit-styled rows
	// =========================================================

	function injectTableRow(entry) {
		var tbody = document.querySelector('#stocks-company-table tbody');
		if (!tbody) { showToast('Saved. Reload to see table.'); return; }

		var ticker  = entry.ticker;
		var aliases = entry.aliases.join(', ');

		var tr = document.createElement('tr');
		tr.id = 'stocks-row-' + ticker;
		tr.setAttribute('data-ticker', ticker);
		tr.style.opacity   = '0';
		tr.style.transform = 'translateX(-10px)';
		tr.style.transition = 'opacity .25s,transform .25s';

		tr.innerHTML =
			'<td><span class="uk-label uk-label-success" style="font-size:10px;">Active</span></td>'
			+ '<td><code class="uk-text-bold" style="font-size:13px;">' + esc(ticker) + '</code></td>'
			+ '<td>'
				+ '<span class="sa-view-name">' + esc(entry.name) + '</span>'
				+ '<input class="sa-edit-name uk-input uk-form-small" type="text" value="' + esc(entry.name) + '" style="display:none;">'
			+ '</td>'
			+ '<td>'
				+ '<span class="sa-view-aliases uk-text-muted uk-text-small">' + esc(aliases) + '</span>'
				+ '<input class="sa-edit-aliases uk-input uk-form-small" type="text" value="' + esc(aliases) + '" style="display:none;" placeholder="alias1, alias2">'
			+ '</td>'
			+ '<td class="uk-text-center">'
				+ '<label style="cursor:pointer;">'
				+ '<input type="checkbox" class="uk-checkbox sa-toggle-parse" data-ticker="' + esc(ticker) + '"' + (entry.parse ? ' checked' : '') + '>'
				+ '</label>'
			+ '</td>'
			+ '<td class="uk-text-center">'
				+ '<label style="cursor:pointer;">'
				+ '<input type="checkbox" class="uk-checkbox sa-toggle-enabled" data-ticker="' + esc(ticker) + '"' + (entry.enabled ? ' checked' : '') + '>'
				+ '</label>'
			+ '</td>'
			+ '<td>'
				+ '<ul class="uk-iconnav">'
				+ '<li><a class="sa-edit-btn" href="#" uk-icon="icon:file-edit" title="Edit" onclick="StocksAdmin.editRow(\'' + esc(ticker) + '\',event);return false;"></a></li>'
				+ '<li><a class="sa-save-btn" href="#" uk-icon="icon:check" title="Save" style="display:none;" onclick="StocksAdmin.saveRow(\'' + esc(ticker) + '\',event);return false;"></a></li>'
				+ '<li><a class="uk-text-danger" href="#" uk-icon="icon:trash" title="Remove" onclick="StocksAdmin.removeCompany(\'' + esc(ticker) + '\');return false;"></a></li>'
				+ '</ul>'
			+ '</td>';

		tbody.appendChild(tr);
		requestAnimationFrame(function () {
			setTimeout(function () { tr.style.opacity = '1'; tr.style.transform = 'translateX(0)'; }, 10);
		});
	}

	function reloadTable(list) {
		var tbody = document.querySelector('#stocks-company-table tbody');
		if (!tbody) return;
		tbody.innerHTML = '';
		list.forEach(function (c) { injectTableRow(c); });
		updateCounter(list.length);
	}

	function updateCounter(count) {
		document.querySelectorAll('.InputfieldHeader').forEach(function (el) {
			if (el.textContent.indexOf('Company List') !== -1) {
				el.textContent = el.textContent.replace(/\(\d+ tracked\)/, '(' + count + ' tracked)');
			}
		});
	}

	// =========================================================
	// Feedback — UIkit-native
	// =========================================================

	function showError(el, msg) {
		if (!el) { alert(msg); return; }
		el.textContent   = msg;
		el.style.display = 'block';
		setTimeout(function () { if (el) el.style.display = 'none'; }, 4000);
	}

	function showBulkResult(el, type, msg) {
		if (!el) return;
		var cls = { success: 'uk-alert-success', warning: 'uk-alert-warning', error: 'uk-alert-danger' };
		el.className = 'uk-alert ' + (cls[type] || cls.error);
		el.textContent = msg;
		el.style.display = 'block';
	}

	function showToast(msg) {
		// Use UIkit notification if available, else fallback
		if (window.UIkit && UIkit.notification) {
			UIkit.notification({ message: msg, status: 'success', pos: 'bottom-right', timeout: 2200 });
			return;
		}
		var toast = document.createElement('div');
		toast.textContent = msg;
		toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:var(--pw-text-color);'
			+ 'color:var(--pw-blocks-background);padding:10px 18px;border-radius:var(--pw-button-radius);'
			+ 'font-size:13px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2);'
			+ 'opacity:0;transform:translateY(8px);transition:opacity .2s,transform .2s;';
		document.body.appendChild(toast);
		requestAnimationFrame(function () {
			setTimeout(function () { toast.style.opacity = '1'; toast.style.transform = 'translateY(0)'; }, 10);
		});
		setTimeout(function () {
			toast.style.opacity = '0'; toast.style.transform = 'translateY(8px)';
			setTimeout(function () { toast.remove(); }, 250);
		}, 2200);
	}

	// =========================================================
	// Utils
	// =========================================================

	function esc(str) {
		return String(str)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
	function val(id)         { var e = document.getElementById(id); return e ? e.value : ''; }
	function set(id, v)      { var e = document.getElementById(id); if (e) e.value = v; }
	function checked(id)     { var e = document.getElementById(id); return e ? e.checked : false; }
	function qsa(ctx, sel)   { return Array.from(ctx.querySelectorAll(sel)); }

	// =========================================================
	// Init
	// =========================================================

	function init() {
		initToggles();

		// Enter key in ticker field
		var tickerInput = document.getElementById('sa_ticker');
		if (tickerInput) {
			tickerInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); addCompany(); }
			});
			tickerInput.addEventListener('input', function () {
				tickerInput.value = tickerInput.value.toUpperCase();
			});
		}

		// Enter key in edit fields saves row
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') return;
			var editInput = e.target.closest && e.target.closest('.sa-edit-name, .sa-edit-aliases');
			if (!editInput) return;
			e.preventDefault();
			var row = e.target.closest('tr[data-ticker]');
			if (row) saveRow(row.getAttribute('data-ticker'), e);
		});

		// Escape cancels edit
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') return;
			var row = document.querySelector('tr .sa-save-btn[style*="display:"]');
			if (!row) return;
			var tr = row.closest && row.closest('tr[data-ticker]');
			if (tr) cancelEdit(tr);
		});

		// Cache clear buttons — stop propagation so they don't submit form
		document.addEventListener('click', function (e) {
			var clearLink = e.target.closest && e.target.closest('a[href*="clearCache"]');
			if (!clearLink) return;
			e.preventDefault();
			var href = clearLink.getAttribute('href');
			var ticker = (href.match(/clearCache=([^&]+)/) || [])[1];
			var label = ticker === 'ALL' ? 'all cache files' : 'cache for ' + ticker;
			if (!confirm('Clear ' + label + '?')) return;
			window.location.href = href;
		});
	}

	function cancelEdit(row) {
		qsa(row, '.sa-view-name,.sa-view-aliases').forEach(function (el) { el.style.display = ''; });
		qsa(row, '.sa-edit-name,.sa-edit-aliases').forEach(function (el) { el.style.display = 'none'; });
		var editBtn = row.querySelector('.sa-edit-btn');
		var saveBtn = row.querySelector('.sa-save-btn');
		if (editBtn) editBtn.style.display = '';
		if (saveBtn) saveBtn.style.display = 'none';
	}

	document.addEventListener('DOMContentLoaded', init);

	// =========================================================
	// Public API
	// =========================================================

	window.StocksAdmin = {
		addCompany:    addCompany,
		removeCompany: removeCompany,
		editRow:       editRow,
		saveRow:       saveRow,
		bulkImport:    bulkImport,
	};

})();

