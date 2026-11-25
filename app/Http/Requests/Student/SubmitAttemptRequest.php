<?php

namespace App\Http\Requests\Student;

use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Answers of a quiz, sent as JSON: { answers: { questionId: choiceId|null }, answered_at: ISO date }.
 * answered_at is the device time (corrected by the measured clock gap) when the student finished.
 */
class SubmitAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quiz $quiz */
        $quiz = $this->route('quiz');

        return Gate::allows('take', $quiz);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array', 'max:200'],
            'answers.*' => ['nullable', 'integer'],
            'answered_at' => ['required', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answers.*.integer' => 'Une réponse ne correspond pas à ce quiz.',
            'answered_at.required' => "L'heure de fin du quiz est manquante.",
            'answered_at.date' => "L'heure de fin du quiz est invalide.",
        ];
    }
}
