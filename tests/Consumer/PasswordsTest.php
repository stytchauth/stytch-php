<?php

namespace Stytch\Tests\Consumer;

use Stytch\Consumer\Client;
use Stytch\Consumer\Models\Passwords\StrengthCheckRequest;
use Stytch\Core\StytchException;
use Stytch\Tests\Helpers\ConsumerPasswordCreateRequest;
use Stytch\Tests\Helpers\ConsumerPasswordAuthenticateRequest;
use Stytch\Tests\TestCase;

class PasswordsTest extends TestCase
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

    public function testCreatePassword(): void
    {
        $email = $this->generateRandomEmail();
        $password = $this->generateRandomPassword();

        $createRequest = new ConsumerPasswordCreateRequest($email, $password);
        $response = $this->client->passwords->create($createRequest->toArray());

        $this->assertNotEmpty($response->userId);
        $this->assertNotEmpty($response->user);
        $this->assertEquals($email, $response->user->emails[0]->email);

        $this->testUsers[] = $response->userId;
    }

    public function testAuthenticatePassword(): void
    {
        $email = $this->generateRandomEmail();
        $password = $this->generateRandomPassword();

        // Create password first
        $createResponse = $this->client->passwords->create([
            'email' => $email,
            'password' => $password,
        ]);
        $this->testUsers[] = $createResponse->userId;

        // Authenticate with password
        $response = $this->client->passwords->authenticate([
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertEquals($createResponse->userId, $response->userId);
    }

    public function testStrengthCheck(): void
    {
        $weakPassword = '123';
        $strongPassword = $this->generateRandomPassword();

        // Test weak password
        $weakRequest = new StrengthCheckRequest($weakPassword);
        $weakResponse = $this->client->passwords->strengthCheck($weakRequest);

        $this->assertFalse($weakResponse->validPassword);
        $this->assertNotEmpty($weakResponse->feedback);

        // Test strong password
        $strongRequest = new StrengthCheckRequest($strongPassword);
        $strongResponse = $this->client->passwords->strengthCheck($strongRequest);

        $this->assertTrue($strongResponse->validPassword);
    }

    public function testPasswordsExistingPassword(): void
    {
        $email = $this->generateRandomEmail();
        $password = $this->generateRandomPassword();
        $newPassword = $this->generateRandomPassword();

        // Create password first
        $createResponse = $this->client->passwords->create([
            'email' => $email,
            'password' => $password,
        ]);
        $this->testUsers[] = $createResponse->userId;

        // Change password using existing password
        $response = $this->client->passwords->existingPassword->reset([
            'email' => $email,
            'existing_password' => $password,
            'new_password' => $newPassword,
        ]);

        $this->assertEquals($createResponse->userId, $response->userId);

        // Verify new password works
        $authResponse = $this->client->passwords->authenticate([
            'email' => $email,
            'password' => $newPassword,
        ]);

        $this->assertEquals(200, $authResponse->statusCode);
    }

    public function testPasswordsSession(): void
    {
        $email = $this->generateRandomEmail();
        $password = $this->generateRandomPassword();
        $newPassword = $this->generateRandomPassword();

        // Create password and get session
        $createResponse = $this->client->passwords->create([
            'email' => $email,
            'password' => $password,
            'session_duration_minutes' => 60,
        ]);
        $this->testUsers[] = $createResponse->userId;

        // Reset password using session
        $response = $this->client->passwords->sessions->reset([
            'session_token' => $createResponse->sessionToken,
            'password' => $newPassword,
        ]);

        $this->assertEquals($createResponse->userId, $response->userId);
    }

    public function testAuthenticateInvalidPassword(): void
    {
        $email = $this->generateRandomEmail();
        $password = $this->generateRandomPassword();

        // Create password first
        $createResponse = $this->client->passwords->create([
            'email' => $email,
            'password' => $password,
        ]);
        $this->testUsers[] = $createResponse->userId;

        // Try to authenticate with wrong password
        $this->expectException(StytchException::class);

        $this->client->passwords->authenticate([
            'email' => $email,
            'password' => 'wrong-password',
        ]);
    }

    public function testCreatePasswordWeakPassword(): void
    {
        $email = $this->generateRandomEmail();

        $this->expectException(StytchException::class);

        $this->client->passwords->create([
            'email' => $email,
            'password' => '123',  // Too weak
        ]);
    }

    public function testAuthenticateNonExistentUser(): void
    {
        $this->expectException(StytchException::class);

        $this->client->passwords->authenticate([
            'email' => 'nonexistent@example.com',
            'password' => 'some-password',
        ]);
    }
}
