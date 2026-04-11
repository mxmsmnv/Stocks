<?php

/**
 * StocksAPI
 *
 * Facade that loads the appropriate provider and delegates fetchQuote() to it.
 *
 * Providers live in /providers/ as separate files:
 *   StocksProviderYahoo.php        — free, no key
 *   StocksProviderFinnhub.php      — free 60 req/min, key required
 *   StocksProviderAlphaVantage.php — free 25 req/day, key required
 *
 * To add a new provider:
 *   1. Create /providers/StocksProviderMyNew.php extending StocksProviderBase
 *   2. Add 'mynew' => 'StocksProviderMyNew' to PROVIDERS map below
 *   3. Add the option to the admin UI in Stocks.module.php
 */
class StocksAPI {

	const PROVIDERS = [
		'yahoo'        => 'StocksProviderYahoo',
		'finnhub'      => 'StocksProviderFinnhub',
		'alphavantage' => 'StocksProviderAlphaVantage',
	];

	/** @var StocksProviderBase */
	protected $provider;

	public function __construct($providerKey = 'yahoo', $apiKey = '', $timeout = 10) {
		$this->provider = self::makeProvider($providerKey, $apiKey, $timeout);
	}

	/**
	 * Fetch quote — delegates to the active provider
	 */
	public function fetchQuote($ticker) {
		return $this->provider->fetchQuote($ticker);
	}

	/**
	 * Instantiate and return a provider object
	 */
	public static function makeProvider($key, $apiKey = '', $timeout = 10) {
		$dir = __DIR__ . '/providers/';

		if (!class_exists('StocksProviderBase')) {
			require_once $dir . 'StocksProviderBase.php';
		}

		$className = self::PROVIDERS[$key] ?? self::PROVIDERS['yahoo'];
		$file      = $dir . $className . '.php';

		if (!class_exists($className)) {
			if (!file_exists($file)) {
				wire('log')->save('stocks', "Provider file not found: {$file}. Falling back to Yahoo.");
				require_once $dir . 'StocksProviderYahoo.php';
				return new StocksProviderYahoo('', $timeout);
			}
			require_once $file;
		}

		return new $className($apiKey, $timeout);
	}

	/**
	 * Return list of available providers with metadata
	 */
	public static function getProviderList() {
		return [
			'yahoo' => [
				'label'        => 'Yahoo Finance (free, no key required)',
				'requires_key' => false,
				'free_limit'   => 'Unofficial API',
				'url'          => '',
			],
			'finnhub' => [
				'label'        => 'Finnhub (free 60 req/min, key required)',
				'requires_key' => true,
				'free_limit'   => '60 req/min',
				'url'          => 'https://finnhub.io/register',
			],
			'alphavantage' => [
				'label'        => 'Alpha Vantage (free 25 req/day, key required)',
				'requires_key' => true,
				'free_limit'   => '25 req/day',
				'url'          => 'https://www.alphavantage.co/support/#api-key',
			],
		];
	}

	/**
	 * Format large numbers for display
	 */
	public static function formatLargeNumber($n) {
		if ($n >= 1_000_000_000_000) return number_format($n / 1_000_000_000_000, 2) . 'T';
		if ($n >= 1_000_000_000)     return number_format($n / 1_000_000_000, 2) . 'B';
		if ($n >= 1_000_000)         return number_format($n / 1_000_000, 2) . 'M';
		if ($n >= 1_000)             return number_format($n / 1_000, 2) . 'K';
		return number_format($n, 0);
	}
}
