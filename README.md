# Pixelforge image generator

A Laravel 13 + Vue 3 + Inertia app where signed-in users generate images through the Pixelforge API. Users pay Pixelforge's cost plus 25%, the app records exactly what each generation cost, and failed generations are free.

- **Generate**: write a prompt, choose a size, quality and optional reference image URLs, and see the estimated and maximum price before you submit.
- **History**: live status (sending, queued, generating, done, failed), the estimated and actual price, the token counts and the image.
- **Credits**: your balance, "Buy credits" packs (a fake purchase) and every ledger entry.
- **Usage report** (admins): what users were charged, what Pixelforge charged us and the profit, per UTC day and per user. Each day is checked against Pixelforge's `GET /v1/usage`.

## Running it locally

Requires Docker.

```bash
cp .env.example .env                  # then fill in the PIXELFORGE_* values below
composer install && npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
npm run dev

# in two more terminals:
./vendor/bin/sail artisan queue:work          # sends generations to Pixelforge
./vendor/bin/sail artisan schedule:work       # polls for overdue webhooks every minute
```

| Variable | What it is |
|---|---|
| `PIXELFORGE_API_KEY` | The API key for this environment (use a separate key per environment) |
| `PIXELFORGE_WEBHOOK_SECRET` | That key's `whsec_…` signing secret |
| `PIXELFORGE_BASE_URL` | Defaults to `https://pixelforge.promptedit.dev/v1` |

Set the key's webhook URL in the Pixelforge dashboard to `https://<your-host>/webhooks/pixelforge`. Locally you need a tunnel such as `cloudflared tunnel --url http://localhost` or `ngrok http 80`.

Make yourself an admin with `./vendor/bin/sail artisan users:make-admin you@example.com`.

**Tests:** `./vendor/bin/sail pest`. **Code style:** `./vendor/bin/sail pint`, `npm run check`, `npm run types:check`.

## Where things are

| What | Where |
|---|---|
| Money maths (cost, markup, estimate) | `app/Services/PricingService.php`, `app/Support/Nanodollars.php` |
| Everything that changes a generation's status or money | `app/Services/GenerationService.php` |
| Balance changes (ledger) | `app/Services/CreditLedger.php` |
| Sending to Pixelforge, with retries | `app/Jobs/SubmitGeneration.php` |
| Pixelforge HTTP calls | `app/Services/PixelforgeClient.php` |
| Webhook signature check | `app/Http/Middleware/VerifyPixelforgeSignature.php` |
| Fallback poller | `app/Console/Commands/SyncPendingGenerations.php` (scheduled in `routes/console.php`) |
| Admin report | `app/Http/Controllers/Admin/UsageReportController.php` |
| Pages | `resources/js/pages/generations`, `credits`, `admin` |
| Tests | `tests/Unit/PricingServiceTest.php`, `tests/Feature/{Webhooks,Generations,Credits}` |

## How money works

**All amounts are integers in nanodollars** (1 = $0.000000001), stored in `bigint` columns. Pixelforge reports costs to exactly 9 decimals, so this is exact and no float ever touches money. The UI rounds to 4 decimals for reading and shows the exact figure on hover.

- **Provider cost** = Σ tokens × rate ÷ 1,000,000, always using the `rates` Pixelforge froze on that generation. This matches `/v1/usage` to the nanodollar. A test checks it against the docs example (`0.169080000`).
- **User price** = provider cost × 1.25, **rounded up to the next nanodollar**. The markup can produce half a nanodollar. Rounding up means we never undercharge, and overcharge by at most $0.000000001.
- **Before submitting**, output and reference-image tokens are known exactly, while text tokens (±15%) and reasoning tokens (a range per quality) are not. So we show:
  - **Estimated**: text tokens at `ceil(chars/4)` and reasoning at the midpoint of its range.
  - **At most**: text tokens +15% and reasoning at its maximum. The Generate page computes this live in `resources/js/lib/pricing.ts`, a copy of the PHP logic, and the server recomputes it on submit.
- **Balances use a hold:**
  1. On submit, the maximum price is held (taken from the balance). If the balance can't cover it, the request is refused before anything is sent.
  2. On success, the hold is returned and the exact price is charged.
  3. On failure, the hold is returned and nothing is charged.

  The user can never go negative, and every step is a row in `credit_transactions`.

## How it copes with Pixelforge

- **Charged at most once.** `credit_transactions` has a unique index on `(generation_id, type)`, so the database itself rejects a second hold, release or charge for a generation. The code also checks first.
- **Duplicate webhooks.** Each event id is stored in `webhook_events` (unique), so a redelivery is recognised and skipped. Recording the event and applying it share one transaction: if applying fails, the event isn't recorded, we return 500 and Pixelforge's retry starts clean.
- **Out-of-order webhooks.** Status only moves forward (pending → queued → processing → succeeded/failed), and a finished generation is never touched again. A late `processing` event after `succeeded` does nothing.
- **Webhook before the POST returns.** We send our own id as `metadata.generation_id` and look generations up by it, so we don't need Pixelforge's id yet.
- **Rate limits, concurrency limit, overload, network errors.** The job retries with `Retry-After` or a growing wait (5s, 10s, 20s… up to 60s), 8 attempts in total (about 5 minutes). Every attempt sends the same `Idempotency-Key` (our generation id), so a retry can never create or bill a second generation. After the last attempt the generation is marked failed and its hold returned.
- **Errors that won't go away** (`content_policy_violation`, `daily_limit_exceeded`, validation errors) fail straight away with a plain-English message.
- **Missing webhooks.** Every minute, `generations:sync` asks Pixelforge directly about generations that have been in progress for more than 2 minutes.
- **Signatures.** HMAC-SHA256 over `"<t>.<raw body>"`, compared in constant time. Timestamps older than 5 minutes are rejected, so captured requests can't be replayed.

## Decisions and trade-offs

- **The rare "gave up but Pixelforge did it anyway" case.** If every submit attempt hit a network error but one actually reached Pixelforge, we've already told the user it failed. If that generation later succeeds, we keep it failed and free for the user, but record its provider cost so our books still match `/v1/usage`. The report shows it as a small loss.
- **Billing day = UTC completion day**, the same as Pixelforge. The app timezone is UTC.
- **Rates can change** between the estimate and creation. The charge always uses the generation's frozen rates. If rates rose in that window, the charge could exceed the hold slightly. This is accepted, because Pixelforge's prices aren't expected to change minute to minute.
- **Images aren't stored.** Pixelforge's links expire after 24 hours, and History then says so.
- **Live updates use polling** (Inertia `usePoll`, every 3 seconds, only while something is in progress) rather than websockets. It's simpler and plenty for this.
- **Buying credits is fake**: fixed $5 / $10 / $25 packs, no payment.
- **Divergences from my usual API-only Laravel conventions:** this is an Inertia app with session auth (from the Laravel Vue starter kit), so pages take the place of a JSON API.

## Testing error handling by hand

Put these in a prompt: `[fail]` (fails after being accepted), `[slow]` (60–90 s), `[duplicate]` (every webhook sent twice), `[overloaded]` (503 on submit, which retries until it gives up), `[blocked]` (rejected by the content policy). The Pixelforge dashboard can also resend any webhook. Resending a `generation.succeeded` must not change the balance.
