<?php

namespace Stytch\Tests\Consumer;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Stytch\Consumer\Client;
use Stytch\Core\StytchException;
use Stytch\Tests\TestCase;

class UsersTest extends TestCase
{
    private Client $client;
    private array $testUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client(
            projectId: $this->getConsumerProjectId(),
            secret: $this->getConsumerSecret()
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->testUsers as $userId) {
            try {
                $this->client->users->delete(['user_id' => $userId]);
            } catch (StytchException $e) {
                // Ignore cleanup errors
            }
        }
        parent::tearDown();
    }

    public function testCreateUser(): void
    {
        $email = $this->generateRandomEmail();

        $response = $this->client->users->create([
            'email' => $email,
        ]);

        $this->assertNotEmpty($response->userId);
        $this->assertNotEmpty($response->requestId);
        $this->assertEquals($email, $response->user->emails[0]->email);

        $this->testUsers[] = $response->userId;
    }

    public function testCreateUserWithName(): void
    {
        $email = $this->generateRandomEmail();
        $firstName = 'Test';
        $lastName = 'User';

        $response = $this->client->users->create([
            'email' => $email,
            'name' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
        ]);

        $this->assertEquals($firstName, $response->user->name->firstName);
        $this->assertEquals($lastName, $response->user->name->lastName);

        $this->testUsers[] = $response->userId;
    }

    public function testGetUser(): void
    {
        // Create a user first
        $email = $this->generateRandomEmail();
        $createResponse = $this->client->users->create(['email' => $email]);
        $this->testUsers[] = $createResponse->userId;

        // Get the user
        $response = $this->client->users->get(['user_id' => $createResponse->userId]);

        $this->assertEquals($createResponse->userId, $response->userId);
        $this->assertEquals($email, $response->emails[0]->email);
    }

    public function testUpdateUser(): void
    {
        // Create a user first
        $email = $this->generateRandomEmail();
        $createResponse = $this->client->users->create(['email' => $email]);
        $this->testUsers[] = $createResponse->userId;

        // Update the user
        $newFirstName = 'Updated';
        $newLastName = 'Name';

        $response = $this->client->users->update([
            'user_id' => $createResponse->userId,
            'name' => [
                'first_name' => $newFirstName,
                'last_name' => $newLastName,
            ],
        ]);

        $this->assertEquals($newFirstName, $response->user->name->firstName);
        $this->assertEquals($newLastName, $response->user->name->lastName);
    }

    public function testDeleteUser(): void
    {
        // Create a user first
        $email = $this->generateRandomEmail();
        $createResponse = $this->client->users->create(['email' => $email]);

        // Delete the user
        $response = $this->client->users->delete(['user_id' => $createResponse->userId]);

        $this->assertEquals($createResponse->userId, $response->userId);

        // Verify user is deleted by trying to get it
        $this->expectException(StytchException::class);
        $this->client->users->get(['user_id' => $createResponse->userId]);
    }

    public function testSearchUsers(): void
    {
        // Create a couple of users
        $email1 = $this->generateRandomEmail();
        $email2 = $this->generateRandomEmail();

        $user1 = $this->client->users->create(['email' => $email1]);
        $user2 = $this->client->users->create(['email' => $email2]);

        $this->testUsers[] = $user1->userId;
        $this->testUsers[] = $user2->userId;

        // Search for users
        $response = $this->client->users->search([
            'limit' => 10,
        ]);

        $this->assertIsArray($response->results);
        $this->assertGreaterThanOrEqual(2, count($response->results));
    }

    public function testCreateUserInvalidEmail(): void
    {
        $this->expectException(StytchException::class);

        $this->client->users->create([
            'email' => 'invalid-email',
        ]);
    }

    public function testGetNonExistentUser(): void
    {
        $this->expectException(StytchException::class);

        $this->client->users->get(['user_id' => 'user-test-nonexistent']);
    }

    // ASYNC API TESTS

    public function testCreateUserAsync(): void
    {
        $email = $this->generateRandomEmail();

        // Call async method
        $promise = $this->client->users->createAsync([
            'email' => $email,
        ]);

        // Verify it returns a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the result
        $response = $promise->wait();

        // Verify the response
        $this->assertNotEmpty($response->userId);
        $this->assertNotEmpty($response->requestId);
        $this->assertEquals($email, $response->user->emails[0]->email);

        // Add to cleanup
        $this->testUsers[] = $response->userId;
    }

    public function testGetUserAsync(): void
    {
        // First create a user synchronously
        $email = $this->generateRandomEmail();
        $createResponse = $this->client->users->create([
            'email' => $email,
        ]);
        $this->testUsers[] = $createResponse->userId;

        // Now get the user asynchronously
        $promise = $this->client->users->getAsync([
            'user_id' => $createResponse->userId,
        ]);

        // Verify it returns a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the result
        $response = $promise->wait();

        // Verify the response
        $this->assertEquals($createResponse->userId, $response->userId);
        $this->assertEquals($email, $response->emails[0]->email);
    }

    public function testAsyncPromiseChaining(): void
    {
        $email = $this->generateRandomEmail();

        // Chain async operations
        $finalPromise = $this->client->users->createAsync([
            'email' => $email,
        ])->then(function ($createResponse) {
            // User created, now get the user
            $this->testUsers[] = $createResponse->userId; // Add to cleanup
            return $this->client->users->getAsync([
                'user_id' => $createResponse->userId,
            ]);
        });

        // Wait for the final result
        $getUserResponse = $finalPromise->wait();

        // Verify the final response
        $this->assertEquals($email, $getUserResponse->emails[0]->email);
        $this->assertNotEmpty($getUserResponse->userId);
    }

    public function testConcurrentAsyncRequests(): void
    {
        // Create test users first
        $emails = [
            $this->generateRandomEmail(),
            $this->generateRandomEmail(),
            $this->generateRandomEmail(),
        ];

        $userIds = [];
        foreach ($emails as $email) {
            $response = $this->client->users->create(['email' => $email]);
            $userIds[] = $response->userId;
            $this->testUsers[] = $response->userId;
        }

        // Now get all users concurrently using async
        $promises = [];
        foreach ($userIds as $userId) {
            $promises[$userId] = $this->client->users->getAsync([
                'user_id' => $userId,
            ]);
        }

        // Wait for all promises to complete
        $results = Utils::settle($promises)->wait();

        // Verify all requests succeeded
        $this->assertCount(3, $results);
        foreach ($results as $userId => $result) {
            $this->assertEquals(
                'fulfilled',
                $result['state'],
                "Request for user {$userId} should have succeeded"
            );
            $this->assertEquals($userId, $result['value']->userId);
        }
    }

    public function testAsyncErrorHandling(): void
    {
        // Try to get a non-existent user - this should throw StytchException
        $promise = $this->client->users->getAsync([
            'user_id' => 'user-test-nonexistent-async',
        ]);

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
        $fallbackValue = 'error-fallback';

        $promise2 = $this->client->users->getAsync([
            'user_id' => 'user-test-nonexistent-async-2',
        ]);

        $handledPromise = $promise2->otherwise(function ($exception) use (&$errorCaught, &$errorMessage, $fallbackValue) {
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
