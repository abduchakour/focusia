<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ManagesContent;
use App\Http\Presenters\ContentPresenter;
use App\Http\Requests\Teacher\MaterialRequest;
use App\Models\Material;
use App\Services\FileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Course materials of the teacher (file and/or link, published in groups). */
class MaterialController extends Controller
{
    use ManagesContent;

    private const FIELDS = ['title', 'description', 'type'];

    public function index(Request $request): Response
    {
        $materials = $request->user()->materials()->with('groups:id,name')->withCount('assignments')->latest()->get();

        return Inertia::render('teacher/content/Index', [
            'kind' => 'support',
            'items' => $materials->map(fn (Material $m) => [...ContentPresenter::present($m, 'support'), 'assignments_count' => $m->assignments_count]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('teacher/content/Form', [
            'kind' => 'support',
            'item' => null,
            'types' => self::types(),
            ...$this->formOptions($request),
        ]);
    }

    public function store(MaterialRequest $request, FileStore $files): RedirectResponse
    {
        $material = new Material(['teacher_id' => $request->user()->id]);
        $this->saveContent($request, $material, self::FIELDS, "materials/{$request->user()->id}", $files);

        return to_route('teacher.materials.index')->with('success', "Support « {$material->title} » enregistré.");
    }

    public function edit(Request $request, Material $material): Response
    {
        Gate::authorize('manage', $material);

        return Inertia::render('teacher/content/Form', [
            'kind' => 'support',
            'item' => ContentPresenter::present($material->load('groups:id,name'), 'support'),
            'types' => self::types(),
            ...$this->formOptions($request),
        ]);
    }

    public function update(MaterialRequest $request, Material $material, FileStore $files): RedirectResponse
    {
        $this->saveContent($request, $material, self::FIELDS, "materials/{$request->user()->id}", $files);

        return to_route('teacher.materials.index')->with('success', "Support « {$material->title} » mis à jour.");
    }

    public function destroy(Material $material, FileStore $files): RedirectResponse
    {
        Gate::authorize('manage', $material);

        // Rule 19: linked homework is kept; the database only clears its link (nullOnDelete).
        $linked = $material->assignments()->count();
        $files->forget($material->file_path);
        $material->delete();

        $message = "Support « {$material->title} » supprimé.";
        if ($linked > 0) {
            $message .= $linked > 1 ? " Les {$linked} devoirs liés sont conservés, sans ce support." : ' Le devoir lié est conservé, sans ce support.';
        }

        return to_route('teacher.materials.index')->with('success', $message);
    }

    /** @return list<array{value: string, label: string}> */
    private static function types(): array
    {
        return array_map(fn (MaterialType $t) => ['value' => $t->value, 'label' => $t->label()], MaterialType::cases());
    }
}
