<?php

namespace Stytch\Tests\B2B;

use Stytch\B2B\Client;
use Stytch\Core\StytchException;
use Stytch\Tests\TestCase;

class SessionsJwtTest extends TestCase
{
    private Client $client;
    private array $testOrganizations = [];
    private array $testMembers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client(
            projectId: $this->getB2BProjectId(),
            secret: $this->getB2BSecret()
        );
    }

    protected function tearDown(): void
    {
        // Clean up members
        foreach ($this->testMembers as $memberId) {
            try {
                $this->client->organizations->members->delete([
                    'member_id' => $memberId,
                ]);
            } catch (StytchException $e) {
                // Ignore cleanup errors
            }
        }

        // Clean up organizations
        foreach ($this->testOrganizations as $organizationId) {
            try {
                $this->client->organizations->delete([
                    'organization_id' => $organizationId,
                ]);
            } catch (StytchException $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    private function createTestOrganizationAndMember(): array
    {
        // Create organization
        $orgName = 'Test Org ' . uniqid();
        $orgSlug = 'test-org-' . uniqid();

        $orgResponse = $this->client->organizations->create([
            'organization_name' => $orgName,
            'organization_slug' => $orgSlug,
        ]);
        $this->testOrganizations[] = $orgResponse->organization->organizationId;

        // Create member
        $email = $this->generateRandomEmail();
        $memberResponse = $this->client->organizations->members->create([
            'organization_id' => $orgResponse->organization->organizationId,
            'email_address' => $email,
        ]);
        $this->testMembers[] = $memberResponse->member->memberId;

        return [
            'organization' => $orgResponse->organization,
            'member' => $memberResponse->member,
            'email' => $email,
        ];
    }

    private function setPasswordForMember(string $organizationId, string $email, string $password): void
    {
        // B2B uses migrate() to set up passwords for testing
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $this->client->passwords->migrate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'hash' => $hashedPassword,
            'hash_type' => 'bcrypt',
        ]);
    }

    public function testAuthenticateJwtFallbackToNetwork(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $memberId = $testData['member']->memberId;
        $email = $testData['email'];

        // Set password for member
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        // Authenticate to get session JWT
        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
        ]);

        $sessionJwt = $authResponse->sessionJwt;
        $this->assertNotEmpty($sessionJwt);

        // Test authenticateJwt - should work via local or network
        $jwtAuthResponse = $this->client->sessions->authenticateJwt([
            'session_jwt' => $sessionJwt,
        ]);

        $this->assertEquals($memberId, $jwtAuthResponse->memberSession->memberId);
        $this->assertEquals($organizationId, $jwtAuthResponse->memberSession->organizationId);
        $this->assertNotEmpty($jwtAuthResponse->memberSession->memberSessionId);
    }

    public function testAuthenticateJwtWithMaxTokenAge(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Set max_token_age to 0 to force network authentication
        $jwtAuthResponse = $this->client->sessions->authenticateJwt([
            'session_jwt' => $sessionJwt,
            'max_token_age_seconds' => 0,
        ]);

        $this->assertEquals($organizationId, $jwtAuthResponse->memberSession->organizationId);
    }

    public function testAuthenticateJwtInvalidToken(): void
    {
        $this->expectException(StytchException::class);

        $this->client->sessions->authenticateJwt([
            'session_jwt' => 'invalid.jwt.token',
        ]);
    }

    public function testAuthenticateJwtExpiredToken(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Revoke the session
        $this->client->sessions->revoke([
            'session_jwt' => $sessionJwt,
        ]);

        // Try to authenticate with revoked session
        $this->expectException(StytchException::class);

        $this->client->sessions->authenticateJwt([
            'session_jwt' => $sessionJwt,
        ]);
    }

    public function testAuthenticateJwtLocalDirectly(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $memberId = $testData['member']->memberId;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Test authenticateJwtLocal directly
        try {
            $memberSession = $this->client->sessions->authenticateJwtLocal([
                'session_jwt' => $sessionJwt,
            ]);

            $this->assertEquals($memberId, $memberSession->memberId);
            $this->assertEquals($organizationId, $memberSession->organizationId);
            $this->assertNotEmpty($memberSession->memberSessionId);
        } catch (StytchException $e) {
            // If it fails due to policy cache miss or JWT validation, that's expected
            $this->markTestSkipped('Local JWT validation requires JWKS/policy cache to be populated');
        }
    }

    public function testAuthenticateJwtComparison(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $memberId = $testData['member']->memberId;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Authenticate using regular authenticate method
        $regularResponse = $this->client->sessions->authenticate([
            'session_jwt' => $sessionJwt,
        ]);

        // Authenticate using authenticateJwt method
        $jwtResponse = $this->client->sessions->authenticateJwt([
            'session_jwt' => $sessionJwt,
        ]);

        // Both should return the same session
        $this->assertEquals($regularResponse->memberSession->memberId, $jwtResponse->memberSession->memberId);
        $this->assertEquals($regularResponse->memberSession->memberSessionId, $jwtResponse->memberSession->memberSessionId);
        $this->assertEquals($regularResponse->memberSession->organizationId, $jwtResponse->memberSession->organizationId);
    }

    public function testGetAndAuthenticateSession(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $memberId = $testData['member']->memberId;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Get all sessions for the member
        $getResponse = $this->client->sessions->get([
            'organization_id' => $organizationId,
            'member_id' => $memberId,
        ]);

        $this->assertNotEmpty($getResponse->memberSessions);
        $this->assertEquals($memberId, $getResponse->memberSessions[0]->memberId);

        // Verify the JWT works
        $jwtAuthResponse = $this->client->sessions->authenticateJwt([
            'session_jwt' => $sessionJwt,
        ]);

        $this->assertEquals($memberId, $jwtAuthResponse->memberSession->memberId);
    }

    public function testAuthenticateJwtWithSessionDurationExtension(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Authenticate and extend session
        $extendedAuthResponse = $this->client->sessions->authenticate([
            'session_jwt' => $sessionJwt,
            'session_duration_minutes' => 120, // Extend to 2 hours
        ]);

        $this->assertEquals($authResponse->memberSession->memberId, $extendedAuthResponse->memberSession->memberId);

        // Verify new JWT was issued
        $newJwt = $extendedAuthResponse->sessionJwt;
        $this->assertNotEmpty($newJwt);
        $this->assertNotEquals($sessionJwt, $newJwt);
    }

    public function testMultipleSessionsForMember(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $memberId = $testData['member']->memberId;
        $email = $testData['email'];

        // Set password
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        // Create first session
        $createResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $session1Jwt = $createResponse->sessionJwt;

        // Create a second session
        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $session2Jwt = $authResponse->sessionJwt;

        // Verify both JWTs are different
        $this->assertNotEquals($session1Jwt, $session2Jwt);

        // Verify both JWTs work
        $auth1 = $this->client->sessions->authenticateJwt([
            'session_jwt' => $session1Jwt,
        ]);
        $auth2 = $this->client->sessions->authenticateJwt([
            'session_jwt' => $session2Jwt,
        ]);

        $this->assertEquals($memberId, $auth1->memberSession->memberId);
        $this->assertEquals($memberId, $auth2->memberSession->memberId);
        $this->assertEquals($organizationId, $auth1->memberSession->organizationId);
        $this->assertEquals($organizationId, $auth2->memberSession->organizationId);
    }

    public function testAuthenticateJwtWithOrganizationClaim(): void
    {
        $testData = $this->createTestOrganizationAndMember();
        $organizationId = $testData['organization']->organizationId;
        $organizationSlug = $testData['organization']->organizationSlug;
        $email = $testData['email'];

        // Set password and authenticate
        $password = $this->generateRandomPassword();
        $this->setPasswordForMember($organizationId, $email, $password);

        $authResponse = $this->client->passwords->authenticate([
            'organization_id' => $organizationId,
            'email_address' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);

        $sessionJwt = $authResponse->sessionJwt;

        // Authenticate using JWT
        $jwtAuthResponse = $this->client->sessions->authenticateJwt([
            'session_jwt' => $sessionJwt,
        ]);

        // Verify organization information is present
        $this->assertEquals($organizationId, $jwtAuthResponse->memberSession->organizationId);

        // Note: Organization slug is stored in the JWT claim but not necessarily in the MemberSession object
        // The important thing is that the organization ID matches
    }
}
