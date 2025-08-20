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
                $model->request_number = static::generateRequestNumber();
            }
        });
    }

    // Relationships
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
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendingApproval($query, string $role)
    {
        return $query->whereHas('approvals', function ($q) use ($role) {
            $q->where('role', $role)->where('status', 'pending');
        });
    }

    // Methods
    public function canBeEditedBy(User $user): bool
    {
        return $this->status === 'draft' && $this->user_id === $user->id;
    }

    public function canBeApprovedBy(User $user): bool
    {
        $userRoles = $user->getRoleNames()->toArray();
        
        return match ($this->status) {
            'submitted' => in_array('section_head', $userRoles) && $user->department_id === $this->department_id,
            'section_approved' => in_array('scm_head', $userRoles),
            'scm_approved' => in_array('pjo', $userRoles),
            default => false
        };
    }

    public static function generateRequestNumber(): string
    {
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', now())->count() + 1;
        return "REQ-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
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
}