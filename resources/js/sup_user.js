'use strict';

let evalId       = null;
let decisionSel  = null;
let reservaACancelar = null;
let ocultarCanceladasSup     = true;
let ocultarRechazadasEquipo  = true; // Equipo: ocultar rechazadas por defecto

window.toggleRechazadasEquipo = function() {
    ocultarRechazadasEquipo = !ocultarRechazadasEquipo;
    const icon  = document.getElementById('iconToggleRech');
    const label = document.getElementById('lblToggleRech');
    const btn   = document.getElementById('btnToggleRechazadas');
    if (ocultarRechazadasEquipo) {
        icon.className  = 'ph ph-eye-slash';
        label.textContent = 'Ocultar rechazadas';
        btn.classList.remove('bg-gray-100');
    } else {
        icon.className  = 'ph ph-eye';
        label.textContent = 'Ver rechazadas';
        btn.classList.add('bg-gray-100');
    }
    cargarEquipo();
}

window.toggleCanceladasSup = function() {
    ocultarCanceladasSup = !ocultarCanceladasSup;
    const icon  = document.getElementById('iconToggleSup');
    const label = document.getElementById('lblToggleSup');
    const btn   = document.getElementById('btnToggleSupCanceladas');
    if (ocultarCanceladasSup) {
        icon.className  = 'ph ph-eye-slash';
        label.textContent = 'Ocultar canceladas';
        btn.classList.remove('bg-gray-100');
    } else {
        icon.className  = 'ph ph-eye';
        label.textContent = 'Ver canceladas';
        btn.classList.add('bg-gray-100');
    }
    cargarMisSolicitudes();
}

// ── Colores de estado ─────────────────────────────────────────────────────────
const colorMap = {
    yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    blue:   'bg-blue-100 text-blue-800 border-blue-200',
    green:  'bg-green-100 text-green-800 border-green-200',
    red:    'bg-red-100 text-red-800 border-red-200',
    gray:   'bg-gray-100 text-gray-600 border-gray-200',
};

// ── KPIs ──────────────────────────────────────────────────────────────────────
window.cargarKPIs = async () => {
    try {
        const d = await (await fetch('/api/supervisor/kpis', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();
        document.getElementById('kpi-pendientes').textContent = d.pendientes;
        document.getElementById('kpi-enviadas').textContent   = d.enviadas_rh;
        document.getElementById('kpi-equipo').textContent     = d.total_equipo;

        const badge = document.getElementById('badge-pendientes');
        if (d.pendientes > 0) {
            badge.textContent = `${d.pendientes} pendientes`;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    } catch(e) { console.error(e); }
}

// ── Solicitudes del equipo ────────────────────────────────────────────────────
window.cargarEquipo = async () => {
    const tbody = document.getElementById('tablaEquipo');
    tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-primary text-lg mb-1 block"></i>Cargando...</td></tr>`;

    try {
        const rows = await (await fetch('/api/supervisor/equipo', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-gray-400 text-sm">
                <i class="ph ph-users text-2xl mb-2 block text-gray-300"></i>
                Tu equipo no tiene solicitudes activas.</td></tr>`;
            return;
        }

        // Filtrar rechazadas por supervisor si el toggle está activo
        const rowsFiltrados = ocultarRechazadasEquipo
            ? rows.filter(r => r.color !== 'red' && r.color !== 'gray')
            : rows;

        if (!rowsFiltrados.length && rows.length > 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                <i class="ph ph-funnel text-2xl mb-1 block"></i>
                Solo hay solicitudes rechazadas o canceladas.
                <button onclick="toggleRechazadasEquipo()" class="block mx-auto mt-1 text-primary text-xs underline">Mostrar todas</button>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = rowsFiltrados.map(r => {
            const color = colorMap[r.color] ?? colorMap.gray;
            const ini   = r.nombre_empleado.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
            return `
            <tr class="hover:bg-blue-50/20 transition group">
                <td class="px-5 py-3 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-full bg-primary/10 text-primary text-xs font-bold flex items-center justify-center flex-shrink-0">${ini}</div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">${r.nombre_empleado}</p>
                            <p class="text-xs text-gray-400">#${r.nomina}</p>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3 whitespace-nowrap">
                    <p class="text-sm font-medium text-gray-800">${fmtFecha(r.fecha_inicio)} — ${fmtFecha(r.fecha_fin)}</p>
                    <p class="text-xs text-gray-400">${r.dias_habiles ?? '—'} días hábiles</p>
                </td>
                <td class="px-5 py-3 whitespace-nowrap">
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">${r.tipo}</span>
                </td>
                <td class="px-5 py-3 text-center whitespace-nowrap">
                    <span class="px-2.5 py-1 inline-flex text-xs font-semibold rounded-full border ${color}">${r.estado_nombre}</span>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                    <div class="flex justify-end gap-1 items-center">
                        <button onclick="verHistorialEquipo(${r.id})"
                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition opacity-40 group-hover:opacity-100" title="Historial de cambios">
                            <i class="ph ph-clock-counter-clockwise text-sm"></i>
                        </button>
                        <button onclick="verGrupo()"
                                class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition opacity-40 group-hover:opacity-100"
                                title="Ver mi equipo completo">
                            <i class="ph ph-users text-sm"></i>
                        </button>
                        ${r.puede_actuar ? `
                        <button onclick="abrirEvaluar(${r.id}, '${escHtml(r.nombre_empleado)}', '${fmtFecha(r.fecha_inicio)}', '${fmtFecha(r.fecha_fin)}', '${escHtml(r.tipo)}', ${r.dias_habiles ?? 0})"
                                class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1 shadow-sm">
                            <i class="ph ph-magnifying-glass"></i> Evaluar
                        </button>` : `
                        <span class="text-xs text-gray-400 italic">${r.estado_nombre}</span>`}
                    </div>
                </td>
            </tr>`;
        }).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-red-400 text-sm">Error al cargar datos.</td></tr>`;
    }
}

// ── Mis solicitudes ───────────────────────────────────────────────────────────
window.cargarMisSolicitudes = async () => {
    const tbody = document.getElementById('tablaMisSolicitudes');
    tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-primary mr-1"></i>Cargando...</td></tr>`;

    try {
        const data = await (await fetch('/api/reservas', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();

        if (!data.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                <i class="ph ph-calendar-x text-2xl mb-1 block"></i>Sin solicitudes registradas.</td></tr>`;
            return;
        }

        // Filtrar canceladas
        const dataSupFiltrada = ocultarCanceladasSup
            ? data.filter(r => r.color !== 'gray')
            : data;

        if (!dataSupFiltrada.length && data.length > 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                <i class="ph ph-funnel text-2xl mb-1 block"></i>
                Todas tus solicitudes están canceladas.
                <button onclick="toggleCanceladasSup()" class="block mx-auto mt-1 text-primary text-xs underline">Mostrar canceladas</button>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = dataSupFiltrada.map(r => {
            const color = colorMap[r.color] ?? colorMap.gray;
            return `
            <tr class="hover:bg-gray-50/50 transition group">
                <td class="px-5 py-3">
                    <p class="text-sm font-semibold text-gray-800">${r.fecha_inicio} — ${r.fecha_fin}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Solicitado: ${r.fecha_creacion}</p>
                </td>
                <td class="px-5 py-3">
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">${r.tipo}</span>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="px-2.5 py-1 inline-flex text-xs font-semibold rounded-full border ${color}">${r.estado}</span>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="text-xs text-gray-600 font-medium">${r.regreso}</span>
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex justify-end gap-1 opacity-40 group-hover:opacity-100 transition">
                        <button onclick="verHistorialMio(${r.id})"
                                class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Historial de cambios">
                            <i class="ph ph-clock-counter-clockwise text-sm"></i>
                        </button>
                        ${r.color === 'yellow' || r.color === 'blue' ? `
                        <button onclick="abrirCancelar(${r.id})"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Cancelar">
                            <i class="ph ph-trash text-sm"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-red-400 text-sm">Error al cargar.</td></tr>`;
    }
}

// ── Abrir modal evaluar ───────────────────────────────────────────────────────
window.abrirEvaluar = function(id, nombre, inicio, fin, tipo, dias) {
    evalId      = id;
    decisionSel = null;
    document.getElementById('evalNombre').value    = nombre;
    document.getElementById('evalFechas').value    = `${inicio} — ${fin}`;
    document.getElementById('evalTipo').value      = tipo;
    document.getElementById('evalDias').value      = `${dias} días hábiles`;
    document.getElementById('evalObservacion').value = '';
    document.getElementById('evalDecision').value  = '';
    document.getElementById('evalMsg').classList.add('hidden');
    document.getElementById('motivoReq').classList.add('hidden');

    // Resetear botones
    document.querySelectorAll('.decision-btn').forEach(b => {
        b.classList.remove('border-green-400','bg-green-50','text-green-700','border-red-400','bg-red-50','text-red-700');
        b.classList.add('border-gray-200','text-gray-600');
    });

    openModal('evaluarModal');
}

window.seleccionarDecision = function(val) {
    decisionSel = val;
    document.getElementById('evalDecision').value = val;

    const vobo    = document.getElementById('btnVoBo');
    const rechazar= document.getElementById('btnRechazar');

    vobo.className = `decision-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 text-sm font-semibold transition ${
        val === 2 ? 'border-green-400 bg-green-50 text-green-700' : 'border-gray-200 text-gray-600 hover:border-green-400 hover:bg-green-50 hover:text-green-700'
    }`;
    rechazar.className = `decision-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 text-sm font-semibold transition ${
        val === 3 ? 'border-red-400 bg-red-50 text-red-700' : 'border-gray-200 text-gray-600 hover:border-red-400 hover:bg-red-50 hover:text-red-700'
    }`;

    document.getElementById('motivoReq').classList.toggle('hidden', val !== 3);
}

window.confirmarEvaluacion = async () => {
    const decision = decisionSel;
    const obs      = document.getElementById('evalObservacion').value.trim();
    const msg      = document.getElementById('evalMsg');

    if (!decision) {
        msg.textContent = 'Selecciona una decisión: Visto Bueno o Rechazar.';
        msg.className   = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
        msg.classList.remove('hidden');
        return;
    }

    if (decision === 3 && !obs) {
        msg.textContent = 'Escribe el motivo del rechazo para continuar.';
        msg.className   = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
        msg.classList.remove('hidden');
        return;
    }

    try {
        const res  = await fetch(`/api/supervisor/evaluar/${evalId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ decision, observaciones: obs }),
        });
        const data = await res.json();

        if (res.ok) {
            msg.textContent = data.message;
            msg.className   = 'p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200';
            msg.classList.remove('hidden');
            setTimeout(() => {
                closeModal('evaluarModal');
                cargarEquipo();
                cargarKPIs();
            }, 1200);
        } else {
            msg.textContent = data.error ?? 'Error al guardar.';
            msg.className   = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
            msg.classList.remove('hidden');
        }
    } catch(e) { console.error(e); }
}

// ── Preview fechas propias ────────────────────────────────────────────────────
window.actualizarPreview = async () => {
    const inicio = document.getElementById('fechaInicio').value;
    const fin    = document.getElementById('fechaFin').value;
    const card   = document.getElementById('previewCard');
    if (!inicio || !fin || fin < inicio) { card.classList.add('hidden'); return; }

    // Mostrar la tarjeta con loading
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
            const warn = document.getElementById('saldoWarning');
            document.getElementById('saldoWarningText').textContent =
                data.error ?? data.message ?? 'Error al calcular.';
            warn.classList.remove('hidden');
            ['prevInicio','prevFin','prevRegreso','prevDias'].forEach(id =>
                document.getElementById(id).textContent = '—');
            return;
        }

        document.getElementById('prevInicio').textContent  = fmtFecha(inicio);
        document.getElementById('prevFin').textContent     = fmtFecha(fin);
        document.getElementById('prevRegreso').textContent = data.regreso;
        document.getElementById('prevDias').textContent    = `${data.dias_habiles} día(s)`;

        const usaSaldo = document.getElementById('tipoSolicitud').selectedOptions[0]?.dataset.usaSaldo === '1';
        const warn     = document.getElementById('saldoWarning');
        if (usaSaldo && data.dias_habiles > window.saldoActual) {
            document.getElementById('saldoWarningText').textContent =
                `Saldo insuficiente: tienes ${window.saldoActual} día(s), solicitas ${data.dias_habiles}.`;
            warn.classList.remove('hidden');
        } else { warn.classList.add('hidden'); }
    } catch(e) {
        console.error('Preview error:', e);
        document.getElementById('prevDias').textContent = 'Error';
    }
}

// document.getElementById('fechaInicio').addEventListener('change', function() {
//     document.getElementById('fechaFin').min = this.value;
//     actualizarPreview();
// });
// document.getElementById('fechaFin').addEventListener('change', actualizarPreview);
// document.getElementById('tipoSolicitud').addEventListener('change', function() {
//     const usa = this.selectedOptions[0]?.dataset.usaSaldo === '1';
//     document.getElementById('saldoIndicador').classList.toggle('hidden', !usa);
//     actualizarPreview();
// });

// ── Inicializar — reemplaza el DOMContentLoaded al final de sup_user.js ───────
document.addEventListener('DOMContentLoaded', async () => {
    // 1. Cargar tipos desde BD ANTES de que el usuario pueda interactuar
    await cargarTiposSolicitud(['#tipoSolicitud']);

    // 2. Activar el indicador de saldo al cambiar el tipo
    document.getElementById('tipoSolicitud').addEventListener('change', function () {
        const usa = this.selectedOptions[0]?.dataset.usaSaldo === '1';
        document.getElementById('saldoIndicador').classList.toggle('hidden', !usa);
        actualizarPreview();
    });

    // 3. Cargar datos del panel
    cargarKPIs();
    cargarEquipo();
    cargarMisSolicitudes();

    // 4. Eventos de fecha
    document.getElementById('fechaInicio').addEventListener('change', function () {
        document.getElementById('fechaFin').min = this.value;
        actualizarPreview();
    });
    document.getElementById('fechaFin').addEventListener('change', actualizarPreview);
});

window.solicitarFechas = async () => {
    const inicio = document.getElementById('fechaInicio').value;
    const fin    = document.getElementById('fechaFin').value;
    const tipo   = document.getElementById('tipoSolicitud').value;
    const obs    = document.getElementById('observaciones').value;
    const btn    = document.getElementById('btnSolicitar');
    const msg    = document.getElementById('formMsg');

    if (!inicio || !fin) { showMsg(msg, 'Selecciona fechas de inicio y fin.', 'error'); return; }
    if (!tipo)           { showMsg(msg, 'Selecciona el tipo de permiso.', 'error'); return; }

    btn.disabled = true;
    btn.querySelector('span') ? btn.querySelector('span').textContent = 'Enviando...' : null;

    const res  = await fetch('/api/reservas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ fecha_inicial: inicio, fecha_final: fin, id_tipo: tipo, observaciones: obs }),
    });
    const data = await res.json();

    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');

    if (res.ok) {
        window.saldoActual = data.nuevo_saldo;
        document.getElementById('saldoDisplay').textContent = data.nuevo_saldo;
        document.getElementById('fechaInicio').value  = '';
        document.getElementById('fechaFin').value     = '';
        document.getElementById('tipoSolicitud').value = '';
        document.getElementById('observaciones').value = '';
        document.getElementById('previewCard').classList.add('hidden');
        cargarMisSolicitudes();
    }

    btn.disabled = false;
}

window.abrirCancelar = function(id) {
    reservaACancelar = id;
    openModal('deleteModal');
}

document.getElementById('btnConfirmarCancelar').addEventListener('click', async () => {
    const res  = await fetch(`/api/reservas/${reservaACancelar}`, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF },
    });
    const data = await res.json();
    if (res.ok) {
        window.saldoActual = data.nuevo_saldo;
        document.getElementById('saldoDisplay').textContent = data.nuevo_saldo;
        cargarMisSolicitudes();
    }
    closeModal('deleteModal');
});

// ── Helpers ───────────────────────────────────────────────────────────────────
window.fmtFecha = function(iso) {
    if (!iso) return '—';
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const [y, m, d] = iso.split('-');
    return `${parseInt(d)} ${meses[parseInt(m)-1]} ${y}`;
}
window.escHtml = function(s) { return String(s).replace(/'/g, "\\'"); }
window.showMsg = function(el, text, type) {
    el.textContent = text;
    el.className = `mb-3 p-3 rounded-lg text-sm ${type === 'success'
        ? 'bg-green-50 text-green-700 border border-green-200'
        : 'bg-red-50 text-red-700 border border-red-200'}`;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}



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

window.verGrupo = async () => {
    const contenido = document.getElementById('equipoModalContenido');
    contenido.innerHTML = `<div class="text-center text-gray-400 py-6">
        <i class="fas fa-spinner fa-spin text-indigo-400 text-xl mb-2 block"></i>Cargando...</div>`;
    openModal('equipoModal');

    try {
        const res     = await fetch('/api/supervisor/mi-grupo', { headers: { 'X-CSRF-TOKEN': CSRF } });
        const miembros = await res.json();

        if (!miembros.length) {
            contenido.innerHTML = `<p class="text-center text-gray-400 text-sm py-6">
                <i class="ph ph-users text-2xl mb-1 block"></i>No hay empleados asignados a tu grupo.</p>`;
            return;
        }

        contenido.innerHTML = `
            <p class="text-xs text-gray-400 mb-4">${miembros.length} empleado(s) en tu equipo</p>
            <ul class="space-y-2">
                ${miembros.map(m => {
                    const ini = m.nombre.split(' ').map(p => p[0]).slice(0,2).join('').toUpperCase();
                    return `
                    <li class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100 hover:bg-indigo-50 hover:border-indigo-100 transition">
                        <div class="h-9 w-9 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center flex-shrink-0">
                            ${ini}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">${m.nombre}</p>
                            <p class="text-xs text-gray-400">#${m.nomina} · ${m.centro_pago ?? '—'}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-bold text-indigo-600">${m.saldo} días</p>
                            <p class="text-xs text-gray-400">saldo</p>
                        </div>
                    </li>`;
                }).join('')}
            </ul>`;
    } catch(e) {
        contenido.innerHTML = `<p class="text-center text-red-400 text-sm py-6">Error al cargar el equipo.</p>`;
    }
}

window.verHistorial = function(id)       { _cargarHistorialModal(id); }
window.verHistorialMio = function(id)    { _cargarHistorialModal(id); }
window.verHistorialEquipo = function(id) { _cargarHistorialModal(id); }
