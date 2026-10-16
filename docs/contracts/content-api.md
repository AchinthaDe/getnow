# Content API v1 Contract

Version: v1  
Status: Active  
Base Path: `/api/v1/content`

The Content API delivers public storefront layout state from the GetNow CMS. It is optimized for server-side or client-side storefront consumption with deterministic revision-based conditional caching (`ETag` / `If-None-Match`).

---

## 1. Endpoints

### `GET /api/v1/content/layout`

Delivers the complete public layout graph, including global site settings, visible placement blocks, and enabled navigation menus.

#### Access & Authentication
- **Authentication**: None. Publicly accessible.
- **Rate Limit**: 60 requests per minute per IP address by default (`CONTENT_API_RATE_LIMIT_PER_MINUTE=60`). When exceeded, returns `429 Too Many Requests`.

#### Storefront Caching Guidance
The Blade storefront server and client consumers should leverage HTTP conditional caching. Each response includes a strong `ETag: "<revision>"`. When fetching layout, clients should supply the cached revision in `If-None-Match: "<revision>"`. If the content has not changed, the server returns `304 Not Modified` with zero body content, avoiding redundant serialization and network payload transfer.

#### Request Headers
| Header | Type | Required | Description |
|---|---|---|---|
| `Accept` | `string` | Recommended | Must be `application/json` |
| `If-None-Match` | `string` | Optional | Client's previously received `meta.revision` quoted string, weak token, comma-separated list, or wildcard `*` (e.g., `"1a2b3c..."`, `W/"1a2b3c..."`, or `*`) |

#### Response Headers
| Header | Description |
|---|---|
| `Content-Type` | `application/json` |
| `ETag` | Quoted SHA-256 revision hash matching `meta.revision` (e.g. `"a8f5f167..."`) |
| `Cache-Control` | Default Laravel private cache control (`no-cache, private`) |

#### Response Codes
- `200 OK`: Full layout JSON payload returned.
- `304 Not Modified`: Cached content matches current revision. Empty response body.
- `429 Too Many Requests`: Client exceeded per-minute rate limit.

---

## 2. Response Schema

### Root Structure
```json
{
  "data": {
    "settings": {
      "site_name": "GetNow Marketplace",
      "default_meta_description": "Wholesale mobile phones and consumer electronics.",
      "search_placeholder": "Search models, brands, or SKUs...",
      "footer_brand_text": "GetNow Electronics Wholesale Ltd."
    },
    "placements": {
      "announcement_bar": [
        {
          "id": "01jar000000000000000000001",
          "type": "announcement_bar",
          "version": 2,
          "data": {
            "text": "Welcome to GetNow Wholesale Electronics",
            "secondary_text": null,
            "locale_text": null,
            "link_url": null,
            "tone": "info"
          }
        }
      ],
      "home_hero_carousel": [],
      "home_hero_side": [],
      "home_service_strip": [],
      "home_categories_header": [],
      "home_featured_header": [],
      "home_collection_banner": [],
      "home_new_arrivals_header": [],
      "home_brand_strip": []
    },
    "menus": {
      "main_nav": {
        "title": "Main Navigation",
        "items": [
          {
            "id": "01jar000000000000000000002",
            "label": "Phones",
            "url": "/phones",
            "icon": null,
            "new_tab": false,
            "children": [
              {
                "id": "01jar000000000000000000003",
                "label": "Apple",
                "url": "/phones/apple",
                "icon": null,
                "new_tab": false,
                "children": []
              }
            ]
          }
        ]
      },
      "mobile_nav": {
        "title": null,
        "items": []
      },
      "footer_column_1": {
        "title": null,
        "items": []
      },
      "footer_column_2": {
        "title": null,
        "items": []
      },
      "footer_column_3": {
        "title": null,
        "items": []
      }
    }
  },
  "meta": {
    "api_version": 1,
    "revision": "a8f5f167f44f4964e6c998dee827110ca8f5f167f44f4964e6c998dee827110c",
    "generated_at": "2026-10-14T10:00:00Z"
  }
}
```

---

## 3. Data Integrity & Contract Guarantees

1. **Complete Key Presence**: Every defined placement (`Placement::cases()`) and menu key (`MenuKey::cases()`) is always present in the response, returning empty arrays or default structures when unpopulated or unprovisioned.
2. **Server-Side Visibility Enforcement**: Content that is draft, archived, disabled, scheduled in the future, or expired is never exposed.
3. **Menu Hierarchy & Cascading Exclusion**: If a parent menu item is disabled, the parent and all of its descendant children are omitted from the response. Remaining items are strictly ordered by `sort_order ASC, id ASC`.
4. **ID Anonymization**: Internal PostgreSQL incremental IDs are never exposed. All entity identifiers are external Crockford ULIDs mapped to the property name `id`.
5. **Deterministic Revision**: `meta.revision` is computed from the canonical JSON serialization of the `data` object, ensuring identical content produces identical hashes across different request timestamps.
6. **Placement Cardinality & Unservable Resilience**: Placements respect their configured max counts (`home_hero_carousel` up to 5, `home_service_strip` up to 4, all other 7 placements up to 1). Candidate blocks that are unservable (due to unknown type, unsupported schema version, upgrade failure, or invalid payload) or whose type is not permitted for the placement are skipped silently, falling back to the next eligible candidate in serving order (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`).

---

## 4. Block Type Payload Schemas

### 1. `announcement_bar` (Version 2)
| Field | Type | Nullable | Constraints & Description |
|---|---|---|---|
| `text` | `string` | No | Min 1, max 120 characters. Plain text only (`NoHtml`). |
| `secondary_text` | `string` | Yes | Max 120 characters. Plain text only (`NoHtml`). Defaults to `null`. |
| `locale_text` | `string` | Yes | Max 60 characters. Plain text only (`NoHtml`). Defaults to `null`. |
| `link_url` | `string` | Yes | Validated path starting with `/` or `https://` URL (`SafeLink`). Defaults to `null`. |
| `tone` | `string` | No | Enum value: `"info"`, `"warning"`, or `"success"`. Defaults to `"info"`. |

```json
{
  "text": "Good tech. Better everyday.",
  "secondary_text": "Free delivery on orders over Rs. 25,000",
  "locale_text": "Sri Lanka / LKR",
  "link_url": null,
  "tone": "info"
}
```

### 2. `promo_slide` (Version 1)
| Field | Type | Nullable | Constraints & Description |
|---|---|---|---|
| `theme` | `string` | No | Enum value: `"sky"`, `"lilac"`, or `"mint"`. Slide background styling token. |
| `eyebrow` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `title_line_1` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `title_line_2` | `string` | Yes | Max 60 characters. Plain text only (`NoHtml`). Defaults to `null`. |
| `description` | `string` | No | Min 1, max 200 characters. Plain text only (`NoHtml`). |
| `cta_label` | `string` | No | Min 1, max 50 characters. Plain text only (`NoHtml`). |
| `cta_url` | `string` | No | Validated path starting with `/` or `https://` URL (`SafeLink`). |
| `image_url` | `string` | No | Validated path starting with `/` or `https://` URL (`SafeLink`). |
| `image_alt` | `string` | No | Min 1, max 120 characters. Plain text only (`NoHtml`). |
| `label` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). Accessible slide/tab label. |

```json
{
  "theme": "sky",
  "eyebrow": "FIND YOUR FREQUENCY",
  "title_line_1": "Less noise.",
  "title_line_2": "More you.",
  "description": "Make room for better sound. Explore offers on everyday audio.",
  "cta_label": "Shop audio offers",
  "cta_url": "/shop?category=audio&deals=1",
  "image_url": "/images/products/soundcore-space-2.jpg",
  "image_alt": "Soundcore Space 2 over-ear headphones",
  "label": "Audio offers"
}
```

### 3. `feature_banner` (Version 1)
| Field | Type | Nullable | Constraints & Description |
|---|---|---|---|
| `eyebrow` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `title_line_1` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `title_line_2` | `string` | Yes | Max 60 characters. Plain text only (`NoHtml`). Defaults to `null`. |
| `description` | `string` | Yes | Max 240 characters. Plain text only (`NoHtml`). Defaults to `null`. |
| `cta_label` | `string` | No | Min 1, max 50 characters. Plain text only (`NoHtml`). |
| `cta_url` | `string` | No | Validated path starting with `/` or `https://` URL (`SafeLink`). |
| `image_url` | `string` | No | Validated path starting with `/` or `https://` URL (`SafeLink`). |
| `image_alt` | `string` | No | Min 1, max 120 characters. Plain text only (`NoHtml`). |
| `badge_text` | `string` | Yes | Max 40 characters. Plain text only (`NoHtml`). Defaults to `null`. |

```json
{
  "eyebrow": "MAKE ROOM FOR GREAT SOUND",
  "title_line_1": "Big sound.",
  "title_line_2": "Small footprint.",
  "description": null,
  "cta_label": "Explore speakers",
  "cta_url": "/shop?category=speakers",
  "image_url": "/images/products/marshall-acton-iii.png",
  "image_alt": "Marshall Acton III speaker",
  "badge_text": "MARSHALL ACTON III"
}
```

### 4. `service_item` (Version 1)
| Field | Type | Nullable | Constraints & Description |
|---|---|---|---|
| `icon` | `string` | No | Enum value: `"truck"`, `"box"`, `"shield"`, `"headphones"`, `"bolt"`, `"check"`, `"lock"`, `"heart"`, `"user"`, `"watch"`, `"phone"`, `"speaker"`, `"battery"`, `"gamepad"`, `"home"`, `"laptop"`, `"mail"`, `"search"`, or `"bag"`. |
| `title` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `subtitle` | `string` | No | Min 1, max 100 characters. Plain text only (`NoHtml`). |
| `link_url` | `string` | Yes | Validated path starting with `/` or `https://` URL (`SafeLink`). Defaults to `null`. |

```json
{
  "icon": "truck",
  "title": "Islandwide delivery",
  "subtitle": "From our store to your door",
  "link_url": null
}
```

### 5. `section_header` (Version 1)
| Field | Type | Nullable | Constraints & Description |
|---|---|---|---|
| `eyebrow` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `title` | `string` | No | Min 1, max 100 characters. Plain text only (`NoHtml`). |
| `cta_label` | `string` | Yes | Max 50 characters. Plain text only (`NoHtml`). Defaults to `null`. |
| `cta_url` | `string` | Yes | Validated path starting with `/` or `https://` URL (`SafeLink`). Defaults to `null`. |

```json
{
  "eyebrow": "FIND YOUR THING",
  "title": "A little something for every day.",
  "cta_label": "Explore all categories",
  "cta_url": "/shop"
}
```

### 6. `brand_strip` (Version 1)
| Field | Type | Nullable | Constraints & Description |
|---|---|---|---|
| `eyebrow` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `title` | `string` | No | Min 1, max 60 characters. Plain text only (`NoHtml`). |
| `brands` | `array` | No | 0 to 10 sequential brand item objects. |
| `brands[].label` | `string` | No | Min 1, max 40 characters. Plain text only (`NoHtml`). |
| `brands[].url` | `string` | No | Validated path starting with `/` or `https://` URL (`SafeLink`). |

```json
{
  "eyebrow": "GOOD COMPANY",
  "title": "Great brands.",
  "brands": [
    { "label": "Apple", "url": "/shop?brand=Apple" },
    { "label": "Samsung", "url": "/shop?brand=Samsung" },
    { "label": "Anker", "url": "/shop?brand=Anker" },
    { "label": "JBL", "url": "/shop?brand=JBL" },
    { "label": "Marshall", "url": "/shop?brand=Marshall" },
    { "label": "Sony", "url": "/shop?brand=Sony" }
  ]
}
```
