import { TabulatorFull as Tabulator } from "tabulator-tables";
import "tabulator-tables/dist/css/tabulator_simple.min.css";

import axios from "axios";
import Alpine from "alpinejs";

axios.defaults.withCredentials = true;
axios.defaults.headers.common["X-CSRF-TOKEN"] = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");

document.addEventListener("DOMContentLoaded", () => {
    const state = {
        memoSearchTerm: "",
        memoCliente: "",
        memoNivel: null,
        memoSucursal: "",
        memoTipo: "",
        cursoResponsable: "",
        cursoTipo: "",
        cursoAnio: "",
    };

    const elements = {
        searchCurso: document.getElementById("buscarCursoSeguimiento"),
        filtroResponsableCursos: document.getElementById(
            "filtroResponsableCursos",
        ),
        filtroTipoCursos: document.getElementById("filtroTipoCursos"),
        filtroAnioCursos: document.getElementById("filtroAnioCursos"),
        searchPersonal: document.getElementById("buscarPersonalSeguimiento"),
        searchMemos: document.getElementById("buscarMemosEnviados"),
        filtroClienteMemos: document.getElementById("filtroClienteMemos"),
        filtroTipoTrabMemos: document.getElementById("filtroTipoTrabMemos"),
        filtroSucursalMemos: document.getElementById("filtroSucursalMemos"),
        btnBuscarMemos: document.getElementById("btnBuscarMemosEnviados"),
        btnLimpiarMemos: document.getElementById("btnLimpiarFiltroMemos"),
        filtroSucursalPersonal: document.getElementById(
            "filtroSucursalPersonal",
        ),
        filtroTipoPersonal: document.getElementById("filtroTipoPersonal"),
        filtroCargoPersonal: document.getElementById("filtroCargoPersonal"),
        filtroClientePersonal: document.getElementById("filtroClientePersonal"),
    };

    const personasSeleccionadasPersonal = new Set();

    window.personalSeleccionado = () => {
        const table = window.tabulatorPersonal;
        if (!table) return [];
        return table
            .getData()
            .filter((row) => personasSeleccionadasPersonal.has(row.dni));
    };

    const actualizarContadorSeleccionPersonal = () => {
        const count = personasSeleccionadasPersonal.size;
        const el = document.getElementById("selectedPersonalCount");
        const container = document.getElementById("selectedPersonalInfo");
        if (el) el.textContent = count;
        if (container) {
            container.classList.toggle("hidden", count === 0);
        }
        const btn = document.getElementById("btnEnviarMemosPersonal");
        const btnText = document.getElementById("btnEnviarMemosText");
        if (btn) {
            if (count > 0) {
                btn.removeAttribute("disabled");
                btn.classList.remove("opacity-50", "cursor-not-allowed");
            } else {
                btn.setAttribute("disabled", "disabled");
                btn.classList.add("opacity-50", "cursor-not-allowed");
            }
        }
        if (btnText) {
            btnText.textContent =
                count > 0
                    ? `Enviar MEMOs al personal seleccionado (${count} seleccionado${count !== 1 ? "s" : ""})`
                    : "Enviar MEMOs al personal seleccionado";
        }
        const btnDeshacer = document.getElementById(
            "btnDeshacerSeleccionPersonal",
        );
        if (btnDeshacer) {
            if (count > 0) {
                btnDeshacer.removeAttribute("disabled");
                btnDeshacer.classList.remove(
                    "opacity-50",
                    "cursor-not-allowed",
                );
            } else {
                btnDeshacer.setAttribute("disabled", "disabled");
                btnDeshacer.classList.add("opacity-50", "cursor-not-allowed");
            }
        }
    };

    const limpiarSeleccionPersonal = () => {
        personasSeleccionadasPersonal.clear();
        const table = window.tabulatorPersonal;
        if (table) {
            const visibleRows = table.getRows("visible");
            visibleRows.forEach((row) => {
                const cell = row.getCell("seleccionar");
                if (cell) {
                    const el = cell.getElement();
                    const cb = el?.querySelector(".checkbox-personal-row");
                    const label = el?.querySelector("label");
                    if (cb) {
                        cb.checked = false;
                        cb.style.borderColor = "#d1d5db";
                        cb.style.background = "transparent";
                    }
                    if (label) {
                        label.style.background = "transparent";
                    }
                }
            });
        }
        actualizarContadorSeleccionPersonal();
    };

    function configurarEventosCheckboxesPersonal() {
        const container = document.getElementById("tblPersonalSeguimiento");
        if (!container) return;
        if (container.dataset.checkboxListener) return;
        container.dataset.checkboxListener = "true";

        container.addEventListener("change", function (e) {
            const table = window.tabulatorPersonal;
            if (!table) return;

            if (e.target.classList.contains("checkbox-personal-row")) {
                const dni = e.target.dataset.dni;
                if (e.target.checked) {
                    personasSeleccionadasPersonal.add(dni);
                } else {
                    personasSeleccionadasPersonal.delete(dni);
                }
                actualizarContadorSeleccionPersonal();
            }

            if (e.target.classList.contains("checkbox-select-all")) {
                const checked = e.target.checked;
                const visibleData = table.getData("visible");
                const visibleRows = table.getRows("visible");
                visibleData.forEach((row) => {
                    if (checked) {
                        personasSeleccionadasPersonal.add(row.dni);
                    } else {
                        personasSeleccionadasPersonal.delete(row.dni);
                    }
                });
                visibleRows.forEach((row) => {
                    const cell = row.getCell("seleccionar");
                    if (cell) {
                        const el = cell.getElement();
                        const cb = el?.querySelector(".checkbox-personal-row");
                        const label = el?.querySelector("label");
                        if (cb) {
                            cb.checked = checked;
                            cb.style.borderColor = checked
                                ? "#3b82f6"
                                : "#d1d5db";
                            cb.style.background = checked
                                ? "#3b82f6"
                                : "transparent";
                        }
                        if (label) {
                            label.style.background = checked
                                ? "rgba(37,99,235,.1)"
                                : "transparent";
                        }
                    }
                });
                actualizarContadorSeleccionPersonal();
            }
        });
    }

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

    function anioDeFecha(value) {
        if (!value) return "";
        const d = new Date(value);
        if (!Number.isNaN(d.getTime())) return String(d.getFullYear());
        const m = String(value).match(/(\d{4})/);
        return m ? m[1] : "";
    }

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

    function abrirModalPersonal(data) {
        const modalEl = document.getElementById("modal-usuario");
        if (!modalEl) return;

        const alpineData = Alpine.$data(modalEl);
        if (alpineData?.mostrar) {
            alpineData.mostrar(data);
        }
    }

    function cargarClientesMemos(lista = []) {
        const select = elements.filtroClienteMemos;
        if (!select) return;

        const actual = select.value;
        const clientes = [
            ...new Set(lista.map((m) => m.CLIENTE).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todos los clientes</option>` +
            clientes.map((c) => `<option value="${c}">${c}</option>`).join("");

        select.value = actual;
    }

    function cargarTiposTrabMemos(lista = []) {
        const select = elements.filtroTipoTrabMemos;
        if (!select) return;

        const actual = select.value;
        const tiposTrab = [
            ...new Set(lista.map((m) => m.TIPO_TRABAJADOR).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todos los tipos</option>` +
            tiposTrab.map((t) => `<option value="${t}">${t}</option>`).join("");

        select.value = actual;
    }

    function cargarSucursalesMemos(lista = []) {
        const select = elements.filtroSucursalMemos;
        if (!select) return;

        const actual = select.value;
        const sucursales = [
            ...new Set(lista.map((m) => m.SUCURSAL).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todas las sucursales</option>` +
            sucursales
                .map((s) => `<option value="${s}">${s}</option>`)
                .join("");

        select.value = actual;
    }

    function cargarSucursalesPersonal(lista = []) {
        const select = elements.filtroSucursalPersonal;
        if (!select) return;

        const actual = select.value;
        const sucursales = [
            ...new Set(lista.map((p) => p.sucursal).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todas las sucursales</option>` +
            sucursales
                .map((s) => `<option value="${s}">${s}</option>`)
                .join("");

        select.value = actual;
    }

    function cargarCargosPersonal(lista = []) {
        const select = elements.filtroCargoPersonal;
        if (!select) return;

        const actual = select.value;
        const cargos = [
            ...new Set(lista.map((p) => p.cargo).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todos los cargos</option>` +
            cargos.map((c) => `<option value="${c}">${c}</option>`).join("");

        select.value = actual;
    }

    function cargarClientesPersonal(lista = []) {
        const select = elements.filtroClientePersonal;
        if (!select) return;

        const actual = select.value;
        const clientes = [
            ...new Set(lista.map((p) => p.cliente).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todos los clientes</option>` +
            `<option value="Sin cliente">Sin cliente</option>` +
            clientes.map((c) => `<option value="${c}">${c}</option>`).join("");

        select.value = actual;
    }

    function cargarTiposPersonal(lista = []) {
        const select = elements.filtroTipoPersonal;
        if (!select) return;

        const actual = select.value;
        const tipos = [
            ...new Set(lista.map((p) => p.tipo_trabajador).filter(Boolean)),
        ].sort();

        select.innerHTML =
            `<option value="">Todos los tipos</option>` +
            tipos.map((t) => `<option value="${t}">${t}</option>`).join("");

        select.value = actual;
    }

    function aplicarFiltrosMemos() {
        const tbl = window.tabulatorMemos;
        if (!tbl) return;

        const term = state.memoSearchTerm.toLowerCase().trim();
        const cliente = state.memoCliente;
        const nivel = state.memoNivel;
        const sucursal = state.memoSucursal.toUpperCase();
        const tipo = state.memoTipo;

        tbl.setFilter((data) => {
            const nombre = (data.NOMBRE_COMPLETO || "").toLowerCase();
            const dni = String(data.NRO_DOCU_IDEN || "").toLowerCase();
            const cli = data.CLIENTE || "";
            const niv = Number(data.NUM_MEMO);

            const matchTexto =
                !term || nombre.includes(term) || dni.includes(term);

            const matchCliente = !cliente || cli === cliente;

            const matchNivel = !nivel || niv === nivel;

            const matchSucursal =
                !sucursal || (data.SUCURSAL || "").toUpperCase() === sucursal;

            const matchTipo =
                !tipo ||
                (data.TIPO_TRABAJADOR || "").toUpperCase() ===
                    tipo.toUpperCase();

            return (
                matchTexto &&
                matchCliente &&
                matchNivel &&
                matchSucursal &&
                matchTipo
            );
        });
    }

    function ejecutarBusquedaMemos() {
        state.memoSearchTerm = elements.searchMemos?.value || "";
        aplicarFiltrosMemos();
    }

    function aplicarFiltrosPersonal() {
        const tbl = window.tabulatorPersonal;
        if (!tbl) return;

        const sucursal = (
            elements.filtroSucursalPersonal?.value || ""
        ).toUpperCase();
        const tipo = elements.filtroTipoPersonal?.value || "";
        const cargo = (elements.filtroCargoPersonal?.value || "").toUpperCase();
        const cliente = elements.filtroClientePersonal?.value || "";

        if (!sucursal && !tipo && !cargo && !cliente) {
            tbl.clearFilter();
            return;
        }

        tbl.setFilter((data) => {
            if (sucursal && (data.sucursal || "").toUpperCase() !== sucursal)
                return false;
            if (
                tipo &&
                (data.tipo_trabajador || "").toUpperCase() !==
                    tipo.toUpperCase()
            )
                return false;
            if (cargo && (data.cargo || "").toUpperCase() !== cargo)
                return false;
            if (cliente) {
                const dataCliente = data.cliente ? data.cliente.trim() : "";
                if (cliente === "Sin cliente") {
                    if (dataCliente) return false;
                } else {
                    if (dataCliente.toUpperCase() !== cliente.toUpperCase())
                        return false;
                }
            }
            return true;
        });
    }

    function initTablaCursosSeguimiento() {
        const table = new Tabulator("#tblCursosSeguimiento", {
            ajaxURL: `${VITE_URL_APP}/api/get-cursos-seguimiento-new`,
            layout: "fitColumns",
            placeholder: "No se encontraron cursos",
            pagination: "local",
            paginationSize: 15,
            paginationSizeSelector: [15, 25, 50],
            paginationCounter: "rows",
            locale: "es-es",
            langs: { "es-es": esLocale },

            rowFormatter(row) {
                setRowHoverEffect(row);
            },

            columns: [
                {
                    title: "Código",
                    field: "codigo",
                    width: 100,
                    hozAlign: "center",
                },
                {
                    title: "Nombre de curso",
                    field: "nombre",
                    minWidth: 250,
                    headerSort: true,
                    formatter(cell) {
                        const val = cell.getValue();
                        return `<span style="font-weight:500; color:#111827;">${val ?? "—"}</span>`;
                    },
                },
                {
                    title: "Responsable",
                    field: "responsable",
                    minWidth: 200,
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
                    title: "Creación",
                    field: "fecha_creacion",
                    width: 150,
                    hozAlign: "center",
                    headerSort: true,
                    formatter(cell) {
                        const { fecha, hora } = formatDateTime(cell.getValue());
                        if (fecha === "—") return "—";
                        return `
                        <div style="display:flex; flex-direction:column; align-items:center; line-height:1.4;">
                            <span style="font-size:12px; font-weight:600; color:#374151;">${fecha}</span>
                            <span style="font-size:11px; color:#9ca3af;">${hora}</span>
                        </div>
                    `;
                    },
                },
                {
                    title: "Acciones",
                    width: 150,
                    hozAlign: "center",
                    headerSort: false,
                    formatter(cell) {
                        const vigente = cell.getData().vigente;
                        if (!vigente) {
                            return `
                            <button class="btn-detalle-curso" disabled title="Este curso aún no se ha aperturado, no se puede realizar un seguimiento" style="
                                background: #e5e7eb;
                                color: #9ca3af;
                                border: none;
                                border-radius: 6px;
                                padding: 6px 12px;
                                font-size: 12px;
                                font-weight: 500;
                                cursor: not-allowed;
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                            ">
                                <i class="ti ti-eye-off" style="font-size: 14px;"></i>
                                Ver detalle
                            </button>
                        `;
                        }
                        return `
                        <button class="btn-detalle-curso" data-row-id="${cell.getRow().getIndex()}" style="
                            background: #4f46e5;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            padding: 6px 12px;
                            font-size: 12px;
                            font-weight: 500;
                            cursor: pointer;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            transition: all 0.2s;
                        ">
                            <i class="ti ti-eye" style="font-size: 14px;"></i>
                            Ver detalle
                        </button>
                    `;
                    },
                    cellClick: function (e, cell) {
                        const vigente = cell.getData().vigente;
                        if (!vigente) return;
                        e.stopPropagation();
                        const row = cell.getRow();
                        const data = row.getData();

                        const modalEl = document.getElementById(
                            "modal-detalle-curso",
                        );
                        const alpineComponent = modalEl?._x_dataStack?.[0];

                        if (alpineComponent?.mostrar) {
                            const dummyGetUsuarios = () =>
                                Promise.resolve({ data: [] });
                            const dummySendMail = () =>
                                Promise.resolve({ data: {} });

                            alpineComponent.mostrar(
                                data,
                                dummyGetUsuarios,
                                dummySendMail,
                            );
                        } else {
                            console.warn(
                                "No se encontró el componente Alpine del modal",
                            );
                        }
                    },
                },
            ],
        });

        window.tabulatorCursos = table;

        table.on("dataLoaded", function (data) {
            const responsables = [
                ...new Set(data.map((r) => r.responsable).filter(Boolean)),
            ].sort();

            const select = elements.filtroResponsableCursos;
            if (select) {
                const actual = select.value;
                select.innerHTML =
                    `<option value="">Todos los responsables</option>` +
                    responsables
                        .map((r) => `<option value="${r}">${r}</option>`)
                        .join("");
                select.value = actual;
            }

            const tipos = [
                ...new Set(data.map((r) => r.tipo_curso).filter(Boolean)),
            ].sort();

            const selectTipo = elements.filtroTipoCursos;
            if (selectTipo) {
                const actualTipo = selectTipo.value;
                selectTipo.innerHTML =
                    `<option value="">Todos los tipos</option>` +
                    tipos
                        .map((t) => `<option value="${t}">${t}</option>`)
                        .join("");
                selectTipo.value = actualTipo;
            }

            const anios = [
                ...new Set(
                    data.map((r) => anioDeFecha(r.fecha_creacion)).filter(Boolean),
                ),
            ]
                .sort()
                .reverse();

            const selectAnio = elements.filtroAnioCursos;
            if (selectAnio) {
                selectAnio.innerHTML =
                    `<option value="">Todos los años</option>` +
                    anios.map((a) => `<option value="${a}">${a}</option>`).join("");
                selectAnio.value = anios.includes(state.cursoAnio)
                    ? state.cursoAnio
                    : "";
                state.cursoAnio = selectAnio.value;
            }
        });

        function aplicarFiltrosCursos() {
            const term = (elements.searchCurso?.value || "")
                .trim()
                .toLowerCase();
            const resp = state.cursoResponsable;
            const tipo = state.cursoTipo;
            const anio = state.cursoAnio;

            if (term || resp || tipo || anio) {
                table.setFilter((data) => {
                    if (resp && (data.responsable || "") !== resp)
                        return false;
                    if (tipo && (data.tipo_curso || "") !== tipo)
                        return false;
                    if (anio && anioDeFecha(data.fecha_creacion) !== anio)
                        return false;
                    if (term) {
                        const nombre = (data.nombre || "").toLowerCase();
                        const codigo = String(
                            data.codigo || "",
                        ).toLowerCase();
                        if (
                            !nombre.includes(term) &&
                            !codigo.includes(term)
                        )
                            return false;
                    }
                    return true;
                });
            } else {
                table.clearFilter();
            }
        }

        if (elements.filtroResponsableCursos) {
            elements.filtroResponsableCursos.addEventListener(
                "change",
                function () {
                    state.cursoResponsable = this.value;
                    aplicarFiltrosCursos();
                },
            );
        }

        if (elements.filtroTipoCursos) {
            elements.filtroTipoCursos.addEventListener(
                "change",
                function () {
                    state.cursoTipo = this.value;
                    aplicarFiltrosCursos();
                },
            );
        }

        if (elements.filtroAnioCursos) {
            elements.filtroAnioCursos.addEventListener(
                "change",
                function () {
                    state.cursoAnio = this.value;
                    aplicarFiltrosCursos();
                },
            );
        }

        if (elements.searchCurso) {
            let debounceTimer;
            elements.searchCurso.addEventListener("input", function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(aplicarFiltrosCursos, 300);
            });
        }
    }

    function initTablaMemosEnviados() {
        const table = new Tabulator("#tblMemosEnviados", {
            ajaxURL: `${VITE_URL_APP}/api/obtener-memos-enviados`,
            ajaxResponse(url, params, response) {
                const data = response.data || [];

                const alpineEl = document.querySelector(
                    '[x-data="memosStats()"]',
                );
                if (alpineEl?._x_dataStack?.[0]?.actualizar) {
                    alpineEl._x_dataStack[0].actualizar(data);
                }

                cargarTiposTrabMemos(data);
                cargarClientesMemos(data);
                cargarSucursalesMemos(data);
                return data;
            },

            layout: "fitData",
            placeholder: "No se encontraron MEMOs enviados",
            pagination: "local",
            paginationSize: 15,
            paginationSizeSelector: [15, 25, 50],
            paginationCounter: "rows",
            columnDefaults: {
                headerHozAlign: "center",
                vertAlign: "middle",
            },
            rowHeight: 58,
            locale: "es-es",
            langs: { "es-es": esLocale },

            rowFormatter(row) {
                setRowHoverEffect(row, "rgba(59,130,246,.04)", "#3b82f6");
            },

            columns: [
                {
                    title: "#",
                    formatter: "rownum",
                    width: 60,
                    hozAlign: "center",
                    headerSort: false,
                    cssClass: "font-semibold text-default-500",
                },
                {
                    title: "Personal",
                    field: "NOMBRE_COMPLETO",
                    minWidth: 300,
                    widthGrow: 3,
                    formatter(cell) {
                        const row = cell.getRow().getData();
                        const nombre = row.NOMBRE_COMPLETO || "Sin nombre";
                        const tipo = row.TIPO_TRABAJADOR || "Sin tipo";

                        return `
                            <div style="display:flex; flex-direction:column; overflow:hidden;">
                                <span style="
                                    font-size:13px;
                                    font-weight:600;
                                    color:#111827;
                                    white-space:nowrap;
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                ">${nombre}</span>
                                <span style="font-size:11px; color:#9ca3af;">${tipo}</span>
                            </div>
                        `;
                    },
                },
                {
                    title: "DNI",
                    field: "NRO_DOCU_IDEN",
                    width: 120,
                    hozAlign: "center",
                    formatter(cell) {
                        return `
                            <span style="
                                font-family:monospace;
                                font-size:12px;
                                font-weight:700;
                                color:#374151;
                                letter-spacing:.03em;
                            ">${cell.getValue() || "—"}</span>
                        `;
                    },
                },
                {
                    title: "Sucursal",
                    field: "SUCURSAL",
                    width: 140,
                    hozAlign: "center",
                    formatter(cell) {
                        const val = cell.getValue() || "—";
                        return `
                            <span style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                padding:4px 10px;
                                border-radius:999px;
                                background:#f3f4f6;
                                color:#374151;
                                font-size:11px;
                                font-weight:600;
                            ">${val}</span>
                        `;
                    },
                },
                {
                    title: "Nivel",
                    field: "NUM_MEMO",
                    width: 150,
                    hozAlign: "center",
                    formatter(cell) {
                        const val = Number(cell.getValue());

                        let bg = "#dbeafe";
                        let color = "#1d4ed8";
                        let icon = "ti-info-circle";
                        let text = "NIVEL UNO";

                        if (val === 2) {
                            bg = "#ffedd5";
                            color = "#c2410c";
                            icon = "ti-alert-triangle";
                            text = "NIVEL DOS";
                        }

                        if (val === 3) {
                            bg = "#fee2e2";
                            color = "#b91c1c";
                            icon = "ti-bell-ringing";
                            text = "NIVEL TRES";
                        }

                        return `
                            <span style="
                                display:inline-flex;
                                align-items:center;
                                gap:6px;
                                padding:5px 12px;
                                border-radius:999px;
                                background:${bg};
                                color:${color};
                                font-size:11px;
                                font-weight:700;
                            ">
                                <i class="ti ${icon}"></i>
                                ${text}
                            </span>
                        `;
                    },
                },
                {
                    title: "Cliente",
                    field: "CLIENTE",
                    minWidth: 180,
                    widthGrow: 1,
                    hozAlign: "center",
                    headerSort: true,
                    sorter: "string",
                    formatter(cell) {
                        const val = cell.getValue();
                        if (!val) {
                            return `
                                <span style="font-size:11px; color:#9ca3af; font-style:italic;">
                                    Sin cliente
                                </span>
                            `;
                        }

                        return `
                            <span style="
                                font-size:12px;
                                color:#374151;
                                font-weight:500;
                            ">${val}</span>
                        `;
                    },
                },
                {
                    title: "Total",
                    field: "TOTAL_MEMOS",
                    width: 90,
                    hozAlign: "center",
                    formatter(cell) {
                        return `
                            <div style="display:flex; justify-content:center;">
                                <span style="
                                    min-width:32px;
                                    height:32px;
                                    padding:0 10px;
                                    border-radius:10px;
                                    background:#eef2ff;
                                    color:#4338ca;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    font-size:12px;
                                    font-weight:700;
                                ">${cell.getValue() || 0}</span>
                            </div>
                        `;
                    },
                },
                {
                    title: "Último envío",
                    field: "ULTIMO_ENVIO",
                    width: 155,
                    hozAlign: "center",
                    formatter(cell) {
                        const { fecha, hora } = formatDateTime(cell.getValue());
                        if (fecha === "—") return "—";

                        return `
                            <div style="display:flex; flex-direction:column; align-items:center; line-height:1.35;">
                                <span style="font-size:12px; font-weight:600; color:#374151;">${fecha}</span>
                                <span style="font-size:10px; color:#9ca3af;">${hora}</span>
                            </div>
                        `;
                    },
                },
            ],
        });

        window.tabulatorMemos = table;

        table.on("rowClick", (e, row) => {
            const data = row.getData();
            const nroDoc = data.NRO_DOCU_IDEN;
            const nombre = data.NOMBRE_COMPLETO;
            const nivel = Number(data.NUM_MEMO) || 1;

            const modalEl = document.getElementById("modal-memos-persona");
            const alpine = modalEl?._x_dataStack?.[0];
            if (alpine?.mostrar) {
                alpine.mostrar(nroDoc, nivel, nombre);
            }
        });

        if (elements.btnBuscarMemos) {
            elements.btnBuscarMemos.addEventListener(
                "click",
                ejecutarBusquedaMemos,
            );
        }

        if (elements.searchMemos) {
            elements.searchMemos.addEventListener("keydown", (e) => {
                if (e.key === "Enter") ejecutarBusquedaMemos();
            });
        }

        if (elements.filtroClienteMemos) {
            elements.filtroClienteMemos?.addEventListener("change", () => {
                state.memoCliente = elements.filtroClienteMemos.value;
                aplicarFiltrosMemos();
            });
        }

        if (elements.filtroSucursalMemos) {
            elements.filtroSucursalMemos.addEventListener("change", () => {
                state.memoSucursal = elements.filtroSucursalMemos.value;
                aplicarFiltrosMemos();
            });
        }

        if (elements.btnLimpiarMemos) {
            elements.btnLimpiarMemos?.addEventListener("click", () => {
                state.memoSearchTerm = "";
                state.memoCliente = "";
                state.memoNivel = null;
                state.memoSucursal = "";
                state.memoTipo = "";

                if (elements.searchMemos) elements.searchMemos.value = "";
                if (elements.filtroClienteMemos)
                    elements.filtroClienteMemos.value = "";
                if (elements.filtroSucursalMemos)
                    elements.filtroSucursalMemos.value = "";
                if (elements.filtroSucursalPersonal)
                    elements.filtroSucursalPersonal.value = "";
                if (elements.filtroTipoPersonal)
                    elements.filtroTipoPersonal.value = "";
                if (elements.filtroCargoPersonal)
                    elements.filtroCargoPersonal.value = "";
                if (elements.filtroClientePersonal)
                    elements.filtroClientePersonal.value = "";

                const alpineEl = document.querySelector(
                    '[x-data="memosStats()"]',
                );
                alpineEl?._x_dataStack?.[0] &&
                    (alpineEl._x_dataStack[0].nivelActivo = null);

                aplicarFiltrosPersonal();
                aplicarFiltrosMemos();
            });
        }
    }

    function initTablaPersonalSeguimiento() {
        const table = new Tabulator("#tblPersonalSeguimiento", {
            data: [],
            layout: "fitColumns",
            placeholder: "No se encontraron empleados",
            pagination: "local",
            paginationSize: 15,
            paginationSizeSelector: [15, 25, 50],
            paginationCounter: "rows",
            locale: "es-es",
            langs: { "es-es": esLocale },

            columns: [
                {
                    field: "seleccionar",
                    formatter(cell) {
                        const data = cell.getRow().getData();
                        const checked = personasSeleccionadasPersonal.has(
                            data.dni,
                        )
                            ? "checked"
                            : "";
                        return `<label style="display:inline-flex;align-items:center;justify-content:center;cursor:pointer;width:28px;height:28px;border-radius:10px;transition:all .15s ease;background:${checked ? "rgba(37,99,235,.1)" : "transparent"}" onclick="event.stopPropagation()">
                            <input type="checkbox" class="checkbox-personal-row" data-dni="${data.dni}" ${checked} style="width:16px;height:16px;border-radius:5px;border:2px solid ${checked ? "#3b82f6" : "#d1d5db"};background:${checked ? "#3b82f6" : "transparent"};appearance:none;-webkit-appearance:none;cursor:pointer;transition:all .15s ease;position:relative;flex-shrink:0" onchange="this.style.borderColor=this.checked?'#3b82f6':'#d1d5db';this.style.background=this.checked?'#3b82f6':'transparent';this.closest('label').style.background=this.checked?'rgba(37,99,235,.1)':'transparent'">
                        </label>`;
                    },
                    titleFormatter() {
                        return `<div style="display:inline-flex;align-items:center;justify-content:center;cursor:pointer">
                            <input type="checkbox" class="checkbox-select-all" title="Seleccionar todos" style="width:16px;height:16px;border-radius:5px;border:2px solid #d1d5db;background:transparent;appearance:none;-webkit-appearance:none;cursor:pointer;transition:all .15s ease;position:relative;flex-shrink:0" onclick="event.stopPropagation();const cb=this;const checked=cb.checked;cb.style.borderColor=checked?'#3b82f6':'#d1d5db';cb.style.background=checked?'#3b82f6':'transparent'">
                        </div>`;
                    },
                    headerSort: false,
                    width: 65,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    vertAlign: "middle",
                },
                {
                    title: "DNI",
                    field: "dni",
                    width: 110,
                    hozAlign: "center",
                    headerSort: false,
                },
                {
                    title: "Nombre Completo",
                    field: "nombre_completo",
                    minWidth: 250,
                },
                {
                    title: "Cargo",
                    field: "cargo",
                    minWidth: 200,
                    formatter(cell) {
                        return `<span class="text-xs text-gray-700">${cell.getValue() || ""}</span>`;
                    },
                },
                {
                    title: "Tipo de trabajador",
                    field: "tipo_trabajador",
                    width: 150,
                    headerSort: true,
                    formatter(cell) {
                        return `<span class="text-xs text-gray-600">${cell.getValue() || ""}</span>`;
                    },
                },
                {
                    title: "Sucursal",
                    field: "sucursal",
                    width: 130,
                    headerSort: false,
                    formatter(cell) {
                        return `<span class="text-xs text-blue-600/80">${cell.getValue() || ""}</span>`;
                    },
                },
                {
                    title: "Cliente",
                    field: "cliente",
                    minWidth: 180,
                    formatter(cell) {
                        return `<span class="text-xs text-green-700">${cell.getValue() || "SIN CLIENTE"}</span>`;
                    },
                },
                {
                    title: "Acción",
                    headerSort: false,
                    width: 80,
                    hozAlign: "center",
                    formatter() {
                        return `
                        <button class="px-2 py-1 rounded bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors text-xs font-medium">
                            <i class="ti ti-eye"></i> Ver
                        </button>
                    `;
                    },
                    cellClick(e, cell) {
                        e.stopPropagation();
                        abrirModalPersonal(cell.getRow().getData());
                    },
                },
            ],

            rowClick(e, row) {
                const el = row.getElement();
                const cb = el?.querySelector(".checkbox-personal-row");
                if (!cb) return;
                const checked = !cb.checked;
                cb.checked = checked;
                cb.style.borderColor = checked ? "#3b82f6" : "#d1d5db";
                cb.style.background = checked ? "#3b82f6" : "transparent";
                const label = cb.closest("label");
                if (label) {
                    label.style.background = checked
                        ? "rgba(37,99,235,.1)"
                        : "transparent";
                }
                const dni = cb.dataset.dni;
                if (checked) {
                    personasSeleccionadasPersonal.add(dni);
                } else {
                    personasSeleccionadasPersonal.delete(dni);
                }
                actualizarContadorSeleccionPersonal();
            },
        });

        window.tabulatorPersonal = table;

        table.on("tableBuilt", () => {
            configurarEventosCheckboxesPersonal();
        });

        const aplicarFiltrosCombinados = () => {
            state.memoSucursal = elements.filtroSucursalPersonal?.value || "";
            state.memoTipo = elements.filtroTipoPersonal?.value || "";
            aplicarFiltrosPersonal();
            aplicarFiltrosMemos();
        };

        if (elements.filtroSucursalPersonal) {
            elements.filtroSucursalPersonal.addEventListener(
                "change",
                aplicarFiltrosCombinados,
            );
        }

        if (elements.filtroTipoPersonal) {
            elements.filtroTipoPersonal.addEventListener(
                "change",
                aplicarFiltrosCombinados,
            );
        }

        if (elements.filtroCargoPersonal) {
            elements.filtroCargoPersonal.addEventListener(
                "change",
                aplicarFiltrosCombinados,
            );
        }

        if (elements.filtroClientePersonal) {
            elements.filtroClientePersonal.addEventListener(
                "change",
                aplicarFiltrosCombinados,
            );
        }

        const btnLimpiarPersonal = document.getElementById(
            "btnLimpiarFiltroPersonal",
        );
        if (btnLimpiarPersonal) {
            btnLimpiarPersonal.addEventListener("click", () => {
                if (elements.filtroSucursalPersonal)
                    elements.filtroSucursalPersonal.value = "";
                if (elements.filtroTipoPersonal)
                    elements.filtroTipoPersonal.value = "";
                if (elements.filtroCargoPersonal)
                    elements.filtroCargoPersonal.value = "";
                if (elements.filtroClientePersonal)
                    elements.filtroClientePersonal.value = "";

                const searchEl = document.querySelector(
                    '[x-data="searchPersonalSeguimiento()"]',
                );
                if (searchEl) {
                    const data = searchEl._x_dataStack?.[0];
                    if (data) data.query = "";
                }

                const filtrosEl = document.querySelector(
                    '[x-data="filtrosPersonal()"]',
                );
                if (filtrosEl) {
                    const data = filtrosEl._x_dataStack?.[0];
                    if (data && data.verificar) data.verificar();
                }

                aplicarFiltrosPersonal();
            });
        }
    }

    function buscarPersonalSeguimiento() {
        axios
            .get(`${VITE_URL_APP}/api/obtener-personal`)
            .then((res) => {
                if (res.data.success && window.tabulatorPersonal) {
                    const personal = res.data.personal || [];

                    window.tabulatorPersonal.setData(personal);

                    cargarSucursalesPersonal(personal);
                    cargarCargosPersonal(personal);
                    cargarClientesPersonal(personal);
                    cargarTiposPersonal(personal);
                    aplicarFiltrosPersonal();
                }
            })
            .catch((err) => console.error("Error al cargar personal:", err));
    }

    if (elements.searchPersonal) {
        let debounceTimer;
        elements.searchPersonal.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                buscarPersonalSeguimiento(this.value.trim());
            }, 400);
        });
    }

    window.__memoState = state;
    window.__aplicarFiltrosMemos = aplicarFiltrosMemos;

    initTablaCursosSeguimiento();
    initTablaMemosEnviados();
    initTablaPersonalSeguimiento();

    buscarPersonalSeguimiento("");

    const btnDeshacerSeleccionPersonal = document.getElementById(
        "btnDeshacerSeleccionPersonal",
    );
    if (btnDeshacerSeleccionPersonal) {
        btnDeshacerSeleccionPersonal.addEventListener(
            "click",
            limpiarSeleccionPersonal,
        );
    }

    const btnEnviarMemosPersonal = document.getElementById(
        "btnEnviarMemosPersonal",
    );
    if (btnEnviarMemosPersonal) {
        btnEnviarMemosPersonal.addEventListener("click", () => {
            const seleccionados = window.personalSeleccionado();
            if (seleccionados.length === 0) return;

            const personales = seleccionados.map((p) => ({
                nroDoc: p.dni,
                nombreCompleto: p.nombre_completo,
                correo: p.email,
                cargo: p.cargo,
            }));

            Swal.fire({
                title: "Enviando MEMOs...",
                text: "Por favor espere",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            axios
                .post(`${VITE_URL_APP}/api/enviar-memos-varios`, { personales })
                .then((res) => {
                    if (res.data.success) {
                        if (window.tabulatorMemos) {
                            window.tabulatorMemos.setData(
                                `${VITE_URL_APP}/api/obtener-memos-enviados`,
                            );
                        }
                        limpiarSeleccionPersonal();
                        Swal.fire({
                            icon: "success",
                            title: "MEMOs enviados",
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text:
                                res.data.message ||
                                "No se pudieron enviar los MEMOs",
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "No se pudieron enviar los MEMOs",
                    });
                });
        });
    }
});
