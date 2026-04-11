/**
 * Stocks Plugin — Frontend Popup Handler
 * Supports: Vanilla CSS, Tailwind CSS, Bootstrap 5, UIkit 3
 *
 * Popup markup lives in assets/popup/{theme}.js — edit those files
 * to change layout, add/remove data rows, or reskin the popup.
 *
 * Reads window.StocksConfig injected by Stocks.module.php:
 *   { theme, popupStyle, moduleUrl }
 */

(function () {
	'use strict';

	var Config = window.StocksConfig || { theme: 'vanilla', popupStyle: 'vanilla', moduleUrl: '' };

	// =========================================================
	// Helper utilities (passed into every template as `h`)
	// =========================================================

	function esc(str) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(str || ''));
		return d.innerHTML;
	}

	function formatNum(n, dec) {
		if (n === null || n === undefined || isNaN(parseFloat(n))) return 'N/A';
		dec = (dec === undefined) ? 2 : dec;
		return Number(n).toLocaleString('en-US', {
			minimumFractionDigits: dec,
			maximumFractionDigits: dec,
		});
	}

	function formatLarge(n) {
		if (!n && n !== 0) return null;
		n = parseFloat(n);
		if (n >= 1e12) return (n / 1e12).toFixed(2) + 'T';
		if (n >= 1e9)  return (n / 1e9).toFixed(2) + 'B';
		if (n >= 1e6)  return (n / 1e6).toFixed(2) + 'M';
		if (n >= 1e3)  return (n / 1e3).toFixed(2) + 'K';
		return n.toLocaleString();
	}

	function formatAge(ts) {
		if (!ts) return 'Unknown';
		var sec = Math.floor(Date.now() / 1000) - parseInt(ts, 10);
		if (sec < 60)    return 'Just now';
		if (sec < 3600)  return Math.floor(sec / 60) + 'm ago';
		if (sec < 86400) return Math.floor(sec / 3600) + 'h ago';
		return Math.floor(sec / 86400) + 'd ago';
	}

	function marketState(state) {
		var map = {
			'REGULAR': { label: '&#9679; Live',        cls: 'market-regular' },
			'PRE':     { label: '&#9680; Pre-Market',  cls: 'market-pre' },
			'POST':    { label: '&#9681; After Hours', cls: 'market-post' },
			'CLOSED':  { label: '&#9675; Closed',      cls: 'market-closed' },
		};
		return map[state] || { label: '&#9675; Closed', cls: 'market-closed' };
	}

	function prepData(data) {
		var change = parseFloat(data.change || 0);
		var pct    = parseFloat(data.change_percent || 0);
		var isUp   = change >= 0;
		var sign   = isUp ? '+' : '';
		return {
			isUp:      isUp,
			dirClass:  isUp ? 'stocks-popup-up' : 'stocks-popup-down',
			arrow:     isUp ? '&#9650;' : '&#9660;',
			ticker:    esc(data.ticker || ''),
			name:      esc(data.name || data.ticker || ''),
			price:     formatNum(data.price),
			currency:  esc(data.currency || 'USD'),
			changeFmt: sign + formatNum(Math.abs(change)),
			pctFmt:    sign + formatNum(Math.abs(pct)) + '%',
		};
	}

	// =========================================================
	// Stats grid — shared across all templates
	// =========================================================

	/**
	 * Build the stats grid HTML for a given theme.
	 * To add or remove data points, edit the rows array below.
	 *
	 * @param {object} data  - stock data object
	 * @param {string} theme - vanilla | tailwind | bootstrap | uikit
	 * @returns {string} HTML string
	 */
	function buildStatsGrid(data, theme) {
		var currency = data.currency || 'USD';

		var rows = [
			{ label: 'Open',       value: data.price_open          ? currency + ' ' + formatNum(data.price_open)          : null },
			{ label: 'Prev Close', value: data.price_prev_close    ? currency + ' ' + formatNum(data.price_prev_close)    : null },
			{ label: "Day's High", value: data.price_high          ? currency + ' ' + formatNum(data.price_high)          : null },
			{ label: "Day's Low",  value: data.price_low           ? currency + ' ' + formatNum(data.price_low)           : null },
			{ label: '52W High',   value: data.fifty_two_week_high ? currency + ' ' + formatNum(data.fifty_two_week_high) : null },
			{ label: '52W Low',    value: data.fifty_two_week_low  ? currency + ' ' + formatNum(data.fifty_two_week_low)  : null },
			{ label: 'Volume',     value: data.volume     ? formatLarge(data.volume)     : null },
			{ label: 'Avg Vol',    value: data.avg_volume ? formatLarge(data.avg_volume) : null },
			{ label: 'Mkt Cap',    value: data.market_cap ? formatLarge(data.market_cap) : null },
			{ label: 'P/E',        value: data.pe_ratio   ? formatNum(data.pe_ratio)     : null },
			{ label: 'EPS',        value: data.eps        ? currency + ' ' + formatNum(data.eps) : null },
			{ label: 'Beta',       value: data.beta       ? formatNum(data.beta)         : null },
			{ label: 'Div Yield',  value: data.dividend_yield ? formatNum(parseFloat(data.dividend_yield) * 100) + '%' : null },
		].filter(function (r) { return r.value !== null; });

		switch (theme) {
			case 'tailwind':
				return rows.map(function (r) {
					return '<div>'
						+ '<div class="text-gray-400 font-semibold mb-0.5" style="font-size:10px;text-transform:uppercase;letter-spacing:.05em;">' + esc(r.label) + '</div>'
						+ '<div class="text-xs font-semibold text-gray-700">' + esc(r.value) + '</div>'
						+ '</div>';
				}).join('');

			case 'bootstrap':
				return rows.map(function (r) {
					return '<div class="col">'
						+ '<div class="text-uppercase text-muted fw-semibold" style="font-size:9px;letter-spacing:.05em;">' + esc(r.label) + '</div>'
						+ '<div class="fw-semibold text-body" style="font-size:12px;">' + esc(r.value) + '</div>'
						+ '</div>';
				}).join('');

			case 'uikit':
				return rows.map(function (r) {
					return '<div>'
						+ '<dt style="font-size:9px;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;font-weight:700;margin-bottom:2px;">' + esc(r.label) + '</dt>'
						+ '<dd style="font-size:12px;font-weight:600;color:#374151;margin:0;">' + esc(r.value) + '</dd>'
						+ '</div>';
				}).join('');

			default: // vanilla
				return rows.map(function (r) {
					return '<div class="stocks-popup-stat">'
						+ '<span class="stocks-popup-stat-label">' + esc(r.label) + '</span>'
						+ '<span class="stocks-popup-stat-value">' + esc(r.value) + '</span>'
						+ '</div>';
				}).join('');
		}
	}

	// =========================================================
	// Template helpers object — passed to every template build()
	// =========================================================

	var helpers = {
		esc:            esc,
		formatNum:      formatNum,
		formatLarge:    formatLarge,
		formatAge:      formatAge,
		marketState:    marketState,
		prepData:       prepData,
		buildStatsGrid: buildStatsGrid,
	};

	// =========================================================
	// Template loader
	// =========================================================

	/**
	 * Load a popup template by theme name.
	 * Templates live in assets/popup/{theme}.js and register themselves
	 * on window.StocksTemplates[theme].
	 *
	 * Falls back to the vanilla template if the requested one is absent.
	 */
	function loadTemplate(theme, callback) {
		// Already registered (cached after first load)
		if (window.StocksTemplates && window.StocksTemplates[theme]) {
			return callback(window.StocksTemplates[theme]);
		}

		var url    = Config.moduleUrl + 'assets/popup/' + theme + '.js';
		var script = document.createElement('script');
		script.src   = url;
		script.async = true;

		script.onload = function () {
			var tpl = (window.StocksTemplates && window.StocksTemplates[theme])
				? window.StocksTemplates[theme]
				: null;

			if (!tpl) {
				console.warn('[Stocks] Template "' + theme + '" did not register. Falling back to vanilla.');
				loadTemplate('vanilla', callback);
				return;
			}
			callback(tpl);
		};

		script.onerror = function () {
			console.warn('[Stocks] Could not load template "' + theme + '". Falling back to vanilla.');
			if (theme !== 'vanilla') loadTemplate('vanilla', callback);
		};

		document.head.appendChild(script);
	}

	// =========================================================
	// Popup state
	// =========================================================

	var activePopup   = null;
	var activeOverlay = null;
	var activeTicker  = null;

	var TICKER_SEL = '.stocks-ticker[data-stocks]';
	var OFFSET     = 12;

	function positionPopup(popup, trigger) {
		var rect   = trigger.getBoundingClientRect();
		var vpW    = window.innerWidth;
		var vpH    = window.innerHeight;
		var popupW = popup.offsetWidth  || 340;
		var popupH = popup.offsetHeight || 420;

		var top  = rect.bottom + OFFSET;
		var left = rect.left;

		if (rect.bottom + popupH + OFFSET > vpH) top = rect.top - popupH - OFFSET;
		if (left + popupW > vpW - 16) left = vpW - popupW - 16;
		if (left < 8) left = 8;
		if (top  < 8) top  = 8;

		popup.style.top  = top  + 'px';
		popup.style.left = left + 'px';
	}

	function showPopup(tickerEl) {
		closePopup(true);

		var rawData = tickerEl.getAttribute('data-stocks');
		if (!rawData) return;

		var data;
		try { data = JSON.parse(rawData); } catch (e) { return; }

		var theme = Config.popupStyle || 'vanilla';

		loadTemplate(theme, function (tpl) {
			var overlay = document.createElement('div');
			overlay.className = tpl.overlayClass;
			overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:9998;opacity:0;transition:opacity .2s;';
			document.body.appendChild(overlay);

			var popup = document.createElement('div');
			popup.className = typeof tpl.wrapClass === 'function' ? tpl.wrapClass(data) : tpl.wrapClass;
			popup.setAttribute('role', 'dialog');
			popup.setAttribute('aria-modal', 'true');
			popup.setAttribute('aria-label', 'Stock details for ' + (data.ticker || ''));
			popup.innerHTML = tpl.build(data, helpers);
			popup.style.cssText = 'position:fixed;z-index:9999;opacity:0;transform:translateY(-8px) scale(.97);transition:opacity .2s ease,transform .2s ease;';
			document.body.appendChild(popup);

			positionPopup(popup, tickerEl);

			requestAnimationFrame(function () {
				setTimeout(function () {
					overlay.style.opacity = '1';
					popup.style.opacity   = '1';
					popup.style.transform = 'translateY(0) scale(1)';
				}, 10);
			});

			activePopup   = popup;
			activeOverlay = overlay;
			activeTicker  = tickerEl;

			tickerEl.setAttribute('aria-expanded', 'true');

			var closeBtn = popup.querySelector(tpl.closeSelector);
			if (closeBtn) {
				closeBtn.addEventListener('click', function () { closePopup(); cleanup(); });
				setTimeout(function () { closeBtn.focus(); }, 200);
			}

			overlay.addEventListener('click', function () { closePopup(); cleanup(); });

			document.addEventListener('keydown', onKeyDown);
			window.addEventListener('resize', onResize);
			window.addEventListener('scroll', onScroll, { passive: true });
		});
	}

	function closePopup(immediate) {
		if (!activePopup) return;

		var popup   = activePopup;
		var overlay = activeOverlay;
		var ticker  = activeTicker;

		activePopup   = null;
		activeOverlay = null;
		activeTicker  = null;

		if (ticker) ticker.setAttribute('aria-expanded', 'false');

		if (immediate) {
			popup.remove();
			overlay.remove();
			return;
		}

		popup.style.opacity   = '0';
		popup.style.transform = 'translateY(-8px) scale(.97)';
		overlay.style.opacity = '0';

		setTimeout(function () { popup.remove(); overlay.remove(); }, 250);
	}

	function onKeyDown(e) { if (e.key === 'Escape') { closePopup(); cleanup(); } }
	function onResize()   { if (activePopup && activeTicker) positionPopup(activePopup, activeTicker); }
	function onScroll()   { if (activePopup && activeTicker) positionPopup(activePopup, activeTicker); }

	function cleanup() {
		document.removeEventListener('keydown', onKeyDown);
		window.removeEventListener('resize', onResize);
		window.removeEventListener('scroll', onScroll);
	}

	// =========================================================
	// Event delegation
	// =========================================================

	document.addEventListener('click', function (e) {
		var ticker = e.target.closest(TICKER_SEL);

		if (ticker) {
			e.preventDefault();
			e.stopPropagation();
			if (activeTicker === ticker) {
				closePopup();
				cleanup();
			} else {
				showPopup(ticker);
			}
			return;
		}

		if (activePopup && !activePopup.contains(e.target)) {
			closePopup();
			cleanup();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') return;
		var ticker = e.target.closest(TICKER_SEL);
		if (ticker) { e.preventDefault(); showPopup(ticker); }
	});

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll(TICKER_SEL).forEach(function (el) {
			if (!el.getAttribute('tabindex')) el.setAttribute('tabindex', '0');
			el.setAttribute('role', 'button');
			el.setAttribute('aria-expanded', 'false');
			el.setAttribute('aria-haspopup', 'dialog');
		});
	});

	// =========================================================
	// Public API
	// =========================================================

	window.StocksPlugin = {
		show:  showPopup,
		close: closePopup,
		theme: Config.popupStyle,
	};

})();
