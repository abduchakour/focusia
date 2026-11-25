<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('subject', 150);
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2', 'OTHER']);
            $table->char('access_code', 6)->unique();
            $table->timestamps();
        });

        // Rule 1: a student may belong to several groups. Rule 18: removal keeps
        // the row (removed_at) so results and submissions stay attached.
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->unique(['group_id', 'student_id']);
            $table->index(['student_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('groups');
    }
};
