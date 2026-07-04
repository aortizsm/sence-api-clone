A continuación, presento el documento Markdown (`.md`) con la especificación técnica exhaustiva para construir tu **Mock Server de SENCE en CodeIgniter 4**. 

Este documento está diseñado para que lo copies y pegues en tu repositorio (ej. `SENCE_MOCK_SPEC.md`). Contiene **exactamente** las estructuras de respuesta, formatos y lógicas de validación exigidas por la documentación oficial, adaptadas para ejecutarse localmente con `php spark serve`.

***

# Especificación Técnica: Mock Server SENCE (CodeIgniter 4)

## 1. Consideraciones Críticas de Arquitectura
El sistema de SENCE no es una API REST tradicional unificada. Consta de **dos servicios independientes** con comportamientos HTTP y formatos de respuesta completamente distintos. Tu Mock en CodeIgniter 4 debe emular ambos comportamientos de manera aislada.

### 1.1. Diferencia Fundamental en las Respuestas
*   **Servicio 1: Registro de Asistencia (RCE):** **NO devuelve JSON**. Recibe un `POST` (form-data) y devuelve un **Redireccionamiento HTTP vía POST** (usando un formulario HTML auto-enviable) hacia la `UrlRetoma` o `UrlError` proporcionada por Moodle.
    *   *Referencia:* `integracion_registro_asistencia_sence_v1.1.6.pdf`, Página 9, Párrafo "En caso de inicio de sesión exitoso, el sistema SENCE redireccionará a la URL... enviando los siguientes parámetros vía POST".
*   **Servicio 2: API Gestor Intermedio (Avance SIC):** **SÍ devuelve JSON**. Recibe un `POST` (raw JSON) y devuelve una respuesta JSON directa en el cuerpo de la respuesta HTTP.
    *   *Referencia:* `instructivo_tecnico_de_integracion_entre_lms_y_sic_v2.0_0.pdf`, Página 12, Tabla "Envío de Datos a API REST".

### 1.2. Mapeo de Rutas (app/Config/Routes.php)
Para que tu plugin de Moodle no detecte la diferencia, debes mapear las rutas de `localhost:8080` para que coincidan exactamente con las URLs de SENCE.

```php
// app/Config/Routes.php
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');

// --- RCE (Registro de Asistencia) ---
// Mapeo para Ambiente Test (Pág 15, Anexo 1)
$routes->post('rcetest/Registro/IniciarSesion', 'SenceRce::iniciarSesion');
$routes->post('rcetest/Registro/CerrarSesion', 'SenceRce::cerrarSesion');

// --- API Gestor Intermedio (Avance SIC) ---
// Mapeo exacto de los Endpoints (Pág 12 y Pág 40)
$routes->post('gestor/API/avance-sic/enviarAvance', 'SenceSic::enviarAvance');
$routes->get('gestor/API/avance-sic/historialEnvios', 'SenceSic::historialEnvios');
```

---

## 2. Servicio 1: Mock RCE (Registro de Asistencia)

### 2.1. Validaciones y Disparadores de Error (GlosaError)
El controlador `SenceRce` debe validar los parámetros recibidos y, si fallan, redirigir a `UrlError` inyectando el código de error (`GlosaError`).

**Códigos de Error a Simular (Tabla de Errores):**
*   **200:** Parámetros mandatorios vacíos o mal escritos (ej. `RutAlumno` en vez de `RunAlumno`). *(Pág 16)*
*   **201 / 202 / 203:** `UrlRetoma` o `UrlError` vacías o con formato incorrecto. *(Pág 16)*
*   **205:** `CodigoCurso` tiene menos de 7 caracteres (Excepto si es `-1` en Test). *(Pág 16)*
*   **211 / 212 / 303:** Token inválido, no vigente o formato incorrecto. *(Pág 16)*
*   **311:** El RUT ingresado en el login no coincide con `RunAlumno`. *(Pág 17)*
*   **313:** URL de Cierre de sesión Incorrecta. *(Pág 17 - Nuevo en v1.1.6)*

### 2.2. Estructura Exacta de Respuesta (Redirect POST)
Cuando la validación es exitosa (o falla), el Mock **no usa `return redirect()->to()`** de CI4 (ya que eso hace un GET). Debe renderizar una vista HTML con un `<form method="POST">` y un `<script>document.form.submit();</script>`.

#### A. Respuesta de Éxito - Inicio de Sesión
*Redirige a `UrlRetoma` con los siguientes campos POST exactos:*
*(Referencia: `integracion_registro_asistencia_sence_v1.1.6.pdf`, Página 9, Tabla de parámetros de éxito)*

| Parámetro | Tipo | Valor a Inyectar en el Mock |
| :--- | :--- | :--- |
| `CodSence` | Texto | El mismo recibido (o `-1`) |
| `CodigoCurso` | Texto | El mismo recibido (o `-1`) |
| `IdSesionAlumno` | Texto | El mismo recibido |
| `IdSesionSence` | Texto | **Generar UUID o Timestamp** (Ej: `MOCK-SESS-` . time()) |
| `RunAlumno` | Texto | El mismo recibido |
| `FechaHora` | Texto | `date('Y-m-d H:i:s')` |
| `ZonaHoraria` | Texto | `America/Santiago` |
| `LineaCapacitacion`| Entero | El mismo recibido (1, 3 o 6) |

#### B. Respuesta de Éxito - Cierre de Sesión
*Redirige a `UrlRetoma` con los siguientes campos POST exactos:*
*(Referencia: `integracion_registro_asistencia_sence_v1.1.6.pdf`, Página 12, Tabla de parámetros de éxito)*
**¡Atención!** Aquí **NO** se envía `IdSesionSence`.

| Parámetro | Tipo | Valor a Inyectar en el Mock |
| :--- | :--- | :--- |
| `CodSence` | Texto | El mismo recibido |
| `CodigoCurso` | Texto | El mismo recibido |
| `IdSesionAlumno` | Texto | El mismo recibido |
| `RunAlumno` | Texto | El mismo recibido |
| `FechaHora` | Texto | `date('Y-m-d H:i:s')` |
| `ZonaHoraria` | Texto | `America/Santiago` |
| `LineaCapacitacion`| Entero | El mismo recibido |

#### C. Respuesta de Error (Inicio o Cierre)
*Redirige a `UrlError` con los campos de éxito correspondientes + `GlosaError`.*
*(Referencia: `integracion_registro_asistencia_sence_v1.1.6.pdf`, Páginas 10 y 12)*

---

## 3. Servicio 2: Mock API Gestor Intermedio (Avance SIC)

### 3.1. Validaciones y Disparadores de Error (JSON)
El controlador `SenceSic` debe leer el `raw JSON` y validar:
1.  **Token en Mayúsculas:** Si el token tiene minúsculas, devolver Error **001**. *(Ref: v2.0, Pág 17, Pregunta Frecuente #11)*.
2.  **idSistema:** Debe ser exactamente `1350`. Si no, devolver Error **001**. *(Ref: v2.0, Pág 10)*.
3.  **Retroceso de Avance (Error 025):** El Mock debe guardar en sesión/DB el último `porcentajeAvance` por `rutAlumno` y `codigoModulo`. Si el nuevo JSON envía un porcentaje menor, devolver Error **025**. *(Ref: v2.0, Pág 14)*.
4.  **Fechas:** `fechaFin` no puede ser menor a `fechaInicio`. Formato estricto `YYYY-MM-DD`. *(Ref: v2.0, Pág 6-7)*.

### 3.2. Estructura Exacta de Respuesta JSON (enviarAvance)
*(Referencia: `instructivo_tecnico_de_integracion_entre_lms_y_sic_v2.0_0.pdf`)*

#### A. Respuesta de Éxito (Página 12)
```json
{
  "id_proceso": 25,
  "envio": [
    {
      "rutAlumno": 15943354,
      "dvAlumno": "4",
      "tiempoConectividad": 30,
      "porcentajeAvance": 10,
      "estado": 1,
      "fechaInicio": "2021-04-01 00:00:00",
      "fechaFin": "2021-08-01 00:00:00",
      "fechaEjecucion": "2021-07-22 00:00:00",
      "listaModulos": [
        {
          "codigoModulo": "C51737-O14-M1",
          "tiempoConectividad": 1,
          "porcentajeAvance": 10,
          "estado": 1,
          "fechaInicio": "2021-04-01",
          "fechaFin": "2021-08-01",
          "listaActividades": [
            {
              "codigoActividad": "Tarea de prueba"
            }
          ]
        }
      ]
    }
  ],
  "errores": [],
  "respuesta_SIC": "El proceso 25 ha finalizado correctamente, para ver la cantidad de registros y detalle, diríjase a la pantalla de procesos"
}
```

#### B. Respuesta de Error por Alumno (Página 13)
*Nota: La API procesa los alumnos correctos y solo rechaza los incorrectos en el array `datosError`.*
```json
{
  "id_proceso": 31,
  "datosEnviados": [],
  "datosError": [
    {
      "alumno": {
        "rutAlumno": "11111111",
        "dvAlumno": "5",
        "tiempoConectividad": 6000,
        "porcentajeAvance": 20,
        "estado": 1,
        "fechaInicio": "2021-04-01 00:00:00",
        "fechaFin": "2021-08-01 00:00:00",
        "listaModulos": [
          {
            "codigoModulo": "C51737-O14-M1",
            "tiempoConectividad": 600,
            "porcentajeAvance": 50,
            "estado": 1,
            "fechaInicio": "2021-04-01",
            "fechaFin": "2021-08-01",
            "listaActividades": [
              {
                "codigoActividad": "Tarea de prueba"
              }
            ]
          }
        ]
      },
      "codigo": "012",
      "mensaje": "El alumno 11111111-5 no se encuentra registrado."
    }
  ],
  "respuesta_SIC": ""
}
```
*(Códigos de error a simular en `codigo`: 001, 010, 011, 012, 021 a 033. Ver Tabla Páginas 14-15).*

### 3.3. Estructura Exacta de Respuesta JSON (historialEnvios)
*(Referencia: `instructivo_tecnico...v2.0_0.pdf`, Páginas 41-42)*
```json
[
  {
    "id_proceso_externo": "30",
    "id_lms": "0",
    "codigo_externo": "qa-f-2",
    "fecha": "2021-07-27 02:11:13",
    "horario": null,
    "observaciones": "El proceso 30 ha finalizado correctamente...",
    "observaciones_sic": null,
    "status_code": "0",
    "estado": "2",
    "rut_otec": "77124930",
    "listaregistros": [
      {
        "id_registro": "44",
        "rut_otec": "77124930",
        "dv_otec": "2",
        "oferta_cod": "AYSEN-20-03-11-0007",
        "seccion_cod": "C51737-O14-M2",
        "rut_alumno": "9562011",
        "dv_alumno": "6",
        "tiempo_conectividad": "200",
        "estado": "1",
        "porcentaje_avance": "10.00000",
        "fecha_inicio": "2021-04-01 00:00:00",
        "fecha_fin": "2021-08-01 00:00:00",
        "fecha_ejecucion": "2021-07-27 20:37:06",
        "mod_cod": "C51737-O14-M2",
        "mod_tiempo_conectividad": "120",
        "mod_estado": "1",
        "mod_porcentaje_avance": "10.00000",
        "mod_fecha_inicio": "2021-04-01 00:00:00",
        "mod_fecha_fin": "2021-08-01 00:00:00",
        "mod_obligatorio": null,
        "mod_nota": null,
        "act_cod": null,
        "act_estado": null,
        "estado_registro": "3",
        "obs_registro": "[010] modulo.porcentajeAvance debe ser mayor al anterior(20.00000)",
        "id_proceso_externo": "30"
      }
    ]
  }
]
```

---

## 4. Implementación Base en CodeIgniter 4

### 4.1. Controlador RCE (Simulando Redirect POST)
Dado que SENCE hace un POST a la URL de retorno, en CI4 debes renderizar una vista que auto-envíe el formulario.

```php
// app/Controllers/SenceRce.php
namespace App\Controllers;

class SenceRce extends BaseController
{
    public function iniciarSesion()
    {
        $rutOtec = $this->request->getPost('RutOtec');
        $token = $this->request->getPost('Token');
        $urlRetoma = $this->request->getPost('UrlRetoma');
        $urlError = $this->request->getPost('UrlError');

        // 1. Validación Mock (Ejemplo: Token en Mayúsculas y idSistema no aplica aquí, pero sí Token)
        if (empty($rutOtec) || empty($token) || empty($urlRetoma) || empty($urlError)) {
            return $this->renderErrorRedirect($urlError, $this->request->getPost(), '200');
        }

        // 2. Construir datos de Éxito (Pág 9)
        $datosExito = [
            'CodSence' => $this->request->getPost('CodSence'),
            'CodigoCurso' => $this->request->getPost('CodigoCurso'),
            'IdSesionAlumno' => $this->request->getPost('IdSesionAlumno'),
            'IdSesionSence' => 'MOCK-' . time(), // Generado por el Mock
            'RunAlumno' => $this->request->getPost('RunAlumno'),
            'FechaHora' => date('Y-m-d H:i:s'),
            'ZonaHoraria' => 'America/Santiago',
            'LineaCapacitacion' => $this->request->getPost('LineaCapacitacion')
        ];

        // 3. Renderizar formulario auto-enviable a UrlRetoma
        return $this->renderPostRedirect($urlRetoma, $datosExito);
    }

    private function renderPostRedirect($url, $data)
    {
        $html = '<html><body><form id="sence_mock" action="' . htmlspecialchars($url) . '" method="POST">';
        foreach ($data as $key => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }
        $html .= '</form><script>document.getElementById("sence_mock").submit();</script></body></html>';
        return $html;
    }
    
    private function renderErrorRedirect($urlError, $originalPost, $glosaError)
    {
        $data = $originalPost;
        $data['GlosaError'] = $glosaError;
        $data['FechaHora'] = date('Y-m-d H:i:s');
        $data['ZonaHoraria'] = 'America/Santiago';
        return $this->renderPostRedirect($urlError, $data);
    }
}
```

### 4.2. Controlador SIC (Respuestas JSON Exactas)
```php
// app/Controllers/SenceSic.php
namespace App\Controllers;

class SenceSic extends BaseController
{
    public function enviarAvance()
    {
        // Asegurar Content-Type JSON
        $json = $this->request->getJSON(true);
        
        $token = $json['token'] ?? '';
        $idSistema = $json['idSistema'] ?? 0;

        // Validación Mock 1: idSistema debe ser 1350 (Pág 10)
        if ($idSistema !== 1350) {
            return $this->response->setJSON([
                "id_proceso" => 0,
                "datosEnviados" => [],
                "datosError" => [["codigo" => "001", "mensaje" => "idSistema incorrecto, debe ser 1350"]],
                "respuesta_SIC" => ""
            ]);
        }

        // Validación Mock 2: Token en Mayúsculas (Pág 17)
        if ($token !== strtoupper($token)) {
            return $this->response->setJSON([
                "id_proceso" => 0,
                "datosEnviados" => [],
                "datosError" => [["codigo" => "001", "mensaje" => "Token inválido. Debe estar en mayúsculas."]],
                "respuesta_SIC" => ""
            ]);
        }

        // Simulación de Éxito (Estructura exacta de Pág 12)
        $respuestaExito = [
            "id_proceso" => rand(100, 999),
            "envio" => $json['listaAlumnos'] ?? [], // Devolvemos lo que nos enviaron para debug
            "errores" => [],
            "respuesta_SIC" => "El proceso " . rand(100, 999) . " ha finalizado correctamente, para ver la cantidad de registros y detalle, diríjase a la pantalla de procesos"
        ];

        return $this->response->setJSON($respuestaExito);
    }
}
```

---

## 5. Datos de Prueba Hardcodeados (Para tu Plugin de Moodle)
Configura tu plugin de Moodle con estos datos extraídos de los documentos para que el Mock los acepte sin problemas:

*   **RUT OTEC:** `77124930-2` *(Ref: estructura_de_pruebas_para_integracion.pdf / v2.0 Pág 41)*
*   **Token:** `5EEBF607-25A9-4DB2-A4DD-5D31BDAE3220` *(Ref: v2.0 Pág 10 - Recuerda enviarlo en MAYÚSCULAS)*
*   **idSistema:** `1350` *(Ref: v2.0 Pág 10)*
*   **RUTs Alumnos de Prueba:**
    *   `9445435-2` (GUSTAVO RAMÓN JARA ORTIZ)
    *   `10312870-6` (HILDA MAGDALENA BEZERRA SAAVEDRA)
    *   `10176851-1` (MARIBEL IRENE OLGUÍN CONTRERAS)
    *(Ref: estructura_de_pruebas_para_integracion.pdf, Tabla de RUTs)*
*   **Código Curso / Oferta:** `CAP-15-01-13-0446` *(Ref: estructura_de_pruebas_para_integracion.pdf)*
*   **Código Módulo:** `C46982-O565656565-M1` *(Ref: estructura_de_pruebas_para_integracion.pdf)*
*   **URLs para configurar en Moodle:**
    *   RCE Inicio: `http://localhost:8080/rcetest/Registro/IniciarSesion`
    *   RCE Cierre: `http://localhost:8080/rcetest/Registro/CerrarSesion`
    *   SIC Avance: `http://localhost:8080/gestor/API/avance-sic/enviarAvance`

---
**Nota para el Desarrollador:** Al ejecutar `php spark serve`, CodeIgniter 4 escuchará en el puerto 8080. Asegúrate de que las variables `UrlRetoma` y `UrlError` que configures en tu plugin de Moodle apunten a la IP pública o dominio de tu máquina de desarrollo (ej. `http://192.168.1.50:8080/retoma.php`), ya que si usas `localhost`, el servidor CI4 no podrá hacer el callback a tu propio Moodle si están en contenedores o redes distintas. Si todo corre en la misma máquina local, `localhost` funcionará correctamente.

---

## 6. Sistema de Forzado de Errores (Error Injection)

Para probar cómo reacciona el plugin de Moodle ante distintos códigos de error, el Mock incluye un sistema de inyección de errores controlado desde el Dashboard.

### 6.1. Dashboard de Errores

| URL | Método | Descripción |
| :--- | :--- | :--- |
| `http://localhost:8080/sence/dashboard/errores` | GET/POST | Panel de gestión de errores forzados |

### 6.2. Cómo funciona

1. Se accede al Dashboard y se navega a "Forzar Errores"
2. Se selecciona el endpoint, código de error y número de peticiones a fallar
3. Las siguientes N peticiones a ese endpoint devolverán el error configurado
4. Una vez consumidas las peticiones, el endpoint vuelve a su comportamiento normal

### 6.3. Endpoints que soportan forzado

| Endpoint ID | Servicio | Errores disponibles |
| :--- | :--- | :--- |
| `rce_inicio` | RCE IniciarSesion | 200, 201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 300, 303, 311, 313 |
| `rce_cierre` | RCE CerrarSesion | (mismos que inicio) |
| `sic_avance` | SIC enviarAvance | 001, 003, 010, 011, 012, 021-033 |
| `sic_historial` | SIC historialEnvios | Cualquier código (devuelve HTTP 500) |

### 6.4. Tabla: `sence_error_queue`

| Campo | Tipo | Descripción |
| :--- | :--- | :--- |
| `id` | INTEGER PK | Autoincremental |
| `endpoint` | VARCHAR(50) | Identificador del endpoint a fallar |
| `codigo_error` | VARCHAR(10) | Código de error a devolver |
| `peticiones_restantes` | INTEGER | Contador de peticiones pendientes (0 = inactivo) |
| `glosa_error` | TEXT | Mensaje personalizado (opcional) |
| `created_at` | DATETIME | Timestamp |

---

## 7. Estructura del Proyecto

```
sence-api-clone/
├── .env                          # Configuracion SQLite + datos prueba + guia Moodle
├── app/
│   ├── Config/
│   │   └── Routes.php            # 8 rutas (RCE test+prod, SIC, Dashboard)
│   ├── Controllers/
│   │   ├── SenceRce.php          # Servicio RCE (inicio/cierre sesion + redirect POST)
│   │   ├── SenceSic.php          # Servicio SIC (avance + historial + JSON)
│   │   └── SenceDashboard.php    # Dashboard (resumen, sesiones, procesos, errores)
│   ├── Database/Migrations/
│   │   ├── 001_CreateSenceSesiones
│   │   ├── 002_CreateSenceProcesos
│   │   ├── 003_CreateSenceAvances
│   │   ├── 004_CreateSenceProcesoRegistros
│   │   └── 005_CreateSenceErrorQueue
│   ├── Models/
│   │   ├── SenceSesionModel.php
│   │   ├── SenceProcesoModel.php
│   │   ├── SenceAvanceModel.php
│   │   ├── SenceProcesoRegistroModel.php
│   │   └── SenceErrorQueueModel.php
│   └── Views/sence/
│       ├── redirect.php           # HTML form auto-submit para RCE
│       └── dashboard.php          # Panel de control
├── writable/
│   └── sence_mock.db             # SQLite (se crea al ejecutar migraciones)
└── docs/
    ├── idea.md                   # Este documento
    └── *.txt                     # Documentacion oficial SENCE de referencia
```

---

## 8. Changelog

| Versión | Cambios |
| :--- | :--- |
| **1.2.0** | + Fix 1: `CodSence` 10 dígitos obligatorio para línea 3 (error 204 automático). + Fix 2: `CodSence` debe ir en blanco para línea 1 (error 206). + Fix 3: FPT (línea 6) sin mínimo de 7 caracteres en `CodigoCurso`. + Fix 4: Campos `cantActividadSincronica`/`cantActividadAsincronica` del curso validados (errores 032/033). + Fix 5: Validación rango 0-100 en `porcentajeAvance` (errores 022/024 automáticos). + Fix 6: Validación `estado` debe ser 1/2/3 a nivel alumno y módulo. + Fix 7: Validación `fechaEjecucion` entre `fechaInicio` y `fechaFin` (error 026 automático). |
| **1.1.0** | + Sistema de forzado de errores (tabla `sence_error_queue` + dashboard). + Fix error 200 vs 201 (UrlRetoma/UrlError vacías ahora retornan 201). + Fix `codigoEnvio` orden de lectura (primero `codigoEnvio`, fallback a `codigoExterno`). + Validación `fechaFin >= fechaInicio` a nivel alumno y módulo. + Rutas de producción (`rce/`). + `.env` con guía de configuración de Moodle. |
| **1.0.0** | Mock inicial. Servicios RCE (inicio/cierre sesión con redirect POST) y SIC (avance con JSON + historialEnvios). SQLite para persistencia. Dashboard básico. |