<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Insights;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Understanding of one group, notion by notion, and the students who need help. */
class GroupInsightController extends Controller
{
    public function __invoke(Group $group, Insights $insights): Response
    {
        Gate::authorize('manage', $group);

        $data = $insights->group($group);

        return Inertia::render('teacher/groups/Insights', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject,
                'level' => $group->level->label(),
            ],
            'thresholds' => [
                'average' => (float) config('focusia.difficulty.average'),
                'rate' => (float) config('focusia.difficulty.notion_rate'),
            ],
            'quizzes' => $data['quizzes'],
            'notions' => $data['notions'],
            'students' => $data['students'],
        ]);
    }
}
