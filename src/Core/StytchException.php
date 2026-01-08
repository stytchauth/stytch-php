<?php

namespace Stytch\Core;

/**
 * Stytch API Exception
 */
class StytchException extends \Exception
{
    private ?array $errorData;
    private ?string $requestId;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?array $errorData = null,
        ?\Throwable $previous = null
    ) {
        $message = $message ?: 'An error occurred with the Stytch API';
        $message .= $errorData ? ' - ' . json_encode($errorData) : '';
        parent::__construct($message, $code, $previous);
        $this->errorData = $errorData;
        $this->requestId = $errorData['request_id'] ?? null;
    }

    public function getErrorData(): ?array
    {
        return $this->errorData;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
