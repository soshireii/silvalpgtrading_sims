<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'phone', 'address'];

    /** Full name, so views can keep using $customer->name. */
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Match a search box value against first name, last name, "first last" or phone.
     * (No CONCAT(): it isn't available on every database, e.g. SQLite in the tests.)
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $like = "%{$term}%";

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like);

            if (str_contains($term, ' ')) {
                [$first, $last] = explode(' ', $term, 2);
                $q->orWhere(fn (Builder $n) => $n
                    ->where('first_name', 'like', "%{$first}%")
                    ->where('last_name', 'like', '%' . trim($last) . '%'));
            }
        });
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
