<?php

/**
 * StocksProviderBase
 *
 * Abstract base class for all stock data providers.
 * Each provider extends this and implements fetchQuote().
 */
abstract class StocksProviderBase {

	/** @var string API key (empty for keyless providers) */
	protected $apiKey;

	/** @var int HTTP timeout in seconds */
	protected $timeout;

	public function __construct($apiKey = '', $timeout = 10) {
		$this->apiKey  = $apiKey;
		$this->timeout = (int) $timeout;
	}

	/**
	 * Fetch and normalize quote data for a ticker
	 *
	 * @param string $ticker
	 * @return array|false Normalized quote array or false on failure
	 */
	abstract public function fetchQuote($ticker);

	/**
	 * Provider identifier string (used in cached data)
	 *
	 * @return string
	 */
	abstract public function getProviderName();

	/**
	 * Whether this provider requires an API key
	 *
	 * @return bool
	 */
	public function requiresKey() {
		return true;
	}

	// ── HTTP transport ────────────────────────────────────────

	protected function httpGet($url, array $extraHeaders = []) {
		if (function_exists('curl_init')) return $this->curlGet($url, $extraHeaders);
		return $this->fileGet($url);
	}

	protected function curlGet($url, array $extraHeaders = []) {
		$headers = array_merge([
			'Accept: application/json',
			'Accept-Language: en-US,en;q=0.9',
		], $extraHeaders);

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 3,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_ENCODING       => 'gzip, deflate',
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ProcessWire Stocks/1.0)',
			CURLOPT_HTTPHEADER     => $headers,
		]);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error    = curl_error($ch);
		curl_close($ch);

		if ($error || $httpCode !== 200) {
			wire('log')->save('stocks', "[{$this->getProviderName()}] HTTP {$httpCode} — {$url} — {$error}");
			return false;
		}

		return $response;
	}

	protected function fileGet($url) {
		$context = stream_context_create([
			'http' => [
				'method'  => 'GET',
				'timeout' => $this->timeout,
				'header'  => "User-Agent: Mozilla/5.0 (compatible; ProcessWire Stocks/1.0)\r\nAccept: application/json\r\n",
			],
			'ssl' => [
				'verify_peer'      => true,
				'verify_peer_name' => true,
			],
		]);

		$result = @file_get_contents($url, false, $context);
		if ($result === false) {
			wire('log')->save('stocks', "[{$this->getProviderName()}] file_get_contents failed — {$url}");
		}
		return $result;
	}

	protected function decodeJson($raw) {
		if (!$raw) return null;
		$data = json_decode($raw, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			wire('log')->save('stocks', "[{$this->getProviderName()}] JSON decode error: " . json_last_error_msg());
			return null;
		}
		return $data;
	}
}
