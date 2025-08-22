<?php

namespace Stytch\Tests\B2B;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Stytch\B2B\Client;
use Stytch\B2B\Models\Organizations\CreateRequest;
use Stytch\B2B\Models\Organizations\GetRequest;
use Stytch\B2B\Models\Organizations\DeleteRequest;
use Stytch\B2B\Models\Organizations\UpdateRequest;
use Stytch\B2B\Models\Organizations\SearchRequest;
use Stytch\Core\StytchException;
use Stytch\Tests\TestCase;

class OrganizationsTest extends TestCase
{
    private Client $client;
    private array $testOrganizations = [];

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

    public function testCreateOrganization(): void
    {
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $createRequest = new \Stytch\B2B\Models\Organizations\CreateRequest($organizationName, $organizationSlug);
        $response = $this->client->organizations->create($createRequest);

        $this->assertNotEmpty($response->organization->organizationId);
        $this->assertEquals($organizationName, $response->organization->organizationName);
        $this->assertEquals($organizationSlug, $response->organization->organizationSlug);

        $this->testOrganizations[] = $response->organization->organizationId;
    }

    public function testCreateOrganizationWithEmailDomains(): void
    {
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());
        $emailDomains = ['testdomain.com', 'anotherdomain.com'];

        $createRequest = CreateRequest::fromArray([
            'organization_name' => $organizationName,
            'organization_slug' => $organizationSlug,
            'email_allowed_domains' => $emailDomains
        ]);
        $response = $this->client->organizations->create($createRequest);

        $this->assertNotEmpty($response->organization->organizationId);
        $this->assertEqualsCanonicalizing($emailDomains, $response->organization->emailAllowedDomains);

        $this->testOrganizations[] = $response->organization->organizationId;
    }

    public function testGetOrganization(): void
    {
        // Create an organization first
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        $createResponse = $this->client->organizations->create($createRequest);
        $this->testOrganizations[] = $createResponse->organization->organizationId;

        // Get the organization
        $getRequest = new GetRequest($createResponse->organization->organizationId);
        $response = $this->client->organizations->get($getRequest);

        $this->assertNotEmpty($response->organization);
        $this->assertEquals($createResponse->organization->organizationId, $response->organization->organizationId);
        $this->assertEquals($organizationName, $response->organization->organizationName);
    }

    public function testUpdateOrganization(): void
    {
        // Create an organization first
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        $createResponse = $this->client->organizations->create($createRequest);
        $this->testOrganizations[] = $createResponse->organization->organizationId;

        // Update the organization
        $newOrganizationName = 'Updated Organization ' . $this->generateRandomString();

        $updateRequest = UpdateRequest::fromArray([
            'organization_id' => $createResponse->organization->organizationId,
            'organization_name' => $newOrganizationName
        ]);
        $response = $this->client->organizations->update($updateRequest);

        $this->assertNotEmpty($response->organization);
        $this->assertEquals($newOrganizationName, $response->organization->organizationName);
        $this->assertEquals($organizationSlug, $response->organization->organizationSlug); // Should remain unchanged
    }

    public function testUpdateOrganizationSettings(): void
    {
        // Create an organization first
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        $createResponse = $this->client->organizations->create($createRequest);
        $this->testOrganizations[] = $createResponse->organization->organizationId;

        // Update settings
        $updateRequest = UpdateRequest::fromArray([
            'organization_id' => $createResponse->organization->organizationId,
            'email_jit_provisioning' => 'RESTRICTED',
            'email_allowed_domains' => ['example.com', 'test.com'],
            'email_invites' => 'ALL_ALLOWED',
            'auth_methods' => 'ALL_ALLOWED'
        ]);
        $response = $this->client->organizations->update($updateRequest);

        $this->assertNotEmpty($response->organization);
        $this->assertEquals('RESTRICTED', $response->organization->emailJITProvisioning);
        $this->assertEquals('ALL_ALLOWED', $response->organization->emailInvites);
        $this->assertEquals('ALL_ALLOWED', $response->organization->authMethods);
    }

    public function testDeleteOrganization(): void
    {
        // Create an organization first
        $organizationName = 'Test Organization ' . $this->generateRandomString();
        $organizationSlug = 'test-org-' . strtolower($this->generateRandomString());

        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        $createResponse = $this->client->organizations->create($createRequest);

        // Delete the organization
        $deleteRequest = new DeleteRequest($createResponse->organization->organizationId);
        $response = $this->client->organizations->delete($deleteRequest);

        $this->assertNotEmpty($response->organizationId);
        $this->assertEquals($createResponse->organization->organizationId, $response->organizationId);

        // Verify organization is deleted by trying to get it
        $this->expectException(StytchException::class);
        $getRequest = new GetRequest($createResponse->organization->organizationId);
        $this->client->organizations->get($getRequest);
    }

    public function testSearchOrganizations(): void
    {
        // Create a couple of organizations
        $org1Name = 'Test Org 1 ' . $this->generateRandomString();
        $org2Name = 'Test Org 2 ' . $this->generateRandomString();

        $org1CreateRequest = new CreateRequest($org1Name, 'test-org-1-' . strtolower($this->generateRandomString()));
        $org1 = $this->client->organizations->create($org1CreateRequest);

        $org2CreateRequest = new CreateRequest($org2Name, 'test-org-2-' . strtolower($this->generateRandomString()));
        $org2 = $this->client->organizations->create($org2CreateRequest);

        $this->testOrganizations[] = $org1->organization->organizationId;
        $this->testOrganizations[] = $org2->organization->organizationId;

        // Search for organizations
        $searchRequest = SearchRequest::fromArray(['limit' => 10]);
        $response = $this->client->organizations->search($searchRequest);

        $this->assertIsArray($response->organizations);
        $this->assertGreaterThanOrEqual(2, count($response->organizations));
    }

    public function testCreateOrganizationDuplicateSlug(): void
    {
        $organizationSlug = 'duplicate-slug-' . strtolower($this->generateRandomString());

        // Create first organization
        $createRequest1 = new CreateRequest('First Organization', $organizationSlug);
        $createResponse1 = $this->client->organizations->create($createRequest1);
        $this->testOrganizations[] = $createResponse1->organization->organizationId;

        // Try to create second organization with same slug
        $this->expectException(StytchException::class);

        $createRequest2 = new CreateRequest('Second Organization', $organizationSlug);
        $this->client->organizations->create($createRequest2);
    }

    public function testGetNonExistentOrganization(): void
    {
        $this->expectException(StytchException::class);

        $getRequest = new GetRequest('organization-test-nonexistent');
        $this->client->organizations->get($getRequest);
    }

    public function testCreateOrganizationInvalidSlug(): void
    {
        $this->expectException(StytchException::class);

        $createRequest = new CreateRequest('Test Organization', 'Invalid Slug With Spaces');
        $this->client->organizations->create($createRequest);
    }

    // ASYNC API TESTS

    public function testCreateOrganizationAsync(): void
    {
        $organizationName = 'Async Test Org ' . $this->generateRandomString();
        $organizationSlug = 'async-test-org-' . strtolower($this->generateRandomString());

        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        
        // Call async method
        $promise = $this->client->organizations->createAsync($createRequest);

        // Verify it returns a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the result
        $response = $promise->wait();

        // Verify the response
        $this->assertNotEmpty($response->organization->organizationId);
        $this->assertEquals($organizationName, $response->organization->organizationName);
        $this->assertEquals($organizationSlug, $response->organization->organizationSlug);

        // Add to cleanup
        $this->testOrganizations[] = $response->organization->organizationId;
    }

    public function testGetOrganizationAsync(): void
    {
        // First create an organization synchronously
        $organizationName = 'Test Async Get Org ' . $this->generateRandomString();
        $organizationSlug = 'test-async-get-' . strtolower($this->generateRandomString());
        
        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        $createResponse = $this->client->organizations->create($createRequest);
        $this->testOrganizations[] = $createResponse->organization->organizationId;

        // Now get the organization asynchronously
        $getRequest = new GetRequest($createResponse->organization->organizationId);
        $promise = $this->client->organizations->getAsync($getRequest);

        // Verify it returns a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the result
        $response = $promise->wait();

        // Verify the response
        $this->assertEquals($createResponse->organization->organizationId, $response->organization->organizationId);
        $this->assertEquals($organizationName, $response->organization->organizationName);
        $this->assertEquals($organizationSlug, $response->organization->organizationSlug);
    }

    public function testAsyncOrganizationChaining(): void
    {
        $organizationName = 'Chain Test Org ' . $this->generateRandomString();
        $organizationSlug = 'chain-test-' . strtolower($this->generateRandomString());
        $updatedName = 'Updated ' . $organizationName;

        $createRequest = new CreateRequest($organizationName, $organizationSlug);
        
        // Chain async operations: create -> get -> update -> get
        $finalPromise = $this->client->organizations->createAsync($createRequest)
            ->then(function($createResponse) use ($updatedName) {
                // Organization created, add to cleanup
                $this->testOrganizations[] = $createResponse->organization->organizationId;
                
                // Now update the organization
                $updateRequest = new UpdateRequest($createResponse->organization->organizationId);
                $updateRequest->organizationName = $updatedName;
                
                return $this->client->organizations->updateAsync($updateRequest);
            })
            ->then(function($updateResponse) {
                // Organization updated, now get it to verify
                $getRequest = new GetRequest($updateResponse->organization->organizationId);
                return $this->client->organizations->getAsync($getRequest);
            });

        // Wait for the final result
        $finalResponse = $finalPromise->wait();

        // Verify the final response has the updated name
        $this->assertEquals($updatedName, $finalResponse->organization->organizationName);
        $this->assertEquals($organizationSlug, $finalResponse->organization->organizationSlug);
    }

    public function testConcurrentOrganizationRequests(): void
    {
        // Create test organizations first
        $orgData = [
            ['name' => 'Concurrent Org 1 ' . $this->generateRandomString(), 'slug' => 'concurrent-1-' . strtolower($this->generateRandomString())],
            ['name' => 'Concurrent Org 2 ' . $this->generateRandomString(), 'slug' => 'concurrent-2-' . strtolower($this->generateRandomString())],
            ['name' => 'Concurrent Org 3 ' . $this->generateRandomString(), 'slug' => 'concurrent-3-' . strtolower($this->generateRandomString())]
        ];
        
        $organizationIds = [];
        foreach ($orgData as $data) {
            $createRequest = new CreateRequest($data['name'], $data['slug']);
            $response = $this->client->organizations->create($createRequest);
            $organizationIds[] = $response->organization->organizationId;
            $this->testOrganizations[] = $response->organization->organizationId;
        }

        // Now get all organizations concurrently using async
        $promises = [];
        foreach ($organizationIds as $organizationId) {
            $getRequest = new GetRequest($organizationId);
            $promises[$organizationId] = $this->client->organizations->getAsync($getRequest);
        }

        // Wait for all promises to complete
        $results = Utils::settle($promises)->wait();

        // Verify all requests succeeded
        $this->assertCount(3, $results);
        foreach ($results as $organizationId => $result) {
            $this->assertEquals('fulfilled', $result['state'], 
                "Request for organization {$organizationId} should have succeeded");
            $this->assertEquals($organizationId, $result['value']->organization->organizationId);
        }
    }

    public function testAsyncOrganizationErrorHandling(): void
    {
        // Try to get a non-existent organization - this should throw StytchException
        $getRequest = new GetRequest('organization-test-nonexistent-async');
        $promise = $this->client->organizations->getAsync($getRequest);

        // Test 1: Direct exception handling with wait()
        try {
            $promise->wait();
            $this->fail('Expected StytchException to be thrown');
        } catch (StytchException $e) {
            $this->assertStringContainsString('could not be found', $e->getMessage());
        }

        // Test 2: Promise error handling with ->otherwise()
        $errorCaught = false;
        $errorMessage = null;
        $fallbackValue = 'org-error-fallback';

        $getRequest2 = new GetRequest('organization-test-nonexistent-async-2');
        $promise2 = $this->client->organizations->getAsync($getRequest2);

        $handledPromise = $promise2->otherwise(function($exception) use (&$errorCaught, &$errorMessage, $fallbackValue) {
            $errorCaught = true;
            $errorMessage = $exception->getMessage();
            $this->assertInstanceOf(StytchException::class, $exception);
            return $fallbackValue; // Return fallback value
        });

        $result = $handledPromise->wait();

        // Verify error was caught and fallback returned
        $this->assertTrue($errorCaught, 'Error should have been caught');
        $this->assertStringContainsString('could not be found', $errorMessage);
        $this->assertEquals($fallbackValue, $result, 'Should return fallback value');
    }
}
