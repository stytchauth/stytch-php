# Stytch PHP SDK

The Stytch PHP SDK makes it simple to integrate Stytch into your PHP application.

## Installation

Install the SDK using Composer:

```bash
composer require stytch/stytch-php
```

## Usage

### Consumer (B2C) Client

```php
use Stytch\Consumer\Client;

$client = new Client(
    projectId: 'project-test-12345678-1234-1234-1234-123456789012',
    secret: 'secret-test-12345678901234567890123456789012',
);

// Create a user
$response = $client->users->create([
    'email' => 'user@example.com'
]);
```

### B2B Client

```php
use Stytch\B2B\Client;

$client = new Client(
    projectId: 'project-test-12345678-1234-1234-1234-123456789012',
    secret: 'secret-test-12345678901234567890123456789012',
);

// Create an organization
$response = $client->organizations->create([
    'organization_name' => 'Example Corp',
    'organization_slug' => 'example-corp'
]);
```

## Environment

The SDK supports both test and live environments:

- `test`: Uses `https://test.stytch.com`
- `live`: Uses `https://api.stytch.com`

If no environment is specified, the SDK will auto-detect based on your project ID.

## Async API Support

All SDK methods have async counterparts that return Guzzle Promises, allowing for non-blocking operations and concurrent requests.

### Basic Async Usage

```php
use GuzzleHttp\Promise\Utils;

// Single async request
$promise = $client->users->getAsync(['user_id' => 'user-123']);
$user = $promise->wait(); // Block until response

// Or use promise chaining
$promise->then(function($user) {
    echo "User: " . $user->name->firstName;
})->otherwise(function($exception) {
    echo "Error: " . $exception->getMessage();
});
```

### Concurrent Requests

```php
use GuzzleHttp\Promise\Utils;

// Send multiple requests concurrently
$promises = [
    'user1' => $client->users->getAsync(['user_id' => 'user-123']),
    'user2' => $client->users->getAsync(['user_id' => 'user-456']),
    'user3' => $client->users->getAsync(['user_id' => 'user-789']),
];

// Wait for all to complete
$responses = Utils::settle($promises)->wait();

foreach ($responses as $key => $response) {
    if ($response['state'] === 'fulfilled') {
        echo "User {$key}: " . $response['value']->name->firstName . "\n";
    } else {
        echo "Error for {$key}: " . $response['reason']->getMessage() . "\n";
    }
}
```

### Advanced Promise Usage

```php
use GuzzleHttp\Promise\Utils;

// Chain multiple operations
$client->users->createAsync(['email' => 'user@example.com'])
    ->then(function($createResponse) use ($client) {
        // User created, now send magic link
        return $client->magic_links->email->sendAsync([
            'user_id' => $createResponse->userId,
            'email' => $createResponse->user->emails[0]->email,
        ]);
    })
    ->then(function($sendResponse) {
        echo "Magic link sent! Request ID: " . $sendResponse->requestId;
    })
    ->otherwise(function($exception) {
        echo "Error in chain: " . $exception->getMessage();
    });
```

## Error Handling

The SDK throws `StytchException` for API errors:

```php
use Stytch\Core\StytchException;

try {
    $response = $client->users->create(['email' => 'invalid-email']);
} catch (StytchException $e) {
    echo 'Error: ' . $e->getMessage();
    echo 'Status Code: ' . $e->getCode();
    echo 'Error Type: ' . $e->getErrorType();
}
```

### Async Error Handling

Async methods handle errors through promise rejection:

```php
$client->users->getAsync(['user_id' => 'invalid-id'])
    ->then(function($user) {
        // Success handler
        return $user;
    })
    ->otherwise(function($exception) {
        // Error handler - $exception is a StytchException
        echo "Error: " . $exception->getMessage();
        echo "Status: " . $exception->getCode();
        return null; // Return fallback value
    });
```

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client

## Upgrading to 2.0

### PHP 8.4 Compatibility

Version 2.0 adds full support for PHP 8.4 by using explicit nullable type declarations. If you're using PHP 8.4 with strict deprecation handling (common in PHPUnit/Laravel test environments), you **must** upgrade to v2.0+ to avoid deprecation errors.

**Fixed in v2.0:**

```php
// v1.x - Implicit nullable (deprecated in PHP 8.4)
function example(array $data = null) { }

// v2.0+ - Explicit nullable (PHP 8.4 compatible)
function example(?array $data = null) { }
```

### Breaking Changes

#### WhatsApp OTP Namespace Change

The WhatsApp OTP class has been renamed to fix capitalization:

```php
// v1.x
use Stytch\Consumer\Api\OTPsWhatsapp;  // Old
$client->otps->whatsapp->send(...);   // Still works

// v2.0+
use Stytch\Consumer\Api\OTPsWhatsApp;  // New (capital A)
$client->otps->whatsapp->send(...);   // Still works
```

**Impact:** Only affects code that directly imports or type-hints the `OTPsWhatsapp` class. The property access `$client->otps->whatsapp` remains unchanged.

### New Features in 2.0

- **Local JWT Authentication**: Authenticate JWTs locally without making an API call
- **RBAC Organization Policies**: Set and retrieve organization-specific RBAC policies
- **External ID Management**: Delete external IDs for users and organization members
- **DFP Email Risk API**: New device fingerprinting email risk endpoint

## Testing

The SDK includes a comprehensive test suite covering both Consumer and B2B functionality.

### Setup Tests

1. Set up environment variables:

```bash
export STYTCH_PROJECT_ID="your-consumer-project-id"
export STYTCH_PROJECT_SECRET="your-consumer-project-secret"
export STYTCH_B2B_PROJECT_ID="your-b2b-project-id"
export STYTCH_B2B_PROJECT_SECRET="your-b2b-project-secret"
```

2. Install test dependencies:

```bash
composer install
```

### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run Consumer tests only
vendor/bin/phpunit --testsuite Consumer

# Run B2B tests only
vendor/bin/phpunit --testsuite B2B

# Run with coverage
vendor/bin/phpunit --coverage-html coverage-html
```

### Test Coverage

The test suite covers:

**Consumer API:**

- Users: create, get, update, delete, search (sync & async)
- Passwords: create, authenticate, strength check, reset operations (sync & async)
- Sessions: authenticate, get, revoke, exchange (sync & async)
- Magic Links: send, authenticate, invite operations (sync & async)

**B2B API:**

- Organizations: create, get, update, delete, search (sync & async)
- Organization Members: create, get, update, delete, search, reactivate (sync & async)
- Passwords: create, authenticate, strength check, reset operations, discovery (sync & async)

**Async Functionality:**

- Core HTTP client async methods (GET, POST, PUT, DELETE)
- Promise chaining and error handling
- Concurrent request processing
- Integration with all API classes

See [tests/README.md](tests/README.md) for detailed testing documentation.

## Development

### Project Structure

```
src/
├── Consumer/           # Consumer (B2C) API client
│   ├── Api/           # API endpoint classes
│   ├── Models/        # Response models
│   └── Client.php     # Main consumer client
├── B2B/               # B2B API client
│   ├── Api/           # API endpoint classes
│   ├── Models/        # Response models
│   └── Client.php     # Main B2B client
├── Core/              # Shared core functionality
└── Shared/            # Shared utilities

tests/
├── Consumer/          # Consumer API tests
├── B2B/              # B2B API tests
├── Integration/      # Integration tests
└── TestCase.php      # Base test class
```

### Code Quality

This project uses:

- **PHPStan** for static analysis
- **PHPUnit** for testing
- **PSR-4** autoloading
- **PSR-12** coding standards

Run code quality checks:

```bash
vendor/bin/phpstan analyse src tests
vendor/bin/phpunit
```

## Contributing

Please see our [contribution guidelines](CONTRIBUTING.md) for details on how to contribute to this project.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
