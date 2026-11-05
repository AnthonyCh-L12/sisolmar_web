import { TabulatorFull as Tabulator } from "tabulator-tables";
import "tabulator-tables/dist/css/tabulator_simple.min.css";

document.addEventListener("DOMContentLoaded", () => {
    const elTabla = document.getElementById("tblCursosNew");
    if (!elTabla) return;

    const elements = {
        search: document.getElementById("buscarCursoNew"),
        filtroPlan: document.getElementById("filtroPlanCursoNew"),
        filtroTipo: document.getElementById("filtroTipoCursoNew"),
        filtroAnio: document.getElementById("filtroAnioCursoNew"),
        filtroDesde: document.getElementById("filtroDesdeCursoNew"),
        filtroHasta: document.getElementById("filtroHastaCursoNew"),
        btnLimpiar: document.getElementById("btnLimpiarFiltrosCursosNew"),
        statTotal: document.getElementById("statTotalCursos"),
    };

    const state = { term: "", plan: "", tipo: "", anio: "", desde: "", hasta: "" };

    const esLocale = {
        pagination: {
            page_size: "Filas por página",
            first: "Primero",
            last: "Último",
            prev: "Anterior",
            next: "Siguiente",
            counter: { showing: "Mostrando", of: "de", rows: "filas", pages: "páginas" },
        },
        data: { loading: "Cargando...", error: "Error al cargar los datos" },
    };

    const estilosTipo = {
        INDUCCIÓN: "bg-sky-500/10 text-sky-600",
        CHARLA: "bg-amber-500/10 text-amber-600",
        CAPACITACIÓN: "bg-primary/10 text-primary",
        ENTRENAMIENTO: "bg-emerald-500/10 text-emerald-600",
        "SIMULACROS DE EMERGENCIA": "bg-rose-500/10 text-rose-600",
    };

    const esc = (v) => String(v ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

    // Fecha creación: API envía "dd/mm/YYYY" (display) + "YYYY-MM-DD" (ISO). Normalizar a ISO para filtrar/ordenar.
    function fechaAISO(v) {
        if (!v) return "";
        const s = String(v).trim();
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
        const m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (m) return `${m[3]}-${String(m[2]).padStart(2, "0")}-${String(m[1]).padStart(2, "0")}`;
        return "";
    }

    function anioDe(data) {
        const iso = data.CURS_CREADO_FECHA_ISO || fechaAISO(data.CURS_CREADO_FECHA);
        return iso ? iso.slice(0, 4) : "";
    }

    // ── DISPATCHERS (Desacoplados para comunicarse con el archivo de modales) ──
    window.solicitarEdicionCursoNew = (cod) => {
        if (!cod) return;
        window.__pendingEdicionNew = String(cod);
        window.dispatchEvent(new CustomEvent("open-modal-edicion-new", { detail: { codigo: String(cod) } }));
    };

    window.solicitarAperturaCursoNew = (cod) => {
        if (!cod || typeof window.abrirModalAperturaNew !== "function") {
            if (!cod) return;
            window.__pendingAperturaNew = String(cod);
            window.dispatchEvent(new CustomEvent("open-modal-apertura-new", { detail: { codigo: String(cod) } }));
            return;
        }
        window.abrirModalAperturaNew(cod);
    };

    window.solicitarAplazarCursoNew = (cod) => {
        if (!cod || typeof window.abrirModalAplazarNew !== "function") {
            if (!cod) return;
            window.__pendingAplazarNew = String(cod);
            window.dispatchEvent(new CustomEvent("open-modal-aplazar-new", { detail: { codigo: String(cod) } }));
            return;
        }
        window.abrirModalAplazarNew(cod);
    };

    // NUEVO: Dispatcher para Subir al AV
    window.solicitarSubirCursoAV = (cod) => {
        if (!cod) return;
        window.dispatchEvent(new CustomEvent("subir-curso-av", { detail: { codigo: String(cod) } }));
    };

    // ── BOTONES DE ACCIÓN ──
    function botonesAccionVisual(cod, tieneVigente, subidoAV) {
        const safeCod = String(cod ?? "").replace(/'/g, "\\'");
        const bloqueado = "btn btn-sm rounded bg-gray-100/50 text-gray-400 cursor-not-allowed";
        
        // 1. Editar
        const btnEditar = tieneVigente
            ? `<button type="button" disabled title="No se puede editar: tiene un periodo VIGENTE activo"
                class="${bloqueado}">
                <i class="bx bxs-edit text-base pointer-events-none"></i>
            </button>`
            : `<button type="button" title="Editar curso" onclick="window.solicitarEdicionCursoNew('${safeCod}')"
                class="btn btn-sm rounded bg-info/10 text-info hover:bg-info hover:text-white transition-colors">
                <i class="bx bxs-edit text-base pointer-events-none"></i>
            </button>`;
            
        // 2. Aperturar (NUEVA LÓGICA AQUÍ)
        const puedeAperturar = !tieneVigente && subidoAV;
        
        // Definimos el mensaje de error según el motivo de bloqueo
        let motivoBloqueoApertura = "No disponible";
        if (tieneVigente) {
            motivoBloqueoApertura = "Ya tiene un periodo VIGENTE activo";
        } else if (!subidoAV) {
            motivoBloqueoApertura = "El curso debe subirse al AV antes de aperturarse";
        }

        const btnAperturar = puedeAperturar
            ? `<button type="button" title="Aperturar curso" onclick="window.solicitarAperturaCursoNew('${safeCod}')"
                class="btn btn-sm rounded bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors">
                <i class="bx bx-calendar-star text-base pointer-events-none"></i>
            </button>`
            : `<button type="button" disabled title="${motivoBloqueoApertura}"
                class="${bloqueado}">
                <i class="bx bx-calendar-star text-base pointer-events-none"></i>
            </button>`;
            
        // 3. Aplazar
        const btnAplazar = tieneVigente
            ? `<button type="button" title="Extender plazo del curso" onclick="window.solicitarAplazarCursoNew('${safeCod}')"
                class="btn btn-sm rounded bg-success/10 text-success hover:bg-success hover:text-white transition-colors">
                <i class="bx bx-time-five text-base pointer-events-none"></i>
            </button>`
            : `<button type="button" disabled title="Solo disponible con un periodo VIGENTE activo"
                class="${bloqueado}">
                <i class="bx bx-time-five text-base pointer-events-none"></i>
            </button>`;

        // 4. Subir al AV (Se mantiene igual: bloqueado si ya se subió)
        const btnSubirAV = subidoAV
            ? `<button type="button" disabled title="Curso ya subido al AV"
                class="${bloqueado}">
                <i class="bx bx-cloud-upload text-base pointer-events-none"></i>
            </button>`
            : `<button type="button" title="Subir curso al AV" onclick="window.solicitarSubirCursoAV('${safeCod}')"
                class="btn btn-sm rounded bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-colors">
                <i class="bx bx-cloud-upload text-base pointer-events-none"></i>
            </button>`;

        // 5. Deshabilitar
        const btnEliminar = tieneVigente
            ? `<button type="button" disabled title="No se puede deshabilitar: tiene un periodo VIGENTE activo"
                class="${bloqueado}">
                <i class="bx bx-trash text-base pointer-events-none"></i>
            </button>`
            : `<button type="button" title="Deshabilitar curso (próximamente)"
                class="btn btn-sm rounded bg-danger/10 text-danger hover:bg-danger hover:text-white transition-colors">
                <i class="bx bx-trash text-base pointer-events-none"></i>
            </button>`;
            
        return `
        <div class="flex items-center justify-center gap-1.5">
            ${btnEditar}
            ${btnAperturar}
            ${btnAplazar}
            ${btnSubirAV}
            ${btnEliminar}
        </div>`;
    }

    const table = new Tabulator("#tblCursosNew", {
        ajaxURL: `${VITE_URL_APP}/api/obtener-cursos-new`,
        ajaxResponse(_url, _params, res) {
            const rows = Array.isArray(res) ? res : (res?.data ?? []);
            if (elements.statTotal) {
                const total = res?.total ?? rows.length;
                elements.statTotal.textContent = total;
            }
            return rows;
        },
        layout: "fitDataFill",
        placeholder: "No se encontraron cursos",
        pagination: "local",
        paginationSize: 5,
        paginationSizeSelector: [5, 15, 25, 50],
        paginationCounter: "rows",
        locale: "es-es",
        langs: { "es-es": esLocale },
        initialSort: [{ column: "CURS_CREADO_FECHA", dir: "desc" }],
        rowFormatter(row) {
            row.getElement().style.cursor = "pointer";
            row.getElement().setAttribute("title", "Clic para editar el curso");
        },
        rowClick(e, row) {
            // Los botones de acción tienen su propio onclick; el clic en fila también abre edición
            if (e?.target?.closest?.("button")) return;
            window.solicitarEdicionCursoNew(row.getData()?.CURS_COD);
        },
        columns: [
            {
                title: "Nombre de curso",
                field: "CURS_NOMBRE",
                minWidth: 250,
                headerSort: true,
                formatter: (cell) =>
                    `<span style="font-weight:500; color:#111827;">${esc(cell.getValue()) || "—"}</span>`,
            },
            {
                title: "Tipo",
                field: "CURS_TIPO",
                width: 200,
                headerSort: true,
                headerHozAlign: "center",
                hozAlign: "center",
                formatter(cell) {
                    const v = cell.getValue() || "—";
                    const cls = estilosTipo[v] || "bg-gray-500/10 text-gray-600";
                    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ${cls}">${esc(v)}</span>`;
                },
            },
            {
                title: "Plan",
                field: "CURS_PLAN_CAPAC_NOMBRE",
                width: 110,
                hozAlign: "center",
                headerHozAlign: "center",
                headerSort: true,
                formatter: (cell) =>
                    `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">${esc(cell.getValue()) || "—"}</span>`,
            },
            {
                title: "Sist. gestión",
                field: "CURS_SIST_GESTION_NOMBRE",
                width: 130,
                hozAlign: "center",
                headerSort: true,
                formatter: (cell) =>
                    `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-500/10 text-indigo-600">${esc(cell.getValue()) || "—"}</span>`,
            },
            {
                title: "Creación",
                field: "CURS_CREADO_FECHA",
                width: 130,
                hozAlign: "center",
                headerHozAlign: "center",
                headerSort: true,
                sorter: (a, b, aRow, bRow) => {
                    const isoA = aRow.getData().CURS_CREADO_FECHA_ISO || fechaAISO(a);
                    const isoB = bRow.getData().CURS_CREADO_FECHA_ISO || fechaAISO(b);
                    return String(isoA || "").localeCompare(String(isoB || ""));
                },
                formatter: (cell) =>
                    `<span style="font-size:12px; font-weight:600; color:#374151;">${esc(cell.getValue()) || "—"}</span>`,
            },
            {
                title: "Acciones",
                field: "ACCIONES",
                hozAlign: "center",
                headerHozAlign: "center",
                headerSort: false,
                formatter: (cell) => {
                    const d = cell.getData() || {};
                    // ✅ Le pasamos el tercer parámetro d.CURS_SUBIDO_AV === true
                    return botonesAccionVisual(d.CURS_COD, d.CURS_TIENE_VIGENTE === true, d.CURS_SUBIDO_AV === true);
                },
            },
        ],
    });

    window.tablaCursosNew = table;

    table.on("dataLoaded", (rows) => {
        const uniq = (arr) => [...new Set(arr.filter(Boolean))].sort();

        if (elements.filtroPlan) {
            const actual = elements.filtroPlan.value;
            elements.filtroPlan.innerHTML =
                `<option value="">Todos los planes</option>` +
                uniq(rows.map((r) => r.CURS_PLAN_CAPAC_NOMBRE))
                    .map((v) => `<option value="${esc(v)}">${esc(v)}</option>`)
                    .join("");
            elements.filtroPlan.value = actual;
        }
        if (elements.filtroTipo) {
            const actual = elements.filtroTipo.value;
            elements.filtroTipo.innerHTML =
                `<option value="">Todos los tipos</option>` +
                uniq(rows.map((r) => r.CURS_TIPO))
                    .map((v) => `<option value="${esc(v)}">${esc(v)}</option>`)
                    .join("");
            elements.filtroTipo.value = actual;
        }
        if (elements.filtroAnio) {
            const actual = elements.filtroAnio.value;
            const anios = [...new Set(rows.map(anioDe).filter(Boolean))].sort().reverse();
            elements.filtroAnio.innerHTML =
                `<option value="">Todos los años</option>` +
                anios.map((v) => `<option value="${esc(v)}">${esc(v)}</option>`).join("");
            if (actual && anios.includes(actual)) elements.filtroAnio.value = actual;
        }
    });

    function aplicarFiltros() {
        const { term, plan, tipo, anio, desde, hasta } = state;
        if (term || plan || tipo || anio || desde || hasta) {
            table.setFilter((data) => {
                if (plan && (data.CURS_PLAN_CAPAC_NOMBRE || "") !== plan) return false;
                if (tipo && (data.CURS_TIPO || "") !== tipo) return false;
                if (anio && anioDe(data) !== anio) return false;
                if (desde || hasta) {
                    const iso = data.CURS_CREADO_FECHA_ISO || fechaAISO(data.CURS_CREADO_FECHA);
                    if (!iso) return false;
                    if (desde && iso < desde) return false;
                    if (hasta && iso > hasta) return false;
                }
                if (term) {
                    const nom = String(data.CURS_NOMBRE || "").toLowerCase();
                    if (!nom.includes(term)) return false;
                }
                return true;
            });
        } else {
            table.clearFilter();
        }
    }

    let searchT;
    elements.search?.addEventListener("input", function () {
        clearTimeout(searchT);
        searchT = setTimeout(() => {
            state.term = (this.value || "").trim().toLowerCase();
            aplicarFiltros();
        }, 250);
    });
    elements.filtroPlan?.addEventListener("change", function () {
        state.plan = this.value;
        aplicarFiltros();
    });
    elements.filtroTipo?.addEventListener("change", function () {
        state.tipo = this.value;
        aplicarFiltros();
    });
    elements.filtroAnio?.addEventListener("change", function () {
        state.anio = this.value;
        aplicarFiltros();
    });
    elements.filtroDesde?.addEventListener("change", function () {
        state.desde = this.value;
        aplicarFiltros();
    });
    elements.filtroHasta?.addEventListener("change", function () {
        state.hasta = this.value;
        aplicarFiltros();
    });
    elements.btnLimpiar?.addEventListener("click", () => {
        state.term = "";
        state.plan = "";
        state.tipo = "";
        state.anio = "";
        state.desde = "";
        state.hasta = "";
        if (elements.search) elements.search.value = "";
        if (elements.filtroPlan) elements.filtroPlan.value = "";
        if (elements.filtroTipo) elements.filtroTipo.value = "";
        if (elements.filtroAnio) elements.filtroAnio.value = "";
        if (elements.filtroDesde) elements.filtroDesde.value = "";
        if (elements.filtroHasta) elements.filtroHasta.value = "";
        table.clearFilter();
    });

});