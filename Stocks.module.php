<?php

/**
 * Stocks
 *
 * ProcessWire module for live stock market badges.
 * Fetches data from Yahoo Finance or Alpha Vantage, caches results,
 * renders inline badges with popup details, supports 4 CSS frameworks.
 *
 * @version 1.0.0
 */
class Stocks extends WireData implements Module, ConfigurableModule {

	public static function getModuleInfo() {
		return [
			'title'    => 'Stocks',
			'version'  => 100,
			'summary'  => 'Live stock market badges with company manager and multi-framework UI',
			'author'   => 'Maxim Semenov',
			'href'     => 'https://smnv.org',
			'singular' => true,
			'autoload' => true,
			'icon'     => 'line-chart',
			'requires' => ['ProcessWire>=3.0.0'],
		];
	}

	public static function getDefaultConfig() {
		return [
			'cache_time'      => 300,
			'api_provider'    => 'yahoo',
			'api_key'         => '',
			'cache_dir'       => 'stocks',
			'request_timeout' => 10,
			'currency'        => 'USD',
			'ui_theme'        => 'auto',
			'inject_css'      => 1,
			'inject_js'       => 1,
			'companies_json'  => '',
			'untracked_mode'  => 'plain',
		];
	}

	/** @var StocksCompanyManager|null */
	protected $_companies = null;

	/** @var StocksRenderer|null */
	protected $_renderer = null;

	public function __construct() {
		parent::__construct();
		foreach (self::getDefaultConfig() as $key => $value) {
			$this->$key = $value;
		}
	}

	public function init() {
		require_once __DIR__ . '/StocksAPI.php';
		require_once __DIR__ . '/StocksRenderer.php';
		require_once __DIR__ . '/StocksCompanyManager.php';

		$this->addHookAfter('Page::render', $this, 'injectAssets');
		$this->addHookAfter('Page::render', $this, 'injectAdminAssets');
	}

	// =========================================================
	// Accessors
	// =========================================================

	/**
	 * Get company manager instance (lazy singleton on object)
	 *
	 * @return StocksCompanyManager
	 */
	public function companies() {
		if ($this->_companies === null) {
			$this->_companies = StocksCompanyManager::fromJson($this->companies_json);
		}
		return $this->_companies;
	}

	/**
	 * Get renderer instance (lazy singleton on object)
	 *
	 * @return StocksRenderer
	 */
	public function renderer() {
		if ($this->_renderer === null) {
			$this->_renderer = new StocksRenderer($this, $this->ui_theme);
		}
		return $this->_renderer;
	}

	/**
	 * Reset cached instances (call after saving module config)
	 */
	public function resetInstances() {
		$this->_companies = null;
		$this->_renderer  = null;
	}

	// =========================================================
	// Public API
	// =========================================================

	/**
	 * Render badge for a ticker.
	 * Untracked tickers are handled per untracked_mode setting.
	 *
	 * @param string $ticker
	 * @param array  $options
	 * @return string HTML
	 */
	public function renderBadge($ticker, array $options = []) {
		$ticker  = strtoupper(trim($ticker));
		$company = $this->companies()->get($ticker);

		if (!$company || empty($company['enabled'])) {
			return $this->renderUntracked($ticker, $options);
		}

		$data = $this->getStock($ticker);

		if (!$data) {
			return $this->renderer()->renderErrorBadge($ticker);
		}

		if (empty($data['name']) && !empty($company['name'])) {
			$data['name'] = $company['name'];
		}

		return $this->renderer()->renderBadge($data, $options);
	}

	/**
	 * Render badge with a forced theme override
	 *
	 * @param string $ticker
	 * @param string $theme  vanilla|tailwind|bootstrap|uikit
	 * @param array  $options
	 * @return string
	 */
	public function renderBadgeAs($ticker, $theme, array $options = []) {
		$renderer = new StocksRenderer($this, $theme);
		$data     = $this->getStock($ticker);
		if (!$data) return $renderer->renderErrorBadge($ticker);
		return $renderer->renderBadge($data, $options);
	}

	/**
	 * Render untracked ticker per untracked_mode setting
	 *
	 * @param string $ticker
	 * @param array  $options
	 * @return string
	 */
	public function renderUntracked($ticker, array $options = []) {
		$label = htmlspecialchars($ticker);

		switch ($this->untracked_mode) {
			case 'hide':
				return '';

			case 'badge_nodata':
				return '<span class="stocks-ticker stocks-nodata" '
					. 'data-ticker="' . $label . '" '
					. 'title="No data — add ' . $label . ' to tracked companies">'
					. '<span class="stocks-symbol">' . $label . '</span>'
					. '<span class="stocks-price" style="opacity:0.4;">N/A</span>'
					. '</span>';

			case 'plain':
			default:
				return '<span class="stocks-untracked" '
					. 'data-ticker="' . $label . '" '
					. 'title="' . $label . ' (not tracked)">'
					. $label
					. '</span>';
		}
	}

	/**
	 * Get stock data for one ticker (with caching + circuit breaker)
	 *
	 * @param string $ticker
	 * @param bool   $forceRefresh
	 * @return array|false
	 */
	// Circuit breaker — stored on object, not static (safe for long-running processes)
	const CIRCUIT_FAIL_THRESHOLD = 2;
	const CIRCUIT_OPEN_SECONDS   = 60;
	protected $apiFailCount = 0;
	protected $apiOpenUntil = 0;

	public function getStock($ticker, $forceRefresh = false) {
		$ticker = strtoupper(trim($ticker));
		if (empty($ticker)) return false;

		// Fresh cache — return immediately, no API call needed
		if (!$forceRefresh) {
			$cached = $this->getCached($ticker);
			if ($cached !== false) return $cached;
		}

		// Circuit breaker: if API has been failing, skip the call
		// and return stale cache or false immediately
		if ($this->apiOpenUntil > time()) {
			$stale = $this->getCached($ticker, true);
			if ($stale) {
				$stale['stale'] = true;
				return $stale;
			}
			return false;
		}

		$api  = new StocksAPI($this->api_provider, $this->api_key, $this->request_timeout);
		$data = $api->fetchQuote($ticker);

		if ($data) {
			$this->apiFailCount = 0;
			$this->setCache($ticker, $data);
			return $data;
		}

		// API call failed
		$this->apiFailCount++;
		if ($this->apiFailCount >= self::CIRCUIT_FAIL_THRESHOLD) {
			$this->apiOpenUntil = time() + self::CIRCUIT_OPEN_SECONDS;
			wire('log')->save('stocks', "Circuit breaker opened — {$this->api_provider} failed {$this->apiFailCount}x. Pausing " . self::CIRCUIT_OPEN_SECONDS . "s.");
		}

		// Return stale cache if available
		$stale = $this->getCached($ticker, true);
		if ($stale) {
			$stale['stale'] = true;
			return $stale;
		}

		return false;
	}

	/**
	 * Get stock data for multiple tickers
	 *
	 * @param array $tickers
	 * @return array
	 */
	public function getStocks(array $tickers) {
		$results = [];
		foreach ($tickers as $ticker) {
			$data = $this->getStock($ticker);
			if ($data) $results[strtoupper($ticker)] = $data;
		}
		return $results;
	}

	/**
	 * Prefetch all tracked companies into cache
	 *
	 * @return array ticker => bool
	 */
	public function prefetchAll() {
		$results = [];
		foreach ($this->companies()->tickers(true) as $ticker) {
			$data            = $this->getStock($ticker, true);
			$results[$ticker] = (bool) $data;
		}
		return $results;
	}

	// =========================================================
	// Cache
	// =========================================================

	protected function getCached($ticker, $allowStale = false) {
		$file = $this->getCacheFilePath($ticker);
		if (!file_exists($file)) return false;

		$age = time() - filemtime($file);
		if (!$allowStale && $age > (int) $this->cache_time) return false;

		$data = json_decode(file_get_contents($file), true);
		if (!$data) return false;

		$data['cached_at'] = filemtime($file);
		$data['cache_age'] = $age;
		return $data;
	}

	protected function setCache($ticker, array $data) {
		$dir = $this->getCacheDir();
		if (!is_dir($dir) && !wireMkdir($dir)) return false;
		return file_put_contents(
			$this->getCacheFilePath($ticker),
			json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		) !== false;
	}

	protected function getCacheDir() {
		return $this->wire('config')->paths->cache . $this->cache_dir . '/';
	}

	protected function getCacheFilePath($ticker) {
		return $this->getCacheDir()
			. preg_replace('/[^A-Z0-9.\-]/', '', strtoupper($ticker))
			. '.json';
	}

	/**
	 * Clear cache for one ticker or all
	 *
	 * @param string|null $ticker
	 * @return int Number of files deleted
	 */
	public function clearCache($ticker = null) {
		$count = 0;
		if ($ticker) {
			$file = $this->getCacheFilePath($ticker);
			if (file_exists($file)) { unlink($file); $count++; }
		} else {
			foreach (glob($this->getCacheDir() . '*.json') ?: [] as $f) {
				unlink($f);
				$count++;
			}
		}
		return $count;
	}

	// =========================================================
	// Asset injection
	// =========================================================

	public function injectAssets(HookEvent $event) {
		$page = $event->object;
		if ($page->template == 'admin') return;

		$output = $event->return;
		if (strpos($output, 'stocks-ticker') === false
			&& strpos($output, 'stocks-untracked') === false) return;

		$moduleUrl = $this->wire('config')->urls->siteModules . 'Stocks/';
		$theme     = $this->renderer()->getTheme();
		$inject    = '';

		if ($this->inject_css && $theme === StocksRenderer::THEME_VANILLA) {
			$inject .= '<link rel="stylesheet" href="' . $moduleUrl . 'css/stocks.css">' . "\n";
		}

		if ($this->inject_js) {
			$jsConfig = json_encode($this->renderer()->getJsConfig());
			$inject  .= '<script>window.StocksConfig=' . $jsConfig . ';</script>' . "\n";
			$inject  .= '<script src="' . $moduleUrl . 'js/stocks.js" defer></script>' . "\n";
		}

		if ($inject) {
			$event->return = str_replace('</head>', $inject . '</head>', $output);
		}
	}

	public function injectAdminAssets(HookEvent $event) {
		$page = $event->object;
		if ($page->template != 'admin') return;

		// Inject only on Stocks or TextFormatterStocks config pages
		$name  = (string) $this->wire('input')->get('name');
		$reset = (string) $this->wire('input')->get('reset');
		if (!in_array($name, ['Stocks', 'TextFormatterStocks']) && $reset !== '2') return;
		// reset=2 is ProcessWire module install/uninstall page — skip it
		if ($reset === '2') return;

		$url    = $this->wire('config')->urls->siteModules . 'Stocks/';
		$output = $event->return;
		$inject = '<script src="' . $url . 'js/stocks-admin.js"></script>';

		$event->return = str_replace('</body>', $inject . '</body>', $output);
	}

	// =========================================================
	// Module Config
	// =========================================================

	public static function getModuleConfigInputfields(array $data) {
		$modules  = wire('modules');
		$fields   = new InputfieldWrapper();
		$defaults = self::getDefaultConfig();

		foreach ($defaults as $k => $v) {
			if (!isset($data[$k])) $data[$k] = $v;
		}

		// Handle incoming companies_json POST update
		self::handleConfigActions($data);

		// ── Tracked Companies ─────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label       = 'Tracked Companies';
		$fs->icon        = 'building';
		$fs->description = 'Only companies in this list will show live stock badges. '
			. 'Others are rendered as plain text (or hidden) per the fallback setting below.';

			$f = $modules->get('InputfieldMarkup');
			$f->label = 'Add Company';
			$f->icon  = 'plus-circle';
			$f->value = self::renderAddForm();
			$fs->add($f);

			$manager   = StocksCompanyManager::fromJson($data['companies_json']);
			$companies = $manager->all();

			$f = $modules->get('InputfieldMarkup');
			$f->label = 'Company List (' . count($companies) . ' tracked)';
			$f->icon  = 'list';
			$f->value = self::renderCompanyTable($companies);
			$fs->add($f);

			$f = $modules->get('InputfieldHidden');
			$f->attr('name', 'companies_json');
			$f->attr('value', $data['companies_json']);
			$f->attr('id', 'stocks_companies_json');
			$fs->add($f);

			$f = $modules->get('InputfieldMarkup');
			$f->label     = 'Bulk Import';
			$f->icon      = 'upload';
			$f->collapsed = Inputfield::collapsedYes;
			$f->value     = self::renderBulkImport();
			$fs->add($f);

		$fields->add($fs);

		// ── Untracked behaviour ───────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label       = 'Untracked Tickers';
		$fs->icon        = 'question-circle';
		$fs->description = 'What to show when a ticker is found in text but is not in your company list.';

			$f = $modules->get('InputfieldRadios');
			$f->attr('name', 'untracked_mode');
			$f->label = 'Fallback rendering';
			$f->addOption('plain',        'Plain text — show as <span> (no badge, no API call)');
			$f->addOption('hide',         'Hide — remove mention completely');
			$f->addOption('badge_nodata', 'Empty badge — badge shell with N/A (no API call)');
			$f->attr('value', $data['untracked_mode']);
			$f->optionColumns = 1;
			$fs->add($f);

			$f = $modules->get('InputfieldMarkup');
			$f->label = 'Mode Preview';
			$f->value = self::renderUntrackedPreview();
			$fs->add($f);

		$fields->add($fs);

		// ── API & Cache ───────────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label     = 'API & Cache';
		$fs->icon      = 'plug';
		$fs->collapsed = Inputfield::collapsedYes;

			$f = $modules->get('InputfieldSelect');
			$f->attr('name', 'api_provider');
			$f->label       = 'API Provider';
			$f->description = 'Select the data source for stock quotes.';
			$f->addOption('yahoo',        'Yahoo Finance (free, no key required)');
			$f->addOption('finnhub',      'Finnhub (free 60 req/min, key required)');
			
			$f->addOption('alphavantage', 'Alpha Vantage (free 25 req/day, key required)');
			$f->attr('value', $data['api_provider']);
			$fs->add($f);

			$f = $modules->get('InputfieldMarkup');
			$f->label = 'Provider Comparison';
			$f->value = '<table class="uk-table uk-table-small uk-table-divider uk-table-hover" style="font-size:12px;">'
				. '<thead><tr><th>Provider</th><th>Cost</th><th>Rate Limit</th><th>Data Quality</th><th>Key</th></tr></thead>'
				. '<tbody>'
				. '<tr><td><strong>Yahoo Finance</strong></td><td><span class="uk-label uk-label-success">Free</span></td><td class="uk-text-muted">Unofficial</td><td>Price, 52w, exchange</td><td class="uk-text-muted">—</td></tr>'
				. '<tr><td><strong>Finnhub</strong></td><td><span class="uk-label uk-label-success">Free tier</span></td><td class="uk-text-muted">60 req/min</td><td>Real-time US, profile, metrics</td><td><a href="https://finnhub.io/register" target="_blank" class="uk-link">finnhub.io</a></td></tr>'
				. '<tr><td><strong>Alpha Vantage</strong></td><td><span class="uk-label uk-label-success">Free tier</span></td><td class="uk-text-muted">25 req/day</td><td>Basic — no 52w, no currency</td><td><a href="https://alphavantage.co" target="_blank" class="uk-link">alphavantage.co</a></td></tr>'
				. '</tbody></table>';
			$fs->add($f);

			$f = $modules->get('InputfieldText');
			$f->attr('name', 'api_key');
			$f->label       = 'API Key';
			$f->description = 'Required for Finnhub and Alpha Vantage.';
			$f->notes       = 'Finnhub: get free key at finnhub.io/register · Alpha Vantage: alphavantage.co/support/#api-key';
			$f->attr('value', $data['api_key']);
			$f->showIf = 'api_provider!=yahoo';
			$fs->add($f);

			$f = $modules->get('InputfieldInteger');
			$f->attr('name', 'cache_time');
			$f->label = 'Cache Duration (seconds)';
			$f->attr('value', (int) $data['cache_time']);
			$f->min = 60;
			$fs->add($f);

			$f = $modules->get('InputfieldInteger');
			$f->attr('name', 'request_timeout');
			$f->label = 'HTTP Timeout (seconds)';
			$f->attr('value', (int) $data['request_timeout']);
			$f->min = 3;
			$f->max = 30;
			$fs->add($f);

		$fields->add($fs);

		// ── UI Theme ──────────────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label     = 'UI Theme';
		$fs->icon      = 'paint-brush';
		$fs->collapsed = Inputfield::collapsedYes;

			$f = $modules->get('InputfieldSelect');
			$f->attr('name', 'ui_theme');
			$f->label = 'CSS Framework';
			$f->addOption('auto',      'Auto-detect (recommended)');
			$f->addOption('vanilla',   'Vanilla CSS (built-in)');
			$f->addOption('tailwind',  'Tailwind CSS');
			$f->addOption('bootstrap', 'Bootstrap 5');
			$f->addOption('uikit',     'UIkit 3');
			$f->attr('value', $data['ui_theme']);
			$fs->add($f);

			$f = $modules->get('InputfieldCheckbox');
			$f->attr('name', 'inject_css');
			$f->label       = 'Inject built-in CSS (Vanilla only)';
			$f->description = 'Disable if you include stocks.css manually.';
			$f->attr('checked', $data['inject_css'] ? 'checked' : '');
			$f->attr('value', 1);
			$fs->add($f);

			$f = $modules->get('InputfieldCheckbox');
			$f->attr('name', 'inject_js');
			$f->label       = 'Inject popup JavaScript';
			$f->description = 'Disable if you include stocks.js manually.';
			$f->attr('checked', $data['inject_js'] ? 'checked' : '');
			$f->attr('value', 1);
			$fs->add($f);

		$fields->add($fs);

		// ── Cache management ──────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label     = 'Cache';
		$fs->icon      = 'database';
		$fs->collapsed = Inputfield::collapsedYes;

			$cacheDir   = wire('config')->paths->cache . ($data['cache_dir'] ?? 'stocks') . '/';
			$cacheFiles = glob($cacheDir . '*.json') ?: [];
			$totalSize  = array_sum(array_map('filesize', $cacheFiles));

			$rows = '';
			foreach ($cacheFiles as $file) {
				$t   = basename($file, '.json');
				$age = time() - filemtime($file);
				$rows .= '<tr>'
					. '<td><code>' . $t . '</code></td>'
					. '<td class="uk-text-muted">' . round(filesize($file) / 1024, 1) . ' KB</td>'
					. '<td class="uk-text-muted">' . ($age < 60 ? $age . 's' : round($age / 60) . 'min') . ' ago</td>'
					. '<td><a href="?name=Stocks&clearCache=' . urlencode($t)
					. '" class="uk-button uk-button-danger">'
					. '<span uk-icon="icon:trash;ratio:0.7"></span></a></td>'
					. '</tr>';
			}

			$f = $modules->get('InputfieldMarkup');
			$f->label = count($cacheFiles) . ' cached files / ' . round($totalSize / 1024, 1) . ' KB total';
			$f->value = $rows
				? '<table class="uk-table uk-table-small uk-table-divider" style="font-size:12px;">'
				  . '<thead><tr><th>Ticker</th><th>Size</th><th>Age</th><th></th></tr></thead>'
				  . '<tbody>' . $rows . '</tbody></table>'
				  . '<a href="?name=Stocks&clearCache=ALL" class="uk-button uk-button-danger">'
				  . '<span uk-icon="icon:trash;ratio:0.8" class="uk-margin-small-right"></span>Clear All Cache</a>'
				: '<p class="uk-text-muted uk-text-small">No cache files yet.</p>';
			$fs->add($f);

		$fields->add($fs);

		// Handle cache clear
		$cc = wire('input')->get('clearCache');
		if ($cc) {
			$stocks = wire('modules')->get('Stocks');
			$count  = $cc === 'ALL' ? $stocks->clearCache() : $stocks->clearCache($cc);
			wire('session')->message("Cache cleared: {$count} file(s).");
		}

		return $fields;
	}

	// =========================================================
	// Config UI HTML builders
	// =========================================================

	protected static function handleConfigActions(array &$data) {
		$newJson = wire('input')->post('companies_json');
		if ($newJson !== null) {
			$decoded = json_decode($newJson, true);
			if (is_array($decoded)) {
				$data['companies_json'] = $newJson;
			}
		}
	}

	protected static function renderAddForm() {
		return <<<HTML
		<div class="uk-margin-small-bottom">
		    <div uk-grid class="uk-grid-small uk-flex-bottom">
		        <div class="uk-width-1-6@m uk-width-1-2@s">
		            <label class="uk-form-label">Ticker <span class="uk-text-danger">*</span></label>
		            <div class="uk-form-controls">
		                <input type="text" id="sa_ticker" placeholder="AAPL"
		                       class="uk-input uk-form-small"
		                       style="font-family:monospace;text-transform:uppercase;">
		            </div>
		        </div>
		        <div class="uk-width-1-3@m uk-width-1-2@s">
		            <label class="uk-form-label">Company Name</label>
		            <div class="uk-form-controls">
		                <input type="text" id="sa_name" placeholder="Apple Inc." class="uk-input uk-form-small">
		            </div>
		        </div>
		        <div class="uk-width-1-3@m uk-width-1-1@s">
		            <label class="uk-form-label">
		                Aliases
		                <span class="uk-text-muted" style="font-weight:400;font-size:11px;">(comma-separated)</span>
		            </label>
		            <div class="uk-form-controls">
		                <input type="text" id="sa_aliases" placeholder="apple, iphone" class="uk-input uk-form-small">
		            </div>
		        </div>
		        <div class="uk-width-auto@m uk-width-1-2@s">
		            <label class="uk-text-small" style="cursor:pointer;white-space:nowrap;">
		                <input type="checkbox" id="sa_parse" class="uk-checkbox" checked>
		                <span class="uk-margin-small-left">Auto-parse</span>
		            </label>
		        </div>
		        <div class="uk-width-auto@m uk-width-1-2@s uk-text-right@s">
		            <button type="button" onclick="StocksAdmin.addCompany()" class="uk-button uk-button-primary">
		                <span uk-icon="icon:plus;ratio:0.8" class="uk-margin-small-right"></span>Add
		            </button>
		        </div>
		    </div>
		    <div id="sa_error" class="uk-alert uk-alert-warning uk-margin-small-top" style="display:none;"></div>
		</div>
		HTML;
	}

	protected static function renderCompanyTable(array $companies) {
		if (empty($companies)) {
			return '<div class="uk-alert uk-alert-primary uk-text-center uk-text-muted">'
				. '<span uk-icon="icon:info" class="uk-margin-small-right"></span>'
				. 'No companies tracked yet. Add your first company above.'
				. '</div>';
		}

		$rows = '';
		foreach ($companies as $c) {
			$ticker         = htmlspecialchars($c['ticker']);
			$name           = htmlspecialchars($c['name'] ?? '');
			$aliases        = htmlspecialchars(implode(', ', $c['aliases'] ?? []));
			$enabled        = !empty($c['enabled']);
			$parse          = !empty($c['parse']);
			$parseChecked   = $parse   ? 'checked' : '';
			$enabledChecked = $enabled ? 'checked' : '';

			$statusLabel = $enabled
				? '<span class="uk-label uk-label-success" style="font-size:10px;">Active</span>'
				: '<span class="uk-label" style="font-size:10px;background:var(--pw-muted-color);">Off</span>';

			$rows .= '<tr id="stocks-row-' . $ticker . '" data-ticker="' . $ticker . '"'
				. ($enabled ? '' : ' class="uk-text-muted"') . '>'
				. '<td>' . $statusLabel . '</td>'
				. '<td><code class="uk-text-bold" style="font-size:13px;">' . $ticker . '</code></td>'
				. '<td>'
					. '<span class="sa-view-name">' . $name . '</span>'
					. '<input class="sa-edit-name uk-input uk-form-small" type="text" value="' . $name . '" style="display:none;">'
				. '</td>'
				. '<td>'
					. '<span class="sa-view-aliases uk-text-muted uk-text-small">' . $aliases . '</span>'
					. '<input class="sa-edit-aliases uk-input uk-form-small" type="text" value="' . $aliases . '" '
					. 'style="display:none;" placeholder="alias1, alias2">'
				. '</td>'
				. '<td class="uk-text-center">'
					. '<label class="uk-text-small" style="cursor:pointer;">'
					. '<input type="checkbox" class="uk-checkbox sa-toggle-parse" data-ticker="' . $ticker . '" ' . $parseChecked . '>'
					. '</label>'
				. '</td>'
				. '<td class="uk-text-center">'
					. '<label class="uk-text-small" style="cursor:pointer;">'
					. '<input type="checkbox" class="uk-checkbox sa-toggle-enabled" data-ticker="' . $ticker . '" ' . $enabledChecked . '>'
					. '</label>'
				. '</td>'
				. '<td>'
					. '<ul class="uk-iconnav">'
					. '<li><a class="sa-edit-btn" href="#" uk-icon="icon:file-edit" title="Edit" onclick="StocksAdmin.editRow(\'' . $ticker . '\',event);return false;"></a></li>'
					. '<li><a class="sa-save-btn" href="#" uk-icon="icon:check" title="Save" style="display:none;" onclick="StocksAdmin.saveRow(\'' . $ticker . '\',event);return false;"></a></li>'
					. '<li><a class="uk-text-danger" href="#" uk-icon="icon:trash" title="Remove" onclick="StocksAdmin.removeCompany(\'' . $ticker . '\');return false;"></a></li>'
					. '</ul>'
				. '</td>'
				. '</tr>';
		}

		return '<table class="uk-table uk-table-small uk-table-divider uk-table-hover" id="stocks-company-table" style="table-layout:fixed;width:100%;">'
			. '<colgroup>'
			. '<col style="width:70px;">'
			. '<col style="width:80px;">'
			. '<col>'
			. '<col>'
			. '<col style="width:90px;">'
			. '<col style="width:70px;">'
			. '<col style="width:100px;">'
			. '</colgroup>'
			. '<thead><tr>'
			. '<th></th>'
			. '<th>Ticker</th>'
			. '<th>Company Name</th>'
			. '<th>Aliases</th>'
			. '<th class="uk-text-center">Auto-parse</th>'
			. '<th class="uk-text-center">Active</th>'
			. '<th></th>'
			. '</tr></thead>'
			. '<tbody>' . $rows . '</tbody>'
			. '</table>'
			. '<div class="pw-notes uk-margin-small-top">'
			. '<strong>Auto-parse ON</strong> — company name/aliases trigger auto badge in text fields. &nbsp;'
			. '<strong>Auto-parse OFF</strong> — badge only for explicit <code>[stock:TICKER]</code> tags. &nbsp;'
			. '<strong>Active OFF</strong> — treated as untracked.'
			. '</div>';
	}

	protected static function renderBulkImport() {
		return <<<HTML
		<div class="uk-margin-small-top">
		    <p class="uk-text-small uk-text-muted">
		        One ticker per line. Accepted formats:<br>
		        <code>AAPL</code> &nbsp;&mdash;&nbsp;
		        <code>AAPL=Apple Inc.</code> &nbsp;&mdash;&nbsp;
		        <code>AAPL=Apple Inc.=apple,iphone,ios</code>
		    </p>
		    <textarea id="sa_bulk_input" rows="6"
		              placeholder="AAPL=Apple Inc.=apple&#10;TSLA=Tesla=tesla motors&#10;MSFT=Microsoft&#10;GOOGL=Alphabet=google"
		              class="uk-textarea uk-form-small"
		              style="font-family:monospace;resize:vertical;"></textarea>
		    <div class="uk-flex uk-flex-middle uk-margin-small-top" style="gap:12px;">
		        <button type="button" onclick="StocksAdmin.bulkImport()" class="uk-button uk-button-secondary">
		            <span uk-icon="icon:upload;ratio:0.8" class="uk-margin-small-right"></span>Import
		        </button>
		        <label class="uk-text-small" style="cursor:pointer;">
		            <input type="checkbox" id="sa_bulk_merge" class="uk-checkbox" checked>
		            <span class="uk-margin-small-left">Merge with existing (uncheck to replace all)</span>
		        </label>
		    </div>
		    <div id="sa_bulk_result" class="uk-margin-small-top" style="display:none;"></div>
		</div>
		HTML;
	}

	protected static function renderUntrackedPreview() {
		return <<<HTML
		<dl class="uk-description-list uk-description-list-divider uk-text-small">
		    <dt><code>plain</code></dt>
		    <dd class="uk-text-muted">UNKNOWN &rarr; plain text, no badge, no API call</dd>
		    <dt><code>hide</code></dt>
		    <dd class="uk-text-muted"><em>(nothing)</em> &rarr; removed from output entirely</dd>
		    <dt><code>badge_nodata</code></dt>
		    <dd>
		        <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 8px;
		                     border-radius:4px;font-family:monospace;font-size:12px;
		                     background:var(--pw-inputs-background);border:1px solid var(--pw-border-color);">
		            <strong>UNKNOWN</strong>
		            <span class="uk-text-muted">N/A</span>
		        </span>
		        <span class="uk-text-muted"> &rarr; badge shell, no API call</span>
		    </dd>
		</dl>
		HTML;
	}
}
