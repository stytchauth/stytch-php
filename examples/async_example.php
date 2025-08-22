<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Promise\Utils;
use Stytch\Consumer\Client;
use Stytch\Core\StytchException;

/**
 * Comprehensive examples of async API usage with the Stytch PHP SDK
 */

// Create a client
$client = new Client(
    projectId: getenv('STYTCH_PROJECT_ID') ?: 'project-test-12345678-1234-1234-1234-123456789012',
    secret: getenv('STYTCH_PROJECT_SECRET') ?: 'secret-test-12345678901234567890123456789012',
    environment: 'test'
);

echo "=== Stytch PHP SDK Async Examples ===\n\n";

// Example 1: Basic async usage
echo "1. Basic Async Usage\n";
echo "-------------------\n";

try {
    // Create a user asynchronously
    $createPromise = $client->users->createAsync([
        'email' => 'async-user@example.com',
        'name' => [
            'first_name' => 'Async',
            'last_name' => 'User'
        ]
    ]);

    echo "Promise created, waiting for response...\n";
    
    // Wait for the response (this blocks, but in real apps you'd chain promises)
    $createResponse = $createPromise->wait();
    
    echo "✅ User created successfully!\n";
    echo "User ID: " . $createResponse->userId . "\n";
    echo "Request ID: " . $createResponse->requestId . "\n\n";
    
} catch (StytchException $e) {
    echo "❌ Error creating user: " . $e->getMessage() . "\n\n";
}

// Example 2: Promise chaining
echo "2. Promise Chaining\n";
echo "------------------\n";

$client->users->createAsync([
    'email' => 'chained-user@example.com'
])
->then(function($createResponse) use ($client) {
    echo "✅ User created: " . $createResponse->userId . "\n";
    
    // Chain another async operation
    return $client->magic_links->email->sendAsync([
        'user_id' => $createResponse->userId,
        'email' => $createResponse->user->emails[0]->email,
        'login_magic_link_url' => 'https://example.com/authenticate',
        'signup_magic_link_url' => 'https://example.com/authenticate',
    ]);
})
->then(function($sendResponse) {
    echo "✅ Magic link sent!\n";
    echo "Request ID: " . $sendResponse->requestId . "\n";
})
->otherwise(function($exception) {
    echo "❌ Error in chain: " . $exception->getMessage() . "\n";
});

echo "\n";

// Example 3: Concurrent requests
echo "3. Concurrent Requests\n";
echo "---------------------\n";

// Create multiple users concurrently
$promises = [];
for ($i = 1; $i <= 3; $i++) {
    $promises["user{$i}"] = $client->users->createAsync([
        'email' => "concurrent-user{$i}@example.com",
        'name' => [
            'first_name' => "User",
            'last_name' => "Number{$i}"
        ]
    ]);
}

echo "Created " . count($promises) . " concurrent requests...\n";

// Wait for all promises to complete
$results = Utils::settle($promises)->wait();

foreach ($results as $key => $result) {
    if ($result['state'] === 'fulfilled') {
        $response = $result['value'];
        echo "✅ {$key}: " . $response->user->emails[0]->email . " (ID: " . $response->userId . ")\n";
    } else {
        echo "❌ {$key}: " . $result['reason']->getMessage() . "\n";
    }
}

echo "\n";

// Example 4: Error handling with async
echo "4. Async Error Handling\n";
echo "----------------------\n";

$client->users->getAsync(['user_id' => 'nonexistent-user-id'])
    ->then(function($user) {
        // This won't be called if the user doesn't exist
        echo "✅ User found: " . $user->user->emails[0]->email . "\n";
        return $user;
    })
    ->otherwise(function($exception) {
        echo "❌ Expected error caught: " . $exception->getMessage() . "\n";
        echo "Status code: " . $exception->getCode() . "\n";
        return null; // Return fallback value
    });

echo "\n";

// Example 5: Mixed sync/async operations
echo "5. Mixed Sync/Async Operations\n";
echo "-----------------------------\n";

try {
    // Synchronous user creation
    $syncUser = $client->users->create([
        'email' => 'mixed-user@example.com'
    ]);
    
    echo "✅ Sync user created: " . $syncUser->userId . "\n";
    
    // Asynchronous operations on the user
    $asyncPromises = [
        'get' => $client->users->getAsync(['user_id' => $syncUser->userId]),
        'update' => $client->users->updateAsync([
            'user_id' => $syncUser->userId,
            'name' => [
                'first_name' => 'Updated',
                'last_name' => 'Name'
            ]
        ])
    ];
    
    $asyncResults = Utils::settle($asyncPromises)->wait();
    
    if ($asyncResults['get']['state'] === 'fulfilled') {
        echo "✅ Async get successful\n";
    }
    
    if ($asyncResults['update']['state'] === 'fulfilled') {
        echo "✅ Async update successful\n";
    }
    
} catch (StytchException $e) {
    echo "❌ Error in mixed operations: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 6: Complex workflow with error recovery
echo "6. Complex Workflow with Error Recovery\n";
echo "--------------------------------------\n";

$client->users->createAsync([
    'email' => 'workflow-user@example.com'
])
->then(function($createResponse) use ($client) {
    echo "✅ Step 1: User created\n";
    
    // Try to create a TOTP (might fail if not configured)
    return $client->totps->createAsync([
        'user_id' => $createResponse->userId,
        'expiration_minutes' => 10
    ]);
})
->then(function($totpResponse) {
    echo "✅ Step 2: TOTP created successfully\n";
    return $totpResponse;
})
->otherwise(function($exception) use ($client) {
    echo "⚠️  Step 2 failed (expected): " . $exception->getMessage() . "\n";
    echo "✅ Recovering with magic link instead...\n";
    
    // Recovery: send magic link instead
    // Note: In a real app, you'd need to pass the user info through the chain
    return null; // Simplified for example
});

echo "\n";

// Example 7: Performance comparison (conceptual)
echo "7. Performance Benefits\n";
echo "----------------------\n";

echo "Async operations allow for:\n";
echo "• Non-blocking I/O - other code can run while waiting for API responses\n";
echo "• Concurrent requests - multiple API calls can happen simultaneously\n";
echo "• Better resource utilization - less time spent waiting\n";
echo "• Scalable applications - handle more users with the same resources\n\n";

// Example 8: Real-world authentication flow
echo "8. Real-World Authentication Flow\n";
echo "--------------------------------\n";

$authFlow = $client->users->createAsync([
    'email' => 'auth-flow@example.com'
])
->then(function($createResponse) use ($client) {
    echo "✅ User created for auth flow\n";
    
    // Send magic link
    return $client->magic_links->email->sendAsync([
        'user_id' => $createResponse->userId,
        'email' => $createResponse->user->emails[0]->email,
        'login_magic_link_url' => 'https://example.com/authenticate',
    ]);
})
->then(function($sendResponse) {
    echo "✅ Magic link sent, user can authenticate\n";
    echo "In a real app, user would click the link and you'd call authenticateAsync\n";
    
    return [
        'flow_complete' => true,
        'request_id' => $sendResponse->requestId
    ];
})
->otherwise(function($exception) {
    echo "❌ Auth flow failed: " . $exception->getMessage() . "\n";
    return ['flow_complete' => false, 'error' => $exception->getMessage()];
});

echo "\n=== Examples Complete ===\n";

echo "\n💡 Tips for using async methods:\n";
echo "• Always handle promise rejections with ->otherwise()\n";
echo "• Use Utils::settle() for concurrent requests that might fail\n";
echo "• Chain operations with ->then() instead of blocking with ->wait()\n";
echo "• Consider using an event loop in long-running applications\n";
echo "• Async methods return the same Response objects as sync methods\n";