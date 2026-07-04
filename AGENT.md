# SENCE API Mock Server — AGENT.md

## 1. Project Overview

This is a **CodeIgniter 4 mock server** that emulates the two SENCE (Servicio Nacional de Capacitacion y Empleo) web services used by Moodle plugins for the Chilean government's training certification system.

**Stack**: CodeIgniter 4 (PHP 8+), SQLite3 database, no external libraries beyond framework defaults.

**What it does**: Accepts POST/GET requests from a Moodle SENCE plugin, validates parameters against SENCE's documented rules, persists data to SQLite, and returns responses in the exact format the real SENCE servers produce — HTML form POST redirects for RCE, JSON responses for SIC.

**Why**: SENCE only provides a production environment. There is no official test sandbox. This mock lets developers test the full integration loop locally without hitting real SENCE servers.

---

## 2. The Two SENCE Services Mocked

### 2.1 Service RCE — Registro de Asistencia (Session Registration)

**Protocol**: POST (form-urlencoded) → HTML redirect form (auto-submit POST)

SENCE RCE does **NOT return JSON**. It receives POST form data and responds with an HTML page containing a `<form>` that auto-submits via JavaScript to redirect the user back to Moodle.

#### Endpoint: Iniciar Sesion (Start Session)

| Aspect | Detail |
|---|---|
| **Route (test)** | `POST /rcetest/Registro/IniciarSesion` → `SenceRce::iniciarSesion` |
| **Route (prod)** | `POST /rce/Registro/IniciarSesion` → `SenceRce::iniciarSesion` |
| **Input format** | Standard POST form fields (form-urlencoded) |

**Required POST input fields** (`SenceRce.php:115`):
| Field | Type | Description |
|---|---|---|
| `RutOtec` | string | RUT of the OTEC (training provider), format `12345678-9` |
| `Token` | string | Authentication token provided by SENCE |
| `CodSence` | string | SENCE code |
| `CodigoCurso` | string | Course code (`-1` for test mode, else >= 7 chars) |
| `LineaCapacitacion` | int | Training line: 1=Programas Sociales, 3=Franquicia, 6=FPT |
| `RunAlumno` | string | Student RUT, e.g. `9445435-2` |
| `IdSesionAlumno` | string | Student session ID from Moodle |
| `UrlRetoma` | string | Valid URL to redirect on success |
| `UrlError` | string | Valid URL to redirect on failure |

**Success output** (POSTed to `UrlRetoma` via HTML form):
| Field | Value |
|---|---|
| `CodSence` | Echoed from input |
| `CodigoCurso` | Echoed from input |
| `IdSesionAlumno` | Echoed from input |
| `IdSesionSence` | Generated: `MOCK-SESS-` + 16 hex chars |
| `RunAlumno` | Echoed from input |
| `FechaHora` | Current datetime, e.g. `2024-01-01 12:00:00` |
| `ZonaHoraria` | `America/Santiago` |
| `LineaCapacitacion` | Echoed from input |

**Error output** (POSTed to `UrlError` via HTML form):
Same as success fields plus `GlosaError` = the error code string (e.g., `'200'`). If `IdSesionSence` existed in the original POST (for cierre), it is included too.

#### Endpoint: Cerrar Sesion (Close Session)

| Aspect | Detail |
|---|---|
| **Route (test)** | `POST /rcetest/Registro/CerrarSesion` → `SenceRce::cerrarSesion` |
| **Route (prod)** | `POST /rce/Registro/CerrarSesion` → `SenceRce::cerrarSesion` |
| **Input format** | Standard POST form fields |

**Required POST input fields**: Same as IniciarSesion, plus `IdSesionSence` (the session ID returned by the start call).

**Success output** (POSTed to `UrlRetoma`): Same as IniciarSesion but **without** `IdSesionSence`.

**Error output**: Same structure as IniciarSesion errors + `GlosaError`.

---

### 2.2 Service SIC — API Gestor Intermedio (Progress/Avance)

**Protocol**: JSON POST / JSON GET

SENCE SIC is a REST-like API. It receives raw JSON bodies and returns raw JSON responses.

#### Endpoint: Enviar Avance (Submit Progress)

| Aspect | Detail |
|---|---|
| **Route** | `POST /gestor/API/avance-sic/enviarAvance` → `SenceSic::enviarAvance` |
| **Input format** | Raw JSON body |
| **Output format** | JSON |

**Input JSON structure**:
```json
{
  "rutOtec": "77124930-2",
  "token": "5EEBF607-25A9-4DB2-A4DD-5D31BDAE3220",
  "idSistema": 1350,
  "codigoOferta": "CAP-15-01-13-0446",
  "codigoGrupo": "SENCE--1",
  "codigoEnvio": "unique-send-id-123",
  "cantActividadSincronica": 1,
  "cantActividadAsincronica": 3,
  "listaAlumnos": [
    {
      "rutAlumno": "9445435",
      "dvAlumno": "2",
      "porcentajeAvance": 80.5,
      "tiempoConectividad": 1200,
      "estado": "1",
      "fechaInicio": "2024-01-01 00:00:00",
      "fechaFin": "2024-12-31 00:00:00",
      "fechaEjecucion": "2024-06-15 00:00:00",
      "listaModulos": [
        {
          "codigoModulo": "C46982-O565656565-M1",
          "porcentajeAvance": 75.0,
          "tiempoConectividad": 600,
          "estado": "1",
          "fechaInicio": "2024-01-01",
          "fechaFin": "2024-12-31",
          "listaActividades": [
            { "codigoActividad": "Tarea de prueba" }
          ]
        }
      ]
    }
  ]
}
```

**Success JSON response** (all students OK):
```json
{
  "id_proceso": 457,
  "envio": [ ... listaAlumnos echoed back ... ],
  "errores": [],
  "respuesta_SIC": "El proceso 457 ha finalizado correctamente, para ver la cantidad de registros y detalle, dirigase a la pantalla de procesos"
}
```

**Partial error JSON response** (some students rejected by validation):
```json
{
  "id_proceso": 312,
  "datosEnviados": [],
  "datosError": [
    {
      "alumno": { ... full alumno object ... },
      "codigo": "025",
      "mensaje": "modulo.porcentajeAvance debe ser mayor al anterior (85.00000). Alumno 9445435-2, Modulo C46982-O565656565-M1."
    }
  ],
  "respuesta_SIC": ""
}
```

**Full JSON parse error response**:
```json
{
  "id_proceso": 0,
  "datosEnviados": [],
  "datosError": [{ "codigo": "001", "mensaje": "JSON invalido o vacio." }],
  "respuesta_SIC": ""
}
```

**idSistema mismatch**:
```json
{
  "id_proceso": 0,
  "datosEnviados": [],
  "datosError": [{ "codigo": "001", "mensaje": "idSistema incorrecto, debe ser 1350" }],
  "respuesta_SIC": ""
}
```

**Token with lowercase**:
```json
{
  "id_proceso": 0,
  "datosEnviados": [],
  "datosError": [{ "codigo": "001", "mensaje": "Token invalido. Debe estar en mayusculas." }],
  "respuesta_SIC": ""
}
```

**Forced error response** (via error queue):
```json
{
  "id_proceso": 567,
  "datosEnviados": [],
  "datosError": [{
    "codigo": "012",
    "alumno": {
      "rutAlumno": "00000000",
      "dvAlumno": "0",
      "tiempoConectividad": 0,
      "porcentajeAvance": 0,
      "estado": 1,
      "fechaInicio": "2024-01-01 00:00:00",
      "fechaFin": "2024-01-01 00:00:00",
      "listaModulos": []
    },
    "mensaje": "Error forzado: 012"
  }],
  "respuesta_SIC": ""
}
```

#### Endpoint: Historial Envios (Send History)

| Aspect | Detail |
|---|---|
| **Route** | `GET /gestor/API/avance-sic/historialEnvios` → `SenceSic::historialEnvios` |
| **Input format** | Query string parameters |
| **Output format** | JSON array |

**Query parameters**:
| Parameter | Required | Description |
|---|---|---|
| `rutOtec` | Yes | RUT of the OTEC |
| `idSistema` | Yes | System ID (must be 1350 per config) |
| `token` | Yes | Auth token |
| `fechaDesde` | No | Filter by created_at >= date |
| `codigo_externo` | No | Filter by external code |
| `id_proceso` | No | Filter by specific process ID |

**JSON response** (array of processes, each with nested `listaregistros`):
```json
[
  {
    "id_proceso_externo": "457",
    "id_lms": "0",
    "codigo_externo": "unique-send-id-123",
    "fecha": "2024-01-01 12:00:00",
    "horario": null,
    "observaciones": "El proceso 457 ha finalizado correctamente...",
    "observaciones_sic": null,
    "status_code": "0",
    "estado": "2",
    "rut_otec": "77124930-2",
    "listaregistros": [
      {
        "id_registro": "1",
        "rut_otec": "77124930-2",
        "dv_otec": "2",
        "oferta_cod": "CAP-15-01-13-0446",
        "seccion_cod": "C46982-O565656565-M1",
        "rut_alumno": "9445435",
        "dv_alumno": "2",
        "tiempo_conectividad": "0",
        "estado": "1",
        "porcentaje_avance": "80.50000",
        "fecha_inicio": "2024-01-01 00:00:00",
        "fecha_fin": "2024-12-31 00:00:00",
        "fecha_ejecucion": "2024-06-15 00:00:00",
        "mod_cod": "C46982-O565656565-M1",
        "mod_tiempo_conectividad": "0",
        "mod_estado": "1",
        "mod_porcentaje_avance": "75.00000",
        "mod_fecha_inicio": "2024-01-01",
        "mod_fecha_fin": "2024-12-31",
        "mod_obligatorio": null,
        "mod_nota": null,
        "act_cod": "Tarea de prueba",
        "act_estado": null,
        "estado_registro": "1",
        "obs_registro": "",
        "id_proceso_externo": "457"
      }
    ]
  }
]
```

Results limited to 50, ordered by id DESC.

---

## 3. Complete Project Structure

```
sence-api-clone/
├── .env                              # Environment variables (SQLite config, SENCE test credentials, Moodle setup guide)
├── .gitignore
├── AGENT.md                          # THIS FILE
├── composer.json                     # CI4 dependencies
├── composer.lock
├── LICENSE
├── README.md
├── spark                             # CI4 CLI entry point
├── phpunit.dist.xml
├── preload.php
├── app/
│   ├── Config/
│   │   └── Routes.php                # Route definitions (8 routes)
│   ├── Controllers/
│   │   ├── SenceRce.php              # RCE service: iniciarSesion, cerrarSesion, validation, POST redirect rendering
│   │   ├── SenceSic.php              # SIC service: enviarAvance, historialEnvios, JSON validation, JSON responses
│   │   └── SenceDashboard.php        # Dashboard: index, sesiones, procesos, avances, errores (GET/POST), limpiar
│   ├── Database/
│   │   └── Migrations/
│   │       ├── 2024-01-01-000001_CreateSenceSesiones.php       # sence_sesiones table
│   │       ├── 2024-01-01-000002_CreateSenceProcesos.php       # sence_procesos table
│   │       ├── 2024-01-01-000003_CreateSenceAvances.php        # sence_avances table
│   │       ├── 2024-01-01-000004_CreateSenceProcesoRegistros.php # sence_proceso_registros table
│   │       └── 2024-01-01-000005_CreateSenceErrorQueue.php     # sence_error_queue table
│   ├── Models/
│   │   ├── SenceSesionModel.php       # ORM for sence_sesiones (has findBySesionSence helper)
│   │   ├── SenceProcesoModel.php      # ORM for sence_procesos
│   │   ├── SenceAvanceModel.php       # ORM for sence_avances (has getLastAvance helper)
│   │   ├── SenceProcesoRegistroModel.php # ORM for sence_proceso_registros
│   │   └── SenceErrorQueueModel.php   # ORM for sence_error_queue (getPending, decrement, setError, getAllActive, clearAll, deleteById)
│   └── Views/
│       └── sence/
│           ├── redirect.php           # Minimal HTML form with hidden inputs that auto-submits via JS (used by RCE)
│           └── dashboard.php          # Full dashboard UI (244 lines, dark theme, tables for sessions/processes/avances, error injection UI)
├── docs/
│   ├── idea.md                        # Technical specification document (445 lines) with full SENCE reference
│   └── *.txt                          # Official SENCE integration PDFs (reference material)
├── public/                            # CI4 web root
├── writable/                          # Logs, cache, temp; sence_mock.db created here
├── tests/
└── vendor/                            # Composer dependencies
```

---

## 4. Database Schema

All tables use SQLite3 with INTEGER primary keys and auto-increment.

### 4.1 `sence_sesiones` — RCE Session Records

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK AUTO | Row ID |
| `rut_otec` | VARCHAR(20) NULL | OTEC RUT from POST |
| `token` | VARCHAR(50) NULL | Auth token |
| `cod_sence` | VARCHAR(10) NULL | SENCE code |
| `codigo_curso` | VARCHAR(50) NULL | Course code |
| `linea_capacitacion` | INTEGER NULL | Training line (1/3/6) |
| `run_alumno` | VARCHAR(10) NULL | Student RUT |
| `id_sesion_alumno` | VARCHAR(149) NULL | Client-side session ID |
| `id_sesion_sence` | VARCHAR(149) NULL | Server-generated session ID (MOCK-SESS-XXXXXXXX) |
| `url_retoma` | VARCHAR(255) NULL | Success callback URL |
| `url_error` | VARCHAR(255) NULL | Error callback URL |
| `estado` | VARCHAR(10) DEFAULT 'activa' | 'activa' or 'cerrada' |
| `glosa_error` | VARCHAR(5) NULL | Error code if session errored |
| `fecha_inicio` | DATETIME NULL | Session start timestamp |
| `fecha_cierre` | DATETIME NULL | Session close timestamp |
| `created_at` | DATETIME NULL | Record creation timestamp |

### 4.2 `sence_procesos` — SIC Process Records

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK AUTO | Row ID |
| `id_proceso` | VARCHAR(50) | Random process ID (100-999) |
| `rut_otec` | VARCHAR(20) | OTEC RUT |
| `codigo_oferta` | VARCHAR(50) NULL | Course offering code |
| `codigo_grupo` | VARCHAR(50) NULL | Group code |
| `codigo_externo` | VARCHAR(100) NULL | External send code (codigoEnvio or codigoExterno) |
| `id_lms` | VARCHAR(10) DEFAULT '0' | LMS identifier |
| `estado` | VARCHAR(2) DEFAULT '2' | '1' = has errors, '2' = all OK |
| `status_code` | VARCHAR(2) DEFAULT '0' | SIC status code |
| `respuesta_sic` | TEXT NULL | SIC response message |
| `observaciones_sic` | TEXT NULL | SIC observations |
| `horario` | DATETIME NULL | Schedule timestamp |
| `created_at` | DATETIME NULL | Record creation timestamp |

### 4.3 `sence_avances` — SIC Progress History

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK AUTO | Row ID |
| `rut_alumno` | VARCHAR(10) | Student RUT (without DV) |
| `dv_alumno` | VARCHAR(2) | Student verification digit |
| `codigo_modulo` | VARCHAR(100) | Module code |
| `porcentaje_avance` | DECIMAL(10,5) | Progress percentage (stored with 5 decimals) |
| `proceso_id` | INTEGER FK | References sence_procesos.id |
| `created_at` | DATETIME NULL | Record creation timestamp |

**Index**: Composite key on (`rut_alumno`, `codigo_modulo`) for fast lookups.

The `getLastAvance(rutAlumno, codigoModulo)` method on this model is used to enforce error 025 (progress must not decrease).

### 4.4 `sence_proceso_registros` — SIC Process Detail Records

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK AUTO | Row ID |
| `proceso_id` | INTEGER FK | References sence_procesos.id |
| `rut_otec` | VARCHAR(20) NULL | OTEC RUT |
| `dv_otec` | VARCHAR(2) NULL | OTEC DV (always '2') |
| `oferta_cod` | VARCHAR(50) NULL | Course offering code |
| `seccion_cod` | VARCHAR(50) NULL | Section/module code |
| `rut_alumno` | VARCHAR(10) NULL | Student RUT |
| `dv_alumno` | VARCHAR(2) NULL | Student DV |
| `tiempo_conectividad` | VARCHAR(20) NULL | Student-level connectivity time |
| `estado` | VARCHAR(2) NULL | Student-level status |
| `porcentaje_avance` | VARCHAR(20) NULL | Student-level progress % |
| `fecha_inicio` | VARCHAR(30) NULL | Student-level start date |
| `fecha_fin` | VARCHAR(30) NULL | Student-level end date |
| `fecha_ejecucion` | VARCHAR(30) NULL | Execution date |
| `mod_cod` | VARCHAR(100) NULL | Module code |
| `mod_tiempo_conectividad` | VARCHAR(20) NULL | Module-level connectivity time |
| `mod_estado` | VARCHAR(2) NULL | Module-level status |
| `mod_porcentaje_avance` | VARCHAR(20) NULL | Module-level progress % |
| `mod_fecha_inicio` | VARCHAR(30) NULL | Module-level start date |
| `mod_fecha_fin` | VARCHAR(30) NULL | Module-level end date |
| `mod_obligatorio` | VARCHAR(10) NULL | Module mandatory flag |
| `mod_nota` | VARCHAR(10) NULL | Module grade (from notaModulo) |
| `act_cod` | VARCHAR(255) NULL | Activity code (first from listaActividades) |
| `act_estado` | VARCHAR(10) NULL | Activity status |
| `estado_registro` | VARCHAR(2) NULL | Record state (always '1') |
| `obs_registro` | TEXT NULL | Record observations |
| `id_proceso_externo` | VARCHAR(50) NULL | External process ID |
| `created_at` | DATETIME NULL | Timestamp |

### 4.5 `sence_error_queue` — Forced Error Injection Queue

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER PK AUTO | Row ID |
| `endpoint` | VARCHAR(50) | Target endpoint: `rce_inicio`, `rce_cierre`, `sic_avance`, `sic_historial` |
| `codigo_error` | VARCHAR(10) | Error code to return (e.g., '200', '012', '303') |
| `peticiones_restantes` | INTEGER DEFAULT 0 | Number of remaining requests to fail (0 = inactive) |
| `glosa_error` | TEXT NULL | Optional custom error message |
| `created_at` | DATETIME NULL | Timestamp |

**Index**: On `endpoint` column.

---

## 5. Configuration (.env)

Located at `C:\wamp64\www\devlearn\sence-api-clone\.env`.

### Environment
```
CI_ENVIRONMENT = development
```

### App Base URL
```
app.baseURL = 'http://localhost:8080/'
```

### Database (SQLite3)
```
database.default.DBDriver = SQLite3
database.default.database = sence_mock.db
database.default.DBPrefix =
database.default.foreignKeys = true
database.default.busyTimeout = 1000
```

### SENCE Test Credentials
```
sence.rutOtec = 77124930-2
sence.token = 5EEBF607-25A9-4DB2-A4DD-5D31BDAE3220
sence.idSistema = 1350
sence.codigoOferta = CAP-15-01-13-0446
sence.codigoModulo = C46982-O565656565-M1
```

These values are loaded via `env('sence.rutOtec')` in `SenceSic::__construct()` and serve as the baseline validation values for the SIC service.

### Hardcoded Valid Students (in `SenceSic::__construct()`)
```php
$this->alumnosValidos = [
    '9445435'  => ['dv' => '2', 'nombre' => 'GUSTAVO RAMON JARA ORTIZ'],
    '10312870' => ['dv' => '6', 'nombre' => 'HILDA MAGDALENA BEZERRA SAAVEDRA'],
    '10176851' => ['dv' => '1', 'nombre' => 'MARIBEL IRENE OLGUIN CONTRERAS'],
];
```
Note: These are defined in code, not in .env. They are not currently used for active validation in this version.

---

## 6. How to Run

### Prerequisites
- PHP 8.0+ with SQLite3 extension enabled
- Composer installed

### Setup Commands
```bash
# 1. Install dependencies
composer install

# 2. Run migrations (creates SQLite DB and all tables)
php spark migrate

# 3. Start the development server
php spark serve
# Server listens on http://localhost:8080
```

After `php spark serve`, all endpoints are available:
- Dashboard: `http://localhost:8080/sence/dashboard`
- RCE test: `http://localhost:8080/rcetest/Registro/IniciarSesion` and `.../CerrarSesion`
- RCE prod: `http://localhost:8080/rce/Registro/IniciarSesion` and `.../CerrarSesion`
- SIC: `http://localhost:8080/gestor/API/avance-sic/enviarAvance` and `.../historialEnvios`

### Reset All Data
Visit `http://localhost:8080/sence/dashboard/limpiar` or click "Limpiar Datos" in the dashboard. This truncates all 5 tables.

---

## 7. RCE Flow in Detail

### Iniciar Sesion (Start Session)

1. **Moodle POSTs** form-encoded params to `/rce/Registro/IniciarSesion`
2. **Error queue check**: `SenceErrorQueueModel::getPending('rce_inicio')` — if a matching row exists with `peticiones_restantes > 0`, the request is rejected with the forced error code. The counter is decremented.
3. **Validation** (`validarParametrosInicio`):
   - All 9 required fields (`RutOtec`, `Token`, `CodSence`, `CodigoCurso`, `LineaCapacitacion`, `RunAlumno`, `IdSesionAlumno`, `UrlRetoma`, `UrlError`) must exist and be non-empty
   - Missing `UrlRetoma` or `UrlError` → error `201`
   - Missing other fields → error `200`
   - `UrlRetoma` not a valid URL → error `202`
   - `UrlError` not a valid URL → error `203`
   - `LineaCapacitacion` not in [1, 3, 6] → error `206`
   - **v1.2.0** `CodSence` < 10 chars for Franquicia Tributaria (línea 3) → error `204` (bypassed by `-1`)
   - **v1.2.0** `CodSence` non-empty for Programas Sociales (línea 1) → error `206` (must be blank, `-1` in test)
   - `CodigoCurso` != '-1' AND length < 7 → error `205`
   - **v1.2.0** `CodigoCurso` length check skipped for FPT (línea 6) per manual
   - `RunAlumno` fails RUT format/checksum validation → error `207`
   - `RutOtec` fails RUT format/checksum validation → error `209`
4. **On success**: Generates `IdSesionSence` = `MOCK-SESS-` + 16 random hex chars. Inserts a row into `sence_sesiones` with `estado = 'activa'`.
5. **Response**: Renders `app/Views/sence/redirect.php` — an HTML page with a hidden `<form method="POST">` containing all success fields, auto-submitted by `<script>document.getElementById("sence_form").submit();</script>`. The form action is `UrlRetoma`.
6. **On error**: Same HTML redirect but form action is `UrlError`, and the data includes `GlosaError` with the error code.

### Cerrar Sesion (Close Session)

1. **Moodle POSTs** to `/rce/Registro/CerrarSesion`
2. **Error queue check**: `getPending('rce_cierre')` — same mechanism as inicio
3. **Validation** (`validarParametrosCierre`): Same 9 fields as inicio plus `IdSesionSence`. Same error codes.
4. **On success**: Looks up the session by `IdSesionSence` in `sence_sesiones`. If found, updates `estado = 'cerrada'` and sets `fecha_cierre`. If not found, still succeeds silently (logs a note).
5. **Response**: Same HTML POST redirect to `UrlRetoma`. Note: the cierre success response does **NOT** include `IdSesionSence` in the returned fields.

### HTML Redirect Template (`redirect.php`)

```html
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>SENCE - Redireccionando...</title></head>
<body>
    <form id="sence_form" action="<URL>" method="POST">
        <input type="hidden" name="key1" value="val1">
        <input type="hidden" name="key2" value="val2">
        <!-- ... -->
    </form>
    <script>document.getElementById("sence_form").submit();</script>
</body>
</html>
```

### RUT Validation Algorithm (`validarFormatoRun`)

In `SenceRce.php:197-226`:
1. Regex check: `/^\d{7,8}-[0-9Kk]$/`
2. Break into number and DV (verification digit)
3. Multiply digits right-to-left by factors 2,3,4,5,6,7 (cycling)
4. Sum products, compute `11 - (sum % 11)`
5. If result is 11 → DV = 0; if 10 → DV = K
6. Compare computed DV with provided DV

---

## 8. SIC Flow in Detail

### Enviar Avance (Submit Progress)

1. **Moodle POSTs** raw JSON to `/gestor/API/avance-sic/enviarAvance`
2. **Error queue check**: `getPending('sic_avance')` — if active, returns a JSON response with the forced error code and a fake student (RUT 00000000-0)
3. **JSON parse check**: If `$this->request->getJSON(true)` returns null, returns `{id_proceso: 0, datosError: [{codigo: '001', mensaje: 'JSON invalido o vacio.'}]}`
4. **idSistema validation**: Must equal `1350` (from env). If not, returns error 001 with message "idSistema incorrecto, debe ser 1350"
5. **Token case validation**: Token must be all uppercase (`$token !== strtoupper($token)`). If not, returns error 001 "Token invalido. Debe estar en mayusculas."
6. **Curso-level validation** (v1.2.0):
   - `cantActividadSincronica < 0` → error `032`
   - `cantActividadAsincronica < 0` → error `033`
7. **Per-student validation** (each alumno in `listaAlumnos`):
   - `estado` must be 1, 2, or 3 → error `002` if invalid (v1.2.0)
   - `porcentajeAvance` must be between 0 and 100 → error `022` if out of range (v1.2.0)
   - `fechaFin < fechaInicio` → error `027`, student is skipped
   - `fechaEjecucion` outside `[fechaInicio, fechaFin]` range → error `026` (v1.2.0)
   - `porcentajeAvance < last recorded percentage for same rut+modulo` → error `025`, student is skipped
8. **Per-module validation** (each modulo in `listaModulos`):
   - `estado` must be 1, 2, or 3 → error `002` if invalid (v1.2.0)
   - `porcentajeAvance` must be between 0 and 100 → error `024` if out of range (v1.2.0)
   - `fechaFin < fechaInicio` → error `027`, student is skipped
9. **Database inserts** (for valid students):
   - One row in `sence_procesos` (id_proceso is random 100-999, estado='1' if any errors else '2')
   - One row per module in `sence_avances` (tracks percentage history, percentage stored as `number_format(value, 5, '.', '')`)
   - One row per module in `sence_proceso_registros` (full detail including module fields prefixed `mod_`, activity code, etc.)
8. **Response JSON**: Success returns `id_proceso`, `envio`, `errores: []`, `respuesta_SIC`. Partial failure returns `id_proceso`, `datosEnviados: []`, `datosError` array, `respuesta_SIC: ''`.

### Historial Envios (Send History)

1. **Moodle GETs** `/gestor/API/avance-sic/historialEnvios?rutOtec=...&idSistema=...&token=...`
2. **Error queue check**: `getPending('sic_historial')` — if active, returns HTTP 500 with `{error: 'Error forzado: <code>'}`
3. **Required params**: `rutOtec`, `idSistema`, `token` must all be present, else HTTP 400
4. **Query**: Filters by `rut_otec` (required), optional `fechaDesde`, `codigo_externo`, `id_proceso`. Ordered by `id DESC`, limit 50.
5. **Response JSON**: Array of process objects, each containing `listaregistros` array with full detail records joined from `sence_proceso_registros`.

---

## 9. Error Codes

### 9.1 RCE Error Codes (200–313)

| Code | Description | Trigger |
|---|---|---|
| `200` | Mandatory parameters empty or malformed | Any required field (except UrlRetoma/UrlError) missing or empty |
| `201` | UrlRetoma or UrlError empty | UrlRetoma or UrlError field missing or blank |
| `202` | UrlRetoma invalid format | UrlRetoma fails `filter_var(FILTER_VALIDATE_URL)` |
| `203` | UrlError invalid format | UrlError fails `filter_var(FILTER_VALIDATE_URL)` |
| `204` | CodSence invalid (linea 3: < 10 chars) | `CodSence != '-1' AND linea=3 AND strlen < 10` — v1.2.0 auto-triggered |
| `205` | CodigoCurso must be >= 7 chars (unless -1) | `CodigoCurso != '-1' AND linea != 6 AND strlen < 7` — v1.2.0 FPT exempt |
| `206` | LineaCapacitacion invalid | Value not in [1, 3, 6] OR linea=1 with non-empty CodSence (v1.2.0) |
| `207` | RunAlumno invalid format | RUT fails regex or checksum validation |
| `208` | RunAlumno not authorized | (available in force-error UI, not auto-triggered) |
| `209` | RutOtec invalid format | OTEC RUT fails regex or checksum validation |
| `210` | Session expired | (available in force-error UI, not auto-triggered) |
| `211` | Token does not belong | (available in force-error UI, not auto-triggered) |
| `212` | Token not current | (available in force-error UI, not auto-triggered) |
| `300` | Internal SENCE error | (available in force-error UI, not auto-triggered) |
| `303` | Token does not exist | (available in force-error UI, not auto-triggered) |
| `311` | RUT does not match login | (available in force-error UI, not auto-triggered) |
| `313` | URL cierre incorrecta | (available in force-error UI, not auto-triggered) |

**Auto-triggered** means the validation code in `SenceRce.php` natively produces this error. **Force-error UI only** codes can only be triggered via the dashboard's error injection system.

### 9.2 SIC Error Codes (001–033)

| Code | Description | Native Trigger |
|---|---|---|
| `001` | Token invalid / not uppercase / idSistema wrong / JSON parse error | Yes (multiple pre-validation checks) |
| `002` | General authentication/validation error | v1.2.0 auto-triggered (estado not 1/2/3 at student or module level) |
| `003` | Course not registered | Force-error only |
| `010` | Student not in module | Force-error only |
| `011` | Student not in course | Force-error only |
| `012` | Student not registered | Force-error only |
| `021` | tiempoConectividad invalid | Force-error only |
| `022` | porcentajeAvance invalid (outside 0-100) | v1.2.0 auto-triggered (student-level range check) |
| `023` | modulo.tiempoConectividad invalid | Force-error only |
| `024` | modulo.porcentajeAvance invalid (outside 0-100) | v1.2.0 auto-triggered (module-level range check) |
| `025` | Progress regression (new % < previous %) | Yes (checks sence_avances table) |
| `026` | fechaInicio/fechaEjecucion invalid | v1.2.0 auto-triggered (fechaEjecucion outside [inicio, fin] range) |
| `027` | fechaFin < fechaInicio (at student or module level) | Yes (two-level date check) |
| `028` | notaModulo invalid | Force-error only |
| `029` | cantSincronica invalid | Force-error only |
| `030` | cantAsincronica invalid | Force-error only |
| `031` | evaluacionFinal invalid | Force-error only |
| `032` | curso.cantActividadSincronica invalid | v1.2.0 auto-triggered (cantActividadSincronica < 0) |
| `033` | curso.cantActividadAsincronica invalid | v1.2.0 auto-triggered (cantActividadAsincronica < 0) |

---

## 10. Error Forcing System (Error Injection)

### Database: `sence_error_queue`

The table stores error injection rules. When an endpoint is hit, the controller checks this table **before** any validation logic.

### How it works

1. **Create**: Via dashboard POST to `/sence/dashboard/errores` with `action=activar`, specifying endpoint, error code, and petition count
2. **Intercept**: `SenceErrorQueueModel::getPending(endpoint)` queries for the first row where `endpoint` matches AND `peticiones_restantes > 0`
3. **Decrement**: `decrement()` reduces `peticiones_restantes` by 1 after each intercepted request
4. **Auto-cleanup**: When `peticiones_restantes` reaches 0, the row is ignored by `getPending()` (though not deleted)
5. **Manual cleanup**: Dashboard provides "X" button per row (`action=eliminar`) and "Limpiar Todos" button (`action=limpiar` which truncates the table)

### Endpoint identifiers

| `endpoint` value | Intercepts |
|---|---|
| `rce_inicio` | `SenceRce::iniciarSesion` |
| `rce_cierre` | `SenceRce::cerrarSesion` |
| `sic_avance` | `SenceSic::enviarAvance` |
| `sic_historial` | `SenceSic::historialEnvios` |

### Forced error behavior per endpoint

- **rce_inicio / rce_cierre**: Skips all validation. Returns an error POST redirect to `UrlError` with `GlosaError = codigo_error`. Logs `[RCE] ... ERROR FORZADO {code}`.
- **sic_avance**: Skips all validation. Returns JSON with the forced error code in `datosError` with a dummy student (RUT 00000000-0). Logs `[SIC] enviarAvance ERROR FORZADO {code}`.
- **sic_historial**: Returns HTTP 500 with `{error: 'Error forzado: <code>'}`. Logs `[SIC] historialEnvios ERROR FORZADO {code}`.

### Dashboard error endpoints

The forcing API operates via `POST /sence/dashboard/errores` with fields:
| Field | Value |
|---|---|
| `action` | `activar`, `eliminar`, or `limpiar` |
| `endpoint` | One of: `rce_inicio`, `rce_cierre`, `sic_avance`, `sic_historial` |
| `codigo_error` | Any code from 200-313 (RCE) or 001-033 (SIC) |
| `peticiones` | Integer, 1-100 |
| `glosa_error` | Optional freeform text |
| `error_id` | ID of row to delete (for `eliminar` action) |

---

## 11. How to Test with Moodle Plugin

### Moodle Plugin Configuration

Navigate to: **Site administration > Plugins > Blocks > SENCE** and set:

| Setting | Value |
|---|---|
| RUT OTEC | `77124930-2` |
| Token OTEC | `5EEBF607-25A9-4DB2-A4DD-5D31BDAE3220` |
| URL inicio sesion | `http://localhost:8080/rce/Registro/IniciarSesion` |
| URL cierre sesion | `http://localhost:8080/rce/Registro/CerrarSesion` |
| Prefijo grupo | `SENCE-` |

### Course-Level Block Configuration

| Setting | Value | Notes |
|---|---|---|
| CodigoCurso | `-1` | `-1` = test mode (bypasses 7-char minimum) |
| LineaCapacitacion | `3` | 1=Programas Sociales, 3=Franquicia, 6=FPT |
| Forzar cierre | `1` | |
| Tiempo max sesion | `10800` | 3 hours in seconds |
| Email alerta | `test@test.cl` | Optional |

### Groups Setup

Create groups in **Course > Participants > Groups**:

| Group Name | Mode |
|---|---|
| `SENCE--1` | Test mode (bypasses group validation, CodigoCurso = -1) |
| `SENCE-CAP-15-01-13-0446` | Real mode (uses actual SIC course offering code) |

### Student Profile Fields

Set `idnumber` in each student's profile to one of:

| ID Number | Name |
|---|---|
| `9445435-2` | GUSTAVO RAMON JARA ORTIZ |
| `10312870-6` | HILDA MAGDALENA BEZERRA SAAVEDRA |
| `10176851-1` | MARIBEL IRENE OLGUIN CONTRERAS |

### Testing the RCE Flow

1. Add the SENCE block to a course
2. Configure the block settings as above
3. Create groups matching the patterns above
4. Add students with the RUTs above to the groups
5. As a student, click the "Iniciar Sesion" button in the block
6. You should be redirected to SENCE (the mock), which validates params and redirects back to Moodle
7. The dashboard at `/sence/dashboard/sesiones` should show the new session

### Testing the SIC Flow

1. Configure the SIC cron task in Moodle's SENCE plugin
2. The cron will POST progress data to `/gestor/API/avance-sic/enviarAvance`
3. Check results at `/sence/dashboard/procesos` and `/sence/dashboard/avances`

### Testing Error Scenarios

1. Open `http://localhost:8080/sence/dashboard/errores`
2. Select an endpoint (e.g., `rce_inicio`)
3. Select an error code (e.g., `207 - RunAlumno formato`)
4. Set number of requests to fail (e.g., `3`)
5. Click "Activar Error"
6. The next 3 login attempts will receive error 207. The 4th will succeed normally.

---

## 12. Logging

### Format

All logging uses CodeIgniter 4's built-in `log_message()` function. Logs are written to files in `writable/logs/`.

### Log Levels Used

| Level | Usage |
|---|---|
| `info` | Normal operation events: successful request processing, counts of records |
| `warning` | Validation failures, forced errors |

### Log Message Patterns

**RCE**:
```
[RCE] POST /rce/Registro/IniciarSesion | RunAlumno=9445435-2 | CodigoCurso=-1 | RutOtec=77124930-2
[RCE] IniciarSesion OK | IdSesionSence=MOCK-SESS-ABCD1234... | RunAlumno=9445435-2 | CodigoCurso=-1
[RCE] CerrarSesion OK | IdSesionSence=MOCK-SESS-ABCD1234... | sesion previa encontrada y cerrada
[RCE] CerrarSesion OK | IdSesionSence=MOCK-SESS-... | sesion no encontrada en DB (se acepta igual)
[RCE] IniciarSesion ERROR VALIDACION 207 | RunAlumno=12345
[RCE] IniciarSesion ERROR FORZADO 311 | restantes=2
```

**SIC**:
```
[SIC] POST /gestor/API/avance-sic/enviarAvance | rutOtec=77124930-2 | alumnos=3
[SIC] enviarAvance OK | id_proceso=457 | alumnos=3 | codigoOferta=CAP-15-01-13-0446 | codigoEnvio=...
[SIC] enviarAvance PARCIAL | id_proceso=312 | errores=2 | alumnos_ok=1
[SIC] enviarAvance ERROR 001 | JSON invalido o vacio
[SIC] enviarAvance ERROR 001 | idSistema=9999 esperado=1350
[SIC] enviarAvance ERROR 001 | token con minusculas
[SIC] enviarAvance ERROR FORZADO 012 | restantes=1
[SIC] GET /gestor/API/avance-sic/historialEnvios | rutOtec=77124930-2 | filters=fechaDesde=2024-01-01...
[SIC] historialEnvios OK | procesos=5
[SIC] historialEnvios ERROR | parametros requeridos faltantes
[SIC] historialEnvios ERROR FORZADO 500
```

**Dashboard**:
```
[DASHBOARD] Error activado | endpoint=rce_inicio | codigo=207 | peticiones=3
[DASHBOARD] Error eliminado | id=5
[DASHBOARD] Todos los errores forzados eliminados
```

### Log Location

Default CI4 log directory: `writable/logs/`. The exact filename depends on CI4's Logger config (typically `log-YYYY-MM-DD.log`). Since `CI_ENVIRONMENT = development`, debug-level logging is enabled by default.

---

## Appendix: Routes Reference

```php
// app/Config/Routes.php

// RCE - Ambiente Test
POST  rcetest/Registro/IniciarSesion  →  SenceRce::iniciarSesion
POST  rcetest/Registro/CerrarSesion   →  SenceRce::cerrarSesion

// RCE - Ambiente Produccion
POST  rce/Registro/IniciarSesion      →  SenceRce::iniciarSesion
POST  rce/Registro/CerrarSesion       →  SenceRce::cerrarSesion

// SIC - API Gestor Intermedio
POST  gestor/API/avance-sic/enviarAvance     →  SenceSic::enviarAvance
GET   gestor/API/avance-sic/historialEnvios  →  SenceSic::historialEnvios

// Dashboard
GET   sence/dashboard                 →  SenceDashboard::index
GET   sence/dashboard/sesiones        →  SenceDashboard::sesiones
GET   sence/dashboard/procesos        →  SenceDashboard::procesos
GET   sence/dashboard/avances         →  SenceDashboard::avances
GET   sence/dashboard/errores         →  SenceDashboard::errores
POST  sence/dashboard/errores         →  SenceDashboard::errores
GET   sence/dashboard/limpiar         →  SenceDashboard::limpiar

// Default
GET   /                               →  Home::index
```

## Appendix: Key Design Decisions

1. **RCE uses HTML form POST redirects, not HTTP redirects**: The real SENCE specification requires POSTing form data back to the client. CodeIgniter's `redirect()` does a GET redirect, so the mock renders `Views/sence/redirect.php` which is an HTML page with hidden inputs and auto-submit JavaScript.

2. **SQLite instead of MySQL**: Makes the mock zero-config. No database server needed. Just `composer install && php spark migrate && php spark serve`.

3. **No authentication on dashboard**: The dashboard at `/sence/dashboard` is intentionally open — this is a local dev tool, not a production service.

4. **Error queue precedes validation**: When forcing errors, the error queue check runs before any parameter validation. This means you can force error 205 without actually sending an invalid CodigoCurso.

5. **CierreSesion accepts unknown IdSesionSence**: The close endpoint logs a note but still returns success even if the session ID is not found in the database. This matches observed SENCE behavior where they may not maintain cross-request session state.

6. **SIC stores percentages with 5 decimal places**: `number_format($value, 5, '.', '')` before insertion. The `porcentaje_avance` field in `sence_proceso_registros` is VARCHAR(20) by design (matching real SIC field type), though `sence_avances` uses DECIMAL(10,5).
