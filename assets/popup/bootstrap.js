/**
 * Stocks popup template — Bootstrap 5
 *
 * Receives: data (stock object), helpers (esc, formatNum, formatLarge, formatAge, marketState, buildStatsGrid)
 * Returns:  { html, wrapClass, overlayClass, closeSelector }
 *
 * Edit this file to customise the Bootstrap popup layout and content.
 * Uses Bootstrap 5 utility classes and modal-* structural classes.
 */
window.StocksTemplates = window.StocksTemplates || {};

window.StocksTemplates.bootstrap = {
	build: function (data, h) {
		var d  = h.prepData(data);
		var ms = h.marketState(data.market_state);

		// Direction-aware classes
		var headerBg  = d.isUp ? 'bg-success-subtle' : 'bg-danger-subtle';
		var badgeCls  = d.isUp ? 'bg-success'        : 'bg-danger';
		var changeCls = d.isUp ? 'text-success-emphasis' : 'text-danger-emphasis';
		var msBadge   = ms.cls === 'market-regular' ? 'text-bg-success'
			: ms.cls === 'market-closed'             ? 'text-bg-secondary'
			:                                          'text-bg-warning';

		// ── Header ───────────────────────────────────────────
		var header = ''
			+ '<div class="modal-header ' + headerBg + ' border-0 py-3">'
			+   '<div>'
			+     '<h5 class="modal-title fw-bolder font-monospace mb-0 fs-5">' + d.ticker + '</h5>'
			+     '<small class="text-muted">' + d.name + '</small>'
			+   '</div>'
			+   '<button class="stocks-popup-close btn-close ms-auto" aria-label="Close"></button>'
			+ '</div>';

		// ── Price ─────────────────────────────────────────────
		var price = ''
			+ '<div class="modal-body py-3 border-top">'
			+   '<div class="fs-3 fw-bold mb-1">' + d.currency + '&nbsp;' + d.price + '</div>'
			+   '<div class="d-flex align-items-center gap-2">'
			+     '<span class="badge ' + badgeCls + ' font-monospace">' + d.arrow + '&nbsp;' + d.changeFmt + '</span>'
			+     '<span class="' + changeCls + ' fw-semibold small">' + d.pctFmt + '</span>'
			+     '<span class="text-muted small">Today</span>'
			+   '</div>'
			+ '</div>';

		// ── Stats grid ────────────────────────────────────────
		var stats = ''
			+ '<div class="modal-body pt-0 pb-2">'
			+   '<div class="row row-cols-2 g-2 small">'
			+     h.buildStatsGrid(data, 'bootstrap')
			+   '</div>'
			+ '</div>';

		// ── Footer ────────────────────────────────────────────
		var footer = ''
			+ '<div class="modal-footer py-2 bg-light justify-content-between">'
			+   '<small class="text-muted font-monospace">' + h.esc(data.exchange || '') + '</small>'
			+   '<span class="badge rounded-pill ' + msBadge + '">' + ms.label + '</span>'
			+ '</div>'
			+ '<div class="text-end px-3 pb-2 bg-light">'
			+   '<small class="text-muted" style="font-size:10px;">Updated:&nbsp;' + h.formatAge(data.fetched_at || data.cached_at) + '</small>'
			+ '</div>';

		return header + price + stats + footer;
	},

	wrapClass:     'stocks-popup modal-content shadow-lg position-fixed border-0 rounded-3 overflow-hidden',
	overlayClass:  'stocks-popup-overlay modal-backdrop fade show',
	closeSelector: '.stocks-popup-close',
};
