<?php

namespace Stytch\Tests\Unit\Shared;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Stytch\Core\Client;
use Stytch\Shared\JwksCache;
use Stytch\Tests\TestCase;

class JwksCacheTest extends TestCase
{
    private Client $client;
    private MockHandler $mockHandler;
    private JwksCache $jwksCache;

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

        $this->jwksCache = new JwksCache($this->client, 'project-test-12345');
    }

    public function testGetReturnsNullWhenNotCached(): void
    {
        $result = $this->jwksCache->get('project-test-12345');
        $this->assertNull($result);
    }

    public function testSetAndGet(): void
    {
        $jwks = [
            'key-id-1' => [
                'kty' => 'RSA',
                'kid' => 'key-id-1',
                'n' => 'test-n-value',
                'e' => 'AQAB',
            ],
        ];

        $this->jwksCache->set('project-test-12345', $jwks);
        $result = $this->jwksCache->get('project-test-12345');

        $this->assertEquals($jwks, $result);
    }

    public function testGetReturnsNullAfterExpiration(): void
    {
        // Use reflection to set a very short TTL for testing
        $reflection = new \ReflectionClass($this->jwksCache);
        $ttlProperty = $reflection->getProperty('ttl');
        $ttlProperty->setAccessible(true);
        $ttlProperty->setValue($this->jwksCache, 1); // 1 second TTL

        $jwks = [
            'key-id-1' => [
                'kty' => 'RSA',
                'kid' => 'key-id-1',
            ],
        ];

        $this->jwksCache->set('project-test-12345', $jwks);

        // Wait for expiration
        sleep(2);

        $result = $this->jwksCache->get('project-test-12345');
        $this->assertNull($result);
    }

    public function testClear(): void
    {
        $jwks = [
            'key-id-1' => [
                'kty' => 'RSA',
                'kid' => 'key-id-1',
            ],
        ];

        $this->jwksCache->set('project-test-12345', $jwks);
        $this->assertNotNull($this->jwksCache->get('project-test-12345'));

        $this->jwksCache->clear('project-test-12345');
        $this->assertNull($this->jwksCache->get('project-test-12345'));
    }

    public function testClearAll(): void
    {
        $jwks1 = [
            'key-id-1' => [
                'kty' => 'RSA',
                'kid' => 'key-id-1',
            ],
        ];
        $jwks2 = [
            'key-id-2' => [
                'kty' => 'RSA',
                'kid' => 'key-id-2',
            ],
        ];

        $this->jwksCache->set('project-1', $jwks1);
        $this->jwksCache->set('project-2', $jwks2);

        $this->assertNotNull($this->jwksCache->get('project-1'));
        $this->assertNotNull($this->jwksCache->get('project-2'));

        $this->jwksCache->clearAll();

        $this->assertNull($this->jwksCache->get('project-1'));
        $this->assertNull($this->jwksCache->get('project-2'));
    }

    public function testFetchFromApi(): void
    {
        // Mock the API response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'jwk-test-key-id-1',
                    'n' => 'test-n-value',
                    'e' => 'AQAB',
                    'alg' => 'RS256',
                ],
                [
                    'kty' => 'RSA',
                    'kid' => 'jwk-test-key-id-2',
                    'n' => 'test-n-value-2',
                    'e' => 'AQAB',
                    'alg' => 'RS256',
                ],
            ],
        ])));

        $jwks = $this->jwksCache->fetch('project-test-12345');

        // Verify the JWKS was indexed by kid
        $this->assertArrayHasKey('jwk-test-key-id-1', $jwks);
        $this->assertArrayHasKey('jwk-test-key-id-2', $jwks);
        $this->assertEquals('RSA', $jwks['jwk-test-key-id-1']['kty']);
        $this->assertEquals('test-n-value', $jwks['jwk-test-key-id-1']['n']);
    }

    public function testFetchUsesCache(): void
    {
        // Mock the API response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'jwk-test-key-id',
                    'n' => 'test-n-value',
                    'e' => 'AQAB',
                ],
            ],
        ])));

        // Add a second response that should NOT be called
        $this->mockHandler->append(new Response(500, [], json_encode([
            'error' => 'This should not be called',
        ])));

        // First fetch should hit the API
        $jwks1 = $this->jwksCache->fetch('project-test-12345');

        // Second fetch should use cache (no API call)
        $jwks2 = $this->jwksCache->fetch('project-test-12345');

        // Both should return the same data
        $this->assertEquals($jwks1, $jwks2);

        // Verify only one API call was made (second response should still be in queue)
        $this->assertCount(1, $this->mockHandler);
    }

    public function testFetchWithEmptyKeys(): void
    {
        // Mock API response with no keys
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'keys' => [],
        ])));

        $jwks = $this->jwksCache->fetch('project-test-12345');

        $this->assertIsArray($jwks);
        $this->assertEmpty($jwks);
    }

    public function testFetchWithMissingKid(): void
    {
        // Mock API response with key missing kid
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status_code' => 200,
            'keys' => [
                [
                    'kty' => 'RSA',
                    'n' => 'test-n-value',
                    'e' => 'AQAB',
                ],
            ],
        ])));

        $jwks = $this->jwksCache->fetch('project-test-12345');

        // Key without kid should be skipped
        $this->assertIsArray($jwks);
        $this->assertEmpty($jwks);
    }
}
