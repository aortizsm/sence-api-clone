<?php

namespace App\Controllers;

use App\Models\SenceAvanceModel;
use App\Models\SenceProcesoModel;
use App\Models\SenceProcesoRegistroModel;
use App\Models\SenceErrorQueueModel;

class SenceSic extends BaseController
{
    protected string $rutOtecValido;
    protected string $tokenValido;
    protected int $idSistemaValido;
    protected array $alumnosValidos;

    public function __construct()
    {
        $this->rutOtecValido   = env('sence.rutOtec', '77124930-2');
        $this->tokenValido     = env('sence.token', '5EEBF607-25A9-4DB2-A4DD-5D31BDAE3220');
        $this->idSistemaValido = (int)env('sence.idSistema', 1350);
        $this->alumnosValidos  = [
            '9445435'  => ['dv' => '2', 'nombre' => 'GUSTAVO RAMON JARA ORTIZ'],
            '10312870' => ['dv' => '6', 'nombre' => 'HILDA MAGDALENA BEZERRA SAAVEDRA'],
            '10176851' => ['dv' => '1', 'nombre' => 'MARIBEL IRENE OLGUIN CONTRERAS'],
        ];
    }

    public function enviarAvance()
    {
        $json = $this->request->getJSON(true);
        log_message('info', '[SIC] POST /gestor/API/avance-sic/enviarAvance | rutOtec=' . ($json['rutOtec'] ?? 'N/A') . ' | alumnos=' . count($json['listaAlumnos'] ?? []));

        $forcedError = (new SenceErrorQueueModel())->getPending('sic_avance');
        if ($forcedError) {
            (new SenceErrorQueueModel())->decrement($forcedError);
            log_message('warning', '[SIC] enviarAvance ERROR FORZADO {code} | restantes={rest}', ['code' => $forcedError['codigo_error'], 'rest' => $forcedError['peticiones_restantes'] - 1]);
            return $this->forcedErrorResponse($forcedError['codigo_error']);
        }

        if ($json === null) {
            log_message('warning', '[SIC] enviarAvance ERROR 001 | JSON invalido o vacio');
            return $this->errorResponse('001', 'JSON invalido o vacio.');
        }

        $token     = $json['token'] ?? '';
        $rutOtec   = $json['rutOtec'] ?? '';
        $idSistema = $json['idSistema'] ?? 0;

        if ((int)$idSistema !== $this->idSistemaValido) {
            log_message('warning', '[SIC] enviarAvance ERROR 001 | idSistema={got} esperado={exp}', ['got' => $idSistema, 'exp' => $this->idSistemaValido]);
            return $this->response->setJSON([
                'id_proceso'    => 0,
                'datosEnviados' => [],
                'datosError'    => [['codigo' => '001', 'mensaje' => 'idSistema incorrecto, debe ser ' . $this->idSistemaValido]],
                'respuesta_SIC' => '',
            ]);
        }

        if ($token !== strtoupper($token)) {
            log_message('warning', '[SIC] enviarAvance ERROR 001 | token con minusculas');
            return $this->response->setJSON([
                'id_proceso'    => 0,
                'datosEnviados' => [],
                'datosError'    => [['codigo' => '001', 'mensaje' => 'Token invalido. Debe estar en mayusculas.']],
                'respuesta_SIC' => '',
            ]);
        }

        $alumnos      = $json['listaAlumnos'] ?? [];
        $codigoOferta = $json['codigoOferta'] ?? '';
        $codigoGrupo  = $json['codigoGrupo'] ?? '';
        $codigoEnvio  = $json['codigoEnvio'] ?? ($json['codigoExterno'] ?? '');

        $cantActividadSincronica  = (int)($json['cantActividadSincronica'] ?? 0);
        $cantActividadAsincronica = (int)($json['cantActividadAsincronica'] ?? 0);

        if ($cantActividadSincronica < 0) {
            return $this->forcedErrorResponse('032', 'curso.cantActividadSincronica invalido');
        }
        if ($cantActividadAsincronica < 0) {
            return $this->forcedErrorResponse('033', 'curso.cantActividadAsincronica invalido');
        }

        $idProceso = (string)rand(100, 999);

        $errores    = [];
        $enviados   = [];
        $erroresDetalle = [];
        $avanceModel    = new SenceAvanceModel();

        foreach ($alumnos as $index => $alumno) {
            $rutAlumno        = (string)($alumno['rutAlumno'] ?? '');
            $dvAlumno         = $alumno['dvAlumno'] ?? '';
            $modulos          = $alumno['listaModulos'] ?? [];

            $estado = (int)($alumno['estado'] ?? 1);
            if (!in_array($estado, [1, 2, 3], true)) {
                $erroresDetalle[] = [
                    'alumno'  => $alumno,
                    'codigo'  => '002',
                    'mensaje' => "estado {$estado} debe ser 1, 2 o 3. Alumno {$rutAlumno}-{$dvAlumno}.",
                ];
                continue;
            }

            $porcentajeAlumno = (float)($alumno['porcentajeAvance'] ?? 0);
            if ($porcentajeAlumno < 0 || $porcentajeAlumno > 100) {
                $erroresDetalle[] = [
                    'alumno'  => $alumno,
                    'codigo'  => '022',
                    'mensaje' => "porcentajeAvance {$porcentajeAlumno} fuera de rango 0-100. Alumno {$rutAlumno}-{$dvAlumno}.",
                ];
                continue;
            }

            $fechaInicio    = $alumno['fechaInicio'] ?? '';
            $fechaFin       = $alumno['fechaFin'] ?? '';
            $fechaEjecucion = $alumno['fechaEjecucion'] ?? '';

            if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) {
                $erroresDetalle[] = [
                    'alumno'  => $alumno,
                    'codigo'  => '027',
                    'mensaje' => "fechaFin ({$fechaFin}) no puede ser menor a fechaInicio ({$fechaInicio}). Alumno {$rutAlumno}-{$dvAlumno}.",
                ];
                continue;
            }

            if ($fechaInicio && $fechaFin && $fechaEjecucion) {
                if ($fechaEjecucion < $fechaInicio || $fechaEjecucion > $fechaFin) {
                    $erroresDetalle[] = [
                        'alumno'  => $alumno,
                        'codigo'  => '026',
                        'mensaje' => "fechaEjecucion ({$fechaEjecucion}) debe estar entre fechaInicio ({$fechaInicio}) y fechaFin ({$fechaFin}). Alumno {$rutAlumno}-{$dvAlumno}.",
                    ];
                    continue;
                }
            }

            foreach ($modulos as $modulo) {
                $codigoModulo      = $modulo['codigoModulo'] ?? '';
                $porcentajeModulo  = (float)($modulo['porcentajeAvance'] ?? 0);

                if ($porcentajeModulo < 0 || $porcentajeModulo > 100) {
                    $erroresDetalle[] = [
                        'alumno'  => $alumno,
                        'codigo'  => '024',
                        'mensaje' => "modulo.porcentajeAvance {$porcentajeModulo} fuera de rango 0-100. Alumno {$rutAlumno}-{$dvAlumno}, Modulo {$codigoModulo}.",
                    ];
                    continue;
                }

                $modEstado = (int)($modulo['estado'] ?? 1);
                if (!in_array($modEstado, [1, 2, 3], true)) {
                    $erroresDetalle[] = [
                        'alumno'  => $alumno,
                        'codigo'  => '002',
                        'mensaje' => "modulo.estado {$modEstado} debe ser 1, 2 o 3. Alumno {$rutAlumno}-{$dvAlumno}, Modulo {$codigoModulo}.",
                    ];
                    continue;
                }

                $modFechaInicio = $modulo['fechaInicio'] ?? '';
                $modFechaFin    = $modulo['fechaFin'] ?? '';
                if ($modFechaInicio && $modFechaFin && $modFechaFin < $modFechaInicio) {
                    $erroresDetalle[] = [
                        'alumno'  => $alumno,
                        'codigo'  => '027',
                        'mensaje' => "modulo.fechaFin ({$modFechaFin}) no puede ser menor a modulo.fechaInicio ({$modFechaInicio}). Alumno {$rutAlumno}-{$dvAlumno}, Modulo {$codigoModulo}.",
                    ];
                    continue;
                }

                $lastAvance = $avanceModel->getLastAvance($rutAlumno, $codigoModulo);
                if ($lastAvance && $porcentajeModulo < (float)$lastAvance['porcentaje_avance']) {
                    $erroresDetalle[] = [
                        'alumno'  => $alumno,
                        'codigo'  => '025',
                        'mensaje' => "modulo.porcentajeAvance debe ser mayor al anterior ({$lastAvance['porcentaje_avance']}). Alumno {$rutAlumno}-{$dvAlumno}, Modulo {$codigoModulo}.",
                    ];
                    continue;
                }
            }

            $enviados[] = $alumno;
        }

        $procesoModel = new SenceProcesoModel();
        $procesoId = $procesoModel->insert([
            'id_proceso'     => $idProceso,
            'rut_otec'       => $rutOtec,
            'codigo_oferta'  => $codigoOferta,
            'codigo_grupo'   => $codigoGrupo,
            'codigo_externo' => $codigoEnvio,
            'estado'         => empty($erroresDetalle) ? '2' : '1',
            'status_code'    => '0',
            'respuesta_sic'  => '',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $registroModel = new SenceProcesoRegistroModel();

        foreach ($enviados as $alumno) {
            $rutAlumno = (string)($alumno['rutAlumno'] ?? '');
            $dvAlumno  = $alumno['dvAlumno'] ?? '';
            $modulos   = $alumno['listaModulos'] ?? [];

            foreach ($modulos as $modulo) {
                $codigoModulo     = $modulo['codigoModulo'] ?? '';
                $porcentajeModulo = (float)($modulo['porcentajeAvance'] ?? 0);

                $avanceModel->insert([
                    'rut_alumno'        => $rutAlumno,
                    'dv_alumno'         => $dvAlumno,
                    'codigo_modulo'     => $codigoModulo,
                    'porcentaje_avance' => number_format($porcentajeModulo, 5, '.', ''),
                    'proceso_id'        => $procesoId,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);

                $actividades = $modulo['listaActividades'] ?? [];
                $actCod = !empty($actividades) ? $actividades[0]['codigoActividad'] ?? null : null;

                $registroModel->insert([
                    'proceso_id'              => $procesoId,
                    'rut_otec'                => $rutOtec,
                    'dv_otec'                 => '2',
                    'oferta_cod'              => $codigoOferta,
                    'seccion_cod'             => $codigoModulo,
                    'rut_alumno'              => $rutAlumno,
                    'dv_alumno'               => $dvAlumno,
                    'tiempo_conectividad'     => (string)($alumno['tiempoConectividad'] ?? '0'),
                    'estado'                  => (string)($alumno['estado'] ?? '1'),
                    'porcentaje_avance'       => number_format($alumno['porcentajeAvance'] ?? 0, 5, '.', ''),
                    'fecha_inicio'            => $alumno['fechaInicio'] ?? '',
                    'fecha_fin'               => $alumno['fechaFin'] ?? '',
                    'fecha_ejecucion'         => $alumno['fechaEjecucion'] ?? date('Y-m-d H:i:s'),
                    'mod_cod'                 => $codigoModulo,
                    'mod_tiempo_conectividad' => (string)($modulo['tiempoConectividad'] ?? '0'),
                    'mod_estado'              => (string)($modulo['estado'] ?? '1'),
                    'mod_porcentaje_avance'   => number_format($porcentajeModulo, 5, '.', ''),
                    'mod_fecha_inicio'        => $modulo['fechaInicio'] ?? '',
                    'mod_fecha_fin'           => $modulo['fechaFin'] ?? '',
                    'mod_obligatorio'         => null,
                    'mod_nota'                => isset($modulo['notaModulo']) ? (string)$modulo['notaModulo'] : null,
                    'act_cod'                 => $actCod,
                    'act_estado'              => null,
                    'estado_registro'         => '1',
                    'obs_registro'            => '',
                    'id_proceso_externo'      => $idProceso,
                    'created_at'              => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $respuestaSIC = "El proceso {$idProceso} ha finalizado correctamente, para ver la cantidad de registros y detalle, dirigase a la pantalla de procesos";

        if (!empty($erroresDetalle)) {
            log_message('warning', '[SIC] enviarAvance PARCIAL | id_proceso={pid} | errores=' . count($erroresDetalle) . ' | alumnos_ok=' . count($enviados), ['pid' => $idProceso]);
            return $this->response->setJSON([
                'id_proceso'    => (int)$idProceso,
                'datosEnviados' => [],
                'datosError'    => $erroresDetalle,
                'respuesta_SIC' => '',
            ]);
        }

        log_message('info', '[SIC] enviarAvance OK | id_proceso={pid} | alumnos=' . count($enviados) . ' | codigoOferta=' . $codigoOferta . ' | codigoEnvio=' . $codigoEnvio, ['pid' => $idProceso]);

        return $this->response->setJSON([
            'id_proceso'    => (int)$idProceso,
            'envio'         => $enviados,
            'errores'       => [],
            'respuesta_SIC' => $respuestaSIC,
        ]);
    }

    public function historialEnvios()
    {
        $rutOtec      = $this->request->getGet('rutOtec');
        $idSistema    = $this->request->getGet('idSistema');
        $token        = $this->request->getGet('token');
        $fechaDesde   = $this->request->getGet('fechaDesde');
        $codigoExt    = $this->request->getGet('codigo_externo');
        $idProceso    = $this->request->getGet('id_proceso');

        log_message('info', '[SIC] GET /gestor/API/avance-sic/historialEnvios | rutOtec=' . ($rutOtec ?? 'N/A') . ' | filters=' . http_build_query(array_filter(['fechaDesde' => $fechaDesde, 'codigo_externo' => $codigoExt, 'id_proceso' => $idProceso])));

        $forcedError = (new SenceErrorQueueModel())->getPending('sic_historial');
        if ($forcedError) {
            (new SenceErrorQueueModel())->decrement($forcedError);
            log_message('warning', '[SIC] historialEnvios ERROR FORZADO {code}', ['code' => $forcedError['codigo_error']]);
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Error forzado: ' . $forcedError['codigo_error']]);
        }

        if (!$rutOtec || !$idSistema || !$token) {
            log_message('warning', '[SIC] historialEnvios ERROR | parametros requeridos faltantes');
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Parametros requeridos: rutOtec, idSistema, token']);
        }

        $procesoModel = new SenceProcesoModel();
        $builder = $procesoModel->where('rut_otec', $rutOtec);

        if ($fechaDesde) {
            $builder->where('created_at >=', $fechaDesde . ' 00:00:00');
        }
        if ($codigoExt) {
            $builder->where('codigo_externo', $codigoExt);
        }
        if ($idProceso) {
            $builder->where('id_proceso', $idProceso);
        }

        $procesos = $builder->orderBy('id', 'DESC')->findAll(50);

        $registroModel = new SenceProcesoRegistroModel();
        $resultado = [];

        foreach ($procesos as $proceso) {
            $registros = $registroModel->where('proceso_id', $proceso['id'])->findAll();
            $listaRegistros = [];
            foreach ($registros as $r) {
                $listaRegistros[] = [
                    'id_registro'             => (string)$r['id'],
                    'rut_otec'                => $r['rut_otec'],
                    'dv_otec'                 => $r['dv_otec'],
                    'oferta_cod'              => $r['oferta_cod'],
                    'seccion_cod'             => $r['seccion_cod'],
                    'rut_alumno'              => $r['rut_alumno'],
                    'dv_alumno'               => $r['dv_alumno'],
                    'tiempo_conectividad'     => $r['tiempo_conectividad'],
                    'estado'                  => $r['estado'],
                    'porcentaje_avance'       => $r['porcentaje_avance'],
                    'fecha_inicio'            => $r['fecha_inicio'],
                    'fecha_fin'               => $r['fecha_fin'],
                    'fecha_ejecucion'         => $r['fecha_ejecucion'],
                    'mod_cod'                 => $r['mod_cod'],
                    'mod_tiempo_conectividad' => $r['mod_tiempo_conectividad'],
                    'mod_estado'              => $r['mod_estado'],
                    'mod_porcentaje_avance'   => $r['mod_porcentaje_avance'],
                    'mod_fecha_inicio'        => $r['mod_fecha_inicio'],
                    'mod_fecha_fin'           => $r['mod_fecha_fin'],
                    'mod_obligatorio'         => $r['mod_obligatorio'],
                    'mod_nota'                => $r['mod_nota'],
                    'act_cod'                 => $r['act_cod'],
                    'act_estado'              => $r['act_estado'],
                    'estado_registro'         => $r['estado_registro'],
                    'obs_registro'            => $r['obs_registro'],
                    'id_proceso_externo'      => $r['id_proceso_externo'],
                ];
            }

            $resultado[] = [
                'id_proceso_externo' => $proceso['id_proceso'],
                'id_lms'             => $proceso['id_lms'],
                'codigo_externo'     => $proceso['codigo_externo'],
                'fecha'              => $proceso['created_at'],
                'horario'            => $proceso['horario'],
                'observaciones'      => $proceso['respuesta_sic'],
                'observaciones_sic' => $proceso['observaciones_sic'],
                'status_code'        => $proceso['status_code'],
                'estado'             => $proceso['estado'],
                'rut_otec'           => $proceso['rut_otec'],
                'listaregistros'     => $listaRegistros,
            ];
        }

        log_message('info', '[SIC] historialEnvios OK | procesos=' . count($resultado));
        return $this->response->setJSON($resultado);
    }

    private function errorResponse(string $codigo, string $mensaje): \CodeIgniter\HTTP\Response
    {
        return $this->response->setJSON([
            'id_proceso'    => 0,
            'datosEnviados' => [],
            'datosError'    => [['codigo' => $codigo, 'mensaje' => $mensaje]],
            'respuesta_SIC' => '',
        ]);
    }

    private function forcedErrorResponse(string $codigo): \CodeIgniter\HTTP\Response
    {
        return $this->response->setJSON([
            'id_proceso'    => (int)(rand(100, 999)),
            'datosEnviados' => [],
            'datosError'    => [[
                'codigo'  => $codigo,
                'alumno'  => [
                    'rutAlumno'          => '00000000',
                    'dvAlumno'           => '0',
                    'tiempoConectividad' => 0,
                    'porcentajeAvance'   => 0,
                    'estado'             => 1,
                    'fechaInicio'        => date('Y-m-d') . ' 00:00:00',
                    'fechaFin'           => date('Y-m-d') . ' 00:00:00',
                    'listaModulos'       => [],
                ],
                'mensaje' => 'Error forzado: ' . $codigo,
            ]],
            'respuesta_SIC' => '',
        ]);
    }
}
