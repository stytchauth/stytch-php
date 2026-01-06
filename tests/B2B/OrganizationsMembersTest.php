<?php

namespace Stytch\Tests\B2B;

use Stytch\B2B\Client;
use Stytch\B2B\Models\Organizations\DeleteRequest;
use Stytch\B2B\Models\Organizations\Members\DeleteRequest as MemberDeleteRequest;
use Stytch\B2B\Models\Organizations\Members\CreateRequest as MemberCreateRequest;
use Stytch\Core\StytchException;
use Stytch\Tests\TestCase;

class OrganizationsMembersTest extends TestCase
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
                if (!empty($memberData['member_id']) && !empty($memberData['organization_id'])) {
                    $deleteRequest = new MemberDeleteRequest(
                        $memberData['organization_id'],
                        $memberData['member_id']
                    );
                    $this->client->organizations->members->delete($deleteRequest);
                }
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

    private function createTestOrganization(): array
    {
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $createRequest = new \Stytch\B2B\Models\Organizations\CreateRequest($organizationName, $organizationSlug);
        $response = $this->client->organizations->create($createRequest);

        $this->testOrganizations[] = $response->organization->organizationId;

        return [
            'organization_id' => $response->organization->organizationId,
            'organization' => $response->organization,
        ];
    }

    public function testCreateMember(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();

        $createRequest = new MemberCreateRequest(
            $orgData['organization_id'],
            $email
        );
        $response = $this->client->organizations->members->create($createRequest);

        $this->assertNotEmpty($response->memberId);
        $this->assertEquals($email, $response->member->emailAddress);
        $this->assertEquals($orgData['organization_id'], $response->organization->organizationId);

        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $response->memberId,
        ];
    }

    public function testCreateMemberWithName(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();
        $firstName = 'John';
        $lastName = 'Doe';

        $createRequest = MemberCreateRequest::fromArray([
            'organization_id' => $orgData['organization_id'],
            'email_address' => $email,
            'name' => $firstName . ' ' . $lastName,
        ]);
        $response = $this->client->organizations->members->create($createRequest);

        $this->assertEquals($email, $response->member->emailAddress);
        $this->assertEquals($firstName . ' ' . $lastName, $response->member->name);

        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $response->memberId,
        ];
    }

    public function testCreateMemberWithRoles(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();
        // Skip this test as it requires configured RBAC roles in the test project
        $this->markTestSkipped('RBAC roles not configured in test environment');
    }

    public function testGetMember(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();

        // Create a member first
        $createRequest = new MemberCreateRequest($orgData['organization_id'], $email);
        $createResponse = $this->client->organizations->members->create($createRequest);

        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse->memberId,
        ];

        // Get the member
        $response = $this->client->organizations->members->get([
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse->memberId,
        ]);

        $this->assertEquals($createResponse->memberId, $response->member->memberId);
        $this->assertEquals($email, $response->member->emailAddress);
    }

    public function testUpdateMember(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();

        // Create a member first
        $createRequest = new MemberCreateRequest($orgData['organization_id'], $email);
        $createResponse = $this->client->organizations->members->create($createRequest);

        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse->memberId,
        ];

        // Update the member
        $newName = 'Updated Name';

        $response = $this->client->organizations->members->update([
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse->memberId,
            'name' => $newName,
        ]);

        $this->assertEquals($newName, $response->member->name);
    }

    public function testDeleteMember(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();

        // Create a member first
        $createRequest = new MemberCreateRequest($orgData['organization_id'], $email);
        $createResponse = $this->client->organizations->members->create($createRequest);

        // Delete the member
        $response = $this->client->organizations->members->delete([
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse->memberId,
        ]);

        $this->assertEquals($createResponse->memberId, $response->memberId);

        // Verify member is deleted by trying to get it
        $this->expectException(StytchException::class);
        $this->client->organizations->members->get([
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse->memberId,
        ]);
    }

    public function testSearchMembers(): void
    {
        $orgData = $this->createTestOrganization();

        // Create a couple of members
        $email1 = $this->generateRandomEmail();
        $email2 = $this->generateRandomEmail();

        $createRequest1 = new MemberCreateRequest($orgData['organization_id'], $email1);
        $member1 = $this->client->organizations->members->create($createRequest1);

        $createRequest2 = new MemberCreateRequest($orgData['organization_id'], $email2);
        $member2 = $this->client->organizations->members->create($createRequest2);

        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $member1->memberId,
        ];
        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $member2->memberId,
        ];

        // Search for members
        $response = $this->client->organizations->members->search([
            'organization_ids' => [$orgData['organization_id']],
            'limit' => 10,
        ]);

        $this->assertIsArray($response->members);
        $this->assertGreaterThanOrEqual(2, count($response->members));
    }

    public function testReactivateMember(): void
    {
        // Skip this test as it requires a verified email address
        // The reactivate endpoint only works for members with at least one verified email
        $this->markTestSkipped('Member reactivation requires verified email address which cannot be set via API in test environment');
    }

    public function testCreateMemberDuplicateEmail(): void
    {
        $orgData = $this->createTestOrganization();
        $email = $this->generateRandomEmail();

        // Create first member
        $createRequest1 = new MemberCreateRequest($orgData['organization_id'], $email);
        $createResponse1 = $this->client->organizations->members->create($createRequest1);

        $this->testMembers[] = [
            'organization_id' => $orgData['organization_id'],
            'member_id' => $createResponse1->memberId,
        ];

        // Try to create second member with same email
        $this->expectException(StytchException::class);

        $createRequest = new MemberCreateRequest($orgData['organization_id'], $email);
        $this->client->organizations->members->create($createRequest);
    }

    public function testGetNonExistentMember(): void
    {
        $orgData = $this->createTestOrganization();

        $this->expectException(StytchException::class);

        $this->client->organizations->members->get([
            'organization_id' => $orgData['organization_id'],
            'member_id' => 'member-test-nonexistent',
        ]);
    }

    public function testCreateMemberInvalidEmail(): void
    {
        $orgData = $this->createTestOrganization();

        $this->expectException(StytchException::class);

        $createRequest = new MemberCreateRequest($orgData['organization_id'], 'invalid-email');
        $this->client->organizations->members->create($createRequest);
    }
}
