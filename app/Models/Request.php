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
        'signature_data',
        'cancellation_reason',
        'cancelled_at'
    ];

    protected $casts = [
        'request_date' => 'date',
        'signature_data' => 'array',
        'cancelled_at' => 'datetime'
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

    // ✅ FIXED: PERMISSION CHECKS
    public function canBeEditedBy(User $user): bool
    {
        // ✅ FIXED: Allow edit for both draft AND revision_requested status
        return in_array($this->status, ['draft', 'revision_requested'])
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

    // ✅ CANCELLATION & REVISION METHODS
    public function canBeCancelledBy(User $user): bool
    {
        // Only requester can cancel
        if ($this->user_id !== $user->id) {
            return false;
        }

        // Can only cancel if not completed/rejected/cancelled
        return in_array($this->status, [
            'draft', 'submitted', 'section_approved', 'scm_approved'
        ]);
    }

    public function canRequestRevisionBy(User $user): bool
    {
        // Only approvers can request revision
        $hasApprovalRole = $this->approvals()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if (!$hasApprovalRole) {
            return false;
        }

        // Can request revision if request is in active approval process
        return in_array($this->status, [
            'submitted', 'section_approved', 'scm_approved'
        ]);
    }

    public function cancelRequest(string $reason): bool
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now()
        ]);

        // Cancel all pending approvals
        $this->approvals()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return true;
    }

    public function requestRevision(User $approver, string $reason): bool
    {
        // Create revision record in approval
        $approval = $this->approvals()
            ->where('user_id', $approver->id)
            ->where('status', 'pending')
            ->first();

        if (!$approval) {
            return false;
        }

        $approval->update([
            'status' => 'revision_requested',
            'comments' => $reason
        ]);

        // Update request status to revision_requested
        $this->update([
            'status' => 'revision_requested',
            'notes' => $this->notes ? 
                $this->notes . "\n\nRevision Requested: " . $reason : 
                "Revision Requested: " . $reason
        ]);

        return true;
    }

    public function resubmitAfterRevision(): bool
    {
        if ($this->status !== 'revision_requested') {
            return false;
        }

        // Reset to submitted status
        $this->update(['status' => 'submitted']);

        // Reset all approvals to pending (restart workflow)
        $this->approvals()->update(['status' => 'pending']);

        return true;
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
            $departmentCode = $department ? $department->code : 'GEN';
            
            // Get current year and month
            $year = date('Y');
            $month = date('m');
            
            // Create base format: COMPANY-DEPT-YYYYMM
            $baseNumber = "{$companyCode}-{$departmentCode}-{$year}{$month}";
            
            // Get last sequence number for this month
            $lastRequest = static::where('request_number', 'LIKE', $baseNumber . '%')
                ->orderByRaw('CAST(RIGHT(request_number, 4) AS UNSIGNED) DESC')
                ->first();
            
            $sequence = 1;
            if ($lastRequest) {
                $lastSequence = (int) substr($lastRequest->request_number, -4);
                $sequence = $lastSequence + 1;
            }
            
            // Generate final request number: COMPANY-DEPT-YYYYMM-NNNN
            return $baseNumber . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        });
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
            'cancelled' => 'gray',
            'revision_requested' => 'orange'
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
            'cancelled' => 'Cancelled',
            'revision_requested' => 'Revision Requested',
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
            'cancelled' => $query->clone()->where('status', 'cancelled')->count(),
            'revision_requested' => $query->clone()->where('status', 'revision_requested')->count(),
        ];
    }

    // ✅ VALIDATION HELPERS
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'revision_requested']);
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRevisionRequested(): bool
    {
        return $this->status === 'revision_requested';
    }
}