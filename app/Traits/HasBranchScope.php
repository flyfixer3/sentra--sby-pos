<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait HasBranchScope
{
    protected static array $branchColumnCache = [];

    public static function bootHasBranchScope()
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (!auth()->check() || !session()->has('active_branch')) {
                return;
            }

            $active = session('active_branch');

            if ($active === 'all') {
                return;
            }

            if (!is_numeric($active)) {
                return;
            }

            $table = $builder->getModel()->getTable();

            if (!array_key_exists($table, static::$branchColumnCache)) {
                static::$branchColumnCache[$table] = Schema::hasColumn($table, 'branch_id');
            }

            if (static::$branchColumnCache[$table]) {
                $builder->where($table . '.branch_id', (int) $active);
            }
        });
    }
}
