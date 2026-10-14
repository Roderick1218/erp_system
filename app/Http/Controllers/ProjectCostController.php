<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectCostController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'cost_date' => 'required|date',
            'category' => ['required', Rule::in(ProjectCost::CATEGORIES)],
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'reference' => 'nullable|string|max:64',
        ]);
        $project->costs()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->to(route('projects.show', $project).'#costs')->with('ok', __('Cost of :amount recorded.', ['amount' => money($data['amount'])]));
    }

    public function destroy(ProjectCost $cost)
    {
        if ($cost->stock_movement_id) {
            return back()->with('error', __('This material cost came from a stock issue (:ref). Return the stock in Inventory instead of deleting the cost line.', ['ref' => $cost->reference]));
        }
        $projectId = $cost->project_id;
        $cost->delete();

        return redirect()->to(route('projects.show', $projectId).'#costs')->with('ok', __('Cost line deleted.'));
    }
}
