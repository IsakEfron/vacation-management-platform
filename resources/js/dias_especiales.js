// ─────────────────────────────────────────────────────────────────────────────
// dias_especiales.js — Gestión de Días Especiales, Quincenas y Tipos de Solicitud
// ─────────────────────────────────────────────────────────────────────────────
'use strict';

const DIAS_NOMBRES = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const TIPO_COLORS = {
    feriado:  'bg-red-100 text-red-700 border-red-200',
    puente:   'bg-orange-100 text-orange-700 border-orange-200',
    especial: 'bg-purple-100 text-purple-700 border-purple-200',
};

// ── Pestañas ─────────────────────────────────────────────────────────────────
window.cambiarTab = function(tab) {
    ['dias', 'quincenas', 'tipos'].forEach(t => {
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

    if (tab === 'quincenas' && !_quincenansCargadas) {
        cargarQuincenas();
        _quincenansCargadas = true;
    }
    if (tab === 'tipos' && !_tiposCargados) {
        cargarTipos();
        _tiposCargados = true;
    }
}

let _quincenansCargadas = false;
let _tiposCargados      = false;

// ── Helper de mensajes ────────────────────────────────────────────────────────
window.showMsg = function(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className   = `mb-3 p-3 rounded-lg text-sm ${
        type === 'success'
            ? 'bg-green-50 text-green-700 border border-green-200'
            : 'bg-red-50 text-red-700 border border-red-200'
    }`;
    el.classList.remove('hidden');
    if (type === 'success') setTimeout(() => el.classList.add('hidden'), 4000);
}

// ══════════════════════════════════════════════════════════════════════════════
// SECCIÓN 1 — DÍAS ESPECIALES
// ══════════════════════════════════════════════════════════════════════════════

// ── Toggle días de la semana ──────────────────────────────────────────────────
window.toggleDia = function(btn) {
    const activo = btn.dataset.activo === '1';
    btn.dataset.activo = activo ? '0' : '1';
    if (!activo) {
        btn.classList.add('bg-primary', 'text-white', 'border-primary', 'shadow-sm');
        btn.classList.remove('bg-gray-100', 'text-gray-400', 'border-gray-200');
    } else {
        btn.classList.remove('bg-primary', 'text-white', 'border-primary', 'shadow-sm');
        btn.classList.add('bg-gray-100', 'text-gray-400', 'border-gray-200');
    }
}

window.getDiasSeleccionados = function() {
    return Array.from(document.querySelectorAll('.dia-btn[data-dia]'))
        .filter(b => b.dataset.activo === '1')
        .map(b => parseInt(b.dataset.dia));
}

window.setDias = function(diasHabiles) {
    document.querySelectorAll('.dia-btn[data-dia]').forEach(btn => {
        const esHabil = diasHabiles.includes(parseInt(btn.dataset.dia));
        btn.dataset.activo = esHabil ? '1' : '0';
        if (esHabil) {
            btn.classList.add('bg-primary', 'text-white', 'border-primary', 'shadow-sm');
            btn.classList.remove('bg-gray-100', 'text-gray-400', 'border-gray-200');
        } else {
            btn.classList.remove('bg-primary', 'text-white', 'border-primary', 'shadow-sm');
            btn.classList.add('bg-gray-100', 'text-gray-400', 'border-gray-200');
        }
    });
}

// ── Radio aplica_a ────────────────────────────────────────────────────────────
document.querySelectorAll('input[name="aplicaA"]').forEach(r => {
    r.addEventListener('change', () => {
        const esEspecifico = document.getElementById('radioEspecifico').checked;
        document.getElementById('diaAplicaCentro').classList.toggle('hidden', !esEspecifico);
        document.getElementById('diaAplicaA').value = esEspecifico
            ? document.getElementById('diaAplicaCentro').value
            : 'todos';
    });
});
document.getElementById('diaAplicaCentro').addEventListener('change', function () {
    document.getElementById('diaAplicaA').value = this.value;
});

// ── Cargar días ───────────────────────────────────────────────────────────────
let mostrandoDesactivados = false;

window.cargarDias = async () => {
    const anio   = document.getElementById('filtroAnio').value;
    const activo = mostrandoDesactivados ? 0 : 1;
    const lista  = document.getElementById('listaDias');

    lista.innerHTML = '<div class="py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';

    const rows = await (await fetch(
        `/api/dias-especiales?anio=${anio}&activo=${activo}`,
        { headers: { 'X-CSRF-TOKEN': CSRF } }
    )).json();

    document.getElementById('btnFiltroActivo').innerHTML = mostrandoDesactivados
        ? '<i class="fas fa-eye mr-1"></i> Ver activos'
        : '<i class="fas fa-eye-slash mr-1"></i> Ver desactivados';

    if (!rows.length) {
        lista.innerHTML = `<div class="py-10 text-center text-gray-400 text-sm">
            <i class="fas fa-calendar-check text-green-300 text-3xl mb-2 block"></i>
            Sin días ${mostrandoDesactivados ? 'desactivados' : 'especiales'} para ${anio}.</div>`;
        return;
    }

    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    lista.innerHTML = rows.map(d => {
        const fecha  = new Date(d.fecha + 'T12:00');
        const diaSem = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][fecha.getDay()];
        const cls    = TIPO_COLORS[d.tipo] ?? TIPO_COLORS.especial;
        const audit  = d.modificado_por
            ? `Editado por ${d.modificado_por}`
            : `Creado por ${d.creado_por}`;

        return `
        <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition ${!d.activo ? 'opacity-50' : ''}">
            <div class="flex items-center gap-3">
                <div class="text-center w-12 flex-shrink-0">
                    <p class="text-xs text-gray-400">${diaSem}</p>
                    <p class="text-sm font-black text-gray-800">${fecha.getDate()}</p>
                    <p class="text-xs text-gray-400">${meses[fecha.getMonth()]}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">${d.descripcion}</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs px-2 py-0.5 rounded-full border font-semibold ${cls}">${d.tipo}</span>
                        ${d.aplica_a !== 'todos'
                            ? `<span class="text-xs text-gray-400"><i class="fas fa-building mr-1"></i>${d.aplica_a}</span>`
                            : ''}
                        <span class="text-xs text-gray-300" title="${audit}"><i class="fas fa-info-circle"></i></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1">
                ${d.activo ? `
                <button onclick='abrirModalEditarDia(${JSON.stringify(d)})'
                        class="p-2 text-gray-300 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Editar">
                    <i class="fas fa-pencil-alt text-sm"></i>
                </button>` : ''}
                <button onclick="toggleDiaEspecial(${d.id}, ${d.activo})"
                        class="p-2 rounded-lg transition ${d.activo
                            ? 'text-green-400 hover:text-red-500 hover:bg-red-50'
                            : 'text-gray-300 hover:text-green-500 hover:bg-green-50'}"
                        title="${d.activo ? 'Desactivar' : 'Activar'}">
                    <i class="fas ${d.activo ? 'fa-toggle-on' : 'fa-toggle-off'} text-sm"></i>
                </button>
                ${!d.activo ? `
                <button onclick="hardDeleteDia(${d.id}, '${d.descripcion.replace(/'/g, "\\'")}')"
                        class="p-2 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Eliminar permanentemente">
                    <i class="fas fa-trash text-sm"></i>
                </button>` : ''}
            </div>
        </div>`;
    }).join('');
}

window.toggleFiltroActivo = function() {
    mostrandoDesactivados = !mostrandoDesactivados;
    cargarDias();
}

window.toggleDiaEspecial = async (id, activoActual) => {
    if (!confirm(`¿${activoActual ? 'Desactivar' : 'Activar'} este día especial?`)) return;
    const res  = await fetch(`/api/dias-especiales/${id}/toggle`, {
        method: 'PATCH', headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (res.ok) cargarDias();
    else alert(data.error ?? 'Error al cambiar estado.');
}

window.hardDeleteDia = async (id, descripcion) => {
    if (!confirm(`¿Eliminar permanentemente "${descripcion}"?\nEsta acción no se puede deshacer.`)) return;
    const res  = await fetch(`/api/dias-especiales/${id}/hard`, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (res.ok) cargarDias();
    else alert(data.error ?? 'Error al eliminar.');
}

window.agregarDia = async () => {
    const fecha       = document.getElementById('diaFecha').value;
    const descripcion = document.getElementById('diaDescripcion').value.trim();
    const tipo        = document.getElementById('diaTipo').value;
    const aplica_a    = document.getElementById('diaAplicaA').value;
    const msg         = document.getElementById('diaMsg');

    if (!fecha || !descripcion) { showMsg(msg, 'Completa la fecha y la descripción.', 'error'); return; }

    const res  = await fetch('/api/dias-especiales', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ fecha, descripcion, tipo, aplica_a }),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) {
        document.getElementById('diaFecha').value = '';
        document.getElementById('diaDescripcion').value = '';
        cargarDias();
    }
}

// ── Modal editar día ──────────────────────────────────────────────────────────
window.abrirModalEditarDia = function(dia) {
    document.getElementById('editDiaId').value          = dia.id;
    document.getElementById('editDiaFecha').value       = dia.fecha;
    document.getElementById('editDiaDescripcion').value = dia.descripcion;
    document.getElementById('editDiaTipo').value        = dia.tipo;
    document.getElementById('editDiaMsg').classList.add('hidden');

    const sel    = document.getElementById('editDiaAplicaA');
    const existe = Array.from(sel.options).some(o => o.value === dia.aplica_a);
    if (!existe) sel.add(new Option(dia.aplica_a, dia.aplica_a));
    sel.value = dia.aplica_a;

    document.getElementById('modalEditarDia').classList.remove('hidden');
}

window.cerrarModalEditarDia = function() {
    document.getElementById('modalEditarDia').classList.add('hidden');
}

window.guardarEdicionDia = async () => {
    const id  = document.getElementById('editDiaId').value;
    const msg = document.getElementById('editDiaMsg');
    const body = {
        fecha:       document.getElementById('editDiaFecha').value,
        descripcion: document.getElementById('editDiaDescripcion').value.trim(),
        tipo:        document.getElementById('editDiaTipo').value,
        aplica_a:    document.getElementById('editDiaAplicaA').value,
    };
    if (!body.fecha || !body.descripcion) { showMsg(msg, 'Completa todos los campos.', 'error'); return; }

    const res  = await fetch(`/api/dias-especiales/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) { cerrarModalEditarDia(); cargarDias(); }
}

// ── Centros ───────────────────────────────────────────────────────────────────
window.cargarCentros = async () => {
    const res  = await fetch('/api/dias-especiales/centros', { headers: { 'X-CSRF-TOKEN': CSRF } });
    const data = await res.json();

    const opts = data.centros.map(c => `<option value="${c}">${c}</option>`).join('');
    document.getElementById('centroPago').innerHTML    = '<option value="">Seleccionar centro...</option>' + opts;
    document.getElementById('diaAplicaCentro').innerHTML = '<option value="">Seleccionar...</option>' + opts;
    document.getElementById('editDiaAplicaA').innerHTML =
        '<option value="todos">Todos los centros</option>' + opts;

    const configurados = Object.keys(data.configurados);
    const resumen = document.getElementById('centrosConfigurados');
    if (!configurados.length) {
        resumen.innerHTML = '<p class="text-gray-400">Todos usan la regla global (L–S).</p>';
    } else {
        resumen.innerHTML = configurados.map(c => {
            const diasHabiles = Object.values(data.configurados[c])
                .filter(d => parseInt(d.es_habil) === 1)
                .map(d => DIAS_NOMBRES[d.dia_semana]?.slice(0, 3) ?? '')
                .join(', ');
            return `<div class="flex justify-between items-center py-1.5 border-b border-gray-100 last:border-0">
                <span class="font-medium text-gray-700">${c}</span>
                <span class="text-gray-500">${diasHabiles}</span>
            </div>`;
        }).join('');
    }
}

window.cargarConfigCentro = async (centro) => {
    if (!centro) {
        document.getElementById('panelDiasSemana').classList.add('hidden');
        document.getElementById('sinCentro').classList.remove('hidden');
        return;
    }

    const res  = await fetch('/api/dias-especiales/centros', { headers: { 'X-CSRF-TOKEN': CSRF } });
    const data = await res.json();
    const cfg  = data.configurados[centro];

    setDias(cfg
        ? Object.values(cfg).filter(d => parseInt(d.es_habil) === 1).map(d => parseInt(d.dia_semana))
        : [1, 2, 3, 4, 5, 6]
    );

    document.getElementById('centroMsg').classList.add('hidden');
    document.getElementById('panelDiasSemana').classList.remove('hidden');
    document.getElementById('sinCentro').classList.add('hidden');
}

window.guardarCentro = async () => {
    const centro = document.getElementById('centroPago').value;
    const msg    = document.getElementById('centroMsg');
    if (!centro) { showMsg(msg, 'Selecciona un centro.', 'error'); return; }

    const res  = await fetch('/api/dias-especiales/centros', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ centro_pago: centro, dias: getDiasSeleccionados() }),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) cargarCentros();
}

window.eliminarCentro = async () => {
    const centro = document.getElementById('centroPago').value;
    if (!centro || !confirm(`¿Eliminar la configuración de "${centro}"?`)) return;

    const res  = await fetch(`/api/dias-especiales/centros/${encodeURIComponent(centro)}`, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    const msg  = document.getElementById('centroMsg');
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) { cargarCentros(); cargarConfigCentro(centro); }
}

// ══════════════════════════════════════════════════════════════════════════════
// SECCIÓN 2 — QUINCENAS
// ══════════════════════════════════════════════════════════════════════════════

let mostrandoQInactivas = false;

window.toggleQFiltroActivo = function() {
    mostrandoQInactivas = !mostrandoQInactivas;
    document.getElementById('btnQFiltroActivo').innerHTML = mostrandoQInactivas
        ? '<i class="fas fa-eye mr-1"></i> Ver activas'
        : '<i class="fas fa-eye-slash mr-1"></i> Ver inactivas';
    cargarQuincenas();
}

window.cargarQuincenas = async () => {
    const anio   = document.getElementById('qFiltroAnio').value;
    const activo = mostrandoQInactivas ? 0 : 1;
    const lista  = document.getElementById('listaQuincenas');

    lista.innerHTML = '<div class="py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';

    const rows = await (await fetch(
        `/api/quincenas?anio=${anio}&activo=${activo}`,
        { headers: { 'X-CSRF-TOKEN': CSRF } }
    )).json();

    if (!rows.length) {
        lista.innerHTML = `<div class="py-10 text-center text-gray-400 text-sm">
            <i class="fas fa-calendar-alt text-3xl mb-2 block text-emerald-200"></i>
            Sin quincenas ${mostrandoQInactivas ? 'inactivas' : 'registradas'} para ${anio}.
            ${!mostrandoQInactivas ? `<button onclick="generarQuincenasRapido(${anio})"
                class="block mx-auto mt-2 text-xs text-emerald-600 underline">Generar ${anio} automáticamente</button>` : ''}
        </div>`;
        return;
    }

    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    lista.innerHTML = rows.map(q => {
        const fi  = new Date(q.fecha_inicio + 'T12:00');
        const ff  = new Date(q.fecha_fin    + 'T12:00');
        const fmtFi = `${fi.getDate()} ${meses[fi.getMonth()]}`;
        const fmtFf = `${ff.getDate()} ${meses[ff.getMonth()]} ${ff.getFullYear()}`;

        return `
        <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition group ${!q.activo ? 'opacity-50' : ''}">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-700 text-xs font-black flex items-center justify-center flex-shrink-0">
                    Q${q.numero}
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">${q.descripcion}</p>
                    <p class="text-xs text-gray-400">${fmtFi} — ${fmtFf}</p>
                </div>
            </div>
            <div class="flex items-center gap-1 opacity-40 group-hover:opacity-100 transition">
                ${q.activo ? `
                <button onclick='abrirModalEditarQ(${JSON.stringify(q)})'
                        class="p-2 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Editar">
                    <i class="fas fa-pencil-alt text-sm"></i>
                </button>` : ''}
                <button onclick="toggleQuincena(${q.id}, ${q.activo})"
                        class="p-2 rounded-lg transition ${q.activo
                            ? 'text-green-400 hover:text-red-500 hover:bg-red-50'
                            : 'text-gray-300 hover:text-green-500 hover:bg-green-50'}"
                        title="${q.activo ? 'Desactivar' : 'Activar'}">
                    <i class="fas ${q.activo ? 'fa-toggle-on' : 'fa-toggle-off'} text-sm"></i>
                </button>
                ${!q.activo ? `
                <button onclick="hardDeleteQuincena(${q.id}, '${q.descripcion.replace(/'/g, "\\'")}')"
                        class="p-2 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Eliminar permanentemente">
                    <i class="fas fa-trash text-sm"></i>
                </button>` : ''}
            </div>
        </div>`;
    }).join('');
}

window.agregarQuincena = async () => {
    const msg = document.getElementById('qMsg');
    const body = {
        anio:         parseInt(document.getElementById('qAnio').value),
        numero:       parseInt(document.getElementById('qNumero').value),
        descripcion:  document.getElementById('qDescripcion').value.trim(),
        fecha_inicio: document.getElementById('qFechaInicio').value,
        fecha_fin:    document.getElementById('qFechaFin').value,
    };
    if (!body.descripcion || !body.fecha_inicio || !body.fecha_fin || !body.numero) {
        showMsg(msg, 'Completa todos los campos.', 'error'); return;
    }

    const res  = await fetch('/api/quincenas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) {
        document.getElementById('qNumero').value      = '';
        document.getElementById('qDescripcion').value = '';
        document.getElementById('qFechaInicio').value = '';
        document.getElementById('qFechaFin').value    = '';
        document.getElementById('qFiltroAnio').value  = body.anio;
        cargarQuincenas();
    }
}

window.generarQuincenas = async () => {
    const anio = parseInt(document.getElementById('qGenerarAnio').value);
    const msg  = document.getElementById('qGenerarMsg');
    if (!confirm(`¿Generar las 24 quincenas del año ${anio}?\nPodrás editarlas después.`)) return;

    const res  = await fetch('/api/quincenas/generar-anio', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ anio }),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) {
        document.getElementById('qFiltroAnio').value = anio;
        _quincenansCargadas = true;
        cargarQuincenas();
    }
}

window.generarQuincenasRapido = async (anio) => {
    if (!confirm(`¿Generar las 24 quincenas del año ${anio}?`)) return;
    const res  = await fetch('/api/quincenas/generar-anio', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ anio }),
    });
    const data = await res.json();
    if (res.ok) cargarQuincenas();
    else alert(data.error ?? 'Error al generar.');
}

window.toggleQuincena = async (id, activoActual) => {
    if (!confirm(`¿${activoActual ? 'Desactivar' : 'Activar'} esta quincena?`)) return;
    const res  = await fetch(`/api/quincenas/${id}/toggle`, {
        method: 'PATCH', headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (res.ok) cargarQuincenas();
    else alert(data.error ?? 'Error.');
}

window.hardDeleteQuincena = async (id, descripcion) => {
    if (!confirm(`¿Eliminar permanentemente "${descripcion}"?`)) return;
    const res  = await fetch(`/api/quincenas/${id}/hard`, {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (res.ok) cargarQuincenas();
    else alert(data.error ?? 'Error al eliminar.');
}

// ── Modal editar quincena ─────────────────────────────────────────────────────
window.abrirModalEditarQ = function(q) {
    document.getElementById('editQId').value          = q.id;
    document.getElementById('editQAnio').value        = q.anio;
    document.getElementById('editQNumero').value      = q.numero;
    document.getElementById('editQDescripcion').value = q.descripcion;
    document.getElementById('editQFechaInicio').value = q.fecha_inicio;
    document.getElementById('editQFechaFin').value    = q.fecha_fin;
    document.getElementById('editQMsg').classList.add('hidden');
    document.getElementById('modalEditarQuincena').classList.remove('hidden');
}

window.cerrarModalEditarQ = function() {
    document.getElementById('modalEditarQuincena').classList.add('hidden');
}

window.guardarEdicionQ = async () => {
    const id  = document.getElementById('editQId').value;
    const msg = document.getElementById('editQMsg');
    const body = {
        anio:         parseInt(document.getElementById('editQAnio').value),
        numero:       parseInt(document.getElementById('editQNumero').value),
        descripcion:  document.getElementById('editQDescripcion').value.trim(),
        fecha_inicio: document.getElementById('editQFechaInicio').value,
        fecha_fin:    document.getElementById('editQFechaFin').value,
    };
    if (!body.descripcion || !body.fecha_inicio || !body.fecha_fin) {
        showMsg(msg, 'Completa todos los campos.', 'error'); return;
    }

    const res  = await fetch(`/api/quincenas/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) { cerrarModalEditarQ(); cargarQuincenas(); }
}

// ══════════════════════════════════════════════════════════════════════════════
// SECCIÓN 3 — TIPOS DE SOLICITUD
// ══════════════════════════════════════════════════════════════════════════════

window.cargarTipos = async () => {
    const lista = document.getElementById('listaTipos');
    lista.innerHTML = '<div class="py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';

    const rows = await (await fetch('/api/tipos-solicitud', { headers: { 'X-CSRF-TOKEN': CSRF } })).json();

    if (!rows.length) {
        lista.innerHTML = '<div class="py-8 text-center text-gray-400 text-sm">Sin tipos registrados.</div>';
        return;
    }

    lista.innerHTML = rows.map(t => `
        <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition group ${!t.activo ? 'opacity-50' : ''}">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl ${t.activo ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400'} flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-tag text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">${t.nombre}</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs px-2 py-0.5 rounded-full border ${t.con_goce
                            ? 'bg-green-100 text-green-700 border-green-200'
                            : 'bg-gray-100 text-gray-500 border-gray-200'}">
                            ${t.con_goce ? 'Con goce' : 'Sin goce'}
                        </span>
                        ${t.usa_saldo ? `<span class="text-xs px-2 py-0.5 rounded-full border bg-amber-100 text-amber-700 border-amber-200">
                            Descuenta saldo</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1 opacity-40 group-hover:opacity-100 transition">
                ${t.activo ? `
                <button onclick='abrirModalEditarTipo(${JSON.stringify(t)})'
                        class="p-2 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Editar">
                    <i class="fas fa-pencil-alt text-sm"></i>
                </button>` : ''}
                <button onclick="toggleTipo(${t.id}, ${t.activo})"
                        class="p-2 rounded-lg transition ${t.activo
                            ? 'text-green-400 hover:text-red-500 hover:bg-red-50'
                            : 'text-gray-300 hover:text-green-500 hover:bg-green-50'}"
                        title="${t.activo ? 'Desactivar' : 'Activar'}">
                    <i class="fas ${t.activo ? 'fa-toggle-on' : 'fa-toggle-off'} text-sm"></i>
                </button>
            </div>
        </div>`).join('');
}

window.agregarTipo = async () => {
    const msg = document.getElementById('tipoMsg');
    const body = {
        nombre:    document.getElementById('tipoNombre').value.trim(),
        con_goce:  document.querySelector('input[name="tipoConGoce"]:checked')?.value === '1',
        usa_saldo: document.querySelector('input[name="tipoUsaSaldo"]:checked')?.value === '1',
    };
    if (!body.nombre) { showMsg(msg, 'Escribe el nombre del tipo.', 'error'); return; }

    const res  = await fetch('/api/tipos-solicitud', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) {
        document.getElementById('tipoNombre').value = '';
        cargarTipos();
    }
}

window.toggleTipo = async (id, activoActual) => {
    const accion = activoActual ? 'desactivar' : 'activar';
    if (!confirm(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} este tipo de solicitud?`)) return;

    const res  = await fetch(`/api/tipos-solicitud/${id}/toggle`, {
        method: 'PATCH', headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();
    if (res.ok) cargarTipos();
    else alert(data.error ?? 'Error.');
}

// ── Modal editar tipo ─────────────────────────────────────────────────────────
window.abrirModalEditarTipo = (t) => {
    document.getElementById('editTipoId').value     = t.id;
    document.getElementById('editTipoNombre').value = t.nombre;
    document.getElementById('editTipoMsg').classList.add('hidden');

    document.querySelector(`input[name="editTipoConGoce"][value="${t.con_goce ? '1' : '0'}"]`).checked   = true;
    document.querySelector(`input[name="editTipoUsaSaldo"][value="${t.usa_saldo ? '1' : '0'}"]`).checked = true;

    document.getElementById('modalEditarTipo').classList.remove('hidden');
}


window.cerrarModalEditarTipo = function() {
    document.getElementById('modalEditarTipo').classList.add('hidden');
        console.log("cerrar")
}

window.guardarEdicionTipo = async () => {
    const id  = document.getElementById('editTipoId').value;
    const msg = document.getElementById('editTipoMsg');
    const body = {
        nombre:    document.getElementById('editTipoNombre').value.trim(),
        con_goce:  document.querySelector('input[name="editTipoConGoce"]:checked')?.value === '1',
        usa_saldo: document.querySelector('input[name="editTipoUsaSaldo"]:checked')?.value === '1',
    };
    if (!body.nombre) { showMsg(msg, 'Escribe el nombre.', 'error'); return; }

    const res  = await fetch(`/api/tipos-solicitud/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    showMsg(msg, res.ok ? data.message : (data.error ?? 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) { cerrarModalEditarTipo(); cargarTipos(); }
}

// ── Inicializar ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cargarDias();
    cargarCentros();
    // Quincenas y tipos se cargan lazy (al hacer clic en la pestaña)
});