<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait HasBranchScope
{
    public static function bootHasBranchScope()
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (
                session()->has('active_branch') &&
                auth()->check()
            ) {
                $table = $builder->getModel()->getTable();

                if (Schema::hasColumn($table, 'branch_id')) {
                    $builder->where($table . '.branch_id', session('active_branch'));
                }
            }
        });
    }

}
