<# EJECUTAR: PowerShell -ExecutionPolicy Bypass -File .\setup-scheduler.ps1 #>
<# VERIFICAR: PowerShell -ExecutionPolicy Bypass -File .\setup-scheduler.ps1 -Verificar #>

param(
    [switch]$Desinstalar,
    [switch]$Verificar
)

$RutaProyecto = "C:\Users\gialvarado\Documents\GitHub\Canels_SV_La"
$RutaPHP      = "C:\tools\php83\php.exe"
$NombreTarea  = "Laravel_Canels_Scheduler"

$esAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")
if (-NOT $esAdmin) {
    Write-Host "ERROR: Ejecuta PowerShell como Administrador." -ForegroundColor Red
    exit 1
}

if ($Verificar) {
    Write-Host ""
    Write-Host "=== Estado del Scheduler de Laravel ===" -ForegroundColor Cyan
    $tarea = Get-ScheduledTask -TaskName $NombreTarea -ErrorAction SilentlyContinue
    if ($tarea) {
        $info = Get-ScheduledTaskInfo -TaskName $NombreTarea
        Write-Host "Tarea          : $NombreTarea" -ForegroundColor Green
        Write-Host "Estado         : $($tarea.State)"
        Write-Host "Ultima ejecucion : $($info.LastRunTime)"
        Write-Host "Ultimo resultado : $($info.LastTaskResult)  (0 = exitoso)"
        Write-Host "Proxima ejecucion: $($info.NextRunTime)"
    } else {
        Write-Host "La tarea $NombreTarea NO esta registrada." -ForegroundColor Red
    }
    exit 0
}

if ($Desinstalar) {
    Write-Host ""
    Write-Host "Desinstalando $NombreTarea ..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $NombreTarea -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Tarea eliminada." -ForegroundColor Green
    exit 0
}

Write-Host ""
Write-Host "=== Instalando Scheduler de Laravel - Canels ===" -ForegroundColor Cyan

if (-NOT (Test-Path $RutaProyecto)) {
    Write-Host "ERROR: Ruta del proyecto no existe: $RutaProyecto" -ForegroundColor Red
    Write-Host "Edita la variable RutaProyecto en este script." -ForegroundColor Yellow
    exit 1
}

if (-NOT (Test-Path $RutaPHP)) {
    Write-Host "ERROR: PHP no encontrado en: $RutaPHP" -ForegroundColor Red
    Write-Host "Edita la variable RutaPHP en este script." -ForegroundColor Yellow
    exit 1
}

$RutaArtisan = Join-Path $RutaProyecto "artisan"
if (-NOT (Test-Path $RutaArtisan)) {
    Write-Host "ERROR: No se encontro artisan en $RutaProyecto" -ForegroundColor Red
    exit 1
}

Write-Host "Proyecto : $RutaProyecto" -ForegroundColor Gray
Write-Host "PHP      : $RutaPHP" -ForegroundColor Gray
Write-Host "Artisan  : $RutaArtisan" -ForegroundColor Gray

$existente = Get-ScheduledTask -TaskName $NombreTarea -ErrorAction SilentlyContinue
if ($existente) {
    Write-Host "Eliminando tarea anterior..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $NombreTarea -Confirm:$false
}

$action = New-ScheduledTaskAction `
    -Execute $RutaPHP `
    -Argument "artisan schedule:run" `
    -WorkingDirectory $RutaProyecto

$trigger = New-ScheduledTaskTrigger `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -Once `
    -At (Get-Date)

$settings = New-ScheduledTaskSettingsSet `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5) `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable

Register-ScheduledTask `
    -TaskName $NombreTarea `
    -Action   $action `
    -Trigger  $trigger `
    -Settings $settings `
    -RunLevel Highest `
    -User     "SYSTEM" `
    -Force

Write-Host ""
Write-Host "Tarea $NombreTarea registrada correctamente." -ForegroundColor Green
Write-Host ""
Write-Host "Comandos:" -ForegroundColor Cyan
Write-Host "  Ver estado   : .\setup-scheduler.ps1 -Verificar"
Write-Host "  Desinstalar  : .\setup-scheduler.ps1 -Desinstalar"
Write-Host "  Ver en UI    : taskschd.msc"