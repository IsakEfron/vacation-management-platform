<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table      = 'roles';
    protected $primaryKey = 'id_rol';
    public $incrementing  = false;
    public $timestamps    = false;

    protected $fillable = ['id_rol', 'tipo', 'nivel'];

    public function empleados()
    {
        return $this->hasMany(Empleado::class, 'rol', 'id_rol');
    }
}