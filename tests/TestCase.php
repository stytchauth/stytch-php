<?php

namespace Stytch\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvVar(string $name): string
    {
        $value = $_ENV[$name] ?? getenv($name);
        if (empty($value)) {
            $this->markTestSkipped("Environment variable {$name} not set");
        }
        return $value;
    }

    protected function getConsumerProjectId(): string
    {
        return $this->getEnvVar('STYTCH_PROJECT_ID');
    }

    protected function getConsumerSecret(): string
    {
        return $this->getEnvVar('STYTCH_PROJECT_SECRET');
    }

    protected function getB2BProjectId(): string
    {
        return $this->getEnvVar('STYTCH_B2B_PROJECT_ID');
    }

    protected function getB2BSecret(): string
    {
        return $this->getEnvVar('STYTCH_B2B_PROJECT_SECRET');
    }

    protected function generateRandomEmail(): string
    {
        return 'test+' . uniqid() . '@example.com';
    }

    protected function generateRandomPhoneNumber(): string
    {
        return '+1555' . str_pad((string)rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    }

    protected function generateRandomPassword(): string
    {
        return 'TestPass123!' . uniqid();
    }

    protected function generateRandomString(int $length = 10): string
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }
}
