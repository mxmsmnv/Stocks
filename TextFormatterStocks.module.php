<?php

/**
 * TextFormatter Stocks
 *
 * Parses stock mentions in text fields and replaces them with
 * interactive stock badges rendered by the Stocks module.
 *
 * Three parse modes:
 *   explicit  — only [stock:AAPL] and [stock:Apple Inc] tags
 *   dollar    — + $AAPL and #TSLA cashtag/hashtag notation
 *   auto      — + company names from the tracked list (Apple, Tesla...)
 *
 * @version 1.0.0
 */
class TextFormatterStocks extends TextFormatter implements Module, ConfigurableModule {

	public static function getModuleInfo() {
		return [
			'title'    => 'TextFormatter Stocks',
			'version'  => 100,
			'summary'  => 'Replaces [stock:TICKER], $TICKER, #TICKER and company names with stock badges',
			'author'   => 'Maxim Semenov',
			'href'     => 'https://smnv.org',
			'singular' => true,
			'autoload' => false,
			'icon'     => 'line-chart',
			'requires' => ['Stocks'],
		];
	}

	// Parse mode constants
	const MODE_EXPLICIT = 'explicit';
	const MODE_DOLLAR   = 'dollar';
	const MODE_AUTO     = 'auto';

	// Tag patterns
	const PATTERN_TAG    = '/\[stock:([^\]]{1,60}?)(?:\s+([^\]]*))?\]/iu';
	const PATTERN_DOLLAR = '/(?<![a-zA-Z])[\$#]([A-Z]{1,5}(?:-[A-Z]{2,4})?)\b/';

	public static function getDefaultConfig() {
		return [
			'parse_mode'     => self::MODE_DOLLAR,
			'excluded_words' => 'IT,AT,ON,AI,OR,GO',
			'skip_tags'      => 'pre,code,script,style,a',
			'min_word_len'   => 3,
		];
	}

	public function __construct() {
		parent::__construct();
		foreach (self::getDefaultConfig() as $k => $v) {
			$this->$k = $v;
		}
	}

	// =========================================================
	// TextFormatter interface
	// =========================================================

	public function format(&$str) {
		if (empty(trim($str))) return;
		if (strpos($str, '[stock:') === false
			&& $this->parse_mode === self::MODE_EXPLICIT) return;

		$excluded = $this->parseExcluded($this->excluded_words);
		$str      = $this->processHtmlSafely($str, $excluded);
	}

	// =========================================================
	// HTML-safe processing
	// =========================================================

	protected function processHtmlSafely($html, array $excluded) {
		// First, protect ALL existing HTML tags and entities by replacing them with placeholders.
		// This is the only reliable way to avoid regex operating inside HTML attributes.
		$tokens = [];
		$tokenized = preg_replace_callback(
			'/((?:<(?:[^<>"\']|"[^"]*"|\'[^\']*\')*>)|(?:&[a-zA-Z0-9#]+;))/s',
			function($m) use (&$tokens) {
				$key = "\x02T" . count($tokens) . "\x03";
				$tokens[$key] = $m[0];
				return $key;
			},
			$html
		);

		if ($tokenized === null) return $html;

		// Now split on token boundaries to get alternating [token, text, token, text...] segments
		$parts = preg_split('/(\x02T\d+\x03)/', $tokenized, -1, PREG_SPLIT_DELIM_CAPTURE);
		if (!$parts) return $html;

		$skipTags  = array_map('trim', explode(',', $this->skip_tags));
		$result    = '';
		$skipDepth = 0;
		$skipTag   = '';

		foreach ($parts as $part) {
			// Token (placeholder for an HTML tag or entity)
			if (isset($tokens[$part])) {
				$original = $tokens[$part];
				// Check if it's an HTML tag (not an entity)
				if ($original[0] === '<') {
					if (preg_match('/^<(' . implode('|', array_map('preg_quote', $skipTags)) . ')[\s>\/]/i', $original, $m)) {
						if (!preg_match('/\/>$/', $original)) {
							$skipDepth++;
							if ($skipDepth === 1) $skipTag = strtolower($m[1]);
						}
					}
					if ($skipDepth > 0 && preg_match('/^<\/(' . preg_quote($skipTag, '/') . ')>/i', $original)) {
						$skipDepth = max(0, $skipDepth - 1);
					}
				}
				$result .= $original;
				continue;
			}

			// Plain text segment — process only if not inside a skip tag
			$result .= ($skipDepth > 0) ? $part : $this->processTextNode($part, $excluded);
		}

		return $result;
	}

	protected function processTextNode($text, array $excluded) {
		if (empty(trim($text))) return $text;

		// Collect all replacement positions on the ORIGINAL text.
		// Never run a parser on text that already contains rendered HTML.
		$replacements = []; // [ [offset, length, html], ... ]

		// Step 1: explicit [stock:...] tags
		preg_match_all(self::PATTERN_TAG, $text, $m1, PREG_OFFSET_CAPTURE);
		foreach ($m1[0] as $i => $match) {
			$raw     = trim($m1[1][$i][0]);
			$options = $this->parseInlineOptions($m1[2][$i][0] ?? '');
			$ticker  = StocksCompanyManager::looksLikeTicker($raw)
				? strtoupper($raw)
				: $this->resolveNameToTicker($raw);
			if (!$ticker) {
				$html = '<span class="stocks-ticker stocks-unknown" '
					. 'title="Unknown: ' . htmlspecialchars($raw) . '" '
					. 'style="opacity:0.5;cursor:help;text-decoration:underline dotted;">'
					. htmlspecialchars($raw) . '</span>';
			} else {
				$html = $this->stocks()->renderBadge($ticker, $options);
			}
			$replacements[] = [$match[1], strlen($match[0]), $html];
		}

		// Step 2: $AAPL / #AAPL (only if mode allows)
		if (in_array($this->parse_mode, [self::MODE_DOLLAR, self::MODE_AUTO])) {
			preg_match_all(self::PATTERN_DOLLAR, $text, $m2, PREG_OFFSET_CAPTURE);
			foreach ($m2[0] as $i => $match) {
				$ticker = strtoupper($m2[1][$i][0]);
				if (in_array($ticker, $excluded)) continue;
				if (!$this->isOffsetFree($match[1], strlen($match[0]), $replacements)) continue;
				$replacements[] = [$match[1], strlen($match[0]), $this->stocks()->renderBadge($ticker)];
			}
		}

		// Step 3: company names (only in auto mode)
		if ($this->parse_mode === self::MODE_AUTO) {
			$manager   = $this->stocks()->companies();
			$parseable = $manager->parseable();
			$minLen    = max(2, (int) $this->min_word_len);
			if (!empty($parseable)) {
				$phraseMap = [];
				foreach ($parseable as $company) {
					$ticker = $company['ticker'];
					if (!empty($company['name'])) {
						$phrase = mb_strtolower(trim($company['name']), 'UTF-8');
						if (mb_strlen($phrase) >= $minLen) {
							$phraseMap[$phrase] = $ticker;
						}
					}
					foreach ($company['aliases'] as $alias) {
						if (!empty($alias)) {
							$phrase = mb_strtolower(trim($alias), 'UTF-8');
							if (mb_strlen($phrase) >= $minLen) {
								$phraseMap[$phrase] = $ticker;
							}
						}
					}
					// Note: do NOT add ticker itself as a phrase — tickers are handled by explicit [stock:] tags
					// and dollar/hashtag mode ($AAPL). Adding them here causes double rendering.
				}
				if (!empty($phraseMap)) {
					uksort($phraseMap, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
					$patterns = array_map(fn($p) => preg_quote($p, '/'), array_keys($phraseMap));
					$regex    = '/\b(' . implode('|', $patterns) . ')\b/ui';
					preg_match_all($regex, $text, $m3, PREG_OFFSET_CAPTURE);
					foreach ($m3[0] as $i => $match) {
						$phrase = mb_strtolower($match[0], 'UTF-8');
						$ticker = $phraseMap[$phrase] ?? null;
						if (!$ticker) continue;
						if (in_array(strtoupper($ticker), $excluded)) continue;
						if (!$this->isOffsetFree($match[1], strlen($match[0]), $replacements)) continue;
						$replacements[] = [$match[1], strlen($match[0]), $this->stocks()->renderBadge($ticker)];
					}
				}
			}
		}

		if (empty($replacements)) return $text;

		// Sort by offset and apply all replacements from end to start (to preserve offsets)
		usort($replacements, fn($a, $b) => $b[0] - $a[0]);
		foreach ($replacements as [$offset, $length, $html]) {
			$text = substr_replace($text, $html, $offset, $length);
		}

		return $text;
	}

	/**
	 * Check that a given offset+length range doesn't overlap with existing replacements
	 */
	protected function isOffsetFree($offset, $length, array $replacements) {
		$end = $offset + $length;
		foreach ($replacements as [$rOffset, $rLength]) {
			$rEnd = $rOffset + $rLength;
			if ($offset < $rEnd && $end > $rOffset) return false;
		}
		return true;
	}

	// =========================================================
	// Helpers
	// =========================================================

	protected function resolveNameToTicker($name) {
		$company = $this->stocks()->companies()->resolve($name, false);
		return $company ? $company['ticker'] : null;
	}

	protected function stocks() {
		return $this->wire('modules')->get('Stocks');
	}

	protected function parseExcluded($str) {
		if (empty(trim((string) $str))) return [];
		return array_map('strtoupper', array_filter(array_map('trim', explode(',', $str))));
	}

	protected function parseInlineOptions($optStr) {
		$options = [];
		if (empty(trim($optStr))) return $options;
		preg_match_all('/([a-z]+)="([^"]*)"/i', $optStr, $m, PREG_SET_ORDER);
		foreach ($m as $match) {
			$options[$match[1]] = $match[2];
		}
		return $options;
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

		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'parse_mode');
		$f->label       = 'Parse Mode';
		$f->description = 'Controls how aggressively the formatter detects stock mentions in text.';
		$f->addOption(self::MODE_EXPLICIT, '1 — Explicit tags only: [stock:AAPL] and [stock:Apple Inc]');
		$f->addOption(self::MODE_DOLLAR,   '2 — + Cashtag/hashtag: $AAPL, #TSLA');
		$f->addOption(self::MODE_AUTO,     '3 — + Auto company names from tracked list');
		$f->attr('value', $data['parse_mode']);
		$fields->add($f);

		$f = $modules->get('InputfieldMarkup');
		$f->label = 'Mode Examples';
		$f->value = '<table class="uk-table uk-table-small uk-table-divider" style="font-size:13px;">'
			. '<thead><tr><th>Input</th><th class="uk-text-center">Mode 1<br><span class="uk-text-muted" style="font-size:11px;font-weight:400;">Explicit</span></th>'
			. '<th class="uk-text-center">Mode 2<br><span class="uk-text-muted" style="font-size:11px;font-weight:400;">+ $/#</span></th>'
			. '<th class="uk-text-center">Mode 3<br><span class="uk-text-muted" style="font-size:11px;font-weight:400;">+ Auto</span></th></tr></thead>'
			. '<tbody>'
			. '<tr><td><code>[stock:AAPL]</code></td><td class="uk-text-center uk-text-success">&#10003;</td><td class="uk-text-center uk-text-success">&#10003;</td><td class="uk-text-center uk-text-success">&#10003;</td></tr>'
			. '<tr><td><code>[stock:Apple Inc]</code></td><td class="uk-text-center uk-text-success">&#10003;</td><td class="uk-text-center uk-text-success">&#10003;</td><td class="uk-text-center uk-text-success">&#10003;</td></tr>'
			. '<tr><td><code>$AAPL rallied</code></td><td class="uk-text-center uk-text-muted">&#10007;</td><td class="uk-text-center uk-text-success">&#10003;</td><td class="uk-text-center uk-text-success">&#10003;</td></tr>'
			. '<tr><td><code>#TSLA hit ATH</code></td><td class="uk-text-center uk-text-muted">&#10007;</td><td class="uk-text-center uk-text-success">&#10003;</td><td class="uk-text-center uk-text-success">&#10003;</td></tr>'
			. '<tr><td><code>Apple grew 5%</code></td><td class="uk-text-center uk-text-muted">&#10007;</td><td class="uk-text-center uk-text-muted">&#10007;</td><td class="uk-text-center uk-text-success">&#10003;</td></tr>'
			. '</tbody></table>'
			. '<div class="pw-notes">Mode 3 auto-detects company names and aliases from your tracked list.</div>';
		$fields->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'excluded_words');
		$f->label       = 'Excluded Words / Tickers';
		$f->description = 'Comma-separated. Prevents these from being auto-linked (modes 2 and 3).';
		$f->notes       = 'Common false positives: IT, AT, ON, AI, OR, GO, US, UK';
		$f->attr('value', $data['excluded_words']);
		$fields->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'skip_tags');
		$f->label       = 'Skip HTML Tags';
		$f->description = 'Comma-separated. Stock detection is disabled inside these tags.';
		$f->attr('value', $data['skip_tags']);
		$fields->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'min_word_len');
		$f->label       = 'Minimum Word Length for Auto-Detect';
		$f->description = 'Ignore single words shorter than this length. Reduces false positives.';
		$f->attr('value', (int) $data['min_word_len']);
		$f->min = 2;
		$f->max = 6;
		$fields->add($f);

		return $fields;
	}
}
