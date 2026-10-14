<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Warranty;
use App\Support\DocNo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarrantyController extends Controller
{
    public function index(Request $request)
    {
        $today = today()->toDateString();
        $soon = today()->addDays(60)->toDateString();
        $tab = $request->input('tab', 'active');

        $q = Warranty::with(['customer', 'project']);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('doc_no', 'like', "%$s%")->orWhere('service', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('project', fn ($p) => $p->where('code', 'like', "%$s%")));
        }
        $q->when($request->input('service'), fn ($w, $v) => $w->where('service', $v))
            ->when($request->input('years'), fn ($w, $v) => $w->where('years', $v));

        $counts = [
            'active' => (clone $q)->where('status', '!=', 'void')->whereDate('end_date', '>=', $today)->count(),
            'expiring' => (clone $q)->where('status', '!=', 'void')->whereDate('end_date', '>=', $today)->whereDate('end_date', '<=', $soon)->count(),
            'expired' => (clone $q)->where('status', '!=', 'void')->whereDate('end_date', '<', $today)->count(),
            'void' => (clone $q)->where('status', 'void')->count(),
        ];
        match ($tab) {
            'expiring' => $q->where('status', '!=', 'void')->whereDate('end_date', '>=', $today)->whereDate('end_date', '<=', $soon)->orderBy('end_date'),
            'expired' => $q->where('status', '!=', 'void')->whereDate('end_date', '<', $today)->orderByDesc('end_date'),
            'void' => $q->where('status', 'void')->latest(),
            default => $q->where('status', '!=', 'void')->whereDate('end_date', '>=', $today)->orderBy('end_date'),
        };

        return view('warranties.index', [
            'rows' => $q->paginate(25)->withQueryString(), 'counts' => $counts, 'tab' => $tab,
        ]);
    }

    public function create(Request $request)
    {
        $w = new Warranty(['start_date' => today(), 'years' => 1]);
        if ($p = Project::find($request->input('project_id'))) {
            $w->fill(['project_id' => $p->id, 'customer_id' => $p->customer_id, 'service' => $p->service, 'start_date' => $p->end_date ?? today()]);
        }
        $w->coverage ??= 'Workmanship and water-tightness of the treated roof area. Excludes damage from third-party works, structural movement or natural disasters.';

        return view('warranties.form', ['w' => $w, ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $w = DB::transaction(fn () => Warranty::create($data + ['doc_no' => DocNo::next('warranties', 'WR'), 'status' => 'active']));

        return redirect()->route('warranties.show', $w)->with('ok', __('Warranty :doc issued — valid until :date.', ['doc' => $w->doc_no, 'date' => d($w->end_date)]));
    }

    public function show(Warranty $warranty)
    {
        $warranty->load(['customer', 'project', 'serviceRequests.assignee']);

        return view('warranties.show', ['w' => $warranty]);
    }

    public function edit(Warranty $warranty)
    {
        return view('warranties.form', ['w' => $warranty, ...$this->lookups()]);
    }

    public function update(Request $request, Warranty $warranty)
    {
        $data = $this->validated($request);
        $data['status'] = $request->input('status') === 'void' ? 'void' : 'active';
        $warranty->update($data);

        return redirect()->route('warranties.show', $warranty)->with('ok', __('Warranty updated.'));
    }

    public function destroy(Warranty $warranty)
    {
        if ($warranty->serviceRequests()->exists()) {
            return back()->with('error', __('Service requests are linked to this warranty — mark it Void instead.'));
        }
        $warranty->delete();

        return redirect()->route('warranties.index')->with('ok', __('Warranty :doc deleted.', ['doc' => $warranty->doc_no]));
    }

    public function print(Warranty $warranty)
    {
        $warranty->load(['customer', 'project']);

        return view('warranties.print', ['w' => $warranty]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'service' => 'required|string|max:64',
            'coverage' => 'nullable|string|max:5000',
            'start_date' => 'required|date',
            'years' => 'required|integer|min:1|max:30',
        ]);
        $data['end_date'] = Carbon::parse($data['start_date'])->addYears((int) $data['years'])->toDateString();

        return $data;
    }

    private function lookups(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(['id', 'code', 'name'])->mapWithKeys(fn ($c) => [$c->id => $c->code.' — '.$c->name])->all(),
            'projects' => Project::with('customer:id,name')->orderByDesc('id')->get(['id', 'code', 'name', 'customer_id'])->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->name])->all(),
            'services' => config('erp.services'),
        ];
    }
}
