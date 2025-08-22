<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                try {
                    $model->request_number = static::generateRequestNumber(
                        $model->company_id, 
                        $model->department_id
                    );
                } catch (\Exception $e) {
                    Log::error("Failed to generate request number: " . $e->getMessage());
                    throw new \Exception("Unable to generate request number. Please try again.");
                }
            }
        });
    }

    // ✅ RELATIONSHIPS
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

    // ✅ SCOPES
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
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

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('approvals', function ($subQ) use ($user) {
                  $subQ->where('user_id', $user->id);
              });
        })->where('company_id', $user->company_id);
    }

    // ✅ MAIN METHODS
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

    public function canBeViewedBy(User $user): bool
    {
        // Admin can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Owner can view
        if ($this->user_id === $user->id) {
            return true;
        }

        // Approvers can view
        if ($this->approvals()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Same company users with relevant roles
        if ($this->company_id === $user->company_id) {
            return $user->hasAnyRole(['section_head', 'scm_head', 'pjo']);
        }

        return false;
    }

    // ✅ REQUEST NUMBER GENERATION
    public static function generateRequestNumber($companyId = null, $departmentId = null): string
    {
        return DB::transaction(function () use ($companyId, $departmentId) {
            // Get company code
            $company = Company::find($companyId);
            $companyCode = $company ? $company->code : 'GEN';
            
            // Get department code
            $department = Department::find($departmentId);
            $departmentCode = $department ? $department->code : 'DEF';
            
            $date = now()->format('Ymd');
            
            // Get last request for same company + department + date
            $lastRequest = static::whereDate('created_at', now())
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();
                
            // Extract sequence from last request number or start from 1
            $sequence = 1;
            if ($lastRequest && !empty($lastRequest->request_number)) {
                $lastSequence = (int) substr($lastRequest->request_number, -4);
                $sequence = $lastSequence + 1;
            }
                
            // Format: REQ-{companyCode}-{departmentCode}-{date}-{sequence}
            return "REQ-{$companyCode}-{$departmentCode}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    public function regenerateRequestNumber(): string
    {
        $newNumber = static::generateRequestNumber($this->company_id, $this->department_id);
        $this->update(['request_number' => $newNumber]);
        return $newNumber;
    }

    // ✅ STATUS MANAGEMENT
    public function submit(): bool
    {
        if ($this->status !== 'draft') {
            return false;
        }

        $this->update(['status' => 'submitted']);
        $this->createApprovalRecords();
        
        return true;
    }

    public function reject(string $reason = null): bool
    {
        $this->update([
            'status' => 'rejected',
            'notes' => $reason ? $this->notes . "\n\nRejection: " . $reason : $this->notes
        ]);

        return true;
    }

    public function getNextApprovalRole(): ?string
    {
        return match ($this->status) {
            'submitted' => 'section_head',
            'section_approved' => 'scm_head',
            'scm_approved' => 'pjo',
            default => null
        };
    }

    public function getPendingApprovals()
    {
        $nextRole = $this->getNextApprovalRole();
        
        if (!$nextRole) {
            return collect();
        }

        return $this->approvals()
            ->where('role', $nextRole)
            ->where('status', 'pending')
            ->with('user')
            ->get();
    }

    // ✅ APPROVAL RECORDS CREATION
    private function createApprovalRecords(): void
    {
        $approvalRoles = [
            ['role' => 'section_head', 'company_specific' => true, 'department_specific' => true],
            ['role' => 'scm_head', 'company_specific' => false, 'department_specific' => false],
            ['role' => 'pjo', 'company_specific' => true, 'department_specific' => false],
        ];

        foreach ($approvalRoles as $approval) {
            $query = User::role($approval['role']);
            
            if ($approval['company_specific']) {
                $query->where('company_id', $this->company_id);
            }
            
            if ($approval['department_specific']) {
                $query->where('department_id', $this->department_id);
            }
            
            $approvers = $query->get();
            
            foreach ($approvers as $approver) {
                Approval::firstOrCreate([
                    'request_id' => $this->id,
                    'user_id' => $approver->id,
                    'role' => $approval['role'],
                ], [
                    'status' => 'pending',
                ]);
            }
        }
    }

    // ✅ HELPER METHODS & ATTRIBUTES
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

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'section_approved' => 'Section Approved',
            'scm_approved' => 'SCM Approved',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            default => 'Unknown'
        };
    }

    public function getFullIdentifierAttribute(): string
    {
        return "{$this->request_number} - {$this->company?->code}";
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getApprovalProgressAttribute(): array
    {
        $roles = ['section_head', 'scm_head', 'pjo'];
        $progress = [];

        foreach ($roles as $role) {
            $approval = $this->approvals()->where('role', $role)->first();
            $progress[$role] = [
                'status' => $approval?->status ?? 'pending',
                'user' => $approval?->user?->name,
                'approved_at' => $approval?->approved_at,
                'comments' => $approval?->comments,
            ];
        }

        return $progress;
    }

    // ✅ QUERY HELPERS
    public static function getStatistics($companyId = null, $departmentId = null): array
    {
        $query = static::query();
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return [
            'total' => $query->count(),
            'draft' => $query->clone()->where('status', 'draft')->count(),
            'submitted' => $query->clone()->where('status', 'submitted')->count(),
            'section_approved' => $query->clone()->where('status', 'section_approved')->count(),
            'scm_approved' => $query->clone()->where('status', 'scm_approved')->count(),
            'completed' => $query->clone()->where('status', 'completed')->count(),
            'rejected' => $query->clone()->where('status', 'rejected')->count(),
            'pending_approval' => $query->clone()->whereIn('status', [
                'submitted', 'section_approved', 'scm_approved'
            ])->count(),
        ];
    }

    public static function getRecentRequests($limit = 10, $companyId = null)
    {
        return static::with(['user', 'company', 'department'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit($limit)
            ->get();
    }

    // ✅ VALIDATION HELPERS
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft']);
    }

    public function isDeletable(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function isApprovable(): bool
    {
        return in_array($this->status, ['submitted', 'section_approved', 'scm_approved']);
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // ✅ SEARCH & FILTERING
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('request_number', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhereHas('user', function ($userQ) use ($search) {
                  $userQ->where('name', 'like', "%{$search}%")
                       ->orWhere('employee_id', 'like', "%{$search}%");
              })
              ->orWhereHas('items', function ($itemQ) use ($search) {
                  $itemQ->where('description', 'like', "%{$search}%")
                       ->orWhere('specification', 'like', "%{$search}%");
              });
        });
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('request_date', [$startDate, $endDate]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }
}