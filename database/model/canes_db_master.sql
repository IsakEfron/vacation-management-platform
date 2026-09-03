USE [master]
GO

-- Crear la base de datos si no existe
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'vacation_db')
    CREATE DATABASE [vacation_db];
GO

USE [vacation_db]
GO

-- ============================================================
-- TABLAS BASE (sin FK)
-- ============================================================

IF OBJECT_ID('dbo.roles', 'U') IS NULL
CREATE TABLE [dbo].[roles](
    [id_rol] [int] NOT NULL,
    [tipo]   [varchar](50) NOT NULL,
    [nivel]  [int] NOT NULL,
    PRIMARY KEY CLUSTERED ([id_rol] ASC)
);
GO

IF OBJECT_ID('dbo.estado', 'U') IS NULL
CREATE TABLE [dbo].[estado](
    [id_estado]   [int] IDENTITY(1,1) NOT NULL,
    [nombre]      [varchar](100) NOT NULL,
    [color_badge] [varchar](50)  NULL,
    PRIMARY KEY CLUSTERED ([id_estado] ASC)
);
GO

IF OBJECT_ID('dbo.tipo_solicitud', 'U') IS NULL
CREATE TABLE [dbo].[tipo_solicitud](
    [id_tipo]   [int] IDENTITY(1,1) NOT NULL,
    [nombre]    [varchar](150) NOT NULL,
    [con_goce]  [bit] NOT NULL DEFAULT(1),
    [usa_saldo] [bit] NOT NULL DEFAULT(0),
    [activo]    [bit] NOT NULL DEFAULT(1),
    PRIMARY KEY CLUSTERED ([id_tipo] ASC)
);
GO

-- ============================================================
-- TABLAS CON FK A roles
-- ============================================================

IF OBJECT_ID('dbo.empleados', 'U') IS NULL
CREATE TABLE [dbo].[empleados](
    [nomina]          [varchar](255) NOT NULL,
    [nombre]          [varchar](120) NOT NULL,
    [password]        [varchar](255) NOT NULL,
    [saldo]           [int]          NOT NULL DEFAULT(0),
    [rol]             [int]          NOT NULL,
    [activo]          [bit]          NOT NULL DEFAULT(1),
    [login_bloqueado] [bit]          NOT NULL DEFAULT(0),
    [primera_vez]     [bit]          NOT NULL DEFAULT(1),
    [tipo_nomina]     [tinyint]      NOT NULL DEFAULT(0),
    [centro_pago]     [varchar](100) NULL,
    [created_at]      [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [updated_at]      [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([nomina] ASC),
    CONSTRAINT [FK_empleados_roles] FOREIGN KEY([rol]) REFERENCES [dbo].[roles]([id_rol]),
    CONSTRAINT [CK_empleados_saldo_positivo]     CHECK ([saldo] >= 0),
    CONSTRAINT [CK_empleados_tipo_nomina_valido] CHECK ([tipo_nomina] IN (0, 1, 3))
);
GO

-- ============================================================
-- RESTO DE TABLAS
-- ============================================================

IF OBJECT_ID('dbo.reservas', 'U') IS NULL
CREATE TABLE [dbo].[reservas](
    [id_reserva]    [int]          IDENTITY(1,1) NOT NULL,
    [fecha_inicial] [date]         NOT NULL,
    [fecha_final]   [date]         NOT NULL,
    [dias_habiles]  [int]          NULL,
    [id_empleado]   [varchar](255) NOT NULL,
    [id_tipo]       [int]          NOT NULL,
    [estado]        [int]          NOT NULL,
    [observaciones] [varchar](500) NULL,
    [deleted_at]    [datetime2](7) NULL,
    [created_at]    [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [updated_at]    [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([id_reserva] ASC),
    CONSTRAINT [FK_reservas_empleado] FOREIGN KEY([id_empleado]) REFERENCES [dbo].[empleados]([nomina]),
    CONSTRAINT [FK_reservas_estado]   FOREIGN KEY([estado])      REFERENCES [dbo].[estado]([id_estado]),
    CONSTRAINT [FK_reservas_tipo]     FOREIGN KEY([id_tipo])     REFERENCES [dbo].[tipo_solicitud]([id_tipo]),
    CONSTRAINT [CK_reservas_fechas]         CHECK ([fecha_final] >= [fecha_inicial]),
    CONSTRAINT [CK_reservas_dias_positivos] CHECK ([dias_habiles] IS NULL OR [dias_habiles] >= 0)
);
GO

IF OBJECT_ID('dbo.history', 'U') IS NULL
CREATE TABLE [dbo].[history](
    [id_history]      [int]          IDENTITY(1,1) NOT NULL,
    [id_reserva]      [int]          NOT NULL,
    [estado_anterior] [int]          NULL,
    [estado_nuevo]    [int]          NOT NULL,
    [modificado_por]  [varchar](255) NOT NULL,
    [detalles_cambio] [varchar](500) NULL,
    [fecha_cambio]    [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([id_history] ASC),
    CONSTRAINT [FK_history_reserva]     FOREIGN KEY([id_reserva])      REFERENCES [dbo].[reservas]([id_reserva]) ON DELETE CASCADE,
    CONSTRAINT [FK_history_estado_ant]  FOREIGN KEY([estado_anterior]) REFERENCES [dbo].[estado]([id_estado]),
    CONSTRAINT [FK_history_estado_nuevo]FOREIGN KEY([estado_nuevo])    REFERENCES [dbo].[estado]([id_estado]),
    CONSTRAINT [FK_history_modificado]  FOREIGN KEY([modificado_por])  REFERENCES [dbo].[empleados]([nomina])
);
GO

IF OBJECT_ID('dbo.grupos', 'U') IS NULL
CREATE TABLE [dbo].[grupos](
    [id_grupo]   [int]          IDENTITY(1,1) NOT NULL,
    [nombre]     [varchar](255) NOT NULL,
    [supervisor] [varchar](255) NOT NULL,
    [created_at] [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [updated_at] [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([id_grupo] ASC),
    CONSTRAINT [FK_grupos_supervisor] FOREIGN KEY([supervisor]) REFERENCES [dbo].[empleados]([nomina])
);
GO

IF OBJECT_ID('dbo.grupo_empleado', 'U') IS NULL
CREATE TABLE [dbo].[grupo_empleado](
    [id]         [int]          IDENTITY(1,1) NOT NULL,
    [id_grupo]   [int]          NOT NULL,
    [nomina]     [varchar](255) NOT NULL,
    [created_at] [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ_ge_unico]   UNIQUE ([id_grupo], [nomina]),
    CONSTRAINT [FK_ge_grupo]   FOREIGN KEY([id_grupo]) REFERENCES [dbo].[grupos]([id_grupo]) ON DELETE CASCADE,
    CONSTRAINT [FK_ge_empleado]FOREIGN KEY([nomina])   REFERENCES [dbo].[empleados]([nomina])
);
GO

IF OBJECT_ID('dbo.auditorias', 'U') IS NULL
CREATE TABLE [dbo].[auditorias](
    [id_auditoria] [int]          IDENTITY(1,1) NOT NULL,
    [empleado]     [varchar](255) NULL,
    [accion]       [varchar](100) NOT NULL,
    [detalles]     [varchar](500) NULL,
    [fecha]        [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [ip_origen]    [varchar](45)  NULL,
    PRIMARY KEY CLUSTERED ([id_auditoria] ASC)
);
GO

IF OBJECT_ID('dbo.mantenimientos', 'U') IS NULL
CREATE TABLE [dbo].[mantenimientos](
    [id_mantenimiento] [int]          IDENTITY(1,1) NOT NULL,
    [categoria]        [varchar](100) NOT NULL,
    [fecha_inicio]     [datetime2](7) NULL,
    [fecha_fin]        [datetime2](7) NULL,
    [notas]            [varchar](500) NULL,
    [estado]           [tinyint]      NOT NULL DEFAULT(1),
    [creado_por]       [varchar](255) NULL,
    [created_at]       [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [updated_at]       [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([id_mantenimiento] ASC),
    CONSTRAINT [FK_mant_creado]      FOREIGN KEY([creado_por]) REFERENCES [dbo].[empleados]([nomina]),
    CONSTRAINT [CK_mant_estado_valido] CHECK ([estado] IN (1,2,3,4,5))
);
GO

IF OBJECT_ID('dbo.login_intentos', 'U') IS NULL
CREATE TABLE [dbo].[login_intentos](
    [id]           [int]          IDENTITY(1,1) NOT NULL,
    [nomina]       [varchar](255) NOT NULL,
    [ip]           [varchar](45)  NOT NULL,
    [fecha]        [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [exitoso]      [bit]          NOT NULL DEFAULT(0),
    [bloqueado_en] [datetime2](7) NULL,
    PRIMARY KEY CLUSTERED ([id] ASC)
);
GO

IF OBJECT_ID('dbo.dias_especiales', 'U') IS NULL
CREATE TABLE [dbo].[dias_especiales](
    [id_dia]         [int]          IDENTITY(1,1) NOT NULL,
    [fecha]          [date]         NOT NULL,
    [descripcion]    [varchar](200) NOT NULL,
    [tipo]           [varchar](20)  NOT NULL,
    [aplica_a]       [varchar](100) NOT NULL,
    [activo]         [bit]          NOT NULL DEFAULT(1),
    [creado_por]     [varchar](255) NULL,
    [modificado_por] [varchar](255) NULL,
    [created_at]     [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [updated_at]     [datetime2](7) NULL,
    PRIMARY KEY CLUSTERED ([id_dia] ASC),
    CONSTRAINT [FK_dias_creado]    FOREIGN KEY([creado_por])     REFERENCES [dbo].[empleados]([nomina]),
    CONSTRAINT [FK_dias_modificado]FOREIGN KEY([modificado_por]) REFERENCES [dbo].[empleados]([nomina]),
    CONSTRAINT [CK_dias_tipo_valido] CHECK ([tipo] IN ('feriado','puente','especial'))
);
GO

IF OBJECT_ID('dbo.centro_dias_habiles', 'U') IS NULL
CREATE TABLE [dbo].[centro_dias_habiles](
    [id]          [int]          IDENTITY(1,1) NOT NULL,
    [centro_pago] [varchar](100) NOT NULL,
    [dia_semana]  [tinyint]      NOT NULL,
    [es_habil]    [bit]          NOT NULL DEFAULT(1),
    PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ_centro_dia]           UNIQUE ([centro_pago], [dia_semana]),
    CONSTRAINT [CK_centro_dia_semana_valido] CHECK ([dia_semana] BETWEEN 1 AND 7)
);
GO

IF OBJECT_ID('dbo.quincenas', 'U') IS NULL
CREATE TABLE [dbo].[quincenas](
    [id_quincena]  [int]          IDENTITY(1,1) NOT NULL,
    [descripcion]  [varchar](100) NOT NULL,
    [numero]       [tinyint]      NOT NULL,
    [anio]         [smallint]     NOT NULL,
    [fecha_inicio] [date]         NOT NULL,
    [fecha_fin]    [date]         NOT NULL,
    [activo]       [bit]          NOT NULL DEFAULT(1),
    [creado_por]   [varchar](255) NULL,
    [created_at]   [datetime2](7) NOT NULL DEFAULT(GETDATE()),
    [updated_at]   [datetime2](7) NULL,
    PRIMARY KEY CLUSTERED ([id_quincena] ASC),
    CONSTRAINT [UQ_quincena_numero_anio] UNIQUE ([numero], [anio]),
    CONSTRAINT [FK_quincenas_creado]     FOREIGN KEY([creado_por]) REFERENCES [dbo].[empleados]([nomina]),
    CONSTRAINT [CK_quincena_numero]      CHECK ([numero] BETWEEN 1 AND 24),
    CONSTRAINT [CK_quincena_anio]        CHECK ([anio] BETWEEN 2020 AND 2099),
    CONSTRAINT [CK_quincena_fechas]      CHECK ([fecha_fin] >= [fecha_inicio])
);
GO

-- Tablas de Laravel (cache, sessions, jobs, etc.)

IF OBJECT_ID('dbo.cache', 'U') IS NULL
CREATE TABLE [dbo].[cache](
    [key]        [nvarchar](255) NOT NULL,
    [value]      [nvarchar](max) NOT NULL,
    [expiration] [bigint]        NOT NULL,
    CONSTRAINT [cache_key_primary] PRIMARY KEY CLUSTERED ([key] ASC)
);
GO

IF OBJECT_ID('dbo.cache_locks', 'U') IS NULL
CREATE TABLE [dbo].[cache_locks](
    [key]        [nvarchar](255) NOT NULL,
    [owner]      [nvarchar](255) NOT NULL,
    [expiration] [bigint]        NOT NULL,
    CONSTRAINT [cache_locks_key_primary] PRIMARY KEY CLUSTERED ([key] ASC)
);
GO

-- ============================================================
-- TABLA sessions — CORREGIDA (user_id como nvarchar, no bigint)
-- ============================================================
IF OBJECT_ID('dbo.sessions', 'U') IS NULL
CREATE TABLE [dbo].[sessions](
    [id]            [nvarchar](255) NOT NULL,
    [user_id]       [nvarchar](255) NULL,      -- CORREGIDO: era bigint, debe ser nvarchar
    [ip_address]    [nvarchar](45)  NULL,
    [user_agent]    [nvarchar](max) NULL,
    [payload]       [nvarchar](max) NOT NULL,
    [last_activity] [int]           NOT NULL,
    CONSTRAINT [sessions_id_primary] PRIMARY KEY CLUSTERED ([id] ASC)
);
GO

IF OBJECT_ID('dbo.jobs', 'U') IS NULL
CREATE TABLE [dbo].[jobs](
    [id]           [bigint]        IDENTITY(1,1) NOT NULL,
    [queue]        [nvarchar](255) NOT NULL,
    [payload]      [nvarchar](max) NOT NULL,
    [attempts]     [tinyint]       NOT NULL,
    [reserved_at]  [int]           NULL,
    [available_at] [int]           NOT NULL,
    [created_at]   [int]           NOT NULL,
    PRIMARY KEY CLUSTERED ([id] ASC)
);
GO

IF OBJECT_ID('dbo.failed_jobs', 'U') IS NULL
CREATE TABLE [dbo].[failed_jobs](
    [id]         [bigint]        IDENTITY(1,1) NOT NULL,
    [uuid]       [nvarchar](255) NOT NULL,
    [connection] [nvarchar](max) NOT NULL,
    [queue]      [nvarchar](max) NOT NULL,
    [payload]    [nvarchar](max) NOT NULL,
    [exception]  [nvarchar](max) NOT NULL,
    [failed_at]  [datetime]      NOT NULL DEFAULT(GETDATE()),
    PRIMARY KEY CLUSTERED ([id] ASC)
);
GO

IF OBJECT_ID('dbo.migrations', 'U') IS NULL
CREATE TABLE [dbo].[migrations](
    [id]        [int]           IDENTITY(1,1) NOT NULL,
    [migration] [nvarchar](255) NOT NULL,
    [batch]     [int]           NOT NULL,
    PRIMARY KEY CLUSTERED ([id] ASC)
);
GO

-- ============================================================
-- VISTAS
-- ============================================================

IF OBJECT_ID('dbo.vw_reservas_completas', 'V') IS NOT NULL DROP VIEW [dbo].[vw_reservas_completas];
GO
CREATE VIEW [dbo].[vw_reservas_completas] AS
    SELECT r.id_reserva, r.fecha_inicial, r.fecha_final, r.dias_habiles,
           r.observaciones, r.created_at AS fecha_solicitud, r.deleted_at,
           e.nomina, e.nombre AS nombre_empleado, e.centro_pago, e.tipo_nomina,
           es.id_estado, es.nombre AS estado_nombre, es.color_badge,
           ts.id_tipo, ts.nombre AS tipo_nombre, ts.con_goce, ts.usa_saldo
    FROM   reservas r
    INNER JOIN empleados      e  ON r.id_empleado = e.nomina
    INNER JOIN estado         es ON r.estado       = es.id_estado
    INNER JOIN tipo_solicitud ts ON r.id_tipo      = ts.id_tipo;
GO

IF OBJECT_ID('dbo.vw_historial_completo', 'V') IS NOT NULL DROP VIEW [dbo].[vw_historial_completo];
GO
CREATE VIEW [dbo].[vw_historial_completo] AS
    SELECT h.id_history, h.id_reserva, h.fecha_cambio, h.detalles_cambio,
           e.nombre  AS modificado_por_nombre, h.modificado_por AS modificado_por_nomina,
           ea.nombre AS estado_anterior_nombre, ea.color_badge AS color_anterior,
           en.nombre AS estado_nuevo_nombre,    en.color_badge AS color_nuevo
    FROM   history h
    INNER JOIN empleados e  ON h.modificado_por  = e.nomina
    LEFT  JOIN estado    ea ON h.estado_anterior = ea.id_estado
    INNER JOIN estado    en ON h.estado_nuevo    = en.id_estado;
GO

IF OBJECT_ID('dbo.vw_grupos', 'V') IS NOT NULL DROP VIEW [dbo].[vw_grupos];
GO
CREATE VIEW [dbo].[vw_grupos] AS
    SELECT g.id_grupo, g.nombre AS nombre_grupo, g.supervisor,
           e.nombre AS nombre_supervisor, COUNT(ge.nomina) AS total_miembros
    FROM   grupos g
    INNER JOIN empleados      e  ON g.supervisor = e.nomina
    LEFT  JOIN grupo_empleado ge ON g.id_grupo   = ge.id_grupo
    GROUP BY g.id_grupo, g.nombre, g.supervisor, e.nombre;
GO

-- ============================================================
-- ÍNDICES ORIGINALES
-- ============================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_auditorias_fecha_accion')
CREATE NONCLUSTERED INDEX [IX_auditorias_fecha_accion] ON [dbo].[auditorias]
([fecha] DESC, [accion] ASC) INCLUDE([empleado],[detalles]);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'cache_expiration_index')
CREATE NONCLUSTERED INDEX [cache_expiration_index] ON [dbo].[cache] ([expiration] ASC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'cache_locks_expiration_index')
CREATE NONCLUSTERED INDEX [cache_locks_expiration_index] ON [dbo].[cache_locks] ([expiration] ASC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_dias_activo_fecha')
CREATE NONCLUSTERED INDEX [IX_dias_activo_fecha] ON [dbo].[dias_especiales]
([activo] ASC, [fecha] ASC) WHERE ([activo] = 1);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_dias_especiales_fecha_aplica')
CREATE NONCLUSTERED INDEX [IX_dias_especiales_fecha_aplica] ON [dbo].[dias_especiales]
([activo] ASC, [fecha] ASC, [aplica_a] ASC) WHERE ([activo] = 1);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_empleados_activo')
CREATE NONCLUSTERED INDEX [IX_empleados_activo] ON [dbo].[empleados]
([activo] ASC) INCLUDE([nomina],[nombre],[saldo]);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_empleados_tipo_nomina')
CREATE NONCLUSTERED INDEX [IX_empleados_tipo_nomina] ON [dbo].[empleados]
([tipo_nomina] ASC) WHERE ([tipo_nomina] IS NOT NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_ge_grupo')
CREATE NONCLUSTERED INDEX [IX_ge_grupo] ON [dbo].[grupo_empleado] ([id_grupo] ASC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_history_modificado_fecha')
CREATE NONCLUSTERED INDEX [IX_history_modificado_fecha] ON [dbo].[history]
([modificado_por] ASC, [fecha_cambio] DESC) INCLUDE([id_reserva],[estado_nuevo]);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_history_reserva')
CREATE NONCLUSTERED INDEX [IX_history_reserva] ON [dbo].[history]
([id_reserva] ASC, [fecha_cambio] DESC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'jobs_queue_index')
CREATE NONCLUSTERED INDEX [jobs_queue_index] ON [dbo].[jobs] ([queue] ASC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_login_intentos_fecha')
CREATE NONCLUSTERED INDEX [IX_login_intentos_fecha] ON [dbo].[login_intentos]
([fecha] ASC) WHERE ([exitoso] = 0);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_login_intentos_ip_bloqueado')
CREATE NONCLUSTERED INDEX [IX_login_intentos_ip_bloqueado] ON [dbo].[login_intentos]
([ip] ASC, [bloqueado_en] ASC) WHERE ([bloqueado_en] IS NOT NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_login_intentos_nomina')
CREATE NONCLUSTERED INDEX [IX_login_intentos_nomina] ON [dbo].[login_intentos]
([nomina] ASC, [bloqueado_en] ASC) WHERE ([bloqueado_en] IS NOT NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_login_nomina_fecha')
CREATE NONCLUSTERED INDEX [IX_login_nomina_fecha] ON [dbo].[login_intentos]
([nomina] ASC, [fecha] DESC) INCLUDE([exitoso],[bloqueado_en]);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mant_estado_activo')
CREATE NONCLUSTERED INDEX [IX_mant_estado_activo] ON [dbo].[mantenimientos]
([estado] ASC) WHERE ([estado] = 2);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_quincenas_anio_activo')
CREATE NONCLUSTERED INDEX [IX_quincenas_anio_activo] ON [dbo].[quincenas]
([anio] ASC, [activo] ASC, [fecha_inicio] ASC) WHERE ([activo] = 1);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_reservas_empleado')
CREATE NONCLUSTERED INDEX [IX_reservas_empleado] ON [dbo].[reservas]
([id_empleado] ASC) WHERE ([deleted_at] IS NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_reservas_empleado_estado')
CREATE NONCLUSTERED INDEX [IX_reservas_empleado_estado] ON [dbo].[reservas]
([id_empleado] ASC, [estado] ASC) WHERE ([deleted_at] IS NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_reservas_estado')
CREATE NONCLUSTERED INDEX [IX_reservas_estado] ON [dbo].[reservas]
([estado] ASC) WHERE ([deleted_at] IS NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_reservas_estado_deleted')
CREATE NONCLUSTERED INDEX [IX_reservas_estado_deleted] ON [dbo].[reservas]
([estado] ASC, [deleted_at] ASC, [created_at] ASC) INCLUDE([id_empleado]);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'sessions_last_activity_index')
CREATE NONCLUSTERED INDEX [sessions_last_activity_index] ON [dbo].[sessions] ([last_activity] ASC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'sessions_user_id_index')
CREATE NONCLUSTERED INDEX [sessions_user_id_index] ON [dbo].[sessions] ([user_id] ASC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_tipo_solicitud_activo')
CREATE NONCLUSTERED INDEX [IX_tipo_solicitud_activo] ON [dbo].[tipo_solicitud]
([activo] ASC) WHERE ([activo] = 1);
GO

-- ============================================================
-- ÍNDICES NUEVOS (mejoras de rendimiento)
-- ============================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mant_estado_programado')
CREATE NONCLUSTERED INDEX [IX_mant_estado_programado] ON [dbo].[mantenimientos]
([estado] ASC) WHERE ([estado] = 1);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_auditorias_empleado_fecha')
CREATE NONCLUSTERED INDEX [IX_auditorias_empleado_fecha] ON [dbo].[auditorias]
([empleado] ASC, [fecha] DESC) INCLUDE([accion],[detalles]);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_login_intentos_exitoso')
CREATE NONCLUSTERED INDEX [IX_login_intentos_exitoso] ON [dbo].[login_intentos]
([exitoso] ASC, [fecha] DESC) WHERE ([exitoso] = 1);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_reservas_fechas')
CREATE NONCLUSTERED INDEX [IX_reservas_fechas] ON [dbo].[reservas]
([fecha_inicial] ASC, [fecha_final] ASC) WHERE ([deleted_at] IS NULL);
GO

-- Para el filtro de búsqueda en auditorías (AuditoriaController::index)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_auditorias_empleado_accion')
CREATE NONCLUSTERED INDEX [IX_auditorias_empleado_accion] ON [dbo].[auditorias]
([empleado] ASC, [accion] ASC, [fecha] DESC);
GO

-- Para PersonalController::index cuando busca por nombre
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_empleados_nombre')
CREATE NONCLUSTERED INDEX [IX_empleados_nombre] ON [dbo].[empleados]
([nombre] ASC, [activo] ASC)
INCLUDE([nomina],[saldo],[rol],[centro_pago],[tipo_nomina]);
GO

-- ============================================================
-- STORED PROCEDURE
-- ============================================================

IF OBJECT_ID('dbo.sp_ArchivarAuditorias', 'P') IS NOT NULL
    DROP PROCEDURE [dbo].[sp_ArchivarAuditorias];
GO
CREATE PROCEDURE [dbo].[sp_ArchivarAuditorias]
    @anio INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @anioArchivar   INT           = ISNULL(@anio, YEAR(GETDATE()) - 1);
    DECLARE @tablaHistorico NVARCHAR(100) = 'auditorias_historico_' + CAST(@anioArchivar AS VARCHAR(4));
    DECLARE @sql            NVARCHAR(MAX);
    DECLARE @registros      INT;
    DECLARE @copiados       INT;
    DECLARE @msg            NVARCHAR(500);

    SELECT @registros = COUNT(*) FROM auditorias WHERE YEAR(fecha) = @anioArchivar;
    IF @registros = 0 BEGIN
        PRINT 'Sin auditorías del año ' + CAST(@anioArchivar AS VARCHAR(4)) + ' — nada que archivar.';
        RETURN;
    END

    SET @sql = N'IF NOT EXISTS (SELECT * FROM sys.objects WHERE name = ''' + @tablaHistorico + N''' AND type = ''U'')
                 SELECT TOP 0 * INTO [' + @tablaHistorico + N'] FROM auditorias;';
    EXEC sp_executesql @sql;

    SET @sql = N'INSERT INTO [' + @tablaHistorico + N'] SELECT * FROM auditorias WHERE YEAR(fecha) = ' + CAST(@anioArchivar AS NVARCHAR(4)) + N';';
    EXEC sp_executesql @sql;

    SET @sql = N'SELECT @c = COUNT(*) FROM [' + @tablaHistorico + N'] WHERE YEAR(fecha) = ' + CAST(@anioArchivar AS NVARCHAR(4)) + N';';
    EXEC sp_executesql @sql, N'@c INT OUTPUT', @c = @copiados OUTPUT;

    IF @copiados >= @registros BEGIN
        DELETE FROM auditorias WHERE YEAR(fecha) = @anioArchivar;
        PRINT 'OK — ' + CAST(@registros AS VARCHAR(10)) + ' registros archivados en [' + @tablaHistorico + '].';
    END ELSE BEGIN
        SET @msg = 'ERROR — Conteos no coinciden. No se eliminaron registros.';
        RAISERROR(@msg, 16, 1);
    END
END
GO

-- ============================================================
-- DATOS INICIALES (catálogos)
-- ============================================================

-- Roles
IF NOT EXISTS (SELECT 1 FROM [dbo].[roles] WHERE id_rol = 1)
INSERT INTO [dbo].[roles] (id_rol, tipo, nivel) VALUES
    (1, 'Empleado',   1),
    (2, 'Supervisor', 2),
    (3, 'Admin RH',   3),
    (4, 'SuperAdmin', 4);
GO

-- Estados de reserva
IF NOT EXISTS (SELECT 1 FROM [dbo].[estado])
INSERT INTO [dbo].[estado] (nombre, color_badge) VALUES
    ('Pendiente',                'yellow'),
    ('Visto Bueno Supervisor',   'blue'),
    ('Rechazada por Supervisor', 'red'),
    ('Aprobada',                 'green'),
    ('Rechazada por RH',         'red'),
    ('Cancelada',                'gray');
GO

-- Tipos de solicitud
IF NOT EXISTS (SELECT 1 FROM [dbo].[tipo_solicitud])
INSERT INTO [dbo].[tipo_solicitud] (nombre, con_goce, usa_saldo, activo) VALUES
    ('Vacaciones',              1, 1, 1),
    ('Permiso con goce',        1, 0, 1),
    ('Permiso sin goce',        0, 0, 1),
    ('Día económico',           1, 0, 1),
    ('Incapacidad médica',      1, 0, 1);
GO

-- SuperAdmin de prueba
-- Password: Admin123! (bcrypt)
-- IMPORTANTE: cambiar en producción
IF NOT EXISTS (SELECT 1 FROM [dbo].[empleados] WHERE nomina = 'admin')
INSERT INTO [dbo].[empleados]
    (nomina, nombre, password, saldo, rol, activo, login_bloqueado, primera_vez, tipo_nomina, centro_pago)
VALUES
    ('admin',
     'Administrador del Sistema',
     '$2y$12$HTxXhNK89xEVYTqoV31oM.oV1aKopce3CgIrV74xu7Kp3mHdiN/Iy', -- 12345
     0, 4, 1, 0, 0, 0, NULL);
GO

-- Empleado de prueba — rol Empleado
-- Password: su nómina (MD5 temporal): emp001
IF NOT EXISTS (SELECT 1 FROM [dbo].[empleados] WHERE nomina = 'emp001')
INSERT INTO [dbo].[empleados]
    (nomina, nombre, password, saldo, rol, activo, login_bloqueado, primera_vez, tipo_nomina, centro_pago)
VALUES
    ('emp001',
     'Juan Pérez García',
     CONCAT('$md5$', LOWER(CONVERT(VARCHAR(32), HASHBYTES('MD5', 'emp001'), 2))),
     15, 1, 1, 0, 1, 3, 'Planta Principal');
GO

-- Empleado de prueba — rol Supervisor
IF NOT EXISTS (SELECT 1 FROM [dbo].[empleados] WHERE nomina = 'sup001')
INSERT INTO [dbo].[empleados]
    (nomina, nombre, password, saldo, rol, activo, login_bloqueado, primera_vez, tipo_nomina, centro_pago)
VALUES
    ('sup001',
     'María González López',
     CONCAT('$md5$', LOWER(CONVERT(VARCHAR(32), HASHBYTES('MD5', 'sup001'), 2))),
     10, 2, 1, 0, 1, 1, 'Planta Principal');
GO

-- Empleado de prueba — rol Admin RH
IF NOT EXISTS (SELECT 1 FROM [dbo].[empleados] WHERE nomina = 'rh001')
INSERT INTO [dbo].[empleados]
    (nomina, nombre, password, saldo, rol, activo, login_bloqueado, primera_vez, tipo_nomina, centro_pago)
VALUES
    ('rh001',
     'Carlos Rodríguez Martínez',
     CONCAT('$md5$', LOWER(CONVERT(VARCHAR(32), HASHBYTES('MD5', 'rh001'), 2))),
     0, 3, 1, 0, 1, 0, NULL);
GO

-- Días especiales de prueba (2026)
IF NOT EXISTS (SELECT 1 FROM [dbo].[dias_especiales] WHERE YEAR(fecha) = 2026)
INSERT INTO [dbo].[dias_especiales] (fecha, descripcion, tipo, aplica_a, activo, creado_por)
VALUES
    ('2026-01-01', 'Año Nuevo',              'feriado', 'todos', 1, 'admin'),
    ('2026-02-02', 'Día de la Constitución', 'feriado', 'todos', 1, 'admin'),
    ('2026-03-16', 'Natalicio de Juárez',    'feriado', 'todos', 1, 'admin'),
    ('2026-05-01', 'Día del Trabajo',        'feriado', 'todos', 1, 'admin'),
    ('2026-09-16', 'Independencia',          'feriado', 'todos', 1, 'admin'),
    ('2026-11-02', 'Día de Muertos',         'feriado', 'todos', 1, 'admin'),
    ('2026-11-16', 'Revolución Mexicana',    'feriado', 'todos', 1, 'admin'),
    ('2026-12-25', 'Navidad',                'feriado', 'todos', 1, 'admin');
GO


USE [vacation_db];
ALTER DATABASE [vacation_db] SET AUTO_UPDATE_STATISTICS ON;
ALTER DATABASE [vacation_db] SET AUTO_CREATE_STATISTICS ON;
GO

PRINT 'Base de datos vacation_db creada y configurada correctamente.';
PRINT '';
PRINT 'Credenciales de prueba:';    
PRINT '  SuperAdmin : nomina=admin    / password=12345  (cambiar en primer login)';
PRINT '  Admin RH   : nomina=rh001   / password=rh001   (cambiar en primer login)';
PRINT '  Supervisor : nomina=sup001  / password=sup001  (cambiar en primer login)';
PRINT '  Empleado   : nomina=emp001  / password=emp001  (cambiar en primer login)';
GO