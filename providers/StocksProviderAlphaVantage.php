<?php

/**
 * StocksProviderAlphaVantage
 *
 * Alpha Vantage — free tier 25 req/day, API key required.
 * Docs: https://www.alphavantage.co/documentation/#latestprice
 *
 * Single request: GET /query?function=GLOBAL_QUOTE&symbol={ticker}
 * Returns: price, open, high, low, prev close, change, change%, volume.
 * Does NOT return: currency, 52-week range, market cap, company name.
 *
 * Limitations:
 *   - Always assumes USD (no currency field in response)
 *   - No 52-week high/low
 *   - Free plan: 25 req/day, 5 req/min
 *
 * Get key: https://www.alphavantage.co/support/#api-key
 */
class StocksProviderAlphaVantage extends StocksProviderBase {

	const URL = 'https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=%s&apikey=%s';

	public function getProviderName() { return 'alphavantage'; }

	public function fetchQuote($ticker) {
		if (empty($this->apiKey)) return false;

		$url  = sprintf(self::URL, urlencode($ticker), urlencode($this->apiKey));
		$data = $this->decodeJson($this->httpGet($url));

		if (!$data || empty($data['Global Quote'])) return false;

		return $this->normalize($ticker, $data['Global Quote']);
	}

	protected function normalize($ticker, array $q) {
		// "10. change percent" comes as "0.6141%" — strip everything except digits, dot, minus
		$changePct = (float) preg_replace('/[^0-9.\-]/', '', $q['10. change percent'] ?? '0');

		return [
			'ticker'              => strtoupper($ticker),
			'name'                => strtoupper($ticker),  // not available in GLOBAL_QUOTE
			'price'               => (float) ($q['05. price']          ?? 0),
			'price_open'          => (float) ($q['02. open']           ?? 0),
			'price_high'          => (float) ($q['03. high']           ?? 0),
			'price_low'           => (float) ($q['04. low']            ?? 0),
			'price_prev_close'    => (float) ($q['08. previous close'] ?? 0),
			'change'              => (float) ($q['09. change']         ?? 0),
			'change_percent'      => $changePct,
			'volume'              => (int)   ($q['06. volume']         ?? 0),
			'avg_volume'          => 0,
			'market_cap'          => 0,
			'currency'            => 'USD',  // not provided by API
			'exchange'            => '',
			'market_state'        => '',
			'fifty_two_week_high' => 0,      // not provided by GLOBAL_QUOTE
			'fifty_two_week_low'  => 0,
			'pe_ratio'            => null,
			'dividend_yield'      => null,
			'eps'                 => null,
			'beta'                => null,
			'fetched_at'          => time(),
			'provider'            => $this->getProviderName(),
		];
	}
}
