/**
 * Stocks popup template — Vanilla CSS
 *
 * Receives: data (stock object), helpers (esc, formatNum, formatLarge, formatAge, marketState, buildStatsGrid)
 * Returns:  { html, wrapClass, overlayClass, closeSelector }
 *
 * Edit this file to customise the Vanilla popup layout and content.
 * Remove any stat row from buildStatsGrid() call or rearrange sections freely.
 */
window.StocksTemplates = window.StocksTemplates || {};

window.StocksTemplates.vanilla = {
	build: function (data, h) {
		var d  = h.prepData(data);
		var ms = h.marketState(data.market_state);

		// ── Header ───────────────────────────────────────────
		var header = ''
			+ '<div class="stocks-popup-header ' + d.dirClass + '">'
			+   '<div class="stocks-popup-title">'
			+     '<span class="stocks-popup-ticker">' + d.ticker + '</span>'
			+     '<span class="stocks-popup-name">' + d.name + '</span>'
			+   '</div>'
			+   '<button class="stocks-popup-close" aria-label="Close">&times;</button>'
			+ '</div>';

		// ── Price ─────────────────────────────────────────────
		var price = ''
			+ '<div class="stocks-popup-price-section">'
			+   '<div class="stocks-popup-current-price">' + d.currency + '&nbsp;' + d.price + '</div>'
			+   '<div class="stocks-popup-change ' + d.dirClass + '">'
			+     '<span class="stocks-popup-change-badge">' + d.arrow + '&nbsp;' + d.changeFmt + '&nbsp;(' + d.pctFmt + ')</span>'
			+     '<span style="color:#9ca3af;font-weight:400;">Today</span>'
			+   '</div>'
			+ '</div>';

		// ── Stats grid ────────────────────────────────────────
		var stats = '<div class="stocks-popup-stats">' + h.buildStatsGrid(data, 'vanilla') + '</div>';

		// ── Footer ────────────────────────────────────────────
		var footer = ''
			+ '<div class="stocks-popup-footer">'
			+   '<span class="stocks-popup-exchange">' + h.esc(data.exchange || '') + '</span>'
			+   '<span class="stocks-popup-market-state ' + ms.cls + '">' + ms.label + '</span>'
			+ '</div>'
			+ '<div class="stocks-popup-updated">Updated:&nbsp;' + h.formatAge(data.fetched_at || data.cached_at) + '</div>';

		return header + price + stats + footer;
	},

	wrapClass:     'stocks-popup',
	overlayClass:  'stocks-popup-overlay',
	closeSelector: '.stocks-popup-close',
};
