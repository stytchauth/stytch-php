<?php

namespace Stytch\Tests\Helpers;

class ConsumerPasswordAuthenticateRequest
{
    public string $email;
    public string $password;
    public ?int $session_duration_minutes = null;

    public function __construct(
        string $email,
        string $password,
        ?int $session_duration_minutes = null
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->session_duration_minutes = $session_duration_minutes;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'session_duration_minutes' => $this->session_duration_minutes,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['email'],
            $data['password'],
            $data['session_duration_minutes'] ?? null
        );
    }
}
