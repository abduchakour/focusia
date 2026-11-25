<?php

namespace App\Http\Requests\Teacher;

use App\Enums\Level;
use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update a quiz with its questions in one request.
 *
 * Payload: title, description, subject, level, duration_minutes, due_at, corrections_at,
 * group_ids[], publish (bool), questions[]: { text, notion_id, choices: [4 strings], correct: 0..3 }.
 *
 * Rule 4: exactly 4 choices and one correct answer (`correct` is a single index, so exactly one).
 * Rule 5: every question is linked to one of the teacher's notions.
 */
class QuizRequest extends FormRequest
{
    /** Ownership is checked before validation: an intruder gets 403, not validation errors. */
    public function authorize(): bool
    {
        return $this->quiz() === null || $this->user()->can('manage', $this->quiz());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $teacherId = $this->user()->id;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject' => ['required', 'string', 'max:150'],
            'level' => ['required', Rule::enum(Level::class)],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'due_at' => ['required', 'date'],
            'corrections_at' => ['required', 'date', 'after_or_equal:due_at'],
            'publish' => ['boolean'],
            'group_ids' => ['array'],
            'group_ids.*' => ['integer', 'distinct', Rule::exists('groups', 'id')->where('teacher_id', $teacherId)],
        ];

        if (! $this->questionsLocked()) {
            $rules += [
                'questions' => ['required', 'array', 'min:1', 'max:100'],
                'questions.*.text' => ['required', 'string', 'max:2000'],
                'questions.*.notion_id' => ['required', 'integer', Rule::exists('notions', 'id')->where('teacher_id', $teacherId)],
                'questions.*.choices' => ['required', 'array', 'size:4'],
                'questions.*.choices.*' => ['required', 'string', 'max:500'],
                'questions.*.correct' => ['required', 'integer', 'between:0,3'],
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->boolean('publish')) {
                    return;
                }
                if (count($this->input('group_ids', [])) === 0) {
                    $validator->errors()->add('group_ids', 'Choisissez au moins un groupe pour publier le quiz.');
                }
                // A quiz being published must still be open (an already published quiz may be edited later).
                $alreadyPublished = $this->quiz()?->published_at !== null;
                if (! $alreadyPublished && $this->date('due_at')?->isPast()) {
                    $validator->errors()->add('due_at', 'La date limite doit être dans le futur pour publier le quiz.');
                }
            },
        ];
    }

    /** Decision 2026-11-25: once a student has answered, questions can no longer change. */
    public function questionsLocked(): bool
    {
        return $this->quiz()?->attempts()->exists() ?? false;
    }

    public function quiz(): ?Quiz
    {
        $quiz = $this->route('quiz');

        return $quiz instanceof Quiz ? $quiz : null;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'questions.required' => 'Ajoutez au moins une question.',
            'questions.min' => 'Ajoutez au moins une question.',
            'questions.*.text.required' => 'Écrivez l’énoncé de la question.',
            'questions.*.notion_id.required' => 'Choisissez la notion évaluée par cette question.',
            'questions.*.notion_id.exists' => 'Cette notion n’existe pas.',
            'questions.*.choices.size' => 'Chaque question doit avoir exactement 4 propositions.',
            'questions.*.choices.*.required' => 'Remplissez les 4 propositions.',
            'questions.*.correct.required' => 'Indiquez la bonne réponse.',
            'questions.*.correct.between' => 'Indiquez la bonne réponse.',
            'corrections_at.after_or_equal' => 'Les corrections doivent être publiées après la date limite.',
            'group_ids.*.exists' => 'Ce groupe n’existe pas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'titre',
            'subject' => 'matière',
            'level' => 'niveau',
            'duration_minutes' => 'durée',
            'due_at' => 'date limite',
            'corrections_at' => 'date de publication des corrections',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title) ? trim($this->title) : $this->title,
            'subject' => is_string($this->subject) ? trim($this->subject) : $this->subject,
            'duration_minutes' => $this->duration_minutes === '' ? null : $this->duration_minutes,
        ]);
    }
}
