<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'request_date',
        'company_id',
        'user_id',
        'department_id',
        'status',
        'notes',
        'signature_data'
    ];

    protected $casts = [
        'request_date' => 'date',
        'signature_data' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->request_number)) {
                $model->request_number = static::generateRequestNumber($model->company_id);
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class)->orderBy('item_number');
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

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendingApproval($query, string $role, $companyId = null)
    {
        return $query->whereHas('approvals', function ($q) use ($role, $companyId) {
            $q->where('role', $role)->where('status', 'pending');
            
            if ($companyId && $role !== 'scm_head') {
                // SCM is centralized, others are company-specific
                $q->whereHas('request', function ($requestQ) use ($companyId) {
                    $requestQ->where('company_id', $companyId);
                });
            }
        });
    }

    // Methods
    public function canBeEditedBy(User $user): bool
    {
        return $this->status === 'draft' 
            && $this->user_id === $user->id
            && $this->company_id === $user->company_id;
    }

    public function canBeApprovedBy(User $user): bool
    {
        return match ($this->status) {
            'submitted' => $user->canApproveSectionRequest($this),
            'section_approved' => $user->canApproveSCMRequest($this),
            'scm_approved' => $user->canApproveFinalRequest($this),
            default => false
        };
    }

    public static function generateRequestNumber($companyId = null): string
    {
        $company = Company::find($companyId);
        $companyCode = $company ? $company->code : 'GEN';
        $date = now()->format('Ymd');
        
        $sequence = static::whereDate('created_at', now())
            ->when($companyId, function ($q) use ($companyId) {
                return $q->where('company_id', $companyId);
            })
            ->count() + 1;
            
        return "REQ-{$companyCode}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'secondary',
            'submitted' => 'warning',
            'section_approved' => 'info',
            'scm_approved' => 'primary',
            'completed' => 'success',
            'rejected' => 'danger',
            default => 'secondary'
        };
    }

    public function getFullIdentifierAttribute(): string
    {
        return "{$this->request_number} - {$this->company?->code}";
    }
}