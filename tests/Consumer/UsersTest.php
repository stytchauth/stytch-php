<?php

namespace Stytch\Tests\Consumer;

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
            'email' => $email
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
                'last_name' => $lastName
            ]
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
                'last_name' => $newLastName
            ]
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
            'limit' => 10
        ]);

        $this->assertIsArray($response->results);
        $this->assertGreaterThanOrEqual(2, count($response->results));
    }

    public function testCreateUserInvalidEmail(): void
    {
        $this->expectException(StytchException::class);

        $this->client->users->create([
            'email' => 'invalid-email'
        ]);
    }

    public function testGetNonExistentUser(): void
    {
        $this->expectException(StytchException::class);

        $this->client->users->get(['user_id' => 'user-test-nonexistent']);
    }
}
