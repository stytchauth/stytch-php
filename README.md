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
$response = $client->users->create(
    email: 'user@example.com'
);
```

### B2B Client

```php
use Stytch\B2B\Client;

$client = new Client(
    projectId: 'project-test-12345678-1234-1234-1234-123456789012',
    secret: 'secret-test-12345678901234567890123456789012',
);

// Create an organization
$response = $client->organizations->create(
    organizationName: 'Example Corp',
    organizationSlug: 'example-corp'
);
```

## Environment

The SDK supports both test and live environments:

- `test`: Uses `https://test.stytch.com`
- `live`: Uses `https://api.stytch.com`

If no environment is specified, the SDK will auto-detect based on your project ID.

## Error Handling

The SDK throws `StytchException` for API errors:

```php
use Stytch\Core\StytchException;

try {
    $response = $client->users->create(email: 'invalid-email');
} catch (StytchException $e) {
    echo 'Error: ' . $e->getMessage();
    echo 'Status Code: ' . $e->getCode();
    echo 'Error Type: ' . $e->getErrorType();
}
```

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client

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
- Users: create, get, update, delete, search
- Passwords: create, authenticate, strength check, reset operations

**B2B API:**
- Organizations: create, get, update, delete, search
- Organization Members: create, get, update, delete, search, reactivate
- Passwords: create, authenticate, strength check, reset operations, discovery

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
