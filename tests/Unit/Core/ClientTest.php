<?php

namespace Stytch\Tests\Unit\Core;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Stytch\Core\Client;
use Stytch\Core\StytchException;
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

    // ASYNC METHODS TESTS

    public function testGetAsync(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'async-get-123',
            'data' => ['test' => 'value']
        ])));

        // Make an async GET request
        $promise = $this->client->getAsync('/v1/test/endpoint', ['param' => 'value']);

        // Assert we got a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the promise to resolve
        $response = $promise->wait();

        // Assert the response
        $this->assertEquals(200, $response['status_code']);
        $this->assertEquals('async-get-123', $response['request_id']);
        $this->assertEquals(['test' => 'value'], $response['data']);
    }

    public function testPostAsync(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(201, [], json_encode([
            'status_code' => 201,
            'request_id' => 'async-post-456',
            'user_id' => 'user-12345'
        ])));

        // Make an async POST request
        $promise = $this->client->postAsync('/v1/users', ['email' => 'test@example.com']);

        // Assert we got a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the promise to resolve
        $response = $promise->wait();

        // Assert the response
        $this->assertEquals(201, $response['status_code']);
        $this->assertEquals('async-post-456', $response['request_id']);
        $this->assertEquals('user-12345', $response['user_id']);
    }

    public function testPutAsync(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'async-put-789',
            'updated' => true
        ])));

        // Make an async PUT request
        $promise = $this->client->putAsync('/v1/users/{user_id}', [
            'user_id' => 'user-123',
            'name' => ['first_name' => 'Updated']
        ]);

        // Assert we got a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the promise to resolve
        $response = $promise->wait();

        // Assert the response
        $this->assertEquals(200, $response['status_code']);
        $this->assertEquals('async-put-789', $response['request_id']);
        $this->assertTrue($response['updated']);
    }

    public function testDeleteAsync(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'async-delete-101112'
        ])));

        // Make an async DELETE request
        $promise = $this->client->deleteAsync('/v1/users/{user_id}', ['user_id' => 'user-456']);

        // Assert we got a promise
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        // Wait for the promise to resolve
        $response = $promise->wait();

        // Assert the response
        $this->assertEquals(200, $response['status_code']);
        $this->assertEquals('async-delete-101112', $response['request_id']);
    }

    public function testAsyncWithAuthorization(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'async-auth-131415'
        ])));

        // Create authorization
        $authorization = new Authorization(sessionToken: 'async-session-token');

        // Make an async POST request with authorization
        $promise = $this->client->postAsync('/v1/test/authenticated', [
            'test_data' => 'value'
        ], [$authorization]);

        // Wait for the promise to resolve
        $response = $promise->wait();

        // Assert the response
        $this->assertEquals(200, $response['status_code']);

        // Verify the authorization header was added
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertTrue($lastRequest->hasHeader('X-Stytch-Member-Session'));
        $this->assertEquals('async-session-token', $lastRequest->getHeaderLine('X-Stytch-Member-Session'));
    }

    public function testAsyncPromiseChaining(): void
    {
        // Mock multiple responses for chaining
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'user_id' => 'user-chain-123'
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'session_token' => 'session-chain-456'
        ])));

        // Chain async requests
        $finalPromise = $this->client->postAsync('/v1/users', ['email' => 'chain@example.com'])
            ->then(function ($createResponse) {
                // Return another async request
                return $this->client->postAsync('/v1/sessions/authenticate', [
                    'user_id' => $createResponse['user_id']
                ]);
            });

        // Wait for the final result
        $finalResponse = $finalPromise->wait();

        // Assert the final response
        $this->assertEquals(200, $finalResponse['status_code']);
        $this->assertEquals('session-chain-456', $finalResponse['session_token']);
    }

    public function testAsyncErrorHandling(): void
    {
        // Mock an error response
        $this->mockHandler->append(new Response(400, [], json_encode([
            'status_code' => 400,
            'error_type' => 'invalid_request',
            'error_message' => 'Invalid user ID'
        ])));

        // Make an async request that will fail
        $promise = $this->client->getAsync('/v1/users/invalid-id');

        // Expect the promise to be rejected with a StytchException
        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('Invalid user ID');
        $this->expectExceptionCode(400);

        $promise->wait();
    }

    public function testAsyncErrorHandlingWithOtherwise(): void
    {
        // Mock an error response
        $this->mockHandler->append(new Response(404, [], json_encode([
            'status_code' => 404,
            'error_type' => 'user_not_found',
            'error_message' => 'User not found'
        ])));

        $errorCaught = false;
        $errorMessage = null;

        // Make an async request with error handling
        $promise = $this->client->getAsync('/v1/users/nonexistent')
            ->otherwise(function ($exception) use (&$errorCaught, &$errorMessage) {
                $errorCaught = true;
                $errorMessage = $exception->getMessage();
                return null; // Return fallback value
            });

        $result = $promise->wait();

        // Assert error was caught and handled
        $this->assertTrue($errorCaught);
        $this->assertStringContainsString('User not found', $errorMessage);
        $this->assertNull($result);
    }

    public function testConcurrentAsyncRequests(): void
    {
        // Mock multiple responses
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'user_id' => 'user-1',
            'name' => 'User One'
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'user_id' => 'user-2',
            'name' => 'User Two'
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'user_id' => 'user-3',
            'name' => 'User Three'
        ])));

        // Create concurrent async requests
        $promises = [
            'user1' => $this->client->getAsync('/v1/users/user-1'),
            'user2' => $this->client->getAsync('/v1/users/user-2'),
            'user3' => $this->client->getAsync('/v1/users/user-3'),
        ];

        // Wait for all promises to settle
        $results = Utils::settle($promises)->wait();

        // Assert all requests succeeded
        foreach ($results as $key => $result) {
            $this->assertEquals('fulfilled', $result['state']);
            $this->assertEquals(200, $result['value']['status_code']);
        }

        // Assert specific results
        $this->assertEquals('User One', $results['user1']['value']['name']);
        $this->assertEquals('User Two', $results['user2']['value']['name']);
        $this->assertEquals('User Three', $results['user3']['value']['name']);
    }

    public function testAsyncPathSubstitution(): void
    {
        // Mock the HTTP response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'request_id' => 'path-sub-123',
            'user_id' => 'user-456'
        ])));

        // Make request with path parameters
        $promise = $this->client->getAsync('/v1/users/{user_id}/sessions/{session_id}', [
            'user_id' => 'user-456',
            'session_id' => 'session-789',
            'extra_param' => 'should_remain'
        ]);

        $response = $promise->wait();

        // Assert the response
        $this->assertEquals(200, $response['status_code']);

        // Verify the path was correctly substituted and params cleaned
        $lastRequest = $this->mockHandler->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertEquals('/v1/users/user-456/sessions/session-789', $lastRequest->getUri()->getPath());

        // Verify query parameters were cleaned (path params removed)
        parse_str($lastRequest->getUri()->getQuery(), $queryParams);
        $this->assertArrayHasKey('extra_param', $queryParams);
        $this->assertArrayNotHasKey('user_id', $queryParams);
        $this->assertArrayNotHasKey('session_id', $queryParams);
    }

    public function testAsyncJsonDecodeError(): void
    {
        // Mock response with invalid JSON
        $this->mockHandler->append(new Response(200, [], 'invalid-json-response'));

        $promise = $this->client->getAsync('/v1/test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $promise->wait();
    }
}
