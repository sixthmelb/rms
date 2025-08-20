@if($approval->qr_code_path)
<div class="flex flex-col items-center space-y-4">
    <div class="p-4 bg-white rounded-lg border">
        <img src="{{ Storage::disk('public')->url($approval->qr_code_path) }}" 
             alt="QR Code" 
             class="w-48 h-48">
    </div>
    
    <div class="text-center space-y-2">
        <p class="text-sm text-gray-600">
            Digital signature for {{ $approval->user->name }}
        </p>
        <p class="text-xs text-gray-500">
            Approved at: {{ $approval->approved_at->format('d/m/Y H:i') }}
        </p>
        <p class="text-xs text-gray-400 font-mono">
            Request: {{ $approval->request->request_number }}
        </p>
    </div>
</div>
@else
<div class="text-center py-8">
    <p class="text-gray-500">No QR code available for this approval.</p>
</div>
@endif