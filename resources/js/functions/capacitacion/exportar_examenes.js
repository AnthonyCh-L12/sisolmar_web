import axios from "axios";
import ExcelJS from "exceljs";

function _assetUrl(path) {
    const env = typeof APP_ENV !== "undefined" ? APP_ENV : (typeof window !== "undefined" ? window.APP_ENV : undefined);
    // local => raíz del sitio; cualquier otro/fallback => prefijo /sisolmar (producción)
    const base = env === "local" ? "" : "/sisolmar";
    return `${base}${path}`;
}

function _cargarImagen(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = url;
    });
}

// ====== CONFIGURACIÓN DEL PDF ======
const ORIENTACION = "portrait"; // "portrait" | "landscape"
const MARGEN = 12; // mm (más margen = tabla más angosta)
const ESCALA_MIN = 0.5; // escala mínima de fuentes/paddings

function _dibujarDetalle(doc, detalle, esc, ctx) {
    const {
        pageWidth,
        margen,
        anchoTabla,
        anchoMitad,
        logoSol,
        logoAV,
        medir,
    } = ctx;
    const s = (n) => n * esc;

    // ====== CABECERA ======
    let y = 7;
    const logoAVWidth = 13;
    const logoSolWidth = 22;

    if (!medir) {
        if (logoAV) {
            const h = logoAVWidth * (logoAV.height / logoAV.width);
            doc.addImage(logoAV, "PNG", margen, y, logoAVWidth, h);
        }
        if (logoSol) {
            const h = logoSolWidth * (logoSol.height / logoSol.width);
            doc.addImage(
                logoSol,
                "PNG",
                pageWidth - margen - logoSolWidth,
                y,
                logoSolWidth,
                h,
            );
        }
    }

    const anchoTitulo = pageWidth - margen * 2 - logoAVWidth - logoSolWidth - 8;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(10);
    doc.setTextColor(20, 40, 80);
    doc.text(
        (detalle.course_name || "CURSO").toUpperCase(),
        pageWidth / 2,
        y + 6,
        { align: "center", maxWidth: anchoTitulo },
    );

    y += 16;

    // ====== DATOS DEL ALUMNO ======
    const fsDatos = s(7.5);
    const labelStyle = {
        fontStyle: "bold",
        fillColor: [225, 232, 245],
        halign: "center",
        fontSize: fsDatos,
    };
    const valueStyle = { halign: "center", fontSize: fsDatos };

    doc.autoTable({
        startY: y,
        theme: "grid",
        tableWidth: anchoTabla,
        styles: {
            fontSize: fsDatos,
            cellPadding: s(0.8),
            valign: "middle",
            lineColor: [20, 40, 80],
            lineWidth: 0.12,
            textColor: [20, 20, 20],
        },
        body: [
            [
                { content: "Alumno", styles: labelStyle },
                {
                    content: (detalle.full_name || "—").toUpperCase(),
                    colSpan: 3,
                    styles: { ...valueStyle, fontStyle: "bold" },
                },
                { content: "DNI", styles: labelStyle },
                { content: detalle.dni || "—", styles: valueStyle },
            ],
            [
                { content: "Fecha", styles: labelStyle },
                { content: detalle.attempt_date || "—", styles: valueStyle },
                { content: "Duración", styles: labelStyle },
                { content: detalle.duration || "—", styles: valueStyle },
                { content: "Intento", styles: labelStyle },
                {
                    content: String(detalle.attempt_number ?? "—"),
                    styles: valueStyle,
                },
            ],
            [
                { content: "Inicio", styles: labelStyle },
                { content: detalle.start_time || "—", styles: valueStyle },
                { content: "Término", styles: labelStyle },
                { content: detalle.end_time || "—", styles: valueStyle },
                { content: "% Aprob.", styles: labelStyle },
                {
                    content:
                        detalle.passing_percentage != null
                            ? `${detalle.passing_percentage}%`
                            : "—",
                    styles: valueStyle,
                },
            ],
            [
                { content: "NOTA", styles: labelStyle },
                {
                    content:
                        detalle.obtained_grade != null
                            ? `${detalle.obtained_grade} / 20`
                            : "—",
                    styles: { ...valueStyle, fontStyle: "bold" },
                },
                { content: "Correctas", styles: labelStyle },
                {
                    content: String(detalle.correct_answers ?? "—"),
                    styles: { ...valueStyle, fontStyle: "bold" },
                },
                { content: "Incorrectas", styles: labelStyle },
                {
                    content: String(detalle.incorrect_answers ?? "—"),
                    styles: {
                        ...valueStyle,
                        fontStyle: "bold",
                        textColor: [180, 0, 0],
                    },
                },
            ],
        ],
        columnStyles: {
            0: { cellWidth: anchoTabla * 0.14 },
            1: { cellWidth: anchoTabla * 0.22 },
            2: { cellWidth: anchoTabla * 0.14 },
            3: { cellWidth: anchoTabla * 0.22 },
            4: { cellWidth: anchoTabla * 0.14 },
            5: { cellWidth: anchoTabla * 0.14 },
        },
        margin: { left: margen, right: margen, bottom: 9 },
    });

    y = doc.lastAutoTable.finalY + s(2);

    // ====== PREGUNTAS ======
    const preguntas = Array.isArray(detalle.questions_answers)
        ? detalle.questions_answers
        : [];
    const letras = ["a", "b", "c", "d", "e", "f", "g", "h"];

    preguntas.forEach((q, i) => {
        const opciones =
            Array.isArray(q.options) && q.options.length ? q.options : ["—"];

        const buildCell = (opt, idx) => {
            if (opt === undefined) {
                return {
                    content: "",
                    styles: { fillColor: [255, 255, 255], lineWidth: 0 },
                };
            }
            const esSel = q.response === opt;
            return {
                content: `${letras[idx] || "-"}) ${opt}`,
                styles: {
                    fontStyle: esSel ? "bold" : "normal",
                    textColor: [40, 40, 40],
                    fillColor: esSel ? [235, 235, 235] : [255, 255, 255],
                    cellPadding: {
                        top: s(0.5),
                        bottom: s(0.5),
                        left: 3,
                        right: 1.5,
                    },
                },
            };
        };

        const bodyOpciones = [];
        for (let j = 0; j < opciones.length; j += 2) {
            bodyOpciones.push([
                buildCell(opciones[j], j),
                buildCell(opciones[j + 1], j + 1),
            ]);
        }

        doc.autoTable({
            startY: y,
            theme: "grid",
            tableWidth: anchoTabla,
            head: [
                [
                    {
                        content: `${i + 1}. ${q.question || "—"}`,
                        colSpan: 2,
                        styles: {
                            fillColor: [225, 235, 250],
                            textColor: [20, 40, 80],
                            fontStyle: "bold",
                            fontSize: s(7.8),
                            halign: "left",
                            valign: "middle",
                            cellPadding: {
                                top: s(0.9),
                                bottom: s(0.9),
                                left: 3,
                                right: 3,
                            },
                            lineColor: [180, 195, 215],
                            lineWidth: 0.12,
                        },
                    },
                ],
            ],
            body: bodyOpciones,
            columnStyles: {
                0: { cellWidth: anchoMitad },
                1: { cellWidth: anchoMitad },
            },
            styles: {
                fontSize: s(7),
                cellPadding: s(0.5),
                lineColor: [200, 210, 225],
                lineWidth: 0.1,
                valign: "middle",
                overflow: "linebreak",
                minCellHeight: s(3.5),
            },
            margin: { left: margen, right: margen, bottom: 9 },
            rowPageBreak: "avoid",
        });

        y = doc.lastAutoTable.finalY + s(0.8);
    });

    return y;
}

// Busca la escala más grande con la que el examen entra en 1 sola hoja
function _calcularEscala(jsPDF, detalle, ctxBase) {
    for (let esc = 1; esc >= ESCALA_MIN; esc -= 0.05) {
        const tmp = new jsPDF({
            orientation: ORIENTACION,
            unit: "mm",
            format: "a4",
        });
        _dibujarDetalle(tmp, detalle, esc, { ...ctxBase, medir: true });
        if (tmp.getNumberOfPages() === 1) return esc;
    }
    return ESCALA_MIN;
}

export default document.addEventListener("alpine:init", () => {
    Alpine.data("exportExamenes", () => ({
        cursos: [],
        cursosAgrupados: [],
        searchCurso: "",
        selectedYear: "",
        anios: [],
        loadingCursos: false,
        errorCursos: false,
        totalCursos: 0,

        expandedCourseId: null,
        seleccionado: "",

        page: 1,
        perPage: 7,

        personal: [],
        loadingPersonal: false,
        errorPersonal: false,
        busquedaPersonal: "",
        filtroSucursal: "",
        filtroTipoTrabajador: "",
        filtroVigencia: "1",
        filtroCargo: "",
        personalPage: 1,
        personalPerPage: 6,

        seleccionadosPersonal: [],
        exportandoPDF: false,

        mostrarPreview: false,
        pdfUrl: null,
        pdfNombre: null,
        pdfDoc: null,

        // Aviso previo: personal que no desarrolló el examen
        mostrarModalPendientes: false,
        pendientes: [],
        exitosPendientes: [],
        generandoExcelPendientes: false,
        pendientesExcelListo: false,

        async init() {
            await Promise.all([this.cargarCursos(), this.cargarPersonal()]);
        },

        async cargarCursos() {
            this.loadingCursos = true;
            this.errorCursos = false;
            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/obtener-cursos-av`,
                );
                if (data.success) {
                    this.cursos = Array.isArray(data.data?.cursos)
                        ? data.data.cursos
                        : [];
                    this.totalCursos = data.data?.total || this.cursos.length;
                    this.agruparPorCurso();
                } else {
                    this.cursos = [];
                    this.errorCursos = true;
                }
            } catch (e) {
                console.error("Error cargando cursos:", e);
                this.cursos = [];
                this.errorCursos = true;
            } finally {
                this.loadingCursos = false;
            }
        },

        agruparPorCurso() {
            const mapa = new Map();
            const aniosSet = new Set();

            this.cursos.forEach((item) => {
                const anio = item.course_created
                    ? String(item.course_created).slice(0, 4)
                    : "";
                if (anio) aniosSet.add(anio);

                if (!mapa.has(item.course_id)) {
                    mapa.set(item.course_id, {
                        course_id: item.course_id,
                        course_name: item.course_name,
                        course_created: item.course_created,
                        anio,
                        quizzes: [],
                    });
                }
                mapa.get(item.course_id).quizzes.push({
                    quiz_id: item.quiz_id,
                    quiz_name: item.quiz_name,
                });
            });

            this.cursosAgrupados = [...mapa.values()];
            this.anios = [...aniosSet].sort((a, b) => b.localeCompare(a));
            this.totalCursos = this.cursosAgrupados.length;
            this.page = 1;
        },

        get cursosFiltrados() {
            let cursos = this.cursosAgrupados;

            if (this.selectedYear) {
                cursos = cursos.filter(
                    (curso) => curso.anio === this.selectedYear,
                );
            }

            const termino = (this.searchCurso || "").trim().toLowerCase();
            if (!termino) return cursos;

            return cursos
                .map((curso) => ({
                    ...curso,
                    quizzes: curso.quizzes.filter(
                        (quiz) =>
                            quiz.quiz_name.toLowerCase().includes(termino) ||
                            curso.course_name.toLowerCase().includes(termino),
                    ),
                }))
                .filter((curso) => curso.quizzes.length > 0);
        },

        get cursosPaginados() {
            const inicio = (this.page - 1) * this.perPage;
            return this.cursosFiltrados.slice(inicio, inicio + this.perPage);
        },

        get totalPaginas() {
            return Math.max(
                1,
                Math.ceil(this.cursosFiltrados.length / this.perPage),
            );
        },

        get paginas() {
            const total = this.totalPaginas;
            const actual = this.page;
            const inicio = Math.max(1, actual - 2);
            const fin = Math.min(total, inicio + 4);
            const rango = [];
            for (let i = inicio; i <= fin; i++) rango.push(i);
            return rango;
        },

        get textoMostrando() {
            const total = this.cursosFiltrados.length;
            if (total === 0) return "0 resultado(s)";
            const inicio = (this.page - 1) * this.perPage + 1;
            const fin = Math.min(this.page * this.perPage, total);
            return `${inicio} - ${fin} de ${total}`;
        },

        irAPagina(pagina) {
            if (pagina < 1 || pagina > this.totalPaginas) return;
            this.page = pagina;
            this.expandedCourseId = null;
        },

        toggleExpandirCurso(courseId) {
            this.expandedCourseId =
                this.expandedCourseId === courseId ? null : courseId;
        },

        keySeleccion(curso, quiz) {
            return `${curso.course_id}:${quiz.quiz_id}`;
        },

        esSeleccionado(curso, quiz) {
            return this.seleccionado === this.keySeleccion(curso, quiz);
        },

        seleccionarExamen(curso, quiz) {
            const key = this.keySeleccion(curso, quiz);
            this.seleccionado = this.esSeleccionado(curso, quiz) ? "" : key;
        },

        get totalSeleccionados() {
            return this.seleccionado ? 1 : 0;
        },

        get hayExamenSeleccionado() {
            return this.seleccionado !== "";
        },

        cursoBloqueado(curso) {
            return (
                this.hayExamenSeleccionado &&
                !curso.quizzes.some((q) => this.esSeleccionado(curso, q))
            );
        },

        async cargarPersonal() {
            this.loadingPersonal = true;
            this.errorPersonal = false;
            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/obtener-personal`,
                    { params: { vigente: "2" } },
                );
                if (data.success) {
                    this.personal = Array.isArray(data.personal)
                        ? data.personal
                        : [];
                } else {
                    this.personal = [];
                    this.errorPersonal = true;
                }
            } catch (e) {
                console.error("Error cargando personal:", e);
                this.personal = [];
                this.errorPersonal = true;
            } finally {
                this.loadingPersonal = false;
            }
        },

        get sucursales() {
            return this.valoresUnicos(this.personal, "sucursal");
        },

        get tiposTrabajador() {
            return this.valoresUnicos(this.personal, "tipo_trabajador");
        },

        get cargos() {
            return this.valoresUnicos(this.personal, "cargo");
        },

        valoresUnicos(lista, campo) {
            const valores = new Set(
                lista
                    .map((p) => (p[campo] || "").toString().trim())
                    .filter((v) => v !== ""),
            );
            return [...valores].sort((a, b) => a.localeCompare(b));
        },

        get personalFiltrado() {
            let lista = this.personal;

            const termino = (this.busquedaPersonal || "").trim().toLowerCase();
            if (termino) {
                lista = lista.filter(
                    (p) =>
                        (p.nombre_completo || "")
                            .toLowerCase()
                            .includes(termino) ||
                        (p.dni || "").toLowerCase().includes(termino),
                );
            }

            if (this.filtroSucursal) {
                lista = lista.filter((p) => p.sucursal === this.filtroSucursal);
            }

            if (this.filtroTipoTrabajador) {
                lista = lista.filter(
                    (p) => p.tipo_trabajador === this.filtroTipoTrabajador,
                );
            }

            if (this.filtroCargo) {
                lista = lista.filter((p) => p.cargo === this.filtroCargo);
            }

            if (this.filtroVigencia === "1") {
                lista = lista.filter((p) => p.vigente === true);
            } else if (this.filtroVigencia === "0") {
                lista = lista.filter((p) => p.vigente === false);
            }

            const idxSeleccionados = new Map();
            lista.forEach((p, i) =>
                idxSeleccionados.set(this.clavePersonal(p), i),
            );

            return lista.slice().sort((a, b) => {
                const aSel = this.estaSeleccionado(a) ? 0 : 1;
                const bSel = this.estaSeleccionado(b) ? 0 : 1;
                if (aSel !== bSel) return aSel - bSel;
                return (
                    (idxSeleccionados.get(this.clavePersonal(a)) ?? 0) -
                    (idxSeleccionados.get(this.clavePersonal(b)) ?? 0)
                );
            });
        },

        get personalPaginado() {
            const inicio = (this.personalPage - 1) * this.personalPerPage;
            return this.personalFiltrado.slice(
                inicio,
                inicio + this.personalPerPage,
            );
        },

        get totalPaginasPersonal() {
            return Math.max(
                1,
                Math.ceil(this.personalFiltrado.length / this.personalPerPage),
            );
        },

        get paginasPersonal() {
            const total = this.totalPaginasPersonal;
            const actual = this.personalPage;
            const inicio = Math.max(1, actual - 2);
            const fin = Math.min(total, inicio + 4);
            const rango = [];
            for (let i = inicio; i <= fin; i++) rango.push(i);
            return rango;
        },

        get textoMostrandoPersonal() {
            const total = this.personalFiltrado.length;
            if (total === 0) return "0 resultado(s)";
            const inicio = (this.personalPage - 1) * this.personalPerPage + 1;
            const fin = Math.min(
                this.personalPage * this.personalPerPage,
                total,
            );
            return `${inicio} - ${fin} de ${total}`;
        },

        irAPaginaPersonal(pagina) {
            if (pagina < 1 || pagina > this.totalPaginasPersonal) return;
            this.personalPage = pagina;
        },

        clavePersonal(p) {
            return `${p.codigo ?? ""}:${p.dni ?? ""}`;
        },

        estaSeleccionado(p) {
            return this.seleccionadosPersonal.includes(this.clavePersonal(p));
        },

        toggleSeleccionarPersonal(p) {
            const clave = this.clavePersonal(p);
            const idx = this.seleccionadosPersonal.indexOf(clave);
            if (idx !== -1) {
                this.seleccionadosPersonal.splice(idx, 1);
                return;
            }
            this.seleccionadosPersonal.push(clave);
        },

        // true si todos los personales filtrados (de todas las páginas) ya están seleccionados
        get todosFiltradosSeleccionados() {
            const lista = this.personalFiltrado;
            return (
                lista.length > 0 && lista.every((p) => this.estaSeleccionado(p))
            );
        },

        // Selecciona todos los filtrados; si ya estaban todos, los deselecciona
        seleccionarTodosFiltrados() {
            const lista = this.personalFiltrado;
            if (!lista.length) return;

            if (this.todosFiltradosSeleccionados) {
                const claves = new Set(lista.map((p) => this.clavePersonal(p)));
                this.seleccionadosPersonal = this.seleccionadosPersonal.filter(
                    (c) => !claves.has(c),
                );
                return;
            }

            const actuales = new Set(this.seleccionadosPersonal);
            lista.forEach((p) => {
                const clave = this.clavePersonal(p);
                if (!actuales.has(clave)) {
                    this.seleccionadosPersonal.push(clave);
                    actuales.add(clave);
                }
            });
        },

        personalCompleto() {
            return this.seleccionadosPersonal.length;
        },

        get examenSeleccionadoInfo() {
            if (!this.seleccionado) return null;
            const [courseId, quizId] = this.seleccionado.split(":");
            const curso = this.cursosAgrupados.find(
                (c) => String(c.course_id) === String(courseId),
            );
            if (!curso) return null;
            const quiz = curso.quizzes.find(
                (q) => String(q.quiz_id) === String(quizId),
            );
            return {
                course_name: curso.course_name,
                quiz_name: quiz ? quiz.quiz_name : "",
            };
        },

        get personalSeleccionadoDetalle() {
            return this.seleccionadosPersonal
                .map((clave) =>
                    this.personal.find((p) => this.clavePersonal(p) === clave),
                )
                .filter(Boolean);
        },

        get puedeExportar() {
            return (
                this.hayExamenSeleccionado &&
                this.seleccionadosPersonal.length > 0
            );
        },

        async exportar() {
            if (!this.puedeExportar || this.exportandoPDF) return;

            const personas = this.personalSeleccionadoDetalle;
            const quizId = this.seleccionado.split(":")[1];
            if (!personas.length || !quizId) return;

            this.exportandoPDF = true;
            try {
                const { data } = await axios.post(
                    `${VITE_URL_APP}/api/obtener-datos-reporte-av`,
                    {
                        dnis: personas.map((p) => p.dni),
                        quizId,
                    },
                );

                if (!data.success) {
                    Swal.fire(
                        "Sin resultados",
                        data.message ||
                            "El personal no tiene intentos registrados en este examen.",
                        "warning",
                    );
                    return;
                }

                const resultados = Array.isArray(data.data?.resultados)
                    ? data.data.resultados
                    : [];

                // Índice DNI -> persona (la API normaliza los DNIs a minúsculas)
                const normDni = (v) => String(v ?? "").trim().toLowerCase();
                const personaPorDni = new Map(
                    personas.map((p) => [normDni(p.dni), p]),
                );

                const fallidos = [];
                const exitos = [];
                const errores = [];
                for (const resultado of resultados) {
                    if (resultado.success && resultado.data) {
                        exitos.push(resultado.data);
                        continue;
                    }
                    // "No desarrolló el examen": la API devuelve el DNI consultado.
                    // Se cruza con el detalle del personal seleccionado para
                    // recuperar nombre, sucursal, cargo, etc.
                    if (resultado.dni) {
                        const dniNorm = normDni(resultado.dni);
                        const persona = personaPorDni.get(dniNorm);
                        fallidos.push(
                            persona
                                ? { ...persona, dni: resultado.dni }
                                : {
                                      nombre_completo: resultado.dni,
                                      dni: resultado.dni,
                                      sucursal: "",
                                      cargo: "",
                                      tipo_trabajador: "",
                                      vigente: null,
                                  },
                        );
                    } else {
                        errores.push(
                            resultado.message || "Error desconocido",
                        );
                    }
                }

                this.exitosPendientes = exitos;
                this.pendientes = fallidos;
                this.pendientesExcelListo = false;

                if (fallidos.length > 0) {
                    // Primero se avisa quiénes no desarrollaron el examen;
                    // la vista previa se muestra al pulsar "Entendido".
                    this.mostrarModalPendientes = true;
                } else if (exitos.length > 0) {
                    await this.generarPreviewPdf(exitos);
                }

                if (errores.length > 0) {
                    Swal.fire(
                        "Reporte incompleto",
                        "No se pudo obtener la información de algunos DNIs: " +
                            errores.join(", "),
                        "error",
                    );
                }
            } catch (e) {
                console.error(e);
                const msg = e.response?.data?.message;
                if (msg) {
                    Swal.fire("Sin resultados", msg, "warning");
                } else {
                    Swal.fire(
                        "Error",
                        "No se pudo obtener el reporte del examen.",
                        "error",
                    );
                }
            } finally {
                this.exportandoPDF = false;
            }
        },

        async generarPreviewPdf(detalles) {
            const { jsPDF } = window.jspdf;

            if (!jsPDF) {
                Swal.fire(
                    "Error",
                    "jsPDF no está disponible. Revise su conexión e intente nuevamente.",
                    "error",
                );
                return;
            }

            const doc = new jsPDF({
                orientation: ORIENTACION,
                unit: "mm",
                format: "a4",
            });
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();

            const margen = MARGEN;
            const anchoTabla = pageWidth - margen * 2;
            const anchoMitad = anchoTabla / 2;

            const logoSol = await _cargarImagen(
                _assetUrl("/images/logo_sol.png"),
            );
            const logoAV = await _cargarImagen(_assetUrl("/images/AV.png"));

            const ctx = {
                pageWidth,
                margen,
                anchoTabla,
                anchoMitad,
                logoSol,
                logoAV,
                medir: false,
            };

            // Una hoja por persona: se calcula la mayor escala que cabe en 1 página
            detalles.forEach((detalle, index) => {
                if (index > 0) doc.addPage();
                const esc = _calcularEscala(jsPDF, detalle, ctx);
                _dibujarDetalle(doc, detalle, esc, ctx);
            });

            // ====== PIE DE PÁGINA ======
            const totalPaginas = doc.getNumberOfPages();
            for (let i = 1; i <= totalPaginas; i++) {
                doc.setPage(i);
                doc.setFont("helvetica", "normal");
                doc.setFontSize(6.5);
                doc.setTextColor(140, 140, 140);

                const fechaGeneracion = new Date().toLocaleString("es-PE", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hour12: true,
                });

                doc.text(
                    `Generado por Sisolmar Web · ${fechaGeneracion}`,
                    margen,
                    pageHeight - 3,
                );
                doc.text(
                    `Página ${i} de ${totalPaginas}`,
                    pageWidth - margen,
                    pageHeight - 3,
                    { align: "right" },
                );
            }

            // ====== NOMBRE DINÁMICO DEL ARCHIVO ======
            const hoy = new Date();
            const dd = String(hoy.getDate()).padStart(2, "0");
            const mm = String(hoy.getMonth() + 1).padStart(2, "0");
            const yyyy = hoy.getFullYear();
            const fechaStr = `${dd}-${mm}-${yyyy}`;

            const sanitizar = (str) =>
                (str || "")
                    .normalize("NFD")
                    .replace(/[\u0300-\u036f]/g, "")
                    .replace(/[\\/:*?"<>|]/g, "")
                    .trim();

            const primerDetalle = detalles[0] || {};
            const courseName = sanitizar(primerDetalle.course_name || "CURSO");

            let nombreArchivo;
            if (detalles.length === 1) {
                const fullName = sanitizar(
                    primerDetalle.full_name || "PERSONAL",
                )
                    .toUpperCase()
                    .replace(/\s+/g, "_");
                nombreArchivo = `${fullName} - ${courseName} - ${fechaStr}.pdf`;
            } else {
                nombreArchivo = `REPORTE_EXAMENES - ${courseName} - ${fechaStr}.pdf`;
            }

            // ====== PREVIEW ======
            const blob = doc.output("blob");
            if (this.pdfUrl) URL.revokeObjectURL(this.pdfUrl);
            this.pdfUrl = URL.createObjectURL(blob);
            this.pdfNombre = nombreArchivo;
            this.pdfDoc = doc;
            this.mostrarPreview = true;
        },

        descargarPdf() {
            if (!this.pdfDoc) return;
            this.pdfDoc.save(this.pdfNombre || "reporte.pdf");
        },

        cerrarPreview() {
            this.mostrarPreview = false;
            if (this.pdfUrl) {
                URL.revokeObjectURL(this.pdfUrl);
                this.pdfUrl = null;
            }
            this.pdfDoc = null;
            this.pdfNombre = null;
        },

        // ====== AVISO: PERSONAL QUE NO DESARROLLÓ EL EXAMEN ======
        sanitizarNombreArchivo(str) {
            return (str || "")
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/[\\/:*?"<>|]/g, "")
                .replace(/\s+/g, "_")
                .trim();
        },

        async exportarPendientesExcel() {
            if (this.generandoExcelPendientes || this.pendientes.length === 0)
                return;

            this.generandoExcelPendientes = true;
            try {
                const workbook = new ExcelJS.Workbook();
                workbook.creator = "Sisolmar Web";
                workbook.created = new Date();

                const ws = workbook.addWorksheet("No rindieron");

                const COLS = 7;
                const borde = {
                    top: { style: "thin", color: { argb: "FFD1D5DB" } },
                    left: { style: "thin", color: { argb: "FFD1D5DB" } },
                    bottom: { style: "thin", color: { argb: "FFD1D5DB" } },
                    right: { style: "thin", color: { argb: "FFD1D5DB" } },
                };

                const mergeTitulo = (texto, estilos) => {
                    const fila = ws.rowCount + 1;
                    ws.mergeCells(fila, 1, fila, COLS);
                    const celda = ws.getCell(fila, 1);
                    Object.assign(celda, estilos);
                    celda.value = texto;
                    celda.alignment = {
                        horizontal: "left",
                        vertical: "middle",
                        wrapText: true,
                        ...(estilos.alignment || {}),
                    };
                    return fila;
                };

                let fila = mergeTitulo(
                    "PERSONAL QUE NO DESARROLLÓ EL EXAMEN",
                    {
                        font: {
                            bold: true,
                            size: 14,
                            color: { argb: "FF92400E" },
                        },
                        fill: {
                            type: "pattern",
                            pattern: "solid",
                            fgColor: { argb: "FFFEF3C7" },
                        },
                        alignment: { horizontal: "center" },
                    },
                );
                ws.getRow(fila).height = 26;

                const info = this.examenSeleccionadoInfo;
                if (info) {
                    fila = mergeTitulo(`Curso: ${info.course_name || "—"}`, {
                        font: { bold: true, size: 10, color: { argb: "FF374151" } },
                    });
                    fila = mergeTitulo(`Examen: ${info.quiz_name || "—"}`, {
                        font: { size: 10, color: { argb: "FF6B7280" } },
                    });
                }

                const fechaGeneracion = new Date().toLocaleString("es-PE", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: true,
                });
                fila = mergeTitulo(
                    `Generado: ${fechaGeneracion} · Total: ${this.pendientes.length} persona(s)`,
                    { font: { size: 9, color: { argb: "FF6B7280" } } },
                );

                // Fila vacía de separación
                ws.addRow([]);

                // Cabecera de la tabla
                const cabeceras = [
                    "N°",
                    "Apellidos y nombres",
                    "DNI",
                    "Sucursal",
                    "Cargo",
                    "Tipo de trabajador",
                    "Vigencia",
                ];
                fila = ws.rowCount + 1;
                cabeceras.forEach((texto, i) => {
                    const celda = ws.getCell(fila, i + 1);
                    celda.value = texto;
                    celda.font = {
                        bold: true,
                        size: 10,
                        color: { argb: "FFFFFFFF" },
                    };
                    celda.fill = {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: "FF374151" },
                    };
                    celda.alignment = {
                        horizontal: "center",
                        vertical: "middle",
                        wrapText: true,
                    };
                    celda.border = borde;
                });
                ws.getRow(fila).height = 24;

                const filaCabecera = fila;

                // Datos
                this.pendientes.forEach((p, i) => {
                    fila = ws.rowCount + 1;
                    const valores = [
                        i + 1,
                        (p.nombre_completo || "—").toUpperCase(),
                        p.dni || "—",
                        p.sucursal || "—",
                        p.cargo || "—",
                        p.tipo_trabajador || "—",
                        p.vigente === null || p.vigente === undefined
                            ? "—"
                            : p.vigente
                              ? "Vigente"
                              : "No vigente",
                    ];
                    valores.forEach((valor, ci) => {
                        const celda = ws.getCell(fila, ci + 1);
                        celda.value = valor;
                        celda.font = { size: 10 };
                        celda.border = borde;
                        celda.alignment = {
                            horizontal: ci === 0 || ci === 2 ? "center" : "left",
                            vertical: "middle",
                        };
                        if (i % 2 === 1) {
                            celda.fill = {
                                type: "pattern",
                                pattern: "solid",
                                fgColor: { argb: "FFF9FAFB" },
                            };
                        }
                    });
                });

                ws.columns = [
                    { width: 6 },
                    { width: 42 },
                    { width: 14 },
                    { width: 24 },
                    { width: 28 },
                    { width: 20 },
                    { width: 12 },
                ];
                ws.autoFilter = {
                    from: { row: filaCabecera, column: 1 },
                    to: { row: filaCabecera, column: COLS },
                };
                ws.views = [{ state: "frozen", ySplit: filaCabecera }];

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });

                const hoy = new Date();
                const dd = String(hoy.getDate()).padStart(2, "0");
                const mm = String(hoy.getMonth() + 1).padStart(2, "0");
                const yyyy = hoy.getFullYear();
                const curso = this.sanitizarNombreArchivo(
                    info?.course_name || "CURSO",
                );

                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = `PERSONAL_SIN_EXAMEN_-_${curso}_-_${dd}-${mm}-${yyyy}.xlsx`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);

                this.pendientesExcelListo = true;
                setTimeout(() => {
                    this.pendientesExcelListo = false;
                }, 2500);
            } catch (e) {
                console.error(e);
                Swal.fire(
                    "Error",
                    "No se pudo generar el Excel con el personal pendiente.",
                    "error",
                );
            } finally {
                this.generandoExcelPendientes = false;
            }
        },

        async continuarPreview() {
            const exitos = this.exitosPendientes || [];
            this.mostrarModalPendientes = false;
            this.exitosPendientes = [];
            this.pendientesExcelListo = false;
            if (exitos.length > 0) {
                await this.generarPreviewPdf(exitos);
            }
        },
    }));
});
