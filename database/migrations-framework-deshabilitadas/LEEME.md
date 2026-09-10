# Migraciones por defecto de Laravel — deshabilitadas a propósito

Estas son las migraciones que trae `laravel/laravel` de fábrica: crean las tablas
`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches` y `failed_jobs`.

**Se sacaron de `database/migrations/` para que `php artisan migrate` no las
ejecute por accidente.** Este proyecto apunta a la misma base de datos que la app
Yii2 en producción (esquema `topkapital`, 44 tablas del negocio); crear ahí las
tablas de andamiaje de Laravel ensuciaría un esquema compartido y `users` chocaría
conceptualmente con la tabla real de usuarios, que es `usuario`.

En su lugar:

- **Sesión y caché** usan el driver `file` (ver `.env`).
- **Colas** usan `sync`.
- **La autenticación** se hará contra la tabla `usuario` existente, con un modelo
  Eloquent mapeado (`$table = 'usuario'`, `$primaryKey = 'usuarioId'`).

Si más adelante hace falta alguna de estas tablas —por ejemplo colas en base de
datos—, se recupera el archivo correspondiente y se le pone prefijo `lv_` al
nombre de la tabla, para que quede separada de las del negocio.

Ver `docs/PLAN-MIGRACION.md`, sección 5.
