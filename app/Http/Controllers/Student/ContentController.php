<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Presenters\ContentPresenter;
use App\Models\Group;
use App\Models\Material;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Course materials and library of the student: only what is published in their
 * current groups (rules 12, 18), each item once even if published in several groups.
 */
class ContentController extends Controller
{
    public function materials(Request $request): Response
    {
        $student = $request->user();
        $mine = $student->groups()->pluck('groups.id');
        $materials = Material::visibleTo($student)->with(['teacher:id,first_name,last_name', 'groups:id,name,subject'])->latest()->get();

        return Inertia::render('student/content/Index', [
            'kind' => 'support',
            'items' => $materials->map(fn (Material $m) => $this->item($m, 'support', $mine))->values(),
        ]);
    }

    public function resources(Request $request): Response
    {
        $student = $request->user();
        $mine = $student->groups()->pluck('groups.id');
        $resources = Resource::visibleTo($student)->with(['teacher:id,first_name,last_name', 'groups:id,name,subject'])->latest()->get();

        return Inertia::render('student/content/Index', [
            'kind' => 'ressource',
            'items' => $resources->map(fn (Resource $r) => $this->item($r, 'ressource', $mine))->values(),
        ]);
    }

    /**
     * @param  Collection<int, int>  $myGroups  ids of the student's current groups
     * @return array<string, mixed>
     */
    private function item(Model $content, string $kind, Collection $myGroups): array
    {
        /** @var Collection<int, Group> $groups */
        $groups = $content->getRelation('groups')->whereIn('id', $myGroups)->values();
        $data = ContentPresenter::present($content->setRelation('groups', $groups), $kind);

        return [
            ...$data,
            'teacher' => $content->getRelation('teacher')->name,
            // Materials have no subject of their own: the subject of the group they are published in.
            'subject' => $data['subject'] ?? $groups->first()?->subject,
        ];
    }
}
