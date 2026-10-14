{{-- Standalone printable document shared by quotation / invoice / proforma.
     Params: $title, $doc (with customer, lines, totals), $meta [label => value], $back (url),
     optional $site, $footerTitle, $footerText, $signature, $payments, $balance. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->doc_no }} · {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body { background: #e9edf3; }
        .doc { margin-top: 0; margin-bottom: 40px; }
        .doc .meta td { padding: 2px 0 2px 14px; font-size: 12.5px; }
        .doc .meta td:first-child { color: #666; padding-left: 0; }
        .doc .party { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 8px; }
        .doc .lbl { font-size: 10.5px; letter-spacing: 1px; text-transform: uppercase; color: #777; font-family: var(--mono); margin-bottom: 3px; }
        .doc table.items td.n, .doc table.items th.n { text-align: right; white-space: nowrap; }
        .doc .tot { width: 300px; margin-left: auto; border-collapse: collapse; }
        .doc .tot td { padding: 5px 0; }
        .doc .tot td:last-child { text-align: right; }
        .doc .tot .g td { border-top: 2px solid var(--navy); font-weight: 700; font-size: 15px; padding-top: 8px; }
        .doc .sign { display: flex; justify-content: space-between; gap: 40px; margin-top: 56px; }
        .doc .sign div { flex: 1; border-top: 1px solid #999; padding-top: 6px; font-size: 12px; color: #555; }
        .doc .foot { margin-top: 26px; font-size: 11.5px; color: #444; white-space: pre-line; }
        .stamp { display: inline-block; border: 2px solid; border-radius: 6px; padding: 2px 10px; font-weight: 700; letter-spacing: 1px; transform: rotate(-4deg); }
    </style>
</head>
<body>
<div class="print-bar">
    <a class="btn btn-ghost" href="{{ $back }}"><x-icon name="arrow-left" class="ico ico-sm" />Back</a>
    <button class="btn btn-primary" onclick="window.print()"><x-icon name="printer" class="ico ico-sm" />Print / Save as PDF</button>
</div>
<div class="doc">
    <div class="doc-head">
        <div>
            <div class="co">{{ \App\Models\Setting::get('company_name') }}</div>
            <div class="small" style="color:#555">({{ \App\Models\Setting::get('company_reg_no') }})</div>
            @if($doc->customer->branch)<div class="small" style="color:#555;margin-top:4px">{{ $doc->customer->branch->legal_name }}@if($doc->customer->branch->phone) · Tel {{ $doc->customer->branch->phone }}@endif</div>@endif
        </div>
        <div>
            <div class="doc-title">{{ $title }}</div>
            <table class="meta" style="margin-left:auto;margin-top:6px">
                @foreach($meta as $k => $v)<tr><td>{{ $k }}</td><td><b>{{ $v }}</b></td></tr>@endforeach
            </table>
        </div>
    </div>

    <div class="party">
        <div>
            <div class="lbl">Bill to</div>
            <b>{{ $doc->customer->name }}</b>
            <div style="white-space:pre-line;font-size:12.5px">{{ $doc->customer->contact_person ? 'Attn: '.$doc->customer->contact_person."\n" : '' }}{{ $doc->customer->address }}{{ $doc->customer->postcode ? "\n".$doc->customer->postcode.' '.$doc->customer->city.', '.$doc->customer->state : '' }}</div>
            <div style="font-size:12.5px">Tel: {{ $doc->customer->phone }}</div>
        </div>
        @if(! empty($site))
            <div style="max-width:300px">
                <div class="lbl">Site</div>
                <div style="font-size:12.5px">{{ $site }}</div>
            </div>
        @endif
    </div>

    <table class="items">
        <thead><tr><th style="width:36px">No</th><th>Description</th><th class="n">Qty</th><th>UOM</th><th class="n">Unit price (RM)</th><th class="n">Amount (RM)</th></tr></thead>
        <tbody>
        @foreach($doc->lines as $l)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $l->description }}</td><td class="n">{{ qty($l->qty) }}</td><td>{{ $l->uom }}</td><td class="n">{{ money($l->unit_price, false) }}</td><td class="n">{{ money($l->amount, false) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <table class="tot">
        <tr><td>Subtotal</td><td>{{ money($doc->subtotal) }}</td></tr>
        @if((float) $doc->discount)<tr><td>Discount</td><td>− {{ money($doc->discount) }}</td></tr>@endif
        @if((float) $doc->tax)<tr><td>Tax ({{ qty($doc->tax_rate) }}%)</td><td>{{ money($doc->tax) }}</td></tr>@endif
        <tr class="g"><td>Total</td><td>{{ money($doc->total) }}</td></tr>
        @isset($balance)
            <tr><td>Paid</td><td>{{ money($doc->paid_amount) }}</td></tr>
            <tr class="g"><td>Balance due</td><td>{{ money($balance) }}</td></tr>
        @endisset
    </table>

    @if($doc->notes)<div class="foot"><div class="lbl">Remarks</div>{{ $doc->notes }}</div>@endif
    @if(! empty($footerText))<div class="foot"><div class="lbl">{{ $footerTitle ?? 'Notes' }}</div>{{ $footerText }}</div>@endif

    @if(! empty($signature))
        <div class="sign">
            <div>Prepared by<br><b>{{ \App\Models\Setting::get('company_name') }}</b></div>
            <div>Accepted by customer (signature, name &amp; date)</div>
        </div>
    @endif
    <p class="small" style="color:#888;margin-top:28px;text-align:center">This is a computer-generated document.</p>
</div>
</body>
</html>
