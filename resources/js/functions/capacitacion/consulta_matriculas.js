import { TabulatorFull as Tabulator } from "tabulator-tables";
import "tabulator-tables/dist/css/tabulator_simple.min.css";

import axios from "axios";
import Alpine from "alpinejs";

axios.defaults.withCredentials = true;
axios.defaults.headers.common["X-CSRF-TOKEN"] = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");

document.addEventListener("DOMContentLoaded", () => {
    const esLocale = {
        pagination: {
            page_size: "Filas por página",
            first: "Primero",
            last: "Último",
            prev: "Anterior",
            next: "Siguiente",
            counter: {
                showing: "Mostrando",
                of: "de",
                rows: "filas",
                pages: "páginas",
            },
        },
        data: {
            loading: "Cargando...",
            error: "Error al cargar los datos",
        },
    };

    function setRowHoverEffect(
        row,
        color = "rgba(99, 102, 241, 0.05)",
        accent = "#6366f1",
    ) {
        const el = row.getElement();
        el.style.cursor = "pointer";
        el.style.transition = "all .15s ease";

        el.addEventListener("mouseenter", () => {
            el.style.background = color;
            el.style.boxShadow = `inset 3px 0 0 ${accent}`;
        });

        el.addEventListener("mouseleave", () => {
            el.style.background = "";
            el.style.boxShadow = "";
        });
    }

    function getInitials(fullName = "") {
        return fullName
            .split(" ")
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0] || "")
            .join("")
            .toUpperCase();
    }

    function formatDateTime(value, locale = "es-PE") {
        if (!value) return { fecha: "—", hora: "—" };

        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return { fecha: "—", hora: "—" };

        return {
            fecha: d.toLocaleDateString(locale, {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            }),
            hora: d.toLocaleTimeString(locale, {
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            }),
        };
    }

    function fillSelect(id, values, label) {
        const select = document.getElementById(id);
        if (!select) return;
        select.innerHTML = "";
        if (label) {
            const def = document.createElement("option");
            def.value = "";
            def.textContent = label;
            select.appendChild(def);
        }
        values.forEach((val) => {
            const opt = document.createElement("option");
            opt.value = val;
            opt.textContent = val;
            select.appendChild(opt);
        });
    }

    let _cursosFullData = [];

    function recargarListadoCursos() {
        if (
            window.tabulatorCursos &&
            typeof window.tabulatorCursos.replaceData === "function"
        ) {
            window.tabulatorCursos.replaceData();
        }
    }

    // Fecha_Creacion llega como timestamp Unix (segundos).
    function anioDeCurso(data) {
        if (!data?.Fecha_Creacion) return "";
        const d = new Date(Number(data.Fecha_Creacion) * 1000);
        return Number.isNaN(d.getTime()) ? "" : String(d.getFullYear());
    }

    function populateFilters(data) {
        _cursosFullData = data;
        const unique = (field) =>
            [...new Set(data.map((d) => d[field]).filter(Boolean))].sort();

        const anios = [...new Set(data.map(anioDeCurso).filter(Boolean))]
            .sort()
            .reverse();
        const selectAnio = document.getElementById("filtroAnioCurso");
        const anioActual = selectAnio?.value || "";

        fillSelect("filtroTipoCurso", unique("Tipo"));
        fillSelect("filtroAreaCurso", unique("Area"), "Todas las áreas");
        fillSelect("filtroSistemaCurso", unique("Sistema"), "Todos los sistemas");
        fillSelect("filtroJefaturaCurso", unique("Responsable"), "Todas las jefaturas");
        fillSelect("filtroClienteCurso", unique("Cliente"), "Todos los clientes");
        fillSelect("filtroAnioCurso", anios, "Todos los años");

        if (selectAnio && anioActual && anios.includes(anioActual)) {
            selectAnio.value = anioActual;
        }
    }

    function repopulateSecondaryFilters(tipo) {
        const filtered = tipo
            ? _cursosFullData.filter(d => d.Tipo === tipo)
            : _cursosFullData;
        const unique = (field) =>
            [...new Set(filtered.map((d) => d[field]).filter(Boolean))].sort();

        document.getElementById("filtroAreaCurso").value = "";
        document.getElementById("filtroJefaturaCurso").value = "";
        document.getElementById("filtroSistemaCurso").value = "";
        document.getElementById("filtroClienteCurso").value = "";

        fillSelect("filtroAreaCurso", unique("Area"), "Todas las áreas");
        fillSelect("filtroSistemaCurso", unique("Sistema"), "Todos los sistemas");
        fillSelect("filtroJefaturaCurso", unique("Responsable"), "Todas las jefaturas");
        fillSelect("filtroClienteCurso", unique("Cliente"), "Todos los clientes");
    }

    function applyFilters(table) {
        const nombre = document.getElementById("txtBuscarCurso")?.value?.trim();
        const tipo = document.getElementById("filtroTipoCurso")?.value;
        const area = document.getElementById("filtroAreaCurso")?.value;
        const sistema = document.getElementById("filtroSistemaCurso")?.value;
        const jefatura = document.getElementById("filtroJefaturaCurso")?.value;
        const cliente = document.getElementById("filtroClienteCurso")?.value;
        const anio = document.getElementById("filtroAnioCurso")?.value;
        const filters = [];

        if (nombre)
            filters.push({ field: "Nombre", type: "like", value: nombre });
        if (tipo) filters.push({ field: "Tipo", type: "=", value: tipo });
        if (area) filters.push({ field: "Area", type: "=", value: area });
        if (sistema)
            filters.push({ field: "Sistema", type: "=", value: sistema });
        if (jefatura)
            filters.push({ field: "Responsable", type: "=", value: jefatura });
        if (cliente)
            filters.push({ field: "Cliente", type: "=", value: cliente });

        table.clearFilter();
        if (filters.length) table.setFilter(filters);

        if (anio) {
            table.addFilter((data) => anioDeCurso(data) === anio);
        }
    }

    function toggleSecondaryFilters(tipo) {
        const sistemaContainer = document.getElementById("filtroSistemaCursoContainer");
        const clienteContainer = document.getElementById("filtroClienteCursoContainer");

        if (!sistemaContainer || !clienteContainer) return;

        if (tipo === "PCA") {
            sistemaContainer.style.display = "none";
            clienteContainer.style.display = "";
            document.getElementById("filtroSistemaCurso").value = "";
        } else if (tipo === "PCI") {
            sistemaContainer.style.display = "";
            clienteContainer.style.display = "";
        } else {
            sistemaContainer.style.display = "";
            clienteContainer.style.display = "none";
            document.getElementById("filtroClienteCurso").value = "";
        }
    }

    function clearFilters(table) {
        [
            "filtroTipoCurso",
            "filtroAreaCurso",
            "filtroSistemaCurso",
            "filtroJefaturaCurso",
            "filtroClienteCurso",
            "filtroAnioCurso",
        ].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = "";
        });
        const busquedaEl = document.getElementById("txtBuscarCurso");
        if (busquedaEl) busquedaEl.value = "";
        document.getElementById("btnLimpiarBusquedaCurso")?.classList.add("hidden");
        const tipoEl = document.getElementById("filtroTipoCurso");
        if (tipoEl) {
            tipoEl.value = "PCE";
            toggleSecondaryFilters("PCE");
            repopulateSecondaryFilters("PCE");
        }
        table.clearFilter();
    }

    function initTableCursos() {
        const table = new Tabulator("#tblCursos", {
            ajaxURL: `${VITE_URL_APP}/api/obtener-cursos-matriculas-new`,
            ajaxResponse: function (url, params, response) {
                return response.Cursos;
            },
            layout: "fitDataFill",
            placeholder: "No hay cursos disponibles...",
            pagination: "local",
            paginationSize: 5,
            paginationSizeSelector: [5, 10, 15, 20],
            paginationCounter: "rows",
            locale: "es-es",
            langs: { "es-es": esLocale },

            rowFormatter(row) {
                setRowHoverEffect(row);
            },

            dataLoaded(data) {
                populateFilters(data);
            },

            columns: [
                {
                    title: "Código",
                    field: "Codigo",
                    width: 100,
                    visible: false,
                },
                {
                    title: "Nombre de curso",
                    field: "Nombre",
                    minWidth: 280,
                    formatter(cell) {
                        const val = cell.getValue();
                        return `<span style="font-weight:500; color:#111827;">${val ?? "—"}</span>`;
                    },
                },
                {
                    title: "Responsable",
                    field: "Responsable",
                    width: 280,
                    headerSort: true,
                    formatter(cell) {
                        const val = cell.getValue();
                        if (!val) return "—";
                        const initials = getInitials(val);
                        return `
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="width:28px; height:28px; border-radius:50%; background:#e0e7ff; color:#4338ca; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">${initials}</div>
                            <span style="font-size:13px; color:#374151;">${val}</span>
                        </div>
                    `;
                    },
                },
                {
                    title: "Matriculados",
                    field: "Total_Matriculados",
                    width: 130,
                    hozAlign: "center",
                    headerSort: true,
                    visible: false,
                    formatter(cell) {
                        const val = cell.getValue() ?? 0;
                        return `
                        <div style="display:flex; align-items:center; justify-content:center; gap:5px;">
                            <i class="ti ti-users" style="font-size:13px; color:#6366f1;"></i>
                            <span style="font-weight:600; color:#111827;">${val}</span>
                        </div>
                    `;
                    },
                },
                {
                    title: "Creación",
                    field: "Fecha_Creacion",
                    width: 130,
                    hozAlign: "center",
                    headerSort: true,
                    formatter(cell) {
                        const { fecha, hora } = formatDateTime(
                            cell.getValue() * 1000,
                        ); // ← * 1000
                        if (fecha === "—") return "—";
                        return `
            <div style="display:flex; flex-direction:column; align-items:center; line-height:1.4;">
                <span style="font-size:12px; font-weight:600; color:#374151;">${fecha}</span>
                <span style="font-size:11px; color:#9ca3af;">${hora}</span>
            </div>`;
                    },
                },
                {
                    title: "Acciones",
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerSort: false,
                    formatter(cell) {
                        const sinVigente = !cell.getData()?.Vigente;
                        const bloqueado = sinVigente ? 'disabled title="El curso no tiene una programación vigente"' : '';
                        const estiloBloqueado = sinVigente ? 'opacity:.45; cursor:not-allowed;' : 'cursor:pointer;';
                        return `
                        <div style="display:flex; gap:6px; justify-content:center;">
                            <button class="btn-matricular-curso" ${bloqueado} style="
                                background: #4f46e5;
                                color: white;
                                border: none;
                                border-radius: 6px;
                                padding: 6px 12px;
                                font-size: 12px;
                                font-weight: 500;
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                transition: all 0.2s;
                                ${estiloBloqueado}
                            ">
                                <i class="ti ti-user-plus" style="font-size: 14px;"></i>
                                Matricular
                            </button>
                            <button class="btn-desmatricular-curso" ${bloqueado} style="
                                background: #fff;
                                color: #dc2626;
                                border: 1px solid #fecaca;
                                border-radius: 6px;
                                padding: 6px 12px;
                                font-size: 12px;
                                font-weight: 500;
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                transition: all 0.2s;
                                ${estiloBloqueado}
                            ">
                                <i class="ti ti-user-minus" style="font-size: 14px;"></i>
                                Desmatricular
                            </button>
                        </div>
                    `;
                    },
                    cellClick: async function (e, cell) {
                        const btnMat = e.target?.closest?.('.btn-matricular-curso');
                        const btnDes = e.target?.closest?.('.btn-desmatricular-curso');
                        if (!btnMat && !btnDes) return;
                        e.stopPropagation();
                        const data = cell.getRow().getData();

                        // Bloqueo preventivo: sin vigente no se opera
                        if (!data?.Vigente) return;

                        // Desmatricular abre el modal de participantes inscritos en Moodle
                        if (btnDes) {
                            const modalParticipantesEl = document.getElementById(
                                "modal-participantes-moodle",
                            );
                            const componenteParticipantes =
                                modalParticipantesEl?._x_dataStack?.[0];

                            if (!componenteParticipantes?.abrir) {
                                console.warn(
                                    "No se encontró el componente Alpine del modal de participantes",
                                );
                                return;
                            }

                            componenteParticipantes.abrir(data);
                            return;
                        }

                        window._activeTab = 'por-matricular';

                        const modalEl = document.getElementById(
                            "modal-lista-matriculados",
                        );
                        const alpineComponent = modalEl?._x_dataStack?.[0];

                        if (!alpineComponent?.mostrar) {
                            console.warn(
                                "No se encontró el componente Alpine del modal",
                            );
                            return;
                        }

                        // Abrir el modal de inmediato con loader animado
                        alpineComponent.mostrar(data);

                        // Resolver la programación vigente primero
                        let progVigente = null;
                        try {
                            const progResp = await axios.get(`${VITE_URL_APP}/api/cursos/obtener-prog-actual/${data.LocalId}`);
                            progVigente = progResp.data?.success ? progResp.data?.data : null;
                        } catch (err) {
                            console.error(err);
                            alpineComponent.cerrar();
                            await Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo verificar la programación del curso.', confirmButtonColor: '#6366f1' });
                            return;
                        }

                        if (!progVigente || String(progVigente.estado_periodo || '').toUpperCase() !== 'VIGENTE') {
                            alpineComponent.cerrar();
                            await Swal.fire({ icon: 'warning', title: 'Sin programación vigente', text: 'El curso no tiene una programación vigente. Cree una programación antes de gestionar matrículas.', confirmButtonColor: '#6366f1' });
                            return;
                        }

                        if (window.cargarDatosModalMatriculados) {
                            window.cargarDatosModalMatriculados(alpineComponent.cursoId, alpineComponent);
                        }
                    },
                },
            ],
        });

        table.on("dataLoaded", function (data) {
            populateFilters(data);
            const tipoEl = document.getElementById("filtroTipoCurso");
            if (tipoEl) {
                tipoEl.value = "PCE";
                toggleSecondaryFilters("PCE");
                repopulateSecondaryFilters("PCE");
                applyFilters(table);
            }
        });

        [
            "filtroAreaCurso",
            "filtroSistemaCurso",
            "filtroJefaturaCurso",
            "filtroClienteCurso",
            "filtroAnioCurso",
        ].forEach((id) => {
            document
                .getElementById(id)
                ?.addEventListener("change", () => applyFilters(table));
        });

        document.getElementById("filtroTipoCurso")
            ?.addEventListener("change", function () {
                toggleSecondaryFilters(this.value);
                repopulateSecondaryFilters(this.value);
                applyFilters(table);
            });

        document
            .getElementById("btnLimpiarFiltrosCursos")
            ?.addEventListener("click", () => clearFilters(table));

        const buscarCursoEl = document.getElementById("txtBuscarCurso");
        const btnLimpiarBusquedaCurso = document.getElementById("btnLimpiarBusquedaCurso");
        if (buscarCursoEl) {
            let debounceTimer;
            buscarCursoEl.addEventListener("input", function () {
                btnLimpiarBusquedaCurso?.classList.toggle("hidden", !this.value);
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => applyFilters(table), 200);
            });
        }
        btnLimpiarBusquedaCurso?.addEventListener("click", () => {
            if (!buscarCursoEl) return;
            buscarCursoEl.value = "";
            btnLimpiarBusquedaCurso.classList.add("hidden");
            applyFilters(table);
            buscarCursoEl.focus();
        });

        window.tabulatorCursos = table;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '??';
        const d = new Date(dateStr.includes(' ') ? dateStr.replace(' ', 'T') : dateStr);
        if (isNaN(d.getTime())) return '??';
        return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function updateMatricularButton() {
        const btn = document.getElementById('btnGuardarMatriculas');
        if (!btn) return;
        if ((window._activeTab || 'por-matricular') === 'matriculados') {
            btn.innerHTML = '<i class="ti ti-user-plus"></i> Matricular personal (0)';
            btn.disabled = true;
            btn.style.display = 'none';
            return;
        }
        btn.style.display = '';
        const table = window.tabulatorPersonalMatriculado;
        if (!table) {
            btn.innerHTML = '<i class="ti ti-user-plus"></i> Matricular personal (0)';
            btn.disabled = true;
            return;
        }
        const data = table.getData() || [];
        let count = 0;
        data.forEach(d => {
            if (d._seleccionado && !d._matriculado) count++;
        });
        btn.innerHTML = `<i class="ti ti-user-plus"></i> Matricular personal (${count})`;
        const progVigente = window._programacionVigente?.codigo || '';
        btn.disabled = count === 0 || !progVigente;
    }

    window.limpiarModalMatriculados = function() {
        if (typeof window._detenerTextoLoader === 'function') window._detenerTextoLoader();
        if (window.tabulatorPersonalMatriculado) {
            window.tabulatorPersonalMatriculado.destroy();
            window.tabulatorPersonalMatriculado = null;
        }
        window._matriculadosData = [];
        window._searchTerm = '';
        window._estadoFilter = '';
        window._activeTab = 'por-matricular';
        if (typeof window.setTabMatricula === 'function') window.setTabMatricula('por-matricular');
        const txtBuscar = document.getElementById('txtBuscarPersonal');
        if (txtBuscar) { txtBuscar.value = ''; }
        const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
        if (btnLimpiar) { btnLimpiar.style.display = 'none'; }
        window._programacionVigente = null;
        const txtProg = document.getElementById('txtProgramacionVigente');
        if (txtProg) txtProg.textContent = '—';
        ['slcFiltroCliente', 'slcFiltroSucursal', 'slcFiltroCargo', 'slcFiltroTipoTrabajador'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value="">' + (id.includes('Cliente') ? 'Todos' : id.includes('Sucursal') ? 'Todas' : id.includes('Cargo') ? 'Todos' : 'Todos') + '</option>';
        });
        const cntInfo = document.getElementById('txtConteoPersonal');
        if (cntInfo) cntInfo.textContent = '';
        const btn = document.getElementById('btnGuardarMatriculas');
        if (btn) {
            btn.innerHTML = '<i class="ti ti-user-plus"></i> Matricular personal (0)';
            btn.disabled = true;
        }
    }

    function actualizarContadores() {
        const table = window.tabulatorPersonalMatriculado;
        const data = table ? table.getData() || [] : [];
        const modo = window._activeTab || 'por-matricular';
        const el = document.getElementById('txtConteoPersonal');
        if (el) el.textContent = modo === 'matriculados'
            ? `${data.length} persona(s) matriculada(s)`
            : `${data.length} persona(s) disponible(s) para matricular`;
    }

    function getRowsFiltradosVisibles() {
        const table = window.tabulatorPersonalMatriculado;
        if (!table) return [];

        return table.getRows("active") || [];
    }

    function actualizarBotonSeleccionarFiltrados() {
        const btn = document.getElementById('btnSeleccionarFiltrados');
        if (!btn) return;

        const rows = getRowsFiltradosVisibles();
        const count = rows.length;
        const pendientes = rows.filter(r => !r.getData()._matriculado && !r.getData()._seleccionado);
        const todosSeleccionados = pendientes.length === 0 && count > 0;
        if (todosSeleccionados) {
            btn.innerHTML = `<i class="ti ti-checks text-sm"></i><span>Deseleccionar filtrados (${count})</span>`;
            btn.dataset.mode = "deselect";
        } else {
            btn.innerHTML = `<i class="ti ti-checks text-sm"></i><span>Seleccionar filtrados (${count})</span>`;
            btn.dataset.mode = "select";
        }
        btn.disabled = count === 0;
    }

    window._activeTab = window._activeTab || 'por-matricular';

    function refreshTabsUI() {
        const modo = window._activeTab || 'por-matricular';
        const badge = document.getElementById('badgeModoMatricula');
        const txt = document.getElementById('txtModoMatricula');
        if (badge && txt) {
            const esMat = modo === 'matriculados';
            txt.textContent = esMat ? 'Matriculados' : 'Por matricular';
            badge.className = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold border ' +
                (esMat ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-amber-50 border-amber-200 text-amber-700');
            const icon = badge.querySelector('i');
            if (icon) icon.className = esMat ? 'ti ti-users text-sm' : 'ti ti-user-plus text-sm';
        }
        const btnSel = document.getElementById('btnSeleccionarFiltrados');
        if (btnSel) btnSel.style.display = modo === 'por-matricular' ? '' : 'none';
        const lblNotif = document.getElementById('lblNotificarMatricula');
        if (lblNotif) lblNotif.style.display = modo === 'por-matricular' ? '' : 'none';
    }

    window.setTabMatricula = function (tab) {
        window._activeTab = tab;
        window._estadoFilter = '';
        refreshTabsUI();
        const table = window.tabulatorPersonalMatriculado;
        if (table) {
            try {
                const col = table.getColumn('_seleccionado');
                if (col) {
                    if (tab === 'por-matricular') col.show();
                    else col.hide();
                }
            } catch (e) { /* columna aún no lista */ }
        }
        if (table && typeof window._aplicarFiltrosMatricula === 'function') {
            window._aplicarFiltrosMatricula();
        }
        updateMatricularButton();
        actualizarBotonSeleccionarFiltrados();
    };

    window._iniciarTextoLoader = function (comp) {
        window._detenerTextoLoader();
        const base = 'Cargando información';
        let n = 3;
        if (comp) comp.loadingTitle = base + '.'.repeat(n);
        window._loaderDotsTimer = setInterval(() => {
            n = n === 1 ? 3 : n - 1;
            if (comp) comp.loadingTitle = base + '.'.repeat(n);
        }, 500);
    };

    window._detenerTextoLoader = function () {
        if (window._loaderDotsTimer) {
            clearInterval(window._loaderDotsTimer);
            window._loaderDotsTimer = null;
        }
    };

    window.limpiarModalParticipantesMoodle = function () {
        if (window.tabulatorParticipantesMoodle) {
            window.tabulatorParticipantesMoodle.destroy();
            window.tabulatorParticipantesMoodle = null;
        }
    };

    window.cargarParticipantesMoodle = async function (alpineComponent) {
        const cursoMoodleId = alpineComponent?.cursoMoodleId;
        window.limpiarModalParticipantesMoodle();

        if (!cursoMoodleId || Number(cursoMoodleId) <= 0) {
            if (alpineComponent) {
                alpineComponent.isLoading = false;
                alpineComponent.total = 0;
            }
            await Swal.fire({
                icon: 'warning',
                title: 'Curso sin aula virtual',
                text: 'El curso no está vinculado al aula virtual, por lo que no tiene participantes inscritos en Moodle.',
                confirmButtonColor: '#6366f1',
            });
            return;
        }

        try {
            const res = await axios.get(
                `${VITE_URL_APP}/api/obtener-participantes-inscritos-new/${cursoMoodleId}`,
            );
            const participantes = res.data?.matriculados || [];
            if (alpineComponent) {
                alpineComponent.total = Number(
                    res.data?.total ?? participantes.length,
                ) || 0;
            }

            window.tabulatorParticipantesMoodle = new Tabulator(
                "#tblParticipantesMoodle",
                {
                    data: participantes,
                    placeholder:
                        '<div style="padding:28px 16px; text-align:center; color:#94a3b8; font-size:13px; font-weight:600;">No hay participantes inscritos en el aula virtual.</div>',
                    layout: "fitDataFill",
                    pagination: "local",
                    paginationSize: 10,
                    paginationSizeSelector: [10, 20, 50, 100],
                    paginationCounter: "rows",
                    langs: { "es-es": esLocale },
                    locale: "es-es",
                    initialSort: [
                        { column: "nombre_completo", dir: "asc" },
                    ],
                    rowFormatter(row) {
                        setRowHoverEffect(row);
                    },
                    columns: [
                        {
                            title: "Nombre del participante",
                            field: "nombre_completo",
                            minWidth: 240,
                            formatter(cell) {
                                const val = cell.getValue();
                                if (!val) return "—";
                                const initials = getInitials(val);
                                return `
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:28px; height:28px; border-radius:50%; background:#e0e7ff; color:#4338ca; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">${initials}</div>
                                        <span style="font-size:13px; color:#374151;">${val}</span>
                                    </div>
                                `;
                            },
                        },
                        {
                            title: "Cargo del participante",
                            field: "cargo",
                            minWidth: 180,
                            formatter(cell) {
                                const val = cell.getValue();
                                return (
                                    val ||
                                    '<span style="color:#94a3b8;">—</span>'
                                );
                            },
                        },
                        {
                            title: "Tipo de trabajador",
                            field: "tipo_trabajador",
                            minWidth: 150,
                            formatter(cell) {
                                const val = cell.getValue();
                                return (
                                    val ||
                                    '<span style="color:#94a3b8;">—</span>'
                                );
                            },
                        },
                        {
                            title: "Fecha inicio",
                            field: "fecha_inicio",
                            width: 120,
                            hozAlign: "center",
                            formatter(cell) {
                                return formatDate(cell.getValue());
                            },
                        },
                        {
                            title: "Fecha fin",
                            field: "fecha_fin",
                            width: 120,
                            hozAlign: "center",
                            formatter(cell) {
                                return formatDate(cell.getValue());
                            },
                        },
                        {
                            title: "Acción",
                            hozAlign: "center",
                            headerHozAlign: "center",
                            headerSort: false,
                            width: 150,
                            formatter() {
                                return `
                                    <button class="btn-desmatricular-participante" style="
                                        background: #fff;
                                        color: #dc2626;
                                        border: 1px solid #fecaca;
                                        border-radius: 6px;
                                        padding: 6px 12px;
                                        font-size: 12px;
                                        font-weight: 500;
                                        display: inline-flex;
                                        align-items: center;
                                        gap: 6px;
                                        transition: all 0.2s;
                                        cursor: pointer;
                                    ">
                                        <i class="ti ti-user-minus" style="font-size: 14px;"></i>
                                        Desmatricular
                                    </button>
                                `;
                            },
                            cellClick: async function (e, cell) {
                                const btn = e.target?.closest?.(
                                    ".btn-desmatricular-participante",
                                );
                                if (!btn) return;
                                e.stopPropagation();

                                const rowData = cell.getRow().getData();
                                const nombre =
                                    rowData.nombre_completo ||
                                    "este participante";

                                const result = await Swal.fire({
                                    icon: "question",
                                    title: "¿Desmatricular?",
                                    text: `¿Está seguro de desmatricular a ${nombre} del aula virtual?`,
                                    showCancelButton: true,
                                    confirmButtonText: "Sí, desmatricular",
                                    cancelButtonText: "Cancelar",
                                    confirmButtonColor: "#ef4444",
                                    cancelButtonColor: "#6366f1",
                                    reverseButtons: true,
                                });
                                if (!result.isConfirmed) return;

                                try {
                                    Swal.fire({
                                        title: "Desmatriculando...",
                                        allowOutsideClick: false,
                                        didOpen: () => Swal.showLoading(),
                                    });

                                    const resp = await axios.post(
                                        `${VITE_URL_APP}/api/capacitacion/desmatricular-moodle-new`,
                                        {
                                            cursoMoodleId:
                                                alpineComponent?.cursoMoodleId,
                                            dni: rowData.dni,
                                        },
                                    );
                                    Swal.close();

                                    if (resp.data?.success) {
                                        cell.getRow().delete();
                                        if (alpineComponent) {
                                            alpineComponent.total = (
                                                window.tabulatorParticipantesMoodle?.getData() ||
                                                []
                                            ).length;
                                        }
                                        await Swal.fire({
                                            icon: "success",
                                            title: "Desmatriculado",
                                            text:
                                                resp.data.message ||
                                                "El participante fue desmatriculado del aula virtual.",
                                            confirmButtonColor: "#6366f1",
                                            timer: 2000,
                                            showConfirmButton: false,
                                        });
                                    } else {
                                        await Swal.fire({
                                            icon: "error",
                                            title: "Error",
                                            text:
                                                resp.data?.message ||
                                                "Error desconocido",
                                            confirmButtonColor: "#6366f1",
                                        });
                                    }
                                } catch (err) {
                                    Swal.close();
                                    await Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text:
                                            err.response?.data?.message ||
                                            "Error al desmatricular al participante",
                                        confirmButtonColor: "#6366f1",
                                    });
                                }
                            },
                        },
                    ],
                },
            );
        } catch (error) {
            console.error("Error cargando participantes de Moodle", error);
            await Swal.fire({
                icon: "error",
                title: "Error",
                text: "No se pudieron cargar los participantes inscritos en el aula virtual.",
                confirmButtonColor: "#6366f1",
            });
        } finally {
            if (alpineComponent) alpineComponent.isLoading = false;
        }
    };

    window.cargarDatosModalMatriculados = async function(cursoId, alpineComponent) {
        if (!cursoId) return;

        try {
            const res = await axios.get(`${VITE_URL_APP}/api/obtener-datos-matricula/${cursoId}`);

            const programaciones = res.data.programaciones || res.data.Programaciones || [];
            const personalData = res.data.personal || [];
            const matriculadosData = res.data.matriculados || res.data.Matriculados || [];

            // Ambos flujos operan siempre sobre la programación vigente
            const progVigente = (programaciones || []).find(p =>
                String(p.estado_periodo || p.estado || '').toUpperCase() === 'VIGENTE'
            ) || null;
            window._programacionVigente = progVigente ? {
                codigo: String(progVigente.codigo_programacion || progVigente.cod_programacion || progVigente.codigo || '').trim(),
                fecha_inicio: progVigente.fecha_inicio,
                fecha_final: progVigente.fecha_final,
                estado: progVigente.estado_periodo || progVigente.estado || 'VIGENTE',
            } : null;

            const txtProg = document.getElementById('txtProgramacionVigente');
            if (txtProg) {
                txtProg.textContent = window._programacionVigente
                    ? `Programación ${window._programacionVigente.codigo} | ${formatDate(window._programacionVigente.fecha_inicio)} - ${formatDate(window._programacionVigente.fecha_final)} (${window._programacionVigente.estado})`
                    : 'Sin programación vigente';
            }
            window._matriculadosData = matriculadosData;

            const responsableCodigo = alpineComponent?.codResponsable;
            const modoModal = window._activeTab || 'por-matricular';
            window._personalBase = personalData
                .filter(p => String(p.codigo) !== String(responsableCodigo));

            // Solo la población del modo: sin matricular (matricular) o matriculados (desmatricular)
            const construirFilas = () => {
                const codProg = window._programacionVigente?.codigo || '';
                const filtrados = codProg
                    ? (window._matriculadosData || []).filter(m => String(m.cod_programacion || m.Cod_Programacion || '').trim() === codProg)
                    : [];
                return (window._personalBase || [])
                    .map(p => {
                        const personalId = String(p.codigo || p.dni || p.id);
                        const match = filtrados.find(m => String(m.cod_personal || m.Id_Personal || m.id || m.Id) === personalId);
                        const yaMatriculado = !!match;
                        return {
                            ...p,
                            _matriculado: yaMatriculado,
                            _seleccionado: false,
                            _fecha_matricula: match ? match.fecha_matricula || match.Fecha_Matricula || null : null,
                            _estado: match ? (match.estado || match.Estado || 'MATRICULADO') : null
                        };
                    })
                    .filter(r => modoModal === 'matriculados' ? r._matriculado : !r._matriculado);
            };

            const tableData = construirFilas();
            window._reconstruirFilasPersonal = construirFilas;

            const unique = (field) => [...new Set(tableData.map(d => d[field]).filter(Boolean))].sort();
            fillSelect("slcFiltroCliente", unique("cliente"), "Todos los clientes");
            fillSelect("slcFiltroSucursal", unique("sucursal"), "Todas las sucursales");
            fillSelect("slcFiltroCargo", unique("cargo"), "Todos los cargos");
            fillSelect("slcFiltroTipoTrabajador", unique("tipo_trabajador"), "Todos los tipos");

            if (window.tabulatorPersonalMatriculado) {
                window.tabulatorPersonalMatriculado.destroy();
            }

            return new Promise((resolve) => {
                setTimeout(() => {
                    window.tabulatorPersonalMatriculado = new Tabulator(
                        "#tblPersonalMatriculado",
                        {
                            data: tableData,
                            placeholder: () =>
                                (window._activeTab || "por-matricular") ===
                                "matriculados"
                                    ? '<div style="padding:28px 16px; text-align:center; color:#94a3b8; font-size:13px; font-weight:600;">No hay personal matriculado para desmatricular.</div>'
                                    : '<div style="padding:28px 16px; text-align:center; color:#94a3b8; font-size:13px; font-weight:600;">No hay personal pendiente para matricular.</div>',
                            layout: "fitDataFill",
                            pagination: "local",
                            paginationSize: 10,
                            langs: { "es-es": esLocale },
                            locale: "es-es",
                            initialSort: [
                                { column: "nombre_completo", dir: "asc" },
                            ],
                            rowFormatter(row) {
                                setRowHoverEffect(row);
                            },
                            columns: [
                                {
                                    title: "<input type='checkbox' class='checkbox-select-all' title='Seleccionar todos' aria-label='Seleccionar todos'>",
                                    field: "_seleccionado",
                                    width: 50,
                                    hozAlign: "center",
                                    headerSort: false,
                                    formatter: function (cell) {
                                        const isChecked = cell.getValue();
                                        const rowData = cell.getRow().getData();
                                        const locked = rowData._matriculado;
                                        return `<div style="position:relative; display:inline-block; width:16px; height:16px;">
                                                <input type="checkbox" class="checkbox-personal-row" style="width:100%; height:100%; cursor:${locked ? "not-allowed" : "pointer"}; opacity:${locked ? "0.5" : "1"};" ${isChecked ? "checked" : ""} ${locked ? "disabled" : ""}>
                                            </div>`;
                                    },
                                    cellClick: function (e, cell) {
                                        e.preventDefault();
                                        const rowData = cell.getRow().getData();
                                        if (rowData._matriculado) return;
                                        cell.setValue(!cell.getValue());
                                        updateMatricularButton();
                                        actualizarContadores();
                                    },
                                },
                                {
                                    title: "Nombres",
                                    field: "nombre_completo",
                                    minWidth: 200,
                                },
                                {
                                    title: "Cliente",
                                    field: "cliente",
                                    sorter: function(a, b) {
                                        const va = a || "Sin cliente";
                                        const vb = b || "Sin cliente";
                                        return va.localeCompare(vb, "es");
                                    },
                                    formatter(cell) {
                                        return (
                                            cell.getValue() ||
                                            '<span style="color:#94a3b8;">Sin cliente</span>'
                                        );
                                    },
                                },
                                {
                                    title: "Sucursal",
                                    field: "sucursal",
                                },
                                { title: "Cargo", field: "cargo" },
                                {
                                    title: "TIPO TRAB",
                                    field: "tipo_trabajador",
                                },
                                {
                                    title: "Estado",
                                    field: "_estado",
                                    hozAlign: "center",
                                    sorter: function(a, b) {
                                        const va = a || "—";
                                        const vb = b || "—";
                                        const order = { "—": 0, "SUSPENDIDO": 1, "MATRICULADO": 2, "FINALIZADO": 3 };
                                        return (order[va] || 0) - (order[vb] || 0);
                                    },
                                    formatter(cell) {
                                        const val = cell.getValue();
                                        const rowData = cell.getRow().getData();
                                        if (!val || !rowData._matriculado)
                                            return '<span style="color:#cbd5e1;">—</span>';
                                        const colors = {
                                            MATRICULADO: {
                                                bg: "#dcfce7",
                                                text: "#166534",
                                                icon: "ti ti-circle-check-filled",
                                            },
                                            SUSPENDIDO: {
                                                bg: "#fef3c7",
                                                text: "#92400e",
                                                icon: "ti ti-alert-circle",
                                            },
                                            FINALIZADO: {
                                                bg: "#dbeafe",
                                                text: "#1e40af",
                                                icon: "ti ti-circle-check-filled",
                                            },
                                        };
                                        const style = colors[
                                            val.toUpperCase()
                                        ] || {
                                            bg: "#f1f5f9",
                                            text: "#475569",
                                            icon: "ti ti-circle",
                                        };
                                        return `<button type="button" class="btn-desmatricular" style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;background:${style.bg};color:${style.text};white-space:nowrap;border:none;cursor:pointer;"><i class="${style.icon} estado-icon" style="font-size:12px;"></i><span class="estado-label">${val}</span></button>`;
                                    },
                                    cellClick(e, cell) {
                                        const rowData = cell.getRow().getData();
                                        if (!rowData._matriculado) return;
                                        if (rowData._estado === 'FINALIZADO') {
                                            Swal.fire({ icon: 'info', title: 'Matrícula finalizada', text: 'No se puede desmatricular un curso que ya ha finalizado.', confirmButtonText: 'Entendido' });
                                            return;
                                        }
                                        e.stopPropagation();
                                        const nombre = rowData.nombre_completo || 'este usuario';
                                        Swal.fire({
                                            icon: 'question',
                                            title: '¿Desmatricular?',
                                            text: `¿Está seguro de desmatricular a ${nombre}?`,
                                            showCancelButton: true,
                                            confirmButtonText: 'Sí, desmatricular',
                                            cancelButtonText: 'Cancelar',
                                            confirmButtonColor: '#ef4444',
                                            cancelButtonColor: '#6366f1',
                                            reverseButtons: true,
                                        }).then(async (result) => {
                                            if (!result.isConfirmed) return;
                                            const codPersonal = String(rowData.codigo || rowData.dni || rowData.id);
                                            try {
                                                Swal.fire({ title: 'Desmatriculando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                                                const resp = await axios.post(`${VITE_URL_APP}/api/capacitacion/desmatricular-usuario-new`, { codPersonal, cursoId });
                                                Swal.close();
                                                if (resp.data.success) {
                                                    const progActual = window._programacionVigente?.codigo || '';
                                                    if (Array.isArray(window._matriculadosData)) {
                                                        window._matriculadosData = window._matriculadosData.filter(m =>
                                                            !(String(m.cod_personal || m.Id_Personal || m.id || m.Id) === codPersonal &&
                                                              String(m.cod_programacion || m.Cod_Programacion || '').trim() === String(progActual).trim())
                                                        );
                                                    }
                                                    cell.getRow().delete();
                                                    await Swal.fire({ icon: 'success', title: 'Desmatriculado', text: resp.data.message || 'El personal fue desmatriculado correctamente.', confirmButtonColor: '#6366f1', timer: 2000, showConfirmButton: false });
                                                    updateMatricularButton();
                                                    actualizarContadores();
                                                    if (typeof window._aplicarFiltrosMatricula === 'function') window._aplicarFiltrosMatricula();
                                                    actualizarBotonSeleccionarFiltrados();
                                                    recargarListadoCursos();
                                                } else {
                                                    await Swal.fire({ icon: 'error', title: 'Error', text: resp.data.message || 'Error desconocido', confirmButtonColor: '#6366f1' });
                                                }
                                            } catch (err) {
                                                Swal.close();
                                                await Swal.fire({ icon: 'error', title: 'Error', text: err.response?.data?.message || 'Error al desmatricular', confirmButtonColor: '#6366f1' });
                                            }
                                        });
                                    },
                                },
                            ],
                        },
                    );

                    window.tabulatorPersonalMatriculado.on("tableBuilt", function() {
                        const headerCheckbox = document.querySelector('.checkbox-select-all');

                        // Reset header checkbox al cambiar de página
                        window.tabulatorPersonalMatriculado.on("pageLoaded", function() {
                            if (headerCheckbox) headerCheckbox.checked = false;
                        });

                        if (headerCheckbox) {
                            headerCheckbox.addEventListener('change', function(e) {
                                const isChecked = e.target.checked;
                                const rows = window.tabulatorPersonalMatriculado.getRows();
                                rows.forEach(row => {
                                    const el = row.getElement();
                                    if (el && el.style.display === 'none') return;
                                    const d = row.getData();
                                    if (!d._matriculado) {
                                        row.update({ _seleccionado: isChecked });
                                    }
                                });
                                updateMatricularButton();
                                actualizarContadores();
                            });
                        }
                        
                        function aplicarFiltrosCombinados() {
                            const cli = document.getElementById("slcFiltroCliente")?.value;
                            const suc = document.getElementById("slcFiltroSucursal")?.value;
                            const car = document.getElementById("slcFiltroCargo")?.value;
                            const tip = document.getElementById("slcFiltroTipoTrabajador")?.value;
                            const term = window._searchTerm || '';

                            // El dataset ya viene filtrado por modo (ver construirFilas); aquí solo filtros secundarios + búsqueda
                            const filters = [];
                            if (cli) filters.push({ field: "cliente", type: "=", value: cli });
                            if (suc) filters.push({ field: "sucursal", type: "=", value: suc });
                            if (car) filters.push({ field: "cargo", type: "=", value: car });
                            if (tip) filters.push({ field: "tipo_trabajador", type: "=", value: tip });

                            window.tabulatorPersonalMatriculado.clearFilter();
                            if (filters.length) window.tabulatorPersonalMatriculado.setFilter(filters);

                            if (term) {
                                window.tabulatorPersonalMatriculado.addFilter(data => {
                                    const nombre = (data.nombre_completo || '').toLowerCase();
                                    const codigo = String(data.codigo || '');
                                    const dni = String(data.dni || '');
                                    return nombre.includes(term) || codigo.includes(term) || dni.includes(term);
                                });
                            }

                            actualizarBotonSeleccionarFiltrados();
                        }
                        window._aplicarFiltrosMatricula = aplicarFiltrosCombinados;

                        const txtBuscar = document.getElementById('txtBuscarPersonal');
                        const btnLimpiarBusqueda = document.getElementById('btnLimpiarBusqueda');
                        if (txtBuscar) {
                            txtBuscar.addEventListener('input', function() {
                                window._searchTerm = this.value.toLowerCase().trim();
                                if (btnLimpiarBusqueda) btnLimpiarBusqueda.style.display = this.value ? '' : 'none';
                                aplicarFiltrosCombinados();
                            });
                        }
                        if (btnLimpiarBusqueda) {
                            btnLimpiarBusqueda.addEventListener('click', function() {
                                txtBuscar.value = '';
                                window._searchTerm = '';
                                this.style.display = 'none';
                                aplicarFiltrosCombinados();
                            });
                        }

                        // Sin tabs: el modo lo define el botón de origen (Matricular / Desmatricular) y filtra el dataset

                        ["slcFiltroCliente", "slcFiltroSucursal", "slcFiltroCargo", "slcFiltroTipoTrabajador"].forEach(id => {
                            document.getElementById(id)?.addEventListener('change', aplicarFiltrosCombinados);
                        });

                        // Sin selector: siempre se opera sobre la programación vigente fija

                        const btnSeleccionarFiltrados = document.getElementById('btnSeleccionarFiltrados');
                        if (btnSeleccionarFiltrados) {
                            btnSeleccionarFiltrados.onclick = function() {
                                const rows = getRowsFiltradosVisibles();

                                if (!rows.length) {
                                    Swal.fire({ icon: 'warning', title: 'Sin resultados', text: 'No hay personal filtrado para seleccionar', confirmButtonColor: '#6366f1' });
                                    return;
                                }

                                const seleccionar = btnSeleccionarFiltrados.dataset.mode !== "deselect";

                                rows.forEach((row) => {
                                    const d = row.getData();
                                    if (!d._matriculado) {
                                        row.update({ _seleccionado: seleccionar });
                                    }
                                });

                                updateMatricularButton();
                                actualizarContadores();
                                actualizarBotonSeleccionarFiltrados();
                            };
                        }

                        // Botón guardar matrícula
                        const btnGuardar = document.getElementById('btnGuardarMatriculas');
                        if (btnGuardar) {
                            btnGuardar.onclick = async function() {
                                const modalEl = document.getElementById('modal-lista-matriculados');
                                const alpineComponent = modalEl?._x_dataStack?.[0];
                                const cId = alpineComponent?.cursoId;
                                const progId = window._programacionVigente?.codigo || '';

                                if (!progId) {
                                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'El curso no tiene una programación vigente', confirmButtonColor: '#6366f1' });
                                    return;
                                }

                                const allData = window.tabulatorPersonalMatriculado?.getData() || [];
                                const personalIds = allData.filter(d => d._seleccionado && !d._matriculado).map(d => String(d.codigo || d.dni || d.id)).filter(Boolean);

                                if (!personalIds.length) {
                                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione al menos un personal sin matricular', confirmButtonColor: '#6366f1' });
                                    return;
                                }

                                const notificar = !!alpineComponent?.notificarCorreo;
                                const correo = String(alpineComponent?.correoNotificacion || '').trim();

                                if (notificar && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                                    Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingresa un correo válido para enviar el aviso.', confirmButtonColor: '#6366f1' });
                                    return;
                                }

                                // Cerrar modal inmediatamente
                                if (alpineComponent?.cerrar) alpineComponent.cerrar();

                                Swal.fire({ title: 'Matriculando personal...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                                try {
                                    const resp = await axios.post(
                                        `${VITE_URL_APP}/api/capacitacion/save-matricula-new`,
                                        {
                                            cursoId: String(cId),
                                            programacionId: String(progId),
                                            personalIds,
                                            notificar_email: notificar,
                                            correo_notificacion: notificar ? correo : null,
                                        },
                                    );
                                    Swal.close();
                                    if (resp.data.success) {
                                        await Swal.fire({ icon: 'success', title: '¡Solicitud enviada!', text: 'La matriculación fue enviada a procesamiento.' + (notificar ? ` Si son más de 10 personas, se enviará el resumen a ${correo} al finalizar.` : ''), confirmButtonColor: '#6366f1' });
                                        recargarListadoCursos();
                                    } else {
                                        await Swal.fire({ icon: 'error', title: 'Error', text: resp.data.message || 'Error desconocido', confirmButtonColor: '#6366f1' });
                                    }
                                } catch (err) {
                                    Swal.close();
                                    console.error(err);
                                    await Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar la matrícula', confirmButtonColor: '#6366f1' });
                                }
                            };
                        }

                        window.setTabMatricula(window._activeTab || 'por-matricular');
                        actualizarContadores();
                        if (typeof window._detenerTextoLoader === 'function') window._detenerTextoLoader();
                        if (alpineComponent) alpineComponent.isLoading = false;
                    });

                    resolve();
                }, 300);
            });

        } catch (error) {
            console.error("Error cargando datos del modal", error);
            if (typeof window._detenerTextoLoader === 'function') window._detenerTextoLoader();
            if (alpineComponent) alpineComponent.isLoading = false;
        }
    };

    initTableCursos();
});
