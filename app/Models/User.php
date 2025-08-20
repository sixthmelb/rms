<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'company_id',
        'department_id',
        'position',
        'signature_path'
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
        ];
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    // Scopes
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->role($role);
    }

    public function scopeSectionHeadsForCompany($query, $companyId)
    {
        return $query->role('section_head')->where('company_id', $companyId);
    }

    public function scopePJOForCompany($query, $companyId)
    {
        return $query->role('pjo')->where('company_id', $companyId);
    }

    public function scopeSCMHeads($query)
    {
        return $query->role('scm_head'); // SCM centralized
    }

    // Helper methods
    public function canApproveSectionRequest(Request $request): bool
    {
        return $this->hasRole('section_head') 
            && $this->company_id === $request->company_id
            && $this->department_id === $request->department_id;
    }

    public function canApproveSCMRequest(Request $request): bool
    {
        return $this->hasRole('scm_head'); // SCM can approve any company
    }

    public function canApproveFinalRequest(Request $request): bool
    {
        return $this->hasRole('pjo') 
            && $this->company_id === $request->company_id;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->employee_id})";
    }

    public function getCompanyDepartmentAttribute(): string
    {
        return "{$this->company?->code} - {$this->department?->code}";
    }
}