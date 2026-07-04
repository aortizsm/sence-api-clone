<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// --- RCE (Registro de Asistencia) ---
// Ambiente Test
$routes->post('rcetest/Registro/IniciarSesion', 'SenceRce::iniciarSesion');
$routes->post('rcetest/Registro/CerrarSesion', 'SenceRce::cerrarSesion');
// Ambiente Produccion
$routes->post('rce/Registro/IniciarSesion', 'SenceRce::iniciarSesion');
$routes->post('rce/Registro/CerrarSesion', 'SenceRce::cerrarSesion');

// --- API Gestor Intermedio (Avance SIC) ---
$routes->post('gestor/API/avance-sic/enviarAvance', 'SenceSic::enviarAvance');
$routes->get('gestor/API/avance-sic/historialEnvios', 'SenceSic::historialEnvios');

// --- Dashboard ---
$routes->get('sence/dashboard', 'SenceDashboard::index');
$routes->get('sence/dashboard/(:segment)', 'SenceDashboard::$1');
