<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    /** Append-only change history of core records (who changed what, old → new). */
    public function index(Request $request)
    {
        $rows = Activity::with(['causer', 'subject'])
            ->when($request->input('log'), fn ($q, $v) => $q->where('log_name', $v))
            ->when($request->input('event'), fn ($q, $v) => $q->where('event', $v))
            ->when($request->input('causer'), fn ($q, $v) => $q->where('causer_type', User::class)->where('causer_id', $v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')->paginate(30)->withQueryString();

        return view('audit.index', [
            'rows' => $rows,
            'logs' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter(),
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function logins(Request $request)
    {
        $rows = LoginActivity::with('user')
            ->when($request->input('user'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->input('event'), fn ($q, $v) => $q->where('event', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('email', 'like', "%$v%")->orWhere('ip', 'like', "%$v%")))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')->paginate(40)->withQueryString();

        return view('audit.logins', [
            'rows' => $rows,
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'failed24h' => LoginActivity::where('event', 'failed')->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }
}
