<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $rows = Announcement::with('author')
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('body', 'like', "%$v%")))
            ->when($request->input('year'), fn ($q, $v) => $q->whereYear('published_on', $v))
            ->orderByDesc('is_pinned')->orderByDesc('published_on')->paginate(20)->withQueryString();

        return view('announcements.index', ['rows' => $rows]);
    }

    public function store(Request $request)
    {
        $a = Announcement::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('ok', __('Announcement “:title” published.', ['title' => $a->title]));
    }

    public function update(Request $request, Announcement $announcement)
    {
        // The pin button posts only is_pinned; the edit dialog posts everything.
        if ($request->has('toggle_pin')) {
            $announcement->update(['is_pinned' => ! $announcement->is_pinned]);

            return back()->with('ok', $announcement->is_pinned ? __('Pinned to the top of the dashboard.') : __('Unpinned.'));
        }
        $announcement->update($this->validated($request));

        return back()->with('ok', __('Announcement updated.'));
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('ok', __('Announcement deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:10000',
            'published_on' => 'required|date',
        ]);
        $data['is_pinned'] = $request->boolean('is_pinned');

        return $data;
    }
}
