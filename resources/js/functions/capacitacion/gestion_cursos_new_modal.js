import Swal from "sweetalert2";
import axios from "axios";
import imageCompression from "browser-image-compression";

/**
 * Modal Nuevo — Registrar curso (vista gestion_cursos_new).
 * Componente Alpine independiente: window.formCursoNew()
 * Mantiene los mismos endpoints que gestion_cursos.js:
 *  - GET get-capacitacion-tipo-cursos (planes)
 *  - GET obtener-capacitacion-sistemas (sistemas de gestión)
 *  - GET obtener-areas-por-sistema/{id} / obtener-areas (áreas responsables)
 *  - GET get-areas-encargadas / get-clientes-pac / obtener-sucursales / listar-jefaturas
 *  - POST capacitacion/procesar-examen-word (Word -> preguntas)
 *  - POST guardar-curso-new (alta en sw_cursos_new)
 *  - POST cursos/subir-av (crear curso en Moodle, imágenes y examen)
 * Al abrir el modal se precargan sucursales, áreas, planes, dirigido a, etc.
 */

window.abrirModalRegistroNew = () => {
    const el = document.querySelector('#modalRegistroCursoNew [x-data^="formCursoNew"]')
        || document.getElementById("formCursoNewRoot");
    if (el && window.Alpine) {
        try {
            const data = window.Alpine.$data(el);
            if (data && typeof data.abrir === "function") {
                data.abrir();
                return;
            }
        } catch (e) {
            console.warn("No se pudo obtener Alpine data, se abre por evento", e);
        }
    }
    window.dispatchEvent(new CustomEvent("open-modal-registro-new"));
};

window.cerrarModalRegistroNew = () => {
    window.dispatchEvent(new CustomEvent("close-modal-registro-new"));
};

// ── Apertura de curso (vista nueva) ──
// Endpoints: GET obtener-curso-new/{cod} (datos + regla), POST cursos/aperturar-curso.
// Agrega feedback de vigencia/renovación.
window.abrirModalAperturaNew = (cursCod) => {
    if (cursCod) window.__pendingAperturaNew = String(cursCod);
    const el = document.getElementById("formAperturaNewRoot");
    if (el && window.Alpine) {
        try {
            const data = window.Alpine.$data(el);
            if (data && typeof data.abrir === "function") {
                data.abrir(cursCod);
                return;
            }
        } catch (e) {
            console.warn("Apertura: no se pudo usar Alpine.$data, uso evento", e);
        }
    }
    window.dispatchEvent(new CustomEvent("open-modal-apertura-new", { detail: { codigo: cursCod } }));
};

window.cerrarModalAperturaNew = () => {
    window.dispatchEvent(new CustomEvent("close-modal-apertura-new"));
};

// ── Extender plazo (misma lógica que modalApertura/modalAplazarCurso original) ──
// Endpoints: GET obtener-curso-new/{cod} (PK + programación VIGENTE),
// POST cursos/aplazar-curso-new (actualiza fecha fin del curso y de matriculados, incl. Moodle).
window.abrirModalAplazarNew = (cursCod) => {
    if (cursCod) window.__pendingAplazarNew = String(cursCod);
    const el = document.getElementById("formAplazarNewRoot");
    if (el && window.Alpine) {
        try {
            const data = window.Alpine.$data(el);
            if (data && typeof data.abrir === "function") {
                data.abrir(cursCod);
                return;
            }
        } catch (e) {
            console.warn("Aplazar: no se pudo usar Alpine.$data, uso evento", e);
        }
    }
    window.dispatchEvent(new CustomEvent("open-modal-aplazar-new", { detail: { codigo: cursCod } }));
};

window.cerrarModalAplazarNew = () => {
    window.dispatchEvent(new CustomEvent("close-modal-aplazar-new"));
};

window.formAplazarNew = function () {
    return {
        showModal: false,
        cargando: false,
        guardando: false,
        error: "",
        errorFecha: "",
        codigoPk: "",
        cursoNombre: "",
        programacion: null,
        fechaNuevaFin: "",
        fechaValida: false,

        get fechaMinima() {
            if (!this.programacion?.fecha_final) return "";
            return String(this.programacion.fecha_final).slice(0, 10);
        },
        fmtFecha(v) {
            if (!v) return "—";
            const p = String(v).slice(0, 10).split("-");
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : v;
        },
        get extensionDias() {
            if (!this.programacion?.fecha_final || !this.fechaNuevaFin) return 0;
            const d = Math.round((new Date(this.fechaNuevaFin) - new Date(String(this.programacion.fecha_final).slice(0, 10))) / 86400000);
            return d > 0 ? d : 0;
        },

        validarFecha() {
            this.errorFecha = "";
            this.fechaValida = false;
            if (!this.fechaNuevaFin || !this.programacion) return;
            if (this.extensionDias <= 0) {
                this.errorFecha = "La nueva fecha de fin debe ser posterior a la fecha actual de fin.";
                return;
            }
            this.fechaValida = true;
        },

        async abrir(cursCod) {
            const cod = cursCod || window.__pendingAplazarNew;
            if (!cod) return;
            window.__pendingAplazarNew = null;
            this.error = "";
            this.errorFecha = "";
            this.fechaNuevaFin = "";
            this.fechaValida = false;
            this.programacion = null;
            this.showModal = true;
            document.body.style.overflow = "hidden";
            this.cargando = true;
            try {
                const res = await axios.get(`${VITE_URL_APP}/api/obtener-curso-new/${cod}`);
                const curso = res?.data?.data;
                if (!res?.data?.success || !curso) throw new Error("No se pudo cargar el curso.");
                const vigente = (curso.CURS_PROGRAMACIONES || []).find(
                    (p) => String(p.estado_periodo || "").toUpperCase() === "VIGENTE"
                );
                if (!vigente) {
                    this.showModal = false;
                    document.body.style.overflow = "";
                    Swal.fire("Sin programación vigente", "Solo se puede extender el plazo de un curso con periodo VIGENTE.", "warning");
                    return;
                }
                this.codigoPk = curso.CURS_PK ? String(curso.CURS_PK) : "";
                this.cursoNombre = curso.CURS_NOMBRE || "";
                this.programacion = vigente;
            } catch (e) {
                console.error("Error abriendo extender plazo:", e);
                this.error = "No se pudo cargar la programación del curso.";
            } finally {
                this.cargando = false;
            }
        },
        cerrar() {
            this.showModal = false;
            document.body.style.overflow = "";
            window.dispatchEvent(new CustomEvent("close-modal-aplazar-new"));
        },

        async guardarExtension() {
            this.validarFecha();
            if (!this.fechaValida || !this.fechaNuevaFin || this.guardando) return;
            this.guardando = true;
            try {
                const res = await axios.post(`${VITE_URL_APP}/api/cursos/aplazar-curso-new`, {
                    cod_curso: this.codigoPk,
                    nueva_fecha_final: this.fechaNuevaFin,
                });
                if (res.data?.success) {
                    this.cerrar();
                    Swal.fire({
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        icon: "success",
                        title: res.data.message || "Plazo extendido correctamente",
                    });
                    if (window.tablaCursosNew) {
                        try {
                            await window.tablaCursosNew.setData(`${VITE_URL_APP}/api/obtener-cursos-new`);
                        } catch {}
                    }
                } else {
                    Swal.fire("No se pudo extender", res.data?.message || "Error al procesar la solicitud.", "error");
                }
            } catch (err) {
                console.error("Error al extender plazo:", err);
                Swal.fire("Error de Servidor", err.response?.data?.message || "Ocurrió un problema de conectividad con el servidor.", "error");
            } finally {
                this.guardando = false;
            }
        },

        init() {
            window.addEventListener("open-modal-aplazar-new", (e) => {
                const c = e.detail?.codigo || window.__pendingAplazarNew;
                if (c) this.abrir(c);
            });
            window.addEventListener("close-modal-aplazar-new", () => {
                this.showModal = false;
                document.body.style.overflow = "";
            });
            if (window.__pendingAplazarNew && !this.showModal) {
                const c = window.__pendingAplazarNew;
                window.__pendingAplazarNew = null;
                this.abrir(c);
            }
            this.$watch("fechaNuevaFin", () => this.validarFecha());
        },
    };
};

window.formAperturaNew = function () {
    return {
        showModal: false,
        cargando: false,
        guardando: false,
        error: "",
        codigoPk: "",
        codigoLocal: "",
        cursoNombre: "",
        planNombre: "",
        tipoCursoId: "",
        dirigidoA: "",
        dirigidoNombre: "",
        sucursalCodigo: "",
        sucursalNombre: "",
        frecuencia: "",
        esPeriodico: true,
        fechaInicio: "",
        fechaFin: "",
        incluirAutomatico: true,
        matricularAutomaticamente: true,
        notificarCorreo: false,
        correoNotificacion: "",

        get fechaMinima() {
            const t = new Date();
            return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, "0")}-${String(t.getDate()).padStart(2, "0")}`;
        },
        get esDirigidoOtros() {
            return String(this.dirigidoA) === "OTROS" || String(this.dirigidoA) === "0";
        },
        get dirigidoLabel() {
            if (this.dirigidoNombre) return this.dirigidoNombre.toLowerCase();
            const labels = { 1: "todo el personal", 2: "personal administrativo", 3: "personal operativo" };
            return labels[String(this.dirigidoA)] || "";
        },
        get sucursalLabel() {
            if (String(this.sucursalCodigo).toUpperCase() === "TODAS" || !this.sucursalCodigo) return "todas las sucursales";
            return `la sucursal ${this.sucursalNombre || this.sucursalCodigo}`;
        },
        get requiereFechaFin() {
            return !this.esPeriodico || String(this.frecuencia).toUpperCase() === "PERSONALIZADO";
        },
        // ── Feedback de vigencia (réplica del cálculo del backend) ──
        fechaFinEstimada() {
            if (!this.fechaInicio) return "";
            if (this.requiereFechaFin) return this.fechaFin || "";
            const [y, m, d] = this.fechaInicio.split("-").map(Number);
            const fin = new Date(y, m - 1, d);
            const f = String(this.frecuencia).toUpperCase();
            if (f === "ANUAL") fin.setFullYear(fin.getFullYear() + 1);
            else if (f === "SEMESTRAL") fin.setMonth(fin.getMonth() + 6);
            else if (f === "CUATRIMESTRAL") fin.setMonth(fin.getMonth() + 4);
            else if (f === "TRIMESTRAL") fin.setMonth(fin.getMonth() + 3);
            else if (f === "BIMESTRAL") fin.setMonth(fin.getMonth() + 2);
            else fin.setMonth(fin.getMonth() + 1); // MENSUAL y resto
            return `${fin.getFullYear()}-${String(fin.getMonth() + 1).padStart(2, "0")}-${String(fin.getDate()).padStart(2, "0")}`;
        },
        fmtFecha(v) {
            if (!v) return "—";
            const p = String(v).slice(0, 10).split("-");
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : v;
        },
        get diasDuracion() {
            const fin = this.fechaFinEstimada();
            if (!this.fechaInicio || !fin) return 0;
            return Math.round((new Date(fin) - new Date(this.fechaInicio)) / 86400000);
        },
        get fechaRenovacion() {
            const fin = this.fechaFinEstimada();
            if (!fin) return "";
            const d = new Date(fin);
            d.setDate(d.getDate() + 1);
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
        },
        get renovacionAutomatica() {
            return this.esPeriodico && String(this.frecuencia).toUpperCase() !== "PERSONALIZADO";
        },
        get fechasValidas() {
            if (!this.fechaInicio) return false;
            if (this.requiereFechaFin) {
                if (!this.fechaFin || this.fechaInicio > this.fechaFin) return false;
            }
            return true;
        },

        async abrir(cursCod) {
            const cod = cursCod || window.__pendingAperturaNew;
            if (!cod) return;
            window.__pendingAperturaNew = null;
            this.error = "";
            this.showModal = true;
            document.body.style.overflow = "hidden";
            this.cargando = true;
            try {
                const [cursoRes, sucRes] = await Promise.all([
                    axios.get(`${VITE_URL_APP}/api/obtener-curso-new/${cod}`),
                    axios.get(`${VITE_URL_APP}/api/obtener-sucursales`).catch(() => null),
                ]);
                const curso = cursoRes?.data?.data;
                if (!cursoRes?.data?.success || !curso) throw new Error("No se pudo cargar el curso.");
                this.codigoPk = curso.CURS_PK ? String(curso.CURS_PK) : "";
                this.codigoLocal = curso.CURS_COD || String(cod);
                this.cursoNombre = curso.CURS_NOMBRE || "";
                this.planNombre = curso.CURS_PLAN_CAPAC_NOMBRE || "—";
                this.tipoCursoId = curso.CURS_PLAN_COD ? String(curso.CURS_PLAN_COD) : "";
                this.dirigidoA = curso.CURS_DIRIGIDO_A != null ? String(curso.CURS_DIRIGIDO_A) : "";
                this.dirigidoNombre = curso.CURS_DIRIGIDO_NOMBRE || "";
                this.sucursalCodigo = curso.CURS_SUCURSAL != null ? String(curso.CURS_SUCURSAL) : "";
                this.sucursalNombre = "";
                if (this.sucursalCodigo && this.sucursalCodigo.toUpperCase() !== "TODAS" && sucRes?.data?.success) {
                    const match = (sucRes.data.data || []).find((s) => String(s.Codigo) === this.sucursalCodigo);
                    if (match) this.sucursalNombre = match.Sucursal || "";
                }
                this.frecuencia = curso.CURS_FRECUENCIA || "";
                this.esPeriodico = curso.CURS_ES_PERIODICO !== false;
                this.fechaInicio = this.fechaMinima;
                this.fechaFin = "";
                // Por defecto matricular; si es OTROS siempre será manual
                this.matricularAutomaticamente = true;
                this.incluirAutomatico = true;
                this.notificarCorreo = false;
                this.correoNotificacion = "";
            } catch (e) {
                console.error("Error abriendo apertura:", e);
                this.error = "No se pudo cargar la información del curso.";
            } finally {
                this.cargando = false;
            }
        },
        cerrar() {
            this.showModal = false;
            document.body.style.overflow = "";
            window.dispatchEvent(new CustomEvent("close-modal-apertura-new"));
        },

        async guardarApertura() {
            // Mismas validaciones que el modal original
            if (!this.esPeriodico) {
                if (!this.fechaInicio || !this.fechaFin) {
                    Swal.fire("Atención", "Debe seleccionar fecha de inicio y fecha de fin.", "warning");
                    return;
                }
                if (this.fechaInicio > this.fechaFin) {
                    Swal.fire("Atención", "La fecha de inicio no puede ser mayor a la fecha de fin.", "warning");
                    return;
                }
            } else if (!this.fechaInicio) {
                Swal.fire("Atención", "Debe seleccionar una fecha de inicio.", "warning");
                return;
            }
            if (this.requiereFechaFin && String(this.frecuencia).toUpperCase() === "PERSONALIZADO" && !this.fechaFin) {
                Swal.fire("Atención", "Con frecuencia personalizada debe indicar la fecha de fin.", "warning");
                return;
            }
            const matricular = this.esDirigidoOtros ? false : !!this.matricularAutomaticamente;
            const correoNotificacion = String(this.correoNotificacion || "").trim();
            if (matricular && this.notificarCorreo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correoNotificacion)) {
                Swal.fire("Atención", "Ingresa un correo válido para enviar el aviso.", "warning");
                return;
            }
            // Apertura con opción de matrícula automática (switch del modal)
            this.guardando = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
                const headers = { "Content-Type": "application/json" };
                if (csrf) headers["X-CSRF-TOKEN"] = csrf;
                const payload = {
                    cod_curso: this.codigoPk,
                    fecha_inicio: this.fechaInicio,
                    incluir_automatico: matricular,
                    matricular_automatico: matricular,
                    notificar_email: this.notificarCorreo,
                    correo_notificacion: matricular && this.notificarCorreo ? correoNotificacion : null,
                };
                if (this.requiereFechaFin) payload.fecha_final = this.fechaFin;
                const res = await axios.post(`${VITE_URL_APP}/api/cursos/aperturar-curso`, payload, { headers });
                if (res.data?.success) {
                    this.cerrar();
                    Swal.fire({
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true,
                        icon: "success",
                        title: (res.data.message || "Curso aperturado correctamente") + (matricular && this.notificarCorreo ? ` Se enviará el resumen a ${correoNotificacion} al finalizar.` : ""),
                    });
                    if (window.tablaCursosNew) {
                        try {
                            await window.tablaCursosNew.setData(`${VITE_URL_APP}/api/obtener-cursos-new`);
                        } catch {}
                    }
                } else {
                    Swal.fire("No se pudo aperturar", res.data?.message || "Error al procesar la solicitud.", "error");
                }
            } catch (err) {
                console.error("Error aperturando curso:", err);
                Swal.fire("Error de Servidor", err.response?.data?.message || "Ocurrió un problema de conectividad con el servidor.", "error");
            } finally {
                this.guardando = false;
            }
        },

        init() {
            window.addEventListener("open-modal-apertura-new", (e) => {
                const c = e.detail?.codigo || window.__pendingAperturaNew;
                if (c) this.abrir(c);
            });
            window.addEventListener("close-modal-apertura-new", () => {
                this.showModal = false;
                document.body.style.overflow = "";
            });
            if (window.__pendingAperturaNew && !this.showModal) {
                const c = window.__pendingAperturaNew;
                window.__pendingAperturaNew = null;
                this.abrir(c);
            }
        },
    };
};

window.formCursoNew = function () {
    return {
        // ── Modal / pasos ──
        showModal: false,
        paso: 1,
        totalPasos: 2,
        cargandoCombos: false,
        combosError: "",
        puntosCarga: 3,
        intervaloCarga: null,

        // ── Paso 1: datos generales ──
        nombre: "",
        descripcion: "",
        categoria: "",
        codResponsable: "",
        personalJefaturas: [],
        esPeriodico: false,
        frecuencia: "",

        // ── Paso 2: planificación ──
        planes: [],
        tipoCurso: "5",
        esPAC: false,
        clientesDisponibles: [],
        clienteSeleccionado: "",
        busquedaCliente: "",
        areasEncargadas: [],
        areasAsignadas: [],
        busquedaAreaPCI: "",
        sucursalesDisponibles: [],
        sucursalesAsignadas: [],
        busquedaSucursal: "",
        sucursalesOpciones: [],
        sistemas: [],
        areaConocimiento: "",
        area: "",
        areasResponsables: [],
        areaResponsable: "",
        codMoodleArea: "",
        sucursal: "",
        dirigido: "",
        lastSistemaId: null,

        // ── Evaluación + recursos (fase 2) ──
        aplicaEvaluacion: false,
        limiteTiempo: "30",
        nota: "10",
        intentos: "1",
        cantidadPreguntas: "1",
        preguntasBalotario: "1",
        archivoWord: null,
        archivoWordNombre: "",
        cargandoWord: false,
        preguntasExamen: [],
        wordMetrics: { tokensInput: 0, tokensOutput: 0, tokensTotal: 0, costoUSD: 0, tiempoSeg: 0 },
        imageFilePortada: null,
        imagePreviewPortada: null,
        imageFileAfiche: null,
        imagePreviewAfiche: null,
        modalPreviewAbierto: false,
        modalPreviewSrc: "",
        modalPreviewTitulo: "",

        // ── Computed ──
        get dirigidosBase() {
            const raw = window.dirigidosNew || [];
            return Array.isArray(raw) ? raw : [];
        },
        get opcionesDirigido() {
            // Misma regla que el modal original:
            // tipo 5 -> lista fija / tipo 6 (PCU) -> según cliente / resto -> catálogo completo
            if (this.tipoCurso == "5") {
                return [
                    { codigo: "1", texto: "Todos" },
                    { codigo: "7", texto: "Personal Administrativo (Todos)" },
                    { codigo: "8", texto: "Personal Administrativo (4°)" },
                    { codigo: "9", texto: "Personal Administrativo (5°)" },
                    { codigo: "10", texto: "Personal Operativo (Todos)" },
                    { codigo: "11", texto: "Personal Operativo (4°)" },
                    { codigo: "12", texto: "Personal Operativo (5°)" },
                    { codigo: "OTROS", texto: "Otros" },
                ];
            }
            if (this.tipoCurso == "6") {
                const cliente = (this.clientesDisponibles || []).find((c) => String(c.codigo) === String(this.clienteSeleccionado));
                const desc = String(cliente?.descripcion || "").toLowerCase();
                if (desc.includes("linea 2") || desc.includes("línea 2")) {
                    return this.dirigidosBase.filter((o) => ["Todos", "PTSA", "Estaciones"].includes(o.texto));
                }
                if (this.clienteSeleccionado) {
                    const base = this.dirigidosBase.filter((o) => o.texto === "Todos");
                    return [...base, { codigo: "OTROS", texto: "Otros" }];
                }
            }
            return this.dirigidosBase;
        },
        get clientesFiltrados() {
            if (!this.busquedaCliente) return this.clientesDisponibles;
            const s = this.busquedaCliente.toLowerCase();
            return this.clientesDisponibles.filter(
                (c) => String(c.descripcion || "").toLowerCase().includes(s) || String(c.codigo || "").toLowerCase().includes(s)
            );
        },
        get sucursalesFiltradas() {
            if (!this.busquedaSucursal) return this.sucursalesDisponibles;
            const s = this.busquedaSucursal.toLowerCase();
            return this.sucursalesDisponibles.filter((x) => String(x).toLowerCase().includes(s));
        },
        get areasPCIFiltradas() {
            if (!this.busquedaAreaPCI) return this.areasEncargadas;
            const s = this.busquedaAreaPCI.toLowerCase();
            return this.areasEncargadas.filter(
                (a) => String(a.descripcion || "").toLowerCase().includes(s) || String(a.codigo || "").toLowerCase().includes(s)
            );
        },
        get puedeAvanzarPaso1() {
            const base = this.nombre.trim().length > 0 && !!this.categoria && !!this.codResponsable;
            if (!base) return false;
            // Si es periódico, la frecuencia es obligatoria (incluye PERSONALIZADO sin fechas extra:
            // las fechas se definen al aperturar el curso).
            if (this.esPeriodico && !this.frecuencia) return false;
            return true;
        },
        get puedeAvanzarPaso2() {
            if (!this.tipoCurso || !this.areaResponsable || !this.sucursal || !this.dirigido) return false;
            if (this.tipoCurso != "6" && !this.areaConocimiento) return false;
            if (this.tipoCurso == "6" && !this.clienteSeleccionado) return false;
            return true;
        },
        get formularioCompleto() {
            return this.puedeAvanzarPaso1 && this.puedeAvanzarPaso2;
        },
        get tituloCamposFaltantes() {
            const f = [];
            if (!this.nombre.trim()) f.push("Nombre");
            if (!this.categoria) f.push("Tipo de curso");
            if (!this.codResponsable) f.push("Responsable");
            if (!this.tipoCurso) f.push("Plan de capacitación");
            if (this.tipoCurso != "6" && !this.areaConocimiento) f.push("Sistema de gestión");
            if (!this.areaResponsable) f.push("Área responsable");
            if (!this.sucursal) f.push("Sucursal");
            if (!this.dirigido) f.push("Dirigido a");
            return f.length ? "Faltan: " + f.join(", ") : "Completa los campos requeridos";
        },

        // ── Apertura / cierre ──
        get textoCarga() {
            return "Cargando información" + ".".repeat(this.puntosCarga);
        },
        iniciarTextoCarga() {
            this.puntosCarga = 3;
            if (this.intervaloCarga) clearInterval(this.intervaloCarga);
            this.intervaloCarga = setInterval(() => {
                this.puntosCarga = this.puntosCarga <= 1 ? 3 : this.puntosCarga - 1;
            }, 400);
        },
        detenerTextoCarga() {
            if (this.intervaloCarga) {
                clearInterval(this.intervaloCarga);
                this.intervaloCarga = null;
            }
        },
        async abrir() {
            this.limpiarCampos();
            this.paso = 1;
            this.showModal = true;
            document.body.style.overflow = "hidden";
            this.iniciarTextoCarga();
            await this.cargarCombosIniciales();
            this.detenerTextoCarga();
        },
        cerrar() {
            this.detenerTextoCarga();
            this.showModal = false;
            document.body.style.overflow = "";
            window.dispatchEvent(new CustomEvent("close-modal-registro-new"));
        },
        siguiente() {
            if (this.paso < this.totalPasos) this.paso++;
        },
        anterior() {
            if (this.paso > 1) this.paso--;
        },
        irPaso(n) {
            // Solo permite retroceder o avanzar si el paso actual es válido
            if (n < this.paso) this.paso = n;
            else if (n === 2 && this.puedeAvanzarPaso1) this.paso = 2;
        },

        // ── Combos (se cargan al abrir) ──
        async cargarCombosIniciales() {
            this.cargandoCombos = true;
            this.combosError = "";
            try {
                const [planesRes, sistemasRes, jefRes, sucRes, cliRes, areasEncRes] = await Promise.all([
                    axios.get(`${VITE_URL_APP}/api/get-capacitacion-tipo-cursos`).catch(() => null),
                    axios.get(`${VITE_URL_APP}/api/obtener-capacitacion-sistemas`).catch(() => null),
                    axios.get(`${VITE_URL_APP}/api/listar-jefaturas`).catch(() => null),
                    axios.get(`${VITE_URL_APP}/api/obtener-sucursales`).catch(() => null),
                    axios.get(`${VITE_URL_APP}/api/get-clientes-pac`).catch(() => null),
                    axios.get(`${VITE_URL_APP}/api/get-areas-encargadas`).catch(() => null),
                ]);
                if (planesRes?.data) this.planes = Array.isArray(planesRes.data) ? planesRes.data : [];
                if (sistemasRes?.data) {
                    const arr = Array.isArray(sistemasRes.data) ? sistemasRes.data : [];
                    this.sistemas = arr.map((a) => ({ codigo: a.codigo, descripcion: a.abreviatura || a.descripcion }));
                }
                if (jefRes?.data) this.personalJefaturas = jefRes.data.personal || [];
                if (sucRes?.data?.success) {
                    this.sucursalesOpciones = sucRes.data.data || [];
                    this.sucursalesDisponibles = (sucRes.data.data || []).map((s) => s.Sucursal);
                }
                if (cliRes?.data) this.clientesDisponibles = cliRes.data || [];
                if (areasEncRes?.data) this.areasEncargadas = areasEncRes.data || [];
            } catch (e) {
                console.error("Error cargando combos del modal nuevo:", e);
                this.combosError = "No se pudieron cargar algunos catálogos. Intenta cerrar y reabrir.";
            } finally {
                this.cargandoCombos = false;
            }
        },
        async cargarAreasResponsables(sistemaId) {
            if (!sistemaId) {
                this.areasResponsables = [];
                this.areaResponsable = "";
                this.lastSistemaId = null;
                return;
            }
            if (String(sistemaId) === String(this.lastSistemaId)) return;
            // Al cambiar de sistema, se invalida el área elegida hasta cargar la nueva lista
            this.areaResponsable = "";
            this.areasResponsables = [];
            try {
                this.lastSistemaId = sistemaId;
                const res = await axios.get(`${VITE_URL_APP}/api/obtener-areas-por-sistema/${sistemaId}`);
                if (res.data?.success) this.areasResponsables = res.data.areas || [];
            } catch (e) {
                console.error("Error cargando áreas responsables:", e);
                this.lastSistemaId = null;
            }
        },
        async cargarAreasResponsablesPCA() {
            try {
                const res = await axios.get(`${VITE_URL_APP}/api/obtener-areas`);
                if (res.data?.success && Array.isArray(res.data.areas)) this.areasResponsables = res.data.areas;
                this.lastSistemaId = null;
            } catch (e) {
                console.error("Error cargando áreas PCA:", e);
            }
        },
        checkEsPACByText(text) {
            this.esPAC = String(text || "").toUpperCase().includes("PAC");
            if (!this.esPAC) this.sucursalesAsignadas = [];
        },

        // ── Word / imágenes ──
        previewImage(src, titulo) {
            this.modalPreviewSrc = src;
            this.modalPreviewTitulo = titulo;
            this.modalPreviewAbierto = true;
        },
        async handleImageUpload(event, type = "portada") {
            let file = event.target.files?.[0];
            if (!file) return;
            const allowed = ["image/jpeg", "image/jpg", "image/png"];
            if (!allowed.includes(file.type)) {
                Swal.fire("Atención", "Solo se permiten imágenes .jpg, .jpeg o .png", "warning");
                event.target.value = "";
                return;
            }
            if (file.size > 1990 * 1024) {
                await Swal.fire({
                    title: "Imagen pesada",
                    text: "Supera 1.9 MB, se intentará comprimir automáticamente.",
                    icon: "info",
                    confirmButtonText: "Entendido",
                });
                try {
                    file = await imageCompression(file, { maxSizeMB: 1.9, maxWidthOrHeight: 1920, useWebWorker: true });
                } catch {
                    Swal.fire("Error", "No se pudo comprimir la imagen.", "error");
                    event.target.value = "";
                    return;
                }
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                if (type === "portada") {
                    this.imagePreviewPortada = e.target.result;
                    this.imageFilePortada = file;
                } else {
                    this.imagePreviewAfiche = e.target.result;
                    this.imageFileAfiche = file;
                }
            };
            reader.readAsDataURL(file);
        },
        async analizarExamenWord() {
            if (!this.archivoWord) return;
            const ext = this.archivoWord.name.split(".").pop().toLowerCase();
            if (ext === "doc" || ext === "dot") {
                Swal.fire({
                    title: "Formato deprecado",
                    html: "Abre el archivo y <b>guárdalo como .docx</b> antes de subirlo.",
                    icon: "warning",
                    confirmButtonText: "Entendido",
                });
                return;
            }
            this.cargandoWord = true;
            try {
                const fd = new FormData();
                fd.append("archivo", this.archivoWord);
                const res = await axios.post(`${VITE_URL_APP}/api/capacitacion/procesar-examen-word`, fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });
                if (res.data?.success) {
                    this.preguntasExamen = res.data.preguntas || [];
                    if (res.data.metrics) {
                        this.wordMetrics = {
                            tokensInput: res.data.metrics.tokens_input,
                            tokensOutput: res.data.metrics.tokens_output,
                            tokensTotal: res.data.metrics.tokens_total,
                            costoUSD: res.data.metrics.costo_usd,
                            tiempoSeg: res.data.metrics.tiempo_seg,
                        };
                    }
                    const count = this.preguntasExamen.length;
                    this.cantidadPreguntas = String(count);
                    this.preguntasBalotario = String(count);
                    this.verVistaPrevia();
                } else {
                    Swal.fire("Error", res.data?.message || "No se pudo procesar el Word", "error");
                }
            } catch (e) {
                console.error(e);
                Swal.fire("Error", "No se pudo procesar el archivo Word", "error");
            } finally {
                this.cargandoWord = false;
            }
        },
        verVistaPrevia() {
            if (!this.preguntasExamen.length) {
                Swal.fire("Sin preguntas", "Primero analiza un archivo Word.", "info");
                return;
            }
            window.dispatchEvent(
                new CustomEvent("abrir-modal-word-new", {
                    detail: {
                        preguntas: this.preguntasExamen,
                        nombreArc: this.archivoWordNombre,
                        metrics: this.wordMetrics,
                    },
                })
            );
        },

        // ── Guardado (POST guardar-curso-new) ──
        async registrar(e) {
            e?.preventDefault?.();
            if (this.tipoCurso == "6" && !this.clienteSeleccionado) {
                Swal.fire("Atención", "Debe seleccionar un cliente para cursos PCA", "warning");
                return;
            }
            if (!this.codResponsable) {
                Swal.fire("Atención", "Debe seleccionar un responsable", "warning");
                return;
            }
            if (!this.areaResponsable) {
                Swal.fire("Atención", "Debe seleccionar un área responsable", "warning");
                return;
            }
            if (!this.dirigido) {
                Swal.fire("Atención", "Debe seleccionar a quién va dirigido el curso", "warning");
                return;
            }
            if (!this.nombre.trim() || !this.tipoCurso || !this.categoria || !this.sucursal) {
                Swal.fire("Atención", "Completa los campos obligatorios (*)", "warning");
                return;
            }

            const toInt = (v) => {
                const n = parseInt(String(v ?? "").replace(/[^\d-]/g, ""), 10);
                return Number.isFinite(n) ? n : 0;
            };
            const frecuenciaCodigo = {
                MENSUAL: 1,
                BIMESTRAL: 2,
                TRIMESTRAL: 3,
                CUATRIMESTRAL: 4,
                SEMESTRAL: 6,
                ANUAL: 12,
            };
            const cliente = (this.clientesDisponibles || []).find(
                (c) => String(c.codigo) === String(this.clienteSeleccionado)
            );

            const fd = new FormData();
            fd.append("SW_CURS_NOMBRE", this.nombre.trim());
            fd.append("SW_CURS_DESCRIPCION", (this.descripcion || "").trim());
            fd.append("SW_CURS_TIPO_COD", String(toInt(this.categoria)));
            fd.append("SW_CURS_PLAN_CAPAC_COD", String(toInt(this.tipoCurso)));
            fd.append("SW_CURS_SIST_GESTION_COD", String(toInt(this.areaConocimiento)));
            fd.append("SW_CURS_AREA_COD", String(toInt(this.areaResponsable)));
            fd.append("SW_CURS_RESP_COD", String(toInt(this.codResponsable)));
            fd.append("SW_CURS_DIRIGIDO_COD", this.dirigido === "OTROS" ? "0" : String(toInt(this.dirigido)));
            fd.append("SW_CURS_SUCURSAL_COD", this.sucursal === "TODAS" ? "0" : String(toInt(this.sucursal)));
            fd.append("SW_CURS_CANT_PERIOD", this.esPeriodico ? (frecuenciaCodigo[this.frecuencia] || 0) : 0);
            fd.append("SW_CURS_AV_COD", "0");
            fd.append("SW_CURS_PERIODICIDAD", this.esPeriodico ? "1" : "0");
            fd.append("SW_CURS_EVALUACION", "0");
            fd.append("SW_CURS_CLIENTE_COD", cliente?.cod_legacy ? String(cliente.cod_legacy) : "");
            fd.append("habilitado", "1");

            Swal.fire({
                title: "Registrando curso...",
                html: "Por favor espera mientras se procesa la información.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });
            try {
                const res = await axios.post(`${VITE_URL_APP}/api/guardar-curso-new`, fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });
                if ((res.status === 200 || res.status === 201) && res.data?.success) {
                    Swal.fire("Éxito", res.data.message || "Curso registrado correctamente", "success");
                    this.cerrar();
                    if (window.tablaCursosNew) {
                        try {
                            await window.tablaCursosNew.setData(`${VITE_URL_APP}/api/obtener-cursos-new`);
                        } catch {}
                    }
                } else {
                    Swal.fire("Error", res.data?.message || "No se pudo registrar el curso", "error");
                }
            } catch (err) {
                console.error(err);
                if (err.response?.status === 422) {
                    const errors = err.response.data.errors || {};
                    let html = '<ul class="text-left text-sm">';
                    for (const [k, msgs] of Object.entries(errors)) html += `<li><b>${k}</b>: ${msgs[0]}</li>`;
                    html += "</ul>";
                    Swal.fire({ title: "Errores de validación", html, icon: "warning" });
                } else {
                    Swal.fire("Error", "Ocurrió un problema al registrar el curso", "error");
                }
            }
        },

        limpiarCampos() {
            this.paso = 1;
            this.nombre = "";
            this.descripcion = "";
            this.categoria = "";
            this.codResponsable = "";
            this.esPeriodico = false;
            this.frecuencia = "";
            this.tipoCurso = "5";
            this.esPAC = false;
            this.clienteSeleccionado = "";
            this.busquedaCliente = "";
            this.areasAsignadas = [];
            this.busquedaAreaPCI = "";
            this.sucursalesAsignadas = [];
            this.busquedaSucursal = "";
            this.areaConocimiento = "";
            this.area = "";
            this.areaResponsable = "";
            this.codMoodleArea = "";
            this.sucursal = "";
            this.dirigido = "";
            this.aplicaEvaluacion = false;
            this.limiteTiempo = "30";
            this.nota = "10";
            this.intentos = "1";
            this.cantidadPreguntas = "1";
            this.preguntasBalotario = "1";
            this.archivoWord = null;
            this.archivoWordNombre = "";
            this.preguntasExamen = [];
            this.imageFilePortada = null;
            this.imagePreviewPortada = null;
            this.imageFileAfiche = null;
            this.imagePreviewAfiche = null;
        },

        init() {
            // Escucha apertura por evento (fallback si no hay Alpine.$data)
            window.addEventListener("open-modal-registro-new", async () => {
                this.limpiarCampos();
                this.paso = 1;
                this.showModal = true;
                document.body.style.overflow = "hidden";
                this.iniciarTextoCarga();
                await this.cargarCombosIniciales();
                this.detenerTextoCarga();
            });
            window.addEventListener("close-modal-registro-new", () => {
                this.showModal = false;
                document.body.style.overflow = "";
            });
            this.$watch("areaConocimiento", (val) => {
                if (this.tipoCurso != "6") this.cargarAreasResponsables(val);
            });
            this.$watch("tipoCurso", (val) => {
                if (val == "6") {
                    this.areaConocimiento = "";
                    this.area = "";
                    this.cargarAreasResponsablesPCA();
                } else {
                    this.areasResponsables = [];
                    this.areaResponsable = "";
                    this.lastSistemaId = null;
                }
            });
        },
    };
};

// ── Edición (modal simple, solo cursos inactivos) ──
// Regla: VIGENTE o PENDIENTE bloquean. Solo editable si no hay programación
// o todas están CERRADAS. Campos editables: nombre, descripción, tipo,
// responsable, periódico, frecuencia, sistema (PCE) y área responsable.
window.abrirModalEdicionNew = (cod) => {
    if (cod) window.__pendingEdicionNew = String(cod);
    const el = document.getElementById("formCursoEditNewRoot");
    if (el && window.Alpine) {
        try {
            const data = window.Alpine.$data(el);
            if (data && typeof data.abrir === "function") {
                data.abrir(cod);
                return;
            }
        } catch (e) {
            console.warn("Edición: no se pudo usar Alpine.$data, uso evento", e);
        }
    }
    window.dispatchEvent(new CustomEvent("open-modal-edicion-new", { detail: { codigo: cod } }));
};

window.cerrarModalEdicionNew = () => {
    window.dispatchEvent(new CustomEvent("close-modal-edicion-new"));
};

window.formCursoEditNew = function () {
    return {
        showModal: false,
        cargando: false,
        guardando: false,
        error: "",
        codigo: "",
        codigoPk: "",
        planNombre: "—",
        tipoCursoCodigo: "",
        estadoProg: "",
        // editables
        nombre: "",
        descripcion: "",
        categoria: "",
        codResponsable: "",
        personalJefaturas: [],
        esPeriodico: false,
        frecuencia: "",
        sistemas: [],
        areaConocimiento: "",
        areasResponsables: [],
        areaResponsable: "",
        codMoodleArea: "",
        lastSistemaId: null,
        _original: {},

        get esPCU() {
            return String(this.tipoCursoCodigo) === "6";
        },
        get areaBloqueada() {
            return !this.esPCU && !this.areaConocimiento;
        },
        get formularioCompleto() {
            if (!this.nombre.trim() || !this.categoria || !this.codResponsable || !this.areaResponsable) return false;
            if (!this.esPCU && !this.areaConocimiento) return false;
            if (this.esPeriodico && !this.frecuencia) return false;
            return true;
        },
        get hayCambios() {
            const o = this._original || {};
            return ["nombre", "descripcion", "categoria", "codResponsable", "esPeriodico", "frecuencia", "areaConocimiento", "areaResponsable"]
                .some((k) => String(this[k] ?? "") !== String(o[k] ?? ""));
        },
        get tituloBoton() {
            if (!this.formularioCompleto) return "Completa los campos obligatorios (*)";
            if (!this.hayCambios) return "Sin cambios por guardar";
            return "";
        },

        async abrir(cod) {
            // Si otro pedido quedó en cola y este abrir ya lo atiende, limpiar la cola
            if (cod && String(window.__pendingEdicionNew || "") === String(cod)) window.__pendingEdicionNew = null;
            else if (!cod && window.__pendingEdicionNew) cod = window.__pendingEdicionNew;
            if (!cod) return;
            window.__pendingEdicionNew = null;
            this.codigo = cod;
            this.error = "";
            this.showModal = true;
            document.body.style.overflow = "hidden";
            this.cargando = true;
            try {
                const [cursoRes, sistRes, jefRes] = await Promise.all([
                    axios.get(`${VITE_URL_APP}/api/obtener-curso-new/${cod}`),
                    axios.get(`${VITE_URL_APP}/api/obtener-capacitacion-sistemas`).catch(() => null),
                    axios.get(`${VITE_URL_APP}/api/listar-jefaturas`).catch(() => null),
                ]);
                const curso = cursoRes?.data?.data;
                if (!cursoRes?.data?.success || !curso) {
                    throw new Error("No se pudo cargar el curso.");
                }
                // ── Regla de negocio: bloquear VIGENTE / PENDIENTE ──
                const progs = curso.CURS_PROGRAMACIONES || [];
                const hasVigente = curso.CURS_TIENE_VIGENTE === true;
                const hasPendiente = curso.CURS_TIENE_PENDIENTE === true;
                if (hasVigente || hasPendiente) {
                    this.showModal = false;
                    document.body.style.overflow = "";
                    Swal.fire(
                        "No se puede editar",
                        hasVigente
                            ? "El curso tiene una programación VIGENTE (aperturado). Solo se editan cursos inactivos."
                            : "El curso tiene una programación PENDIENTE. Solo se editan cursos inactivos (solo CERRADOS o sin programación).",
                        "warning"
                    );
                    return;
                }
                this.estadoProg = progs.length === 0 ? "Sin programación" : "Solo programaciones cerradas";

                // ── Combos ──
                if (sistRes?.data) {
                    const arr = Array.isArray(sistRes.data) ? sistRes.data : [];
                    this.sistemas = arr.map((a) => ({ codigo: a.codigo, descripcion: a.abreviatura || a.descripcion }));
                }
                if (jefRes?.data) this.personalJefaturas = jefRes.data.personal || [];

                // ── Datos del curso (claves CURS_ de la API nueva) ──
                this.codigoPk = curso.CURS_PK ? String(curso.CURS_PK) : "";
                this.planNombre = curso.CURS_PLAN_CAPAC_NOMBRE || "—";
                this.tipoCursoCodigo = curso.CURS_PLAN_COD ? String(curso.CURS_PLAN_COD) : "";
                this.nombre = curso.CURS_NOMBRE || "";
                this.descripcion = curso.CURS_DESCRIPCION || "";
                this.categoria = curso.CURS_CATEGORIA != null ? String(curso.CURS_CATEGORIA) : "";
                this.codResponsable = curso.CURS_COD_RESPONSABLE ? String(curso.CURS_COD_RESPONSABLE) : "";
                this.esPeriodico = curso.CURS_ES_PERIODICO === true;
                this.frecuencia = curso.CURS_FRECUENCIA || "";
                this.areaConocimiento = curso.CURS_SIST_GESTION_COD ? String(curso.CURS_SIST_GESTION_COD) : "";
                this.codMoodleArea = curso.CURS_COD_MOODLE_AREA || "";

                // Áreas responsables según plan
                if (this.esPCU) {
                    try {
                        const r = await axios.get(`${VITE_URL_APP}/api/obtener-areas`);
                        if (r.data?.success) this.areasResponsables = r.data.areas || [];
                    } catch {}
                    this.lastSistemaId = null;
                } else if (this.areaConocimiento) {
                    await this.cargarAreasResponsables(this.areaConocimiento, true);
                } else {
                    this.areasResponsables = [];
                }
                this.areaResponsable = curso.CURS_AREA_RESPONSABLE != null ? String(curso.CURS_AREA_RESPONSABLE) : "";

                this._original = {
                    nombre: this.nombre,
                    descripcion: this.descripcion,
                    categoria: this.categoria,
                    codResponsable: this.codResponsable,
                    esPeriodico: this.esPeriodico,
                    frecuencia: this.frecuencia,
                    areaConocimiento: this.areaConocimiento,
                    areaResponsable: this.areaResponsable,
                    codMoodleArea: this.codMoodleArea
                };
            } catch (e) {
                console.error("Error abriendo edición:", e);
                this.error = "No se pudo cargar la información del curso.";
            } finally {
                this.cargando = false;
            }
        },
        cerrar() {
            this.showModal = false;
            document.body.style.overflow = "";
            window.dispatchEvent(new CustomEvent("close-modal-edicion-new"));
        },
        async cargarAreasResponsables(sistemaId, forzar = false) {
            if (!sistemaId) {
                this.areasResponsables = [];
                this.areaResponsable = "";
                this.lastSistemaId = null;
                return;
            }
            if (!forzar && String(sistemaId) === String(this.lastSistemaId)) return;
            this.areaResponsable = "";
            this.areasResponsables = [];
            try {
                this.lastSistemaId = sistemaId;
                const res = await axios.get(`${VITE_URL_APP}/api/obtener-areas-por-sistema/${sistemaId}`);
                if (res.data?.success) this.areasResponsables = res.data.areas || [];
            } catch (e) {
                console.error("Error cargando áreas responsables:", e);
                this.lastSistemaId = null;
            }
        },
        async guardar(e) {
            e?.preventDefault?.();
            if (!this.formularioCompleto || !this.hayCambios || this.guardando) return;
            const o = this._original || {};
            const changed = (k) => String(this[k] ?? "") !== String(o[k] ?? "");
            const fd = new FormData();

            fd.append("SW_CURS_COD", String(this.codigo));

            const toInt = (v) => {
                const n = parseInt(String(v ?? "").replace(/[^\d-]/g, ""), 10);
                return Number.isFinite(n) ? n : 0;
            };

            if (changed("nombre"))         fd.append("SW_CURS_NOMBRE", this.nombre);
            if (changed("descripcion"))    fd.append("SW_CURS_DESCRIPCION", this.descripcion || "");
            if (changed("categoria"))      fd.append("SW_CURS_TIPO_COD", String(this.categoria));
            if (changed("codResponsable")) fd.append("SW_CURS_RESP_COD", String(this.codResponsable));
            if (changed("areaConocimiento") && !this.esPCU) fd.append("SW_CURS_SIST_GESTION_COD", String(this.areaConocimiento));
            if (changed("areaResponsable")) fd.append("SW_CURS_AREA_COD", String(this.areaResponsable));
            if (changed("codMoodleArea") && this.codMoodleArea) fd.append("cod_moodle_area", this.codMoodleArea);
            if (changed("esPeriodico")) fd.append("SW_CURS_PERIODICIDAD", this.esPeriodico ? 1 : 0);
            if (changed("frecuencia")) fd.append("SW_CURS_CANT_PERIOD", toInt(this.frecuencia));
            
            this.guardando = true;
            try {
                const res = await axios.post(`${VITE_URL_APP}/api/actualizar-curso-new`, fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });
                if (res.data?.success) {
                    Swal.fire("Éxito", res.data.message || "Curso actualizado correctamente", "success");
                    this.cerrar();
                    if (window.tablaCursosNew) {
                        try {
                            await window.tablaCursosNew.setData(`${VITE_URL_APP}/api/obtener-cursos-new`);
                        } catch {}
                    }
                } else {
                    Swal.fire("Error", res.data?.message || "No se pudo actualizar el curso", "error");
                }
            } catch (err) {
                console.error(err);
                Swal.fire("Error", "Ocurrió un problema al actualizar el curso", "error");
            } finally {
                this.guardando = false;
            }
        },
        init() {
            window.addEventListener("open-modal-edicion-new", (e) => {
                const c = e.detail?.codigo || window.__pendingEdicionNew;
                if (c) this.abrir(c);
            });
            window.addEventListener("close-modal-edicion-new", () => {
                this.showModal = false;
                document.body.style.overflow = "";
            });
            // Si el pedido llegó antes de que Alpine inicializara, atenderlo ahora
            if (window.__pendingEdicionNew && !this.showModal) {
                const c = window.__pendingEdicionNew;
                window.__pendingEdicionNew = null;
                this.abrir(c);
            }
            this.$watch("areaConocimiento", (val) => {
                if (!this.esPCU && this.showModal && !this.cargando) this.cargarAreasResponsables(val);
            });
        },
    };
};

// ── Subir curso al AV (recursos visuales + evaluación) ──
window.formSubirAvNew = function () {
    return {
        showModal: false,
        cargando: false,
        guardando: false,
        error: "",
        codigo: "",
        cursoCodigo: "",
        cursoNombre: "",
        planNombre: "",
        areaNombre: "",
        responsableNombre: "",

        // Evaluación (vacía por defecto)
        aplicaEvaluacion: false,
        limiteTiempo: "",
        nota: "",
        intentos: "",
        cantidadPreguntas: "",
        preguntasBalotario: "",

        // Banco de preguntas
        archivoWord: null,
        archivoWordNombre: "",
        cargandoWord: false,
        preguntasExamen: [],
        wordMetrics: { tokensInput: 0, tokensOutput: 0, tokensTotal: 0, costoUSD: 0, tiempoSeg: 0 },

        // Recursos visuales
        imageFilePortada: null,
        imagePreviewPortada: null,
        imageFileAfiche: null,
        imagePreviewAfiche: null,
        modalPreviewAbierto: false,
        modalPreviewSrc: "",
        modalPreviewTitulo: "",

        get puedeSubir() {
            if (this.guardando || !this.cursoCodigo) return false;
            if (!this.aplicaEvaluacion) return true;

            const num = (v) => (v === "" || v === null || v === undefined ? NaN : Number(v));
            const tiempo = num(this.limiteTiempo);
            const nota = num(this.nota);
            const intentos = num(this.intentos);
            const cantidad = num(this.cantidadPreguntas);

            return (
                Number.isFinite(tiempo) && tiempo >= 1 &&
                Number.isFinite(nota) && nota >= 0 &&
                Number.isFinite(intentos) && intentos >= 1 &&
                Number.isFinite(cantidad) && cantidad >= 1 &&
                this.preguntasExamen.length > 0
            );
        },

        resetFormulario() {
            this.error = "";
            this.guardando = false;
            this.codigo = "";
            this.cursoCodigo = "";
            this.cursoNombre = "";
            this.planNombre = "";
            this.areaNombre = "";
            this.responsableNombre = "";
            this.aplicaEvaluacion = false;
            this.limiteTiempo = "";
            this.nota = "";
            this.intentos = "";
            this.cantidadPreguntas = "";
            this.preguntasBalotario = "";
            this.archivoWord = null;
            this.archivoWordNombre = "";
            this.cargandoWord = false;
            this.preguntasExamen = [];
            this.imageFilePortada = null;
            this.imagePreviewPortada = null;
            this.imageFileAfiche = null;
            this.imagePreviewAfiche = null;
            this.modalPreviewAbierto = false;
            this.modalPreviewSrc = "";
            this.modalPreviewTitulo = "";
        },

        async abrir(codigo) {
            const cod = codigo || window.__pendingSubirAvNew;
            if (!cod) return;
            window.__pendingSubirAvNew = null;

            this.resetFormulario();
            this.codigo = String(cod);
            this.showModal = true;
            document.body.style.overflow = "hidden";
            this.cargando = true;

            try {
                const res = await axios.get(`${VITE_URL_APP}/api/obtener-curso-new/${cod}`);
                const curso = res?.data?.data;
                if (!res?.data?.success || !curso) throw new Error("No se pudo cargar el curso.");

                this.cursoCodigo = curso.CURS_COD || String(cod);
                this.cursoNombre = curso.CURS_NOMBRE || "";
                this.planNombre = curso.CURS_PLAN_CAPAC_NOMBRE || "—";
                this.areaNombre = curso.CURS_AREA_RESPONSABLE_NOMBRE || "—";
                this.responsableNombre = curso.CURS_RESPONSABLE_NOMBRE || "—";
            } catch (e) {
                console.error("Error abriendo subir al AV:", e);
                this.error = "No se pudo cargar la información del curso seleccionado.";
            } finally {
                this.cargando = false;
            }
        },

        cerrar() {
            this.showModal = false;
            document.body.style.overflow = "";
            this.resetFormulario();
        },

        previewImage(src, titulo) {
            this.modalPreviewSrc = src;
            this.modalPreviewTitulo = titulo;
            this.modalPreviewAbierto = true;
        },

        async handleImageUpload(event, type = "portada") {
            let file = event.target.files?.[0];
            if (!file) return;

            const allowed = ["image/jpeg", "image/jpg", "image/png"];
            if (!allowed.includes(file.type)) {
                Swal.fire("Atención", "Solo se permiten imágenes .jpg, .jpeg o .png", "warning");
                event.target.value = "";
                return;
            }

            if (file.size > 1990 * 1024) {
                await Swal.fire({
                    title: "Imagen pesada",
                    text: "Supera 1.9 MB, se intentará comprimir automáticamente.",
                    icon: "info",
                    confirmButtonText: "Entendido",
                });
                try {
                    file = await imageCompression(file, { maxSizeMB: 1.9, maxWidthOrHeight: 1920, useWebWorker: true });
                } catch {
                    Swal.fire("Error", "No se pudo comprimir la imagen.", "error");
                    event.target.value = "";
                    return;
                }
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                if (type === "portada") {
                    this.imagePreviewPortada = e.target.result;
                    this.imageFilePortada = file;
                } else {
                    this.imagePreviewAfiche = e.target.result;
                    this.imageFileAfiche = file;
                }
            };
            reader.readAsDataURL(file);
        },

        async analizarExamenWord() {
            if (!this.archivoWord) return;

            const ext = this.archivoWord.name.split(".").pop().toLowerCase();
            if (ext === "doc" || ext === "dot") {
                Swal.fire({
                    title: "Formato deprecado",
                    html: "Abre el archivo y <b>guárdalo como .docx</b> antes de subirlo.",
                    icon: "warning",
                    confirmButtonText: "Entendido",
                });
                return;
            }

            this.cargandoWord = true;
            try {
                const fd = new FormData();
                fd.append("archivo", this.archivoWord);
                const res = await axios.post(`${VITE_URL_APP}/api/capacitacion/procesar-examen-word`, fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                if (res.data?.success) {
                    this.preguntasExamen = res.data.preguntas || [];
                    if (res.data.metrics) {
                        this.wordMetrics = {
                            tokensInput: res.data.metrics.tokens_input,
                            tokensOutput: res.data.metrics.tokens_output,
                            tokensTotal: res.data.metrics.tokens_total,
                            costoUSD: res.data.metrics.costo_usd,
                            tiempoSeg: res.data.metrics.tiempo_seg,
                        };
                    }
                    const count = this.preguntasExamen.length;
                    this.cantidadPreguntas = String(count);
                    this.preguntasBalotario = String(count);
                    this.verVistaPrevia();
                } else {
                    Swal.fire("Error", res.data?.message || "No se pudo procesar el Word", "error");
                }
            } catch (e) {
                console.error(e);
                Swal.fire("Error", "No se pudo procesar el archivo Word", "error");
            } finally {
                this.cargandoWord = false;
            }
        },

        async subirAlAv() {
            if (!this.puedeSubir) {
                const faltantes = [];
                if (!this.cursoCodigo) faltantes.push("el código del curso");
                if (this.aplicaEvaluacion) {
                    const num = (v) => (v === "" || v === null || v === undefined ? NaN : Number(v));
                    if (!(num(this.limiteTiempo) >= 1)) faltantes.push("el límite de tiempo");
                    if (!(num(this.nota) >= 0)) faltantes.push("la nota mínima");
                    if (!(num(this.intentos) >= 1)) faltantes.push("el número de intentos");
                    if (!(num(this.cantidadPreguntas) >= 1)) faltantes.push("la cantidad de preguntas");
                    if (!this.preguntasExamen.length) faltantes.push("al menos una pregunta analizada del Word");
                }

                Swal.fire(
                    "Atención",
                    `Completa ${faltantes.join(", ") || "los datos del curso"} antes de subir al AV.`,
                    "warning"
                );
                return;
            }

            this.guardando = true;
            this.error = "";

            Swal.fire({
                title: "Subiendo curso al AV...",
                html: "Estamos creando el curso y cargando los recursos en Moodle.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const fd = new FormData();
                fd.append("codigo_curso", this.cursoCodigo || this.codigo);
                fd.append("aplica_evaluacion", this.aplicaEvaluacion ? "1" : "0");

                if (this.aplicaEvaluacion) {
                    fd.append("tiempo", String(Number(this.limiteTiempo)));
                    fd.append("nota", String(Number(this.nota)));
                    fd.append("intentos", String(Number(this.intentos)));
                    fd.append("cantidad_preguntas", String(Number(this.cantidadPreguntas)));
                    fd.append("preguntas_balotario", String(Number(this.preguntasBalotario) || 0));
                    fd.append("preguntas", JSON.stringify(this.preguntasExamen));
                }

                if (this.imageFilePortada) fd.append("image_portada", this.imageFilePortada);
                if (this.imageFileAfiche) fd.append("image_afiche", this.imageFileAfiche);

                const res = await axios.post(`${VITE_URL_APP}/api/cursos/subir-av`, fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                if (res.data?.success) {
                    Swal.fire("Éxito", res.data.message || "Curso subido al AV correctamente.", "success");
                    this.cerrar();
                    if (window.tablaCursosNew) {
                        try {
                            await window.tablaCursosNew.setData(`${VITE_URL_APP}/api/obtener-cursos-new`);
                        } catch {}
                    }
                } else {
                    Swal.fire("Error", res.data?.message || "No se pudo subir el curso al AV", "error");
                }
            } catch (e) {
                console.error("Error subiendo curso al AV:", e);
                const errors = e.response?.data?.errors;

                if (errors && typeof errors === "object") {
                    let html = '<ul class="text-left text-sm">';
                    for (const [campo, mensajes] of Object.entries(errors)) {
                        html += `<li><b>${campo}</b>: ${mensajes[0]}</li>`;
                    }
                    html += "</ul>";
                    Swal.fire({ title: "Errores de validación", html, icon: "warning" });
                } else {
                    const msg = e.response?.data?.message || "No se pudo subir el curso al AV.";
                    this.error = msg;
                    Swal.fire("Error", msg, "error");
                }
            } finally {
                this.guardando = false;
            }
        },

        verVistaPrevia() {
            if (!this.preguntasExamen.length) {
                Swal.fire("Sin preguntas", "Primero analiza un archivo Word.", "info");
                return;
            }
            window.dispatchEvent(
                new CustomEvent("abrir-modal-word-new", {
                    detail: {
                        preguntas: this.preguntasExamen,
                        nombreArc: this.archivoWordNombre,
                        metrics: this.wordMetrics,
                    },
                })
            );
        },

        init() {
            window.addEventListener("subir-curso-av", (e) => {
                const c = e.detail?.codigo || window.__pendingSubirAvNew;
                if (c) this.abrir(c);
            });
            window.addEventListener("close-modal-subir-av-new", () => {
                this.showModal = false;
                document.body.style.overflow = "";
            });
            if (window.__pendingSubirAvNew && !this.showModal) {
                const c = window.__pendingSubirAvNew;
                window.__pendingSubirAvNew = null;
                this.abrir(c);
            }
        },
    };
};

// Modal simple de revisión Word para la vista nueva (solo lectura/confirmación)
window.modalExamenWordNew = function () {
    return {
        mostrarModal: false,
        preguntas: [],
        preguntasOriginales: [],
        archivoNombre: "",
        abrirModalWord(preguntas, nombreArc) {
            this.preguntas = Array.isArray(preguntas) ? preguntas : [];
            this.preguntasOriginales = JSON.parse(JSON.stringify(this.preguntas));
            this.archivoNombre = nombreArc || "";
            this.mostrarModal = true;
        },
        undoChanges() {
            this.preguntas = JSON.parse(JSON.stringify(this.preguntasOriginales));
        },
        chr(code) {
            return String.fromCharCode(code);
        },
        init() {
            window.addEventListener("abrir-modal-word-new", (e) => {
                this.abrirModalWord(e.detail.preguntas, e.detail.nombreArc);
            });
        },
    };
};
