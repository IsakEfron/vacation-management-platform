<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Archivado anual — 1 de enero a la 1:00 AM ────────────────────────────────
// Archiva reservas cerradas, historial y auditorías del año anterior
// SIEMPRE antes que la limpieza de auditorías
Schedule::command('sistema:archivar')
    ->yearlyOn(1, 1, '01:00')       // 1 de enero a la 1:00 AM
    ->withoutOverlapping()
    ->runInBackground();

// ── Limpieza de auditorías viejas — día 1 de cada mes a las 2:00 AM ──────────
Schedule::command('auditorias:limpiar --meses=12')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground();

// ── Limpieza de datos de soporte — domingos a las 3:00 AM ────────────────────
Schedule::command('sistema:limpiar --dias-login-ok=90 --dias-login-fail=30')
    ->weekly()->sundays()->at('03:00')
    ->withoutOverlapping()
    ->runInBackground();