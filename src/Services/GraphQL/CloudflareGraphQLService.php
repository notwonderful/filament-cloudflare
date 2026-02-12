<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\GraphQL;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareRequestException;

class CloudflareGraphQLService
{
    protected const string ZULU_TIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(
        protected readonly CloudflareClientInterface $client
    ) {}

    /**
     * Execute GraphQL query
     *
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function query(?string $operationName, string $query, array $variables = []): array
    {
        $graphqlClient = $this->client->forGraphQL();

        try {
            $json = match (true) {
                $operationName !== null => [
                    'operationName' => $operationName,
                    'query' => $query,
                    'variables' => $variables,
                ],
                default => [
                    'query' => $query,
                    'variables' => $variables,
                ],
            };

            $response = $graphqlClient->request('POST', '', ['json' => $json]);

            $body = json_decode($response->getBody()->getContents(), true);
            return $body['data'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('Cloudflare GraphQL Error', [
                'operation' => $operationName,
                'error' => $e->getMessage(),
            ]);

            throw new CloudflareRequestException(
                'Cloudflare GraphQL request failed: ' . $e->getMessage(),
                'POST',
                '/graphql',
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get zone analytics via GraphQL
     *
     * @return array<string, mixed>
     */
    public function getZoneAnalytics(
        string $zoneId,
        int $days = 1,
        bool $exactDate = false,
        ?\DateTimeInterface $dateFrom = null
    ): array {
        $datetimeStart = Carbon::instance($dateFrom ?? now())->subDays($days);
        $datetimeEnd = Carbon::instance($dateFrom ?? now())->subSecond();

        // Adjust for 1 day without exact date (use 1 hour ago for better data)
        [$groupName, $timeField, $dateFormat] = match (true) {
            $days === 1 && !$exactDate => ['httpRequests1hGroups', 'datetime', self::ZULU_TIME_FORMAT],
            default => ['httpRequests1dGroups', 'date', 'Y-m-d'],
        };

        $query = "query GetZoneAnalytics(\$zoneTag: string, \$since: string, \$until: string) {
            viewer {
                zones(filter: {zoneTag: \$zoneTag}) {
                    totals: {$groupName}(limit: 10000, filter: {{$timeField}_geq: \$since, {$timeField}_lt: \$until}) {
                        uniq {
                            uniques
                            __typename
                        }
                        __typename
                    }
                    zones: {$groupName}(orderBy: [{$timeField}_ASC], limit: 10000, filter: {{$timeField}_geq: \$since, {$timeField}_lt: \$until}) {
                        dimensions {
                            timeslot: {$timeField}
                            __typename
                        }
                        uniq {
                            uniques
                            __typename
                        }
                        sum {
                            browserMap {
                                pageViews
                                key: uaBrowserFamily
                                __typename
                            }
                            bytes
                            cachedBytes
                            cachedRequests
                            contentTypeMap {
                                bytes
                                requests
                                key: edgeResponseContentTypeName
                                __typename
                            }
                            clientSSLMap {
                                requests
                                key: clientSSLProtocol
                                __typename
                            }
                            countryMap {
                                bytes
                                requests
                                threats
                                key: clientCountryName
                                __typename
                            }
                            encryptedBytes
                            encryptedRequests
                            ipClassMap {
                                requests
                                key: ipType
                                __typename
                            }
                            pageViews
                            requests
                            responseStatusMap {
                                requests
                                key: edgeResponseStatus
                                __typename
                            }
                            threats
                            threatPathingMap {
                                requests
                                key: threatPathingName
                                __typename
                            }
                            __typename
                        }
                        __typename
                    }
                    __typename
                }
                __typename
            }
        }";

        return $this->query('GetZoneAnalytics', $query, [
            'zoneTag' => $zoneId,
            'since' => $datetimeStart->format($dateFormat),
            'until' => $datetimeEnd->format($dateFormat),
        ]);
    }

    /**
     * Get CAPTCHA solve rate
     *
     * @return array<string, mixed>
     */
    public function getCaptchaSolveRate(string $zoneId, string $ruleId, int $days = 1): array
    {
        $datetimeStart = now()->subDays($days);
        $datetimeEnd = now()->subSecond();

        $query = 'query GetCaptchaSolvedRate (
            $zoneTag: string
        ) {
            viewer {
                zones(filter: { zoneTag: $zoneTag }) {
                    issued: firewallEventsAdaptiveByTimeGroups(
                        limit: 1
                        filter: $issued_filter
                    ) {
                        count
                    }
                    solved: firewallEventsAdaptiveByTimeGroups(
                        limit: 1
                        filter: $solved_filter
                    ) {
                        count
                    }
                }
            }
        }';

        return $this->query('GetCaptchaSolvedRate', $query, [
            'zoneTag' => $zoneId,
            'issued_filter' => [
                'OR' => [
                    ['action' => 'jschallenge'],
                    ['action' => 'managed_challenge'],
                    ['action' => 'challenge'],
                ],
                'datetime_geq' => $datetimeStart->format(self::ZULU_TIME_FORMAT),
                'datetime_leq' => $datetimeEnd->format(self::ZULU_TIME_FORMAT),
                'ruleId' => $ruleId,
            ],
            'solved_filter' => [
                'OR' => [
                    ['action' => 'jschallenge_solved'],
                    ['action' => 'challenge_solved'],
                    ['action' => 'managed_challenge_non_interactive_solved'],
                    ['action' => 'managed_challenge_interactive_solved'],
                ],
                'datetime_geq' => $datetimeStart->format(self::ZULU_TIME_FORMAT),
                'datetime_leq' => $datetimeEnd->format(self::ZULU_TIME_FORMAT),
                'ruleId' => $ruleId,
            ],
        ]);
    }

    /**
     * Get rule activity query
     *
     * @return array<string, mixed>
     */
    public function getRuleActivity(string $zoneId, string $ruleId, int $days = 1): array
    {
        $datetimeStart = now()->subDays($days);
        $datetimeEnd = now()->subSecond();

        $query = 'query RuleActivityQuery (
            $zoneTag: string
        ) {
            viewer {
                zones(filter: { zoneTag: $zoneTag }) {
                    issued: firewallEventsAdaptiveByTimeGroups(
                        limit: 1
                        filter: $filter
                    ) {
                        count
                    }
                }
            }
        }';

        return $this->query('RuleActivityQuery', $query, [
            'zoneTag' => $zoneId,
            'filter' => [
                'AND' => [
                    ['action_neq' => 'challenge_solved'],
                    ['action_neq' => 'challenge_failed'],
                    ['action_neq' => 'challenge_bypassed'],
                    ['action_neq' => 'jschallenge_solved'],
                    ['action_neq' => 'jschallenge_failed'],
                    ['action_neq' => 'jschallenge_bypassed'],
                    ['action_neq' => 'managed_challenge_skipped'],
                    ['action_neq' => 'managed_challenge_non_interactive_solved'],
                    ['action_neq' => 'managed_challenge_interactive_solved'],
                    ['action_neq' => 'managed_challenge_bypassed'],
                ],
                'datetime_geq' => $datetimeStart->format(self::ZULU_TIME_FORMAT),
                'datetime_leq' => $datetimeEnd->format(self::ZULU_TIME_FORMAT),
                'ruleId' => $ruleId,
            ],
        ]);
    }

    /**
     * Get DMARC sources
     *
     * @param array<int, string> $approvedSources
     * @return array<string, mixed>
     */
    public function getDmarcSources(string $zoneId, array $approvedSources = [], int $days = 7): array
    {
        $currentDate = now();
        $dateFrom = (clone $currentDate)->subDays($days);

        $query = 'query {
            viewer {
                zones(filter: {zoneTag: $zoneTag}) {
                    dmarcReportsSourcesAdaptiveGroups(limit: 10000, filter: $filter, orderBy: [sum_totalMatchingMessages_DESC]) {
                        dimensions {
                            sourceOrgName
                            sourceOrgSlug
                            __typename
                        }
                        avg {
                            dmarc
                            dkimPass
                            spfPass
                            __typename
                        }
                        sum {
                            totalMatchingMessages
                            __typename
                        }
                        uniq {
                            ipCount
                            __typename
                        }
                        __typename
                    }
                    __typename
                }
                __typename
            }
        }';

        $variables = [
            'zoneTag' => $zoneId,
            'filter' => array_merge([
                'date_gt' => $dateFrom->format('Y-m-d'),
                'date_leq' => $currentDate->format('Y-m-d'),
            ], match (count($approvedSources) > 0) {
                true => ['AND' => [['sourceOrgSlug_notin' => $approvedSources]]],
                false => [],
            }),
        ];

        return $this->query(null, $query, $variables);
    }

    /**
     * Get DMARC analytics
     *
     * @return array<string, mixed>
     */
    public function getDmarcAnalytics(string $zoneId, int $days = 7): array
    {
        $currentDate = now();
        $dateFrom = (clone $currentDate)->subDays($days);

        $query = 'query {
            viewer {
                zones(filter: {zoneTag: $zoneTag}) {
                    dmarcReportsSourcesAdaptiveGroups(limit: 10000, filter: $filter, orderBy: [datetimeDay_DESC, sum_totalMatchingMessages_DESC]) {
                        dimensions {
                            datetimeDay
                            dkim
                            spf
                            __typename
                        }
                        sum {
                            totalMatchingMessages
                            __typename
                        }
                        __typename
                    }
                    __typename
                }
                __typename
            }
        }';

        return $this->query(null, $query, [
            'zoneTag' => $zoneId,
            'filter' => [
                'AND' => [
                    [
                        'date_geq' => $dateFrom->format('Y-m-d'),
                        'date_leq' => $currentDate->format('Y-m-d'),
                    ],
                ],
            ],
        ]);
    }
}
