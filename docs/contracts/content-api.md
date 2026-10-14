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
| `If-None-Match` | `string` | Optional | Client's previously received `meta.revision` quoted string or weak token (e.g., `"1a2b3c..."` or `W/"1a2b3c..."`) |

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
          "version": 1,
          "data": {
            "text": "Welcome to GetNow Wholesale Electronics",
            "link_url": null,
            "tone": "info"
          }
        }
      ]
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
    "revision": "a8f5f167f44f4964e6c998dee827110c",
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
