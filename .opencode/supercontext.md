# SISOLMAR Web — Supercontexto del proyecto

> Documento de contexto técnico y funcional del repositorio `sisolmar_web`.
> Objetivo: que cualquier persona (o agente de IA) entienda qué es el sistema, cómo está construido, dónde vive cada cosa y cuáles son sus reglas de negocio y riesgos antes de tocar código.
>
> **Última revisión:** noviembre 2026
> **Estado:** documento descriptivo del código real del repositorio (no aspiracional).

---

## 1. Ficha rápida

| Campo | Valor |
|---|---|
| Nombre del proyecto | SISOLMAR Web / Grupo Solmar |
| Tipo | Aplicación web monolítica (Laravel) |
| Framework | Laravel 10.x (`laravel/framework ^10.10`) |
| Lenguaje backend | PHP `^8.1` |
| Base de datos principal | SQL Server (`sqlsrv`), base `sisolm_web` |
| Bases secundarias | SQL Server `reclusol`, SQL Server `CONTROLCLIENTES2018`, MySQL de Moodle (`grupoihb_see`) |
| Frontend | Blade + Vite 5 + Tailwind CSS 3 + Alpine.js + jQuery + librerías JS |
| Autenticación | Sesión web contra tabla `sw_usuarios` |
| Autorización | Roles + menús/submenús + funcionalidades (`sw_roles_permisos`, `sw_funcionalidades`) |
| Zona horaria | `America/Lima` |
| Idioma de interfaz | Español (`locale = es`) |
| Gestor de colas | Base de datos (`jobs`) en desarrollo; `sync` en el `.env.prod` actual |
| Integraciones | Moodle (MySQL + Web Services), Biblioteca Gráfica (HTTP/UNC), ERP legado `si_solm` |
| Repositorio | Git local; `.opencode/` no está versionado actualmente |

---

## 2. Resumen ejecutivo

SISOLMAR Web es el sistema interno de **Grupo Solmar** para la **gestión documentaria y de cumplimiento del personal** (legajos, folios, declaraciones juradas), la **capacitación corporativa** (cursos, programaciones, matrículas, exámenes, certificados y memorandos) y la **generación de reportes** operativos y de RR. HH.

La aplicación es un **monolito Laravel** que:

- Se autentica contra usuarios propios (`sw_usuarios`) y autoriza por rol, menú y submenú.
- Consume y cruza información del **ERP legado** alojado en la base `si_solm` (tablas `si_solm.dbo.*`).
- Se integra con **Moodle** (base MySQL `grupoihb_see`) para crear cursos, matricular alumnos, subir contenido y sincronizar exámenes.
- Accede a una **Biblioteca Gráfica** de archivos (fotos, firmas, huellas, DNI, PDFs de DJ) por HTTP y por rutas UNC de red.
- Usa **stored procedures** de SQL Server para la mayoría de listados y reportes pesados.
- Tiene dos generaciones de módulo de capacitación conviviendo: el legado (`sw_cursos`) y el nuevo (`sw_cursos_new`).

El código es funcional y altamente orientado a negocio, pero presenta **deuda técnica significativa**: controladores muy grandes, SQL crudo y stored procedures, rutas duplicadas, archivos de respaldo, credenciales hardcodeadas como fallback y pruebas automatizadas casi inexistentes.

---

## 3. Propósito y alcance funcional

### 3.1 ¿Qué resuelve?

1. **Control de legajos y folios del personal**
   - Registro de personal, cargos, clientes, legajos y documentos.
   - Folios vigentes, pendientes, próximos a vencer y vencidos.
   - Carga y escaneo de documentos, generación de PDFs de legajo.
   - Dashboard y reportes por sucursal, cliente, cargo y estado.

2. **Declaración Jurada (DJ)**
   - Gestión de DJ del personal y de sus derechohabientes.
   - Actualizaciones por periodo y por corte.
   - Validación de documentos, edad, contrato, vacaciones y coincidencias.
   - Carga de fotos, DNI (anverso/reverso) y DJs firmadas.
   - Reportes de avance, personal sin migración y etapa 2.
   - Excepciones de edad autorizadas por RR. HH.

3. **Capacitación**
   - Catálogo de cursos, áreas, sistemas, planes, tipos y dirigidos.
   - Programaciones (aperturas) por curso, sucursal y cliente.
   - Matrículas individuales y **masivas** (procesadas en cola).
   - Cursos periódicos: clonación, cierre y reprogramación automática.
   - Exámenes (configuración, preguntas, intentos, notas).
   - Planes de capacitación PCE y PCA.
   - Seguimiento de matrículas y avance de alumnos.
   - Memorandos (memos) de capacitación y recordatorios.
   - Certificados y reportes de capacitación (Excel/PDF/ZIP).

4. **Maestros y seguridad**
   - Usuarios, roles, menús, sucursales y permisos por usuario.

5. **Notificaciones y correo**
   - Alertas de documentos por vencer.
   - Notificaciones de matrícula.
   - Correos de bienvenida, recordatorios, memos y resúmenes de matrícula masiva.

### 3.2 Usuarios típicos

- Personal de **RR. HH.** y **Operaciones**.
- Administradores del sistema.
- Responsables de **capacitación**.
- Usuarios de **file control** / archivo documentario.
- Jefaturas y administradores de sucursal.

---

## 4. Stack tecnológico

### 4.1 Backend

| Componente | Versión / detalle |
|---|---|
| PHP | `^8.1` |
| Laravel | `^10.10` |
| Sanctum | `^3.3` (API tokens; la app web usa guard de sesión) |
| Guzzle | `^7.2` (HTTP client) |
| DomPDF | `barryvdh/laravel-dompdf ^3.1` |
| Snappy / wkhtmltopdf | `barryvdh/laravel-snappy ^1.0` |
| PhpSpreadsheet | `^5.7` (Excel) |
| PhpWord | `^1.4` (Word / exámenes) |
| Pusher PHP Server | `^7.2` (broadcasting) |
| Tinker | `^2.8` |

Dev: PHPUnit `^10.1`, Mockery, Faker, Laravel Pint, Laravel Sail, Collision, Spatie Ignition.

### 4.2 Frontend

| Componente | Detalle |
|---|---|
| Motor de plantillas | Blade |
| Bundler | Vite `^5.4` + `laravel-vite-plugin ^1.3` |
| CSS | Tailwind CSS `^3.4`, SCSS, CSS propio |
| JS base | Alpine.js `^3.14`, jQuery `^3.7`, Axios `^1.8` |
| Tablas | Tabulator `^6.3`, DataTables, Grid.js, Vanilla DataTables |
| UI | SweetAlert2 `^11.17`, Preline `^2.5`, Boxicons, Iconify, Tagify, Quill, Dropzone, SimpleBar, GLightbox, Swiper |
| Gráficos | Chart.js `^4.4`, ApexCharts `3.27`, Morris.js, jsVectorMap |
| PDF / Excel | jsPDF `^3`, jsPDF-AutoTable, html2pdf.js, ExcelJS `^4.4`, SheetJS `xlsx`, FileSaver |
| Fechas | Moment, Day.js, vanilla-datepicker, FullCalendar `^6.1` |
| Mapas | gmaps, jsVectorMap |

### 4.3 Infraestructura

- **Windows Server** (producción), IIS o Apache con `public/web.config`.
- SQL Server remoto (`192.168.10.21`) y servidores auxiliares (`192.168.10.20`).
- MySQL de Moodle (acceso desde la app mediante `mysql_grupoihb`).
- Queue worker de Laravel ejecutado en el mismo servidor web (ver `INSTRUCCIONES_PRODUCCION.md`).
- Programador de tareas de Windows para `schedule:run` y para mantener vivo el worker.

---

## 5. Estructura de directorios

```text
sisolmar_web/
├── app/
│   ├── Console/
│   │   ├── Commands/            # Comandos Artisan de negocio
│   │   └── Kernel.php           # Scheduler
│   ├── Events/                  # Eventos de matrícula masiva
│   ├── Exceptions/
│   ├── Helpers/                 # PdfHelper, ImagenHelper (autoload files)
│   ├── Http/
│   │   ├── Controllers/         # Controladores web y Api/
│   │   ├── Middleware/          # CargarMenus, Authenticate, etc.
│   │   └── Kernel.php           # Middleware global y grupos
│   ├── Jobs/                    # Jobs de cola
│   ├── Mail/                    # Mailables
│   ├── Models/                  # Eloquent
│   ├── Providers/               # App, Auth, Broadcast, Event, Route
│   └── Services/                # Dj, Firma, Moodle, Pdf
├── bootstrap/
├── config/                      # Configuración Laravel
├── database/
│   ├── migrations/              # Migraciones (mayormente tablas sw_*)
│   ├── seeders/
│   └── factories/
├── docs/                        # Planes y reportes de orquestación
├── public/
│   ├── images/ img/ pdfs/ reportes/ temp_legajos/
│   ├── index.php
│   └── web.config
├── resources/
│   ├── css/                     # CSS propio
│   ├── js/
│   │   ├── app.js
│   │   ├── functions/           # Lógica JS por módulo
│   │   └── pages/               # Scripts de páginas de plantilla
│   ├── scss/
│   └── views/
│       ├── layouts/             # base, vertical, shared/*
│       ├── file_control/        # Vistas de legajos, folios, DJ, reportes
│       ├── capacitacion/        # Vistas de cursos, matrículas, planes
│       ├── maestros/            # Usuarios
│       ├── emails/              # Plantillas de correo
│       ├── enterprise/          # Documentos
│       ├── forms/, ui/, apps/   # Vistas de plantilla UI
│       └── errors/
├── routes/
│   ├── web.php                  # Casi todas las rutas de la app
│   ├── api.php                  # API mínima (Sanctum + DJ externo)
│   ├── auth.php                 # Login/logout
│   ├── channels.php             # Canales de broadcasting
│   └── console.php
├── storage/                     # Logs, caché, archivos de Laravel
├── temp/                        # Directorio temporal forzado por AppServiceProvider
├── tests/                       # Pruebas base (casi vacías)
├── .agent/                      # Toolkit de agentes de IA (no es código de la app)
├── .opencode/                   # Contexto para agentes (este archivo)
├── .env / .env.dev / .env.prod / .env.example
├── artisan
├── composer.json
├── package.json
├── vite.config.js
├── tailwind.config.js
├── iniciar_queue_worker.bat
├── INSTRUCCIONES_PRODUCCION.md
└── README.md
```

### 5.1 Archivos y carpetas relevantes

- `.agent/`: kit de agentes/skills de IA. **No forma parte del runtime** de la aplicación.
- `.opencode/supercontext.md`: este documento.
- `docs/PLAN.md`: plan de corrección de fechas en el SP de folios pendientes.
- `docs/ORCHESTRATION_REPORT.md`: reporte de orquestación.
- `walkthrough.md`: pasos manuales de verificación de matrículas/cursos.
- `table_structure.json`: estructura de la tabla legada `PERSONAL` (tipos y longitudes).
- `test_db.php`, `test_notificacion.php`: scripts sueltos de prueba.
- `INSTRUCCIONES_PRODUCCION.md`: configuración del queue worker en Windows.

---

## 6. Arquitectura general

### 6.1 Vista de alto nivel

```text
┌──────────────────────────────┐
│ Navegador (Blade + JS)       │
│ Tailwind / Alpine / jQuery   │
│ Tabulator / SweetAlert / etc.│
└──────────────┬───────────────┘
               │ HTTP (sesión + CSRF)
               ▼
┌──────────────────────────────┐
│ Laravel 10 (monolito)        │
│                              │
│ routes/web.php               │
│   ├─ Login/auth              │
│   ├─ /api/* (web + auth)     │
│   └─ catch-all de vistas     │
│                              │
│ Middleware web:              │
│   EncryptCookies, Session,   │
│   CSRF, CargarMenus          │
│                              │
│ RoutingController            │
│   ├─ permisos por rol        │
│   └─ dispatch a controlador  │
│                              │
│ Controllers (23k líneas)     │
│ Services / Helpers           │
│ Jobs / Events / Mail         │
└──────┬───────────┬───────────┘
       │           │
       │           ├──────────────────────────────┐
       │           │                              │
       ▼           ▼                              ▼
┌────────────┐ ┌──────────────────┐      ┌────────────────────┐
│ SQL Server │ │ MySQL Moodle     │      │ Biblioteca Gráfica │
│ sisolm_web │ │ grupoihb_see     │      │ HTTP + UNC         │
│ si_solm    │ │ mdl_*            │      │ fotos, firmas, DNI │
│ reclusol   │ │                  │      │ PDFs, huellas      │
│ control... │ │                  │      │                    │
└────────────┘ └──────────────────┘      └────────────────────┘
```

### 6.2 Patrón de arquitectura

- **Monolito MVC clásico** con controladores “gordos” que concentran lógica de negocio, consultas SQL y armado de respuestas JSON/vistas.
- **Frontend server-side** con Blade y una capa grande de JavaScript por módulo que consume endpoints `/api/*`.
- **Sin capa de repositorios ni DTOs formales**: los controladores usan directamente `DB::select`, `DB::table`, modelos Eloquent y stored procedures.
- **Permisos en sesión**: el árbol de menús se calcula al iniciar sesión y se reutiliza para renderizar el sidebar y para autorizar la navegación.
- **Rutas de vista catch-all**: la URL `/{modulo}/{vista}` se resuelve contra `RoutingController`, que valida el permiso y despacha al controlador o renderiza la vista Blade.
- **Colas para procesos pesados**: matrícula masiva, correos y resúmenes.

### 6.3 Flujo de una petición típica

1. El navegador solicita una URL, por ejemplo `/capacitacion/gestion_cursos`.
2. Laravel aplica el grupo `web`: cookies, sesión, CSRF y `CargarMenus`.
3. `CargarMenus` obtiene permisos del rol y funcionalidades, y las comparte con las vistas y la sesión.
4. La ruta catch-all `{first}/{second}` llama a `RoutingController@secondLevel`.
5. `RoutingController` construye la clave `modulo.vista` (ej. `capacitacion.gestion_cursos`), verifica que esté en `session('permisos')` y, si el rol es `1`, deja pasar siempre.
6. Si hay un controlador mapeado, lo ejecuta; si no, renderiza la vista Blade.
7. La vista carga assets de Vite y ejecuta JS que llama a endpoints `/api/...`.
8. Los endpoints responden JSON usando datos de SQL Server, MySQL/Moodle, stored procedures o servicios.
9. Si hay procesos pesados (matrícula masiva), se encola un job y se notifica el progreso por eventos/canal privado.

---

## 7. Autenticación y autorización

### 7.1 Login

- Ruta: `GET /login` → `LoginController@index` (vista `login`).
- Validación: `POST /validate` → `App\Http\Controllers\Auth\AuthenticatedSessionController@store` (nombre `login.validar`, middleware `guest`).
- El login busca en `sw_usuarios` por `usuario` y `habilitado = 1`, y valida con `Hash::check($password, $user->clave)`.
- Modelo: `App\Models\User` (`Authenticatable`), tabla `sw_usuarios`, PK `codigo`, sin timestamps, `getAuthPassword()` devuelve `clave`.
- Guard: `web` (sesión), provider `users` (Eloquent).

### 7.2 Datos guardados en sesión al iniciar

| Clave | Origen |
|---|---|
| `nombre` | `sw_usuarios.nombre_1` |
| `apellido` | `sw_usuarios.apellido_1` |
| `usuario` | `sw_usuarios.usuario` |
| `tipo_rol` | `sw_usuarios.tipo_rol` |
| `limitarTipoPer` | `sw_usuarios.limitarTipoPer` |
| `limitarSucursales` | `sw_usuarios.limitarSucursal` |
| `permisos` | árbol de menús/submenús del rol |
| `funcionalidades` | nombres de funcionalidades del rol |

Además se limpia `notif_popups_ya_mostrados` para que las notificaciones emergentes se muestren una vez por sesión.

### 7.3 Permisos y menús

El modelo `App\Models\Permisos` ejecuta:

- `sw_menus` + `sw_roles_permisos` para menús habilitados por rol.
- `sw_submenus` + `sw_roles_permisos` para submenús habilitados por rol.
- Construye un árbol con `id`, `nombre`, `modulo`, `icono`, `orden`, `tiene_submenu`, `submenus[]`.

El sidebar (`resources/views/layouts/shared/sidenav.blade.php`) recorre `$permisos` y genera enlaces con `route('second', [modulo, vista])`.

### 7.4 Funcionalidades

Además de los menús, existen **funcionalidades** que habilitan acciones especiales (por ejemplo, exenciones de validación o cambio de tipo de personal):

```sql
SELECT f.nombre
FROM sw_funcionalidades f
INNER JOIN sw_roles_funcionalidades rf ON f.codigo = rf.codFuncionalidad
WHERE rf.codRol = ? AND rf.habilitado = 1 AND f.habilitado = 1
```

Se guardan en sesión como `funcionalidades` y se comparten con las vistas.

### 7.5 Autorización de vistas

`RoutingController`:

- `getVistasPermitidas()` recorre `session('permisos')` y arma claves `modulo.vista`.
- `tienePermiso($vista)` devuelve `true` si `tipo_rol == 1`; si no, busca la clave en la lista.
- `secondLevel` mapea explícitamente controladores para:
  - `dj.gestion_dj`, `dj.actualizar_dj`, `dj.reporte_personal`, `dj.gestionar_actualizaciones_dj`
  - `file_control.chargefile`, `cargos`, `legajos`, `legajos_comercial`, `folios`, `search_legajos`, `legajos_pdf`, `dashboard`, `reportes`, `carga_escaneo_dj`
  - `capacitacion.consulta_matriculas`, `historial_capacitaciones`, `gestion_cursos`, `cursos_sucamec`, `seguimiento_matriculas`, `reportes_capacitaciones`, `planes_capacitacion`
  - `maestros.usuarios`
- Si no hay controlador mapeado, intenta `view($vistaKey)`.
- `thirdLevel` valida permiso y renderiza `modulo.vista.subvista`.
- `root($first)` renderiza `view($first)` sin validar permisos (solo para rutas de primer nivel).
- Si no hay permiso: `abort(403)` con vista `errors/403.blade.php`.

### 7.6 Logout y cambio de contraseña

- `POST /logout` → `AuthenticatedSessionController@destroy` (middleware `auth`), invalida sesión y regenera token.
- `POST /api/change-password-user` → `LoginController@updatePasswordUser`.
- `GET /usuario` → `LoginController@getUsuarioSession` devuelve datos del usuario en sesión.

### 7.7 Rate limiting

Definido en `RouteServiceProvider`:

| Limiter | Límite | Clave |
|---|---|---|
| `api` | 60 req/min | usuario autenticado o IP |
| `dj_api` | 90 000 req/min | IP |

El grupo `/api/*` de `routes/web.php` **no** usa el limiter `api` explícitamente; el grupo `/dj/*` sí usa `throttle:dj_api`.

---

## 8. Módulos funcionales y mapa de rutas

### 8.1 Estructura de rutas

- `routes/web.php`: **286 definiciones de ruta**. Contiene login, rutas autenticadas, todo `/api/*` y el catch-all de vistas.
- `routes/api.php`: 1 ruta (`/api/ver-dj-externo/{codPersonal}`), además del endpoint Sanctum `/api/user`.
- `routes/auth.php`: `POST /validate` y `POST /logout`.
- `routes/channels.php`: canales `App.Models.User.{id}` y `usuario.{id}`.

> **Importante:** casi toda la “API” interna vive en `routes/web.php` bajo el grupo `auth` y el middleware `web`. Por lo tanto usa sesión y CSRF, no tokens Sanctum. Solo `routes/api.php` usa el grupo `api` (Sanctum).

### 8.2 Módulos y prefijos

| Módulo | Prefijo / rutas | Vista principal |
|---|---|---|
| Login | `/login`, `/validate`, `/logout` | `login.blade.php` |
| Inicio | `/home`, `/` | `index.blade.php` |
| File Control | `/file_control/*`, `/api/get-personal*`, `/api/get-folios*`, `/api/save_folio*` | `file_control/*` |
| Declaración Jurada | `/dj/*`, `/api/dj/*`, `/api/get-personal-dj*`, `/ver-dj/{cod}` | `file_control/gestion_dj`, `actualizar_dj`, `reporte_personal` |
| Capacitación | `/capacitacion/*`, `/api/get-cursos*`, `/api/obtener-*`, `/api/cursos/*` | `capacitacion/*` |
| Reportes | `/reporte/*`, `/api/reporte/*`, `/reportes` | `file_control/reportes` |
| Maestros | `/maestros/usuarios`, `/api/get-usuarios`, `/api/save-usuario` | `maestros/usuarios.blade.php` |
| Notificaciones | `/api/notificaciones-*`, `/api/get-notificaciones` | Componentes JS |
| Ubicación | `/api/ubicacion/*` | Selectores de departamento/provincia/distrito |
| Correo | `/api/mail/*` | `emails/*` |
| Biometría | `/api/get-biometrico/{codigo}` | Proxy de imágenes |
| Catálogos de capacitación | `/api/catalogos-capacitacion/*` | JSON |

### 8.3 Rutas destacadas por área

#### File Control / Legajos / Folios

- `GET /api/get-personal`, `/get-personal-total`, `/get-personal-legajos`, `/get-personal-legajos-pdf`
- `GET /api/get-cargos-erp`, `/get-cargos`, `/get-cargos/{codigo}`
- `GET /api/get-clientes`, `/get-clientes-legajos`
- `GET /api/get-legajos`, `/get-folios`, `/get-folios-vigentes`, `/get-folios-pendientes`
- `GET /api/folios/proximos-vencer`
- `POST /api/save_folio`, `/save_folio_persona`, `/save_legajo`, `/save_cargo`
- `POST /api/disabled_folio`, `/activar_folio`, `/delete-cargo`, `/activar-cargo`
- `POST /generar-pdf`, `/generar-pdf2`, `/dash-rrhh`, `/pdf_vacio`
- `GET /ver-dj/{codPersonal}`, `/api/get-documentos/{personalId}`

#### Declaración Jurada

- `GET /dj/get-personal-data`, `/dj/get-catalogs`, `/dj/get-ubicacion`
- `POST /dj/save-dj-completo`, `/dj/save-nueva-dj`
- `GET /dj/get-backup-data`, `/dj/proxy-foto`
- `POST /dj/update-check-pdf`, `/dj/reset-check-pdf`, `/dj/save-recontratacion`
- `POST /dj/upload-foto-personal`, `/dj/upload-dni-personal`, `/dj/upload-dni-hijos`
- `GET /dj/get-dni-personal`, `/dj/get-foto-personal`, `/dj/get-dni-hijos`
- `GET /dj/validar-documento`, `/dj/verificar-contrato`, `/dj/verificar-vacaciones`, `/dj/buscar-coincidencias`
- `POST /dj/validar-excepcion-edad`, `/dj/save-usuarios-excepcion-edad`
- `GET /dj/reporte-personal-datos-generales`, `/dj/reporte/personal-documentos`, `/dj/reporte/dni-hijos`
- `GET /api/reporte-avances-dj`, `/api/reporte-etapa4-dj`
- `GET /dj/periodos/gestionar`, `/api/dj/periodos/listado`, `/dj/periodos/personal-corte`
- `POST /dj/periodos/guardar`

#### Capacitación (legado y nuevo)

- `GET /api/get-cursos/{op?}`, `/api/get-curso-id/{id}`, `/api/get-programacion-id/{id}`
- `POST /api/save-cursos`, `/api/update-curso`, `/api/update-programacion`
- `DELETE /api/cursos/{codigo}`, `PATCH /api/cursos/{codigo}/habilitado`
- `GET /api/get-matriculas-curso/{cursoId}`, `/api/get-historial-capacitaciones/{personalId}`
- `POST /api/save-matricula`, `/api/capacitacion/save-matricula-new`
- `GET /api/matriculas-masivas/{jobId}`
- `POST /api/cursos/analizar-plantilla`, `/api/capacitacion/procesar-examen-word`, `/api/capacitacion/guardar-examen-word`
- `POST /api/capacitacion/desmatricular-usuario`, `/api/capacitacion/suspender-usuario/{cursoId}`
- `GET /api/obtener-plan-pce`, `/api/obtener-plan-pca/{codCliente}`
- `GET /api/obtener-plan-pce-new`, `/api/obtener-plan-pca-new/{codCliente}`
- `POST /api/guardar-curso-new`, `/api/actualizar-curso-new`, `/api/cursos/aperturar-curso`, `/api/cursos/subir-av`
- `POST /api/capacitacion/registrar-reporte`, `GET /api/capacitacion/listar-reportes`
- `GET /api/obtener-certificados`, `/api/obtener-certificados-portuarios`
- `POST /api/enviar-memos-varios`, `/api/enviar-memo-personal`
- `GET /api/reportes-capacitaciones/*`, `/api/catalogos-capacitacion/*`

#### Reportes

- `GET /reporte/folios-pendientes-sucursal`
- `GET /reporte/folios-por-vencer`
- `GET /reporte/folios-pendientes-registro`
- `GET /reporte/carnet`
- `GET /reporte/vigencia-dni`
- `GET /reporte/vigencia-brevete`
- `GET /reporte/categorias-brevete`
- `GET /reporte/certificados`
- `GET /reportes` (vista)
- `GET /rrhh/reporte-avances`

#### Maestros / Usuarios

- `GET /api/get-usuarios`
- `POST /api/save-usuario`, `/api/update-usuario`, `/api/toggle-usuario`
- `GET /api/get-sucursales-usuario/{codUsuario}`, `POST /api/save-sucursales-usuario`
- `GET /api/get-menus-usuario/{codUsuario}`, `POST /api/save-menus-usuario`

#### Notificaciones

- `GET /api/get-notificaciones`
- `POST /api/delete-notif`
- `GET /api/notificaciones-matriculas`
- `POST /api/notificaciones-matriculas/{codigo}/leer`, `/api/notificaciones-matriculas/leer-todas`
- `GET /api/notificaciones/folios-por-vencer`
- `GET /api/notificaciones/pendientes-etapa2`
- `GET /api/notificaciones/demandas-admin`, `POST /api/notificaciones/demandas-admin/borrar`

### 8.4 Rutas de prueba o diagnóstico

Estas rutas existen y deben revisarse antes de producción:

- `GET /preview-email-caducidad` (sin auth): renderiza un correo de prueba.
- `GET /test-email-caducidad` (sin auth): envía un correo real a `webmaster@gruposolmar.com.pe`.
- `GET /debug-permisos` (con auth): devuelve `tipo_rol` y `permisos` de la sesión.
- `GET /api/ver-dj-externo/{codPersonal}` en `routes/api.php`: acceso externo a PDF de DJ con Sanctum (revisar alcance).

### 8.5 Rutas declaradas sin método implementado

En `routes/web.php` existen:

- `POST /api/capacitacion/procesar-examen-ia` → `CapacitacionController@procesarExamenConIA`
- `POST /api/capacitacion/guardar-examen-ia` → `CapacitacionController@guardarExamenIA`

No se encontraron esos métodos en los controladores. Las rutas equivalentes implementadas son `procesarExamenWord` y `guardarExamenWord`. **No usar las rutas `-ia`** hasta implementarlas o eliminarlas.

---

## 9. Inventario de controladores

> Total: 23 archivos de controlador (excluyendo backups), ~23 000 líneas y ~298 métodos públicos.

### 9.1 Controladores web

#### `LoginController.php`
- `index()`: muestra login.
- `updatePasswordUser()`: cambio de contraseña.
- `getUsuarioSession()`: datos del usuario autenticado.
- `logout()`: cierre de sesión.

#### `Auth\AuthenticatedSessionController.php`
- `create()`: vista de login.
- `store()`: validación de credenciales, carga de sesión, permisos y funcionalidades.
- `destroy()`: logout.

#### `RoutingController.php`
- `index()`: redirige a `/index`.
- `root($first)`: renderiza vista de primer nivel.
- `secondLevel($first, $second)`: resuelve permisos y despacha a controlador o vista.
- `thirdLevel($first, $second, $third)`: resuelve permisos y renderiza subvista.

#### `FileController.php` (~98 KB)
Núcleo de **file control / legajos / folios / DJ / PDFs**.
- Vistas: `index`, `indexGestionDj`, `indexActualizarDj`, `indexReportePersonal`, `ViewReportes`, `ViewCargo`, `ViewLegajo`, `ViewFolios`, `ViewBusquedaLegajo`, `ViewLegajoPdf`, `ViewLegajo_comercial`, `ViewDashboard`, `ViewEscaneoDJ`.
- Personal: `getPersonal`, `getPersonalTotal`, `getPersonalTotalReporte`, `getPersonalTotalPrueba`, `getPersonalReportePersonal`, `getDetallePersonalHistorial`, `getPersonalLegajos`, `getPersonalLegajosPdf`, `getPostulantes`.
- Clientes/cargos: `getClientes`, `getClientesLegajos`, `getCargos`, `getCargosXCliente`, `getCargosErp`, `getCargosXCodigo`, `getAllCargo`, `saveCargo`, `insertarCargo`, `updateCargo`, `eliminarCargo`, `activarCargo`, `checkNombreCargo`.
- Legajos: `getLegajos`, `saveLegajo`, `getCoincidencias`, `getEstadoLegajos`.
- Folios: `getFolios`, `getFoliosXLegajo`, `getFoliosXLegajo_comercial`, `getFoliosClienteCargo`, `getFoliosXPersonas`, `getFoliosXPersona_uno`, `saveFolio`, `saveFolioPersona`, `disabledFolio`, `activarFolio`, `checkNombreFolio`.
- DJ: `getListaDJ`, `getListaDJXusuario`, `getListaDJ2026`, `getListaDJMigracion`, `saveDjFolio`, `saveDjFolioAux`, `verDjPdf`, `verDjPdfExterno`, `getViewDocumentsPer`, `getDocumentosXPersonal`.
- PDFs/reportes: `generarPDF`, `generarPDF2`, `generarPDF_env`, `generarReporteConsulta`, `pdf_vacio`, `dashboard`, `obtenerNombreVista`.
- Catálogos: `getAreas`, `getPosicion`, `getGrupo`, `getGrupoId`.
- Solicitudes: `saveSolicitud`.
- Notificaciones: `getNotificaciones`.

#### `DjController.php` (~218 KB)
Núcleo de **Declaración Jurada**.
- DJ: `saveDeclaracionJurada`, `saveNuevaDj`, `saveDjCompleto`, `saveRecontratacion`.
- Datos: `getPersonalData`, `getCatalogs`, `getPaises`, `getUbicacion`, `getBackupData`, `completarCamposNull`.
- Migración/sincronización: `migrarPersonal`, `updateMigraPersonal`, `syncDJ2026ToPersonal`, `saveFamiliares`, `migrarFamiliares`, `saveOcupaciones`, `migrarOcupaciones`, `saveTelefonosTemp`, `migrarTelefonos`, `insertOrUpdateDJ2026Personal`, `saveFamiliaresTemp`, `saveOcupacionesTemp`.
- Archivos: `uploadFotoPersonal`, `uploadDniPersonal`, `uploadDniHijos`, `getDniPersonal`, `getFotoPersonal`, `getDniHijos`, `proxyFoto`.
- Validaciones: `validarDocumentoDj`, `verificarContrato`, `verificarVacaciones`, `buscarCoincidencias`, `validarExcepcionEdad`, `reglasEdad`.
- Excepciones de edad: `getUsuariosExcepcionEdad`, `saveUsuariosExcepcionEdad`, `excepcionEdadAutorizada`, `registrarExcepcionEdad`.
- Checks de PDF: `updateCheckPdf`, `resetCheckPdf`, `getCheckPdf`.
- Reportes: `reportePersonalSinMigracion`, `reportePersonalSinMigracionV2`, `reportePersonalEtapa2PDF`, `reporteAvancesDj`, `reporteEtapa4Dj`, `saveReporteAvanceDj`.
- Catálogos: `getTipoDoc`, `getEstadoCivil`, `getSistemaPrev`, `getTipoPer`, `getCargosDj`.
- PDF: `generarPDF`.

#### `CapacitacionController.php` (~250 KB, el más grande)
Núcleo de **capacitación legado** (`sw_cursos`) + buena parte del nuevo flujo.
- Cursos: `index`, `getCursoExamenXId`, `saveCurso`, `updateCurso`, `actualizarCurso`, `updateCursoHab`, `destroyCurso`, `generateCourseCode`, `generateCourseCodeByArea`, `calcularPeriodicidad`.
- Programaciones: `saveProgramacion`, `updateProgramacion`, `updateProgramacionHab`, `getProgramacionXId`, `getCursoProgramacionXId`, `storeProgramacionManual`, `obtenerProgActual`, `obtenerProgramaciones`, `aplazarCurso`.
- Matrículas: `saveMatricula`, `estadoMatriculaMasiva`, `getMatriculasPorCurso`, `getMatriculasMigraPersonal`, `obtenerMatriculados`, `obtenerDatosMatricula`, `desmatricularUsuario`, `suspenderUsuario`.
- Moodle: `getOrCreateMoodleUser`, `createMoodleCourse`, `uploadFileToMoodleDraft`, `uploadPortadaToMoodle`, `uploadAficheToMoodle`, `actualizarCursoMoodle`, `realizarMatriculacion`, `actualizarFechasMatriculasMoodle`.
- Exámenes: `createExamen`, `syncPreguntasWord`, `guardarPreguntasLocal`, `transformarPreguntas`, `procesarExamenWord`, `guardarExamenWord`, `analizarPlantilla`.
- Personal: `buscarPersonalCapacitacion`, `obtenerPersonal`, `resolverPersonalPorDnis`, `obtenerPersonalTodasEmpresas`, `obtenerPersonalParaRecord`, `obtenerPersonalParaReporte`, `getPersonalPorSucursal`, `listarJefaturas`, `mapearPersonales`.
- Catálogos: `getTipoCursos`, `getAreas`, `getAreasEncargadas`, `getAreasPorSistema`, `obtenerAreas`, `getSucursales`, `listarSucursales`, `listarTiposPers`, `getEmpresasList`, `getClientesForPAC`, `obtenerSistemas`, `obtenerTiposDeCurso`, `getCombosApertura`.
- Planes: `obtenerPlanPCE`, `obtenerPlanPCA`, `obtenerCursosPorPlan`.
- Reportes: `obtenerCursosParaReportes`, `obtenerCursos`, `obtenerCursosPorCliente`, `obtenerReporteGeneral`, `obtenerReporteFormato`, `saveReporteCapacitacion`, `listarReportesCapacitaciones`, `descargarReporte`, `descargarReportesZip`, `actualizarReporte`, `actualizarEstadoReporte`, `eliminarReporte`.
- Certificados: `listarCertificados`, `listarCertificadosPortuarios`, `reporteCursosPortuarios`.
- Memos: `enviarMemo`, `enviarMemos`, `procesarMemo`, `obtenerMemosEnviados`, `obtenerMemosResumen`, `obtenerDetalleMemo`, `obtenerMemosPersonal`, `obtenerInfoMemo`, `compararMemos`.
- Seguimiento: `getCursosSeguimiento`, `obtenerDetalleCurso`, `obtenerEstudiantesCurso`, `obtenerEstadoCursosAlumno`, `getHistorialCapacitaciones`.
- Vistas: `vistaConsultaMatriculas`, `vistaGestionCursos`, `vistaCursosSucamec`, `vistaHistorialCapacitaciones`, `vistaSeguimientoMatriculas`, `vistaReportesCapacitaciones`, `vistaPlanesCapacitacion`.
- Alertas: `getAlertasVencimiento`.

#### `ActualizacionesDjController.php`
- `index()`: vista de gestión de periodos.
- `getPersonalPorCorte()`: personal por corte.
- `storePeriodo()`: guarda periodo.
- `getPeriodos()`: lista periodos.

#### `ReporteController.php`
- `index()`: vista de reportes.
- `foliosPendientesPorSucursal()`, `foliosPendientesRegistro()`, `foliosPorVencer()`, `foliosPorVencerXCliente()`.
- `carnet()`, `vigenciaDni()`, `vigenciaBrevete()`, `categoriasBrevete()`, `certificados()`.

#### `ReportePersonalController.php` (~63 KB)
Reportes de personal y documentos.
- `datosGenerales()`, `documentosPendientes()`, `dniHijosPendientes()`, `descargarReporte()`, `proxyImagen()`.
- Genera Excel de documentos y DNI de hijos.
- Verifica URLs de archivos en lotes, calcula estados (foto, DNI anverso/reverso, edad, tipo de personal).

#### `UsuarioController.php`
- `index()` (vista maestros/usuarios).
- `getUsuarios`, `store`, `update`, `toggleHabilitado`.
- `getSucursalesUsuario`, `saveSucursalesUsuario`.
- `getMenusUsuario`, `saveMenusUsuario`.

#### `NotificacionController.php`
- `deleteNotificacion`, `foliosPorVencer`, `enviarAlertasCaducidad`, `pendientesEtapa2`, `getDemandasAdmin`, `deleteDemandaAdmin`.

#### `ConsultaController.php`
- `getSucursalesXCliente`, `getFoliosVigentes`, `getFoliosPendientes`, `getFoliosProximosVencer`.

#### `BiometricoController.php`
- `show($codigo)`: busca huella/firma/foto en Biblioteca Gráfica (HTTP o UNC) y responde en base64.
- Métodos privados de búsqueda y conversión.

#### `ExamenesController.php`
- `obtenerCursosAV()`, `obtenerDatosReporteAV()`: reportes de exámenes/cursos.

#### `ReporteAvancesController.php`
- `index()`: reporte de avances por sucursal/tipo usando stored procedure.
- Normaliza y formatea registros.

#### `UbicacionController.php`
- `departamentos()`, `provincias($departamento_id)`, `distritos($provincia_id)`.

### 9.2 Controladores API (`app/Http/Controllers/Api`)

#### `NewCapacController.php` (~68 KB)
Nueva generación de capacitación (`sw_cursos_new`).
- `obtenerCursos`, `obtenerCursosMatriculas`, `obtenerCurso`, `ultimoCodigoCurso`.
- `dirigidos`, `obtenerDirigidosNew`, `obtenerAreasNew`, `obtenerSistemasNew`, `obtenerPlanesNew`, `obtenerTiposCursoNew`.
- `guardarCursoNew`, `actualizarCursoNew`, `subirCursoAv`, `aperturarCurso`, `aplazarCurso`, `actualizarFechasProgramacion`.
- `saveMatriculaNew`, `desmatricularUsuarioNew`.
- `despacharMatriculaCursoNuevo`, `decodificarPreguntas`.

#### `PlanesCapacitacionNewController.php`
- `obtenerTiposDeCursoNew`, `obtenerCursosPorPlanNew`, `obtenerPlanPCENew`, `obtenerPlanPCANew`.
- Helpers de programaciones, sistemas, áreas, dirigidos y clientes.

#### `SeguimientoMatriculasNewController.php`
- `getCursosSeguimientoNew`, `obtenerDetalleCursoNew`, `obtenerEstadoCursosAlumnoNew`.

#### `ReporteCapacitacionesController.php` (~27 KB)
- `cursos`, `personal`, `recordPersonal`, `general`, `formato`.
- Helpers: `cursosLocales`, `programacionesVigentes`, `responsablesMoodle`, `nombreCursoLocal`, `mapearPersonales`, `toTimestamp`.

#### `CatalogoCapacitacionController.php`
- `index`, `sistemas`, `areas`, `tipos`, `planes`, `dirigidos`, `areasPorSistema`.

#### `MailController.php`
- `send`, `enviarRecordatorioCurso`, `enviarRecordatorioCursos`.

#### `Concerns\ReporteFormatoHelpers.php`
- Trait con helpers de formato: `obtenerValorColumna`, `obtenerDniColumna`, `filtrarAprobados`, `mapearCategoriaCurso`.

### 9.3 Controladores obsoletos o de respaldo

- `FileController_copy.php` (~83 KB): copia antigua de `FileController`.
- `CapacitacionController-backup.php` (~158 KB): respaldo del controlador de capacitación.

No están referenciados en rutas. Se conservan como respaldo histórico; **no deben usarse ni modificarse sin confirmar**.

---

## 10. Modelos y dominio

### 10.1 Modelos principales

| Modelo | Tabla | PK | Conexión | Uso |
|---|---|---|---|---|
| `User` | `sw_usuarios` | `codigo` | default (`sqlsrv`) | Autenticación |
| `Permisos` | — | — | default | Árbol de menús/permisos por rol |
| `Personal` | `si_solm.dbo.PERSONAL` | `CODI_PERS` | default (`sqlsrv`) | Personal del ERP legado |
| `Cargo` | `sw_cargos` | `codigo` | default | Cargos |
| `Folio` | `sw_folios` | `codigo` | default | Folios documentarios |
| `FileControl` | — | — | default | Soporte de file control |
| `Consulta` | — | — | default | Consultas |
| `Reporte` | — | — | default | Reportes |
| `Cursos` | `sw_cursos` | `codigo` | default | Cursos legado |
| `CursoNew` | `sw_cursos_new` | `CODIGO` | `sqlsrv` | Cursos nuevo |
| `CursoProgramacion` | `sw_cursos_programacion` | `codigo` | `sqlsrv` | Programaciones |
| `Matricula` | `sw_matriculas` | `codigo` | default | Matrículas |
| `CapacitacionAreas` | `sw_capacitacion_areas` | `codigo` | `sqlsrv` | Áreas |
| `CapacitacionTipoCurso` | `sw_capacitacion_tipo_curso` | `codigo` | `sqlsrv` | Tipos de curso |
| `CapacitacionReporteHistorial` | `sw_capacitacion_reportes_historial` | — | default | Historial de reportes |
| `ExamenCurso` | `sw_cursos_examen` | `codigo` | `sqlsrv` | Examen por curso |
| `ExamenConfiguracion2026` | `sw_examen_configuracion_2026` | `codigo` | `sqlsrv` | Configuración de examen |
| `ExamenPregunta2026` | `sw_examen_preguntas_2026` | `codigo` | `sqlsrv` | Preguntas |
| `ExamenesModel` | — | — | `mysql_grupoihb` | Exámenes Moodle |
| `DjGeneradaPeriodo` | `sw_dj_generada_x_periodo` | `codigo` | `sqlsrv` | DJ generadas por periodo |
| `DerechoHabiente` | `sw_MIGRA_DERECHO_HABIENTE` | `CODI_DERE_HABI` | default | Derechohabientes migrados |
| `Telefono` | `sw_MIGRA_TELEFONO` | — | default | Teléfonos migrados |
| `MigraPersonal` | `sw_MIGRA_PERSONAL` | `CODI_PERS` | default | Personal migrado |
| `NotificacionMatricula` | `sw_notificaciones_matriculas` | `codigo` | default | Notificaciones de matrícula |
| `NotificacionModel` | — | — | default | Notificaciones |
| `ExamenPregunta2026` | `sw_examen_preguntas_2026` | `codigo` | `sqlsrv` | Preguntas del examen 2026 |

### 10.2 Relaciones relevantes

- `Personal` `hasMany` `Telefono` y `DerechoHabiente` por `CODI_PERS`.
- `Cursos` `hasOne` `ExamenCurso` por `cod_cursos`; `hasMany` `CursoProgramacion`; `belongsTo` `CapacitacionTipoCurso` y `CapacitacionAreas`.
- `Matricula` `belongsTo` `CursoProgramacion`, `Cursos`, `Personal`.
- `Matricula` define estados: `MATRICULADO`, `EN_PROGRESO`, `COMPLETADO`, `APROBADO`, `REPROBADO`, `CANCELADO`.

### 10.3 Notas sobre el modelo de datos

- La tabla `PERSONAL` es **legada del ERP** (`si_solm.dbo.PERSONAL`) y tiene más de 180 columnas. Es la fuente maestra del personal.
- La aplicación mantiene tablas espejo/migración `sw_MIGRA_*` y tablas `DJ2026_*` para la declaración jurada.
- `sw_cursos` (legado) y `sw_cursos_new` (nuevo) conviven; varias vistas y endpoints usan una u otra.
- `sw_matriculas` une cursos, programaciones y personal.
- `sw_capacitacion_reportes_historial` guarda reportes generados y su estado.
- `sw_folios` y `sw_folios_detalles` manejan la vigencia documentaria; `sw_folio_encargado` asigna responsables.

---

## 11. Base de datos

### 11.1 Conexiones configuradas

Definidas en `config/database.php`:

| Conexión | Driver | Base | Host por defecto | Uso |
|---|---|---|---|---|
| `sqlsrv` (default) | `sqlsrv` | `sisolm_web` | `192.168.10.21:1433` | Base principal |
| `sqlsrv_prueba1` | `sqlsrv` | `reclusol` | `192.168.10.21:1433` | Base auxiliar / pruebas; usada por `FileController` |
| `sqlsrv_controlclientes` | `sqlsrv` | `CONTROLCLIENTES2018` | `192.168.10.20:1433` | Clientes / control |
| `mysql_grupoihb` | `mysql` | `grupoihb_see` | configurable | Moodle |

También existen configuraciones estándar `sqlite`, `mysql`, `pgsql` sin uso real en la app.

### 11.2 Particularidades

- `AppServiceProvider` redefine el conector `db.connector.sqlsrv` para **quitar `PDO::ATTR_STRINGIFY_FETCHES`**, porque los drivers antiguos de SQL Server no lo soportan.
- `AppServiceProvider::boot()` fuerza `TMPDIR`, `TEMP` y `TMP` a `base_path('temp')`, creando la carpeta si no existe. Esto evita errores de PhpSpreadsheet, reportes y subidas.
- La conexión `sqlsrv` usa `encrypt=yes` y `trust_server_certificate=true` por defecto.
- `config/database.php` contiene **credenciales hardcodeadas como valores por defecto** (usuario, contraseña, host). Es un riesgo de seguridad: deben eliminarse y depender solo de variables de entorno.

### 11.3 Bases lógicas y cruces

- `sisolm_web`: tablas propias de la aplicación (`sw_*`, `jobs`, etc.).
- `si_solm`: ERP legado. La app lo consulta con nombres de tres partes (`si_solm.dbo.PERSONAL`, `si_solm.dbo.CARGOS`, etc.) sobre la misma conexión SQL Server.
- `reclusol`: usada por `FileController` (`DB::connection('sqlsrv_prueba1')`) para algunas consultas.
- `CONTROLCLIENTES2018`: usada en una consulta específica.
- `grupoihb_see` (MySQL): base de Moodle con tablas `mdl_*` y stored procedures `SP_*`.

### 11.4 Migraciones

Migraciones relevantes (no cubren todo el esquema; gran parte del esquema se administra fuera de Laravel):

- `create_users_table`, `create_password_reset_tokens_table`, `create_failed_jobs_table`, `create_personal_access_tokens_table`.
- `create_folio_encargado_table`, `add_habilitado_to_sw_folio_encargado_table`.
- `create_jobs_table`.
- `fix_periodicidad_values_in_sw_cursos`.
- `drop_proyeccion_anios_from_sw_cursos`.
- `rename_cod_cursos_to_cod_curso_in_sw_cursos_programacion`.
- `create_memo_recordatorios_table`.
- `add_sucursal_to_sw_cursos`.
- `add_tipo_archivo_to_sw_capacitacion_reportes_historial`.
- `add_preguntas_json_to_sw_cursos_examen_table`.
- `add_categoria_to_sw_cursos_table`.
- `create_matricula_masiva_jobs_table`.

### 11.5 Tablas por dominio

#### Seguridad y permisos
`sw_usuarios`, `sw_roles`, `sw_menus`, `sw_submenus`, `sw_submenus_opciones`, `sw_roles_permisos`, `sw_roles_funcionalidades`, `sw_funcionalidades`, `sw_permisos_usuario_sucursal`, `sw_permisos_usuario_submenu_opcion`, `sw_usuarios_permisos`.

#### File control / legajos / folios
`sw_folios`, `sw_folios_detalles`, `sw_folios_categoria`, `sw_folio_encargado`, `sw_legajos`, `sw_legajos_detalles`, `sw_cargos`, `sw_clientes`, `sw_solicitud_cargo_comercial`, `sw_curso_sucursales`, `sw_cliente_curso`.

#### Declaración Jurada
`sw_dj_periodos`, `sw_dj_periodo_personal`, `sw_dj_generada_x_periodo`, `sw_dj_excepciones_edad`, `sw_dj_notificaciones_act`, `sw_permisos_excepcion_edad`, `sw_log_migracion_huella_firma`, `sw_MIGRA_PERSONAL`, `sw_MIGRA_DERECHO_HABIENTE`, `sw_MIGRA_TELEFONO`, `sw_MIGRA_EMPRESA`, `sw_MIGRA_SISO_SUCURSAL`, `sw_MIGRA_REDO_AREA`.

Tablas legadas de DJ: `si_solm.dbo.DJ2026_PERSONAL`, `DJ2026_DERECHO_HABIENTE`, `DJ2026_OCUPACIONES_PER`, `DJ2026_BACKUP_PERSONAL`.

#### Capacitación
`sw_cursos`, `sw_cursos_new`, `sw_cursos_programacion`, `sw_matriculas`, `sw_cursos_examen`, `sw_examen_configuracion_2026`, `sw_examen_preguntas_2026`, `sw_capacitacion_areas`, `sw_capacitacion_tipo_curso`, `sw_cursos_area`, `sw_cursos_dirigido`, `sw_cursos_plan`, `sw_cursos_sistema`, `sw_cursos_tipo`, `sw_curso_sucursales`, `sw_cliente_curso`, `sw_capacitacion_reportes_historial`, `sw_notificaciones_matriculas`, `SW_MEMO_RECORDATORIOS`, `SW_MEMO_RECORDATORIOS_CURSOS`.

#### Moodle
`mdl_course`, `mdl_user`, `mdl_quiz`, `mdl_question`, `mdl_question_answers`, `mdl_question_attempts`, `mdl_question_attempt_steps`, `mdl_question_usages`, `mdl_quiz_attempts`, `mdl_user_enrolments`, `mdl_enrol`, `mdl_role_assignments`, `mdl_context`, `mdl_course_modules`, `mdl_course_sections`, `mdl_modules`, `mdl_files`, `mdl_label`.

#### ERP legado (`si_solm.dbo.*`)
`PERSONAL`, `CARGOS`, `CLIENTE_PROVEEDOR`, `EMPRESA`, `SISO_SUCURSAL`, `UNID_OPER`, `CONTRATOS_PERSONAL`, `VACACIONES`, `VACACIONES_CRONOGRAMA`, `DERECHO_HABIENTE`, `TELEFONO`, `ADMI_DEPARTAMENTO`, `ADMI_PROVINCIA`, `ADMI_DISTRITO`, `ADMI_PAIS`, `ADMI_ESTADO_CIVIL`, `ADMI_TIPO_PERSONAL`, `TIPO_DOCUMENTO`, `TIPO_ZONA`, `SISTEMA_PENSIONES`, `BANCOS`, `REDO_CARNET_CATEGORIA`, `REDO_CERTIFICADO`, `REDO_REQUISITOS`, `SUNAT_CARRERAS`, `SUNAT_IEDUCATIVA`, `SUNAT_NIVEL_EDUCATIVO`, `OPER_ASISTENCIA_CAB`, `OPER_ASITENCIA_DET`, `F_VER_ESCANEO_DNI`, `SipCartaElectronica`, `VALORES_UNITARIOS_ANUALES`, `USUARIOS`, `sw_roles`.

#### Infraestructura Laravel
`jobs`, `failed_jobs`, `migrations`, `password_reset_tokens`, `personal_access_tokens`, `matricula_masiva_jobs`.

> **Nota:** `INSTRUCCIONES_PRODUCCION.md` menciona `sw_jobs`, pero la migración `2026_05_11_115654_create_jobs_table.php` y `config/queue.php` apuntan a `jobs`. Verificar el nombre real de la tabla en cada entorno antes de monitorear la cola.

---

## 12. Stored procedures

La app delega listados y reportes pesados a SPs de SQL Server. Los más usados:

### 12.1 Folios y reportes documentarios

- `SW_LISTAR_FOLIOS_PENDIENTES_CONSULTAS`
- `SW_LISTAR_FOLIOS_VIGENTES`
- `SW_LISTAR_FOLIOS_PROXIMOS_VENCER_CONSULTAS`
- `SW_LISTAR_FOLIOS`
- `SW_LISTAR_FOLIOS_COMERCIAL`
- `SW_LISTAR_FOLIOS_X_LEGAJO`
- `SW_LISTAR_FOLIOS_X_PERSONAL`
- `SW_LISTAR_DATOS_FOLIO_X_PERSONA`
- `SW_REPORTE_FOLIOS_PENDIENTES_X_SUCURSAL`
- `SW_REPORTE_FOLIOS_PENDIENTES_REGISTRO`
- `SW_REPORTE_FOLIOS_POR_VENCER`
- `SW_REPORTE_FOLIOS_POR_VENCER_X_CLIENTE`
- `SW_RUTA_ARCHIVO_FOLIO`

### 12.2 Personal y legajos

- `SW_LISTAR_PERSONAL_X_SUCURSAL`
- `SW_LISTAR_PERSONAL_X_SUCURSAL_LEGAJO_PDF`
- `SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_CARGA_DE_ARCHIVOS`
- `SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_PRUEBA`
- `SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_REPORTE`
- `SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_V3`
- `SW_LISTAR_PERSONAL_DJ`
- `SW_LISTAR_PERSONAL_DJ_2026`
- `SW_LISTAR_PERSONAL_DJ_2026_GESTION`
- `SW_LISTAR_PERSONAL_DJ_MIGRACION_ETAPA2_VIGENTES_DNI`
- `SW_LISTAR_PERSONAL_DJ_MIGRACION_V2`
- `SW_LISTAR_PERSONAL_DJ_V2`
- `SW_LISTAR_PERSONAL_CORTE_DJ`
- `SW_LISTAR_REPORTE_PERSONAL_DJ_2026_V4`
- `SW_REPORTE_LISTAR_PERSONAL_SIN_MIGRACION`
- `SW_REPORTE_LISTAR_PERSONAL_SIN_MIGRACION_2_ETAPA`
- `SW_REPORTE_LISTAR_AVANCE_DJ`
- `SW_CONTAR_PERSONAL`
- `SW_SUCURSAL_X_PERSONA`
- `SW_DOCS_PER_VIEW`
- `SW_LISTAR_LEGAJOS_X_PERSONA`
- `SW_LISTAR_LEGAJOS_X_CLIENTE_X_CARGO`
- `SW_LISTAR_COINCIDENCIAS_LEGAJOS`
- `SW_LISTAR_COINCIDENCIAS_LEGAJOS_GENERAL`
- `SW_INSERTAR_LEGAJO_MAIN`
- `SW_INSERTAR_LEGAJO_DETALLE`
- `UF_LISTAR_PERSONAL_LEGAJOS`
- `UF_LISTAR_ESTADO_LEGAJOS`

### 12.3 Cargos, clientes y catálogos

- `SW_LISTAR_CARGOS_X_CLIENTES_LEGAJOS`
- `SW_LISTAR_CARGO_GESTION_DE_CARGOS`
- `SW_LISTAR_CLIENTES`
- `SW_LISTAR_CLIENTES_LEGAJOS`
- `SW_INSERTAR_CARGO`
- `SW_LISTAR_AREAS`
- `SW_LISTAR_AREAS_POR_SISTEMA`
- `SW_LISTAR_GRUPO`
- `SW_LISTAR_GRUPO_ID`
- `SW_LISTAR_POSICION`
- `SW_LISTAR_SUCURSALES`
- `SW_LISTAR_PERMISOS`
- `SW_LISTAR_PERIODOS`
- `SW_LISTAR_NOTIFICACIONES`
- `SW_SAVE_SOLICITUD`
- `SW_SISOLMAR_REPORTES`
- `USP_LISTAR_SUCURSALES_X_CLIENTE`
- `SP_LISTAR_JEFATURAS_AREAS`

### 12.4 Reportes de carnet y certificados

- `USP_Redo_CarnetReporte`
- `USP_Redo_CertificadosReporte`
- `USP_Redo_CertificadosReporte_2026`

### 12.5 Moodle (MySQL)

- `SP_CREAR_CURSO`
- `SP_OBTENER_CURSOS`
- `SP_OBTENER_CURSOS_POR_USUARIO`
- `SP_MEMOS_*` (familia de SPs de memos)

> Los SPs no están versionados en el repositorio: viven en las bases de datos. Cualquier cambio debe coordinarse con el DBA y probarse contra los datos reales.

---

## 13. Servicios y helpers

### 13.1 `App\Services\DjService`
- `guardarDeclaracionJurada(array $data)`: guarda una DJ.
- `splitName(string $fullName)`: separa nombres/apellidos.
- `saveTelefonos($codiPers, array $data)`: guarda teléfonos.

### 13.2 `App\Services\FirmaService`
- `toUrl(?string $rutaFirma): ?string`: normaliza una ruta de firma a URL pública de Biblioteca Gráfica (`http://190.116.178.163/Biblioteca_Grafica/FIRMAS/PERSONAL/{archivo}`).

### 13.3 `App\Services\MoodleCursoService`
Encapsula la integración con Moodle:
- `crearCurso(...)`
- `actualizarFechasCurso(int $courseId, int $inicio, int $fin): bool`
- `actualizarFechasMatriculas(int $courseId, int $fin, ?int $inicio = null): bool`
- `subirPortada(int $courseId, ?UploadedFile $file): bool`
- `subirAfiche(int $courseId, ?UploadedFile $file): bool`
- `sincronizarPreguntas(...)`
- `seleccionarPreguntas(...)`, `transformarPreguntas(...)`
- `subirArchivoBorrador(UploadedFile $file): ?int`
- `getOrCreateMoodleUser(?string $codResponsable): ?int`

### 13.4 `App\Services\PdfService`
- `generarPdf(array $data)`: generación de PDF con DomPDF/Snappy.

### 13.5 Helpers (autoload `files`)

- `App\Helpers\PdfHelper::descargarImagenesLegajo(array $urls): array`: descarga imágenes de legajo a `public/temp_legajos/{carpeta}/` para incrustarlas en PDFs.
- `App\Helpers\ImagenHelper`: utilidades de imagen (complementa la descarga/conversión).

---

## 14. Colas, jobs, eventos y broadcasting

### 14.1 Configuración

- `QUEUE_CONNECTION=database` en `.env` (desarrollo).
- `QUEUE_CONNECTION=sync` en `.env.prod` (producción actual). **Esto desactiva el procesamiento asíncrono**; con `sync`, los jobs se ejecutan en la misma petición y puede haber timeouts.
- Tabla de jobs: `jobs` (migración `create_jobs_table`).
- Tabla de fallos: `failed_jobs`.
- Tabla de estado de matrícula masiva: `matricula_masiva_jobs`.

### 14.2 Jobs

#### `DispatchMatriculaBatchJob`
- Implementa `ShouldQueue`.
- Constructor: `__construct(int $cursoCodigo, string $programacionCodigo, array $personalIds, int $usuarioId)`.
- Divide/despacha lotes de matrícula.

#### `MatriculaMasivaJob`
- Implementa `ShouldQueue`.
- Procesa la matrícula masiva y envía resumen por correo (`MatriculaMasivaResumenMail`).
- Notifica progreso/finalización por eventos.

#### `EnviarCorreosBienvenidaJob`
- Implementa `ShouldQueue`.
- `public int $tries = 3`.
- Envía correos de bienvenida con `Mail::to($email)->queue(...)`.

### 14.3 Eventos y canales

- `App\Events\MatriculaMasivaProgreso`
- `App\Events\MatriculaMasivaFinalizada`
- Canal privado: `PrivateChannel("usuario.{id}")`.
- `routes/channels.php` autoriza `usuario.{id}` comparando `$user->codigo` con el `id`.

### 14.4 Broadcasting

- `BROADCAST_DRIVER=log` en `.env` y `.env.prod`.
- Existe configuración de Pusher (`PUSHER_*`, `VITE_PUSHER_*`) y `laravel-echo`/`pusher-js` en frontend.
- Con `log`, los eventos no se transmiten en tiempo real; solo se registran en logs. Para tiempo real hay que cambiar a `pusher` y configurar credenciales.

### 14.5 Operación del worker en producción

`INSTRUCCIONES_PRODUCCION.md` indica:

- El worker debe correr **en el servidor web**, no en el cliente ni en el servidor de BD.
- Programar `iniciar_queue_worker.bat` en el Programador de tareas de Windows al iniciar el sistema.
- Comando base: `php artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-jobs=1000`.
- Monitoreo: `php artisan tinker --execute="echo 'Jobs en cola: ' . DB::table('sw_jobs')->count();"` (ojo: la tabla real parece ser `jobs`).
- Si el worker no corre: matrículas no se guardan, correos no salen, jobs se acumulan.

---

## 15. Comandos Artisan y scheduler

### 15.1 Comandos disponibles

| Comando | Descripción |
|---|---|
| `capacitacion:clonar-vencidos` | Clona cursos periódicos vencidos y abre ventana de 1 mes. |
| `app:enviar-alertas-caducidad` | Envía correos de documentos por vencer a cada personal. |
| `capacitacion:enviar-recordatorios-curso` | Envía recordatorios a matriculados que no iniciaron cursos. Acepta `--dry-run`. |
| `capacitacion:limpiar-memos-recordatorios` | Elimina registros de `SW_MEMO_RECORDATORIOS` y `SW_MEMO_RECORDATORIOS_CURSOS`. |
| `capacitacion:procesar-cursos` | Procesa cursos periódicos y no periódicos, cierra programaciones y genera nuevas. |
| `capacitacion:procesar-cursos-periodicos` | Procesa cursos periódicos vencidos y genera programaciones pendientes. |

### 15.2 Scheduler (`app/Console/Kernel.php`)

| Tarea | Frecuencia | Estado |
|---|---|---|
| `app:enviar-alertas-caducidad` | Diario 06:00 | Activa |
| `capacitacion:procesar-cursos` | Diario 12:30 | Activa |
| `capacitacion:enviar-recordatorios-curso` | Lunes 08:45 | Comentada |

Todas usan `withoutOverlapping()` y logging de éxito/fallo.

### 15.3 Ejecución programada en Windows

Configurar una tarea que ejecute cada minuto:

```bat
php artisan schedule:run
```

O usar el Programador de tareas de Windows apuntando al PHP del servidor.

---

## 16. Correos y notificaciones

### 16.1 Mailables (`app/Mail`)

| Mailable | Uso |
|---|---|
| `AlertaCaducidadMail` | Documentos por vencer |
| `BienvenidaMatriculaMail` | Bienvenida a matrícula |
| `BienvenidaSipMail` | Bienvenida SIP |
| `DjAprobadaMail` | DJ aprobada |
| `ExcepcionEdadMail` | Excepción de edad |
| `MatriculaMasivaResumenMail` | Resumen de matrícula masiva |
| `MatriculaNotificacion` | Notificación de matrícula |
| `MemoMail` | Memorandos de capacitación |
| `RecordatorioCursoPendienteMail` | Recordatorio individual |
| `RecordatorioCursosPendientesMail` | Recordatorio masivo |

### 16.2 Plantillas (`resources/views/emails`)

- `alerta-caducidad.blade.php`
- `bienvenida-curso.blade.php`
- `bienvenida-sip.blade.php`
- `dj_aprobada.blade.php`
- `excepcion_edad.blade.php`
- `matricula-masiva-resumen.blade.php`
- `matricula-notificacion.blade.php`
- `memorandum-1-cursos.blade.php`, `memorandum-2-cursos.blade.php`, `memorandum-3-cursos.blade.php`, `memorandum-body.blade.php`
- `recordatorio-curso.blade.php`, `recordatorio-pendientes.blade.php`

### 16.3 Notificaciones en aplicación

- Tabla `sw_notificaciones_matriculas`.
- Endpoints en `NotificacionController` y `FileController@getNotificaciones`.
- Pop-ups de notificaciones al iniciar sesión (se muestran una vez por login).
- Alertas de folios por vencer y pendientes de etapa 2.
- Notificaciones de demandas administrativas.

### 16.4 Variables de correo

Se usan `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `CAPA_COORD_EMAIL`. Los valores reales viven en `.env`/`.env.prod` y **no deben copiarse a documentación ni a Git**.

---

## 17. Middleware

### 17.1 Middleware global

- `TrustProxies`
- `HandleCors`
- `PreventRequestsDuringMaintenance`
- `ValidatePostSize`
- `TrimStrings`
- `ConvertEmptyStringsToNull`

`TrustHosts` y `RefreshPermissions` están comentados.

### 17.2 Grupo `web`

1. `EncryptCookies`
2. `AddQueuedCookiesToResponse`
3. `StartSession`
4. `ShareErrorsFromSession`
5. `VerifyCsrfToken`
6. `SubstituteBindings`
7. `CargarMenus` (permisos + funcionalidades)

### 17.3 Grupo `api`

1. `ThrottleRequests:api`
2. `SubstituteBindings`

Sanctum `EnsureFrontendRequestsAreStateful` está comentado.

### 17.4 Alias

`auth`, `auth.basic`, `auth.session`, `cache.headers`, `can`, `guest`, `password.confirm`, `precognitive`, `signed`, `throttle`, `verified`.

### 17.5 Middleware propios

- `CargarMenus`: carga permisos y funcionalidades del rol autenticado; comparte con vistas y sesión.
- `RefreshPermissions`: recalcula permisos en cada request; **no está activo**.
- `Authenticate`, `RedirectIfAuthenticated`, `EncryptCookies`, `VerifyCsrfToken`, `TrimStrings`, `TrustProxies`, `PreventRequestsDuringMaintenance`, `ValidateSignature`, `TrustHosts`.

---

## 18. Frontend

### 18.1 Vistas Blade

- `layouts/base.blade.php`, `layouts/vertical.blade.php`.
- `layouts/shared/`: `head-css`, `topbar`, `sidenav`, `footer`, `footer-scripts`, `main`, `page-title`, `title-meta`.
- `index.blade.php`: página de inicio/dashboard.
- `login.blade.php`.
- `file_control/`: legajos, folios, cargos, dashboard, reportes, DJ, PDFs.
- `capacitacion/`: gestión de cursos (legado y nuevo), matrículas, seguimiento, planes, reportes, exámenes, parciales de formularios.
- `maestros/usuarios.blade.php`.
- `emails/`: plantillas de correo.
- `enterprise/documentos.blade.php`.
- `errors/403.blade.php`, `errors/documento_no_encontrado.blade.php`.
- `apps/`, `forms/`, `ui/`: vistas de plantilla de UI (calendario, contactos, galería, componentes).

### 18.2 Assets y Vite

`vite.config.js` define como entradas:

- SCSS: `resources/scss/app.scss`.
- CSS: `resources/css/app.css`, `icons.css`, `styles.css`.
- CSS de librerías: jsVectorMap, GLightbox, Quill, Tabulator, SweetAlert2.
- JS: `resources/js/app.js`, páginas (`dashboard`, `app-calendar`, `gallery`, `charts-apex`, `maps-vector`, `form-editor`, `file-upload`, `form-inputmask`).
- JS de negocio: `resources/js/functions/**` (file control, capacitación, reportes, chargefile).
- `refresh: true` para hot reload.
- `base: "./"`.

### 18.3 JavaScript por módulo

- File Control: `chargeFile.js`, `legajo.js`, `legajo_comercial.js`, `legajos_pdf.js`, `folios.js`, `cargo.js`, `search_legajos.js`, `consultas.js`, `reportes.js`, `notifications.js`, `actualizar_dj.js`, `gestion_dj.js`, `nueva_dj.js`, `carga_escaneo_dj.js`, `reporte_personal.js`, `modal_reporte.js`, `dj_pdf.js`.
- Capacitación: `gestion_cursos.js` (legado), `gestion_cursos_new.js`, `gestion_cursos_new_modal.js`, `gestion_cursos_file.js`, `gestion_matricula.js`, `gestion_programacion.js`, `consulta_matriculas.js`, `seguimiento_matriculas.js`, `seguimiento_matriculas_new.js`, `planes_capacitaciones.js`, `planes_capacitaciones_new.js`, `reportes_capacitaciones.js`, `cursos_externos.js`, `cursos_sucamec.js`, `exportar_examenes.js`, `trabajos_fallidos.js`.
- Reportes: `reportes/carnet.js`, `certificados.js`, `folios-*.js`, `vigencia-documentos.js`.
- Chargefile: `reporteAvances.js`, `reporteAvancesExcel.js`, `reporteAvancesPDF.js`.

### 18.4 Estilos

- Tailwind configurado en `tailwind.config.js` (tema, colores, tipografía, iconos).
- SCSS propio en `resources/scss` con parciales de botones, cards, charts, forms, fullcalendar, helpers, simplebar, estructura general, sidenav y topbar.
- CSS adicional: `modal_dj.css`, `split_improvements.css`, `styles_old.css` (legado).

### 18.5 Librerías de UI destacadas

Tabulator, DataTables, Grid.js, SweetAlert2, Dropzone, Quill, Tagify, Select2 (si está incluido por plantilla), Flatpickr/vanilla-datepicker, FullCalendar, ApexCharts, Chart.js, jsVectorMap, GLightbox, SimpleBar, Swiper, Preline, Alpine.

---

## 19. Integraciones externas

### 19.1 Moodle

**Acceso a base de datos**
- Conexión `mysql_grupoihb` (MySQL).
- Base `grupoihb_see` (Moodle).
- Tablas `mdl_*` y stored procedures `SP_*`.
- Es la conexión más usada en el código (≈95 referencias), especialmente en capacitación, exámenes y seguimiento.

**Acceso HTTP (Web Services)**
- Configuración en `config/services.php`:
  - `moodle.url` ← `MOODLE_URL`
  - `moodle.ws_token` ← `MOODLE_WS_TOKEN`
  - `moodle.admin_id` ← `MOODLE_ADMIN_ID`
  - `moodle.dataroot` ← `MOODLE_DATAROOT`
- Funciones:
  - Crear curso.
  - Actualizar fechas de curso y matrículas.
  - Subir portada y afiche.
  - Subir archivos a borrador.
  - Crear/obtener usuario Moodle.
  - Sincronizar preguntas de examen.
- Implementación principal: `MoodleCursoService` y métodos privados de `CapacitacionController`.

**Riesgos**
- Dependencia fuerte de la estructura interna de Moodle (`mdl_*`), no solo del Web Service.
- Cambios de versión de Moodle pueden romper consultas y SPs.
- `MOODLE_WS_TOKEN` es un secreto: no debe versionarse.

### 19.2 Biblioteca Gráfica / servidor de archivos

**HTTP**
- Base: `http://190.116.178.163/Biblioteca_Grafica`.
- Carpetas:
  - `Fotos/{codiPers}.jpg`
  - `FIRMAS/PERSONAL/{codPersonal}.jpg`
  - `HUELLAS_DIGITALES/PERSONAL/{codPersonal}.jpg`
  - `DOCUMENTOS_PERS/DJ_2026/firmas/`
  - `DOCUMENTOS_PERS/DJ_2026/huellas/`

**UNC**
- `\\192.168.10.2\Biblioteca_Grafica` (fotos, firmas, huellas, DNI).
- `\\192.168.10.5\Extranet_2024\apps\sisolmar\storage\app\dj\2026\`
- `\\192.168.10.5\Extranet_2024\apps\sisolmar\storage\app\dni\anverso\`
- `\\192.168.10.5\Extranet_2024\apps\sisolmar\storage\app\dni\reverso\`
- `\\192.168.10.5\Extranet_2024\storage_app\sisolmar\DJ`
- `\\192.168.10.5:86\sisolmar\DATA_SISOLMAR\`

**Uso**
- `BiometricoController` busca archivos y los devuelve en base64.
- `DjController` sube/consulta fotos y DNI.
- `ReportePersonalController` verifica existencia de documentos por URL.
- `PdfHelper` descarga imágenes para incrustarlas en PDFs de legajo.
- `FirmaService` normaliza rutas de firma a URL pública.

**Consideraciones**
- Requiere acceso de red y permisos de lectura/escritura desde el servidor web.
- Las rutas UNC y las URLs HTTP están **hardcodeadas** en varios controladores; un cambio de servidor implica buscar y reemplazar en varios archivos.

### 19.3 ERP legado `si_solm`

- La app no escribe directamente en las tablas del ERP salvo casos concretos de migración/sincronización (`DJ2026_*`, `PERSONAL`, etc.).
- La mayoría de lecturas usan stored procedures y SQL con nombres de tres partes.
- Cambios de esquema del ERP impactan directamente la app.

---

## 20. Variables de entorno

> Solo se listan **nombres**. Los valores reales están en `.env`, `.env.dev`, `.env.prod` y no deben documentarse ni commitearse.

### 20.1 Aplicación

`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `VITE_URL`, `APP_LOCALE` (si se usa), `LOG_CHANNEL`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL`.

### 20.2 Base de datos principal

`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ENCRYPT`, `DB_TRUST_SERVER_CERTIFICATE`.

### 20.3 Base de datos auxiliar / pruebas

`DB_HOST_PRUEBA1`, `DB_PORT_PRUEBA1`, `DB_DATABASE_PRUEBA1`, `DB_USERNAME_PRUEBA1`, `DB_PASSWORD_PRUEBA1`.

### 20.4 Base de datos de control de clientes

`DB_HOST_CONTROL`, `DB_PORT_CONTROL`, `DB_DATABASE_CONTROL`, `DB_USERNAME_CONTROL`, `DB_PASSWORD_CONTROL`.

### 20.5 Moodle / MySQL

`DB_CONNECTION_MYSQL`, `DB_HOST_MYSQL`, `DB_PORT_MYSQL`, `DB_DATABASE_MYSQL`, `DB_USERNAME_MYSQL`, `DB_PASSWORD_MYSQL`, `MOODLE_URL`, `MOODLE_WS_TOKEN`, `MOODLE_ADMIN_ID`, `MOODLE_DATAROOT`.

### 20.6 Correo

`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `CAPA_COORD_EMAIL`.

### 20.7 Colas, sesión, caché y archivos

`QUEUE_CONNECTION`, `SESSION_DRIVER`, `SESSION_LIFETIME`, `CACHE_DRIVER`, `FILESYSTEM_DISK`, `MEMCACHED_HOST`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`.

### 20.8 Broadcasting / Pusher

`BROADCAST_DRIVER`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`, `PUSHER_APP_CLUSTER`, `VITE_PUSHER_APP_KEY`, `VITE_PUSHER_HOST`, `VITE_PUSHER_PORT`, `VITE_PUSHER_SCHEME`, `VITE_PUSHER_APP_CLUSTER`.

### 20.9 Otros

`MASTER_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT`.

> **Seguridad:** `.env.prod` tiene actualmente `APP_DEBUG=true`. En producción debe ser `false` para no exponer stack traces ni rutas internas. También hay credenciales hardcodeadas en `config/database.php` como fallback; deben migrarse a variables de entorno.

---

## 21. Instalación y ejecución local

### 21.1 Requisitos

- PHP 8.1+ con extensiones:
  - `pdo_sqlsrv` / `sqlsrv` (SQL Server).
  - `pdo_mysql` (Moodle).
  - `mbstring`, `openssl`, `fileinfo`, `gd` (para imágenes/PDF), `zip`, `xml`, `curl`.
- Composer 2.
- Node.js 18+ y npm.
- Acceso de red a:
  - SQL Server `192.168.10.21` (y `192.168.10.20` si se usa control de clientes).
  - MySQL de Moodle.
  - Biblioteca Gráfica (`190.116.178.163` y rutas UNC).
- Extensiones de PHP para wkhtmltopdf/Snappy si se usa esa ruta de PDF.

### 21.2 Pasos

```bash
# 1. Dependencias backend
composer install

# 2. Dependencias frontend
npm install

# 3. Configuración
copy .env.example .env
php artisan key:generate

# 4. Configurar .env con las conexiones reales
#    (SQL Server, Moodle, correo, etc.)

# 5. Compilar assets
npm run build
# o para desarrollo:
npm run dev

# 6. Servir
php artisan serve
```

Abrir `http://127.0.0.1:8000`.

### 21.3 Scripts disponibles

`composer.json`:

```bash
composer dev   # concurrently: php artisan serve + npm run dev
```

`package.json`:

```bash
npm run dev    # Vite dev server
npm run build  # build de producción
```

### 21.4 Cola en local

Si `QUEUE_CONNECTION=database`:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Si se usa `sync`, los jobs se ejecutan inline (útil para pruebas, pero no para cargas masivas).

### 21.5 Scheduler en local

```bash
php artisan schedule:work
```

---

## 22. Despliegue en producción

### 22.1 Servidor

- Windows Server con IIS o Apache.
- Document root apuntando a `public/`.
- `public/web.config` ya existe para IIS.
- PHP configurado con extensiones SQL Server y MySQL.
- Acceso a las rutas UNC de la Biblioteca Gráfica.

### 22.2 Pasos de despliegue

1. Copiar/actualizar el código en el servidor.
2. `composer install --no-dev --optimize-autoloader`.
3. `npm ci && npm run build` (o copiar `public/build` ya compilado).
4. Configurar `.env` de producción.
5. `php artisan config:cache`, `route:cache`, `view:cache` (evaluar compatibilidad con rutas catch-all y closures).
6. `php artisan migrate --force` si hay migraciones nuevas.
7. Reiniciar el sitio en IIS.
8. Verificar queue worker y scheduler.

### 22.3 Queue worker

- Usar `iniciar_queue_worker.bat` en el Programador de tareas de Windows.
- Comando esperado:

```bat
php artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-jobs=1000
```

- El worker debe reiniciarse automáticamente si falla.
- Monitorear `jobs`/`failed_jobs`.

### 22.4 Scheduler

Crear tarea programada cada minuto:

```bat
php artisan schedule:run
```

### 22.5 Consideraciones específicas

- `QUEUE_CONNECTION=sync` en `.env.prod` desactiva la cola real. Si se usa matrícula masiva, cambiar a `database` y asegurar el worker.
- `APP_DEBUG=true` en `.env.prod` debe cambiarse a `false`.
- `MAIL_MAILER=smtp` con `MAIL_HOST=mailpit` en `.env.prod` parece una configuración de prueba; verificar el servidor de correo real.
- `BROADCAST_DRIVER=log` no entrega eventos en tiempo real.
- Si se usa `route:cache`, revisar que las rutas catch-all y closures no generen conflictos.

---

## 23. Convenciones y patrones del código

### 23.1 Nomenclatura

- Tablas propias: prefijo `sw_` (`sw_cursos`, `sw_folios`, `sw_matriculas`).
- Tablas de migración: `sw_MIGRA_*`.
- Tablas de DJ: `sw_dj_*` y `DJ2026_*`.
- Tablas del ERP: nombres en mayúsculas (`PERSONAL`, `CARGOS`, `EMPRESA`).
- Columnas del ERP: mayúsculas con guiones bajos (`CODI_PERS`, `NRO_DOCU_IDEN`).
- Columnas propias: minúsculas (`codigo`, `cod_curso`, `habilitado`).
- Rutas: español, kebab-case o snake_case (`get-personal`, `save_folio_persona`).
- Métodos de controlador: camelCase, en español (`obtenerCursos`, `saveMatricula`).

### 23.2 Patrones recurrentes

- **Respuesta JSON directa** con `response()->json([...])` y claves en español (`success`, `data`, `message`, `error`).
- **Consultas con `DB::select` / `DB::table`** en lugar de repositorios.
- **Stored procedures** para listados y reportes.
- **Validación con `Validator::make`** en algunos endpoints; otros confían en el frontend.
- **Transacciones** `DB::beginTransaction()` / `commit()` / `rollBack()` en operaciones de escritura críticas.
- **Logs** con `Log::info`, `Log::error`, `Log::warning`.
- **Manejo de errores** con `try/catch` y respuestas JSON con `success => false`.
- **Vistas Blade** con lógica JS inline y llamadas AJAX a `/api/*`.
- **Permisos** por rol/menú, no por políticas Laravel.

### 23.3 Estilo

- No hay una guía formal de estilo; Laravel Pint está disponible como dev dependency.
- Mezcla de español e inglés en nombres y comentarios.
- Comentarios explican reglas de negocio y a veces incluyen advertencias o autores.
- Uso intensivo de `Carbon` para fechas.

---

## 24. Problemas conocidos, riesgos y deuda técnica

### 24.1 Seguridad

1. **Credenciales hardcodeadas** en `config/database.php` como fallback (host, usuario, contraseña). Deben migrarse a `.env` y rotarse.
2. **`APP_DEBUG=true` en `.env.prod`**: expone stack traces, rutas y consultas.
3. **Rutas de prueba sin autenticación**:
   - `/preview-email-caducidad`
   - `/test-email-caducidad` (envía correo real)
4. **`/debug-permisos`** expone permisos de sesión (requiere auth, pero es información sensible).
5. **`/api/ver-dj-externo/{codPersonal}`** en `routes/api.php` permite consultar PDFs de DJ con token Sanctum; revisar si debe ser público o tener alcance limitado.
6. **Rate limiting**: el grupo `/api/*` interno no aplica el limiter `api`; solo `/dj/*` usa `dj_api` (90 000 req/min, muy permisivo).
7. **Catch-all de vistas**: `RoutingController@root` renderiza `view($first)` sin validar permisos para rutas de primer nivel.
8. **Tokens y secretos** (`MOODLE_WS_TOKEN`, `MASTER_PASSWORD`, `APP_KEY`, credenciales de correo) deben permanecer fuera del repositorio.

### 24.2 Arquitectura y mantenibilidad

1. **Controladores gigantes**: `CapacitacionController` (~250 KB), `DjController` (~218 KB), `FileController` (~98 KB). Dificultan pruebas y cambios seguros.
2. **Duplicación de lógica**: módulo de capacitación legado (`sw_cursos`) y nuevo (`sw_cursos_new`), con endpoints y JS duplicados.
3. **Archivos de respaldo** en el árbol de producción:
   - `FileController_copy.php`
   - `CapacitacionController-backup.php`
   - `gestion_cursos.js.backup`
   - `capacitacion-old/`
   - `styles_old.css`
4. **Vistas y JS con lógica de negocio** mezclada; difícil de testear.
5. **SQL crudo y SPs**: potente pero acoplado al esquema y sin versionado de SPs.
6. **Rutas declaradas sin método** (`procesarExamenConIA`, `guardarExamenIA`).
7. **Catch-all de vistas** puede renderizar plantillas no previstas si el permiso no se valida correctamente.
8. **Dependencia de rutas UNC y URLs hardcodeadas** de la Biblioteca Gráfica en varios archivos.

### 24.3 Operación

1. **`QUEUE_CONNECTION=sync` en `.env.prod`**: la matrícula masiva puede bloquear la petición o agotar el tiempo.
2. **Worker no garantizado**: si no corre, los jobs se acumulan y los correos no salen.
3. **Scheduler**: requiere tarea externa cada minuto; si no está configurada, no se ejecutan alertas ni procesamiento de cursos.
4. **`BROADCAST_DRIVER=log`**: no hay progreso en tiempo real.
5. **Inconsistencia `jobs` vs `sw_jobs`** entre migración/config y documentación de producción.
6. **Dependencia de red** a SQL Server, Moodle y Biblioteca Gráfica; fallas de red afectan la app.

### 24.4 Calidad y pruebas

1. **Pruebas mínimas**: solo `ExampleTest` de Feature y Unit. No hay cobertura de reglas de negocio.
2. **Sin tests de integración** contra SQL Server/Moodle.
3. **Sin pipeline CI/CD** visible.
4. **Sin análisis estático** configurado (PHPStan/Psalm).
5. **Migraciones incompletas**: gran parte del esquema se administra manualmente.
6. **Scripts sueltos** (`test_db.php`, `test_notificacion.php`) en la raíz.

### 24.5 Recomendaciones priorizadas

1. Rotar credenciales y eliminar fallbacks de `config/database.php`.
2. Poner `APP_DEBUG=false` en producción y proteger/eliminar rutas de prueba.
3. Cambiar `QUEUE_CONNECTION` a `database` en producción y garantizar el worker.
4. Configurar correctamente el scheduler.
5. Eliminar rutas `-ia` o implementar los métodos.
6. Mover lógica de negocio de controladores a servicios/acciones por módulo.
7. Unificar el módulo de capacitación (legado vs nuevo).
8. Añadir pruebas de Feature para login, permisos, matrícula y DJ.
9. Versionar los stored procedures y documentar sus contratos.
10. Externalizar rutas de Biblioteca Gráfica a configuración (`config/services.php` o `.env`).

---

## 25. Glosario de dominio

| Término | Significado |
|---|---|
| **SISOLMAR** | Sistema de Solmar / Grupo Solmar. |
| **DJ** | Declaración Jurada del personal. |
| **Legajo** | Expediente documentario de un trabajador. |
| **Folio** | Documento o requisito documentario con vigencia y caducidad. |
| **Folio vigente** | Folio habilitado y dentro de su fecha de validez. |
| **Folio pendiente** | Folio requerido que aún no se ha registrado o cargado. |
| **Personal** | Trabajador registrado en el ERP (`PERSONAL`). |
| **Cargo** | Puesto o función del personal. |
| **Cliente** | Empresa o cliente para el que presta servicio el personal. |
| **Sucursal** | Unidad operativa de la empresa. |
| **Programación** | Apertura o edición de un curso en una fecha/sucursal determinada. |
| **Matrícula** | Registro de un personal en un curso/programación. |
| **Curso periódico** | Curso que se repite y debe renovarse (clonación/reprogramación). |
| **PCE** | Plan de Capacitación Específico. |
| **PCA** | Plan de Capacitación Anual. |
| **Memo** | Memorando de capacitación enviado a jefaturas/personal. |
| **Moodle** | Plataforma LMS externa usada para cursos y exámenes. |
| **Biblioteca Gráfica** | Repositorio de fotos, firmas, huellas y documentos del personal. |
| **SI-SOLM** | ERP legado de Grupo Solmar (base `si_solm`). |
| **RENIEC** | Registro Nacional de Identificación y Estado Civil (validación de DNI). |
| **SUCAMEC** | Superintendencia Nacional de Control de Servicios de Seguridad, Armas, Municiones y Explosivos (cursos/certificados). |
| **Carnet** | Credencial de identificación del personal. |
| **Brevete** | Licencia de conducir. |
| **Huella / firma** | Datos biométricos usados en DJ y legajos. |
| **Etapa 2** | Fase de migración/regularización de DJ. |
| **Corte** | Fecha de corte para reportes de avance de DJ. |
| **Excepción de edad** | Autorización de RR. HH. para personal fuera del rango de edad estándar. |

---

## 26. Anexos

### 26.1 Resumen de volúmenes

| Métrica | Valor aproximado |
|---|---|
| Rutas en `routes/web.php` | 286 |
| Rutas en `routes/api.php` | 1 (+ endpoint Sanctum) |
| Controladores (sin backups) | 23 archivos |
| Métodos públicos de controlador | ~298 |
| Líneas de controladores | ~23 000 |
| Modelos | 26 archivos |
| Migraciones | 16 |
| Comandos Artisan de negocio | 6 |
| Jobs | 3 |
| Mailables | 10 |
| Eventos | 2 |
| Vistas Blade | ~120 |

### 26.2 Archivos clave para empezar a leer

1. `routes/web.php` — mapa completo de endpoints y vistas.
2. `app/Http/Controllers/RoutingController.php` — cómo se autoriza y resuelve una vista.
3. `app/Http/Middleware/CargarMenus.php` — carga de permisos y funcionalidades.
4. `app/Models/Permisos.php` — consulta de menús/submenús por rol.
5. `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — login y sesión.
6. `app/Http/Controllers/FileController.php` — file control, legajos, folios, PDFs.
7. `app/Http/Controllers/DjController.php` — declaración jurada.
8. `app/Http/Controllers/CapacitacionController.php` — capacitación legado.
9. `app/Http/Controllers/Api/NewCapacController.php` — capacitación nuevo.
10. `app/Services/MoodleCursoService.php` — integración Moodle.
11. `config/database.php` — conexiones.
12. `INSTRUCCIONES_PRODUCCION.md` — cola y despliegue.

### 26.3 Comandos útiles

```bash
# Rutas
php artisan route:list

# Limpiar cachés
php artisan optimize:clear

# Cola
php artisan queue:work --tries=3 --timeout=120
php artisan queue:failed
php artisan queue:retry all

# Scheduler
php artisan schedule:list
php artisan schedule:run

# Migraciones
php artisan migrate
php artisan migrate:status

# Frontend
npm run dev
npm run build
```

### 26.4 Checklist antes de modificar código

- [ ] ¿La ruta existe en `routes/web.php` y está dentro del grupo `auth`?
- [ ] ¿El permiso `modulo.vista` está en `sw_roles_permisos` para el rol correspondiente?
- [ ] ¿El cambio afecta la tabla `sw_*` o el ERP `si_solm.dbo.*`?
- [ ] ¿Hay un stored procedure involucrado? ¿Está versionado/documentado?
- [ ] ¿Se usa la conexión correcta (`sqlsrv`, `sqlsrv_prueba1`, `mysql_grupoihb`, `sqlsrv_controlclientes`)?
- [ ] ¿El cambio afecta la cola o el worker?
- [ ] ¿El cambio afecta archivos de Biblioteca Gráfica (URLs/UNC hardcodeadas)?
- [ ] ¿Hay vistas Blade y JS asociados que también deban actualizarse?
- [ ] ¿Se agregaron credenciales o secretos al código? No hacerlo.
- [ ] ¿Se probó con datos reales de personal/curso?

---

## 27. Nota final

Este documento describe el estado observado del repositorio. No reemplaza:

- La documentación funcional de negocio de Grupo Solmar.
- El diccionario de datos oficial de `sisolm_web` y `si_solm`.
- La documentación de los stored procedures (que vive en la base de datos).
- Las credenciales y configuraciones de cada entorno.

Si se detecta una discrepancia entre este documento y el código, **prevalece el código**; actualizar este archivo cuando se confirmen cambios estructurales.
