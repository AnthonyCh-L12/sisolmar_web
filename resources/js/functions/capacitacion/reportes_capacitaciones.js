import axios from "axios";
import ExcelJS from "exceljs";
import { saveAs } from "file-saver";

function _assetUrl(path) {
    const env = typeof APP_ENV !== "undefined" ? APP_ENV : (typeof window !== "undefined" ? window.APP_ENV : undefined);
    // local => raíz del sitio; cualquier otro/fallback => prefijo /sisolmar (producción)
    const base = env === "local" ? "" : "/sisolmar";
    return `${base}${path}`;
}

async function _cargarLogoExcel(workbook) {
    try {
        const response = await fetch(_assetUrl("/images/logo_sol.png"));
        const arrayBuffer = await response.arrayBuffer();
        return workbook.addImage({ buffer: arrayBuffer, extension: "png" });
    } catch (e) {
        console.error("Error cargando logo:", e);
        return null;
    }
}

function _estiloEncabezadoExcel(cell) {
    cell.font = { bold: true, color: { argb: "FFFFFFFF" } };
    cell.fill = {
        type: "pattern",
        pattern: "solid",
        fgColor: { argb: "FF1F4E79" },
    };
    cell.alignment = { vertical: "middle", horizontal: "center" };
    cell.border = {
        top: { style: "thin" },
        left: { style: "thin" },
        bottom: { style: "thin" },
        right: { style: "thin" },
    };
}

function _estiloDatoExcel(cell) {
    cell.border = {
        top: { style: "thin" },
        left: { style: "thin" },
        bottom: { style: "thin" },
        right: { style: "thin" },
    };
    cell.alignment = { vertical: "middle", horizontal: "left", wrapText: true };
}

function _blobExcel(buffer) {
    return new Blob([buffer], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
}

function _cargarImagen(url) {
    return new Promise((resolve) => {
        if (!url) {
            resolve(null);
            return;
        }
        const img = new Image();
        img.onload = () =>
            resolve(
                img.naturalWidth > 0 && img.naturalHeight > 0 ? img : null,
            );
        img.onerror = () => resolve(null);
        img.src = url;
    });
}

function _imagenAPngDataUrl(img) {
    if (!img) return null;
    try {
        const canvas = document.createElement("canvas");
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        const ctx = canvas.getContext("2d");
        if (!ctx) return null;
        ctx.drawImage(img, 0, 0);
        return canvas.toDataURL("image/png");
    } catch (e) {
        return null;
    }
}

async function _cargarFirma(url) {
    if (!url) return null;
    try {
        const respuesta = await fetch(url);
        if (!respuesta.ok) return null;
        let buffer = await respuesta.arrayBuffer();
        let bytes = new Uint8Array(buffer);

        // Buscar el marcador JPEG real (FF D8 FF) y descartar basura previa
        const jpegStart = bytes.findIndex(
            (b, i) =>
                b === 0xff && bytes[i + 1] === 0xd8 && bytes[i + 2] === 0xff,
        );
        if (jpegStart > 0) {
            bytes = bytes.slice(jpegStart);
        }

        const blob = new Blob([bytes], { type: "image/jpeg" });

        const dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });

        const img = await new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = reject;
            image.src = dataUrl;
        });

        const canvas = document.createElement("canvas");
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0);

        const pngDataUrl = canvas.toDataURL("image/png");
        const pngBytes = Uint8Array.from(atob(pngDataUrl.split(",")[1]), (c) =>
            c.charCodeAt(0),
        );

        return {
            src: pngBytes,
            format: "PNG",
            width: img.naturalWidth,
            height: img.naturalHeight,
        };
    } catch (e) {
        console.error("Error cargando firma:", e);
        return null;
    }
}

let _catalogosCapacitacionPromise = null;

async function _fetchCatalogosCapacitacion() {
    if (!_catalogosCapacitacionPromise) {
        _catalogosCapacitacionPromise = axios
            .get(`${VITE_URL_APP}/api/catalogos-capacitacion`)
            .then(({ data }) => data)
            .catch((error) => {
                _catalogosCapacitacionPromise = null;
                throw error;
            });
    }

    return _catalogosCapacitacionPromise;
}

async function _fetchSistemas() {
    const data = await _fetchCatalogosCapacitacion();
    return Array.isArray(data?.sistemas) ? data.sistemas : [];
}

async function _fetchAreas() {
    const data = await _fetchCatalogosCapacitacion();
    return Array.isArray(data?.areas) ? data.areas : [];
}

async function _fetchSucursales() {
    const { data } = await axios.get(`${VITE_URL_APP}/api/get-sucursales`);
    return data.success ? data.sucursales : [];
}

async function _fetchPersonales() {
    const { data } = await axios.get(`${VITE_URL_APP}/api/obtener-personal`);
    return data.success ? data.personal : [];
}

function _ordenarPersonal(datos, columna, direccion) {
    if (columna && direccion) {
        datos.sort((a, b) => {
            if (columna === "Nota_Final") {
                const valA = parseFloat(a[columna]) || 0;
                const valB = parseFloat(b[columna]) || 0;
                return direccion === "asc" ? valA - valB : valB - valA;
            }
            const valA = (a[columna] || "").toString();
            const valB = (b[columna] || "").toString();
            const cmp = valA.localeCompare(valB, "es", { sensitivity: "base" });
            return direccion === "asc" ? cmp : -cmp;
        });
    } else {
        datos.sort((a, b) =>
            (a.NombreCompleto || "").localeCompare(
                b.NombreCompleto || "",
                "es",
                { sensitivity: "base" },
            ),
        );
    }
    return datos;
}

function _ordenarCursosConFechas(datos, columna, direccion, obtenerValor) {
    if (columna && direccion) {
        datos.sort((a, b) => {
            if (columna === "FechaInicio" || columna === "FechaFin") {
                const va = obtenerValor(a, columna);
                const vb = obtenerValor(b, columna);
                const na = va == null ? -Infinity : va;
                const nb = vb == null ? -Infinity : vb;
                return (direccion === "asc" ? 1 : -1) * (na - nb);
            }
            const valA = obtenerValor(a, columna);
            const valB = obtenerValor(b, columna);
            const cmp = valA.localeCompare(valB, "es", {
                sensitivity: "base",
            });
            return direccion === "asc" ? cmp : -cmp;
        });
    } else {
        datos.sort((a, b) =>
            (a.NombreCurso || "").localeCompare(b.NombreCurso || "", "es", {
                sensitivity: "base",
            }),
        );
    }
    return datos;
}

let _catalogsPromise = null;
async function ensureCatalogsLoaded() {
    if (!_catalogsPromise) {
        _catalogsPromise = (async () => {
            try {
                const [
                    sucursalesRes,
                    catalogos,
                    cursosRes,
                    personalesRes,
                    empresasRes,
                    personalTodasEmpresasRes,
                ] = await Promise.all([
                    axios.get(`${VITE_URL_APP}/api/get-sucursales`),
                    _fetchCatalogosCapacitacion(),
                    axios.get(
                        `${VITE_URL_APP}/api/reportes-capacitaciones/cursos`,
                    ),
                    axios.get(`${VITE_URL_APP}/api/obtener-personal`),
                    axios.get(`${VITE_URL_APP}/api/get-empresas`),
                    axios.get(
                        `${VITE_URL_APP}/api/obtener-personal-todas-empresas`,
                    ),
                ]);

                const personalesTodas =
                    personalTodasEmpresasRes.data.success &&
                    Array.isArray(personalTodasEmpresasRes.data.personal)
                        ? personalTodasEmpresasRes.data.personal
                        : [];
                const tipos = [
                    ...new Set(
                        personalesTodas
                            .map((p) => p.tipo_trabajador)
                            .filter(Boolean),
                    ),
                ];
                const cargos = [
                    ...new Set(
                        personalesTodas.map((p) => p.cargo).filter(Boolean),
                    ),
                ];

                return {
                    sucursales: sucursalesRes.data.success
                        ? sucursalesRes.data.sucursales
                        : [],
                    sistemas: Array.isArray(catalogos?.sistemas)
                        ? catalogos.sistemas
                        : [],
                    areas: Array.isArray(catalogos?.areas)
                        ? catalogos.areas
                        : [],
                    cursos: cursosRes.data.success
                        ? cursosRes.data.Cursos || []
                        : [],
                    personales:
                        personalesRes.data.success &&
                        Array.isArray(personalesRes.data.personal)
                            ? personalesRes.data.personal
                            : [],
                    empresas: Array.isArray(empresasRes.data)
                        ? empresasRes.data
                        : [],
                    personalesTodasEmpresas: personalesTodas,
                    tiposTrabajo: tipos.sort(),
                    cargosReporte: cargos.sort(),
                };
            } catch (e) {
                console.error("Error cargando catálogos:", e);
                return {
                    sucursales: [],
                    sistemas: [],
                    areas: [],
                    cursos: [],
                    personales: [],
                    empresas: [],
                    personalesTodasEmpresas: [],
                    tiposTrabajo: [],
                    cargosReporte: [],
                };
            }
        })();
    }
    return _catalogsPromise;
}

/* -------------------------------------------------------------------------
 * Importación de DNIs desde Excel
 * Formato esperado: columna A = Nro, columna B = DNI, fila 1 = encabezados.
 * ---------------------------------------------------------------------- */

const MAX_DNIS_IMPORTACION = 5000;
const MAX_TAMANO_XLSX_MB = 5;

// Bandera compartida para que los modales de reporte no se cierren con Escape
// mientras el modal de importación está abierto.
let _importarDnisAbierto = false;

function _esperarPintado() {
    return new Promise((resolve) => setTimeout(resolve, 30));
}

function _valorCeldaExcel(cell) {
    const valor = cell?.value;
    if (valor === null || valor === undefined) return "";

    if (typeof valor === "object") {
        if (Array.isArray(valor.richText)) {
            return valor.richText.map((t) => t.text || "").join("");
        }
        if (Object.prototype.hasOwnProperty.call(valor, "text")) {
            return String(valor.text ?? "");
        }
        if (Object.prototype.hasOwnProperty.call(valor, "result")) {
            return String(valor.result ?? "");
        }
        if (valor instanceof Date) {
            return valor.toISOString().slice(0, 10);
        }
        return "";
    }

    return String(valor);
}

function _normalizarEncabezadoExcel(valor) {
    return String(valor || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, "");
}

function _normalizarDniExcel(valor) {
    if (valor === null || valor === undefined) return { dni: "", motivo: "" };

    const texto = String(valor).trim();
    if (!texto) return { dni: "", motivo: "" };

    if (/^\d+\.\d+$/.test(texto) && !/\.0+$/.test(texto)) {
        return { dni: "", motivo: "Número con decimales" };
    }
    if (/[eE][+-]?\d+$/.test(texto)) {
        return { dni: "", motivo: "Notación científica" };
    }

    const limpio = texto.replace(/\.0+$/, "").replace(/[\s.\-]/g, "");

    if (!/^\d+$/.test(limpio)) {
        return { dni: "", motivo: "Contiene caracteres no numéricos" };
    }
    if (limpio.length < 6 || limpio.length > 15) {
        return { dni: "", motivo: "Longitud inválida" };
    }

    return { dni: limpio, motivo: "" };
}

async function _extraerDnisExcel(file) {
    if (!/\.xlsx$/i.test(file.name)) {
        throw new Error("El archivo debe tener formato .xlsx.");
    }
    if (file.size > MAX_TAMANO_XLSX_MB * 1024 * 1024) {
        throw new Error(
            `El archivo supera el límite de ${MAX_TAMANO_XLSX_MB} MB.`,
        );
    }

    const workbook = new ExcelJS.Workbook();
    await workbook.xlsx.load(await file.arrayBuffer());

    const hoja =
        workbook.worksheets.find((ws) => ws.rowCount > 1) ||
        workbook.worksheets[0];

    if (!hoja || hoja.rowCount < 2) {
        throw new Error(
            "El archivo no contiene datos. Se esperan encabezados en la fila 1 y DNIs desde la fila 2.",
        );
    }

    const advertencias = [];
    const encabezadoB = _normalizarEncabezadoExcel(
        _valorCeldaExcel(hoja.getCell(1, 2)),
    );

    if (!encabezadoB.includes("DNI") && !encabezadoB.includes("DOC")) {
        advertencias.push(
            'La celda B1 no tiene el encabezado "DNI". Se leerán igualmente los valores de la columna B.',
        );
    }

    const dnis = [];
    const vistos = new Set();
    const invalidos = [];
    const duplicados = [];
    const ultimaFila = hoja.rowCount;

    for (let fila = 2; fila <= ultimaFila; fila++) {
        const crudo = _valorCeldaExcel(hoja.getCell(fila, 2));
        if (!String(crudo).trim()) continue;

        const { dni, motivo } = _normalizarDniExcel(crudo);

        if (!dni) {
            invalidos.push({
                fila,
                valor: String(crudo).trim(),
                motivo: motivo || "Formato inválido",
            });
            continue;
        }

        if (vistos.has(dni)) {
            duplicados.push({ fila, valor: dni });
            continue;
        }

        if (dnis.length >= MAX_DNIS_IMPORTACION) {
            throw new Error(
                `El archivo supera el máximo de ${MAX_DNIS_IMPORTACION} DNIs.`,
            );
        }

        vistos.add(dni);
        dnis.push(dni);
    }

    return {
        dnis,
        invalidos,
        duplicados,
        advertencias,
        totalFilas: Math.max(0, ultimaFila - 1),
    };
}

export default document.addEventListener("alpine:init", () => {
    Alpine.data("reportesApp", () => ({
        init() {
            ensureCatalogsLoaded();
        },
        abrirModalReporte() {
            window.dispatchEvent(new CustomEvent("abrir-reporte"));
        },
        abrirModalCursosArea() {
            window.dispatchEvent(new CustomEvent("abrir-cursos-area"));
        },
        abrirModalRecordPersonal() {
            window.dispatchEvent(new CustomEvent("abrir-record-personal"));
        },
        abrirModalHistorial() {
            window.dispatchEvent(new CustomEvent("abrir-historial-reportes"));
        },
        abrirModalReporteGeneral() {
            window.dispatchEvent(new CustomEvent("abrir-reporte-general"));
        },
        abrirModalReporteFormato() {
            window.dispatchEvent(new CustomEvent("abrir-reporte-formato"));
        },
    }));

    const _rankEstado = (estado) => {
        const orden = {
            APROBADO: 0,
            DESAPROBADO: 1,
            "EN CURSO": 2,
            "SIN ACCEDER": 3,
            "NO MATRICULADO": 4,
        };
        return orden[estado] ?? 4;
    };

    Alpine.data("modalReportePorCapacitacion", () => ({
        open: false,
        view: "filters",

        courseIds: [],
        searchCurso: "",
        selectedPeriodo: "",
        selectedFechaInicio: "",
        selectedFechaFin: "",
        selectedEstado: 0,
        selectedSucursal: "",
        personalVigente: "1",

        cursos: [],
        todosLosCursos: [],
        periodos: [],
        fechasInicio: [],
        fechasFin: [],
        sucursales: [],

        personal: [],
        totalPersonal: 0,
        loadingPersonal: false,
        loadingCursos: false,
        loadingSucursales: false,

        currentPage: 1,
        perPage: 20,

        sortColumn: null,
        sortDirection: null,

        exportandoExcel: false,
        exportandoPDF: false,

        selectedCursoIndex: -1,
        selectedSucursalIdx: 0,

        cacheReportes: {},
        _cursosExportData: [],

        async init() {
            window.addEventListener("abrir-reporte", () => {
                this.abrir();
            });

            this.loadingCursos = true;
            this.loadingSucursales = true;

            const catalogs = await ensureCatalogsLoaded();

            this.sucursales = catalogs.sucursales;
            this.todosLosCursos = [...catalogs.cursos];
            this.cursos = [...catalogs.cursos];

            this.loadingCursos = false;
            this.loadingSucursales = false;
        },

        async cargarSucursales() {
            this.loadingSucursales = true;
            try {
                const response = await axios.get(
                    `${VITE_URL_APP}/api/get-sucursales`,
                );
                if (response.data.success) {
                    this.sucursales = response.data.sucursales;
                }
            } catch (error) {
                console.error(error);
                this.sucursales = [];
            } finally {
                this.loadingSucursales = false;
            }
        },

        formatearFecha(valor) {
            if (!valor || valor <= 0) {
                return null;
            }

            let fecha;
            if (typeof valor === "number" || /^\d+$/.test(valor)) {
                fecha = new Date(Number(valor) * 1000);
            } else {
                fecha = new Date(
                    valor.includes("T") ? valor : valor.replace(" ", "T"),
                );
            }

            if (isNaN(fecha.getTime())) {
                return null;
            }

            const dia = String(fecha.getDate()).padStart(2, "0");
            const mes = String(fecha.getMonth() + 1).padStart(2, "0");
            const anio = fecha.getFullYear();

            return `${dia}/${mes}/${anio}`;
        },

        obtenerPeriodo(curso) {
            const creacion = this.formatearFecha(curso.Fecha_Creacion);

            if (!creacion) {
                return null;
            }

            return `${creacion}`;
        },

        obtenerFecha(timestamp) {
            return this.formatearFecha(timestamp);
        },

        async cargarCursos() {
            this.loadingCursos = true;
            try {
                const response = await axios.get(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/cursos`,
                );

                if (response.data.success) {
                    this.todosLosCursos = [...response.data.Cursos];
                    this.filtrarCursosPorFecha();
                } else {
                    this.cursos = [];
                    this.todosLosCursos = [];
                }
            } catch (error) {
                console.error(error);

                this.cursos = [];
                this.todosLosCursos = [];
            } finally {
                this.loadingCursos = false;
            }
        },

        formatearFechaISO(valor) {
            if (!valor || valor <= 0) return null;
            let fecha;
            if (typeof valor === "number" || /^\d+$/.test(valor)) {
                fecha = new Date(Number(valor) * 1000);
            } else {
                fecha = new Date(
                    valor.includes("T") ? valor : valor.replace(" ", "T"),
                );
            }
            if (isNaN(fecha.getTime())) return null;
            const dia = String(fecha.getDate()).padStart(2, "0");
            const mes = String(fecha.getMonth() + 1).padStart(2, "0");
            const anio = fecha.getFullYear();
            return `${anio}-${mes}-${dia}`;
        },

        filtrarCursosPorFecha() {
            this.cursos = this.todosLosCursos.filter((curso) => {
                const fechaCreacion = this.formatearFechaISO(
                    curso.Fecha_Creacion,
                );

                if (
                    this.selectedFechaInicio &&
                    fechaCreacion < this.selectedFechaInicio
                ) {
                    return false;
                }

                if (
                    this.selectedFechaFin &&
                    fechaCreacion > this.selectedFechaFin
                ) {
                    return false;
                }

                return true;
            });

            this.courseIds = [];
        },

        abrir() {
            this.open = true;
        },

        cerrar() {
            this.open = false;
            this.view = "filters";

            this.courseIds = [];
            this.selectedPeriodo = "";
            this.selectedFechaInicio = "";
            this.selectedFechaFin = "";
            this.selectedEstado = 0;
            this.selectedSucursal = "";
            this.personalVigente = "1";

            this.cursos = [...this.todosLosCursos];
            this.periodos = [];
            this.fechasInicio = [];
            this.fechasFin = [];

            this.personal = [];
            this.totalPersonal = 0;
            this.currentPage = 1;
            this.loadingPersonal = false;
            this.selectedCursoIndex = -1;
            this.selectedSucursalIdx = 0;

            this.cacheReportes = {};
        },

        volverAFiltros() {
            this.view = "filters";
            this.personal = [];
            this.totalPersonal = 0;
            this.currentPage = 1;
            this.sortColumn = null;
            this.sortDirection = null;
            this.loadingPersonal = false;
            this.selectedCursoIndex = -1;
            this.selectedSucursalIdx = 0;
        },

        get todosCursosSeleccionados() {
            const visibles = this.cursos.filter(
                (c) =>
                    !this.searchCurso ||
                    c.Nombre.toLowerCase().includes(
                        this.searchCurso.toLowerCase(),
                    ),
            );
            return (
                visibles.length > 0 &&
                visibles.every((c) => this.courseIds.includes(c.Id))
            );
        },

        toggleSeleccionarTodos() {
            const visibles = this.cursos.filter(
                (c) =>
                    !this.searchCurso ||
                    c.Nombre.toLowerCase().includes(
                        this.searchCurso.toLowerCase(),
                    ),
            );
            if (this.todosCursosSeleccionados) {
                this.courseIds = this.courseIds.filter(
                    (id) => !visibles.some((v) => v.Id === id),
                );
            } else {
                visibles.forEach((v) => {
                    if (!this.courseIds.includes(v.Id)) {
                        this.courseIds.push(v.Id);
                    }
                });
            }
            this.courseIds = this.courseIds.map((id) =>
                typeof id === "string" ? parseInt(id) : id,
            );
        },

        ordenar(columna) {
            if (this.sortColumn === columna) {
                if (this.sortDirection === "asc") {
                    this.sortDirection = "desc";
                } else {
                    this.sortColumn = null;
                    this.sortDirection = null;
                }
            } else {
                this.sortColumn = columna;
                this.sortDirection = "asc";
            }
            this.currentPage = 1;
        },

        seleccionarCurso(index) {
            if (this.selectedCursoIndex === index) {
                this.selectedCursoIndex = -1;
                this.selectedSucursalIdx = 0;
            } else {
                this.selectedCursoIndex = index;
                this.selectedSucursalIdx = 0;
            }
        },

        seleccionarSucursal(idx) {
            this.selectedSucursalIdx = idx;
            this.currentPage = 1;
        },

        get nombreCurso() {
            const count = this.courseIds.length;
            if (count === 0) return "TODOS LOS CURSOS";
            if (count === 1)
                return (
                    this.cursos.find((c) => c.Id == this.courseIds[0])
                        ?.Nombre || ""
                );
            return `${count} CURSOS SELECCIONADOS`;
        },

        get nombreCursosSeleccionados() {
            if (this.courseIds.length === 0) return "SIN CURSO";
            if (this.courseIds.length === 1) {
                return (
                    this.cursos.find((c) => c.Id == this.courseIds[0])
                        ?.Nombre || "SIN NOMBRE"
                );
            }
            return this.courseIds
                .map((id) => this.cursos.find((c) => c.Id == id)?.Nombre)
                .filter(Boolean)
                .join(", ");
        },

        get cursoActual() {
            if (this.personal.length > 0) {
                return (
                    this.personal[this.selectedCursoIndex] || this.personal[0]
                );
            }
            return null;
        },

        get personalCursoActual() {
            return this.cursoActual?.Personales || [];
        },

        get personalCursoActualPaginado() {
            const datos = _ordenarPersonal(
                [...this.personalCursoActual],
                this.sortColumn,
                this.sortDirection,
            );
            const start = (this.currentPage - 1) * this.perPage;
            return datos.slice(start, start + this.perPage);
        },

        get sucursalesDelCursoActual() {
            const personas = this.personalCursoActual;
            const grupos = {};
            personas.forEach((p) => {
                const codigo = p.SucursalCodigo || "SIN_SUCURSAL";
                if (!grupos[codigo]) {
                    const suc = this.sucursales.find(
                        (s) => s.codigo == p.SucursalCodigo,
                    );
                    grupos[codigo] = {
                        SucursalCodigo: codigo,
                        SucursalNombre: suc
                            ? suc.sucursal.toUpperCase()
                            : "SIN SUCURSAL",
                        Personales: [],
                    };
                }
                grupos[codigo].Personales.push(p);
            });
            const todas = {
                SucursalCodigo: "TODAS",
                SucursalNombre: "TODAS LAS SUCURSALES",
                Personales: personas,
            };
            return [todas, ...Object.values(grupos)];
        },

        get personalSucursalActual() {
            const sucs = this.sucursalesDelCursoActual;
            return sucs[this.selectedSucursalIdx]?.Personales || [];
        },

        get personalSucursalActualPaginado() {
            const datos = _ordenarPersonal(
                [...this.personalSucursalActual],
                this.sortColumn,
                this.sortDirection,
            );
            const start = (this.currentPage - 1) * this.perPage;
            return datos.slice(start, start + this.perPage);
        },

        get totalPagesSucursal() {
            return Math.ceil(this.personalSucursalActual.length / this.perPage);
        },

        get totalPagesCursoActual() {
            return Math.ceil(this.personalCursoActual.length / this.perPage);
        },

        _escribirEncabezadoExcel(
            sheet,
            logoImageId,
            titulo,
            sucursalNombre,
            totalTexto,
        ) {
            sheet.getRow(1);
            sheet.getRow(2);
            sheet.getRow(3);
            sheet.getRow(4);

            if (logoImageId !== null) {
                sheet.addImage(logoImageId, {
                    tl: { col: 1, row: 0 },
                    br: { col: 3, row: 2 },
                    editAs: "absolute",
                });
            }

            sheet.getRow(1).height = 50;

            sheet.mergeCells("D1:F1");
            const infoCell = sheet.getCell("D1");
            infoCell.value = titulo;
            infoCell.font = {
                bold: true,
                size: 13,
                color: { argb: "FF1F4E79" },
            };
            infoCell.alignment = {
                vertical: "middle",
                horizontal: "right",
            };

            sheet.mergeCells("D2:F2");
            const sucursalCell = sheet.getCell("D2");
            sucursalCell.value = `Sucursal: ${sucursalNombre}`;
            sucursalCell.font = {
                size: 11,
                color: { argb: "FF333333" },
            };
            sucursalCell.alignment = {
                vertical: "middle",
                horizontal: "right",
            };

            sheet.mergeCells("D3:F3");
            const totalCell = sheet.getCell("D3");
            totalCell.value = totalTexto;
            totalCell.font = {
                size: 11,
                color: { argb: "FF333333" },
            };
            totalCell.alignment = {
                vertical: "middle",
                horizontal: "right",
            };

            sheet.getRow(4).height = 8;
        },

        _agregarFilasPersonalExcel(sheet, personal, startRow) {
            const headers = [
                "#",
                "Código Pers.",
                "Nombre Completo",
                "DNI",
                "Tipo Trabajador",
                "Cargo",
                "Nota Final",
                "Estado",
            ];

            const headerRow = sheet.getRow(startRow);
            headerRow.values = headers;
            headerRow.eachCell((cell) => _estiloEncabezadoExcel(cell));

            const dataOrdenada = [...personal].sort((a, b) =>
                (a.NombreCompleto || "").localeCompare(b.NombreCompleto || ""),
            );

            dataOrdenada.forEach((p, i) => {
                const row = sheet.addRow([
                    i + 1,
                    p.CodigoPers,
                    p.NombreCompleto,
                    p.DNI,
                    p.TipoTrabajador,
                    p.Cargo,
                    p.Nota_Final || "—",
                    p.Estado,
                ]);
                row.eachCell((cell) => _estiloDatoExcel(cell));
            });

            return startRow + 1 + dataOrdenada.length;
        },

        _agruparPorSucursal(personales) {
            const grupos = {};
            (personales || []).forEach((p) => {
                const codigo = p.SucursalCodigo || "SIN_SUCURSAL";
                if (!grupos[codigo]) {
                    const suc = this.sucursales.find(
                        (s) => s.codigo == p.SucursalCodigo,
                    );
                    grupos[codigo] = {
                        SucursalCodigo: codigo,
                        SucursalNombre: suc
                            ? suc.sucursal.toUpperCase()
                            : "SIN SUCURSAL",
                        Personales: [],
                    };
                }
                grupos[codigo].Personales.push(p);
            });
            return Object.values(grupos).sort((a, b) =>
                a.SucursalNombre.localeCompare(b.SucursalNombre),
            );
        },

        async exportarExcel() {
            this.exportandoExcel = true;
            try {
                const sucursal = this.sucursales.find(
                    (s) => s.codigo == this.selectedSucursal,
                );
                const sucursalNombre = sucursal
                    ? sucursal.sucursal.toUpperCase()
                    : "TODAS LAS SUCURSALES";

                const workbook = new ExcelJS.Workbook();
                const logoImageId = await _cargarLogoExcel(workbook);
                const sheet = workbook.addWorksheet("Personal");

                this._escribirEncabezadoExcel(
                    sheet,
                    logoImageId,
                    "REPORTE POR CAPACITACIÓN",
                    sucursalNombre,
                    `Total general: ${this.totalPersonal} personal(es)`,
                );

                let currentRow = 6;

                this.personal.forEach((grupo, gi) => {
                    if (gi > 0) {
                        currentRow += 1;
                    }

                    const cursoRow = sheet.getRow(currentRow);
                    cursoRow.height = 22;
                    const cursoCell = cursoRow.getCell(1);
                    cursoCell.value = grupo.Curso?.toUpperCase() || "CURSO";
                    cursoCell.font = {
                        bold: true,
                        size: 11,
                        color: { argb: "FF1F4E79" },
                    };
                    cursoCell.alignment = {
                        vertical: "middle",
                    };
                    sheet.mergeCells(`A${currentRow}:G${currentRow}`);
                    currentRow++;

                    const subRow = sheet.getRow(currentRow);
                    subRow.height = 16;
                    const subCell = subRow.getCell(1);
                    subCell.value = `Total del curso: ${(grupo.Personales || []).length} personal(es)`;
                    subCell.font = {
                        size: 9,
                        italic: true,
                        color: { argb: "FF666666" },
                    };
                    sheet.mergeCells(`A${currentRow}:G${currentRow}`);
                    currentRow++;

                    const sucursalesCurso = this._agruparPorSucursal(
                        grupo.Personales || [],
                    );

                    sucursalesCurso.forEach((sucursalGrupo, si) => {
                        if (si > 0) {
                            currentRow += 1;
                        }

                        const sucursalRow = sheet.getRow(currentRow);
                        sucursalRow.height = 18;
                        const sucursalCell = sucursalRow.getCell(1);
                        sucursalCell.value = `Sucursal: ${sucursalGrupo.SucursalNombre} - ${sucursalGrupo.Personales.length} personal(es)`;
                        sucursalCell.font = {
                            bold: true,
                            size: 10,
                            color: { argb: "FF333333" },
                        };
                        sucursalCell.fill = {
                            type: "pattern",
                            pattern: "solid",
                            fgColor: { argb: "FFF2F2F2" },
                        };
                        sucursalCell.alignment = {
                            vertical: "middle",
                        };
                        sheet.mergeCells(`A${currentRow}:G${currentRow}`);
                        currentRow++;

                        currentRow = this._agregarFilasPersonalExcel(
                            sheet,
                            sucursalGrupo.Personales,
                            currentRow,
                        );
                    });
                });

                sheet.columns = [
                    { width: 5 },
                    { width: 15 },
                    { width: 40 },
                    { width: 15 },
                    { width: 25 },
                    { width: 30 },
                    { width: 12 },
                    { width: 15 },
                ];

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = _blobExcel(buffer);
                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                const nombreArchivo = `REPORTE_POR_CAPACITACION_${fechaRep}.xlsx`;

                await this.registrarReporteEnHistorial(
                    nombreArchivo,
                    null,
                    blob,
                );

                saveAs(blob, nombreArchivo);

                Swal.fire(
                    "Éxito",
                    "Reporte exportado a Excel correctamente.",
                    "success",
                );
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo exportar el Excel.", "error");
            } finally {
                this.exportandoExcel = false;
            }
        },

        async exportarPDF() {
            this.exportandoPDF = true;
            try {
                const { jsPDF } = window.jspdf;

                const doc = new jsPDF({
                    orientation: "landscape",
                    unit: "mm",
                    format: "a4",
                });

                const pageHeight = doc.internal.pageSize.getHeight();

                const logoSol = await _cargarImagen(_assetUrl("/images/logo_sol.png"));

                const dibujarEncabezado = () => {
                    let logoBottomY = 26;
                    const logoWidth = 60;
                    const startX = 14;

                    if (logoSol) {
                        const ratio = logoSol.height / logoSol.width;

                        const height = logoWidth * ratio;

                        doc.addImage(
                            logoSol,
                            "PNG",
                            startX,
                            10,
                            logoWidth,
                            height,
                        );

                        logoBottomY = 10 + height;
                    }

                    const lineY = logoBottomY + 2;

                    doc.setDrawColor(150, 150, 150);
                    doc.setLineWidth(0.2);
                    doc.line(startX, lineY, startX + 75, lineY);

                    doc.setFont("helvetica", "italic");
                    doc.setFontSize(7);
                    doc.setTextColor(80, 80, 80);

                    doc.text(
                        "Chimbote: Calle Los Laureles Nº206 Urb. La Caleta",
                        startX,
                        lineY + 4,
                    );

                    doc.text("RUC: 20445414833", startX, lineY + 7);

                    return {
                        startX,
                        startY: 66,
                    };
                };

                const generarTablaCurso = (
                    nombreCurso,
                    sucursalGrupoNombre,
                    personalCurso,
                    startX,
                    startY,
                ) => {
                    doc.setTextColor(0, 0, 0);

                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(11);

                    doc.text(
                        (nombreCurso || "CURSO").toUpperCase(),
                        startX,
                        startY - 10,
                    );

                    doc.setFont("helvetica", "normal");
                    doc.setFontSize(9);

                    doc.text(
                        `${sucursalGrupoNombre} - ${personalCurso.length} personal(es)`,
                        startX,
                        startY - 4,
                    );

                    const personalOrdenado = [...personalCurso].sort((a, b) => {
                        const cmpNombre = (
                            a.NombreCompleto || ""
                        ).localeCompare(b.NombreCompleto || "");
                        if (cmpNombre !== 0) return cmpNombre;
                        return (a.Estado || "").localeCompare(b.Estado || "");
                    });

                    const filas = personalOrdenado.map((p) => [
                        p.CodigoPers || "",
                        p.NombreCompleto || "",
                        p.DNI || "",
                        p.TipoTrabajador || "",
                        p.Cargo || "Sin cargo",
                        p.Nota_Final || "Sin nota",
                        p.Estado || "",
                    ]);

                    let primeraPagina = true;

                    doc.autoTable({
                        startY,
                        head: [
                            [
                                "Código\nPers.",
                                "Nombre Completo",
                                "DNI",
                                "Tipo Trabajador",
                                "Cargo",
                                "Nota final",
                                "Estado",
                            ],
                        ],
                        body: filas,
                        theme: "grid",
                        styles: {
                            fontSize: 8,
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.1,
                            valign: "middle",
                            halign: "center",
                        },
                        headStyles: {
                            fillColor: [253, 245, 230],
                            textColor: [0, 0, 0],
                            fontStyle: "bold",
                            halign: "center",
                        },
                        columnStyles: {
                            0: {
                                cellWidth: 17,
                            },
                            1: {
                                cellWidth: "auto",
                                halign: "left",
                            },
                            2: {
                                cellWidth: "auto",
                                halign: "center",
                            },
                            3: {
                                cellWidth: "auto",
                                halign: "center",
                            },
                            4: {
                                cellWidth: "auto",
                                halign: "center",
                            },
                            5: {
                                cellWidth: "auto",
                                halign: "center",
                            },
                            6: {
                                cellWidth: "auto",
                                halign: "center",
                            },
                        },
                        margin: {
                            top: 24,
                            left: 14,
                            right: 14,
                        },
                        didDrawPage: () => {
                            if (primeraPagina) {
                                primeraPagina = false;
                                return;
                            }

                            doc.setFont("helvetica", "bold");
                            doc.setFontSize(10);
                            doc.setTextColor(0, 0, 0);
                            doc.text(
                                (nombreCurso || "CURSO").toUpperCase(),
                                startX,
                                14,
                            );

                            doc.setFont("helvetica", "normal");
                            doc.setFontSize(9);
                            doc.text(
                                `${sucursalGrupoNombre} - ${personalCurso.length} personal(es)`,
                                startX,
                                19,
                            );
                        },
                    });
                };

                this.personal.forEach((cursoData, index) => {
                    if (index > 0) {
                        doc.addPage();
                    }

                    const { startX, startY } = dibujarEncabezado();
                    const sucursalesCurso = this._agruparPorSucursal(
                        cursoData.Personales || [],
                    );

                    if (sucursalesCurso.length === 0) {
                        generarTablaCurso(
                            cursoData.Curso,
                            "SIN SUCURSAL",
                            [],
                            startX,
                            startY,
                        );
                        return;
                    }

                    let nextY = startY;

                    sucursalesCurso.forEach((sucursalGrupo, si) => {
                        if (si > 0) {
                            nextY =
                                (doc.lastAutoTable?.finalY || nextY) + 20;
                        }

                        if (nextY > pageHeight - 22) {
                            doc.addPage();
                            nextY = dibujarEncabezado().startY;
                        }

                        generarTablaCurso(
                            cursoData.Curso,
                            sucursalGrupo.SucursalNombre,
                            sucursalGrupo.Personales,
                            startX,
                            nextY,
                        );
                    });
                });

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                const nombreArchivo = `REPORTE_POR_CAPACITACION_${fechaRep}.pdf`;

                doc.setProperties({
                    title: nombreArchivo,
                });

                const pdfBlob = doc.output("blob");

                await this.registrarReporteEnHistorial(
                    nombreArchivo,
                    pdfBlob,
                    null,
                );

                saveAs(pdfBlob, nombreArchivo);

                Swal.fire("Éxito", "PDF generado correctamente.", "success");
            } catch (error) {
                console.error(error);

                Swal.fire("Error", "No se pudo generar el PDF.", "error");
            } finally {
                this.exportandoPDF = false;
            }
        },

        async registrarReporteEnHistorial(nombreArchivo, pdfBlob, excelBlob) {
            try {
                const formData = new FormData();
                formData.append("nombre_archivo", nombreArchivo);
                formData.append("descripcion", "");

                if (pdfBlob) {
                    formData.append(
                        "archivo_pdf",
                        pdfBlob,
                        nombreArchivo.replace(/\.pdf$/i, "") + ".pdf",
                    );
                    formData.append("tipo_archivo", "pdf");
                }

                if (excelBlob) {
                    formData.append(
                        "archivo_excel",
                        excelBlob,
                        nombreArchivo.replace(/\.xlsx$/i, "") + ".xlsx",
                    );
                    formData.append("tipo_archivo", "xlsx");
                }

                // Registro en historial deshabilitado temporalmente.
                // await axios.post(
                //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                //     formData,
                // );

                // window.dispatchEvent(
                //     new CustomEvent("historial-reportes-actualizado"),
                // );
            } catch (error) {
                console.error(
                    "Error al registrar reporte en historial:",
                    error,
                );
            }
        },

        get esReportePorCursos() {
            return this.personal.length > 0;
        },

        async obtenerPersonal() {
            const cacheKey = [
                this.courseIds.length > 0 ? this.courseIds.join(",") : "todos",
                this.selectedSucursal || "todas",
                this.selectedEstado ?? 0,
            ].join("_");

            if (this.cacheReportes[cacheKey]) {
                const cached = this.cacheReportes[cacheKey];
                this.personal = cached.personal;
                this.totalPersonal = cached.total;
                this.currentPage = 1;
                this.selectedCursoIndex = -1;
                this.selectedSucursalIdx = 0;
                this.view = "personal";
                return;
            }

            this.view = "personal";
            this.personal = [];
            this.loadingPersonal = true;

            const params = { estadoId: this.selectedEstado ?? 0 };

            if (this.courseIds.length > 0) params.courseIds = this.courseIds;
            if (this.selectedSucursal)
                params.sucursalId = this.selectedSucursal;

            try {
                const response = await axios.post(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/personal`,
                    params,
                );

                if (response.data.success) {
                    const cursos = response.data.Cursos || [];
                    this._cursosExportData = cursos;

                    this.personal = cursos;
                    this.totalPersonal = cursos.reduce(
                        (sum, c) => sum + (c.Personales?.length ?? 0),
                        0,
                    );

                    this.selectedCursoIndex = this.personal.length > 0 ? 0 : -1;
                    this.selectedSucursalIdx = 0;

                    this.cacheReportes[cacheKey] = {
                        personal: this.personal,
                        total: this.totalPersonal,
                    };

                    this.currentPage = 1;
                } else {
                    this.personal = [];
                    this.totalPersonal = 0;
                }
            } catch (error) {
                console.error(error);
                this.personal = [];
                this.totalPersonal = 0;
                Swal.fire(
                    "Error",
                    "No se pudo obtener el personal. Intente nuevamente.",
                    "error",
                );
            } finally {
                this.loadingPersonal = false;
            }
        },

        async _fetchPersonal() {
            const cacheKey = [
                this.courseIds.length > 0 ? this.courseIds.join(",") : "todos",
                this.selectedSucursal || "todas",
                this.selectedEstado || 0,
            ].join("_");

            if (this.cacheReportes[cacheKey]) {
                this.personal = this.cacheReportes[cacheKey].personal;
                this.totalPersonal = this.cacheReportes[cacheKey].total;
                return true;
            }

            this.personal = [];

            const params = {};
            if (this.courseIds.length > 0) params.courseIds = this.courseIds;
            if (this.selectedSucursal)
                params.sucursalId = this.selectedSucursal;
            params.estadoId = this.selectedEstado ?? 0;

            try {
                const response = await axios.post(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/personal`,
                    params,
                );
                if (response.data.success) {
                    const cursos = response.data.Cursos || [];
                    this._cursosExportData = cursos;
                    this.personal = cursos.flatMap(
                        (curso) => curso.Personales || [],
                    );
                    this.totalPersonal = this.personal.length;
                    this.cacheReportes[cacheKey] = {
                        personal: this.personal,
                        total: this.totalPersonal,
                    };
                    return true;
                } else {
                    this.personal = [];
                    this.totalPersonal = 0;
                    return false;
                }
            } catch (error) {
                console.error(error);
                this.personal = [];
                this.totalPersonal = 0;
                Swal.fire(
                    "Error",
                    "No se pudo obtener el personal. Intente nuevamente.",
                    "error",
                );
                return false;
            }
        },

        async exportarExcelDesdeFiltros() {
            if (this.courseIds.length === 0) {
                Swal.fire(
                    "Atención",
                    "Seleccione al menos un curso para exportar.",
                    "warning",
                );
                return;
            }
            this.exportandoExcel = true;
            const ok = await this._fetchPersonal();
            if (!ok || this.personal.length === 0) {
                this.exportandoExcel = false;
                if (ok)
                    Swal.fire(
                        "Atención",
                        "No se encontró personal con los filtros seleccionados.",
                        "warning",
                    );
                return;
            }
            await this.exportarExcel();
        },

        async exportarPDFDesdeFiltros() {
            if (this.courseIds.length === 0) {
                Swal.fire(
                    "Atención",
                    "Seleccione al menos un curso para exportar.",
                    "warning",
                );
                return;
            }
            this.exportandoPDF = true;
            const ok = await this._fetchPersonal();
            if (!ok || this.personal.length === 0) {
                this.exportandoPDF = false;
                if (ok)
                    Swal.fire(
                        "Atención",
                        "No se encontró personal con los filtros seleccionados.",
                        "warning",
                    );
                return;
            }
            await this.exportarPDF();
        },
    }));

    Alpine.data("modalReporteDeCapacitaciones", () => ({
        open: false,
        view: "filters",

        selectedSistema: "",
        selectedArea: "",
        selectedFechaInicio: "",
        selectedFechaFin: "",

        sistemas: [],
        areas: [],
        cursosFilas: [],

        loadingSistemas: false,
        loadingAreas: false,
        loadingCursos: false,

        currentPage: 1,
        perPage: 15,

        sortColumn: null,
        sortDirection: null,

        exportandoExcel: false,
        exportandoPDF: false,

        selectedSistemaIndex: 0,
        selectedAreaIdx: 0,

        cacheReportes: {},

        async init() {
            window.addEventListener("abrir-cursos-area", () => {
                this.abrir();
            });

            this.loadingSistemas = true;
            this.loadingAreas = true;

            const catalogs = await ensureCatalogsLoaded();

            this.sistemas = catalogs.sistemas;
            this.areas = catalogs.areas;

            this.loadingSistemas = false;
            this.loadingAreas = false;
        },

        async cargarSistemas() {
            this.loadingSistemas = true;
            try {
                this.sistemas = await _fetchSistemas();
            } catch (error) {
                console.error(error);
                this.sistemas = [];
            } finally {
                this.loadingSistemas = false;
            }
        },

        async cargarAreas() {
            this.loadingAreas = true;
            try {
                this.areas = await _fetchAreas();
            } catch (error) {
                console.error(error);
                this.areas = [];
            } finally {
                this.loadingAreas = false;
            }
        },

        formatearFecha(timestamp) {
            if (!timestamp || timestamp <= 0) {
                return "";
            }
            const fecha = new Date(timestamp * 1000);
            const dia = String(fecha.getDate()).padStart(2, "0");
            const mes = String(fecha.getMonth() + 1).padStart(2, "0");
            const anio = fecha.getFullYear();
            return `${dia}/${mes}/${anio}`;
        },

        timestampInicioDia(timestamp) {
            if (!timestamp || timestamp <= 0) {
                return null;
            }
            const fecha = new Date(timestamp * 1000);
            fecha.setHours(0, 0, 0, 0);
            return fecha.getTime();
        },

        parseYmdToTime(ymd) {
            if (!ymd) {
                return null;
            }
            const [y, m, d] = ymd.split("-").map(Number);
            if (!y || !m || !d) {
                return null;
            }
            const fecha = new Date(y, m - 1, d);
            fecha.setHours(0, 0, 0, 0);
            return fecha.getTime();
        },

        cursoSolapaRangoUsuario(curso) {
            const uIni = this.parseYmdToTime(this.selectedFechaInicio);
            const uFin = this.parseYmdToTime(this.selectedFechaFin);

            if (uIni === null && uFin === null) {
                return true;
            }

            const cCre = this.timestampInicioDia(curso.Fecha_Creacion);

            if (cCre === null) {
                return true;
            }

            const rangoIni = uIni !== null ? uIni : -Infinity;
            const rangoFin = uFin !== null ? uFin : Infinity;

            return cCre >= rangoIni && cCre <= rangoFin;
        },

        stripHtml(html) {
            if (!html) {
                return "";
            }
            const tmp = document.createElement("div");
            tmp.innerHTML = html;
            return (tmp.textContent || tmp.innerText || "").trim();
        },

        filaDesdeCurso(curso) {
            const iniTs = this.timestampInicioDia(curso.Fecha_Inicio);
            const finTs = this.timestampInicioDia(curso.Fecha_Fin);
            const creTs = this.timestampInicioDia(curso.Fecha_Creacion);

            const nombre = (curso.Nombre || "").toString().trim();
            const descripcion = this.stripHtml(curso.Descripcion || "");
            const sistema = (curso.Sistema || "").toString().trim();
            const area = (curso.Area || "").toString().trim();
            const responsable = (curso.Responsable || "").toString().trim();
            const matriculados = curso.Total_Matriculados;
            const fechaIniStr = this.formatearFecha(curso.Fecha_Inicio);
            const fechaFinStr = this.formatearFecha(curso.Fecha_Fin);
            const fechaCreStr = this.formatearFecha(curso.Fecha_Creacion);

            return {
                id: curso.Id,
                Nombre: nombre || "Sin nombre de curso",
                Descripcion: descripcion || "Sin descripción",
                Sistema: sistema || "Sin sistema",
                Area: area || "Sin área",
                Responsable: responsable || "Sin responsable",
                Total_Matriculados: matriculados != null ? matriculados : "—",
                Fecha_Inicio: fechaIniStr || "Sin fecha de inicio",
                Fecha_Fin: fechaFinStr || "Sin fecha de fin",
                Fecha_Creacion: fechaCreStr || "Sin fecha de creación",
                _sortInicio: iniTs,
                _sortFin: finTs,
                _sortCreacion: creTs,
            };
        },

        ordenar(columna) {
            if (this.sortColumn === columna) {
                if (this.sortDirection === "asc") {
                    this.sortDirection = "desc";
                } else {
                    this.sortColumn = null;
                    this.sortDirection = null;
                }
            } else {
                this.sortColumn = columna;
                this.sortDirection = "asc";
            }
            this.currentPage = 1;
        },

        valorOrden(fila, columna) {
            if (columna === "Fecha_Inicio") {
                return fila._sortInicio;
            }
            if (columna === "Fecha_Fin") {
                return fila._sortFin;
            }
            if (columna === "Fecha_Creacion") {
                return fila._sortCreacion;
            }
            return (fila[columna] || "").toString();
        },

        get cursosPaginado() {
            const datos = _ordenarCursosConFechas(
                [...this.cursosFilas],
                this.sortColumn,
                this.sortDirection,
                (fila, col) => this.valorOrden(fila, col),
            );
            const start = (this.currentPage - 1) * this.perPage;
            return datos.slice(start, start + this.perPage);
        },

        get totalPagesCursos() {
            const n = this.cursosFilas.length;
            if (n === 0) {
                return 1;
            }
            return Math.ceil(n / this.perPage);
        },

        get sistemasEnCursos() {
            const map = {};
            this.cursosFilas.forEach((c) => {
                map[c.Sistema] = true;
            });
            return Object.keys(map).sort();
        },

        get areasDelSistemaActual() {
            if (
                this.selectedSistemaIndex < 0 ||
                this.selectedSistemaIndex >= this.sistemasEnCursos.length
            )
                return [];
            const sistemaNombre =
                this.sistemasEnCursos[this.selectedSistemaIndex];
            const areas = {};
            this.cursosFilas.forEach((c) => {
                if (c.Sistema === sistemaNombre) areas[c.Area] = true;
            });
            return Object.keys(areas).sort();
        },

        get cursosAreaActual() {
            if (
                this.selectedSistemaIndex < 0 ||
                this.selectedSistemaIndex >= this.sistemasEnCursos.length
            )
                return [];
            const sistemaNombre =
                this.sistemasEnCursos[this.selectedSistemaIndex];
            let filtered = this.cursosFilas.filter(
                (c) => c.Sistema === sistemaNombre,
            );
            if (
                this.selectedAreaIdx > 0 &&
                this.selectedAreaIdx <= this.areasDelSistemaActual.length
            ) {
                const areaNombre =
                    this.areasDelSistemaActual[this.selectedAreaIdx - 1];
                filtered = filtered.filter((c) => c.Area === areaNombre);
            }
            return filtered;
        },

        get cursosAreaActualPaginado() {
            const datos = _ordenarCursosConFechas(
                [...this.cursosAreaActual],
                this.sortColumn,
                this.sortDirection,
                (fila, col) => this.valorOrden(fila, col),
            );
            const start = (this.currentPage - 1) * this.perPage;
            return datos.slice(start, start + this.perPage);
        },

        get totalPagesArea() {
            const n = this.cursosAreaActual.length;
            if (n === 0) return 1;
            return Math.ceil(n / this.perPage);
        },

        seleccionarSistema(index) {
            if (this.selectedSistemaIndex === index) {
                this.selectedSistemaIndex = -1;
                this.selectedAreaIdx = 0;
            } else {
                this.selectedSistemaIndex = index;
                this.selectedAreaIdx = 0;
            }
            this.currentPage = 1;
        },

        seleccionarArea(idx) {
            this.selectedAreaIdx = idx;
            this.currentPage = 1;
        },

        formatearYmdADmY(ymd) {
            if (!ymd) {
                return "";
            }
            const parts = String(ymd).split("-");
            if (parts.length !== 3) {
                return String(ymd);
            }
            const [y, m, d] = parts;
            return `${String(d).padStart(2, "0")}/${String(m).padStart(2, "0")}/${y}`;
        },

        get textoRangoFechasHistorial() {
            const ini = this.formatearYmdADmY(this.selectedFechaInicio);
            const fin = this.formatearYmdADmY(this.selectedFechaFin);
            if (ini && fin) {
                return `Del ${ini} al ${fin}`;
            }
            if (ini) {
                return `Desde el ${ini}`;
            }
            if (fin) {
                return `Hasta el ${fin}`;
            }
            return "Todo el período";
        },

        observacionParaExportacion(fila) {
            return "";
        },

        obtenerCursosFilasOrdenadosParaExport() {
            return _ordenarCursosConFechas(
                [...this.cursosFilas],
                this.sortColumn,
                this.sortDirection,
                (fila, col) => this.valorOrden(fila, col),
            );
        },

        async _fetchCursos() {
            const cacheKey = `${this.selectedSistema || "_"}_${this.selectedArea || "_"}`;

            if (this.cacheReportes[cacheKey]) {
                const filas = this.cacheReportes[cacheKey]
                    .filter((c) => this.cursoSolapaRangoUsuario(c))
                    .map((c) => this.filaDesdeCurso(c));
                this.cursosFilas = filas;
                return true;
            }

            this.cursosFilas = [];

            try {
                const params = {};
                if (this.selectedSistema)
                    params.systemId = this.selectedSistema;
                if (this.selectedArea) params.areaId = this.selectedArea;

                const response = await axios.get(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/cursos`,
                    {
                        params,
                    },
                );
                const cursosRaw =
                    response.data.Cursos || response.data.cursos || [];

                if (!response.data.success || !Array.isArray(cursosRaw)) {
                    return false;
                }

                this.cacheReportes[cacheKey] = cursosRaw;

                const filas = cursosRaw
                    .filter((c) => this.cursoSolapaRangoUsuario(c))
                    .map((c) => this.filaDesdeCurso(c));

                this.cursosFilas = filas;
                return this.cursosFilas.length > 0;
            } catch (error) {
                console.error(error);
                this.cursosFilas = [];
                return false;
            }
        },

        async exportarExcelHistorialCursosDesdeFiltros() {
            this.exportandoExcel = true;
            const ok = await this._fetchCursos();
            if (!ok || this.cursosFilas.length === 0) {
                this.exportandoExcel = false;
                if (ok)
                    Swal.fire(
                        "Atención",
                        "No se encontraron cursos con los filtros seleccionados.",
                        "warning",
                    );
                return;
            }
            await this.exportarExcelHistorialCursos();
        },

        async exportarPDFHistorialCursosDesdeFiltros() {
            this.exportandoPDF = true;
            const ok = await this._fetchCursos();
            if (!ok || this.cursosFilas.length === 0) {
                this.exportandoPDF = false;
                if (ok)
                    Swal.fire(
                        "Atención",
                        "No se encontraron cursos con los filtros seleccionados.",
                        "warning",
                    );
                return;
            }
            await this.exportarPDFHistorialCursos();
        },

        async exportarExcelHistorialCursos() {
            if (!this.cursosFilas.length) {
                Swal.fire(
                    "Atención",
                    "No hay cursos para exportar.",
                    "warning",
                );
                return;
            }

            this.exportandoExcel = true;
            try {
                const filasExport =
                    this.obtenerCursosFilasOrdenadosParaExport();
                const sistema = this.sistemas.find(
                    (s) => String(s.codigo) === String(this.selectedSistema),
                );
                const nombreArea =
                    this.selectedAreaIdx > 0
                        ? this.areasDelSistemaActual[
                              this.selectedAreaIdx - 1
                          ] || "Área"
                        : "Todas las áreas";
                const nombreSistema = sistema
                    ? sistema.descripcion
                    : "Sistema de gestión";

                const workbook = new ExcelJS.Workbook();

                const logoImageId = await _cargarLogoExcel(workbook);

                const sheet = workbook.addWorksheet("Historial");

                const headerRowNumber = 5;

                sheet.getRow(1);
                sheet.getRow(2);
                sheet.getRow(3);
                sheet.getRow(4);

                if (logoImageId !== null) {
                    sheet.addImage(logoImageId, {
                        tl: { col: 1, row: 0 },
                        br: { col: 3, row: 2 },
                        editAs: "absolute",
                    });
                }

                sheet.getRow(1).height = 50;

                sheet.mergeCells("D1:H1");

                const infoCell = sheet.getCell("D1");
                infoCell.value = `Área responsable: ${nombreArea}`;
                infoCell.font = {
                    bold: true,
                    size: 13,
                    color: { argb: "FF1F4E79" },
                };
                infoCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.mergeCells("D2:H2");

                const sistemaCell = sheet.getCell("D2");
                sistemaCell.value = `Sistema de gestión: ${nombreSistema}`;
                sistemaCell.font = {
                    size: 11,
                    color: { argb: "FF333333" },
                };
                sistemaCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.mergeCells("D3:H3");

                const totalCell = sheet.getCell("D3");
                totalCell.value = `Total: ${filasExport.length} curso(s) · ${this.textoRangoFechasHistorial}`;
                totalCell.font = {
                    size: 11,
                    color: { argb: "FF333333" },
                };
                totalCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.getRow(4).height = 8;

                const headers = [
                    "#",
                    "Capacitación",
                    "Descripción",
                    "Sistema",
                    "Área",
                    "Matriculados",
                    "Responsable",
                    "Fecha inicio",
                    "Fecha fin",
                    "Fecha creación",
                ];

                const headerRow = sheet.getRow(headerRowNumber);

                headerRow.values = headers;

                headerRow.eachCell((cell) => _estiloEncabezadoExcel(cell));

                filasExport.forEach((fila, i) => {
                    const row = sheet.addRow([
                        i + 1,
                        fila.Nombre,
                        fila.Descripcion,
                        fila.Sistema,
                        fila.Area,
                        fila.Total_Matriculados,
                        fila.Responsable,
                        fila.Fecha_Inicio,
                        fila.Fecha_Fin,
                        fila.Fecha_Creacion,
                    ]);

                    row.eachCell((cell) => _estiloDatoExcel(cell));
                });

                sheet.columns = [
                    { width: 5 },
                    { width: 35 },
                    { width: 30 },
                    { width: 18 },
                    { width: 18 },
                    { width: 12 },
                    { width: 26 },
                    { width: 14 },
                    { width: 14 },
                    { width: 14 },
                ];

                sheet.autoFilter = {
                    from: `A${headerRowNumber}`,
                    to: `J${headerRowNumber}`,
                };

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = _blobExcel(buffer);

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                const nombreArchivo = `REPORTE_HISTORIAL_CURSOS_${fechaRep}.xlsx`;

                await this.registrarReporteEnHistorial(
                    nombreArchivo,
                    null,
                    blob,
                );

                saveAs(blob, nombreArchivo);

                Swal.fire(
                    "Éxito",
                    "Historial exportado a Excel correctamente.",
                    "success",
                );
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo exportar el Excel.", "error");
            } finally {
                this.exportandoExcel = false;
            }
        },

        async exportarPDFHistorialCursos() {
            if (!this.cursosFilas.length) {
                Swal.fire(
                    "Atención",
                    "No hay cursos para exportar.",
                    "warning",
                );
                return;
            }

            this.exportandoPDF = true;
            try {
                const { jsPDF } = window.jspdf;

                const doc = new jsPDF({
                    orientation: "landscape",
                    unit: "mm",
                    format: "a4",
                });

                const pageWidth = doc.internal.pageSize.getWidth();

                const filasExport =
                    this.obtenerCursosFilasOrdenadosParaExport();

                const sistema = this.sistemas.find(
                    (s) => String(s.codigo) === String(this.selectedSistema),
                );
                const nombreAreaPdf =
                    this.selectedAreaIdx > 0
                        ? this.areasDelSistemaActual[
                              this.selectedAreaIdx - 1
                          ] || ""
                        : "TODAS LAS ÁREAS";

                const logoSol = await _cargarImagen(
                    _assetUrl("/images/logo_sol.png"),
                );

                let logoBottomY = 26;
                const logoWidth = 60;
                const startX = 14;

                if (logoSol) {
                    const ratio = logoSol.height / logoSol.width;
                    const height = logoWidth * ratio;
                    doc.addImage(logoSol, "PNG", startX, 10, logoWidth, height);
                    logoBottomY = 10 + height;
                }

                const lineY = logoBottomY + 2;
                doc.setDrawColor(150, 150, 150);
                doc.setLineWidth(0.2);
                doc.line(startX, lineY, startX + 75, lineY);

                doc.setFont("helvetica", "italic");
                doc.setFontSize(7);
                doc.setTextColor(80, 80, 80);
                doc.text(
                    "Chimbote: Calle Los Laureles Nº206 Urb. La Caleta",
                    startX,
                    lineY + 4,
                );
                doc.text("RUC: 20445414833", startX, lineY + 7);

                const title = sistema
                    ? `${sistema.descripcion.toUpperCase()}`
                    : "";
                const subtitle = nombreAreaPdf;

                doc.setTextColor(0, 0, 0);
                doc.setFont("helvetica", "bold");
                doc.setFontSize(12);

                const titleX = pageWidth - 14;
                doc.text(title, titleX, 40, { align: "right" });
                const titleWidth = doc.getTextWidth(title);
                doc.setLineWidth(0.3);
                doc.setDrawColor(0, 0, 0);
                if (titleWidth > 0) {
                    doc.line(titleX - titleWidth, 41, titleX, 41);
                }

                doc.setFont("helvetica", "normal");
                doc.setFontSize(10);
                doc.text(subtitle, titleX, 46, { align: "right" });

                const textoListado = `HISTORIAL - ${filasExport.length} CURSO(S)`;

                doc.setFont("helvetica", "bold");
                doc.setFontSize(10);
                doc.text(textoListado, startX, 56);

                const periodoLinea =
                    this.textoRangoFechasHistorial.toUpperCase();
                doc.setFont("helvetica", "normal");
                doc.setFontSize(9);
                const twPeriodo = pageWidth - startX - 100;
                const lineasPeriodo = doc.splitTextToSize(
                    periodoLinea,
                    twPeriodo,
                );
                doc.text(lineasPeriodo, startX, 61);

                const tableStartY =
                    lineasPeriodo.length > 1
                        ? 61 + lineasPeriodo.length * 4.2 + 4
                        : 66;

                const filas = filasExport.map((f, i) => [
                    i + 1,
                    f.Nombre || "",
                    f.Descripcion || "",
                    f.Sistema || "",
                    f.Area || "",
                    f.Total_Matriculados != null ? f.Total_Matriculados : "",
                    f.Responsable || "",
                    f.Fecha_Inicio || "",
                    f.Fecha_Fin || "",
                    f.Fecha_Creacion || "",
                ]);

                doc.autoTable({
                    startY: tableStartY,
                    head: [
                        [
                            "It",
                            "Capacitación",
                            "Descripción",
                            "Sistema",
                            "Área",
                            "Matriculados",
                            "Responsable",
                            "Fecha inicio",
                            "Fecha fin",
                            "Fecha creación",
                        ],
                    ],
                    body: filas,
                    theme: "grid",
                    styles: {
                        fontSize: 7,
                        textColor: [0, 0, 0],
                        lineColor: [0, 0, 0],
                        lineWidth: 0.1,
                        halign: "center",
                        valign: "middle",
                    },
                    headStyles: {
                        fillColor: [253, 245, 230],
                        textColor: [0, 0, 0],
                        fontStyle: "bold",
                        halign: "center",
                    },
                    columnStyles: {
                        0: { cellWidth: 10, halign: "center" },
                        1: { cellWidth: "auto", halign: "left" },
                        2: { cellWidth: "auto", halign: "left" },
                        3: { cellWidth: "auto", halign: "left" },
                        4: { cellWidth: "auto" },
                        5: { cellWidth: "auto" },
                        6: { cellWidth: "auto" },
                        7: { cellWidth: "auto" },
                        8: { cellWidth: "auto" },
                        9: { cellWidth: "auto" },
                    },
                    margin: { left: 10, right: 10 },
                });

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                const nombreArchivo = `REPORTE_HISTORIAL_CURSOS_${fechaRep}.pdf`;

                const pdfBlob = doc.output("blob");

                await this.registrarReporteEnHistorial(
                    nombreArchivo,
                    pdfBlob,
                    null,
                );

                saveAs(pdfBlob, nombreArchivo);

                Swal.fire("Éxito", "PDF generado correctamente.", "success");
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo generar el PDF.", "error");
            } finally {
                this.exportandoPDF = false;
            }
        },

        async obtenerCursos() {
            if (
                this.selectedFechaInicio &&
                this.selectedFechaFin &&
                this.selectedFechaInicio > this.selectedFechaFin
            ) {
                Swal.fire(
                    "Atención",
                    "La fecha de inicio no puede ser posterior a la fecha de fin.",
                    "warning",
                );
                return;
            }

            this.view = "cursos";
            this.cursosFilas = [];

            const cacheKey = `${this.selectedSistema || "_"}_${this.selectedArea || "_"}`;

            if (this.cacheReportes[cacheKey]) {
                const filas = this.cacheReportes[cacheKey]
                    .filter((c) => this.cursoSolapaRangoUsuario(c))
                    .map((c) => this.filaDesdeCurso(c));

                this.cursosFilas = filas;
                this.sortColumn = null;
                this.sortDirection = null;
                this.currentPage = 1;
                this.loadingCursos = false;

                this.selectedSistemaIndex = 0;
                this.selectedAreaIdx = 0;

                return;
            }

            this.loadingCursos = true;
            try {
                const params = {};
                if (this.selectedSistema)
                    params.systemId = this.selectedSistema;
                if (this.selectedArea) params.areaId = this.selectedArea;

                const response = await axios.get(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/cursos`,
                    {
                        params,
                    },
                );

                const cursosRaw =
                    response.data.Cursos || response.data.cursos || [];

                if (!response.data.success || !Array.isArray(cursosRaw)) {
                    this.cursosFilas = [];
                    this.currentPage = 1;
                    return;
                }

                this.cacheReportes[cacheKey] = cursosRaw;

                const filas = cursosRaw
                    .filter((c) => this.cursoSolapaRangoUsuario(c))
                    .map((c) => this.filaDesdeCurso(c));

                this.cursosFilas = filas;
                this.sortColumn = null;
                this.sortDirection = null;
                this.currentPage = 1;

                this.selectedSistemaIndex = 0;
                this.selectedAreaIdx = 0;
            } catch (error) {
                console.error(error);
                this.cursosFilas = [];
                Swal.fire(
                    "Error",
                    "No se pudieron cargar los cursos. Intente nuevamente.",
                    "warning",
                );
            } finally {
                this.loadingCursos = false;
            }
        },

        volverAFiltros() {
            this.view = "filters";
            this.cursosFilas = [];
            this.currentPage = 1;
            this.sortColumn = null;
            this.sortDirection = null;
            this.selectedSistemaIndex = 0;
            this.selectedAreaIdx = 0;
        },

        async abrir() {
            this.open = true;
        },

        cerrar() {
            this.open = false;
            this.view = "filters";
            this.selectedSistema = "";
            this.selectedArea = "";
            this.selectedFechaInicio = "";
            this.selectedFechaFin = "";
            this.cursosFilas = [];
            this.currentPage = 1;
            this.loadingCursos = false;
            this.sortColumn = null;
            this.sortDirection = null;
            this.cacheReportes = {};
            this.selectedSistemaIndex = 0;
            this.selectedAreaIdx = 0;
        },

        async registrarReporteEnHistorial(nombreArchivo, pdfBlob, excelBlob) {
            try {
                const formData = new FormData();
                formData.append("nombre_archivo", nombreArchivo);
                formData.append("descripcion", "");

                if (pdfBlob) {
                    formData.append(
                        "archivo_pdf",
                        pdfBlob,
                        nombreArchivo.replace(/\.pdf$/i, "") + ".pdf",
                    );
                    formData.append("tipo_archivo", "pdf");
                }

                if (excelBlob) {
                    formData.append(
                        "archivo_excel",
                        excelBlob,
                        nombreArchivo.replace(/\.xlsx$/i, "") + ".xlsx",
                    );
                    formData.append("tipo_archivo", "xlsx");
                }

                // Registro en historial deshabilitado temporalmente.
                // await axios.post(
                //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                //     formData,
                // );

                // window.dispatchEvent(
                //     new CustomEvent("historial-reportes-actualizado"),
                // );
            } catch (error) {
                console.error(
                    "Error al registrar reporte en historial:",
                    error,
                );
                Swal.fire(
                    "Error",
                    error.response?.data?.message ||
                        "No se pudo guardar en el historial de reportes.",
                    "warning",
                );
            }
        },
    }));

    Alpine.data("modalHistorialReportes", () => ({
        open: false,
        reportes: [],
        loading: false,
        cacheLoaded: false,

        editingId: null,
        editForm: { nombre_archivo: "", descripcion: "" },
        savingEdit: false,

        sortColumn: null,
        sortDirection: null,
        searchQuery: "",
        showDeletedOnly: false,
        filtroFecha: "",
        perPage: 5,
        currentPage: 1,

        selectedReportes: [],
        downloadingZip: false,

        async init() {
            window.addEventListener("abrir-historial-reportes", () => {
                this.abrir();
            });
            window.addEventListener("historial-reportes-actualizado", () => {
                this.cacheLoaded = false;
                this.sortColumn = null;
                this.sortDirection = null;
                this.searchQuery = "";
                this.showDeletedOnly = false;
                this.filtroFecha = "";
                this.currentPage = 1;
                this.selectedReportes = [];
            });

            this.$watch("reportesFiltrados", () => {
                if (this.currentPage > this.totalPages) {
                    this.currentPage = this.totalPages;
                }
            });
        },

        async abrir() {
            this.open = true;
            if (!this.cacheLoaded) {
                await this.cargarReportes();
            }
        },

        cerrar() {
            this.open = false;
            this.sortColumn = null;
            this.sortDirection = null;
            this.searchQuery = "";
            this.showDeletedOnly = false;
            this.filtroFecha = "";
            this.currentPage = 1;
            this.selectedReportes = [];
        },

        async cargarReportes() {
            this.loading = true;
            try {
                const response = await axios.get(
                    `${VITE_URL_APP}/api/capacitacion/listar-reportes`,
                );
                if (response.data.success) {
                    this.reportes = response.data.reportes;
                    this.cacheLoaded = true;
                    this.searchQuery = "";
                    this.currentPage = 1;
                }
            } catch (error) {
                console.error(error);
                this.reportes = [];
            } finally {
                this.loading = false;
            }
        },

        iniciarEdicion(reporte) {
            this.editingId = reporte.id;
            this.editForm = {
                nombre_archivo: reporte.nombre_archivo,
                descripcion: reporte.descripcion,
            };
        },

        cancelarEdicion() {
            this.editingId = null;
            this.editForm = { nombre_archivo: "", descripcion: "" };
        },

        ordenar(columna) {
            if (this.sortColumn === columna) {
                if (this.sortDirection === "asc") {
                    this.sortDirection = "desc";
                } else if (this.sortDirection === "desc") {
                    this.sortColumn = null;
                    this.sortDirection = null;
                } else {
                    this.sortDirection = "asc";
                }
            } else {
                this.sortColumn = columna;
                this.sortDirection = "asc";
            }
            this.currentPage = 1;
        },

        get reportesFiltrados() {
            let resultados = this.reportes;

            if (this.showDeletedOnly) {
                resultados = resultados.filter((r) => !r.habilitado);
            }

            if (this.searchQuery.trim()) {
                const query = this.searchQuery.trim().toLowerCase();
                resultados = resultados.filter((r) => {
                    const nombre = (r.nombre_archivo || "").toLowerCase();
                    return nombre.includes(query);
                });
            }

            if (this.filtroFecha) {
                resultados = resultados.filter((r) => {
                    const s = String(r.fecha_creacion || "");
                    const match = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
                    if (match) {
                        return `${match[1]}-${match[2]}-${match[3]}` ===
                            this.filtroFecha;
                    }
                    const d = new Date(s);
                    if (isNaN(d.getTime())) return false;
                    return (
                        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}` ===
                        this.filtroFecha
                    );
                });
            }

            if (!this.sortColumn || !this.sortDirection) {
                return resultados;
            }

            return [...resultados].sort((a, b) => {
                let valA = a[this.sortColumn] || "";
                let valB = b[this.sortColumn] || "";

                if (this.sortColumn === "fecha_creacion") {
                    valA = valA ? new Date(valA).getTime() : 0;
                    valB = valB ? new Date(valB).getTime() : 0;
                    return this.sortDirection === "asc"
                        ? valA - valB
                        : valB - valA;
                }

                const cmp = valA
                    .toString()
                    .localeCompare(valB.toString(), "es", {
                        sensitivity: "base",
                    });
                return this.sortDirection === "asc" ? cmp : -cmp;
            });
        },

        get reportesSeleccionables() {
            return this.reportesFiltrados.filter((r) => r.habilitado);
        },

        get totalPages() {
            return Math.max(
                1,
                Math.ceil(this.reportesFiltrados.length / this.perPage),
            );
        },

        get reportesPaginados() {
            const inicio = (this.currentPage - 1) * this.perPage;
            return this.reportesFiltrados.slice(
                inicio,
                inicio + this.perPage,
            );
        },

        get paginasVisibles() {
            const total = this.totalPages;
            const actual = this.currentPage;
            const ventana = 5;
            let inicio = Math.max(1, actual - Math.floor(ventana / 2));
            let fin = Math.min(total, inicio + ventana - 1);
            inicio = Math.max(1, fin - ventana + 1);
            const paginas = [];
            for (let i = inicio; i <= fin; i++) {
                paginas.push(i);
            }
            return paginas;
        },

        get todosSeleccionados() {
            return (
                this.reportesSeleccionables.length > 0 &&
                this.selectedReportes.length ===
                    this.reportesSeleccionables.length
            );
        },

        async guardarEdicion() {
            if (!this.editingId) return;
            this.savingEdit = true;
            try {
                const response = await axios.put(
                    `${VITE_URL_APP}/api/capacitacion/actualizar-reporte/${this.editingId}`,
                    {
                        nombre_archivo: this.editForm.nombre_archivo,
                        descripcion: this.editForm.descripcion,
                    },
                );

                if (response.data.success) {
                    const idx = this.reportes.findIndex(
                        (r) => r.id === this.editingId,
                    );
                    if (idx !== -1) {
                        this.reportes[idx].nombre_archivo =
                            this.editForm.nombre_archivo;
                        this.reportes[idx].descripcion =
                            this.editForm.descripcion;
                    }
                    this.cancelarEdicion();
                    this.cacheLoaded = false;
                }
            } catch (error) {
                console.error("Error al editar reporte:", error);
                Swal.fire(
                    "Error",
                    "No se pudo actualizar el reporte.",
                    "error",
                );
            } finally {
                this.savingEdit = false;
            }
        },

        async cambiarEstado(id, habilitado) {
            const accion = habilitado ? "recuperar" : "eliminar";
            const mensaje = habilitado
                ? "¿Desea recuperar este reporte?"
                : "¿Desea eliminar este reporte? Podrá recuperarlo luego.";

            try {
                await Swal.fire({
                    title: habilitado
                        ? "Recuperar reporte"
                        : "Eliminar reporte",
                    text: mensaje,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: habilitado ? "#10b981" : "#ef4444",
                    confirmButtonText: habilitado
                        ? "Sí, recuperar"
                        : "Sí, eliminar",
                    cancelButtonText: "Cancelar",
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await axios.patch(
                            `${VITE_URL_APP}/api/capacitacion/actualizar-estado-reporte/${id}`,
                            {
                                habilitado: habilitado,
                            },
                        );

                        if (response.data.success) {
                            const idx = this.reportes.findIndex(
                                (r) => r.id === id,
                            );
                            if (idx !== -1) {
                                this.reportes[idx].habilitado = habilitado;
                            }
                            this.cacheLoaded = false;
                            Swal.fire(
                                "Éxito",
                                response.data.message,
                                "success",
                            );
                        }
                    }
                });
            } catch (error) {
                console.error(`Error al ${accion} reporte:`, error);
                Swal.fire("Error", `No se pudo ${accion} el reporte.`, "error");
            }
        },

        async eliminarDefinitivamente(id) {
            try {
                const result = await Swal.fire({
                    title: "Eliminar definitivamente",
                    text: "¿Está seguro? Esta acción no se puede deshacer.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#ef4444",
                    confirmButtonText: "Sí, eliminar permanentemente",
                    cancelButtonText: "Cancelar",
                });

                if (!result.isConfirmed) return;

                const response = await axios.delete(
                    `${VITE_URL_APP}/api/capacitacion/eliminar-reporte/${id}`,
                );

                if (response.data.success) {
                    this.reportes = this.reportes.filter((r) => r.id !== id);
                    this.cacheLoaded = false;
                    Swal.fire("Eliminado", response.data.message, "success");
                }
            } catch (error) {
                console.error(
                    "Error al eliminar reporte permanentemente:",
                    error,
                );
                Swal.fire(
                    "Error",
                    "No se pudo eliminar el reporte permanentemente.",
                    "error",
                );
            }
        },

        async descargarArchivo(id, tipo) {
            Swal.fire({
                title: "Consultando reporte",
                text: "Espere un momento, se está consultando el reporte...",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });
            try {
                const response = await axios.get(
                    `${VITE_URL_APP}/api/capacitacion/descargar-reporte/${id}/${tipo}`,
                    {
                        responseType: "blob",
                    },
                );

                const contentDisposition =
                    response.headers["content-disposition"];
                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                let nombreArchivo = `REPORTE_${fechaRep}.${tipo === "pdf" ? "pdf" : "xlsx"}`;
                if (contentDisposition) {
                    const match =
                        contentDisposition.match(/filename="?(.+?)"?$/);
                    if (match) {
                        nombreArchivo = match[1];
                    }
                }

                const blob = new Blob([response.data]);
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = nombreArchivo;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                Swal.close();
            } catch (error) {
                console.error("Error al descargar archivo:", error);
                Swal.close();
                Swal.fire(
                    "Error",
                    "No se pudo descargar el archivo. Intente nuevamente.",
                    "error",
                );
            }
        },

        toggleSeleccion(id, seleccionado) {
            if (seleccionado) {
                if (!this.selectedReportes.includes(id)) {
                    this.selectedReportes.push(id);
                }
            } else {
                this.selectedReportes = this.selectedReportes.filter(
                    (rId) => rId !== id,
                );
            }
        },

        toggleSeleccionTodos(seleccionado) {
            if (seleccionado) {
                this.selectedReportes = this.reportesSeleccionables.map(
                    (r) => r.id,
                );
            } else {
                this.selectedReportes = [];
            }
        },

        async descargarSeleccionadosZip() {
            if (this.selectedReportes.length === 0) return;

            this.downloadingZip = true;
            try {
                const response = await axios.post(
                    `${VITE_URL_APP}/api/capacitacion/descargar-reportes-zip`,
                    {
                        ids: this.selectedReportes,
                    },
                    {
                        responseType: "blob",
                    },
                );

                const blob = new Blob([response.data], {
                    type: "application/zip",
                });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = "reportes_capacitaciones.zip";
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                Swal.fire(
                    "Éxito",
                    "Archivo ZIP descargado correctamente.",
                    "success",
                );
            } catch (error) {
                console.error("Error al descargar ZIP:", error);
                Swal.fire(
                    "Error",
                    "No se pudo generar el archivo ZIP.",
                    "error",
                );
            } finally {
                this.downloadingZip = false;
            }
        },

        formatearFecha(fecha) {
            if (!fecha) return "—";
            const d = new Date(fecha);
            const dia = String(d.getDate()).padStart(2, "0");
            const mes = String(d.getMonth() + 1).padStart(2, "0");
            const anio = d.getFullYear();
            const hora = String(d.getHours()).padStart(2, "0");
            const min = String(d.getMinutes()).padStart(2, "0");
            return `${dia}/${mes}/${anio} ${hora}:${min}`;
        },
    }));

    Alpine.data("modalRecordPersonal", () => ({
        open: false,
        view: "filters",

        loadingSistemas: false,
        loadingAreas: false,
        loadingCursos: false,
        loadingPersonal: false,
        loadingClientes: false,
        loadingSucursales: false,
        buscando: false,
        generandoPDF: false,
        generandoExcel: false,
        loadingInicial: true,

        selectedSistema: "",
        selectedArea: "",
        selectedCliente: "",
        selectedSucursal: "",
        selectedTipoTrabajador: "",
        selectedCargo: "",
        searchPersonal: "",
        searchCurso: "",
        selectedEstadoId: "0",
        selectedFechaDesde: "",
        selectedFechaHasta: "",
        selectedAnio: "",

        sistemas: [],
        aniosDisponibles: [],
        areas: [],
        cursos: [],
        todosLosCursos: [],
        personalesFiltrados: [],
        todosLosPersonales: [],
        clientes: [],
        sucursales: [],
        tiposTrabajador: [],
        cargos: [],

        selectedCourseIds: [],
        selectedUsernames: [],
        importados: [],
        selectAllCursos: false,
        selectAllPersonal: false,

        personalPerPage: 20,
        cursosPerPage: 15,
        personalPage: 1,
        cursosPage: 1,
        selectedPersonalIdx: -1,
        sortColumn: null,
        sortDirection: null,

        get personalTotalPages() {
            return Math.max(
                1,
                Math.ceil(
                    this.personalesFiltrados.length / this.personalPerPage,
                ),
            );
        },

        get cursosTotalPages() {
            return Math.max(
                1,
                Math.ceil(this.cursos.length / this.cursosPerPage),
            );
        },

        get cursosPersonalActual() {
            const p =
                this.selectedPersonalIdx >= 0
                    ? this.resultados[this.selectedPersonalIdx]
                    : null;
            const cursos = p?.Cursos || [];
            if (!this.sortColumn) {
                return [...cursos].sort(
                    (a, b) => _rankEstado(a.Estado) - _rankEstado(b.Estado),
                );
            }
            return cursos;
        },

        get cursosPersonalActualPaginado() {
            const start = (this.cursosPage - 1) * this.cursosPerPage;
            return this.cursosPersonalActual.slice(
                start,
                start + this.cursosPerPage,
            );
        },

        get totalPagesCursosPersonal() {
            return Math.max(
                1,
                Math.ceil(
                    this.cursosPersonalActual.length / this.cursosPerPage,
                ),
            );
        },

        personalesPaginados() {
            const start = (this.personalPage - 1) * this.personalPerPage;
            return this.personalesFiltrados.slice(
                start,
                start + this.personalPerPage,
            );
        },
        
        cursosPaginados() {
            const start = (this.cursosPage - 1) * this.cursosPerPage;
            return this.cursos.slice(start, start + this.cursosPerPage);
        },

        resultados: [],
        totalResultados: 0,

        async init() {
            window.addEventListener("abrir-record-personal", () => {
                this.abrir();
            });
        },

        async abrir() {
            this.open = true;
            this.loadingInicial = true;

            const catalogs = await ensureCatalogsLoaded();

            this.sistemas = catalogs.sistemas;
            this.sucursales = catalogs.sucursales;
            this.todosLosCursos = catalogs.cursos;
            this.aniosDisponibles = [
                ...new Set(
                    catalogs.cursos.map((c) => c.Anio_Curso).filter(Boolean),
                ),
            ].sort((a, b) => b - a);
            this.filtrarCursos();
            this.todosLosPersonales = catalogs.personales;
            const tipos = [
                ...new Set(
                    catalogs.personales
                        .map((p) => p.tipo_trabajador)
                        .filter(Boolean),
                ),
            ];
            this.tiposTrabajador = tipos.sort();
            const cargos = [
                ...new Set(
                    catalogs.personales.map((p) => p.cargo).filter(Boolean),
                ),
            ];
            this.cargos = cargos.sort();
            this.filtrarPersonales();

            this.cargarClientes();
            this.loadingInicial = false;
        },

        async cargarSistemas() {
            this.loadingSistemas = true;
            try {
                this.sistemas = await _fetchSistemas();
            } catch (e) {
                console.error(e);
                this.sistemas = [];
            } finally {
                this.loadingSistemas = false;
            }
        },

        async cargarAreas() {
            this.loadingAreas = true;
            try {
                let areas = [];
                if (this.selectedSistema) {
                    const { data } = await axios.get(
                        `${VITE_URL_APP}/api/catalogos-capacitacion/areas-por-sistema/${this.selectedSistema}`,
                    );
                    if (data.success) {
                        areas = (data.areas || []).map((a) => ({
                            codModdle: a.codModdle || a.codArea || "",
                            Area: a.Area || a.nombre || a.descripcion || "",
                        }));
                    }
                } else {
                    areas = await _fetchAreas();
                }
                this.areas = areas;
            } catch (e) {
                console.error(e);
                this.areas = [];
            } finally {
                this.loadingAreas = false;
            }
        },

        async cargarCursos() {
            this.loadingCursos = true;
            try {
                const response = await axios.get(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/cursos`,
                );
                if (response.data.success) {
                    this.todosLosCursos = response.data.Cursos || [];
                    this.filtrarCursos();
                }
            } catch (e) {
                console.error(e);
                this.cursos = [];
                this.todosLosCursos = [];
            } finally {
                this.loadingCursos = false;
            }
        },

        async cargarPersonales() {
            this.loadingPersonal = true;
            try {
                const personales = await _fetchPersonales();
                this.todosLosPersonales = personales;
                const tipos = [
                    ...new Set(
                        personales
                            .map((p) => p.tipo_trabajador)
                            .filter(Boolean),
                    ),
                ];
                this.tiposTrabajador = tipos.sort();
                const cargos = [
                    ...new Set(personales.map((p) => p.cargo).filter(Boolean)),
                ];
                this.cargos = cargos.sort();
                this.filtrarPersonales();
            } catch (e) {
                console.error(e);
                this.todosLosPersonales = [];
                this.personalesFiltrados = [];
            } finally {
                this.loadingPersonal = false;
            }
        },

        async cargarClientes() {
            this.loadingClientes = true;
            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/get-clientes-pac`,
                );
                this.clientes = Array.isArray(data) ? data : [];
            } catch (e) {
                console.error(e);
                this.clientes = [];
            } finally {
                this.loadingClientes = false;
            }
        },

        async cargarSucursales() {
            this.loadingSucursales = true;
            try {
                this.sucursales = await _fetchSucursales();
            } catch (e) {
                console.error(e);
                this.sucursales = [];
            } finally {
                this.loadingSucursales = false;
            }
        },

        async onSistemaChange() {
            this.selectedArea = "";
            this.areas = [];
            await this.cargarAreas();
            this.filtrarCursos();
        },

        async onAreaChange() {
            this.filtrarCursos();
        },

        filtrarCursos() {
            this.cursos = this.todosLosCursos.filter((c) => {
                if (
                    this.selectedSistema &&
                    String(c.SistemaId || c.sistema_id || "") !==
                        String(this.selectedSistema)
                ) {
                    const sistema = (c.Sistema || "").toString().toLowerCase();
                    const sistSel = this.sistemas.find(
                        (s) =>
                            String(s.codigo) === String(this.selectedSistema),
                    );
                    if (
                        !sistSel ||
                        !sistema.includes(sistSel.descripcion.toLowerCase())
                    ) {
                        return false;
                    }
                }
                if (this.selectedArea) {
                    const areaId = String(
                        c.AreaId || c.area_id || c.codModdle || "",
                    );
                    if (areaId !== String(this.selectedArea)) {
                        const area = (c.Area || "").toString().toLowerCase();
                        const areaSel = this.areas.find(
                            (a) =>
                                String(a.codModdle) ===
                                String(this.selectedArea),
                        );
                        if (
                            !areaSel ||
                            !area.includes(areaSel.Area.toLowerCase())
                        ) {
                            return false;
                        }
                    }
                }
                if (this.selectedFechaDesde && c.Fecha_Creacion) {
                    const desdeTs =
                        new Date(this.selectedFechaDesde).getTime() / 1000;
                    if (c.Fecha_Creacion < desdeTs) return false;
                }
                if (this.selectedFechaHasta && c.Fecha_Creacion) {
                    const hastaTs =
                        new Date(
                            this.selectedFechaHasta + " 23:59:59",
                        ).getTime() / 1000;
                    if (c.Fecha_Creacion > hastaTs) return false;
                }
                if (
                    this.selectedAnio &&
                    c.Anio_Curso &&
                    Number(c.Anio_Curso) !== Number(this.selectedAnio)
                )
                    return false;
                if (this.searchCurso) {
                    const term = this.searchCurso.toLowerCase();
                    if (!(c.Nombre || "").toLowerCase().includes(term))
                        return false;
                }
                return true;
            });
            this.cursosPage = 1;
        },

        filtrarPersonales() {
            let resultados = [...this.todosLosPersonales];
            if (this.selectedCliente) {
                const clienteSel = this.clientes.find(
                    (c) => String(c.codigo) === String(this.selectedCliente),
                );
                if (clienteSel) {
                    resultados = resultados.filter(
                        (p) =>
                            String(p.cliente) ===
                            String(clienteSel.descripcion),
                    );
                } else {
                    resultados = [];
                }
            }
            if (this.selectedSucursal) {
                const sucursalSel = this.sucursales.find(
                    (s) => String(s.codigo) === String(this.selectedSucursal),
                );
                if (sucursalSel) {
                    resultados = resultados.filter(
                        (p) =>
                            String(p.sucursal) === String(sucursalSel.sucursal),
                    );
                } else {
                    resultados = [];
                }
            }
            if (this.selectedTipoTrabajador) {
                resultados = resultados.filter(
                    (p) =>
                        String(p.tipo_trabajador) ===
                        String(this.selectedTipoTrabajador),
                );
            }
            if (this.selectedCargo) {
                resultados = resultados.filter(
                    (p) => String(p.cargo) === String(this.selectedCargo),
                );
            }
            if (this.searchPersonal) {
                const term = this.searchPersonal.toLowerCase();
                resultados = resultados.filter(
                    (p) =>
                        (p.nombre_completo || "")
                            .toLowerCase()
                            .includes(term) || (p.dni || "").includes(term),
                );
            }
            this.personalesFiltrados = resultados;
            this.personalPage = 1;
            this.recalcularSeleccionPersonal();
        },

        toggleCurso(id) {
            const idx = this.selectedCourseIds.indexOf(id);
            if (idx === -1) {
                this.selectedCourseIds.push(id);
            } else {
                this.selectedCourseIds.splice(idx, 1);
            }
            this.selectAllCursos =
                this.selectedCourseIds.length === this.cursos.length &&
                this.cursos.length > 0;
        },

        toggleAllCursos() {
            if (this.selectAllCursos) {
                this.selectedCourseIds = [];
                this.selectAllCursos = false;
            } else {
                this.selectedCourseIds = this.cursos
                    .map((c) => c.Id)
                    .filter(Boolean);
                this.selectAllCursos = true;
            }
        },

        togglePersonal(dni) {
            const idx = this.selectedUsernames.indexOf(dni);
            if (idx === -1) {
                this.selectedUsernames.push(dni);
            } else {
                this.selectedUsernames.splice(idx, 1);
                this.importados = this.importados.filter(
                    (i) => i.dni !== dni,
                );
            }
            this.recalcularSeleccionPersonal();
        },

        toggleAllPersonal() {
            if (this.selectAllPersonal) {
                this.selectedUsernames = [];
                this.importados = [];
                this.selectAllPersonal = false;
                return;
            }

            const dnis = [
                ...this.personalesFiltrados.map((p) => p.dni).filter(Boolean),
                ...this.importados.map((i) => i.dni),
            ];
            this.selectedUsernames = [...new Set(dnis)];
            this.selectAllPersonal = true;
        },

        recalcularSeleccionPersonal() {
            this.selectAllPersonal =
                this.personalesFiltrados.length > 0 &&
                this.personalesFiltrados.every((p) =>
                    this.selectedUsernames.includes(p.dni),
                );
        },

        aplicarDnisImportados(detalle) {
            if (!detalle || detalle.target !== "record") return;

            const encontrados = Array.isArray(detalle.encontrados)
                ? detalle.encontrados
                : [];

            if (encontrados.length === 0) return;

            const seleccionados = new Set(this.selectedUsernames);
            const mapa = new Map(this.importados.map((i) => [i.dni, i]));

            encontrados.forEach((persona) => {
                if (!persona?.dni) return;
                seleccionados.add(persona.dni);
                mapa.set(persona.dni, persona);
            });

            this.selectedUsernames = [...seleccionados];
            this.importados = [...mapa.values()];
            this.recalcularSeleccionPersonal();
        },

        quitarImportados() {
            if (this.importados.length === 0) return;

            const dnis = new Set(this.importados.map((i) => i.dni));
            this.selectedUsernames = this.selectedUsernames.filter(
                (dni) => !dnis.has(dni),
            );
            this.importados = [];
            this.recalcularSeleccionPersonal();
        },

        async obtenerPersonalRecord() {
            if (this.selectedUsernames.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un personal.",
                    "warning",
                );
                return;
            }
            if (this.selectedCourseIds.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un curso.",
                    "warning",
                );
                return;
            }

            this.view = "results";
            this.resultados = [];
            this.buscando = true;

            try {
                const payload = {
                    usernames: this.selectedUsernames || null,
                    courseIds: this.selectedCourseIds || null,
                    desde: this.selectedFechaDesde || null,
                    hasta: this.selectedFechaHasta || null,
                    estadoId: parseInt(this.selectedEstadoId) || 0,
                };

                const response = await axios.post(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/personal-record`,
                    payload,
                );

                if (!response.data.success) {
                    Swal.fire(
                        "Error",
                        response.data.message || "No se obtuvieron resultados.",
                        "warning",
                    );
                    return;
                }

                const personales = response.data.Personales || [];
                if (personales.length === 0) {
                    Swal.fire(
                        "Atención",
                        "No se encontraron resultados con los criterios seleccionados.",
                        "warning",
                    );
                    return;
                }

                this.resultados = personales;
                this.totalResultados = personales.length;
                this.selectedPersonalIdx = 0;
                this.cursosPage = 1;
                this.sortColumn = null;
                this.sortDirection = null;
            } catch (error) {
                console.error(error);
                Swal.fire(
                    "Error",
                    error.response?.data?.message ||
                        "No se pudo obtener el récord.",
                    "error",
                );
            } finally {
                this.buscando = false;
            }
        },

        seleccionarPersonal(idx) {
            this.selectedPersonalIdx = idx;
            this.cursosPage = 1;
            this.sortColumn = null;
            this.sortDirection = null;
        },

        ordenarCursos(columna) {
            if (this.sortColumn === columna) {
                this.sortDirection =
                    this.sortDirection === "asc" ? "desc" : "asc";
            } else {
                this.sortColumn = columna;
                this.sortDirection = "asc";
            }

            const cursos = this.cursosPersonalActual;
            const dir = this.sortDirection;
            const col = this.sortColumn;

            cursos.sort((a, b) => {
                if (col === "Nota_Final") {
                    const va = parseFloat(a[col]) || 0;
                    const vb = parseFloat(b[col]) || 0;
                    return dir === "asc" ? va - vb : vb - va;
                }
                if (col === "Fecha_Acceso") {
                    const va = a.Fecha_Nota || a.Fecha_Ultimo_Acceso || "";
                    const vb = b.Fecha_Nota || b.Fecha_Ultimo_Acceso || "";
                    if (va < vb) return dir === "asc" ? -1 : 1;
                    if (va > vb) return dir === "asc" ? 1 : -1;
                    return 0;
                }
                if (col === "Estado") {
                    return dir === "asc"
                        ? _rankEstado(a.Estado) - _rankEstado(b.Estado)
                        : _rankEstado(b.Estado) - _rankEstado(a.Estado);
                }
                const va = (a[col] || "").toString().toLowerCase();
                const vb = (b[col] || "").toString().toLowerCase();
                const cmp = va.localeCompare(vb, "es", { sensitivity: "base" });
                return dir === "asc" ? cmp : -cmp;
            });

            this.cursosPage = 1;
        },

        cerrar() {
            if (_importarDnisAbierto) return;

            this.open = false;
            this.view = "filters";
            this.selectedSistema = "";
            this.selectedArea = "";
            this.selectedCliente = "";
            this.selectedSucursal = "";
            this.selectedTipoTrabajador = "";
            this.selectedCargo = "";
            this.searchPersonal = "";
            this.searchCurso = "";
            this.selectedEstadoId = "0";
            this.selectedFechaDesde = "";
            this.selectedFechaHasta = "";
            this.selectedAnio = "";
            this.selectedCourseIds = [];
            this.selectedUsernames = [];
            this.importados = [];
            this.selectAllCursos = false;
            this.selectAllPersonal = false;
            this.resultados = [];
            this.totalResultados = 0;
            this.selectedPersonalIdx = -1;
            this.buscando = false;
        },

        volverAFiltros() {
            this.view = "filters";
            this.resultados = [];
            this.totalResultados = 0;
            this.selectedPersonalIdx = -1;
            this.buscando = false;
        },

        async exportarPDFRecord() {
            if (this.generandoPDF) return;
            if (this.selectedUsernames.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un personal.",
                    "warning",
                );
                return;
            }
            if (this.selectedCourseIds.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un curso.",
                    "warning",
                );
                return;
            }

            this.generandoPDF = true;

            Swal.fire({
                title: "Generando PDF...",
                text: "Por favor espere mientras se genera el reporte.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const payload = {
                    usernames: this.selectedUsernames || null,
                    courseIds: this.selectedCourseIds || null,
                    desde: this.selectedFechaDesde || null,
                    hasta: this.selectedFechaHasta || null,
                    estadoId: parseInt(this.selectedEstadoId) || 0,
                };

                const response = await axios.post(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/personal-record`,
                    payload,
                );

                if (!response.data.success) {
                    Swal.close();
                    Swal.fire(
                        "Error",
                        response.data.message || "No se obtuvieron resultados.",
                        "warning",
                    );
                    return;
                }

                const personales = response.data.Personales || [];
                if (personales.length === 0) {
                    Swal.close();
                    Swal.fire(
                        "Atención",
                        "No se encontraron resultados para generar el PDF.",
                        "warning",
                    );
                    return;
                }

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({
                    orientation: "portrait",
                    unit: "mm",
                    format: "a4",
                });

                const logoSol = await _cargarImagen(
                    _assetUrl("/images/logo_sol.png"),
                );

                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const marginL = 14;
                const marginR = 14;
                const contentWidth = pageWidth - marginL - marginR;

                // ── Formatea fecha "YYYY-MM-DD" → "DD/MM/YYYY" ──────────────────
                const fmtFecha = (val) => {
                    if (!val) return "";
                    const m = String(val).match(/(\d{4})-(\d{2})-(\d{2})/);
                    return m ? `${m[3]}/${m[2]}/${m[1]}` : val;
                };

                // ── Rango de fechas para el subtítulo ────────────────────────────
                const rangoTexto = (() => {
                    const d = this.selectedFechaDesde
                        ? fmtFecha(this.selectedFechaDesde)
                        : null;
                    const h = this.selectedFechaHasta
                        ? fmtFecha(this.selectedFechaHasta)
                        : null;
                    if (d && h) return `Del ${d} al ${h}`;
                    if (d) return `Desde ${d}`;
                    if (h) return `Hasta ${h}`;
                    return "";
                })();

                // ── Cabecera (logo + título) – se dibuja en la página actual ─────
                const dibujarCabecera = () => {
                    let logoBottomY = 26;
                    const logoWidth = 60;

                    if (logoSol) {
                        const ratio = logoSol.height / logoSol.width;
                        const logoH = logoWidth * ratio;
                        doc.addImage(
                            logoSol,
                            "PNG",
                            marginL,
                            10,
                            logoWidth,
                            logoH,
                        );
                        logoBottomY = 10 + logoH;
                    }

                    // Línea + dirección bajo el logo
                    const lineY = logoBottomY + 2;
                    doc.setDrawColor(150, 150, 150);
                    doc.setLineWidth(0.2);
                    doc.line(marginL, lineY, marginL + 75, lineY);

                    doc.setFont("helvetica", "italic");
                    doc.setFontSize(7);
                    doc.setTextColor(80, 80, 80);
                    doc.text(
                        "Chimbote: Calle Los Laureles Nº206 Urb. La Caleta",
                        marginL,
                        lineY + 4,
                    );
                    doc.text("RUC: 20445414833", marginL, lineY + 7);

                    // Título centrado (debajo del logo y dirección)
                    const tituloY = logoBottomY + 25;
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(13);
                    doc.setTextColor(0, 0, 0);
                    const titulo = "RECORD DE CAPACITACIONES";
                    doc.text(titulo, pageWidth / 2, tituloY, {
                        align: "center",
                    });

                    // Subrayado del título
                    const tW = doc.getTextWidth(titulo);
                    doc.setLineWidth(0.3);
                    doc.setDrawColor(0, 0, 0);
                    doc.line(
                        pageWidth / 2 - tW / 2,
                        tituloY + 1.5,
                        pageWidth / 2 + tW / 2,
                        tituloY + 1.5,
                    );

                    // Subtítulo con rango
                    if (rangoTexto) {
                        doc.setFont("helvetica", "bold");
                        doc.setFontSize(9);
                        doc.text(rangoTexto, pageWidth / 2, tituloY + 7, {
                            align: "center",
                        });
                    }

                    // Y de inicio del contenido
                    return rangoTexto ? tituloY + 15 : tituloY + 10;
                };

                // ── Primera cabecera ─────────────────────────────────────────────
                let yPos = dibujarCabecera();

                // ── Iterar por personal ──────────────────────────────────────────
                for (let idx = 0; idx < personales.length; idx++) {
                    const personal = personales[idx];

                    // Espacio mínimo estimado antes de decidir nueva página
                    // (etiqueta sucursal ~6 + tabla datos ~22 + encabezado cursos ~8 = ~36 mm)
                    if (idx > 0) {
                        if (yPos + 36 > pageHeight - 10) {
                            doc.addPage();
                            yPos = 14;
                        } else {
                            yPos += 6; // separación entre personas
                        }
                    }

                    // ── Etiqueta de sucursal ─────────────────────────────────────
                    doc.setFillColor(255, 255, 255);
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.text(personal.Sucursal || "—", marginL, yPos + 4);
                    yPos += 7;

                    // ── Tabla DATOS GENERALES ────────────────────────────────────
                    // Cabecera de sección
                    doc.setFillColor(253, 245, 230);
                    doc.rect(marginL, yPos, contentWidth, 6, "F");
                    doc.setDrawColor(0, 0, 0);
                    doc.setLineWidth(0.2);
                    doc.rect(marginL, yPos, contentWidth, 6, "S");
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(8);
                    doc.setTextColor(0, 0, 0);
                    doc.text("DATOS GENERALES", marginL + 2, yPos + 4);
                    yPos += 6;

                    // Fila 1: Nº Documento | valor | Apellidos y Nombres | valor
                    const col1W = 28,
                        col2W = 38,
                        col3W = 38,
                        col4W = contentWidth - col1W - col2W - col3W;
                    const rowH = 6;

                    const drawCell = (
                        x,
                        y,
                        w,
                        h,
                        text,
                        bold = false,
                        bg = null,
                    ) => {
                        if (bg) {
                            doc.setFillColor(...bg);
                            doc.rect(x, y, w, h, "F");
                        }
                        doc.setDrawColor(0, 0, 0);
                        doc.setLineWidth(0.2);
                        doc.rect(x, y, w, h, "S");
                        doc.setFont("helvetica", bold ? "bold" : "normal");
                        doc.setFontSize(8);
                        doc.setTextColor(0, 0, 0);
                        // Truncar si es necesario
                        const maxW = w - 3;
                        let t = text || "—";
                        while (doc.getTextWidth(t) > maxW && t.length > 1)
                            t = t.slice(0, -1);
                        doc.text(t, x + 2, y + 4);
                    };

                    let cx = marginL;
                    // Fila 1
                    drawCell(
                        cx,
                        yPos,
                        col1W,
                        rowH,
                        "Nº Documento",
                        true,
                        [253, 245, 230],
                    );
                    drawCell(
                        cx + col1W,
                        yPos,
                        col2W,
                        rowH,
                        personal.NroDoc || "—",
                    );
                    drawCell(
                        cx + col1W + col2W,
                        yPos,
                        col3W,
                        rowH,
                        "Apellidos y Nombres",
                        true,
                        [253, 245, 230],
                    );
                    drawCell(
                        cx + col1W + col2W + col3W,
                        yPos,
                        col4W,
                        rowH,
                        personal.NombreCompleto || "—",
                    );
                    yPos += rowH;

                    // Fila 2: Código Pers. | valor | Cargo | valor
                    drawCell(
                        cx,
                        yPos,
                        col1W,
                        rowH,
                        "Código Pers.",
                        true,
                        [253, 245, 230],
                    );
                    drawCell(
                        cx + col1W,
                        yPos,
                        col2W,
                        rowH,
                        personal.CodigoPersonal || "—",
                    );
                    drawCell(
                        cx + col1W + col2W,
                        yPos,
                        col3W,
                        rowH,
                        "Cargo",
                        true,
                        [253, 245, 230],
                    );
                    drawCell(
                        cx + col1W + col2W + col3W,
                        yPos,
                        col4W,
                        rowH,
                        personal.Cargo || "—",
                    );
                    yPos += rowH;

                    // Fila 3: Tipo Trabajador | valor | Cliente | valor
                    drawCell(
                        cx,
                        yPos,
                        col1W,
                        rowH,
                        "Tipo Trabajador",
                        true,
                        [253, 245, 230],
                    );
                    drawCell(
                        cx + col1W,
                        yPos,
                        col2W,
                        rowH,
                        personal.TipoTrabajador || "—",
                    );
                    drawCell(
                        cx + col1W + col2W,
                        yPos,
                        col3W,
                        rowH,
                        "Cliente",
                        true,
                        [253, 245, 230],
                    );
                    drawCell(
                        cx + col1W + col2W + col3W,
                        yPos,
                        col4W,
                        rowH,
                        personal.Cliente || "—",
                    );
                    yPos += rowH;

                    // ── Tabla de cursos con autoTable ────────────────────────────
                    const cursos = personal.Cursos || [];
                    const filas = cursos.map((c, i) => [
                        i + 1,
                        c.Nombre || "",
                        c.Estado || "",
                        c.Nota_Final != null
                            ? c.Fecha_Nota
                                ? fmtFecha(c.Fecha_Nota)
                                : ""
                            : c.Fecha_Ultimo_Acceso
                              ? fmtFecha(c.Fecha_Ultimo_Acceso)
                              : "",
                        c.Nota_Final != null ? c.Nota_Final : "",
                    ]);

                    doc.autoTable({
                        startY: yPos,
                        head: [
                            [
                                "It",
                                "Capacitación",
                                "Estado",
                                "Fecha / Últ. acceso",
                                "Nota",
                            ],
                        ],
                        body: filas,
                        theme: "grid",
                        styles: {
                            fontSize: 7.5,
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.1,
                            valign: "middle",
                            cellPadding: 1.5,
                        },
                        headStyles: {
                            fillColor: [253, 245, 230],
                            textColor: [0, 0, 0],
                            fontStyle: "bold",
                            halign: "center",
                        },
                        columnStyles: {
                            0: { cellWidth: 8, halign: "center" },
                            1: { cellWidth: "auto", halign: "left" },
                            2: { cellWidth: 24, halign: "center" },
                            3: { cellWidth: 20, halign: "center" },
                            4: { cellWidth: 12, halign: "center" },
                        },
                        margin: { left: marginL, right: marginR },
                        // Si la tabla se corta en otra página, redibujar cabecera
                        didDrawPage: (data) => {
                            if (data.pageNumber > 1 || idx > 0) {
                                // la cabecera ya fue dibujada al addPage arriba,
                                // aquí solo actualizamos yPos si autoTable salto de página
                            }
                        },
                    });

                    yPos = doc.lastAutoTable.finalY + 2;
                }

                // ── Guardar y descargar ──────────────────────────────────────────
                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                const nombreArchivo = `RECORD_CAPACITACIONES_${fechaRep}.pdf`;

                const pdfBlob = doc.output("blob");

                try {
                    const formData = new FormData();
                    formData.append("nombre_archivo", nombreArchivo);
                    formData.append("archivo_pdf", pdfBlob, nombreArchivo);
                    // Registro en historial deshabilitado temporalmente.
                    // await axios.post(
                    //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                    //     formData,
                    // );
                    // window.dispatchEvent(
                    //     new CustomEvent("historial-reportes-actualizado"),
                    // );
                } catch (error) {
                    console.error("Error al guardar en historial:", error);
                }

                saveAs(pdfBlob, nombreArchivo);
                Swal.close();
                Swal.fire("Éxito", "PDF generado correctamente.", "success");
            } catch (error) {
                console.error(error);
                Swal.close();
                Swal.fire("Error", "No se pudo generar el PDF.", "error");
            } finally {
                this.generandoPDF = false;
            }
        },

        async registrarReporteEnHistorial(nombreArchivo, pdfBlob, excelBlob) {
            try {
                const formData = new FormData();
                formData.append("nombre_archivo", nombreArchivo);
                formData.append("descripcion", "");

                if (pdfBlob) {
                    formData.append(
                        "archivo_pdf",
                        pdfBlob,
                        nombreArchivo.replace(/\.pdf$/i, "") + ".pdf",
                    );
                }

                if (excelBlob) {
                    formData.append(
                        "archivo_excel",
                        excelBlob,
                        nombreArchivo.replace(/\.xlsx$/i, "") + ".xlsx",
                    );
                }

                // Registro en historial deshabilitado temporalmente.
                // await axios.post(
                //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                //     formData,
                // );

                // window.dispatchEvent(
                //     new CustomEvent("historial-reportes-actualizado"),
                // );
            } catch (error) {
                console.error(
                    "Error al registrar reporte en historial:",
                    error,
                );
                Swal.fire(
                    "Error",
                    error.response?.data?.message ||
                        "No se pudo guardar en el historial de reportes.",
                    "warning",
                );
            }
        },

        async exportarExcelRecord() {
            if (this.generandoExcel) return;
            if (this.selectedUsernames.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un personal.",
                    "warning",
                );
                return;
            }
            if (this.selectedCourseIds.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un curso.",
                    "warning",
                );
                return;
            }

            this.generandoExcel = true;

            Swal.fire({
                title: "Generando Excel...",
                text: "Por favor espere mientras se genera el reporte.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const payload = {
                    usernames: this.selectedUsernames || null,
                    courseIds: this.selectedCourseIds || null,
                    desde: this.selectedFechaDesde || null,
                    hasta: this.selectedFechaHasta || null,
                    estadoId: parseInt(this.selectedEstadoId) || 0,
                };

                const response = await axios.post(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/personal-record`,
                    payload,
                );

                if (!response.data.success) {
                    Swal.close();
                    Swal.fire(
                        "Error",
                        response.data.message || "No se obtuvieron resultados.",
                        "warning",
                    );
                    return;
                }

                const personales = response.data.Personales || [];
                if (personales.length === 0) {
                    Swal.close();
                    Swal.fire(
                        "Atención",
                        "No se encontraron resultados para exportar.",
                        "warning",
                    );
                    return;
                }

                const workbook = new ExcelJS.Workbook();
                const logoImageId = await _cargarLogoExcel(workbook);
                const sheet = workbook.addWorksheet("Record");

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;

                const headerRowNumber = 5;

                if (logoImageId !== null) {
                    sheet.addImage(logoImageId, {
                        tl: { col: 1, row: 0 },
                        br: { col: 3, row: 2 },
                        editAs: "absolute",
                    });
                }

                sheet.getRow(1).height = 50;

                sheet.mergeCells("D1:H1");
                const infoCell = sheet.getCell("D1");
                infoCell.value = "RÉCORD DE CAPACITACIONES";
                infoCell.font = {
                    bold: true,
                    size: 13,
                    color: { argb: "FF1F4E79" },
                };
                infoCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.mergeCells("D2:H2");
                const totalCell = sheet.getCell("D2");
                totalCell.value = `Total: ${personales.length} personal(es)`;
                totalCell.font = { size: 11, color: { argb: "FF333333" } };
                totalCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.getRow(4).height = 8;

                const headers = [
                    "#",
                    "Sucursal",
                    "N° Documento",
                    "Apellidos y Nombres",
                    "Código Pers.",
                    "Cargo",
                    "Tipo Trabajador",
                    "Cliente",
                    "Capacitación",
                    "Estado",
                    "Nota Final",
                ];

                const headerRow = sheet.getRow(headerRowNumber);
                headerRow.values = headers;
                headerRow.eachCell((cell) => _estiloEncabezadoExcel(cell));

                let rowIdx = 1;
                personales.forEach((personal) => {
                    const cursos = personal.Cursos || [];
                    if (cursos.length === 0) {
                        const row = sheet.addRow([
                            rowIdx++,
                            personal.Sucursal || "—",
                            personal.NroDoc || "—",
                            personal.NombreCompleto || "—",
                            personal.CodigoPersonal || "—",
                            personal.Cargo || "—",
                            personal.TipoTrabajador || "—",
                            personal.Cliente || "—",
                            "—",
                            "—",
                            "—",
                        ]);
                        row.eachCell((cell) => _estiloDatoExcel(cell));
                    } else {
                        cursos.forEach((curso) => {
                            const row = sheet.addRow([
                                rowIdx++,
                                personal.Sucursal || "—",
                                personal.NroDoc || "—",
                                personal.NombreCompleto || "—",
                                personal.CodigoPersonal || "—",
                                personal.Cargo || "—",
                                personal.TipoTrabajador || "—",
                                personal.Cliente || "—",
                                curso.Nombre || "—",
                                curso.Estado || "—",
                                curso.Nota_Final != null
                                    ? curso.Nota_Final
                                    : "—",
                            ]);
                            row.eachCell((cell) => _estiloDatoExcel(cell));
                        });
                    }
                });

                sheet.columns = [
                    { width: 5 }, // #
                    { width: 16 }, // Sucursal
                    { width: 16 }, // N° Documento
                    { width: 35 }, // Apellidos y Nombres
                    { width: 14 }, // Código Pers.
                    { width: 22 }, // Cargo
                    { width: 18 }, // Tipo Trabajador
                    { width: 18 }, // Cliente
                    { width: 35 }, // Capacitación
                    { width: 14 }, // Estado
                    { width: 12 }, // Nota Final
                ];

                sheet.autoFilter = {
                    from: `A${headerRowNumber}`,
                    to: `K${headerRowNumber}`,
                };

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = _blobExcel(buffer);
                const nombreArchivo = `RECORD_CAPACITACIONES_${fechaRep}.xlsx`;

                await this.registrarReporteEnHistorial(
                    nombreArchivo,
                    null,
                    blob,
                );

                saveAs(blob, nombreArchivo);
                Swal.close();
                Swal.fire(
                    "Éxito",
                    "Record exportado a Excel correctamente.",
                    "success",
                );
            } catch (error) {
                console.error(error);
                Swal.close();
                Swal.fire("Error", "No se pudo exportar el Excel.", "error");
            } finally {
                this.generandoExcel = false;
            }
        },
    }));

    Alpine.data("modalReporteGeneral", () => ({
        open: false,
        loadingInicial: false,
        loadingPersonal: false,
        loadingCursos: false,
        loadingClientes: false,

        selectedCliente: "",
        selectedEmpresa: "",
        selectedSucursal: "",
        selectedTipoTrabajo: "",
        selectedCargo: "",
        searchPersonal: "",
        selectedFechaDesde: "",
        selectedFechaHasta: "",
        selectedEstado: "",
        searchCurso: "",

        clientes: [],
        empresas: [],
        sucursales: [],
        tiposTrabajo: [],
        cargos: [],
        todosLosPersonales: [],
        personalesFiltrados: [],
        cursos: [],
        todosLosCursos: [],

        selectedUsernames: [],
        selectedCourseIds: [],
        selectAllPersonal: false,
        selectAllCursos: false,
        importados: [],

        personalPerPage: 20,
        cursosPerPage: 15,
        personalPage: 1,
        cursosPage: 1,

        get personalTotalPages() {
            return Math.max(
                1,
                Math.ceil(
                    this.personalesFiltrados.length / this.personalPerPage,
                ),
            );
        },
        get cursosTotalPages() {
            return Math.max(
                1,
                Math.ceil(this.cursos.length / this.cursosPerPage),
            );
        },

        personalesPaginados() {
            const start = (this.personalPage - 1) * this.personalPerPage;
            return this.personalesFiltrados.slice(
                start,
                start + this.personalPerPage,
            );
        },
        cursosPaginados() {
            const start = (this.cursosPage - 1) * this.cursosPerPage;
            return this.cursos.slice(start, start + this.cursosPerPage);
        },

        init() {
            window.addEventListener("abrir-reporte-general", () => {
                this.abrir();
            });
        },

        async abrir() {
            this.open = true;
            this.loadingInicial = true;

            try {
                const catalogs = await ensureCatalogsLoaded();

                this.empresas = catalogs.empresas;
                this.sucursales = catalogs.sucursales;
                this.todosLosCursos = catalogs.cursos;
                this.filtrarCursos();

                const personales = catalogs.personalesTodasEmpresas;
                this.todosLosPersonales = personales;
                this.tiposTrabajo = catalogs.tiposTrabajo;
                this.cargos = catalogs.cargosReporte;

                this.filtrarPersonales();

                this.cargarClientes();
            } catch (e) {
                console.error("Error cargando datos:", e);
                this.empresas = [];
                this.sucursales = [];
                this.todosLosPersonales = [];
                this.personalesFiltrados = [];
                this.todosLosCursos = [];
                this.cursos = [];
            } finally {
                this.loadingInicial = false;
            }
        },

        async cargarClientes() {
            this.loadingClientes = true;
            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/get-clientes-pac`,
                );
                this.clientes = Array.isArray(data) ? data : [];
            } catch (e) {
                console.error(e);
                this.clientes = [];
            } finally {
                this.loadingClientes = false;
            }
        },

        filtrarPersonales() {
            let resultados = [...this.todosLosPersonales];

            if (this.selectedCliente) {
                const clienteSel = this.clientes.find(
                    (c) => String(c.codigo) === String(this.selectedCliente),
                );
                if (clienteSel) {
                    const desc = String(clienteSel.descripcion).toLowerCase();
                    resultados = resultados.filter((p) => {
                        const emp = String(p.empresa || "").toLowerCase();
                        return (
                            emp === desc ||
                            emp.includes(desc) ||
                            desc.includes(emp)
                        );
                    });
                } else {
                    resultados = [];
                }
            }

            if (this.selectedEmpresa === "__sin_empresa__") {
                resultados = resultados.filter(
                    (p) => !p.empresa && !p.empresa_codigo,
                );
            } else if (this.selectedEmpresa) {
                const empSel = this.empresas.find(
                    (e) => String(e.codigo) === String(this.selectedEmpresa),
                );
                if (empSel) {
                    const codigo = String(empSel.codigo).toLowerCase();
                    const desc = String(empSel.descripcion).toLowerCase();
                    resultados = resultados.filter((p) => {
                        const emp = String(
                            p.empresa || p.empresa_codigo || "",
                        ).toLowerCase();
                        if (!emp) return false;
                        return (
                            emp === desc ||
                            emp === codigo ||
                            emp.includes(desc) ||
                            desc.includes(emp)
                        );
                    });
                }
            }
            if (this.selectedSucursal) {
                const sucSel = this.sucursales.find(
                    (s) => String(s.codigo) === String(this.selectedSucursal),
                );
                if (sucSel) {
                    resultados = resultados.filter(
                        (p) =>
                            String(p.sucursal).toLowerCase() ===
                            String(sucSel.sucursal).toLowerCase(),
                    );
                }
            }
            if (this.selectedTipoTrabajo) {
                resultados = resultados.filter(
                    (p) =>
                        String(p.tipo_trabajador) ===
                        String(this.selectedTipoTrabajo),
                );
            }
            if (this.selectedCargo) {
                resultados = resultados.filter(
                    (p) => String(p.cargo) === String(this.selectedCargo),
                );
            }
            if (this.searchPersonal) {
                const term = this.searchPersonal.toLowerCase();
                resultados = resultados.filter(
                    (p) =>
                        (p.nombre_completo || "")
                            .toLowerCase()
                            .includes(term) || (p.dni || "").includes(term),
                );
            }

            this.personalesFiltrados = resultados;
            this.personalPage = 1;
        },

        filtrarCursos() {
            this.cursos = this.todosLosCursos.filter((c) => {
                if (this.selectedFechaDesde && c.Fecha_Creacion) {
                    const desdeTs =
                        new Date(this.selectedFechaDesde).getTime() / 1000;
                    if (c.Fecha_Creacion < desdeTs) return false;
                }
                if (this.selectedFechaHasta && c.Fecha_Creacion) {
                    const hastaTs =
                        new Date(
                            this.selectedFechaHasta + " 23:59:59",
                        ).getTime() / 1000;
                    if (c.Fecha_Creacion > hastaTs) return false;
                }
                if (this.selectedEstado) {
                    const estadoCurso = (c.Estado || "").toUpperCase();
                    if (estadoCurso !== this.selectedEstado) return false;
                }
                if (this.searchCurso) {
                    const term = this.searchCurso.toLowerCase();
                    if (!(c.Nombre || "").toLowerCase().includes(term))
                        return false;
                }
                return true;
            });
            this.cursosPage = 1;
        },

        togglePersonal(dni) {
            const idx = this.selectedUsernames.indexOf(dni);
            if (idx === -1) {
                this.selectedUsernames.push(dni);
            } else {
                this.selectedUsernames.splice(idx, 1);
                this.importados = this.importados.filter(
                    (i) => i.dni !== dni,
                );
            }
            this.recalcularSeleccionPersonal();
        },

        toggleAllPersonal() {
            if (this.selectAllPersonal) {
                this.selectedUsernames = [];
                this.importados = [];
                this.selectAllPersonal = false;
                return;
            }

            const dnis = [
                ...this.personalesFiltrados.map((p) => p.dni).filter(Boolean),
                ...this.importados.map((i) => i.dni),
            ];
            this.selectedUsernames = [...new Set(dnis)];
            this.selectAllPersonal = true;
        },

        recalcularSeleccionPersonal() {
            this.selectAllPersonal =
                this.personalesFiltrados.length > 0 &&
                this.personalesFiltrados.every((p) =>
                    this.selectedUsernames.includes(p.dni),
                );
        },

        aplicarDnisImportados(detalle) {
            if (!detalle || detalle.target !== "general") return;

            const encontrados = Array.isArray(detalle.encontrados)
                ? detalle.encontrados
                : [];

            if (encontrados.length === 0) return;

            const seleccionados = new Set(this.selectedUsernames);
            const mapa = new Map(this.importados.map((i) => [i.dni, i]));

            encontrados.forEach((persona) => {
                if (!persona?.dni) return;
                seleccionados.add(persona.dni);
                mapa.set(persona.dni, persona);
            });

            this.selectedUsernames = [...seleccionados];
            this.importados = [...mapa.values()];
            this.recalcularSeleccionPersonal();
        },

        quitarImportados() {
            if (this.importados.length === 0) return;

            const dnis = new Set(this.importados.map((i) => i.dni));
            this.selectedUsernames = this.selectedUsernames.filter(
                (dni) => !dnis.has(dni),
            );
            this.importados = [];
            this.recalcularSeleccionPersonal();
        },

        toggleCurso(id) {
            const idx = this.selectedCourseIds.indexOf(id);
            if (idx === -1) {
                this.selectedCourseIds.push(id);
            } else {
                this.selectedCourseIds.splice(idx, 1);
            }
            this.selectAllCursos =
                this.selectedCourseIds.length === this.cursos.length &&
                this.cursos.length > 0;
        },

        toggleAllCursos() {
            if (this.selectAllCursos) {
                this.selectedCourseIds = [];
                this.selectAllCursos = false;
            } else {
                this.selectedCourseIds = this.cursos
                    .map((c) => c.Id)
                    .filter(Boolean);
                this.selectAllCursos = true;
            }
        },

        exportandoExcel: false,
        exportandoPDF: false,
        buscando: false,
        view: "filters",
        resultados: [],
        selectedSucursalIdx: -1,
        selectedPersonalIdx: -1,
        cursosSortColumn: null,
        cursosSortDirection: null,
        cursosPage: 1,
        cursosPerPage: 15,

        get sucursalesEnResultados() {
            const sucs = new Set();
            this.resultados.forEach((p) => {
                if (p.Sucursal) sucs.add(p.Sucursal);
            });
            return Array.from(sucs).sort();
        },

        get personalesDeSucursalActual() {
            if (this.selectedSucursalIdx < 0) return [];
            const suc = this.sucursalesEnResultados[this.selectedSucursalIdx];
            if (!suc) return [];
            return this.resultados.filter((p) => p.Sucursal === suc);
        },

        get cursosPersonalActual() {
            if (this.selectedPersonalIdx < 0) return [];
            const personal =
                this.personalesDeSucursalActual[this.selectedPersonalIdx];
            if (!personal) return [];
            let cursos = personal.Cursos || [];
            if (this.cursosSortColumn) {
                const dir = this.cursosSortDirection === "desc" ? -1 : 1;
                cursos = [...cursos].sort((a, b) => {
                    let va = (a[this.cursosSortColumn] || "")
                        .toString()
                        .toLowerCase();
                    let vb = (b[this.cursosSortColumn] || "")
                        .toString()
                        .toLowerCase();
                    if (this.cursosSortColumn === "Nota_Final") {
                        va = parseFloat(va) || 0;
                        vb = parseFloat(vb) || 0;
                        return (va - vb) * dir;
                    }
                    return va.localeCompare(vb) * dir;
                });
            }
            return cursos;
        },

        get cursosPersonalActualPaginado() {
            const start = (this.cursosPage - 1) * this.cursosPerPage;
            return this.cursosPersonalActual.slice(
                start,
                start + this.cursosPerPage,
            );
        },

        get totalPagesCursosPersonal() {
            return Math.max(
                1,
                Math.ceil(
                    this.cursosPersonalActual.length / this.cursosPerPage,
                ),
            );
        },

        seleccionarSucursal(idx) {
            if (this.selectedSucursalIdx === idx) {
                this.selectedSucursalIdx = -1;
                this.selectedPersonalIdx = -1;
            } else {
                this.selectedSucursalIdx = idx;
                this.selectedPersonalIdx = -1;
            }
            this.cursosPage = 1;
        },

        seleccionarPersonal(idx) {
            this.selectedPersonalIdx = idx;
            this.cursosPage = 1;
        },

        ordenarCursos(col) {
            if (this.cursosSortColumn === col) {
                this.cursosSortDirection =
                    this.cursosSortDirection === "asc" ? "desc" : "asc";
            } else {
                this.cursosSortColumn = col;
                this.cursosSortDirection = "asc";
            }
        },

        volverAFiltros() {
            this.view = "filters";
            this.resultados = [];
            this.selectedSucursalIdx = -1;
            this.selectedPersonalIdx = -1;
            this.cursosPage = 1;
        },

        async _fetchReporte() {
            if (this.selectedUsernames.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un personal.",
                    "warning",
                );
                return null;
            }
            if (this.selectedCourseIds.length === 0) {
                Swal.fire(
                    "Atención",
                    "Debe seleccionar al menos un curso.",
                    "warning",
                );
                return null;
            }

            const payload = {
                usernames: this.selectedUsernames || null,
                courseIds: this.selectedCourseIds || null,
                estado: this.selectedEstado || null,
                desde: this.selectedFechaDesde || null,
                hasta: this.selectedFechaHasta || null,
                cliente: this.selectedCliente || null,
            };

            const response = await axios.post(
                `${VITE_URL_APP}/api/reportes-capacitaciones/general`,
                payload,
            );

            if (!response.data.success) {
                Swal.fire(
                    "Error",
                    response.data.message || "No se obtuvieron resultados.",
                    "warning",
                );
                return null;
            }

            const personales = response.data.Personales || [];
            if (personales.length === 0) {
                Swal.fire(
                    "Atención",
                    "No se encontraron resultados.",
                    "warning",
                );
                return null;
            }

            return personales;
        },

        async obtenerReporte() {
            this.view = "results";
            this.resultados = [];
            this.selectedSucursalIdx = -1;
            this.selectedPersonalIdx = -1;
            this.buscando = true;
            try {
                const personales = await this._fetchReporte();
                if (personales) {
                    this.resultados = personales;
                }
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo obtener el reporte.", "error");
            } finally {
                this.buscando = false;
            }
        },

        async exportarPDFReporteGeneral() {
            this.exportandoPDF = true;

            try {
                const personales =
                    this.resultados.length > 0
                        ? this.resultados
                        : await this._fetchReporte();

                if (!personales || personales.length === 0) {
                    if (!this.resultados.length)
                        Swal.fire(
                            "Atención",
                            "No se encontraron resultados para generar el PDF.",
                            "warning",
                        );
                    return;
                }

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({
                    orientation: "landscape",
                    unit: "mm",
                    format: "a4",
                });

                const logoSol = await _cargarImagen(
                    _assetUrl("/images/logo_sol.png"),
                );
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const marginL = 10;
                const marginR = 10;
                const contentWidth = pageWidth - marginL - marginR;

                const fmtFecha = (val) => {
                    if (!val) return "";
                    const m = String(val).match(/(\d{4})-(\d{2})-(\d{2})/);
                    return m ? `${m[3]}/${m[2]}/${m[1]}` : val;
                };

                const dibujarCabecera = (tituloExtra) => {
                    let logoBottomY = 18;
                    const logoWidth = 50;
                    if (logoSol) {
                        const ratio = logoSol.height / logoSol.width;
                        doc.addImage(
                            logoSol,
                            "PNG",
                            marginL,
                            8,
                            logoWidth,
                            logoWidth * ratio,
                        );
                        logoBottomY = 8 + logoWidth * ratio;
                    }
                    const lineY = logoBottomY + 2;
                    doc.setDrawColor(150, 150, 150);
                    doc.setLineWidth(0.2);
                    doc.line(marginL, lineY, marginL + 75, lineY);
                    doc.setFont("helvetica", "italic");
                    doc.setFontSize(6);
                    doc.setTextColor(80, 80, 80);
                    doc.text(
                        "Chimbote: Calle Los Laureles Nº206 Urb. La Caleta",
                        marginL,
                        lineY + 3.5,
                    );
                    doc.text("RUC: 20445414833", marginL, lineY + 6);

                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(11);
                    doc.setTextColor(0, 0, 0);
                    const titulo = "REPORTE GENERAL DE CAPACITACIONES";
                    doc.text(titulo, pageWidth / 2, lineY + 18, {
                        align: "center",
                    });
                    const tW = doc.getTextWidth(titulo);
                    doc.setLineWidth(0.3);
                    doc.setDrawColor(0, 0, 0);
                    doc.line(
                        pageWidth / 2 - tW / 2,
                        lineY + 19.5,
                        pageWidth / 2 + tW / 2,
                        lineY + 19.5,
                    );

                    let y = lineY + 24;
                    if (tituloExtra) {
                        doc.setFont("helvetica", "bold");
                        doc.setFontSize(8);
                        doc.setTextColor(60, 60, 60);
                        doc.text(tituloExtra, marginL, y + 2);
                        y += 7;
                    }
                    return y;
                };

                let yPos = dibujarCabecera();

                const sucursales = {};
                personales.forEach((p) => {
                    const key = p.Sucursal || "SIN SUCURSAL";
                    if (!sucursales[key]) sucursales[key] = [];
                    sucursales[key].push(p);
                });

                const sucKeys = Object.keys(sucursales).sort();
                let primeraSuc = true;

                for (const sucName of sucKeys) {
                    const grupo = sucursales[sucName];

                    if (!primeraSuc) {
                        if (yPos + 30 > pageHeight - 10) {
                            doc.addPage();
                            yPos = dibujarCabecera();
                        } else {
                            yPos += 4;
                        }
                    }
                    primeraSuc = false;

                    if (yPos + 8 > pageHeight - 10) {
                        doc.addPage();
                        yPos = dibujarCabecera();
                    }

                    doc.setFillColor(253, 245, 230);
                    doc.rect(marginL, yPos, contentWidth, 5.5, "F");
                    doc.setDrawColor(0, 0, 0);
                    doc.setLineWidth(0.2);
                    doc.rect(marginL, yPos, contentWidth, 5.5, "S");
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(8);
                    doc.setTextColor(0, 0, 0);
                    doc.text(
                        "SUCURSAL: " +
                            (sucName === "SIN SUCURSAL"
                                ? "SIN SUCURSAL"
                                : sucName),
                        marginL + 2,
                        yPos + 4,
                    );
                    yPos += 5.5;

                    const filas = [];
                    grupo.forEach((p) => {
                        const cursos = p.Cursos || [];
                        if (cursos.length === 0) {
                            filas.push([
                                p.CodigoPersonal || "—",
                                p.NombreCompleto || "—",
                                p.NroDoc || "—",
                                p.Cargo || "—",
                                "—",
                                "—",
                                "—",
                                "—",
                            ]);
                        } else {
                            cursos.forEach((c) => {
                                filas.push([
                                    p.CodigoPersonal || "—",
                                    p.NombreCompleto || "—",
                                    p.NroDoc || "—",
                                    p.Cargo || "—",
                                    c.Nombre || "—",
                                    c.Estado || "—",
                                    c.Nota_Final != null
                                        ? c.Fecha_Nota
                                            ? fmtFecha(c.Fecha_Nota)
                                            : ""
                                        : c.Fecha_Ultimo_Acceso
                                          ? fmtFecha(c.Fecha_Ultimo_Acceso)
                                          : "",
                                    c.Nota_Final != null
                                        ? String(c.Nota_Final)
                                        : "—",
                                ]);
                            });
                        }
                    });

                    if (filas.length > 0) {
                        doc.autoTable({
                            startY: yPos,
                            head: [
                                [
                                    "Código Pers.",
                                    "Apellidos y Nombres",
                                    "Nro. Doc",
                                    "Cargo",
                                    "Capacitación",
                                    "Estado",
                                    "Fecha / Últ. Acceso",
                                    "Nota",
                                ],
                            ],
                            body: filas,
                            theme: "grid",
                            styles: {
                                fontSize: 6.5,
                                textColor: [0, 0, 0],
                                lineColor: [0, 0, 0],
                                lineWidth: 0.1,
                                valign: "middle",
                                cellPadding: 1.2,
                            },
                            headStyles: {
                                fillColor: [253, 245, 230],
                                textColor: [0, 0, 0],
                                fontStyle: "bold",
                                halign: "center",
                                fontSize: 6.5,
                            },
                            columnStyles: {
                                0: { cellWidth: 16, halign: "center" },
                                1: { cellWidth: 48 },
                                2: { cellWidth: 18, halign: "center" },
                                3: { cellWidth: 28 },
                                4: { cellWidth: "auto" },
                                5: { cellWidth: 18, halign: "center" },
                                6: { cellWidth: 22, halign: "center" },
                                7: { cellWidth: 12, halign: "center" },
                            },
                            margin: { left: marginL, right: marginR },
                        });
                        yPos = doc.lastAutoTable.finalY + 2;
                    }
                }

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;
                const nombreArchivo = `REPORTE_GENERAL_CAPACITACIONES_${fechaRep}.pdf`;
                const pdfBlob = doc.output("blob");

                try {
                    const formData = new FormData();
                    formData.append("nombre_archivo", nombreArchivo);
                    formData.append("archivo_pdf", pdfBlob, nombreArchivo);
                    // Registro en historial deshabilitado temporalmente.
                    // await axios.post(
                    //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                    //     formData,
                    // );
                    // window.dispatchEvent(
                    //     new CustomEvent("historial-reportes-actualizado"),
                    // );
                } catch (error) {
                    console.error("Error al guardar en historial:", error);
                }

                saveAs(pdfBlob, nombreArchivo);
                Swal.fire("Éxito", "PDF generado correctamente.", "success");
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo generar el PDF.", "error");
            } finally {
                this.exportandoPDF = false;
            }
        },

        async exportarExcelReporteGeneral() {
            this.exportandoExcel = true;

            try {
                const personales =
                    this.resultados.length > 0
                        ? this.resultados
                        : await this._fetchReporte();

                if (!personales || personales.length === 0) {
                    if (!this.resultados.length)
                        Swal.fire(
                            "Atención",
                            "No se encontraron resultados para exportar.",
                            "warning",
                        );
                    return;
                }

                const workbook = new ExcelJS.Workbook();
                const logoImageId = await _cargarLogoExcel(workbook);
                const sheet = workbook.addWorksheet("Reporte General");

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;

                const headerRowNumber = 5;

                if (logoImageId !== null) {
                    sheet.addImage(logoImageId, {
                        tl: { col: 1, row: 0 },
                        br: { col: 3, row: 2 },
                        editAs: "absolute",
                    });
                }

                sheet.getRow(1).height = 50;

                sheet.mergeCells("D1:J1");
                const infoCell = sheet.getCell("D1");
                infoCell.value = "REPORTE GENERAL DE CAPACITACIONES";
                infoCell.font = {
                    bold: true,
                    size: 13,
                    color: { argb: "FF1F4E79" },
                };
                infoCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.mergeCells("D2:J2");
                const totalCell = sheet.getCell("D2");
                totalCell.value = `Total: ${personales.length} personal(es)`;
                totalCell.font = { size: 11, color: { argb: "FF333333" } };
                totalCell.alignment = {
                    vertical: "middle",
                    horizontal: "right",
                };

                sheet.getRow(4).height = 8;

                const headers = [
                    "#",
                    "Sucursal",
                    "Código Pers.",
                    "Apellidos y Nombres",
                    "Nro. Doc",
                    "Cargo",
                    "Capacitación",
                    "Estado",
                    "Fecha / Últ. Acceso",
                    "Nota",
                ];

                const headerRow = sheet.getRow(headerRowNumber);
                headerRow.values = headers;
                headerRow.eachCell((cell) => _estiloEncabezadoExcel(cell));

                let rowIdx = 1;
                personales.forEach((personal) => {
                    const cursos = personal.Cursos || [];
                    if (cursos.length === 0) {
                        const row = sheet.addRow([
                            rowIdx++,
                            personal.Sucursal || "—",
                            personal.CodigoPersonal || "—",
                            personal.NombreCompleto || "—",
                            personal.NroDoc || "—",
                            personal.Cargo || "—",
                            "—",
                            "—",
                            "—",
                            "—",
                        ]);
                        row.eachCell((cell) => _estiloDatoExcel(cell));
                    } else {
                        cursos.forEach((curso) => {
                            const fechaVal =
                                curso.Nota_Final != null
                                    ? curso.Fecha_Nota || ""
                                    : curso.Fecha_Ultimo_Acceso || "";
                            const row = sheet.addRow([
                                rowIdx++,
                                personal.Sucursal || "—",
                                personal.CodigoPersonal || "—",
                                personal.NombreCompleto || "—",
                                personal.NroDoc || "—",
                                personal.Cargo || "—",
                                curso.Nombre || "—",
                                curso.Estado || "—",
                                fechaVal,
                                curso.Nota_Final != null
                                    ? String(curso.Nota_Final)
                                    : "—",
                            ]);
                            row.eachCell((cell) => _estiloDatoExcel(cell));
                        });
                    }
                });

                sheet.columns = [
                    { width: 5 },
                    { width: 18 },
                    { width: 14 },
                    { width: 38 },
                    { width: 16 },
                    { width: 24 },
                    { width: 38 },
                    { width: 14 },
                    { width: 18 },
                    { width: 12 },
                ];

                sheet.autoFilter = {
                    from: `A${headerRowNumber}`,
                    to: `J${headerRowNumber}`,
                };

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = _blobExcel(buffer);
                const nombreArchivo = `REPORTE_GENERAL_CAPACITACIONES_${fechaRep}.xlsx`;

                try {
                    const formData = new FormData();
                    formData.append("nombre_archivo", nombreArchivo);
                    formData.append("archivo_excel", blob, nombreArchivo);
                    // Registro en historial deshabilitado temporalmente.
                    // await axios.post(
                    //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                    //     formData,
                    // );
                    // window.dispatchEvent(
                    //     new CustomEvent("historial-reportes-actualizado"),
                    // );
                } catch (error) {
                    console.error("Error al guardar en historial:", error);
                }

                saveAs(blob, nombreArchivo);
                Swal.fire("Éxito", "Excel generado correctamente.", "success");
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo generar el Excel.", "error");
            } finally {
                this.exportandoExcel = false;
            }
        },

        cerrar() {
            this.open = false;
            this.view = "filters";
            this.resultados = [];
            this.selectedSucursalIdx = -1;
            this.selectedPersonalIdx = -1;
            this.cursosSortColumn = null;
            this.cursosSortDirection = null;
            this.cursosPage = 1;
            this.selectedCliente = "";
            this.selectedEmpresa = "";
            this.selectedSucursal = "";
            this.selectedTipoTrabajo = "";
            this.selectedCargo = "";
            this.searchPersonal = "";
            this.selectedFechaDesde = "";
            this.selectedFechaHasta = "";
            this.selectedEstado = "";
            this.searchCurso = "";
            this.selectedUsernames = [];
            this.selectedCourseIds = [];
            this.selectAllPersonal = false;
            this.selectAllCursos = false;
            this.importados = [];
        },
    }));

    Alpine.data("modalReporteFormato", () => ({
        open: false,

        loadingInicial: true,
        loadingCursos: false,
        loadingPersonal: false,

        view: "filtros",
        generando: false,
        exportandoPDF: false,
        reporteData: null,

        fechaCreacionDesde: "",
        fechaCreacionHasta: "",
        selectedAnio: "",
        searchCurso: "",

        selectedSucursal: "",
        selectedVigencia: "1",
        selectedCargo: "",
        selectedTipoTrabajador: "",
        selectedCliente: "",
        searchPersonal: "",

        todosLosCursos: [],
        cursos: [],
        aniosDisponibles: [],
        todosLosPersonales: [],
        personales: [],
        sucursales: [],

        selectedCursoId: null,
        selectedPersonalDnis: [],
        importados: [],
        selectAllPersonal: false,

        cursosPerPage: 15,
        personalPerPage: 20,
        cursosPage: 1,
        personalPage: 1,

        async init() {
            window.addEventListener("abrir-reporte-formato", () => {
                this.abrir();
            });
        },

        async abrir() {
            this.open = true;
            this.loadingInicial = true;

            const catalogs = await ensureCatalogsLoaded();
            this.sucursales = catalogs.sucursales;
            this.todosLosCursos = [...(catalogs.cursos || [])];
            this.aniosDisponibles = [
                ...new Set(
                    this.todosLosCursos
                        .map((c) => c.Anio_Curso)
                        .filter(Boolean),
                ),
            ].sort((a, b) => b - a);
            this.selectedAnio = this.aniosDisponibles[0] || "";
            this.filtrarCursos();

            await this.cargarPersonal();

            this.loadingInicial = false;
        },

        async cargarPersonal() {
            this.loadingPersonal = true;
            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/obtener-personal`,
                    {
                        params: { vigente: 2 },
                    },
                );
                this.todosLosPersonales =
                    data.success && Array.isArray(data.personal)
                        ? data.personal
                        : [];
                this.filtrarPersonales();
            } catch (e) {
                console.error(e);
                this.todosLosPersonales = [];
                this.personales = [];
            } finally {
                this.loadingPersonal = false;
            }
        },

        formatearFecha(timestamp) {
            if (!timestamp || timestamp <= 0) return "";
            let fecha;
            if (
                typeof timestamp === "number" ||
                /^\d+$/.test(String(timestamp))
            ) {
                fecha = new Date(Number(timestamp) * 1000);
            } else {
                fecha = new Date(
                    String(timestamp).includes("T")
                        ? timestamp
                        : String(timestamp).replace(" ", "T"),
                );
            }
            if (isNaN(fecha.getTime())) return "";
            const dia = String(fecha.getDate()).padStart(2, "0");
            const mes = String(fecha.getMonth() + 1).padStart(2, "0");
            return `${dia}/${mes}/${fecha.getFullYear()}`;
        },

        filtrarCursos() {
            this.cursos = this.todosLosCursos.filter((c) => {
                if (this.fechaCreacionDesde && c.Fecha_Creacion) {
                    const desdeTs =
                        new Date(this.fechaCreacionDesde).getTime() / 1000;
                    if (c.Fecha_Creacion < desdeTs) return false;
                }
                if (this.fechaCreacionHasta && c.Fecha_Creacion) {
                    const hastaTs =
                        new Date(
                            this.fechaCreacionHasta + " 23:59:59",
                        ).getTime() / 1000;
                    if (c.Fecha_Creacion > hastaTs) return false;
                }
                if (
                    this.selectedAnio &&
                    c.Anio_Curso &&
                    Number(c.Anio_Curso) !== Number(this.selectedAnio)
                ) {
                    return false;
                }
                if (this.searchCurso) {
                    const term = this.searchCurso.toLowerCase();
                    if (!(c.Nombre || "").toLowerCase().includes(term))
                        return false;
                }
                return true;
            });
            this.cursosPage = 1;
        },

        filtrarPersonales() {
            let resultados = [...this.todosLosPersonales];

            if (this.selectedSucursal) {
                const sucursalSel = this.sucursales.find(
                    (s) => String(s.codigo) === String(this.selectedSucursal),
                );
                if (sucursalSel) {
                    resultados = resultados.filter(
                        (p) =>
                            String(p.sucursal) === String(sucursalSel.sucursal),
                    );
                } else {
                    resultados = [];
                }
            }

            if (this.selectedVigencia === "1") {
                resultados = resultados.filter((p) => p.vigente === true);
            } else if (this.selectedVigencia === "0") {
                resultados = resultados.filter((p) => p.vigente === false);
            }

            if (this.selectedCargo) {
                resultados = resultados.filter(
                    (p) =>
                        String((p.cargo || "").trim()) ===
                        String(this.selectedCargo),
                );
            }
            if (this.selectedTipoTrabajador) {
                resultados = resultados.filter(
                    (p) =>
                        String((p.tipo_trabajador || "").trim()) ===
                        String(this.selectedTipoTrabajador),
                );
            }
            if (this.selectedCliente) {
                resultados = resultados.filter(
                    (p) =>
                        String((p.cliente || "").trim()) ===
                        String(this.selectedCliente),
                );
            }

            if (this.searchPersonal) {
                const term = this.searchPersonal.toLowerCase();
                resultados = resultados.filter(
                    (p) =>
                        (p.nombre_completo || "")
                            .toLowerCase()
                            .includes(term) || (p.dni || "").includes(term),
                );
            }

            this.personales = resultados;
            this.personalPage = 1;
            this.recalcularSeleccionPersonal();
        },

        get cargosDisponibles() {
            return [
                ...new Set(
                    this.todosLosPersonales
                        .map((p) => (p.cargo || "").trim())
                        .filter(Boolean),
                ),
            ].sort((a, b) => a.localeCompare(b));
        },
        get tiposTrabajadorDisponibles() {
            return [
                ...new Set(
                    this.todosLosPersonales
                        .map((p) => (p.tipo_trabajador || "").trim())
                        .filter(Boolean),
                ),
            ].sort((a, b) => a.localeCompare(b));
        },
        get clientesDisponibles() {
            return [
                ...new Set(
                    this.todosLosPersonales
                        .map((p) => (p.cliente || "").trim())
                        .filter(Boolean),
                ),
            ].sort((a, b) => a.localeCompare(b));
        },

        get cursosTotalPages() {
            return Math.max(
                1,
                Math.ceil(this.cursos.length / this.cursosPerPage),
            );
        },
        get personalTotalPages() {
            return Math.max(
                1,
                Math.ceil(this.personales.length / this.personalPerPage),
            );
        },

        cursosPaginados() {
            const start = (this.cursosPage - 1) * this.cursosPerPage;
            return this.cursos.slice(start, start + this.cursosPerPage);
        },
        personalesPaginados() {
            const start = (this.personalPage - 1) * this.personalPerPage;
            return this.personales.slice(start, start + this.personalPerPage);
        },

        seleccionarCurso(id) {
            this.selectedCursoId = this.selectedCursoId === id ? null : id;
        },
        togglePersonal(dni) {
            const idx = this.selectedPersonalDnis.indexOf(dni);
            if (idx === -1) {
                this.selectedPersonalDnis.push(dni);
            } else {
                this.selectedPersonalDnis.splice(idx, 1);
                this.importados = this.importados.filter(
                    (i) => i.dni !== dni,
                );
            }
            this.recalcularSeleccionPersonal();
        },
        toggleAllPersonal() {
            if (this.selectAllPersonal) {
                this.selectedPersonalDnis = [];
                this.importados = [];
                this.selectAllPersonal = false;
                return;
            }

            const dnis = [
                ...this.personales.map((p) => p.dni).filter(Boolean),
                ...this.importados.map((i) => i.dni),
            ];
            this.selectedPersonalDnis = [...new Set(dnis)];
            this.selectAllPersonal = true;
        },

        recalcularSeleccionPersonal() {
            this.selectAllPersonal =
                this.personales.length > 0 &&
                this.personales.every((p) =>
                    this.selectedPersonalDnis.includes(p.dni),
                );
        },

        aplicarDnisImportados(detalle) {
            if (!detalle || detalle.target !== "formato") return;

            const encontrados = Array.isArray(detalle.encontrados)
                ? detalle.encontrados
                : [];

            if (encontrados.length === 0) return;

            const seleccionados = new Set(this.selectedPersonalDnis);
            const mapa = new Map(this.importados.map((i) => [i.dni, i]));

            encontrados.forEach((persona) => {
                if (!persona?.dni) return;
                seleccionados.add(persona.dni);
                mapa.set(persona.dni, persona);
            });

            this.selectedPersonalDnis = [...seleccionados];
            this.importados = [...mapa.values()];
            this.recalcularSeleccionPersonal();
        },

        quitarImportados() {
            if (this.importados.length === 0) return;

            const dnis = new Set(this.importados.map((i) => i.dni));
            this.selectedPersonalDnis = this.selectedPersonalDnis.filter(
                (dni) => !dnis.has(dni),
            );
            this.importados = [];
            this.recalcularSeleccionPersonal();
        },

        async generarReporte() {
            if (
                this.selectedCursoId === null ||
                this.selectedCursoId === undefined
            ) {
                Swal.fire(
                    "Atención",
                    "Seleccione una capacitación.",
                    "warning",
                );
                return;
            }
            if (this.selectedPersonalDnis.length === 0) {
                Swal.fire(
                    "Atención",
                    "Seleccione al menos un personal.",
                    "warning",
                );
                return;
            }

            this.generando = true;
            try {
                const { data } = await axios.post(
                    `${VITE_URL_APP}/api/reportes-capacitaciones/formato`,
                    {
                        courseId: this.selectedCursoId,
                        persDnis: this.selectedPersonalDnis,
                    },
                );

                if (!data.success) {
                    Swal.fire(
                        "Error",
                        data.message || "No se pudo generar el reporte.",
                        "error",
                    );
                    return;
                }

                const personal = Array.isArray(data.Personal)
                    ? data.Personal
                    : [];
                if (personal.length === 0) {
                    Swal.fire(
                        "Atención",
                        "El personal seleccionado no participó o no culminó la capacitación.",
                        "warning",
                    );
                    return;
                }

                this.reporteData = data;
                this.view = "resultado";
            } catch (e) {
                console.error(e);
                Swal.fire(
                    "Error",
                    e.response?.data?.message ||
                        "No se pudo generar el reporte. Intente nuevamente.",
                    "error",
                );
            } finally {
                this.generando = false;
            }
        },

        volverAFiltros() {
            this.view = "filtros";
            this.reporteData = null;
        },

        async generarPDF() {
            if (!this.reporteData) {
                Swal.fire(
                    "Atención",
                    "No hay datos para generar el PDF.",
                    "warning",
                );
                return;
            }

            if (this.exportandoPDF) return;

            this.exportandoPDF = true;

            try {
                const { jsPDF } = window.jspdf;

                const data = this.reporteData;
                const logoSol = await _cargarImagen(
                    _assetUrl("/images/logo_sol.png"),
                );

                // Proxy server-side: convierte la URL cross-origin de la firma
                // en una URL del mismo origen para poder leer sus bytes en el JS.
                const proxyFirma = (u) =>
                    u &&
                    u.startsWith("http://190.116.178.163/Biblioteca_Grafica/")
                        ? `${VITE_URL_APP}/api/dj/reporte/proxy-imagen?url=${encodeURIComponent(u)}`
                        : u;

                const firmaResponsable = await _cargarFirma(
                    proxyFirma(data.Firma_Responsable || ""),
                );
                console.log("firmaResponsable:", firmaResponsable);
                const personalFirmas = {};
                await Promise.all(
                    (data.Personal || []).map(async (p) => {
                        if (p.Firma_Personal) {
                            personalFirmas[p.DNI_Personal] =
                                await _cargarFirma(proxyFirma(p.Firma_Personal));
                        }
                    }),
                );

                const margenIzq = 14.1;

                const headerStyle = {
                    fillColor: [167, 203, 240],
                    textColor: [0, 0, 0],
                    fontStyle: "bold",
                    halign: "center",
                };

                function dibujarCelda(doc, x, y, w, h, lineas, opts = {}) {
                    const fontSize = opts.fontSize || 8;
                    const align = opts.align || "left";
                    const ptToMm = 0.3528;
                    const lineHeight = fontSize * ptToMm * 1.15;

                    doc.setDrawColor(0, 0, 0);
                    doc.setLineWidth(0.2);
                    doc.rect(x, y, w, h);

                    doc.setFontSize(fontSize);
                    const totalTextHeight = lineas.length * lineHeight;
                    let ty = y + (h - totalTextHeight) / 2 + lineHeight * 0.8;

                    lineas.forEach((segments) => {
                        let lineWidth = 0;
                        segments.forEach((seg) => {
                            doc.setFont(
                                undefined,
                                seg.bold ? "bold" : "normal",
                            );
                            lineWidth += doc.getTextWidth(seg.text);
                        });

                        let tx =
                            align === "center"
                                ? x + (w - lineWidth) / 2
                                : x + 2;

                        segments.forEach((seg) => {
                            doc.setFont(
                                undefined,
                                seg.bold ? "bold" : "normal",
                            );
                            doc.text(seg.text, tx, ty);
                            tx += doc.getTextWidth(seg.text);
                        });

                        ty += lineHeight;
                    });
                    doc.setFont(undefined, "normal");
                }

                function dibujarEncabezadoPagina(
                    doc,
                    paginaActual,
                    totalPaginas,
                ) {
                    const x0 = margenIzq;
                    const y0 = 10;
                    const widths = [30, 85, 45, 25];
                    const heights = [13, 12, 10];
                    const alturaTotal = heights[0] + heights[1] + heights[2];
                    const colX = [
                        x0,
                        x0 + widths[0],
                        x0 + widths[0] + widths[1],
                        x0 + widths[0] + widths[1] + widths[2],
                    ];

                    doc.setDrawColor(0, 0, 0);
                    doc.setLineWidth(0.2);

                    if (logoSol) {
                        doc.addImage(
                            logoSol,
                            "PNG",
                            x0 + 2,
                            y0 + (alturaTotal - 15) / 2,
                            26,
                            15,
                        );
                    }
                    doc.rect(x0, y0, widths[0], alturaTotal);

                    dibujarCelda(
                        doc,
                        colX[1],
                        y0,
                        widths[1],
                        heights[0],
                        [[{ text: "SOL SECURITY S.A.C.", bold: true }]],
                        { align: "center" },
                    );
                    dibujarCelda(
                        doc,
                        colX[2],
                        y0,
                        widths[2],
                        heights[0],
                        [
                            [{ text: "CODIGO", bold: true }],
                            [{ text: "FAS-05-007", bold: false }],
                        ],
                        { align: "center" },
                    );
                    dibujarCelda(
                        doc,
                        colX[3],
                        y0,
                        widths[3],
                        heights[0],
                        [[{ text: "SEXTA EDICIÓN", bold: true }]],
                        { align: "center" },
                    );

                    dibujarCelda(
                        doc,
                        colX[1],
                        y0 + heights[0],
                        widths[1],
                        heights[1],
                        [
                            [
                                { text: "Formato: ", bold: true },
                                {
                                    text: "Registro de inducción, charla, capacitación,",
                                    bold: false,
                                },
                            ],
                            [
                                {
                                    text: "entrenamiento y simulacros de emergencia",
                                    bold: false,
                                },
                            ],
                        ],
                        { align: "left" },
                    );
                    dibujarCelda(
                        doc,
                        colX[2],
                        y0 + heights[0],
                        widths[2],
                        heights[1],
                        [
                            [{ text: "FECHA DE APROBACIÓN:", bold: true }],
                            [{ text: "10 Febrero 2026", bold: false }],
                        ],
                        { align: "center" },
                    );
                    dibujarCelda(
                        doc,
                        colX[3],
                        y0 + heights[0],
                        widths[3],
                        heights[1],
                        [
                            [
                                {
                                    text: `Página ${paginaActual} de ${totalPaginas}`,
                                    bold: false,
                                },
                            ],
                        ],
                        { align: "center" },
                    );

                    dibujarCelda(
                        doc,
                        colX[1],
                        y0 + heights[0] + heights[1],
                        widths[1],
                        heights[2],
                        [
                            [
                                { text: "Área de Influencia: ", bold: true },
                                { text: "Todas las áreas", bold: false },
                            ],
                        ],
                        { align: "left" },
                    );
                    dibujarCelda(
                        doc,
                        colX[2],
                        y0 + heights[0] + heights[1],
                        widths[2],
                        heights[2],
                        [
                            [{ text: "FECHA DE REVISIÓN:", bold: true }],
                            [{ text: "-", bold: false }],
                        ],
                        { align: "center" },
                    );
                    dibujarCelda(
                        doc,
                        colX[3],
                        y0 + heights[0] + heights[1],
                        widths[3],
                        heights[2],
                        [[{ text: "", bold: false }]],
                        { align: "center" },
                    );
                }

                function dibujarFirmaEnCelda(doc, firma, cell, opts = {}) {
                    if (
                        !firma ||
                        !firma.src ||
                        !isFinite(firma.width) ||
                        !isFinite(firma.height) ||
                        firma.width <= 0 ||
                        firma.height <= 0 ||
                        !isFinite(cell.x) ||
                        !isFinite(cell.y) ||
                        !isFinite(cell.width) ||
                        !isFinite(cell.height) ||
                        cell.width <= 0 ||
                        cell.height <= 0
                    ) {
                        return;
                    }
                    const pad = opts.pad ?? 1;
                    const align = opts.align || "center";
                    const maxW = cell.width - pad * 2;
                    const maxH = cell.height - pad * 2;
                    let ratio = firma.width / firma.height;
                    let w;
                    let h;
                    if (opts.height != null) {
                        h = Math.min(opts.height, maxH);
                        w = h * ratio;
                        if (w > maxW) {
                            w = maxW;
                            h = w / ratio;
                        }
                    } else {
                        w = maxW;
                        h = w / ratio;
                        if (h > maxH) {
                            h = maxH;
                            w = h * ratio;
                        }
                        if (w > maxW) {
                            w = maxW;
                            h = w / ratio;
                        }
                        if (h < 3) {
                            h = Math.min(3, maxH);
                            w = h * ratio;
                            if (w > maxW) {
                                w = maxW;
                                h = w / ratio;
                            }
                        }
                    }
                    const x =
                        align === "left"
                            ? cell.x + pad
                            : cell.x + (cell.width - w) / 2;
                    const y = cell.y + (cell.height - h) / 2;
                    doc.addImage(
                        firma.src,
                        firma.format || "PNG",
                        x,
                        y,
                        w,
                        h,
                    );
                }

                const renderContenido = (doc, totalPaginas) => {
                    let ultimaPaginaEncabezado = 0;

                    const onDidDrawPage = (tableData) => {
                        if (!totalPaginas) return;
                        if (tableData.pageNumber === ultimaPaginaEncabezado)
                            return;
                        ultimaPaginaEncabezado = tableData.pageNumber;
                        dibujarEncabezadoPagina(
                            doc,
                            tableData.pageNumber,
                            totalPaginas,
                        );
                    };

                    const margenContenido = {
                        top: 49,
                        left: margenIzq,
                        right: margenIzq,
                        bottom: margenIzq,
                    };

                    const normalizarTipo = (s) =>
                        (s || "")
                            .toUpperCase()
                            .normalize("NFD")
                            .replace(/[\u0300-\u036f]/g, "")
                            .replace(/\s+/g, " ")
                            .trim();

                    const tipoCursoSeleccionado = normalizarTipo(
                        data.Tipo_Curso,
                    );
                    const marcarTipo = (label) =>
                        normalizarTipo(label) === tipoCursoSeleccionado
                            ? `${label} (X)`
                            : `${label} (  )`;

                    // Datos del empleador
                    doc.autoTable({
                        startY: 49,
                        margin: margenContenido,
                        theme: "grid",
                        didDrawPage: onDidDrawPage,
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            textColor: [0, 0, 0],
                            valign: "middle",
                            lineColor: [0, 0, 0],
                            lineWidth: 0.2,
                        },
                        head: [
                            [
                                {
                                    content: "DATOS DEL EMPLEADOR",
                                    colSpan: 5,
                                    styles: headerStyle,
                                },
                            ],
                            [
                                {
                                    content: "RAZÓN SOCIAL",
                                    styles: headerStyle,
                                },
                                {
                                    content: "RUC",
                                    styles: headerStyle,
                                },
                                {
                                    content:
                                        "DOMICILIO (Dirección, distrito,\ndepartamento, provincia)",
                                    styles: headerStyle,
                                },
                                {
                                    content: "ACTIVIDAD ECONÓMICA",
                                    styles: headerStyle,
                                },
                                {
                                    content:
                                        "Nº DE TRABAJADORES EN EL\nCENTRO LABORAL",
                                    styles: headerStyle,
                                },
                            ],
                        ],
                        body: [
                            [
                                {
                                    content: "SOL SECURITY S.A.C.",
                                    styles: { halign: "center" },
                                },
                                {
                                    content: "20445414833",
                                    styles: { halign: "center" },
                                },
                                {
                                    content:
                                        "Calle José Gálvez 334, Callao -\nProv. Const. Del Callao",
                                    styles: { halign: "center" },
                                },
                                {
                                    content: "SEGURIDAD PRIVADA\nY ELECTRÓNICA",
                                    styles: { halign: "center" },
                                },
                                {
                                    content: data.Total_Aprobados || "0",
                                    styles: { halign: "center" },
                                },
                            ],
                        ],
                        columnStyles: {
                            0: { cellWidth: 33 },
                            1: { cellWidth: 27 },
                            2: { cellWidth: 55 },
                            3: { cellWidth: 35 },
                            4: { cellWidth: 35 },
                        },
                    });

                    // Marcar (X)
                    doc.autoTable({
                        startY: doc.lastAutoTable.finalY,
                        margin: margenContenido,
                        theme: "grid",
                        didDrawPage: onDidDrawPage,
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            valign: "middle",
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.2,
                        },
                        body: [
                            [
                                {
                                    content: "MARCAR (X)",
                                    colSpan: 5,
                                    styles: headerStyle,
                                },
                            ],
                            [
                                marcarTipo("INDUCCIÓN"),
                                marcarTipo("CHARLA"),
                                marcarTipo("CAPACITACIÓN"),
                                marcarTipo("ENTRENAMIENTO"),
                                marcarTipo("SIMULACROS DE EMERGENCIA"),
                            ],
                        ],
                        columnStyles: {
                            0: { cellWidth: 33 },
                            1: { cellWidth: 27 },
                            2: { cellWidth: 30 },
                            3: { cellWidth: 35 },
                            4: { cellWidth: 60 },
                        },
                    });

                    // Datos de capacitación y capacitador
                    doc.autoTable({
                        startY: doc.lastAutoTable.finalY,
                        margin: margenContenido,
                        theme: "grid",
                        didDrawPage: onDidDrawPage,
                        didDrawCell: (cellData) => {
                            if (
                                cellData.section === "body" &&
                                cellData.row.index === 2 &&
                                cellData.column.index === 3
                            ) {
                                dibujarFirmaEnCelda(
                                    doc,
                                    firmaResponsable,
                                    cellData.cell,
                                );
                            }
                        },
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            valign: "middle",
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.2,
                        },
                        body: [
                            [
                                {
                                    content: "TEMA:",
                                    styles: {
                                        ...headerStyle,
                                        halign: "left",
                                        cellWidth: 33,
                                    },
                                },
                                {
                                    content: data.Nombre_Curso || "",
                                    colSpan: 3,
                                },
                            ],
                            [
                                {
                                    content: "FECHA:",
                                    styles: { ...headerStyle, halign: "left" },
                                },
                                {
                                    content: data.Fecha_Reporte || "",
                                    colSpan: 3,
                                },
                            ],
                            [
                                {
                                    content: "CAPACITADOR:",
                                    styles: { ...headerStyle, halign: "left" },
                                },
                                data.Nombre_Responsable || "",
                                { content: "FIRMA", styles: headerStyle },
                                {
                                    content: "",
                                    styles: { minCellHeight: 16 },
                                },
                            ],
                            [
                                {
                                    content: "Nº DE HORAS:",
                                    styles: { ...headerStyle, halign: "left" },
                                },
                                { content: "01", colSpan: 3 },
                            ],
                        ],
                        columnStyles: {
                            0: { cellWidth: 33 },
                            1: { cellWidth: 92 },
                            2: { cellWidth: 25 },
                            3: { cellWidth: 35 },
                        },
                    });

                    // Listado de personal capacitado
                    doc.autoTable({
                        startY: doc.lastAutoTable.finalY,
                        margin: margenContenido,
                        theme: "grid",
                        didDrawPage: onDidDrawPage,
                        didDrawCell: (cellData) => {
                            if (
                                cellData.section === "body" &&
                                cellData.column.index === 4
                            ) {
                                const persona = (data.Personal || [])[
                                    cellData.row.index
                                ];
                                dibujarFirmaEnCelda(
                                    doc,
                                    persona
                                        ? personalFirmas[persona.DNI_Personal]
                                        : null,
                                    cellData.cell,
                                );
                            }
                        },
                        styles: {
                            fontSize: 8,
                            cellPadding: 1.5,
                            valign: "middle",
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.2,
                        },
                        head: [
                            [
                                { content: "Nº", styles: headerStyle },
                                {
                                    content: "APELLIDOS Y NOMBRES",
                                    styles: headerStyle,
                                },
                                { content: "DNI", styles: headerStyle },
                                { content: "CARGO", styles: headerStyle },
                                { content: "FIRMA", styles: headerStyle },
                            ],
                        ],
                        body: (data.Personal || []).map((persona, index) => [
                            String(index + 1),
                            persona.Nombre_Personal || "",
                            persona.DNI_Personal || "",
                            persona.Cargo_Personal || "",
                            { content: "", styles: { minCellHeight: 10 } },
                        ]),
                        columnStyles: {
                            0: { cellWidth: 10, halign: "center" },
                            1: { cellWidth: 75 },
                            2: { cellWidth: 25, halign: "center" },
                            3: { cellWidth: 45 },
                            4: { cellWidth: 30 },
                        },
                    });

                    // Responsable del registro
                    doc.autoTable({
                        startY: doc.lastAutoTable.finalY + 10,
                        margin: margenContenido,
                        theme: "grid",
                        didDrawPage: onDidDrawPage,
                        didDrawCell: (cellData) => {
                            if (
                                cellData.section === "body" &&
                                cellData.row.index === 4 &&
                                cellData.column.index === 1
                            ) {
                                dibujarFirmaEnCelda(doc, firmaResponsable, cellData.cell, {
                                    pad: 0.5,
                                    align: "left",
                                    height: 13,
                                });
                            }
                        },
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            valign: "middle",
                            textColor: [0, 0, 0],
                            lineColor: [0, 0, 0],
                            lineWidth: 0.2,
                        },
                        body: [
                            [
                                {
                                    content: "RESPONSABLE DEL REGISTRO",
                                    colSpan: 2,
                                    styles: headerStyle,
                                },
                            ],
                            [
                                {
                                    content: "Nombre:",
                                    styles: {
                                        fontStyle: "bold",
                                        halign: "left",
                                        cellWidth: 33,
                                    },
                                },
                                {
                                    content: data.Nombre_Responsable || "",
                                },
                            ],
                            [
                                {
                                    content: "Cargo:",
                                    styles: {
                                        fontStyle: "bold",
                                        halign: "left",
                                    },
                                },
                                {
                                    content: data.Cargo_Responsable || "",
                                },
                            ],
                            [
                                {
                                    content: "Fecha:",
                                    styles: {
                                        fontStyle: "bold",
                                        halign: "left",
                                    },
                                },
                                {
                                    content: data.Fecha_Reporte || "",
                                },
                            ],
                            [
                                {
                                    content: "Firma:",
                                    styles: {
                                        fontStyle: "bold",
                                        halign: "left",
                                        minCellHeight: 17,
                                    },
                                },
                                {
                                    content: "",
                                    styles: { minCellHeight: 17 },
                                },
                            ],
                        ],
                        columnStyles: {
                            0: { cellWidth: 33 },
                            1: { cellWidth: 152 },
                        },
                    });
                };

                const docConteo = new jsPDF({
                    orientation: "portrait",
                    unit: "mm",
                    format: "a4",
                });
                renderContenido(docConteo, null);
                const totalPaginas = docConteo.internal.getNumberOfPages();

                const doc = new jsPDF({
                    orientation: "portrait",
                    unit: "mm",
                    format: "a4",
                });
                renderContenido(doc, totalPaginas);

                const d = new Date();
                const fechaRep = `${d.getFullYear()}_${String(d.getMonth() + 1).padStart(2, "0")}_${String(d.getDate()).padStart(2, "0")}_${String(d.getHours()).padStart(2, "0")}_${String(d.getMinutes()).padStart(2, "0")}`;

                const nombreArchivo = `REPORTE_FORMATO_${fechaRep}.pdf`;

                const pdfBlob = doc.output("blob");

                await this.registrarReporteEnHistorial(
                    nombreArchivo,
                    pdfBlob,
                    null,
                );

                saveAs(pdfBlob, nombreArchivo);

                Swal.fire("Éxito", "PDF generado correctamente.", "success");
            } catch (error) {
                console.error(error);
                Swal.fire("Error", "No se pudo generar el PDF.", "error");
            } finally {
                this.exportandoPDF = false;
            }
        },

        async registrarReporteEnHistorial(nombreArchivo, pdfBlob, excelBlob) {
            try {
                const formData = new FormData();
                formData.append("nombre_archivo", nombreArchivo);
                formData.append("descripcion", "");

                if (pdfBlob) {
                    formData.append(
                        "archivo_pdf",
                        pdfBlob,
                        nombreArchivo.replace(/\.pdf$/i, "") + ".pdf",
                    );
                    formData.append("tipo_archivo", "pdf");
                }

                if (excelBlob) {
                    formData.append(
                        "archivo_excel",
                        excelBlob,
                        nombreArchivo.replace(/\.xlsx$/i, "") + ".xlsx",
                    );
                    formData.append("tipo_archivo", "xlsx");
                }

                // Registro en historial deshabilitado temporalmente.
                // await axios.post(
                //     `${VITE_URL_APP}/api/capacitacion/registrar-reporte`,
                //     formData,
                // );

                // window.dispatchEvent(
                //     new CustomEvent("historial-reportes-actualizado"),
                // );
            } catch (error) {
                console.error(
                    "Error al registrar reporte en historial:",
                    error,
                );
                Swal.fire(
                    "Error",
                    error.response?.data?.message ||
                        "No se pudo guardar en el historial de reportes.",
                    "warning",
                );
            }
        },

        sortKey: null,
        sortDir: "asc",

        get personalOrdenado() {
            const lista = this.reporteData?.Personal || [];
            if (!this.sortKey) return lista;
            const dir = this.sortDir === "asc" ? 1 : -1;
            return [...lista].sort((a, b) => {
                const va = String(a[this.sortKey] || "").toLowerCase();
                const vb = String(b[this.sortKey] || "").toLowerCase();
                return va.localeCompare(vb, "es", { numeric: true }) * dir;
            });
        },

        ordenarPersonal(key) {
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === "asc" ? "desc" : "asc";
            } else {
                this.sortKey = key;
                this.sortDir = "asc";
            }
        },

        sortIcon(key) {
            if (this.sortKey !== key) return "";
            return this.sortDir === "asc"
                ? "ti ti-sort-ascending"
                : "ti ti-sort-descending";
        },

        cerrar() {
            if (_importarDnisAbierto) return;

            this.open = false;
            this.view = "filtros";
            this.generando = false;
            this.exportandoPDF = false;
            this.reporteData = null;
            this.fechaCreacionDesde = "";
            this.fechaCreacionHasta = "";
            this.selectedAnio = "";
            this.searchCurso = "";
            this.selectedSucursal = "";
            this.selectedVigencia = "1";
            this.selectedCargo = "";
            this.selectedTipoTrabajador = "";
            this.selectedCliente = "";
            this.searchPersonal = "";
            this.selectedCursoId = null;
            this.selectedPersonalDnis = [];
            this.importados = [];
            this.selectAllPersonal = false;
            this.cursosPage = 1;
            this.personalPage = 1;
            this.sortKey = null;
            this.sortDir = "asc";
            this.todosLosPersonales = [];
            this.personales = [];
        },
    }));

    Alpine.data("modalImportarDnis", () => ({
        open: false,
        target: null,
        cursoSeleccionado: false,
        stage: "idle",
        errorMsg: "",
        mostrarDescartados: false,

        resumen: {
            totalFilas: 0,
            validos: 0,
            encontrados: 0,
            noEncontrados: 0,
            invalidos: 0,
            duplicados: 0,
        },
        encontrados: [],
        noEncontrados: [],
        invalidos: [],
        duplicados: [],
        advertencias: [],

        get procesando() {
            return ["leyendo", "limpiando", "validando"].includes(this.stage);
        },

        get textoEtapa() {
            return (
                {
                    leyendo: "Leyendo archivo...",
                    limpiando: "Limpiando y validando datos...",
                    validando: "Validando DNIs en el sistema...",
                }[this.stage] || "Procesando..."
            );
        },

        get descartadosTotal() {
            return this.invalidos.length + this.duplicados.length;
        },

        abrir(detalle = {}) {
            this.target = detalle.target || null;
            this.cursoSeleccionado = Boolean(detalle.cursoSeleccionado);
            this.reiniciar();

            // Debe ejecutarse dentro del gesto del usuario para que el
            // navegador permita abrir el selector de archivos.
            this.$refs.archivo?.click();
        },

        reiniciar() {
            this.stage = "idle";
            this.errorMsg = "";
            this.mostrarDescartados = false;
            this.encontrados = [];
            this.noEncontrados = [];
            this.invalidos = [];
            this.duplicados = [];
            this.advertencias = [];
            this.resumen = {
                totalFilas: 0,
                validos: 0,
                encontrados: 0,
                noEncontrados: 0,
                invalidos: 0,
                duplicados: 0,
            };
        },

        cerrar() {
            if (this.procesando) return;

            this.open = false;
            this.stage = "idle";
            _importarDnisAbierto = false;
        },

        async onFile(event) {
            const file = event.target.files?.[0];
            event.target.value = "";
            if (!file) return;

            this.open = true;
            _importarDnisAbierto = true;
            this.reiniciar();

            try {
                this.stage = "leyendo";
                await _esperarPintado();

                this.stage = "limpiando";
                await _esperarPintado();

                const resultado = await _extraerDnisExcel(file);

                this.invalidos = resultado.invalidos;
                this.duplicados = resultado.duplicados;
                this.advertencias = resultado.advertencias;
                this.resumen.totalFilas = resultado.totalFilas;
                this.resumen.validos = resultado.dnis.length;
                this.resumen.invalidos = resultado.invalidos.length;
                this.resumen.duplicados = resultado.duplicados.length;

                if (resultado.dnis.length === 0) {
                    throw new Error(
                        "No se encontraron DNIs válidos en la columna B del archivo.",
                    );
                }

                this.stage = "validando";
                await _esperarPintado();

                const { data } = await axios.post(
                    `${VITE_URL_APP}/api/resolver-personal-dnis`,
                    { dnis: resultado.dnis },
                );

                if (!data.success) {
                    throw new Error(
                        data.message || "No se pudieron validar los DNIs.",
                    );
                }

                this.encontrados = data.encontrados || [];
                this.noEncontrados = data.no_encontrados || [];
                this.resumen.encontrados = this.encontrados.length;
                this.resumen.noEncontrados = this.noEncontrados.length;
                this.stage = "resultado";
            } catch (error) {
                console.error(error);
                this.errorMsg =
                    error.response?.data?.message ||
                    error.message ||
                    "No se pudo procesar el archivo.";
                this.stage = "error";
            }
        },

        async descargarFormato() {
            try {
                const workbook = new ExcelJS.Workbook();
                const sheet = workbook.addWorksheet("Personal");

                sheet.columns = [
                    { header: "Nro", key: "nro", width: 10 },
                    { header: "DNI", key: "dni", width: 22 },
                ];

                const headerRow = sheet.getRow(1);
                headerRow.height = 20;
                headerRow.eachCell((cell) => _estiloEncabezadoExcel(cell));

                sheet.getColumn(2).numFmt = "@";
                sheet.views = [{ state: "frozen", ySplit: 1 }];

                const buffer = await workbook.xlsx.writeBuffer();
                saveAs(
                    _blobExcel(buffer),
                    "FORMATO_IMPORTACION_PERSONAL.xlsx",
                );
            } catch (error) {
                console.error(error);
                Swal.fire(
                    "Error",
                    "No se pudo descargar el formato.",
                    "error",
                );
            }
        },

        confirmar() {
            if (this.encontrados.length === 0) return;

            this.$dispatch("dnis-importados", {
                target: this.target,
                dnis: this.encontrados.map((p) => p.dni),
                encontrados: this.encontrados,
            });

            this.open = false;
            this.stage = "idle";
            _importarDnisAbierto = false;
        },
    }));
});
