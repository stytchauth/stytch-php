<?php

namespace Stytch\Tests\Unit\Core;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Stytch\Core\Client;
use Stytch\Shared\MethodOptions\Authorization;
use Stytch\Tests\TestCase;

class ClientTest extends TestCase
{
    private Client $client;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock handler for Guzzle
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);

        // Create the client
        $this->client = new Client(
            'project-test-12345',
            'secret-test-67890'
        );

        // Use reflection to replace the httpClient with our mocked one
        $reflection = new \ReflectionClass($this->client);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->client, new GuzzleClient([
            'handler' => $handlerStack,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Stytch PHP SDK v1.0.0',
                'Content-Type' => 'application/json',
            ],
            'auth' => ['project-test-12345', 'secret-test-67890'],
        ]));
    }

    public function testPostWithSessionTokenAuthorization(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'request-id-123',
            'session_token' => 'session-token-456'
        ])));

        // Create authorization with session token
        $authorization = new Authorization(sessionToken: 'test-session-token-123');

        // Make a POST request with authorization
        $response = $this->client->post('/v1/test/endpoint', [
            'test_data' => 'value'
        ], [$authorization]);

        // Assert the response
        $this->assertEquals(200, $response['status_code']);
        $this->assertEquals('request-id-123', $response['request_id']);

        // Get the last request to verify headers were added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify the session token header was added
        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-Session'));
        $this->assertEquals('test-session-token-123', $lastRequest->getHeaderLine('X-Stytch-Member-Session'));
    }

    public function testGetWithSessionJwtAuthorization(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'request-id-456',
            'data' => ['test' => 'value']
        ])));

        // Create authorization with session JWT
        $authorization = new Authorization(sessionJwt: 'test-session-jwt-789');

        // Make a GET request with authorization
        $response = $this->client->get('/v1/test/endpoint', [
            'query_param' => 'value'
        ], [$authorization]);

        // Assert the response
        $this->assertEquals(200, $response['status_code']);
        $this->assertEquals('request-id-456', $response['request_id']);

        // Get the last request to verify headers were added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify the session JWT header was added
        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-SessionJWT'));
        $this->assertEquals('test-session-jwt-789', $lastRequest->getHeaderLine('X-Stytch-Member-SessionJWT'));
    }

    public function testPutWithBothTokenTypes(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'request-id-789'
        ])));

        // Create authorization with both token types (session token takes precedence)
        $authorization = new Authorization(
            sessionToken: 'test-session-token-456',
            sessionJwt: 'test-session-jwt-123'
        );

        // Make a PUT request with authorization
        $response = $this->client->put('/v1/test/endpoint', [
            'update_data' => 'new_value'
        ], [$authorization]);

        // Assert the response
        $this->assertEquals(200, $response['status_code']);

        // Get the last request to verify both headers were added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify both headers were added
        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-Session'));
        $this->assertEquals('test-session-token-456', $lastRequest->getHeaderLine('X-Stytch-Member-Session'));

        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-SessionJWT'));
        $this->assertEquals('test-session-jwt-123', $lastRequest->getHeaderLine('X-Stytch-Member-SessionJWT'));
    }

    public function testDeleteWithoutAuthorization(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'request-id-delete'
        ])));

        // Make a DELETE request without authorization
        $response = $this->client->delete('/v1/test/endpoint', [
            'delete_id' => '12345'
        ]);

        // Assert the response
        $this->assertEquals(200, $response['status_code']);

        // Get the last request to verify no auth headers were added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify no session headers were added
        $this->assertFalse($lastRequest->hasHeader('X-Stytch-Member-Session'));
        $this->assertFalse($lastRequest->hasHeader('X-Stytch-Member-SessionJWT'));
    }

    public function testMultipleAuthorizationObjects(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'request-id-multi'
        ])));

        // Create multiple authorization objects (only the first with actual tokens should add headers)
        $auth1 = new Authorization(sessionToken: 'token-1');
        $auth2 = new Authorization(sessionJwt: 'jwt-2');

        // Make a POST request with multiple authorization objects
        $response = $this->client->post('/v1/test/endpoint', [
            'test_data' => 'value'
        ], [$auth1, $auth2]);

        // Assert the response
        $this->assertEquals(200, $response['status_code']);

        // Get the last request to verify headers from both objects were added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify headers from both authorization objects were added
        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-Session'));
        $this->assertEquals('token-1', $lastRequest->getHeaderLine('X-Stytch-Member-Session'));

        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-SessionJWT'));
        $this->assertEquals('jwt-2', $lastRequest->getHeaderLine('X-Stytch-Member-SessionJWT'));
    }

    public function testRequestWithEmptyMethodOptions(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'request-id-empty'
        ])));

        // Make a GET request with empty method options
        $response = $this->client->get('/v1/test/endpoint', [], []);

        // Assert the response
        $this->assertEquals(200, $response['status_code']);

        // Get the last request to verify no auth headers were added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify no session headers were added
        $this->assertFalse($lastRequest->hasHeader('X-Stytch-Member-Session'));
        $this->assertFalse($lastRequest->hasHeader('X-Stytch-Member-SessionJWT'));
    }
}
