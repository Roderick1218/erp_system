<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectCost;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CostingController extends Controller
{
    /** Project Cost Management — budget vs actual per project, by category. */
    public function projects(Request $request)
    {
        $q = Project::query()->with(['customer', 'branch']);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('invoices', fn ($i) => $i->where('doc_no', 'like', "%$s%")));
        }
        $q->when($request->input('branch'), fn ($w, $v) => $w->where('branch_id', $v))
            ->when($request->input('status'), fn ($w, $v) => $w->where('status', $v), fn ($w) => $w->where('status', '!=', 'cancelled'));

        $projects = $q->orderByDesc('start_date')->get();
        $costs = ProjectCost::whereIn('project_id', $projects->pluck('id'))
            ->selectRaw('project_id, category, SUM(amount) as total')->groupBy('project_id', 'category')->get()
            ->groupBy('project_id')->map(fn ($g) => $g->pluck('total', 'category')->map(fn ($v) => (float) $v));
        $invoiced = Invoice::whereIn('project_id', $projects->pluck('id'))->where('type', 'invoice')->where('status', '!=', 'void')
            ->selectRaw('project_id, SUM(total) as t, SUM(paid_amount) as p')->groupBy('project_id')->get()->keyBy('project_id');

        $rows = $projects->map(function (Project $p) use ($costs, $invoiced) {
            $byCat = $costs[$p->id] ?? collect();
            $actual = (float) $byCat->sum();

            return [
                'p' => $p, 'cat' => $byCat, 'actual' => $actual,
                'budgetPct' => $p->budget_cost > 0 ? round($actual / (float) $p->budget_cost * 100) : null,
                'margin' => (float) $p->contract_value - $actual,
                'marginPct' => $p->contract_value > 0 ? round(((float) $p->contract_value - $actual) / (float) $p->contract_value * 100, 1) : null,
                'invoiced' => (float) ($invoiced[$p->id]->t ?? 0),
            ];
        });
        if ($request->input('flag') === 'over') {
            $rows = $rows->filter(fn ($r) => $r['budgetPct'] !== null && $r['budgetPct'] > 100)->values();
        }

        $totals = [
            'contract' => $rows->sum(fn ($r) => (float) $r['p']->contract_value),
            'budget' => $rows->sum(fn ($r) => (float) $r['p']->budget_cost),
            'actual' => $rows->sum('actual'),
            'cat' => collect(ProjectCost::CATEGORIES)->mapWithKeys(fn ($c) => [$c => $rows->sum(fn ($r) => (float) ($r['cat'][$c] ?? 0))]),
            'invoiced' => $rows->sum('invoiced'),
        ];
        $totals['margin'] = $totals['contract'] - $totals['actual'];

        return view('costing.projects', [
            'rows' => $rows, 'totals' => $totals, 'categories' => ProjectCost::CATEGORIES,
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id'),
            'overCount' => $rows->filter(fn ($r) => $r['budgetPct'] !== null && $r['budgetPct'] > 100)->count(),
        ]);
    }

    /** Costing Summary Report — cost vs revenue grouped by month / category / branch. */
    public function summary(Request $request)
    {
        [$from, $to, $group] = $this->params($request);

        return view('costing.summary', [
            'from' => $from, 'to' => $to, 'group' => $group,
            'rows' => $this->summaryRows($from, $to, $group),
            'categories' => ProjectCost::CATEGORIES,
        ]);
    }

    public function export(Request $request)
    {
        [$from, $to, $group] = $this->params($request);
        $rows = $this->summaryRows($from, $to, $group);
        $cats = ProjectCost::CATEGORIES;

        return csv_download("costing-summary-{$group}-{$from->format('Ymd')}-{$to->format('Ymd')}.csv",
            array_merge([['month' => __('Month'), 'category' => __('Cost category'), 'branch' => __('Branch')][$group]], array_map('__', $cats), [__('Total cost'), __('Revenue (invoiced)'), __('Gross margin'), __('Margin %')]),
            $rows->map(fn ($r) => array_merge([$group === 'category' ? __($r['label']) : $r['label']], array_map(fn ($c) => round($r['cat'][$c] ?? 0, 2), $cats),
                [round($r['cost'], 2), round($r['revenue'], 2), round($r['margin'], 2), $r['marginPct'] === null ? '' : $r['marginPct']])));
    }

    // ------------------------------------------------------------------

    private function params(Request $request): array
    {
        $from = Carbon::parse($request->input('from') ?: now()->startOfYear()->toDateString())->startOfDay();
        $to = Carbon::parse($request->input('to') ?: now()->toDateString())->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $group = in_array($request->input('group'), ['month', 'category', 'branch'], true) ? $request->input('group') : 'month';

        return [$from, $to, $group];
    }

    /** @return Collection<int, array{label:string,cat:array,cost:float,revenue:float,margin:float,marginPct:?float}> */
    private function summaryRows(Carbon $from, Carbon $to, string $group): Collection
    {
        $costs = ProjectCost::with('project:id,branch_id')->whereDate('cost_date', '>=', $from->toDateString())->whereDate('cost_date', '<=', $to->toDateString())->get();
        $invoices = Invoice::with('project:id,branch_id', 'customer:id,branch_id')->where('type', 'invoice')->where('status', '!=', 'void')
            ->whereDate('invoice_date', '>=', $from->toDateString())->whereDate('invoice_date', '<=', $to->toDateString())->get();
        $branches = Branch::pluck('legal_name', 'id');

        $keyCost = match ($group) {
            'month' => fn ($c) => $c->cost_date->format('Y-m'),
            'category' => fn ($c) => $c->category,
            'branch' => fn ($c) => (string) ($c->project?->branch_id ?? 0),
        };
        $keyInv = match ($group) {
            'month' => fn ($i) => $i->invoice_date->format('Y-m'),
            'category' => fn ($i) => null, // revenue is not split by cost category
            'branch' => fn ($i) => (string) ($i->project?->branch_id ?? $i->customer?->branch_id ?? 0),
        };

        if ($group === 'month') {
            $keys = collect();
            for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
                $keys->push($m->format('Y-m'));
            }
        } elseif ($group === 'category') {
            $keys = collect(ProjectCost::CATEGORIES);
        } else {
            $keys = $branches->keys()->map(fn ($k) => (string) $k)->push('0');
        }

        $costGroups = $costs->groupBy($keyCost);
        $revGroups = $invoices->groupBy(fn ($i) => $keyInv($i) ?? '_');

        return $keys->map(function ($key) use ($costGroups, $revGroups, $group, $branches) {
            $list = $costGroups[$key] ?? collect();
            $cost = (float) $list->sum('amount');
            $revenue = $group === 'category' ? 0.0 : (float) ($revGroups[$key] ?? collect())->sum('total');
            $label = match ($group) {
                'month' => str_starts_with(app()->getLocale(), 'zh') ? Carbon::parse($key.'-01')->format('Y-m') : Carbon::parse($key.'-01')->format('M Y'),
                'category' => $key,
                'branch' => $key === '0' ? __('Unassigned') : ($branches[$key] ?? $key),
            };

            return [
                'key' => $key, 'label' => $label,
                'cat' => $list->groupBy('category')->map(fn ($g) => (float) $g->sum('amount'))->all(),
                'cost' => $cost, 'revenue' => $revenue, 'margin' => $revenue - $cost,
                'marginPct' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
            ];
        })->filter(fn ($r) => $group !== 'branch' || $r['key'] !== '0' || $r['cost'] > 0 || $r['revenue'] > 0)->values();
    }
}
