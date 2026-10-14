<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ServiceRequest;
use App\Models\Warranty;
use App\Support\DocNo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    public const TYPES = ['inspection' => 'Inspection', 'repair' => 'Repair', 'aftercare' => 'Aftercare', 'complaint' => 'Complaint'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUSES = ['open' => 'Open', 'scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'done' => 'Done', 'cancelled' => 'Cancelled'];

    public function index(Request $request)
    {
        $q = ServiceRequest::with(['customer', 'assignee', 'project']);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")->orWhere('title', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        $q->when($request->input('type'), fn ($w, $v) => $w->where('type', $v))
            ->when($request->input('priority'), fn ($w, $v) => $w->where('priority', $v))
            ->when($request->input('assignee'), fn ($w, $v) => $w->where('assigned_to', $v))
            ->when($request->boolean('mine'), fn ($w) => $w->where('assigned_to', $request->user()->employee_id ?? 0))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('scheduled_at', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('scheduled_at', '<=', $v));

        $counts = (clone $q)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $q->when($request->input('status'), fn ($w, $v) => $w->where('status', $v));

        $rows = $q->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'open' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('scheduled_at')->paginate(25)->withQueryString();

        return view('service_requests.index', ['rows' => $rows, 'counts' => $counts, 'employees' => $this->employeeOptions()]);
    }

    public function calendar(Request $request)
    {
        $start = Carbon::parse($request->input('week') ?: today()->toDateString())->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();
        $events = ServiceRequest::with(['customer', 'assignee'])->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $start)->where('scheduled_at', '<=', $end)
            ->when($request->input('assignee'), fn ($w, $v) => $w->where('assigned_to', $v))
            ->where('status', '!=', 'cancelled')->orderBy('scheduled_at')->get();

        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

        return view('service_requests.calendar', [
            'start' => $start, 'days' => $days, 'events' => $events->groupBy(fn ($e) => $e->scheduled_at->toDateString()),
            'firstHour' => 7, 'lastHour' => 20, 'employees' => $this->employeeOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $sr = new ServiceRequest(['type' => 'inspection', 'priority' => 'normal', 'duration_minutes' => 60, 'status' => 'open',
            'scheduled_at' => today()->addDay()->setTime(10, 0)]);
        if ($w = Warranty::find($request->input('warranty_id'))) {
            $sr->fill(['warranty_id' => $w->id, 'customer_id' => $w->customer_id, 'project_id' => $w->project_id, 'type' => 'repair', 'title' => __('Warranty claim').' — '.__($w->service)]);
        }
        if ($p = Project::find($request->input('project_id'))) {
            $sr->fill(['project_id' => $p->id, 'customer_id' => $p->customer_id]);
        }
        if ($request->filled('customer_id')) {
            $sr->customer_id = (int) $request->input('customer_id');
        }
        if ($request->filled('scheduled_at')) {
            $sr->scheduled_at = Carbon::parse($request->input('scheduled_at'));
        }

        return view('service_requests.form', ['sr' => $sr, ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $sr = DB::transaction(fn () => ServiceRequest::create($data + [
            'doc_no' => DocNo::next('service_requests', 'SR'),
            'created_by' => $request->user()->id,
        ]));

        return redirect()->route('service-requests.show', $sr)->with('ok', __('Service request :doc created.', ['doc' => $sr->doc_no]));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load(['customer', 'project', 'warranty', 'assignee', 'creator']);
        $history = ServiceRequest::with('assignee')->where('customer_id', $serviceRequest->customer_id)->whereKeyNot($serviceRequest->id)->latest('scheduled_at')->limit(8)->get();

        return view('service_requests.show', ['sr' => $serviceRequest, 'history' => $history]);
    }

    public function edit(ServiceRequest $serviceRequest)
    {
        return view('service_requests.form', ['sr' => $serviceRequest, ...$this->lookups()]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        $serviceRequest->update($this->validated($request, true));

        return redirect()->route('service-requests.show', $serviceRequest)->with('ok', __('Service request updated.'));
    }

    public function destroy(ServiceRequest $serviceRequest)
    {
        $serviceRequest->delete();

        return redirect()->route('service-requests.index')->with('ok', __('Service request :doc deleted.', ['doc' => $serviceRequest->doc_no]));
    }

    public function status(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'resolution' => 'nullable|string|max:5000',
        ]);
        if ($data['status'] === 'done' && blank($data['resolution'] ?? $serviceRequest->resolution)) {
            $msg = __('Please write what was done (resolution) before closing the job.');
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['resolution' => $msg]);
        }
        $serviceRequest->update(array_filter($data, fn ($v) => $v !== null));

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $serviceRequest->status]);
        }

        return back()->with('ok', $serviceRequest->doc_no.' → '.__(self::STATUSES[$serviceRequest->status]).'.');
    }

    private function validated(Request $request, bool $editing = false): array
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'warranty_id' => 'nullable|exists:warranties,id',
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => ['required', Rule::in(array_keys(self::PRIORITIES))],
            'scheduled_at' => 'nullable|date',
            'duration_minutes' => 'required|integer|min:15|max:1440',
            'assigned_to' => 'nullable|exists:employees,id',
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'resolution' => 'nullable|string|max:5000',
        ]);
        // A request with a date and a technician is scheduled, not merely open.
        if ($data['status'] === 'open' && ! empty($data['scheduled_at']) && ! empty($data['assigned_to'])) {
            $data['status'] = 'scheduled';
        }

        return $data;
    }

    private function employeeOptions(): array
    {
        return Employee::active()->orderBy('name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->display_name])->all();
    }

    private function lookups(): array
    {
        return [
            'customers' => Customer::where('status', '!=', 'blacklist')->orderBy('name')->get(['id', 'code', 'name'])->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all(),
            'projects' => Project::orderByDesc('id')->get(['id', 'code', 'name'])->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all(),
            'warranties' => Warranty::with('customer:id,name')->where('status', '!=', 'void')->orderByDesc('id')->get()->mapWithKeys(fn ($w) => [$w->id => $w->doc_no.' — '.$w->customer->name.' ('.__('to :date', ['date' => d($w->end_date)]).')'])->all(),
            'employees' => $this->employeeOptions(),
        ];
    }
}
