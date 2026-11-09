import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import axios from "axios";

export default document.addEventListener("alpine:init", () => {
    Alpine.data("planesCapacAppNew", () => ({
        pdfUrl: "",
        open: false,
        openConsultas: false,
        planes: [],
        loadingPlanes: false,
        selectedCodigo: null,
        selectedPlan: null,
        selectedAbreviatura: null,
        cursos: [],
        loadingCursos: false,
        filtroSistema: "",
        filtroArea: "",
        filtroCliente: "",
        filtroMes: "",
        filtroAnio: "",
        paginaActual: 1,
        cursosPorPagina: 5,
        openSeleccionCliente: false,
        clientesPCA: [],
        loadingClientesPCA: false,
        selectedClientePCA: "",
        pdfTitulo: "Plan de Capacitación Estándar (PCE)",
        pdfDownloadNombre: "Plan_Capacitacion_Estandar_PCE_" + new Date().getFullYear() + ".pdf",

        generarDocEstiloPCE(data, tituloCabecera) {
            const anio = data.Anio;

            const doc = new jsPDF({
                orientation: "landscape",
                unit: "mm",
                format: "a4",
            });

            const months = [
                "ENERO",
                "FEBRERO",
                "MARZO",
                "ABRIL",
                "MAYO",
                "JUNIO",
                "JULIO",
                "AGOSTO",
                "SETIEMBRE",
                "OCTUBRE",
                "NOVIEMBRE",
                "DICIEMBRE",
            ];

            const monthHeaders = [
                "ENE",
                "FEB",
                "MAR",
                "ABR",
                "MAY",
                "JUN",
                "JUL",
                "AGO",
                "SEP",
                "OCT",
                "NOV",
                "DIC",
            ];
            const esPCA = !(data.Sistemas && data.Sistemas.length) && !!data.Cursos;
            const TOTAL_COLS = (esPCA ? 4 : 5) + 24;

            const rawRows = [];

            const armarFila = (curso, item) => {
                const row = {
                    isSystem: false,
                    item: String(item),
                    curso: (curso.Nombre || "").toUpperCase(),
                    dirigido: (curso.Dirigido_a || "").toUpperCase(),
                    area: (curso.Area_Resp || "").toUpperCase(),
                    tiempo: curso.Tiempo_Horas
                        ? String(curso.Tiempo_Horas).toUpperCase() === "1"
                            ? "01 HORA"
                            : String(curso.Tiempo_Horas).toUpperCase()
                        : "01 HORA",
                };

                for (const m of months) {
                    row[`${m}_1`] = "";
                    row[`${m}_2`] = "";
                }

                for (const prog of curso.Programaciones || []) {
                    let mesNorm = (prog.Mes || "").toUpperCase().trim();
                    if (mesNorm === "SEPTIEMBRE") mesNorm = "SETIEMBRE";
                    const idx = months.indexOf(mesNorm);
                    if (idx < 0) continue;
                    const key = `${months[idx]}_${prog.Bloque}`;
                    if (prog.Ejecutado) row[key] = "E";
                    else if (prog.Programado) row[key] = "P";
                    else if (prog.Reprogramado) row[key] = "R";
                }

                return row;
            };

            if (data.Sistemas && data.Sistemas.length) {
                for (const sistema of data.Sistemas) {
                    rawRows.push({
                        isSystem: true,
                        label: sistema.Nombre.toUpperCase(),
                    });

                    if (sistema.Cursos.length === 0) {
                        rawRows.push({ isEmpty: true });
                        continue;
                    }

                    let item = 0;

                    for (const curso of sistema.Cursos) {
                        item++;
                        rawRows.push(armarFila(curso, item));
                    }
                }
            } else {
                // PCA: lista plana sin agrupación por sistemas
                let item = 0;
                for (const curso of data.Cursos || []) {
                    item++;
                    rawRows.push(armarFila(curso, item));
                }
            }

            const bodyRows = rawRows.map((r) => {
                if (r.isSystem) {
                    return [
                        {
                            content: r.label,
                            colSpan: TOTAL_COLS,
                            styles: {
                                fillColor: [184, 204, 228],
                                textColor: [0, 0, 0],
                                fontStyle: "bold",
                                fontSize: 7,
                                halign: "left",
                                cellPadding: {
                                    top: 2,
                                    bottom: 2,
                                    left: 2,
                                    right: 2,
                                },
                            },
                        },
                    ];
                }

                if (r.isEmpty) {
                    return Array.from({ length: TOTAL_COLS }, () => ({
                        content: "",
                        styles: {
                            cellPadding: {
                                top: 3,
                                bottom: 3,
                                left: 2,
                                right: 2,
                            },
                            lineColor: [0, 0, 0],
                            lineWidth: 0.1,
                        },
                    }));
                }

                return esPCA
                    ? [
                          r.item,
                          r.curso,
                          r.dirigido,
                          r.tiempo,
                          ...months.flatMap((m) => [
                              r[`${m}_1`] || "",
                              r[`${m}_2`] || "",
                          ]),
                      ]
                    : [
                          r.item,
                          r.curso,
                          r.dirigido,
                          r.area,
                          r.tiempo,
                          ...months.flatMap((m) => [
                              r[`${m}_1`] || "",
                              r[`${m}_2`] || "",
                          ]),
                      ];
            });

            const periodoCronograma = esPCA
                ? `${anio} - ${Number(anio) + 1}`
                : `${anio}`;

            autoTable(doc, {
                startY: 10,
                margin: { left: 5, right: 5 },
                head: [
                    [
                        {
                            content: tituloCabecera,
                            colSpan: TOTAL_COLS,
                            styles: {
                                fillColor: [184, 204, 228],
                                textColor: [0, 0, 0],
                                fontStyle: "bold",
                                fontSize: 10,
                                halign: "center",
                                valign: "middle",
                                cellPadding: {
                                    top: 4,
                                    bottom: 4,
                                    left: 2,
                                    right: 2,
                                },
                            },
                        },
                    ],
                    [
                        {
                            content: "ITEM",
                            rowSpan: 2,
                            styles: { halign: "center", valign: "middle" },
                        },
                        {
                            content: "TEMA",
                            rowSpan: 2,
                            styles: { halign: "center", valign: "middle" },
                        },
                        {
                            content: "DIRIGIDO A",
                            rowSpan: 2,
                            styles: { halign: "center", valign: "middle" },
                        },
                        ...(esPCA
                            ? []
                            : [
                                  {
                                      content: "ÁREA\nRESPONSABLE",
                                      rowSpan: 2,
                                      styles: {
                                          halign: "center",
                                          valign: "middle",
                                      },
                                  },
                              ]),
                        {
                            content: "TIEMPO",
                            rowSpan: 2,
                            styles: { halign: "center", valign: "middle" },
                        },
                        {
                            content: `CRONOGRAMA ${periodoCronograma}`,
                            colSpan: 24,
                            styles: {
                                halign: "center",
                                valign: "middle",
                                fontStyle: "bold",
                            },
                        },
                    ],
                    [
                        ...monthHeaders.map((m) => ({
                            content: m,
                            colSpan: 2,
                            styles: { halign: "center", valign: "middle" },
                        })),
                    ],
                ],
                body: bodyRows,
                styles: {
                    fontSize: 6,
                    cellPadding: {
                        top: 1.5,
                        bottom: 1.5,
                        left: 1.5,
                        right: 1.5,
                    },
                    valign: "middle",
                    lineColor: [0, 0, 0],
                    lineWidth: 0.1,
                },
                headStyles: {
                    fillColor: [255, 255, 255],
                    textColor: [0, 0, 0],
                    fontSize: 6,
                    fontStyle: "bold",
                    halign: "center",
                    valign: "middle",
                },
                columnStyles: {
                    0: { cellWidth: 8, halign: "center" }, // ITEM
                    [esPCA ? 3 : 4]: { cellWidth: 12, halign: "center" }, // TIEMPO
                    ...Object.fromEntries(
                        Array.from({ length: 24 }, (_, i) => [
                            i + (esPCA ? 4 : 5),
                            { cellWidth: 5, halign: "center" },
                        ]),
                    ),
                },
                didParseCell(data) {
                    if (
                        data.section === "body" &&
                        data.column.index >= (esPCA ? 4 : 5)
                    ) {
                        const val = data.cell.text
                            ?.join("")
                            ?.trim()
                            ?.toUpperCase();
                        if (val === "P") {
                            data.cell.styles.fillColor = [220, 38, 38];
                            data.cell.styles.textColor = [255, 255, 255];
                            data.cell.styles.fontStyle = "bold";
                        } else if (val === "E") {
                            data.cell.styles.fillColor = [37, 99, 235];
                            data.cell.styles.textColor = [255, 255, 255];
                            data.cell.styles.fontStyle = "bold";
                        } else if (val === "R") {
                            data.cell.styles.fillColor = [234, 88, 12];
                            data.cell.styles.textColor = [255, 255, 255];
                            data.cell.styles.fontStyle = "bold";
                        }
                    }
                },
            });

            return doc;
        },

        async abrirPDF() {
            this.pdfTitulo = "Plan de Capacitación Estándar (PCE)";
            this.pdfDownloadNombre = "Plan_Capacitacion_Estandar_PCE_" + new Date().getFullYear() + ".pdf";

            Swal.fire({
                title: "Generando PDF...",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/obtener-plan-pce-new`,
                );

                const doc = this.generarDocEstiloPCE(
                    data,
                    `PROGRAMA DE CAPACITACIÓN ESTÁNDAR DEL AÑO ${data.Anio}`,
                );

                const blob = doc.output("blob");
                if (this.pdfUrl && this.pdfUrl.startsWith("blob:")) URL.revokeObjectURL(this.pdfUrl);
                this.pdfUrl = URL.createObjectURL(blob);

                Swal.close();
                this.open = true;
            } catch (e) {
                Swal.close();
                console.error(e);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No se pudo generar el PDF.",
                });
            }
        },

        get sistemasUnicos() {
            return [...new Set(this.cursos.map(c => c.Sistema).filter(Boolean))].sort();
        },

        get areasUnicas() {
            return [...new Set(this.cursos.map(c => c.Area).filter(Boolean))].sort();
        },

        get clientesUnicos() {
            return [...new Set(this.cursos.map(c => c.Cliente).filter(Boolean))].sort();
        },

        get hayVigentes() {
            return this.cursosFiltrados.some(c => c.Vigente);
        },

        _parseFecha(fecha) {
            if (!fecha) return null;
            const partes = fecha.split('/');
            if (partes.length < 3) return null;
            const dia = +partes[0];
            const mes = +partes[1] - 1;
            const anioHora = partes[2].split(' ');
            return new Date(+anioHora[0], mes, dia);
        },

        _meses: [
            { valor: '1', label: 'Enero' }, { valor: '2', label: 'Febrero' },
            { valor: '3', label: 'Marzo' }, { valor: '4', label: 'Abril' },
            { valor: '5', label: 'Mayo' }, { valor: '6', label: 'Junio' },
            { valor: '7', label: 'Julio' }, { valor: '8', label: 'Agosto' },
            { valor: '9', label: 'Setiembre' }, { valor: '10', label: 'Octubre' },
            { valor: '11', label: 'Noviembre' }, { valor: '12', label: 'Diciembre' },
        ],

        get mesesUnicos() {
            const meses = new Set();
            this.cursos.forEach(c => {
                const d = this._parseFecha(c.Fecha_Creacion);
                if (d) meses.add(d.getMonth() + 1);
            });
            return [...meses].sort((a, b) => a - b).map(m => ({
                valor: String(m),
                label: this._meses[m - 1].label,
            }));
        },

        get aniosUnicos() {
            const anios = new Set();
            this.cursos.forEach(c => {
                const d = this._parseFecha(c.Fecha_Creacion);
                if (d) anios.add(d.getFullYear());
            });
            return [...anios].sort((a, b) => b - a);
        },

        get cursosFiltrados() {
            return this.cursos.filter(c => {
                if (this.filtroSistema && c.Sistema !== this.filtroSistema) return false;
                if (this.filtroArea && c.Area !== this.filtroArea) return false;
                if (this.filtroCliente && c.Cliente !== this.filtroCliente) return false;
                if (this.filtroMes || this.filtroAnio) {
                    const d = this._parseFecha(c.Fecha_Creacion);
                    if (!d) return false;
                    if (this.filtroMes && String(d.getMonth() + 1) !== this.filtroMes) return false;
                    if (this.filtroAnio && String(d.getFullYear()) !== this.filtroAnio) return false;
                }
                return true;
            });
        },

        get totalPaginas() {
            return Math.max(1, Math.ceil(this.cursosFiltrados.length / this.cursosPorPagina));
        },

        get paginaClamp() {
            return Math.min(this.paginaActual, this.totalPaginas);
        },

        get cursosPaginados() {
            const inicio = (this.paginaClamp - 1) * this.cursosPorPagina;
            return this.cursosFiltrados.slice(inicio, inicio + this.cursosPorPagina);
        },

        get paginasVisibles() {
            const total = this.totalPaginas;
            const actual = this.paginaClamp;
            const ventana = 5;
            let inicio = Math.max(1, actual - 2);
            let fin = Math.min(total, inicio + ventana - 1);
            inicio = Math.max(1, fin - ventana + 1);
            const paginas = [];
            for (let p = inicio; p <= fin; p++) paginas.push(p);
            return paginas;
        },

        irAPagina(n) {
            if (n >= 1 && n <= this.totalPaginas) this.paginaActual = n;
        },

        abrirConsultas() {
            this.openConsultas = true;
            if (this.planes.length === 0) {
                this.cargarPlanes();
            }
        },

        cerrarConsultas() {
            this.openConsultas = false;
            this.selectedCodigo = null;
            this.selectedPlan = null;
            this.selectedAbreviatura = null;
            this.cursos = [];
            this.filtroSistema = "";
            this.filtroArea = "";
            this.filtroCliente = "";
            this.filtroMes = "";
            this.filtroAnio = "";
            this.paginaActual = 1;
        },

        async abrirSeleccionCliente() {
            this.openSeleccionCliente = true;
            this.selectedClientePCA = "";
            if (this.clientesPCA.length === 0) {
                this.loadingClientesPCA = true;
                try {
                    const { data } = await axios.get(
                        `${VITE_URL_APP}/api/get-clientes-pac`,
                    );
                    this.clientesPCA = Array.isArray(data) ? data : [];
                } catch (e) {
                    console.error(e);
                    this.clientesPCA = [];
                } finally {
                    this.loadingClientesPCA = false;
                }
            }
        },

        cerrarSeleccionCliente() {
            this.openSeleccionCliente = false;
            this.selectedClientePCA = "";
        },

        async obtenerPDF_PCA() {
            const codLegacy = this.selectedClientePCA;
            if (!codLegacy) return;

            // COSCO (código 10535): mostrar PDF estático en lugar de generarlo
            if (String(codLegacy).trim() === "10535") {
                const cliente = this.clientesPCA.find(
                    (c) => String(c.cod_legacy) === String(codLegacy),
                );
                const nombreCliente = ((cliente ? cliente.descripcion : "") || "COSCO").toUpperCase();

                this.pdfTitulo = `Plan de Capacitación Aliado | ${nombreCliente}`;
                this.pdfDownloadNombre = "Plan_Capacitacion_Aliado_COSCO.pdf";
                if (this.pdfUrl && this.pdfUrl.startsWith("blob:")) URL.revokeObjectURL(this.pdfUrl);
                this.pdfUrl = `${VITE_URL_APP}/pdfs/PCA_COSCO.pdf`;

                this.openSeleccionCliente = false;
                this.open = true;
                return;
            }

            if (String(codLegacy).trim() === "36628") {
                const cliente = this.clientesPCA.find(
                    (c) => String(c.cod_legacy) === String(codLegacy),
                );
                const nombreCliente = (
                    (cliente ? cliente.descripcion : "") || "MLL2"
                ).toUpperCase();

                this.pdfTitulo = `Plan de Capacitación Aliado | ${nombreCliente}`;
                this.pdfDownloadNombre = "Plan_Capacitacion_Aliado_MLL2.pdf";
                if (this.pdfUrl && this.pdfUrl.startsWith("blob:"))
                    URL.revokeObjectURL(this.pdfUrl);
                this.pdfUrl = `${VITE_URL_APP}/pdfs/PCA_MLL2.pdf`;

                this.openSeleccionCliente = false;
                this.open = true;
                return;
            }

            Swal.fire({
                title: "Generando PDF...",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/obtener-plan-pca-new/${codLegacy}`,
                );

                const cliente = this.clientesPCA.find(
                    (c) => String(c.cod_legacy) === String(codLegacy),
                );
                const nombreCliente = (data.Cliente || (cliente ? cliente.descripcion : "") || "").toUpperCase();

                if (!data.Cursos || data.Cursos.length === 0) {
                    Swal.close();
                    Swal.fire({
                        icon: "warning",
                        title: "Sin cursos",
                        text: "No hay cursos programados para el cliente seleccionado en el año actual.",
                    });
                    return;
                }

                const periodo = `${data.Anio} - ${Number(data.Anio) + 1}`;

                this.pdfTitulo =
                    `Plan de Capacitación Aliado | ${nombreCliente} | ${periodo}`;
                this.pdfDownloadNombre =
                    "Plan_Capacitacion_Aliado_" +
                    (nombreCliente ? nombreCliente.replace(/\s+/g, "_") + "_" : "") +
                    data.Anio +
                    ".pdf";

                const doc = this.generarDocEstiloPCE(
                    data,
                    `PLAN DE CAPACITACIÓN ALIADO | ${nombreCliente} | ${periodo}`,
                );

                const blob = doc.output("blob");
                if (this.pdfUrl && this.pdfUrl.startsWith("blob:")) URL.revokeObjectURL(this.pdfUrl);
                this.pdfUrl = URL.createObjectURL(blob);

                Swal.close();
                this.openSeleccionCliente = false;
                this.open = true;
            } catch (e) {
                Swal.close();
                console.error(e);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No se pudo generar el PDF.",
                });
            }
        },

        async cargarPlanes() {
            this.loadingPlanes = true;
            try {
                const { data } = await axios.get(
                    `${VITE_URL_APP}/api/obtener-tipos-curso-new`,
                );
                this.planes = data.Tipos || [];
            } catch (e) {
                console.error(e);
                this.planes = [];
            } finally {
                this.loadingPlanes = false;
            }
        },

        async seleccionarPlan(plan) {
            this.selectedCodigo = plan.Codigo;
            this.selectedPlan = plan.Nombre;
            this.selectedAbreviatura = plan.Abreviatura;
            this.filtroSistema = "";
            this.filtroArea = "";
            this.filtroCliente = "";
            this.filtroMes = "";
            this.filtroAnio = "";
            this.paginaActual = 1;
            this.loadingCursos = true;
            this.cursos = [];
            try {
                const { data } = await axios.get(`${VITE_URL_APP}/api/obtener-cursos-por-plan-new/${plan.Codigo}`);
                this.cursos = data.Cursos || [];
            } catch (e) {
                console.error(e);
                this.cursos = [];
            } finally {
                this.loadingCursos = false;
            }
        },

        cerrar() {
            this.open = false;
            if (this.pdfUrl && this.pdfUrl.startsWith("blob:")) {
                URL.revokeObjectURL(this.pdfUrl);
            }
            this.pdfUrl = "";
        },
    }));
});
