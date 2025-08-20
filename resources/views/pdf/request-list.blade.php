{{-- resources/views/pdf/request-list.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Request List - {{ $request->request_number }}</title>
    <style>
        @page {
            margin: 10mm;
            size: A5 landscape;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 1;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.2;
        }
        
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }
        
        .logo {
            width: 60px;
            height: 40px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #f9ca24);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 8px;
            margin-right: 10px;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            padding-left: 4px;
            color: #333;
        }
        
        .company-subtitle {
            font-size: 8px;
            color: #666;
        }
        
        .document-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .document-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .table-container {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .table-container th,
        .table-container td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            vertical-align: middle;
        }
        
        .table-container th {
            background-color: #ffeb3b;
            font-weight: bold;
            font-size: 10px;
        }
        
        .table-container td {
            font-size: 10px;
            min-height: 25px;
        }
        
        .col-no { width: 5%; }
        .col-description { width: 25%; }
        .col-specification { width: 35%; }
        .col-qty { width: 8%; }
        .col-uom { width: 10%; }
        .col-remarks { width: 17%; }
        
        .signature-section {
            margin-top: 15px;
        }
        
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .signature-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            vertical-align: middle;
            height: 30px;
        }
        
        .signature-header {
            background-color: #ffeb3b;
            font-weight: bold;
            font-size: 12px;
            height: 20%;
        }
        
        .signature-content {
            position: relative;
        }
        
        .signature-role {
            font-size: 10px;
            margin-bottom: 3px;
            font-weight: bold;
        }
        
        .signature-name {
            font-size: 10px;
            margin-top: 3px;
        }
        
        .qr-code {
            width: 75px;
            height: 75px;
            margin: 2px auto;
        }
        
        .notes-section {
            display: flex;
            margin-top: 10px;
        }
        
        .notes-left {
            flex: 1;
            margin-right: 10px;
        }
        
        .notes-right {
            width: 200px;
            border: 1px solid #000;
            padding: 5px;
        }
        
        .notes-item {
            font-size: 7px;
            margin-bottom: 1px;
        }
        
        .text-left { text-align: left !important; }
        .text-center { text-align: center !important; }
        
        .empty-row {
            height: 25px;
        }
    </style>
</head>
<body>
    {{-- Header Section --}}
    <div class="header">
        <div class="company-info">
            <div class="company-name">PT. ADIJAYA KARYA MAKMUR</div>
            <div class="company-subtitle">Excellence in Construction & Engineering</div>
        </div>
    </div>

    {{-- Document Title --}}
    <div class="document-title">REQUEST LIST</div>

    {{-- Document Info --}}
    <div class="document-info">
        <div><strong>Request No:</strong> {{ $request->request_number }}</div>
        <div><strong>DATE:</strong> {{ $request->request_date->format('d/m/Y') }}</div>
    </div>

    {{-- Main Table --}}
    <table class="table-container">
        <thead>
            <tr>
                <th class="col-no">NO</th>
                <th class="col-description">DESCRIPTION</th>
                <th class="col-specification">SPECIFICATION</th>
                <th class="col-qty">QTY</th>
                <th class="col-uom">UOM</th>
                <th class="col-remarks">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($request->items as $item)
            <tr>
                <td>{{ $item->item_number }}</td>
                <td class="text-left">{{ $item->description }}</td>
                <td class="text-left">{{ $item->specification }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->unit_of_measurement }}</td>
                <td class="text-left">{{ $item->remarks }}</td>
            </tr>
            @endforeach
            
            {{-- Fill empty rows to make 6 total --}}
            @for($i = count($request->items); $i < 6; $i++)
            <tr class="empty-row">
                <td>{{ $i + 1 }}</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            @endfor
        </tbody>
    </table>

    {{-- Signature Section --}}
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td colspan="4" class="signature-header">APPROVALS</td>
            </tr>
            <tr>
                <td style="width: 25%;">
                    <div class="signature-role">Request By,</div>
                    <div class="signature-content">
                        @if($signatures['requester'])
                            <img src="{{ $signatures['requester'] }}" class="qr-code" alt="Requester QR">
                        @endif
                    </div>
                    <div class="signature-name">
                        {{ $request->user->name }}<br>
                        <small>User</small>
                    </div>
                </td>
                <td style="width: 25%;">
                    <div class="signature-role">Responsible By,</div>
                    <div class="signature-content">
                        @if($signatures['section_head'])
                            <img src="{{ $signatures['section_head'] }}" class="qr-code" alt="Section Head QR">
                        @endif
                    </div>
                    <div class="signature-name">
                        @php
                            $sectionHead = $request->approvals->where('role', 'section_head')->where('status', 'approved')->first();
                        @endphp
                        @if($sectionHead)
                            {{ $sectionHead->user->name }}<br>
                            <small>SPV/Section Head Dept.</small>
                        @else
                            <br><small>SPV/Section Head Dept.</small>
                        @endif
                    </div>
                </td>
                <td style="width: 25%;">
                    <div class="signature-role">Checked By,</div>
                    <div class="signature-content">
                        @if($signatures['scm_head'])
                            <img src="{{ $signatures['scm_head'] }}" class="qr-code" alt="SCM Head QR">
                        @endif
                    </div>
                    <div class="signature-name">
                        @php
                            $scmHead = $request->approvals->where('role', 'scm_head')->where('status', 'approved')->first();
                        @endphp
                        @if($scmHead)
                            {{ $scmHead->user->name }}<br>
                            <small>SPV/Section Head SCM</small>
                        @else
                            <br><small>SPV/Section Head SCM</small>
                        @endif
                    </div>
                </td>
                <td style="width: 25%;">
                    <div class="signature-role">Approved By,</div>
                    <div class="signature-content">
                        @if($signatures['pjo'])
                            <img src="{{ $signatures['pjo'] }}" class="qr-code" alt="PJO QR">
                        @endif
                    </div>
                    <div class="signature-name">
                        @php
                            $pjo = $request->approvals->where('role', 'pjo')->where('status', 'approved')->first();
                        @endphp
                        @if($pjo)
                            {{ $pjo->user->name }}<br>
                            <small>PJO</small>
                        @else
                            <br><small>PJO</small>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Notes Section --}}
    <div class="notes-section">
        <div class="notes-right">
            @if($request->notes)
                <strong style="font-size: 8px;">Notes:</strong><br>
                <span style="font-size: 7px;">{{ $request->notes }}</span>
            @endif
        </div>
    </div>
</body>
</html>