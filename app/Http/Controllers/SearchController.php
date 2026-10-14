<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Project;
use App\Models\Quotation;
use App\Support\Status;
use Illuminate\Http\Request;

/** JSON results for the Ctrl+K command palette — only modules the user may see. */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.$q.'%';
        $user = $request->user();
        $out = [];

        if ($user->can('customers.view')) {
            foreach (Customer::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('contact_person', 'like', $like))->limit(6)->get() as $c) {
                $out[] = ['type' => 'Customers', 'label' => $c->name, 'hint' => $c->code.' · '.$c->phone, 'url' => route('customers.show', $c)];
            }
            foreach (Enquiry::with('customer')->where(fn ($w) => $w->where('doc_no', 'like', $like)->orWhere('site_address', 'like', $like))->limit(4)->get() as $e) {
                $out[] = ['type' => 'Enquiries', 'label' => $e->doc_no.' — '.$e->customer->name, 'hint' => $e->service, 'url' => route('enquiries.show', $e)];
            }
        }
        if ($user->can('projects.view')) {
            foreach (Project::where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('site_address', 'like', $like))->limit(6)->get() as $p) {
                $out[] = ['type' => 'Projects', 'label' => $p->code.' — '.$p->name, 'hint' => Status::label($p->status), 'url' => route('projects.show', $p)];
            }
        }
        if ($user->can('sales.manage')) {
            foreach (Quotation::with('customer')->where('doc_no', 'like', $like)->limit(4)->get() as $d) {
                $out[] = ['type' => 'Quotations', 'label' => $d->doc_no.' — '.$d->customer->name, 'hint' => money($d->total), 'url' => route('quotations.show', $d)];
            }
            foreach (Invoice::with('customer')->where('doc_no', 'like', $like)->limit(4)->get() as $d) {
                $out[] = ['type' => 'Invoices', 'label' => $d->doc_no.' — '.$d->customer->name, 'hint' => money($d->total), 'url' => route('invoices.show', $d)];
            }
        }
        if ($user->can('inventory.view')) {
            foreach (Item::where(fn ($w) => $w->where('sku', 'like', $like)->orWhere('name', 'like', $like))->limit(5)->get() as $i) {
                $out[] = ['type' => 'Stock items', 'label' => $i->name, 'hint' => $i->sku, 'url' => route('items.show', $i)];
            }
        }
        if ($user->can('hr.manage')) {
            foreach (Employee::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('nickname', 'like', $like)->orWhere('emp_no', 'like', $like))->limit(5)->get() as $e) {
                $out[] = ['type' => 'Employees', 'label' => $e->display_name, 'hint' => $e->emp_no.' · '.$e->position, 'url' => route('employees.show', $e)];
            }
        }

        return response()->json($out);
    }
}
