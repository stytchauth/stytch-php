#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Stytch\Consumer\Client as ConsumerClient;
use Stytch\B2B\Client as B2BClient;

function testConsumer(string $email): void
{
    echo "\n=== CONSUMER TEST ===\n\n";

    $projectId = getenv('STYTCH_PROJECT_ID');
    $secret = getenv('STYTCH_PROJECT_SECRET');

    if (!$projectId || !$secret) {
        throw new Exception("STYTCH_PROJECT_ID and STYTCH_PROJECT_SECRET must be set");
    }

    $client = new ConsumerClient($projectId, $secret);

    // Step 1: Send email OTP
    echo "1. Sending email OTP to {$email}...\n";
    $sendResponse = $client->otps->email->loginOrCreate([
        'email' => $email,
    ]);
    echo "   ✓ OTP sent! Email ID: {$sendResponse->emailId}\n\n";

    // Step 2: Prompt for code
    echo "2. Enter the OTP code: ";
    $code = trim(fgets(STDIN));
    echo "\n";

    // Step 3: Authenticate OTP
    echo "3. Authenticating OTP...\n";
    $authResponse = $client->otps->authenticate([
        'method_id' => $sendResponse->emailId,
        'code' => $code,
        'session_duration_minutes' => 60,
    ]);
    echo "   ✓ OTP authenticated!\n";
    echo "   User ID: {$authResponse->userId}\n";
    echo "   Session ID: {$authResponse->session->sessionId}\n";
    $jwt = $authResponse->sessionJwt;
    echo "   JWT: " . substr($jwt, 0, 50) . "...\n\n";

    // Step 4: Authenticate JWT locally
    echo "4. Authenticating JWT locally...\n";
    try {
        $session = $client->sessions->authenticateJwtLocal([
            'session_jwt' => $jwt,
        ]);
        echo "   ✓ JWT authenticated locally!\n";
        echo "   Session ID: {$session->sessionId}\n";
        echo "   User ID: {$session->userId}\n";
        echo "   Expires at: {$session->expiresAt}\n";
    } catch (Exception $e) {
        echo "   ✗ JWT local authentication failed: {$e->getMessage()}\n";
        throw $e;
    }

    echo "\n=== CONSUMER TEST PASSED ===\n";
}

function testB2B(string $email): void
{
    echo "\n=== B2B TEST ===\n\n";

    $projectId = getenv('STYTCH_B2B_PROJECT_ID');
    $secret = getenv('STYTCH_B2B_PROJECT_SECRET');

    if (!$projectId || !$secret) {
        throw new Exception("STYTCH_B2B_PROJECT_ID and STYTCH_B2B_PROJECT_SECRET must be set");
    }

    $client = new B2BClient($projectId, $secret);

    // Step 1: Create test organization
    echo "1. Creating test organization...\n";
    $timestamp = time();
    $orgSlug = "test-org-{$timestamp}";

    // Extract email domain for JIT provisioning
    $emailParts = explode('@', $email);
    $emailDomain = $emailParts[1] ?? '';

    $orgResponse = $client->organizations->create([
        'organization_name' => "Test Org {$timestamp}",
        'organization_slug' => $orgSlug,
        'email_jit_provisioning' => 'RESTRICTED',
        'email_allowed_domains' => [$emailDomain],
    ]);
    $organizationId = $orgResponse->organization->organizationId;
    echo "   ✓ Organization created!\n";
    echo "   Organization ID: {$organizationId}\n";
    echo "   Organization Slug: {$orgSlug}\n";
    echo "   Email domain allowed: {$emailDomain}\n\n";

    // Step 2: Send email OTP
    echo "2. Sending email OTP to {$email}...\n";
    $sendResponse = $client->otps->email->loginOrSignup([
        'organization_id' => $organizationId,
        'email_address' => $email,
    ]);
    echo "   ✓ OTP sent! Email ID: {$sendResponse->email_id}\n\n";

    // Step 3: Prompt for code
    echo "3. Enter the OTP code: ";
    $code = trim(fgets(STDIN));
    echo "\n";

    // Step 4: Authenticate OTP
    echo "4. Authenticating OTP...\n";
    $authResponse = $client->otps->email->authenticate([
        'organization_id' => $organizationId,
        'email_address' => $email,
        'code' => $code,
        'session_duration_minutes' => 60,
    ]);
    echo "   ✓ OTP authenticated!\n";
    echo "   Member ID: {$authResponse->memberId}\n";
    echo "   Session ID: {$authResponse->memberSession->memberSessionId}\n";
    $jwt = $authResponse->sessionJwt;
    echo "   JWT: " . substr($jwt, 0, 50) . "...\n\n";

    // Step 5: Authenticate JWT locally
    echo "5. Authenticating JWT locally...\n";
    try {
        $session = $client->sessions->authenticateJwtLocal([
            'session_jwt' => $jwt,
        ]);
        echo "   ✓ JWT authenticated locally!\n";
        echo "   Session ID: {$session->memberSessionId}\n";
        echo "   Member ID: {$session->memberId}\n";
        echo "   Organization ID: {$session->organizationId}\n";
        echo "   Expires at: {$session->expiresAt}\n";
    } catch (Exception $e) {
        echo "   ✗ JWT local authentication failed: {$e->getMessage()}\n";
        throw $e;
    }

    echo "\n=== B2B TEST PASSED ===\n";
}

// Main execution
if ($argc < 2) {
    echo "Usage: php test_jwt_local.php <email>\n";
    echo "Example: php test_jwt_local.php user@example.com\n";
    exit(1);
}

$email = $argv[1];

try {
    testConsumer($email);
    echo "\n" . str_repeat("=", 60) . "\n";
    testB2B($email);
    echo "\n✅ All tests passed!\n";
} catch (Exception $e) {
    echo "\n❌ Test failed: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
