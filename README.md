# menj.click — Twenty Twenty-Five Child Theme

Version 2.1.4

A custom child theme for **menj.click**, designed as a personal link hub, branded short-link directory and QR destination site, with a Notes blog and a curated website directory.

**Everything is native to the theme.** Short links, QR codes, click stats and settings all live here — no companion plugins. Switch themes and it all goes dormant (nothing is deleted); switch back and it returns.

## Design concept

**menj.click = everything worth clicking.**

It acts as the front door to:
- menj.me
- menj.bio
- menj.blog
- menj.buzz
- books and publications
- projects
- contact links
- QR destinations
- a curated directory of sites, tools and businesses

## Installation

1. Install Twenty Twenty-Five (the parent theme). It doesn't need to be active.
2. Upload and activate **menj.click**. The zip's folder is `click`, matching the live site, so **Appearance → Themes → Add New → Upload** offers *Replace current with uploaded* for updates, no FTP needed.
3. Go to **menj.click → Diagnostics** and click each fix it offers:
   - **Create pages** — Home, Notes, My Sites, Shorts, QR Codes; sets Home as the front page and Notes as the posts page.
   - **Use /notes/%postname%/** — posts move under /notes/, so they never compete with short links.
4. **Coming from 1.x?** On **Short Links → Import and export**, click **Import from URL Shortify** (slugs, destinations, click totals, redirect types and parameter forwarding come across; existing slugs are skipped). Then deactivate URL Shortify and QR Code Composer.
5. **Starting fresh?** Click **Add starter links**: seo, blog, bio, buzz, book, apo, projects, contact and work arrive as drafts. Fill in any missing destinations and switch each to **Active**.
6. **Directory:** add categories under **Directory → Categories**, then set prices, PayPal and submission rules under **menj.click → Directory**. Test payments with **Sandbox** on before going live.

## Settings (menj.click in the dashboard)

| Tab | What it does |
| --- | --- |
| **Link Hub** | Hero text and background (code-drawn ridgeline, photo, or plain), the link panel beside the hero, and the short-link examples on the home page. |
| **Short Links** | Add, edit, search and delete links; per-link stats; CSV import/export; URL Shortify import; default redirect, click retention, bot counting. |
| **QR Codes** | The four codes on the home page, colours, error correction, quiet zone; SVG/PNG downloads; a one-off code for any URL. |
| **Directory** | Sub-tabs: General, Listing types, Payments, Link back & checks, Submissions & spam, Emails, Import & export. Listings themselves are under **Directory** in the dashboard menu. |
| **Notes** | Section title and intro, typewriter masthead, latest-notes strip on the home page; setup checklist. |
| **Footer** | Tagline (also your site tagline), website, email and X icons. |
| **Appearance** | Colour schemes (Dusk, Daylight, Midnight, Ink) and the typeface roles. |
| **Diagnostics** | Health checks with one-click fixes, settings backup/restore, and **Delete all theme data**. |

## Short links

- **Address:** `menj.click/{slug}` — 1–64 lowercase letters, digits, `-` or `_`. Matching is case-insensitive.
- **Redirects:** 307 by default (temporary; safe to change later). 301 is available but browsers cache it — avoid it for anything printed as a QR code.
- **Protected slugs:** site paths (wp-admin, feed, notes, links, qr, …) are refused, and a new page can't take a short link's slug (WordPress adds `-2`).
- **Stats:** every click logs date, source (web or QR scan), referring site and device. Visitors are counted with a daily salted hash — no IP addresses are stored. Bots and chat-app link previews aren't counted unless you turn that on.
- **Drafts** don't redirect and stay hidden from visitors.
- **Query forwarding** (per link) appends `?utm_…` and similar parameters to the destination.

## QR codes

- Each code encodes the **short link** (`menj.click/{slug}?src=qr`), never the destination — change where it goes without reprinting, and scans show separately in the stats.
- Images: `menj.click/qr/{slug}.svg` or `.png` (add `?download=1` to download). Only active links have codes.
- Codes are drawn by the theme's own encoder (all 40 versions, all four error-correction levels); PNG needs the PHP GD extension, SVG needs nothing.
- The 1.x shortcodes still work: `[menj_qr slug="book"]`, `[menj_qr url="https://example.com/"]`, `[menj_short_url slug="book"]`.

## Notes

The blog, at `/notes/`. Articles are set in **Sabon Next LT**, with **EB Garamond** filling in characters Sabon lacks (transliteration marks, polytonic Greek). The masthead can use **Special Elite**.

### Languages and scripts

Select text and use **Language** in the formatting toolbar, or set a whole block's **Language** in the block settings (paragraph, heading, quote, pullquote, verse, list, preformatted, table). This sets `lang` and reading direction, and picks the face:

| Language | Typeface |
| --- | --- |
| Hebrew | SBL Hebrew |
| Greek | SBL Greek |
| Transliteration | SBL BibLit |
| Syriac | Noto Sans Syriac (Unicode Estrangela) |
| Christian Palestinian Aramaic | Evangelion CPA |
| Arabic | Noto Naskh Arabic (headings: Dubidam Arabic) |
| Qur’an | KFGQPC HAFS Uthmanic Script |
| Arabic calligraphy | Arslan Wessam A / B |
| Cuneiform | Noto Sans Cuneiform |
| Estrangelo (legacy) | For text typed in the old pre-Unicode encoding (Latin keys) |

Untagged Hebrew, Arabic, Syriac and cuneiform still find the right face automatically. Every script font carries a `unicode-range`, so a page downloads a font only when it contains that script. Licensed fonts ship exactly as supplied; OFL fonts are subset to WOFF2 with their licences alongside.

## Directory

A phpLD-style link directory at `/directory/`, built natively on WordPress (listings are a post type, so they get the editor, revisions, feeds and sitemaps).

- **Browsing:** front page with search, Sponsored strip, category grid (counts + subcategory previews), Featured and recent listings, A–Z. Category pages pin Sponsored and up to *n* Featured listings, then list the rest with a sort menu (newest, A–Z, most visited, top rated). Tag pages, search (`?dq=`) and A–Z pages (`/directory/a-z/m/`).
- **Listings** (`/directory/site/{slug}/`): letter tile or featured image, Visit site button, description, facts (listed, visits, business details, tags, short link), QR code, reviews with star ratings.
- **Visits:** outbound links go through `/directory/go/{id}/` — counted once per visitor per day, bots excluded — then redirect.
- **Submissions** (`/directory/submit/`): choose Free, Featured or Sponsored; email confirmation; review queue. Spam checks: honeypot, time check, a small sum, a daily per-visitor limit, ban lists (domains, emails, IPs, words), one listing per domain, and a live check that the site responds.
- **Owners** get a private manage link by email (no accounts): see status and visits, pay or renew, upgrade, edit (changes to live listings wait for your review), or remove the listing.
- **Listing types:** Free (optional review, `ugc nofollow` by default), Featured and Sponsored (price, one-off/monthly/yearly, `sponsored` links). When a paid period ends the listing moves to Free or is hidden until renewed; a reminder goes out first.
- **Payments:** PayPal Standard checkout confirmed by IPN (checked for receiver, amount, currency and duplicates), or invoice mode — you record the payment from the listing. Every payment is logged, with PayPal's raw messages viewable under each payment.
- **Auto-renewal:** monthly and yearly listings can be paid by PayPal subscription. Each renewal is recorded as its own payment and extends the listing; if the owner cancels in PayPal, the listing runs to the end of the paid period. Renewal reminders are skipped while a subscription is active, and expiry waits three days for PayPal's renewal to arrive.
- **Paying ahead:** owners paying once can buy up to *n* periods in one payment, with an optional multi-period discount.
- **Link back (reciprocal):** off, optional (reviewed first, rewarded with a followed link) or required for Free listings. Checked on submission and rechecked on a schedule; if it disappears the owner is emailed and the listing is hidden after a grace period.
- **Link checker:** hourly batches record each site's HTTP status; broken sites are flagged (or hidden, if you choose).
- **Moderation:** Approve / Reject (with a reason for the owner) / Check now from the listings screen, bulk approve, pending queue with verified link-backs first, and a details box for URL, type, link rel, paid-until, owner and payments. **Create short link** mints a menj.click short link for any listing.
- **Emails:** eleven editable templates with placeholders.
- **Import & export:** CSV in both directions; categories as `Parent > Child`.

**Setup:** open **Directory → Categories** and add your categories, then set prices and PayPal under **menj.click → Directory**. Nothing to flush — the theme updates its permalinks itself.

The feature set was informed by a licensed copy of phpLD 4.2.0, used only as a reference. Its licence doesn't allow reuse of its code, so none is included: this is an independent implementation. Dated parts (PageRank/Alexa, user accounts, widgets, newsletters, image captchas) were left out.

## Colour schemes

Four schemes recolour everything, including the hero artwork: **Dusk** (default), **Daylight**, **Midnight** and **Ink** (monochrome, underlined links). Apply one under **Appearance**, then fine-tune in **Appearance → Editor → Styles**. Only this theme's schemes are offered; Twenty Twenty-Five's own variations are hidden because they'd swap in its fonts.

## Blocks

Eleven theme blocks, all editable in the Site Editor: Wordmark, Link hub hero, Link panel, Feature icon, Short links, QR codes, Latest notes, Notes masthead, Social links, Directory, Directory listing. Their content comes from the settings tabs.

## Files

```
menj-click/
├── assets/
│   ├── css/      front, languages, directory, editor, editor-notes, admin
│   ├── js/       admin, front, blocks-editor, language-format, directory, directory-admin
│   └── fonts/    one folder per family, licences included
├── inc/          helpers, class-qr, short-links, qr, lifecycle, blocks, notes, typography, admin, diagnostics,
│                 directory, directory-submit, directory-payments, directory-checks, directory-admin
├── parts/        header, footer
├── styles/       daylight, midnight, ink
├── templates/    front-page, home, index, single, page, page-links, page-short-urls, page-qr, archive, search, 404,
│                 archive-menj_listing, single-menj_listing, taxonomy-menj_dir_category, taxonomy-menj_dir_tag
├── functions.php
├── style.css     (header only)
└── theme.json    (Dusk scheme, fonts, sizes)
```

## Deployment notes

- **Page caching:** exclude short-link paths and `/directory/go/`, `/directory/submit/`, `/directory/manage/`, `/directory/pay/` and `/directory/confirm/` from full-page caches. Redirects already send `no-cache` headers; most caches respect them.
- **Email:** directory emails use `wp_mail()`. Use an SMTP plugin or your host's mailer so confirmations aren't lost.
- **Cron:** link checks, renewal reminders and expiry rely on WP-Cron; on low-traffic sites trigger `wp-cron.php` from a real cron job.
- **PayPal:** a PayPal Business account is needed (subscriptions included). PayPal must be able to reach `/wp-json/menj-click/v1/paypal-ipn/`, so don't block the REST API for anonymous visitors in security plugins or firewalls. Diagnostics shows whether PayPal is live or in sandbox.
- **Compression:** make sure `.ttf` and `.otf` are served with gzip or Brotli. On Apache:
  ```apache
  <IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE font/ttf font/otf application/x-font-ttf application/vnd.ms-opentype
  </IfModule>
  ```
- **Requirements:** WordPress 6.7+, PHP 7.4+, Twenty Twenty-Five installed. GD for PNG QR downloads.
- **Folder name:** you can name the theme folder anything. WordPress won't offer WordPress.org themes with the same name as updates.
- **Removing the theme's data:** Diagnostics → Delete all theme data (type DELETE). This also removes directory listings, categories and payment records. Posts and pages are never touched.

## Changelog

### 2.1.4
- **Shorts:** the short-links page and its menu item are now called **Shorts** (address still `/short-urls/`). The dashboard tab keeps the descriptive name *Short Links*.

### 2.1.3
- **Clearer names:** Links → **My Sites** (your own sites and profiles), Short URLs → **Short Links**, QR → **QR Codes**, matching the dashboard tabs. Addresses (`/links/`, `/short-urls/`, `/qr/`) are unchanged, so nothing shared breaks. Page titles still at the old defaults are renamed automatically.
- **My Sites** no longer repeats the short-link list; that lives on Short Links only.

### 2.1.2
- **No false updates:** the theme declares itself as not from WordPress.org and ignores update offers for its folder name, so renaming the folder (e.g. to `click`) can't pull in a same-named WordPress.org theme.

### 2.1.1
- **PayPal subscriptions:** optional automatic renewal for monthly and yearly listings (sign-up, renewals, cancellation and end-of-term handled from IPN).
- **Multi-period payments:** buy up to 10 months or years at once, with an optional discount for 2+ periods.
- **IPN log:** every PayPal message is stored against its payment and viewable in the admin; unverifiable messages ask PayPal to retry.
- Database version 3 (three new payment columns; existing payments kept).

### 2.1.0
- **Directory:** categories, tags, listings, search, A–Z, sort, pinned Featured/Sponsored placements, visit counter.
- **Submissions:** public form with email confirmation, spam checks and ban lists; owner manage links for edits, upgrades, renewals and removal.
- **Paid listings:** Featured and Sponsored types, PayPal (IPN-verified) or invoices, payment log, expiry with reminders.
- **Reciprocal links and link checker:** scheduled checks, grace periods, followed-link reward.
- **Reviews:** star ratings on listings, averaged on approval.
- **Admin:** Directory tab with sub-tabs, listing columns and quick moderation, details box, CSV import/export, directory health checks.
- Header gains a Directory link (hidden when the directory is off). Database version 2.
- Listings published by hand from the editor email the owner once; “Send a new manage link” has its own email.

### 2.0.0
- Short links, click stats and redirects built into the theme, replacing URL Shortify (with a one-click importer and CSV import/export).
- QR codes built into the theme, replacing QR Code Composer: SVG and PNG, styled, cached, no JavaScript.
- New Notes blog at /notes/ with Sabon Next, EB Garamond fallback and optional Special Elite masthead.
- Multi-script typography with a Language toolbar button and block setting.
- Four colour schemes; hero artwork drawn in code and recoloured by each.
- Tabbed settings screen and Diagnostics with one-click fixes.
- Front page rebuilt from real blocks (was one raw HTML block); navigation works on mobile.
- Fixed: QR codes never rendered; Twenty Twenty-Five's presets were being replaced, leaving buttons unstyled; false "plugin inactive" notices.
- All CSS and JavaScript moved into `assets/css` and `assets/js`.

### 1.1.0
- Link hub front page; companion-plugin integration for URL Shortify 2.6.0 and QR Code Composer 3.0.5.
