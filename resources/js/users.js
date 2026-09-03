'use strict';

let saldoActual = window.SALDO ?? 0;
let diasHabilesActual = 0;
let reservaAEliminar = null;
let ocultarCanceladas = true; // Por defecto ocultas

window.toggleCanceladas = function() {
    ocultarCanceladas = !ocultarCanceladas;
    const btn   = document.getElementById('btnToggleCanceladas');
    const icon  = document.getElementById('iconToggle');
    const label = document.getElementById('lblToggle');
    if (ocultarCanceladas) {
        icon.className  = 'ph ph-eye-slash';
        label.textContent = 'Ocultar canceladas';
        btn.classList.remove('bg-gray-100', 'text-gray-700');
    } else {
        icon.className  = 'ph ph-eye';
        label.textContent = 'Ver canceladas';
        btn.classList.add('bg-gray-100', 'text-gray-700');
    }
    cargarSolicitudes();
}

// ── Helpers ───────────────────────────────────────────────────────────────────

window.colorBadge = function(color) {
    const map = {
        yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        blue:   'bg-blue-100 text-blue-800 border-blue-200',
        green:  'bg-green-100 text-green-800 border-green-200',
        red:    'bg-red-100 text-red-800 border-red-200',
        gray:   'bg-gray-100 text-gray-700 border-gray-200',
    };
    return map[color] ?? map.gray;
}

window.showFormMsg = function(text, type) {
    const el = document.getElementById('formMsg');
    el.textContent = text;
    el.className = 'mb-3 p-3 rounded-lg text-sm border ' +
        (type === 'success'
            ? 'bg-green-50 text-green-700 border-green-200'
            : 'bg-red-50 text-red-700 border-red-200');
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

// ── Preview de fechas en tiempo real ─────────────────────────────────────────

window.actualizarPreview = async () => {
    const inicio = document.getElementById('fechaInicio').value;
    const fin    = document.getElementById('fechaFin').value;
    const card   = document.getElementById('previewCard');

    if (!inicio || !fin || fin < inicio) {
        card.classList.add('hidden');
        return;
    }

    // Mostrar loading mientras calcula
    document.getElementById('prevInicio').textContent  = '...';
    document.getElementById('prevFin').textContent     = '...';
    document.getElementById('prevRegreso').textContent = '...';
    document.getElementById('prevDias').textContent    = '...';
    card.classList.remove('hidden');

    try {
        const res  = await fetch('/api/reservas/calcular', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ fecha_inicial: inicio, fecha_final: fin }),
        });

        const data = await res.json();

        if (!res.ok) {
            // Mostrar el error en lugar de ocultar silenciosamente
            document.getElementById('prevInicio').textContent  = '—';
            document.getElementById('prevFin').textContent     = '—';
            document.getElementById('prevRegreso').textContent = '—';
            document.getElementById('prevDias').textContent    = '—';
            const warn  = document.getElementById('saldoWarning');
            const warnT = document.getElementById('saldoWarningText');
            warnT.textContent = data.error ?? data.message ?? 'Error al calcular fechas.';
            warn.classList.remove('hidden');
            return;
        }

        diasHabilesActual = data.dias_habiles;

        document.getElementById('prevInicio').textContent  = formatFecha(inicio);
        document.getElementById('prevFin').textContent     = formatFecha(fin);
        document.getElementById('prevRegreso').textContent = data.regreso;
        document.getElementById('prevDias').textContent    = `${data.dias_habiles} día(s)`;

        // Advertencia de saldo
        const tipoSel  = document.getElementById('tipoSolicitud');
        const usaSaldo = tipoSel.selectedOptions[0]?.dataset.usaSaldo === '1';
        const warning  = document.getElementById('saldoWarning');
        const warnText = document.getElementById('saldoWarningText');

        if (usaSaldo && data.dias_habiles > saldoActual) {
            warnText.textContent = `Saldo insuficiente: tienes ${saldoActual} día(s), solicitas ${data.dias_habiles}.`;
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }

    } catch (e) {
        console.error('Preview error:', e);
        document.getElementById('prevDias').textContent = 'Error';
    }
}

window.formatFecha = function(iso) {
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const [y, m, d] = iso.split('-');
    return `${parseInt(d)} ${meses[parseInt(m)-1]}`;
}

// ── Indicador de tipo que descuenta saldo ─────────────────────────────────────

// document.getElementById('tipoSolicitud').addEventListener('change', function() {
//     const usaSaldo = this.selectedOptions[0]?.dataset.usaSaldo === '1';
//     document.getElementById('saldoIndicador').classList.toggle('hidden', !usaSaldo);
//     actualizarPreview(); // Re-calcular advertencia de saldo
// });

// document.getElementById('fechaInicio').addEventListener('change', function() {
//     // El fin no puede ser anterior al inicio
//     document.getElementById('fechaFin').min = this.value;
//     actualizarPreview();
// });
// document.getElementById('fechaFin').addEventListener('change', actualizarPreview);

// ── Inicializar — reemplaza el DOMContentLoaded al final de users.js ──────────
document.addEventListener('DOMContentLoaded', async () => {
    // 1. Cargar tipos desde BD ANTES de que el usuario pueda interactuar
    //    cargarTiposSolicitud() está definida en tiposSolicitud.js (cargado primero)
    await cargarTiposSolicitud(['#tipoSolicitud']);

    // 2. Activar el indicador de saldo al cambiar el tipo (ahora el select ya tiene opciones)
    document.getElementById('tipoSolicitud').addEventListener('change', function () {
        const usaSaldo = this.selectedOptions[0]?.dataset.usaSaldo === '1';
        document.getElementById('saldoIndicador').classList.toggle('hidden', !usaSaldo);
        actualizarPreview();
    });

    // 3. Cargar solicitudes
    cargarSolicitudes();

    // 4. Eventos de fecha
    document.getElementById('fechaInicio').addEventListener('change', function () {
        document.getElementById('fechaFin').min = this.value;
        actualizarPreview();
    });
    document.getElementById('fechaFin').addEventListener('change', actualizarPreview);
});

// ── Enviar solicitud ──────────────────────────────────────────────────────────

window.solicitarFechas = async () => {
    const inicio   = document.getElementById('fechaInicio').value;
    const fin      = document.getElementById('fechaFin').value;
    const tipo     = document.getElementById('tipoSolicitud').value;
    const obs      = document.getElementById('observaciones').value;
    const btn      = document.getElementById('btnSolicitar');
    const btnText  = document.getElementById('btnText');

    if (!inicio || !fin) { showFormMsg('Selecciona las fechas de inicio y fin.', 'error'); return; }
    if (!tipo)           { showFormMsg('Selecciona el tipo de permiso.', 'error'); return; }

    btn.disabled = true;
    btnText.textContent = 'Enviando...';

    try {
        const res  = await fetch('/api/reservas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ fecha_inicial: inicio, fecha_final: fin, id_tipo: tipo, observaciones: obs }),
        });
        const data = await res.json();

        if (res.ok) {
            showFormMsg(data.message, 'success');

            // Actualizar saldo en pantalla
            saldoActual = data.nuevo_saldo;
            document.getElementById('saldoDisplay').textContent = data.nuevo_saldo;

            // Limpiar formulario
            document.getElementById('fechaInicio').value = '';
            document.getElementById('fechaFin').value    = '';
            document.getElementById('tipoSolicitud').value = '';
            document.getElementById('observaciones').value = '';
            document.getElementById('previewCard').classList.add('hidden');
            document.getElementById('saldoIndicador').classList.add('hidden');

            // Recargar tabla
            cargarSolicitudes();
        } else {
            showFormMsg(data.error ?? 'Error al enviar la solicitud.', 'error');
        }
    } catch (e) {
        showFormMsg('Error de conexión.', 'error');
    } finally {
        btn.disabled = false;
        btnText.textContent = 'Enviar Solicitud';
    }
}

// ── Cargar tabla de solicitudes ───────────────────────────────────────────────

window.cargarSolicitudes = async () => {
    const tbody = document.getElementById('tablaSolicitudes');
    tbody.innerHTML = `
        <tr><td colspan="5" class="px-5 py-8 text-center">
            <i class="fas fa-spinner fa-spin text-primary text-lg mb-1 block"></i>
            <span class="text-xs text-gray-400">Cargando...</span>
        </td></tr>`;

    try {
        const res  = await fetch('/api/reservas', { headers: { 'X-CSRF-TOKEN': CSRF } });
        const data = await res.json();

        if (!data.length) {
            tbody.innerHTML = `
                <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">
                    <i class="ph ph-calendar-x text-2xl mb-2 block"></i>
                    No tienes solicitudes registradas.
                </td></tr>`;
            return;
        }

        // Filtrar canceladas si el toggle está activo
        const mostrarCanceladas = !ocultarCanceladas;
        const dataFiltrada = mostrarCanceladas
            ? data
            : data.filter(r => r.color !== 'gray');

        if (!dataFiltrada.length && data.length > 0) {
            tbody.innerHTML = `
                <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">
                    <i class="ph ph-funnel text-2xl mb-2 block"></i>
                    Todas tus solicitudes están canceladas.
                    <button onclick="toggleCanceladas()" class="block mx-auto mt-2 text-primary text-xs underline">Mostrar canceladas</button>
                </td></tr>`;
            return;
        }

        tbody.innerHTML = dataFiltrada.map(r => `
            <tr class="hover:bg-blue-50/20 transition group">
                <td class="px-5 py-3">
                    <div class="text-sm font-semibold text-gray-800">${r.fecha_inicio} — ${r.fecha_fin}</div>
                    <div class="text-xs text-gray-400 mt-0.5">Solicitado: ${r.fecha_creacion}</div>
                </td>
                <td class="px-5 py-3">
                    <span class="text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full">${r.tipo}</span>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="text-xs font-bold text-gray-700 bg-gray-100 px-2.5 py-1 rounded-full">${r.dias_habiles}</span>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="px-2.5 py-1 inline-flex text-xs font-semibold rounded-full border ${colorBadge(r.color)}">${r.estado}</span>
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex justify-end gap-1 opacity-40 group-hover:opacity-100 transition">
                        <button onclick="verHistorial(${r.id})"
                                class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition text-xs"
                                title="Ver historial de cambios">
                            <i class="ph ph-clock-counter-clockwise"></i>
                        </button>
                        ${r.color === 'yellow' ? `
                        <button onclick="abrirCancelar(${r.id})"
                                class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition text-xs"
                                title="Cancelar solicitud">
                            <i class="ph ph-trash"></i>
                        </button>` : ''}
                        <div class="text-xs text-gray-400 py-2">
                            Regreso: <span class="font-medium text-gray-600">${r.regreso}</span>
                        </div>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-5 py-6 text-center text-red-400 text-sm">Error al cargar datos.</td></tr>`;
    }
}

// ── Cancelar solicitud ────────────────────────────────────────────────────────

window.abrirCancelar = function(id) {
    reservaAEliminar = id;
    openModal('deleteModal');
}

document.getElementById('btnConfirmarCancelar').addEventListener('click', async () => {
    if (!reservaAEliminar) return;

    try {
        const res  = await fetch(`/api/reservas/${reservaAEliminar}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const data = await res.json();

        if (res.ok) {
            saldoActual = data.nuevo_saldo;
            document.getElementById('saldoDisplay').textContent = data.nuevo_saldo;
            showFormMsg(data.message, 'success');
            cargarSolicitudes();
        } else {
            showFormMsg(data.error ?? 'No se pudo cancelar.', 'error');
        }
    } catch (e) {
        showFormMsg('Error de conexión.', 'error');
    } finally {
        closeModal('deleteModal');
        reservaAEliminar = null;
    }
});

// ── Inicializar ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', cargarSolicitudes);

// ── Historial de cambios ──────────────────────────────────────────────────────
window._cargarHistorialModal = async (id) => {
    document.getElementById('historialContenido').innerHTML =
        '<div class="py-6 text-center text-gray-400"><i class="fas fa-spinner fa-spin text-primary text-xl mb-2 block"></i>Cargando...</div>';
    openModal('historialModal');

    try {
        const res   = await fetch(`/api/reservas/${id}/historial`, { headers: { 'X-CSRF-TOKEN': CSRF } });
        const items = await res.json();

        if (!items.length) {
            document.getElementById('historialContenido').innerHTML =
                '<p class="text-center text-gray-400 text-sm py-6">Sin historial registrado.</p>';
            return;
        }

        const estadoColores = {
            'Pendiente':                'bg-yellow-100 text-yellow-700 border-yellow-200',
            'Visto Bueno Supervisor':   'bg-blue-100 text-blue-700 border-blue-200',
            'Aprobada':                 'bg-green-100 text-green-700 border-green-200',
            'Rechazada por Supervisor': 'bg-red-100 text-red-700 border-red-200',
            'Rechazada por RH':         'bg-red-100 text-red-700 border-red-200',
            'Cancelada':                'bg-gray-100 text-gray-600 border-gray-200',
        };

        const items_rev = [...items].reverse(); // mostrar más antiguo primero

        document.getElementById('historialContenido').innerHTML = `
        <ol class="relative border-l-2 border-gray-200 ml-3 space-y-5">
            ${items_rev.map((h, idx) => {
                const isFirst  = idx === 0;
                const colorNvo = estadoColores[h.estado_nuevo]  ?? 'bg-gray-100 text-gray-600 border-gray-200';
                const colorAnt = estadoColores[h.estado_anterior] ?? 'bg-gray-100 text-gray-500 border-gray-200';
                return `
                <li class="ml-8">
                    <span class="absolute flex items-center justify-center w-7 h-7 rounded-full -left-3.5 ring-4 ring-white
                        ${isFirst ? 'bg-yellow-50 border border-yellow-300' : 'bg-blue-50 border border-blue-200'}">
                        <i class="ph ${isFirst ? 'ph-file-plus text-yellow-600' : 'ph-arrow-circle-right text-primary'} text-sm"></i>
                    </span>
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                        <div class="flex justify-between items-start mb-2 flex-wrap gap-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                ${h.estado_anterior && h.estado_anterior !== 'Creación' ? `
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold border ${colorAnt}">${h.estado_anterior}</span>
                                <i class="ph ph-arrow-right text-gray-400 text-xs"></i>` : ''}
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold border ${colorNvo}">${h.estado_nuevo}</span>
                            </div>
                            <span class="text-xs text-gray-400 whitespace-nowrap">${h.fecha}</span>
                        </div>
                        <p class="text-xs font-semibold text-gray-600 flex items-center gap-1">
                            <i class="ph ph-user text-gray-400"></i>${h.modificado_por}
                        </p>
                        ${h.detalles ? `<p class="text-xs text-gray-500 mt-1.5 bg-white rounded-lg px-2.5 py-1.5 border border-gray-100 italic">"${h.detalles}"</p>` : ''}
                    </div>
                </li>`;
            }).join('')}
        </ol>`;
    } catch(e) {
        console.error('Historial error:', e);
        document.getElementById('historialContenido').innerHTML =
            '<p class="text-center text-red-400 text-sm py-6"><i class="fas fa-exclamation-circle mr-1"></i>Error al cargar el historial.</p>';
    }
}

window.verHistorial = function(id)       { _cargarHistorialModal(id); }
window.verHistorialMio = function(id)    { _cargarHistorialModal(id); }
window.verHistorialEquipo = function(id) { _cargarHistorialModal(id); }
