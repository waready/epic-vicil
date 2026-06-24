# Esquema de Base de Datos MVP

## Core

```txt
users
faculties
programs
study_plans
semesters: academic_terms
courses
teachers
course_assignments
```

El proyecto conserva clases de dominio como `AcademicProgram` y `CurriculumCourse`, pero las tablas normalizadas del MVP son `programs` y `courses`.

## Acreditacion

```txt
accreditation_models
accreditation_cycles
accreditation_criteria
accreditation_subcriteria
evidence_requirements
evidence_tasks
```

`evidence_tasks` representa el checklist generado por ciclo, programa, criterio, requerimiento y contexto.

## Evidencias

```txt
file_assets
evidence_submissions
evidence_versions
evidence_reviews
evidence_status_histories
```

`file_assets` separa el binario fisico de la evidencia logica:

```txt
disk
path
original_name
stored_name
extension
mime_type
size_bytes
checksum
uploaded_by
metadata
```

`evidence_submissions` guarda la entrega logica:

```txt
program_id
accreditation_cycle_id
accreditation_criterion_id
accreditation_subcriterion_id
evidence_requirement_id
evidence_task_id
course_id
teacher_id
current_file_asset_id
title
description
status
version_number
submitted_by
reviewed_by
validated_by
approved_by
submitted_at
reviewed_at
validated_at
approved_at
```

Cada nueva version se registra en `evidence_versions`; no se elimina la version anterior.

## Auditoria y Exportaciones

```txt
audit_logs
notifications
export_jobs
```

## Indices Clave

- `evidence_tasks(accreditation_cycle_id, status)`
- `evidence_tasks(program_id, accreditation_criterion_id, status)`
- `evidence_tasks(assigned_to, status)`
- `evidence_submissions(program_id, status)`
- `evidence_submissions(accreditation_cycle_id, status)`
- `evidence_submissions(accreditation_criterion_id, status)`
- `evidence_submissions(teacher_id, status)`
- `file_assets(disk, checksum)`
- `accreditation_cycles(program_id, year, status)`
