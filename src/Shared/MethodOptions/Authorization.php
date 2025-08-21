<?php

namespace Stytch\Shared\MethodOptions;

class Authorization {
  public ?string $sessionToken = null;
  public ?string $sessionJwt = null;

  public function __construct(
    ?string $sessionToken = null,
    ?string $sessionJwt = null
  ) {
    $this->sessionToken = $sessionToken;
    $this->sessionJwt = $sessionJwt;
  }

  public function addHeaders(array $headers): array {
    if ($this->sessionToken) {
      $headers["X-Stytch-Member-Session"] = $this->sessionToken;
    }
    if ($this->sessionJwt) {
      $headers["X-Stytch-Member-SessionJWT"] = $this->sessionJwt;
    }
    return $headers;
  }
}
