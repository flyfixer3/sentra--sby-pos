<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait HasBranchScope
{
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

            if (Schema::hasColumn($table, 'branch_id')) {
                $builder->where($table . '.branch_id', (int) $active);
            }
        });
    }
}
