<?php

namespace App\Http\Controllers\Student;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitHomeworkRequest;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\FileStore;
use App\Support\SubmissionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Homework of the student: see it, hand it in (file and/or text), replace it until the deadline. */
class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        $assignments = Assignment::visibleTo($student)->with('teacher:id,first_name,last_name')->orderBy('due_at')->get();
        $submissions = $student->submissions()->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');

        return Inertia::render('student/assignments/Index', [
            'assignments' => $assignments->map(function (Assignment $a) use ($submissions) {
                $submission = $submissions->get($a->id);
                $state = SubmissionState::for($a, $submission);

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'teacher' => $a->teacher->name,
                    'due_at' => $a->due_at->toIso8601String(),
                    'allow_late' => $a->allow_late,
                    'state' => $state->value,
                    'state_label' => $state->label(),
                    'grade' => $submission?->grade === null ? null : (float) $submission->grade,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, Assignment $assignment): Response
    {
        Gate::authorize('view', $assignment);
        $assignment->load(['teacher:id,first_name,last_name', 'material']);
        $submission = $assignment->submissions()->where('student_id', $request->user()->id)->first();
        $state = SubmissionState::for($assignment, $submission);
        $acceptance = SubmissionState::acceptance($assignment);

        return Inertia::render('student/assignments/Show', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'teacher' => $assignment->teacher->name,
                'due_at' => $assignment->due_at->toIso8601String(),
                'allow_late' => $assignment->allow_late,
                'material' => $assignment->material ? [
                    'title' => $assignment->material->title,
                    'url' => $assignment->material->url,
                    'file' => $assignment->material->file_name ? [
                        'name' => $assignment->material->file_name,
                        'size' => $assignment->material->file_size,
                        'extension' => $assignment->material->fileExtension(),
                        'download' => route('files.link', ['kind' => 'support', 'id' => $assignment->material->id]),
                    ] : null,
                ] : null,
            ],
            'state' => $state->value,
            'stateLabel' => $state->label(),
            // Rules 14-15: replaceable until the due date, late only if allowed; a graded work is final.
            'canSubmit' => $acceptance !== null && $state !== SubmissionStatus::Graded,
            'willBeLate' => $acceptance === true,
            'submission' => $submission ? [
                'body' => $submission->body,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'is_late' => $submission->is_late,
                'grade' => $submission->grade === null ? null : (float) $submission->grade,
                'comment' => $submission->comment,
                'graded_at' => $submission->graded_at?->toIso8601String(),
                'file' => $submission->file_name ? [
                    'name' => $submission->file_name,
                    'size' => $submission->file_size,
                    'extension' => $submission->fileExtension(),
                    'download' => route('files.link', ['kind' => 'rendu', 'id' => $submission->id]),
                ] : null,
            ] : null,
            'limits' => ['max_mb' => round(config('focusia.max_upload_kb') / 1024), 'extensions' => array_keys(config('focusia.file_types'))],
        ]);
    }

    public function submit(SubmitHomeworkRequest $request, Assignment $assignment, FileStore $files): RedirectResponse
    {
        $student = $request->user();
        $existing = $assignment->submissions()->where('student_id', $student->id)->first();

        if ($existing?->grade !== null) {
            return back()->with('error', 'Ce devoir est déjà noté : le rendu ne peut plus être modifié.');
        }
        $late = SubmissionState::acceptance($assignment);
        if ($late === null) {
            return back()->with('error', "La date limite est dépassée : votre enseignant n'accepte pas les rendus en retard.");
        }

        DB::transaction(function () use ($request, $assignment, $student, $existing, $files, $late) {
            $submission = $existing ?? new Submission(['assignment_id' => $assignment->id, 'student_id' => $student->id]);
            $old = $submission->file_path;

            if ($request->hasFile('file')) {
                $submission->fill($files->store($request->file('file'), "submissions/{$assignment->id}"));
            } elseif ($request->boolean('remove_file')) {
                $submission->fill($files->forget(null));
            }
            $submission->fill([
                'body' => $request->filled('body') ? $request->input('body') : null,
                'submitted_at' => now(),
                'is_late' => $late,
            ])->save();

            if ($old && $old !== $submission->file_path) {
                $files->disk()->delete($old);
            }
        });

        $message = $existing ? 'Votre rendu a été remplacé.' : 'Devoir rendu !';

        return back()->with('success', $late ? "{$message} Il est signalé en retard." : $message);
    }
}
