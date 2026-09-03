<?php
// app/Models/Quincena.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quincena extends Model
{
    protected $table      = 'quincenas';
    protected $primaryKey = 'id_quincena';

    protected $fillable = [
        'descripcion', 'numero', 'anio',
        'fecha_inicio', 'fecha_fin', 'activo', 'creado_por',
    ];

    protected $casts = [
        'activo'      => 'boolean',
        'fecha_inicio'=> 'date',
        'fecha_fin'   => 'date',
        'numero'      => 'integer',
        'anio'        => 'integer',
    ];

    public $timestamps = false;

    // Buscar la quincena que contiene una <- dada
    public static function buscarPorFecha(string $fecha): ?self
    {
        return static::where('activo', 1)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin',    '>=', $fecha)
            ->first();
    }
}