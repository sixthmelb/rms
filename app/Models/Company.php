<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code', 
        'address',
        'phone',
        'email',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper methods
    public function getPJOUsers()
    {
        return $this->users()->role('pjo')->get();
    }

    public function getSectionHeads()
    {
        return $this->users()->role('section_head')->get();
    }

    public function getSectionHeadForDepartment($departmentId)
    {
        return $this->users()
            ->role('section_head')
            ->where('department_id', $departmentId)
            ->first();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }
}