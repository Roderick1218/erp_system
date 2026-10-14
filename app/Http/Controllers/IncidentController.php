<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Incident;
use App\Models\Project;
use App\Support\DocNo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    public const TYPES = ['injury' => 'Injury', 'near_miss' => 'Near miss', 'property_damage' => 'Property damage', 'vehicle' => 'Vehicle', 'other' => 'Other'];

    public const SEVERITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    public const STATUSES = ['open' => 'Open', 'investigating' => 'Investigating', 'closed' => 'Closed'];

    public function index(Request $request)
    {
        $q = Incident::with(['project', 'employee', 'reporter']);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")->orWhere('description', 'like', "%$s%")->orWhere('location', 'like', "%$s%")
                ->orWhereHas('project', fn ($p) => $p->where('code', 'like', "%$s%")));
        }
        $q->when($request->input('type'), fn ($w, $v) => $w->where('type', $v))
            ->when($request->input('severity'), fn ($w, $v) => $w->where('severity', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('occurred_at', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('occurred_at', '<=', $v));
        $counts = (clone $q)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $q->when($request->input('status'), fn ($w, $v) => $w->where('status', $v));

        return view('incidents.index', [
            'rows' => $q->latest('occurred_at')->paginate(25)->withQueryString(),
            'counts' => $counts,
            'daysSince' => ($last = Incident::where('type', 'injury')->max('occurred_at')) ? (int) Carbon::parse($last)->startOfDay()->diffInDays(today()) : null,
        ]);
    }

    public function create(Request $request)
    {
        return view('incidents.form', ['i' => new Incident([
            'occurred_at' => now()->startOfHour(), 'severity' => 'low', 'status' => 'open', 'type' => 'near_miss',
            'project_id' => $request->input('project_id'),
        ]), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $i = DB::transaction(fn () => Incident::create($data + ['doc_no' => DocNo::next('incidents', 'INC'), 'reported_by' => $request->user()->id]));

        return redirect()->route('incidents.show', $i)->with('ok', __('Incident :doc reported.', ['doc' => $i->doc_no]));
    }

    public function show(Incident $incident)
    {
        $incident->load(['project.customer', 'employee', 'reporter']);

        return view('incidents.show', ['i' => $incident]);
    }

    public function edit(Incident $incident)
    {
        return view('incidents.form', ['i' => $incident, ...$this->lookups()]);
    }

    public function update(Request $request, Incident $incident)
    {
        $incident->update($this->validated($request));

        return redirect()->route('incidents.show', $incident)->with('ok', __('Incident updated.'));
    }

    public function destroy(Incident $incident)
    {
        $incident->delete();

        return redirect()->route('incidents.index')->with('ok', __('Incident :doc deleted.', ['doc' => $incident->doc_no]));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'occurred_at' => 'required|date|before_or_equal:now',
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'severity' => ['required', Rule::in(array_keys(self::SEVERITIES))],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'project_id' => 'nullable|exists:projects,id',
            'employee_id' => 'nullable|exists:employees,id',
            'location' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'action_taken' => 'nullable|string|max:5000',
        ]);
        if ($data['status'] === 'closed' && blank($data['action_taken'] ?? null)) {
            throw ValidationException::withMessages(['action_taken' => __('Record the corrective action before closing the incident.')]);
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'projects' => Project::orderByDesc('id')->get(['id', 'code', 'name'])->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all(),
            'employees' => Employee::active()->orderBy('name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->display_name])->all(),
        ];
    }
}
