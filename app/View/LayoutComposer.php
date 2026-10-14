<?php

namespace App\View;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LeaveRequest;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Feeds the app shell: permission-filtered sidebar menu, Ctrl+K page list
 * and the bell notifications (things waiting for this user).
 */
class LayoutComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $current = Route::currentRouteName();
        $menu = [];
        $pages = [];

        foreach (config('erp.menu') as $entry) {
            if (isset($entry['route'])) {
                $menu[] = ['label' => __($entry['label'])] + $entry + ['url' => route($entry['route']), 'active' => $current === $entry['route']];
                $pages[] = ['label' => __($entry['label']), 'group' => '', 'kw' => $entry['label'], 'url' => route($entry['route'])];

                continue;
            }
            $items = [];
            foreach ($entry['items'] as $item) {
                if (! empty($item['can']) && ! $user->can($item['can'])) {
                    continue;
                }
                if (! Route::has($item['route'])) {
                    continue;
                }
                $url = route($item['route'], $item['params'] ?? []);
                $items[] = ['label' => __($item['label'])] + $item + ['url' => $url, 'active' => $this->isActive($item, $current)];
                $pages[] = ['label' => __($item['label']), 'group' => __($entry['group']), 'kw' => $item['label'].' '.$entry['group'], 'url' => $url];
            }
            if ($items) {
                // Only one entry per group may be highlighted (e.g. List vs Board share a route).
                $hit = false;
                foreach ($items as &$i) {
                    $i['active'] = $i['active'] && ! $hit;
                    $hit = $hit || $i['active'];
                }
                unset($i);
                $menu[] = ['group' => __($entry['group']), 'icon' => $entry['icon'], 'items' => $items, 'active' => $hit];
            }
        }

        $view->with([
            'navMenu' => $menu,
            'palettePages' => $pages,
            'notifications' => $this->notifications($user),
        ]);
    }

    private function isActive(array $item, ?string $current): bool
    {
        if (! $current) {
            return false;
        }
        if (! empty($item['params'])) {
            foreach ($item['params'] as $k => $v) {
                if (request($k) !== $v) {
                    return false;
                }
            }

            return $current === $item['route'];
        }
        if ($current === $item['route']) {
            return request('view') === null || request('view') === 'list';
        }
        // projects.index also lights up for projects.show / projects.edit …
        $prefix = substr($item['route'], 0, strrpos($item['route'], '.'));

        return str_ends_with($item['route'], '.index') && str_starts_with($current, $prefix.'.')
            && ! in_array($current, ['projects.calendar', 'projects.schedule', 'service-requests.calendar', 'attendance.board', 'attendance.report', 'leave.approvals'], true);
    }

    /** @return array<int, array{icon:string,title:string,text:string,url:string,tone:string}> */
    private function notifications($user): array
    {
        $n = [];
        if ($user->can('hr.manage')) {
            $pending = LeaveRequest::where('status', 'pending')->count();
            if ($pending) {
                $n[] = ['icon' => 'calendar', 'title' => __(':n leave request(s) awaiting approval', ['n' => $pending]), 'text' => __('HR').' · '.__('Leave Approval'), 'url' => route('leave.approvals'), 'tone' => 'warning'];
            }
            $soon = now()->addDays(30)->toDateString();
            $expiring = Employee::active()->where(fn ($q) => $q->where('passport_expiry', '<=', $soon)->orWhere('work_permit_expiry', '<=', $soon)
                ->orWhere('cidb_expiry', '<=', $soon)->orWhere('gdl_expiry', '<=', $soon))->count();
            if ($expiring) {
                $n[] = ['icon' => 'identification', 'title' => __(':n employee document(s) expire within 30 days', ['n' => $expiring]), 'text' => __('Passport / work permit / CIDB / GDL'), 'url' => route('dashboard').'#expiry', 'tone' => 'danger'];
            }
        }
        if ($user->can('inventory.view')) {
            $low = Item::where('is_active', true)->where('reorder_level', '>', 0)->withSum('balances', 'qty')->get()
                ->filter(fn ($i) => (float) $i->balances_sum_qty <= (float) $i->reorder_level)->count();
            if ($low) {
                $n[] = ['icon' => 'cube', 'title' => __(':n item(s) at or below reorder level', ['n' => $low]), 'text' => __('Inventory').' · '.__('Stock Master'), 'url' => route('items.index', ['stock' => 'low']), 'tone' => 'warning'];
            }
        }
        if ($user->can('sales.manage')) {
            $overdue = Invoice::where('type', 'invoice')->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->count();
            if ($overdue) {
                $n[] = ['icon' => 'banknotes', 'title' => __(':n invoice(s) overdue', ['n' => $overdue]), 'text' => __('Sales').' · '.__('Invoices'), 'url' => route('invoices.index', ['status' => 'overdue']), 'tone' => 'danger'];
            }
        }
        if ($user->employee_id) {
            $mine = ServiceRequest::where('assigned_to', $user->employee_id)->whereIn('status', ['open', 'scheduled'])->count();
            if ($mine && $user->can('service.manage')) {
                $n[] = ['icon' => 'wrench', 'title' => __(':n service job(s) assigned to you', ['n' => $mine]), 'text' => __('After-sales').' · '.__('Service Requests'), 'url' => route('service-requests.index', ['mine' => 1]), 'tone' => 'info'];
            }
        }

        return $n;
    }
}
