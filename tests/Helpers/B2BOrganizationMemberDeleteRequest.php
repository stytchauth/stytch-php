<?php

namespace Stytch\Tests\Helpers;

class B2BOrganizationMemberDeleteRequest
{
    public string $organization_id;
    public string $member_id;

    public function __construct(string $organization_id, string $member_id)
    {
        $this->organization_id = $organization_id;
        $this->member_id = $member_id;
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization_id,
            'member_id' => $this->member_id,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static($data['organization_id'], $data['member_id']);
    }
}
