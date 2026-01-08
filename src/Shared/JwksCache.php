<?php

namespace Stytch\Shared;

use Stytch\Core\Client;

/**
 * JWKS (JSON Web Key Set) cache for JWT signature verification
 *
 * Caches JWKS with a 5-minute TTL to handle key rotation while minimizing API calls
 */
class JwksCache
{
    private Client $client;
    private string $projectId;
    private array $jwksCache = [];
    private int $ttl = 300; // 5 minutes default TTL (matches JWT lifetime)

    public function __construct(Client $client, string $projectId, int $ttl = 300)
    {
        $this->client = $client;
        $this->projectId = $projectId;
        $this->ttl = $ttl;
    }

    /**
     * Get cached JWKS for a project
     *
     * @param string $projectId Project ID
     * @return array|null Cached JWKS data or null if not found/expired
     */
    public function get(string $projectId): ?array
    {
        if (!isset($this->jwksCache[$projectId])) {
            return null;
        }

        $cached = $this->jwksCache[$projectId];

        // Check if expired
        if (time() > $cached['expires_at']) {
            unset($this->jwksCache[$projectId]);
            return null;
        }

        return $cached['jwks'];
    }

    /**
     * Store JWKS in cache
     *
     * @param string $projectId Project ID
     * @param array $jwks JWKS data
     */
    public function set(string $projectId, array $jwks): void
    {
        $this->jwksCache[$projectId] = [
            'jwks' => $jwks,
            'expires_at' => time() + $this->ttl,
        ];
    }

    /**
     * Fetch JWKS from API and cache it
     *
     * If JWKS is already cached and not expired, returns cached version.
     * Otherwise, fetches from the API and caches it.
     *
     * @param string $projectId Project ID
     * @return array JWKS data with keys indexed by 'kid' (key ID)
     * @throws \Stytch\Core\StytchException If API call fails
     */
    public function fetch(string $projectId): array
    {
        // Check cache first
        $cached = $this->get($projectId);
        if ($cached !== null) {
            return $cached;
        }

        // Fetch from API
        $response = $this->client->get("/v1/sessions/jwks/{$projectId}");

        // Extract keys array from response
        $keys = $response['keys'] ?? [];

        // Index by 'kid' for O(1) lookup during validation
        $jwks = [];
        foreach ($keys as $key) {
            if (isset($key['kid'])) {
                $jwks[$key['kid']] = $key;
            }
        }

        // Cache it
        $this->set($projectId, $jwks);

        return $jwks;
    }

    /**
     * Clear cached JWKS for a specific project
     *
     * @param string $projectId Project ID
     */
    public function clear(string $projectId): void
    {
        unset($this->jwksCache[$projectId]);
    }

    /**
     * Clear all cached JWKS
     */
    public function clearAll(): void
    {
        $this->jwksCache = [];
    }
}
