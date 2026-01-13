<?php

namespace Stytch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stytch\Tests\Helpers\B2BOrganizationGetRequest;
use Stytch\Tests\Helpers\B2BOrganizationDeleteRequest;
use Stytch\Tests\Helpers\B2BOrganizationMemberDeleteRequest;

class OrganizationHelpersTest extends TestCase
{
    public function testB2BOrganizationGetRequest(): void
    {
        $request = new B2BOrganizationGetRequest('org-123');

        $array = $request->toArray();
        $this->assertEquals('org-123', $array['organization_id']);
    }

    public function testB2BOrganizationDeleteRequest(): void
    {
        $request = new B2BOrganizationDeleteRequest('org-456');

        $array = $request->toArray();
        $this->assertEquals('org-456', $array['organization_id']);
    }

    public function testB2BOrganizationMemberDeleteRequest(): void
    {
        $request = new B2BOrganizationMemberDeleteRequest('org-789', 'member-123');

        $array = $request->toArray();
        $this->assertEquals('org-789', $array['organization_id']);
        $this->assertEquals('member-123', $array['member_id']);
    }

    public function testFromArrayMethods(): void
    {
        // Test Organization Get
        $getRequest = B2BOrganizationGetRequest::fromArray(['organization_id' => 'org-test']);
        $this->assertEquals('org-test', $getRequest->organization_id);

        // Test Organization Delete
        $deleteRequest = B2BOrganizationDeleteRequest::fromArray(['organization_id' => 'org-test2']);
        $this->assertEquals('org-test2', $deleteRequest->organization_id);

        // Test Member Delete
        $memberDeleteRequest = B2BOrganizationMemberDeleteRequest::fromArray([
            'organization_id' => 'org-test3',
            'member_id' => 'member-test',
        ]);
        $this->assertEquals('org-test3', $memberDeleteRequest->organization_id);
        $this->assertEquals('member-test', $memberDeleteRequest->member_id);
    }
}
