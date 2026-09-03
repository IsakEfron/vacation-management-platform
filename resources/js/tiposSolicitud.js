// ── tiposSolicitud.js ─────────────────────────────────────────────────────────
// Carga los tipos de solicitud activos desde BD y puebla el <select>.
// Encapsulado en IIFE para no contaminar el scope global.
// ─────────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    async function cargarTiposSolicitud(selectIds) {
        selectIds = selectIds || ['#tipoSolicitud'];

        // Mostrar estado "cargando" mientras se hace el fetch
        selectIds.forEach(function (selector) {
            var sel = document.querySelector(selector);
            if (sel) sel.innerHTML = '<option value="" disabled selected>Cargando tipos...</option>';
        });

        try {
            const res = await fetch('/api/reservas/tipos-catalogo', {
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                }
            });

            if (!res.ok) {
                console.error('[tiposSolicitud] HTTP ' + res.status + ' al cargar tipos.');
                mostrarFallback(selectIds, 'Error del servidor (' + res.status + ') — recarga la página');
                return;
            }

            var tipos;
            try {
                tipos = await res.json();
            } catch (parseErr) {
                console.error('[tiposSolicitud] Respuesta no es JSON válido:', parseErr);
                mostrarFallback(selectIds, 'Respuesta inválida — recarga la página');
                return;
            }

            if (!Array.isArray(tipos) || tipos.length === 0) {
                console.warn('[tiposSolicitud] Lista vacía — ¿existen tipos activos en BD?');
                mostrarFallback(selectIds, 'Sin tipos disponibles — contacta al administrador');
                return;
            }

            // Agrupar por campo 'grupo' que viene del backend
            var grupos = {};
            tipos.forEach(function (t) {
                if (!grupos[t.grupo]) grupos[t.grupo] = [];
                grupos[t.grupo].push(t);
            });

            var ordenGrupos = ['Vacaciones', 'Con Goce de Sueldo', 'Sin Goce de Sueldo'];

            selectIds.forEach(function (selector) {
                var sel = document.querySelector(selector);
                if (!sel) return;

                var valorPrevio = sel.value;
                sel.innerHTML = '<option value="" disabled selected>Seleccione una opción...</option>';

                ordenGrupos.forEach(function (nombreGrupo) {
                    var tiposGrupo = grupos[nombreGrupo];
                    if (!tiposGrupo || tiposGrupo.length === 0) return;

                    var optgroup   = document.createElement('optgroup');
                    optgroup.label = nombreGrupo;

                    tiposGrupo.forEach(function (t) {
                        var opt              = document.createElement('option');
                        opt.value            = t.id;          // ← sin corchetes, valor directo
                        opt.textContent      = t.nombre;
                        opt.dataset.usaSaldo = t.usa_saldo ? '1' : '0';
                        opt.dataset.conGoce  = t.con_goce  ? '1' : '0';
                        optgroup.appendChild(opt);
                    });

                    sel.appendChild(optgroup);
                });

                if (valorPrevio) sel.value = valorPrevio;
            });

        } catch (e) {
            // NetworkError, timeout, etc.
            console.error('[tiposSolicitud] Error de red:', e);
            mostrarFallback(selectIds, 'Sin conexión — recarga la página');
        }
    }

    function mostrarFallback(selectIds, mensaje) {
        selectIds.forEach(function (selector) {
            var sel = document.querySelector(selector);
            if (sel) {
                sel.innerHTML = '<option value="" disabled selected>' + mensaje + '</option>';
            }
        });
    }

    // Exponer al scope global para que users.js / sup_user.js lo llamen
    window.cargarTiposSolicitud = cargarTiposSolicitud;

})();