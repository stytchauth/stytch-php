# Async API Guide for Stytch PHP SDK

The Stytch PHP SDK provides full async support through Guzzle Promises, allowing for non-blocking operations and concurrent requests.

## Table of Contents

- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
- [Promise Chaining](#promise-chaining)
- [Concurrent Requests](#concurrent-requests)
- [Error Handling](#error-handling)
- [Performance Benefits](#performance-benefits)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Quick Start

Every synchronous method in the SDK has an async counterpart by adding `Async` to the method name:

```php
// Synchronous
$user = $client->users->create(['email' => 'user@example.com']);

// Asynchronous
$promise = $client->users->createAsync(['email' => 'user@example.com']);
$user = $promise->wait(); // Block until completion
```

## Basic Usage

### Simple Async Request

```php
use GuzzleHttp\Promise\PromiseInterface;

$promise = $client->users->getAsync(['user_id' => 'user-123']);

// Promise implements PromiseInterface
assert($promise instanceof PromiseInterface);

// Wait for completion (blocks)
$user = $promise->wait();
echo "User: " . $user->user->emails[0]->email;
```

### Promise Handlers

Instead of blocking with `wait()`, use promise handlers:

```php
$client->users->getAsync(['user_id' => 'user-123'])
    ->then(function($response) {
        // Success handler
        echo "User found: " . $response->user->emails[0]->email;
        return $response;
    })
    ->otherwise(function($exception) {
        // Error handler
        echo "Error: " . $exception->getMessage();
        return null;
    });
```

## Promise Chaining

Chain multiple async operations together:

```php
$client->users->createAsync(['email' => 'new-user@example.com'])
    ->then(function($createResponse) use ($client) {
        // User created, now send magic link
        return $client->magic_links->email->sendAsync([
            'user_id' => $createResponse->userId,
            'email' => $createResponse->user->emails[0]->email,
            'login_magic_link_url' => 'https://example.com/authenticate',
        ]);
    })
    ->then(function($sendResponse) {
        // Magic link sent
        echo "Magic link sent! Request ID: " . $sendResponse->requestId;
    })
    ->otherwise(function($exception) {
        echo "Error in chain: " . $exception->getMessage();
    });
```

### Branching Chains

Handle different outcomes in your chains:

```php
$client->users->getAsync(['user_id' => $userId])
    ->then(function($userResponse) use ($client) {
        // User exists, authenticate with password
        return $client->passwords->authenticateAsync([
            'email' => $userResponse->user->emails[0]->email,
            'password' => $password,
        ]);
    })
    ->otherwise(function($exception) use ($client, $email) {
        // User doesn't exist, create new user
        return $client->users->createAsync(['email' => $email]);
    })
    ->then(function($response) {
        echo "Authentication flow complete!";
    });
```

## Concurrent Requests

### Batch Processing

Process multiple requests simultaneously:

```php
use GuzzleHttp\Promise\Utils;

$userIds = ['user-1', 'user-2', 'user-3'];
$promises = [];

foreach ($userIds as $userId) {
    $promises[$userId] = $client->users->getAsync(['user_id' => $userId]);
}

// Wait for all to complete
$results = Utils::settle($promises)->wait();

foreach ($results as $userId => $result) {
    if ($result['state'] === 'fulfilled') {
        $user = $result['value'];
        echo "✅ {$userId}: " . $user->user->emails[0]->email . "\n";
    } else {
        $exception = $result['reason'];
        echo "❌ {$userId}: " . $exception->getMessage() . "\n";
    }
}
```

### Performance Comparison

```php
use GuzzleHttp\Promise\Utils;

// ❌ Slow: Sequential requests (blocks on each)
$start = microtime(true);
for ($i = 1; $i <= 5; $i++) {
    $user = $client->users->get(['user_id' => "user-{$i}"]);
}
$sequentialTime = microtime(true) - $start;

// ✅ Fast: Concurrent requests
$start = microtime(true);
$promises = [];
for ($i = 1; $i <= 5; $i++) {
    $promises[] = $client->users->getAsync(['user_id' => "user-{$i}"]);
}
Utils::settle($promises)->wait();
$concurrentTime = microtime(true) - $start;

echo "Sequential: {$sequentialTime}s\n";
echo "Concurrent: {$concurrentTime}s\n";
echo "Speedup: " . round($sequentialTime / $concurrentTime, 2) . "x\n";
```

### Concurrent with Limits

Use `Utils::some()` to limit concurrent requests:

```php
use GuzzleHttp\Promise\Utils;

$promises = [];
for ($i = 1; $i <= 100; $i++) {
    $promises[] = $client->users->getAsync(['user_id' => "user-{$i}"]);
}

// Wait for at least 10 to complete successfully
$results = Utils::some(10, $promises)->wait();
echo "Got " . count($results) . " successful responses\n";
```

## Error Handling

### Promise Rejection

Async methods handle errors through promise rejection:

```php
$client->users->getAsync(['user_id' => 'invalid-id'])
    ->then(function($user) {
        // Won't be called for invalid user
        return $user;
    })
    ->otherwise(function($exception) {
        // $exception is a StytchException
        echo "Status: " . $exception->getCode();        // HTTP status
        echo "Type: " . $exception->getErrorType();     // Stytch error type
        echo "Message: " . $exception->getMessage();    // Error message

        // Return fallback value
        return null;
    });
```

### Catch and Continue

Handle errors gracefully without breaking the chain:

```php
$client->users->createAsync(['email' => $email])
    ->otherwise(function($exception) use ($client, $email) {
        if ($exception->getCode() === 409) {
            // User already exists, get instead
            return $client->users->searchAsync([
                'query' => ['operands' => [['field_name' => 'email', 'filter_value' => [$email]]]]
            ]);
        }
        throw $exception; // Re-throw other errors
    })
    ->then(function($response) {
        // Handle both create and search responses
        $user = $response->user ?? $response->results[0];
        echo "User ready: " . $user->userId;
    });
```

### Global Error Handling

```php
function handleStytchError($exception) {
    error_log("Stytch API Error: " . $exception->getMessage());

    // Send to monitoring service
    // Monitor::error('stytch_api', $exception);

    return null; // Fallback value
}

$client->users->getAsync(['user_id' => $userId])
    ->otherwise('handleStytchError')
    ->then(function($user) {
        if ($user) {
            // Success case
            processUser($user);
        } else {
            // Error case (handled by global handler)
            showErrorMessage();
        }
    });
```

## Performance Benefits

### Non-blocking Operations

```php
// ❌ Blocking: Other code waits
$user = $client->users->get(['user_id' => $userId]);
processUser($user);
doOtherWork(); // Has to wait

// ✅ Non-blocking: Other code runs immediately
$userPromise = $client->users->getAsync(['user_id' => $userId]);
doOtherWork(); // Runs immediately!

// Process user when ready
$userPromise->then(function($user) {
    processUser($user);
});
```

### Resource Utilization

Async operations use fewer system resources:

- **Memory**: Less memory per concurrent operation
- **CPU**: Better utilization during I/O waits
- **Connections**: Reuses HTTP connections efficiently
- **Scalability**: Handle more users with same resources

## Best Practices

### 1. Always Handle Rejections

```php
// ❌ Bad: Unhandled promise rejection
$client->users->getAsync(['user_id' => $userId]);

// ✅ Good: Always handle errors
$client->users->getAsync(['user_id' => $userId])
    ->otherwise(function($exception) {
        handleError($exception);
    });
```

### 2. Use Promise Chaining

```php
// ❌ Bad: Nested callbacks (callback hell)
$client->users->createAsync($data)->then(function($user) use ($client) {
    $client->magic_links->email->sendAsync($linkData)->then(function($result) use ($client) {
        $client->sessions->authenticateAsync($authData)->then(function($session) {
            // deeply nested...
        });
    });
});

// ✅ Good: Flat promise chain
$client->users->createAsync($data)
    ->then(function($user) use ($client) {
        return $client->magic_links->email->sendAsync($linkData);
    })
    ->then(function($result) use ($client) {
        return $client->sessions->authenticateAsync($authData);
    })
    ->then(function($session) {
        processSession($session);
    });
```

### 3. Batch Concurrent Requests

```php
// ❌ Bad: Sequential processing
foreach ($userIds as $userId) {
    $user = $client->users->get(['user_id' => $userId]);
    processUser($user);
}

// ✅ Good: Concurrent batch processing
$promises = array_map(function($userId) use ($client) {
    return $client->users->getAsync(['user_id' => $userId]);
}, $userIds);

Utils::settle($promises)->wait();
```

### 4. Set Timeouts

```php
use GuzzleHttp\Promise\Utils;

// Set timeout for promise resolution
$promise = $client->users->getAsync(['user_id' => $userId]);

try {
    $user = $promise->wait(timeout: 5.0); // 5 second timeout
} catch (GuzzleHttp\Promise\TimeoutException $e) {
    echo "Request timed out";
}
```

### 5. Use Type Hints

```php
use GuzzleHttp\Promise\PromiseInterface;
use Stytch\Consumer\Models\Users\GetResponse;

function getUserAsync(string $userId): PromiseInterface
{
    return $client->users->getAsync(['user_id' => $userId])
        ->then(function(GetResponse $response): GetResponse {
            return $response;
        });
}
```

## API Reference

### Available Async Methods

All API classes support async methods by appending `Async` to the method name.

### Return Types

All async methods return `GuzzleHttp\Promise\PromiseInterface` that resolves to the same response objects as their synchronous counterparts.

```php
// Sync method returns GetResponse directly
$response = $client->users->get(['user_id' => $userId]);
assert($response instanceof \Stytch\Consumer\Models\Users\GetResponse);

// Async method returns Promise<GetResponse>
$promise = $client->users->getAsync(['user_id' => $userId]);
assert($promise instanceof \GuzzleHttp\Promise\PromiseInterface);

$response = $promise->wait();
assert($response instanceof \Stytch\Consumer\Models\Users\GetResponse);
```

### Promise Utilities

The SDK works with all Guzzle Promise utilities:

```php
use GuzzleHttp\Promise\Utils;

// Wait for all promises
Utils::settle($promises)->wait();

// Wait for some promises
Utils::some(3, $promises)->wait();

// Race promises (first to complete wins)
Utils::any($promises)->wait();

// All must succeed
Utils::all($promises)->wait();
```

## Integration with Frameworks

### Laravel

```php
// In a Laravel controller/job
use GuzzleHttp\Promise\Utils;

public function processUsers(array $userIds)
{
    $promises = collect($userIds)->map(function($userId) {
        return $this->stytch->users->getAsync(['user_id' => $userId]);
    })->toArray();

    $results = Utils::settle($promises)->wait();

    foreach ($results as $i => $result) {
        if ($result['state'] === 'fulfilled') {
            User::updateFromStytch($userIds[$i], $result['value']);
        } else {
            Log::error('Failed to fetch user', ['user_id' => $userIds[$i]]);
        }
    }
}
```

### Symfony

```php
// In a Symfony service
class UserSyncService
{
    public function syncUsers(array $userIds): Promise
    {
        $promises = array_map(function($userId) {
            return $this->stytch->users->getAsync(['user_id' => $userId])
                ->then(function($response) use ($userId) {
                    return $this->updateLocalUser($userId, $response);
                });
        }, $userIds);

        return Utils::all($promises);
    }
}
```

## Troubleshooting

### Common Issues

1. **Unhandled Promise Rejection**

   ```php
   // ❌ This will cause issues
   $client->users->getAsync(['user_id' => 'invalid']);

   // ✅ Always handle rejections
   $client->users->getAsync(['user_id' => 'invalid'])
       ->otherwise(function($e) { /* handle error */ });
   ```

2. **Memory Leaks with Large Batches**

   ```php
   // ❌ Too many concurrent requests
   $promises = [];
   for ($i = 0; $i < 10000; $i++) {
       $promises[] = $client->users->getAsync(['user_id' => "user-{$i}"]);
   }

   // ✅ Process in chunks
   $chunks = array_chunk($userIds, 50);
   foreach ($chunks as $chunk) {
       $promises = array_map([$client->users, 'getAsync'], $chunk);
       Utils::settle($promises)->wait();
   }
   ```

3. **Blocking the Event Loop**

   ```php
   // ❌ Don't do heavy work in promise callbacks
   $promise->then(function($response) {
       sleep(10); // Blocks everything!
       heavyComputation(); // Also bad!
   });

   // ✅ Keep callbacks light and fast
   $promise->then(function($response) {
       $this->queueHeavyWork($response); // Queue for later
   });
   ```

### Debug Tips

```php
// Enable detailed promise debugging
$promise = $client->users->getAsync(['user_id' => $userId])
    ->then(function($response) {
        error_log('Promise fulfilled: ' . $response->requestId);
        return $response;
    })
    ->otherwise(function($exception) {
        error_log('Promise rejected: ' . $exception->getMessage());
        throw $exception;
    });
```

## Migration from Sync to Async

### Step-by-step Migration

1. **Identify bottlenecks** - Find sequential API calls
2. **Add async suffix** - Change method names
3. **Add promise handling** - Use ->then() and ->otherwise()
4. **Test thoroughly** - Async code behaves differently
5. **Monitor performance** - Measure improvements

### Example Migration

Before (synchronous):

```php
function processUser($userId) {
    $user = $this->stytch->users->get(['user_id' => $userId]);
    $sessions = $this->stytch->sessions->get(['user_id' => $userId]);

    return $this->combineData($user, $sessions);
}
```

After (asynchronous):

```php
function processUser($userId): PromiseInterface {
    $userPromise = $this->stytch->users->getAsync(['user_id' => $userId]);
    $sessionsPromise = $this->stytch->sessions->getAsync(['user_id' => $userId]);

    return Utils::all([$userPromise, $sessionsPromise])
        ->then(function($results) {
            [$user, $sessions] = $results;
            return $this->combineData($user, $sessions);
        });
}
```

The async version runs both requests concurrently, potentially cutting response time in half!
