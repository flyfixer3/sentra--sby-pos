<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BranchContext
{
    public static function active()
    {
        return session('active_branch');
    }

    public static function isAll(): bool
    {
        return self::active() === 'all';
    }

    public static function id(): ?int
    {
        $active = self::active();
        if ($active === 'all' || !$active) {
            return null;
        }
        return (int) $active;
    }

    public static function canViewAll(): bool
    {
        return Gate::allows('view_all_branches');
    }

    public static function set($branch): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $raw = trim((string) $branch);
        $key = strtolower($raw);

        if ($key === 'all') {
            abort_unless(self::canViewAll(), 403);
            session(['active_branch' => 'all']);
            return;
        }

        abort_unless(is_numeric($raw), 403);
        $branchId = (int) $raw;

        $allowedIds = $user->allAvailableBranches()
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        abort_unless(in_array($branchId, $allowedIds, true), 403);

        session(['active_branch' => $branchId]);
    }
}
