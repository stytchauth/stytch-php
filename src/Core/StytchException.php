<?php

namespace Stytch\Core;

/**
 * Stytch API Exception
 */
class StytchException extends \Exception
{
    private ?array $errorData;

    public function __construct(
        string $message = '',
        int $code = 0,
        array $errorData = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorData = $errorData;
    }

    public function getErrorData(): ?array
    {
        return $this->errorData;
    }

    public function getErrorType(): ?string
    {
        return $this->errorData['error_type'] ?? null;
    }

    public function getErrorUrl(): ?string
    {
        return $this->errorData['error_url'] ?? null;
    }
}