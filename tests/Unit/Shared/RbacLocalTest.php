<?php

namespace Stytch\Tests\Unit\Shared;

use Stytch\Core\StytchException;
use Stytch\Shared\RbacLocal;
use Stytch\Tests\TestCase;

class RbacLocalTest extends TestCase
{
    public function testPerformRoleAuthorizationCheckSuccess(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'admin',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read', 'write', 'delete'],
                        ],
                    ],
                ],
                [
                    'role_id' => 'viewer',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['admin'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'write',
        ];

        // Should not throw exception
        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );

        $this->assertTrue(true); // If we get here, the check passed
    }

    public function testPerformRoleAuthorizationCheckWithWildcard(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'superadmin',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['*'], // Wildcard allows all actions
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['superadmin'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'any_action',
        ];

        // Should not throw exception
        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );

        $this->assertTrue(true);
    }

    public function testPerformRoleAuthorizationCheckFailureNoPermission(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'viewer',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['viewer'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'delete', // Viewer doesn't have delete permission
        ];

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('User does not have permission to perform the requested action');

        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );
    }

    public function testPerformRoleAuthorizationCheckFailureNoRole(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'admin',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read', 'write'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['guest']; // Guest role not in policy
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'read',
        ];

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('User does not have permission to perform the requested action');

        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );
    }

    public function testPerformRoleAuthorizationCheckFailureWrongResource(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'admin',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read', 'write'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['admin'];
        $authorizationCheck = [
            'resource_id' => 'images', // Different resource
            'action' => 'read',
        ];

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('User does not have permission to perform the requested action');

        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );
    }

    public function testPerformRoleAuthorizationCheckWithMultipleRoles(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'viewer',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read'],
                        ],
                    ],
                ],
                [
                    'role_id' => 'editor',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['write'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['viewer', 'editor'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'write',
        ];

        // Should succeed because editor role has write permission
        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );

        $this->assertTrue(true);
    }

    public function testPerformScopeAuthorizationCheckSuccess(): void
    {
        $policy = [
            'scopes' => [
                [
                    'scope' => 'documents:write',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read', 'write'],
                        ],
                    ],
                ],
            ],
        ];

        $tokenScopes = ['documents:write'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'write',
        ];

        // Should not throw exception
        RbacLocal::performScopeAuthorizationCheck(
            $policy,
            $tokenScopes,
            $authorizationCheck,
            'User'
        );

        $this->assertTrue(true);
    }

    public function testPerformScopeAuthorizationCheckWithWildcard(): void
    {
        $policy = [
            'scopes' => [
                [
                    'scope' => 'admin:*',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['*'],
                        ],
                    ],
                ],
            ],
        ];

        $tokenScopes = ['admin:*'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'any_action',
        ];

        // Should not throw exception
        RbacLocal::performScopeAuthorizationCheck(
            $policy,
            $tokenScopes,
            $authorizationCheck,
            'User'
        );

        $this->assertTrue(true);
    }

    public function testPerformScopeAuthorizationCheckFailure(): void
    {
        $policy = [
            'scopes' => [
                [
                    'scope' => 'documents:read',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read'],
                        ],
                    ],
                ],
            ],
        ];

        $tokenScopes = ['documents:read'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'delete', // Scope doesn't allow delete
        ];

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('User does not have permission to perform the requested action');

        RbacLocal::performScopeAuthorizationCheck(
            $policy,
            $tokenScopes,
            $authorizationCheck,
            'User'
        );
    }

    public function testPerformRoleAuthorizationCheckCustomCallerType(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'viewer',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = ['viewer'];
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'delete',
        ];

        try {
            RbacLocal::performRoleAuthorizationCheck(
                $policy,
                $subjectRoles,
                $authorizationCheck,
                'Member'
            );
            $this->fail('Expected exception was not thrown');
        } catch (StytchException $e) {
            $this->assertStringContainsString('Member does not have permission', $e->getMessage());
        }
    }

    public function testPerformRoleAuthorizationCheckEmptyRoles(): void
    {
        $policy = [
            'roles' => [
                [
                    'role_id' => 'admin',
                    'permissions' => [
                        [
                            'resource_id' => 'documents',
                            'actions' => ['read', 'write'],
                        ],
                    ],
                ],
            ],
        ];

        $subjectRoles = []; // No roles
        $authorizationCheck = [
            'resource_id' => 'documents',
            'action' => 'read',
        ];

        $this->expectException(StytchException::class);
        $this->expectExceptionMessage('User does not have permission to perform the requested action');

        RbacLocal::performRoleAuthorizationCheck(
            $policy,
            $subjectRoles,
            $authorizationCheck,
            'User'
        );
    }
}
