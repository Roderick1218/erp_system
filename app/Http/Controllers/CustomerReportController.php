<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerReportController extends Controller
{
    /** "Customer Report": one row per enquiry with its whole document trail. */
    public function customers(Request $request)
    {
        [$from, $to] = $this->range($request);
        $by = $request->input('by') === 'inspection' ? 'inspection_date' : 'enquiry_date';

        $q = Enquiry::query()->with(['customer', 'salesman', 'quotations.invoices', 'project.invoices'])
            ->whereDate($by, '>=', $from->toDateString())->whereDate($by, '<=', $to->toDateString())
            ->when($request->input('salesman'), fn ($w, $v) => $w->where('salesman_id', $v))
            ->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('stage'), fn ($w, $v) => $w->where('stage', $v))
            ->orderByDesc($by)->orderByDesc('id');

        if ($request->input('export') === 'csv') {
            $rows = $q->get()->map(function (Enquiry $e) {
                $t = $this->trail($e);

                return [
                    $e->enquiry_date?->format('Y-m-d'), $e->customer->code, $e->customer->name, $e->contact_person, $e->contact_no, __($e->service),
                    $e->site_address, $e->inspection_date?->format('Y-m-d'), $e->salesman?->name, __(Enquiry::STAGES[$e->stage] ?? $e->stage), $e->feedback,
                    $e->estimate_amount, $t['quotation']?->doc_no, $t['quotation']?->total, $t['proforma']?->doc_no, $t['proforma']?->total,
                    $t['invoice']?->doc_no, $t['invoiced'],
                ];
            });

            return csv_download('customer-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', array_map('__', [
                'Enquiry date', 'Customer code', 'Profile', 'Contact person', 'Contact no', 'Service', 'Site address', 'Inspection date', 'Salesman', 'Stage',
                'Feedback', 'Estimate', 'Quotation no', 'Quotation total', 'Proforma no', 'Proforma total', 'Invoice no', 'Invoiced total',
            ]), $rows);
        }

        $rows = $q->paginate(25)->withQueryString();
        $rows->getCollection()->transform(fn (Enquiry $e) => tap($e, fn ($e) => $e->setAttribute('trail', $this->trail($e))));

        return view('reports.customers', [
            'rows' => $rows,
            'from' => $from, 'to' => $to, 'by' => $by,
            'salesmen' => $this->salesmen(),
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
        ]);
    }

    /** "Salesman Report": funnel & conversion per salesman for the period. */
    public function salesman(Request $request)
    {
        [$from, $to] = $this->range($request);
        $enquiries = Enquiry::with('quotations')
            ->whereDate('enquiry_date', '>=', $from->toDateString())->whereDate('enquiry_date', '<=', $to->toDateString())
            ->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->get();
        $quotes = Quotation::whereDate('quote_date', '>=', $from->toDateString())->whereDate('quote_date', '<=', $to->toDateString())
            ->where('status', '!=', 'draft')->get();
        $names = User::withTrashed()->pluck('name', 'id');

        $ids = $enquiries->pluck('salesman_id')->merge($quotes->pluck('salesman_id'))->unique()->values();
        $rows = $ids->map(function ($id) use ($enquiries, $quotes, $names) {
            /** @var Collection $mine */
            $mine = $enquiries->where('salesman_id', $id);
            $myQuotes = $quotes->where('salesman_id', $id);
            $won = $mine->where('stage', 'won');
            $lost = $mine->where('stage', 'lost');
            $decided = $won->count() + $lost->count();
            $daysToQuote = $mine->map(function (Enquiry $e) {
                $first = $e->quotations->where('status', '!=', 'draft')->sortBy('quote_date')->first();

                return $first ? $e->enquiry_date->diffInDays($first->quote_date) : null;
            })->filter(fn ($d) => $d !== null);

            return [
                'id' => $id,
                'name' => $id ? ($names[$id] ?? __('User #:id', ['id' => $id])) : __('Unassigned'),
                'enquiries' => $mine->count(),
                'inspected' => $mine->whereNotNull('inspection_date')->count(),
                'quotes' => $myQuotes->count(),
                'quoteValue' => (float) $myQuotes->sum('total'),
                'won' => $won->count(),
                'wonValue' => (float) $won->sum('estimate_amount'),
                'lost' => $lost->count(),
                'open' => $mine->whereNotIn('stage', ['won', 'lost'])->count(),
                'winRate' => $decided ? round($won->count() / $decided * 100) : null,
                'conversion' => $mine->count() ? round($won->count() / $mine->count() * 100) : null,
                'daysToQuote' => $daysToQuote->count() ? round($daysToQuote->avg(), 1) : null,
            ];
        })->sortByDesc('wonValue')->values();

        if ($request->input('export') === 'csv') {
            return csv_download('salesman-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv',
                array_map('__', ['Salesman', 'Enquiries', 'Inspected', 'Quotations', 'Quoted (RM)', 'Won', 'Won (RM)', 'Lost', 'Open', 'Win rate %', 'Conversion %', 'Avg days to quote']),
                $rows->map(fn ($r) => [$r['name'], $r['enquiries'], $r['inspected'], $r['quotes'], $r['quoteValue'], $r['won'], $r['wonValue'], $r['lost'], $r['open'], $r['winRate'], $r['conversion'], $r['daysToQuote']]));
        }

        return view('reports.salesman', [
            'rows' => $rows,
            'from' => $from, 'to' => $to,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
            'totals' => [
                'enquiries' => $rows->sum('enquiries'), 'quotes' => $rows->sum('quotes'), 'quoteValue' => $rows->sum('quoteValue'),
                'won' => $rows->sum('won'), 'wonValue' => $rows->sum('wonValue'), 'lost' => $rows->sum('lost'),
            ],
        ]);
    }

    // ------------------------------------------------------------------

    /** Latest quotation / proforma / invoice linked to an enquiry (via its quotations or its project). */
    private function trail(Enquiry $e): array
    {
        $quotation = $e->quotations->sortByDesc('quote_date')->first();
        $invoices = $e->quotations->flatMap->invoices->merge($e->project?->invoices ?? collect())->unique('id')->where('status', '!=', 'void');
        $tax = $invoices->where('type', 'invoice');

        return [
            'quotation' => $quotation,
            'proforma' => $invoices->where('type', 'proforma')->sortByDesc('invoice_date')->first(),
            'invoice' => $tax->sortByDesc('invoice_date')->first(),
            'invoiceCount' => $tax->count(),
            'invoiced' => (float) $tax->sum('total'),
        ];
    }

    /** Default range = last 30 days, like a typical sales report. */
    private function range(Request $request): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today();
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : $to->copy()->subDays(30);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function salesmen(): array
    {
        return User::where('is_active', true)->orderBy('name')->get()->filter(fn (User $u) => $u->can('customers.manage'))->pluck('name', 'id')->all();
    }
}
