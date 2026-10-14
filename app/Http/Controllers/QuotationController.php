<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Support\DocNo;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $base = $this->filtered($request, withStatus: false);
        $counts = (clone $base)->reorder()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $filtered = $this->filtered($request);

        return view('quotations.index', [
            'rows' => (clone $filtered)->with(['customer', 'salesman', 'enquiry'])->paginate(25)->withQueryString(),
            'counts' => $counts,
            'sum' => (float) (clone $filtered)->reorder()->sum('total'),
            'salesmen' => $this->salesmen(),
        ]);
    }

    public function create(Request $request)
    {
        $enquiry = $request->filled('enquiry_id') ? Enquiry::with('customer')->find($request->input('enquiry_id')) : null;
        $customerId = $enquiry?->customer_id ?? $request->input('customer_id');
        $q = new Quotation([
            'customer_id' => $customerId,
            'enquiry_id' => $enquiry?->id,
            'salesman_id' => $enquiry?->salesman_id ?? $request->user()->id,
            'quote_date' => today(),
            'valid_until' => today()->addDays((int) Setting::get('quotation_validity_days', '30')),
            'status' => 'draft',
            'discount' => 0,
            'tax_rate' => (float) Setting::get('sst_rate', '0'),
            'terms' => Setting::get('quotation_terms'),
        ]);
        $lines = $enquiry ? [['item_id' => null, 'description' => 'Labour & workmanship — '.$enquiry->service, 'qty' => 1, 'uom' => 'LOT', 'unit_price' => (float) $enquiry->estimate_amount]] : [];

        return view('quotations.form', ['q' => $q, 'lines' => $lines, 'enquiry' => $enquiry, ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $quotation = DB::transaction(function () use ($data, $request) {
            $q = Quotation::create(collect($data)->except(['lines', 'discount', 'tax_rate'])->all() + [
                'doc_no' => DocNo::next('quotations', 'QT'),
                'status' => $request->input('action') === 'send' ? 'sent' : 'draft',
            ]);
            $q->syncLines($data['lines'], (float) ($data['discount'] ?? 0), (float) ($data['tax_rate'] ?? 0));
            if ($q->status === 'sent') {
                $this->advanceEnquiry($q, 'quotation');
            }

            return $q;
        });

        return redirect()->route('quotations.show', $quotation)->with('ok', __('Quotation :no saved.', ['no' => $quotation->doc_no]));
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['customer', 'enquiry', 'project', 'salesman', 'lines.item', 'invoices']);

        return view('quotations.show', ['q' => $quotation]);
    }

    public function print(Quotation $quotation)
    {
        $quotation->load(['customer.branch', 'salesman', 'lines', 'enquiry']);

        return view('quotations.print', ['q' => $quotation]);
    }

    public function edit(Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('Only draft or sent quotations can be edited.'));
        }
        $quotation->load('lines', 'enquiry');

        return view('quotations.form', [
            'q' => $quotation,
            'lines' => $quotation->lines->map->only(['item_id', 'description', 'qty', 'uom', 'unit_price'])->all(),
            'enquiry' => $quotation->enquiry,
            ...$this->lookups(),
        ]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('Only draft or sent quotations can be edited.'));
        }
        $data = $this->validated($request);
        DB::transaction(function () use ($quotation, $data) {
            $quotation->update(collect($data)->except(['lines', 'discount', 'tax_rate'])->all());
            $quotation->syncLines($data['lines'], (float) ($data['discount'] ?? 0), (float) ($data['tax_rate'] ?? 0));
        });

        return redirect()->route('quotations.show', $quotation)->with('ok', __('Quotation updated.'));
    }

    public function destroy(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return back()->with('error', __('Only draft quotations can be deleted — reject it instead.'));
        }
        $quotation->delete();

        return redirect()->route('quotations.index')->with('ok', __('Quotation :no deleted.', ['no' => $quotation->doc_no]));
    }

    /** draft → sent → accepted / rejected (and back to sent for a revised offer). */
    public function status(Request $request, Quotation $quotation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected'])]]);
        $allowed = [
            'draft' => ['sent'],
            'sent' => ['accepted', 'rejected', 'draft'],
            'rejected' => ['sent'],
            'accepted' => ['sent'],
            'expired' => ['sent'],
        ][$quotation->status] ?? [];
        if (! in_array($data['status'], $allowed, true)) {
            return back()->with('error', __('A :from quotation cannot be marked :to.', ['from' => Status::label($quotation->status), 'to' => Status::label($data['status'])]));
        }
        if ($data['status'] === 'sent' && $quotation->status === 'accepted' && $quotation->invoices()->exists()) {
            return back()->with('error', __('This quotation has already been invoiced.'));
        }

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update(['status' => $data['status']]);
            match ($data['status']) {
                'sent' => $this->advanceEnquiry($quotation, 'quotation'),
                'accepted' => $quotation->enquiry?->update(['stage' => 'won', 'estimate_amount' => $quotation->total, 'lost_reason' => null]),
                default => null,
            };
        });

        return back()->with('ok', __('Quotation :no marked :status.', ['no' => $quotation->doc_no, 'status' => Status::label($data['status'])]));
    }

    /** Copy the quotation into an invoice/proforma, optionally for a % (e.g. 50% deposit). */
    public function toInvoice(Request $request, Quotation $quotation)
    {
        $data = $request->validate([
            'type' => 'required|in:invoice,proforma',
            'percent' => 'required|numeric|min:1|max:100',
        ]);
        if (! in_array($quotation->status, ['accepted', 'sent'], true)) {
            return back()->with('error', __('Only sent or accepted quotations can be invoiced.'));
        }
        $quotation->load('lines');
        $pct = (float) $data['percent'];

        $invoice = DB::transaction(function () use ($quotation, $data, $pct, $request) {
            $invoice = Invoice::create([
                'doc_no' => DocNo::next('invoices', $data['type'] === 'proforma' ? 'PF' : 'INV'),
                'type' => $data['type'],
                'customer_id' => $quotation->customer_id,
                'project_id' => $quotation->project_id,
                'quotation_id' => $quotation->id,
                'invoice_date' => today(),
                'due_date' => today()->addDays((int) Setting::get('invoice_due_days', '30')),
                'status' => 'draft',
                'notes' => 'Ref. quotation '.$quotation->doc_no,
                'created_by' => $request->user()->id,
            ]);
            if ($pct >= 100) {
                $lines = $quotation->lines->map->only(['item_id', 'description', 'qty', 'uom', 'unit_price'])->all();
                $invoice->syncLines($lines, (float) $quotation->discount, (float) $quotation->tax_rate);
            } else {
                $base = round(((float) $quotation->subtotal - (float) $quotation->discount) * $pct / 100, 2);
                $invoice->syncLines([[
                    'description' => qty($pct).'% of quotation '.$quotation->doc_no.' ('.money($quotation->total).')',
                    'qty' => 1, 'uom' => 'LOT', 'unit_price' => $base,
                ]], 0, (float) $quotation->tax_rate);
            }
            if ($quotation->status === 'sent') {
                $quotation->update(['status' => 'accepted']);
                $quotation->enquiry?->update(['stage' => 'won', 'estimate_amount' => $quotation->total]);
            }

            return $invoice;
        });

        return redirect()->route('invoices.edit', $invoice)->with('ok', __($data['type'] === 'proforma' ? 'Proforma :no drafted — review and issue it.' : 'Invoice :no drafted — review and issue it.', ['no' => $invoice->doc_no]));
    }

    public function toProject(Quotation $quotation)
    {
        if ($quotation->project_id) {
            return redirect()->route('projects.show', $quotation->project_id);
        }

        return redirect()->route('projects.create', array_filter(['enquiry_id' => $quotation->enquiry_id, 'quotation_id' => $quotation->id, 'customer_id' => $quotation->customer_id]));
    }

    // ------------------------------------------------------------------

    private function advanceEnquiry(Quotation $q, string $stage): void
    {
        if ($q->enquiry && in_array($q->enquiry->stage, ['new', 'inspection'], true)) {
            $q->enquiry->update(['stage' => $stage]);
        }
    }

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        $q = Quotation::query();
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        $status = $withStatus ? $request->input('status') : null;
        if ($status === 'expired') {
            $q->where('status', 'sent')->whereDate('valid_until', '<', today());
        } elseif ($status) {
            $q->where('status', $status);
        }
        $q->when($request->input('salesman'), fn ($w, $v) => $w->where('salesman_id', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('quote_date', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('quote_date', '<=', $v));

        return $q->orderByDesc('quote_date')->orderByDesc('id');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'enquiry_id' => 'nullable|exists:enquiries,id',
            'salesman_id' => 'nullable|exists:users,id',
            'quote_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quote_date',
            'notes' => 'nullable|string|max:5000',
            'terms' => 'nullable|string|max:5000',
        ] + Quotation::lineRules());
        if (! Quotation::hasUsableLine($data['lines'])) {
            throw ValidationException::withMessages(['lines' => __('Add at least one line with a description.')]);
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'customers' => Customer::where('status', '!=', 'blacklist')->orderBy('name')->get(['id', 'code', 'name', 'phone']),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom', 'selling_price']),
            'salesmen' => $this->salesmen(),
        ];
    }

    private function salesmen(): array
    {
        return User::where('is_active', true)->orderBy('name')->get()->filter(fn (User $u) => $u->can('sales.manage') || $u->can('customers.manage'))->pluck('name', 'id')->all();
    }
}
