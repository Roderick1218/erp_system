{{-- Customer-facing certificate: always printed in English, whatever the UI language. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warranty {{ $w->doc_no }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        .cert { border: 6px double var(--navy); padding: 34px 40px; }
        .cert h1 { text-align: center; color: var(--navy); font-size: 28px; letter-spacing: 3px; margin: 10px 0 4px; }
        .cert .subt { text-align: center; color: #555; margin-bottom: 26px; }
        .cert table.kv { width: 100%; border-collapse: collapse; margin: 14px 0; }
        .cert table.kv td { padding: 8px 6px; border-bottom: 1px solid #e3e7ee; vertical-align: top; }
        .cert table.kv td:first-child { width: 190px; color: #555; }
        .sign { display: flex; justify-content: space-between; margin-top: 60px; gap: 40px; }
        .sign div { flex: 1; border-top: 1px solid #333; padding-top: 6px; font-size: 12px; text-align: center; }
    </style>
</head>
<body style="background:#eef2f8">
<div class="print-bar"><button class="btn btn-primary" onclick="window.print()">{{ __('Print / Save as PDF') }}</button><a class="btn btn-ghost" href="{{ route('warranties.show', $w) }}">{{ __('Back') }}</a></div>
<div class="doc" style="margin-bottom:30px">
    <div class="cert">
        <div class="doc-head" style="border-bottom:0;margin-bottom:0">
            <div><div class="co">{{ \App\Models\Setting::get('company_name') }}</div><div class="small">Co. Reg. {{ \App\Models\Setting::get('company_reg_no') }}</div></div>
            <div class="right"><div class="mono">No. {{ $w->doc_no }}</div><div class="small">Issued {{ $w->created_at->format('d M Y') }}</div></div>
        </div>
        <h1>WARRANTY CERTIFICATE</h1>
        <div class="subt">This certifies that the works described below are warranted by {{ \App\Models\Setting::get('company_name') }}.</div>
        <table class="kv">
            <tr><td>Customer</td><td><b>{{ $w->customer->name }}</b>@if($w->customer->contact_person)<br>Attn: {{ $w->customer->contact_person }}@endif</td></tr>
            <tr><td>Site address</td><td>{{ $w->project?->site_address ?? trim($w->customer->address.', '.$w->customer->postcode.' '.$w->customer->city.', '.$w->customer->state, ', ') }}</td></tr>
            @if($w->project)<tr><td>Project reference</td><td>{{ $w->project->code }} — {{ $w->project->name }}</td></tr>@endif
            <tr><td>Scope of works</td><td>{{ $w->service }}</td></tr>
            <tr><td>Warranty period</td><td><b>{{ $w->years }} year{{ $w->years > 1 ? 's' : '' }}</b> — from {{ $w->start_date->format('d M Y') }} to {{ $w->end_date->format('d M Y') }}</td></tr>
            <tr><td>Coverage &amp; exclusions</td><td style="white-space:pre-line">{{ $w->coverage }}</td></tr>
        </table>
        <p class="small" style="color:#555">To make a warranty claim, contact us with this certificate number. Claims are subject to inspection. This warranty is void if the treated area is altered or repaired by third parties.</p>
        @if($w->status === 'void')<p style="color:#dc2645;font-weight:700;text-align:center;font-size:20px">VOID</p>@endif
        <div class="sign"><div>Authorised signature &amp; company stamp</div><div>Customer acknowledgement</div></div>
    </div>
</div>
</body>
</html>
