<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\AssignmentRequest;
use App\Http\Requests\Teacher\GradeRequest;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Submission;
use App\Models\User;
use App\Support\SubmissionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Homework of the teacher: create, publish in groups, follow submissions, grade (rules 13–17). */
class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $assignments = $request->user()->assignments()
            ->with(['groups:id,name', 'material:id,title'])
            ->withCount(['submissions', 'submissions as graded_count' => fn ($q) => $q->whereNotNull('grade')])
            ->orderByDesc('due_at')
            ->get();

        return Inertia::render('teacher/assignments/Index', [
            'assignments' => $assignments->map(fn (Assignment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'due_at' => $a->due_at->toIso8601String(),
                'is_open' => $a->due_at->isFuture(),
                'allow_late' => $a->allow_late,
                'material' => $a->material?->title,
                'groups' => $a->groups->pluck('name'),
                'submitted' => $a->submissions_count,
                'graded' => $a->graded_count,
                'expected' => $this->activeStudentIds($a)->count(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('teacher/assignments/Form', ['assignment' => null, ...$this->options($request)]);
    }

    public function store(AssignmentRequest $request): RedirectResponse
    {
        $assignment = DB::transaction(function () use ($request) {
            $assignment = $request->user()->assignments()->create($this->attributes($request));
            $assignment->groups()->sync($request->validated('group_ids'));

            return $assignment;
        });

        return to_route('teacher.assignments.show', ['assignment' => $assignment->id])->with('success', "Devoir « {$assignment->title} » publié.");
    }

    public function show(Assignment $assignment): Response
    {
        Gate::authorize('manage', $assignment);
        $assignment->load(['groups:id,name', 'material:id,title']);

        $submissions = $assignment->submissions()->with('student:id,first_name,last_name')->get()->keyBy('student_id');
        $activeIds = $this->activeStudentIds($assignment);
        $groupNames = Enrollment::whereIn('group_id', $assignment->groups->pluck('id'))->with('group:id,name')->get()
            ->groupBy('student_id')->map(fn ($e) => $e->pluck('group.name')->unique()->values());

        // Current members (with or without a submission) + removed students who had submitted (rule 18).
        $students = User::whereIn('id', $activeIds->merge($submissions->keys())->unique())
            ->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        $rows = $students->map(function (User $student) use ($assignment, $submissions, $activeIds, $groupNames) {
            /** @var Submission|null $submission */
            $submission = $submissions->get($student->id);
            $state = SubmissionState::for($assignment, $submission);

            return [
                'student' => ['id' => $student->id, 'name' => $student->name],
                'groups' => $groupNames->get($student->id, []),
                'removed' => ! $activeIds->contains($student->id),
                'state' => $state->value,
                'state_label' => $state->label(),
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'body' => $submission->body,
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                    'is_late' => $submission->is_late,
                    'grade' => $submission->grade === null ? null : (float) $submission->grade,
                    'comment' => $submission->comment,
                    'file' => $submission->file_name ? [
                        'name' => $submission->file_name,
                        'size' => $submission->file_size,
                        'extension' => $submission->fileExtension(),
                        'download' => route('files.link', ['kind' => 'rendu', 'id' => $submission->id]),
                    ] : null,
                ] : null,
            ];
        });

        $grades = $submissions->pluck('grade')->filter(fn ($g) => $g !== null)->map(fn ($g) => (float) $g);

        return Inertia::render('teacher/assignments/Show', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at->toIso8601String(),
                'is_open' => $assignment->due_at->isFuture(),
                'allow_late' => $assignment->allow_late,
                'material' => $assignment->material?->only(['id', 'title']),
                'groups' => $assignment->groups->pluck('name'),
            ],
            'stats' => [
                'expected' => $activeIds->count(),
                'submitted' => $submissions->count(),
                'late' => $submissions->where('is_late', true)->count(),
                'graded' => $grades->count(),
                // Rule 17: the homework average stays apart from quiz grades.
                'average' => $grades->isEmpty() ? null : round($grades->avg(), 2),
                'missing' => $rows->where('state', SubmissionStatus::Missing->value)->count(),
            ],
            'rows' => $rows->values(),
        ]);
    }

    public function edit(Request $request, Assignment $assignment): Response
    {
        Gate::authorize('manage', $assignment);

        return Inertia::render('teacher/assignments/Form', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at->format('Y-m-d\TH:i'),
                'allow_late' => $assignment->allow_late,
                'material_id' => $assignment->material_id,
                'group_ids' => $assignment->groups()->pluck('groups.id'),
                'submissions_count' => $assignment->submissions()->count(),
            ],
            ...$this->options($request),
        ]);
    }

    public function update(AssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment) {
            $assignment->update($this->attributes($request));
            $assignment->groups()->sync($request->validated('group_ids'));
        });

        return to_route('teacher.assignments.show', ['assignment' => $assignment->id])->with('success', 'Devoir mis à jour.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        Gate::authorize('manage', $assignment);

        // Students' work is never deleted silently.
        $count = $assignment->submissions()->count();
        if ($count > 0) {
            return back()->with('error', "Ce devoir a déjà {$count} rendu(s) : il ne peut pas être supprimé, pour conserver le travail des étudiants.");
        }
        $assignment->delete();

        return to_route('teacher.assignments.index')->with('success', "Devoir « {$assignment->title} » supprimé.");
    }

    /** Rule 16: 0 to 20, decimals allowed, optional comment. */
    public function grade(GradeRequest $request, Assignment $assignment, Submission $submission): RedirectResponse
    {
        $submission->update([
            'grade' => $request->validated('grade'),
            'comment' => $request->validated('comment'),
            'graded_at' => now(),
        ]);

        return back()->with('success', "Note enregistrée pour {$submission->student->name}.");
    }

    /** @return array<string, mixed> */
    private function attributes(AssignmentRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'instructions' => $request->validated('instructions'),
            'due_at' => $request->validated('due_at'),
            'allow_late' => $request->boolean('allow_late'),
            'material_id' => $request->validated('material_id'),
        ];
    }

    /** @return array<string, mixed> */
    private function options(Request $request): array
    {
        return [
            'groups' => $request->user()->teachingGroups()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name]),
            'materials' => $request->user()->materials()->latest()->get(['id', 'title'])
                ->map(fn ($m) => ['value' => (string) $m->id, 'label' => $m->title]),
        ];
    }

    /** @return Collection<int, int> */
    private function activeStudentIds(Assignment $assignment): Collection
    {
        return Enrollment::active()->whereIn('group_id', $assignment->groups()->select('groups.id'))->distinct()->pluck('student_id');
    }
}
