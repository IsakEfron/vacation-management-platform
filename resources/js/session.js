// ─────────────────────────────────────────────────────────────────────────────
// session.js — Timeout de inactividad + cross-tab logout
// Se carga solo cuando el usuario está autenticado (ver app.blade.php).
// Lee window._APP.esAdmin para configurar el timeout.
// ─────────────────────────────────────────────────────────────────────────────
(function () {
    'use strict';

    const TIMEOUT_SEG = window._APP?.esAdmin ? 0 : 3600; // 0 = sin timeout (SuperAdmin)
    const AVISO_SEG   = 60;
    const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Cross-tab broadcast ───────────────────────────────────────────────────
    let bc;
    try {
        bc = new BroadcastChannel('session_canal');
        bc.onmessage = e => {
            if (e.data === 'logout' && !window._sesionExpirada) {
                window._sesionExpirada = true;
                window.location.replace('/');
            }
        };
    } catch (err) { /* BroadcastChannel no disponible en este navegador */ }

    // Interceptar formulario de logout para notificar otras pestañas
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[action*="logout"]').forEach(form => {
            form.addEventListener('submit', () => {
                try { bc?.postMessage('logout'); } catch (e) {}
            });
        });
    });

    // SuperAdmin: solo cross-tab, sin timeout
    if (TIMEOUT_SEG === 0) return;

    // ── Estado de inactividad ─────────────────────────────────────────────────
    let ultimaActividad = Date.now();
    let avisoMostrado   = false;

    const resetear = () => {
        ultimaActividad = Date.now();
        ocultarAviso();
    };

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click']
        .forEach(ev => document.addEventListener(ev, resetear, { passive: true }));

    // ── Verificación cada segundo ─────────────────────────────────────────────
    setInterval(() => {
        const inactivoSeg = (Date.now() - ultimaActividad) / 1000;
        const restante    = TIMEOUT_SEG - inactivoSeg;

        if (restante <= 0) {
            expirarSesion();
        } else if (restante <= AVISO_SEG && !avisoMostrado) {
            mostrarAvisoProximo(Math.floor(restante));
        } else if (avisoMostrado && restante > AVISO_SEG) {
            ocultarAviso();
        }
    }, 1000);

    // ── Aviso de proximidad ───────────────────────────────────────────────────
    function mostrarAvisoProximo(segsRestantes) {
        avisoMostrado = true;
        let bar = document.getElementById('sessionWarningBar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id        = 'sessionWarningBar';
            bar.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 z-[9998] bg-amber-500 text-white px-6 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-sm font-semibold';
            bar.innerHTML = `
                <i class="fas fa-clock text-lg"></i>
                <span>Tu sesión cerrará en <strong id="warnSeg">${segsRestantes}</strong>s por inactividad.</span>
                <button onclick="(()=>{ document.getElementById('sessionWarningBar')?.remove(); })()"
                        class="ml-2 bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg text-xs transition">
                    Seguir activo
                </button>`;
            document.body.appendChild(bar);
        } else {
            const el = bar.querySelector('#warnSeg');
            if (el) el.textContent = segsRestantes;
        }
    }

    function ocultarAviso() {
        avisoMostrado = false;
        document.getElementById('sessionWarningBar')?.remove();
    }

    // ── Expirar sesión ────────────────────────────────────────────────────────
    function expirarSesion() {
        if (window._sesionExpirada) return;
        window._sesionExpirada = true;

        try { bc?.postMessage('logout'); } catch (e) {}

        ocultarAviso();

        // Cerrar modales abiertos
        document.querySelectorAll('.modal:not(.hidden)').forEach(m => {
            m.classList.add('hidden');
        });
        document.body.classList.remove('overflow-hidden');

        const banner    = document.createElement('div');
        banner.id       = 'sessionExpiredBanner';
        banner.className = 'fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 backdrop-blur-sm';
        banner.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center">
                <div class="h-16 w-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-clock text-amber-500 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Sesión expirada</h3>
                <p class="text-gray-500 text-sm mb-1">Cerraste por inactividad.</p>
                <p class="text-gray-400 text-xs mb-6">Redirigiendo en <span id="cntSeg">8</span>s...</p>
                <button onclick="window.location.replace('/')"
                        class="w-full bg-primary hover:bg-blue-900 text-white font-bold py-3 px-6 rounded-xl transition">
                    Iniciar sesión
                </button>
            </div>`;
        document.body.appendChild(banner);

        let seg = 8;
        const t = setInterval(() => {
            seg--;
            const el = document.getElementById('cntSeg');
            if (el) el.textContent = seg;
            if (seg <= 0) { clearInterval(t); window.location.replace('/'); }
        }, 1000);
    }

})();