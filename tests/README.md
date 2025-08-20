# Stytch PHP SDK Tests

This directory contains comprehensive integration tests for the Stytch PHP SDK, covering both Consumer and B2B functionality.

## Setup

### Environment Variables

Before running the tests, you need to set up the following environment variables:

```bash
export STYTCH_PROJECT_ID="your-consumer-project-id"
export STYTCH_PROJECT_SECRET="your-consumer-project-secret"
export STYTCH_B2B_PROJECT_ID="your-b2b-project-id"
export STYTCH_B2B_PROJECT_SECRET="your-b2b-project-secret"
```

Alternatively, you can create a `.env` file in the project root:

```env
STYTCH_PROJECT_ID=your-consumer-project-id
STYTCH_PROJECT_SECRET=your-consumer-project-secret
STYTCH_B2B_PROJECT_ID=your-b2b-project-id
STYTCH_B2B_PROJECT_SECRET=your-b2b-project-secret
```

### Installing Dependencies

```bash
composer install
```

## Running Tests

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Consumer tests only
vendor/bin/phpunit --testsuite Consumer

# B2B tests only
vendor/bin/phpunit --testsuite B2B

# Integration tests only
vendor/bin/phpunit --testsuite Integration
```

### Run Specific Test Files

```bash
# Consumer user tests
vendor/bin/phpunit tests/Consumer/UsersTest.php

# Consumer password tests
vendor/bin/phpunit tests/Consumer/PasswordsTest.php

# B2B organization tests
vendor/bin/phpunit tests/B2B/OrganizationsTest.php

# B2B member tests
vendor/bin/phpunit tests/B2B/OrganizationsMembersTest.php

# B2B password tests
vendor/bin/phpunit tests/B2B/PasswordsTest.php
```

### Run with Coverage

```bash
vendor/bin/phpunit --coverage-html coverage-html
```

## Test Coverage

The test suite covers the following endpoints:

### Consumer API
- **Users**: create, get, update, delete, search
- **Passwords**: create, authenticate, strength check, reset (email/existing/session)

### B2B API
- **Organizations**: create, get, update, delete, search
- **Organization Members**: create, get, update, delete, search, reactivate
- **Passwords**: create, authenticate, strength check, reset (email/existing/session), discovery

## Test Structure

### Base Test Class
- `TestCase.php`: Base class with helper methods for generating test data and accessing environment variables

### Consumer Tests
- `Consumer/UsersTest.php`: Tests for user management operations
- `Consumer/PasswordsTest.php`: Tests for password operations

### B2B Tests
- `B2B/OrganizationsTest.php`: Tests for organization management
- `B2B/OrganizationsMembersTest.php`: Tests for member management
- `B2B/PasswordsTest.php`: Tests for B2B password operations

## Test Data

All tests use randomly generated data to avoid conflicts:
- Email addresses: `test+{unique_id}@example.com`
- Phone numbers: `+1555{7_digit_random}`
- Passwords: `TestPass123!{unique_id}`
- Organization names/slugs: Include random strings

## Cleanup

Tests automatically clean up created resources in the `tearDown()` method to ensure a clean state for subsequent test runs.

## Important Notes

1. **Environment Variables**: Tests will be skipped if required environment variables are not set
2. **Rate Limits**: Be aware of Stytch API rate limits when running tests frequently
3. **Test Environment**: Use test project credentials, not production ones
4. **Cleanup**: Tests clean up their resources, but manual cleanup may be needed if tests are interrupted
5. **Network Dependency**: These are integration tests that make real API calls to Stytch

## Troubleshooting

### Tests Skipped
If tests are being skipped, ensure all required environment variables are set correctly.

### Authentication Errors
Verify that your project credentials are correct and have the necessary permissions.

### Rate Limiting
If you encounter rate limiting errors, add delays between test runs or reduce the number of concurrent tests.