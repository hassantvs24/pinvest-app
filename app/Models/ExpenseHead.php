<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class ExpenseHead extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseHeadFactory> */
    use HasFactory;

    /**
     * Scope: only active heads (shown in dropdowns).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
