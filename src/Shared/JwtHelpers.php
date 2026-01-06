<?php

namespace Stytch\Shared;

use Firebase\JWT\JWT;
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
            throw new StytchException('jwt_invalid', 'Invalid JWT format');
        }

        $headb64 = $tks[0];
        $headerJson = JWT::urlsafeB64Decode($headb64);
        $header = json_decode($headerJson, true);

        if (!isset($header['kid'])) {
            throw new StytchException('jwt_invalid', 'JWT header missing kid');
        }

        $kid = $header['kid'];

        // Find matching key in JWKS
        if (!isset($jwks[$kid])) {
            throw new StytchException('jwt_invalid', 'No matching key found in JWKS');
        }

        $jwk = $jwks[$kid];

        // Convert JWK to PEM format for firebase/php-jwt
        $publicKey = self::jwkToPem($jwk);

        // Set leeway for clock tolerance
        JWT::$leeway = $clockTolerance;

        // Decode and validate JWT
        try {
            $payload = JWT::decode($jwt, new Key($publicKey, $jwk['alg'] ?? 'RS256'));
        } catch (\Exception $e) {
            throw new StytchException('jwt_invalid', 'Could not verify JWT: ' . $e->getMessage(), $e);
        } finally {
            // Reset leeway
            JWT::$leeway = 0;
        }

        // Convert payload to array
        $payload = (array) $payload;

        // Validate issuer
        $issuer = $payload['iss'] ?? null;
        if (!$issuer || !self::validateIssuer($issuer, $projectId)) {
            throw new StytchException('jwt_invalid', 'Invalid issuer');
        }

        // Validate audience
        $audience = $payload['aud'] ?? null;
        if ($audience !== $projectId) {
            throw new StytchException('jwt_invalid', 'Invalid audience');
        }

        // Check max token age if specified
        if ($maxTokenAge !== null) {
            $iat = $payload['iat'] ?? null;
            if (!$iat) {
                throw new StytchException('jwt_invalid', 'JWT was missing iat claim');
            }

            $now = $currentDate->getTimestamp();
            if ($now - $iat >= $maxTokenAge) {
                throw new StytchException(
                    'jwt_too_old',
                    "JWT was issued at {$iat}, more than {$maxTokenAge} seconds ago"
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
            throw new StytchException('jwt_invalid', 'JWT missing session claim');
        }

        // Convert to array if it's an object
        if (is_object($sessionClaim)) {
            $sessionClaim = (array) $sessionClaim;
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
     * - Secondary: Custom CNAME issuers (if configured)
     *
     * @param string $issuer Issuer from JWT
     * @param string $projectId Project ID
     * @return bool True if issuer is valid
     */
    public static function validateIssuer(string $issuer, string $projectId): bool
    {
        // Primary issuer format
        if ($issuer === "stytch.com/{$projectId}") {
            return true;
        }

        // TODO: Add support for custom CNAME issuers if needed
        // For now, only support the primary format

        return false;
    }

    /**
     * Convert JWK to PEM format
     *
     * firebase/php-jwt requires PEM format public keys
     *
     * @param array $jwk JSON Web Key
     * @return string PEM formatted public key
     * @throws StytchException If JWK is invalid
     */
    private static function jwkToPem(array $jwk): string
    {
        if (!isset($jwk['kty']) || $jwk['kty'] !== 'RSA') {
            throw new StytchException('jwt_invalid', 'Only RSA keys are supported');
        }

        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            throw new StytchException('jwt_invalid', 'Invalid RSA key: missing n or e');
        }

        // Decode base64url-encoded values
        $n = self::base64UrlDecode($jwk['n']);
        $e = self::base64UrlDecode($jwk['e']);

        // Build RSA public key structure
        $modulus = self::encodeLength(strlen($n)) . $n;
        $exponent = self::encodeLength(strlen($e)) . $e;
        $sequence = "\x30" . self::encodeLength(strlen($modulus) + strlen($exponent)) . $modulus . $exponent;

        // RSA public key algorithm identifier
        $rsaOID = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitString = "\x03" . self::encodeLength(strlen($sequence) + 1) . "\x00" . $sequence;
        $publicKey = "\x30" . self::encodeLength(strlen($rsaOID) . strlen($bitString)) . $rsaOID . $bitString;

        // Convert to PEM format
        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($publicKey), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * Base64url decode
     *
     * @param string $data Base64url encoded string
     * @return string Decoded binary data
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Encode ASN.1 DER length field
     *
     * @param int $length Length to encode
     * @return string Encoded length
     */
    private static function encodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $lengthBytes = '';
        while ($length > 0) {
            $lengthBytes = chr($length & 0xff) . $lengthBytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($lengthBytes)) . $lengthBytes;
    }
}
