<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $with = ['media'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')
            ->useFallbackUrl('https://www.gravatar.com/avatar/' . md5(strtolower(trim($this->email))));
    }

    public function scopeIsActive(Builder $builder)
    {
        return $builder->where('is_active', 1);
    }

    public function branches()
    {
        return $this->belongsToMany(\Modules\Branch\Entities\Branch::class, 'branch_user');
    }

    public function displayRoleName(): string
    {
        return $this->getRoleNames()->first() ?: 'No Role Assigned';
    }

    /**
     * Cabang yang bisa dilihat user
     * - Kalau user punya permission view_all_branches => boleh lihat semua cabang
     * - Kalau tidak => hanya cabang yang ada di pivot branch_user
     */
    public function allAvailableBranches()
    {
        // tetap dukung role Super Admin (kalau kamu pakai)
        $roleNames = $this->getRoleNames()->map(fn ($role) => strtolower((string) $role))->all();
        if (in_array('super admin', $roleNames, true) || in_array('administrator', $roleNames, true)) {
            return \Modules\Branch\Entities\Branch::query()->orderBy('name')->get();
        }

        // permission-based ALL
        if ($this->can('view_all_branches')) {
            return \Modules\Branch\Entities\Branch::query()->orderBy('name')->get();
        }

        // kalau bukan ALL, return cabang pivot (Collection)
        return $this->branches()->orderBy('name')->get();
    }
}
