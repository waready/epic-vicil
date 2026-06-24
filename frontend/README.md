# Frontend Quasar

Cliente Quasar + Vue 3 con Options API.

Requiere Node.js 22.22+ para las versiones actuales de Quasar/Vite.

```bash
npm install
npm run build
quasar dev
```

En Windows, Rolldown/Vite requiere el binding nativo `@rolldown/binding-win32-x64-msvc`, incluido como devDependency para evitar fallos de optional dependencies de npm.

Configura la API con:

```env
API_URL=http://localhost:8000/api
```

Pantallas incluidas: login, dashboard, evidencias, carga de evidencia, detalle, criterios, programas, docentes, reportes y exportaciones.
