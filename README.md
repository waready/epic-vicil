# Sistema de Gestion de Acreditacion y Evidencias Academicas

MVP full-stack para la Escuela Profesional de Ingenieria Civil de la UNAP Puno. El sistema gestiona modelos de acreditacion, ciclos, criterios, requerimientos, evidencias, versiones de archivos, revisiones, dashboard y exportaciones ZIP para ICACIT, con estructura extensible para SINEACE u otros modelos.

## Requisitos

- PHP 8.2 o superior
- Composer 2
- MySQL 8 o PostgreSQL 14+
- Node.js 22.22+ para Quasar/Vite actual
- npm
- Quasar CLI local mediante `@quasar/app-vite`
- Extension PHP `zip` para exportaciones

En Windows, este workspace quedo probado con PHP portable `tools/php-8.3/php.exe` (PHP 8.3.31) y Node `22.22.0` via nvm.

## Estructura

```txt
backend/   Laravel API REST, Sanctum, Spatie Permission, migraciones, seeders, services
frontend/  Quasar Framework, Vue 3 Options API, Axios boot, rutas protegidas
docs/      Documentacion de arquitectura, contrato API y roadmap
```

## Instalacion Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Si tu `php` global apunta a XAMPP 7.x, usa el PHP portable instalado en este workspace:

```powershell
..\tools\php-8.3\php.exe artisan migrate --seed
..\tools\php-8.3\php.exe artisan serve
```

Variables principales en `backend/.env`:

```env
FILESYSTEM_DISK=public
MAX_EVIDENCE_FILE_MB=500
ACCREDITATION_EXPORT_DISK=local
EVIDENCE_DIRECT_UPLOAD_ENABLED=true
EVIDENCE_DIRECT_UPLOAD_DISK=s3
EVIDENCE_DIRECT_UPLOAD_MAX_MB=2048
EVIDENCE_DIRECT_UPLOAD_THRESHOLD_MB=100
EVIDENCE_DIRECT_UPLOAD_EXPIRATION_MINUTES=15
EVIDENCE_VIDEO_TRANSCODING_ENABLED=false
SANCTUM_EXPIRATION_MINUTES=480
CORS_ALLOWED_ORIGINS=http://localhost:9000,http://127.0.0.1:9000
```

La autenticacion del MVP usa tokens Bearer de Sanctum para la API. El frontend guarda el token solo en `sessionStorage`, por lo que la sesion no queda permanente al cerrar el navegador. Los tokens expiran segun `SANCTUM_EXPIRATION_MINUTES`.

Para S3, MinIO, DigitalOcean Spaces o Cloudflare R2 usa el disco `s3` de Laravel. El flujo normal sube por el backend; si el docente sube videos o archivos de 100 MB o mas, el frontend intenta usar subida directa con URL firmada para que el archivo vaya del navegador al storage externo.

Ejemplo para DigitalOcean Spaces:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=tu-space
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
AWS_URL=https://tu-space.nyc3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

En Spaces configura CORS para permitir `PUT`, `POST`, `GET` y `HEAD` desde el dominio del frontend. La compresion/transcodificacion de videos queda desactivada por defecto (`EVIDENCE_VIDEO_TRANSCODING_ENABLED=false`) para no bloquear el request; cuando se active debe ejecutarse en cola con `ffmpeg`.

## Instalacion Frontend

```bash
cd frontend
npm install
quasar dev
```

Con nvm en Windows:

```powershell
nvm install 22.22.0
nvm use 22.22.0
npm install
npm run build
quasar dev
```

La API se toma de `API_URL`; por defecto apunta a:

```env
API_URL=http://localhost:8000/api
```

## Verificacion Local

Con backend levantado en `http://127.0.0.1:8000`, ejecuta el smoke test:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\smoke-api.ps1
```

La prueba realiza login, consulta catalogos, sube un PDF, agrega version, observa, valida, aprueba y genera un ZIP de exportacion.

URLs locales probadas:

```txt
Backend API: http://127.0.0.1:8000/api
Frontend:    http://localhost:9000
Login:       http://localhost:9000/login
```

## Usuarios Seed

Todos inician con la contrasena temporal `password`. En el primer ingreso el sistema obliga a cambiarla antes de permitir navegar por el resto de modulos.

| Rol | Correo |
| --- | --- |
| super_admin | admin@acreditacion.local |
| director_programa | director@acreditacion.local |
| coordinador_acreditacion | coordinador@acreditacion.local |
| docente | docente@acreditacion.local |
| consulta | consulta@acreditacion.local |

## Modelo Inicial

El seeder crea:

- Institucion: Universidad Nacional del Altiplano Puno
- Facultad: Facultad de Ingenieria Civil y Arquitectura
- Programa: Escuela Profesional de Ingenieria Civil
- Modelo ICACIT con criterios 1 al 11
- Modelo SINEACE configurable
- Ciclos semestrales: `ICACIT 2026-I Ingenieria Civil` activo y `ICACIT 2026-II Ingenieria Civil` en planificacion
- Planes de estudio `IC-2026` e `IC-2018`
- Carga docente base, 12 cursos de assessment 2026-I y checklist inicial de evidencias
- Cuentas docentes para los responsables del plan de assessment

## Endpoints Principales

Auth:

```txt
POST /api/auth/login
GET  /api/auth/me
POST /api/auth/logout
```

Catalogos:

```txt
GET /api/faculties
GET /api/programs
GET /api/study-plans
GET /api/semesters
GET /api/courses
GET /api/teachers
```

Acreditacion:

```txt
GET /api/accreditation-models
GET /api/accreditation-cycles
GET /api/accreditation-criteria
GET /api/evidence-requirements
GET /api/evidence-tasks/catalog
GET /api/my/evidence-tasks
POST /api/evidence-tasks/{id}/submissions
```

Evidencias:

```txt
GET    /api/evidences
POST   /api/evidences
GET    /api/evidences/{id}
POST   /api/evidences/{id}/versions
POST   /api/evidences/{id}/observe
POST   /api/evidences/{id}/validate
POST   /api/evidences/{id}/approve
DELETE /api/evidences/{id}
```

Dashboard y exportacion:

```txt
GET  /api/dashboard/summary
GET  /api/dashboard/progress-by-criterion
GET  /api/dashboard/progress-by-program
GET  /api/dashboard/pending-by-teacher
POST /api/exports/evidences-zip
```

Administracion de catalogos:

```txt
GET    /api/admin/institutions
POST   /api/admin/institutions
PUT    /api/admin/institutions/{id}
DELETE /api/admin/institutions/{id}
GET    /api/admin/users
POST   /api/admin/users
PUT    /api/admin/users/{id}
DELETE /api/admin/users/{id}
GET    /api/admin/roles
GET    /api/admin/faculties
POST   /api/admin/faculties
PUT    /api/admin/faculties/{id}
DELETE /api/admin/faculties/{id}
GET    /api/admin/programs
POST   /api/admin/programs
PUT    /api/admin/programs/{id}
DELETE /api/admin/programs/{id}
GET    /api/admin/study-plans
POST   /api/admin/study-plans
PUT    /api/admin/study-plans/{id}
DELETE /api/admin/study-plans/{id}
GET    /api/admin/courses
POST   /api/admin/courses
PUT    /api/admin/courses/{id}
DELETE /api/admin/courses/{id}
GET    /api/admin/course-offerings
POST   /api/admin/course-offerings
PUT    /api/admin/course-offerings/{id}
DELETE /api/admin/course-offerings/{id}
GET    /api/admin/teachers
POST   /api/admin/teachers
PUT    /api/admin/teachers/{id}
DELETE /api/admin/teachers/{id}
POST   /api/admin/teachers/{id}/user
POST   /api/admin/teachers/{id}/cv
```

En el frontend se administran desde `Usuarios`, `Instituciones`, `Facultades`, `Programas`, `Planes`, `Cursos`, `Carga docente` y `Docentes` en el menu lateral. Estas rutas requieren el permiso `manage.catalogs`.

Desde `Usuarios` se crean cuentas con contrasena inicial y uno o varios roles: `super_admin`, `admin_facultad`, `director_programa`, `coordinador_acreditacion`, `comite_calidad`, `docente`, `responsable_laboratorio`, `auditor_interno` o `consulta`.

Cuando se crea o resetea una contrasena, la cuenta queda marcada con cambio obligatorio. Cada usuario puede abrir el menu de cuenta para entrar a `Mi perfil`, actualizar datos basicos y cambiar su contrasena.

Desde `Docentes` se puede crear la cuenta de acceso del docente y subir el CV como evidencia del criterio `C6 - Cuerpo de Profesores`, requerimiento `C6-REG-01 CV docente y documentos de soporte`. El formulario general `Subir evidencia` tambien muestra criterio, subcriterio y requerimiento para registrar evidencias por estructura de acreditacion.

Los docentes no acceden a administracion, repositorio global, exportaciones ni carga docente. Al iniciar sesion son enviados a `Mis evidencias`; la API filtra sus tareas/evidencias por usuario, ficha docente y cursos asignados en la carga academica/assessment.

## Portafolio Docente y Assessment 2026-I

El portafolio docente general se gestiona con tareas C5 por carga docente:

- `C5-PORT-01` Silabo del curso
- `C5-PORT-02` Temario, sesiones y cronograma
- `C5-PORT-03` Examenes aplicados, formatos, solucionarios y criterios
- `C5-PORT-04` Guias de trabajo, practicas, laboratorios y trabajos encargados
- `C5-PORT-05` Trabajos de estudiantes clasificados: bueno, regular y malo
- `C5-PORT-06` Rubricas, actas y resultados de aprendizaje
- `C5-PORT-07` Material de ensenanza

Los cursos de medicion de resultados del estudiante se marcan en `Carga docente` con:

- `Curso de medicion / assessment`
- `Resultado del estudiante (RE-Ixx)`
- `Nombre del resultado`
- `Requiere video de 10 minutos`

El seeder deja cargado el plan de assessment 2026-I con 12 resultados `RE-I01` a `RE-I12`. Para esos cursos se generan tareas C3:

- Trabajos de todos los estudiantes
- Guias de assessment
- Rubricas de assessment
- Video de 10 minutos para cada uno de los 12 cursos de assessment

Ejemplos de cuentas docentes seed para probar, todas con clave temporal `password`:

| Docente | Correo |
| --- | --- |
| Marwin Douglas Mendoza Larico | mdmendoza@docentes.com |
| Douglas Arturo Quintanilla Anaypoma | daquintanilla@docentes.com |
| Gleny Zoila De La Riva Tapia | gzdelariva@docentes.com |
| Gino Nels Najar Vizcarra | gnnajar@docentes.com |

El formato de correo docente es `iniciales de nombres + primer apellido + @docentes.com`. Ejemplo: `Antony Juan Japura` genera `ajjapura@docentes.com`.

## Flujo Docente C5/C6/C3

1. El admin crea o edita el docente en `Docentes`. Puede activar `Crear usuario docente` al crear el registro, o usar el boton de cuenta en un docente existente. La clave inicial por defecto es `password` y el docente debe cambiarla en su primer ingreso.
2. El admin define la carga academica en `Carga docente`: programa, semestre, curso, seccion y docente principal.
3. Si el curso mide un resultado del estudiante, el admin activa `Curso de medicion / assessment`, coloca el RE-Ixx y marca video si corresponde.
4. Al guardar una carga docente, el backend crea automaticamente tareas C5 por curso. Si es assessment, tambien crea las tareas C3 de medicion.
5. El docente ingresa con su correo y clave, abre `Mis evidencias` y sube los archivos de cada tarea. La pantalla ya trae criterio, subcriterio, requerimiento, curso y semestre; el docente no los elige manualmente.
6. El admin/coordinador revisa en `Evidencias`, observa, valida o aprueba. El dashboard y exportacion usan esos estados.
7. Los documentos del docente como CV se gestionan en C6 y quedan asociados al docente, ciclo, programa, criterio y requerimiento.

## Flujo de Evidencias

1. El seeder genera `evidence_tasks` como checklist por ciclo, programa, criterio y requerimiento.
2. El usuario sube una evidencia desde el frontend. Se crea `file_assets`, `evidence_submissions` y la primera fila en `evidence_versions`.
3. Cada nueva version crea otro `file_assets` y `evidence_versions`; la version anterior no se borra.
4. Las acciones `observe`, `validate` y `approve` registran `evidence_reviews`, actualizan estado y dejan auditoria.
5. El dashboard calcula avance por estado, criterio, programa y responsable.
6. La exportacion ZIP incluye evidencias validadas/aprobadas/listas segun parametros.

## Notas de Despliegue

- Configura `FILESYSTEM_DISK=s3` para storage compatible con S3 en produccion.
- Aumenta `upload_max_filesize`, `post_max_size` y limites de Nginx/Apache segun `MAX_EVIDENCE_FILE_MB` para el flujo por backend. Los videos grandes deben usar subida directa a S3/Spaces.
- Configura CORS del bucket/Space para subida directa desde el dominio real del frontend.
- Usa colas para exportaciones grandes en una fase posterior; el MVP genera ZIP de forma sincrona.
- Sirve el frontend compilado con `quasar build` y configura CORS/Sanctum con el dominio real.
- Ejecuta backups de base de datos y storage; los archivos no se guardan en la base de datos.
