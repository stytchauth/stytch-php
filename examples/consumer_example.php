<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Stytch\Consumer\Client;
use Stytch\Core\StytchException;

// Initialize the client
$client = new Client(
    projectId: 'project-test-12345678-1234-1234-1234-123456789012',
    secret: 'secret-test-12345678901234567890123456789012',
);

try {
    // Example: Authenticate a session using AuthenticateRequest object
    $response = $client->sessions->authenticate(
        new \Stytch\Consumer\Models\Sessions\AuthenticateRequest(
            sessionToken: 'mZAYn5aLEqKUlZ_Ad9U_fWr38GaAQ1oFAhT8ds245v7Q',
            sessionDurationMinutes: 60
        )
    );

    // Alternative: Using array format
    // $response = $client->sessions->authenticate([
    //     'session_token' => 'mZAYn5aLEqKUlZ_Ad9U_fWr38GaAQ1oFAhT8ds245v7Q',
    //     'session_duration_minutes' => 60
    // ]);

    echo "Session authenticated successfully!\n";
    echo "Session Token: " . $response->sessionToken . "\n";
    echo "Request ID: " . $response->requestId . "\n";

} catch (StytchException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Status Code: " . $e->getCode() . "\n";
    echo "Error Type: " . $e->getErrorType() . "\n";
}
