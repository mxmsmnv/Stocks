<?php

/**
 * StocksProviderYahoo
 *
 * Yahoo Finance v8 — free, no API key required.
 * Endpoint: /v8/finance/chart/{ticker}
 *
 * Returns: price, high, low, prev close, volume, currency,
 *          exchange, market state, 52-week high/low.
 *
 * Note: Unofficial API, may change without notice.
 */
class StocksProviderYahoo extends StocksProviderBase {

	const URL = 'https://query1.finance.yahoo.com/v8/finance/chart/%s?interval=1d&range=1d';

	public function getProviderName() { return 'yahoo'; }
	public function requiresKey()     { return false; }

	public function fetchQuote($ticker) {
		$url = sprintf(self::URL, urlencode($ticker));
		$raw = $this->httpGet($url);
		$json = $this->decodeJson($raw);
		if (!$json) return false;

		$meta = $json['chart']['result'][0]['meta'] ?? null;
		if (empty($meta)) return false;

		return $this->normalize($ticker, $meta);
	}

	protected function normalize($ticker, array $meta) {
		$price     = (float) ($meta['regularMarketPrice'] ?? 0);
		$prevClose = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0);
		$change    = $prevClose ? $price - $prevClose : 0;
		$changePct = $prevClose ? ($change / $prevClose) * 100 : 0;

		return [
			'ticker'              => strtoupper($ticker),
			'name'                => $meta['longName'] ?? $meta['shortName'] ?? strtoupper($ticker),
			'price'               => $price,
			'price_open'          => (float) ($meta['regularMarketDayHigh'] ?? 0),
			'price_high'          => (float) ($meta['regularMarketDayHigh'] ?? 0),
			'price_low'           => (float) ($meta['regularMarketDayLow'] ?? 0),
			'price_prev_close'    => $prevClose,
			'change'              => $change,
			'change_percent'      => $changePct,
			'volume'              => (int) ($meta['regularMarketVolume'] ?? 0),
			'avg_volume'          => 0,
			'market_cap'          => 0,
			'currency'            => $meta['currency'] ?? 'USD',
			'exchange'            => $meta['exchangeName'] ?? '',
			'market_state'        => $meta['marketState'] ?? '',
			'fifty_two_week_high' => (float) ($meta['fiftyTwoWeekHigh'] ?? 0),
			'fifty_two_week_low'  => (float) ($meta['fiftyTwoWeekLow'] ?? 0),
			'pe_ratio'            => null,
			'dividend_yield'      => null,
			'eps'                 => null,
			'beta'                => null,
			'fetched_at'          => time(),
			'provider'            => $this->getProviderName(),
		];
	}
}
