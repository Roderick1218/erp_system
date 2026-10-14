<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Holiday;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        $year = (int) $request->input('year', $today->year);
        $month = (int) $request->input('month', $today->month);

        $data = [
            'today' => $today,
            'attendance' => $user->employee_id ? Attendance::where('employee_id', $user->employee_id)->whereDate('work_date', $today)->first() : null,
            'announcements' => Announcement::whereYear('published_on', $year)->orderByDesc('is_pinned')->orderByDesc('published_on')->limit(12)->get(),
            'announcementYears' => Announcement::selectRaw('DISTINCT '.$this->yearExpr('published_on').' as y')->orderByDesc('y')->pluck('y')->push($today->year)->unique()->sortDesc()->values(),
            'year' => $year,
            'month' => $month,
            'holidays' => Holiday::whereDate('date', '>=', $today)->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $user->branch_id))->orderBy('date')->limit(8)->get(),
            'pending' => $this->pendingWork($user),
            'kpis' => [],
        ];

        if ($user->can('customers.view')) {
            $data['kpis'][] = ['label' => __('Open enquiries'), 'value' => Enquiry::whereNotIn('stage', ['won', 'lost'])->count(), 'icon' => 'funnel', 'tone' => 'info',
                'hint' => __(':n new in last 7 days', ['n' => Enquiry::where('enquiry_date', '>=', $today->copy()->subDays(7))->count()]), 'href' => route('enquiries.index', ['view' => 'board'])];
            $pipeline = Enquiry::selectRaw('stage, COUNT(*) as n, COALESCE(SUM(estimate_amount),0) as amt')->groupBy('stage')->get()->keyBy('stage');
            $data['pipeline'] = collect(Enquiry::STAGES)->map(fn ($label, $key) => ['label' => $label, 'n' => (int) ($pipeline[$key]->n ?? 0), 'amt' => (float) ($pipeline[$key]->amt ?? 0)]);
            $won = (int) ($pipeline['won']->n ?? 0);
            $lost = (int) ($pipeline['lost']->n ?? 0);
            $data['winRate'] = $won + $lost ? round($won / ($won + $lost) * 100) : null;
        }
        if ($user->can('projects.view')) {
            $data['kpis'][] = ['label' => __('Active projects'), 'value' => Project::whereIn('status', ['scheduled', 'in_progress'])->count(), 'icon' => 'briefcase', 'tone' => 'success',
                'hint' => __(':n on site now', ['n' => Project::where('status', 'in_progress')->count()]), 'href' => route('projects.index', ['view' => 'board'])];
        }
        if ($user->can('sales.manage')) {
            $outstanding = Invoice::where('type', 'invoice')->whereIn('status', ['unpaid', 'partial'])->selectRaw('COALESCE(SUM(total - paid_amount),0) as o, COUNT(*) as n')->first();
            $overdue = Invoice::where('type', 'invoice')->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', $today)->count();
            $data['kpis'][] = ['label' => __('Receivables'), 'value' => money($outstanding->o), 'icon' => 'banknotes', 'tone' => $overdue ? 'danger' : 'warning',
                'hint' => __(':n invoices · :o overdue', ['n' => $outstanding->n, 'o' => $overdue]), 'href' => route('invoices.index', ['status' => 'outstanding'])];
            $data['collected'] = Payment::whereBetween('paid_on', [$today->copy()->startOfMonth(), $today])->sum('amount');
            // Invoiced per month, last 6 months (single series → one hue).
            $data['revenue'] = collect(range(5, 0))->map(function ($i) use ($today) {
                $m = $today->copy()->startOfMonth()->subMonths($i);

                return ['label' => $m->translatedFormat('M'), 'full' => $m->translatedFormat('F Y'),
                    'amt' => (float) Invoice::where('type', 'invoice')->where('status', '!=', 'void')->whereBetween('invoice_date', [$m, $m->copy()->endOfMonth()])->sum('total')];
            });
        }
        if ($user->can('inventory.view')) {
            $low = Item::where('is_active', true)->where('reorder_level', '>', 0)->withSum('balances', 'qty')->get()
                ->filter(fn ($i) => (float) $i->balances_sum_qty <= (float) $i->reorder_level);
            $data['kpis'][] = ['label' => __('Low stock items'), 'value' => $low->count(), 'icon' => 'cube', 'tone' => $low->count() ? 'warning' : 'success',
                'hint' => __('At or below reorder level'), 'href' => route('items.index', ['stock' => 'low'])];
        }
        if ($user->can('hr.manage')) {
            $present = Attendance::whereDate('work_date', $today)->whereNotNull('clock_in')->count();
            $onLeave = LeaveRequest::where('status', 'approved')->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count();
            $data['kpis'][] = ['label' => __('Present today'), 'value' => $present.' / '.Employee::active()->count(), 'icon' => 'identification', 'tone' => 'neutral',
                'hint' => __(':n on leave', ['n' => $onLeave]), 'href' => route('attendance.board')];

            $data['birthdays'] = Employee::active()->whereNotNull('dob')->get()->filter(fn ($e) => $e->dob->month === $month)->sortBy(fn ($e) => $e->dob->day)->values();
            $data['expiry'] = collect([
                'passport_expiry' => ['Passport expiry', 'passport_no'],
                'work_permit_expiry' => ['Work permit expiry', 'work_permit_no'],
                'cidb_expiry' => ['CIDB expiry', 'cidb_no'],
                'gdl_expiry' => ['GDL expiry', 'gdl_no'],
            ])->map(fn ($meta, $col) => [
                'title' => $meta[0], 'numberCol' => $meta[1], 'col' => $col,
                'rows' => Employee::active()->whereNotNull($col)->whereDate($col, '<=', $today->copy()->addDays(60))->orderBy($col)->get(),
            ]);
        }

        return view('dashboard', $data);
    }

    /** Things waiting for THIS user, newest first. */
    private function pendingWork($user): array
    {
        $items = [];
        if ($user->can('hr.manage')) {
            foreach (LeaveRequest::with('employee', 'leaveType')->where('status', 'pending')->orderBy('start_date')->limit(5)->get() as $l) {
                $items[] = ['icon' => 'calendar', 'title' => __('Leave approval').' — '.$l->employee->display_name, 'meta' => __($l->leaveType->name).' · '.d($l->start_date).' · '.__(':n day(s)', ['n' => qty($l->days)]), 'url' => route('leave.approvals'), 'tone' => 'warning'];
            }
        }
        if ($user->can('customers.view')) {
            $q = Enquiry::with('customer')->whereIn('stage', ['new', 'inspection'])->where(fn ($q) => $q->whereNull('inspection_date')->orWhereDate('inspection_date', '<=', today()->addDays(2)));
            if (! $user->isManager()) {
                $q->where('salesman_id', $user->id);
            }
            foreach ($q->orderBy('enquiry_date')->limit(5)->get() as $e) {
                $items[] = ['icon' => 'funnel', 'title' => ($e->stage === 'new' ? __('Arrange inspection') : __('Inspection due')).' — '.$e->customer->name, 'meta' => $e->doc_no.' · '.__($e->service).($e->inspection_date ? ' · '.d($e->inspection_date) : ''), 'url' => route('enquiries.show', $e), 'tone' => 'info'];
            }
        }
        if ($user->employee_id) {
            foreach (ServiceRequest::with('customer')->where('assigned_to', $user->employee_id)->whereIn('status', ['open', 'scheduled'])->orderBy('scheduled_at')->limit(5)->get() as $s) {
                $items[] = ['icon' => 'wrench', 'title' => $s->title, 'meta' => $s->doc_no.' · '.$s->customer->name.' · '.d($s->scheduled_at, 'd M H:i'), 'url' => $user->can('service.manage') ? route('service-requests.show', $s) : '#', 'tone' => 'info'];
            }
            foreach (ProjectTask::with('project')->where('assignee_id', $user->employee_id)->where('status', '!=', 'done')->orderBy('end_date')->limit(5)->get() as $t) {
                $items[] = ['icon' => 'clipboard', 'title' => __($t->title), 'meta' => $t->project->code.' · '.__('due').' '.d($t->end_date), 'url' => $user->can('projects.view') ? route('projects.show', $t->project) : '#', 'tone' => 'neutral'];
            }
        }

        return $items;
    }

    private function yearExpr(string $col): string
    {
        return \DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%Y', $col) AS INTEGER)" : "YEAR($col)";
    }
}
