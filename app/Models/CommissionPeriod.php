<?php

namespace App\Models;

use Database\Factories\CommissionPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'opened_at', 'opening_cash', 'note', 'status', 'profit', 'closed_at'])]
class CommissionPeriod extends Model
{
    /** @use HasFactory<CommissionPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
            'opening_cash' => 'decimal:2',
            'profit' => 'decimal:2',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }
}
