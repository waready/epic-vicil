<?php

namespace App\Support;

use App\Models\CourseAssignment;
use App\Models\EvidenceAccessDelegation;
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

        $visibleUserIds = self::visibleUserIds($user);
        $teacherIds = self::teacherIdsForUsers($visibleUserIds);

        if ($visibleUserIds->isEmpty() && $teacherIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $courseOfferingIds = self::courseOfferingIdsForTeachers($teacherIds);

        return $query->where(function (Builder $inner) use ($visibleUserIds, $teacherIds, $courseOfferingIds) {
            $inner->whereIn('assigned_to', $visibleUserIds);

            if ($teacherIds->isNotEmpty()) {
                $inner->orWhere(function (Builder $teacherQuery) use ($teacherIds) {
                    $teacherQuery->where('context_type', 'teacher')
                        ->whereIn('context_id', $teacherIds);
                });
            }

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

        $visibleUserIds = self::visibleUserIds($user);
        $teacherIds = self::teacherIdsForUsers($visibleUserIds);

        if ($visibleUserIds->isEmpty() && $teacherIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($visibleUserIds, $teacherIds, $user) {
            $inner->whereIn('submitted_by', $visibleUserIds);

            if ($teacherIds->isNotEmpty()) {
                $inner->orWhereIn('teacher_id', $teacherIds);
            }

            $inner
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

    public static function taskIsWritable(EvidenceTask $task, ?User $user): bool
    {
        if (! self::isTeacherOnly($user)) {
            return true;
        }

        return (int) $task->assigned_to === (int) $user?->id;
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

    public static function evidenceIsWritable(EvidenceSubmission $evidence, ?User $user): bool
    {
        if (! self::isTeacherOnly($user)) {
            return true;
        }

        return (int) $evidence->submitted_by === (int) $user?->id
            || ($evidence->task && self::taskIsWritable($evidence->task, $user));
    }

    public static function teacherForUser(?User $user): ?Teacher
    {
        if (! $user) {
            return null;
        }

        return Teacher::query()->where('user_id', $user->id)->first();
    }

    private static function visibleUserIds(User $user): Collection
    {
        $delegatedUserIds = EvidenceAccessDelegation::query()
            ->where('delegate_user_id', $user->id)
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->pluck('source_user_id');

        return $delegatedUserIds
            ->push($user->id)
            ->unique()
            ->values();
    }

    private static function teacherIdsForUsers(Collection $userIds): Collection
    {
        return Teacher::query()
            ->whereIn('user_id', $userIds)
            ->pluck('id');
    }

    private static function courseOfferingIdsForTeachers(Collection $teacherIds): Collection
    {
        return CourseAssignment::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereHas('courseOffering')
            ->pluck('course_offering_id')
            ->unique()
            ->values();
    }
}
