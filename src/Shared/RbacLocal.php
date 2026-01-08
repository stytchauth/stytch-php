<?php

namespace Stytch\Shared;

use Stytch\Core\StytchException;

/**
 * RBAC authorization helpers for local permission checking
 *
 * Mirrors the logic from stytch-node/lib/shared/rbac_local.ts
 */
class RbacLocal
{
    /**
     * Perform role-based authorization check
     *
     * Checks if the subject's roles grant permission for the requested resource/action
     *
     * @param array $policy RBAC policy with roles and permissions
     * @param array $subjectRoles Array of role IDs assigned to the subject
     * @param array $authorizationCheck Authorization check with resource_id and action
     * @param string $callerType Type of caller (e.g., 'User', 'Member')
     * @throws StytchException If authorization check fails
     */
    public static function performRoleAuthorizationCheck(
        array $policy,
        array $subjectRoles,
        array $authorizationCheck,
        string $callerType
    ): void {
        $resourceId = $authorizationCheck['resource_id'] ?? '';
        $action = $authorizationCheck['action'] ?? '';

        $roles = $policy['roles'] ?? [];

        // Check if any of the subject's roles grant permission
        $hasPermission = false;
        foreach ($roles as $role) {
            $roleId = $role['role_id'] ?? '';

            // Skip if this role is not assigned to the subject
            if (!in_array($roleId, $subjectRoles, true)) {
                continue;
            }

            // Check if this role has permissions for the resource
            $permissions = $role['permissions'] ?? [];
            foreach ($permissions as $permission) {
                $permResourceId = $permission['resource_id'] ?? '';
                $permActions = $permission['actions'] ?? [];

                // Check if resource matches and action is allowed
                if ($permResourceId === $resourceId) {
                    if (in_array($action, $permActions, true) || in_array('*', $permActions, true)) {
                        $hasPermission = true;
                        break 2; // Break out of both loops
                    }
                }
            }
        }

        if (!$hasPermission) {
            throw new StytchException(
                "{$callerType} does not have permission to perform the requested action",
                0
            );
        }
    }

    /**
     * Perform scope-based authorization check
     *
     * Checks if the token's scopes grant permission for the requested resource/action
     *
     * @param array $policy RBAC policy with scopes and permissions
     * @param array $tokenScopes Array of scope strings from the token
     * @param array $authorizationCheck Authorization check with resource_id and action
     * @param string $callerType Type of caller (e.g., 'User', 'Member')
     * @throws StytchException If authorization check fails
     */
    public static function performScopeAuthorizationCheck(
        array $policy,
        array $tokenScopes,
        array $authorizationCheck,
        string $callerType
    ): void {
        $resourceId = $authorizationCheck['resource_id'] ?? '';
        $action = $authorizationCheck['action'] ?? '';

        $scopes = $policy['scopes'] ?? [];

        // Check if any of the token's scopes grant permission
        $hasPermission = false;
        foreach ($scopes as $scope) {
            $scopeId = $scope['scope'] ?? '';

            // Skip if this scope is not in the token
            if (!in_array($scopeId, $tokenScopes, true)) {
                continue;
            }

            // Check if this scope has permissions for the resource
            $permissions = $scope['permissions'] ?? [];
            foreach ($permissions as $permission) {
                $permResourceId = $permission['resource_id'] ?? '';
                $permActions = $permission['actions'] ?? [];

                // Check if resource matches and action is allowed
                if ($permResourceId === $resourceId) {
                    if (in_array($action, $permActions, true) || in_array('*', $permActions, true)) {
                        $hasPermission = true;
                        break 2; // Break out of both loops
                    }
                }
            }
        }

        if (!$hasPermission) {
            throw new StytchException(
                "{$callerType} does not have permission to perform the requested action",
                0
            );
        }
    }
}
