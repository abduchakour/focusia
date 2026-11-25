<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course materials, homework (assignments + submissions) and library resources.
 * Files live on a private disk; only their path is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fileColumns = function (Blueprint $table): void {
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable(); // original name, used for download
            $table->string('mime_type', 150)->nullable();
            $table->unsignedInteger('file_size')->nullable(); // bytes
        };

        Schema::create('materials', function (Blueprint $table) use ($fileColumns) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['course', 'case_study', 'other']);
            $fileColumns($table);
            $table->string('url', 2048)->nullable();
            $table->timestamps();
        });

        // Rule 11: never twice the same (content, group) pair.
        Schema::create('group_material', function (Blueprint $table) {
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['material_id', 'group_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            // Rule 19: deleting the material only removes the link.
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->dateTime('due_at');
            $table->boolean('allow_late')->default(false);
            $table->timestamps();
        });

        Schema::create('assignment_group', function (Blueprint $table) {
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['assignment_id', 'group_id']);
        });

        // Rule 14: one submission per student per assignment (replaceable).
        Schema::create('submissions', function (Blueprint $table) use ($fileColumns) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $fileColumns($table);
            $table->text('body')->nullable();
            $table->dateTime('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->decimal('grade', 4, 2)->nullable();
            $table->text('comment')->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'student_id']);
        });

        Schema::create('resources', function (Blueprint $table) use ($fileColumns) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['document', 'video', 'link', 'exercise']);
            $table->string('subject', 150);
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2', 'OTHER']);
            $fileColumns($table);
            $table->string('url', 2048)->nullable();
            $table->timestamps();
            $table->index(['subject', 'level', 'type']);
        });

        Schema::create('group_resource', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['resource_id', 'group_id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // Rule 10: a file, a link, or both.
            DB::statement('ALTER TABLE materials ADD CONSTRAINT materials_file_or_url CHECK (file_path IS NOT NULL OR url IS NOT NULL)');
            DB::statement('ALTER TABLE resources ADD CONSTRAINT resources_file_or_url CHECK (file_path IS NOT NULL OR url IS NOT NULL)');
            // Rule 14: a file, a text, or both.
            DB::statement('ALTER TABLE submissions ADD CONSTRAINT submissions_file_or_body CHECK (file_path IS NOT NULL OR body IS NOT NULL)');
            // Rule 16: grade between 0 and 20.
            DB::statement('ALTER TABLE submissions ADD CONSTRAINT submissions_grade_range CHECK (grade IS NULL OR grade BETWEEN 0 AND 20)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_resource');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('assignment_group');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('group_material');
        Schema::dropIfExists('materials');
    }
};
