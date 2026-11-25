<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 150);
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['teacher_id', 'subject', 'name']);
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('subject', 150);
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2', 'OTHER']);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->dateTime('due_at');
            $table->dateTime('corrections_at');
            $table->dateTime('published_at')->nullable(); // null = draft
            $table->timestamps();
        });

        // Rule 5: every question is worth 1 point and belongs to a notion.
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notion_id')->constrained()->restrictOnDelete();
            $table->text('text');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['quiz_id', 'position']);
        });

        // Rule 4: exactly 4 choices and one correct answer.
        // The DB guarantees positions 0..3 unique per question (so at most 4) and
        // at most one correct choice (unique generated column). "Exactly 4 and
        // exactly 1" is validated by the app and written in a single transaction.
        Schema::create('choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('position');
            $table->unsignedBigInteger('correct_for_question')
                ->nullable()
                ->storedAs('CASE WHEN is_correct = 1 THEN question_id ELSE NULL END');
            $table->unique(['question_id', 'position']);
            $table->unique('correct_for_question');
        });

        // Rule 2: a quiz may target several groups, each at most once.
        Schema::create('group_quiz', function (Blueprint $table) {
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['quiz_id', 'group_id']);
        });

        // Rule 3: one attempt per student per quiz.
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['started', 'submitted'])->default('started');
            $table->dateTime('started_at');              // server time: quiz downloaded
            $table->dateTime('answered_at')->nullable(); // device time: answers given
            $table->dateTime('synced_at')->nullable();   // server time: answers received
            $table->decimal('score', 4, 2)->nullable();  // out of 20
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->unsignedSmallInteger('total_count')->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('choice_id')->nullable()->constrained()->nullOnDelete(); // null = no answer
            $table->boolean('is_correct');
            $table->primary(['attempt_id', 'question_id']);
            $table->index('question_id');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE choices ADD CONSTRAINT choices_position_range CHECK (position BETWEEN 0 AND 3)');
            DB::statement('ALTER TABLE quizzes ADD CONSTRAINT quizzes_duration_positive CHECK (duration_minutes IS NULL OR duration_minutes > 0)');
            DB::statement('ALTER TABLE attempts ADD CONSTRAINT attempts_score_range CHECK (score IS NULL OR score BETWEEN 0 AND 20)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('group_quiz');
        Schema::dropIfExists('choices');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('notions');
    }
};
