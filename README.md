# Filament Cloudflare

Manage Cloudflare settings, firewall rules, cache, page rules, analytics, access apps, and edge caching directly from your Filament admin panel.

## Requirements

- PHP 8.3+
- Laravel 11.28+ or 12.x
- Filament 5.0+
- A Cloudflare account with at least one zone

## Installation

### 1. Install via Composer

```bash
composer require notwonderful/filament-cloudflare
```

### 2. Publish the config

```bash
php artisan vendor:publish --tag=cloudflare-config
```

### 3. Run migrations

The package stores encrypted credentials in the database as a fallback when `.env` values are not set.

```bash
php artisan migrate
```

### 4. Set Cloudflare credentials

Add to your `.env` file:

```env
# Option 1: API Token (recommended)
CLOUDFLARE_TOKEN=your_api_token
CLOUDFLARE_ZONE_ID=your_zone_id
CLOUDFLARE_ACCOUNT_ID=your_account_id

# Option 2: Email + Global API Key
CLOUDFLARE_EMAIL=you@example.com
CLOUDFLARE_API_KEY=your_global_api_key
CLOUDFLARE_ZONE_ID=your_zone_id
CLOUDFLARE_ACCOUNT_ID=your_account_id
```

Credentials can also be managed from the Filament Settings page (stored encrypted in the database). `.env` values take priority.

### 5. Register the plugin

In your Filament panel provider:

```php
use notwonderful\FilamentCloudflare\CloudflarePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(CloudflarePlugin::make());
}
```

## Features

| Feature | Description |
|---|---|
| **Zone Settings** | View and edit SSL mode, security level, HTTPS enforcement, browser integrity check, and more |
| **Firewall Access Rules** | Block, challenge, or whitelist IPs, IP ranges, and countries |
| **User Agent Rules** | Block or challenge requests by user agent string |
| **Custom Firewall Rules** | View custom WAF rules (read-only) |
| **Cache Management** | Purge everything, or by specific files, tags, or hosts |
| **Cache Rules** | Create, edit, and delete cache rules via the Rulesets API |
| **Page Rules** | Manage page rules with forwarding URLs, cache levels, SSL settings |
| **Analytics** | Requests, bandwidth, unique visitors, cached vs uncached, threats — with chart widgets |
| **Access Apps** | View, create, and delete Cloudflare Access applications |
| **Edge Caching** | One-click guest page caching and media attachment caching via cache rules |

## Cloudflare API Coverage

### Supported

- [x] Zone Settings — read & edit (`zones/{id}/settings`)
- [x] Zone Details — list & read (`zones`, `zones/{id}`)
- [x] Firewall Access Rules — CRUD (`zones/{id}/firewall/access_rules/rules`)
- [x] User Agent Rules — CRUD (`zones/{id}/firewall/ua_rules`)
- [x] Custom Firewall Rules — read-only (`zones/{id}/rulesets/phases/http_request_firewall_custom/entrypoint`)
- [x] Cache Purge — purge all, by files, tags, hosts (`zones/{id}/purge_cache`)
- [x] Cache Rules (Rulesets API) — CRUD (`zones/{id}/rulesets/phases/http_request_cache_settings/...`)
- [x] Page Rules — CRUD (`zones/{id}/pagerules`)
- [x] Analytics (GraphQL) — zone analytics, captcha, rule activity, DMARC
- [x] Access Applications — create, read, delete (`accounts/{id}/access/apps`)
- [x] Access Groups — read (`accounts/{id}/access/groups`)
- [x] Access Identity Providers — read (`accounts/{id}/access/identity_providers`)
- [x] Edge Caching — guest page caching & media caching (built on Cache Rules)

### Not Supported

- [ ] DNS Records management
- [ ] SSL/TLS Certificate management
- [ ] Workers / Workers Routes
- [ ] Load Balancing
- [ ] Rate Limiting (rulesets API)
- [ ] WAF Managed Rulesets
- [ ] Spectrum
- [ ] Argo Smart Routing
- [ ] R2 Storage
- [ ] D1 Database
- [ ] Pages (deployment)
- [ ] Tunnel management
- [ ] Email Routing
- [ ] Waiting Room
- [ ] Bot Management

## API Usage

The package exposes a `Cloudflare` facade with lazy-loaded service accessors. Each service corresponds to a Cloudflare API area.

```php
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
```

### Zone

```php
// List all zones on the account
$zones = Cloudflare::zone()->listZones();

// Get current zone settings
$settings = Cloudflare::zone()->getZoneSettings();

// Update a single setting
Cloudflare::zone()->updateZoneSetting('ssl', 'full');

// Batch update multiple settings
Cloudflare::zone()->updateZoneSettings([
    'ssl' => 'full',
    'security_level' => 'medium',
]);
```

### Cache

```php
// Purge everything
Cloudflare::cache()->purgeCache(purgeEverything: true);

// Purge specific files
Cloudflare::cache()->purgeCache(
    purgeEverything: false,
    files: ['https://example.com/style.css', 'https://example.com/app.js'],
);

// Purge by tags or hosts
Cloudflare::cache()->purgeCache(
    purgeEverything: false,
    tags: ['static-assets'],
    hosts: ['cdn.example.com'],
);
```

### Firewall

```php
use notwonderful\FilamentCloudflare\Enums\FirewallMode;

// List access rules
$rules = Cloudflare::firewall()->getFirewallAccessRules(page: 1, perPage: 50);

// Block an IP
Cloudflare::firewall()->createFirewallAccessRule(
    mode: FirewallMode::Block,
    configuration: ['target' => 'ip', 'value' => '203.0.113.1'],
    notes: 'Suspicious activity',
);

// Delete a rule
Cloudflare::firewall()->deleteFirewallAccessRule('rule-id');

// User agent rules (returns CloudflarePaginatedResult)
$result = Cloudflare::firewall()->getFirewallUserAgentRules();
$result->totalCount();   // total items
$result->totalPages();   // total pages
$result->isEmpty();      // bool

// Create a user agent rule
Cloudflare::firewall()->createFirewallUserAgentRule(
    userAgent: 'BadBot/1.0',
    mode: FirewallMode::Block,
    description: 'Block bad bot',
);
```

### Cache Rules

```php
// List cache rules (Rulesets API)
$ruleset = Cloudflare::cacheRules()->getCacheRules();

// Create a cache rule
Cloudflare::cacheRules()->createCacheRule(
    description: 'Cache API responses',
    expression: '(http.request.uri.path matches "^/api/public/")',
    actionParameters: [
        'cache' => true,
        'edge_ttl' => ['default' => 3600, 'mode' => 'override_origin'],
    ],
);

// Update / delete
Cloudflare::cacheRules()->updateCacheRule($rulesetId, $ruleId, ...);
Cloudflare::cacheRules()->deleteCacheRule($rulesetId, $ruleId);
```

### Page Rules

```php
use notwonderful\FilamentCloudflare\DataTransferObjects\PageRuleData;
use notwonderful\FilamentCloudflare\Enums\PageRuleStatus;

// List page rules
$rules = Cloudflare::pageRules()->getPageRules();

// Create via DTO
$dto = new PageRuleData(
    target: 'example.com/images/*',
    actions: [['id' => 'cache_level', 'value' => 'cache_everything']],
    priority: 1,
    status: PageRuleStatus::Active,
);
Cloudflare::pageRules()->createPageRule($dto);
```

### Analytics

Analytics data is fetched via the Cloudflare GraphQL API.

```php
// Zone analytics for the last 7 days
$data = Cloudflare::analytics()->getGraphQLAnalytics(days: 7);

// Captcha solve rate for a specific rule
$data = Cloudflare::analytics()->getGraphQLCaptchaSolveRate(ruleId: 'abc123', days: 30);

// Rule activity
$data = Cloudflare::analytics()->getGraphQLRuleActivity(ruleId: 'abc123', days: 7);
```

### Access

```php
// List access apps, groups, identity providers
$apps   = Cloudflare::access()->getAccessApps();
$groups = Cloudflare::access()->getAccessGroups();
$idps   = Cloudflare::access()->getAccessIdentityProviders();

// Create an access app to protect /admin
Cloudflare::access()->createAdminAccessApp(type: 'admin');

// Delete an access app
Cloudflare::access()->deleteAccessApp('app-id');
```

### Edge Caching

High-level helpers that create/remove cache rules under the hood.

```php
// Cache guest pages (no laravel_session cookie) for 1 hour
Cloudflare::edgeCaching()->enableGuestCache(seconds: 3600);
Cloudflare::edgeCaching()->isGuestCacheEnabled(); // true
Cloudflare::edgeCaching()->disableGuestCache();

// Cache media files in /storage for 1 day
Cloudflare::edgeCaching()->enableMediaCache(seconds: 86400);
Cloudflare::edgeCaching()->disableMediaCache();
```

## Configuration

Published to `config/cloudflare.php`:

```php
return [
    // Auth — .env takes priority, DB settings are fallback
    'email'      => env('CLOUDFLARE_EMAIL'),
    'api_key'    => env('CLOUDFLARE_API_KEY'),
    'token'      => env('CLOUDFLARE_TOKEN'),
    'zone_id'    => env('CLOUDFLARE_ZONE_ID'),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),

    // API response caching (0 = disabled)
    'cache' => [
        'ttl'    => (int) env('CLOUDFLARE_CACHE_TTL', 300), // seconds
        'prefix' => 'cloudflare',
    ],
];
```

The cache uses version-based invalidation — when data is mutated through the plugin, the relevant cache group is automatically invalidated. This works with all Laravel cache drivers.

## API Token Permissions

When creating a Cloudflare API Token, assign these permissions:

| Scope | Permission | Level |
|---|---|---|
| Zone | Zone Settings | Read, Edit |
| Zone | Zone | Read |
| Zone | Firewall Services | Edit |
| Zone | Cache Purge | Purge |
| Zone | Page Rules | Edit |
| Account | Access: Apps and Policies | Edit |
| Account | Account Settings | Read |

For read-only analytics, no additional permissions are needed beyond Zone Read (GraphQL API uses the same token).

## Architecture

```
Cloudflare (facade)
  -> Cloudflare (service locator, lazy init)
       -> CloudflareZoneService
       -> CloudflareCacheService
       -> CloudflareFirewallService
       -> CloudflareCacheRulesService
       -> CloudflarePageRulesService
       -> CloudflareAnalyticsService -> CloudflareGraphQLService
       -> CloudflareAccessService
       -> CloudflareEdgeCachingService -> CloudflareCacheRulesService
```

Key design decisions:

- **Contracts** — `CloudflareClientInterface`, `CloudflareSettingsInterface`, `CloudflareAuthInterface` for testability
- **Retry middleware** — `CloudflareClient` retries on 429 and 5xx with exponential backoff
- **CloudflareResponse** — wraps PSR-7 responses with `throwIfFailed()`, `getResult()`, `getResultInfo()`
- **CloudflarePaginatedResult** — DTO for paginated endpoints with `totalPages()`, `totalCount()`, `isEmpty()`
- **DTOs and Enums** — `FirewallRuleData`, `PageRuleData`, `ZoneSettingsData`, plus 8 enums for type safety
- **Typed exceptions** — `CloudflareApiException`, `CloudflareConfigurationException`, `CloudflareRequestException`
- **Version-based cache** — `CloudflareBaseService::remember()` with cache key versioning; `invalidateCache()` bumps version

## Cloudflare API Deprecation Notes

Some endpoints used by this plugin are deprecated by Cloudflare:

| Feature | Endpoint | Status |
|---|---|---|
| Firewall Access Rules | `firewall/access_rules/rules` | Functional, but Cloudflare recommends WAF Custom Rules |
| User Agent Rules | `firewall/ua_rules` | Deprecated in favor of Custom Rules with `http.user_agent` |
| Page Rules | `pagerules` | Deprecated, being replaced by Redirect Rules / Cache Rules |

These features continue to work. Future versions of this plugin may migrate to their replacements.

## Testing

The package has 115 tests with 186 assertions covering all services, the HTTP client, response handling, authentication, and settings.

```bash
# Run tests
vendor/bin/phpunit

# Run static analysis (Larastan level 8)
vendor/bin/phpstan analyse
```

## CI

GitHub Actions runs on every push and PR:

- PHPUnit on PHP 8.3 and 8.4
- Larastan level 8 (zero errors)

## License

MIT. See [LICENSE.md](LICENSE.md).

## Credits

Inspired by the [DigitalPoint App for Cloudflare](https://xenforo.com/community/resources/digitalpoint-app-for-cloudflare-r.8750/) for XenForo.
