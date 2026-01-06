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
# Unit tests
vendor/bin/phpunit tests/Unit/Shared/JwksCacheTest.php
vendor/bin/phpunit tests/Unit/Shared/JwtHelpersTest.php
vendor/bin/phpunit tests/Unit/Shared/RbacLocalTest.php

# Consumer tests
vendor/bin/phpunit tests/Consumer/UsersTest.php
vendor/bin/phpunit tests/Consumer/PasswordsTest.php
vendor/bin/phpunit tests/Consumer/SessionsJwtTest.php

# B2B tests
vendor/bin/phpunit tests/B2B/OrganizationsTest.php
vendor/bin/phpunit tests/B2B/OrganizationsMembersTest.php
vendor/bin/phpunit tests/B2B/PasswordsTest.php
vendor/bin/phpunit tests/B2B/SessionsJwtTest.php
```

### Run with Coverage

```bash
vendor/bin/phpunit --coverage-html coverage-html
```

## Test Coverage

The test suite covers the following endpoints:

### Unit Tests
- **JwksCache**: JWKS caching, TTL, fetch logic, cache invalidation
- **JwtHelpers**: JWT parsing, validation, signature verification, claim extraction, issuer validation
- **RbacLocal**: Role-based and scope-based authorization checks

### Consumer API
- **Users**: create, get, update, delete, search
- **Passwords**: create, authenticate, strength check, reset (email/existing/session)
- **Sessions (JWT)**: authenticateJwt, authenticateJwtLocal, local validation with fallback, session management

### B2B API
- **Organizations**: create, get, update, delete, search
- **Organization Members**: create, get, update, delete, search, reactivate
- **Passwords**: create, authenticate, strength check, reset (email/existing/session), discovery
- **Sessions (JWT)**: authenticateJwt, authenticateJwtLocal, organization claim handling, RBAC authorization

## Test Structure

### Base Test Class
- `TestCase.php`: Base class with helper methods for generating test data and accessing environment variables

### Unit Tests
- `Unit/Shared/JwksCacheTest.php`: Tests for JWKS caching functionality
- `Unit/Shared/JwtHelpersTest.php`: Tests for JWT validation and parsing
- `Unit/Shared/RbacLocalTest.php`: Tests for local RBAC authorization

### Consumer Tests
- `Consumer/UsersTest.php`: Tests for user management operations
- `Consumer/PasswordsTest.php`: Tests for password operations
- `Consumer/SessionsJwtTest.php`: Tests for JWT session authentication

### B2B Tests
- `B2B/OrganizationsTest.php`: Tests for organization management
- `B2B/OrganizationsMembersTest.php`: Tests for member management
- `B2B/PasswordsTest.php`: Tests for B2B password operations
- `B2B/SessionsJwtTest.php`: Tests for B2B JWT session authentication with organization claims

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
5. **Network Dependency**: Integration tests make real API calls to Stytch; unit tests use mocks
6. **JWT Tests**: JWT authentication tests include both local validation (unit tests) and network fallback (integration tests)
7. **RBAC Tests**: RBAC authorization tests require policies to be configured in the test project

## Troubleshooting

### Tests Skipped
If tests are being skipped, ensure all required environment variables are set correctly.

### Authentication Errors
Verify that your project credentials are correct and have the necessary permissions.

### Rate Limiting
If you encounter rate limiting errors, add delays between test runs or reduce the number of concurrent tests.