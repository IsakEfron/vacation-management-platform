'use strict';

let enMantenimiento = false;

// ── Estado del sistema ────────────────────────────────────────────────────────
window.cargarEstado = async () => {
    try {
        const d = await (await fetch('/api/mantenimiento/estado', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();
        enMantenimiento = d.en_mantenimiento;

        // DB status
        const dbEl = document.getElementById('dbStatus');
        dbEl.textContent  = d.db_ok ? ' Conectada' : ' Error';
        dbEl.className    = `text-xs px-2 py-1 rounded font-bold ${d.db_ok ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`;

        // Último mant
        document.getElementById('lastMaint').textContent = d.ultimo_mant ?? 'Nunca';

        // Modo actual
        const modoEl = document.getElementById('modoActual');
        modoEl.textContent = enMantenimiento ? 'MANTENIMIENTO' : 'Normal';
        modoEl.className   = `text-xs px-2 py-1 rounded font-bold ${enMantenimiento ? 'bg-red-100 text-red-700 animate-pulse' : 'bg-green-100 text-green-700'}`;

        // Banner
        document.getElementById('bannerMant').classList.toggle('hidden', !enMantenimiento);

        // Habilitar/deshabilitar acciones críticas
        toggleAccionesCriticas(enMantenimiento);
    } catch(e) { console.error(e); }
}

window.toggleAccionesCriticas = function(activo) {
    // Excel
    const excelFile  = document.getElementById('excelFile');
    const excelLabel = document.getElementById('excelLabel');
    const btnImp     = document.getElementById('btnImportar');
    excelFile.disabled  = !activo;
    btnImp.disabled     = !activo;
    excelLabel.classList.toggle('opacity-50', !activo);
    excelLabel.classList.toggle('cursor-not-allowed', !activo);
    excelLabel.classList.toggle('cursor-pointer', activo);

    // SQL
    const sqlFile  = document.getElementById('sqlFile');
    const sqlLabel = document.getElementById('sqlLabel');
    const btnRest  = document.getElementById('btnRestaurar');
    sqlFile.disabled  = !activo;
    btnRest.disabled  = !activo;
    sqlLabel.classList.toggle('opacity-50', !activo);
    sqlLabel.classList.toggle('cursor-not-allowed', !activo);
    sqlLabel.classList.toggle('cursor-pointer', activo);

    // Reiniciar
    document.getElementById('btnReiniciar').disabled = !activo;
}

// ── Bitácora ──────────────────────────────────────────────────────────────────
const estadoMap = {
    1: { label: 'Programado', cls: 'bg-blue-100 text-blue-800 border-blue-200' },
    2: { label: 'Activo',     cls: 'bg-red-100 text-red-800 border-red-200 animate-pulse' },
    3: { label: 'Completado', cls: 'bg-green-100 text-green-800 border-green-200' },
    4: { label: 'Cancelado',  cls: 'bg-gray-100 text-gray-600 border-gray-200' },
    5: { label: 'Vencido',    cls: 'bg-orange-100 text-orange-700 border-orange-200' },
};

window.cargarBitacora = async () => {
    const tbody = document.getElementById('tablaBitacora');
    tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-primary mr-2"></i>Cargando...</td></tr>`;

    try {
        const rows = await (await fetch('/api/mantenimiento', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">Sin registros aún.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(m => {
            const st  = estadoMap[m.estado] ?? estadoMap[4];
            const ini = fmtDatetime(m.fecha_inicio);
            const fin = fmtDatetime(m.fecha_fin);

            const acciones = [];

            if (m.estado === 1) {
                // Programado -> puede activar o cancelar
                acciones.push(`<button onclick="accionMant(${m.id},'activar')" title="Activar" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition"><i class="fas fa-play"></i></button>`);
                acciones.push(`<button onclick="accionMant(${m.id},'cancelar')" title="Cancelar" class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition"><i class="fas fa-ban"></i></button>`);
            }
            if (m.estado === 2) {
                // Activo -> puede detener o cancelar
                acciones.push(`<button onclick="accionMant(${m.id},'detener')" title="Detener" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition"><i class="fas fa-stop"></i></button>`);
                acciones.push(`<button onclick="accionMant(${m.id},'cancelar')" title="Cancelar" class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition"><i class="fas fa-ban"></i></button>`);
            }
            if (m.estado === 3 || m.estado === 4 || m.estado === 5) {
                // Finalizado / Cancelado / Vencido -> solo eliminar
                acciones.push(`<button onclick="accionMant(${m.id},'eliminar')" title="Eliminar registro" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition"><i class="fas fa-trash"></i></button>`);
            }

            return `
            <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-medium text-gray-800">${m.categoria}</td>
                <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">${ini}</td>
                <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">${fin}</td>
                <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate">${m.notas ?? '—'}</td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full border ${st.cls}">${st.label}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex justify-center gap-1">${acciones.join('')}</div>
                </td>
            </tr>`;
        }).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-400 text-sm">Error al cargar.</td></tr>`;
    }
}

// ── Acciones de bitácora ──────────────────────────────────────────────────────
window.accionMant = async (id, accion) => {
    const confirmTexts = {
        activar:  '¿Activar este mantenimiento? El sistema quedará en modo mantenimiento y los usuarios serán desconectados.',
        detener:  '¿Marcar como completado? El sistema volverá a estar en línea.',
        cancelar: '¿Cancelar este mantenimiento?',
        eliminar: '¿Eliminar este registro permanentemente?',
    };
    if (!confirm(confirmTexts[accion])) return;

    let url, method;
    if (accion === 'eliminar') {
        url = `/api/mantenimiento/${id}`;
        method = 'DELETE';
    } else {
        url = `/api/mantenimiento/${id}/${accion}`;
        method = 'PUT';
    }

    try {
        const res  = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': CSRF } });
        const data = await res.json();
        if (res.ok) {
            mostrarToast(data.message, 'success');
            cargarBitacora();
            cargarEstado();
        } else {
            mostrarToast(data.error ?? 'Error', 'error');
        }
    } catch(e) { console.error(e); }
}

// ── Programar mantenimiento ───────────────────────────────────────────────────
window.programarMantenimiento = async () => {
    const categoria = document.getElementById('newCategoria').value;
    const inicio    = document.getElementById('newInicio').value;
    const fin       = document.getElementById('newFin').value;
    const notas     = document.getElementById('newNotas').value;
    const msg       = document.getElementById('formMantMsg');

    if (!categoria || !inicio || !fin) {
        showMsg(msg, 'Completa categoría, fecha inicio y fecha fin.', 'error'); return;
    }

    const res  = await fetch('/api/mantenimiento', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ categoria, fecha_inicio: inicio, fecha_fin: fin, notas }),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) {
        document.getElementById('newCategoria').value = '';
        document.getElementById('newInicio').value    = '';
        document.getElementById('newFin').value       = '';
        document.getElementById('newNotas').value     = '';
        cargarBitacora();
    }
}

// ── Importar Excel ────────────────────────────────────────────────────────────
window.importarExcel = async () => {
    const file = document.getElementById('excelFile').files[0];
    const modo = document.querySelector('input[name="modoImport"]:checked').value;
    const msg  = document.getElementById('excelMsg');

    if (!file) { showMsg(msg, 'Selecciona un archivo Excel primero.', 'error'); return; }

    const fd = new FormData();
    fd.append('archivo', file);
    fd.append('modo', modo);

    showMsg(msg, '<i class="fas fa-spinner fa-spin mr-1"></i>Procesando archivo...', 'info');

    try {
        const res  = await fetch('/api/mantenimiento/importar-excel', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: fd,
        });
        const data = await res.json();

        if (res.ok) {
            msg.className = 'mb-3 p-3 rounded-lg text-xs bg-green-50 text-green-800 border border-green-200';
            msg.innerHTML = `
                <p class="font-bold mb-1">✓ ${data.message}</p>
                <div class="grid grid-cols-4 gap-2 text-center mt-2">
                    <div class="bg-white rounded-lg p-1.5 border border-green-200">
                        <p class="text-lg font-black text-green-700">${data.insertados}</p>
                        <p class="text-gray-500 text-xs">Nuevos</p>
                    </div>
                    <div class="bg-white rounded-lg p-1.5 border border-blue-200">
                        <p class="text-lg font-black text-blue-700">${data.actualizados}</p>
                        <p class="text-gray-500 text-xs">Actualizados</p>
                    </div>
                    <div class="bg-white rounded-lg p-1.5 border border-orange-200">
                        <p class="text-lg font-black text-orange-600">${data.desactivados ?? 0}</p>
                        <p class="text-gray-500 text-xs">Desactivados</p>
                    </div>
                    <div class="bg-white rounded-lg p-1.5 border border-gray-200">
                        <p class="text-lg font-black text-gray-600">${data.omitidos}</p>
                        <p class="text-gray-500 text-xs">Omitidos</p>
                    </div>
                </div>
                ${data.errores?.length ? `<p class="mt-2 text-red-700 font-semibold">${data.errores.length} errores. Revisa el log.</p>` : ''}`;
        } else {
            showMsg(msg, data.error ?? 'Error al importar.', 'error');
        }
        msg.classList.remove('hidden');
    } catch(e) { showMsg(msg, 'Error inesperado.', 'error'); }
}

// ── Backup ────────────────────────────────────────────────────────────────────
window.descargarBackup = function() {
    window.location.href = '/api/mantenimiento/backup';
}

// ── Restaurar backup ──────────────────────────────────────────────────────────
window.restaurarBackup = async () => {
    const file = document.getElementById('sqlFile').files[0];
    const msg  = document.getElementById('sqlMsg');

    if (!file) { showMsg(msg, 'Selecciona un archivo .sql primero.', 'error'); return; }
    if (!confirm('¿Restaurar la base de datos con este archivo? Esto sobrescribirá los datos actuales.')) return;

    const fd = new FormData();
    fd.append('archivo', file);

    showMsg(msg, '<i class="fas fa-spinner fa-spin mr-1"></i>Restaurando...', 'info');

    try {
        const res  = await fetch('/api/mantenimiento/restaurar', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: fd,
        });
        const data = await res.json();
        if (res.ok) {
            msg.className = 'mb-3 p-3 rounded-lg text-xs bg-green-50 text-green-800 border border-green-200';
            msg.innerHTML = `✓ ${data.message}<br>Sentencias ejecutadas: ${data.ejecutados}${data.errores?.length ? `<br><span class="text-red-600">${data.errores.length} con errores</span>` : ''}`;
        } else {
            showMsg(msg, data.error ?? 'Error al restaurar.', 'error');
        }
        msg.classList.remove('hidden');
    } catch(e) { showMsg(msg, 'Error inesperado.', 'error'); }
}

// ── Reiniciar sistema ─────────────────────────────────────────────────────────
window.confirmarReinicio = async () => {
    const pwd = document.getElementById('pwdReiniciar').value;
    const msg = document.getElementById('reiniciarMsg');

    if (!pwd) { showMsg(msg, 'Ingresa tu contraseña.', 'error'); return; }

    const res  = await fetch('/api/mantenimiento/reiniciar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ password_maestra: pwd }),
    });
    const data = await res.json();

    if (res.ok) {
        msg.textContent = data.message + ' Redirigiendo...';
        msg.className   = 'mb-3 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200';
        msg.classList.remove('hidden');
        setTimeout(() => window.location.href = '/', 2500);
    } else {
        showMsg(msg, data.error ?? 'Error', 'error');
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
window.fmtDatetime = function(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

window.showMsg = function(el, html, type) {
    const clsMap = {
        success: 'bg-green-50 text-green-800 border border-green-200',
        error:   'bg-red-50 text-red-800 border border-red-200',
        info:    'bg-blue-50 text-blue-800 border border-blue-200',
    };
    el.innerHTML  = html;
    el.className  = `mb-3 p-3 rounded-lg text-xs ${clsMap[type] ?? clsMap.info}`;
    el.classList.remove('hidden');
}

window.mostrarToast = function(msg, type) {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-5 right-5 z-50 px-5 py-3 rounded-xl shadow-xl text-sm font-semibold transition ${
        type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
    }`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

// ── Auditoría ─────────────────────────────────────────────────────────────────
let audPagina = 1;

window.cargarAcciones = async () => {
    try {
        const acc = await (await fetch('/api/auditoria/acciones', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();
        const sel = document.getElementById('audAccion');
        if (!sel) return;
        acc.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a; opt.textContent = a;
            sel.appendChild(opt);
        });
    } catch(e) {}
}

window.cargarAuditoria = async (pagina = 1) => {
    audPagina = pagina;
    const accion  = document.getElementById('audAccion')?.value ?? '';
    const buscar  = document.getElementById('audBuscar')?.value ?? '';
    const desde   = document.getElementById('audDesde')?.value ?? '';
    const hasta   = document.getElementById('audHasta')?.value ?? '';
    const tabla   = document.getElementById('audTabla');

    if (!tabla) return;
    tabla.innerHTML = '<div class="py-4 text-center text-gray-400 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i>Cargando...</div>';

    const params = new URLSearchParams({ page: pagina });
    if (accion) params.set('accion', accion);
    if (buscar) params.set('buscar', buscar);
    if (desde)  params.set('fecha_desde', desde);
    if (hasta)  params.set('fecha_hasta', hasta);

    try {
        const res  = await fetch(`/api/auditoria?${params}`, { headers: { 'X-CSRF-TOKEN': CSRF } });
        const data = await res.json();

        if (!data.data.length) {
            tabla.innerHTML = '<div class="py-4 text-center text-gray-400 text-xs">Sin registros.</div>';
            document.getElementById('audInfo').textContent = '';
            return;
        }

        const accionColores = {
            LOGIN: 'text-green-600', LOGOUT: 'text-gray-500', LOGIN_FALLIDO: 'text-red-600',
            RESERVA: 'text-blue-600', MANT: 'text-orange-600', BACKUP: 'text-indigo-600',
            SISTEMA: 'text-red-700', IMPORTAR: 'text-teal-600', CAMBIO: 'text-amber-600',
        };
        const getColor = a => {
            for (const [k, c] of Object.entries(accionColores)) {
                if (a.startsWith(k)) return c;
            }
            return 'text-gray-600';
        };

        tabla.innerHTML = `
        <table class="w-full text-[10px]">
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th class="px-2 py-1.5 text-left text-gray-500 font-semibold">Quién</th>
                    <th class="px-2 py-1.5 text-left text-gray-500 font-semibold">Acción</th>
                    <th class="px-2 py-1.5 text-left text-gray-500 font-semibold">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                ${data.data.map(a => `
                <tr class="hover:bg-gray-50">
                    <td class="px-2 py-1.5">
                        <p class="font-semibold text-gray-800">${a.nombre}</p>
                        <p class="text-gray-400">#${a.nomina}</p>
                    </td>
                    <td class="px-2 py-1.5">
                        <span class="font-bold ${getColor(a.accion)}">${a.accion}</span>
                        ${a.detalles ? `<p class="text-gray-400 truncate max-w-[120px]">${a.detalles}</p>` : ''}
                    </td>
                    <td class="px-2 py-1.5 whitespace-nowrap text-gray-500">${a.fecha}</td>
                </tr>`).join('')}
            </tbody>
        </table>`;

        document.getElementById('audInfo').textContent =
            `${data.meta.from}–${data.meta.to} de ${data.meta.total} registros`;
    } catch(e) {
        tabla.innerHTML = '<div class="py-3 text-center text-red-400 text-xs">Error al cargar.</div>';
    }
}

window.exportarAuditoria = function() {
    const accion = document.getElementById('audAccion')?.value ?? '';
    const buscar = document.getElementById('audBuscar')?.value ?? '';
    const desde  = document.getElementById('audDesde')?.value ?? '';
    const hasta  = document.getElementById('audHasta')?.value ?? '';
    const params = new URLSearchParams();
    if (accion) params.set('accion', accion);
    if (buscar) params.set('buscar', buscar);
    if (desde)  params.set('fecha_desde', desde);
    if (hasta)  params.set('fecha_hasta', hasta);
    window.location.href = `/api/auditoria/exportar?${params}`;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarEstado();
    cargarBitacora();
    cargarAcciones();
    cargarAuditoria(1);
    // Refrescar estado cada 30 segundos
    setInterval(cargarEstado, 30000);
});