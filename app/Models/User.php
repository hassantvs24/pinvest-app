<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'commission_rate', 'preferred_language', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Whether this user is the business owner.
     */
    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope: only partner accounts.
     */
    public function scopePartners(Builder $query): Builder
    {
        return $query->where('role', UserRole::Partner);
    }

    /**
     * Scope: only active accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function stockLosses(): HasMany
    {
        return $this->hasMany(StockLoss::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function confirmedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'confirmed_by');
    }

    public function confirmedPurchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'confirmed_by');
    }

    public function confirmedSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'confirmed_by');
    }

    /**
     * Number of this partner's entries awaiting owner confirmation.
     */
    public function pendingEntriesCount(): int
    {
        return $this->expenses()->pending()->count()
            + $this->purchases()->pending()->count()
            + $this->sales()->pending()->count()
            + $this->productions()->pending()->count()
            + $this->stockLosses()->pending()->count();
    }

    /**
     * Total confirmed sales amount (for the partner leaderboard).
     */
    public function confirmedSalesTotal(): float
    {
        return (float) $this->sales()->confirmed()->sum('total');
    }

    /**
     * Total confirmed purchase amount (for the partner leaderboard).
     */
    public function confirmedPurchasesTotal(): float
    {
        return (float) $this->purchases()->confirmed()->sum('total');
    }

    /**
     * Total confirmed expense amount (for the partner leaderboard).
     */
    public function confirmedExpensesTotal(): float
    {
        return (float) $this->expenses()->confirmed()->sum('amount');
    }
}
