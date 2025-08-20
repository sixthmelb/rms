<?php
// app/Http/Controllers/RequestPdfController.php
namespace App\Http\Controllers;

use App\Models\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class RequestPdfController extends Controller
{
    public function downloadPdf(Request $request)
    {
        $request->load(['user', 'department', 'items', 'approvals.user']);
        
        // Get signature QR codes
        $signatures = $this->getSignatures($request);
        
        $pdf = Pdf::loadView('pdf.request-list', [
            'request' => $request,
            'signatures' => $signatures,
        ]);
        
        // Set paper size to A5 landscape
        $pdf->setPaper('A5', 'landscape');
        
        $fileName = "Request_List_{$request->request_number}.pdf";
        
        return $pdf->download($fileName);
    }

    private function getSignatures(Request $request): array
    {
        $signatures = [
            'requester' => null,
            'section_head' => null,
            'scm_head' => null,
            'pjo' => null,
        ];

        // Requester signature (always show if submitted)
        if ($request->status !== 'draft') {
            $signatures['requester'] = $this->generateRequesterQRCode($request);
        }

        // Approval signatures
        foreach ($request->approvals as $approval) {
            if ($approval->status === 'approved' && $approval->qr_code_path) {
                $role = $approval->role === 'scm_head' ? 'scm_head' : $approval->role;
                $signatures[$role] = Storage::disk('public')->path($approval->qr_code_path);
            }
        }

        return $signatures;
    }

    private function generateRequesterQRCode(Request $request): string
    {
        $qrData = json_encode([
            'request_id' => $request->id,
            'user_id' => $request->user_id,
            'role' => 'requester',
            'submitted_at' => $request->updated_at->toISOString(),
            'hash' => hash('sha256', $request->id . $request->user_id . 'requester' . $request->updated_at)
        ]);

        $fileName = "qr_codes/requester_{$request->id}.png";
        
        if (!Storage::disk('public')->exists($fileName)) {
            $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size(80)
                ->margin(1)
                ->generate($qrData);

            Storage::disk('public')->put($fileName, $qrCode);
        }

        return Storage::disk('public')->path($fileName);
    }
}