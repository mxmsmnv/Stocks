<?php

/**
 * StocksRenderer
 *
 * Renders stock badges and provides JS popup config.
 * Supports: Vanilla CSS, Tailwind CSS, Bootstrap 5, UIkit 3.
 */
class StocksRenderer {

	const THEME_AUTO      = 'auto';
	const THEME_VANILLA   = 'vanilla';
	const THEME_TAILWIND  = 'tailwind';
	const THEME_BOOTSTRAP = 'bootstrap';
	const THEME_UIKIT     = 'uikit';

	/** @var object Stocks module */
	protected $module;

	/** @var string */
	protected $theme;

	/** @var string|null Resolved theme cache */
	protected $resolved = null;

	public function __construct($module, $theme = self::THEME_AUTO) {
		$this->module = $module;
		$this->theme  = $theme;
	}

	// =========================================================
	// Theme detection
	// =========================================================

	/**
	 * Detect active CSS framework or return configured theme
	 *
	 * @return string
	 */
	public function detectFramework() {
		if ($this->theme !== self::THEME_AUTO) return $this->theme;

		$config  = wire('config');
		$all     = array_merge((array) $config->styles, (array) $config->scripts);

		foreach ($all as $asset) {
			$asset = strtolower(is_array($asset) ? (string) reset($asset) : (string) $asset);
			if (strpos($asset, 'bootstrap') !== false) return self::THEME_BOOTSTRAP;
			if (strpos($asset, 'uikit') !== false)     return self::THEME_UIKIT;
			if (strpos($asset, 'tailwind') !== false)  return self::THEME_TAILWIND;
		}

		if ($config->get('tailwind'))  return self::THEME_TAILWIND;
		if ($config->get('bootstrap')) return self::THEME_BOOTSTRAP;
		if ($config->get('uikit'))     return self::THEME_UIKIT;

		$page = wire('page');
		if ($page) {
			$fw = strtolower((string) $page->get('css_framework'));
			if (in_array($fw, [self::THEME_TAILWIND, self::THEME_BOOTSTRAP, self::THEME_UIKIT])) {
				return $fw;
			}
		}

		return self::THEME_VANILLA;
	}

	/**
	 * Get resolved theme (cached)
	 *
	 * @return string
	 */
	public function getTheme() {
		if ($this->resolved === null) {
			$this->resolved = $this->detectFramework();
		}
		return $this->resolved;
	}

	// =========================================================
	// Badge rendering
	// =========================================================

	/**
	 * Render stock badge
	 *
	 * @param array $data   Normalized stock data
	 * @param array $options
	 * @return string
	 */
	public function renderBadge(array $data, array $options = []) {
		switch ($this->getTheme()) {
			case self::THEME_TAILWIND:  return $this->renderBadgeTailwind($data, $options);
			case self::THEME_BOOTSTRAP: return $this->renderBadgeBootstrap($data, $options);
			case self::THEME_UIKIT:     return $this->renderBadgeUikit($data, $options);
			default:                    return $this->renderBadgeVanilla($data, $options);
		}
	}

	/**
	 * Render error badge for unknown or failed tickers
	 *
	 * @param string $ticker
	 * @return string
	 */
	public function renderErrorBadge($ticker) {
		$t = htmlspecialchars($ticker);

		switch ($this->getTheme()) {
			case self::THEME_TAILWIND:
				return '<span class="stocks-ticker inline-flex items-center gap-1 px-2 py-0.5 rounded '
					. 'text-xs font-mono bg-gray-100 text-gray-400 border border-gray-200 cursor-default" '
					. 'data-ticker="' . $t . '">' . $t . ' <span class="opacity-50">N/A</span></span>';

			case self::THEME_BOOTSTRAP:
				return '<span class="stocks-ticker badge bg-secondary font-monospace" '
					. 'data-ticker="' . $t . '">' . $t . ' N/A</span>';

			case self::THEME_UIKIT:
				return '<span class="stocks-ticker uk-badge" style="background:#6c757d" '
					. 'data-ticker="' . $t . '">' . $t . ' N/A</span>';

			default:
				return '<span class="stocks-ticker stocks-error" data-ticker="' . $t . '">' . $t . '</span>';
		}
	}

	// ── Vanilla ───────────────────────────────────────────────

	protected function renderBadgeVanilla(array $data, array $options = []) {
		$p        = $this->prep($data);
		$dataAttr = $this->buildDataAttr($data);

		return sprintf(
			'<span class="stocks-ticker %s" data-ticker="%s" %s tabindex="0" role="button" aria-expanded="false" aria-haspopup="dialog">'
			. '<span class="stocks-symbol">%s</span>'
			. '<span class="stocks-price">%s&nbsp;%s</span>'
			. '<span class="stocks-change">%s %s (%s)</span>%s'
			. '</span>',
			$p['colorClass'],
			$p['ticker'],
			$dataAttr,
			$p['ticker'],
			$p['currency'],
			$p['price'],
			$p['arrow'],
			$p['changeFmt'],
			$p['percentFmt'],
			$p['staleHtml']
		);
	}

	// ── Tailwind ──────────────────────────────────────────────

	protected function renderBadgeTailwind(array $data, array $options = []) {
		$p        = $this->prep($data);
		$dataAttr = $this->buildDataAttr($data);

		if ($p['isUp']) {
			$wrap   = 'bg-green-50 border-green-300 text-green-800 hover:bg-green-100 hover:border-green-400 hover:shadow-sm hover:shadow-green-200';
			$symbol = 'text-green-900 font-bold';
			$change = 'text-green-700';
		} else {
			$wrap   = 'bg-red-50 border-red-300 text-red-800 hover:bg-red-100 hover:border-red-400 hover:shadow-sm hover:shadow-red-200';
			$symbol = 'text-red-900 font-bold';
			$change = 'text-red-700';
		}

		return sprintf(
			'<span class="stocks-ticker inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md border '
			. 'font-mono text-xs cursor-pointer transition-all duration-150 select-none %s" '
			. 'data-ticker="%s" %s tabindex="0" role="button" aria-expanded="false" aria-haspopup="dialog">'
			. '<span class="%s tracking-wide">%s</span>'
			. '<span class="font-medium">%s&nbsp;%s</span>'
			. '<span class="%s text-[0.7rem]">%s %s (%s)</span>%s'
			. '</span>',
			$wrap,
			$p['ticker'],
			$dataAttr,
			$symbol,
			$p['ticker'],
			$p['currency'],
			$p['price'],
			$change,
			$p['arrow'],
			$p['changeFmt'],
			$p['percentFmt'],
			$p['staleHtml']
		);
	}

	// ── Bootstrap 5 ───────────────────────────────────────────

	protected function renderBadgeBootstrap(array $data, array $options = []) {
		$p        = $this->prep($data);
		$dataAttr = $this->buildDataAttr($data);

		$badgeClass = $p['isUp'] ? 'text-bg-success' : 'text-bg-danger';

		return sprintf(
			'<span class="stocks-ticker badge %s font-monospace fw-normal '
			. 'd-inline-flex align-items-center gap-1" '
			. 'data-ticker="%s" %s tabindex="0" role="button" aria-expanded="false" aria-haspopup="dialog" '
			. 'style="cursor:pointer;font-size:0.8em;padding:4px 8px;">'
			. '<strong>%s</strong>'
			. '<span>%s&nbsp;%s</span>'
			. '<span class="opacity-75 small">%s %s (%s)</span>%s'
			. '</span>',
			$badgeClass,
			$p['ticker'],
			$dataAttr,
			$p['ticker'],
			$p['currency'],
			$p['price'],
			$p['arrow'],
			$p['changeFmt'],
			$p['percentFmt'],
			$p['staleHtml']
		);
	}

	// ── UIkit 3 ───────────────────────────────────────────────

	protected function renderBadgeUikit(array $data, array $options = []) {
		$p        = $this->prep($data);
		$dataAttr = $this->buildDataAttr($data);

		$labelClass  = $p['isUp'] ? 'uk-label-success' : 'uk-label-danger';
		$textColor   = $p['isUp'] ? '#166534' : '#991b1b';
		$bgColor     = $p['isUp'] ? '#f0fdf4' : '#fef2f2';
		$borderColor = $p['isUp'] ? '#86efac' : '#fca5a5';

		return sprintf(
			'<span class="stocks-ticker uk-label %s" '
			. 'data-ticker="%s" %s tabindex="0" role="button" aria-expanded="false" aria-haspopup="dialog" '
			. 'style="cursor:pointer;font-family:monospace;font-size:0.8em;background:%s;color:%s;'
			. 'border:1px solid %s;border-radius:4px;padding:3px 8px;white-space:nowrap;'
			. 'display:inline-flex;align-items:center;gap:5px;">'
			. '<strong>%s</strong>'
			. '<span>%s&nbsp;%s</span>'
			. '<span style="opacity:0.8;font-size:0.9em;">%s %s (%s)</span>%s'
			. '</span>',
			$labelClass,
			$p['ticker'],
			$dataAttr,
			$bgColor,
			$textColor,
			$borderColor,
			$p['ticker'],
			$p['currency'],
			$p['price'],
			$p['arrow'],
			$p['changeFmt'],
			$p['percentFmt'],
			$p['staleHtml']
		);
	}

	// =========================================================
	// JS config for popup
	// =========================================================

	/**
	 * Get JS config array passed to window.StocksConfig
	 *
	 * @return array
	 */
	public function getJsConfig() {
		$theme = $this->getTheme();
		return [
			'theme'     => $theme,
			'popupStyle'=> $theme,
			'moduleUrl' => wire('config')->urls->siteModules . 'Stocks/',
		];
	}

	// =========================================================
	// Helpers
	// =========================================================

	/**
	 * Prepare common render values from stock data
	 *
	 * @param array $data
	 * @return array
	 */
	protected function prep(array $data) {
		$change  = (float) ($data['change'] ?? 0);
		$pct     = (float) ($data['change_percent'] ?? 0);
		$isUp    = $change >= 0;
		$sign    = $isUp ? '+' : '';

		$staleHtml = '';
		if (!empty($data['stale'])) {
			$staleHtml = ' <span class="stocks-stale" title="Data may be outdated" '
				. 'style="opacity:0.5;font-size:0.75em;">~</span>';
		}

		return [
			'isUp'       => $isUp,
			'colorClass' => $isUp ? 'stocks-up' : 'stocks-down',
			'arrow'      => $isUp ? '▲' : '▼',
			'sign'       => $sign,
			'ticker'     => htmlspecialchars(strtoupper($data['ticker'] ?? '')),
			'price'      => number_format((float) ($data['price'] ?? 0), 2),
			'currency'   => htmlspecialchars($data['currency'] ?? 'USD'),
			'changeFmt'  => $sign . number_format(abs($change), 2),
			'percentFmt' => $sign . number_format(abs($pct), 2) . '%',
			'staleHtml'  => $staleHtml,
		];
	}

	/**
	 * Build data-stocks JSON attribute (slimmed for HTML)
	 *
	 * @param array $data
	 * @return string
	 */
	protected function buildDataAttr(array $data) {
		$slim = array_intersect_key($data, array_flip([
			'ticker', 'name', 'price', 'price_open', 'price_high', 'price_low',
			'price_prev_close', 'change', 'change_percent', 'volume', 'avg_volume',
			'market_cap', 'currency', 'exchange', 'market_state',
			'fifty_two_week_high', 'fifty_two_week_low',
			'pe_ratio', 'dividend_yield', 'eps', 'beta',
			'fetched_at', 'cached_at', 'cache_age', 'stale',
		]));

		// Use double-quote attribute to avoid breakage with apostrophes in names (e.g. "McDonald's")
		return 'data-stocks="' . htmlspecialchars(
			json_encode($slim, JSON_UNESCAPED_UNICODE),
			ENT_QUOTES,
			'UTF-8'
		) . '"';
	}
}
