'use strict';

const MI_NOMINA = window.MI_NOMINA ?? '';
let nominaEditando = null;
let rolFiltro      = '';
let activoFiltro   = '1';
let orderBy        = 'nombre';   // columna de ordenamiento actual
let orderDir       = 'asc';      // dirección actual

const rolColors = {
    1: 'bg-gray-100 text-gray-700 border-gray-200',
    2: 'bg-blue-100 text-blue-800 border-blue-200',
    3: 'bg-purple-100 text-purple-800 border-purple-200',
    4: 'bg-amber-100 text-amber-800 border-amber-200',
};

window.puedeDarDeBaja = function(nomina, rol) {
    if (nomina === MI_NOMINA) return false;
    if (window.MI_ROL === 3 && rol >= 3) return false;
    return true;
}
window.puedeEditar = function(nomina, rol) {
    if (nomina === MI_NOMINA) return false;
    if (window.MI_ROL === 3 && rol >= 3) return false;
    return true;
}

// ── Ordenamiento ──────────────────────────────────────────────────────────────
window.ordenar = function(col) {
    if (orderBy === col) {
        orderDir = orderDir === 'asc' ? 'desc' : 'asc';
    } else {
        orderBy  = col;
        orderDir = 'asc';
    }
    actualizarIconosOrden();
    cargarPersonal(1);
}

window.actualizarIconosOrden = function() {
    // Resetear todos
    document.querySelectorAll('[data-sort]').forEach(th => {
        const icon = th.querySelector('i');
        if (!icon) return;
        icon.className = 'fas fa-sort text-gray-300 ml-1 text-[10px]';
    });
    // Marcar el activo
    const thActivo = document.querySelector(`[data-sort="${orderBy}"]`);
    if (thActivo) {
        const icon = thActivo.querySelector('i');
        if (icon) icon.className = `fas fa-sort-${orderDir === 'asc' ? 'up' : 'down'} text-primary ml-1 text-[10px]`;
    }
}

// ── Filtros ───────────────────────────────────────────────────────────────────
window.filtrarActivo = function(val) {
    activoFiltro = val;
    document.querySelectorAll('.filtro-activo').forEach(btn => {
        const activo = btn.dataset.activo === val;
        btn.className = `filtro-activo px-3 py-1.5 text-xs font-semibold rounded-lg border transition ${
            activo ? 'bg-primary text-white border-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-100'
        }`;
    });
    cargarPersonal(1);
}

window.filtrarRol = function(rol) {
    rolFiltro = rol;
    document.querySelectorAll('.filtro-rol').forEach(btn => {
        const activo = String(btn.dataset.rol) === String(rol);
        btn.className = `filtro-rol px-3 py-1.5 text-xs font-semibold rounded-lg border transition ${
            activo ? 'bg-primary text-white border-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-100'
        }`;
    });
    cargarPersonal(1);
}

// ── Paginación inteligente (máx 7 botones visibles + elipsis) ─────────────────
window.renderPaginacion = function(currentPage, lastPage, onClick) {
    const container = document.getElementById('personalPagina');
    container.innerHTML = '';
    if (lastPage <= 1) return;

    const btn = (label, page, activo = false, disabled = false, esElipsis = false) => {
        const b = document.createElement('button');
        b.innerHTML = label;
        if (esElipsis) {
            b.className = 'px-2 py-1.5 text-xs text-gray-400 cursor-default select-none';
            b.disabled  = true;
        } else {
            b.className = activo
                ? 'px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-bold min-w-[32px]'
                : 'px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 text-xs text-gray-600 disabled:opacity-40 disabled:cursor-not-allowed min-w-[32px] transition';
            b.disabled = disabled;
            if (!disabled && !activo) b.onclick = () => onClick(page);
        }
        return b;
    };

    // Anterior
    container.appendChild(btn('‹', currentPage - 1, false, currentPage === 1));

    // Lógica de ventana deslizante
    const WINDOW = 3; // páginas a mostrar a cada lado del actual
    let pages = new Set();
    pages.add(1);
    pages.add(lastPage);
    for (let i = Math.max(2, currentPage - WINDOW); i <= Math.min(lastPage - 1, currentPage + WINDOW); i++) {
        pages.add(i);
    }
    pages = [...pages].sort((a, b) => a - b);

    let prev = 0;
    for (const p of pages) {
        if (p - prev > 1) {
            container.appendChild(btn('…', null, false, false, true));
        }
        container.appendChild(btn(p, p, p === currentPage));
        prev = p;
    }

    // Siguiente
    container.appendChild(btn('›', currentPage + 1, false, currentPage === lastPage));
}

// ── Cargar tabla ──────────────────────────────────────────────────────────────
window.cargarPersonal = async (pagina = 1) => {
    const buscar = document.getElementById('buscarPersonal').value;
    const tbody  = document.getElementById('tablaPersonal');
    tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-primary text-lg mb-1 block"></i>Cargando...</td></tr>`;

    try {
        const params = new URLSearchParams({ page: pagina, activo: activoFiltro, order: orderBy, dir: orderDir });
        if (buscar)          params.append('buscar', buscar);
        if (rolFiltro !== '') params.append('rol', rolFiltro);

        const res  = await fetch(`/api/personal?${params}`, { headers: { 'X-CSRF-TOKEN': CSRF } });
        const json = await res.json();

        const { total, current_page, last_page, per_page } = json.meta;
        const desde = ((current_page - 1) * per_page) + 1;
        const hasta = Math.min(current_page * per_page, total);

        document.getElementById('totalBadge').textContent = `${total} empleados`;
        document.getElementById('personalInfo').textContent =
            total > 0 ? `Mostrando ${desde}–${hasta} de ${total}` : 'Sin resultados';

        if (!json.data.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-gray-400 text-sm">
                <i class="ph ph-magnifying-glass text-2xl mb-2 block"></i>Sin resultados.</td></tr>`;
            document.getElementById('personalPagina').innerHTML = '';
            return;
        }

        tbody.innerHTML = json.data.map(e => {
            const iniciales = e.nombre.split(' ').map(p=>p[0]).slice(0,2).join('').toUpperCase();
            const esActivo  = e.activo;

            return `
            <tr class="hover:bg-blue-50/20 transition group ${!esActivo ? 'opacity-60' : ''}">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-full ${esActivo ? 'bg-primary/10 text-primary' : 'bg-gray-200 text-gray-400'} text-xs font-bold flex items-center justify-center flex-shrink-0">
                            ${iniciales}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 ${!esActivo ? 'line-through decoration-gray-400' : ''}">${e.nombre}</p>
                            <p class="text-xs text-gray-400 font-mono">#${e.nomina}</p>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="text-sm font-bold text-gray-800">${e.saldo}</span>
                    <span class="text-xs text-gray-400 ml-1">días</span>
                </td>
                <td class="px-5 py-3">
                    <span class="text-xs text-gray-500">${e.centro_pago ?? '—'}</span>
                </td>
                <td class="px-5 py-3">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full border ${rolColors[e.rol] ?? rolColors[1]}">
                        ${e.rol_nombre}
                    </span>
                </td>
                <td class="px-5 py-3 text-center">
                    ${esActivo
                        ? '<span class="text-xs bg-green-100 text-green-700 border border-green-200 px-2 py-0.5 rounded-full font-semibold">Activo</span>'
                        : '<span class="text-xs bg-red-100 text-red-700 border border-red-200 px-2 py-0.5 rounded-full font-semibold">Inactivo</span>'
                    }
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex justify-end gap-1 opacity-40 group-hover:opacity-100 transition">
                        ${esActivo && puedeEditar(e.nomina, e.rol) ? `
                        <button onclick="abrirEditRol('${e.nomina}', '${e.nombre.replace(/'/g,"\\'")}', ${e.rol})"
                                class="p-2 text-gray-500 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Cambiar rol">
                            <i class="ph ph-user-gear"></i>
                        </button>` : ''}
                        ${esActivo ? `
                        <button onclick="abrirResetPwd('${e.nomina}', '${e.nombre.replace(/'/g,"\\'")}')"
                                class="p-2 text-gray-500 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition" title="Restablecer contraseña">
                            <i class="ph ph-key"></i>
                        </button>` : ''}
                        ${esActivo && puedeDarDeBaja(e.nomina, e.rol) ? `
                        <button onclick="abrirBaja('${e.nomina}', '${e.nombre.replace(/'/g,"\\'")}')"
                                class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Dar de baja">
                            <i class="ph ph-user-minus"></i>
                        </button>` : ''}
                        ${!esActivo ? `
                            <div class="flex justify-end gap-1 opacity-40 group-hover:opacity-100 transition">
                                ${window.MI_ROL === 4 ? `
                                <button onclick="reactivar('${e.nomina}', '${e.nombre.replace(/'/g, "\\'")}')"
                                        class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition"
                                        title="Reactivar empleado">
                                    <i class="ph ph-user-check"></i>
                                </button>
                                <button onclick="abrirEliminarEmpleado('${e.nomina}', '${e.nombre.replace(/'/g, "\\'")}')"
                                        class="p-2 text-gray-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition"
                                        title="Eliminar permanentemente de la BD">
                                    <i class="ph ph-trash"></i>
                                </button>
                                ` : `
                                <span class="text-xs text-gray-400 italic px-2">Inactivo</span>
                                `}
                            </div>
                        ` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        // Paginación mejorada
        renderPaginacion(current_page, last_page, cargarPersonal);

    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-400 text-sm">Error al cargar datos.</td></tr>`;
        console.error(e);
    }
}

// ── El resto de funciones sin cambios ─────────────────────────────────────────
// (guardarRol, abrirResetPwd, guardarPassword, abrirBaja, reactivar, pestañas,
//  cargarIpsBloqueadas, cargarUsuariosBloqueados, etc. — igual que antes)

// ── Cambiar rol ───────────────────────────────────────────────────────────────
window.abrirEditRol = function(nomina, nombre, rol) {
    nominaEditando = nomina;
    document.getElementById('editRolNombre').textContent = nombre;
    document.getElementById('editRolValor').value        = rol;
    document.getElementById('editRolMsg').classList.add('hidden');
    openModal('editRolModal');
}
window.guardarRol = async function() {
    const rol = document.getElementById('editRolValor').value;
    const msg = document.getElementById('editRolMsg');
    const res  = await fetch(`/api/personal/${nominaEditando}/rol`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ rol: Number(rol) }),
    });
    const data = await res.json();
    msg.textContent = res.ok ? data.message : (data.error ?? 'Error');
    msg.className   = `p-3 rounded-lg text-sm border ${res.ok ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}`;
    msg.classList.remove('hidden');
    if (res.ok) setTimeout(() => { closeModal('editRolModal'); cargarPersonal(); }, 1200);
}

window.abrirResetPwd = function(nomina, nombre) {
    nominaEditando = nomina;
    document.getElementById('resetPwdNomina').textContent        = nomina;
    document.getElementById('resetPwdNombreDisplay').textContent = nombre;
    document.getElementById('resetPwdNueva').value     = '';
    document.getElementById('resetPwdConfirmar').value = '';
    document.getElementById('resetPwdMsg').classList.add('hidden');
    openModal('resetPwdModal');
}
window.guardarPassword = async () => {
    const nueva     = document.getElementById('resetPwdNueva').value;
    const confirmar = document.getElementById('resetPwdConfirmar').value;
    const msg       = document.getElementById('resetPwdMsg');
    if (nueva.length < 8) { msg.textContent = 'Mínimo 8 caracteres.'; msg.className = 'p-3 rounded-lg text-sm border bg-red-50 text-red-700 border-red-200'; msg.classList.remove('hidden'); return; }
    if (nueva !== confirmar) { msg.textContent = 'Las contraseñas no coinciden.'; msg.className = 'p-3 rounded-lg text-sm border bg-red-50 text-red-700 border-red-200'; msg.classList.remove('hidden'); return; }
    const res  = await fetch(`/api/personal/${nominaEditando}/reset-password`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ new_password: nueva, new_password_confirmation: confirmar }),
    });
    const data = await res.json();
    msg.textContent = res.ok ? data.message : (data.error ?? 'Error');
    msg.className   = `p-3 rounded-lg text-sm border ${res.ok ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}`;
    msg.classList.remove('hidden');
    if (res.ok) setTimeout(() => closeModal('resetPwdModal'), 1200);
}


// usando onclick en la función abrirBaja para resetear estado:
window.abrirBaja = function(nomina, nombre) {
    nominaEditando = nomina;
    document.getElementById('bajaNombreDisplay').textContent = nombre;
    document.getElementById('bajaNominaDisplay').textContent = nomina;
    document.getElementById('bajaMsg').classList.add('hidden');

    // ← Resetear el botón SIEMPRE al abrir el modal
    const btn = document.getElementById('btnConfirmarBaja');
    btn.disabled    = false;
    btn.textContent = 'Sí, dar de baja';

    openModal('bajaModal');
}

window.confirmarBaja = async () => {
    const btn = document.getElementById('btnConfirmarBaja');
    const msg = document.getElementById('bajaMsg');
    btn.disabled    = true;
    btn.textContent = 'Procesando...';
    try {
        const res  = await fetch(`/api/personal/${nominaEditando}/desactivar`, {
            method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const data = await res.json();
        if (res.ok) {
            msg.textContent = data.message;
            msg.className   = 'mb-3 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200';
            msg.classList.remove('hidden');
            setTimeout(() => { closeModal('bajaModal'); cargarPersonal(); }, 1200);
        } else {
            msg.textContent = data.error ?? 'Error.';
            msg.className   = 'mb-3 p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
            msg.classList.remove('hidden');
            btn.disabled    = false;
            btn.textContent = 'Sí, dar de baja';
        }
    } catch(e) {
        btn.disabled    = false;
        btn.textContent = 'Sí, dar de baja';
    }
}

// ── Eliminar empleado permanentemente (hard) — solo SuperAdmin ────────────────
let nominaHardDelete = null;

window.abrirEliminarEmpleado = function(nomina, nombre) {
    nominaHardDelete = nomina;
    document.getElementById('hardDeleteEmpNombre').textContent = nombre;
    document.getElementById('hardDeleteEmpNomina').textContent = nomina;
    document.getElementById('hardDeleteEmpMsg').classList.add('hidden');

    const btn = document.getElementById('btnConfirmarHardDeleteEmp');
    btn.disabled    = false;
    btn.innerHTML   = '<i class="ph ph-trash"></i> Sí, eliminar permanentemente';

    openModal('hardDeleteEmpModal');
}

window.confirmarEliminarEmpleado = async () => {
    const btn = document.getElementById('btnConfirmarHardDeleteEmp');
    const msg = document.getElementById('hardDeleteEmpMsg');

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Eliminando...';

    try {
        const res  = await fetch(`/api/personal/${nominaHardDelete}/hard`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const data = await res.json();

        if (res.ok) {
            msg.textContent = data.message;
            msg.className   = 'mb-3 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200';
            msg.classList.remove('hidden');
            setTimeout(() => {
                closeModal('hardDeleteEmpModal');
                cargarPersonal();
            }, 1500);
        } else {
            msg.textContent = data.error ?? 'Error al eliminar.';
            msg.className   = 'mb-3 p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-200';
            msg.classList.remove('hidden');
            btn.disabled    = false;
            btn.innerHTML   = '<i class="ph ph-skull"></i> Sí, eliminar permanentemente';
        }
    } catch(e) {
        btn.disabled    = false;
        btn.innerHTML   = '<i class="ph ph-skull"></i> Sí, eliminar permanentemente';
        console.error(e);
    }
}

window.reactivar = async (nomina, nombre) => {
    if (!confirm(`¿Reactivar a ${nombre}?`)) return;
    const res  = await fetch(`/api/personal/${nomina}/reactivar`, {
        method: 'PUT', headers: { 'X-CSRF-TOKEN': CSRF },
    });
    const data = await res.json();
    if (res.ok) cargarPersonal();
    else alert(data.error ?? 'Error al reactivar.');
}

document.addEventListener('DOMContentLoaded', () => cargarPersonal(1));

window.cambiarPestana = function(tab) {
    ['personal', 'seguridad'].forEach(t => {
        const panel = document.getElementById(`panel-${t}`);
        const btn   = document.getElementById(`tab-${t}`);
        if (!panel || !btn) return;
        if (t === tab) {
            panel.classList.remove('hidden');
            btn.classList.add('bg-primary', 'text-white');
            btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
        } else {
            panel.classList.add('hidden');
            btn.classList.remove('bg-primary', 'text-white');
            btn.classList.add('text-gray-600', 'hover:bg-gray-100');
        }
    });
    if (tab === 'seguridad') { cargarIpsBloqueadas(); cargarUsuariosBloqueados(); }
}

window.cargarIpsBloqueadas = async function() {
    const contenedor = document.getElementById('tablaIPs');
    if (!contenedor) return;
    contenedor.innerHTML = '<div class="py-6 text-center text-gray-400 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';
    try {
        const res  = await fetch('/api/personal/ips-bloqueadas', { headers: { 'X-CSRF-TOKEN': CSRF } });
        const data = await res.json();
        if (!data.length) { contenedor.innerHTML = `<div class="py-8 text-center text-gray-400 text-sm">Sin IPs bloqueadas actualmente.</div>`; return; }
        contenedor.innerHTML = data.map(ip => `
        <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition group">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-ban text-red-500 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-mono font-bold text-gray-800">${ip.ip}</p>
                    <p class="text-xs text-gray-400"><span class="text-red-500 font-semibold">${ip.intentos}</span> intentos · Bloqueado: ${ip.bloqueado_en}</p>
                </div>
            </div>
            <button onclick="desbloquearIp('${ip.ip}')"
                    class="opacity-40 group-hover:opacity-100 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fas fa-unlock"></i> Desbloquear
            </button>
        </div>`).join('');
    } catch(e) { contenedor.innerHTML = '<div class="py-4 text-center text-red-400 text-sm">Error al cargar.</div>'; }
}

window.desbloquearIp = async (ip) => {
    if (!confirm(`¿Desbloquear la IP ${ip}?`)) return;
    const res  = await fetch(`/api/personal/ips-bloqueadas/${encodeURIComponent(ip)}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
    const data = await res.json();
    if (res.ok) { mostrarToastPersonal(data.message, 'success'); cargarIpsBloqueadas(); }
    else mostrarToastPersonal(data.error ?? 'Error', 'error');
}

const rolNombres = { 1: 'Empleado', 2: 'Supervisor', 3: 'Admin RH', 4: 'SuperAdmin' };

window.cargarUsuariosBloqueados = async () => {
    const contenedor = document.getElementById('tablaUsuariosBloqueados');
    if (!contenedor) return;
    contenedor.innerHTML = '<div class="py-6 text-center text-gray-400 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';
    try {
        const res  = await fetch('/api/personal/bloqueados', { headers: { 'X-CSRF-TOKEN': CSRF } });
        const data = await res.json();
        if (!data.length) { contenedor.innerHTML = `<div class="py-8 text-center text-gray-400 text-sm">Sin usuarios bloqueados actualmente.</div>`; return; }
        contenedor.innerHTML = data.map(u => {
            const ini = u.nombre.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
            return `
        <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition group">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-red-100 text-red-500 text-xs font-bold flex items-center justify-center flex-shrink-0">${ini}</div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">${u.nombre}</p>
                    <p class="text-xs text-gray-400">#${u.nomina} · <span class="text-gray-500">${rolNombres[u.rol] ?? 'Empleado'}</span>${u.centro !== '—' ? ` · ${u.centro}` : ''}</p>
                </div>
            </div>
            <button onclick="desbloquearUsuario('${u.nomina}', '${u.nombre.replace(/'/g, "\\'")}')"
                    class="opacity-40 group-hover:opacity-100 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fas fa-unlock-alt"></i> Desbloquear
            </button>
        </div>`;
        }).join('');
    } catch(e) { contenedor.innerHTML = '<div class="py-4 text-center text-red-400 text-sm">Error al cargar.</div>'; }
}

window.desbloquearUsuario = async (nomina, nombre) => {
    if (!confirm(`¿Desbloquear la cuenta de ${nombre}?`)) return;
    const res  = await fetch(`/api/personal/${nomina}/desbloquear`, { method: 'PUT', headers: { 'X-CSRF-TOKEN': CSRF } });
    const data = await res.json();
    if (res.ok) { mostrarToastPersonal(data.message, 'success'); cargarUsuariosBloqueados(); }
    else mostrarToastPersonal(data.error ?? 'Error', 'error');
}

window.mostrarToastPersonal = function(msg, type) {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-5 right-5 z-50 px-5 py-3 rounded-xl shadow-xl text-sm font-semibold ${type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

window.togglePwd = function(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const shown = input.type === 'text';
    input.type = shown ? 'password' : 'text';
    const icon = btn.querySelector('i');
    if (icon) icon.className = shown ? 'fas fa-eye text-gray-400' : 'fas fa-eye-slash text-gray-400';
}