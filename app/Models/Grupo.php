<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table      = 'grupos';
    protected $primaryKey = 'id_grupo';
    public $timestamps    = true; // grupos SÍ tiene created_at/updated_at

    protected $fillable = ['nombre', 'supervisor'];

    /**
     * Relación con el supervisor (empleado).
     * IMPORTANTE: el método se llama supervisorInfo para no chocar
     * con la columna $this->supervisor (string con la nómina).
     */
    public function supervisorInfo()
    {
        return $this->belongsTo(Empleado::class, 'supervisor', 'nomina');
    }

    /**
     * Miembros del grupo.
     * grupo_empleado NO tiene timestamps -> withTimestamps(false).
     */
    public function empleados()
    {
        return $this->belongsToMany(
            Empleado::class,
            'grupo_empleado',   // tabla pivot
            'id_grupo',         // FK de este modelo en la pivot
            'nomina'            // FK del modelo relacionado en la pivot
        )->withTimestamps(false); // grupo_empleado no tiene created_at/updated_at
    }
}