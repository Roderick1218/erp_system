<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $q = Payment::query()->with(['invoice.customer', 'creator']);
        if ($s = trim((string) $request->input('q'))) {
            $q->where(fn ($w) => $w->where('reference', 'like', "%$s%")
                ->orWhereHas('invoice', fn ($i) => $i->where('doc_no', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%"))));
        }
        $q->when($request->input('method'), fn ($w, $v) => $w->where('method', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('paid_on', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('paid_on', '<=', $v));

        $byMethod = (clone $q)->reorder()->selectRaw('method, SUM(amount) s, COUNT(*) n')->groupBy('method')->get();

        return view('payments.index', [
            'rows' => (clone $q)->orderByDesc('paid_on')->orderByDesc('id')->paginate(25)->withQueryString(),
            'sum' => (float) $byMethod->sum('s'),
            'byMethod' => $byMethod,
            'methods' => config('erp.payment_methods'),
        ]);
    }

    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'paid_on' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::in(config('erp.payment_methods'))],
            'reference' => 'nullable|string|max:64',
        ]);

        DB::transaction(function () use ($invoice, $data, $request) {
            $inv = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (! in_array($inv->status, ['unpaid', 'partial'], true)) {
                throw ValidationException::withMessages(['amount' => __('Payments can only be recorded on issued, unpaid invoices.')]);
            }
            if ((float) $data['amount'] > $inv->balance + 0.004) {
                throw ValidationException::withMessages(['amount' => __('Amount is more than the balance due (:balance).', ['balance' => money($inv->balance)])]);
            }
            $inv->payments()->create($data + ['created_by' => $request->user()->id]);
            $inv->recalcPaid();
        });

        $invoice->refresh();

        return back()->with('ok', __('Payment of :amount recorded — :no is now :status.', ['amount' => money($data['amount']), 'no' => $invoice->doc_no, 'status' => Status::label($invoice->status)]));
    }

    public function destroy(Payment $payment)
    {
        $invoice = $payment->invoice;
        DB::transaction(function () use ($payment, $invoice) {
            $payment->delete();
            $invoice->recalcPaid();
        });

        return back()->with('ok', __('Payment removed — balance recalculated.'));
    }
}
