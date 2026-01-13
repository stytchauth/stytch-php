<?php

namespace Stytch\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stytch\Core\Client;

class UrlEncodingTest extends TestCase
{
    private function invokeSubstitutePath(Client $client, string $path, array $data): string
    {
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('substitutePath');
        $method->setAccessible(true);
        return $method->invokeArgs($client, [$path, $data]);
    }

    public function testEmailWithPlusSignIsUrlEncoded(): void
    {
        $client = new Client('project-test-123', 'secret-test-456');

        $path = '/v1/b2b/organizations/{organization_id}/members/{member_id}';
        $data = [
            'organization_id' => 'org-123',
            'member_id' => 'user+test@example.com',
        ];

        $result = $this->invokeSubstitutePath($client, $path, $data);

        // The + should be encoded as %2B
        $expected = '/v1/b2b/organizations/org-123/members/user%2Btest%40example.com';
        $this->assertEquals($expected, $result);
    }

    public function testSpecialCharactersAreUrlEncoded(): void
    {
        $client = new Client('project-test-123', 'secret-test-456');

        $path = '/v1/test/{param}';
        $testCases = [
            'user+test@example.com' => 'user%2Btest%40example.com',
            'user test' => 'user%20test',
            'user#hash' => 'user%23hash',
            'user&query' => 'user%26query',
            'user/slash' => 'user%2Fslash',
        ];

        foreach ($testCases as $input => $expected) {
            $data = ['param' => $input];
            $result = $this->invokeSubstitutePath($client, $path, $data);
            $this->assertEquals("/v1/test/{$expected}", $result);
        }
    }

    public function testNormalParametersAreNotAffected(): void
    {
        $client = new Client('project-test-123', 'secret-test-456');

        $path = '/v1/b2b/organizations/{organization_id}/members/{member_id}';
        $data = [
            'organization_id' => 'org-123',
            'member_id' => 'member-456',
        ];

        $result = $this->invokeSubstitutePath($client, $path, $data);

        $expected = '/v1/b2b/organizations/org-123/members/member-456';
        $this->assertEquals($expected, $result);
    }

    public function testMissingParametersAreLeftUnchanged(): void
    {
        $client = new Client('project-test-123', 'secret-test-456');

        $path = '/v1/b2b/organizations/{organization_id}/members/{member_id}';
        $data = [
            'organization_id' => 'org-123',
            // missing member_id
        ];

        $result = $this->invokeSubstitutePath($client, $path, $data);

        $expected = '/v1/b2b/organizations/org-123/members/{member_id}';
        $this->assertEquals($expected, $result);
    }
}
