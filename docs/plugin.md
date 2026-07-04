# AGENT.md — Especificación completa del plugin block_sence v2.5.0

> **Propósito de este documento**: Describir el plugin con suficiente detalle para que un
> agente de IA (o un desarrollador nuevo) entienda su estructura, funcionamiento,
> entradas, salidas y puntos de extensión sin necesidad de leer el código fuente.

---

## 1. Composición del plugin

### 1.1 Identidad

| Campo | Valor |
|---|---|
| Componente | `block_sence` |
| Tipo | Bloque de Moodle (`block_base`) |
| Namespace raíz | `block_sence` |
| Versión actual | `2.5.0` (`2025070400`) |
| Requiere Moodle | `>= 2014051200` (Moodle 2.7+) |
| Madurez | `MATURITY_STABLE` |

### 1.2 Estructura de archivos (30 archivos)

```
blocks/sence/
│
├── ▸ ENTRY POINTS
│   ├── block_sence.php              # Clase principal extends block_base
│   ├── report.php                   # Página: reporte por curso (gestor)
│   ├── adminreport.php              # Página: reporte global (admin)
│   └── test_config.php              # Endpoint AJAX: validación y test de conectividad
│
├── ▸ CONFIGURACIÓN
│   ├── settings.php                 # Settings globales del plugin
│   ├── edit_form.php                # Formulario de config por instancia de bloque
│   └── locallib.php                 # Constantes centralizadas (BlockSenceDefaults)
│
├── ▸ CAPA DE DATOS
│   ├── classes/report/course_report.php  # Queries SQL para reportes
│   ├── classes/progress/extractor.php    # Queries SQL para extraer avance de Moodle
│   ├── classes/progress/builder.php      # Construye JSON para API de avance
│   └── classes/progress/sender.php       # cURL POST a la API de avance SENCE
│
├── ▸ EVENTOS (Moodle Events API)
│   ├── classes/event/session_started.php # Evento: inicio de sesión SENCE exitoso
│   └── classes/event/session_error.php   # Evento: error devuelto por SENCE
│
├── ▸ TAREAS PROGRAMADAS
│   ├── classes/task/send_progress.php    # Scheduled task: envío diario de avance
│   └── db/tasks.php                      # Registro del task (22:00 diario)
│
├── ▸ BASE DE DATOS
│   ├── db/install.xml                # Esquema: tablas block_sence + block_sence_log
│   ├── db/upgrade.php                # Migraciones históricas + savepoints
│   ├── db/access.php                 # Capacidades (myaddinstance, addinstance)
│   ├── db/install.php                # Post-install
│   └── db/uninstall.php              # Cleanup
│
├── ▸ INTERNACIONALIZACIÓN (~170 strings cada idioma)
│   ├── lang/es/block_sence.php
│   └── lang/en/block_sence.php
│
├── ▸ METADATOS
│   └── version.php                   # $plugin->component, release, version, requires
│
├── ▸ DOCUMENTACIÓN
│   ├── README.md                     # Estado técnico y changelog
│   ├── INSTALL.md                    # Manual para administrador y gestor
│   ├── DEVELOPER.md                  # Manual técnico para desarrolladores
│   ├── FUNCIONAMIENTO.md             # Funcionamiento técnico detallado
│   ├── PROBLEMAS.md                  # Historial de problemas y mitigaciones (26 items)
│   ├── ROADMAP.md                    # Checklist de pendientes (35/52 completados)
│   ├── PLAN_DE_TRABAJO.md            # Análisis de cumplimiento vs manual v1.1.6
│   └── AGENT.md                      # Este archivo — especificación para agentes
│
└── ▸ DOCUMENTOS DE REFERENCIA SENCE
    └── docs/
        ├── integracion_registro_asistencia_sence_v1.1.6.txt
        ├── instructivo_tecnico_de_integracion_entre_lms_y_sic_v2.0_0.txt
        ├── estructura_de_pruebas_para_integracion.txt
        ├── capacitacion_integracion_api_gestor_intermedio_1.txt
        ├── manual_tecnico_conexion_lms_externos_0.txt
        └── manual_ejecutores.txt
```

### 1.3 Jerarquía de clases

```
block_base (Moodle core)
└── block_sence                    # Clase principal (block_sence.php)
    ├── render_student_view()      # Vista + lógica para alumnos
    ├── render_manager_view()      # Vista + validaciones para gestores
    └── resolve_action_id()        # Regla de negocio: extraer ID de acción del grupo

block_sence\event\
├── session_started                # extends \core\event\base
└── session_error                  # extends \core\event\base

block_sence\report\
└── course_report                  # Clase estática con queries

block_sence\progress\
├── extractor                      # Clase estática: 12 métodos de extracción
├── builder                        # Clase estática: build + validate + to_json
└── sender                         # Clase estática: cURL + retry + test

block_sence\task\
└── send_progress                  # extends \core\task\scheduled_task
```

---

## 2. Cómo funciona — las dos integraciones

El plugin implementa **dos integraciones separadas** con SENCE:

### Integración 1: Asistencia (inicio/cierre de sesión)

**Modelo**: Redirect flow via navegador.

**Qué hace**:
1. Renderiza formularios HTML con `action="https://sistemas.sence.cl/rce/Registro/IniciarSesion"` (o `CerrarSesion`)
2. El navegador del alumno envía los POST directamente a SENCE
3. SENCE procesa y redirige de vuelta a la URL del curso con parámetros POST
4. El plugin captura los parámetros de retorno (`IdSesionSence`, `FechaHora`, `ZonaHoraria`, `GlosaError`) y actúa

**Quién dispara**: El alumno (al pulsar botones) y SENCE (al redirigir de vuelta).

**Cuándo**: En vivo, mientras el alumno estudia.

**Estado**: ✅ Implementado y funcional.

### Integración 2: Avance a SIC (progreso, notas, tiempos)

**Modelo**: API REST via cURL desde servidor.

**Qué hace**:
1. Un scheduled task se ejecuta cada noche a las 22:00
2. Lee datos de progreso de las tablas de Moodle (`user`, `grade_grades`, `course_completions`, `logstore_standard_log`)
3. Arma un JSON con la estructura exigida por la API
4. Hace POST via cURL a `https://auladigital.sence.cl/gestor/API/avance-sic/enviarAvance`
5. Registra el resultado en `block_sence_log`

**Quién dispara**: El cron de Moodle (automático).

**Cuándo**: Diario, entre 22:00 y 00:00 hrs.

**Estado**: ✅ Implementado (v2.5.0). Requiere activación manual en settings.

---

## 3. Qué espera el plugin (entradas)

### 3.1 Configuración global (settings.php)

Esperada en `get_config('block_sence')`:

| Campo | Tipo | Obligatorio | Default | Uso |
|---|---|---|---|---|
| `rutotec` | string | Sí (para operar) | `''` | Enviado como `RutOtec` en forms de asistencia + API avance |
| `tokenotec` | string | Sí (para operar) | `''` | Enviado como `Token` en forms de asistencia + API avance |
| `urliniciosesion` | string (URL) | No | `.../rce/Registro/IniciarSesion` | Action del form de inicio |
| `urlcierresesion` | string (URL) | No | `.../rce/Registro/CerrarSesion` | Action del form de cierre |
| `prefijogrupo` | string | No | `'SENCE-'` | Prefijo para detectar grupo con ID de acción |
| `correosoportesence` | string (email) | No | `'controlelearning@sence.cl'` | Referencia en correos de error |
| `apienabled` | int (bool) | No | `0` | Activa/desactiva el envío automático de avance |

### 3.2 Configuración por instancia de bloque (edit_form.php)

Esperada en `$this->config` (objeto con propiedades):

| Campo | Tipo | Default | Uso |
|---|---|---|---|
| `lineasdecap` | int | `3` | 1=Programas Sociales, 3=Franquicia Tributaria, 6=FPT |
| `codigocurso` | string | `null` | Código SENCE. 10 dígitos para línea 3. Se envía como `CodSence` |
| `grupobecas` | string | `null` | Nombres de grupo separados por coma. Alumnos exentos de SENCE |
| `correoalerta` | string (email) | `null` | Recibe correos por cada error SENCE |
| `forzarcierre` | int (bool) | `1` | Muestra cronómetro y exige cierre de sesión |
| `sencetimeout` | int (segundos) | `10800` | Duración máxima de sesión (3 horas) |
| `alertafinal` | string | String de idioma | Texto del banner rojo de alerta |

### 3.3 Datos del perfil del alumno

| Fuente | Campo | Formato esperado | Uso |
|---|---|---|---|
| `$USER->idnumber` | RUT | `12345678-9` (con guión, sin puntos) | Enviado como `RunAlumno` |
| `$USER->sesskey` | Session key | string de Moodle | Parte del `IdSesionAlumno` |
| `$USER->id` | User ID | int | Identificación en eventos y logs |

### 3.4 Datos del curso

| Fuente | Uso |
|---|---|
| `$this->page->course->id` | `courseid` en eventos y queries |
| `$this->page->course->shortname` | Sufijo en claves de sesión (`-{shortname}`) |
| Grupos del alumno (`groups_get_all_groups`) | Nombre del grupo → extraer ID de acción |
| `$PAGE->url->out(false)` | `UrlRetoma` y `UrlError` en forms |

### 3.5 Parámetros POST que SENCE envía de vuelta

El plugin espera recibir estos parámetros en `$_POST` cuando SENCE redirige al LMS:

**En éxito (inicio)**:
```
IdSesionSence, FechaHora, ZonaHoraria, RunAlumno,
CodSence, CodigoCurso, LineaCapacitacion, IdSesionAlumno
```

**En error (inicio o cierre)**:
```
Todos los anteriores + GlosaError (int, ej: 205)
```

**En éxito (cierre)**:
```
CodSence, CodigoCurso, IdSesionAlumno, RunAlumno,
FechaHora, ZonaHoraria, LineaCapacitacion
(SIN IdSesionSence)
```

---

## 4. Qué envía el plugin a SENCE (salidas)

### 4.1 Formulario de inicio (renderizado en HTML)

```html
<form action="https://sistemas.sence.cl/rce/Registro/IniciarSesion" method="post">
  <input type="hidden" name="RutOtec" value="12345678-9" />
  <input type="hidden" name="Token" value="5EEBF607-..." />
  <input type="hidden" name="CodSence" value="1234567890" />
  <input type="hidden" name="CodigoCurso" value="ID-ACCION-001" />
  <input type="hidden" name="LineaCapacitacion" value="3" />
  <input type="hidden" name="RunAlumno" value="11111111-1" />
  <input type="hidden" name="IdSesionAlumno" value="sesskey123-5" />
  <input type="hidden" name="UrlRetoma" value="https://moodle.otec.cl/course/view.php?id=5" />
  <input type="hidden" name="UrlError" value="https://moodle.otec.cl/course/view.php?id=5" />
  <button>Inicio Sesión SENCE</button>
</form>
```

### 4.2 Formulario de cierre (solo si `forzarcierre=1` y sesión activa)

```html
<form action="https://sistemas.sence.cl/rce/Registro/CerrarSesion" method="post">
  <!-- Mismos parámetros que inicio + IdSesionSence -->
  <input type="hidden" name="IdSesionSence" value="abc123-xyz" />
  ...
  <button>Cerrar Sesión SENCE</button>
</form>
```

### 4.3 API de avance — JSON enviado por cURL

**Endpoint**: `POST https://auladigital.sence.cl/gestor/API/avance-sic/enviarAvance`
**Headers**: `Content-Type: application/json`
**Body**:

```json
{
  "rutOtec": "11222333-4",
  "idSistema": 1350,
  "token": "5EE5EEEE-5555-...",
  "codigoOferta": "1234567890",
  "codigoGrupo": "1234567890",
  "codigoEnvio": "20250704143000",
  "cantActividadSincronica": 1,
  "cantActividadAsincronica": 3,
  "listaAlumnos": [
    {
      "rutAlumno": "9445435",
      "dvAlumno": "2",
      "tiempoConectividad": 3600,
      "porcentajeAvance": 75,
      "estado": 2,
      "fechaInicio": "2025-06-01 00:00:00",
      "fechaFin": "",
      "fechaEjecucion": "2025-07-04 00:00:00",
      "evaluacionFinal": 85,
      "listaModulos": [
        {
          "codigoModulo": "MODULO-1",
          "tiempoConectividad": 1800,
          "porcentajeAvance": 80,
          "estado": 2,
          "fechaInicio": "2025-06-01",
          "fechaFin": "",
          "notaModulo": 70,
          "cantActividadSincronica": 1,
          "cantActividadAsincronica": 2,
          "listaActividades": [
            { "codigoActividad": "Tarea" },
            { "codigoActividad": "Cuestionario" }
          ]
        }
      ]
    }
  ]
}
```

### 4.4 Correo de alerta (solo si `correoalerta` configurado)

**Destinatario**: `config.correoalerta`
**Asunto**: "Alerta Error SENCE"
**Cuerpo**: Datos del error (código, mensaje, usuario, RUT, ID acción, código curso)

### 4.5 Eventos de Moodle

Disparados en `render_student_view()`:

- `block_sence\event\session_started` → cuando SENCE confirma inicio exitoso
- `block_sence\event\session_error` → cuando SENCE devuelve `GlosaError`

Ambos escriben en `block_sence_log` y aparecen en Reports > Standard log.

### 4.6 Registros en base de datos

| Tabla | Cuándo se escribe | Qué se guarda |
|---|---|---|
| `block_sence` | Inicio de sesión exitoso (si es el primer acceso) | runalumno, codcurso, idaccion, idsesionalumno, idsesionsence, firstaccess |
| `block_sence_log` | Cada inicio exitoso, cada error, cada envío de avance | userid, courseid, eventtype, + datos específicos del evento |

---

## 5. Tablas de base de datos

### 5.1 `block_sence` — Accesos

| Campo | Tipo | NotNull | Descripción |
|---|---|---|---|
| `id` | int(10) | PK | Autoincremental |
| `runalumno` | char(10) | | RUT sin puntos, con guión |
| `codcurso` | char(100) | | Código SENCE del curso |
| `idaccion` | char(100) | | ID de acción (del grupo) |
| `idsesionalumno` | char(100) | | `{sesskey}-{courseid}` |
| `idsesionsence` | char(100) | | ID devuelto por SENCE |
| `firstaccess` | int(10) | | Timestamp Unix del inicio |

### 5.2 `block_sence_log` — Auditoría

| Campo | Tipo | NotNull | Descripción |
|---|---|---|---|
| `id` | int(10) | PK | Autoincremental |
| `userid` | int(10) | FK→user | Usuario |
| `courseid` | int(10) | FK→course | Curso |
| `eventtype` | char(20) | Sí | `session_started`, `session_error`, `progress_sent`, `progress_error`, `progress_skip` |
| `runalumno` | char(10) | | RUT |
| `codcurso` | char(100) | | Código SENCE |
| `idaccion` | char(100) | | ID de acción |
| `idsesionalumno` | char(100) | | ID sesión LMS |
| `idsesionsence` | char(100) | | ID sesión SENCE |
| `lineacapacitacion` | int(2) | | 1, 3, 6 |
| `errorcode` | int(5) | | Código de error o id_proceso |
| `errormessage` | char(255) | | Mensaje descriptivo |
| `zonahoraria` | char(100) | | Zona horaria SENCE |
| `timecreated` | int(10) | Sí | Timestamp del evento |

Índices: `(eventtype, timecreated)`, `(userid, courseid)`

---

## 6. Flujo completo de asistencia (alumno)

```
El alumno entra al curso de Moodle
│
├── El bloque SENCE se renderiza (get_content)
│   ├── Lee config global + config de instancia
│   ├── Detecta que el usuario es alumno (sin capability viewhiddenactivities)
│   └── Llama a render_student_view()
│
├── render_student_view()
│   ├── Obtiene grupos del alumno
│   ├── resolve_action_id() extrae ID de acción
│   │   ├── Busca grupo con prefijo configurado (SENCE-)
│   │   ├── Para línea 3: extrae última parte después de '-'
│   │   └── Para línea 1: usa codigocurso del bloque directamente
│   │
│   ├── Cuenta accesos previos en block_sence
│   │
│   ├── ¿Es becario? → Mensaje "¡Adelante! No requiere informar SENCE"
│   │
│   ├── ¿POST tiene GlosaError? → Muestra error + email + evento session_error
│   │
│   ├── ¿SESSION tiene idSence-{short}? → Sesión activa
│   │   ├── Muestra "sesión iniciada correctamente"
│   │   ├── Inserta en block_sence (si es primer acceso)
│   │   ├── Dispara evento session_started
│   │   ├── Si forzarcierre: cronómetro JS + form de cierre
│   │   └── Script: intercepta logout de Moodle → envía cierre a SENCE
│   │
│   └── ¿No hay sesión activa? → Mostrar form de inicio
│       ├── Valida RUT (9-10 chars, con guión)
│       ├── Valida código curso (línea 3: ≥7 chars)
│       ├── Valida ID acción (no nulo)
│       ├── Activa sence_pending-{short} = true
│       └── Renderiza form con action = urliniciosesion
│
└── El alumno pulsa "Inicio Sesión SENCE"
    ├── Navegador hace POST a sistemas.sence.cl/rce/Registro/IniciarSesion
    ├── SENCE muestra login de Clave Única
    ├── Alumno ingresa RUT + Clave Única
    ├── SENCE redirige a UrlRetoma (misma URL del curso)
    │   ├── Éxito: POST con IdSesionSence, FechaHora, ZonaHoraria...
    │   └── Error: POST con GlosaError
    └── El plugin recibe el POST y el ciclo se repite
```

---

## 7. Flujo de la API de avance (servidor)

```
Cron de Moodle ejecuta scheduled task a las 22:00
│
├── send_progress::execute()
│   ├── Lee config global: rutotec, tokenotec, apienabled
│   ├── Si apienabled != 1 → aborta
│   ├── Si rutotec o tokenotec vacíos → aborta
│   │
│   ├── extractor::get_sence_courses()
│   │   └── SELECT DISTINCT c.* FROM course JOIN context JOIN block_instances
│   │       WHERE blockname = 'sence'
│   │
│   └── Para cada curso:
│       ├── extractor::get_course_sence_code($courseid)
│       │   └── Lee configdata del bloque → codigocurso
│       │
│       ├── extractor::get_enrolled_students($courseid)
│       │   └── get_enrolled_users() de Moodle API
│       │
│       └── Para cada alumno:
│           ├── Separa RUT: idnumber → rutAlumno + dvAlumno
│           ├── get_student_course_grade() → grade_grades.finalgrade
│           ├── get_student_progress() → course_modules_completion
│           ├── get_student_connect_time() → logstore_standard_log (24h)
│           │
│           └── Para cada módulo (course_sections where section > 0):
│               ├── get_module_grade() → grade_items mod
│               ├── get_module_student_progress() → course_modules_completion
│               ├── get_module_activities() → course_modules + modules
│               ├── count_sync_activities() → BBB/Zoom
│               └── count_async_activities() → resto
│
│       ├── builder::build_payload($config, $codigoOferta, $extracted)
│       ├── builder::validate_payload($payload)
│       ├── builder::to_json($payload)
│       │
│       ├── sender::send_with_retry($json)
│       │   └── POST a auladigital.sence.cl/gestor/API/avance-sic/enviarAvance
│       │       Content-Type: application/json
│       │       Hasta 3 reintentos con 5s de delay
│       │
│       └── Log en block_sence_log
│           eventtype = progress_sent | progress_error | progress_skip
│
└── Fin
```

---

## 8. Variables de estado (sesión PHP)

Todas usan sufijo `-{course->shortname}` para aislar cursos.

| Clave en $SESSION | Tipo | Seteada por | Leída por | Limpiada por |
|---|---|---|---|---|
| `idSence-{short}` | string | `$_POST['IdSesionSence']` | render_student_view | Pending check o nueva carga sin POST |
| `start-{short}` | string | `$_POST['FechaHora']` | Cálculo de timestamp | Pending check |
| `senceStartTimestamp-{short}` | int | `$ts + $sencetimeout` | Cronómetro JS | Pending check |
| `sence_pending-{short}` | bool | `true` (antes del form inicio) | Pending check | Cada carga de página |
| `timezone-{short}` | string | `$_POST['ZonaHoraria']` | Eventos (pasado a factory) | Pending check |

---

## 9. Capabilities

```php
'block/sence:myaddinstance'    // Agregar al dashboard (todos los usuarios)
'block/sence:addinstance'      // Agregar a cursos (editingteacher, manager)
```

**Distinción alumno/gestor**: El plugin usa `has_capability('moodle/course:viewhiddenactivities')` en vez de una capability propia. Si el usuario TIENE esta capability → ve la vista de gestor. Si NO → ve la vista de alumno.

---

## 10. Tabla de errores SENCE (códigos mapeados)

| Código | String de idioma | Significado |
|---|---|---|
| 100 | `senceerror_100` | Contraseña incorrecta (no en manual v1.1.6) |
| 200 | `senceerror_200` | Parámetros vacíos o mal escritos |
| 201 | `senceerror_201` | UrlRetoma/UrlError sin información |
| 202-203 | | URL con formato incorrecto |
| 204 | | CodSence < 10 caracteres o inválido |
| 205 | | CodigoCurso < 7 caracteres o inválido |
| 206 | | Línea de capacitación incorrecta |
| 207 | | RunAlumno formato incorrecto o DV erróneo |
| 208 | | RunAlumno no autorizado |
| 209 | | RutOtec formato incorrecto o DV erróneo |
| 210 | | Sesión expirada |
| 211-212 | | Token inválido/caducado |
| 300-305 | | Errores internos SENCE |
| 306-310 | | Curso no coincide/modalidad/fechas/estado |
| 311-312 | | Autenticación Clave Única |
| 313 | `senceerror_313` | URL de Cierre de sesión Incorrecta |

Códigos de la API de avance: 001-033 (ver `DEVELOPER.md` sección 6.5).

---

## 11. Endpoints externos

### Asistencia

| Ambiente | Inicio | Cierre |
|---|---|---|
| Producción | `https://sistemas.sence.cl/rce/Registro/IniciarSesion` | `.../CerrarSesion` |
| Pruebas | `https://sistemas.sence.cl/rcetest/Registro/IniciarSesion` | `.../CerrarSesion` |

### Avance

| Método | URL |
|---|---|
| POST | `https://auladigital.sence.cl/gestor/API/avance-sic/enviarAvance` |
| GET (test) | `https://auladigital.sence.cl/gestor/API/avance-sic/historialEnvios?rutOtec=X&idSistema=1350&token=Y` |

### Token

| URL | Propósito |
|---|---|
| `https://sistemas.sence.cl/rts` | Generar token (RUT empresa + RUT representante + CUS) |

---

## 12. Puntos de extensión

### Para agregar un nuevo evento Moodle:
1. Crear clase en `classes/event/` extendiendo `\core\event\base`
2. Implementar `init()` (crud, edulevel, objecttable), `get_name()`, `get_description()`, factory method
3. Agregar string en `lang/*/block_sence.php`
4. Disparar con `event::create_from_data([...])->trigger()`

### Para agregar configuración global:
1. `$settings->add(new admin_setting_*(...))` en `settings.php`
2. Leer con `get_config('block_sence', 'nuevo_campo')`
3. Agregar strings

### Para agregar configuración por instancia:
1. `$mform->addElement(...)` en `edit_form.php` → `specific_definition()`
2. Leer con `$this->config->nuevo_campo`
3. Agregar strings

### Para extender la API de avance:
1. Nuevo método en `classes/progress/extractor.php`
2. Modificar `builder::build_payload()` para incluir nuevos campos
3. Si el endpoint cambia, modificar `sender::API_URL`

### Para agregar una nueva página:
1. Crear archivo PHP en `blocks/sence/`
2. `require_once __DIR__ . '/../../config.php'`
3. `require_login()` + capability check
4. Usar `$PAGE`, `$OUTPUT`, `$DB` normalmente
