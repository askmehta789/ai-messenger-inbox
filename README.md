# AI Messenger Inbox

A multi-tenant Messenger inbox where your own AI (any provider you plug in) carries the conversation **in Nepali** and collects **name, phone, address, and product** from each customer — with a live inbox dashboard (view conversations, take over manually, manage leads) covering multiple Facebook Pages/businesses from one place.

Stack: PHP 8 + MySQL/MariaDB, no framework, no Composer. Same conventions as `messenger-leadbot`, deployed the same way via cPanel Git Version Control.

This is a **separate system** from `messenger-leadbot` and needs its **own, separate Meta App** — a Meta App only supports one Webhook Callback URL, and `messenger-leadbot`'s app is already wired to its own bot.

## 1. Meta setup (one time, per new Meta App)

1. developers.facebook.com → Create app → "Business" type. Add the **Messenger** product.
2. Messenger → Settings → Webhooks: Callback URL `https://luprah.site/inbox/webhook.php`, Verify token = the `verify_token` you put in `config.php`.
   Subscribe fields: `messages`, `message_echoes`, `messaging_postbacks`, `messaging_referrals`.
3. For each Facebook Page you want this system to manage: generate its access token in "Generate access tokens", then "Add Subscriptions" for the same 4 fields.
4. Add each Page (Page ID + the access token you just generated) via this app's own **Settings → Facebook Pages** screen — not a config file.
5. Test mode first: until App Review is approved, the bot only replies to accounts with a role on the app (add testers).

## 2. Deploy on cPanel

1. Create a MySQL database + user in cPanel, import `schema.sql`.
2. Copy `config.sample.php` → `config.php`. Fill in:
   - `app_id` / `app_secret` / `verify_token` from the new Meta App.
   - `db` credentials.
   - `crypto_key` — generate with `php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"`. This encrypts AI provider API keys at rest; back it up, losing it makes saved keys unrecoverable.
3. Seed an admin login: `php -r "echo password_hash('yourpass', PASSWORD_DEFAULT);"`, then insert into `admin_users` (via phpMyAdmin): `INSERT INTO admin_users (username, password_hash) VALUES ('you', '<hash>');`
4. cPanel → Git™ Version Control: create a repo pointed at this project's GitHub remote. `.cpanel.yml` deploys to `/home/lupruhrv/luprah.site/inbox/`.
5. Open `https://luprah.site/inbox/` and log in.
6. In **Settings**, create a business, add its AI provider + API key + model + instructions, and add its Facebook Page(s).

## 3. How it works

```
Customer ──► Messenger ──► Meta webhook ──► webhook.php
                                              │ verify signature, dedupe message id
                                              │ lock the conversation row
                                              │ build history + known lead fields → AI provider
                                              ├─► leads (name, phone, address, product, status)
                                              └─► Send API reply (in Nepali)
Agent ──► dashboard/inbox.php (live thread, manual reply pauses AI 12h, "Resume AI" to re-enable)
```

## 4. Files

| File | Purpose |
|---|---|
| `webhook.php` | Meta callback: verification, HMAC signature check, fast 200 response, then processing |
| `lib/handler.php` | Conversation state, dedupe, AI dispatch, lead merge |
| `lib/ai/ai.php` | Shared system-prompt builder + JSON-reply parser, provider dispatch |
| `lib/ai/openai.php`, `lib/ai/anthropic.php` | Provider adapters — same input/output shape, add more by copying the pattern |
| `lib/crypto.php` | Encrypts/decrypts AI API keys at rest (libsodium) |
| `lib/graph.php` | Messenger Send API client |
| `dashboard/inbox.php` | Conversation list + live thread + manual takeover |
| `dashboard/leads.php` | Lead board, filters, CSV export |
| `dashboard/settings.php` | Manage businesses, AI provider/key/instructions, Facebook Pages |
| `schema.sql` | `businesses`, `pages`, `conversations`, `leads`, `messages`, `admin_users` |

## 5. Adding another AI provider

Copy `lib/ai/openai.php`'s shape: a function `ai_<provider>_complete($cfg, $business, $system, $history, $userMessage): string` that calls the provider's API and returns the raw text response (expected to be the JSON object described in `ai_build_system_prompt()`). Wire it into the `match()` in `lib/ai/ai.php`'s `ai_generate_reply()`, and add it as an option in `dashboard/settings.php`'s provider dropdown.
