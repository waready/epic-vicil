# Backend Laravel API

API REST para gestion de acreditacion y evidencias.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Incluye Sanctum, Spatie Permission, migraciones propias, seeders ICACIT/SINEACE, servicios `EvidenceService` y `ExportService`, y endpoints bajo `/api`.
