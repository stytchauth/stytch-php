<?php

namespace Stytch\Tests\B2B;

use Stytch\B2B\Client;
use Stytch\B2B\Models\Organizations\CreateRequest;
use Stytch\B2B\Models\Organizations\DeleteRequest;
use Stytch\B2B\Models\Organizations\Members\CreateRequest as MemberCreateRequest;
use Stytch\B2B\Models\Organizations\Members\DeleteRequest as MemberDeleteRequest;
use Stytch\B2B\Models\Passwords\StrengthCheckRequest;
use Stytch\B2B\Models\Passwords\AuthenticateRequest;
use Stytch\B2B\Models\Passwords\MigrateRequest;
use Stytch\B2B\Models\Passwords\ExistingPassword\ResetRequest as ExistingPasswordResetRequest;
use Stytch\B2B\Models\Passwords\Sessions\ResetRequest as SessionResetRequest;
use Stytch\B2B\Models\Passwords\Discovery\AuthenticateRequest as DiscoveryAuthenticateRequest;
use Stytch\Core\StytchException;
use Stytch\Tests\TestCase;

class PasswordsTest extends TestCase
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
        // Clean up members first
        foreach ($this->testMembers as $memberData) {
            try {
                $deleteRequest = new MemberDeleteRequest(
                    $memberData['organization_id'],
                    $memberData['member_id']
                );
                $this->client->organizations->members->delete($deleteRequest);
            } catch (StytchException $e) {
                // Ignore cleanup errors
            }
        }

        // Then clean up organizations
        foreach ($this->testOrganizations as $organizationId) {
            try {
                $deleteRequest = new DeleteRequest($organizationId);
                $this->client->organizations->delete($deleteRequest);
            } catch (StytchException $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    private function createTestOrganizationAndMember(): array
    {
        // Create organization
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $orgCreateRequest = new CreateRequest($organizationName, $organizationSlug);
        $orgResponse = $this->client->organizations->create($orgCreateRequest);

        $this->testOrganizations[] = $orgResponse->organization->organizationId;

        // Create member
        $email = $this->generateRandomEmail();
        $memberCreateRequest = new MemberCreateRequest($orgResponse->organization->organizationId, $email);
        $memberResponse = $this->client->organizations->members->create($memberCreateRequest);

        $this->testMembers[] = [
            'organization_id' => $orgResponse->organization->organizationId,
            'member_id' => $memberResponse->memberId,
        ];

        return [
            'organization_id' => $orgResponse->organization->organizationId,
            'organization' => $orgResponse->organization,
            'member_id' => $memberResponse->memberId,
            'member' => $memberResponse->member,
            'email' => $email,
        ];
    }

    public function testMigratePassword(): void
    {
        $data = $this->createTestOrganizationAndMember();
        $password = $this->generateRandomPassword();

        // Use bcrypt to hash the password for migration
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $migrateRequest = new MigrateRequest(
            $data['email'],
            $hashedPassword,
            'bcrypt',
            $data['organization_id']
        );
        $response = $this->client->passwords->migrate($migrateRequest);

        $this->assertNotEmpty($response->memberId);
        $this->assertEquals($data['member_id'], $response->memberId);
        $this->assertEquals($data['organization_id'], $response->organization->organizationId);

        // Test that we can authenticate with the migrated password
        $authRequest = new AuthenticateRequest(
            $data['organization_id'],
            $data['email'],
            $password
        );
        $authResponse = $this->client->passwords->authenticate($authRequest);

        $this->assertNotEmpty($authResponse->memberId);
        $this->assertNotEmpty($authResponse->sessionToken);
        $this->assertNotEmpty($authResponse->sessionJwt);
    }

    public function testAuthenticatePassword(): void
    {
        $data = $this->createTestOrganizationAndMember();
        $password = $this->generateRandomPassword();

        // Create password first using migrate
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $migrateRequest = new MigrateRequest(
            $data['email'],
            $hashedPassword,
            'bcrypt',
            $data['organization_id']
        );
        $createResponse = $this->client->passwords->migrate($migrateRequest);

        // Authenticate with password
        $authenticateRequest = new AuthenticateRequest(
            $data['organization_id'],
            $data['email'],
            $password
        );
        $response = $this->client->passwords->authenticate($authenticateRequest);

        $this->assertNotEmpty($response->memberId);
        $this->assertEquals($data['member_id'], $response->memberId);
        $this->assertEquals($data['organization_id'], $response->organization->organizationId);
        $this->assertNotEmpty($response->sessionToken);
        $this->assertNotEmpty($response->sessionJwt);
    }

    public function testStrengthCheck(): void
    {
        $weakPassword = '123';
        $strongPassword = $this->generateRandomPassword();

        // Test weak password
        $weakRequest = new StrengthCheckRequest($weakPassword);
        $weakResponse = $this->client->passwords->strengthCheck($weakRequest);

        $this->assertFalse($weakResponse->validPassword);

        // Test strong password
        $strongRequest = new StrengthCheckRequest($strongPassword);
        $strongResponse = $this->client->passwords->strengthCheck($strongRequest);

        $this->assertTrue($strongResponse->validPassword);
    }

    public function testPasswordsExistingPassword(): void
    {
        $data = $this->createTestOrganizationAndMember();
        $password = $this->generateRandomPassword();
        $newPassword = $this->generateRandomPassword();

        // Create password first using migrate
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $migrateRequest = new MigrateRequest(
            $data['email'],
            $hashedPassword,
            'bcrypt',
            $data['organization_id']
        );
        $createResponse = $this->client->passwords->migrate($migrateRequest);

        // Change password using existing password
        $resetRequest = new ExistingPasswordResetRequest(
            $data['email'],
            $password,
            $newPassword,
            $data['organization_id']
        );
        $response = $this->client->passwords->existingPassword->reset($resetRequest);

        $this->assertNotEmpty($response->memberId);
        $this->assertEquals($data['member_id'], $response->memberId);
        $this->assertNotEmpty($response->sessionToken);

        // Verify new password works
        $authRequest = new AuthenticateRequest(
            $data['organization_id'],
            $data['email'],
            $newPassword
        );
        $authResponse = $this->client->passwords->authenticate($authRequest);

        $this->assertNotEmpty($authResponse->memberId);
    }

    public function testPasswordsSession(): void
    {
        $data = $this->createTestOrganizationAndMember();
        $password = $this->generateRandomPassword();
        $newPassword = $this->generateRandomPassword();

        // Create password using migrate
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $migrateRequest = new MigrateRequest(
            $data['email'],
            $hashedPassword,
            'bcrypt',
            $data['organization_id']
        );
        $this->client->passwords->migrate($migrateRequest);

        // Authenticate to get a session
        $authRequest = new AuthenticateRequest(
            $data['organization_id'],
            $data['email'],
            $password
        );
        $authResponse = $this->client->passwords->authenticate($authRequest);

        // Reset password using session
        $sessionResetRequest = new SessionResetRequest(
            $data['organization_id'],
            $newPassword,
            $authResponse->sessionToken
        );
        $response = $this->client->passwords->sessions->reset($sessionResetRequest);

        $this->assertNotEmpty($response->memberId);
        $this->assertEquals($data['member_id'], $response->memberId);
    }

    public function testAuthenticateInvalidPassword(): void
    {
        $data = $this->createTestOrganizationAndMember();
        $password = $this->generateRandomPassword();

        // Create password first using migrate
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $migrateRequest = new MigrateRequest(
            $data['email'],
            $hashedPassword,
            'bcrypt',
            $data['organization_id']
        );
        $this->client->passwords->migrate($migrateRequest);

        // Try to authenticate with wrong password
        $this->expectException(StytchException::class);

        $wrongAuthRequest = new AuthenticateRequest(
            $data['organization_id'],
            $data['email'],
            'wrong-password'
        );
        $this->client->passwords->authenticate($wrongAuthRequest);
    }

    public function testAuthenticateNonExistentMember(): void
    {
        $data = $this->createTestOrganizationAndMember();

        $this->expectException(StytchException::class);

        $nonexistentAuthRequest = new AuthenticateRequest(
            $data['organization_id'],
            'nonexistent@example.com',
            'some-password'
        );
        $this->client->passwords->authenticate($nonexistentAuthRequest);
    }

    public function testAuthenticateWrongOrganization(): void
    {
        $data = $this->createTestOrganizationAndMember();
        $password = $this->generateRandomPassword();

        // Create password first using migrate
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $migrateRequest = new MigrateRequest(
            $data['email'],
            $hashedPassword,
            'bcrypt',
            $data['organization_id']
        );
        $this->client->passwords->migrate($migrateRequest);

        // Try to authenticate with wrong organization ID
        $this->expectException(StytchException::class);

        $wrongOrgAuthRequest = new AuthenticateRequest(
            'organization-test-wrong',
            $data['email'],
            $password
        );
        $this->client->passwords->authenticate($wrongOrgAuthRequest);
    }
}
