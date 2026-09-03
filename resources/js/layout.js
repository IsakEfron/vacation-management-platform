// ─────────────────────────────────────────────────────────────────────────────
// layout.js — JS global compartido por todas las páginas autenticadas
// Requiere window._APP con: passwordChangeRoute, primeraVezForced, esAdmin
// ─────────────────────────────────────────────────────────────────────────────
'use strict';

// ── CSRF token global ─────────────────────────────────────────────────────────
window.CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Modales ───────────────────────────────────────────────────────────────────
window.openModal = function(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    const overlay = m.querySelector('.modal-overlay');
    if (overlay) { void overlay.offsetWidth; overlay.classList.remove('opacity-0'); }
}

window.closeModal = function(id) {
    const m = document.getElementById(id);
    if (!m) return;
    const overlay = m.querySelector('.modal-overlay');
    if (overlay) overlay.classList.add('opacity-0');
    setTimeout(() => { m.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }, 300);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal:not(.hidden)').forEach(m => closeModal(m.id));
});

// ── Cambiar contraseña ────────────────────────────────────────────────────────
window.saveChangePassword = async function() {
    const current = document.getElementById('currentPassword').value;
    const nuevo   = document.getElementById('newPassword').value;
    const confirm = document.getElementById('newPasswordConfirm').value;
    const msgBox  = document.getElementById('passwordMsg');

    if (nuevo !== confirm) { _showMsg(msgBox, 'Las contraseñas no coinciden.', 'error'); return; }
    if (nuevo.length < 8)  { _showMsg(msgBox, 'Mínimo 8 caracteres.', 'error'); return; }

    try {
        const res  = await fetch(window._APP.passwordChangeRoute, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
            body: JSON.stringify({
                current_password:          current,
                new_password:              nuevo,
                new_password_confirmation: confirm,
            }),
        });
        const data = await res.json();

        if (res.ok) {
            _showMsg(msgBox, data.message ?? 'Contraseña actualizada.', 'success');
            setTimeout(() => {
                closeModal('changePasswordModal');
                if (document.body.dataset.primeraVez === '1') window.location.reload();
            }, 1500);
        } else {
            _showMsg(msgBox, data.error ?? 'Error al cambiar la contraseña.', 'error');
        }
    } catch (e) {
        _showMsg(msgBox, 'Error de conexión.', 'error');
    }
}

// ── Toggle visibilidad contraseña ─────────────────────────────────────────────
window.togglePwd = function(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const shown = input.type === 'text';
    input.type = shown ? 'password' : 'text';
    const icon = btn.querySelector('i');
    if (icon) icon.className = shown ? 'fas fa-eye text-gray-400' : 'fas fa-eye-slash text-gray-400';
}

// ── Helper mensajes ───────────────────────────────────────────────────────────
window._showMsg = function(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className   = 'mb-3 p-3 rounded-lg text-sm ' + (type === 'success'
        ? 'bg-green-50 text-green-700 border border-green-200'
        : 'bg-red-50 text-red-700 border border-red-200');
    el.classList.remove('hidden');
}

// ── Notificaciones / Campana ──────────────────────────────────────────────────
const _meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

window._fmtDt = function (iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return `${d.getDate()} ${_meses[d.getMonth()]} ${d.getFullYear()} `
         + `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

window.toggleNotif = function() {
    const panel = document.getElementById('notifPanel');
    if (!panel) return;
    if (panel.style.opacity === '1') {
        cerrarNotif();
    } else {
        panel.classList.remove('hidden');
        void panel.offsetWidth;
        panel.style.opacity   = '1';
        panel.style.transform = 'translateY(0)';
        cargarNotificaciones();
    }
}

window.cerrarNotif = function () {
    const panel = document.getElementById('notifPanel');
    if (!panel) return;
    panel.style.opacity   = '0';
    panel.style.transform = 'translateY(-10px)';
    setTimeout(() => panel.classList.add('hidden'), 250);
}

document.addEventListener('click', e => {
    const panel = document.getElementById('notifPanel');
    if (!panel || panel.classList.contains('hidden')) return;
    if (panel.contains(e.target) || e.target.closest('[onclick="toggleNotif()"]')) return;
    cerrarNotif();
});

window.cargarNotificaciones = async function() {
    const content = document.getElementById('notifContent');
    if (!content) return;
    content.innerHTML = `<div class="flex items-center justify-center py-8 text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin mr-2"></i> Cargando...
    </div>`;

    try {
        const res = await fetch('/api/notificaciones/mantenimiento', {
            headers: { 'X-CSRF-TOKEN': window.CSRF }
        });

        const ct = res.headers.get('content-type') ?? '';
        if (!ct.includes('application/json')) {
            content.innerHTML = `<p class="text-center text-gray-400 text-sm py-6">Sin datos disponibles.</p>`;
            return;
        }

        const d = await res.json();

        const badge = document.getElementById('notifBadge');
        if (badge) {
            const count = d.total ?? 0;
            if (d.en_mantenimiento || count > 0) {
                badge.textContent = d.en_mantenimiento ? '!' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        const stMap = {
            1: { label: 'Próximo', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
            2: { label: 'Activo',  cls: 'bg-red-100 text-red-700 border-red-200' },
        };

        let html = '';

        if (d.en_mantenimiento) {
            html += `
            <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3">
                <div class="h-8 w-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fas fa-tools text-red-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-red-700">Sistema en Mantenimiento</p>
                    <p class="text-xs text-red-500 mt-0.5">Solo el SuperAdmin puede operar ahora.</p>
                </div>
            </div>`;
        }

        if (!d.proximos?.length) {
            html += `
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-check-circle text-green-400 text-3xl mb-2 block"></i>
                <p class="text-sm">Sin mantenimientos próximos programados.</p>
            </div>`;
        } else {
            html += d.proximos.map(m => {
                const st     = stMap[m.estado] ?? stMap[1];
                const ahora  = new Date();
                const inicio = new Date(m.fecha_inicio);
                const diffMs = inicio - ahora;
                const diffH  = Math.floor(diffMs / 3600000);
                const diffMin = Math.floor((diffMs % 3600000) / 60000);
                let countdown = '';
                if (m.estado === 1 && diffMs > 0) {
                    if (diffH >= 24)      countdown = `En ${Math.floor(diffH/24)} día(s)`;
                    else if (diffH > 0)   countdown = `En ${diffH}h ${diffMin}min`;
                    else if (diffMin > 0) countdown = `En ${diffMin} min`;
                    else                  countdown = 'Muy pronto';
                }
                const bg = m.estado === 2 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200';
                return `
                <div class="mb-2 p-3 ${bg} border rounded-xl">
                    <div class="flex justify-between items-start mb-1">
                        <p class="text-sm font-semibold text-gray-800 leading-tight">${m.categoria}</p>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full border ${st.cls} ml-2 flex-shrink-0">${st.label}</span>
                    </div>
                    ${countdown ? `<p class="text-xs font-bold text-blue-600 mb-1.5"><i class="fas fa-hourglass-half mr-1"></i>${countdown}</p>` : ''}
                    <p class="text-xs text-gray-500"><i class="fas fa-play text-green-400 mr-1"></i>${_fmtDt(m.fecha_inicio)}</p>
                    <p class="text-xs text-gray-500"><i class="fas fa-stop text-red-400 mr-1"></i>${_fmtDt(m.fecha_fin)}</p>
                    ${m.notas ? `<p class="text-xs text-gray-400 italic mt-1 border-t border-gray-100 pt-1">${m.notas}</p>` : ''}
                </div>`;
            }).join('');
        }

        content.innerHTML = html;

    } catch (e) {
        content.innerHTML = `<p class="text-center text-gray-400 text-sm py-6">No se pudo cargar la información.</p>`;
        console.error('Error notificaciones:', e);
    }
}

// ── Inicialización en DOMContentLoaded ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // Badge de notificaciones al cargar (sin abrir el panel)
    if (document.getElementById('notifBadge')) {
        fetch('/api/notificaciones/mantenimiento', { headers: { 'X-CSRF-TOKEN': window.CSRF } })
            .then(r => r.ok && r.headers.get('content-type')?.includes('application/json') ? r.json() : null)
            .then(d => {
                if (!d) return;
                const badge = document.getElementById('notifBadge');
                if (!badge) return;
                const count = d.total ?? 0;
                if (d.en_mantenimiento || count > 0) {
                    badge.textContent = d.en_mantenimiento ? '!' : count;
                    badge.classList.remove('hidden');
                }
            })
            .catch(() => {});
    }

    // Modal forzado primer login
    if (window._APP?.primeraVezForced) {
        openModal('changePasswordModal');

        const msgBox = document.getElementById('passwordMsg');
        if (msgBox) {
            msgBox.textContent = '¡Bienvenido! Por seguridad debes cambiar tu contraseña antes de continuar.';
            msgBox.className   = 'mb-3 p-3 rounded-lg text-sm bg-amber-50 text-amber-700 border border-amber-200';
            msgBox.classList.remove('hidden');
        }

        // Bloquear cierre del modal hasta que cambien la contraseña
        const overlay = document.querySelector('#changePasswordModal .modal-overlay');
        if (overlay) overlay.onclick = null;

        // Bloquear Escape
        document.addEventListener('keydown', function bloquearEsc(e) {
            if (e.key === 'Escape') { e.stopImmediatePropagation(); e.preventDefault(); }
        }, true);
    }
});