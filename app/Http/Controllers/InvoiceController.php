<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Setting;
use App\Support\DocNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $base = $this->filtered($request, withStatus: false);
        $counts = [
            'all' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'outstanding' => (clone $base)->whereIn('status', ['unpaid', 'partial'])->count(),
            'overdue' => (clone $base)->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->count(),
            'paid' => (clone $base)->where('status', 'paid')->count(),
            'void' => (clone $base)->where('status', 'void')->count(),
        ];

        // Receivables aging (tax invoices only, ignores the status tab so the strip stays stable).
        $open = Invoice::where('type', 'invoice')->whereIn('status', ['unpaid', 'partial'])->get(['total', 'paid_amount', 'due_date']);
        $aging = ['current' => 0.0, '1-30' => 0.0, '31-60' => 0.0, '60+' => 0.0];
        foreach ($open as $inv) {
            $late = $inv->due_date ? (int) $inv->due_date->diffInDays(today(), false) : 0;
            $bucket = $late <= 0 ? 'current' : ($late <= 30 ? '1-30' : ($late <= 60 ? '31-60' : '60+'));
            $aging[$bucket] += $inv->balance;
        }

        $filtered = $this->filtered($request);

        return view('invoices.index', [
            'rows' => (clone $filtered)->with(['customer', 'project'])->paginate(25)->withQueryString(),
            'counts' => $counts,
            'aging' => $aging,
            'sumTotal' => (float) (clone $filtered)->reorder()->sum('total'),
            'sumPaid' => (float) (clone $filtered)->reorder()->sum('paid_amount'),
        ]);
    }

    public function create(Request $request)
    {
        $project = $request->filled('project_id') ? Project::find($request->input('project_id')) : null;
        $quotation = $request->filled('quotation_id') ? Quotation::with('lines')->find($request->input('quotation_id')) : null;
        $inv = new Invoice([
            'type' => $request->input('type') === 'proforma' ? 'proforma' : 'invoice',
            'customer_id' => $project?->customer_id ?? $quotation?->customer_id ?? $request->input('customer_id'),
            'project_id' => $project?->id ?? $quotation?->project_id,
            'quotation_id' => $quotation?->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays((int) Setting::get('invoice_due_days', '30')),
            'discount' => $quotation?->discount ?? 0,
            'tax_rate' => $quotation?->tax_rate ?? (float) Setting::get('sst_rate', '0'),
        ]);
        $lines = $quotation ? $quotation->lines->map->only(['item_id', 'description', 'qty', 'uom', 'unit_price'])->all()
            : ($project ? [['item_id' => null, 'description' => 'Progress claim — '.$project->code.' '.$project->name, 'qty' => 1, 'uom' => 'LOT', 'unit_price' => '']] : []);

        return view('invoices.form', ['inv' => $inv, 'lines' => $lines, ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(function () use ($data, $request) {
            $inv = Invoice::create(collect($data)->except(['lines', 'discount', 'tax_rate'])->all() + [
                'doc_no' => DocNo::next('invoices', $data['type'] === 'proforma' ? 'PF' : 'INV'),
                'status' => $request->input('action') === 'issue' ? 'unpaid' : 'draft',
                'created_by' => $request->user()->id,
            ]);
            $inv->syncLines($data['lines'], (float) ($data['discount'] ?? 0), (float) ($data['tax_rate'] ?? 0));

            return $inv;
        });

        return redirect()->route('invoices.show', $invoice)->with('ok', __($invoice->status === 'draft' ? ':no saved as draft.' : ':no issued.', ['no' => $invoice->doc_no]));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'project', 'quotation', 'lines.item', 'payments.creator', 'creator']);

        return view('invoices.show', ['inv' => $invoice, 'methods' => config('erp.payment_methods')]);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer.branch', 'project', 'lines', 'payments']);

        return view('invoices.print', ['inv' => $invoice]);
    }

    public function edit(Invoice $invoice)
    {
        if (! $invoice->isEditable()) {
            return redirect()->route('invoices.show', $invoice)->with('error', __('Only drafts or unpaid invoices without payments can be edited.'));
        }
        $invoice->load('lines');

        return view('invoices.form', [
            'inv' => $invoice,
            'lines' => $invoice->lines->map->only(['item_id', 'description', 'qty', 'uom', 'unit_price'])->all(),
            ...$this->lookups(),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if (! $invoice->isEditable()) {
            return redirect()->route('invoices.show', $invoice)->with('error', __('Only drafts or unpaid invoices without payments can be edited.'));
        }
        $data = $this->validated($request, $invoice);
        DB::transaction(function () use ($invoice, $data, $request) {
            $invoice->update(collect($data)->except(['lines', 'discount', 'tax_rate', 'type'])->all());
            $invoice->syncLines($data['lines'], (float) ($data['discount'] ?? 0), (float) ($data['tax_rate'] ?? 0));
            if ($invoice->status === 'draft' && $request->input('action') === 'issue') {
                $invoice->update(['status' => 'unpaid']);
            }
        });

        return redirect()->route('invoices.show', $invoice)->with('ok', __(':no updated.', ['no' => $invoice->doc_no]));
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', __('Only drafts can be deleted — void an issued invoice instead.'));
        }
        $invoice->delete();

        return redirect()->route('invoices.index')->with('ok', __('Draft :no deleted.', ['no' => $invoice->doc_no]));
    }

    public function issue(Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', __('Only drafts can be issued.'));
        }
        if ((float) $invoice->total <= 0) {
            return back()->with('error', __('Cannot issue an invoice with a zero total.'));
        }
        $invoice->update(['status' => 'unpaid']);

        return back()->with('ok', __(':no issued — it now counts as receivable.', ['no' => $invoice->doc_no]));
    }

    public function void(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'void') {
            return back();
        }
        if ($invoice->payments()->exists()) {
            return back()->with('error', __('Delete the recorded payments before voiding this invoice.'));
        }
        $reason = trim((string) $request->input('reason'));
        $invoice->update(['status' => 'void', 'notes' => trim($invoice->notes."\nVOID: ".($reason ?: 'no reason given').' ('.now()->format('d M Y').', '.$request->user()->name.')')]);

        return back()->with('ok', __(':no voided.', ['no' => $invoice->doc_no]));
    }

    // ------------------------------------------------------------------

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        $q = Invoice::query();
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('project', fn ($p) => $p->where('code', 'like', "%$s%")));
        }
        match ($withStatus ? $request->input('status') : null) {
            'draft', 'paid', 'void' => $q->where('status', $request->input('status')),
            'outstanding' => $q->whereIn('status', ['unpaid', 'partial']),
            'overdue' => $q->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today()),
            default => null,
        };
        $q->when(in_array($request->input('type'), ['invoice', 'proforma'], true) ? $request->input('type') : null, fn ($w, $v) => $w->where('type', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('invoice_date', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('invoice_date', '<=', $v));

        return $q->orderByDesc('invoice_date')->orderByDesc('id');
    }

    private function validated(Request $request, ?Invoice $invoice = null): array
    {
        $data = $request->validate([
            'type' => ($invoice ? 'nullable' : 'required').'|in:invoice,proforma',
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string|max:5000',
        ] + Invoice::lineRules());
        if (! Invoice::hasUsableLine($data['lines'])) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line with a description.')]);
        }
        if (! empty($data['project_id']) && Project::find($data['project_id'])?->customer_id != $data['customer_id']) {
            throw ValidationException::withMessages(['project_id' => __('That project belongs to a different customer.')]);
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'customers' => Customer::where('status', '!=', 'blacklist')->orderBy('name')->get(['id', 'code', 'name', 'phone']),
            'projects' => Project::whereNotIn('status', ['cancelled'])->orderByDesc('id')->get(['id', 'code', 'name', 'customer_id']),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom', 'selling_price']),
        ];
    }
}
