<?php

namespace App\Support;

use App\Models\CourseAssignment;
use App\Models\EvidenceSubmission;
use App\Models\EvidenceTask;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccessScope
{
    private const PRIVILEGED_ROLES = [
        'super_admin',
        'admin_facultad',
        'director_programa',
        'coordinador_acreditacion',
        'comite_calidad',
        'responsable_laboratorio',
        'auditor_interno',
    ];

    public static function isTeacherOnly(?User $user): bool
    {
        return (bool) $user
            && $user->hasRole('docente')
            && ! $user->hasAnyRole(self::PRIVILEGED_ROLES);
    }

    public static function applyTaskVisibility(Builder $query, ?User $user): Builder
    {
        if (! self::isTeacherOnly($user)) {
            return $query;
        }

        $teacher = self::teacherForUser($user);

        if (! $teacher) {
            return $query->whereRaw('1 = 0');
        }

        $courseOfferingIds = self::courseOfferingIdsForTeacher($teacher);

        return $query->where(function (Builder $inner) use ($user, $teacher, $courseOfferingIds) {
            $inner->where('assigned_to', $user->id)
                ->orWhere(function (Builder $teacherQuery) use ($teacher) {
                    $teacherQuery->where('context_type', 'teacher')
                        ->where('context_id', $teacher->id);
                });

            if ($courseOfferingIds->isNotEmpty()) {
                $inner->orWhere(function (Builder $courseQuery) use ($courseOfferingIds) {
                    $courseQuery->whereIn('context_type', ['course_offering', 'assessment_course'])
                        ->whereIn('context_id', $courseOfferingIds);
                });
            }
        });
    }

    public static function applyEvidenceVisibility(Builder $query, ?User $user): Builder
    {
        if (! self::isTeacherOnly($user)) {
            return $query;
        }

        $teacher = self::teacherForUser($user);

        if (! $teacher) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($user, $teacher) {
            $inner->where('submitted_by', $user->id)
                ->orWhere('teacher_id', $teacher->id)
                ->orWhereHas('task', fn (Builder $taskQuery) => self::applyTaskVisibility($taskQuery, $user));
        });
    }

    public static function taskIsVisible(EvidenceTask $task, ?User $user): bool
    {
        if (! self::isTeacherOnly($user)) {
            return true;
        }

        return EvidenceTask::query()
            ->whereKey($task->id)
            ->tap(fn (Builder $query) => self::applyTaskVisibility($query, $user))
            ->exists();
    }

    public static function evidenceIsVisible(EvidenceSubmission $evidence, ?User $user): bool
    {
        if (! self::isTeacherOnly($user)) {
            return true;
        }

        return EvidenceSubmission::query()
            ->whereKey($evidence->id)
            ->tap(fn (Builder $query) => self::applyEvidenceVisibility($query, $user))
            ->exists();
    }

    public static function teacherForUser(?User $user): ?Teacher
    {
        if (! $user) {
            return null;
        }

        return Teacher::query()->where('user_id', $user->id)->first();
    }

    private static function courseOfferingIdsForTeacher(Teacher $teacher): Collection
    {
        return CourseAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->whereHas('courseOffering')
            ->pluck('course_offering_id')
            ->unique()
            ->values();
    }
}
