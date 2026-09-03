<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    protected $table      = 'history';
    protected $primaryKey = 'id_history';
    public $timestamps    = false;

    protected $fillable = [
        'id_reserva', 'estado_anterior', 'estado_nuevo',
        'modificado_por', 'detalles_cambio', 'fecha_cambio',
    ];

    protected $casts = ['fecha_cambio' => 'datetime'];

    public function estadoAnterior()
    {
        return $this->belongsTo(Estado::class, 'estado_anterior', 'id_estado');
    }

    public function estadoNuevo()
    {
        return $this->belongsTo(Estado::class, 'estado_nuevo', 'id_estado');
    }

    public function modificadoPor()
    {
        return $this->belongsTo(Empleado::class, 'modificado_por', 'nomina');
    }
}