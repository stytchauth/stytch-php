<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Stytch\B2B\Client;
use Stytch\Core\StytchException;

// Initialize the B2B client
$client = new Client(
    projectId: 'project-test-12345678-1234-1234-1234-123456789012',
    secret: 'secret-test-12345678901234567890123456789012',
);

try {
    // Example B2B operations would go here once services are generated
    echo "B2B Client initialized successfully!\n";
    echo "Project ID: " . $client->getProjectId() . "\n";
    echo "API Base URL: " . $client->getClient()->getApiBase() . "\n";

    // Access policy cache for RBAC
    $policyCache = $client->getPolicyCache();
    echo "Policy cache initialized: " . ($policyCache ? 'Yes' : 'No') . "\n";

} catch (StytchException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Status Code: " . $e->getCode() . "\n";
    echo "Error Type: " . $e->getErrorType() . "\n";
}
