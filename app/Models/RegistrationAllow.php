<?php

namespace App\Models;

use Database\Factories\RegistrationAllowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'email', 'used_at'])]
class RegistrationAllow extends Model
{
    /** @use HasFactory<RegistrationAllowFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    /**
     * Scope: allowances that have not been consumed by a registration yet.
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    /**
     * Scope: allowances already consumed by a registration.
     */
    public function scopeUsed(Builder $query): Builder
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * Mark this allowance as consumed by a registration.
     */
    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Human-readable value: phone or email, whichever is set.
     */
    public function displayValue(): string
    {
        return $this->phone ?? $this->email ?? '—';
    }
}
