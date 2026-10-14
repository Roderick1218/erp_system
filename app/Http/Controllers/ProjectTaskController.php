<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $project->tasks()->max('sort') + 1;
        $project->tasks()->create($data);

        return redirect()->to(route('projects.show', $project).'#tasks')->with('ok', __('Task added.'));
    }

    /** Full edit from the dialog, or a status-only change (JSON from the task list). */
    public function update(Request $request, ProjectTask $task)
    {
        if ($request->has('title')) {
            $task->update($this->validated($request));
        } else {
            $task->update($request->validate(['status' => 'required|in:todo,doing,done']));
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $task->status]);
        }

        return redirect()->to(route('projects.show', $task->project_id).'#tasks')->with('ok', __('Task updated.'));
    }

    public function destroy(ProjectTask $task)
    {
        $projectId = $task->project_id;
        $task->delete();

        return redirect()->to(route('projects.show', $projectId).'#tasks')->with('ok', __('Task deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'assignee_id' => 'nullable|exists:employees,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:todo,doing,done',
        ]);
    }
}
