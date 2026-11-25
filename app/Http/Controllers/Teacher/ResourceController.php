<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ResourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ManagesContent;
use App\Http\Presenters\ContentPresenter;
use App\Http\Requests\Teacher\ResourceRequest;
use App\Models\Resource;
use App\Services\FileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Library resources of the teacher (document, video, link, exercise), classified by subject and level. */
class ResourceController extends Controller
{
    use ManagesContent;

    private const FIELDS = ['title', 'description', 'type', 'subject', 'level'];

    public function index(Request $request): Response
    {
        $resources = $request->user()->resources()->with('groups:id,name')->latest()->get();

        return Inertia::render('teacher/content/Index', [
            'kind' => 'ressource',
            'items' => $resources->map(fn (Resource $r) => ContentPresenter::present($r, 'ressource')),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('teacher/content/Form', [
            'kind' => 'ressource',
            'item' => null,
            'types' => self::types(),
            ...$this->formOptions($request),
        ]);
    }

    public function store(ResourceRequest $request, FileStore $files): RedirectResponse
    {
        $resource = new Resource(['teacher_id' => $request->user()->id]);
        $this->saveContent($request, $resource, self::FIELDS, "resources/{$request->user()->id}", $files);

        return to_route('teacher.resources.index')->with('success', "Ressource « {$resource->title} » ajoutée à la bibliothèque.");
    }

    public function edit(Request $request, Resource $resource): Response
    {
        Gate::authorize('manage', $resource);

        return Inertia::render('teacher/content/Form', [
            'kind' => 'ressource',
            'item' => ContentPresenter::present($resource->load('groups:id,name'), 'ressource'),
            'types' => self::types(),
            ...$this->formOptions($request),
        ]);
    }

    public function update(ResourceRequest $request, Resource $resource, FileStore $files): RedirectResponse
    {
        $this->saveContent($request, $resource, self::FIELDS, "resources/{$request->user()->id}", $files);

        return to_route('teacher.resources.index')->with('success', "Ressource « {$resource->title} » mise à jour.");
    }

    public function destroy(Resource $resource, FileStore $files): RedirectResponse
    {
        Gate::authorize('manage', $resource);

        $files->forget($resource->file_path);
        $resource->delete();

        return to_route('teacher.resources.index')->with('success', "Ressource « {$resource->title} » supprimée.");
    }

    /** @return list<array{value: string, label: string}> */
    private static function types(): array
    {
        return array_map(fn (ResourceType $t) => ['value' => $t->value, 'label' => $t->label()], ResourceType::cases());
    }
}
