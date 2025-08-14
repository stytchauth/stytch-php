<?php

namespace Stytch\Shared;

/**
 * Policy cache for RBAC (Role-Based Access Control) functionality
 */
class PolicyCache
{
    private array $policies = [];
    private int $ttl = 300; // 5 minutes default TTL

    public function __construct(int $ttl = 300)
    {
        $this->ttl = $ttl;
    }

    /**
     * Get a cached policy
     * 
     * @param string $key Policy cache key
     * @return array|null Cached policy data or null if not found/expired
     */
    public function get(string $key): ?array
    {
        if (!isset($this->policies[$key])) {
            return null;
        }

        $cached = $this->policies[$key];
        
        // Check if expired
        if (time() > $cached['expires_at']) {
            unset($this->policies[$key]);
            return null;
        }

        return $cached['policy'];
    }

    /**
     * Store a policy in cache
     * 
     * @param string $key Policy cache key
     * @param array $policy Policy data
     */
    public function set(string $key, array $policy): void
    {
        $this->policies[$key] = [
            'policy' => $policy,
            'expires_at' => time() + $this->ttl,
        ];
    }

    /**
     * Clear a specific policy from cache
     * 
     * @param string $key Policy cache key
     */
    public function clear(string $key): void
    {
        unset($this->policies[$key]);
    }

    /**
     * Clear all policies from cache
     */
    public function clearAll(): void
    {
        $this->policies = [];
    }

    /**
     * Generate cache key for organization and member
     * 
     * @param string $organizationId Organization ID
     * @param string $memberId Member ID
     * @return string Cache key
     */
    public static function generateKey(string $organizationId, string $memberId): string
    {
        return "policy:{$organizationId}:{$memberId}";
    }

    /**
     * Check if user has permission for a resource
     * 
     * @param array $policy User's policy
     * @param string $resource Resource identifier
     * @param string $action Action to check (e.g., 'read', 'write')
     * @return bool True if user has permission
     */
    public function hasPermission(array $policy, string $resource, string $action): bool
    {
        // Simple permission checking logic
        // In a real implementation, this would be more sophisticated
        $permissions = $policy['permissions'] ?? [];
        
        foreach ($permissions as $permission) {
            if ($permission['resource'] === $resource && 
                in_array($action, $permission['actions'] ?? [])) {
                return true;
            }
        }

        return false;
    }
}