<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'user_id',
        'role',
        'status',
        'comments',
        'qr_code_path',
        'qr_code_data',
        'approved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime'
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approve(string $comments = null): void
    {
        $this->update([
            'status' => 'approved',
            'comments' => $comments,
            'approved_at' => now()
        ]);

        $this->generateQRCode();
        $this->updateRequestStatus();
    }

    public function reject(string $comments): void
    {
        $this->update([
            'status' => 'rejected',
            'comments' => $comments
        ]);

        $this->request->update(['status' => 'rejected']);
    }

    public function canBeApprovedBy(User $user): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return match ($this->role) {
            'section_head' => $user->can('approve_section_requests') && 
                            $user->department_id === $this->request->department_id,
            'scm_head' => $user->can('approve_scm_requests'),
            'pjo' => $user->can('approve_final_requests'),
            default => false
        };
    }

    private function generateQRCode(): void
    {
        $qrData = json_encode([
            'request_id' => $this->request_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'approved_at' => $this->approved_at->toISOString(),
            'hash' => hash('sha256', $this->request_id . $this->user_id . $this->role . $this->approved_at)
        ]);

        $fileName = "qr_codes/approval_{$this->id}_{$this->role}.png";
        
        $qrCode = QrCode::format('png')
            ->size(100)
            ->margin(1)
            ->generate($qrData);

        Storage::disk('public')->put($fileName, $qrCode);

        $this->update([
            'qr_code_path' => $fileName,
            'qr_code_data' => $qrData
        ]);
    }

    private function updateRequestStatus(): void
    {
        $nextStatus = match ($this->role) {
            'section_head' => 'section_approved',
            'scm_head' => 'scm_approved',
            'pjo' => 'completed',
            default => $this->request->status
        };

        $this->request->update(['status' => $nextStatus]);
    }
}