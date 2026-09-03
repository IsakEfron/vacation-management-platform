<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model
{
    protected $table      = 'mantenimientos';
    protected $primaryKey = 'id_mantenimiento';
    public $timestamps    = true;

    protected $fillable = [
        'categoria', 'fecha_inicio', 'fecha_fin',
        'notas', 'estado', 'creado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin'    => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function scopeActivo($query)
    {
        return $query->where('active', 2)
                     ->where('start_date', '<=', now())
                     ->where('end_date',   '>=', now());
    }

    // Relación con el creador
    public function creadoPor()
    {
        return $this->belongsTo(\App\Models\Empleado::class, 'creado_por', 'nomina');
    }
}