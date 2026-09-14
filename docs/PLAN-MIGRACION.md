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
| `CuentaBancariaController` (232 L) | 5 | 7 | Alta y confirmación manual de cuentas bancarias. **Heredado: no se porta** — la cuenta se detecta sola al recibir el pago por STP (§8, punto 3) |
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
| Contraseñas hasheadas por Yii2 | El login de Laravel fallaría | **Verificado 2026-09-10:** los 174 usuarios tienen hashes `$2y$` (bcrypt, 60 chars) y `Hash::check()` de Laravel los valida; también al revés. Yii2 hashea con **coste 13** y Laravel usa 12 por defecto, así que se fijó `BCRYPT_ROUNDS=13` para que un cambio de contraseña desde Laravel no debilite el hash |
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

- [x] Tokens de color, tipografía y espaciado; modo claro/oscuro/sistema funcionando.
- [x] `Button`, `Input`, `Select`, `Checkbox`, `Textarea`, `MoneyInput`, `Card`, `Badge`,
      `Alert`, `Modal`, `Skeleton`, `ThemeToggle`.
- [ ] Faltan: `Combobox`, `Radio`, `Switch`, `DatePicker`, `Tabs`, `Tooltip`, `Dropdown`.
- [x] `DataTable` con orden, filtro y paginación server-side (reemplazo de GridView),
      verificada contra `actividad_economica` (1,214 filas).
- [x] `Stepper` para los wizards.
- [x] Skeletons y transiciones de página.
- [x] `AppLayout`. [ ] Faltan `AdminLayout` y `AuthLayout`.
- [x] **Entregable:** catálogo en `/ui` y demo de tabla en `/ui/tabla`.

#### Lección aprendida: la clave de la transición de página

La transición de `AppLayout` se keyeaba con `page.url` **completo**. Efecto: cada orden,
filtro o cambio de página de una tabla cambiaba la clave y **remontaba la página entera**,
perdiendo el foco del buscador y anulando la ganancia de la recarga parcial — justo lo
contrario del objetivo del rewrite. Además, si la animación se interrumpía, el contenido
quedaba invisible en `opacity: 0`.

La clave correcta es **la ruta sin query string**. Así sólo se anima al cambiar de
pantalla de verdad. Cualquier layout nuevo debe hacer lo mismo.

Corolario para la `DataTable`: la recarga parcial debe pedir también `sort`, `direction`
y `search`, no sólo los datos. Si no, esas props se quedan en el valor viejo y el
indicador de orden de la columna nunca se mueve.

### Fase 2 — Autenticación y sesión (bloque regulatorio)

Todo lo de este bloque está normado; ver §8.

- [x] Login con identificador (email) + contraseña, compatible con los hashes existentes.
- [x] Política de contraseñas: 8–30 caracteres, mayúscula, minúscula, dígito, especial; y las
      prohibiciones (identificador, nombre de la institución, >3 caracteres idénticos
      consecutivos, >3 secuenciales). `App\Rules\PoliticaPassword`, con 24 pruebas.
- [x] Bloqueo a los **10 intentos fallidos** con desbloqueo por código al correo.
- [x] **OTP de 8 caracteres con vigencia de 2 minutos** (`App\Services\CodigoOtp`).
- [x] **Imagen de seguridad** mostrada antes de pedir la contraseña.
- [x] Al iniciar sesión se conserva `ultimoLoginAnterior` para poder mostrar el ingreso previo.
- [ ] Falta: **enviar el OTP por correo** (hoy se escribe al log; el mailer llega en la Fase 3).
- [x] Nombre del cliente y fecha del ingreso anterior, visibles en el encabezado.
- [x] Cierre de sesión por inactividad **aplicado en el servidor** + modal con cuenta regresiva.
- [x] **Sesión única** por identificador de cliente.
- [x] Bitácora de accesos en `sesiones_web` (ver abajo: alimenta OFICO).
- [x] Reset de contraseña por código, con historial (`historial_contrasenas`).
- [x] Registro de inversionista con verificación de correo.
- [x] Envío de códigos por correo (`CodigoVerificacion`).
- [ ] Falta: registro del **solicitante** (`/site/signupsl` en Yii2).
- [ ] Falta: elección de la imagen de seguridad desde el perfil.

#### El límite de reúso de contraseñas está roto en Yii2

`frontend/models/ResetPasswordForm.php` limita la comparación con
`Yii::$app->params['maxPasswordHistory']`, que **no está definido en ningún archivo de
params**. La expresión evalúa a `null`, `limit(null)` significa "sin límite", y termina
comparando contra **todo** el historial — emitiendo además un warning de PHP en cada reset.

El comportamiento resultante (nunca reutilizar ninguna contraseña previa) es el estricto,
así que en Laravel se conserva, pero explícito: `password.historial_comparado`, con `null`
como valor por defecto. Ponerle un número finito sería **aflojar** un control de seguridad
y debe ser una decisión consciente, no una corrección silenciosa del descuido.

#### Dónde sí se revela si un correo existe, y dónde no

- **Login:** sí. La imagen de seguridad del §1.4 obliga a distinguir al usuario antes de
  pedir la contraseña; es una consecuencia inevitable del control. Se compensa con
  `throttle`.
- **Recuperación y registro:** no. Ahí nada obliga, así que la respuesta es idéntica exista
  o no la cuenta, y no se regala un enumerador de clientes. Hay una prueba para cada caso.

#### `sesiones_web` no es control de sesión: es bitácora regulatoria

Pese al nombre, esa tabla no controla nada. `OficoSeccion1Service` la lee para dos campos
del reporte que va a la CNBV: la **fecha del último movimiento** del cliente y el **número
de accesos por banca por internet** en el periodo.

Granularidad: **una fila por usuario y por día**. Entrar cinco veces el mismo día cuenta
como un acceso, así que la deduplicación define el número reportado.

Si la app Laravel no la escribe, el reporte **subreporta** en cuanto los clientes empiecen
a entrar por aquí. Ya se escribe en el login, con dos pruebas que lo cubren.

#### La sesión única se ata a un token, no al id de sesión

El id de sesión cambia sin que el usuario haga nada raro: Laravel lo regenera al iniciar
sesión, y conviene regenerarlo también al cambiar la contraseña para cortar la fijación de
sesión. Un control que comparara ids expulsaría al usuario de **su propia** sesión en esos
momentos, y parecería un acceso desde otro dispositivo.

Por eso se registra un token aleatorio guardado **dentro** de la sesión, que sobrevive a la
regeneración del id.

El registro vive en la caché de la aplicación porque `usuario` no se puede alterar y no hay
columna donde quepa (`codigoSesion` es `varchar(8)`). **Con el driver `file` esto sólo vale
en una máquina:** si la app llega a correr en varias instancias, ese store tiene que ser
compartido (Redis o base) o el control deja de valer.

#### El sondeo del estado no renueva la sesión

`GET /sesion/estado` está excluido de renovar el reloj de actividad. Si lo renovara, una
pestaña abierta mantendría la sesión viva indefinidamente y el control del §4.4.4 sería
decorativo. `POST /sesion/renovar` sí la renueva, porque ahí el usuario dijo explícitamente
que sigue presente. Hay una prueba para cada caso.

#### El contador de intentos cuenta hacia ATRÁS

`usuario.intentosLogin` **no** acumula fallos: arranca en 10 (su valor por defecto en la
base) y se **descuenta**. Llegar a 0 es el bloqueo del §4.3.1. Cualquier código que lo
trate como un contador ascendente estará invirtiendo la lógica.

#### Regresión heredada que hay que preservar (DDS-897)

En la app Yii2, la exigencia del segundo factor se evaluaba **después** de validar la
contraseña. Efecto: en la petición en la que el contador llegaba a 0 —cuando `codigoLogin`
todavía no existía en la base— una contraseña correcta entraba **saltándose el OTP**.

En Laravel la decisión va antes de mirar la contraseña, y hay una prueba dedicada
(`test_con_intentos_agotados_la_contrasena_correcta_no_basta`) para que no se vuelva a
colar.

### Fase 3 — Portal del inversionista (mayor impacto en velocidad)

- [x] Panel: capital invertido, número de proyectos, pendientes de depósito,
      mis inversiones, noticias y proyectos en fondeo.
- [x] Listado de proyectos con tarjetas y filtro por etapa.
- [x] Detalle del proyecto con galería y condiciones de la campaña.
- [x] Calendario de pagos: programados, pagados y acumulados de interés, ISR y neto.
- [x] Documentos: facturas, constancias de retención y comprobantes de inversión.
- [x] Perfil: información general, beneficiarios, cuentas bancarias en sólo lectura
      y **elección de la imagen de seguridad** (§1.4).
- [ ] Falta: edición de la información general (llega con el onboarding, Fase 4).
- [ ] Falta: descarga de estado de cuenta por periodo, que exige OTP (§4.4.3).

#### El calendario del cliente sale de `retornos`, no de `calendario_pagos`

`calendario_pagos` es el calendario del **proyecto**: una fila por fecha de pago de la
campaña, sin dueño. Lo que cada inversionista cobra —su capital, sus intereses, su
retención de ISR y su neto— vive en `retornos`, ligado a su `inversionId`.

Además, `retornos` no tiene `usuarioId`: se filtra por el dueño de la inversión.

#### Los importes se leen, no se calculan

Varias columnas de `retornos` son `decimal(x,6)`, no `(x,2)`. Se conservan como están y se
redondean **sólo al mostrar**; los acumulados se suman en la base y se redondean al final,
porque sumar valores ya redondeados arrastraría diferencias de centavos contra lo que el
cliente ve en sus CFDI.

Recalcular por nuestra cuenta lo que ya salió en un CFDI timbrado sería introducir una
discrepancia fiscal. El motor que produce esos números es la Fase 7.

#### Los documentos se sirven con verificación de propiedad

Un CFDI o una constancia de retención llevan el RFC del cliente y sus importes. Una URL
firmada de S3 **caduca, pero no comprueba de quién es el documento**: quien tenga el enlace
lo abre, y la ruta del objeto queda a la vista.

Por eso las rutas de S3 nunca salen al navegador. El cliente pide `/documentos/{tipo}/{id}`
y `DocumentoController` verifica que sea suyo antes de firmar nada — el mismo criterio de
la app Yii2, que los sirve por `site/file` con la ruta cifrada.

Se responde **404 y no 403** ante un documento ajeno: un 403 confirmaría que ese id existe.
Hay pruebas dedicadas para ambas cosas.

#### Las condiciones se leen de la CAMPAÑA, no del proyecto

Un proyecto puede fondearse en varias vueltas. El monto objetivo, el plazo, la tasa y la
etapa viven en `campana`, no en `proyecto`. Leerlos del proyecto mostraría las condiciones
de una vuelta anterior — es decir, una tasa equivocada al cliente.

Por eso tanto el listado como el detalle se arman sobre `Campana` y usan `campanaActual`,
que es la de `campanaId` más alto.

#### Qué cuenta como capital vigente

El scope `SolicitudInversion::vivas()` define las cifras del panel: `confirmada = 1`,
`devuelto = 0` y no cancelada. Es el mismo criterio de la app Yii2, más la exclusión de
canceladas.

**Cambiar ese scope cambia lo que el cliente ve como su dinero.** No tocarlo sin comparar
contra lo que muestra la app Yii2 para el mismo usuario. Hay pruebas que fijan el
comportamiento, incluida una que verifica que el panel no mezcle el dinero de otro cliente.

#### Los archivos de S3 NO son públicos

`topkapital-env.conf` declara `S3_ACL=public-read`, pero **los objetos responden 403** a
una URL directa (comprobado con `curl`). Hay que firmarlas, igual que hace la app Yii2 con
`getPresignedUrl()`.

`Proyecto::urlDeS3()` usa `Storage::disk('s3')->temporaryUrl()` y **devuelve null** si
faltan credenciales o falla la firma: sin ellas la tarjeta dibuja su marcador y la pantalla
sigue siendo usable. Una imagen que no carga no debe tumbar el panel del cliente.

El layout de rutas lo fija la app Yii2: `proyectos/{proyectoId}/archivos/{nombre}`. La
columna `imagenes` guarda **sólo el nombre**, así que sin ese prefijo la firma es válida
pero el objeto no existe y S3 responde 404.

#### En local las imágenes no se ven, y es correcto que así sea

La base local es un respaldo de **producción**, pero el bucket configurado es
**`topkapital-dev`**. Son dos conjuntos de datos distintos: la base referencia los
proyectos 1 y 2, y el bucket de dev tiene los proyectos 10 en adelante. Por eso
`proyectos/1/archivos` está vacío.

**No es un error de código.** Verificado: al firmar un objeto que sí existe en el bucket
(`proyectos/10/archivos/...`) la descarga responde **HTTP 200**. Para ver imágenes en local
haría falta o el bucket de producción, o un respaldo de base que corresponda al de dev.

#### PHP no tenía bundle de certificados

Ninguna llamada HTTPS saliente desde PHP funcionaba en esta máquina: fallaban con
`unable to get local issuer certificate`. Eso no afectaba sólo a S3 — habría tumbado
**todas** las integraciones (Banxico, INEGI, Incode, STP, Facturación Moderna, Gmail API)
en cuanto se migraran en la Fase 7.

Se resolvió copiando el bundle que ya trae Git a `C:\dev\php82\cacert.pem` y apuntando
`curl.cainfo` y `openssl.cafile` en `php.ini`. **Apache necesita reiniciarse** para
tomarlo (`C:\dev\setup-toplaravel.ps1` como administrador ya lo hace). Beneficia también
a la app Yii2.

### Fase 4 — Onboarding (wizards)

- [x] Wizard del inversionista, incluyendo persona moral (RFC de 12 vs 13 caracteres,
      razón social en vez de apellidos).
- [x] Bifurcación **KYC Nivel 1 / Nivel 2** (`App\Services\NivelKyc`), con 10 pruebas
      dedicadas al umbral.
- [x] Constancia de conocimiento de riesgos: seis casillas por separado.
- [x] Contrato de comisión mercantil con **firma autógrafa digitalizada** (canvas con
      Pointer Events: mouse, dedo y lápiz con el mismo código).
- [x] Envío del expediente a revisión, con la misma condición de completitud que
      `UsuarioController::actionValidar` de la app Yii2.
- [ ] Falta: carga de archivos del paso 3 a S3 (identificación y foto sosteniéndola).
- [ ] Falta: integración Incode (biometría).
- [ ] Falta: aviso por correo al Oficial de Cumplimiento y a Operaciones.
- [ ] Falta: wizard del solicitante (5 pasos).

#### Dónde se aplica cada regla de KYC

No están en el mismo sitio, y confundirlas rompe el control:

| Regla | Dónde se evalúa |
|---|---|
| Persona moral, o inversionista Experto o Relacionado → documentación obligatoria | Al **cerrar el onboarding** |
| Monto acumulado supera $5,000 → escalar a Nivel 2 | Al **invertir**, no al registrarse |

Por eso la app Yii2 excluye a las personas morales del umbral con un `tipoPersona != 2`
que a primera vista parece un error: no lo es. La persona moral **ya tuvo que entregar
documentación** para terminar su onboarding, así que volver a exigírsela al invertir sería
redundante. Se conserva ese diseño.

#### La constancia son seis casillas, no una

Cada casilla corresponde a un riesgo que la institución debe acreditar que reveló
(pérdida, liquidez, información, rendimiento, ausencia de aprobación de la CNBV, ausencia
de asesoría). Un único "acepto todo" no acreditaría lo mismo.

Tampoco se re-firma: la primera fecha, junto con la IP, es la evidencia. Lo mismo aplica
al contrato, que **se firma una sola vez** y regula todas las inversiones posteriores.

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

### Discrepancia ABIERTA: el umbral de KYC no se cuenta por mes calendario

El manual dice, tres veces, "monto agregado de inversión **por mes calendario**" (§1.1.2,
§3.1.1, §3.2.1). La app Yii2 hace otra cosa: suma **todas** las inversiones confirmadas del
cliente, sin filtro de fecha y sin excluir las devueltas ni las canceladas.

```php
// frontend/controllers/ProyectoController.php
$totalAcumulado = SolicitudInversion::find()
    ->where(['usuarioId' => ..., 'confirmada' => 1])   // sin rango de fechas
    ->sum('monto');
```

Efecto práctico: el acumulado nunca se reinicia, así que el umbral se cruza **antes** de lo
que la norma exigiría y se pide más documentación, no menos.

**Se conservó el comportamiento de Yii2** porque es el más estricto. Pasarlo a ventana
mensual —lo que dice el manual— **relajaría un control de PLD**, y esa es una decisión del
Oficial de Cumplimiento, no una corrección técnica. Está fijado con pruebas
(`OnboardingTest`), así que cambiarlo obliga a cambiarlas a conciencia.

**Pendiente de resolver con Cumplimiento:** o se alinea el código al manual, o se corrige el
manual para reflejar el acumulado de por vida.

### Discrepancias detectadas entre el manual y el código

Hay que resolverlas **antes** de la Fase 2, porque definen el comportamiento a implementar:

1. ~~**Tiempo de inactividad.**~~ **RESUELTO (2026-09-10).** El valor normativo es el del
   manual: **5 minutos**. Los `TIMELOGOUT=30` / `TIMELOGOUTADMIN=25` que se ven en
   `topkapital-env.conf` son **exclusivos del entorno local**, para que la sesión no se
   cierre a cada rato mientras se desarrolla.

   Consecuencia para la migración: el timeout es **configuración por entorno**, nunca un
   número escrito en el código. `config/topkapital.php` lo lee de `SESSION_IDLE_MINUTES`
   con **5 como valor por defecto**, de modo que cualquier entorno que no lo declare
   cumple la norma; sólo el `.env` local lo sube a 30. Lo mismo aplica al aviso previo.
2. **Hash de contraseñas.** El manual (§4.1) dice **SHA-256**. El código usa `password_hash`
   de PHP (**bcrypt**), que es lo correcto desde el punto de vista de seguridad. Parece un
   error de redacción del manual, no del código: se conserva bcrypt y se sugiere corregir
   el manual.
3. ~~**Cuentas bancarias.**~~ **RESUELTO (2026-09-10).** El comportamiento vigente es el
   del manual: la cuenta bancaria **se detecta automáticamente al recibir el pago por STP**,
   validando titular, RFC, monto y referencia contra el perfil del inversionista. El
   inversionista **no** la captura.

   Consecuencia para la migración: el `CuentaBancariaController` del frontend Yii2
   (alta + confirmación manual, 232 líneas, 7 vistas) es código heredado y **no se porta**.
   En Laravel, "Cuentas bancarias" en el perfil es una vista de **sólo lectura** que
   muestra la(s) cuenta(s) ya identificadas. El alta real ocurre en el webhook de STP
   (Fase 7), no en una pantalla.

---

## 9. Entorno local

| Pieza | Valor |
|---|---|
| Apache | `C:\dev\Apache24` (servicio `Apache2.4`), `mod_php` |
| PHP | `C:\dev\php82` (8.2.33), que es el que carga Apache. **`php82\php.exe` quedó bloqueado por una política de Application Control de Windows el 2026-09-11**; el binario está intacto y `mod_php` no se ve afectado, pero para CLI (artisan, pruebas) hay que usar `C:\dev\php83\php.exe` (8.3.33) hasta que se resuelva |
| MariaDB | `C:\dev\mariadb`, `127.0.0.1:3306` |
| Node | `C:\Program Files\nodejs` (24.x LTS) |
| Composer | `C:\dev\composer\composer.bat` |
| BD | `topkapital` / usuario `topkapital` (respaldo de producción del 2026-08-20) |
| App Yii2 | `https://dev.topkapital.com` (frontend) y `:8080/admin` (backend) |
| **App Laravel (sin admin)** | **`http://toplaravel.localhost:8000`** con `php artisan serve` |
| App Laravel (por Apache) | `http://toplaravel.loc` → `C:\dev\toplaravel\public`, requiere correr `C:\dev\setup-toplaravel.ps1` elevado |
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
   administrador. Se dejó listo `C:\dev\setup-toplaravel.ps1` (idempotente, valida
   la config de Apache antes de reiniciar); hay que correrlo elevado.
2. **Push al repositorio.** El entorno bloqueó las operaciones con el remoto.
   Los commits ya están hechos en local; falta agregar el remoto y empujar.

**Aclaración recibida del usuario (2026-09-10):** la cuenta bancaria **sí** se
detecta automáticamente al recibir el pago por STP. Queda resuelta la discrepancia
3 del §8 y `CuentaBancariaController` sale del alcance de la migración.

### 2026-09-10 — Sesión 1 (continuación): Fase 1

- **Resueltas las 3 discrepancias del §8.** Inactividad: 5 min es el valor normativo,
  los 30 son sólo locales. Cuenta bancaria: se detecta sola con el pago de STP.
  Hash: bcrypt es lo correcto, el manual está mal redactado.
- **Compatibilidad de contraseñas probada**, en ambos sentidos. `BCRYPT_ROUNDS=13`
  para igualar el coste de Yii2.
- `config/topkapital.php`: todos los parámetros regulados (inactividad, OTP, política
  de contraseñas, bloqueo, umbral de KYC) en un solo lugar, con los valores **normativos
  como default** para que un entorno que no declare nada quede conforme.
- Componentes nuevos: `Modal`, `Alert`, `Checkbox`, `Textarea`, `MoneyInput`, `Stepper`
  y `DataTable`.
- Demo de la `DataTable` en `/ui/tabla` contra `actividad_economica`. Verificado:
  búsqueda con debounce (1,214 → 2 resultados), orden en ambos sentidos con `aria-sort`
  correcto, y **el foco se conserva** en el buscador durante la recarga parcial.
- Corregido el bug de la clave de transición (ver la Fase 1 arriba).

### 2026-09-10 — Sesión 1 (continuación): Fase 2

- **Dominio local sin permisos de administrador:** `http://toplaravel.localhost:8000`.
  Los navegadores resuelven `*.localhost` a 127.0.0.1 por sí solos, sin tocar `hosts`.
  El vhost `toplaravel.loc` sigue disponible corriendo el script elevado.
- Modelo `Usuario` mapeado a la tabla existente, con las constantes de rol y estatus
  copiadas del Yii2 y el contrato de autenticación implementado a mano
  (`usuarioId` / `passwordHash`). "Recordarme" queda **deshabilitado**: una cookie
  persistente contradice el cierre por inactividad y la sesión única del §4.4.4.
- `config/auth.php` apunta al provider `usuarios`. Se eliminó el modelo `User` por defecto.
- `PoliticaPassword` (24 pruebas) y `CodigoOtp`.
- Login en dos pasos con imagen de seguridad, y `AuthLayout`.
- **12 pruebas de Feature del login**, incluida la regresión DDS-897.

**Sobre las pruebas:** corren contra la **base compartida**, porque el usuario de base de
datos no tiene privilegios para crear una base aparte. Las que escriben usan
`DatabaseTransactions` y revierten todo. Para que nadie borre las 44 tablas por descuido,
`Tests\TestCase` **aborta la ejecución** si un test usa `RefreshDatabase`,
`DatabaseMigrations` o `DatabaseTruncation`. Verificado: tras correr la suite, la tabla
`usuario` sigue con sus 174 filas y cero residuos.

**Decisión de diseño:** si el usuario no ha elegido imagen de seguridad, la pantalla lo
dice en vez de mostrar una cualquiera. Enseñar una imagen equivocada destruiría justamente
la garantía que ese control existe para dar.

### 2026-09-10 — Sesión 1 (continuación): controles de sesión

- `ControlDeSesion` (inactividad + sesión única), `SesionUnica`, `SesionController`,
  `useSesion` y el modal de aviso con cuenta regresiva.
- `SesionWeb` y su escritura en el login. **Hallazgo:** el login que se había escrito
  antes no alimentaba `sesiones_web`, lo que habría subreportado el OFICO.
- **Bug de producción evitado:** la primera versión de la sesión única comparaba ids de
  sesión. Como Laravel regenera el id al iniciar sesión (y conviene regenerarlo al cambiar
  la contraseña), ese diseño habría expulsado al usuario de su propia sesión. Se cambió a
  un token guardado dentro de la sesión.
- `Modal` acepta `dismissible: false`, para avisos donde cerrar sin elegir no significa nada.
- 8 pruebas nuevas. Suite completa: **46 pruebas, 98 aserciones**.

**Nota sobre el entorno de pruebas:** el harness de Laravel no conserva la cookie de sesión
entre peticiones, así que el id de sesión cambia en cada una aunque los datos persistan.
Eso hizo fallar la primera versión de estas pruebas y fue lo que destapó el bug anterior.
Las pruebas de tiempo usan `travel()` en vez de manipular la sesión, porque `$this->session()`
regenera el id y dispara el control.

### 2026-09-10 — Sesión 1 (continuación): recuperación, registro y correo

- Recuperación de contraseña en tres pasos, que es también el desbloqueo del §4.3.1.
- Registro de inversionista con verificación de correo por código.
- `CodigoVerificacion` y plantilla de correo. **En local el correo va al driver `log`**
  (`storage/logs/laravel.log`), no se envía: las credenciales de `contacto@topkapital.com`
  mandan correo real y no tiene caso arriesgar eso desde una máquina de desarrollo.
  Pasar a SMTP es sólo configuración.
- `HistorialContrasena`, con la prohibición de reúso.
- **Bug corregido:** `fechaCodigoRecuperarCuenta` no tenía cast, así que llegaba como
  cadena y `CodigoOtp` reventaba con un `TypeError` en plena recuperación. Se agregó el
  cast y el servicio ahora normaliza la fecha, para que el mismo descuido en otro campo
  OTP no vuelva a romperlo.
- Suite: **65 pruebas, 170 aserciones.**

**Verificado en el navegador**, no sólo con pruebas: alta → correo en el log → captura del
código → cuenta activada → redirección al login. El usuario de prueba se borró después;
la tabla `usuario` sigue con sus 174 filas.

### 2026-09-10 — Sesión 1 (continuación): Fase 3, portal del inversionista

- Modelos del negocio: `Proyecto`, `Campana`, `SolicitudInversion`, `Noticia`, `Promotor`.
- Panel con cifras reales y **deferred props** de Inertia 2: el resumen se pinta de
  inmediato y las tres secciones pesadas llegan después, con skeletons mientras tanto.
  Es la razón de ser de Inertia aquí.
- Listado de proyectos con filtro por etapa (recarga parcial) y detalle con galería.
- `MoneyFormat.ts` centraliza el formato es-MX. Si cada vista arma su propio
  `Intl.NumberFormat`, tarde o temprano una muestra `$1,000` y otra `$1,000.00` para el
  mismo dato, y en una app financiera eso se lee como un error de saldo.
- Todas las rutas del portal detrás de `auth`.
- 8 pruebas nuevas. Suite: **71 pruebas, 227 aserciones.**
- Se eliminaron los `ExampleTest` de andamiaje: el de Feature afirmaba que `/` respondía
  200, cosa que dejó de ser cierta al proteger el portal, y su intención ya está cubierta
  por `PortalTest`.

**Dos hallazgos de entorno**, ambos detallados en la Fase 3 arriba: los archivos de S3
no son públicos pese a lo que declara la configuración, y **PHP no tenía bundle de
certificados**, lo que habría tumbado todas las integraciones de la Fase 7.

**Verificado a ojo** con un inversionista temporal: el panel mostró
`$165,000.00` de capital, sus dos inversiones y el nombre con la fecha del ingreso
anterior. Los datos de prueba se borraron después; `usuario` volvió a 174 filas y
`solicitud_inversion` a 17.

### 2026-09-11 — Sesión 1 (continuación): calendario, documentos y perfil

- `Retorno`, `Beneficiario` y `CuentaBancaria`.
- Calendario de pagos, pantalla de documentos y perfil, con la elección de la imagen
  de seguridad que faltaba del §1.4.
- `DocumentoController` entrega archivos **sólo tras verificar propiedad**. 9 pruebas
  nuevas, centradas en el control de acceso. Suite: **80 pruebas, 253 aserciones**.
- La CLABE está en `$hidden` del modelo; la vista sólo recibe los últimos cuatro dígitos.

**Incidencia de entorno:** a mitad de la sesión, Windows bloqueó `C:\dev\php82\php.exe`
con una política de Application Control. El binario está intacto y `mod_php` (la DLL que
carga Apache) no se ve afectada —el servidor siguió respondiendo—, pero la CLI dejó de
funcionar. Se pasó a `C:\dev\php83\php.exe`, que también quedó con su bundle de
certificados configurado. Conviene revisar esa política: si algún día alcanza a la DLL,
tumba la app.

### 2026-09-14 — Sesión 1 (continuación): Fase 4, onboarding

- `NivelKyc` concentra la bifurcación de KYC; `DocumentoUsuario` guarda el expediente.
- Wizard del inversionista con pasos adaptativos: el paso de archivos **no se muestra**
  en Nivel 1. El manual dice que ahí "no se presenta o permanece inactivo"; se omite del
  todo para no pedirle al cliente documentación que la norma no le exige.
- `FirmaCanvas`: firma autógrafa con Pointer Events, canvas a `devicePixelRatio` y
  captura de puntero para que el trazo no se corte si el dedo se sale del recuadro.
  El trazo es negro fijo, no un token de tema: la firma acaba en un PDF de fondo blanco
  y en modo oscuro una firma clara sería invisible en el documento.
- 23 pruebas nuevas, 10 de ellas sobre el umbral. Suite: **103 pruebas, 298 aserciones**.

**Hallazgo:** el umbral de KYC no se cuenta por mes calendario como dice el manual, sino
como acumulado de por vida. Ver la discrepancia abierta en la §8.

**Verificado a ojo:** alta de sesión, wizard en Nivel 1 sin paso de archivos, firma de la
constancia, trazo de la firma en el canvas y guardado del contrato. En la base quedaron
`constanciaFirmadoEn`, `contratoFirmadoEn`, `ipFirma` y 14 KB de firma. Los datos de
prueba se borraron: `usuario` sigue en 174 y `documento_usuario` en 5.

**Siguiente paso natural:** completar los componentes que faltan de la Fase 1
(`Combobox`, `Radio`, `Switch`, `DatePicker`, `Tabs`, `Tooltip`, `Dropdown`,
`AuthLayout`, `AdminLayout`) y entrar a la **Fase 2**, que ya no tiene bloqueos:
modelo `Usuario` mapeado a la tabla existente, login, política de contraseñas, OTP,
bloqueo por intentos, sesión única e imagen de seguridad.
