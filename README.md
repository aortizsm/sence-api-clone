# SENCE API Mock Server

Mock server que emula los dos servicios web de SENCE (Registro de Asistencia y API Gestor Intermedio) para desarrollo y testing de integraciones LMS sin necesidad de credenciales reales de SENCE.

## Por qué existe

Al desarrollar un plugin de SENCE para Moodle u otro LMS, necesitas un token y un RUT OTEC válido emitido por SENCE para probar la integración. Conseguir esas credenciales puede tomar días o semanas, y las pruebas contra los servidores reales de SENCE implican registros que no se pueden borrar.

Este mock server **reemplaza completamente los servidores de SENCE en tu entorno local**, permitiéndote:

- Desarrollar sin esperar credenciales oficiales
- Probar todos los códigos de error sin depender del estado real de SENCE
- Forzar errores específicos para verificar el manejo en tu plugin
- Iterar rápido sin preocuparte por afectar datos de producción

## Lo que emula

| Servicio | Endpoints | Respuesta |
|----------|-----------|-----------|
| **RCE** — Registro de Asistencia | `POST /rce/Registro/IniciarSesion`<br>`POST /rce/Registro/CerrarSesion` | HTML form auto-submit (redirect POST) |
| **SIC** — API Gestor Intermedio | `POST /gestor/API/avance-sic/enviarAvance`<br>`GET /gestor/API/avance-sic/historialEnvios` | JSON |

## Requisitos

- PHP 8.2+
- SQLite3 habilitado
- Composer

## Instalación

```bash
git clone <repo-url> sence-api-clone
cd sence-api-clone
composer install
php spark migrate
```

## Uso

```bash
php spark serve
```

El servidor arranca en `http://localhost:8080`.

### Dashboard

```
http://localhost:8080/sence/dashboard
```

El dashboard existe porque durante el desarrollo necesitás **visibilidad de lo que está pasando** entre tu plugin y el mock. Sin él, trabajarías a ciegas: hacés clic en "Inicio Sesión SENCE" en Moodle, el navegador te redirige a SENCE, SENCE te devuelve, y no tenés forma de saber si los parámetros se enviaron bien, si la sesión se creó, o qué error devolvió el mock.

El dashboard te permite:

- **Ver en tiempo real** las sesiones RCE creadas, con su estado (activa/cerrada), RUT, código de curso, IdSesionSence y timestamp
- **Revisar los procesos SIC** enviados por el cron de Moodle: id_proceso, oferta, código externo, estado, fecha
- **Auditar los avances** guardados por alumno y módulo — incluyendo el % histórica para validar el error 025 (regresión de avance)
- **Forzar errores** desde una UI en vez de tener que modificar código o enviar datos inválidos a propósito
- **Limpiar todo** con un botón para reiniciar las pruebas desde cero

Sin el dashboard, cada prueba implicaría revisar la base SQLite a mano o leer logs — el dashboard te ahorra ese tiempo y te da feedback inmediato.

### Configurar tu plugin de Moodle

| Configuración | Valor |
|---------------|-------|
| RUT OTEC | `77124930-2` |
| Token OTEC | `5EEBF607-25A9-4DB2-A4DD-5D31BDAE3220` |
| URL inicio sesión | `http://localhost:8080/rce/Registro/IniciarSesion` |
| URL cierre sesión | `http://localhost:8080/rce/Registro/CerrarSesion` |
| Prefijo grupo | `SENCE-` |
| Código Curso (bloque) | `-1` |
| Línea Capacitación | `3` |
| Grupo en el curso | `SENCE--1` |
| RUTs de alumnos (idnumber) | `9445435-2`, `10312870-6`, `10176851-1` |

Los valores están documentados con más detalle en `.env`.

## Forzar errores

Desde el dashboard podés activar errores forzados: seleccionás el endpoint, el código de error y cuántas peticiones deben fallar. Las siguientes N llamadas devolverán ese error.

Ideal para probar cómo reacciona tu plugin ante cada código de error de SENCE sin tener que provocar condiciones reales.

## Licencia

MIT — libre para uso comercial y no comercial. Este proyecto no está afiliado ni respaldado por SENCE. Usa documentación pública de SENCE como referencia para los formatos de respuesta y códigos de error.

## Documentación para agentes IA

`AGENT.md` contiene la especificación completa del proyecto orientada a que otro agente de IA pueda entenderlo, extenderlo o corregirlo sin leer el código fuente.
