# Top Kapital — app en Laravel

Reescritura de la plataforma de Top Kapital (hoy en Yii2) sobre **Laravel 12 +
Inertia 2 + Vue 3 + Tailwind 4**, con el objetivo principal de mejorar la
velocidad de carga.

> **Lee primero [`docs/PLAN-MIGRACION.md`](docs/PLAN-MIGRACION.md).** Ahí está el
> inventario completo de los módulos, el plan por fases, los requisitos
> regulatorios que hay que preservar y la bitácora de avance.

---

## Estado

**Fase 0 — fundación.** La app arranca, monta Vue vía Inertia, compila Tailwind y
lee la base de datos compartida. Todavía no hay autenticación ni módulos de
negocio migrados.

## Requisitos

| | |
|---|---|
| PHP | 8.2+ (`C:\dev\php82`) |
| Composer | `C:\dev\composer\composer.bat` |
| Node | 22+ LTS |
| MariaDB | con el esquema `topkapital` cargado |

## Puesta en marcha

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Luego llena las credenciales de base de datos en `.env`. En el entorno local de
desarrollo son las mismas que usa la app Yii2 (ver
`C:\dev\Apache24\conf\extra\topkapital-env.conf`).

```bash
npm run dev        # Vite en modo desarrollo
php artisan serve  # o entra por el vhost http://toplaravel.loc
```

## Sobre la base de datos

Esta app apunta **a la misma base de datos que la app Yii2**, que en local es una
copia del respaldo de producción. Por eso:

- **Nunca corras `php artisan migrate:fresh`, `migrate:reset` ni `db:wipe`.**
- Las migraciones por defecto de Laravel están deshabilitadas a propósito
  (`database/migrations-framework-deshabilitadas/`, con su propio LÉEME).
- Sesión y caché usan el driver `file`, y las colas `sync`, justamente para no
  crear tablas de andamiaje dentro del esquema del negocio.
- Los modelos Eloquent se mapean al esquema existente: nombres de tabla y llaves
  primarias en `camelCase`, sin `created_at`/`updated_at`.

## Rutas de referencia

| Ruta | Qué es |
|---|---|
| `/` | Panel provisional; confirma la conexión a la base de datos |
| `/ui` | Catálogo del design system. Revisa aquí todo componente nuevo, en tema claro **y** oscuro, antes de usarlo en una vista |

## Convenciones

- Las vistas usan siempre los **tokens semánticos** (`bg-surface`, `text-fg-muted`,
  `border-line`, `text-accent`), nunca colores crudos. Así el modo oscuro se
  resuelve en un solo lugar: `resources/css/app.css`.
- El tema tiene tres estados: claro, oscuro y sistema. Se aplica con un script
  inline en `resources/views/app.blade.php` antes del primer pintado, para evitar
  el destello blanco.
- Las clases de Tailwind se escriben **completas y literales**. Tailwind escanea
  el fuente como texto: `bg-brand-${n}` no genera ninguna regla.
