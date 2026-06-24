# Contrato API MVP

Base URL:

```txt
/api
```

Todas las rutas, excepto login y health, usan `auth:sanctum` con token Bearer.

## Auth

```txt
POST /auth/login
GET  /auth/me
POST /auth/logout
```

## Catalogos

```txt
GET /faculties
GET /programs
GET /study-plans
GET /semesters
GET /courses
GET /teachers
```

## Acreditacion

```txt
GET /accreditation-models
GET /accreditation-cycles
GET /accreditation-criteria
GET /evidence-requirements
GET /evidence-tasks/catalog
```

Los endpoints `/catalog/*` se mantienen como aliases de compatibilidad.

## Evidencias

```txt
GET    /evidences
POST   /evidences
GET    /evidences/{id}
POST   /evidences/{id}/versions
POST   /evidences/{id}/observe
POST   /evidences/{id}/validate
POST   /evidences/{id}/approve
DELETE /evidences/{id}
```

Carga de evidencia:

```http
POST /api/evidences
Authorization: Bearer TOKEN
Content-Type: multipart/form-data

program_id: 1
accreditation_cycle_id: 1
criterion_id: 5
evidence_requirement_id: 12
evidence_task_id: 30
title: Portafolio del curso
description: Entrega inicial
file: portafolio.pdf
```

## Dashboard

```txt
GET /dashboard/summary
GET /dashboard/progress-by-criterion
GET /dashboard/progress-by-program
GET /dashboard/pending-by-teacher
```

Filtros comunes:

```txt
program_id
cycle_id
accreditation_cycle_id
```

## Exportacion

```txt
POST /exports/evidences-zip
```

Body:

```json
{
  "accreditation_model_id": 1,
  "accreditation_cycle_id": 1,
  "program_id": 1,
  "statuses": ["validated", "approved"]
}
```
