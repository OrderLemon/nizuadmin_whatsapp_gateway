# `get_messages_per_client` — API reference

WhatsApp message statistics for a chosen set of gateway accounts, aggregated
per client, per day, per recipient country, with Meta pricing applied.

It returns the same figures the nightly cron (`v1/cron/day.php`) stores in the
`daily_conversations_stats` table, but computed live, only for the account
numbers you ask for, and split out per account instead of merged. A row from
this endpoint and a row from that table are comparable value for value.

---

## 1. How to call it

This service is a PMSRAPI microservice: there is **one** URL, `/v1/`, and the
function you want is named in the JSON body. There is no `/v1/get_messages_per_client`
path — calling one returns 404.

| | |
|---|---|
| **URL** | `{base_url}/v1/` |
| **Method** | `GET` (with a JSON request body) |
| **Headers** | `Content-Type: application/json`, `Authorization: Bearer {ms_server_token}` |
| **Body** | `{"function": "get_messages_per_client", "parameters": { ... }}` |

```bash
curl --location --request GET 'https://{host}/v1/' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer <ms_server_token>' \
  --data '{
    "function": "get_messages_per_client",
    "parameters": { "numbers": ["32470111222"] }
  }'
```

> **This is a GET request that carries a body.** Many HTTP clients drop the
> body on GET. With curl use `--request GET --data`. In code, use whatever
> option your client offers for "GET with payload"; if it has none, the
> request will fail validation because `numbers` never arrives.

### Prerequisite

The function name must be listed under `allowed_functions.GET` in the service's
secret config JSON (which lives outside the web root). If it is missing, the
dispatcher returns **an empty response with no JSON body at all** — that
symptom almost always means the whitelist entry, not a bug in your request.

---

## 2. Parameters

All parameters go inside the `parameters` object. Only `numbers` is required.

### 2.1 `numbers` — required

The gateway account numbers to report on. These are **your own WhatsApp
business numbers** (the accounts that own the `in_`/`out_` tables), not the
end customers they talk to.

| Accepts | Example |
|---|---|
| Array of strings | `["32470111222", "34600111222"]` |
| Array of integers | `[32470111222]` |
| Comma-separated string | `"32470111222,34600111222"` |

Handling rules:

- Non-digit characters are stripped, so `"+32 470 111 222"` becomes `32470111222`.
- After stripping, an entry must be **1 to 32 digits** or it is rejected.
- Booleans, arrays and objects are rejected (never coerced).
- Duplicates are collapsed; the original order is preserved.
- Rejected entries do **not** fail the request. They come back in
  `data.rejected_numbers`. The request only fails with 400 if *nothing* usable
  remains.
- **Maximum 200 numbers per request.**

Also accepted at `payload.numbers` or at the top level of the body, for
convenience. `parameters.numbers` is the canonical location.

### 2.2 Optional filters

| Parameter | Aliases | Values | Default | Notes |
|---|---|---|---|---|
| `date_from` | `from_date` | `YYYY-MM-DD` | yesterday | Inclusive. Rejects impossible dates like `2026-02-30`. |
| `date_to` | `until_date` | `YYYY-MM-DD` | today | Inclusive. Must be on or after `date_from`. |
| `direction` | — | `out`, `in`, `both` | **`out`** | Which per-account tables to read. See §2.3. |
| `status` | — | `all`, `delivered`, `undelivered` | `all` | Also accepts `sent`/`success` → `delivered`, and `failed`/`not_delivered` → `undelivered`. |
| `errors_only` | `only_errors` | boolean | `false` | Count only sends that errored. See §4.4. |
| `country` | `countries` | ISO 3166-1 alpha-2 | none | Single code `"BE"`, CSV `"BE,ES"`, or array `["BE","ES"]`. Case-insensitive. |
| `message_type` | — | `all`, `template`, `service` | `all` | `templates`/`marketing` are synonyms for `template`; `free_form`/`freeform` for `service`. |

Defaults for the date range mirror the nightly cron, which refreshes yesterday
and today. **Maximum span is 366 days.**

Dates are interpreted in the **server's timezone** and compared against each
message's `incoming_date`.

### 2.3 The `direction` default is `out`

`direction` defaults to `out`, so by default **only outgoing traffic is
counted and `messages_in` reads 0**. That is intentional, not a bug.

Pass `"direction": "both"` to get the full equivalent of the nightly cron,
including incoming messages. Check `data.meta.incoming_included` in the
response to confirm which side was actually counted.

---

## 3. Response

Every response is wrapped by the framework as `{"success": bool, "data": ...}`.

```jsonc
{
  "success": true,
  "data": {
    "values": {
      "clients": [
        {
          "number": "32470111222",
          "tables": { "in": "in_32470111222", "out": "out_32470111222" },
          "totals": {
            "messages_in": 7,
            "messages_out": 22,
            "messages_in_success": 7,
            "messages_out_success": 19,
            "messages_out_error": 3,
            "total_marketing_messages": 17,
            "total_service_messages": 5,
            "cost_in": "0.0000",
            "cost_out": "0.7240"
          },
          "days": [
            {
              "day": "2026-09-01",
              "country_code": "BE",
              "messages_in": 7,
              "messages_out": 18,
              "messages_in_success": 7,
              "messages_out_success": 15,
              "messages_out_error": 3,
              "total_marketing_messages": 13,
              "total_service_messages": 5,
              "cost_in": "0.0000",
              "cost_out": "0.4900"
            }
          ]
        }
      ],
      "totals": { "...": "same nine fields, summed across every client" }
    },
    "filters": {
      "date_from": "2026-09-01",
      "date_to": "2026-09-02",
      "direction": "both",
      "status": "all",
      "errors_only": false,
      "countries": [],
      "message_type": "all"
    },
    "unknown_numbers": ["99999999999"],
    "rejected_numbers": [],
    "meta": {
      "grain": "day + country_code",
      "days_in_range": 2,
      "currency": "EUR",
      "empty_days_omitted": true,
      "incoming_included": true,
      "error_basis": { "out_32470111222": "delivered = 0" },
      "pricing_rates_available": true
    }
  }
}
```

### 3.1 The nine statistic fields

They appear identically in each `days[]` entry, in each client's `totals`, and
in the grand `totals`.

| Field | Type | Meaning |
|---|---|---|
| `messages_in` | int | Incoming messages received. |
| `messages_out` | int | Outgoing messages sent. |
| `messages_in_success` | int | Incoming with `delivered = 1`. |
| `messages_out_success` | int | Outgoing confirmed delivered. **This is the billable count.** |
| `messages_out_error` | int | Outgoing that errored. See §4.4. |
| `total_marketing_messages` | int | Outgoing billed as Marketing. |
| `total_service_messages` | int | Outgoing billed as Service. |
| `cost_in` | string | Always `"0.0000"` — Meta does not charge to receive. |
| `cost_out` | string | Outgoing cost, 4 decimal places, in EUR. |

Counts are JSON numbers. **Costs are JSON strings** with fixed 4-decimal
precision, matching how they are stored in `daily_conversations_stats`. Parse
them as decimals; do not rely on float equality.

`total_marketing_messages + total_service_messages == messages_out`, always.
`messages_out_success` is a subset of `messages_out`, and only that subset is
priced.

### 3.2 Envelope fields

| Field | Meaning |
|---|---|
| `values.clients[]` | One entry per requested number that had at least one matching table. Same order as the request, after de-duplication. |
| `values.clients[].tables` | Which tables were actually read. `null` means that side was not read, because of `direction` or because the table does not exist. |
| `values.clients[].days[]` | One row per `(day, country_code)` that had traffic. Sorted by day ascending, then country code ascending. |
| `values.totals` | The nine fields summed across every returned client. |
| `filters` | The fully resolved filters after defaults and synonyms were applied. Read this back to see what actually ran. |
| `unknown_numbers` | Valid-looking numbers with no matching table for the requested direction. Not an error. |
| `rejected_numbers` | Entries that failed number validation and were skipped. |

### 3.3 `meta`

| Field | Meaning |
|---|---|
| `grain` | Always `"day + country_code"`. |
| `days_in_range` | Days covered by the request, inclusive of both ends. |
| `currency` | Always `"EUR"`. |
| `empty_days_omitted` | Always `true`. Days with no traffic are absent from `days[]`. Zero-fill client-side if you need a continuous series. |
| `incoming_included` | Whether incoming traffic was counted. False when `direction` is `out`, **or** when a `message_type` filter is active (see §4.5). |
| `error_basis` | Per `out_` table, which column the error count was derived from. See §4.4. |
| `pricing_rates_available` | `false` means the pricing table does not exist yet, so every cost reads `0.0000` while the counts stay correct. |

---

## 4. How the figures are derived

### 4.1 Country attribution

The `country_code` is **not** a stored column. It is derived from the
counterparty's phone number:

- **Outgoing:** the recipient, read from the message payload JSON
  (`recipient_phonenumber`, falling back to `to`).
- **Incoming:** the sender's phone number.

A number that cannot be resolved to a country becomes the sentinel `"XX"`.
`+1` numbers default to `US` except for a handful of Caribbean area codes.

### 4.2 Marketing vs Service

Message type `send_template` or `hsm` is a business-initiated template send and
is billed as **Marketing**. Everything else is a free-form reply, billed as
**Service**.

### 4.3 Cost

Cost is charged **per successfully delivered outgoing message**, at its
category's rate for the recipient's Meta pricing market, using the rate version
in effect on that message's own day.

Service messages currently have **no rate** in the seeded pricing table, so
they cost `0.0000` today. The moment a Service rate row is added, this endpoint
starts charging them with no code change. `cost_in` is always zero.

### 4.4 What counts as an error

The per-account tables are created by the gateway, not by this service, so the
error column is **discovered at runtime** rather than assumed. The endpoint
looks for `error`, `error_message`, `error_code`, `errors`, then
`failure_reason`. If none exists, it falls back to treating an undelivered send
as the error case.

**Always read `meta.error_basis` to see which basis was used.** A value of
`"delivered = 0"` means the fallback was in play, so `messages_out_error`
equals `messages_out - messages_out_success`.

### 4.5 A `message_type` filter drops incoming traffic

Incoming messages are never templates, so filtering by `message_type` would
make an unfiltered incoming count misleading. When `message_type` is anything
other than `all`, the incoming side is skipped entirely, even if
`direction` is `in` or `both`. `meta.incoming_included` reports this.

### 4.6 Where each filter is applied

`status` and `errors_only` are pushed into SQL. `country` and `message_type`
are applied in PHP after rows are fetched, because both are derived values.
This affects performance, never correctness.

---

## 5. Errors

| Status | Cause |
|---|---|
| *(empty body)* | `get_messages_per_client` is missing from `allowed_functions.GET` in the service config. |
| `400` | `Content-Type` is not `application/json`, or the body is not valid JSON. |
| `400` | A parameter failed validation — see the table below. |
| `401` | Missing, malformed, or wrong `Authorization: Bearer` token. |
| `405` | The GET method has no `allowed_functions` entry at all. |
| `500` | The service has no database configured. |

Validation failures return `{"success": false, "data": {"error": "Bad Request: ..."}}`.
Triggers include: `numbers` missing, empty, or containing nothing usable; more
than 200 numbers; a malformed or impossible date; `date_from` after `date_to`;
a span over 366 days; an unrecognised `direction`, `status`, or `message_type`;
a country code that is not two letters.

---

## 6. Worked examples

**Yesterday and today, outgoing only** (all defaults):

```json
{
  "function": "get_messages_per_client",
  "parameters": { "numbers": ["32470111222", "34600111222"] }
}
```

**A full month, both directions** — the exact equivalent of the nightly cron,
scoped to these accounts:

```json
{
  "function": "get_messages_per_client",
  "parameters": {
    "numbers": ["32470111222"],
    "date_from": "2026-09-01",
    "date_to": "2026-09-30",
    "direction": "both"
  }
}
```

**Billable template sends to Belgium and Spain** — what you actually paid for:

```json
{
  "function": "get_messages_per_client",
  "parameters": {
    "numbers": ["32470111222"],
    "date_from": "2026-09-01",
    "date_to": "2026-09-30",
    "message_type": "template",
    "status": "delivered",
    "country": "BE,ES"
  }
}
```

Read `values.totals.cost_out` for the total, and each `days[]` row for the
daily breakdown by country.

**Failed sends only, for a deliverability check:**

```json
{
  "function": "get_messages_per_client",
  "parameters": {
    "numbers": ["32470111222"],
    "date_from": "2026-09-15",
    "date_to": "2026-09-18",
    "errors_only": true
  }
}
```

Confirm `meta.error_basis` before trusting the numbers.

---

## 7. Notes for callers

- **Read `filters` back.** It shows the resolved values after defaults and
  synonyms, which is the fastest way to confirm the request meant what you
  intended.
- **`unknown_numbers` is not an error.** It lists numbers with no matching
  table. A number can land there simply because `direction` is `out` and only
  an `in_` table exists.
- **This endpoint only reads.** It never creates, seeds, or writes any table,
  including the pricing table the nightly cron maintains.
- **Cap your requests.** 200 numbers and 366 days are hard limits. For wider
  reporting, page by date range and merge client-side; the `days[]` grain makes
  merging straightforward.
- **Costs are estimates from stored rates**, not invoiced amounts from Meta.
  They track whatever is in the pricing table at query time.
