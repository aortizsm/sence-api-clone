<?php

namespace App\Models;

use CodeIgniter\Model;

class SenceErrorQueueModel extends Model
{
    protected $table          = 'sence_error_queue';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields  = [
        'endpoint', 'codigo_error', 'peticiones_restantes', 'glosa_error', 'created_at',
    ];
    protected $useTimestamps  = false;

    public function getPending(string $endpoint): ?array
    {
        return $this->where('endpoint', $endpoint)
            ->where('peticiones_restantes >', 0)
            ->orderBy('id', 'ASC')
            ->first();
    }

    public function decrement(?array $error): void
    {
        if ($error && isset($error['peticiones_restantes']) && $error['peticiones_restantes'] > 0) {
            $this->update($error['id'], [
                'peticiones_restantes' => $error['peticiones_restantes'] - 1,
            ]);
        }
    }

    public function setError(string $endpoint, string $codigo, int $count, ?string $glosa = null): void
    {
        $this->insert([
            'endpoint'             => $endpoint,
            'codigo_error'         => $codigo,
            'peticiones_restantes' => $count,
            'glosa_error'          => $glosa,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
    }

    public function getAllActive(): array
    {
        return $this->where('peticiones_restantes >', 0)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function clearAll(): void
    {
        $this->truncate();
    }

    public function deleteById(int $id): void
    {
        $this->delete($id);
    }
}
