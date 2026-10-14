<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\DocNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view') === 'board' ? 'board' : 'list';
        $statusCounts = Project::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $lookups = $this->lookups();

        if ($view === 'board') {
            $projects = $this->filtered($request, false)->with(['customer', 'manager'])->withSum('costs', 'amount')->get();

            return view('projects.board', ['columns' => $projects->groupBy('status'), 'statusCounts' => $statusCounts, 'view' => $view, ...$lookups]);
        }

        $rows = $this->filtered($request)->with(['customer', 'manager', 'branch'])->withSum('costs', 'amount')
            ->paginate((int) $request->input('per_page', 25))->withQueryString();

        return view('projects.index', ['rows' => $rows, 'statusCounts' => $statusCounts, 'view' => $view, ...$lookups]);
    }

    public function calendar(Request $request)
    {
        $month = Carbon::parse(($request->input('month') ?: now()->format('Y-m')).'-01');
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $projects = Project::with('customer')
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $gridEnd)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $gridStart)->orWhere(fn ($w) => $w->whereNull('end_date')->whereDate('start_date', '>=', $gridStart)))
            ->orderBy('start_date')->get();

        $days = [];
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = $projects->filter(fn ($p) => $p->start_date->lte($d) && ($p->end_date ?? $p->start_date)->gte($d))->values();
        }

        return view('projects.calendar', [
            'month' => $month, 'days' => $days,
            'holidays' => Holiday::whereBetween('date', [$gridStart, $gridEnd])->get()->keyBy(fn ($h) => $h->date->toDateString()),
            'count' => $projects->count(),
        ]);
    }

    public function schedule(Request $request)
    {
        $start = Carbon::parse($request->input('start') ?: now()->toDateString())->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(41);
        $projects = Project::with('customer')
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $end)
            ->whereDate(DB::raw('COALESCE(end_date, start_date)'), '>=', $start)
            ->orderBy('start_date')->get();

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->copy();
        }

        return view('projects.schedule', compact('start', 'end', 'projects', 'days'));
    }

    public function create(Request $request)
    {
        $p = new Project([
            'status' => 'planning', 'category' => 'normal', 'progress' => 0,
            'branch_id' => auth()->user()->branch_id, 'manager_id' => auth()->id(),
            'start_date' => today(),
        ]);
        if ($q = Quotation::with('enquiry')->find($request->input('quotation_id'))) {
            $p->fill(['customer_id' => $q->customer_id, 'contract_value' => $q->total, 'enquiry_id' => $q->enquiry_id]);
        }
        if ($e = Enquiry::find($request->input('enquiry_id') ?? $p->enquiry_id)) {
            $p->fill(['enquiry_id' => $e->id, 'customer_id' => $e->customer_id, 'service' => $e->service, 'site_address' => $e->site_address,
                'state' => $e->state, 'branch_id' => $e->branch_id ?? $p->branch_id]);
            if (! $p->contract_value && $e->estimate_amount) {
                $p->contract_value = $e->estimate_amount;
            }
        }
        if ($p->customer_id && ! $p->name) {
            $c = Customer::find($p->customer_id);
            $p->name = trim(($p->service ?? 'Project').' — '.($c->city ?? $c->name));
            $p->site_address ??= trim($c->address.', '.$c->postcode.' '.$c->city, ', ');
            $p->state ??= $c->state;
        }
        if ($p->contract_value && ! $p->budget_cost) {
            $p->budget_cost = round((float) $p->contract_value * 0.62, -1);
        }

        return view('projects.form', ['p' => $p, 'quotationId' => $request->input('quotation_id'), ...$this->formLookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $project = DB::transaction(function () use ($data, $request) {
            $data['code'] = DocNo::next('projects', 'PR', 'code');
            $project = Project::create($data);
            if ($project->enquiry_id) {
                Enquiry::whereKey($project->enquiry_id)->update(['project_id' => $project->id, 'stage' => 'won']);
            }
            if ($qid = $request->input('quotation_id')) {
                Quotation::whereKey($qid)->update(['project_id' => $project->id]);
            }

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('ok', __('Project :code created.', ['code' => $project->code]));
    }

    public function show(Project $project)
    {
        $project->load(['customer', 'branch', 'manager', 'enquiry', 'tasks.assignee', 'updates.user', 'costs.supplier', 'costs.creator']);

        $invoices = $project->invoices()->latest('invoice_date')->get();
        $costByCat = $project->costs->groupBy('category')->map->sum('amount');

        return view('projects.show', [
            'p' => $project,
            'invoices' => $invoices,
            'quotations' => $project->quotations()->latest('quote_date')->get(),
            'warranties' => $project->warranties()->latest('start_date')->get(),
            'services' => $project->serviceRequests()->with('assignee')->latest('scheduled_at')->get(),
            'movements' => $project->stockMovements()->with(['item', 'warehouse', 'user'])->where('type', 'project')->latest('moved_at')->get(),
            'costByCat' => $costByCat,
            'actual' => (float) $project->costs->sum('amount'),
            'invoiced' => (float) $invoices->where('status', '!=', 'void')->where('type', 'invoice')->sum('total'),
            'collected' => (float) $invoices->where('status', '!=', 'void')->sum('paid_amount'),
            'employeeOptions' => Employee::active()->orderBy('name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->display_name])->all(),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id')->all(),
            'activity' => Activity::where('subject_type', Project::class)->where('subject_id', $project->id)->with('causer')->latest()->limit(25)->get(),
        ]);
    }

    public function edit(Project $project)
    {
        return view('projects.form', ['p' => $project, 'quotationId' => null, ...$this->formLookups()]);
    }

    public function update(Request $request, Project $project)
    {
        $project->update($this->validated($request, $project));

        return redirect()->route('projects.show', $project)->with('ok', __('Project updated.'));
    }

    public function destroy(Project $project)
    {
        if ($project->invoices()->exists() || $project->costs()->exists()) {
            return back()->with('error', __('This project has invoices or costs — cancel it instead of deleting.'));
        }
        $project->delete();

        return redirect()->route('projects.index')->with('ok', __('Project :code deleted.', ['code' => $project->code]));
    }

    public function status(Request $request, Project $project)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Project::STATUSES))]]);
        $changes = ['status' => $data['status']];
        if ($data['status'] === 'completed') {
            $changes['progress'] = 100;
        }
        if ($data['status'] === 'in_progress' && ! $project->start_date) {
            $changes['start_date'] = today();
        }
        $project->update($changes);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $project->status, 'progress' => $project->progress]);
        }

        return back()->with('ok', __('Status changed to :status.', ['status' => __(Project::STATUSES[$project->status])]));
    }

    public function addUpdate(Request $request, Project $project)
    {
        $data = $request->validate([
            'note' => 'required|string|max:5000',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);
        DB::transaction(function () use ($data, $project, $request) {
            ProjectUpdate::create(['project_id' => $project->id, 'user_id' => $request->user()->id, 'progress' => $data['progress'] ?? null, 'note' => $data['note']]);
            if (isset($data['progress'])) {
                $changes = ['progress' => $data['progress']];
                if ($data['progress'] > 0 && in_array($project->status, ['planning', 'scheduled'], true)) {
                    $changes['status'] = 'in_progress';
                }
                $project->update($changes);
            }
        });

        return redirect()->route('projects.show', $project)->with('ok', __('Progress update posted.'));
    }

    // ------------------------------------------------------------------

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        $q = Project::query();
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%")->orWhere('site_address', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('code', 'like', "%$s%")));
        }
        if ($withStatus) {
            $q->when($request->input('status'), fn ($w, $v) => $w->where('status', $v));
        }
        $q->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('category'), fn ($w, $v) => $w->where('category', $v))
            ->when($request->input('manager'), fn ($w, $v) => $w->where('manager_id', $v))
            ->when($request->input('state'), fn ($w, $v) => $w->where('state', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('start_date', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('start_date', '<=', $v));

        $sort = ['code' => 'code', 'start' => 'start_date', 'value' => 'contract_value', 'progress' => 'progress'][$request->input('sort')] ?? 'start_date';

        return $q->orderBy($sort, $request->input('dir') === 'asc' ? 'asc' : 'desc')->orderByDesc('id');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|exists:customers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'enquiry_id' => 'nullable|exists:enquiries,id',
            'category' => 'required|in:normal,temporary,aftercare',
            'service' => 'nullable|string|max:64',
            'site_address' => 'nullable|string|max:1000',
            'state' => 'nullable|string|max:64',
            'manager_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'progress' => 'nullable|integer|min:0|max:100',
            'contract_value' => 'nullable|numeric|min:0',
            'budget_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        foreach (['progress', 'contract_value', 'budget_cost'] as $k) {
            $data[$k] = $data[$k] ?? ($project?->{$k} ?? 0);
        }
        if ($data['status'] === 'completed') {
            $data['progress'] = 100;
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
            'managers' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function formLookups(): array
    {
        return $this->lookups() + [
            'customers' => Customer::where('status', '!=', 'blacklist')->orderBy('name')->get(['id', 'code', 'name'])->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all(),
            'services' => config('erp.services'),
            'states' => config('erp.states'),
        ];
    }
}
