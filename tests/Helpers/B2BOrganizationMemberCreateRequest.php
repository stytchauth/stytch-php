<?php

namespace Stytch\Tests\Helpers;

class B2BOrganizationMemberCreateRequest
{
    public string $organization_id;
    public string $email_address;
    public ?string $name = null;
    public ?bool $is_breakglass = null;
    public ?array $mfa_enrolled = null;
    public ?array $mfa_phone_number = null;
    public ?array $untrusted_metadata = null;
    public ?array $create_member_as_pending = null;
    public ?array $roles = null;

    public function __construct(
        string $organization_id,
        string $email_address,
        ?string $name = null,
        ?bool $is_breakglass = null,
        ?array $mfa_enrolled = null,
        ?array $mfa_phone_number = null,
        ?array $untrusted_metadata = null,
        ?array $create_member_as_pending = null,
        ?array $roles = null
    ) {
        $this->organization_id = $organization_id;
        $this->email_address = $email_address;
        $this->name = $name;
        $this->is_breakglass = $is_breakglass;
        $this->mfa_enrolled = $mfa_enrolled;
        $this->mfa_phone_number = $mfa_phone_number;
        $this->untrusted_metadata = $untrusted_metadata;
        $this->create_member_as_pending = $create_member_as_pending;
        $this->roles = $roles;
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization_id,
            'email_address' => $this->email_address,
            'name' => $this->name,
            'is_breakglass' => $this->is_breakglass,
            'mfa_enrolled' => $this->mfa_enrolled,
            'mfa_phone_number' => $this->mfa_phone_number,
            'untrusted_metadata' => $this->untrusted_metadata,
            'create_member_as_pending' => $this->create_member_as_pending,
            'roles' => $this->roles,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['organization_id'],
            $data['email_address'],
            $data['name'] ?? null,
            $data['is_breakglass'] ?? null,
            $data['mfa_enrolled'] ?? null,
            $data['mfa_phone_number'] ?? null,
            $data['untrusted_metadata'] ?? null,
            $data['create_member_as_pending'] ?? null,
            $data['roles'] ?? null
        );
    }
}
