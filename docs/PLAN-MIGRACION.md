# Plan de migración: Top Kapital — Yii2 → Laravel 12 + Inertia/Vue (SPA)

> **Documento vivo.** Es el punto de entrada para cualquier sesión futura de trabajo en
> este repositorio. Si retomas el proyecto sin contexto previo, lee este archivo primero
> y luego la **Bitácora** al final, que dice exactamente en qué punto se quedó todo.
>
> - **Repositorio destino:** `https://github.com/ferTopkapital/toplaravel.git`
> - **Repositorio origen (Yii2, sigue en producción):** `C:\dev\topkapital` → `Top-Kapital/appTopKapital`
> - **Última actualización:** 2026-09-10

---

## 1. Objetivo

Reescribir la aplicación de Top Kapital (plataforma de financiamiento colectivo de deuda,
ITF regulada por CNBV) migrándola de **Yii2 Advanced + Bootstrap 5 + Velzon** a
**Laravel 12 + Inertia 2 + Vue 3 + Tailwind 4**, con estos objetivos explícitos:

1. **Velocidad de carga.** Es el motivo principal del rewrite. Hoy cada navegación es un
   round-trip completo con recarga de página y un bundle de Velzon/Bootstrap/jQuery muy
   pesado. Con Inertia la navegación es SPA: sólo viaja JSON y se re-renderiza el componente.
2. **Misma base de datos.** Sin migraciones destructivas, sin renombrar tablas ni columnas.
   La app Yii2 y la app Laravel deben poder convivir apuntando al mismo esquema durante
   toda la transición.
3. **Modo claro / oscuro / sistema.** Requisito nuevo, no existe hoy.
4. **Respetar la identidad visual de Top Kapital, pero mejorarla** — en particular selects,
   botones, estados de carga y transiciones.

### Fuera de alcance (por ahora)

- Cambios de esquema de base de datos.
- Migrar la app móvil (Capacitor, `mobile/` en el repo Yii2).
- Migrar el sitio de marketing WordPress (`topkapital.com`). Sólo se migra la app
  (`app.topkapital.com` + `app.topkapital.com:8080/admin`).

---

## 2. Estado actual — inventario de la app Yii2

La app es un **Yii2 Advanced** con tres puntas: `frontend/` (clientes), `backend/` (admin
interno), `console/` (tareas programadas), más `common/` (modelos y componentes compartidos).

### 2.1 Frontend (inversionistas y solicitantes)

| Controller | Acciones | Vistas | Qué hace |
|---|---|---|---|
| `SiteController` (1027 L) | 23 | 18 | Login, signup (inversionista y solicitante), verificación de correo por OTP, reset de contraseña, imagen de seguridad, T&C, eliminar cuenta, estado de cuenta |
| `ProyectoController` (1304 L) | 15 | 20 | Listado y detalle de proyectos, flujo completo de inversión (invertir → documentos → confirmar con OTP → contrato → constancia → depósito), wizard de alta de proyecto por el solicitante (5 pasos), cancelación |
| `UsuarioController` (864 L) | 12 | 11 | Wizard de onboarding del inversionista (5 pasos), documentos, identidad (Incode), envío a PLD, detalle de intereses |
| `SolicitanteController` (390 L) | 2 | 6 | Wizard de onboarding del solicitante (5 pasos) |
| `CuentaBancariaController` (232 L) | 5 | 7 | Alta y confirmación de cuentas bancarias |
| `BeneficiarioController` (167 L) | 4 | 7 | Beneficiarios designados por el inversionista |
| `PanelController` (127 L) | 7 | 6 | Dashboard: resumen, solicitudes, inversiones, calendario, noticias, documentos |
| `UsuarioDatosController` (95 L) | 2 | — | Información general del perfil |
| `IncodeController` (56 L) | 1 | — | Callback de verificación biométrica Incode |

### 2.2 Backend (usuarios internos)

| Controller | Acciones | Vistas | Qué hace |
|---|---|---|---|
| `ProyectoController` (2493 L) ⚠️ | 33 | 29 | El monstruo. Wizard de autorización de proyecto (6 pasos), campañas, calendario de pagos, generación de CFDI y constancias, retornos, confirmación y registro de pagos, configuración de crédito, interés moratorio |
| `UsuarioController` (571 L) | 19 | 11 | Alta/edición de inversionistas, autorización PLD, creación de cuenta STP, bloqueo/desbloqueo, pendientes de documentación, validar/rechazar documentos |
| `SolicitanteController` (436 L) | 11 | 14 | Pre-autorización, visita domiciliaria, código de aprobación, wizard |
| `SolicitudInversionController` (335 L) | 10 | 10 | Solicitudes de inversión, retiros, reportes por inversionista y por proyecto |
| `SiteController` (415 L) | 14 | 7 | Login admin (con OTP), roles, sesión, T&C globales |
| `StpController` (294 L) | 3 | — | Webhooks de STP: recepción, validación y procesamiento de pagos |
| `ReportesController` (216 L) | 4 | 3 | Reportes STP enviadas/recibidas, OFICO |
| `NoticiaController` (196 L) | 5 | 7 | Noticias de proyectos |
| `RechazoUsuariosController` (185 L) | 5 | 5 | Catálogo de motivos de rechazo |
| `CuentaBancariaController` (174 L) | 6 | 6 | Cuentas bancarias (admin) |
| `AccesoController` (172 L) | 9 | 7 | Usuarios internos y sus accesos |
| `BeneficiarioController` (170 L) | 5 | 7 | Beneficiarios (admin) |
| `BancosController` / `ContratoController` / `ActividadEconomicaController` / `InpcController` / `PromotorController` | 5 c/u | 6 c/u | Catálogos CRUD |
| `BanxicoController` / `InegiController` | 1 c/u | — | Sincronización de INPC y UDIs |

### 2.3 Console (tareas programadas)

`InactividadController`, `InteresMoratorioController`, `OficoSeccion1Controller`,
`TerminosCondicionesController`, `TempController`.

### 2.4 Componentes de negocio compartidos (`common/components/`)

Estos concentran la lógica financiera y las integraciones. **Son lo más delicado de migrar
y lo que menos debe "reinterpretarse":**

| Componente | Responsabilidad |
|---|---|
| `AmortizationService` | Cálculo de tablas de amortización |
| `InteresMoratorioService` | Interés moratorio (ver `docs/manual-interes-moratorio.md` del repo Yii2) |
| `OficoSeccion1..5Service` | Reportes regulatorios OFICO a CNBV |
| `FacturacionModerna` | Timbrado de CFDI |
| `PdfGenerator` + mPDF | Contratos, constancias, comprobantes, estados de cuenta |
| `signSTP`, `OrdenPagoWS`, `Stp` | Firma y envío de órdenes SPEI vía STP |
| `PropPLD` | Integración con el sistema ProPLD (listas negras/grises) |
| `Banxico`, `Inegi` | INPC, UDIs |
| `DynamicMailer` | Correo vía Gmail API / SMTP |
| `AppDbSession`, `LoginThrottle`, `TokenAuth` | Sesión en BD, bloqueo por intentos, tokens |
| `MoneyHelper`, `FechaFormatter`, `DateHelper` | Formato de moneda y fechas (es-MX, America/Mexico_City) |

### 2.5 Base de datos

- **Motor:** MariaDB. **Esquema:** `topkapital`. **44 tablas**, 35 claves foráneas.
- **Tablas anchas:** `proyecto` (149 columnas), `usuario` (114), `promotor` (99),
  `retornos` (55), `solicitud_inversion` (49), `beneficiario` (44).
- **Convención de nombres:** tablas en `snake_case`, columnas y PKs en `camelCase`
  (`usuarioId`, `proyectoId`, `cuenta_bancariaId`…). **No** hay `created_at`/`updated_at`;
  se usa `createdAt`/`createdBy`/`modifiedAt`/`modifiedBy`/`deletedAt`/`deletedBy`.
- **Roles (RBAC de Yii, tabla `auth_item`):** `superAdmin`, `oficial`, `operador`,
  `finanzas`, `stp`, `inversionista`. Además la tabla `usuario` tiene una columna `rol`
  (tinyint) que se usa en paralelo — hay que respetar ambas.

---

## 3. Stack destino

| Capa | Elección | Por qué |
|---|---|---|
| Framework | **Laravel 12** (PHP 8.2) | Coincide con el `mod_php` que ya corre Apache local |
| SPA | **Inertia 2 + Vue 3 + TypeScript** | Sin API REST separada: los controllers devuelven props. Da navegación SPA real, *partial reloads*, *deferred props* y *prefetch* — exactamente lo que necesitan los wizards y las tablas grandes |
| Estilos | **Tailwind CSS 4** | Pedido explícito. v4 compila con el plugin de Vite, sin `tailwind.config.js` |
| Build | **Vite 7** | Incluido en Laravel 12 |
| Componentes | **Reka UI** (headless) + componentes propios | Para selects, comboboxes y modales accesibles sin heredar el peso de Bootstrap |
| Tablas | **TanStack Table v8** | Reemplaza los GridView de Kartik |
| Gráficas | **ApexCharts** (`vue3-apexcharts`) | Ya se usa ApexCharts en Yii2; se conserva para no rehacer las visualizaciones |
| Iconos | **Remix Icon** (`@remixicon/vue`) | Las vistas actuales ya usan `ri-*`; el mapeo es 1:1 |
| PDF | **mPDF** (se conserva) | Los templates de contratos y constancias ya están hechos y validados por Legal |
| Colas | driver `database` | No hay Redis en el entorno local |

### 3.1 Por qué Inertia y no una API REST

Se evaluó una API con Sanctum + SPA independiente. Se descartó **por ahora** porque
duplica el trabajo (auth por tokens, CORS, dos despliegues) y porque los controllers
actuales ya devuelven vistas con datos: el mapeo a props de Inertia es casi mecánico.
Si más adelante la app móvil (Capacitor) necesita consumir la app, se agrega un grupo
`routes/api.php` con Sanctum **sin tocar** las rutas de Inertia.

---

## 4. Arquitectura de la app Laravel

```
app/
  Http/
    Controllers/
      Panel/          # dashboard del inversionista
      Proyecto/       # listado, detalle, flujo de inversión
      Onboarding/     # wizards de inversionista y solicitante
      Admin/          # todo el backend interno
      Webhooks/       # STP
    Middleware/
      EnsureSingleSession.php     # sesión única (manual 4.4.4)
      SessionTimeout.php          # cierre por inactividad
      RequireOtp.php              # segundo factor
    Requests/         # FormRequests = las rules() de Yii
  Models/             # 1 modelo Eloquent por tabla, mapeado al esquema existente
  Services/           # puerto directo de common/components
  Support/            # helpers (MoneyHelper, FechaFormatter…)
  Policies/           # reemplazo del RBAC de Yii

resources/js/
  app.ts
  Layouts/            # AppLayout (cliente), AdminLayout, AuthLayout
  Pages/              # 1:1 con las rutas de Inertia
  Components/
    ui/               # design system: Button, Select, Input, Card, Badge, Modal…
    data/             # DataTable, Pagination, Filters
    wizard/           # Stepper y pasos reutilizables
  composables/        # useTheme, useOtp, useSessionTimer
```

### 4.1 Convenciones no negociables

1. **Ningún modelo Eloquent crea o altera tablas.** Todos declaran explícitamente
   `$table`, `$primaryKey`, `$timestamps = false` y su `$fillable`.
2. **Toda regla de negocio financiera se porta como servicio**, con pruebas que comparan
   el resultado contra la implementación Yii2 antes de darla por buena (§7.1).
3. **Las rutas mantienen los mismos paths que Yii2** siempre que sea posible
   (`/proyecto/detalle`, `/usuario/wizard`…), para no romper enlaces en correos ya enviados.

---

## 5. Estrategia de base de datos

**Regla de oro: la BD no se toca.** Laravel se conecta al mismo esquema `topkapital`.

```php
// app/Models/Usuario.php — patrón para todos los modelos
class Usuario extends Authenticatable
{
    protected $table = 'usuario';
    protected $primaryKey = 'usuarioId';
    public $timestamps = false;          // usa createdAt / modifiedAt
    const CREATED_AT = 'createdAt';      // sólo si se activan timestamps
    const UPDATED_AT = 'modifiedAt';
}
```

### Puntos de atención detectados

| Hallazgo | Impacto | Cómo se maneja |
|---|---|---|
| `proyecto` tiene PK compuesta `(proyectoId, datosGeneralesCompleta)` | Eloquent no soporta PK compuestas | `proyectoId` es `AUTO_INCREMENT`, por lo tanto único por sí solo. Se declara `$primaryKey = 'proyectoId'` y funciona. **No** intentar "arreglar" la PK en la BD compartida |
| PKs en `camelCase` distintas por tabla | Todas las relaciones necesitan FK explícita | Se declara `$primaryKey` y se pasan las llaves en cada `hasMany`/`belongsTo` |
| Sin `created_at`/`updated_at` | Los `$timestamps` por defecto rompen los `INSERT` | `public $timestamps = false` en todos los modelos |
| Contraseñas hasheadas por Yii2 | El login de Laravel fallaría | Yii2 usa `password_hash` con bcrypt → **compatible con `Hash::check()` de Laravel**. Verificar en el primer sprint con un usuario real de la BD local |
| Sesiones en `yii_session` (19,915 filas) y `session` | Dos apps escribiendo sesiones | Laravel usa su propia tabla/driver. **No compartir sesión entre ambas apps**; son logins independientes durante la transición |
| Borrado lógico vía `deletedAt`/`deletedBy` | `SoftDeletes` espera `deleted_at` | `const DELETED_AT = 'deletedAt';` en los modelos que aplique |

### Migraciones de Laravel

Se generan **sólo** para las tablas propias del framework (`cache`, `jobs`, `job_batches`,
`failed_jobs`, `sessions` si se usa driver DB) y se les pone un prefijo `lv_` para que
queden claramente separadas de las 44 tablas del negocio. **`php artisan migrate:fresh`
está prohibido en este proyecto** — borraría la base compartida.

---

## 6. Diseño

### 6.1 Identidad actual (extraída del código Yii2)

| Token | Valor | Uso hoy |
|---|---|---|
| Navy | `#18233E` | Color dominante, sidebar, encabezados |
| Naranja | `#DF591D` | CTA principal ("No cerrar sesión", botones de acción) |
| Taupe | `#C5BCAF` | Fondos y bordes secundarios |
| Beige claro | `#E7E3DD` | Superficies |
| Ámbar | `#F7B84B` | Advertencias |
| Coral | `#F06548` | Errores |

Tipografía: **Antic Slab** (títulos) + **Nunito Sans** (cuerpo). Iconos: **Remix Icon**.

### 6.2 Lo que se conserva y lo que se mejora

**Se conserva:** la paleta, las dos tipografías, los iconos Remix, la estructura general
sidebar + topbar + contenido, y el lenguaje de tarjetas para proyectos.

**Se mejora:**

- **Selects.** Hoy son `kartik/select2` sobre jQuery: lentos, pobres en móvil y sin soporte
  de tema oscuro. Se reemplazan por un `<Select>` propio sobre Reka UI, con búsqueda,
  agrupación, carga asíncrona y navegación por teclado.
- **Botones.** Sistema de variantes (`primary`, `secondary`, `ghost`, `danger`) × tamaños,
  con estado `loading` integrado (spinner + `disabled` automático durante los envíos de
  Inertia). Hoy cada vista repite clases de Bootstrap a mano.
- **Pantallas de carga.** Tres niveles:
  1. Barra de progreso superior en cada navegación de Inertia.
  2. **Skeletons** por página (no spinners genéricos) para dashboard, listado de proyectos
     y tablas del admin.
  3. Estado `loading` en cada botón que dispare una acción.
- **Transiciones suaves.** Transición de página en el layout, `<Transition>` en modales y
  paneles, y animación entre pasos de los wizards. Todo respetando
  `prefers-reduced-motion`.

### 6.3 Modo claro / oscuro / sistema

- Tokens CSS en `:root` (claro) redefinidos bajo `.dark`.
- Tailwind 4 en modo `class`, con la clase en `<html>`.
- Composable `useTheme()` con tres estados: `light` | `dark` | `system`. La preferencia se
  guarda en `localStorage` y se aplica con un script inline en el `<head>` **antes** del
  primer render, para evitar el destello blanco (FOUC).
- La paleta oscura **no** es una inversión automática: el navy `#18233E` pasa a ser el
  fondo y el naranja se satura ligeramente para mantener contraste AA sobre él.

---

## 7. Plan por fases

Cada fase termina con algo funcionando y desplegable. El orden está pensado para atacar
primero lo que da la mejora de velocidad percibida y dejar al final lo más riesgoso
(cálculos financieros y STP).

### Fase 0 — Fundación

- [x] Detectar el stack local (Apache 2.4 + `mod_php` 8.2, MariaDB, sin Node)
- [x] Instalar Node.js 24 LTS
- [x] `composer create-project laravel/laravel toplaravel`
- [x] Instalar Inertia 2 + Vue 3 + TypeScript + Tailwind 4 + Vite
- [x] Configurar `.env` contra la BD `topkapital` existente (verificado: `db:show` lee las 44 tablas)
- [x] Deshabilitar las migraciones de andamiaje de Laravel; sesión/caché a `file`, colas a `sync`
- [x] Vhost `toplaravel.loc` agregado a `httpd-vhosts.conf`
- [ ] **Pendiente (requiere admin):** entrada en `hosts` + reinicio de Apache
- [x] Primer commit
- [ ] **Pendiente:** push a `ferTopkapital/toplaravel`

### Fase 1 — Design system y layouts

Construir el kit antes que las pantallas, para no rehacer 100 vistas después.

- Tokens de color, tipografía y espaciado; modo claro/oscuro/sistema funcionando.
- Componentes `ui/`: `Button`, `Input`, `Select`, `Combobox`, `Checkbox`, `Radio`,
  `Switch`, `Textarea`, `DatePicker`, `MoneyInput`, `Card`, `Badge`, `Alert`, `Modal`,
  `Tabs`, `Tooltip`, `Dropdown`.
- `DataTable` con orden, filtro y paginación server-side (reemplazo de GridView).
- `Stepper` para los wizards.
- Skeletons y transiciones de página.
- `AppLayout`, `AdminLayout`, `AuthLayout`.
- **Entregable:** una página `/ui` con el catálogo completo de componentes en ambos temas.

### Fase 2 — Autenticación y sesión (bloque regulatorio)

Todo lo de este bloque está normado; ver §8.

- Login con identificador (email) + contraseña, compatible con los hashes existentes.
- Política de contraseñas: 8–30 caracteres, mayúscula, minúscula, dígito, especial; y las
  prohibiciones (identificador, nombre de la institución, >3 caracteres idénticos
  consecutivos, >3 secuenciales).
- Bloqueo a los **10 intentos fallidos** con desbloqueo por código de 8 caracteres al correo.
- **OTP de 8 caracteres con vigencia de 2 minutos** como segundo factor.
- **Imagen de seguridad** elegida por el usuario, mostrada antes de pedir la contraseña.
- Cierre de sesión por inactividad + modal de aviso con cuenta regresiva.
- **Sesión única** por identificador de cliente.
- Al iniciar sesión, mostrar nombre del cliente y fecha/hora del último ingreso.
- Reset de contraseña por código al correo, con historial (`historial_contrasenas`).

### Fase 3 — Portal del inversionista (mayor impacto en velocidad)

- Dashboard/panel: resumen, mis inversiones, calendario de pagos, noticias, documentos.
- Listado de proyectos con tarjetas + detalle del proyecto.
- Perfil: información general, beneficiarios, archivos, cuentas bancarias.

### Fase 4 — Onboarding (wizards)

- Wizard del inversionista (5 pasos), incluyendo persona moral.
- Bifurcación **KYC Simplificado (Nivel 1) / KYC Completo (Nivel 2)** con el umbral de
  $5,000 MXN por mes calendario.
- Constancia de conocimiento de riesgos (checkbox) y contrato de comisión mercantil con
  **firma autógrafa digitalizada** (canvas, mouse o dedo).
- Wizard del solicitante (5 pasos).
- Integración Incode (biometría).

### Fase 5 — Flujo de inversión

- Invertir → documentos → pantalla de confirmación con las características de la operación
  → checkbox → **OTP** → instrucciones de depósito a la cuenta STP individual.
- Generación de contrato, constancia y comprobante de inversión.
- Cancelación de inversión.

### Fase 6 — Backend interno

Por orden de volumen de uso: Usuarios/PLD → Solicitantes → Proyectos (el wizard de 6 pasos
y campañas) → Solicitudes de inversión y retiros → Catálogos → Noticias → Reportes.

### Fase 7 — Motor financiero e integraciones

La parte de mayor riesgo. Se deja al final y **se valida contra la implementación Yii2**.

- `AmortizationService`, `InteresMoratorioService`, calendario de pagos, retornos.
- CFDI (Facturación Moderna), constancias de retención, estados de cuenta.
- STP: firma, órdenes de pago, webhooks de recepción y validación.
- Reportes OFICO (secciones 1–5).
- Tareas programadas → `routes/console.php` con el scheduler de Laravel.

### 7.1 Criterio de aceptación del motor financiero

Ningún cálculo se da por migrado hasta que un test compare, sobre los datos reales de la
BD local, la salida del servicio Laravel contra la del componente Yii2 equivalente, para
**todos** los registros existentes de `calendario_pagos`, `retornos`, `daily_interest` e
`interes_moratorio`. Diferencia tolerada: **cero**.

---

## 8. Requisitos regulatorios (Manual de Uso de Medios Electrónicos)

Fuente: [Manual de Uso de Medios Electrónicos](https://topkapital.atlassian.net/wiki/spaces/Marketing/pages/13959468/Manual+de+Uso+de+Medios+Electro+nicos)
(Confluence, espacio Marketing). Estos puntos **no son opcionales**: están declarados ante
la CNBV. Cualquier cambio de comportamiento en el rewrite es un incumplimiento.

| § | Requisito | Dónde se implementa |
|---|---|---|
| 1.4 | Imagen de seguridad personalizada antes de autenticarse | Fase 2 |
| 1.4 | Mostrar nombre del cliente y fecha/hora del último ingreso | Fase 2 |
| 3.1.1 | Verificación de correo por OTP en el registro | Fase 2 |
| 3.1.1 | Bifurcación KYC Nivel 1 / Nivel 2, umbral $5,000 MXN por mes calendario | Fase 4 |
| 3.1.1 | Firma de constancia de riesgos (checkbox) y contrato (firma digitalizada) | Fase 4 |
| 3.1.1 | Identificación automática de cuenta bancaria en la primera transferencia | Fase 7 |
| 3.3 | Pantalla de confirmación de operación + checkbox + OTP | Fase 5 |
| 3.4 | Notificación por correo de cada operación | Transversal |
| 4.1 | Política de contraseñas (longitud 8–30 y composición) | Fase 2 |
| 4.3.1 | Bloqueo a los 10 intentos fallidos | Fase 2 |
| 4.3.2 | Desactivación por 1 año de inactividad, con aviso 30 días antes | Fase 7 (tarea programada) |
| 4.4 | Segundo factor (OTP 8 caracteres, 2 minutos) en: compromiso de inversión, alta/cambio de cuenta destino, cambio de contraseña, consulta de estado de cuenta | Fases 2 y 5 |
| 4.4.4 | Cierre de sesión por inactividad **y sesión única por identificador** | Fase 2 |

### Discrepancias detectadas entre el manual y el código

Hay que resolverlas **antes** de la Fase 2, porque definen el comportamiento a implementar:

1. **Tiempo de inactividad.** El manual (§4.4.4) declara **5 minutos**. El entorno real usa
   `TIMELOGOUT=30` (cliente) y `TIMELOGOUTADMIN=25` (admin). El rewrite debe implementar el
   valor correcto; hay que confirmar con Cumplimiento cuál es.
2. **Hash de contraseñas.** El manual (§4.1) dice **SHA-256**. El código usa `password_hash`
   de PHP (**bcrypt**), que es lo correcto desde el punto de vista de seguridad. Parece un
   error de redacción del manual, no del código: se conserva bcrypt y se sugiere corregir
   el manual.
3. **Cuentas bancarias.** El manual (§1.1.2, 3.1.1) dice que la cuenta se identifica
   automáticamente en la primera transferencia y que **no** se captura en el onboarding.
   El código tiene `CuentaBancariaController` con alta y confirmación manual. Hay que
   confirmar cuál es el comportamiento vigente.

---

## 9. Entorno local

| Pieza | Valor |
|---|---|
| Apache | `C:\dev\Apache24` (servicio `Apache2.4`), `mod_php` |
| PHP | `C:\dev\php82` (8.2.33) — también existe `C:\dev\php83` |
| MariaDB | `C:\dev\mariadb`, `127.0.0.1:3306` |
| Node | `C:\Program Files\nodejs` (24.x LTS) |
| Composer | `C:\dev\composer\composer.bat` |
| BD | `topkapital` / usuario `topkapital` (respaldo de producción del 2026-08-20) |
| App Yii2 | `https://dev.topkapital.com` (frontend) y `:8080/admin` (backend) |
| **App Laravel** | **`http://toplaravel.loc`** → `C:\dev\toplaravel\public` |
| Vhosts | `C:\dev\Apache24\conf\extra\httpd-vhosts.conf` |
| Secretos | `C:\dev\Apache24\conf\extra\topkapital-env.conf` (fuera del repo, a propósito) |

Ambas apps corren **en paralelo** contra la misma BD. Eso es intencional: permite comparar
pantalla contra pantalla durante toda la migración.

---

## 10. Riesgos

| Riesgo | Severidad | Mitigación |
|---|---|---|
| Divergencia en cálculos financieros | **Alta** | §7.1: paridad exacta contra Yii2 sobre datos reales antes de aceptar cada servicio |
| Incumplimiento regulatorio por omitir un control | **Alta** | §8 es una checklist de aceptación, no documentación |
| `backend/ProyectoController` (2493 líneas, 33 acciones) | Alta | Descomponer en servicios antes de escribir la UI; no portar línea por línea |
| Ambas apps escribiendo la misma BD | Media | Logins y sesiones independientes; no compartir estado más allá de las tablas de negocio |
| Tablas de 149 columnas | Media | Modelos con `$fillable` explícito y `select()` acotado; nunca `SELECT *` en listados |
| Integración STP (dinero real) | **Alta** | Último en migrarse, con `PRODUCTION_ENABLED_STP=0` hasta tener paridad demostrada |
| Templates de PDF validados por Legal | Media | Se conserva mPDF y los templates tal cual; no se rehacen |

---

## 11. Bitácora

> Sesiones futuras: **anexen aquí** lo que hicieron. Esta sección es la que dice en qué
> punto real está el proyecto, por encima de las casillas del §7.

### 2026-09-10 — Sesión 1

- Mapeo completo de la app Yii2: controllers, acciones, vistas, modelos, componentes.
- Inventario de la BD: 44 tablas, PKs, anchos, hallazgos (§5).
- Extracción de la identidad visual desde el código (§6.1).
- Lectura del Manual de Uso de Medios Electrónicos y extracción de requisitos (§8),
  incluidas 3 discrepancias contra el código.
- Decisiones tomadas con el usuario: **Inertia 2 + Vue 3**; instalar Node vía winget.
- Node.js 24 LTS instalado.
- Laravel 12.69.2 creado en `C:\dev\toplaravel`, con Inertia 2, Vue 3, TypeScript,
  Tailwind 4, Reka UI y Ziggy.
- Conexión a la BD compartida verificada (`artisan db:show` → 44 tablas, 6.83 MB).
  Se confirmó que **no** se creó ninguna tabla nueva en el esquema.
- Design system inicial: tokens semánticos, tema claro/oscuro/sistema sin FOUC,
  `Button`, `Select` (sobre Reka UI, con buscador), `Input`, `Card`, `Badge`,
  `Skeleton`, `ThemeToggle`, `AppLayout` y el catálogo en `/ui`.
- `npm run build` compila limpio: 70 KB gzip de JS, 6 KB de CSS.
- Vhost `toplaravel.loc` agregado.
- Primer commit hecho.

**Lo que quedó pendiente y por qué:**

1. **Entrada en `hosts` y reinicio de Apache.** Requieren permisos de
   administrador; hay que correrlos desde una terminal elevada.
2. **Push al repositorio.** El entorno bloqueó las operaciones con el remoto.
   El commit ya está hecho en local; falta agregar el remoto y empujar.

**Siguiente paso natural:** Fase 1 — completar el design system (`DataTable`,
`Stepper`, `Modal`, `DatePicker`, `MoneyInput`) y, en paralelo, resolver con
Cumplimiento las 3 discrepancias del §8 antes de entrar a la Fase 2.
