<?php

namespace Stytch\Tests\Helpers;

class B2BOrganizationDeleteRequest
{
    public string $organization_id;

    public function __construct(string $organization_id)
    {
        $this->organization_id = $organization_id;
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization_id,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static($data['organization_id']);
    }
}
