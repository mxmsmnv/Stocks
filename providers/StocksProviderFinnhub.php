<?php

/**
 * StocksProviderFinnhub
 *
 * Finnhub — free tier 60 req/min, API key required.
 * Docs: https://finnhub.io/docs/api/quote
 *
 * Makes up to three requests per ticker (profile + metric are optional):
 *   GET /quote          → real-time price data (c, d, dp, h, l, o, pc, t)
 *   GET /stock/profile2 → company name, currency, exchange, market cap
 *   GET /stock/metric   → 52-week high/low, PE ratio (basic metrics)
 *
 * Profile and metric requests are optional — badge renders even if they fail.
 * Free plan: US stocks real-time, international with delay.
 * Get key: https://finnhub.io/register
 */
class StocksProviderFinnhub extends StocksProviderBase {

	const URL_QUOTE   = 'https://finnhub.io/api/v1/quote?symbol=%s&token=%s';
	const URL_PROFILE = 'https://finnhub.io/api/v1/stock/profile2?symbol=%s&token=%s';
	const URL_METRIC  = 'https://finnhub.io/api/v1/stock/metric?symbol=%s&metric=all&token=%s';

	public function getProviderName() { return 'finnhub'; }

	public function fetchQuote($ticker) {
		if (empty($this->apiKey)) return false;

		// Required: quote data
		$quoteUrl = sprintf(self::URL_QUOTE, urlencode($ticker), urlencode($this->apiKey));
		$quote    = $this->decodeJson($this->httpGet($quoteUrl));

		if (!$quote || empty($quote['c']) || $quote['c'] == 0) return false;

		// Optional: company profile (name, currency, exchange, market cap)
		$profile = [];
		$profileData = $this->decodeJson($this->httpGet(
			sprintf(self::URL_PROFILE, urlencode($ticker), urlencode($this->apiKey))
		));
		if ($profileData && !empty($profileData['name'])) {
			$profile = $profileData;
		}

		// Optional: basic metrics (52-week high/low, PE ratio)
		// Finnhub returns these in /stock/metric, NOT in /stock/profile2
		$metrics = [];
		$metricData = $this->decodeJson($this->httpGet(
			sprintf(self::URL_METRIC, urlencode($ticker), urlencode($this->apiKey))
		));
		if ($metricData && !empty($metricData['metric'])) {
			$metrics = $metricData['metric'];
		}

		return $this->normalize($ticker, $quote, $profile, $metrics);
	}

	protected function normalize($ticker, array $q, array $profile, array $metrics) {
		// Finnhub returns marketCap in millions
		$marketCap = isset($profile['marketCapitalization'])
			? (float) $profile['marketCapitalization'] * 1_000_000
			: 0;

		// Estimate market state from last trade timestamp
		$lastTrade   = (int) ($q['t'] ?? 0);
		$marketState = ($lastTrade && (time() - $lastTrade) < 3600) ? 'REGULAR' : 'CLOSED';

		return [
			'ticker'              => strtoupper($ticker),
			'name'                => $profile['name'] ?? strtoupper($ticker),
			'price'               => (float) ($q['c']  ?? 0),
			'price_open'          => (float) ($q['o']  ?? 0),
			'price_high'          => (float) ($q['h']  ?? 0),
			'price_low'           => (float) ($q['l']  ?? 0),
			'price_prev_close'    => (float) ($q['pc'] ?? 0),
			'change'              => (float) ($q['d']  ?? 0),
			'change_percent'      => (float) ($q['dp'] ?? 0),
			'volume'              => 0,  // not in /quote endpoint
			'avg_volume'          => 0,
			'market_cap'          => $marketCap,
			'currency'            => $profile['currency'] ?? 'USD',
			'exchange'            => $profile['exchange'] ?? '',
			'market_state'        => $marketState,
			// Correct fields from /stock/metric endpoint
			'fifty_two_week_high' => (float) ($metrics['52WeekHigh'] ?? 0),
			'fifty_two_week_low'  => (float) ($metrics['52WeekLow']  ?? 0),
			'pe_ratio'            => isset($metrics['peBasicExclExtraTTM']) ? (float) $metrics['peBasicExclExtraTTM'] : null,
			'dividend_yield'      => isset($metrics['dividendYieldIndicatedAnnual']) ? (float) $metrics['dividendYieldIndicatedAnnual'] : null,
			'eps'                 => isset($metrics['epsBasicExclExtraItemsTTM']) ? (float) $metrics['epsBasicExclExtraItemsTTM'] : null,
			'beta'                => isset($metrics['beta']) ? (float) $metrics['beta'] : null,
			'fetched_at'          => time(),
			'provider'            => $this->getProviderName(),
		];
	}
}

 *
 * Finnhub — free tier 60 req/min, API key required.
 * Docs: https://finnhub.io/docs/api/quote
 *
 * Makes two requests per ticker:
 *   GET /quote          → real-time price data
 *   GET /stock/profile2 → company name, currency, exchange, market cap
 *
 * Profile request is optional — badge renders even if it fails.
 * Free plan: US stocks real-time, international with delay.
 * Get key: https://finnhub.io/register
 */
class StocksProviderFinnhub extends StocksProviderBase {

	const URL_QUOTE   = 'https://finnhub.io/api/v1/quote?symbol=%s&token=%s';
	const URL_PROFILE = 'https://finnhub.io/api/v1/stock/profile2?symbol=%s&token=%s';

	public function getProviderName() { return 'finnhub'; }

	public function fetchQuote($ticker) {
		if (empty($this->apiKey)) return false;

		$quoteUrl = sprintf(self::URL_QUOTE, urlencode($ticker), urlencode($this->apiKey));
		$quote    = $this->decodeJson($this->httpGet($quoteUrl));

		if (!$quote || empty($quote['c']) || $quote['c'] == 0) return false;

		// Profile is optional — one extra request for name/currency/market cap
		$profile = [];
		$profileUrl = sprintf(self::URL_PROFILE, urlencode($ticker), urlencode($this->apiKey));
		$profileData = $this->decodeJson($this->httpGet($profileUrl));
		if ($profileData && !empty($profileData['name'])) {
			$profile = $profileData;
		}

		return $this->normalize($ticker, $quote, $profile);
	}

	protected function normalize($ticker, array $q, array $profile) {
		// Finnhub returns marketCap in millions
		$marketCap = isset($profile['marketCapitalization'])
			? (float) $profile['marketCapitalization'] * 1_000_000
			: 0;

		// Estimate market state from last trade timestamp
		$lastTrade   = (int) ($q['t'] ?? 0);
		$marketState = ($lastTrade && (time() - $lastTrade) < 3600) ? 'REGULAR' : 'CLOSED';

		return [
			'ticker'              => strtoupper($ticker),
			'name'                => $profile['name'] ?? strtoupper($ticker),
			'price'               => (float) ($q['c']  ?? 0),
			'price_open'          => (float) ($q['o']  ?? 0),
			'price_high'          => (float) ($q['h']  ?? 0),
			'price_low'           => (float) ($q['l']  ?? 0),
			'price_prev_close'    => (float) ($q['pc'] ?? 0),
			'change'              => (float) ($q['d']  ?? 0),
			'change_percent'      => (float) ($q['dp'] ?? 0),
			'volume'              => 0,  // not available in /quote endpoint
			'avg_volume'          => 0,
			'market_cap'          => $marketCap,
			'currency'            => $profile['currency'] ?? 'USD',
			'exchange'            => $profile['exchange'] ?? '',
			'market_state'        => $marketState,
			'fifty_two_week_high' => (float) ($profile['52WeekHigh'] ?? 0),
			'fifty_two_week_low'  => (float) ($profile['52WeekLow']  ?? 0),
			'pe_ratio'            => isset($profile['peRatio']) ? (float) $profile['peRatio'] : null,
			'dividend_yield'      => null,
			'eps'                 => null,
			'beta'                => null,
			'fetched_at'          => time(),
			'provider'            => $this->getProviderName(),
		];
	}
}
