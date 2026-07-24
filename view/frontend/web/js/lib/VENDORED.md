# Vendored: card.js (and card.css)

These files are an **intentional copy** of the `jessepollak/card` library at version 2.5.4.
They are NOT a build artifact — do not auto-update them and do not run `npm install` to refresh.

## Source

- Project: https://github.com/jessepollak/card
- Version: **2.5.4** (released 2021-08-14, last release at time of vendoring)
- License: MIT — preserved in the file headers
- Files retrieved from: `https://unpkg.com/card@2.5.4/dist/card.js` and `card.css`

## SHA-256 (verify on intake)

| File | SHA-256 |
|------|---------|
| `js/lib/card.js`  | `18c4b9b4c27233b541a47300a4ee98239e1f8dec4bbcd9fabb6bdad12ca82025` |
| `css/lib/card.css` | `3c3ac2ee3f7f0181ae2b944d8d9d2c9dbc479688fba91302abb2cfdc79ce215e` |

Re-verify with `shasum -a 256 path/to/file`. If a hash drifts, the file was modified — investigate.

## Why vendored, not a dependency

1. **Supply chain hardening** — no CDN runtime fetch, no package-lock pin to maintain, no
   risk of an upstream npm hijack reaching this PCI-scoped page.
2. **Library is stale** — last release ~3.5 years ago. Treating it as a dependency would imply
   we expect updates; we don't, and we've audited the source on intake.
3. **Magento-friendly** — the file is loaded via standard Magento RequireJS, no build step.

## Audit summary (performed at intake)

The 58.6 KB minified bundle and 27 KB CSS were scanned against:

- Network primitives: `fetch`, `XMLHttpRequest`, `sendBeacon`, `WebSocket`, `EventSource`,
  `xhr.open`, jQuery `.ajax/.post/.get` → **0 matches**
- Code execution: `eval(`, `setInterval('…')`, `setTimeout('…')` → **0 matches**
- Storage: `document.cookie`, `localStorage`, `sessionStorage`, `indexedDB` → **0 matches**
- External URLs in JS or CSS: **none** at runtime (one MIT/jQuery license comment string only)
- `new Function(...)` → 1 occurrence, the standard webpack runtime helper that wraps
  `return this()` for global-scope detection. Reviewed; benign.

The library reads form input via DOM only, paints a CSS-only animated card visual, and
performs no I/O. PCI scope is unchanged from the existing module baseline.

## How to upgrade

1. Download the new version manually.
2. Re-run the audit script above.
3. Replace `card.js` and `card.css` and update the hashes in this file.
4. Bump the comment in `requirejs-config.js`.
5. Smoke-test the storefront card form.

Do not auto-update.
