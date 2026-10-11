<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Full name, so views can keep using $user->name. */
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isDelivery(): bool
    {
        return $this->role === 'delivery';
    }

    /**
     * Delivery staff are the only role the owner manages (create/edit/delete)
     * from the app, since — unlike the owner and cashier — they aren't permanent.
     */
    public function scopeDeliveryStaff(Builder $query): Builder
    {
        return $query->where('role', 'delivery');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderByName(Builder $query): Builder
    {
        return $query->orderBy('first_name')->orderBy('last_name');
    }

    /** Where should this user land after login? */
    public function homeRoute(): string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'cashier' => 'cashier.dashboard',
            'delivery' => 'delivery.dashboard',
            default => 'dashboard',
        };
    }

    public function ordersCreated()
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function deliveries()
    {
        return $this->hasMany(Order::class, 'assigned_delivery_id');
    }
}
