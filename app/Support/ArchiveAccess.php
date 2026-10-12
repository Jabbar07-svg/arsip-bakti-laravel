<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;

class ArchiveAccess
{
    public static function canManage(User $user): bool
    {
        return RolePermissions::allows($user, RolePermissions::ARCHIVES_MANAGE);
    }

    public static function canAccessListing(User $user): bool
    {
        return RolePermissions::allows($user, RolePermissions::ARCHIVES_LIST);
    }

    public static function canView(User $user, object|array $archive): bool
    {
        $classification = (string) data_get($archive, 'classification', 'Internal');

        if (RolePermissions::isSuperAdmin($user)) {
            return true;
        }

        if (
            RolePermissions::isDivisionScoped($user)
            && ! RolePermissions::matchesDivision($user, data_get($archive, 'division'))
        ) {
            return false;
        }

        if (
            RolePermissions::isTargetRole($user)
            && ! RolePermissions::allows($user, RolePermissions::ARCHIVES_VIEW)
        ) {
            return false;
        }

        if ($classification === 'Dokumen Rahasia') {
            return false;
        }

        if ($classification === 'Publik') {
            return true;
        }

        if (RolePermissions::allows($user, RolePermissions::CUSTOMER_PORTAL)) {
            return false;
        }

        if ($classification === 'Internal') {
            return RolePermissions::allows($user, RolePermissions::ARCHIVES_VIEW);
        }

        return $classification === 'Internal Divisi'
            && RolePermissions::allows($user, RolePermissions::ARCHIVES_VIEW)
            && RolePermissions::matchesDivision($user, (string) data_get($archive, 'division', ''));
    }

    public static function scope(Builder $query, User $user, string $alias = 'a'): Builder
    {
        if (RolePermissions::isSuperAdmin($user)) {
            return $query;
        }

        if (RolePermissions::isDivisionScoped($user)) {
            return RolePermissions::scopeToDivision($query, $user, $alias);
        }

        if (
            RolePermissions::isTargetRole($user)
            && ! RolePermissions::allows($user, RolePermissions::ARCHIVES_VIEW)
        ) {
            return $query->whereRaw('1 = 0');
        }

        if (
            RolePermissions::allows($user, RolePermissions::CUSTOMER_PORTAL)
            || ! RolePermissions::allows($user, RolePermissions::ARCHIVES_VIEW)
        ) {
            return $query->where($alias.'.classification', 'Publik');
        }

        return $query->where(function (Builder $visible) use ($user, $alias): void {
            $visible
                ->whereIn($alias.'.classification', ['Publik', 'Internal'])
                ->orWhere(function (Builder $division) use ($user, $alias): void {
                    $division
                        ->where($alias.'.classification', 'Internal Divisi')
                        ->whereNotNull($alias.'.division')
                        ->where($alias.'.division', trim((string) $user->division));
                });
        });
    }
}
