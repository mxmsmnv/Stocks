# Changelog

All notable changes to Stocks are documented here.

---

## 1.0.0 — Initial release

- Yahoo Finance, Finnhub, Alpha Vantage providers (IEX Cloud not included — retired August 31, 2024)
- Vanilla CSS, Tailwind, Bootstrap 5, UIkit 3 themes with auto-detection
- TextFormatter with three parse modes: explicit tags, cashtag/hashtag, auto company names
- Company Manager with add/edit/remove, bulk import with merge and replace modes
- Popup templates extracted to `assets/popup/{theme}.js` — one file per framework for easy customisation
- File-based cache with configurable TTL and per-ticker clear from admin
- Circuit breaker with stale cache fallback and `~` indicator on badges
- Automatic asset injection into `<head>` (CSS and JS only when needed)
- Finnhub provider fetches `/stock/metric` as a third request to correctly populate `fifty_two_week_high`, `fifty_two_week_low`, `pe_ratio`, `eps`, and `beta`
- TextFormatter overlap guard — no double-replacement across all three parse steps
- Auto-detect skips ticker symbols in name/alias lists to prevent duplicate badges
- Company table inline editing — pencil → save flow without page reload
- Admin JS injected only on Stocks and TextFormatter Stocks config pages
