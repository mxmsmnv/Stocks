/**
 * Stocks popup template — UIkit 3
 *
 * Receives: data (stock object), helpers (esc, formatNum, formatLarge, formatAge, marketState, buildStatsGrid)
 * Returns:  { html, wrapClass, overlayClass, closeSelector }
 *
 * Edit this file to customise the UIkit popup layout and content.
 * Uses UIkit modal structural classes; inline styles for colours
 * that UIkit's fixed palette cannot express as utility classes.
 */
window.StocksTemplates = window.StocksTemplates || {};

window.StocksTemplates.uikit = {
	build: function (data, h) {
		var d  = h.prepData(data);
		var ms = h.marketState(data.market_state);

		// Direction-aware values
		var headerBg   = d.isUp
			? 'background:linear-gradient(135deg,#f0fdf4,#dcfce7)'
			: 'background:linear-gradient(135deg,#fef2f2,#fee2e2)';
		var priceColor = d.isUp ? '#16a34a' : '#dc2626';
		var msBg       = ms.cls === 'market-regular' ? '#16a34a'
			: ms.cls === 'market-closed'              ? '#6b7280'
			:                                           '#d97706';

		// ── Header ───────────────────────────────────────────
		var header = ''
			+ '<div class="uk-modal-header" style="' + headerBg + ';padding:16px 20px 12px;border-bottom:1px solid #e5e7eb;">'
			+   '<div class="uk-flex uk-flex-between uk-flex-middle">'
			+     '<div>'
			+       '<h3 class="uk-modal-title uk-margin-remove" style="font-family:monospace;font-size:1.3em;font-weight:800;">' + d.ticker + '</h3>'
			+       '<p class="uk-margin-remove uk-text-muted uk-text-small">' + d.name + '</p>'
			+     '</div>'
			+     '<button class="stocks-popup-close" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;line-height:1;" aria-label="Close">&times;</button>'
			+   '</div>'
			+ '</div>';

		// ── Price ─────────────────────────────────────────────
		var price = ''
			+ '<div class="uk-modal-body" style="padding:14px 20px;border-bottom:1px solid #f3f4f6;">'
			+   '<div style="font-size:1.8em;font-weight:700;color:#111;">' + d.currency + '&nbsp;' + d.price + '</div>'
			+   '<div style="display:flex;align-items:center;gap:8px;margin-top:6px;">'
			+     '<span style="background:' + priceColor + ';color:#fff;font-size:11px;padding:3px 8px;border-radius:3px;">' + d.arrow + '&nbsp;' + d.changeFmt + '</span>'
			+     '<span style="color:' + priceColor + ';font-weight:600;font-size:13px;">' + d.pctFmt + '</span>'
			+     '<span style="color:#9ca3af;font-size:12px;">Today</span>'
			+   '</div>'
			+ '</div>';

		// ── Stats grid ────────────────────────────────────────
		var stats = ''
			+ '<div class="uk-modal-body" style="padding:12px 20px;">'
			+   '<dl style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;">'
			+     h.buildStatsGrid(data, 'uikit')
			+   '</dl>'
			+ '</div>';

		// ── Footer ────────────────────────────────────────────
		var footer = ''
			+ '<div class="uk-modal-footer" style="display:flex;justify-content:space-between;align-items:center;padding:8px 20px;background:#f9fafb;">'
			+   '<span style="font-family:monospace;font-size:11px;color:#9ca3af;">' + h.esc(data.exchange || '') + '</span>'
			+   '<span style="background:' + msBg + ';color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;">' + ms.label + '</span>'
			+ '</div>'
			+ '<div style="text-align:right;padding:2px 20px 8px;background:#f9fafb;font-size:10px;color:#d1d5db;">'
			+   'Updated:&nbsp;' + h.formatAge(data.fetched_at || data.cached_at)
			+ '</div>';

		return header + price + stats + footer;
	},

	wrapClass:     'stocks-popup uk-modal-dialog overflow-hidden',
	overlayClass:  'stocks-popup-overlay',
	closeSelector: '.stocks-popup-close',
};
