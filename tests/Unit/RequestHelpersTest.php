<?php

namespace Stytch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stytch\Tests\Helpers\ConsumerPasswordCreateRequest;
use Stytch\Tests\Helpers\ConsumerPasswordAuthenticateRequest;
use Stytch\Tests\Helpers\B2BPasswordCreateRequest;
use Stytch\Tests\Helpers\B2BPasswordAuthenticateRequest;

class RequestHelpersTest extends TestCase
{
    public function testConsumerPasswordCreateRequest(): void
    {
        $request = new ConsumerPasswordCreateRequest('test@example.com', 'password123', 60);

        $array = $request->toArray();
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('password123', $array['password']);
        $this->assertEquals(60, $array['session_duration_minutes']);
    }

    public function testConsumerPasswordAuthenticateRequest(): void
    {
        $request = new ConsumerPasswordAuthenticateRequest('test@example.com', 'password123');

        $array = $request->toArray();
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('password123', $array['password']);
        $this->assertNull($array['session_duration_minutes']);
    }

    public function testB2BPasswordCreateRequest(): void
    {
        $request = new B2BPasswordCreateRequest('org-123', 'test@example.com', 'password123', 120);

        $array = $request->toArray();
        $this->assertEquals('org-123', $array['organization_id']);
        $this->assertEquals('test@example.com', $array['email_address']);
        $this->assertEquals('password123', $array['password']);
        $this->assertEquals(120, $array['session_duration_minutes']);
    }

    public function testB2BPasswordAuthenticateRequest(): void
    {
        $request = new B2BPasswordAuthenticateRequest('org-123', 'test@example.com', 'password123');

        $array = $request->toArray();
        $this->assertEquals('org-123', $array['organization_id']);
        $this->assertEquals('test@example.com', $array['email_address']);
        $this->assertEquals('password123', $array['password']);
        $this->assertNull($array['session_duration_minutes']);
    }

    public function testFromArrayMethod(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'session_duration_minutes' => 30
        ];

        $request = ConsumerPasswordCreateRequest::fromArray($data);
        $this->assertEquals('test@example.com', $request->email);
        $this->assertEquals('password123', $request->password);
        $this->assertEquals(30, $request->session_duration_minutes);
    }
}
