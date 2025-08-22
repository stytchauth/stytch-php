<?php

namespace Stytch\Tests\Helpers;

class B2BPasswordCreateRequest
{
    public string $organization_id;
    public string $email_address;
    public string $password;
    public ?int $session_duration_minutes = null;

    public function __construct(
        string $organization_id,
        string $email_address,
        string $password,
        ?int $session_duration_minutes = null
    ) {
        $this->organization_id = $organization_id;
        $this->email_address = $email_address;
        $this->password = $password;
        $this->session_duration_minutes = $session_duration_minutes;
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization_id,
            'email_address' => $this->email_address,
            'password' => $this->password,
            'session_duration_minutes' => $this->session_duration_minutes,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['organization_id'],
            $data['email_address'],
            $data['password'],
            $data['session_duration_minutes'] ?? null
        );
    }
}
