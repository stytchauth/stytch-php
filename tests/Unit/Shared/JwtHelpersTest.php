<?php

namespace Stytch\Tests\Unit\Shared;

use Firebase\JWT\JWT;
use Stytch\Core\StytchException;
use Stytch\Shared\JwtHelpers;
use Stytch\Tests\TestCase;

class JwtHelpersTest extends TestCase
{
    private array $testJwks;
    private string $privateKey = '';
    private string $publicKey = '';
    private string $projectId = 'project-test-12345';

    protected function setUp(): void
    {
        parent::setUp();

        // Generate RSA key pair for testing
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKeyOut = '';
        openssl_pkey_export($keyPair, $privateKeyOut);
        $this->privateKey = $privateKeyOut;

        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        $this->publicKey = $publicKeyDetails['key'];

        // Create test JWKS from the public key
        $this->testJwks = [
            'test-key-id' => $this->createJwkFromPublicKey($publicKeyDetails),
        ];
    }

    private function createJwkFromPublicKey(array $keyDetails): array
    {
        return [
            'kty' => 'RSA',
            'kid' => 'test-key-id',
            'alg' => 'RS256',
            'n' => rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '='),
        ];
    }

    private function createTestJwt(array $payload, string $keyId = 'test-key-id'): string
    {
        return JWT::encode($payload, $this->privateKey, 'RS256', $keyId);
    }

    public function testAuthenticateJwtLocalSuccess(): void
    {
        $now = time();
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => $now,
            'exp' => $now + 300,
            'custom_claim' => 'custom_value',
        ];

        $jwt = $this->createTestJwt($payload);

        $result = JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );

        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('custom_claims', $result);
        $this->assertEquals($this->projectId, $result['payload']['aud']);
        $this->assertEquals('user-test-12345', $result['payload']['sub']);
        $this->assertEquals('custom_value', $result['custom_claims']['custom_claim']);
    }

    public function testAuthenticateJwtLocalInvalidSignature(): void
    {
        // Create JWT with wrong key
        $wrongKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $wrongPrivateKey = '';
        openssl_pkey_export($wrongKey, $wrongPrivateKey);

        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => time(),
            'exp' => time() + 300,
        ];

        $jwt = JWT::encode($payload, $wrongPrivateKey, 'RS256', 'test-key-id');

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('Could not verify JWT');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testAuthenticateJwtLocalExpired(): void
    {
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => time() - 600,
            'exp' => time() - 300, // Expired 5 minutes ago
        ];

        $jwt = $this->createTestJwt($payload);

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('Expired token');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testAuthenticateJwtLocalInvalidIssuer(): void
    {
        $payload = [
            'iss' => 'invalid-issuer.com',
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => time(),
            'exp' => time() + 300,
        ];

        $jwt = $this->createTestJwt($payload);

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('Invalid issuer');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testAuthenticateJwtLocalInvalidAudience(): void
    {
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => 'wrong-project-id',
            'sub' => 'user-test-12345',
            'iat' => time(),
            'exp' => time() + 300,
        ];

        $jwt = $this->createTestJwt($payload);

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('Invalid audience');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testAuthenticateJwtLocalMissingKid(): void
    {
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => time(),
            'exp' => time() + 300,
        ];

        // Create JWT without kid
        $jwt = JWT::encode($payload, $this->privateKey, 'RS256');

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('JWT header missing kid');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testAuthenticateJwtLocalKeyNotFoundInJwks(): void
    {
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => time(),
            'exp' => time() + 300,
        ];

        $jwt = $this->createTestJwt($payload, 'non-existent-key-id');

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('No matching key found in JWKS');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testAuthenticateJwtLocalMaxTokenAge(): void
    {
        $now = time();
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => $now - 400, // Issued 400 seconds ago
            'exp' => $now + 300,
        ];

        $jwt = $this->createTestJwt($payload);

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('more than 300 seconds ago');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId,
            ['max_token_age_seconds' => 300]
        );
    }

    public function testAuthenticateJwtLocalClockTolerance(): void
    {
        $now = time();
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => $now,
            'exp' => $now - 10, // Expired 10 seconds ago
        ];

        $jwt = $this->createTestJwt($payload);

        // Should succeed with 15 second tolerance
        $result = JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId,
            ['clock_tolerance_seconds' => 15]
        );

        $this->assertArrayHasKey('payload', $result);
    }

    public function testAuthenticateSessionJwtLocal(): void
    {
        $now = time();
        $sessionClaim = [
            'id' => 'session-test-12345',
            'started_at' => date('c', $now - 3600),
            'last_accessed_at' => date('c', $now),
            'expires_at' => date('c', $now + 300),
            'attributes' => ['ip_address' => '127.0.0.1'],
            'authentication_factors' => [['type' => 'password']],
            'roles' => ['admin', 'user'],
        ];

        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => $now,
            'exp' => $now + 300,
            'https://stytch.com/session' => $sessionClaim,
            'custom_claim' => 'custom_value',
        ];

        $jwt = $this->createTestJwt($payload);

        $result = JwtHelpers::authenticateSessionJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );

        $this->assertEquals('user-test-12345', $result['sub']);
        $this->assertEquals('session-test-12345', $result['session_id']);
        $this->assertArrayHasKey('started_at', $result);
        $this->assertArrayHasKey('last_accessed_at', $result);
        $this->assertArrayHasKey('expires_at', $result);
        // Attributes come as arrays from JWT decoding
        $this->assertIsArray($result['attributes']);
        $this->assertEquals('127.0.0.1', $result['attributes']['ip_address']);
        $this->assertEquals(['admin', 'user'], $result['roles']);
        $this->assertEquals('custom_value', $result['custom_claims']['custom_claim']);
    }

    public function testAuthenticateSessionJwtLocalMissingSessionClaim(): void
    {
        $now = time();
        $payload = [
            'iss' => "stytch.com/{$this->projectId}",
            'aud' => $this->projectId,
            'sub' => 'user-test-12345',
            'iat' => $now,
            'exp' => $now + 300,
        ];

        $jwt = $this->createTestJwt($payload);

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('JWT missing session claim');

        JwtHelpers::authenticateSessionJwtLocal(
            $this->testJwks,
            $jwt,
            $this->projectId
        );
    }

    public function testExtractCustomClaims(): void
    {
        $payload = [
            'aud' => 'project-id',
            'exp' => time() + 300,
            'iat' => time(),
            'iss' => 'stytch.com/project-id',
            'sub' => 'user-12345',
            'https://stytch.com/session' => ['id' => 'session-12345'],
            'custom_claim_1' => 'value1',
            'custom_claim_2' => 'value2',
        ];

        $customClaims = JwtHelpers::extractCustomClaims($payload);

        // Should not include standard JWT claims or Stytch claims
        $this->assertArrayNotHasKey('aud', $customClaims);
        $this->assertArrayNotHasKey('exp', $customClaims);
        $this->assertArrayNotHasKey('iat', $customClaims);
        $this->assertArrayNotHasKey('iss', $customClaims);
        $this->assertArrayNotHasKey('sub', $customClaims);
        $this->assertArrayNotHasKey('https://stytch.com/session', $customClaims);

        // Should include custom claims
        $this->assertEquals('value1', $customClaims['custom_claim_1']);
        $this->assertEquals('value2', $customClaims['custom_claim_2']);
    }

    public function testValidateIssuerPrimary(): void
    {
        $result = JwtHelpers::validateIssuer(
            "stytch.com/{$this->projectId}",
            $this->projectId
        );
        $this->assertTrue($result);
    }

    public function testValidateIssuerLegacy(): void
    {
        $result = JwtHelpers::validateIssuer(
            "stytch.com/project-test-{$this->projectId}-uuid",
            $this->projectId
        );
        $this->assertTrue($result);
    }

    public function testValidateIssuerCustomCname(): void
    {
        $result = JwtHelpers::validateIssuer(
            "custom-domain.com/{$this->projectId}",
            $this->projectId
        );
        $this->assertTrue($result);
    }

    public function testValidateIssuerInvalid(): void
    {
        $result = JwtHelpers::validateIssuer(
            'invalid-domain.com/wrong-project',
            $this->projectId
        );
        $this->assertFalse($result);
    }

    public function testAuthenticateJwtLocalInvalidFormat(): void
    {
        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('Invalid JWT format');

        JwtHelpers::authenticateJwtLocal(
            $this->testJwks,
            'invalid.jwt',
            $this->projectId
        );
    }
}
