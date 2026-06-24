# Arquitectura Tecnica

## Vision

La plataforma funciona como sistema institucional de acreditacion, no como gestor simple de archivos.

```txt
Quasar + Vue 3 Options API
        -> Axios Bearer Token
Laravel API REST
        -> Eloquent ORM
MySQL/PostgreSQL
        -> metadata, estados, roles, auditoria
Storage local/S3 compatible
        -> archivos fisicos y exportaciones ZIP
```

## Backend

```txt
app/
  Http/Controllers/Api
  Http/Requests
  Http/Resources
  Models
  Services
  Enums
```

Servicios principales:

- `EvidenceService`: creacion de evidencias, versiones, observacion, validacion, aprobacion y auditoria.
- `ExportService`: generacion de ZIP por modelo, ciclo, programa, criterio y estado.

## Dominios

Core academico:

- Facultades
- Programas
- Planes de estudio
- Semestres
- Cursos
- Docentes
- Asignaciones de curso

Core de acreditacion:

- Modelos
- Ciclos
- Criterios
- Subcriterios
- Requerimientos
- Tareas/checklist

Evidencias:

- Archivo fisico: `file_assets`
- Evidencia logica: `evidence_submissions`
- Versiones: `evidence_versions`
- Revision: `evidence_reviews`
- Historial y auditoria

## Estados

```txt
pending
assigned
uploaded
in_review
observed
corrected
validated
approved
ready_to_export
archived
```

## Escalabilidad

- Los binarios no se guardan en base de datos.
- Los archivos quedan particionados por ciclo, programa y criterio.
- `checksum` permite trazabilidad y control de duplicados.
- Las consultas frecuentes tienen indices por ciclo, programa, criterio, estado, responsable y docente.
- El storage local puede migrar a S3, MinIO, Spaces o R2 mediante `FILESYSTEM_DISK`.

## Frontend

Pantallas protegidas:

- Login
- Dashboard
- Evidencias
- Subir evidencia
- Detalle de evidencia
- Criterios
- Programas
- Docentes
- Reportes
- Exportaciones

Todo componente Vue usa Options API.
