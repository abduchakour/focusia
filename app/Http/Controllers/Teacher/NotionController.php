<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\NotionRequest;
use App\Models\Notion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotionController extends Controller
{
    public function index(Request $request): Response
    {
        $notions = $request->user()->notions()
            ->withCount('questions')
            ->orderBy('subject')
            ->orderBy('name')
            ->get(['id', 'name', 'subject']);

        return Inertia::render('teacher/notions/Index', [
            'notions' => $notions,
            'subjects' => $request->user()->teachingGroups()->distinct()->orderBy('subject')->pluck('subject'),
        ]);
    }

    /** Also used inline from the quiz editor (Inertia keeps the editor state). */
    public function store(NotionRequest $request): RedirectResponse
    {
        $notion = $request->user()->notions()->create($request->validated());

        return back()->with('success', "Notion « {$notion->name} » ajoutée.");
    }

    public function update(NotionRequest $request, Notion $notion): RedirectResponse
    {
        Gate::authorize('manage', $notion);

        $notion->update($request->validated());

        return back()->with('success', 'Notion renommée.');
    }

    public function destroy(Notion $notion): RedirectResponse
    {
        Gate::authorize('manage', $notion);

        // Rule 5: a question always keeps its notion.
        if ($notion->questions()->exists()) {
            return back()->with('error', "« {$notion->name} » est utilisée par des questions : elle ne peut pas être supprimée.");
        }

        $notion->delete();

        return back()->with('success', "Notion « {$notion->name} » supprimée.");
    }
}
