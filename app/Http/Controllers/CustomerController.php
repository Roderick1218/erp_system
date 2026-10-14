<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class CustomerController extends Controller
{
    private const SORTS = ['code' => 'code', 'name' => 'name', 'created' => 'created_at', 'state' => 'state'];

    public function index(Request $request)
    {
        $rows = $this->filtered($request)
            ->withCount(['projects', 'enquiries'])
            ->paginate((int) $request->input('per_page', 25))->withQueryString();

        return view('customers.index', [
            'rows' => $rows,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
            'statusCounts' => Customer::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with('branch')->cursor()->map(fn (Customer $c) => [
            $c->code, $c->name, $c->type, $c->contact_person, $c->phone, $c->email, $c->document_type, $c->document_id,
            $c->address, $c->postcode, $c->city, $c->state, $c->branch?->legal_name, $c->source, $c->status, $c->created_at?->format('Y-m-d'),
        ]);

        return csv_download('customers-'.now()->format('Ymd-His').'.csv',
            ['Code', 'Name', 'Type', 'Contact person', 'Phone', 'Email', 'Doc type', 'Doc ID', 'Address', 'Postcode', 'City', 'State', 'Branch', 'Source', 'Status', 'Created'], $rows);
    }

    public function show(Customer $customer)
    {
        $customer->load(['branch', 'creator']);

        return view('customers.show', [
            'c' => $customer,
            'enquiries' => $customer->enquiries()->with('salesman')->latest('enquiry_date')->get(),
            'projects' => $customer->projects()->withSum('costs', 'amount')->latest('start_date')->get(),
            'quotations' => $customer->quotations()->latest('quote_date')->get(),
            'invoices' => $customer->invoices()->latest('invoice_date')->get(),
            'warranties' => $customer->warranties()->latest('end_date')->get(),
            'services' => $customer->serviceRequests()->with('assignee')->latest('scheduled_at')->get(),
            'activity' => Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->with('causer')->latest()->limit(20)->get(),
        ]);
    }

    public function create()
    {
        return view('customers.form', ['c' => new Customer(['status' => 'active', 'type' => 'individual', 'branch_id' => auth()->user()->branch_id]), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = $this->nextCode();
        $data['created_by'] = $request->user()->id;
        $customer = Customer::create($data);

        if ($request->boolean('then_enquiry')) {
            return redirect()->route('enquiries.create', ['customer_id' => $customer->id])->with('ok', __('Customer :code created — now record the enquiry.', ['code' => $customer->code]));
        }

        return redirect()->route('customers.show', $customer)->with('ok', __('Customer :code created.', ['code' => $customer->code]));
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', ['c' => $customer, ...$this->lookups()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('ok', __('Customer updated.'));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->projects()->exists() || $customer->invoices()->exists()) {
            return back()->with('error', __('This customer has projects or invoices — set the status to Inactive instead of deleting.'));
        }
        $customer->delete();

        return redirect()->route('customers.index')->with('ok', __('Customer :code deleted.', ['code' => $customer->code]));
    }

    // ------------------------------------------------------------------

    private function filtered(Request $request): Builder
    {
        $q = Customer::query()->with('branch');
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")
                ->orWhere('document_id', 'like', "%$s%")->orWhere('contact_person', 'like', "%$s%")->orWhere('city', 'like', "%$s%"));
        }
        $q->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($request->input('type'), fn ($w, $v) => $w->where('type', $v))
            ->when($request->input('state'), fn ($w, $v) => $w->where('state', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('created_at', '<=', $v));

        $sort = self::SORTS[$request->input('sort')] ?? 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : ($request->has('sort') ? 'desc' : 'desc');

        return $q->orderBy($sort, $dir)->orderByDesc('id');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:individual,company,government',
            'branch_id' => 'nullable|exists:branches,id',
            'contact_person' => 'nullable|string|max:255',
            'phone' => ['required', 'string', 'max:32', Rule::unique('customers', 'phone')->ignore($customer?->id)->whereNull('deleted_at')],
            'phone2' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'document_type' => 'nullable|in:IC,BRN,PASSPORT',
            'document_id' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:1000',
            'postcode' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:64',
            'state' => 'nullable|string|max:64',
            'source' => 'nullable|string|max:32',
            'status' => 'required|in:active,inactive,blacklist',
            'notes' => 'nullable|string|max:5000',
        ], ['phone.unique' => __('Another customer already uses this phone number — search for them instead of creating a duplicate.')]);
    }

    private function lookups(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
            'states' => config('erp.states'),
            'sources' => config('erp.customer_sources'),
        ];
    }

    private function nextCode(): string
    {
        $last = Customer::withTrashed()->where('code', 'like', 'C%')->orderByDesc('code')->value('code');

        return sprintf('C%05d', $last ? ((int) substr($last, 1)) + 1 : 1001);
    }
}
