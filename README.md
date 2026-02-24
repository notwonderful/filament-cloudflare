<div align="center">

# Filament Cloudflare

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue?style=for-the-badge&logo=php)
![Filament Version](https://img.shields.io/badge/Filament-4.0%20%7C%205.0-FF2D20?style=for-the-badge&logo=filament)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

Manage Cloudflare settings, firewall rules, cache, page rules, analytics, access apps, and edge caching directly from your Filament admin panel.

</div>

## Installation

### 1. Install via Composer

```bash
composer require notwonderful/filament-cloudflare
```

### 2. Publish the config

```bash
php artisan vendor:publish --tag=cloudflare-config
```

### 3. Set Cloudflare credentials

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

### 4. Register the plugin

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
| **DNS Records** | Full CRUD for A, AAAA, CNAME, MX, TXT, NS, SRV, CAA records. Proxy toggle, export BIND file |
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

- [x] DNS Records management — CRUD (`zones/{id}/dns_records`)
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

## API Token Permissions

When creating a Cloudflare API Token, assign these permissions:

| Scope | Permission | Level |
|---|---|---|
| Zone | DNS | Read, Edit |
| Zone | Zone Settings | Read, Edit |
| Zone | Zone | Read |
| Zone | Firewall Services | Edit |
| Zone | Cache Purge | Purge |
| Zone | Page Rules | Edit |
| Account | Access: Apps and Policies | Edit |
| Account | Account Settings | Read |

For read-only analytics, no additional permissions are needed beyond Zone Read (GraphQL API uses the same token).

## Cloudflare API Deprecation Notes

Some endpoints used by this plugin are deprecated by Cloudflare:

| Feature | Endpoint | Status |
|---|---|---|
| Firewall Access Rules | `firewall/access_rules/rules` | Functional, but Cloudflare recommends WAF Custom Rules |
| User Agent Rules | `firewall/ua_rules` | Deprecated in favor of Custom Rules with `http.user_agent` |
| Page Rules | `pagerules` | Deprecated, being replaced by Redirect Rules / Cache Rules |

These features continue to work. Future versions of this plugin may migrate to their replacements.

## Credits

Inspired by the [DigitalPoint App for Cloudflare](https://xenforo.com/community/resources/digitalpoint-app-for-cloudflare-r.8750/) for XenForo.
