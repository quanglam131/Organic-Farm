<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    // Farm staff: crops, diary, batches, QR codes, waste. Never sees orders or customers.
    public const ROLE_STAFF = 'staff';
    // Sales / customer care: orders, shipping, complaints. Never changes stock or batches.
    public const ROLE_SALES = 'sales';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [self::ROLE_CUSTOMER, self::ROLE_STAFF, self::ROLE_SALES, self::ROLE_ADMIN];

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'points_balance',
        'is_active',
    ];

    // Match the column defaults, so a freshly created model already knows them.
    protected $attributes = [
        'role' => self::ROLE_CUSTOMER,
        'is_active' => true,
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
            'points_balance' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Admins pass every role check; everyone else must hold one of the given roles. */
    public function hasRole(string ...$roles): bool
    {
        return $this->isAdmin() || in_array($this->role, $roles, true);
    }

    public function isEmployee(): bool
    {
        return $this->role !== self::ROLE_CUSTOMER;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }
}
