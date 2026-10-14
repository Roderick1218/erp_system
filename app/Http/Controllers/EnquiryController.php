<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\DocNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view') === 'board' ? 'board' : 'list';
        $base = $this->filtered($request, withStage: false);

        $stageCounts = (clone $base)->reorder()->selectRaw('stage, COUNT(*) n')->groupBy('stage')->pluck('n', 'stage');

        $data = [
            'view' => $view,
            'stageCounts' => $stageCounts,
            'salesmen' => $this->salesmen(),
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
        ];

        if ($view === 'board') {
            $all = (clone $base)->with(['customer', 'salesman'])->get();
            $data['columns'] = collect(Enquiry::STAGES)->map(fn ($label, $key) => [
                'label' => $label,
                'rows' => $all->where('stage', $key)->values(),
                'sum' => (float) $all->where('stage', $key)->sum('estimate_amount'),
            ]);
        } else {
            $q = $this->filtered($request)->with(['customer', 'salesman', 'branch']);
            $data['rows'] = $q->paginate((int) $request->input('per_page', 25))->withQueryString();
            $data['pipelineValue'] = (float) $this->filtered($request)->whereNotIn('stage', ['won', 'lost'])->sum('estimate_amount');
        }

        return view('enquiries.index', $data);
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with(['customer', 'salesman', 'branch'])->cursor()->map(fn (Enquiry $e) => [
            $e->doc_no, $e->enquiry_date?->format('Y-m-d'), $e->customer?->code, $e->customer?->name, $e->contact_person, $e->contact_no,
            __($e->service), $e->site_address, $e->state ? __($e->state) : null, $e->inspection_date?->format('Y-m-d'), $e->salesman?->name,
            __(Enquiry::STAGES[$e->stage] ?? $e->stage), $e->estimate_amount, $e->feedback, $e->lost_reason, $e->branch?->legal_name,
        ]);

        return csv_download('enquiries-'.now()->format('Ymd-His').'.csv',
            array_map('__', ['Doc no', 'Enquiry date', 'Customer code', 'Customer', 'Contact person', 'Contact no', 'Service', 'Site address', 'State', 'Inspection date', 'Salesman', 'Stage', 'Estimate (RM)', 'Feedback', 'Lost reason', 'Branch']), $rows);
    }

    public function create(Request $request)
    {
        $customer = $request->filled('customer_id') ? Customer::find($request->input('customer_id')) : null;
        $e = new Enquiry([
            'customer_id' => $customer?->id,
            'enquiry_date' => today(),
            'stage' => 'new',
            'salesman_id' => $request->user()->id,
            'branch_id' => $customer?->branch_id ?? $request->user()->branch_id,
            'contact_person' => $customer ? ($customer->contact_person ?: $customer->name) : null,
            'contact_no' => $customer?->phone,
            'site_address' => $customer ? trim($customer->address.($customer->postcode ? ', '.$customer->postcode.' '.$customer->city : ''), ', ') : null,
            'state' => $customer?->state,
        ]);

        return view('enquiries.form', ['e' => $e, ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $enquiry = DB::transaction(function () use ($data) {
            $data['doc_no'] = DocNo::next('enquiries', 'ENQ');
            $data['branch_id'] ??= Customer::find($data['customer_id'])?->branch_id;

            return Enquiry::create($data);
        });

        return redirect()->route('enquiries.show', $enquiry)->with('ok', __('Enquiry :no recorded.', ['no' => $enquiry->doc_no]));
    }

    public function show(Enquiry $enquiry)
    {
        $enquiry->load(['customer', 'salesman', 'branch', 'project', 'quotations' => fn ($q) => $q->latest('quote_date')]);

        return view('enquiries.show', [
            'e' => $enquiry,
            'activity' => Activity::where('subject_type', Enquiry::class)->where('subject_id', $enquiry->id)->with('causer')->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Enquiry $enquiry)
    {
        return view('enquiries.form', ['e' => $enquiry, ...$this->lookups()]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $enquiry->update($this->validated($request));

        return redirect()->route('enquiries.show', $enquiry)->with('ok', __('Enquiry updated.'));
    }

    public function destroy(Enquiry $enquiry)
    {
        if ($enquiry->quotations()->exists() || $enquiry->project_id) {
            return back()->with('error', __('This enquiry already has quotations or a project — mark it Lost instead of deleting.'));
        }
        $enquiry->delete();

        return redirect()->route('enquiries.index')->with('ok', __('Enquiry :no deleted.', ['no' => $enquiry->doc_no]));
    }

    /** Pipeline move — used by the kanban board (JSON) and the stage buttons on the detail page (form post). */
    public function stage(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(Enquiry::STAGES))],
            'lost_reason' => 'nullable|string|max:255',
            'inspection_date' => 'nullable|date',
        ]);
        $changes = ['stage' => $data['stage']];
        if ($data['stage'] === 'lost') {
            $changes['lost_reason'] = $data['lost_reason'] ?? $enquiry->lost_reason ?? 'Not specified';
        }
        if ($data['stage'] === 'inspection' && ! empty($data['inspection_date'])) {
            $changes['inspection_date'] = $data['inspection_date'];
        }
        $enquiry->update($changes);
        $label = __(Enquiry::STAGES[$data['stage']]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'stage' => $data['stage'], 'message' => __(':no moved to :stage', ['no' => $enquiry->doc_no, 'stage' => $label])]);
        }

        return back()->with('ok', __(':no moved to :stage.', ['no' => $enquiry->doc_no, 'stage' => $label]));
    }

    // ------------------------------------------------------------------

    private function filtered(Request $request, bool $withStage = true): Builder
    {
        $q = Enquiry::query();
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")->orWhere('contact_person', 'like', "%$s%")->orWhere('contact_no', 'like', "%$s%")
                ->orWhere('site_address', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        $q->when($withStage ? $request->input('stage') : null, fn ($w, $v) => $w->where('stage', $v))
            ->when($request->input('salesman'), fn ($w, $v) => $w->where('salesman_id', $v))
            ->when($request->input('service'), fn ($w, $v) => $w->where('service', $v))
            ->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('enquiry_date', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('enquiry_date', '<=', $v));

        return $q->orderByDesc('enquiry_date')->orderByDesc('id');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'enquiry_date' => 'required|date',
            'contact_person' => 'nullable|string|max:255',
            'contact_no' => 'nullable|string|max:32',
            'service' => 'required|string|max:64',
            'site_address' => 'nullable|string|max:1000',
            'state' => 'nullable|string|max:64',
            'inspection_date' => 'nullable|date',
            'salesman_id' => 'nullable|exists:users,id',
            'stage' => ['required', Rule::in(array_keys(Enquiry::STAGES))],
            'estimate_amount' => 'nullable|numeric|min:0',
            'feedback' => 'nullable|string|max:5000',
            'lost_reason' => 'nullable|required_if:stage,lost|string|max:255',
        ], ['lost_reason.required_if' => __('Please record why the enquiry was lost.')]);
    }

    private function lookups(): array
    {
        return [
            'customers' => Customer::where('status', '!=', 'blacklist')->orderBy('name')->get(['id', 'code', 'name', 'phone', 'contact_person', 'address', 'postcode', 'city', 'state', 'branch_id']),
            'salesmen' => $this->salesmen(),
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
            'services' => collect(config('erp.services'))->mapWithKeys(fn ($s) => [$s => __($s)])->all(),
            'states' => collect(config('erp.states'))->mapWithKeys(fn ($s) => [$s => __($s)])->all(),
        ];
    }

    /** Users who can own enquiries (anyone allowed to manage customers). */
    private function salesmen(): array
    {
        return User::where('is_active', true)->orderBy('name')->get()->filter(fn (User $u) => $u->can('customers.manage'))->pluck('name', 'id')->all();
    }
}
