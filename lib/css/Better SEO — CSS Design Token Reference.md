# Better SEO — CSS Design Token Reference

> **Master reference for all `--better-seo-*` CSS custom properties.**
> Generated from audit of all 10 source files in `lib/css/`.

---

## 📋 Table of Contents

1. [Current State — Where Variables Are Defined](#current-state)
2. [Complete Token Inventory](#token-inventory)
3. [Status / Semantic Colors](#status-colors)
4. [WordPress Admin Colors (Not Tokenized)](#wp-colors)
5. [File Usage Matrix](#usage-matrix)
6. [Recommended Consolidated `:root` Block](#consolidated-root)
7. [Migration Notes](#migration-notes)

---

## 1. Current State — Where Variables Are Defined {#current-state}

| Status | Description |
|---|---|
| ✅ **Declared in `:root`** | Only in `le.css` — 11 variables |
| ⚠️ **Used as hex with comment** | `tt.css`, `settings.css`, `media.css`, `ui.css`, `better-seo.css`, `better-seo-c.css` |
| ❌ **Not yet tokenized** | Status colors (bad/okay/good/unknown), WP admin colors, low-contrast variants |

**Current problem:** The `:root` block in `le.css` defines 11 brand palette variables, but most files use the hex values directly with inline comments (`/* --better-seo-navy */`) rather than `var(--better-seo-navy)`. This means color changes require updating every file individually.

---

## 2. Complete Token Inventory {#token-inventory}

### 2a. Brand Palette (Currently Declared in `le.css` `:root`)

| Variable | Hex | Semantic Role | Declared In |
|---|---|---|---|
| `--better-seo-navy` | `#1a1a2e` | Primary dark — headers, tooltip bg, tab text | `le.css` `:root` |
| `--better-seo-deep-blue` | `#16213e` | Hero / CTA backgrounds | `le.css` `:root` |
| `--better-seo-mid-blue` | `#0f3460` | Accents, links, unknown state | `le.css` `:root` |
| `--better-seo-gold` | `#c9a84c` | Primary accent — warnings, loading, focus glow | `le.css` `:root` |
| `--better-seo-gold-lt` | `#e8c97a` | Light gold — low-contrast okay state | `le.css` `:root` |
| `--better-seo-cream` | `#faf8f4` | Alt section bg — tooltip text | `le.css` `:root` |
| `--better-seo-white` | `#ffffff` | Pure white | `le.css` `:root` |
| `--better-seo-grey-lt` | `#f4f4f4` | Light grey backgrounds | `le.css` `:root` |
| `--better-seo-grey-mid` | `#888` | Muted grey — unknown/disabled states | `le.css` `:root` |
| `--better-seo-text-dark` | `#1a1a2e` | Dark text (same as navy) | `le.css` `:root` |
| `--better-seo-text-body` | `#3d3d3d` | Body paragraph text | `le.css` `:root` |

### 2b. Status Colors (Used as Hex — Not Yet Tokenized)

| Proposed Variable | Hex | Semantic Role | Currently Used In |
|---|---|---|---|
| `--better-seo-status-bad` | `#c0392b` | Bad SEO / Error / Failure | `better-seo.css`, `better-seo-c.css`, `media.css`, `ui.css` |
| `--better-seo-status-okay` | `#c9a84c` | Okay / Warning / Loading | `better-seo.css`, `better-seo-c.css`, `media.css`, `ui.css` (= `--better-seo-gold`) |
| `--better-seo-status-good` | `#27ae60` | Good SEO / Success | `better-seo.css`, `better-seo-c.css`, `ui.css` |
| `--better-seo-status-unknown` | `#888` | Unknown / Undefined | `better-seo.css`, `better-seo-c.css` (= `--better-seo-grey-mid`) |

### 2c. Low-Contrast / Accessibility Variants (Used as Hex — Not Yet Tokenized)

| Proposed Variable | Hex | Semantic Role | Currently Used In |
|---|---|---|---|
| `--better-seo-status-bad-lt` | `#e88080` | Bad — reduced contrast mode | `better-seo.css` |
| `--better-seo-status-okay-lt` | `#e8c97a` | Okay — reduced contrast mode | `better-seo.css` (= `--better-seo-gold-lt`) |
| `--better-seo-status-good-lt` | `#6fcf97` | Good — reduced contrast mode | `better-seo.css` |
| `--better-seo-status-unknown-lt` | `#5b7fa6` | Unknown — reduced contrast mode | `better-seo.css` |
| `--better-seo-status-undefined-lt` | `#aaa` | Undefined — reduced contrast mode | `better-seo.css` |

### 2d. Error Red (Used as Hex — Not Yet Tokenized)

| Proposed Variable | Hex | Semantic Role | Currently Used In |
|---|---|---|---|
| `--better-seo-error` | `#c0392b` | Error state (same as status-bad) | `media.css` |

---

## 3. Status / Semantic Colors {#status-colors}

These colors carry semantic meaning across the entire plugin UI:

| State | Standard | Low Contrast | Usage |
|---|---|---|---|
| **Bad / Error** | `#c0392b` 🔴 | `#e88080` | SEO bar, counter badge, pixel bar, AJAX error, notice error icon |
| **Okay / Warning** | `#c9a84c` 🟡 | `#e8c97a` | SEO bar, counter badge, pixel bar, AJAX loading, notice warning icon, media warning |
| **Good / Success** | `#27ae60` 🟢 | `#6fcf97` | SEO bar, counter badge, pixel bar, AJAX success, notice updated icon |
| **Unknown** | `#888` ⚫ | `#5b7fa6` | SEO bar, counter badge, AJAX unknown |
| **Undefined** | `#888` ⚫ | `#aaa` | SEO bar undefined state |

> **Note:** `--better-seo-status-okay` is intentionally the same hex as `--better-seo-gold` (`#c9a84c`). The gold brand color doubles as the warning/okay semantic color. This is by design — the two tokens should remain separate aliases so they can diverge if needed.

---

## 4. WordPress Admin Colors (Not Tokenized — Intentional) {#wp-colors}

These colors are kept as hardcoded hex values because they must match WordPress admin UI exactly. They are **not** Better SEO design tokens.

| Hex | WP Role | Used For |
|---|---|---|
| `#2271b1` | WP Admin Blue | Focus rings, active tab underline, checked input state |
| `#d63638` | WP Admin Red | Warning-selected input state |
| `#646970` | WP Admin Muted | Description text, dismiss button, placeholder text |
| `#1d2327` | WP Admin Dark | Dismiss button hover |
| `#e2e4e7` | WP Admin Separator | Flex setting borders, pixel counter border |
| `#dadada` | WP Admin Border | Nav tab wrapper border, no-JS tab shadow |
| `#f5f5f5` | WP Admin Light BG | Nav tab wrapper background |
| `#f9f9f9` | WP Admin Label BG | Flex setting label column background |
| `#ccc` | WP Admin Border | Settings nav tab border, separator label border |

---

## 5. File Usage Matrix {#usage-matrix}

| Variable / Color | `le` | `media` | `post` | `pt` | `better-seo` | `better-seo-c` | `term` | `tt` | `ui` | `settings` |
|---|---|---|---|---|---|---|---|---|---|---|
| `--better-seo-navy` (#1a1a2e) | ✅ def | — | — | — | — | — | — | ✅ hex | ✅ hex | ✅ hex |
| `--better-seo-deep-blue` (#16213e) | ✅ def | — | — | — | — | — | — | — | — | — |
| `--better-seo-mid-blue` (#0f3460) | ✅ def | — | — | — | ✅ hex | — | — | — | ✅ hex | — |
| `--better-seo-gold` (#c9a84c) | ✅ def | ✅ hex | — | — | ✅ hex | ✅ hex | — | — | ✅ hex | — |
| `--better-seo-gold-lt` (#e8c97a) | ✅ def | — | — | — | ✅ hex | — | — | — | — | — |
| `--better-seo-cream` (#faf8f4) | ✅ def | — | — | — | — | — | — | ✅ hex | — | — |
| `--better-seo-white` (#ffffff) | ✅ def | — | — | — | — | — | — | — | — | — |
| `--better-seo-grey-lt` (#f4f4f4) | ✅ def | — | — | — | — | — | — | — | — | — |
| `--better-seo-grey-mid` (#888) | ✅ def | — | — | — | ✅ hex | ✅ hex | — | — | — | — |
| `--better-seo-text-dark` (#1a1a2e) | ✅ def | — | — | — | — | — | — | — | — | — |
| `--better-seo-text-body` (#3d3d3d) | ✅ def | — | — | — | — | — | — | — | — | — |
| Status Bad (#c0392b) | — | ✅ hex | — | — | ✅ hex | ✅ hex | — | — | ✅ hex | — |
| Status Good (#27ae60) | — | — | — | — | ✅ hex | ✅ hex | — | — | ✅ hex | — |

**Legend:** `✅ def` = declared here | `✅ hex` = used as hex with comment | `—` = not used

---

## 6. Recommended Consolidated `:root` Block {#consolidated-root}

This block should replace the current `:root` in `le.css` and serve as the **single source of truth** for the entire plugin. All other files should then use `var(--better-seo-*)` instead of hardcoded hex values.

```css
/**
 * Better SEO — Design Token System
 * Single source of truth for all CSS custom properties.
 * Include this :root block once, in the first-loaded CSS file (better-seo.css).
 */
:root {

	/* ── BRAND PALETTE ──────────────────────────────────────── */
	--better-seo-navy:      #1a1a2e;  /* Primary dark — headers, tooltip bg */
	--better-seo-deep-blue: #16213e;  /* Hero / CTA backgrounds */
	--better-seo-mid-blue:  #0f3460;  /* Accents, links, unknown state */
	--better-seo-gold:      #c9a84c;  /* Primary accent — warnings, loading */
	--better-seo-gold-lt:   #e8c97a;  /* Light gold — low-contrast okay */
	--better-seo-cream:     #faf8f4;  /* Alt section bg — tooltip text */
	--better-seo-white:     #ffffff;  /* Pure white */
	--better-seo-grey-lt:   #f4f4f4;  /* Light grey backgrounds */
	--better-seo-grey-mid:  #888888;  /* Muted grey — unknown/disabled */
	--better-seo-text-dark: #1a1a2e;  /* Dark text (alias of navy) */
	--better-seo-text-body: #3d3d3d;  /* Body paragraph text */

	/* ── STATUS COLORS (Standard Contrast) ─────────────────── */
	--better-seo-status-bad:     #c0392b;  /* 🔴 Bad SEO / Error / Failure */
	--better-seo-status-okay:    #c9a84c;  /* 🟡 Okay / Warning (= gold) */
	--better-seo-status-good:    #27ae60;  /* 🟢 Good SEO / Success */
	--better-seo-status-unknown: #888888;  /* ⚫ Unknown / Undefined (= grey-mid) */

	/* ── STATUS COLORS (Low Contrast / Accessibility) ───────── */
	--better-seo-status-bad-lt:      #e88080;  /* Light red */
	--better-seo-status-okay-lt:     #e8c97a;  /* Light gold (= gold-lt) */
	--better-seo-status-good-lt:     #6fcf97;  /* Light green */
	--better-seo-status-unknown-lt:  #5b7fa6;  /* Muted blue */
	--better-seo-status-undef-lt:    #aaaaaa;  /* Light grey */

	/* ── SEMANTIC ALIASES ───────────────────────────────────── */
	/* These aliases make intent explicit at point of use */
	--better-seo-color-error:   var(--better-seo-status-bad);
	--better-seo-color-warning: var(--better-seo-status-okay);
	--better-seo-color-success: var(--better-seo-status-good);
	--better-seo-color-info:    var(--better-seo-mid-blue);
	--better-seo-color-muted:   var(--better-seo-grey-mid);

	/* ── TOOLTIP ────────────────────────────────────────────── */
	--better-seo-tooltip-bg:    var(--better-seo-navy);
	--better-seo-tooltip-color: var(--better-seo-cream);

	/* ── FOCUS RING (Better SEO branded) ───────────────────── */
	--better-seo-focus-ring:  var(--better-seo-navy);
	--better-seo-focus-glow:  var(--better-seo-gold);
}
```

---

## 7. Migration Notes {#migration-notes}

### Phase 1 — Move `:root` to `better-seo.css` (first-loaded file)
Move the `:root` block from `le.css` to `better-seo.css` so it loads first regardless of which CSS file is enqueued.

### Phase 2 — Add missing tokens to `:root`
Add the status colors, low-contrast variants, and semantic aliases from the recommended block above.

### Phase 3 — Replace hex values with `var()` calls

| File | Replacements Needed |
|---|---|
| `tt.css` | `#1a1a2e` → `var(--better-seo-tooltip-bg)`, `#faf8f4` → `var(--better-seo-tooltip-color)` |
| `settings.css` | `#1a1a2e` → `var(--better-seo-navy)` (×2) |
| `media.css` | `#c9a84c` → `var(--better-seo-color-warning)`, `#c0392b` → `var(--better-seo-color-error)` |
| `ui.css` | `#c0392b` → `var(--better-seo-color-error)`, `#c9a84c` → `var(--better-seo-color-warning)`, `#27ae60` → `var(--better-seo-color-success)`, `#0f3460` → `var(--better-seo-color-info)`, `#1a1a2e` → `var(--better-seo-focus-ring)`, `#c9a84c` → `var(--better-seo-focus-glow)` |
| `better-seo.css` | All status hex values → `var(--better-seo-status-*)` |
| `better-seo-c.css` | All status hex values → `var(--better-seo-status-*)` |

### Phase 4 — Remove `:root` from `le.css`
Once `better-seo.css` is the canonical source, remove the `:root` block from `le.css` to eliminate duplication.

---

## Summary Statistics

| Metric | Count |
|---|---|
| CSS files audited | 10 |
| Variables currently declared in `:root` | 11 |
| Additional variables recommended | 16 |
| **Total recommended token count** | **27** |
| Files using hex with comment (needs migration) | 6 |
| WP admin colors (intentionally not tokenized) | 9 |