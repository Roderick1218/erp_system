<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $holidays = Holiday::with('branch')->whereYear('date', $year)->orderBy('date')->get();

        return view('holidays.index', [
            'year' => $year,
            'byMonth' => $holidays->groupBy(fn ($h) => $h->date->format('F')),
            'count' => $holidays->count(),
            'next' => Holiday::whereDate('date', '>=', today()->toDateString())->orderBy('date')->first(),
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
        ]);
        if (Holiday::whereDate('date', $data['date'])->where('branch_id', $data['branch_id'] ?? null)->exists()) {
            return back()->with('error', __('A holiday already exists on that date for this branch.'));
        }
        $h = Holiday::create($data);

        return redirect()->route('holidays.index', ['year' => $h->date->year])->with('ok', __('Holiday “:name” added.', ['name' => __($h->name)]));
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('ok', __('Holiday “:name” removed.', ['name' => __($holiday->name)]));
    }
}
