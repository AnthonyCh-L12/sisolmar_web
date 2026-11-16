/**
 * consultas.js
 * Lógica para el módulo de Consultas de Folios
 */

const ConsultaFolios = (() => {

    // ─── Estado ───────────────────────────────────────────────────────────────
    let tabActual = 'vigentes';
    let resultados = [];
    let personasAgrupadas = [];

    const POR_PAGINA = 15;
    let paginaActual = 1;

    let mapaClientesGlobal = {}; // Nuevo: Mapa para traducir códigos a nombres

    // ─── Config de tabs ───────────────────────────────────────────────────────
    const tabConfig = {
        vigentes: { clase: 'active-vigente' },
        pendientes: { clase: 'active-pendiente' },
        proximos: { clase: 'active-proximos' },
    };

    // ─── URLs de API ──────────────────────────────────────────────────────────
    const API = {
        vigentes: '/api/get-folios-vigentes',
        pendientes: '/api/get-folios-pendientes',
        proximos: '/api/folios/proximos-vencer',
        sucursales: '/api/sucursales-por-cliente',
        clientes: '/api/get-clientes',
        servicios: '/api/catalogos/servicios',
    };

    // ─── Inicialización ───────────────────────────────────────────────────────
    function init() {
        bindEventos();
        cargarCatalogos();
        // Mostrar el panel inicial correctamente
        mostrarPanelResultado('vigentes');
        actualizarVisibilidadStats('vigentes');
    }

    // ─── Cambiar Tab ──────────────────────────────────────────────────────────
    function cambiarTab(el) {
        const tab = el.dataset.tab;
        if (!tab || tab === tabActual) return;
        tabActual = tab;

        // Actualizar estilos de tabs
        document.querySelectorAll('.consulta-tab').forEach(btn => {
            btn.classList.remove('active-vigente', 'active-pendiente', 'active-proximos');
        });
        el.classList.add(tabConfig[tab].clase);

        // Mostrar panel de filtros
        document.querySelectorAll('.filtro-panel').forEach(p => p.classList.remove('active'));
        const panelFiltro = document.getElementById(`panel-${tab}`);
        if (panelFiltro) panelFiltro.classList.add('active');

        // Mostrar panel de resultados
        mostrarPanelResultado(tab);
        actualizarVisibilidadStats(tab);
    }

    function actualizarVisibilidadStats(tab) {
        const cards = {
            total: document.getElementById('stat-total')?.closest('.stat-consulta'),
            personas: document.getElementById('stat-personas')?.closest('.stat-consulta'),
            vigente: document.getElementById('stat-vigente')?.closest('.stat-consulta'),
            porVencer: document.getElementById('stat-por-vencer')?.closest('.stat-consulta'),
            vencido: document.getElementById('stat-vencido')?.closest('.stat-consulta'),
        };

        Object.values(cards).forEach(c => { if (c) c.style.display = 'none'; });

        if (tab === 'vigentes') {
            if (cards.total) cards.total.style.display = 'flex';
            if (cards.vigente) cards.vigente.style.display = 'flex';
        } else if (tab === 'pendientes') {
            if (cards.personas) cards.personas.style.display = 'flex';
            if (cards.vencido) cards.vencido.style.display = 'flex';
        } else if (tab === 'proximos') {
            if (cards.personas) cards.personas.style.display = 'flex';
            if (cards.porVencer) cards.porVencer.style.display = 'flex';
        }
    }

    function mostrarPanelResultado(tab) {
        document.querySelectorAll('.panel-resultado').forEach(p => p.classList.add('hidden'));
        const panelResultado = document.getElementById(`panel-${tab}-resultado`);
        if (panelResultado) panelResultado.classList.remove('hidden');
    }

    // ─── Toggle fechas personalizadas ─────────────────────────────────────────
    function toggleFechasCustom() {
        const val = document.getElementById('prox-periodo')?.value;
        const box = document.getElementById('prox-fechas-custom');
        if (box) box.style.display = val === 'custom' ? 'flex' : 'none';
    }

    // ─── Obtener filtros del panel activo ─────────────────────────────────────
    function obtenerFiltros() {
        const g = (id) => document.getElementById(id)?.value?.trim() || '';

        if (tabActual === 'vigentes') {
            return {
                tipo_folio: g('vig-tipo-folio'),
                prioridad: g('vig-prioridad'),
            };
        }

        if (tabActual === 'pendientes') {
            return {
                dni: g('pen-dni'),
                cliente: g('pen-cliente'),
                sucursal: g('pen-sucursal'),
            };
        }

        if (tabActual === 'proximos') {
            const periodo = g('prox-periodo') || '30';
            const filtros = {
                dni: g('prox-dni'),
                cliente: g('prox-cliente'),
                sucursal: g('prox-sucursal'),
                persona: g('prox-persona'),
                servicio: g('prox-servicio'),
                periodo,
            };
            if (periodo === 'custom') {
                filtros.fecha_desde = g('prox-fecha-desde');
                filtros.fecha_hasta = g('prox-fecha-hasta');
            }
            return filtros;
        }

        return {};
    }

    // ─── Buscar ───────────────────────────────────────────────────────────────
    function buscar() {
        const filtros = obtenerFiltros();
        mostrarLoading();

        const params = new URLSearchParams(filtros).toString();
        const url = `${API[tabActual]}?${params}`;

        fetch(url, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            }
        })
            .then(res => {
                if (!res.ok) throw new Error(`Error HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                resultados = data.data ?? data ?? [];
                paginaActual = 1;

                if (tabActual === 'vigentes') renderVigentes();
                if (tabActual === 'pendientes') renderPendientes();
                if (tabActual === 'proximos') renderProximos();

                actualizarStats();
            })
            .catch(err => {
                console.error('Error al buscar folios:', err);
                mostrarError(tabActual);
            });
    }

    // ─── Render: Vigentes ─────────────────────────────────────────────────────
    function renderVigentes() {
        const tbody = document.getElementById('tbody-vigentes');
        const badge = document.getElementById('badge-count-vigentes');

        if (resultados.length === 0) {
            tbody.innerHTML = emptyState('No se encontraron resultados', 5);
            if (badge) badge.textContent = '0 registros';
            renderPaginacion('vigentes', 0);
            return;
        }

        const total = resultados.length;
        const inicio = (paginaActual - 1) * POR_PAGINA;
        const fin = Math.min(inicio + POR_PAGINA, total);
        const pagina = resultados.slice(inicio, fin);

        tbody.innerHTML = pagina.map((item, i) => `
            <tr>
                <td class="text-center">${inicio + i + 1}</td>
                <td>${item.nombre ?? '—'}</td>
                <td class="text-center">${item.tipoFolio ?? '—'}</td>
                <td class="text-center">${item.prioridad === 'PRINCIPAL' ? 'OBLIGATORIO' : (item.prioridad ?? '—')}</td>
                <td class="text-center">${item.periodo ?? 'Sin vencimiento'}</td>
            </tr>
        `).join('');

        if (badge) badge.textContent = `${total} registros`;

        renderPaginacion('vigentes', total, inicio + 1, fin);
    }

    // ─── Paginación ───────────────────────────────────────────────────────────
    function renderPaginacion(tab, total, desde = 0, hasta = 0) {
        const infoEl = document.getElementById(`pag-info-${tab}`);
        const pagEl = document.getElementById(`pag-controles-${tab}`);
        if (!infoEl || !pagEl) return;

        const totalPags = Math.ceil(total / POR_PAGINA);

        // Info: "Mostrando X–Y de Z registros"
        infoEl.innerHTML = total > 0
            ? `Mostrando <strong>${desde}</strong>–<strong>${hasta}</strong> de <strong>${total}</strong> registros`
            : 'Sin resultados';

        if (totalPags <= 1) {
            pagEl.innerHTML = '';
            return;
        }

        // Calcular ventana de páginas visibles (máx 5 botones)
        const VENTANA = 5;
        let inicio = Math.max(1, paginaActual - Math.floor(VENTANA / 2));
        let fin = inicio + VENTANA - 1;
        if (fin > totalPags) {
            fin = totalPags;
            inicio = Math.max(1, fin - VENTANA + 1);
        }

        let html = '';

        // Botón anterior
        html += `
            <button class="pag-btn ${paginaActual === 1 ? 'disabled' : ''}"
                    ${paginaActual === 1 ? 'disabled' : ''}
                    onclick="ConsultaFolios.irPagina(${paginaActual - 1})">
                <i class="fa-solid fa-chevron-left"></i>
            </button>`;

        // Primera página + ellipsis
        if (inicio > 1) {
            html += `<button class="pag-btn" onclick="ConsultaFolios.irPagina(1)">1</button>`;
            if (inicio > 2) html += `<span class="pag-ellipsis">…</span>`;
        }

        // Páginas de la ventana
        for (let p = inicio; p <= fin; p++) {
            html += `
                <button class="pag-btn ${p === paginaActual ? 'active' : ''}"
                        onclick="ConsultaFolios.irPagina(${p})">
                    ${p}
                </button>`;
        }

        // Última página + ellipsis
        if (fin < totalPags) {
            if (fin < totalPags - 1) html += `<span class="pag-ellipsis">…</span>`;
            html += `<button class="pag-btn" onclick="ConsultaFolios.irPagina(${totalPags})">${totalPags}</button>`;
        }

        // Botón siguiente
        html += `
            <button class="pag-btn ${paginaActual === totalPags ? 'disabled' : ''}"
                    ${paginaActual === totalPags ? 'disabled' : ''}
                    onclick="ConsultaFolios.irPagina(${paginaActual + 1})">
                <i class="fa-solid fa-chevron-right"></i>
            </button>`;

        pagEl.innerHTML = html;
    }

    function irPagina(num) {
        const dataLength = tabActual === 'vigentes' ? resultados.length : personasAgrupadas.length;
        const totalPags = Math.ceil(dataLength / POR_PAGINA);
        if (num < 1 || num > totalPags) return;
        paginaActual = num;

        if (tabActual === 'vigentes') renderVigentes();
        else if (tabActual === 'pendientes') renderPendientes(false); // false para no reagrupar
        else if (tabActual === 'proximos') renderProximos(false);

        // Scroll suave al inicio de la tabla correspondiente
        const tablaId = tabActual === 'vigentes' ? 'tabla-vigentes' :
            (tabActual === 'pendientes' ? 'tabla-personas-pendientes' : 'tabla-personas-proximos');
        document.getElementById(tablaId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ─── Render: Pendientes ───────────────────────────────────────────────────
    function renderPendientes(reagrupar = true) {
        if (reagrupar) personasAgrupadas = agruparPorPersona(resultados);

        const tbody = document.getElementById('tbody-personas-pendientes');
        if (personasAgrupadas.length === 0) {
            tbody.innerHTML = emptyState('No se encontraron resultados', 5);
            limpiarDetalleTabla('tbody-documentos-pendientes', 'Selecciona una persona para ver el detalle');
            renderPaginacion('pendientes', 0);
            return;
        }

        const total = personasAgrupadas.length;
        const inicio = (paginaActual - 1) * POR_PAGINA;
        const fin = Math.min(inicio + POR_PAGINA, total);
        const pagina = personasAgrupadas.slice(inicio, fin);

        tbody.innerHTML = pagina.map(p => `
            <tr style="cursor:pointer;" onclick="ConsultaFolios.seleccionarPersona('${p.id}', 'pendientes')">
                <td>${p.nombre ?? '—'}</td>
                <td class="text-center">${p.dni ?? '—'}</td>
                <td class="text-center text-xs">${p.cliente ?? '—'}</td>
                <td class="text-center text-xs">${p.sucursal ?? '—'}</td>
                <td class="text-center">${p.documentos.length}</td>
            </tr>
        `).join('');

        limpiarDetalleTabla('tbody-documentos-pendientes', 'Selecciona una persona para ver el detalle');
        renderPaginacion('pendientes', total, inicio + 1, fin);
    }

    // ─── Render: Próximos a vencer ────────────────────────────────────────────
    function renderProximos(reagrupar = true) {
        if (reagrupar) personasAgrupadas = agruparPorPersona(resultados);

        const tbody = document.getElementById('tbody-personas-proximos');
        if (personasAgrupadas.length === 0) {
            tbody.innerHTML = emptyState('No se encontraron resultados', 5);
            limpiarDetalleTabla('tbody-documentos-proximos', 'Selecciona una persona para ver el detalle');
            renderPaginacion('proximos', 0);
            return;
        }

        const total = personasAgrupadas.length;
        const inicio = (paginaActual - 1) * POR_PAGINA;
        const fin = Math.min(inicio + POR_PAGINA, total);
        const pagina = personasAgrupadas.slice(inicio, fin);

        tbody.innerHTML = pagina.map(p => `
            <tr style="cursor:pointer;" onclick="ConsultaFolios.seleccionarPersona('${p.id}', 'proximos')">
                <td>${p.nombre ?? '—'}</td>
                <td class="text-center">${p.dni ?? '—'}</td>
                <td class="text-center text-xs">${p.cliente ?? '—'}</td>
                <td class="text-center text-xs">${p.sucursal ?? '—'}</td>
                <td class="text-center">${p.documentos.length}</td>
            </tr>
        `).join('');

        limpiarDetalleTabla('tbody-documentos-proximos', 'Selecciona una persona para ver el detalle');
        renderPaginacion('proximos', total, inicio + 1, fin);
    }

    // ─── Seleccionar persona (detalle de documentos) ──────────────────────────
    function seleccionarPersona(id, tipo) {
        const persona = personasAgrupadas.find(p => p.id === String(id));
        if (!persona) return;

        const tbodyId = `tbody-documentos-${tipo}`;
        const tituloId = `titulo-detalle-${tipo}`;
        const tbody = document.getElementById(tbodyId);
        const titulo = document.getElementById(tituloId);

        if (titulo) titulo.textContent = `Documentos de ${persona.nombre}`;

        // Resaltar fila seleccionada
        const tabla = tipo === 'pendientes'
            ? document.getElementById('tbody-personas-pendientes')
            : document.getElementById('tbody-personas-proximos');

        tabla?.querySelectorAll('tr').forEach(tr => tr.classList.remove('bg-blue-50'));
        tabla?.querySelectorAll('tr').forEach(tr => {
            if (tr.textContent.includes(id)) tr.classList.add('bg-blue-50');
        });

        if (!tbody) return;

        tbody.innerHTML = persona.documentos.map(doc => `
            <tr>
                <td>${doc.documento ?? '—'}</td>
                <td class="text-center">${doc.tipo_folio ?? '—'}</td>
                <td class="text-center">${doc.prioridad === 'PRINCIPAL' || (!doc.prioridad && doc.tipo_folio === 'PRINCIPAL') ? 'OBLIGATORIO' : (doc.prioridad ?? 'NORMAL')}</td>
                <td class="text-center">${formatFecha(doc.fecha_caducidad)}</td>
                <td class="text-center">${getBadgeEstado(doc)}</td>
            </tr>
        `).join('');
    }

    // ─── Agrupar por persona ──────────────────────────────────────────────────
    function agruparPorPersona(data) {
        const mapa = {};
        data.forEach(item => {
            // Usar codPersonal como ID interno para selección (es único)
            const idKey = item.codPersonal;
            if (!mapa[idKey]) {
                mapa[idKey] = {
                    id: idKey,
                    dni: item.dni ?? '—',
                    nombre: item.personal ?? '—',
                    cliente: traducirCliente(item.cliente),
                    sucursal: item.sucursal,
                    documentos: [],
                };
            }
            mapa[idKey].documentos.push(item);
        });
        return Object.values(mapa);
    }

    // ─── Stats ────────────────────────────────────────────────────────────────
    function actualizarStats() {
        const total = resultados.length; // Total Folios
        const totalPers = personasAgrupadas.length; // Total Personas únicas

        const vig = resultados.filter(r => norm(r.estado) === 'vigente').length;
        const porVenc = resultados.filter(r => norm(r.estado) === 'por vencer').length;
        const venc = resultados.filter(r => {
            const est = r.pendiente == 1 ? 'pendiente' : norm(r.estado);
            return ['vencido', 'pendiente', 'falta'].includes(est);
        }).length;

        setText('stat-total', total);
        setText('stat-personas', totalPers);
        setText('stat-vigente', vig);
        setText('stat-por-vencer', porVenc);
        setText('stat-vencido', venc);
    }
    //
    // ─── Limpiar ──────────────────────────────────────────────────────────────
    function limpiar() {
        const panel = document.getElementById(`panel-${tabActual}`);
        if (panel) {
            panel.querySelectorAll('input, select').forEach(el => {
                el.tagName === 'SELECT' ? (el.selectedIndex = 0) : (el.value = '');
            });
        }

        // Restaurar período por defecto
        if (tabActual === 'proximos') {
            const p = document.getElementById('prox-periodo');
            if (p) p.value = '30';
            const f = document.getElementById('prox-fechas-custom');
            if (f) f.style.display = 'none';
        }

        resultados = [];
        personasAgrupadas = [];
        paginaActual = 1;

        // Restaurar tablas vacías según tab
        if (tabActual === 'vigentes') {
            const tb = document.getElementById('tbody-vigentes');
            if (tb) tb.innerHTML = emptyState('Selecciona filtros y haz clic en Buscar', 5);
            const b = document.getElementById('badge-count-vigentes');
            if (b) b.textContent = '0 registros';
        }

        if (tabActual === 'pendientes') {
            const tb = document.getElementById('tbody-personas-pendientes');
            if (tb) tb.innerHTML = emptyState('Realiza una búsqueda para ver resultados', 5);
            limpiarDetalleTabla('tbody-documentos-pendientes', 'Selecciona una persona para ver el detalle');
        }

        if (tabActual === 'proximos') {
            const tb = document.getElementById('tbody-personas-proximos');
            if (tb) tb.innerHTML = emptyState('Realiza una búsqueda para ver resultados', 5);
            limpiarDetalleTabla('tbody-documentos-proximos', 'Selecciona una persona para ver el detalle');
        }

        setText('stat-total', '—');
        setText('stat-vigente', '—');
        setText('stat-por-vencer', '—');
        setText('stat-vencido', '—');
    }

    // ─── Loading ──────────────────────────────────────────────────────────────
    function mostrarLoading() {
        const spinner = `
            <tr>
                <td colspan="5" class="text-center py-8">
                    <div class="spinner"></div> Cargando...
                </td>
            </tr>`;

        if (tabActual === 'vigentes') {
            setText2('tbody-vigentes', spinner);
        }
        if (tabActual === 'pendientes') {
            setText2('tbody-personas-pendientes', spinner);
            limpiarDetalleTabla('tbody-documentos-pendientes', 'Esperando selección de persona...');
        }
        if (tabActual === 'proximos') {
            setText2('tbody-personas-proximos', spinner);
            limpiarDetalleTabla('tbody-documentos-proximos', 'Esperando selección de persona...');
        }
    }

    function mostrarError(tab) {
        const html = `
            <tr>
                <td colspan="5">
                    <div class="empty-state-consulta">
                        <i class="fa-solid fa-circle-exclamation text-red-400"></i>
                        <p class="text-red-500">No se pudo obtener los resultados. Intenta nuevamente.</p>
                    </div>
                </td>
            </tr>`;

        if (tab === 'vigentes') setText2('tbody-vigentes', html);
        if (tab === 'pendientes') setText2('tbody-personas-pendientes', html);
        if (tab === 'proximos') setText2('tbody-personas-proximos', html);
    }

    // ─── Exportar CSV ─────────────────────────────────────────────────────────
    function exportarCSV() {
        if (resultados.length === 0) {
            alert('No hay datos para exportar. Realiza una búsqueda primero.');
            return;
        }
        const filtros = obtenerFiltros();
        const params = new URLSearchParams({ ...filtros, tab: tabActual, formato: 'csv' }).toString();
        window.open(`${API[tabActual]}?${params}`, '_blank');
    }

    // ─── Badges ───────────────────────────────────────────────────────────────
    function getBadgeEstado(item) {
        const mapa = {
            'vigente': ['badge-vigente', 'Vigente'],
            'por vencer': ['badge-por-vencer', 'Por Vencer'],
            'vencido': ['badge-vencido', 'Vencido'],
            'pendiente': ['badge-pendiente', 'Pendiente'],
            'falta': ['badge-falta', 'Falta Entregar'],
            'revision': ['badge-revision', 'En Revisión'],
            'recibido': ['badge-recibido', 'Recibido'],
        };
        const estado = item.pendiente == 1 ? 'pendiente' : norm(item.estado);
        let [cls, label] = mapa[estado] ?? ['badge-pendiente', item.estado ?? 'Pendiente'];

        // Dinámicamente calcular los días si es "por vencer"
        if (estado === 'por vencer' && item.fecha_caducidad) {
            try {
                const soloFecha = String(item.fecha_caducidad).split(' ')[0].split('T')[0];
                const d = new Date(soloFecha + 'T00:00:00');
                if (!isNaN(d.getTime())) {
                    const hoy = new Date();
                    hoy.setHours(0, 0, 0, 0);

                    const diffTime = d.getTime() - hoy.getTime();
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    if (diffDays >= 0) {
                        label = `Por vencer en ${diffDays} día${diffDays !== 1 ? 's' : ''}`;
                    } else {
                        label = 'Vencido';
                        cls = 'badge-vencido';
                    }
                }
            } catch (e) {
                // Ignore, keep default label
            }
        }

        return `<span class="badge-estado ${cls}">${label}</span>`;
    }

    // ─── Catálogos ────────────────────────────────────────────────────────────
    function cargarCatalogos() {
        fetch(API.clientes, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(data => {
                const clientes = data.data ?? data ?? [];

                // Poblamos el mapa global
                clientes.forEach(c => {
                    if (c.cod_legacy) {
                        const cod = String(c.cod_legacy).trim();
                        mapaClientesGlobal[cod] = c.abreviatura;
                    }
                });

                const opts = clientes.map(c =>
                    `<option value="${c.cod_legacy}">${c.abreviatura}</option>`
                ).join('');
                ['pen-cliente', 'prox-cliente'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.innerHTML += opts;
                });

                // Si ya había resultados (race condition), re-renderizamos para que se vean los nombres
                if (resultados.length > 0) {
                    if (tabActual === 'pendientes') renderPendientes();
                    if (tabActual === 'proximos') renderProximos();
                }
            }).catch(() => { });

        fetch(API.servicios, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(data => {
                const opts = (data.data ?? data ?? []).map(s =>
                    `<option value="${s.id}">${s.codigo} – ${s.nombre}</option>`
                ).join('');
                ['prox-servicio'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.innerHTML += opts;
                });
            }).catch(() => { });
    }

    // ─── Bind eventos ─────────────────────────────────────────────────────────
    function bindEventos() {

        // Tabs
        document.querySelectorAll('.consulta-tab').forEach(btn => {
            btn.addEventListener('click', function () { cambiarTab(this); });
        });

        // Buscar / Limpiar
        document.getElementById('btn-buscar')?.addEventListener('click', buscar);
        document.getElementById('btn-limpiar')?.addEventListener('click', limpiar);

        // Exportar
        document.getElementById('btn-exportar-csv')?.addEventListener('click', exportarCSV);

        // Período personalizado
        document.getElementById('prox-periodo')?.addEventListener('change', toggleFechasCustom);

        // Carga dinámica de sucursales al cambiar cliente
        ['pen', 'prox'].forEach(prefix => {
            const clienteSelect = document.getElementById(`${prefix}-cliente`);
            const sucursalSelect = document.getElementById(`${prefix}-sucursal`);
            if (!clienteSelect || !sucursalSelect) return;

            clienteSelect.addEventListener('change', function () {
                const codLegacy = this.value;
                sucursalSelect.innerHTML = '<option value="">Cargando...</option>';

                if (!codLegacy) {
                    sucursalSelect.innerHTML = '<option value="">Todas las sucursales</option>';
                    return;
                }

                fetch(`${API.sucursales}?cod_legacy=${codLegacy}`, {
                    headers: { Accept: 'application/json' }
                })
                    .then(r => r.json())
                    .then(data => {
                        const opts = (data.data ?? data ?? []).map(s =>
                            `<option value="${s.codigo_sucursal}">${s.nombre_sucursal}</option>`
                        ).join('');
                        sucursalSelect.innerHTML = '<option value="">Todas las sucursales</option>' + opts;
                    })
                    .catch(() => {
                        sucursalSelect.innerHTML = '<option value="">Error al cargar</option>';
                    });
            });
        });

        // Modal: cerrar al hacer clic en el fondo o botón
        document.getElementById('modal-detalle')?.addEventListener('click', function (e) {
            if (e.target === this) cerrarModal();
        });
        document.getElementById('btn-cerrar-modal')?.addEventListener('click', cerrarModal);

        // Búsqueda rápida en tabla vigentes
        document.getElementById('busqueda-rapida-vigentes')?.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            const tbody = document.getElementById('tbody-vigentes');
            if (!tbody) return;

            tbody.querySelectorAll('tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ─── Modal detalle ────────────────────────────────────────────────────────
    function cerrarModal() {
        document.getElementById('modal-detalle')?.classList.remove('show');
    }//

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function norm(val) { return (val ?? '').toLowerCase().trim(); }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function traducirCliente(val) {
        if (!val || val === '—') return '—';
        const limpio = String(val).trim();

        // 1. Intento directo en el mapa
        if (mapaClientesGlobal[limpio]) return mapaClientesGlobal[limpio];

        // 2. Normalización numérica (ej. 01 -> 1)
        const num = parseInt(limpio, 10);
        if (!isNaN(num)) {
            const numStr = String(num);
            if (mapaClientesGlobal[numStr]) return mapaClientesGlobal[numStr];

            // Caso especial: 1 -> 01
            const padded = num < 10 ? '0' + num : numStr;
            if (mapaClientesGlobal[padded]) return mapaClientesGlobal[padded];
        }

        // 3. Fallback: Devolver original si no se pudo traducir
        return val;
    }

    function setText2(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function limpiarDetalleTabla(tbodyId, mensaje) {
        setText2(tbodyId, `
            <tr>
                <td colspan="5">
                    <div class="empty-state-consulta">
                        <p>${mensaje}</p>
                    </div>
                </td>
            </tr>
        `);
    }

    function emptyState(mensaje, cols = 5) {
        return `
            <tr>
                <td colspan="${cols}">
                    <div class="empty-state-consulta">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <p>${mensaje}</p>
                    </div>
                </td>
            </tr>`;
    }

    function formatFecha(fecha) {
        if (!fecha || String(fecha).trim() === '') return '<span class="text-orange-500 font-bold">SIN REGISTRO</span>';
        try {
            // Si viene con tiempo (espacio), solo tomamos la fecha
            const soloFecha = String(fecha).split(' ')[0].split('T')[0];
            const d = new Date(soloFecha + 'T00:00:00');

            if (isNaN(d.getTime())) {
                return '<span class="text-orange-500 font-bold">SIN REGISTRO</span>';
            }

            return d.toLocaleDateString('es-PE', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            });
        } catch {
            return '<span class="text-orange-500 font-bold">SIN REGISTRO</span>';
        }
    }

    // ─── API pública ──────────────────────────────────────────────────────────
    return {
        init,
        cambiarTab,
        buscar,
        limpiar,
        exportarCSV,
        seleccionarPersona,
        cerrarModal,
        toggleFechasCustom,
        irPagina,
    };

})();

document.addEventListener('DOMContentLoaded', () => ConsultaFolios.init());

window.ConsultaFolios = ConsultaFolios;
