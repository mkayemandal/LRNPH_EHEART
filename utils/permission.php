<?php

class Permission
{
    private const MAP = [
        'MANAGER'      => ['request.create', 'request.view.own', 'card.complete', 'card.view.own'],
        'HR_ADMIN'     => ['request.view.all', 'card.release', 'card.view.all', 'redemption.validate', 'gc.release', 'reports.view'],
        'SYSTEM_ADMIN' => ['users.manage', 'roles.manage', 'corevalues.manage', 'settings.manage', 'audit.view'],
    ];

    public static function can(string $roleCode, string $capability): bool
    {
        // System Administrator has unrestricted access to all eHeart capabilities.
        if ($roleCode === 'SYSTEM_ADMIN') {
            return true;
        }

        return in_array($capability, self::MAP[$roleCode] ?? [], true);
    }

    public static function requireCapability(string $roleCode, string $capability): void
    {
        if (!self::can($roleCode, $capability)) {
            Response::error('Forbidden. Missing capability: ' . $capability, 403);
        }
    }
}
