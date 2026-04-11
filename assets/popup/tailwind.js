/**
 * Stocks popup template — Tailwind CSS
 *
 * Receives: data (stock object), helpers (esc, formatNum, formatLarge, formatAge, marketState, buildStatsGrid)
 * Returns:  { html, wrapClass, overlayClass, closeSelector }
 *
 * Edit this file to customise the Tailwind popup layout and content.
 * Uses only core Tailwind utility classes — no arbitrary values except font sizes in comments.
 */
window.StocksTemplates = window.StocksTemplates || {};

window.StocksTemplates.tailwind = {
	build: function (data, h) {
		var d  = h.prepData(data);
		var ms = h.marketState(data.market_state);

		// Direction-aware classes
		var headerBg  = d.isUp
			? 'bg-gradient-to-br from-green-50 to-green-100 border-b border-green-200'
			: 'bg-gradient-to-br from-red-50 to-red-100 border-b border-red-200';
		var badgeCls  = d.isUp
			? 'bg-green-600 text-white text-xs font-semibold px-2 py-0.5 rounded'
			: 'bg-red-600 text-white text-xs font-semibold px-2 py-0.5 rounded';
		var priceCls  = d.isUp ? 'text-green-700' : 'text-red-700';
		var msBadge   = ms.cls === 'market-regular'
			? 'bg-green-100 text-green-700 text-xs font-semibold px-2 py-0.5 rounded-full'
			: ms.cls === 'market-closed'
			? 'bg-gray-100 text-gray-500 text-xs font-semibold px-2 py-0.5 rounded-full'
			: 'bg-yellow-100 text-yellow-700 text-xs font-semibold px-2 py-0.5 rounded-full';

		// ── Header ───────────────────────────────────────────
		var header = ''
			+ '<div class="' + headerBg + ' p-4 flex justify-between items-start">'
			+   '<div>'
			+     '<div class="text-xl font-extrabold text-gray-900 tracking-wide">' + d.ticker + '</div>'
			+     '<div class="text-xs text-gray-400 mt-0.5 max-w-xs truncate">' + d.name + '</div>'
			+   '</div>'
			+   '<button class="stocks-popup-close text-gray-400 hover:text-gray-700 hover:bg-white/60 rounded-lg p-1 transition-colors text-lg leading-none" aria-label="Close">&times;</button>'
			+ '</div>';

		// ── Price ─────────────────────────────────────────────
		var price = ''
			+ '<div class="px-4 py-3 border-b border-gray-100">'
			+   '<div class="text-3xl font-bold text-gray-900">' + d.currency + '&nbsp;' + d.price + '</div>'
			+   '<div class="flex items-center gap-2 mt-1.5">'
			+     '<span class="' + badgeCls + '">' + d.arrow + '&nbsp;' + d.changeFmt + '</span>'
			+     '<span class="' + priceCls + ' text-sm font-semibold">' + d.pctFmt + '</span>'
			+     '<span class="text-gray-400 text-xs">Today</span>'
			+   '</div>'
			+ '</div>';

		// ── Stats grid ────────────────────────────────────────
		var stats = ''
			+ '<div class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3 border-b border-gray-100">'
			+   h.buildStatsGrid(data, 'tailwind')
			+ '</div>';

		// ── Footer ────────────────────────────────────────────
		var footer = ''
			+ '<div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 rounded-b-xl">'
			+   '<span class="text-xs text-gray-400">' + h.esc(data.exchange || '') + '</span>'
			+   '<span class="' + msBadge + '">' + ms.label + '</span>'
			+ '</div>'
			+ '<div class="text-right text-gray-300 px-4 pb-2 bg-gray-50" style="font-size:10px;">'
			+   'Updated:&nbsp;' + h.formatAge(data.fetched_at || data.cached_at)
			+ '</div>';

		return header + price + stats + footer;
	},

	wrapClass:     'stocks-popup stocks-popup-tw fixed z-[9999] bg-white rounded-xl shadow-2xl w-80 max-w-[calc(100vw-2rem)] overflow-hidden font-sans text-sm',
	overlayClass:  'stocks-popup-overlay fixed inset-0 z-[9998] backdrop-blur-sm',
	closeSelector: '.stocks-popup-close',
};
