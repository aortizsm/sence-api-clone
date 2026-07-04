<?php

namespace App\Controllers;

use App\Models\SenceSesionModel;
use App\Models\SenceProcesoModel;
use App\Models\SenceAvanceModel;
use App\Models\SenceProcesoRegistroModel;
use App\Models\SenceErrorQueueModel;

class SenceDashboard extends BaseController
{
    public function index()
    {
        $sesionModel  = new SenceSesionModel();
        $procesoModel = new SenceProcesoModel();
        $avanceModel  = new SenceAvanceModel();
        $errorModel   = new SenceErrorQueueModel();

        return view('sence/dashboard', [
            'sesiones'       => $sesionModel->orderBy('id', 'DESC')->findAll(20),
            'sesionesCount'  => $sesionModel->countAllResults(),
            'procesos'       => $procesoModel->orderBy('id', 'DESC')->findAll(10),
            'procesosCount'  => $procesoModel->countAllResults(),
            'avances'        => $avanceModel->orderBy('id', 'DESC')->findAll(20),
            'avancesCount'   => $avanceModel->countAllResults(),
            'erroresActivos' => $errorModel->getAllActive(),
        ]);
    }

    public function sesiones()
    {
        $model = new SenceSesionModel();
        $errorModel = new SenceErrorQueueModel();

        return view('sence/dashboard', [
            'sesiones'       => $model->orderBy('id', 'DESC')->findAll(100),
            'sesionesCount'  => $model->countAllResults(),
            'procesos'       => [],
            'procesosCount'  => 0,
            'avances'        => [],
            'avancesCount'   => 0,
            'erroresActivos' => $errorModel->getAllActive(),
        ]);
    }

    public function procesos()
    {
        $model = new SenceProcesoModel();
        $errorModel = new SenceErrorQueueModel();

        return view('sence/dashboard', [
            'sesiones'       => [],
            'sesionesCount'  => 0,
            'procesos'       => $model->orderBy('id', 'DESC')->findAll(100),
            'procesosCount'  => $model->countAllResults(),
            'avances'        => [],
            'avancesCount'   => 0,
            'erroresActivos' => $errorModel->getAllActive(),
        ]);
    }

    public function avances()
    {
        $model = new SenceAvanceModel();
        $errorModel = new SenceErrorQueueModel();

        return view('sence/dashboard', [
            'sesiones'       => [],
            'sesionesCount'  => 0,
            'procesos'       => [],
            'procesosCount'  => 0,
            'avances'        => $model->orderBy('id', 'DESC')->findAll(100),
            'avancesCount'   => $model->countAllResults(),
            'erroresActivos' => $errorModel->getAllActive(),
        ]);
    }

    public function errores()
    {
        $errorModel = new SenceErrorQueueModel();

        if ($this->request->getMethod() === 'post') {
            $action = $this->request->getPost('action');

            if ($action === 'activar') {
                $endpoint = $this->request->getPost('endpoint');
                $codigo   = $this->request->getPost('codigo_error');
                $count    = (int)$this->request->getPost('peticiones');
                $glosa    = $this->request->getPost('glosa_error') ?: null;

                if ($endpoint && $codigo && $count > 0) {
                    $errorModel->setError($endpoint, $codigo, $count, $glosa);
                    log_message('info', '[DASHBOARD] Error activado | endpoint={ep} | codigo={code} | peticiones={n}', ['ep' => $endpoint, 'code' => $codigo, 'n' => $count]);
                }
            } elseif ($action === 'eliminar') {
                $id = (int)$this->request->getPost('error_id');
                if ($id > 0) {
                    $errorModel->deleteById($id);
                    log_message('info', '[DASHBOARD] Error eliminado | id={id}', ['id' => $id]);
                }
            } elseif ($action === 'limpiar') {
                $errorModel->clearAll();
                log_message('info', '[DASHBOARD] Todos los errores forzados eliminados');
            }

            return redirect()->to('/sence/dashboard/errores');
        }

        return view('sence/dashboard', [
            'sesiones'       => [],
            'sesionesCount'  => 0,
            'procesos'       => [],
            'procesosCount'  => 0,
            'avances'        => [],
            'avancesCount'   => 0,
            'erroresActivos' => $errorModel->getAllActive(),
            'showErrorForm'  => true,
        ]);
    }

    public function limpiar()
    {
        $db = \Config\Database::connect();
        $db->table('sence_sesiones')->truncate();
        $db->table('sence_avances')->truncate();
        $db->table('sence_proceso_registros')->truncate();
        $db->table('sence_procesos')->truncate();
        $db->table('sence_error_queue')->truncate();

        return redirect()->to('/sence/dashboard')->with('message', 'Datos limpiados correctamente.');
    }
}
