# Pre-production runbook

How to stand up the unified app on a pre-prod subdomain against a **copy** of the
production database, and a click-through checklist to verify everything works
before going live.

## Safety model (read first)

Pre-prod points at a **copy** of real data, so a few guards keep it from
touching the real world:

- **`POSTING_ENABLED=false`** — the posting engine will not publish to real
  social accounts or write back to Notion. Keep it `false` until you explicitly
  want to test live posting.
- **`MAIL_MAILER=log`** — scheduled commands (trial-expired, removed-database,
  etc.) email real users. On pre-prod, send mail to the log instead.
- **Stripe test keys** — no real charges.
- **Isolated DB** — never point pre-prod at the live database.
- **Leave production webhooks alone** — don't repoint live platform/Stripe
  webhooks at pre-prod.

---

## Using Laravel Forge

Forge handles most of the server side, so sections 1, 2 and 5 below collapse into
a few clicks. Here's the mapping — **what Forge does for you** vs **what's still
on you**.

**Forge does this automatically:**

- **Provisioning** (PHP 8.3, Composer, Node, MySQL, Nginx) and **SSL** (Let's Encrypt).
- **Web root** → `public/` when you create the site.
- **Deploys** via the site's Deploy Script (git pull + `composer install` + `migrate`).
- **Queue worker** — add one in the site's **Queue** tab (don't hand-roll Supervisor).
- **Scheduler** — flip the **Scheduler** toggle (it installs the `schedule:run` cron).
- **`.env`** — edit it in the site's **Environment** tab.

**Still on you (Forge won't do these):**

1. **Install ffmpeg** — not part of a default Forge box. SSH in (or add a Forge
   *Recipe*) and run: `sudo apt-get update && sudo apt-get install -y ffmpeg`.
2. **Copy the database** — Forge can *create* the DB, but you still import the
   prod dump into it (§3).
3. **Set the `.env` values** in the Environment tab — especially
   `POSTING_ENABLED=false`, `MAIL_MAILER=log`, Stripe **test** keys, `APP_URL`,
   and the DB pointing at the copy (§4).
4. **OAuth callback URLs / Stripe test webhook / noindex** — provider-side, §6.

**Recommended Deploy Script** (add the npm + optimize lines to Forge's default):

```bash
cd /home/forge/<your-site>
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

( flock -w 10 9 || exit 1; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force      # adds seo/blog_posts/media on first deploy
    $FORGE_PHP artisan optimize             # config + route + view cache
    $FORGE_PHP artisan queue:restart        # pick up new code in the worker
fi
```

Notes:
- **Filament static assets** (`/js/filament/*`, `/css/filament/*`,
  `/fonts/filament/*`) are published by `composer install` (via the
  `filament:upgrade` hook in `composer.json`). If you ever see 404s for those on
  a server, run `php artisan filament:assets`. The custom admin theme is built
  separately by `npm run build` (`viteTheme`), so both steps must run on deploy.
- The **Queue** worker should use connection `database` (that's what `QUEUE_CONNECTION` is set to).
- Forge gives a new site a fresh `APP_KEY` — fine (the stored tokens aren't
  `APP_KEY`-encrypted). Paste production's key only if you prefer.
- With the Deploy Script above, `migrate --force` runs on deploy, so the
  manual migrate in §3 is already covered.

With Forge in the picture, your effective list is: **§3 (DB copy)** → **§4 (env
values)** → install **ffmpeg** → add the **Queue** worker + **Scheduler** toggle
→ **§6** provider wiring → then the **§7 smoke-test checklist**.

---

## 1. Host prerequisites
*(Forge users: provisioned for you — see the Forge section above. Just add ffmpeg.)*

- PHP 8.3+, Composer, Node 20+, MySQL 8.
- **ffmpeg** installed on the host (video validation/encoding in the media job).
- A process manager (e.g. Supervisor) for the queue worker, and cron for the
  scheduler.
- The pre-prod subdomain's web root pointed at `public/`.

## 2. Deploy the code

```bash
git clone <repo> && cd <app>
composer install --no-dev --optimize-autoloader
npm ci && npm run build      # production assets — do NOT run the vite dev server
```

## 3. Database (copy + migrate)

```bash
# 1. Create an isolated pre-prod DB
mysql -h<host> -u<user> -p -e "CREATE DATABASE preprod_ns CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Dump production and import into the copy
mysqldump -h<prodhost> -u<user> -p --single-transaction --no-tablespaces <prod_db> \
  | mysql -h<host> -u<user> -p preprod_ns

# 3. Add the 3 landing tables the api DB doesn't have (seo, blog_posts, media).
#    `migrate` will run ONLY those three — everything else is already recorded.
php artisan migrate --force
```

Confirm: `SHOW TABLES` includes `seo`, `blog_posts`, `media`.

> Note on `APP_KEY`: the stored Notion/social tokens are **not** encrypted with
> `APP_KEY` (a fresh key still decrypts them — verified locally), so a new key is
> fine. Reusing production's `APP_KEY` is the safest choice if you're unsure.

## 4. Configure `.env`

Copy production's `.env`, then override:

| Key | Value | Why |
|---|---|---|
| `APP_URL` | `https://<preprod-subdomain>` | drives OAuth callbacks, SEO canonicals, Stripe return URLs |
| `APP_ENV` | `staging` | |
| `APP_DEBUG` | `true` | see errors while testing |
| `DB_DATABASE` / `DB_*` | the `preprod_ns` copy | |
| **`POSTING_ENABLED`** | **`false`** | safety guard |
| **`MAIL_MAILER`** | **`log`** | don't email real users |
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | **test mode** | no real charges |
| `CLOUDINARY_URL` | real or a test cloud | media uploads |
| everything else | copy from prod | Notion/social creds, Postmark, Slack, etc. |

Then:

```bash
php artisan config:clear   # (or config:cache once settled)
```

## 5. Build, cache, workers

```bash
php artisan optimize        # config + route + view cache
php artisan migrate:status  # sanity check
```

- **Queue worker** (Supervisor): `php artisan queue:work --tries=3 --timeout=0`
- **Scheduler** (cron, every minute): `php artisan schedule:run`
  - Safe to enable: posting commands honour `POSTING_ENABLED=false`, and mail
    goes to the log. The token-check commands only flip `is_valid` flags **in the
    copy**. If you'd rather, leave cron off at first and run commands by hand.

## 6. External services to wire (only if testing those flows end-to-end)

- **Connecting NEW accounts (OAuth):** register the pre-prod callback URLs in
  each provider's app, or the connect buttons will redirect but the callback
  will be rejected:
  - `https://<preprod>/app/connect/notion/callback`
  - `.../facebook/callback`, `.../linkedin/callback`, `.../linkedin-pro/callback`,
    `.../twitter/callback`, `.../tiktok/callback`, `.../threads/callback`,
    `.../youtube/callback`
  - You can **skip this** if you only test against the already-connected accounts
    in the copied data.
- **Stripe checkout:** with test keys, checkout works out of the box. To test the
  post-payment webhook, add a **test-mode** Stripe webhook → `https://<preprod>/stripe/webhook`.
- **SEO / crawlers:** add a `noindex` (or HTTP auth) on the pre-prod subdomain so
  Google doesn't index staging. Optionally `php artisan sitemap:generate`.

---

## 7. Smoke-test checklist (click through)

### Marketing (Blade, public)
- [ ] `/` home renders with styling.
- [ ] A solution page (`/instagram`, `/linkedin`, …) and a use-case page (`/for/agencies`).
- [ ] `/socialmedia`, `/blog`.
- [ ] An unknown slug (`/whatever`) → 404.
- [ ] "Get started" buttons land on `/app/register`.

### Auth
- [ ] Register a new account → you're redirected to **`/app/setup`** (not the dashboard).
- [ ] Log out, log back in.
- [ ] Forgot-password flow (check the mail log for the reset link).

### Onboarding wizard (`/app/setup`)
- [ ] Step through Welcome → Connect Notion → Add database → Connect accounts → Done.
- [ ] "Connect Notion" / social buttons redirect to the provider (needs §6 callbacks to fully round-trip).
- [ ] Finishing the wizard sends you to the dashboard and you're no longer force-redirected to setup.

### Dashboard (`/app/dashboard`) — log in as a user that already has data
- [ ] Greeting + plan chips (plan name, databases X/Y, accounts X/Y, posts limit).
- [ ] **Databases** tab: rows show linked-account logos + "Open in Notion".
- [ ] **Add database**: opens, scans your Notion workspace, lists databases. *(Connecting one writes scaffolding columns into real Notion — only do this with a throwaway workspace.)*
- [ ] **Manage accounts** dialog: avatars + platform badges, save updates the links.
- [ ] **Add account**: brand-logo buttons redirect to the provider.
- [ ] **Social Accounts** tab: avatar cards, status dots, stats only when present.
- [ ] **Scheduled Posts** tab: platform logos, "Notion ↗" links, status pills.
- [ ] **Submitted Posts** tab: loads, "Load more" paginates, Facebook posts link out (others don't).
- [ ] Remove / reconnect actions fire and toast.

### Account pages
- [ ] **Pricing**: tiers render, monthly/yearly toggle, current plan highlighted; "Choose plan" → Stripe **test** checkout.
- [ ] **Support**: send a message → check the mail log.
- [ ] **Affiliates**: stats render (or the "not enrolled" state).
- [ ] **Settings → Profile**: change the display name, it persists.
- [ ] **Settings → Security**: change password.

### Admin (`/admin`) — log in as the admin user (id 1)
- [ ] `/admin` loads; a non-admin user gets 403.
- [ ] `/admin/blog-posts` — create/edit a blog post (cover image + SEO fields).
- [ ] `/admin/calendar-view` — the posts calendar renders.
- [ ] `/admin/logs` — log viewer.

### Posting engine (do this last, deliberately)
- [ ] With `POSTING_ENABLED=false`, run `php artisan app:perform-posts` → it should report it dispatched nothing / skipped (no real posts). Check the log for the "Posting disabled" guard.
- [ ] Queue + scheduled jobs process without errors (`php artisan queue:work`, watch the log).
- [ ] **Only when ready**, on a **throwaway test account**, flip `POSTING_ENABLED=true` and test one real post end-to-end, then turn it back off.

---

## 8. Not yet built / intentionally deferred (don't test expecting these)

- Manual post **create/edit** modal (posts flow from Notion; only the legacy
  "fake Facebook" endpoint exists).
- Instagram / Threads / TikTok / LinkedIn **post permalinks** (only Facebook
  feed posts link out — see the `permalink` accessor TODO in `NotionPosts`).
- The Notion **"create a brand-new database in a page"** flow (only "connect an
  existing database" is wired).
- Profile **analytics** page, `/privacy` & `/terms` pages, SimpleStats analytics.
- **2FA / passkeys** were removed by design.

## 9. Going to production (later)

Same as above, but: live DB (auth tables already match — zero schema delta; just
add `seo`/`blog_posts`/`media`), `POSTING_ENABLED=true`, live Stripe keys,
production OAuth callback + platform webhook URLs on the real domain,
`MAIL_MAILER` back to Postmark, remove the `noindex`, and run
`php artisan sitemap:generate`.
