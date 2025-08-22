<?php

namespace Stytch\Tests\Unit;

use Stytch\Tests\TestCase;

class TestCaseTest extends TestCase
{
    public function testGenerateRandomEmail(): void
    {
        $email = $this->generateRandomEmail();

        $this->assertStringContainsString('test+', $email);
        $this->assertStringContainsString('@example.com', $email);
        $this->assertMatchesRegularExpression('/^test\+[a-f0-9]+@example\.com$/', $email);
    }

    public function testGenerateRandomPhoneNumber(): void
    {
        $phone = $this->generateRandomPhoneNumber();

        $this->assertStringStartsWith('+1555', $phone);
        $this->assertEquals(12, strlen($phone)); // +1555 + 7 digits
        $this->assertMatchesRegularExpression('/^\+1555\d{7}$/', $phone);
    }

    public function testGenerateRandomPassword(): void
    {
        $password = $this->generateRandomPassword();

        $this->assertStringStartsWith('TestPass123!', $password);
        $this->assertGreaterThan(12, strlen($password));
    }

    public function testGenerateRandomString(): void
    {
        $string = $this->generateRandomString(15);

        $this->assertEquals(15, strlen($string));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $string);

        // Test default length
        $defaultString = $this->generateRandomString();
        $this->assertEquals(10, strlen($defaultString));
    }

    public function testUniqueGeneratedData(): void
    {
        $email1 = $this->generateRandomEmail();
        $email2 = $this->generateRandomEmail();
        $this->assertNotEquals($email1, $email2);

        $phone1 = $this->generateRandomPhoneNumber();
        $phone2 = $this->generateRandomPhoneNumber();
        $this->assertNotEquals($phone1, $phone2);

        $password1 = $this->generateRandomPassword();
        $password2 = $this->generateRandomPassword();
        $this->assertNotEquals($password1, $password2);
    }

    public function testEnvironmentVariablesAreSet(): void
    {
        // Test that required environment variables are available
        $projectId = $this->getConsumerProjectId();
        $secret = $this->getConsumerSecret();
        $b2bProjectId = $this->getB2BProjectId();
        $b2bSecret = $this->getB2BSecret();

        $this->assertStringStartsWith('project-test-', $projectId);
        $this->assertStringStartsWith('secret-test-', $secret);
        $this->assertStringStartsWith('project-test-', $b2bProjectId);
        $this->assertStringStartsWith('secret-test-', $b2bSecret);
    }
}
