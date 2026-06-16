# Stocks — Live Stock Market Badges for ProcessWire

Embed interactive live stock price badges anywhere on your site. Badges display real-time price, change, and direction and open a full-detail popup on click — all with zero dependencies and support for four CSS frameworks.

---

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).  

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Data Providers](#data-providers)
- [Tracked Companies & Company Manager](#tracked-companies--company-manager)
- [TextFormatter — Automatic Badge Injection](#textformatter--automatic-badge-injection)
  - [Parse Mode 1 — Explicit Tags](#parse-mode-1--explicit-tags)
  - [Parse Mode 2 — Cashtag / Hashtag](#parse-mode-2--cashtag--hashtag)
  - [Parse Mode 3 — Auto Company Names](#parse-mode-3--auto-company-names)
- [PHP API](#php-api)
  - [renderBadge()](#renderbadge)
  - [renderBadgeAs()](#renderbadgeas)
  - [getStock()](#getstock)
  - [getStocks()](#getstocks)
  - [prefetchAll()](#prefetchall)
  - [clearCache()](#clearcache)
- [CSS Frameworks](#css-frameworks)
- [Untracked Ticker Modes](#untracked-ticker-modes)
- [Caching & Circuit Breaker](#caching--circuit-breaker)
- [Asset Injection](#asset-injection)
- [Adding a Custom Provider](#adding-a-custom-provider)
- [Module Configuration Reference](#module-configuration-reference)
- [Changelog](#changelog)
- [License](#license)
- [Author](#author)

---

## Features

- **Live price badges** — ticker symbol, current price, change amount and percentage, up/down direction arrow
- **Popup detail panel** — click any badge to open a floating panel with open/high/low, previous close, volume, 52-week range, P/E ratio, EPS, beta, dividend yield, market state, and data timestamp
- **Four CSS frameworks** — Vanilla CSS (built-in), Tailwind CSS, Bootstrap 5, UIkit 3; auto-detected or manually selected
- **Four data providers** — Yahoo Finance (free, no key), Finnhub (free 60 req/min), Alpha Vantage (free 25 req/day)
- **TextFormatter module** — three parse modes for automatic badge injection into any text field: explicit tags, cashtag/hashtag notation, full auto-detection by company name and aliases
- **Company Manager** — admin UI for tracking companies with names, aliases, enable/disable toggles, bulk CSV import
- **File-based cache** — configurable TTL, per-ticker cache clear from the admin panel
- **Circuit breaker** — automatically pauses API calls after repeated failures and returns stale cache instead
- **Stale data indicator** — `~` marker on badges served from stale cache
- **Accessible markup** — `role="button"`, `aria-expanded`, `tabindex="0"`, keyboard-navigable popups

---

## Requirements

- ProcessWire 3.0.0 or newer
- PHP 8.2 or newer
- `allow_url_fopen = On` or cURL enabled (for HTTP requests to data providers)

---

## Installation

**Via admin:**

1. Download the ZIP from [github.com/mxmsmnv/Stocks](https://github.com/mxmsmnv/Stocks)
2. In your PW admin go to **Modules → Install → Upload ZIP**
3. Install both **Stocks** and (optionally) **TextFormatter Stocks**

Then go to **Modules → Refresh** and install.

---

## Quick Start

### 1. Configure the module

Go to **Modules → Configure → Stocks**:

- Choose a **data provider** (Yahoo Finance works without any API key)
- Add a few **tracked companies** (ticker, name, optional aliases)
- Leave everything else at defaults

### 2. Render a badge in a template

```php
<?php
$stocks = $modules->get('Stocks');
echo $stocks->renderBadge('AAPL');
```

That's it — the badge renders with the current price, and the JS popup is injected automatically before `</head>`.

### 3. (Optional) Enable TextFormatter

Go to **Fields → your_body_field → Details → Text formatters** and add **TextFormatter Stocks**. Now you can write `[stock:AAPL]` or `$AAPL` directly in your content and badges appear automatically.

---

## Data Providers

| Provider | Cost | Rate Limit | Data Depth | API Key |
|---|---|---|---|---|
| **Yahoo Finance** | Free | Unofficial | Price, 52w, exchange | Not required |
| **Finnhub** | Free tier | 60 req/min | Real-time US, profile, metrics | [finnhub.io/register](https://finnhub.io/register) |
| **Alpha Vantage** | Free tier | 25 req/day | Basic — no 52w, no currency | [alphavantage.co](https://www.alphavantage.co/support/#api-key) |

**Yahoo Finance** is the default and requires no setup. It uses the unofficial v8 chart API — reliable for most use cases, but unofficial (may change without notice).

**Finnhub** is the recommended free provider if you need real company names, P/E ratio, and better reliability. The free tier covers 60 requests per minute, which is more than enough when caching is enabled.

---

## Tracked Companies & Company Manager

The Company Manager is the admin UI under **Modules → Configure → Stocks → Tracked Companies**.

Only companies in this list will show live badges. Untracked tickers are handled by the **Untracked Ticker Mode** setting.

Each company record has:

| Field | Description |
|---|---|
| **Ticker** | Stock symbol, e.g. `AAPL`, `TSLA`, `BRK.B` |
| **Company Name** | Human-readable name, e.g. `Apple Inc.` — used in the popup header and in auto-parse mode |
| **Aliases** | Comma-separated alternative names, e.g. `apple, iphone, ios` — matched in auto-parse mode |
| **Auto-parse** | When enabled, company name and aliases trigger badge auto-injection in text fields (mode 3) |
| **Active** | When disabled, the ticker is treated as untracked even though it is listed |

### Bulk Import

The collapsed **Bulk Import** section accepts one ticker per line in three formats:

```
AAPL
AAPL=Apple Inc.
AAPL=Apple Inc.=apple,iphone,ios
```

Check **Merge with existing** to add to the current list, or uncheck to replace all.

---

## TextFormatter — Automatic Badge Injection

Install **TextFormatter Stocks** and attach it to any text field. The formatter runs after HTML formatters (Markdown, Textile, etc.) and is HTML-safe — it never touches content inside `<pre>`, `<code>`, `<script>`, `<style>`, or `<a>` tags, and never modifies HTML attributes.

Configure the formatter under **Modules → Configure → TextFormatter Stocks**.

### Parse Mode 1 — Explicit Tags

Only `[stock:...]` tags are recognised. No automatic scanning. Best for content written by editors who want full control.

**By ticker:**
```
Investors are watching [stock:AAPL] closely this quarter.
```

**By company name (resolves to ticker):**
```
[stock:Apple Inc] reported record earnings.
```

**With inline options** (reserved for future renderer options):
```
[stock:AAPL theme="dark"]
```

**Unknown tag** (neither a valid ticker nor a resolvable name):
```
[stock:FooBar]
→ <span class="stocks-ticker stocks-unknown" ...>FooBar</span>
```

### Parse Mode 2 — Cashtag / Hashtag

Includes everything from Mode 1, plus `$TICKER` and `#TICKER` notation. Useful for finance-focused editorial content.

```
$AAPL hit a new all-time high today while #TSLA pulled back 3%.
Meanwhile [stock:GOOGL] trades sideways.
```

Tickers in the **Excluded Words** list (default: `IT, AT, ON, AI, OR, GO`) are never matched to avoid false positives with common words.

### Parse Mode 3 — Auto Company Names

Includes everything from Modes 1 and 2, plus automatic matching of company names and aliases from your tracked list. The formatter builds a single regex from all tracked names/aliases, sorts by length (longest first to avoid partial matches), and replaces each occurrence once.

```
Apple reported strong iPhone sales, pushing $AAPL higher.
Tesla meanwhile cut prices again — #TSLA bears are watching closely.
Alphabet's YouTube revenue surprised analysts.
```

If `Apple`, `iPhone`, `Tesla`, and `Alphabet` are configured as names/aliases for their respective tickers, all four will render as badges.

**Minimum word length** (default: 3) prevents very short aliases from triggering false positives.

The formatter never double-replaces a match — once a range in the text is claimed by a replacement, it is locked for all subsequent passes.

---

## PHP API

Get the module instance anywhere in your templates or hooks:

```php
$stocks = $modules->get('Stocks');
```

---

### renderBadge()

Render a badge for a tracked ticker using the configured theme.

```php
string renderBadge(string $ticker, array $options = [])
```

```php
// Basic badge
echo $stocks->renderBadge('AAPL');

// Untracked ticker — rendered per untracked_mode setting
echo $stocks->renderBadge('UNKNOWN');
```

If the ticker is not in the tracked list, the output depends on the **Untracked Ticker Mode** setting (plain text, hidden, or empty badge shell).

If the API call fails and there is no cache, an error badge is returned:

```html
<span class="stocks-ticker stocks-error" data-ticker="AAPL">AAPL</span>
```

---

### renderBadgeAs()

Render a badge with a theme override, ignoring the global `ui_theme` setting. Useful for sidebars or widgets that use a different framework from the rest of the page.

```php
string renderBadgeAs(string $ticker, string $theme, array $options = [])
```

Valid `$theme` values: `vanilla`, `tailwind`, `bootstrap`, `uikit`

```php
// Force Bootstrap badge even if the site uses Tailwind
echo $stocks->renderBadgeAs('TSLA', 'bootstrap');

// Force Vanilla CSS
echo $stocks->renderBadgeAs('MSFT', 'vanilla');
```

---

### getStock()

Fetch raw normalised stock data for one ticker. Returns an array on success, `false` on failure.

```php
array|false getStock(string $ticker, bool $forceRefresh = false)
```

```php
$data = $stocks->getStock('AAPL');

if ($data) {
    echo $data['ticker'];           // 'AAPL'
    echo $data['name'];             // 'Apple Inc.'
    echo $data['price'];            // 189.34
    echo $data['change'];           // 2.15
    echo $data['change_percent'];   // 1.15 (%)
    echo $data['price_high'];       // 190.12
    echo $data['price_low'];        // 186.50
    echo $data['price_prev_close']; // 187.19
    echo $data['volume'];           // 54230000
    echo $data['market_cap'];       // 2940000000000
    echo $data['currency'];         // 'USD'
    echo $data['exchange'];         // 'NMS'
    echo $data['market_state'];     // 'REGULAR' | 'PRE' | 'POST' | 'CLOSED'
    echo $data['fifty_two_week_high']; // 199.62
    echo $data['fifty_two_week_low'];  // 124.17
    echo $data['pe_ratio'];         // 29.4 (Finnhub only)
    echo $data['eps'];              // 6.44 (Finnhub only)
    echo $data['beta'];             // 1.21 (Finnhub only)
    echo $data['provider'];         // 'yahoo' | 'finnhub' | 'alphavantage'
    echo $data['fetched_at'];       // Unix timestamp
    echo $data['cached_at'];        // Unix timestamp of cache file mtime
    echo $data['cache_age'];        // Seconds since last fetch
    // $data['stale'] === true if served from expired cache
}
```

Force a fresh API call (bypass cache):

```php
$fresh = $stocks->getStock('AAPL', true);
```

---

### getStocks()

Fetch data for multiple tickers in one call. Returns an associative array keyed by uppercase ticker; failed/missing tickers are omitted.

```php
array getStocks(array $tickers)
```

```php
$data = $stocks->getStocks(['AAPL', 'TSLA', 'MSFT', 'GOOGL']);

foreach ($data as $ticker => $quote) {
    echo "$ticker: {$quote['price']} ({$quote['change_percent']}%)\n";
}

// AAPL: 189.34 (1.15%)
// TSLA: 242.80 (-2.31%)
// MSFT: 415.60 (0.42%)
// GOOGL: 174.20 (0.88%)
```

---

### prefetchAll()

Warm the cache for all tracked and enabled companies. Useful in a LazyCron hook or a manual warm-up script.

```php
array prefetchAll()  // returns [ 'AAPL' => true, 'TSLA' => false, ... ]
```

```php
// Warm cache on a schedule (requires LazyCron module)
$this->addHook('LazyCron::every30Minutes', function() {
    $stocks = $this->modules->get('Stocks');
    $result = $stocks->prefetchAll();

    $ok   = count(array_filter($result));
    $fail = count($result) - $ok;
    $this->log->save('stocks', "Prefetch: $ok OK, $fail failed");
});
```

---

### clearCache()

Delete one or all ticker cache files.

```php
int clearCache(string|null $ticker = null)  // returns number of files deleted
```

```php
// Clear one ticker
$stocks->clearCache('AAPL');

// Clear everything
$stocks->clearCache();
```

---

## CSS Frameworks

The module detects which CSS framework your site uses by inspecting `$config->styles` and `$config->scripts`. You can override this with the **UI Theme** setting in the module config.

| Theme | Rendering approach |
|---|---|
| `vanilla` | Built-in `stocks.css` with CSS variables. Injected automatically into `<head>`. |
| `tailwind` | Utility classes only. No extra CSS needed — works with Tailwind CDN or CLI. |
| `bootstrap` | Uses `.badge`, `.text-bg-success`, `.text-bg-danger`, `.font-monospace`. Bootstrap 5+. |
| `uikit` | Uses `.uk-label`, `.uk-label-success`, `.uk-label-danger`. UIkit 3. |

**Auto-detect** (default) inspects loaded assets and falls back to `vanilla` if no framework is found.

You can also force a specific theme per-badge with `renderBadgeAs()`:

```php
// Render a Bootstrap badge on a Tailwind site
echo $stocks->renderBadgeAs('NVDA', 'bootstrap');
```

---

## Untracked Ticker Modes

When a ticker is referenced (via TextFormatter or `renderBadge()`) but is not in the tracked company list — or its **Active** toggle is off — the module applies the configured fallback:

| Mode | Output | API call? |
|---|---|---|
| `plain` (default) | `<span class="stocks-untracked" data-ticker="XYZ">XYZ</span>` | No |
| `hide` | _(empty string)_ | No |
| `badge_nodata` | Badge shell with `N/A` label | No |

Configure under **Modules → Configure → Stocks → Untracked Tickers**.

---

## Caching & Circuit Breaker

### File cache

Stock data is cached as JSON files in `site/assets/cache/stocks/` (one file per ticker, e.g. `AAPL.json`).

- **Default TTL:** 300 seconds (5 minutes)
- Cache files survive server restarts and are independent of ProcessWire's WireCache
- You can view all cached files, their size and age, and clear individual files from **Modules → Configure → Stocks → Cache**

### Circuit breaker

If the API fails 2 times in a row, the circuit breaker opens for 60 seconds. During this window:

- No new API calls are made
- The module returns stale cache if available (badge displays a `~` marker)
- After 60 seconds, the circuit resets and API calls resume

This protects your site from latency spikes caused by a downstream provider outage.

---

## Asset Injection

The module hooks `Page::render` to inject assets automatically:

**Frontend pages** (when `stocks-ticker` or `stocks-untracked` is found in the rendered HTML):

```html
<!-- Injected before </head> -->
<link rel="stylesheet" href="/site/modules/Stocks/css/stocks.css">
<script>window.StocksConfig = {"theme":"vanilla","popupStyle":"vanilla","moduleUrl":"..."};</script>
<script src="/site/modules/Stocks/js/stocks.js" defer></script>
```

CSS is only injected when the active theme is `vanilla`. The `StocksConfig` global is always injected (needed for popup rendering). To include assets manually, uncheck **Inject built-in CSS** and/or **Inject popup JavaScript** in the module config.

**Admin pages** — `stocks-admin.js` is injected only on the Stocks and TextFormatter Stocks config pages, powering the Company Manager UI.

---

## Adding a Custom Provider

To integrate a different data source:

1. Create `/site/modules/Stocks/providers/StocksProviderMySource.php`
2. Extend `StocksProviderBase` and implement `fetchQuote()` and `getProviderName()`
3. Return the normalised array (same keys as the Yahoo example above)
4. Add `'mysource' => 'StocksProviderMySource'` to the `PROVIDERS` constant in `StocksAPI.php`
5. Add the option to the `api_provider` select in `Stocks.module.php`

**Minimal provider skeleton:**

```php
<?php
class StocksProviderMySource extends StocksProviderBase {

    public function getProviderName() { return 'mysource'; }
    public function requiresKey()     { return true; }

    public function fetchQuote($ticker) {
        if (empty($this->apiKey)) return false;

        $url  = 'https://api.mysource.com/quote/' . urlencode($ticker)
              . '?apikey=' . urlencode($this->apiKey);
        $raw  = $this->httpGet($url);   // cURL/file_get_contents with timeout
        $json = $this->decodeJson($raw);
        if (!$json) return false;

        return [
            'ticker'              => strtoupper($ticker),
            'name'                => $json['companyName'] ?? $ticker,
            'price'               => (float) $json['latestPrice'],
            'price_open'          => (float) ($json['open'] ?? 0),
            'price_high'          => (float) ($json['high'] ?? 0),
            'price_low'           => (float) ($json['low'] ?? 0),
            'price_prev_close'    => (float) ($json['previousClose'] ?? 0),
            'change'              => (float) ($json['change'] ?? 0),
            'change_percent'      => (float) ($json['changePercent'] ?? 0) * 100,
            'volume'              => (int)   ($json['volume'] ?? 0),
            'avg_volume'          => (int)   ($json['avg30Volume'] ?? 0),
            'market_cap'          => (float) ($json['marketCap'] ?? 0),
            'currency'            => 'USD',
            'exchange'            => $json['primaryExchange'] ?? '',
            'market_state'        => 'REGULAR',
            'fifty_two_week_high' => (float) ($json['week52High'] ?? 0),
            'fifty_two_week_low'  => (float) ($json['week52Low'] ?? 0),
            'pe_ratio'            => isset($json['peRatio']) ? (float) $json['peRatio'] : null,
            'dividend_yield'      => null,
            'eps'                 => null,
            'beta'                => null,
            'fetched_at'          => time(),
            'provider'            => $this->getProviderName(),
        ];
    }
}
```

`StocksProviderBase` provides `$this->apiKey`, `$this->timeout`, `httpGet($url)`, and `decodeJson($raw)`.

---

## Module Configuration Reference

### Stocks module

| Setting | Default | Description |
|---|---|---|
| `api_provider` | `yahoo` | Data provider: `yahoo`, `finnhub`, `alphavantage` |
| `api_key` | _(empty)_ | API key for providers that require one |
| `cache_time` | `300` | Cache TTL in seconds (minimum 60) |
| `request_timeout` | `10` | HTTP timeout for API calls (3–30 s) |
| `ui_theme` | `auto` | CSS framework: `auto`, `vanilla`, `tailwind`, `bootstrap`, `uikit` |
| `inject_css` | `1` | Inject `stocks.css` automatically (vanilla theme only) |
| `inject_js` | `1` | Inject `stocks.js` and `StocksConfig` automatically |
| `companies_json` | `[]` | JSON blob storing tracked company records |
| `untracked_mode` | `plain` | How untracked tickers render: `plain`, `hide`, `badge_nodata` |
| `cache_dir` | `stocks` | Subfolder name inside `site/assets/cache/` |

### TextFormatter Stocks module

| Setting | Default | Description |
|---|---|---|
| `parse_mode` | `dollar` | `explicit`, `dollar`, or `auto` |
| `excluded_words` | `IT,AT,ON,AI,OR,GO` | Comma-separated tickers/words never auto-linked |
| `skip_tags` | `pre,code,script,style,a` | HTML tags whose content is never processed |
| `min_word_len` | `3` | Minimum alias length for auto-detection (mode 3) |

---

## Changelog

See [CHANGELOG.md](https://github.com/mxmsmnv/Stocks/blob/main/CHANGELOG.md)

---

## License

MIT — see [LICENSE](https://github.com/mxmsmnv/Stocks/blob/main/LICENSE)

---

## Author

**Maxim Semenov** · [smnv.org](https://smnv.org) · [maxim@smnv.org](mailto:maxim@smnv.org)

GitHub: [github.com/mxmsmnv/Stocks](https://github.com/mxmsmnv/Stocks)
