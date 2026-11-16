@include('layouts.shared/main')

<head>
    @include('layouts.shared/title-meta', ['title' => $title])
    @yield('css')
    @include('layouts.shared/head-css')
</head>

<body class="bg-gray-50 dark:bg-slate-900 transition-colors duration-300">

    <div class="wrapper">

        @include('layouts.shared/sidenav')

        <div id="page-content" class="page-content">

            @include('layouts.shared/topbar')

            <main>
                <!-- Start Content-->
                @yield('content')
            </main>

            @include('layouts.shared/footer')

        </div>

    </div>

<!-- Contenedor Popups Superior Derecho (SIP Demandas) SIN BLOQUEO TEMPORAL -->
    <div id="demandas-toast-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;">
    </div>

    <div id="modal-detalle-solicitud-sip" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-slate-950/50 p-4 opacity-0 transition-opacity duration-300" role="dialog" aria-modal="true" aria-labelledby="titulo-detalle-solicitud-sip">
        <section id="modal-detalle-solicitud-sip-panel" class="w-full max-w-2xl translate-y-4 overflow-hidden rounded-xl bg-white opacity-0 shadow-2xl transition-all duration-300 dark:bg-slate-800">
            <header class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <div>
                    <h2 id="titulo-detalle-solicitud-sip" class="text-lg font-semibold text-slate-900 dark:text-white">Solicitud de actualización desde el SIP</h2>
                    <p id="subtitulo-detalle-solicitud-sip" class="mt-1 text-sm text-slate-500">Datos del personal que envió solicitudes desde el SIP.</p>
                </div>
                <button type="button" onclick="cerrarDetalleDemandaSip()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Cerrar"><i class="bx bx-x text-xl"></i></button>
            </header>
            <div id="detalle-solicitudes-sip-lista" class="max-h-[65vh] space-y-3 overflow-y-auto px-5 py-5"></div>
            <footer class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-700"><button type="button" onclick="cerrarDetalleDemandaSip()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Cerrar</button></footer>
        </section>
    </div>

    <!-- Popup notificación folios por vencer -->
    <div id="folio-toast"
        style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-left: 5px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            min-width: 320px;
            display: none;
            opacity: 1;
            transition: opacity 0.5s ease;
        ">
    </div>


    @include('layouts.shared.footer-scripts')

    @php
        // Pop-ups de notificaciones: solo se muestran UNA VEZ por login.
        // La primera renderización consume el flag; al navegar entre vistas ya no aparecen.
        $ocultarNotificacionesDj = $ocultarNotificacionesDj ?? false;
        $notifPopupsAutoshow = !$ocultarNotificacionesDj && !session('notif_popups_ya_mostrados');
        if ($notifPopupsAutoshow) {
            session(['notif_popups_ya_mostrados' => true]);
        }
    @endphp

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (@json($ocultarNotificacionesDj)) return;
            const ETAPA2_RECORDATORIO_KEY = 'etapa2PopupSiguiente_{{ auth()->id() ?? 'usuario' }}';
            const INTERVALO_RECORDATORIO_POPUP = 2 * 60 * 60 * 1000;

            function consultarPendientesEtapa2(mostrarPopup) {
                return fetch("{{ url('/api/notificaciones/pendientes-etapa2') }}", {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.json();
                    })
                    .then(result => {
                        const total = Number(result.total) || 0;
                        const container = document.getElementById('demandas-toast-container');
                        if (!result.success || total === 0 || !container) return;

                        const modalList = document.getElementById('modal-etapa2-list');
                        const modalTotal = document.getElementById('modal-etapa2-total');
                        if (modalTotal) modalTotal.textContent = total;
                        if (modalList) {
                            modalList.replaceChildren();
                            Object.entries(result.data || {}).forEach(([sucursal, cantidad]) => {
                                const row = document.createElement('div');
                                row.className = 'flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors';
                                const name = document.createElement('span');
                                name.className = 'text-sm font-bold text-gray-700 uppercase';
                                name.textContent = sucursal;
                                const count = document.createElement('span');
                                count.className = 'text-xs font-bold text-red-600 bg-red-50 px-2.5 py-1 rounded-full border border-red-100 shadow-sm';
                                count.textContent = `${cantidad} pend.`;
                                row.append(name, count);
                                modalList.appendChild(row);
                            });
                        }

                        if (!mostrarPopup) return;

                        document.getElementById('etapa2-toast-resumen')?.remove();
                        const toast = document.createElement('div');
                        toast.id = 'etapa2-toast-resumen';
                        toast.style.cssText = 'order:2;background:white;border-left:5px solid #f97316;padding:16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:320px;max-width:460px;position:relative;opacity:0;transform:translateX(100%);transition:all .4s ease;cursor:pointer;';
                        toast.setAttribute('role', 'button');
                        toast.setAttribute('tabindex', '0');
                        toast.setAttribute('aria-label', `Ver ${total} pendientes de Etapa 2`);

                        const close = document.createElement('button');
                        close.type = 'button';
                        close.setAttribute('aria-label', 'Cerrar aviso');
                        close.style.cssText = 'position:absolute;top:10px;right:10px;background:transparent;border:0;font-size:20px;cursor:pointer;color:#9ca3af;';
                        close.innerHTML = '<i class="bx bx-x"></i>';
                        close.addEventListener('click', event => {
                            event.stopPropagation();
                            window.cerrarToastEtapa2();
                        });

                        const title = document.createElement('div');
                        title.style.cssText = 'font-weight:600;margin-bottom:5px;color:#9a3412;padding-right:15px;';
                        title.innerHTML = '<i class="bx bx-task"></i> Pendiente actualización Anual';
                        const message = document.createElement('div');
                        message.style.cssText = 'font-size:13px;color:#4b5563;line-height:1.4;';
                        message.textContent = `Hay ${total} personas pendientes de verificación. Haz clic para ver el detalle.`;
                        toast.append(close, title, message);
                        toast.addEventListener('click', () => {
                            document.getElementById('btn-open-modal-etapa2')?.click();
                            window.cerrarToastEtapa2();
                        });
                        toast.addEventListener('keydown', event => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                toast.click();
                            }
                        });
                        container.appendChild(toast);
                        setTimeout(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(0)'; }, 100);
                        setTimeout(() => window.cerrarToastEtapa2(), 10000);
                    })
                    .catch(error => console.error('Error revisando pendientes de Etapa 2:', error));
            }

            function consultarMayoriaEdad(mostrarPopup) {
                return fetch("{{ url('/api/notificaciones/hijos-por-cumplir-mayoria-edad') }}", {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                }).then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                }).then(result => {
                    const avisos = result.avisos || { '30': [], '15': [] };
                    const porSucursal = new Map();
                    ['30', '15'].forEach(dias => (avisos[dias] || []).forEach(persona => {
                        const sucursal = persona.sucursal || 'SIN SUCURSAL';
                        if (!porSucursal.has(sucursal)) porSucursal.set(sucursal, []);
                        porSucursal.get(sucursal).push({ ...persona, diasAviso: Number(dias) });
                    }));

                    const lista = document.getElementById('modal-mayoria-edad-list');
                    const total30 = document.getElementById('mayoria-edad-total-30');
                    const total15 = document.getElementById('mayoria-edad-total-15');
                    if (total30) total30.textContent = (avisos['30'] || []).length;
                    if (total15) total15.textContent = (avisos['15'] || []).length;
                    if (lista) {
                        lista.replaceChildren();
                        porSucursal.forEach((personas, sucursal) => {
                            const grupo = document.createElement('section');
                            const encabezado = document.createElement('div');
                            encabezado.className = 'sticky top-0 z-10 flex items-center justify-between bg-slate-50 px-3 py-2 border-b border-gray-100';
                            const nombreSucursal = document.createElement('strong');
                            nombreSucursal.className = 'text-sm text-gray-700 uppercase';
                            nombreSucursal.textContent = sucursal;
                            const cantidad = document.createElement('span');
                            cantidad.className = 'text-xs text-gray-500';
                            cantidad.textContent = `${personas.length} ${personas.length === 1 ? 'hijo' : 'hijos'}`;
                            encabezado.append(nombreSucursal, cantidad);
                            grupo.appendChild(encabezado);

                            personas.forEach(persona => {
                                const fila = document.createElement('div');
                                fila.className = 'px-3 py-3 border-b border-gray-100 last:border-b-0';
                                const titulo = document.createElement('div');
                                titulo.className = 'flex flex-wrap items-center gap-2';
                                const nombrePersonal = document.createElement('strong');
                                nombrePersonal.className = 'text-sm text-gray-800';
                                nombrePersonal.textContent = persona.personal || `Personal ${persona.codigo_personal}`;
                                const codigo = document.createElement('span');
                                codigo.className = 'text-xs text-gray-500';
                                codigo.textContent = `DNI ${persona.dni || '—'}`;
                                const etiqueta = document.createElement('span');
                                etiqueta.className = persona.diasAviso === 15
                                    ? 'rounded-full bg-orange-100 text-orange-700 px-2 py-0.5 text-[10px] font-bold'
                                    : 'rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 text-[10px] font-bold';
                                etiqueta.textContent = persona.diasAviso === 15 ? 'RECORDATORIO · 15 DÍAS O MENOS' : 'AVISO · HASTA 30 DÍAS';
                                titulo.append(nombrePersonal, codigo, etiqueta);
                                const detalle = document.createElement('p');
                                detalle.className = 'mt-1 text-xs text-gray-600';
                                detalle.textContent = `${persona.hijo || 'Hijo(a)'} · nació el ${persona.fecha_nacimiento} · cumple 18 años el ${persona.fecha_mayoria} · faltan ${persona.dias_restantes} días`;
                                fila.append(titulo, detalle);
                                grupo.appendChild(fila);
                            });
                            lista.appendChild(grupo);
                        });
                    }

                    const total = Number(result.total) || 0;
                    if (!mostrarPopup || total === 0) return;
                    const container = document.getElementById('demandas-toast-container');
                    if (!container) return;

                    const toast = document.createElement('div');
                    toast.id = 'mayoria-edad-toast-resumen';
                    toast.style.cssText = 'order:3;background:white;border-left:5px solid #f97316;padding:16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:320px;max-width:460px;position:relative;opacity:0;transform:translateX(100%);transition:all .4s ease;cursor:pointer;';
                    toast.setAttribute('role', 'button');
                    toast.setAttribute('tabindex', '0');
                    toast.setAttribute('aria-label', 'Ver detalle por sucursal de asignación familiar próxima a vencer');
                    const cerrar = document.createElement('button');
                    cerrar.type = 'button';
                    cerrar.setAttribute('aria-label', 'Cerrar aviso');
                    cerrar.style.cssText = 'position:absolute;top:10px;right:10px;background:transparent;border:0;font-size:20px;cursor:pointer;color:#9ca3af;';
                    cerrar.innerHTML = '<i class="bx bx-x"></i>';
                    cerrar.addEventListener('click', event => { event.stopPropagation(); toast.remove(); });
                    const titulo = document.createElement('div');
                    titulo.style.cssText = 'font-weight:600;margin-bottom:5px;color:#9a3412;padding-right:15px;';
                    titulo.innerHTML = '<i class="bx bx-child"></i> Asignación familiar próxima a vencer';
                    const mensaje = document.createElement('div');
                    mensaje.style.cssText = 'font-size:13px;color:#4b5563;line-height:1.4;';
                    mensaje.textContent = `${total} hijos próximos a cumplir 18 años. Aviso inicial (hasta 30 días): ${(avisos['30'] || []).length}; recordatorio (15 días o menos): ${(avisos['15'] || []).length}. Haz clic para ver el detalle por sucursal.`;
                    toast.append(cerrar, titulo, mensaje);
                    toast.addEventListener('click', () => {
                        document.getElementById('btn-open-modal-mayoria-edad')?.click();
                        toast.remove();
                    });
                    toast.addEventListener('keydown', event => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toast.click(); }
                    });
                    container.appendChild(toast);
                    setTimeout(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(0)'; }, 100);
                    setTimeout(() => toast.remove(), 10000);
                }).catch(error => console.error('Error revisando hijos próximos a cumplir 18 años:', error));
            }
            window.cargarDetalleMayoriaEdad = () => consultarMayoriaEdad(false);
            window.abrirDetalleMayoriaEdad = () => consultarMayoriaEdad(false).finally(() => {
                document.getElementById('btn-open-modal-mayoria-edad')?.click();
            });

            // El aviso de inicio de sesión conserva su comportamiento. Además,
            // se recuerda cada dos horas mientras la sesión siga abierta.
            if (@json($notifPopupsAutoshow)) {
                consultarPendientesEtapa2(true);
                consultarMayoriaEdad(true);
            }
            let siguienteRecordatorioEtapa2 = Number(sessionStorage.getItem(ETAPA2_RECORDATORIO_KEY)) || 0;
            if (!siguienteRecordatorioEtapa2) {
                siguienteRecordatorioEtapa2 = Date.now() + INTERVALO_RECORDATORIO_POPUP;
                sessionStorage.setItem(ETAPA2_RECORDATORIO_KEY, String(siguienteRecordatorioEtapa2));
            }
            function programarRecordatorioEtapa2() {
                window.setTimeout(function() {
                    consultarPendientesEtapa2(true).finally(function() {
                        siguienteRecordatorioEtapa2 = Date.now() + INTERVALO_RECORDATORIO_POPUP;
                        sessionStorage.setItem(ETAPA2_RECORDATORIO_KEY, String(siguienteRecordatorioEtapa2));
                        programarRecordatorioEtapa2();
                    });
                }, Math.max(1000, siguienteRecordatorioEtapa2 - Date.now()));
            }
            programarRecordatorioEtapa2();

            // 🔹 Solo una vez por login (y sin repetir en el mismo navegador)
            if (!@json($notifPopupsAutoshow) || sessionStorage.getItem("folioToastShown")) {
                return;
            }

            fetch("{{ route('notificaciones.foliosPorVencer') }}")
                .then(response => response.json())
                .then(data => {

                    if (data.length > 0) {

                        sessionStorage.setItem("folioToastShown", "true");

                        const total = data.length;
                        const toast = document.getElementById("folio-toast");

                        toast.innerHTML = `
                            <div style="font-weight:600; margin-bottom:5px;">
                                🔔 Notificación
                            </div>
                            <div style="font-size:14px;">
                                Hay <b>${total}</b> personas con folios a vencer en los próximos 10 días.
                            </div>
                            <div style="font-size:13px; margin-top:6px; opacity:0.8;">
                                Para más detalles puede revisarlo en la barra de notificaciones.
                            </div>
                        `;


                        toast.style.display = "block";

                        setTimeout(() => {
                            toast.style.opacity = "0";
                            setTimeout(() => {
                                toast.style.display = "none";
                            }, 500);
                        }, 6000);
                    }
                })
                .catch(error => console.error(error));
        });
    </script>

<script>
        document.addEventListener("DOMContentLoaded", function() {
            if (@json($ocultarNotificacionesDj)) return;
            // v3 invalida IDs que se marcaron como vistos por versiones anteriores
            // aunque el popup nunca se hubiese podido mostrar.
            const solicitudesVistasKey = `sipDjSolicitudesVistas_v3_{{ auth()->id() ?? 'usuario' }}`;
            const SIP_RECORDATORIO_KEY = `sipDjPopupSiguiente_{{ auth()->id() ?? 'usuario' }}`;
            const INTERVALO_RECORDATORIO_POPUP = 2 * 60 * 60 * 1000;
            let notificacionesMostradas = new Set();
            try {
                notificacionesMostradas = new Set(JSON.parse(sessionStorage.getItem(solicitudesVistasKey) || '[]').map(String));
            } catch (_) {}
            const demandasPorId = new Map();
            const listaNotificacionesSip = document.getElementById('notif-list');
            let primeraConsultaDemandas = true;
            let mostrarResumenRecordatorio = false;
            // El mismo flag de sesión que controla el popup de Pendientes Etapa 2:
            // al iniciar sesión debe avisar de todas las solicitudes SIP pendientes.
            let popupSolicitudAlInicio = @json($notifPopupsAutoshow);

            function guardarSolicitudesVistas() {
                try {
                    sessionStorage.setItem(solicitudesVistasKey, JSON.stringify([...notificacionesMostradas].slice(-300)));
                } catch (_) {}
            }

            function mostrarPopupResumenSolicitudesSip(demandas, container) {
                if (!container || !demandas.length) return false;

                // Evita duplicar el aviso si la primera consulta se reintenta.
                document.getElementById('demanda-toast-resumen')?.remove();
                const toast = document.createElement('div');
                toast.id = 'demanda-toast-resumen';
                toast.style.cssText = 'order:1;background:white;border-left:5px solid #3b82f6;padding:16px 42px 16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:320px;max-width:460px;position:relative;opacity:0;transform:translateX(100%);transition:all .4s ease;cursor:pointer;';
                toast.setAttribute('role', 'button');
                toast.setAttribute('tabindex', '0');
                toast.setAttribute('aria-label', 'Ver solicitudes de actualización DJ pendientes desde el SIP');
                toast.addEventListener('click', event => {
                    if (event.target.closest('button')) return;
                    window.abrirDetalleDemandaSip('grupo');
                });
                toast.addEventListener('keydown', event => {
                    if (event.target !== toast || (event.key !== 'Enter' && event.key !== ' ')) return;
                    event.preventDefault();
                    window.abrirDetalleDemandaSip('grupo');
                });

                const cerrar = document.createElement('button');
                cerrar.type = 'button';
                cerrar.setAttribute('aria-label', 'Cerrar aviso');
                cerrar.style.cssText = 'position:absolute;top:10px;right:10px;background:transparent;border:0;font-size:20px;cursor:pointer;color:#9ca3af;';
                cerrar.innerHTML = '<i class="bx bx-x"></i>';
                cerrar.addEventListener('click', () => window.cerrarToastSip('resumen'));

                const titulo = document.createElement('div');
                titulo.style.cssText = 'font-weight:600;margin-bottom:5px;color:#1e3a8a;';
                titulo.innerHTML = '<i class="bx bx-info-circle"></i> Solicitud de actualización desde el SIP';
                const mensaje = document.createElement('div');
                mensaje.style.cssText = 'font-size:13px;color:#4b5563;line-height:1.4;';
                if (demandas.length === 1) {
                    const nombre = demandas[0].personal || `personal ${demandas[0].codigoPers}`;
                    mensaje.textContent = `El usuario ${nombre} ha solicitado una actualización de su DJ a demanda mediante el SIP.`;
                } else {
                    mensaje.textContent = `Hay ${demandas.length} solicitudes pendientes de actualización de DJ desde el SIP. Haz clic para ver el detalle.`;
                }

                toast.append(cerrar, titulo, mensaje);
                container.appendChild(toast);
                requestAnimationFrame(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(0)'; });
                setTimeout(() => window.cerrarToastSip('resumen'), 10000);
                return true;
            }

            function mostrarPopupSolicitudSip(demanda) {
                const container = document.getElementById('demandas-toast-container');
                if (!container) return false;
                const id = String(demanda.id);
                const toast = document.createElement('div');
                toast.id = `demanda-toast-${id}`;
                toast.dataset.solicitudSipId = id;
                toast.style.cssText = 'order:1;background:white;border-left:5px solid #3b82f6;padding:16px 42px 16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:320px;max-width:460px;position:relative;opacity:0;transform:translateX(100%);transition:all .4s ease;cursor:pointer;';
                toast.setAttribute('role', 'button');
                toast.setAttribute('tabindex', '0');
                toast.setAttribute('aria-label', `Abrir solicitud de actualización DJ de ${demanda.personal || demanda.codigoPers}`);
                toast.addEventListener('click', event => {
                    if (event.target.closest('button')) return;
                    window.abrirDetalleDemandaSip(id);
                });
                toast.addEventListener('keydown', event => {
                    if (event.target !== toast || (event.key !== 'Enter' && event.key !== ' ')) return;
                    event.preventDefault();
                    window.abrirDetalleDemandaSip(id);
                });
                const cerrar = document.createElement('button');
                cerrar.type = 'button';
                cerrar.setAttribute('aria-label', 'Cerrar aviso');
                cerrar.style.cssText = 'position:absolute;top:10px;right:10px;background:transparent;border:0;font-size:20px;cursor:pointer;color:#9ca3af;';
                cerrar.innerHTML = '<i class="bx bx-x"></i>';
                cerrar.addEventListener('click', () => window.cerrarToastSip(id));
                const titulo = document.createElement('div');
                titulo.style.cssText = 'font-weight:600;margin-bottom:5px;color:#1e3a8a;';
                titulo.innerHTML = '<i class="bx bx-info-circle"></i> Solicitud de actualización DJ';
                const mensaje = document.createElement('div');
                mensaje.style.cssText = 'font-size:13px;color:#4b5563;line-height:1.4;';
                mensaje.textContent = `El usuario ${demanda.personal || demanda.codigoPers} ha solicitado una actualización de su DJ a demanda mediante el SIP.`;
                toast.append(cerrar, titulo, mensaje);
                container.appendChild(toast);
                requestAnimationFrame(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(0)'; });
                setTimeout(() => window.cerrarToastSip(id), 10000);
                return true;
            }

            window.abrirDetalleDemandaSip = function(id) {
                const demandas = [...demandasPorId.values()];
                if (!demandas.length) return;
                const lista = document.getElementById('detalle-solicitudes-sip-lista');
                document.getElementById('subtitulo-detalle-solicitud-sip').textContent =
                    `${demandas.length} ${demandas.length === 1 ? 'solicitud pendiente' : 'solicitudes pendientes'} de actualización desde el SIP.`;
                lista.replaceChildren();
                demandas.forEach(demanda => {
                    const tarjeta = document.createElement('article');
                    tarjeta.className = 'rounded-lg border border-slate-200 p-4 dark:border-slate-700';
                    const nombre = document.createElement('h3');
                    nombre.className = 'mb-3 font-semibold text-slate-900 dark:text-white';
                    nombre.textContent = demanda.personal || `Personal ${demanda.codigoPers}`;
                    const datos = document.createElement('dl');
                    datos.className = 'grid grid-cols-1 gap-3 sm:grid-cols-2';
                    [
                        ['Sucursal', demanda.sucursal],
                        ['Tipo de trabajador', demanda.tipo_trabajador],
                        ['Cargo', demanda.cargo],
                        ['DNI', demanda.dni],
                    ].forEach(([etiqueta, valor]) => {
                        const campo = document.createElement('div');
                        const titulo = document.createElement('dt');
                        titulo.className = 'text-xs font-medium uppercase tracking-wide text-slate-500';
                        titulo.textContent = etiqueta;
                        const contenido = document.createElement('dd');
                        contenido.className = 'mt-1 text-sm text-slate-800 dark:text-slate-200';
                        contenido.textContent = valor || '—';
                        campo.append(titulo, contenido);
                        datos.appendChild(campo);
                    });
                    const revisar = document.createElement('a');
                    revisar.className = 'mt-4 inline-flex items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700';
                    revisar.textContent = 'Revisar solicitud';
                    revisar.href = `/dj/actualizar_dj?tab=etapa2&solicitud_id=${encodeURIComponent(demanda.id)}`;
                    tarjeta.append(nombre, datos, revisar);
                    lista.appendChild(tarjeta);
                });
                const modal = document.getElementById('modal-detalle-solicitud-sip');
                const panel = document.getElementById('modal-detalle-solicitud-sip-panel');
                modal.dataset.open = 'true';
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                requestAnimationFrame(() => {
                    modal.classList.remove('opacity-0');
                    modal.classList.add('opacity-100');
                    panel?.classList.remove('opacity-0', 'translate-y-4');
                    panel?.classList.add('opacity-100', 'translate-y-0');
                });
            };

            window.cerrarDetalleDemandaSip = function() {
                const modal = document.getElementById('modal-detalle-solicitud-sip');
                const panel = document.getElementById('modal-detalle-solicitud-sip-panel');
                modal.dataset.open = 'false';
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                panel?.classList.remove('opacity-100', 'translate-y-0');
                panel?.classList.add('opacity-0', 'translate-y-4');
                setTimeout(() => {
                    if (modal.dataset.open === 'false') {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                }, 300);
            };

            document.getElementById('modal-detalle-solicitud-sip').addEventListener('click', event => {
                if (event.target.id === 'modal-detalle-solicitud-sip') window.cerrarDetalleDemandaSip();
            });
            // Delegamos los eventos porque notifications.js vuelve a dibujar
            // #notif-list periódicamente y reemplaza los nodos de cada solicitud.
            listaNotificacionesSip?.addEventListener('click', event => {
                const fila = event.target.closest('[data-demanda-sip-grupo]');
                if (!fila) return;
                window.abrirDetalleDemandaSip('grupo');
            });
            listaNotificacionesSip?.addEventListener('keydown', event => {
                const fila = event.target.closest('[data-demanda-sip-grupo]');
                if (!fila || (event.key !== 'Enter' && event.key !== ' ')) return;
                event.preventDefault();
                window.abrirDetalleDemandaSip('grupo');
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') window.cerrarDetalleDemandaSip();
            });

            function checkDemandasAdmin() {
                return fetch("{{ url('/api/notificaciones/demandas-admin') }}")
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data.length > 0) {
                            const container = document.getElementById("demandas-toast-container");
                            const notifList = document.getElementById("notif-list");
                            const badge = document.getElementById("notif-count");
                            
                            if (notifList && notifList.innerHTML.includes('Â¡Todo al día!')) {
                                notifList.innerHTML = '';
                            }

                            const nuevasSolicitudes = result.data.filter(demanda => !notificacionesMostradas.has(String(demanda.id)));
                            demandasPorId.clear();
                            result.data.forEach(demanda => {
                                demandasPorId.set(String(demanda.id), demanda);
                            });

                            // Una sola notificación en la campanita para todo el grupo SIP.
                            if (notifList) {
                                let itemGrupo = document.getElementById('campana-demanda-sip-grupo');
                                if (!itemGrupo) {
                                    itemGrupo = document.createElement('div');
                                    itemGrupo.id = 'campana-demanda-sip-grupo';
                                    itemGrupo.dataset.demandaSipGrupo = '1';
                                    itemGrupo.className = 'flex cursor-pointer items-center justify-between border-b border-gray-100 px-4 py-3 transition-colors hover:bg-slate-50';
                                    itemGrupo.setAttribute('role', 'button');
                                    itemGrupo.setAttribute('tabindex', '0');
                                    itemGrupo.innerHTML = `
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-md border border-blue-100 bg-blue-50 text-blue-600"><i class="bx bx-info-circle text-lg"></i></div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-gray-700">Solicitudes de actualización desde el SIP</span>
                                                <span class="text-[11px] text-gray-500 demanda-sip-resumen"></span>
                                            </div>
                                        </div>
                                        <span class="demanda-sip-total ml-3 flex h-6 min-w-6 items-center justify-center rounded-full bg-blue-100 px-2 text-xs font-bold text-blue-700"></span>
                                    `;
                                    notifList.prepend(itemGrupo);
                                }
                                itemGrupo.querySelector('.demanda-sip-resumen').textContent =
                                    `${result.data.length} ${result.data.length === 1 ? 'solicitud pendiente' : 'solicitudes pendientes'}. Haz clic para ver el detalle.`;
                                itemGrupo.querySelector('.demanda-sip-total').textContent = result.data.length;
                                if (badge) {
                                    const count = parseInt(badge.textContent) || 0;
                                    badge.textContent = Math.min(99, count + (itemGrupo.dataset.badgeCuenta === '1' ? 0 : 1));
                                    badge.classList.remove('hidden');
                                    itemGrupo.dataset.badgeCuenta = '1';
                                }
                            }

                            // Igual que Pendientes Etapa 2: mostrar las solicitudes SIP
                            // pendientes una vez al iniciar sesión, sin depender de si sus
                            // IDs ya se habían visto en una sesión anterior.
                            if (mostrarResumenRecordatorio && container) {
                                result.data.forEach(demanda => notificacionesMostradas.add(String(demanda.id)));
                                mostrarPopupResumenSolicitudesSip(result.data, container);
                                guardarSolicitudesVistas();
                                mostrarResumenRecordatorio = false;
                            } else if (primeraConsultaDemandas && popupSolicitudAlInicio && result.data.length && container) {
                                result.data.forEach(demanda => notificacionesMostradas.add(String(demanda.id)));
                                mostrarPopupResumenSolicitudesSip(result.data, container);
                                guardarSolicitudesVistas();
                            } else if (!primeraConsultaDemandas && nuevasSolicitudes.length) {
                                nuevasSolicitudes.forEach(demanda => {
                                    if (mostrarPopupSolicitudSip(demanda)) {
                                        notificacionesMostradas.add(String(demanda.id));
                                    }
                                });
                                guardarSolicitudesVistas();
                            }
                        } else if (result.success) {
                            demandasPorId.clear();
                            document.getElementById('campana-demanda-sip-grupo')?.remove();
                            mostrarResumenRecordatorio = false;
                        }

                        primeraConsultaDemandas = false;
                        popupSolicitudAlInicio = false;
                    })
                    .catch(error => console.error('Error revisando demandas del SIP:', error));
            }

            // Repite el resumen de solicitudes SIP pendientes cada dos horas.
            // sessionStorage conserva el horario al navegar entre pantallas.
            let siguienteRecordatorioSip = Number(sessionStorage.getItem(SIP_RECORDATORIO_KEY)) || 0;
            if (!siguienteRecordatorioSip) {
                siguienteRecordatorioSip = Date.now() + INTERVALO_RECORDATORIO_POPUP;
                sessionStorage.setItem(SIP_RECORDATORIO_KEY, String(siguienteRecordatorioSip));
            }
            function programarRecordatorioSolicitudesSip() {
                window.setTimeout(function() {
                    mostrarResumenRecordatorio = true;
                    checkDemandasAdmin().finally(function() {
                        siguienteRecordatorioSip = Date.now() + INTERVALO_RECORDATORIO_POPUP;
                        sessionStorage.setItem(SIP_RECORDATORIO_KEY, String(siguienteRecordatorioSip));
                        programarRecordatorioSolicitudesSip();
                    });
                }, Math.max(1000, siguienteRecordatorioSip - Date.now()));
            }
            programarRecordatorioSolicitudesSip();

            // ── CERRAR EL POP-UP: solo lo oculta en esta sesión ──
            // La notificación NO se borra: sigue en la campanita y volverá a
            // popupear en el PRÓXIMO LOGIN hasta que alguien la atienda desde
            // la campanita. Por eso NO se saca de `notificacionesMostradas`.
            window.cerrarToastSip = function(id) {
                const toast = document.getElementById(id === 'resumen' ? 'demanda-toast-resumen' : `demanda-toast-${id}`);
                if (!toast) return;

                toast.style.opacity = "0";
                toast.style.transform = "translateX(100%)";
                setTimeout(() => toast.remove(), 400);
            };

            window.cerrarToastEtapa2 = function() {
                const toast = document.getElementById('etapa2-toast-resumen');
                if (!toast) return;
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 400);
            };

            // ── ATENDER DESDE LA CAMPANITA: archiva la notificación ──
            // Pone habilitado = 0 en la BD (NO borra la fila, se conserva el
            // historial) y la quita del popup y de la campanita para siempre.
            window.borrarDemandaSip = function(id) {
                fetch("{{ url('/api/notificaciones/demandas-admin/borrar') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        window.cerrarToastSip(id);

                        const itemCampana = document.getElementById(`campana-demanda-${id}`);
                        if (itemCampana) {
                            itemCampana.remove();
                            const badge = document.getElementById("notif-count");
                            if(badge) {
                                let currentCount = parseInt(badge.textContent) || 0;
                                if (currentCount > 0) badge.textContent = currentCount - 1;
                            }
                        }
                        notificacionesMostradas.delete(id);
                    }
                })
                .catch(err => console.error('Error al atender la notificación', err));
            };

            setInterval(checkDemandasAdmin, 5000); 
            checkDemandasAdmin();
        });
    </script>


    @yield('script')

    @vite(['resources/js/app.js'])

</body>

</html>
