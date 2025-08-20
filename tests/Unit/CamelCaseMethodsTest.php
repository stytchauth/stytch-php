<?php

namespace Stytch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CamelCaseMethodsTest extends TestCase
{
    public function testB2BPasswordsMethodsAreCamelCase(): void
    {
        $reflection = new ReflectionClass('Stytch\B2B\Api\Passwords');

        // Check that strengthCheck method exists (not strength_check)
        $this->assertTrue($reflection->hasMethod('strengthCheck'), 'B2B Passwords should have strengthCheck method');
        $this->assertFalse($reflection->hasMethod('strength_check'), 'B2B Passwords should not have strength_check method');
    }

    public function testConsumerPasswordsMethodsAreCamelCase(): void
    {
        $reflection = new ReflectionClass('Stytch\Consumer\Api\Passwords');

        // Check that strengthCheck method exists (not strength_check)
        $this->assertTrue($reflection->hasMethod('strengthCheck'), 'Consumer Passwords should have strengthCheck method');
        $this->assertFalse($reflection->hasMethod('strength_check'), 'Consumer Passwords should not have strength_check method');
    }

    public function testB2BPasswordsPropertiesAreCamelCase(): void
    {
        $reflection = new ReflectionClass('Stytch\B2B\Api\Passwords');

        // Check that existingPassword property exists (not existing_password)
        $this->assertTrue($reflection->hasProperty('existingPassword'), 'B2B Passwords should have existingPassword property');
    }

    public function testConsumerPasswordsPropertiesAreCamelCase(): void
    {
        $reflection = new ReflectionClass('Stytch\Consumer\Api\Passwords');

        // Check that existingPassword property exists (not existing_password)
        $this->assertTrue($reflection->hasProperty('existingPassword'), 'Consumer Passwords should have existingPassword property');
    }
}
