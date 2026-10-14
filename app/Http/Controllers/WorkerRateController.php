<?php

namespace App\Http\Controllers;

use App\Models\WorkerRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Inventory → Worker Rate Management: standard labour rates used when costing jobs. */
class WorkerRateController extends Controller
{
    public const TYPES = ['day' => 'per day', 'hour' => 'per hour', 'sqft' => 'per sq ft', 'job' => 'per job'];

    public function index(Request $request)
    {
        $rows = WorkerRate::query()
            ->when(trim((string) $request->input('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('trade', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))
            ->when($request->input('rate_type'), fn ($q, $v) => $q->where('rate_type', $v))
            ->orderByDesc('is_active')->orderBy('trade')->get();

        return view('worker_rates.index', ['rows' => $rows, 'types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $r = WorkerRate::create($data);

        return back()->with('ok', __('Rate for :trade added.', ['trade' => $r->trade]));
    }

    public function update(Request $request, WorkerRate $workerRate)
    {
        $data = $this->validated($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $workerRate->update($data);

        return back()->with('ok', __('Rate for :trade updated.', ['trade' => $workerRate->trade]));
    }

    public function destroy(WorkerRate $workerRate)
    {
        $workerRate->delete();

        return back()->with('ok', __('Rate for :trade deleted.', ['trade' => $workerRate->trade]));
    }

    private function validated(Request $request)
    {
        $v = Validator::make($request->all(), [
            'trade' => 'required|string|max:64',
            'description' => 'nullable|string|max:255',
            'rate_type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'rate' => 'required|numeric|min:0',
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput()->with('reopen', 'dlg-rate');
        }

        return $v->validated() + ['is_active' => $request->boolean('is_active')];
    }
}
