<?php
// app/Models/Department.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'code',
        'company_id'
    ];

    // ✅ RELATIONSHIPS
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    // ✅ SCOPES
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->whereHas('users');
    }

    public function scopeWithSectionHead($query)
    {
        return $query->whereHas('users', function ($q) {
            $q->role('section_head');
        });
    }

    public function scopeWithoutSectionHead($query)
    {
        return $query->whereDoesntHave('users', function ($q) {
            $q->role('section_head');
        });
    }

    // ✅ HELPER METHODS
    public function getSectionHead()
    {
        return $this->users()->role('section_head')->first();
    }

    public function getActiveRequestsCount(): int
    {
        return $this->requests()
            ->whereNotIn('status', ['completed', 'rejected'])
            ->count();
    }

    public function getCompletedRequestsCount(): int
    {
        return $this->requests()
            ->where('status', 'completed')
            ->count();
    }

    public function getTotalUsersCount(): int
    {
        return $this->users()->count();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->company?->code} - {$this->name} ({$this->code})";
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    // ✅ BUSINESS LOGIC
    public function assignSectionHead(User $user): bool
    {
        // Validate user belongs to this department
        if ($user->department_id !== $this->id) {
            return false;
        }

        // Remove existing section head role from other users in this department
        $this->users()->role('section_head')->each(function ($existingHead) {
            $existingHead->removeRole('section_head');
        });

        // Assign section head role to new user
        $user->assignRole('section_head');
        
        return true;
    }

    public function removeSectionHead(): bool
    {
        $sectionHead = $this->getSectionHead();
        
        if ($sectionHead) {
            $sectionHead->removeRole('section_head');
            return true;
        }
        
        return false;
    }

    public function canBeDeleted(): bool
    {
        return $this->users()->count() === 0 && 
               $this->requests()->count() === 0;
    }

    // ✅ COMPUTED ATTRIBUTES
    public function getStatusAttribute(): string
    {
        if ($this->getSectionHead()) {
            return 'active';
        }
        
        if ($this->users()->count() > 0) {
            return 'needs_section_head';
        }
        
        return 'inactive';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'needs_section_head' => 'warning',
            'inactive' => 'danger',
            default => 'secondary'
        };
    }

    // ✅ VALIDATION HELPERS
    public function isCodeUniqueInCompany(string $code, ?int $excludeId = null): bool
    {
        $query = static::where('company_id', $this->company_id)
            ->where('code', $code);
            
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    // ✅ QUERY HELPERS
    public static function getAvailableForSectionHead(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereDoesntHave('users', function ($q) {
            $q->role('section_head');
        })->get();
    }

    public static function getByCompany(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('company_id', $companyId)
            ->with(['users', 'company'])
            ->orderBy('name')
            ->get();
    }
}