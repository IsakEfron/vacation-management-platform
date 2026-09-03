<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table      = 'auditorias';
    protected $primaryKey = 'id_auditoria';
    public    $timestamps = false;

    protected $fillable = [
        'empleado',
        'accion',
        'detalles',
        'fecha',
        'ip_origen',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public static function registrar(
        ?string $nomina,
        string  $accion,
        ?string $detalles = null,
        ?string $ip       = null
    ): void {
        try {
            static::create([
                'empleado'  => $nomina,
                'accion'    => substr($accion, 0, 100),
                'detalles'  => $detalles ? substr($detalles, 0, 500) : null,
                'fecha'     => now(),
                'ip_origen' => $ip,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Auditoría falló: ' . $e->getMessage()
            );
        }
    }
}