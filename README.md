# Sistema de Gestión de Vacaciones

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![SQL Server](https://img.shields.io/badge/SQL%20Server-2019+-CC2927?logo=microsoft-sql-server&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-4.2.2-06B6D4?logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/Licencia-Propietaria-red)

Sistema interno de gestión de solicitudes de vacaciones, permisos e incapacidades para el personal de **Proyect**. Desarrollado con Laravel 12, SQL Server y TailwindCSS.

---

## Tabla de Contenidos

- [Características](#-características)
- [Requisitos del Sistema](#-requisitos-del-sistema)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Roles y Permisos](#-roles-y-permisos)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Comandos de Mantenimiento](#-comandos-de-mantenimiento)
- [Scheduler](#-scheduler)
- [Despliegue en Producción](#-despliegue-en-producción)
- [Licencias de Dependencias](#-licencias-de-dependencias)
- [Autor](#-autor)
- [Agradecimientos](#-agradecimientos)

---

## Características

- **Autenticación personalizada** con guard propio (`empleado`) y soporte para contraseñas MD5 temporales (migración progresiva a bcrypt)
- **4 niveles de acceso**: Empleado, Supervisor, Admin RH y SuperAdmin
- **Flujo de aprobación** de solicitudes en dos etapas: Supervisor → Admin RH
- **Importación masiva** de empleados desde Excel (MERGE inteligente, sin duplicados)
- **Exportación a Excel** en dos formatos: Reporte RH y formato TREESS-ASCII para nómina
- **Modo mantenimiento** con control granular por rol — el SuperAdmin siempre puede operar
- **Auditoría completa** de todas las acciones con exportación a Excel
- **Cálculo automático de días hábiles** considerando días especiales, feriados y calendario por centro de pago
- **Limpieza automática programada** de datos obsoletos
- **Panel de Telescope** para monitoreo en desarrollo (deshabilitado en producción)

---

## Requisitos del Sistema

| Componente         | Versión mínima       |
|--------------------|----------------------|
| PHP                | 8.3+                 |
| Laravel            | 13                   |
| SQL Server         | 2019+                |
| ODBC Driver        | 17 para SQL Server   |
| Composer           | 2.9.5                  |
| Node.js *(dev)*    | 20+ *(solo en desarrollo para compilar assets)* |
| Extensiones PHP    | `pdo_sqlsrv`, `sqlsrv`, `mbstring`, `openssl`, `tokenizer`, `xml` |

> **Nota:** En producción no se requiere Node.js. Los assets ya están compilados en `public/build/`.

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/Proyect-vacaciones.git
cd Proyect-vacaciones
```

### 2. Instalar dependencias PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar variables de entorno

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con las credenciales del servidor (ver sección [Configuración](#-configuración)).

### 4. Crear la base de datos

Ejecutar el script completo en SQL Server Management Studio:

```
database/vacation_db.sql
```

Este script crea todas las tablas, índices, vistas, el stored procedure de archivo y los datos iniciales.

### 5. Ejecutar migraciones de Telescope *(solo desarrollo)*

```bash
php artisan telescope:install
php artisan migrate
```

### 6. Levantar el servidor de desarrollo

```bash
php artisan serve
```

---

## Configuración

### Variables de entorno clave (`.env`)

```dotenv
APP_NAME="'s Vacaciones"
APP_ENV=production          # cambiar a production en servidor
APP_DEBUG=false             # false en producción
APP_URL=https://vacaciones.Proyect.com

# Base de datos SQL Server
DB_CONNECTION=sqlsrv
DB_HOST=localhost
DB_PORT=1433
DB_DATABASE=vacation_db
DB_USERNAME=sa
DB_PASSWORD=tu_password_seguro

# Sesiones — usar file o redis en producción (nunca database con muchos usuarios)
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache
CACHE_STORE=database        # cambiar a redis si está disponible

# Telescope — deshabilitar en producción
TELESCOPE_ENABLED=false

# Cola de trabajos
QUEUE_CONNECTION=database
```

### Credenciales iniciales de prueba

| Rol        | Nómina  | Contraseña | Notas                          |
|------------|---------|------------|--------------------------------|
| SuperAdmin | `admin` | `12345`    | **Cambiar inmediatamente**     |
| Admin RH   | `rh001` | `rh001`    | Cambio forzado en primer login |
| Supervisor | `sup001`| `sup001`   | Cambio forzado en primer login |
| Empleado   | `emp001`| `emp001`   | Cambio forzado en primer login |

> **Importante:** Cambiar todas las contraseñas antes de usar en producción.

---

## Roles y Permisos

```
SuperAdmin (rol 4)
 ├── Todo lo de Admin RH
 ├── Panel de mantenimiento del sistema
 ├── Auditoría completa exportable
 ├── Importar/Exportar empleados (Excel)
 ├── Backup y restauración de BD
 └── Reinicio del sistema (con confirmación de contraseña)

Admin RH (rol 3)
 ├── Dashboard con KPIs
 ├── Aprobar/Rechazar solicitudes (paso final)
 ├── Gestión de empleados (alta, baja, roles, desbloqueo)
 ├── Gestión de grupos y supervisores
 ├── Días especiales y quincenas
 └── Exportar reservas a Excel (formato RH + TREESS)

Supervisor (rol 2)
 ├── Panel de su equipo
 ├── Visto bueno / Rechazo de solicitudes
 └── Ver solicitudes propias

Empleado (rol 1)
 ├── Crear solicitudes de vacaciones/permisos
 ├── Ver historial propio
 └── Cancelar solicitudes pendientes
```

---

## Estructura del Proyecto

```
Proyect-vacaciones/
├── app/
│   ├── Console/Commands/
│   │   ├── LimpiarAuditoriasAntiguas.php   # Limpieza mensual de auditorías
│   │   └── LimpiarDatosViejos.php          # Limpieza semanal de datos de soporte
│   ├── Http/
│   │   ├── Controllers/                    # AuthController, AdminController, etc.
│   │   └── Middleware/                     # RoleMiddleware, CheckMaintenanceMode, etc.
│   ├── Models/                             # Empleado, Reserva, Auditoria, etc.
│   └── Services/
│       └── LoginSeguridad.php              # Lógica de bloqueo por intentos fallidos
├── database/
│   └── vacation_db.sql                     # Script completo de BD
├── public/
│   └── build/                              # Assets compilados (Vite) — no requiere Node en servidor
├── resources/
│   ├── js/                                 # Scripts por módulo (admin, personal, etc.)
│   └── views/                              # Vistas Blade
├── routes/
│   ├── web.php                             # Todas las rutas de la aplicación
│   └── console.php                         # Comandos y schedule
└── .env.example                            # Plantilla de configuración
```

---

## Comandos de Mantenimiento

### Limpieza de datos de soporte

```bash
# Ver cuántos registros se eliminarían (sin borrar)
php artisan sistema:limpiar --dry-run

# Ejecutar limpieza real
php artisan sistema:limpiar

# Opciones personalizadas
php artisan sistema:limpiar --dias-login-ok=90 --dias-login-fail=30
```

### Limpieza de auditorías

```bash
# Ver registros a eliminar (sin borrar)
php artisan auditorias:limpiar --meses=12

# Limpiar auditorías de más de 6 meses
php artisan auditorias:limpiar --meses=6
```

### Optimización de caché (producción)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Limpiar caché en desarrollo

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

---

## Scheduler

El sistema incluye tareas programadas que se ejecutan automáticamente.

### Tareas configuradas

| Expresión cron | Descripción                                               |
|----------------|-----------------------------------------------------------|
| `0 2 1 * *`    | Día 1 de cada mes a las 2:00 AM — Limpia auditorías viejas |
| `0 3 * * 0`    | Todos los domingos a las 3:00 AM — Limpia login_intentos, sessions y cache |

### Activar el scheduler

**En Windows (servidor de producción):**

Crear una tarea programada que ejecute cada minuto:

```
Programa: php
Argumentos: C:\ruta\al\Proyecto\artisan schedule:run
```

**En Linux:**

```bash
# Agregar al crontab del servidor
* * * * * cd /var/www/Proyect && php artisan schedule:run >> /dev/null 2>&1
```

**Verificar:**

```bash
php artisan schedule:list
```

---

## Despliegue en Producción

```bash
# 1. Clonar en el servidor
git clone https://github.com/tu-usuario/Proyect-vacaciones.git /var/www/Proyect
cd /var/www/Proyect

# 2. Dependencias PHP (sin paquetes de desarrollo)
composer install --no-dev --optimize-autoloader

# 3. Configurar entorno
cp .env.example .env
# Editar .env con credenciales reales

# 4. Generar clave de aplicación
php artisan key:generate

# 5. Ejecutar script de BD en SQL Server Management Studio
#    → database/vacation_db.sql

# 6. Optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Permisos de storage (Linux)
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 8. Configurar el scheduler (ver sección anterior)
```

> Los assets ya están compilados en `public/build/` — **no se necesita Node.js en el servidor**.

---

## Licencias de Dependencias

Este Proyecto utiliza las siguientes dependencias de código abierto:

### Backend (PHP / Composer)

| Paquete                          | Versión  | Licencia   | Uso                                      |
|----------------------------------|----------|------------|------------------------------------------|
| `laravel/framework`              | 12.x     | MIT        | Framework principal                      |
| `laravel/telescope`              | 5.x      | MIT        | Monitoreo y debugging (solo desarrollo)  |
| `phpoffice/phpspreadsheet`       | 3.x      | MIT        | Generación y lectura de archivos Excel   |
| `carbon/carbon`                  | 3.x      | MIT        | Manejo de fechas y tiempos               |

### Frontend (compilado, incluido en `public/build/`)

| Paquete / CDN                    | Versión  | Licencia   | Uso                                      |
|----------------------------------|----------|------------|------------------------------------------|
| `tailwindcss`                    | 3.x      | MIT        | Framework de estilos CSS                 |
| `vite`                           | 6.x      | MIT        | Bundler y compilador de assets           |
| `@vitejs/plugin-laravel`         | —        | MIT        | Integración Vite con Laravel             |
| Font Awesome *(CDN)*             | 6.x      | CC BY 4.0 / OFL | Iconografía                         |
| Google Fonts *(CDN)*             | —        | OFL        | Tipografía (Inter)                       |

### Driver de base de datos

| Componente                       | Licencia         | Notas                              |
|----------------------------------|------------------|------------------------------------|
| Microsoft ODBC Driver 17         | Propietario MSFT | Requerido para conexión SQL Server |
| `ext-sqlsrv` / `ext-pdo_sqlsrv` | MIT              | Extensiones PHP para SQL Server    |

---

## Autor

**Gael Alvarado**  
Desarrollador del Sistema de Gestión de Vacaciones — Proyect  
2026

---

## Agradecimientos

- Al equipo de **Laravel** por un framework que hace posible construir sistemas robustos con elegancia.
- A **PhpOffice/PhpSpreadsheet** por la librería de Excel más completa del ecosistema PHP.
- A **TailwindCSS** por simplificar radicalmente el diseño de interfaces.
- Al equipo de **Microsoft** por los drivers ODBC y las extensiones PHP para SQL Server.
- A **Font Awesome** por la iconografía que da vida a la interfaz.
- Al área de **Recursos Humanos de Proyect** por definir los requerimientos y participar activamente en las pruebas del sistema.

---

## Licencia

Este sistema es **software propietario** desarrollado exclusivamente para uso interno de **Proyect**.  
Queda prohibida su distribución, modificación o uso fuera del contexto para el que fue desarrollado sin autorización expresa del autor y de la empresa.

© 2026 Gael Alvarado — Todos los derechos reservados.
