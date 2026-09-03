'use strict';
let grupoActualId = null;

// ── Cargar lista de grupos ─────────────────────────────────────────────────────

window.cargarGrupos = async () => {
    const r = await fetch('/api/grupos', { headers: { 'X-CSRF-TOKEN': CSRF } });
    const grupos = await r.json();
    document.getElementById('totalGrupos').textContent = grupos.length;

    const lista = document.getElementById('listaGrupos');
    if (!grupos.length) {
        lista.innerHTML = `<li class="p-6 text-center text-gray-400 text-sm">
            <i class="ph ph-users-three text-2xl mb-2 block text-gray-300"></i>No hay grupos.</li>`;
        return;
    }

    lista.innerHTML = grupos.map(g => `
        <li onclick="cargarGrupo(${g.id})"
            class="grupo-item p-4 cursor-pointer transition border-l-4 border-transparent hover:bg-gray-50"
            data-id="${g.id}">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-semibold text-gray-800 text-sm">${g.nombre}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        <i class="fas fa-user-tie mr-1"></i>${g.supervisor_nombre}
                        &nbsp;·&nbsp;<i class="fas fa-users mr-1"></i>${g.total} miembros
                    </p>
                </div>
                <i class="ph ph-caret-right text-gray-300"></i>
            </div>
        </li>`).join('');
}

// ── Cargar detalle de grupo ───────────────────────────────────────────────────

window.cargarGrupo = async (id) => {
    grupoActualId = id;

    document.querySelectorAll('.grupo-item').forEach(el => {
        const activo = Number(el.dataset.id) === Number(id);
        el.classList.toggle('bg-primary',         activo);
        el.classList.toggle('border-amber-400',   activo);
        el.querySelector('h3').classList.toggle('text-white', activo);
        el.querySelector('p').classList.toggle('text-blue-200', activo);
        el.querySelector('p').classList.toggle('text-gray-400', !activo);
    });

    const r = await fetch(`/api/grupos/${id}`, { headers: { 'X-CSRF-TOKEN': CSRF } });
    const g = await r.json();

    document.getElementById('estadoVacio').classList.add('hidden');
    document.getElementById('contenidoGrupo').classList.remove('hidden');
    document.getElementById('grupoNombre').textContent      = g.nombre;
    document.getElementById('supervisorNombre').textContent = g.supervisor_nombre;
    document.getElementById('supervisorNomina').textContent = `Nómina: #${g.supervisor}`;
    document.getElementById('tituloMiembros').textContent   = `Miembros del Equipo (${g.miembros.length})`;

    const grid = document.getElementById('gridMiembros');
    if (!g.miembros.length) {
        grid.innerHTML = `<div class="col-span-2 text-center text-gray-400 text-sm py-8">
            <i class="ph ph-user-plus text-2xl mb-2 block"></i>Sin miembros. Usa "Añadir" o "Importar JSON".</div>`;
        return;
    }

    grid.innerHTML = g.miembros.map(m => `
        <div class="border border-gray-200 rounded-xl p-4 flex items-center justify-between hover:shadow-sm transition bg-white group">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 text-xs font-bold">${m.iniciales}</div>
                <div>
                    <p class="text-sm font-bold text-gray-800">${m.nombre}</p>
                    <p class="text-xs text-gray-500">#${m.nomina} · ${m.rol}</p>
                </div>
            </div>
            <button onclick="quitarMiembro('${m.nomina}', '${m.nombre.replace(/'/g, "\\'")}')"
                    class="text-gray-300 hover:text-red-500 p-2 rounded-lg group-hover:bg-red-50 transition">
                <i class="ph ph-x-circle text-xl"></i>
            </button>
        </div>`).join('');
}

// ── Búsqueda de empleados (dropdown genérico) ─────────────────────────────────

window.buscarEmpleados = async (q, dropdownId, hiddenId, labelId, indicadorId) => {
    const dd = document.getElementById(dropdownId);

    if (q.trim() === '') {
        dd.classList.add('hidden');
        return;
    }

    const r     = await fetch(`/api/grupos/buscar-empleados?q=${encodeURIComponent(q)}`, {
        headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const items = await r.json();

    if (!items.length) {
        dd.innerHTML = `<p class="p-3 text-xs text-gray-400 text-center">Sin resultados para "${q}".</p>`;
        dd.classList.remove('hidden');
        return;
    }

    dd.innerHTML = items.map(e => `
        <div onclick="seleccionarEmpleado('${e.nomina}', '${e.nombre.replace(/'/g, "\\'")}', '${dropdownId}', '${hiddenId}', '${labelId}', '${indicadorId}')"
             class="px-3 py-2.5 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-800">${e.nombre}</span>
            <span class="text-xs text-gray-400 font-mono">#${e.nomina}</span>
        </div>`).join('');

    dd.classList.remove('hidden');
}

window.seleccionarEmpleado = function(nomina, nombre, ddId, hiddenId, labelId, indicadorId) {
    document.getElementById(hiddenId).value = nomina;
    if (labelId) document.getElementById(labelId).value = `${nombre} (#${nomina})`;

    // Mostrar indicador de selección
    if (indicadorId) {
        const ind = document.getElementById(indicadorId);
        const span = ind.querySelector('span');
        if (span) span.textContent = `${nombre} (#${nomina}) seleccionado`;
        ind.classList.remove('hidden');
    }

    document.getElementById(ddId).classList.add('hidden');
}

window.buscarEmpleadoParaSup = function(q) {
    buscarEmpleados(q, 'dropdownSup', 'nuevoGrupoSup', 'nuevoGrupoSupBuscar', 'supSeleccionado');
}
window.buscarParaAgregar = function(q) {
    buscarEmpleados(q, 'dropdownAgregar', 'agregarNomina', 'agregarBuscar', 'agregarSeleccionado');
}
window.buscarParaSupervisor = function(q) {
    buscarEmpleados(q, 'dropdownSupCambio', 'nuevoSupervisor', 'supBuscar', 'supCambioSeleccionado');
}

// Cerrar dropdowns al hacer click fuera
document.addEventListener('click', e => {
    ['dropdownSup', 'dropdownAgregar', 'dropdownSupCambio'].forEach(id => {
        const dd = document.getElementById(id);
        if (!dd.contains(e.target) && !e.target.matches('input')) {
            dd.classList.add('hidden');
        }
    });
});

// ── Crear grupo ───────────────────────────────────────────────────────────────

window.crearGrupo = async () => {
    const nombre = document.getElementById('nuevoGrupoNombre').value.trim();
    const sup    = document.getElementById('nuevoGrupoSup').value;
    const msg    = document.getElementById('nuevoGrupoMsg');

    if (!nombre || !sup) {
        showMsg(msg, 'Completa el nombre y selecciona un supervisor de la lista.', 'error');
        return;
    }

    const r = await fetch('/api/grupos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ nombre, supervisor: sup }),
    });
    const d = await r.json();
    showMsg(msg, d.message ?? d.error, r.ok ? 'success' : 'error');

    if (r.ok) {
        setTimeout(() => { closeModal('nuevoGrupoModal'); cargarGrupos(); cargarGrupo(d.id); }, 900);
    }
}

// ── Agregar miembro individual ────────────────────────────────────────────────

window.abrirAgregarMiembro = function() {
    document.getElementById('agregarBuscar').value  = '';
    document.getElementById('agregarNomina').value  = '';
    document.getElementById('agregarMsg').classList.add('hidden');
    document.getElementById('agregarSeleccionado').classList.add('hidden');
    openModal('agregarModal');
}

window.confirmarAgregarMiembro = async () => {
    const nomina = document.getElementById('agregarNomina').value.trim();
    const msg    = document.getElementById('agregarMsg');

    if (!nomina) {
        showMsg(msg, 'Debes seleccionar un empleado de la lista desplegable.', 'error');
        return;
    }

    const r = await fetch(`/api/grupos/${grupoActualId}/miembros`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ nomina }),
    });
    const d = await r.json();
    showMsg(msg, d.message ?? d.error, r.ok ? 'success' : 'error');

    if (r.ok) setTimeout(() => { closeModal('agregarModal'); cargarGrupo(grupoActualId); cargarGrupos(); }, 900);
}

// ── Importación JSON masiva ───────────────────────────────────────────────────

window.cargarArchivoJSON = function(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('importarJSON').value = e.target.result;
    };
    reader.readAsText(file);
}

window.ejecutarImportacion = async () => {
    const texto    = document.getElementById('importarJSON').value.trim();
    const resultado = document.getElementById('importarResultado');

    if (!texto) {
        showMsg(resultado, 'Pega el JSON o carga un archivo .json antes de importar.', 'error');
        resultado.classList.remove('hidden');
        return;
    }

    let nominas;
    try {
        nominas = JSON.parse(texto);
        if (!Array.isArray(nominas)) throw new Error();
    } catch {
        showMsg(resultado, 'El JSON no es válido. Debe ser un array: ["123", "456"] o [{"nomina":"123"}]', 'error');
        resultado.classList.remove('hidden');
        return;
    }

    const r = await fetch(`/api/grupos/${grupoActualId}/importar`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ nominas }),
    });
    const d = await r.json();

    if (r.ok) {
        resultado.className = 'mb-3 p-4 rounded-xl border bg-green-50 border-green-200 text-sm';
        resultado.innerHTML = `
            <p class="font-bold text-green-800 mb-2"><i class="fas fa-check-circle mr-1"></i>${d.message}</p>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-white rounded-lg p-2 border border-green-200">
                    <p class="text-xl font-black text-green-700">${d.insertados}</p>
                    <p class="text-xs text-gray-500">Agregados</p>
                </div>
                <div class="bg-white rounded-lg p-2 border border-gray-200">
                    <p class="text-xl font-black text-gray-600">${d.ya_miembros}</p>
                    <p class="text-xs text-gray-500">Ya eran miembros</p>
                </div>
                <div class="bg-white rounded-lg p-2 border border-${d.no_encontrados.length ? 'red' : 'gray'}-200">
                    <p class="text-xl font-black text-${d.no_encontrados.length ? 'red' : 'gray'}-600">${d.no_encontrados.length}</p>
                    <p class="text-xs text-gray-500">No encontrados</p>
                </div>
            </div>
            ${d.no_encontrados.length ? `
            <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-xs font-bold text-red-700 mb-1">Nóminas no encontradas en el sistema:</p>
                <p class="text-xs text-red-600 font-mono">${d.no_encontrados.join(', ')}</p>
            </div>` : ''}`;
        resultado.classList.remove('hidden');

        if (d.insertados > 0) {
            cargarGrupo(grupoActualId);
            cargarGrupos();
        }
    } else {
        showMsg(resultado, d.error ?? 'Error al importar.', 'error');
        resultado.classList.remove('hidden');
    }
}

// ── Quitar miembro ────────────────────────────────────────────────────────────

window.quitarMiembro = async (nomina, nombre) => {
    if (!confirm(`¿Quitar a ${nombre} del grupo?`)) return;

    const r = await fetch(`/api/grupos/${grupoActualId}/miembros/${nomina}`, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF },
    });
    const d = await r.json();
    if (r.ok) { cargarGrupo(grupoActualId); cargarGrupos(); }
    else alert(d.error);
}

// ── Cambiar supervisor ────────────────────────────────────────────────────────

window.confirmarCambioSupervisor = async () => {
    const nomina = document.getElementById('nuevoSupervisor').value;
    const msg    = document.getElementById('supMsg');

    if (!nomina) { showMsg(msg, 'Selecciona un empleado de la lista.', 'error'); return; }

    const r = await fetch(`/api/grupos/${grupoActualId}/supervisor`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ supervisor: nomina }),
    });
    const d = await r.json();
    showMsg(msg, d.message ?? d.error, r.ok ? 'success' : 'error');
    if (r.ok) setTimeout(() => { closeModal('supervisorModal'); cargarGrupo(grupoActualId); cargarGrupos(); }, 900);
}

// ── Eliminar grupo ────────────────────────────────────────────────────────────

window.eliminarGrupoActual = async () => {
    if (!confirm('¿Eliminar este grupo? Los empleados no serán afectados.')) return;

    const r = await fetch(`/api/grupos/${grupoActualId}`, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF },
    });
    const d = await r.json();

    if (r.ok) {
        grupoActualId = null;
        document.getElementById('contenidoGrupo').classList.add('hidden');
        document.getElementById('estadoVacio').classList.remove('hidden');
        cargarGrupos();
    } else { alert(d.error); }
}

// ── Helper de mensajes ────────────────────────────────────────────────────────

window.showMsg = function(el, text, type) {
    el.textContent = text;
    el.className = `p-3 rounded-lg text-sm ${type === 'success'
        ? 'bg-green-50 text-green-700 border border-green-200'
        : 'bg-red-50 text-red-700 border border-red-200'}`;
    el.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', cargarGrupos);