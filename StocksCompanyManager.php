<?php

/**
 * StocksCompanyManager
 *
 * Manages the list of tracked companies stored in module config.
 * Each company entry:
 *   ticker   : string  AAPL
 *   name     : string  Apple Inc.
 *   aliases  : array   ['apple', 'iphone maker']
 *   parse    : bool    true = participate in text auto-parsing
 *   enabled  : bool    true = fetch data / show badge
 *   currency : string  override currency (empty = use module default)
 *   note     : string  internal admin note
 */
class StocksCompanyManager {

	/** @var array */
	protected $companies = [];

	/** @var array|null Lookup index: normalized string → ticker */
	protected $index = null;

	public function __construct(array $companies = []) {
		$this->companies = $companies;
	}

	// =========================================================
	// CRUD
	// =========================================================

	/**
	 * Get all companies
	 *
	 * @param bool $enabledOnly
	 * @return array
	 */
	public function all($enabledOnly = false) {
		if (!$enabledOnly) return $this->companies;
		return array_values(array_filter($this->companies, fn($c) => !empty($c['enabled'])));
	}

	/**
	 * Get companies with parse=true and enabled=true
	 *
	 * @return array
	 */
	public function parseable() {
		return array_values(array_filter(
			$this->companies,
			fn($c) => !empty($c['enabled']) && !empty($c['parse'])
		));
	}

	/**
	 * Get company by ticker
	 *
	 * @param string $ticker
	 * @return array|null
	 */
	public function get($ticker) {
		$ticker = strtoupper(trim($ticker));
		foreach ($this->companies as $company) {
			if (strtoupper($company['ticker']) === $ticker) return $company;
		}
		return null;
	}

	/**
	 * Add or update a company
	 *
	 * @param array $company
	 * @return self
	 */
	public function set(array $company) {
		$ticker = strtoupper(trim($company['ticker'] ?? ''));
		if (!$ticker) return $this;

		$company['ticker']  = $ticker;
		$company['aliases'] = $this->normalizeAliases($company['aliases'] ?? []);

		foreach ($this->companies as $i => $existing) {
			if (strtoupper($existing['ticker']) === $ticker) {
				$this->companies[$i] = array_merge($existing, $company);
				$this->index = null;
				return $this;
			}
		}

		$this->companies[] = self::normalizeEntry($company);
		$this->index = null;
		return $this;
	}

	/**
	 * Remove company by ticker
	 *
	 * @param string $ticker
	 * @return self
	 */
	public function remove($ticker) {
		$ticker = strtoupper(trim($ticker));
		$this->companies = array_values(
			array_filter($this->companies, fn($c) => strtoupper($c['ticker']) !== $ticker)
		);
		$this->index = null;
		return $this;
	}

	/**
	 * Set parse flag
	 *
	 * @param string $ticker
	 * @param bool   $state
	 * @return self
	 */
	public function setParse($ticker, $state) {
		return $this->updateField($ticker, 'parse', (bool) $state);
	}

	/**
	 * Set enabled flag
	 *
	 * @param string $ticker
	 * @param bool   $state
	 * @return self
	 */
	public function setEnabled($ticker, $state) {
		return $this->updateField($ticker, 'enabled', (bool) $state);
	}

	// =========================================================
	// Lookup
	// =========================================================

	/**
	 * Resolve a string (name / alias / ticker) to an enabled company
	 *
	 * @param string $input
	 * @param bool   $parseableOnly Only return if parse=true
	 * @return array|null
	 */
	public function resolve($input, $parseableOnly = false) {
		$this->buildIndex();
		$key = $this->normalizeKey($input);

		if (!isset($this->index[$key])) {
			$stripped = $this->stripSuffixes($key);
			if ($stripped !== $key && isset($this->index[$stripped])) {
				$key = $stripped;
			} else {
				foreach ($this->index as $indexKey => $t) {
					if (strpos($indexKey, $key) === 0 || strpos($key, $indexKey) === 0) {
						$key = $indexKey;
						break;
					}
				}
			}
		}

		if (!isset($this->index[$key])) return null;

		$company = $this->get($this->index[$key]);
		if (!$company) return null;
		if (empty($company['enabled'])) return null;
		if ($parseableOnly && empty($company['parse'])) return null;

		return $company;
	}

	/**
	 * Check if ticker is tracked and enabled
	 *
	 * @param string $ticker
	 * @return bool
	 */
	public function isTracked($ticker) {
		$company = $this->get($ticker);
		return $company && !empty($company['enabled']);
	}

	/**
	 * Get all tickers
	 *
	 * @param bool $enabledOnly
	 * @return array
	 */
	public function tickers($enabledOnly = true) {
		$list = $this->all($enabledOnly);
		return array_map(fn($c) => $c['ticker'], $list);
	}

	// =========================================================
	// Serialization
	// =========================================================

	/**
	 * Export to JSON string for module config storage
	 *
	 * @return string
	 */
	public function toJson() {
		return json_encode(array_values($this->companies), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Import from JSON string
	 *
	 * @param string $json
	 * @return self
	 */
	public static function fromJson($json) {
		if (empty(trim((string) $json))) return new self([]);

		$data = json_decode($json, true);
		if (!is_array($data)) return new self([]);

		$companies = [];
		foreach ($data as $entry) {
			if (empty($entry['ticker'])) continue;
			$companies[] = self::normalizeEntry($entry);
		}

		return new self($companies);
	}

	/**
	 * Import from simple text format
	 * One per line: TICKER or TICKER=Name or TICKER=Name=alias1,alias2
	 *
	 * @param string $text
	 * @return self
	 */
	public static function fromSimpleText($text) {
		$companies = [];
		foreach (explode("\n", $text) as $line) {
			$line = trim($line);
			if (empty($line) || $line[0] === '#') continue;

			$parts  = explode('=', $line, 3);
			$ticker = strtoupper(trim($parts[0]));
			if (!preg_match('/^[A-Z0-9]{1,6}(-[A-Z]{1,4})?$/', $ticker)) continue;

			$companies[] = self::normalizeEntry([
				'ticker'  => $ticker,
				'name'    => isset($parts[1]) ? trim($parts[1]) : $ticker,
				'aliases' => isset($parts[2]) ? array_map('trim', explode(',', $parts[2])) : [],
				'parse'   => true,
				'enabled' => true,
			]);
		}

		return new self($companies);
	}

	// =========================================================
	// Static helpers
	// =========================================================

	/**
	 * Check if a string looks like a ticker symbol
	 *
	 * @param string $str
	 * @return bool
	 */
	public static function looksLikeTicker($str) {
		return (bool) preg_match('/^[A-Z]{1,6}(-[A-Z]{2,4}|\.[A-Z]{2})?$/', trim($str));
	}

	/**
	 * Normalize a company entry to full structure
	 *
	 * @param array $entry
	 * @return array
	 */
	public static function normalizeEntry(array $entry) {
		return [
			'ticker'   => strtoupper(trim($entry['ticker'] ?? '')),
			'name'     => trim($entry['name'] ?? ''),
			'aliases'  => array_values(array_filter(array_map('trim', (array) ($entry['aliases'] ?? [])))),
			'parse'    => isset($entry['parse']) ? (bool) $entry['parse'] : true,
			'enabled'  => isset($entry['enabled']) ? (bool) $entry['enabled'] : true,
			'currency' => trim($entry['currency'] ?? ''),
			'note'     => trim($entry['note'] ?? ''),
		];
	}

	// =========================================================
	// Internal
	// =========================================================

	protected function buildIndex() {
		if ($this->index !== null) return;
		$this->index = [];

		foreach ($this->companies as $company) {
			if (empty($company['enabled'])) continue;
			$ticker = strtoupper($company['ticker']);

			$this->index[$this->normalizeKey($ticker)] = $ticker;

			if (!empty($company['name'])) {
				$this->index[$this->normalizeKey($company['name'])] = $ticker;
			}

			foreach ($company['aliases'] ?? [] as $alias) {
				if (!empty($alias)) {
					$this->index[$this->normalizeKey($alias)] = $ticker;
				}
			}
		}
	}

	protected function normalizeKey($str) {
		$str = mb_strtolower(trim($str), 'UTF-8');
		$str = str_replace(['&', '.', ','], ['and', '', ''], $str);
		$str = preg_replace('/\s+/', ' ', $str);
		return trim($str);
	}

	protected function stripSuffixes($name) {
		foreach ([
			' inc', ' corp', ' corporation', ' ltd', ' limited',
			' llc', ' plc', ' ag', ' sa', ' nv', ' co',
			' company', ' group', ' holdings', ' technologies',
			' technology', ' tech', ' systems', ' solutions',
		] as $sfx) {
			if (str_ends_with($name, $sfx)) {
				return rtrim(substr($name, 0, -strlen($sfx)));
			}
		}
		return $name;
	}

	protected function normalizeAliases($aliases) {
		if (is_string($aliases)) {
			$aliases = array_map('trim', explode(',', $aliases));
		}
		return array_values(array_filter(array_map('trim', (array) $aliases)));
	}

	protected function updateField($ticker, $field, $value) {
		$ticker = strtoupper(trim($ticker));
		foreach ($this->companies as $i => $company) {
			if (strtoupper($company['ticker']) === $ticker) {
				$this->companies[$i][$field] = $value;
				$this->index = null;
				break;
			}
		}
		return $this;
	}
}
