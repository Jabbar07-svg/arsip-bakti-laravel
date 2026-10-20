<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;

final class RolePermissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const ARCHIVES_LIST = 'archives.list';

    public const ARCHIVES_VIEW = 'archives.view';

    public const ARCHIVES_MANAGE = 'archives.manage';

    public const ARCHIVES_MANAGE_CONFIDENTIAL = 'archives.manage_confidential';

    public const QR_SCAN = 'qr.scan';

    public const QR_MANAGE = 'qr.manage';

    public const LOANS_MANAGE = 'loans.manage';

    public const LOANS_REQUEST = 'loans.request';

    public const LOANS_APPROVE = 'loans.approve';

    public const DISTRIBUTIONS_REQUEST = 'distributions.request';

    public const DISTRIBUTIONS_APPROVE = 'distributions.approve';

    public const DISTRIBUTIONS_DISPATCH = 'distributions.dispatch';

    public const DISTRIBUTIONS_RECEIVE = 'distributions.receive';

    public const RETENTION_VIEW = 'retention.view';

    public const RETENTION_SCHEDULE = 'retention.schedule';

    public const RETENTION_PROPOSE = 'retention.propose';

    public const RETENTION_REVIEW = 'retention.review';

    public const CUSTOMER_PORTAL = 'customer.portal';

    public const CUSTOMER_REVIEW = 'customer.review';

    public const DUPLICATES_REVIEW = 'duplicates.review';

    public const REPORTS_VIEW = 'reports.view';

    public const IMPORTS_MANAGE = 'imports.manage';

    public const CATEGORIES_MANAGE = 'categories.manage';

    public const LOCATIONS_MANAGE = 'locations.manage';

    public const HISTORY_VIEW = 'history.view';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES = [
        'Admin',
        'Petugas Arsip',
        'Pegawai',
    ];

    public const LEGACY_ROLES = [
        'Super Admin',
        'Petugas Gudang',
        'Admin Unit/Petugas Arsip',
        'Verifikator',
        'Pimpinan/Approver',
        'Customer',
        'Pengguna Umum',
        'Kepala Divisi',
    ];

    public const STAFF_ASSIGNABLE_ROLES = [
        'Petugas Arsip',
        'Pegawai',
    ];

    private const GLOBAL_PERMISSIONS = [
        self::DASHBOARD_VIEW,
        self::ARCHIVES_LIST,
        self::ARCHIVES_VIEW,
        self::ARCHIVES_MANAGE,
        self::ARCHIVES_MANAGE_CONFIDENTIAL,
        self::QR_SCAN,
        self::QR_MANAGE,
        self::LOANS_MANAGE,
        self::LOANS_REQUEST,
        self::LOANS_APPROVE,
        self::DISTRIBUTIONS_REQUEST,
        self::DISTRIBUTIONS_APPROVE,
        self::DISTRIBUTIONS_DISPATCH,
        self::DISTRIBUTIONS_RECEIVE,
        self::RETENTION_VIEW,
        self::RETENTION_SCHEDULE,
        self::RETENTION_PROPOSE,
        self::RETENTION_REVIEW,
        self::DUPLICATES_REVIEW,
        self::REPORTS_VIEW,
        self::IMPORTS_MANAGE,
        self::CATEGORIES_MANAGE,
        self::LOCATIONS_MANAGE,
        self::HISTORY_VIEW,
        self::USERS_MANAGE,
    ];

    private const ROLE_PERMISSIONS = [
        'Admin' => [
            self::DASHBOARD_VIEW,
            self::ARCHIVES_LIST,
            self::ARCHIVES_VIEW,
            self::ARCHIVES_MANAGE,
            self::ARCHIVES_MANAGE_CONFIDENTIAL,
            self::QR_SCAN,
            self::QR_MANAGE,
            self::LOANS_MANAGE,
            self::LOANS_APPROVE,
            self::DISTRIBUTIONS_REQUEST,
            self::DISTRIBUTIONS_APPROVE,
            self::DISTRIBUTIONS_DISPATCH,
            self::DISTRIBUTIONS_RECEIVE,
            self::RETENTION_VIEW,
            self::RETENTION_SCHEDULE,
            self::RETENTION_PROPOSE,
            self::RETENTION_REVIEW,
            self::DUPLICATES_REVIEW,
            self::REPORTS_VIEW,
            self::IMPORTS_MANAGE,
            self::CATEGORIES_MANAGE,
            self::LOCATIONS_MANAGE,
            self::HISTORY_VIEW,
            self::USERS_MANAGE,
        ],
        'Petugas Arsip' => [
            self::DASHBOARD_VIEW,
            self::ARCHIVES_LIST,
            self::ARCHIVES_VIEW,
            self::ARCHIVES_MANAGE,
            self::QR_SCAN,
            self::QR_MANAGE,
            self::LOANS_MANAGE,
            self::DISTRIBUTIONS_REQUEST,
            self::DISTRIBUTIONS_DISPATCH,
            self::DISTRIBUTIONS_RECEIVE,
            self::RETENTION_VIEW,
            self::RETENTION_PROPOSE,
            self::DUPLICATES_REVIEW,
        ],
        'Pegawai' => [
            self::DASHBOARD_VIEW,
            self::HISTORY_VIEW,
            self::ARCHIVES_LIST,
            self::ARCHIVES_VIEW,
            self::QR_SCAN,
            self::LOANS_REQUEST,
            self::DISTRIBUTIONS_REQUEST,
        ],
    ];

    public static function allows(User $user, string $permission): bool
    {
        if (! in_array($permission, self::GLOBAL_PERMISSIONS, true)) {
            return false;
        }

        return in_array($permission, self::ROLE_PERMISSIONS[$user->role] ?? [], true);
    }

    public static function isAdmin(User $user): bool
    {
        return $user->role === 'Admin';
    }

    public static function hasSupportedRole(User $user): bool
    {
        return in_array($user->role, self::ROLES, true);
    }

    public static function landingRouteName(User $user): string
    {
        return $user->role === 'Pegawai' ? 'pegawai.dashboard' : 'dashboard';
    }

    public static function isDivisionScoped(User $user): bool
    {
        return in_array($user->role, ['Petugas Arsip', 'Pegawai'], true);
    }

    public static function isTargetRole(User $user): bool
    {
        return self::hasSupportedRole($user);
    }

    public static function matchesDivision(User $user, ?string $division): bool
    {
        $userDivision = mb_strtolower(trim((string) $user->division));
        $recordDivision = mb_strtolower(trim((string) $division));

        return $userDivision !== '' && $recordDivision !== '' && $userDivision === $recordDivision;
    }

    public static function scopeToDivision(Builder $query, User $user, string $alias): Builder
    {
        if (! self::isDivisionScoped($user)) {
            return $query;
        }

        $division = mb_strtolower(trim((string) $user->division));
        if ($division === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereRaw('LOWER(TRIM('.$alias.'.division)) = ?', [$division])
            ->where($alias.'.classification', '<>', 'Dokumen Rahasia');
    }
}
