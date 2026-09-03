<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Empleado extends Authenticatable
{
    use Notifiable;

    protected $table      = 'empleados';
    protected $primaryKey = 'nomina';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
    'nomina', 'nombre', 'password', 'saldo', 'rol',
    'activo', 'login_bloqueado', 'primera_vez', 'tipo_nomina',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'login_bloqueado' => 'boolean',
        'primera_vez'     => 'boolean',
        'saldo'           => 'integer',
        'rol'             => 'integer',
        'tipo_nomina'     => 'integer', 
    ];

    // ── Relaciones ────────────────────────────────────────────────

    /**
     * IMPORTANTE: La relación se llama rolInfo (no rol) para evitar
     * el conflicto con la columna $this->rol que es un entero.
     * En Blade usar: $emp->rolInfo->tipo
     */
    public function rolInfo()
    {
        return $this->belongsTo(Rol::class, 'rol', 'id_rol');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_empleado', 'nomina');
    }

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'grupo_empleado', 'nomina', 'id_grupo');
    }

    public function gruposSupervisados()
    {
        return $this->hasMany(Grupo::class, 'supervisor', 'nomina');
    }

    // ── Helpers de rol ────────────────────────────────────────────

    public function esEmpleado(): bool    { return $this->rol === 1; }
    public function esSupervisor(): bool  { return $this->rol === 2; }
    public function esAdminRH(): bool     { return $this->rol === 3; }
    public function esSuperAdmin(): bool  { return $this->rol === 4; }
    public function debecambiarPassword(): bool{ return (bool) $this->primera_vez; }

    // ── Override de auth ──────────────────────────────────────────

    public function getAuthIdentifierName(): string { return 'nomina'; }
}