<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoSolicitud extends Model
{
    protected $table      = 'tipo_solicitud';
    protected $primaryKey = 'id_tipo';
    public $timestamps    = false;

    protected $fillable = ['nombre', 'con_goce', 'usa_saldo', 'activo'];

    protected $casts = [
        'con_goce'  => 'boolean',
        'usa_saldo' => 'boolean',
        'activo'    => 'boolean',
    ];
}