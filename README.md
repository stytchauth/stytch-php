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

## Contributing

Please see our [contribution guidelines](CONTRIBUTING.md) for details on how to contribute to this project.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
