@include('quotations._print', [
    'title' => 'QUOTATION',
    'doc' => $q,
    'meta' => [
        'Quotation no' => $q->doc_no,
        'Date' => $q->quote_date?->format('d M Y'),
        'Valid until' => $q->valid_until?->format('d M Y') ?? '—',
        'Salesman' => $q->salesman?->name ?? '—',
    ],
    'back' => route('quotations.show', $q),
    'site' => $q->enquiry?->site_address,
    'footerTitle' => 'Terms & conditions',
    'footerText' => $q->terms,
    'signature' => true,
])
