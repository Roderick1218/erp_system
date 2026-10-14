@include('quotations._print', [
    'title' => $inv->type === 'proforma' ? 'PROFORMA INVOICE' : 'INVOICE',
    'doc' => $inv,
    'meta' => array_filter([
        ($inv->type === 'proforma' ? 'Proforma no' : 'Invoice no') => $inv->doc_no,
        'Date' => $inv->invoice_date?->format('d M Y'),
        'Due date' => $inv->due_date?->format('d M Y'),
        'Project' => $inv->project?->code,
        'Status' => $inv->status === 'void' ? 'VOID' : null,
    ]),
    'back' => route('invoices.show', $inv),
    'site' => $inv->project?->site_address,
    'balance' => $inv->status === 'void' ? 0 : $inv->balance,
    'footerTitle' => 'Payment',
    'footerText' => 'Please make payment to '.\App\Models\Setting::get('company_name').' and quote the document number as reference.',
])
