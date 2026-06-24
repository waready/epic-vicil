# Prompt de Implementacion

Actua como desarrollador senior full-stack especializado en Laravel API, Quasar Framework, Vue 3 Options API, arquitectura modular, gestion documental y sistemas academicos de acreditacion.

El proyecto implementa una plataforma institucional para gestionar evidencias de acreditacion ICACIT y SINEACE. No debe ser solo un gestor de archivos: debe manejar ciclos, criterios, requerimientos, checklist, responsables, versiones, revisiones, dashboard y exportaciones.

## Reglas Tecnicas

1. No quemar ICACIT en la logica. Usar modelos configurables.
2. Soportar muchos ciclos, anos, programas, docentes y archivos.
3. Separar el archivo fisico (`file_assets`) de la evidencia logica (`evidence_submissions`).
4. Registrar versiones en `evidence_versions`; no borrar versiones previas.
5. Registrar acciones en `evidence_reviews` y auditoria en `audit_logs`.
6. Mantener indices por ciclo, programa, criterio, estado, responsable, docente y uploader.
7. Backend con Laravel Sanctum, Spatie Permission, Form Requests, API Resources y Services.
8. Frontend con Quasar + Vue 3 Options API. No usar Composition API.
9. Storage local en MVP, preparado para S3 compatible.
10. Exportacion ZIP por modelo, ciclo, programa, criterio y estado.

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

## MVP Esperado

1. Login, logout y me.
2. Seeders de roles, permisos, usuarios, UNAP Puno Civil, ICACIT y SINEACE.
3. Catalogos academicos y de acreditacion.
4. CRUD basico de evidencias.
5. Upload de archivo y versionado.
6. Observacion, validacion y aprobacion.
7. Dashboard real.
8. Exportacion ZIP.
9. Frontend administrativo con login, dashboard, lista, carga, detalle, criterios y exportaciones.
