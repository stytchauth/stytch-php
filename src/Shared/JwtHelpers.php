<?php

namespace Stytch\Shared;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Stytch\Core\StytchException;

/**
 * JWT validation helpers for local JWT authentication
 *
 * Mirrors the logic from stytch-node/lib/shared/sessions.ts
 */
class JwtHelpers
{
    private const SESSION_CLAIM = 'https://stytch.com/session';
    private const ORGANIZATION_CLAIM = 'https://stytch.com/organization';

    // Standard JWT claims that should not be included in custom claims
    private const RESERVED_CLAIMS = ['aud', 'exp', 'iat', 'iss', 'nbf', 'jti', 'sub'];

    /**
     * Authenticate a JWT locally using JWKS
     *
     * @param array $jwks JWKS data indexed by 'kid'
     * @param string $jwt JWT string to validate
     * @param string $projectId Stytch project ID
     * @param array $options Validation options:
     *   - clock_tolerance_seconds: Tolerance for timestamp validation (default: 0)
     *   - max_token_age_seconds: Maximum age of token in seconds (default: null)
     *   - current_date: Current date for timestamp comparison (default: now)
     * @return array Associative array with 'payload' and 'custom_claims'
     * @throws StytchException If validation fails
     */
    public static function authenticateJwtLocal(
        array $jwks,
        string $jwt,
        string $projectId,
        array $options = []
    ): array {
        $clockTolerance = $options['clock_tolerance_seconds'] ?? 0;
        $maxTokenAge = $options['max_token_age_seconds'] ?? null;
        $currentDate = $options['current_date'] ?? new \DateTimeImmutable();

        // Decode JWT header to get 'kid' (key ID)
        $tks = explode('.', $jwt);
        if (count($tks) !== 3) {
            throw new StytchException('Invalid JWT format', 0);
        }

        $headb64 = $tks[0];
        $headerJson = JWT::urlsafeB64Decode($headb64);
        $header = json_decode($headerJson, true);

        if (!isset($header['kid'])) {
            throw new StytchException('JWT header missing kid', 0);
        }

        $kid = $header['kid'];

        // Find matching key in JWKS
        if (!isset($jwks[$kid])) {
            throw new StytchException('No matching key found in JWKS', 0);
        }

        $jwk = $jwks[$kid];

        // Convert JWK to Key object using firebase/php-jwt's JWK parser
        try {
            $key = JWK::parseKey($jwk);
        } catch (\Exception $e) {
            throw new StytchException('Failed to parse JWK: ' . $e->getMessage(), 0, null, $e);
        }

        // Set leeway for clock tolerance
        JWT::$leeway = $clockTolerance;

        // Decode and validate JWT
        try {
            $payload = JWT::decode($jwt, $key);
        } catch (\Exception $e) {
            throw new StytchException('Could not verify JWT: ' . $e->getMessage(), 0, null, $e);
        } finally {
            // Reset leeway
            JWT::$leeway = 0;
        }

        // Convert payload to array
        $payload = (array) $payload;

        // Validate issuer
        $issuer = $payload['iss'] ?? null;
        if (!$issuer || !self::validateIssuer($issuer, $projectId)) {
            throw new StytchException('Invalid issuer', 0);
        }

        // Validate audience
        // The 'aud' claim can be either a string or an array of strings per JWT spec
        $audience = $payload['aud'] ?? null;
        $validAudience = false;

        if (is_string($audience)) {
            $validAudience = ($audience === $projectId);
        } elseif (is_array($audience)) {
            $validAudience = in_array($projectId, $audience, true);
        }

        if (!$validAudience) {
            throw new StytchException('Invalid audience', 0);
        }

        // Check max token age if specified
        if ($maxTokenAge !== null) {
            $iat = $payload['iat'] ?? null;
            if (!$iat) {
                throw new StytchException('JWT was missing iat claim', 0);
            }

            $now = $currentDate->getTimestamp();
            if ($now - $iat >= $maxTokenAge) {
                throw new StytchException(
                    "JWT was issued at {$iat}, more than {$maxTokenAge} seconds ago",
                    0
                );
            }
        }

        // Extract custom claims (exclude standard JWT claims and Stytch claims)
        $customClaims = self::extractCustomClaims($payload);

        return [
            'payload' => $payload,
            'custom_claims' => $customClaims,
        ];
    }

    /**
     * Authenticate a session JWT locally
     *
     * Calls authenticateJwtLocal() and extracts session-specific data
     *
     * @param array $jwks JWKS data indexed by 'kid'
     * @param string $jwt JWT string to validate
     * @param string $projectId Stytch project ID
     * @param array $options Validation options (same as authenticateJwtLocal)
     * @return array Intermediate session data with session_id, sub, attributes, etc.
     * @throws StytchException If validation fails
     */
    public static function authenticateSessionJwtLocal(
        array $jwks,
        string $jwt,
        string $projectId,
        array $options = []
    ): array {
        $result = self::authenticateJwtLocal($jwks, $jwt, $projectId, $options);
        $payload = $result['payload'];
        $customClaims = $result['custom_claims'];

        // Extract Stytch session claim
        $sessionClaim = $payload[self::SESSION_CLAIM] ?? null;
        if (!$sessionClaim) {
            throw new StytchException('JWT missing session claim', 0);
        }

        // Convert to array recursively if it's an object
        if (is_object($sessionClaim)) {
            $sessionClaim = json_decode(json_encode($sessionClaim), true);
        }

        // Return intermediate session format
        return [
            'sub' => $payload['sub'] ?? '',
            'session_id' => $sessionClaim['id'] ?? '',
            'started_at' => $sessionClaim['started_at'] ?? '',
            'last_accessed_at' => $sessionClaim['last_accessed_at'] ?? '',
            'expires_at' => $sessionClaim['expires_at'] ?? '',
            'attributes' => $sessionClaim['attributes'] ?? [],
            'authentication_factors' => $sessionClaim['authentication_factors'] ?? [],
            'roles' => $sessionClaim['roles'] ?? [],
            'custom_claims' => $customClaims,
        ];
    }

    /**
     * Extract custom claims from JWT payload
     *
     * Filters out standard JWT claims and Stytch-specific claims
     *
     * @param array $payload JWT payload
     * @return array Custom claims
     */
    public static function extractCustomClaims(array $payload): array
    {
        $customClaims = [];

        foreach ($payload as $key => $value) {
            // Skip reserved claims
            if (in_array($key, self::RESERVED_CLAIMS, true)) {
                continue;
            }

            // Skip Stytch claims
            if ($key === self::SESSION_CLAIM || $key === self::ORGANIZATION_CLAIM) {
                continue;
            }

            $customClaims[$key] = $value;
        }

        return $customClaims;
    }

    /**
     * Validate JWT issuer against project ID
     *
     * Supports multiple issuer formats for backwards compatibility:
     * - Primary: stytch.com/{project_id}
     * - Legacy: stytch.com/project-test-{uuid}
     * - Custom CNAME issuers are validated if they contain the project_id
     *
     * @param string $issuer Issuer from JWT
     * @param string $projectId Project ID
     * @return bool True if issuer is valid
     */
    public static function validateIssuer(string $issuer, string $projectId): bool
    {
        // Primary issuer format: stytch.com/{project_id}
        if ($issuer === "stytch.com/{$projectId}") {
            return true;
        }

        // Legacy issuer format: stytch.com/project-test-{uuid}
        // Check if issuer contains the project_id
        if (str_starts_with($issuer, 'stytch.com/') && str_contains($issuer, $projectId)) {
            return true;
        }

        // Custom CNAME issuer: any domain that contains the project_id
        // This handles cases like custom-domain.com/{project_id}
        if (str_contains($issuer, $projectId)) {
            return true;
        }

        return false;
    }
}
