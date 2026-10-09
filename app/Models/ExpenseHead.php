<?php

namespace App\Models;

use App\Enums\ExpenseCostType;
use Database\Factories\ExpenseHeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'cost_type', 'is_active'])]
class ExpenseHead extends Model
{
    /** @use HasFactory<ExpenseHeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_type' => ExpenseCostType::class,
        ];
    }

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
