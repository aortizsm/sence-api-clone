<?php

namespace App\Controllers;

use App\Models\SenceSesionModel;
use App\Models\SenceErrorQueueModel;

class SenceRce extends BaseController
{
    protected array $lineasValidas = [1, 3, 6];

    public function iniciarSesion()
    {
        $post = $this->request->getPost();
        log_message('info', '[RCE] POST /rce/Registro/IniciarSesion | RunAlumno=' . ($post['RunAlumno'] ?? 'N/A') . ' | CodigoCurso=' . ($post['CodigoCurso'] ?? 'N/A') . ' | RutOtec=' . ($post['RutOtec'] ?? 'N/A'));

        $forcedError = (new SenceErrorQueueModel())->getPending('rce_inicio');
        if ($forcedError) {
            (new SenceErrorQueueModel())->decrement($forcedError);
            log_message('warning', '[RCE] IniciarSesion ERROR FORZADO {code} | restantes={rest}', ['code' => $forcedError['codigo_error'], 'rest' => $forcedError['peticiones_restantes'] - 1]);
            $glosa = $forcedError['codigo_error'];
            $urlError = $post['UrlError'] ?? 'http://localhost/error';
            return $this->renderErrorRedirect($urlError, $post, $glosa);
        }

        $error = $this->validarParametrosInicio($post);
        if ($error !== null) {
            log_message('warning', '[RCE] IniciarSesion ERROR VALIDACION {code} | RunAlumno=' . ($post['RunAlumno'] ?? 'N/A'), ['code' => $error]);
            return $this->renderErrorRedirect($post['UrlError'] ?? '', $post, $error);
        }

        $idSesionSence = 'MOCK-SESS-' . strtoupper(bin2hex(random_bytes(8)));

        $model = new SenceSesionModel();
        $model->insert([
            'rut_otec'           => $post['RutOtec'],
            'token'              => $post['Token'],
            'cod_sence'          => $post['CodSence'],
            'codigo_curso'       => $post['CodigoCurso'],
            'linea_capacitacion' => $post['LineaCapacitacion'],
            'run_alumno'         => $post['RunAlumno'],
            'id_sesion_alumno'   => $post['IdSesionAlumno'],
            'id_sesion_sence'    => $idSesionSence,
            'url_retoma'         => $post['UrlRetoma'],
            'url_error'          => $post['UrlError'],
            'estado'             => 'activa',
            'fecha_inicio'       => date('Y-m-d H:i:s'),
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        log_message('info', '[RCE] IniciarSesion OK | IdSesionSence={sess} | RunAlumno=' . $post['RunAlumno'] . ' | CodigoCurso=' . $post['CodigoCurso'], ['sess' => $idSesionSence]);

        $datosExito = [
            'CodSence'          => $post['CodSence'],
            'CodigoCurso'       => $post['CodigoCurso'],
            'IdSesionAlumno'    => $post['IdSesionAlumno'],
            'IdSesionSence'     => $idSesionSence,
            'RunAlumno'         => $post['RunAlumno'],
            'FechaHora'         => date('Y-m-d H:i:s'),
            'ZonaHoraria'       => 'America/Santiago',
            'LineaCapacitacion' => $post['LineaCapacitacion'],
        ];

        return $this->renderPostRedirect($post['UrlRetoma'], $datosExito);
    }

    public function cerrarSesion()
    {
        $post = $this->request->getPost();
        log_message('info', '[RCE] POST /rce/Registro/CerrarSesion | RunAlumno=' . ($post['RunAlumno'] ?? 'N/A') . ' | IdSesionSence=' . ($post['IdSesionSence'] ?? 'N/A'));

        $forcedError = (new SenceErrorQueueModel())->getPending('rce_cierre');
        if ($forcedError) {
            (new SenceErrorQueueModel())->decrement($forcedError);
            log_message('warning', '[RCE] CerrarSesion ERROR FORZADO {code} | restantes={rest}', ['code' => $forcedError['codigo_error'], 'rest' => $forcedError['peticiones_restantes'] - 1]);
            $glosa = $forcedError['codigo_error'];
            $urlError = $post['UrlError'] ?? 'http://localhost/error';
            return $this->renderErrorRedirect($urlError, $post, $glosa);
        }

        $error = $this->validarParametrosCierre($post);
        if ($error !== null) {
            log_message('warning', '[RCE] CerrarSesion ERROR VALIDACION {code} | IdSesionSence=' . ($post['IdSesionSence'] ?? 'N/A'), ['code' => $error]);
            return $this->renderErrorRedirect($post['UrlError'] ?? '', $post, $error);
        }

        $model = new SenceSesionModel();
        $sesion = $model->findBySesionSence($post['IdSesionSence']);

        if ($sesion) {
            $model->update($sesion['id'], [
                'estado'       => 'cerrada',
                'fecha_cierre' => date('Y-m-d H:i:s'),
            ]);
            log_message('info', '[RCE] CerrarSesion OK | IdSesionSence={sess} | sesion previa encontrada y cerrada', ['sess' => $post['IdSesionSence']]);
        } else {
            log_message('info', '[RCE] CerrarSesion OK | IdSesionSence={sess} | sesion no encontrada en DB (se acepta igual)', ['sess' => $post['IdSesionSence']]);
        }

        $datosExito = [
            'CodSence'          => $post['CodSence'],
            'CodigoCurso'       => $post['CodigoCurso'],
            'IdSesionAlumno'    => $post['IdSesionAlumno'],
            'RunAlumno'         => $post['RunAlumno'],
            'FechaHora'         => date('Y-m-d H:i:s'),
            'ZonaHoraria'       => 'America/Santiago',
            'LineaCapacitacion' => $post['LineaCapacitacion'],
        ];

        return $this->renderPostRedirect($post['UrlRetoma'], $datosExito);
    }

    private function validarParametrosInicio(array $post): ?string
    {
        $requeridos = ['RutOtec', 'Token', 'CodSence', 'CodigoCurso', 'LineaCapacitacion', 'RunAlumno', 'IdSesionAlumno', 'UrlRetoma', 'UrlError'];
        foreach ($requeridos as $campo) {
            if (!isset($post[$campo]) || trim((string)$post[$campo]) === '') {
                if (in_array($campo, ['UrlRetoma', 'UrlError'])) {
                    return '201';
                }
                return '200';
            }
        }

        if (!filter_var($post['UrlRetoma'], FILTER_VALIDATE_URL)) {
            return '202';
        }
        if (!filter_var($post['UrlError'], FILTER_VALIDATE_URL)) {
            return '203';
        }

        $codigoCurso = (string)$post['CodigoCurso'];
        $linea = (int)$post['LineaCapacitacion'];

        if (!in_array($linea, $this->lineasValidas, true)) {
            return '206';
        }

        $codSence = (string)$post['CodSence'];
        if ($linea === 3 && $codSence !== '-1' && strlen($codSence) < 10) {
            return '204';
        }

        if ($linea === 1 && $codSence !== '' && $codSence !== '-1') {
            return '206';
        }

        if ($codigoCurso !== '-1' && $linea !== 6 && strlen($codigoCurso) < 7) {
            return '205';
        }

        $run = (string)$post['RunAlumno'];
        if (!$this->validarFormatoRun($run)) {
            return '207';
        }

        $rutOtec = (string)$post['RutOtec'];
        if (!$this->validarFormatoRun($rutOtec)) {
            return '209';
        }

        return null;
    }

    private function validarParametrosCierre(array $post): ?string
    {
        $requeridos = ['RutOtec', 'Token', 'CodSence', 'CodigoCurso', 'LineaCapacitacion', 'RunAlumno', 'IdSesionAlumno', 'IdSesionSence', 'UrlRetoma', 'UrlError'];
        foreach ($requeridos as $campo) {
            if (!isset($post[$campo]) || trim((string)$post[$campo]) === '') {
                if (in_array($campo, ['UrlRetoma', 'UrlError'])) {
                    return '201';
                }
                return '200';
            }
        }

        if (!filter_var($post['UrlRetoma'], FILTER_VALIDATE_URL)) {
            return '202';
        }
        if (!filter_var($post['UrlError'], FILTER_VALIDATE_URL)) {
            return '203';
        }

        $codigoCurso = (string)$post['CodigoCurso'];
        $linea = (int)$post['LineaCapacitacion'];

        if (!in_array($linea, $this->lineasValidas, true)) {
            return '206';
        }

        $codSence = (string)$post['CodSence'];
        if ($linea === 3 && $codSence !== '-1' && strlen($codSence) < 10) {
            return '204';
        }

        if ($linea === 1 && $codSence !== '' && $codSence !== '-1') {
            return '206';
        }

        if ($codigoCurso !== '-1' && $linea !== 6 && strlen($codigoCurso) < 7) {
            return '205';
        }

        $run = (string)$post['RunAlumno'];
        if (!$this->validarFormatoRun($run)) {
            return '207';
        }

        $rutOtec = (string)$post['RutOtec'];
        if (!$this->validarFormatoRun($rutOtec)) {
            return '209';
        }

        return null;
    }

    private function validarFormatoRun(string $run): bool
    {
        if (!preg_match('/^\d{7,8}-[0-9Kk]$/', $run)) {
            return false;
        }

        [$numero, $dv] = explode('-', $run);
        $numero = (int)$numero;
        $dv = strtoupper($dv);

        $suma = 0;
        $factor = 2;
        $tmp = $numero;
        while ($tmp > 0) {
            $suma += ($tmp % 10) * $factor;
            $tmp = (int)($tmp / 10);
            $factor++;
            if ($factor > 7) {
                $factor = 2;
            }
        }
        $resto = $suma % 11;
        $digitoCalculado = 11 - $resto;
        if ($digitoCalculado === 11) {
            $digitoCalculado = 0;
        } elseif ($digitoCalculado === 10) {
            $digitoCalculado = 'K';
        }
        return (string)$digitoCalculado === $dv;
    }

    private function renderPostRedirect(string $url, array $data): string
    {
        return view('sence/redirect', ['url' => $url, 'data' => $data]);
    }

    private function renderErrorRedirect(string $urlError, array $originalPost, string $glosaError): string
    {
        $errorData = [
            'CodSence'          => $originalPost['CodSence'] ?? '',
            'CodigoCurso'       => $originalPost['CodigoCurso'] ?? '',
            'IdSesionAlumno'    => $originalPost['IdSesionAlumno'] ?? '',
            'RunAlumno'         => $originalPost['RunAlumno'] ?? '',
            'FechaHora'         => date('Y-m-d H:i:s'),
            'ZonaHoraria'       => 'America/Santiago',
            'LineaCapacitacion' => $originalPost['LineaCapacitacion'] ?? '',
            'GlosaError'        => $glosaError,
        ];

        if (!empty($originalPost['IdSesionSence'])) {
            $errorData['IdSesionSence'] = $originalPost['IdSesionSence'];
        }

        return $this->renderPostRedirect($urlError, $errorData);
    }
}
