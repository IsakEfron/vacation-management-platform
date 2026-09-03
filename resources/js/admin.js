'use strict';

let paginaActual = 1;
let reservaEditando = null;
let reservaEliminando = null;

// ── Colores de badge ──────────────────────────────────────────────────────────

window.badgeClass = function(color) {
    const m = {
        yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        blue:   'bg-blue-100 text-blue-800 border-blue-200',
        green:  'bg-green-100 text-green-800 border-green-200',
        red:    'bg-red-100 text-red-800 border-red-200',
        gray:   'bg-gray-100 text-gray-700 border-gray-200',
    };
    return m[color] ?? m.gray;
}

window.avatarColor = function(color) {
    const m = {
        yellow: 'bg-yellow-100 text-yellow-700',
        blue:   'bg-blue-100 text-blue-700',
        green:  'bg-green-100 text-green-700',
        red:    'bg-red-100 text-red-600',
        gray:   'bg-gray-100 text-gray-600',
    };
    return m[color] ?? m.gray;
}

// ── KPIs ──────────────────────────────────────────────────────────────────────

let rangoActual = 'mes'; // Por defecto: mes actual

window.setRango = function(rango) {
    rangoActual = rango;
    // Resaltar botón activo
    document.querySelectorAll('.rango-btn').forEach(btn => {
        const isActive = btn.dataset.rango === rango;
        btn.className = `rango-btn px-3 py-1.5 text-xs font-semibold rounded-lg border transition ${
            isActive ? 'bg-primary text-white border-primary'
                     : 'border-gray-200 text-gray-600 hover:bg-gray-50'
        }`;
    });
    cargarKPIs();
}

window.cargarKPIs = async () => {
    const params = new URLSearchParams({ rango: rangoActual });
    if (rangoActual === 'personalizado') {
        const desde = document.getElementById('kpiDesde')?.value;
        const hasta = document.getElementById('kpiHasta')?.value;
        if (desde) params.set('desde', desde);
        if (hasta) params.set('hasta', hasta);
    }

    try {
        const r = await fetch(`/api/admin/kpis?${params}`, { headers: { 'X-CSRF-TOKEN': CSRF } });
        const d = await r.json();
        document.getElementById('kpi-pendientes').textContent  = d.pendientes;
        document.getElementById('kpi-visto').textContent       = d.visto_bueno;
        document.getElementById('kpi-aprobadas').textContent   = d.aprobadas;
        document.getElementById('kpi-rechazadas').textContent  = d.rechazadas;
        document.getElementById('kpi-empleados').textContent   = d.total_empleados;
        document.getElementById('badgePendientes').textContent = `${d.pendientes} pendientes`;
        // Actualizar etiqueta de periodo
        const lbl = document.getElementById('rangoLabel');
        if (lbl) lbl.textContent = d.rango_label ?? '';
    } catch(e) {
        console.error('KPI error:', e);
    }
}

// ── Cargar reservas ───────────────────────────────────────────────────────────

window.cargarReservas = async (pagina = 1) => {
    paginaActual = pagina;
    const estado  = document.getElementById('filtroEstado').value;
    const buscar  = document.getElementById('buscarInput').value.trim();

    const params = new URLSearchParams({ page: pagina });
    if (estado)  params.set('estado', estado);
    if (buscar)  params.set('buscar', buscar);

    const tbody = document.getElementById('tablaReservas');
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</td></tr>`;

    const r = await fetch(`/api/admin/reservas?${params}`, { headers: { 'X-CSRF-TOKEN': CSRF } });
    const d = await r.json();

    if (!d.data.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-gray-400 text-sm">
            <i class="ph ph-magnifying-glass text-2xl mb-2 block"></i>Sin resultados.</td></tr>`;
        document.getElementById('paginacionInfo').textContent = '';
        document.getElementById('paginacionBtns').innerHTML  = '';
        return;
    }

    tbody.innerHTML = d.data.map(r => `
        <tr class="hover:bg-blue-50/20 transition group" data-id="${r.id}">
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-full ${avatarColor(r.color)} flex items-center justify-center text-xs font-bold flex-shrink-0">
                        ${r.iniciales}
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-900">${r.nombre}</div>
                        <div class="text-xs text-gray-400">#${r.nomina}</div>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-800">${r.fecha_inicio} — ${r.fecha_fin}</div>
                <div class="text-xs text-gray-400">${r.dias_habiles} días hábiles</div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <span class="text-xs text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full">${r.tipo}</span>
            </td>
            <td class="px-4 py-3 max-w-[160px]">
                <span class="text-xs text-gray-500 truncate block">${r.observaciones ?? '—'}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-center">
                <span class="px-2.5 py-1 inline-flex text-xs font-semibold rounded-full border ${badgeClass(r.color)}">
                    ${r.estado}
                </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-right">
                <div class="flex justify-end gap-1 opacity-40 group-hover:opacity-100 transition">
                    <button onclick="verHistorial(${r.id})"
                            class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                            title="Historial">
                        <i class="ph ph-clock-counter-clockwise"></i>
                    </button>
                    <button onclick="abrirEditar(${r.id}, '${r.nombre}', '${r.nomina}', '${r.fecha_inicio} - ${r.fecha_fin}', ${r.estado_id}, \`${(r.observaciones ?? '').replace(/`/g,'')}\`)"
                            class="p-2 text-gray-500 hover:text-primary hover:bg-blue-50 rounded-lg transition"
                            title="Editar">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button onclick="abrirEliminar(${r.id}, '${r.nombre}', ${r.estado_id})"
                            class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                            title="Eliminar / Cancelar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    // Paginación
    document.getElementById('paginacionInfo').textContent =
        `Mostrando ${d.from}–${d.to} de ${d.total} resultados`;

    const btnContainer = document.getElementById('paginacionBtns');
    btnContainer.innerHTML = '';

    const btn = (label, pg, activo = false, disabled = false) => {
        const b = document.createElement('button');
        b.textContent = label;
        b.className = activo
            ? 'px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-bold'
            : 'px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-xs text-gray-600 transition disabled:opacity-40';
        b.disabled = disabled;
        if (!disabled && !activo) b.onclick = () => cargarReservas(pg);
        return b;
    };

    btnContainer.appendChild(btn('‹ Anterior', d.current_page - 1, false, d.current_page === 1));
    for (let i = 1; i <= d.last_page; i++) {
        if (i === 1 || i === d.last_page || Math.abs(i - d.current_page) <= 1) {
            btnContainer.appendChild(btn(i, i, i === d.current_page));
        } else if (Math.abs(i - d.current_page) === 2) {
            const s = document.createElement('span');
            s.textContent = '…'; s.className = 'px-2 py-1 text-xs text-gray-400';
            btnContainer.appendChild(s);
        }
    }
    btnContainer.appendChild(btn('Siguiente ›', d.current_page + 1, false, d.current_page === d.last_page));
}

window.buscar = function() { cargarReservas(1); }

window.limpiarFiltros = function() {
    document.getElementById('buscarInput').value = '';
    document.getElementById('filtroEstado').value = '';
    cargarReservas(1);
}

// ── Editar ────────────────────────────────────────────────────────────────────

window.abrirEditar = function(id, nombre, nomina, fechas, estadoId, obs) {
    reservaEditando = id;
    document.getElementById('editNombre').value  = nombre;
    document.getElementById('editNomina').value  = nomina;
    document.getElementById('editFechas').value  = fechas;
    document.getElementById('editEstado').value  = estadoId;
    document.getElementById('editObs').value     = obs;
    document.getElementById('editMsg').classList.add('hidden');
    openModal('editModal');
}

window.guardarEdicion = async function() {
    const estado = parseInt(document.getElementById('editEstado').value);
    const obs    = document.getElementById('editObs').value;
    const msg    = document.getElementById('editMsg');

    const r = await fetch(`/api/admin/reservas/${reservaEditando}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ estado, observaciones: obs }),
    });
    const d = await r.json();

    msg.textContent = d.message ?? d.error;
    msg.className   = `p-3 rounded-lg text-sm ${r.ok ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`;
    msg.classList.remove('hidden');

    if (r.ok) {
        setTimeout(() => { closeModal('editModal'); cargarReservas(paginaActual); cargarKPIs(); }, 1000);
    }
}

// ── Historial ─────────────────────────────────────────────────────────────────

window.verHistorial = async (id) => {
    document.getElementById('historialContenido').innerHTML =
        '<p class="text-center text-gray-400 text-sm py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</p>';
    openModal('historyModal');

    const r = await fetch(`/api/admin/reservas/${id}/historial`, { headers: { 'X-CSRF-TOKEN': CSRF } });
    const items = await r.json();

    if (!items.length) {
        document.getElementById('historialContenido').innerHTML =
            '<p class="text-center text-gray-400 text-sm">Sin historial.</p>';
        return;
    }

    document.getElementById('historialContenido').innerHTML = `
        <ol class="relative border-l border-gray-200 ml-3 space-y-5">
            ${items.map(h => `
            <li class="ml-8">
                <span class="absolute flex items-center justify-center w-7 h-7 bg-blue-50 rounded-full -left-3.5 ring-4 ring-white border border-blue-100">
                    <i class="ph ph-pencil-simple text-primary text-xs"></i>
                </span>
                <p class="text-sm font-semibold text-gray-900">${h.estado_nuevo}</p>
                <time class="text-xs text-gray-400 block mb-1">${h.fecha} · Por: <span class="text-gray-600 font-medium">${h.modificado_por}</span></time>
                ${h.estado_anterior !== 'Creación' ? `
                <div class="text-xs text-gray-600 bg-gray-50 border border-gray-100 p-2 rounded-lg inline-flex items-center gap-2">
                    <span class="line-through text-red-400">${h.estado_anterior}</span>
                    <i class="ph ph-arrow-right text-gray-400"></i>
                    <span class="text-green-600 font-bold">${h.estado_nuevo}</span>
                </div>` : `<span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full border border-yellow-200">Solicitud creada</span>`}
                ${h.detalles ? `<p class="text-xs text-gray-400 mt-1">${h.detalles}</p>` : ''}
            </li>`).join('')}
        </ol>`;
}

// ── Eliminar ──────────────────────────────────────────────────────────────────

window.abrirEliminar = function(id, nombre, estadoId) {
    reservaEliminando = id;
    const label = document.getElementById('deleteNombreLabel');
    if (label) label.textContent = nombre ?? '';
    openModal('deleteModal');
}

// Cancelar (soft — estado 6, devuelve saldo)
const btnCancelar = document.getElementById('btnCancelarSolicitud');
if (btnCancelar) btnCancelar.addEventListener('click', async () => {
    const r = await fetch(`/api/admin/reservas/${reservaEliminando}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    closeModal('deleteModal');
    cargarReservas(paginaActual);
    cargarKPIs();
});

// Eliminar definitivo (hard — solo SuperAdmin)
const btnHard = document.getElementById('btnEliminarDefinitivo');
if (btnHard) btnHard.addEventListener('click', async () => {
    if (!confirm('¿Eliminar permanentemente? Esta acción no puede deshacerse y NO devuelve el saldo.')) return;
    const r = await fetch(`/api/admin/reservas/${reservaEliminando}/hard`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF },
    });
    const d = await r.json();
    closeModal('deleteModal');
    if (r.ok) {
        cargarReservas(paginaActual);
        cargarKPIs();
    } else {
        alert(d.error ?? 'Error al eliminar.');
    }
});

// ── Exportar CSV ──────────────────────────────────────────────────────────────

window.abrirExportar = function() {
    // Limpiar estado anterior
    document.getElementById('exportMsg').classList.add('hidden');
    openModal('exportModal');
    cargarQuincenasExport();
}

/**
 * Carga las quincenas del año seleccionado desde la BD.
 * Si hay quincenas -> muestra el <select> con ellas.
 * Si no hay       -> muestra el input manual con advertencia.
 * Detecta la quincena actual automáticamente por fecha de hoy.
 */
window.cargarQuincenasExport = async () => {
    const anio    = document.getElementById('exportAnio').value;
    const loading = document.getElementById('exportQLoading');
    const select  = document.getElementById('exportQSelect');
    const manual  = document.getElementById('exportQManualWrap');
    const badge   = document.getElementById('exportQBadge');

    // Mostrar loading, ocultar el resto
    loading.classList.remove('hidden');
    select.classList.add('hidden');
    manual.classList.add('hidden');
    badge.textContent = '';

    try {
        const res  = await fetch(`/api/quincenas?anio=${anio}&activo=1`, {
            headers: { 'X-CSRF-TOKEN': CSRF }
        });
        const rows = await res.json();

        loading.classList.add('hidden');

        if (!res.ok || !rows.length) {
            // Sin quincenas en BD -> modo manual
            manual.classList.remove('hidden');
            badge.textContent = '(sin registros en BD)';
            return;
        }

        // Calcular qué quincena es hoy para preseleccionarla
        const hoy = new Date().toISOString().slice(0, 10);
        let   idxActual = 0;

        select.innerHTML = rows.map((q, i) => {
            const esActual = hoy >= q.fecha_inicio && hoy <= q.fecha_fin;
            if (esActual) idxActual = i;

            const fi = fmtFechaCorta(q.fecha_inicio);
            const ff = fmtFechaCorta(q.fecha_fin);
            return `<option value="${q.numero}" data-id="${q.id}"
                        data-inicio="${q.fecha_inicio}" data-fin="${q.fecha_fin}">
                Q${q.numero} — ${q.descripcion} (${fi}–${ff})
            </option>`;
        }).join('');

        select.selectedIndex = idxActual;
        select.classList.remove('hidden');

        const qActual = rows[idxActual];
        badge.textContent = qActual
            ? `(${fmtFechaCorta(qActual.fecha_inicio)} – ${fmtFechaCorta(qActual.fecha_fin)})`
            : '';

        // Actualizar badge al cambiar selección
        select.onchange = () => {
            const opt = select.selectedOptions[0];
            badge.textContent = opt
                ? `(${fmtFechaCorta(opt.dataset.inicio)} – ${fmtFechaCorta(opt.dataset.fin)})`
                : '';
        };

    } catch (e) {
        loading.classList.add('hidden');
        manual.classList.remove('hidden');
        badge.textContent = '(error al cargar)';
    }
}

/**
 * Obtiene el número de quincena seleccionado:
 * - Desde el <select> si hay quincenas en BD
 * - Desde el input manual si no hay
 */
window.getQuincenaSeleccionada = function() {
    const select = document.getElementById('exportQSelect');
    const manual = document.getElementById('exportQManualWrap');

    if (!select.classList.contains('hidden')) {
        return parseInt(select.value) || null;
    }
    if (!manual.classList.contains('hidden')) {
        return parseInt(document.getElementById('exportQuincena').value) || null;
    }
    return null;
}

window.confirmarExportar = function() {
    const estado   = document.getElementById('filtroEstado').value;
    const buscar   = document.getElementById('buscarInput').value.trim();
    const anio     = document.getElementById('exportAnio').value;
    const quincena = getQuincenaSeleccionada();
    const msg      = document.getElementById('exportMsg');

    if (!quincena || quincena < 1 || quincena > 24) {
        msg.textContent = 'Selecciona o ingresa un número de quincena válido (1–24).';
        msg.className   = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
        msg.classList.remove('hidden');
        return;
    }

    msg.classList.add('hidden');

    const params = new URLSearchParams();
    if (estado) params.set('estado', estado);
    if (buscar) params.set('buscar', buscar);
    params.set('quincena', quincena);
    params.set('anio', anio);

    closeModal('exportModal');
    window.location.href = `/api/admin/exportar?${params}`;
}

// ── Helper interno ─────────────────────────────────────────────────────────────
window.fmtFechaCorta = function(iso) {
    if (!iso) return '—';
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const [, m, d] = iso.split('-');
    return `${parseInt(d)} ${meses[parseInt(m) - 1]}`;
}

// ── Inicializar ───────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    cargarKPIs();
    cargarReservas();

    document.getElementById('buscarInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') buscar();
    });

    document.getElementById('filtroEstado').addEventListener('change', buscar);
});