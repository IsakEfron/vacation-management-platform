-- ============================================================
-- crear_tablas_historicas.sql
-- Ejecutar como DBA una sola vez antes de producción.
-- Crea las tablas históricas de archivado para 2026-2035.
-- El usuario de la aplicación (canels_app_user) NO necesita
-- CREATE TABLE — solo INSERT en estas tablas.
-- Cambiar usuario {canels_app_user} por el usuario configurado.
-- ============================================================

USE [vacation_db];
GO

DECLARE @anio INT = 2026;
DECLARE @fin  INT = 2035;
DECLARE @sql  NVARCHAR(MAX);

WHILE @anio <= @fin
BEGIN
    -- Auditorías históricas
    SET @sql = N'
        IF OBJECT_ID(N''dbo.auditorias_historico_' + CAST(@anio AS NVARCHAR(4)) + N''', ''U'') IS NULL
        CREATE TABLE [dbo].[auditorias_historico_' + CAST(@anio AS NVARCHAR(4)) + N'] (
            [id_auditoria] [int]          NOT NULL,
            [empleado]     [varchar](255) NULL,
            [accion]       [varchar](100) NOT NULL,
            [detalles]     [varchar](500) NULL,
            [fecha]        [datetime2](7) NOT NULL,
            [ip_origen]    [varchar](45)  NULL,
            PRIMARY KEY CLUSTERED ([id_auditoria] ASC)
        );';
    EXEC sp_executesql @sql;

    -- Historial de reservas histórico
    SET @sql = N'
        IF OBJECT_ID(N''dbo.history_historico_' + CAST(@anio AS NVARCHAR(4)) + N''', ''U'') IS NULL
        CREATE TABLE [dbo].[history_historico_' + CAST(@anio AS NVARCHAR(4)) + N'] (
            [id_history]      [int]          NOT NULL,
            [id_reserva]      [int]          NOT NULL,
            [estado_anterior] [int]          NULL,
            [estado_nuevo]    [int]          NOT NULL,
            [modificado_por]  [varchar](255) NOT NULL,
            [detalles_cambio] [varchar](500) NULL,
            [fecha_cambio]    [datetime2](7) NOT NULL,
            PRIMARY KEY CLUSTERED ([id_history] ASC)
        );';
    EXEC sp_executesql @sql;

    -- Reservas históricas
    SET @sql = N'
        IF OBJECT_ID(N''dbo.reservas_historico_' + CAST(@anio AS NVARCHAR(4)) + N''', ''U'') IS NULL
        CREATE TABLE [dbo].[reservas_historico_' + CAST(@anio AS NVARCHAR(4)) + N'] (
            [id_reserva]    [int]          NOT NULL,
            [fecha_inicial] [date]         NOT NULL,
            [fecha_final]   [date]         NOT NULL,
            [dias_habiles]  [int]          NULL,
            [id_empleado]   [varchar](255) NOT NULL,
            [id_tipo]       [int]          NOT NULL,
            [estado]        [int]          NOT NULL,
            [observaciones] [varchar](500) NULL,
            [deleted_at]    [datetime2](7) NULL,
            [created_at]    [datetime2](7) NOT NULL,
            [updated_at]    [datetime2](7) NOT NULL,
            PRIMARY KEY CLUSTERED ([id_reserva] ASC)
        );';
    EXEC sp_executesql @sql;

    -- Dar permisos de INSERT/SELECT al usuario de la aplicación
    SET @sql = N'GRANT SELECT, INSERT ON [dbo].[auditorias_historico_'
        + CAST(@anio AS NVARCHAR(4)) + N'] TO [canels_app_user];';
    EXEC sp_executesql @sql;

    SET @sql = N'GRANT SELECT, INSERT ON [dbo].[history_historico_'
        + CAST(@anio AS NVARCHAR(4)) + N'] TO [canels_app_user];';
    EXEC sp_executesql @sql;

    SET @sql = N'GRANT SELECT, INSERT ON [dbo].[reservas_historico_'
        + CAST(@anio AS NVARCHAR(4)) + N'] TO [canels_app_user];';
    EXEC sp_executesql @sql;

    SET @anio = @anio + 1;
END

PRINT 'Tablas historicas creadas para 2026-2035. El usuario canels_app_user tiene INSERT/SELECT en ellas.';
GO