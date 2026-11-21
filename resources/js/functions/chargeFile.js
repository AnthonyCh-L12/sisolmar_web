import axios from 'axios';
import Swal from 'sweetalert2';
import html2pdf from 'html2pdf.js';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';

// ============================================================
// ESTADO GLOBAL
// ============================================================
let usuarioActual = null;
let pageSizePersonas = 10;
let personaSeleccionada = null;
let filaFolioEnEdicion = null;
let claveFolioEnEdicion = null;
let snapshotEdicionFolio = null;
let pdfJsCvPromise = null;
const cambiosTemporalesFolios = new Map();

// ============================================================
// REFERENCIAS AL DOM — modal de carga
// ============================================================
const archivoInput = document.getElementById('archivoInput');
const btnSeleccionar = document.getElementById('btnSeleccionar');
const listaArchivos = document.getElementById('listaArchivos');

// Abrir selector al hacer click en la zona dashed
btnSeleccionar.addEventListener('click', () => archivoInput.click());

// ============================================================
// INIT
// ============================================================
(async () => {
    usuarioActual = await getUsuario();
    getPersonal();

    const init = () => {
        seleccionarPrimeraSucursalValida();
        setTimeout(() => {
            reloadTabla();
            cargarIndicadores(); // 🔥 Disparamos las tarjetas en el primer render
        }, 100);
    };

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();

// ============================================================
// TABULATOR — Personas
// ============================================================
const tblPersonas = new Tabulator("#tblPersonas", {
    height: "100%",
    layout: "fitColumns",
    responsiveLayout: "collapse",
    ajaxConfig: "GET",
    pagination: true,
    paginationMode: "remote",
    paginationSize: pageSizePersonas,
    paginationDataSent: { page: "page", size: "size" },
    paginationDataReceived: { data: "data", last_page: "last_page", last_row: "total" },
    paginationElement: document.getElementById("tablaPaginacion"),
    rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
    locale: "es",
    langs: {
        es: { pagination: { first: "«", prev: "‹", next: "›", last: "»" } }
    },

    ajaxResponse: function (url, params, response) {
        this._totalFiltrado = response.total;
        return response;
    },

    ajaxURLGenerator: function (url, config, params) {
        let codSucursal = document.getElementById("sucursal").value;
        if (!codSucursal || codSucursal === "-Seleccionar-" || codSucursal === "00") codSucursal = "0";

        params.search = document.getElementById("buscarPersonal").value.trim();
        params.codSucursal = codSucursal;
        params.tipo_per = document.getElementById("tipo_per")?.value || "TODOS";
        params.vigencia = document.getElementById("filtroVigencia")?.value || "";

        const filtroDJ = document.getElementById("filtroDJ")?.value || "TODOS";
        if (filtroDJ === "SI") params.tiene_folio_25 = "1";
        else if (filtroDJ === "NO") params.tiene_folio_25 = "0";

        const url_final = `${url}?${new URLSearchParams(params).toString()}`;
        console.log('URL generada:', url_final); // ← abre la consola y filtra DJ
        return url_final;
    },

    rowFormatter: function (row) {
        const vigente = row.getData().PERS_VIGENCIA;
        if (vigente !== 'SI') {
            row.getElement().style.backgroundColor = "#ffe5e5";
            row.getElement().style.color = "#7a1f1f";
        }
    },

    columns: [
        {
            title: "N°",
            field: "nro_fila_estatico", // Asignamos un identificador para llamarlo luego
            formatter: function() { return ""; }, // Lo dejamos vacío porque lo inyectaremos post-render
            hozAlign: "center",
            width: 45,
            headerSort: false,
            responsive: false
        },
        { title: "Cód.", field: "CODI_PERS", hozAlign: "center", width: 75, responsive: false },
        {
            title: "Apellidos", field: "apellidos", hozAlign: "left", minWidth: 105, widthGrow: 1, responsive: false,
            formatter: function (cell) {
                let val = cell.getValue() || '';
                return val.toLowerCase().split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
            }
        },
        {
            title: "Nombres", field: "nombres", hozAlign: "left", minWidth: 110, widthGrow: 1, responsive: false,
            formatter: function (cell) {
                let val = cell.getValue() || '';
                const nombre = val.toLowerCase().split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');

                // Botón azulito de DJ ocultado por instrucción
                return `<span>${nombre}</span>`;
            }
        },
        { title: "Nro Doc.", field: "nroDoc", hozAlign: "center", width: 95, responsive: false },
        { title: "Sucursal", field: "sucursal", hozAlign: "center", minWidth: 90, widthGrow: 1, responsive: 0 },
        { title: "Tipo",     field: "TIPOTRAB2",  hozAlign: "center", minWidth: 145, widthGrow: 1,
            formatter: function(cell) {
                let val = cell.getValue() || '';
                // Reemplazamos las abreviaturas por la palabra completa al vuelo (Mantenemos tu lógica original)
                val = val.replace('OPER', 'OPERATIVO').replace('ADMIN', 'ADMINISTRATIVO');

                let color = 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm'; // Color por defecto

                if (val.toUpperCase().includes('OPERATIVO')) {
                    color = 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm';
                }
                else if (val.toUpperCase().includes('ADMINISTRATIVO')) {
                    color = 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm';
                }
                else if (val.toUpperCase().includes('ESPECIAL')) {
                    color = 'bg-orange-100 border-orange-500 text-orange-800 shadow-sm';
                }

                // Inyectamos las clases idénticas al de la Etapa 1 (justify-center, min-width y flex)
                return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-2 py-1 text-[10px] font-bold tracking-wider whitespace-nowrap">${val}</span>` : '—';
            }
        },
        {
            title: "Acciones", field: "acciones", width: 150, minWidth: 145,
            hozAlign: "center", headerSort: false, responsive: false,
            formatter: function () {
                let html = `<button type="button" class="btn rounded-full docs-btn bg-success/25 text-success hover:bg-success hover:text-white px-2 py-1 text-xs">Folios</button>`;
                if (usuarioActual?.tipo_rol != 8) {
                    html += ` <button type="button" class="btn rounded-full legajo-btn bg-warning/25 text-warning hover:bg-warning hover:text-white px-2 py-1 text-xs">Legajos</button>`;
                    // Se agregó la clase "hidden" para ocultar el botón de la huellita
                    html += ` <button type="button" class="hidden btn rounded-full bio-btn bg-info/25 text-info hover:bg-info hover:text-white"><i class="fa fa-fingerprint bio-btn"></i></button>`;
                }
                return html;
            },
            cellClick: function (e, cell) {
                const registro = cell.getRow().getData();
                const codigo = registro.CODI_PERS;
                const persona = obtenerNombreCompleto(registro);

                document.getElementById('codPersonal').value = codigo;
                updateCardTitle(persona);

                if (e.target.classList.contains('docs-btn')) {
                    personaSeleccionada = registro;
                    // Reseteamos el filtro de prioridad y la búsqueda para que siempre inicie limpio
                    document.getElementById('selectTipoFolio').value = 'TODOS';
                    document.getElementById('buscarFolio').value = '';

                    getDocsObligatorios(codigo);
                    document.getElementById('btn-modal-folios').click();
                    setTimeout(() => tblDocs.redraw(true), 300);
                } else if (e.target.classList.contains('bio-btn')) {
                    verBiometrico(codigo, persona);
                } else if (e.target.classList.contains('legajo-btn')) {
                    document.getElementById('btn-modal-legajos').click();
                    setTimeout(() => tblLegajos.redraw(true), 300);
                }
            }
        },
    ],
});

tblPersonas.on("dataLoaded", function () {
    const total = this._totalFiltrado || 0;
    const page = this.getPage();
    const size = this.getPageSize();
    const start = (page - 1) * size + 1;
    const end = Math.min(page * size, total);
    document.getElementById("tablaInfo").innerText = `${start}-${end} de ${total} registros`;
});

tblPersonas.on("renderComplete", function () {
    if (this._ultimoFiltro) resaltarTexto(this._ultimoFiltro);

    // =========================================================
    // INYECCIÓN DE NUMERACIÓN ESTÁTICA
    // =========================================================
    const page = this.getPage() || 1;
    const size = this.getPageSize() || 10;
    const offset = (page - 1) * size;

    // getRows("active") trae las filas en el orden visual ACTUAL de la pantalla
    this.getRows("active").forEach((row, index) => {
        const cell = row.getCell("nro_fila_estatico");
        if (cell) {
            cell.getElement().innerHTML = `<span class="text-gray-700 font-medium">${offset + index + 1}</span>`;
        }
    });
});

// ============================================================
// TABULATOR — Folios (tblDocs)
// ============================================================
const tblDocs = new Tabulator("#tblDocs", {
    height: "100%",
    layout: "fitDataFill",
    responsiveLayout: "collapse",
    pagination: true,
    paginationSize: 10,
    locale: "es",
    langs: {
        es: {
            pagination: { first: "Primero", last: "Último", prev: "Anterior", next: "Siguiente", all: "Todo" },
            data: { empty: "No hay datos disponibles" }
        }
    },
    columns: [
        { title: "Folio", field: "documento", hozAlign: "left", width: '30%' },
        {
            title: "Obligatorio", field: "tipo_folio", hozAlign: "center", width: '10%',
            formatter: function (cell) {
                const val = cell.getValue();
                if (!val) return '—';

                // Colores más vivos, diseño tipo píldora y sombra suave
                const isPrincipal = val.toUpperCase() === 'PRINCIPAL';
                const color = isPrincipal
                    ? 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'
                    // Aquí le subimos la intensidad al borde morado a 500 para que resalte
                    : 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm';

                const label = isPrincipal ? 'SI' : 'NO';
                return `<span class="inline-flex items-center rounded-full border px-3 py-0.5 text-[10px] font-bold tracking-wider uppercase ${color}">${label}</span>`;
            }
        },
        {
            title: "Emisión", field: "fecha_emision", hozAlign: "center", width: '20%',
            formatter: function (cell) {
                return cell.getValue() === null
                    ? '<span class="rounded-full bg-warning/25">&nbsp;&nbsp;PENDIENTE&nbsp;&nbsp;</span>'
                    : cell.getValue();
            }
        },
        {
            title: "Caducidad", field: "fecha_caducidad", hozAlign: "center", width: '20%',
            formatter: function (cell) {
                const data = cell.getRow().getData();
                const fechaCaducidad = cell.getValue();
                const vigente = parseInt(data.vigente);

                if (!fechaCaducidad && data.vencimiento == 1) return '<span class="rounded-full bg-warning/25">&nbsp;&nbsp;PENDIENTE&nbsp;&nbsp;</span>';
                if (!fechaCaducidad && data.vencimiento != 1) return '<span class="rounded-full bg-black/10">&nbsp;&nbsp;NO TIENE&nbsp;&nbsp;</span>';

                return vigente === 1
                    ? `<span class="text-vigente-800 font-bold">${fechaCaducidad}</span>`
                    : `<span class="text-vencido-800 font-bold">${fechaCaducidad}</span>`;
            }
        },
        {
            title: "Acciones", field: "acciones", hozAlign: "center", width: '20%', headerSort: false,
            formatter: function () {
                return `
                    <button type="button" class="btn rounded-full charge-btn bg-success/25 text-success hover:bg-success hover:text-white">
                        <i class="fa fa-cloud-upload charge-btn"></i>
                    </button>
                    <button type="button" class="btn rounded-full viewdoc-btn bg-warning/25 text-warning hover:bg-warning hover:text-white">
                        <i class="fa fa-eye viewdoc-btn"></i>
                    </button>`;
            },
            cellClick: function (e, cell) {
                const dataTbl = cell.getRow().getData();
                const codFolio = parseInt(dataTbl.codFolio);
                const esDJ = codFolio === 25;
                const esCV = codFolio === 65;

                // — Botón subir —
                if (e.target.classList.contains('charge-btn')) {
                    window.parentModalToReopen = 'modal-folios-personal';
                    document.querySelector('#modal-file h3.modal-title').textContent = `Documento: ${dataTbl.documento}`;
                    document.getElementById('codFolioActual').value = codFolio;

                    if (esDJ) {
                        // DJ: solo PDF, sin campos extra
                        archivoInput.setAttribute('accept', 'application/pdf');
                        archivoInput.multiple = false;
                        mostrarAvisoTipo('Solo se permite PDF. Máx. 1 MB.');

                        if (dataTbl.vencimiento == 0) {
                            document.getElementById('divCaducidad').classList.add('hidden');
                            document.getElementById('fecha_caducidad').removeAttribute('required');
                        } else {
                            document.getElementById('divCaducidad').classList.remove('hidden');
                            document.getElementById('fecha_caducidad').setAttribute('required', 'required');
                        }

                        limpiarModalDj();
                    } else {
                        const cantHojas = parseInt(dataTbl.cantidad_hojas || 1);

                        console.log('CANTIDAD DE HOJAS ', dataTbl.cantidad_hojas);
                        console.log('CANTIDAD DE HOJAS mensaje ->  ', cantHojas);

                        archivoInput.setAttribute('accept', esCV ? '.jpg,.jpeg,.png,.pdf' : 'image/jpeg');
                        archivoInput.multiple = esCV || cantHojas > 1;

                        console.log('CANTIDAD DE HOJAS mensaje ->  ', cantHojas > 1);
                        console.log('NPUT SUBIR ->  ', archivoInput);

                        if (esCV) {
                            mostrarAvisoTipo('CV: puedes cargar una imagen, varias páginas o un PDF. Máx. 1.2 MB por archivo.');
                        } else if (cantHojas > 1) {
                            mostrarAvisoTipo(`Se requieren ${cantHojas} imágenes JPG. Máx. 1 MB cada una.`);
                        } else {
                            mostrarAvisoTipo('Solo imágenes JPG. Máx. 1 MB.');
                        }

                        document.querySelector('#txtPeriodo').textContent = dataTbl.periodo ?? '';
                        document.querySelector('#txtCantHojas').textContent = cantHojas;
                        document.getElementById('cantArchivos').value = cantHojas;
                        document.getElementById('codFolio').value = codFolio;
                        document.getElementById('meses').value = dataTbl.meses ?? '';

                        if (dataTbl.vencimiento == 0) {
                            document.getElementById('divCaducidad').classList.add('hidden');
                            document.getElementById('fecha_caducidad').removeAttribute('required');
                        } else {
                            document.getElementById('divCaducidad').classList.remove('hidden');
                            document.getElementById('fecha_caducidad').setAttribute('required', 'required');
                        }

                        limpiarModal();
                    }


                    const elFechaEmision = document.getElementById('fecha_emision');
                    const elFechaCad = document.getElementById('fecha_caducidad');


                    if (elFechaEmision && dataTbl.fecha_emision)
                        elFechaEmision.value = formatearFechaInput(dataTbl.fecha_emision);
                    if (elFechaCad && dataTbl.fecha_caducidad)
                        elFechaCad.value = formatearFechaInput(dataTbl.fecha_caducidad);


                    document.getElementById('btn-modal-docs').click();
                }

                // — Botón ver —
                if (e.target.classList.contains('viewdoc-btn')) {
                    window.parentModalToReopen = 'modal-folios-personal';
                    if (normalizarTexto(dataTbl.documento) === 'CURRICULUM VITAE DOCUMENTADO') {
                        abrirPrevisualizacionCV(dataTbl);
                        return;
                    }
                    if (normalizarTexto(dataTbl.documento) === 'VERIFICACION DOMICILIARIA') {
                        abrirPrevisualizacionVerificacionDomiciliaria(dataTbl);
                        return;
                    }
                    if (normalizarTexto(dataTbl.documento) === 'EVALUACION DEL POSTULANTE') {
                        abrirFolioRegistrado(dataTbl, cell.getRow());
                        return;
                    }
                    if (esDJ) {
                        window.open(`${VITE_URL_APP}/ver-dj/${dataTbl.codPersonal}`, '_blank');
                    } else {
                        // Folios normales: visor de imágenes
                        axios.get(`${VITE_URL_APP}/api/get-view-documents/${dataTbl.codPersonal}/${codFolio}`)
                            .then(response => {
                                if (response.data.success !== true) {
                                    Swal.fire({ title: 'No se encontraron documentos válidos', icon: 'info' });
                                    return;
                                }

                                document.querySelector('#modal-view-docs .modal-title').textContent = dataTbl.personal ?? '';
                                document.querySelector('#modal-view-docs #txtDocSelec').textContent = dataTbl.documento ?? '';

                                const visor = document.getElementById('visorDocs');
                                visor.innerHTML = '';
                                response.data.rutas.forEach(ruta => {
                                    const rutaCompleta = /^https?:\/\//i.test(ruta)
                                        ? ruta
                                        : `http://${String(ruta).replace(/^\/+/, '')}`;
                                    if (/\.pdf(?:[?#]|$)/i.test(rutaCompleta)) {
                                        const pdf = document.createElement('iframe');
                                        pdf.src = rutaCompleta;
                                        pdf.title = `Documento de ${dataTbl.documento ?? 'folio'}`;
                                        pdf.className = 'w-full h-[75vh] min-h-[500px] mb-3 rounded-md border';
                                        visor.appendChild(pdf);
                                    } else {
                                        const imagen = document.createElement('img');
                                        imagen.src = rutaCompleta;
                                        imagen.alt = `Documento de ${dataTbl.documento ?? 'folio'}`;
                                        imagen.className = 'w-full max-w-[700px] mb-3 rounded-md';
                                        visor.appendChild(imagen);
                                    }
                                });

                                document.getElementById('btn-modal-view-docs').click();
                            })
                            .catch(() => Swal.fire({ title: 'Problema al encontrar documentos', icon: 'error' }));
                    }
                }
            }
        },
    ],
});

async function abrirPrevisualizacionCV(folio) {
    try {
        const codPersonal = folio.codPersonal || document.getElementById('codPersonal').value;
        const { data } = await axios.get(`${VITE_URL_APP}/api/cv-personal/${encodeURIComponent(codPersonal)}`);
        let registroCV = {};
        if (folio.codFolio && folio.codRequisito) {
            try {
                const urlRegistro = `${VITE_URL_APP}/api/get-folio-registrado/${encodeURIComponent(codPersonal)}/${encodeURIComponent(folio.codFolio)}/${encodeURIComponent(folio.codRequisito)}`;
                const respuestaRegistro = await axios.get(urlRegistro);
                registroCV = respuestaRegistro.data || {};
            } catch (errorRegistro) {
                console.warn('No se pudieron consultar las secciones registradas del CV:', errorRegistro);
            }
        }
        const cambiosTemporales = cambiosTemporalesFolios.get(claveTemporalFolio(folio));
        if (cambiosTemporales) Object.assign(registroCV, cambiosTemporales);

        const valores = {
            nombres: data.nombres,
            apellidos: data.apellidos,
            documento: data.documento,
            pais: data.pais,
            fecha_nacimiento: data.fecha_nacimiento,
            edad: data.edad,
            direccion: data.direccion,
            departamento: data.departamento,
            provincia: data.provincia,
            distrito: data.distrito,
            telefono: data.telefono,
            correo: data.correo,
            emergencia: data.emergencia,
        };

        document.querySelectorAll('[data-cv-preview]').forEach(element => {
            const valor = valores[element.dataset.cvPreview];
            element.textContent = valor === null || valor === undefined || String(valor).trim() === '' ? '—' : String(valor).trim();
        });

        renderizarSeccionPreviaCV('academica', registroCV.formacion_academica || []);
        renderizarSeccionPreviaCV('profesional', registroCV.formacion_profesional || []);
        renderizarSeccionPreviaCV('experiencia', registroCV.experiencia_laboral || []);
        renderizarReferenciasPreviaCV(registroCV.referencias_actuales || [], registroCV.referencias_anteriores || []);

        try {
            const { data: certificados } = await axios.get(`${VITE_URL_APP}/api/cv-certificados/${encodeURIComponent(codPersonal)}`);
            renderizarCertificadosCV('estudios', certificados.certificados_estudios || []);
            renderizarCertificadosCV('laborales', certificados.certificados_laborales || []);
        } catch (errorCertificados) {
            console.warn('No se pudieron consultar los certificados del CV:', errorCertificados);
            renderizarCertificadosCV('estudios', []);
            renderizarCertificadosCV('laborales', []);
        }

        const foto = document.getElementById('cv-preview-photo');
        const placeholderFoto = document.getElementById('cv-preview-photo-placeholder');
        foto.classList.add('hidden');
        placeholderFoto.classList.remove('hidden');
        foto.onload = () => {
            foto.classList.remove('hidden');
            placeholderFoto.classList.add('hidden');
        };
        foto.onerror = () => {
            foto.classList.add('hidden');
            placeholderFoto.classList.remove('hidden');
        };
        foto.src = `${VITE_URL_APP}/api/cv-media/foto/${encodeURIComponent(codPersonal)}`;
        document.getElementById('btn-modal-cv-preview').click();
    } catch (error) {
        const mensaje = error.response?.data?.message || 'No se pudieron cargar los datos del currículum.';
        Swal.fire({ title: 'No se pudo generar la previsualización', text: mensaje, icon: 'error' });
    }
}

async function abrirPrevisualizacionVerificacionDomiciliaria(folio) {
    try {
        const codPersonal = folio.codPersonal || document.getElementById('codPersonal').value;
        if (!folio.codRequisito) throw new Error('No se encontró la relación del folio para esta persona.');
        const url = `${VITE_URL_APP}/api/get-folio-registrado/${encodeURIComponent(codPersonal)}/${encodeURIComponent(folio.codFolio)}/${encodeURIComponent(folio.codRequisito)}`;
        const { data: registro } = await axios.get(url);
        const cambios = cambiosTemporalesFolios.get(claveTemporalFolio(folio));
        if (cambios) Object.assign(registro, cambios);

        const verificador = (registro.verificadores || []).find(
            item => String(item.codigo) === String(registro.verificador_codigo)
        );
        const nombreVerificador = verificador?.nombre || registro.verificador_nombre || '';
        const codigoPersonalVerificador = verificador?.cod_personal || registro.verificador_cod_personal;
        const firma = document.getElementById('vdo-preview-signature');
        const firmaPlaceholder = document.getElementById('vdo-preview-signature-placeholder');
        const nombreResponsable = document.getElementById('vdo-preview-responsable');
        nombreResponsable.textContent = nombreVerificador || '—';
        firma.onload = () => {
            firma.classList.remove('hidden');
            firmaPlaceholder.classList.add('hidden');
        };
        firma.onerror = () => {
            firma.classList.add('hidden');
            firmaPlaceholder.classList.remove('hidden');
        };
        if (codigoPersonalVerificador) {
            firma.src = `${VITE_URL_APP}/api/cv-media/firma/${encodeURIComponent(codigoPersonalVerificador)}`;
        } else {
            firma.removeAttribute('src');
            firma.classList.add('hidden');
            firmaPlaceholder.classList.remove('hidden');
        }

        const valorFecha = fechaParaVistaVDO(registro.fecha_emision);
        const campos = {
            'Fecha': valorFecha,
            'Nombres y apellidos': registro.nombres_apellidos,
            'Doc. de Identidad': registro.documento,
            'Cargo': registro.cargo_personal,
            'Dirección': registro.domicilio,
            'Dpto. / Prov. / Dist.': registro.ubicacion,
            'Teléfono': registro.telefono,
            'Estado civil': registro.estado_civil,
        };
        document.querySelector('[data-vdo-preview="fecha"]').textContent = valorFecha || '—';
        const contenedor = document.getElementById('vdo-preview-sections');
        contenedor.replaceChildren();
        agregarSeccionPreviaVDO(contenedor, 'Datos personales', Object.entries(campos).slice(1));
        agregarSeccionPreviaVDO(contenedor, 'De la cónyuge', [
            ['Nombres y apellidos', registro.conyuge_nombre],
            ['Profesión / Ocupación', registro.conyuge_profesion],
            ['Comparte domicilio permanentemente', marcarOpcionVDO(registro.conyuge_comparte_domicilio, { SI: 'Sí ( X )   No (   )', NO: 'Sí (   )   No ( X )' })],
            ['Empresa', registro.conyuge_empresa],
            ['Teléfono', registro.conyuge_telefono],
            ['Problemas de salud', registro.conyuge_salud],
        ]);
        agregarSeccionPreviaVDO(contenedor, 'Vulnerabilidad en el acceso y entorno a la vivienda', [
            ['Vías de acceso', textoOpcionVDO(registro.vias_acceso, { SI: 'Sí', NO: 'No' })],
            ['Iluminación', textoOpcionVDO(registro.iluminacion, { 1: 'Buena', 2: 'Mala', 3: 'Regular' })],
            ['Entorno', registro.entorno],
            ['Puesto de policía o vigilancia cerca', textoOpcionVDO(registro.vigilancia, { SI: 'Sí', NO: 'No' })],
            ['Otros', registro.otros_acceso],
        ]);
        agregarSeccionPreviaVDO(contenedor, 'Vulnerabilidad de la vivienda', [
            ['Material de construcción de la vivienda', textoOpcionVDO(registro.casa_material, { 1: 'Noble', 2: 'Rústico' })],
            ['Seguridad en puertas, ventanas y techos', textoOpcionVDO(registro.casa_segura, { 1: 'Buena', 2: 'Mala', 3: 'Regular' })],
            ['Teléfono u otro medio de comunicación', registro.casa_telefono],
            ['Espacio para quienes viven allí', textoOpcionVDO(registro.casa_espacio, { 1: 'Muy cómodo', 2: 'Cómodo', 3: 'Incómodo', 4: 'Muy incómodo' })],
        ]);
        agregarSeccionPreviaVDO(contenedor, 'Información del medio familiar', [
            ['Enfermos a su cargo', registro.enfermos],
            ['Discapacitados a su cargo', registro.discapacitado],
            ['Drogadicción y alcoholismo', registro.drogas],
        ]);
        agregarSeccionPreviaVDO(contenedor, 'Comodidades', [
            ['Nevera', siNoDesdeBooleanoVDO(registro.nevera)],
            ['Loza y cubiertos', siNoDesdeBooleanoVDO(registro.loza)],
            ['Tipo de cocina', textoOpcionVDO(registro.cocina, { 1: 'Gas', 2: 'Eléctrica', 3: 'Otro' })],
            ['Nro. televisores', registro.televisores],
            ['Nro. vehículos', registro.vehiculos],
            ['Otras comodidades', registro.comodidades],
        ]);
        agregarSeccionPreviaVDO(contenedor, 'Información económica', [
            ['¿Cuáles son los bienes que aspira tener la familia?', registro.bienes_familia],
        ]);
        agregarSeccionPreviaVDO(contenedor, 'Notoriedad social', [
            ['¿Es líder o participa activamente en grupos políticos, religiosos, deportivos, etc.?', registro.lider_social],
            ['¿Lo conocen los vecinos?', textoOpcionVDO(registro.conocido_vecinos, { SI: 'Sí', NO: 'No' })],
        ], { preguntasYRespuestas: true });
        agregarSeccionPreviaVDO(contenedor, 'Actividades de bienestar, recreación y deportes', [
            ['¿Cuáles han sido las tres últimas actividades más importantes en las que ha participado?', registro.actividades],
            ['¿Qué es lo que más le gustaría hacer a la familia?', registro.gusto_familia],
        ], { preguntasYRespuestas: true });
        agregarSeccionPreviaVDO(contenedor, 'Conclusiones', [
            ['Principales fortalezas para no acceder a la presión o al poder de convencimiento de la delincuencia', registro.conclusion_fortalezas],
            ['¿Ubicaría a esta persona en un puesto de alto riesgo? ¿Por qué?', registro.conclusion_riesgo],
        ], { preguntasYRespuestas: true });
        window.parentModalToReopen = 'modal-folios-personal';
        document.getElementById('btn-modal-vdo-preview').click();
    } catch (error) {
        const mensaje = error.response?.data?.message || error.message || 'No se pudo generar la previsualización.';
        Swal.fire({ title: 'No se pudo generar la previsualización', text: mensaje, icon: 'error' });
    }
}

function agregarSeccionPreviaVDO(contenedor, titulo, pares, opciones = {}) {
    const seccion = document.createElement('section');
    seccion.className = 'vdo-preview-section';
    const encabezado = document.createElement('h3');
    encabezado.className = 'mb-1 border-b border-gray-400 pb-2 font-semibold uppercase text-black';
    encabezado.textContent = titulo;
    seccion.appendChild(encabezado);
    if (opciones.preguntasYRespuestas) {
        const lista = document.createElement('div');
        lista.className = 'space-y-1 pt-0.5';
        pares.forEach(([pregunta, respuesta]) => {
            const bloque = document.createElement('div');
            bloque.className = 'break-inside-avoid';
            const textoPregunta = document.createElement('p');
            textoPregunta.className = 'font-normal text-black';
            textoPregunta.style.color = '#000';
            textoPregunta.textContent = pregunta;
            const respuestaBox = document.createElement('p');
            respuestaBox.className = 'mt-0 pl-0 text-black font-normal whitespace-pre-wrap break-words';
            respuestaBox.textContent = (
                respuesta === null || respuesta === undefined || String(respuesta).trim() === ''
                    ? 'Sin respuesta registrada'
                    : String(respuesta).trim()
            );
            respuestaBox.style.color = '#000';
            bloque.append(textoPregunta, respuestaBox);
            lista.appendChild(bloque);
        });
        seccion.appendChild(lista);
        contenedor.appendChild(seccion);
        return;
    }
    const lista = document.createElement('dl');
    lista.className = 'grid grid-cols-[minmax(9rem,auto)_1fr] gap-x-2 gap-y-0';
    pares.forEach(([etiqueta, dato]) => {
        const termino = document.createElement('dt');
        termino.className = 'font-normal text-black';
        termino.style.color = '#000';
        termino.textContent = `${etiqueta}:`;
        const valor = document.createElement('dd');
        valor.className = 'min-h-[14px] whitespace-pre-wrap break-words';
        valor.style.color = '#000';
        valor.textContent = dato === null || dato === undefined || String(dato).trim() === '' ? '—' : String(dato).trim();
        lista.append(termino, valor);
    });
    seccion.appendChild(lista);
    contenedor.appendChild(seccion);
}

function textoOpcionVDO(valor, opciones) {
    if (valor === null || valor === undefined || String(valor).trim() === '') return '';
    const normalizado = normalizarTexto(valor);
    const clave = ({ S: 'SI', N: 'NO' })[normalizado] || normalizado;
    return opciones[clave] ?? opciones[String(valor).trim()] ?? String(valor);
}

function marcarOpcionVDO(valor, opciones) {
    const resultado = textoOpcionVDO(valor, opciones);
    return resultado || '';
}

function siNoDesdeBooleanoVDO(valor) {
    if (valor === null || valor === undefined || String(valor).trim() === '') return '';
    return ['1', 'SI', 'S', 'TRUE'].includes(normalizarTexto(valor)) ? 'Sí' : 'No';
}

function fechaParaVistaVDO(valor) {
    if (!valor) return '';
    const texto = String(valor).slice(0, 10);
    if (/^\d{4}-\d{2}-\d{2}$/.test(texto)) {
        const [anio, mes, dia] = texto.split('-');
        return `${dia}/${mes}/${anio}`;
    }
    return texto;
}

document.getElementById('btn-exportar-vdo-pdf')?.addEventListener('click', async () => {
    const contenido = document.getElementById('vdo-preview-document');
    const persona = document.getElementById('codPersonal')?.value || 'personal';
    const nombre = (document.querySelector('[data-vdo-preview="fecha"]')?.textContent || 'visita')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9_-]+/g, '_');
    try {
        await Promise.all([...contenido.querySelectorAll('img')].map(imagen => {
            if (imagen.complete) return Promise.resolve();
            return new Promise(resolve => {
                imagen.addEventListener('load', resolve, { once: true });
                imagen.addEventListener('error', resolve, { once: true });
                setTimeout(resolve, 5000);
            });
        }));
        await html2pdf().set({
            margin: [6, 8, 6, 8],
            filename: `Verificacion_Domiciliaria_${persona}_${nombre}.pdf`,
            // html2pdf rasteriza el documento; PNG y mayor escala evitan artefactos
            // de compresión en letras y líneas finas.
            image: { type: 'png' },
            html2canvas: { scale: 3, useCORS: true, allowTaint: false, backgroundColor: '#ffffff', scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css'], avoid: ['.vdo-preview-section', '.vdo-preview-signature-block'] },
        }).from(contenido).save();
    } catch (error) {
        console.error('No se pudo generar el PDF de visita domiciliaria:', error);
        Swal.fire({ title: 'No se pudo descargar el PDF', text: error.message || 'Inténtalo nuevamente.', icon: 'error' });
    }
});

document.getElementById('btn-exportar-cv-pdf')?.addEventListener('click', exportarPrevisualizacionCvPdf);

async function exportarPrevisualizacionCvPdf() {
    const contenido = document.getElementById('cv-preview-document');
    if (!contenido) return;

    const nombres = document.querySelector('[data-cv-preview="nombres"]')?.textContent?.trim() || '';
    const apellidos = document.querySelector('[data-cv-preview="apellidos"]')?.textContent?.trim() || '';
    const nombreArchivo = `Curriculum_Vitae_${nombres}_${apellidos}`
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9_-]+/g, '_').replace(/^_+|_+$/g, '');
    const contenedor = document.createElement('div');
    contenedor.style.cssText = 'position:fixed;left:-10000px;top:0;width:794px;background:#fff;z-index:-1;';
    const copia = contenido.cloneNode(true);
    copia.style.cssText += ';width:100%;max-width:none;min-height:0;margin:0;border:0;box-shadow:none;background:#fff;color:#111827;';
    copia.querySelectorAll('#cv-preview-academica-list, #cv-preview-profesional-list, #cv-preview-experiencia-list, #cv-preview-referencias-actuales, #cv-preview-referencias-anteriores').forEach(lista => {
        lista.style.color = '#1f2937';
        lista.style.fontWeight = '500';
    });
    copia.querySelectorAll('img').forEach(imagen => { imagen.loading = 'eager'; });
    contenedor.appendChild(copia);
    document.body.appendChild(contenedor);
    try {
        // html2canvas no dibuja el contenido de iframes PDF. Renderizamos sus páginas
        // a imágenes antes de crear el PDF descargable.
        const iframesPdf = [...copia.querySelectorAll('iframe')];
        if (iframesPdf.length) await asegurarPdfJsCV();
        for (const iframe of iframesPdf) {
            const respuesta = await fetch(iframe.src, { credentials: 'same-origin' });
            if (!respuesta.ok) throw new Error(`No se pudo cargar un certificado PDF (${respuesta.status}).`);
            const datosPdf = new Uint8Array(await respuesta.arrayBuffer());
            const pdf = await window.pdfjsLib.getDocument({ data: datosPdf }).promise;
            const paginas = document.createElement('div');
            for (let numero = 1; numero <= pdf.numPages; numero++) {
                const pagina = await pdf.getPage(numero);
                const viewport = pagina.getViewport({ scale: 2 });
                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(viewport.width);
                canvas.height = Math.ceil(viewport.height);
                await pagina.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                const imagenPagina = document.createElement('img');
                imagenPagina.src = canvas.toDataURL('image/jpeg', 0.94);
                imagenPagina.alt = `Página ${numero} del certificado`;
                paginas.appendChild(crearPaginaCertificadoCV(imagenPagina));
            }
            iframe.replaceWith(paginas);
        }

        ['estudios', 'laborales'].forEach(tipo => {
            const seccion = copia.querySelector(`#cv-preview-cert-${tipo}-section`);
            if (!seccion || seccion.classList.contains('hidden')) return;
            seccion.style.cssText += ';break-before:page;page-break-before:always;margin-top:0;padding-top:0;';
            seccion.querySelectorAll('img').forEach(imagen => {
                if (imagen.closest('.cv-certificate-page')) return;
                imagen.replaceWith(crearPaginaCertificadoCV(imagen));
            });
            seccion.querySelectorAll('.cv-certificate-page').forEach((pagina, indice) => {
                if (indice > 0) pagina.classList.add('cv-pdf-new-page');
            });
        });

        await Promise.all([...copia.querySelectorAll('img')].map(imagen => imagen.complete
            ? Promise.resolve()
            : new Promise(resolve => {
                imagen.addEventListener('load', resolve, { once: true });
                imagen.addEventListener('error', resolve, { once: true });
                setTimeout(resolve, 8000);
            })
        ));

        await html2pdf().set({
            margin: [12, 12, 12, 12],
            filename: `${nombreArchivo || 'Curriculum_Vitae'}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, allowTaint: false, backgroundColor: '#ffffff', scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: {
                mode: ['css', 'legacy'],
                before: ['#cv-preview-cert-estudios-section', '#cv-preview-cert-laborales-section', '.cv-pdf-new-page'],
            },
        }).from(copia).save();
    } catch (error) {
        console.error('No se pudo generar el PDF del CV:', error);
        Swal.fire({ title: 'No se pudo descargar el PDF', text: error.message || 'Inténtalo nuevamente.', icon: 'error' });
    } finally {
        contenedor.remove();
    }
}

function crearPaginaCertificadoCV(imagen) {
    const pagina = document.createElement('div');
    pagina.className = 'cv-certificate-page';
    pagina.style.cssText = 'height:245mm;break-inside:avoid;page-break-inside:avoid;display:flex;align-items:center;justify-content:center;overflow:hidden;';
    const imagenPagina = imagen.cloneNode(true);
    imagenPagina.classList.remove('h-auto', 'max-h-[900px]', 'w-auto', 'max-w-full', 'object-contain');
    imagenPagina.style.cssText += ';display:block;width:auto;height:auto;max-width:100%;max-height:235mm;object-fit:contain;border:1px solid #d1d5db;';
    pagina.appendChild(imagenPagina);
    return pagina;
}

async function asegurarPdfJsCV() {
    if (window.pdfjsLib) return;
    if (!pdfJsCvPromise) {
        pdfJsCvPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
            script.onload = () => {
                if (!window.pdfjsLib) return reject(new Error('PDF.js no está disponible.'));
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                resolve();
            };
            script.onerror = () => reject(new Error('No se pudo cargar el lector de certificados PDF.'));
            document.head.appendChild(script);
        });
    }
    await pdfJsCvPromise;
}

function renderizarSeccionPreviaCV(nombre, registros) {
    const seccion = document.getElementById(`cv-preview-${nombre}-section`);
    if (!seccion) return;

    const filas = Array.isArray(registros) ? registros.filter(registro => {
        const campos = nombre === 'experiencia'
            ? ['empresa', 'cargo', 'tiempo', 'fecha_inicio', 'fecha_cese']
            : nombre === 'profesional'
                ? ['curso_capacitacion', 'descripcion', 'fecha', 'lugar', 'vigencia']
            : Object.keys(registro || {});
        return campos.some(campo => registro?.[campo] !== null && String(registro?.[campo] ?? '').trim() !== '');
    }) : [];
    seccion.classList.toggle('hidden', filas.length === 0);

    if (nombre === 'experiencia') {
        const lista = document.getElementById('cv-preview-experiencia-list');
        lista.replaceChildren();
        filas.forEach(registro => {
            const item = document.createElement('li');
            item.className = 'space-y-1';
            [
                ['EMPRESA', registro.empresa],
                ['CARGO', registro.cargo],
                ['TIEMPO', obtenerTiempoExperienciaCV(registro)],
            ].forEach(([etiqueta, valor]) => {
                const linea = document.createElement('div');
                const nombreCampo = document.createElement('strong');
                nombreCampo.className = 'mr-2 font-semibold';
                nombreCampo.textContent = etiqueta;
                const contenido = document.createElement('span');
                contenido.textContent = valor === null || valor === undefined || String(valor).trim() === '' ? '—' : String(valor).trim();
                linea.append(nombreCampo, document.createTextNode(': '), contenido);
                item.appendChild(linea);
            });
            lista.appendChild(item);
        });
        return;
    }

    if (nombre === 'profesional') {
        const lista = document.getElementById('cv-preview-profesional-list');
        lista.replaceChildren();
        filas.forEach(registro => {
            const item = document.createElement('li');
            item.className = 'space-y-0.5';
            const curso = document.createElement('div');
            curso.className = 'uppercase';
            const tituloCurso = registro.curso_capacitacion || registro.descripcion || '—';
            const anio = String(registro.fecha || '').match(/\b(19|20)\d{2}\b/)?.[0];
            curso.textContent = `${tituloCurso}${anio ? ` (${anio})` : ''}`;
            const lugar = document.createElement('div');
            lugar.className = 'uppercase';
            lugar.textContent = registro.lugar || '—';
            item.append(curso, lugar);
            lista.appendChild(item);
        });
        return;
    }

    const lista = document.getElementById('cv-preview-academica-list');
    if (!lista) return;
    lista.replaceChildren();
    const obtenerAnio = valor => String(valor || '').match(/\b(19|20)\d{2}\b/)?.[0] || '';

    filas.forEach(registro => {
        const item = document.createElement('li');
        item.className = 'space-y-1';
        const grado = document.createElement('strong');
        grado.className = 'block font-semibold uppercase';
        grado.textContent = registro.grado_academico || 'Formación académica';
        const detalle = document.createElement('p');
        const anioInicio = obtenerAnio(registro.fecha_inicio);
        const anioFin = obtenerAnio(registro.fecha_fin);
        const periodo = anioInicio || anioFin ? ` (${anioInicio || '****'} - ${anioFin || 'ACTUALIDAD'})` : '';
        detalle.textContent = `${registro.descripcion || '—'}${periodo}`;
        item.append(grado, detalle);
        lista.appendChild(item);
    });
}

function obtenerTiempoExperienciaCV(registro) {
    const anio = valor => String(valor || '').match(/\b(19|20)\d{2}\b/)?.[0] || '';
    const anioInicio = anio(registro?.fecha_inicio);
    const anioCese = anio(registro?.fecha_cese);
    if (anioInicio || anioCese) {
        const inicio = anioInicio || '****';
        const fin = anioCese || (normalizarTexto(registro?.vigencia) === 'NO' ? '****' : 'ACTUALIDAD');
        return `${inicio} - ${fin}`;
    }
    return registro?.tiempo && String(registro.tiempo).trim() ? String(registro.tiempo).trim() : '—';
}

function renderizarReferenciasPreviaCV(actuales, anteriores) {
    const seccion = document.getElementById('cv-preview-referencias-section');
    const actualesBlock = document.getElementById('cv-preview-referencias-actuales-block');
    const anterioresBlock = document.getElementById('cv-preview-referencias-anteriores-block');
    const listas = [
        { datos: actuales, bloque: actualesBlock, lista: document.getElementById('cv-preview-referencias-actuales') },
        { datos: anteriores, bloque: anterioresBlock, lista: document.getElementById('cv-preview-referencias-anteriores') },
    ];
    let total = 0;

    listas.forEach(({ datos, bloque, lista }) => {
        const registros = Array.isArray(datos) ? datos.filter(registro =>
            ['nombre', 'cargo', 'telefono', 'correo'].some(campo => String(registro?.[campo] ?? '').trim() !== '')
        ) : [];
        total += registros.length;
        bloque.classList.toggle('hidden', registros.length === 0);
        lista.replaceChildren();

        registros.forEach(registro => {
            const item = document.createElement('li');
            item.className = 'space-y-1';
            [
                ['NOMBRE', registro.nombre],
                ['CARGO', registro.cargo],
                ['CELULAR', registro.telefono],
                ['CORREO', registro.correo],
            ].forEach(([etiqueta, valor]) => {
                if (valor === null || valor === undefined || String(valor).trim() === '') return;
                const linea = document.createElement('div');
                const nombreCampo = document.createElement('strong');
                nombreCampo.className = 'mr-2 font-semibold';
                nombreCampo.textContent = etiqueta;
                const contenido = document.createElement('span');
                contenido.textContent = String(valor).trim();
                linea.append(nombreCampo, document.createTextNode(': '), contenido);
                item.appendChild(linea);
            });
            lista.appendChild(item);
        });
    });

    seccion.classList.toggle('hidden', total === 0);
}

function renderizarCertificadosCV(tipo, archivos) {
    const seccion = document.getElementById(`cv-preview-cert-${tipo}-section`);
    const contenedor = document.getElementById(`cv-preview-cert-${tipo}`);
    if (!seccion || !contenedor) return;

    const documentos = Array.isArray(archivos) ? archivos : [];
    contenedor.replaceChildren();
    seccion.classList.toggle('hidden', documentos.length === 0);

    documentos.forEach((documento, indice) => {
        const esPdf = String(documento.tipo || documento.url || '').toLowerCase().includes('pdf');
        const vista = esPdf ? document.createElement('iframe') : document.createElement('img');
        vista.src = documento.url;
        vista.title = `Certificado de ${tipo} ${indice + 1}`;
        vista.className = esPdf
            ? 'h-[700px] w-full rounded border border-gray-200 bg-white'
            : 'mx-auto block h-auto max-h-[900px] w-auto max-w-full border border-gray-200 bg-white object-contain shadow-sm';
        if (!esPdf) {
            vista.alt = `Certificado de ${tipo} ${indice + 1}`;
            vista.loading = 'lazy';
        }
        contenedor.appendChild(vista);
    });
}

// Maneja el doble clic en el DOM para que funcione en cualquier celda del folio.
document.getElementById('tblDocs').addEventListener('dblclick', event => {
    if (event.target.closest('button, a, input, select')) return;

    const fila = event.target.closest('.tabulator-row');
    if (!fila) return;

    event.preventDefault();
    const row = tblDocs.getRow(fila);
    if (row) abrirFolioRegistrado(row.getData(), row);
});


function formatearFechaInput(fecha) {
    if (!fecha) return '';
    // Si ya viene en formato yyyy-MM-dd, no convertir
    if (/^\d{4}-\d{2}-\d{2}$/.test(fecha)) return fecha;
    // Convertir dd/MM/yyyy → yyyy-MM-dd
    const partes = fecha.split('/');
    if (partes.length === 3) return `${partes[2]}-${partes[1]}-${partes[0]}`;
    return '';
}

// ============================================================
// TABULATOR — Legajos
// ============================================================
const tblLegajos = new Tabulator("#tblDocsLegajo", {
    height: "100%",
    layout: "fitDataFill",
    responsiveLayout: "collapse",
    columns: [
        { title: "Folio", field: "documento", hozAlign: "left", width: '40%' },
        {
            title: "Emisión", field: "fecha_emision", hozAlign: "center", width: '20%',
            formatter: function (cell) {
                return cell.getValue() === null
                    ? '<span class="rounded-full bg-warning/25">&nbsp;&nbsp;PENDIENTE&nbsp;&nbsp;</span>'
                    : cell.getValue();
            }
        },
        {
            title: "Caducidad", field: "fecha_caducidad", hozAlign: "center", width: '20%',
            formatter: function (cell) {
                const vigente = cell.getRow().getData().vigente;
                const fechaCaducidad = cell.getValue();
                if (vigente == 1) return `<span class="text-vigente-800 font-bold">${fechaCaducidad ?? '--'}</span>`;
                if (vigente == 0) return `<span class="text-vencido-800 font-bold">${fechaCaducidad ?? '--'}</span>`;
                return '<span class="rounded-full bg-warning/25">&nbsp;&nbsp;PENDIENTE&nbsp;&nbsp;</span>';
            }
        },
        {
            title: "Acciones", field: "accionesy", hozAlign: "center", width: '20%', headerSort: false,
            formatter: function (cell) {
                const filePath = cell.getRow().getData().ruta_archivo;
                const url = '/storage/' + filePath;
                const viewBtn = filePath
                    ? `<a href="${url}" target="_blank" class="btn rounded-full view-btn bg-info/25 text-info hover:bg-info hover:text-white"><i class="fa fa-eye"></i></a>`
                    : `<a class="pointer-events-none btn rounded-full bg-gray-200 text-gray-400"><i class="fa fa-eye"></i></a>`;
                return `<button type="button" class="btn rounded-full charge-btn-leg bg-success/25 text-success hover:bg-success hover:text-white"><i class="fa fa-cloud-upload charge-btn-leg"></i></button> ${viewBtn}`;
            },
            cellClick: function (e, cell) {
                if (e.target.classList.contains('charge-btn-leg')) {
                    window.parentModalToReopen = 'modal-legajos-personal';
                    const dataTbl = cell.getRow().getData();
                    const codFolio = parseInt(dataTbl.codFolio);

                    document.querySelector('#modal-file h3.modal-title').textContent = `Documento: ${dataTbl.documento}`;
                    document.querySelector('#txtPeriodo').textContent = dataTbl.periodo ?? '';
                    document.getElementById('codFolioActual').value = codFolio;
                    document.getElementById('codFolio').value = codFolio;
                    document.getElementById('meses').value = dataTbl.meses ?? '';

                    const cantHojas = parseInt(dataTbl.cantidad_hojas || 1);
                    archivoInput.setAttribute('accept', 'image/jpeg');
                    archivoInput.multiple = cantHojas > 1;

                    if (cantHojas > 1) {
                        mostrarAvisoTipo(`Se requieren ${cantHojas} imágenes JPG. Máx. 1 MB cada una.`);
                    } else {
                        mostrarAvisoTipo('Solo imágenes JPG. Máx. 1 MB.');
                    }
                    document.getElementById('cantArchivos').value = cantHojas;

                    if (dataTbl.vencimiento == 0) {
                        document.getElementById('divCaducidad').classList.add('hidden');
                        document.getElementById('fecha_caducidad').removeAttribute('required');
                    } else {
                        document.getElementById('divCaducidad').classList.remove('hidden');
                        document.getElementById('fecha_caducidad').setAttribute('required', 'required');
                    }

                    limpiarModal();
                    const elFechaEmision = document.getElementById('fecha_emision');
                    const elFechaCad = document.getElementById('fecha_caducidad');
                    if (elFechaEmision && dataTbl.fecha_emision)
                        elFechaEmision.value = formatearFechaInput(dataTbl.fecha_emision);
                    if (elFechaCad && dataTbl.fecha_caducidad)
                        elFechaCad.value = formatearFechaInput(dataTbl.fecha_caducidad);

                    document.getElementById('btn-modal-docs').click();
                }
            }
        },
    ]
});

// ============================================================
// TABULATOR — Coincidencias
// ============================================================
const tblPersonasCN = new Tabulator("#tblPersonasCN", {
    height: "100%",
    layout: "fitDataFill",
    responsiveLayout: "collapse",
    columns: [
        { title: "Código", field: "CODI_PERS", hozAlign: "center", width: '10%' },
        { title: "Personal", field: "personal", hozAlign: "left", width: '30%' },
        { title: "Nro Documento", field: "nroDoc", hozAlign: "center", width: '15%' },
        { title: "Sucursal", field: "sucursal", hozAlign: "center", width: '18%' },
    ],
});

// ============================================================
// MODAL — Carga de archivo
// ============================================================
function limpiarModal() {
    document.getElementById('fecha_emision').value = '';
    document.getElementById('fecha_caducidad').value = '';
    window.archivosSeleccionados = [];
    listaArchivos.innerHTML = '';
    archivoInput.value = '';
    //archivoInput.multiple        = false; // ← resetear
    //const aviso = document.getElementById('aviso-tipo-archivo');
    //if (aviso) aviso.textContent = '';
}

function limpiarModalDj() {
    document.getElementById('fecha_emision').value = '';
    document.getElementById('fecha_caducidad').value = '';
    archivoInput.value = '';
    //archivoInput.multiple = false; // ← resetear
    listaArchivos.innerHTML = '';
    //const aviso = document.getElementById('aviso-tipo-archivo');
    //if (aviso) aviso.textContent = '';
}

function calcularFechaCaducidad(fechaEmision) {
    const meses = parseInt(document.getElementById('meses').value);
    if (meses > 1) {
        const fecha = new Date(fechaEmision);
        fecha.setMonth(fecha.getMonth() + meses);
        const anio = fecha.getFullYear();
        const mes = ('0' + (fecha.getMonth() + 1)).slice(-2);
        const dia = ('0' + fecha.getDate()).slice(-2);
        return `${anio}-${mes}-${dia}`;
    }
    return '';
}

function mostrarAvisoTipo(mensaje) {
    const aviso = document.getElementById('aviso-tipo-archivo');
    if (aviso) aviso.innerHTML = mensaje;
}

function esArchivoCvPermitido(archivo) {
    return ['image/jpeg', 'image/png', 'application/pdf'].includes(archivo.type)
        || /\.(jpe?g|png|pdf)$/i.test(archivo.name || '');
}

// Validación en tiempo real al seleccionar archivo
archivoInput.addEventListener('change', function () {
    const archivos = Array.from(this.files);
    if (!archivos.length) return;

    const codFolio = parseInt(document.getElementById('codFolioActual').value || '0');
    const esDJ = codFolio === 25;
    const esCV = codFolio === 65;
    const maxSize = 1.2 * 1024 * 1024;
    const cantEsperada = parseInt(document.getElementById('cantArchivos')?.value || '1');

    const limpiar = () => {
        this.value = '';
        window.archivosSeleccionados = [];
        listaArchivos.innerHTML = '';
    };

    // Validar tipo
    for (const archivo of archivos) {
        if (esDJ) {
            if (archivo.type !== 'application/pdf') {
                Swal.fire({ title: 'Solo se permite PDF para el DJ', icon: 'warning' });
                limpiar(); return;
            }
        } else if (esCV) {
            if (!esArchivoCvPermitido(archivo)) {
                Swal.fire({ title: 'Para el CV selecciona JPG, PNG o PDF', icon: 'warning' });
                limpiar(); return;
            }
        } else {
            if (archivo.type !== 'image/jpeg') {
                Swal.fire({ title: 'Solo se permiten imágenes JPG', icon: 'warning' });
                limpiar(); return;
            }
        }

        // Validar tamaño
        if (archivo.size > maxSize) {
            Swal.fire({
                title: 'Archivo demasiado grande',
                text: `"${archivo.name}" pesa ${(archivo.size / 1024 / 1024).toFixed(2)} MB. Límite: 1.2 MB por archivo.`,
                icon: 'warning'
            });
            limpiar(); return;
        }
    }

    // Validar cantidad (solo no-DJ)
    if (!esDJ && !esCV && archivos.length !== cantEsperada) {
        Swal.fire({
            title: cantEsperada === 1
                ? 'Solo se permite 1 archivo'
                : `Se requieren exactamente ${cantEsperada} archivos`,
            text: `Seleccionaste ${archivos.length}. Este documento requiere ${cantEsperada} imagen${cantEsperada > 1 ? 'es' : ''}.`,
            icon: 'warning'
        });
        limpiar(); return;
    }

    // Todo OK
    window.archivosSeleccionados = archivos;

    listaArchivos.innerHTML = archivos.map(a => `
        <li class="flex items-center gap-2 text-sm text-gray-700">
            <i class="fa fa-file text-success"></i>
            <span>${a.name}</span>
            <span class="text-gray-400">(${(a.size / 1024).toFixed(1)} KB)</span>
        </li>
    `).join('');
});

// Submit del formulario
document.getElementById('formFolioPersonal').addEventListener('submit', function (event) {
    event.preventDefault();

    const fechaEmision = document.getElementById('fecha_emision').value;
    const codigoPer = document.getElementById('codPersonal').value;
    const codFolio = parseInt(document.getElementById('codFolioActual').value || '0');
    const esDJ = codFolio === 25;
    const esCV = codFolio === 65;
    const maxSize = 1.2 * 1024 * 1024;
    const cantEsperada = parseInt(document.getElementById('cantArchivos')?.value || '1');

    // Determinar archivos a enviar
    const archivos = esDJ
        ? (archivoInput?.files?.[0] ? [archivoInput.files[0]] : [])
        : (window.archivosSeleccionados?.length ? window.archivosSeleccionados : (archivoInput?.files?.[0] ? [archivoInput.files[0]] : []));

    if (!fechaEmision) { Swal.fire({ title: 'Ingrese la fecha de emisión', icon: 'warning' }); return; }
    if (!archivos.length) { Swal.fire({ title: 'Seleccione un archivo', icon: 'warning' }); return; }

    // Validar tipo y tamaño de cada archivo
    for (const a of archivos) {
        if (esDJ && a.type !== 'application/pdf') {
            Swal.fire({ title: 'Para el DJ solo se permite PDF', icon: 'warning' }); return;
        }
        if (esCV && !esArchivoCvPermitido(a)) {
            Swal.fire({ title: 'Para el CV selecciona JPG, PNG o PDF', icon: 'warning' }); return;
        }
        if (!esDJ && !esCV && a.type !== 'image/jpeg') {
            Swal.fire({ title: 'Solo se permiten imágenes JPG', icon: 'warning' }); return;
        }
        if (a.size > maxSize) {
            Swal.fire({ title: 'Archivo demasiado grande', text: `"${a.name}" pesa ${(a.size / 1024 / 1024).toFixed(2)} MB. Límite: 1.2 MB.`, icon: 'warning' }); return;
        }
    }

    // Validar cantidad (solo no-DJ)
    if (!esDJ && !esCV && archivos.length !== cantEsperada) {
        Swal.fire({
            title: `Se requieren ${cantEsperada} archivo${cantEsperada > 1 ? 's' : ''}`,
            text: `Seleccionaste ${archivos.length}.`,
            icon: 'warning'
        }); return;
    }

    if (esDJ) {
        const formData = new FormData();
        formData.append('fecha_emision', fechaEmision);
        formData.append('codPersonal', codigoPer);
        formData.append('pdf', archivos[0]);

        axios.post(`${VITE_URL_APP}/save-dj-folio-2`, formData, { headers: { 'Accept': 'application/json' } })
            .then(() => {
                document.getElementById('btn-modal-docs-close').click();
                getDocsObligatorios(codigoPer);
                limpiarModalDj();
                Swal.fire({ title: 'DJ guardado correctamente', icon: 'success', timer: 2000, showConfirmButton: false });
            })
            .catch(error => {
                const msg = error.response?.data?.error || error.response?.data?.message || 'Error al guardar el DJ';
                Swal.fire({ title: msg, icon: 'error' });
            });

    } else {
        const fechaCaducidad = document.getElementById('fecha_caducidad').value;
        const codFolioHidden = document.getElementById('codFolio').value;

        const formData = new FormData();
        formData.append('fecha_emision', fechaEmision);
        formData.append('fecha_caducidad', fechaCaducidad);
        formData.append('codFolio', codFolioHidden);
        formData.append('codPersonal', codigoPer);
        archivos.forEach(a => formData.append('imagenes[]', a));

        axios.post(`${VITE_URL_APP}/api/save_folio_persona`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
            .then(() => {
                document.getElementById('btn-modal-docs-close').click();
                getDocsObligatorios(codigoPer);
                getLegajos();
                limpiarModal();
                Swal.fire({ title: 'Documento guardado correctamente', icon: 'success', timer: 2000, showConfirmButton: false });
            })
            .catch(error => {
                const msg = error.response?.data?.error || error.response?.data?.message || 'Error al guardar el documento';
                Swal.fire({ title: msg, icon: 'error' });
            });
    }
});

// ============================================================
// LISTENERS — Filtros de la tabla de personas
// ============================================================
document.getElementById("page-size-personas").addEventListener("change", function () {
    pageSizePersonas = parseInt(this.value);
    tblPersonas.setPageSize(pageSizePersonas);
    reloadTabla();
});

// Los indicadores siguen los filtros de sucursal, tipo y vigencia.
document.getElementById("sucursal").addEventListener("change", () => {
    reloadTabla();
    cargarIndicadores();
});
document.getElementById("tipo_per")?.addEventListener("change", () => {
    reloadTabla();
    cargarIndicadores();
});

document.getElementById("filtroVigencia")?.addEventListener("change", () => {
    reloadTabla();
    cargarIndicadores();
});

// Estos solo filtran la tabla visualmente:
document.getElementById("buscarPersonal").addEventListener("keyup", () => reloadTabla());
document.getElementById("filtroDJ")?.addEventListener("change", () => reloadTabla());
// Filtros combinados para el Modal de Folios (Select + Búsqueda)
document.getElementById('selectTipoFolio')?.addEventListener('change', filterTableByTipoFolio);
document.getElementById('buscarFolio')?.addEventListener('keyup', filterTableByTipoFolio);
document.getElementById('filtroTipoFolio')?.addEventListener('change', () => {
    ocultarFormularioCertificado();
    ocultarFormularioCUL();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
    ocultarFormularioDomicilio();
    ocultarFormularioEvaluacion();
    actualizarOpcionesFoliosPorTipo();
});
document.getElementById('filtroFolioTipo')?.addEventListener('change', actualizarFormularioSeleccionado);
document.getElementById('btn-abrir-agregar-folio')?.addEventListener('click', () => {
    mostrarSeleccionFormulario();
});
document.getElementById('btn-modificar-folio')?.addEventListener('click', () => {
    const titulo = document.getElementById('titulo-modal-agregar-folio');
    const folioActual = titulo.textContent.replace(/^Folio registrado:\s*/i, '').trim();
    snapshotEdicionFolio = obtenerValoresFormularioFolio();
    establecerFormularioSoloLectura(false);
    titulo.textContent = `Modificando folio: ${folioActual}`;
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.remove('hidden');
    const esEvaluacion = !document.getElementById('formulario-evaluacion-postulante').classList.contains('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.toggle('hidden', esEvaluacion);
});
document.getElementById('btn-volver-folio')?.addEventListener('click', () => {
    const valores = snapshotEdicionFolio;
    if (valores) aplicarValoresFormularioFolio(valores);

    const nombreFolio = valores?.nombreFolio || document.getElementById('certificado-folio-nombre').value;
    document.getElementById('titulo-modal-agregar-folio').textContent = `Folio registrado: ${nombreFolio}`;
    const legendId = valores?.cul ? 'formulario-cul-legend'
        : valores?.antecedentes ? 'formulario-antecedentes-legend'
            : valores?.covid ? 'formulario-vacuna-covid-legend'
                : valores?.cv ? 'formulario-cv-legend'
                    : valores?.domicilio ? 'dom-legend' : valores?.evaluacion ? 'evaluacion-legend' : 'formulario-folio-legend';
    document.getElementById(legendId).textContent = 'Datos registrados';
    establecerFormularioSoloLectura(true);
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    document.getElementById('btn-modificar-folio').classList.remove('hidden');
    snapshotEdicionFolio = null;
});
document.getElementById('btn-guardar-cambios-folio')?.addEventListener('click', () => {
    if (!claveFolioEnEdicion || !filaFolioEnEdicion) return;

    const formularioCulActivo = !document.getElementById('formulario-cul').classList.contains('hidden');
    const formularioAntecedentesActivo = !document.getElementById('formulario-antecedentes-policiales').classList.contains('hidden');
    const formularioCovidActivo = !document.getElementById('formulario-vacuna-covid').classList.contains('hidden');
    const formularioCvActivo = !document.getElementById('formulario-cv').classList.contains('hidden');
    const formularioDomicilioActivo = !document.getElementById('formulario-visita-domiciliaria').classList.contains('hidden');
    const prefijo = formularioCulActivo ? 'cul' : formularioAntecedentesActivo ? 'antecedentes' : formularioCovidActivo ? 'covid' : formularioCvActivo ? 'cv' : formularioDomicilioActivo ? 'dom' : 'certificado';
    const cambiosDomicilio = formularioDomicilioActivo ? obtenerValoresVisitaDomiciliaria() : null;
    const cambios = formularioDomicilioActivo ? cambiosDomicilio : formularioCovidActivo ? {
        entidad: document.getElementById('covid-entidad').value,
        dosis_1: document.getElementById('covid-dosis-1').value || null,
        dosis_2: document.getElementById('covid-dosis-2').value || null,
        dosis_3: document.getElementById('covid-dosis-3').value || null,
        dosis_4: document.getElementById('covid-dosis-4').value || null,
        fecha_emision: document.getElementById('covid-dosis-1').value || null,
        fecha_caducidad: null,
        observacion: document.getElementById('covid-observacion').value,
        positivo: document.querySelector('input[name="covid-apto"]:checked')?.value || null,
    } : formularioCvActivo ? {
        residencia: document.getElementById('cv-residencia').value,
        direccion: document.getElementById('cv-direccion').value,
        telefono: document.getElementById('cv-telefono').value,
        nivel_idiomas: document.querySelector('input[name="cv-idiomas"]:checked')?.value || null,
        nivel_computacion: document.querySelector('input[name="cv-computacion"]:checked')?.value || null,
        otras_capacidades: document.getElementById('cv-otras-capacidades').value,
        formacion_academica: leerColeccionCV('academica'),
        formacion_profesional: leerColeccionCV('profesional'),
        experiencia_laboral: leerColeccionCV('experiencia'),
    } : {
        entidad: document.getElementById(`${prefijo}-entidad`).value,
        fecha_emision: document.getElementById(`${prefijo}-fecha-emision`).value || null,
        fecha_caducidad: document.getElementById(`${prefijo}-fecha-caducidad`).value || null,
        observacion: document.getElementById(`${prefijo}-observacion`).value,
        positivo: formularioCulActivo
            ? document.querySelector('input[name="cul-sin-observaciones"]:checked')?.value || null
            : formularioAntecedentesActivo
                ? document.querySelector('input[name="antecedentes-registra"]:checked')?.value || null
                : document.querySelector('input[name="certificado-positivo"]:checked')?.value
                    || document.querySelector('input[name="certificado-apto"]:checked')?.value
                    || null,
    };
    if (formularioDomicilioActivo) {
        const fechaEmision = cambios.fecha_emision ? new Date(`${cambios.fecha_emision}T12:00:00`) : null;
        if (fechaEmision && !Number.isNaN(fechaEmision.getTime())) {
            fechaEmision.setFullYear(fechaEmision.getFullYear() + 1);
            cambios.fecha_caducidad = fechaEmision.toISOString().slice(0, 10);
        }
        Object.assign(cambios, { tipoFormularioDomicilio: true, nombreFolio: document.getElementById('dom-folio-nombre').value });
    }
    if (formularioCulActivo) cambios.sin_observaciones = cambios.positivo;
    if (formularioAntecedentesActivo) cambios.registra_antecedentes = cambios.positivo;
    // Estos cambios solo viven en memoria mientras la página siga abierta.
    cambiosTemporalesFolios.set(claveFolioEnEdicion, cambios);
    if (formularioAntecedentesActivo) actualizarMarcaVigenciaAntecedentes();
    filaFolioEnEdicion.update({
        fecha_emision: formatearFechaTabla(cambios.fecha_emision),
        fecha_caducidad: formatearFechaTabla(cambios.fecha_caducidad),
        vigente: formularioCvActivo || formularioCovidActivo ? 1 : cambios.fecha_caducidad && new Date(`${cambios.fecha_caducidad}T23:59:59`) >= new Date() ? 1 : 0,
    });

    const nombreFolio = document.getElementById(`${prefijo}-folio-nombre`).value;
    document.getElementById('titulo-modal-agregar-folio').textContent = `Folio registrado: ${nombreFolio}`;
    const leyenda = formularioCulActivo ? 'formulario-cul-legend'
        : formularioAntecedentesActivo ? 'formulario-antecedentes-legend'
            : formularioCovidActivo ? 'formulario-vacuna-covid-legend'
                : formularioCvActivo ? 'formulario-cv-legend'
                    : formularioDomicilioActivo ? 'dom-legend' : 'formulario-folio-legend';
    document.getElementById(leyenda).textContent = 'Datos registrados';
    establecerFormularioSoloLectura(true);
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    document.getElementById('btn-modificar-folio').classList.remove('hidden');
    snapshotEdicionFolio = null;
});
document.getElementById('btn-cancelar-agregar-folio')?.addEventListener('click', () => {
    cerrarFormularioYVolverAFolios();
});


const definicionesColeccionesCV = {
    academica: [
        { campo: 'descripcion', etiqueta: 'Descripción' },
        { campo: 'grado_academico', etiqueta: 'Grado académico' },
        { campo: 'fecha_inicio', etiqueta: 'Fecha inicio', tipo: 'date' },
        { campo: 'fecha_fin', etiqueta: 'Fecha fin', tipo: 'date' },
        { campo: 'lugar', etiqueta: 'Lugar' },
        { campo: 'vigencia', etiqueta: 'Vigencia', tipo: 'vigencia' },
    ],
    profesional: [
        { campo: 'curso_capacitacion', etiqueta: 'Curso / capacitación' },
        { campo: 'fecha', etiqueta: 'Fecha', tipo: 'date' },
        { campo: 'lugar', etiqueta: 'Lugar' },
        { campo: 'vigencia', etiqueta: 'Vigencia', tipo: 'vigencia' },
    ],
    experiencia: [
        { campo: 'empresa', etiqueta: 'Empresa' },
        { campo: 'cargo', etiqueta: 'Cargo' },
        { campo: 'fecha_inicio', etiqueta: 'Fecha inicio', tipo: 'date' },
        { campo: 'fecha_cese', etiqueta: 'Fecha cese', tipo: 'date' },
        { campo: 'motivo_cese', etiqueta: 'Motivo cese' },
        { campo: 'referencia', etiqueta: 'Referencia' },
        { campo: 'referencia_cargo', etiqueta: 'Referencia cargo' },
        { campo: 'referencia_correo', etiqueta: 'Referencia correo', tipo: 'email' },
        { campo: 'referencia_telefono', etiqueta: 'Referencia teléfono', tipo: 'tel' },
        { campo: 'vigencia', etiqueta: 'Vigencia', tipo: 'vigencia' },
    ],
};

document.getElementById('formulario-cv')?.addEventListener('click', event => {
    const botonToggle = event.target.closest('[data-cv-toggle]');
    if (botonToggle) {
        const panel = document.getElementById(botonToggle.getAttribute('aria-controls'));
        if (!panel) return;
        const expandido = botonToggle.getAttribute('aria-expanded') !== 'true';
        botonToggle.setAttribute('aria-expanded', String(expandido));
        panel.classList.toggle('hidden', !expandido);
        const chevron = botonToggle.querySelector('[data-cv-chevron]');
        if (chevron) chevron.textContent = expandido ? '▾' : '▸';
        return;
    }

    const botonAgregar = event.target.closest('[data-cv-add-row]');
    if (botonAgregar) {
        agregarFilaColeccionCV(botonAgregar.dataset.cvAddRow);
        return;
    }

    const botonQuitar = event.target.closest('[data-cv-remove-row]');
    if (botonQuitar) {
        botonQuitar.closest('tr')?.remove();
        renumerarColeccionCV(botonQuitar.dataset.cvRemoveRow);
        actualizarContadorColeccionCV(botonQuitar.dataset.cvRemoveRow);
    }
});

function actualizarContadorColeccionCV(nombre) {
    const tbody = document.getElementById(`cv-${nombre}-rows`);
    const fieldset = tbody?.closest('[data-cv-collection]');
    const contador = fieldset?.querySelector('[data-cv-count]');
    if (contador) contador.textContent = String(tbody.rows.length);
}

function agregarFilaColeccionCV(nombre, datos = {}) {
    const definicion = definicionesColeccionesCV[nombre];
    const tbody = document.getElementById(`cv-${nombre}-rows`);
    if (!definicion || !tbody) return;

    const fila = document.createElement('tr');
    fila.className = 'border-b border-gray-200';
    const numero = document.createElement('td');
    numero.className = 'border p-2 text-center';
    numero.dataset.cvRowNumber = '';
    fila.appendChild(numero);

    definicion.forEach(({ campo, etiqueta, tipo }) => {
        const celda = document.createElement('td');
        celda.className = 'border p-1';
        let control;
        if (tipo === 'vigencia') {
            control = document.createElement('select');
            control.className = 'form-select min-w-24';
            control.add(new Option('SI', 'SI'));
            control.add(new Option('NO', 'NO'));
            control.value = String(datos[campo] ?? 'SI').toUpperCase() === 'NO' ? 'NO' : 'SI';
        } else {
            control = document.createElement('input');
            control.type = tipo || 'text';
            control.className = 'form-input min-w-[9rem]';
            control.value = datos[campo] ?? '';
            control.setAttribute('aria-label', etiqueta);
        }
        control.dataset.cvField = campo;
        celda.appendChild(control);
        fila.appendChild(celda);
    });

    const acciones = document.createElement('td');
    acciones.className = 'border p-1 text-center';
    const quitar = document.createElement('button');
    quitar.type = 'button';
    quitar.dataset.cvRemoveRow = nombre;
    quitar.className = 'rounded border border-red-300 px-2 py-1 text-red-600 hover:bg-red-50';
    quitar.textContent = 'Quitar';
    acciones.appendChild(quitar);
    fila.appendChild(acciones);
    tbody.appendChild(fila);
    renumerarColeccionCV(nombre);
    actualizarContadorColeccionCV(nombre);
}

function renderizarColeccionCV(nombre, registros = []) {
    const tbody = document.getElementById(`cv-${nombre}-rows`);
    if (!tbody) return;
    tbody.replaceChildren();
    (Array.isArray(registros) ? registros : []).forEach(registro => agregarFilaColeccionCV(nombre, registro));
    actualizarContadorColeccionCV(nombre);
}

function renumerarColeccionCV(nombre) {
    const tbody = document.getElementById(`cv-${nombre}-rows`);
    if (!tbody) return;
    [...tbody.rows].forEach((fila, indice) => {
        fila.querySelector('[data-cv-row-number]').textContent = String(indice + 1);
    });
}

function leerColeccionCV(nombre) {
    const tbody = document.getElementById(`cv-${nombre}-rows`);
    if (!tbody) return [];
    return [...tbody.rows].map(fila => Object.fromEntries(
        [...fila.querySelectorAll('[data-cv-field]')].map(control => [control.dataset.cvField, control.value])
    ));
}

function establecerLecturaColeccionesCV(soloLectura) {
    Object.keys(definicionesColeccionesCV).forEach(nombre => {
        const tbody = document.getElementById(`cv-${nombre}-rows`);
        const fieldset = tbody?.closest('fieldset');
        if (!fieldset) return;
        fieldset.querySelectorAll('[data-cv-field]').forEach(control => {
            if (control.tagName === 'SELECT') control.disabled = soloLectura;
            else control.readOnly = soloLectura;
        });
        fieldset.querySelectorAll('[data-cv-add-row], [data-cv-remove-row]').forEach(button => {
            button.classList.toggle('hidden', soloLectura);
            button.disabled = soloLectura;
        });
    });
}

function actualizarVistaEntidad(select) {
    const contenedor = select.closest('[data-entidad-select]');
    if (!contenedor) return;
    const etiqueta = contenedor.querySelector('[data-entidad-label]');
    const opcion = select.selectedOptions[0];
    etiqueta.textContent = opcion?.value ? opcion.textContent.trim() : 'Seleccionar entidad';
    contenedor.querySelector('[data-entidad-trigger]').disabled = select.disabled;
}

function obtenerValoresFormularioFolio() {
    const cul = !document.getElementById('formulario-cul').classList.contains('hidden');
    const antecedentes = !document.getElementById('formulario-antecedentes-policiales').classList.contains('hidden');
    const covid = !document.getElementById('formulario-vacuna-covid').classList.contains('hidden');
    const cv = !document.getElementById('formulario-cv').classList.contains('hidden');
    const domicilio = !document.getElementById('formulario-visita-domiciliaria').classList.contains('hidden');
    const evaluacion = !document.getElementById('formulario-evaluacion-postulante').classList.contains('hidden');
    const prefijo = cul ? 'cul' : antecedentes ? 'antecedentes' : covid ? 'covid' : cv ? 'cv' : domicilio ? 'dom' : 'certificado';
    const radioName = cul ? 'cul-sin-observaciones' : antecedentes ? 'antecedentes-registra' : covid ? 'covid-apto' : 'certificado-positivo';
    const radioAlternativo = !cul && !antecedentes && !covid && !cv ? 'certificado-apto' : null;

    if (evaluacion) {
        const radio = nombre => document.querySelector(`input[name="evaluacion-${nombre}"]:checked`)?.value || null;
        return {
            evaluacion: true,
            nombreFolio: 'EVALUACION DEL POSTULANTE',
            fecha: document.getElementById('evaluacion-fecha').value,
            area_codigo: document.getElementById('evaluacion-area').value,
            cargo_codigo: document.getElementById('evaluacion-cargo').value,
            vacante: radio('vacante'),
            educacion: radio('educacion'),
            formacion: radio('formacion'),
            experiencia: radio('experiencia'),
            habilidad: radio('habilidad'),
            fortalezas: document.getElementById('evaluacion-fortalezas').value,
            debilidades: document.getElementById('evaluacion-debilidades').value,
            apto: radio('apto'),
            considera_otro_proceso: radio('considera'),
            motivo_otro_proceso: document.getElementById('evaluacion-considera-razon').value,
            evaluador_1: document.getElementById('evaluacion-evaluador-1').value,
            evaluador_2: document.getElementById('evaluacion-evaluador-2').value,
        };
    }

    const valores = {
        cul,
        antecedentes,
        covid,
        cv,
        domicilio,
        nombreFolio: document.getElementById(`${prefijo}-folio-nombre`).value,
    };
    if (domicilio) return { ...valores, ...obtenerValoresVisitaDomiciliaria() };
    if (covid) {
        return {
            ...valores,
            entidad: document.getElementById('covid-entidad').value,
            dosis_1: document.getElementById('covid-dosis-1').value,
            dosis_2: document.getElementById('covid-dosis-2').value,
            dosis_3: document.getElementById('covid-dosis-3').value,
            dosis_4: document.getElementById('covid-dosis-4').value,
            observacion: document.getElementById('covid-observacion').value,
            positivo: document.querySelector('input[name="covid-apto"]:checked')?.value || null,
        };
    }
    if (cv) {
        return {
            ...valores,
            residencia: document.getElementById('cv-residencia').value,
            direccion: document.getElementById('cv-direccion').value,
            telefono: document.getElementById('cv-telefono').value,
            nivel_idiomas: document.querySelector('input[name="cv-idiomas"]:checked')?.value || null,
            nivel_computacion: document.querySelector('input[name="cv-computacion"]:checked')?.value || null,
            otras_capacidades: document.getElementById('cv-otras-capacidades').value,
            formacion_academica: leerColeccionCV('academica'),
            formacion_profesional: leerColeccionCV('profesional'),
            experiencia_laboral: leerColeccionCV('experiencia'),
        };
    }
    return {
        ...valores,
        entidad: document.getElementById(`${prefijo}-entidad`).value,
        fecha_emision: document.getElementById(`${prefijo}-fecha-emision`).value,
        fecha_caducidad: document.getElementById(`${prefijo}-fecha-caducidad`).value,
        observacion: document.getElementById(`${prefijo}-observacion`).value,
        positivo: document.querySelector(`input[name="${radioName}"]:checked`)?.value
            || (radioAlternativo ? document.querySelector(`input[name="${radioAlternativo}"]:checked`)?.value : null)
            || null,
    };
}

function aplicarValoresFormularioFolio(valores) {
    if (valores.evaluacion) {
        document.getElementById('evaluacion-fecha').value = valores.fecha || '';
        document.getElementById('evaluacion-area').value = valores.area_codigo || '';
        document.getElementById('evaluacion-cargo').value = valores.cargo_codigo || '';
        document.getElementById('evaluacion-fortalezas').value = valores.fortalezas || '';
        document.getElementById('evaluacion-debilidades').value = valores.debilidades || '';
        document.getElementById('evaluacion-considera-razon').value = valores.motivo_otro_proceso || '';
        document.getElementById('evaluacion-evaluador-1').value = valores.evaluador_1 || '';
        document.getElementById('evaluacion-evaluador-2').value = valores.evaluador_2 || '';
        ['vacante', 'educacion', 'formacion', 'experiencia', 'habilidad', 'apto', 'considera'].forEach(nombre => {
            marcarRadio(`evaluacion-${nombre}`, valores[nombre === 'considera' ? 'considera_otro_proceso' : nombre]);
        });
        actualizarPuntajeEvaluacion();
        return;
    }
    const prefijo = valores.cul ? 'cul' : valores.antecedentes ? 'antecedentes' : valores.covid ? 'covid' : valores.cv ? 'cv' : valores.domicilio ? 'dom' : 'certificado';
    if (valores.domicilio) {
        aplicarValoresVisitaDomiciliaria(valores);
        return;
    }
    if (valores.cv) {
        document.getElementById('cv-residencia').value = valores.residencia || '';
        document.getElementById('cv-direccion').value = valores.direccion || '';
        document.getElementById('cv-telefono').value = valores.telefono || '';
        document.getElementById('cv-otras-capacidades').value = valores.otras_capacidades || '';
        renderizarColeccionCV('academica', valores.formacion_academica || []);
        renderizarColeccionCV('profesional', valores.formacion_profesional || []);
        renderizarColeccionCV('experiencia', valores.experiencia_laboral || []);
        ['cv-idiomas', 'cv-computacion'].forEach(nombre => {
            document.querySelectorAll(`input[name="${nombre}"]`).forEach(control => {
                const valor = nombre === 'cv-idiomas' ? valores.nivel_idiomas : valores.nivel_computacion;
                control.checked = control.value === valor;
            });
        });
        return;
    }
    if (valores.covid) {
        seleccionarEntidad('covid-entidad', valores.entidad);
        ['1', '2', '3', '4'].forEach(numero => {
            document.getElementById(`covid-dosis-${numero}`).value = valores[`dosis_${numero}`] || '';
        });
        document.getElementById('covid-observacion').value = valores.observacion || '';
        document.querySelectorAll('input[name="covid-apto"]').forEach(control => {
            control.checked = control.value === valores.positivo;
        });
        return;
    }
    seleccionarEntidad(`${prefijo}-entidad`, valores.entidad);
    document.getElementById(`${prefijo}-fecha-emision`).value = valores.fecha_emision || '';
    document.getElementById(`${prefijo}-fecha-caducidad`).value = valores.fecha_caducidad || '';
    document.getElementById(`${prefijo}-observacion`).value = valores.observacion || '';
    if (valores.antecedentes) actualizarMarcaVigenciaAntecedentes();

    const nombresRadio = valores.cul ? ['cul-sin-observaciones']
        : valores.antecedentes ? ['antecedentes-registra']
            : valores.cv ? [] : ['certificado-apto', 'certificado-positivo'];
    nombresRadio.forEach(nombre => {
        document.querySelectorAll(`input[name="${nombre}"]`).forEach(control => {
            control.checked = control.value === valores.positivo;
        });
    });
}

function seleccionarEntidad(idSelect, valor) {
    const select = document.getElementById(idSelect);
    if (!select) return;
    const buscado = normalizarTexto(valor);
    const opcion = [...select.options].find(item =>
        item.value == valor || (item.value && normalizarTexto(item.textContent) === buscado)
    );
    select.value = opcion?.value || '';
    actualizarVistaEntidad(select);
}

function inicializarSelectsEntidad() {
    document.querySelectorAll('[data-entidad-select]').forEach(contenedor => {
        const select = contenedor.querySelector('select');
        const menu = contenedor.querySelector('[data-entidad-menu]');
        const trigger = contenedor.querySelector('[data-entidad-trigger]');
        const busqueda = contenedor.querySelector('[data-entidad-busqueda]');
        const lista = contenedor.querySelector('[data-entidad-opciones]');

        [...select.options].filter(opcion => opcion.value).forEach(opcion => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'block w-full rounded px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none';
            boton.textContent = opcion.textContent.trim();
            boton.addEventListener('click', () => {
                select.value = opcion.value;
                actualizarVistaEntidad(select);
                menu.classList.add('hidden');
                busqueda.value = '';
                lista.querySelectorAll('button').forEach(item => item.classList.remove('hidden'));
            });
            lista.appendChild(boton);
        });

        trigger.addEventListener('click', () => {
            if (select.disabled) return;
            menu.classList.toggle('hidden');
            if (!menu.classList.contains('hidden')) {
                busqueda.value = '';
                lista.querySelectorAll('button').forEach(item => item.classList.remove('hidden'));
                busqueda.focus();
            }
        });
        busqueda.addEventListener('input', () => {
            const termino = normalizarTexto(busqueda.value);
            lista.querySelectorAll('button').forEach(item => {
                item.classList.toggle('hidden', !normalizarTexto(item.textContent).includes(termino));
            });
        });
        actualizarVistaEntidad(select);
    });

    document.addEventListener('click', event => {
        document.querySelectorAll('[data-entidad-select]').forEach(contenedor => {
            if (!contenedor.contains(event.target)) contenedor.querySelector('[data-entidad-menu]').classList.add('hidden');
        });
    });
}

inicializarSelectsEntidad();

async function cerrarFormularioYVolverAFolios() {
    const modalFormulario = document.getElementById('modal-agregar-folio');
    const modalFolios = document.getElementById('modal-folios-personal');

    if (window.HSOverlay) {
        const instanciaFormulario = window.HSOverlay.getInstance(modalFormulario, true);
        if (instanciaFormulario?.element) {
            await instanciaFormulario.element.close(true);
        } else {
            modalFormulario.classList.add('hidden');
        }

        window.HSOverlay.open(modalFolios);
        return;
    }

    modalFormulario.classList.add('hidden');
    modalFolios.classList.remove('hidden');
}

// ============================================================
// LISTENERS — Legajos
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const codPersonal = params.get('codPersonal');
    const nombre = params.get('nombre');
    if (codPersonal) abrirFoliosDesdeNotificacion(codPersonal, nombre);

    document.getElementById('cargos').addEventListener('change', () => getLegajos());
    document.getElementById('clientes').addEventListener('change', function () {
        getCargos(this.value);
    });

    document.getElementById('fecha_emision').addEventListener('change', function () {
        const codFolio = parseInt(document.getElementById('codFolioActual').value || '0');
        if (codFolio !== 25 && this.value) {
            const fechaCalculada = calcularFechaCaducidad(this.value);
            if (fechaCalculada) document.getElementById('fecha_caducidad').value = fechaCalculada;
        }
    });
});

window.addEventListener("sidebar-toggled", () => tblPersonas?.redraw(true));

// ============================================================
// FUNCIONES — Tabla e Indicadores
// ============================================================
function cargarIndicadores() {
    let codSucursal = document.getElementById("sucursal").value;
    if (!codSucursal || codSucursal === "-Seleccionar-" || codSucursal === "00") codSucursal = "0";

    // Los indicadores consideran los filtros de Sucursal, Tipo y Vigencia.
    const params = {
        codSucursal: codSucursal,
        tipo_per: document.getElementById("tipo_per")?.value || "TODOS",
        vigencia: document.getElementById("filtroVigencia")?.value || "",
        size: 99999, // Traemos todos para la matemática
        page: 1
    };

    axios.get(`${VITE_URL_APP}/get-personal-total`, { params })
        .then(response => {
            const data = response.data.data || [];

            const total = data.length;
            // 🔥 Ahora contamos en base a si están Vigentes en la empresa
            const vigentes = data.filter(d => d.PERS_VIGENCIA === 'SI').length;
            const cesados = total - vigentes;

            document.getElementById('countTotal').textContent = total;
            document.getElementById('countVigentes').textContent = vigentes;
            document.getElementById('countCesados').textContent = cesados;
            document.getElementById('barTotal').style.width = total ? '100%' : '0%';
            document.getElementById('barVigentes').style.width = total ? `${(vigentes / total) * 100}%` : '0%';
            document.getElementById('barCesados').style.width = total ? `${(cesados / total) * 100}%` : '0%';
        })
        .catch(error => console.error("Error al cargar indicadores:", error));
}

function reloadTabla() {
    const search = document.getElementById("buscarPersonal").value.trim();
    tblPersonas.setData(`${VITE_URL_APP}/get-personal-total`, { page: 1, size: pageSizePersonas });
    tblPersonas._ultimoFiltro = search;
    setTimeout(() => resaltarTexto(search), 10);
}

function resaltarTexto(valor) {
    tblPersonas.getRows().forEach(row => {
        row.getElement().querySelectorAll(".tabulator-cell").forEach((cell, i, cells) => {
            if (i === cells.length - 1) return;
            const text = cell.textContent;
            if (valor && text.toLowerCase().includes(valor)) {
                cell.innerHTML = text.replace(new RegExp(`(${valor})`, "gi"), "<span class='bg-warning/25'>$1</span>");
            } else {
                cell.innerHTML = text;
            }
        });
    });
}

function normalizarTexto(valor = '') {
    return String(valor)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toUpperCase();
}

function obtenerNombreCompleto(persona = {}) {
    const nombre = [persona.apellidos, persona.nombres]
        .filter(Boolean)
        .join(' ')
        .trim();
    const nombreCompleto = nombre || persona.personal || '';

    return nombreCompleto
        .toLowerCase()
        .split(/\s+/)
        .map(palabra => palabra.charAt(0).toUpperCase() + palabra.slice(1))
        .join(' ');
}

function obtenerNombresYApellidos(persona = {}) {
    const nombre = [persona.nombres, persona.apellidos]
        .filter(Boolean)
        .join(' ')
        .trim();
    const nombreCompleto = nombre || persona.personal || '';

    return nombreCompleto
        .toLowerCase()
        .split(/\s+/)
        .map(palabra => palabra.charAt(0).toUpperCase() + palabra.slice(1))
        .join(' ');
}

function mostrarSeleccionFormulario() {
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    filaFolioEnEdicion = null;
    claveFolioEnEdicion = null;
    document.getElementById('formulario-folio-legend').textContent = 'Datos del certificado';
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    document.getElementById('filtroTipoFolio').value = '';
    ocultarFormularioCertificado();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
    ocultarFormularioDomicilio();
    ocultarFormularioEvaluacion();
    establecerFormularioSoloLectura(false);
    document.getElementById('certificado-entidad').value = '';
    actualizarVistaEntidad(document.getElementById('certificado-entidad'));
    document.getElementById('certificado-fecha-emision').value = '';
    document.getElementById('certificado-fecha-caducidad').value = '';
    document.getElementById('certificado-observacion').value = '';
    document.querySelectorAll('input[name="certificado-apto"]').forEach(input => input.checked = false);
    document.querySelectorAll('input[name="certificado-positivo"]').forEach(input => input.checked = false);
    ocultarFormularioCUL();
    document.getElementById('cul-entidad').value = '';
    actualizarVistaEntidad(document.getElementById('cul-entidad'));
    document.getElementById('cul-fecha-emision').value = '';
    document.getElementById('cul-fecha-caducidad').value = '';
    document.getElementById('cul-observacion').value = '';
    document.querySelectorAll('input[name="cul-sin-observaciones"]').forEach(input => input.checked = false);
    ocultarFormularioAntecedentes();
    document.getElementById('antecedentes-entidad').value = '';
    actualizarVistaEntidad(document.getElementById('antecedentes-entidad'));
    document.getElementById('antecedentes-fecha-emision').value = '';
    document.getElementById('antecedentes-fecha-caducidad').value = '';
    document.getElementById('antecedentes-observacion').value = '';
    document.querySelectorAll('input[name="antecedentes-registra"]').forEach(input => input.checked = false);
    document.getElementById('covid-entidad').value = '';
    actualizarVistaEntidad(document.getElementById('covid-entidad'));
    ['covid-dosis-1', 'covid-dosis-2', 'covid-dosis-3', 'covid-dosis-4', 'covid-observacion'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.querySelectorAll('input[name="covid-apto"]').forEach(input => input.checked = false);
    document.getElementById('cv-folio-nombre').value = 'CURRICULUM VITAE DOCUMENTADO';
    document.getElementById('cv-nombre-personal').value = '';
    document.getElementById('cv-documento-personal').value = '';
    document.getElementById('cv-edad').value = '';
    document.getElementById('cv-residencia').value = '';
    document.getElementById('cv-direccion').value = '';
    document.getElementById('cv-telefono').value = '';
    document.getElementById('cv-otras-capacidades').value = '';
    document.getElementById('cv-usuario-registro').value = '';
    document.getElementById('cv-usuario-modificacion').value = '';
    document.getElementById('cv-fecha-registro').value = '';
    document.getElementById('cv-fecha-modificacion').value = '';
    document.querySelectorAll('input[name="cv-idiomas"], input[name="cv-computacion"]').forEach(input => input.checked = false);
    renderizarColeccionCV('academica', []);
    renderizarColeccionCV('profesional', []);
    renderizarColeccionCV('experiencia', []);
    ocultarFormularioCV();
    actualizarOpcionesFoliosPorTipo();
}

function mostrarFormularioCertificado(nombreFolio, esToxicologico = false) {
    const persona = personaSeleccionada || {};
    const usuario = usuarioActual?.usuario || usuarioActual?.USUARIO || '—';
    const fechaRegistro = new Date().toLocaleString('es-PE', { hour12: false });

    document.getElementById('certificado-nombre-personal').value = obtenerNombresYApellidos(persona);
    document.getElementById('certificado-documento-personal').value = persona.nroDoc || '';
    document.getElementById('certificado-folio-nombre').value = nombreFolio;
    const entidadPorDefecto = esToxicologico ? 'POLICLINICO "JESUS VIDA"' : 'CLINICA SOSMA SAC';
    seleccionarEntidad('certificado-entidad', entidadPorDefecto);
    document.getElementById('certificado-usuario-registro').value = usuario;
    document.getElementById('certificado-fecha-registro').value = fechaRegistro;
    document.getElementById('certificado-usuario-modificacion').value = '';
    document.getElementById('certificado-fecha-modificacion').value = '';
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    document.getElementById('formulario-folio-legend').textContent = 'Datos del certificado';
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    establecerFormularioSoloLectura(false);
    ocultarFormularioVacunaCovid();
    document.getElementById('certificado-apto-container').classList.toggle('hidden', esToxicologico);
    document.getElementById('certificado-positivo-container').classList.toggle('hidden', !esToxicologico);

    document.getElementById('formulario-certificado-examen-medico').classList.remove('hidden');
    ocultarFormularioCUL();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
}

function mostrarFormularioVacunaCovid(nombreFolio) {
    const persona = personaSeleccionada || {};
    const usuario = usuarioActual?.usuario || usuarioActual?.USUARIO || '—';
    const selectEntidad = document.getElementById('covid-entidad');
    const opcionEntidad = [...selectEntidad.options].find(opcion =>
        normalizarTexto(opcion.textContent).includes('MINISTERIO DE SALUD')
    );

    document.getElementById('covid-nombre-personal').value = obtenerNombresYApellidos(persona);
    document.getElementById('covid-documento-personal').value = persona.nroDoc || '';
    document.getElementById('covid-folio-nombre').value = nombreFolio;
    seleccionarEntidad('covid-entidad', opcionEntidad?.value || '');
    ['covid-dosis-1', 'covid-dosis-2', 'covid-dosis-3', 'covid-dosis-4'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('covid-observacion').value = '';
    document.querySelectorAll('input[name="covid-apto"]').forEach(control => { control.checked = control.value === 'SI'; });
    document.getElementById('covid-usuario-registro').value = usuario;
    document.getElementById('covid-fecha-registro').value = new Date().toLocaleString('es-PE', { hour12: false });
    document.getElementById('covid-usuario-modificacion').value = '';
    document.getElementById('covid-fecha-modificacion').value = '';
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    establecerFormularioSoloLectura(false);
    ocultarFormularioCertificado();
    ocultarFormularioCUL();
    ocultarFormularioAntecedentes();
    ocultarFormularioCV();
    document.getElementById('formulario-vacuna-covid').classList.remove('hidden');
}

function mostrarFormularioCUL(nombreFolio) {
    const persona = personaSeleccionada || {};
    const usuario = usuarioActual?.usuario || usuarioActual?.USUARIO || '—';

    document.getElementById('cul-nombre-personal').value = obtenerNombresYApellidos(persona);
    document.getElementById('cul-documento-personal').value = persona.nroDoc || '';
    document.getElementById('cul-folio-nombre').value = nombreFolio;
    const selectEntidad = document.getElementById('cul-entidad');
    const opcionMinisterio = [...selectEntidad.options].find(opcion =>
        normalizarTexto(opcion.textContent).includes('MINISTERIO DE TRABAJO Y PROMOCION DEL EMPLEO')
    );
    seleccionarEntidad('cul-entidad', opcionMinisterio?.value || '');
    document.getElementById('cul-fecha-emision').value = '';
    document.getElementById('cul-fecha-caducidad').value = '';
    document.getElementById('cul-observacion').value = '';
    document.querySelectorAll('input[name="cul-sin-observaciones"]').forEach(input => input.checked = false);
    document.getElementById('cul-usuario-registro').value = usuario;
    document.getElementById('cul-fecha-registro').value = new Date().toLocaleString('es-PE', { hour12: false });
    document.getElementById('cul-usuario-modificacion').value = '';
    document.getElementById('cul-fecha-modificacion').value = '';
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    establecerFormularioSoloLectura(false);
    ocultarFormularioCertificado();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
    document.getElementById('formulario-cul').classList.remove('hidden');
}

function mostrarFormularioAntecedentes(nombreFolio) {
    const persona = personaSeleccionada || {};
    const usuario = usuarioActual?.usuario || usuarioActual?.USUARIO || '—';
    const selectEntidad = document.getElementById('antecedentes-entidad');
    const folioNormalizado = normalizarTexto(nombreFolio);
    const esPenales = folioNormalizado === 'CERTIFICADO DE ANTECEDENTES PENALES';
    const esJudiciales = folioNormalizado === 'CERTIFICADO DE ANTECEDENTES JUDICIALES';
    const entidadPorDefecto = esPenales
        ? 'PODER JUDICIAL'
        : esJudiciales
            ? 'MINISTERIO DE TRABAJO Y PROMOCION DEL EMPLEO'
            : 'POLICIA NACIONAL DEL PERU';
    const opcionEntidad = [...selectEntidad.options].find(opcion =>
        normalizarTexto(opcion.textContent).includes(entidadPorDefecto)
    );

    document.getElementById('antecedentes-nombre-personal').value = obtenerNombresYApellidos(persona);
    document.getElementById('antecedentes-documento-personal').value = persona.nroDoc || '';
    document.getElementById('antecedentes-folio-nombre').value = nombreFolio;
    seleccionarEntidad('antecedentes-entidad', opcionEntidad?.value || '');
    document.getElementById('antecedentes-fecha-emision').value = '';
    document.getElementById('antecedentes-fecha-caducidad').value = '';
    actualizarMarcaVigenciaAntecedentes();
    document.getElementById('antecedentes-observacion').value = '';
    document.querySelectorAll('input[name="antecedentes-registra"]').forEach(input => input.checked = input.value === 'NO');
    document.getElementById('antecedentes-usuario-registro').value = usuario;
    document.getElementById('antecedentes-fecha-registro').value = new Date().toLocaleString('es-PE', { hour12: false });
    document.getElementById('antecedentes-usuario-modificacion').value = '';
    document.getElementById('antecedentes-fecha-modificacion').value = '';
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    establecerFormularioSoloLectura(false);
    ocultarFormularioCertificado();
    ocultarFormularioCUL();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
    document.getElementById('formulario-antecedentes-policiales').classList.remove('hidden');
}

function ocultarFormularioDomicilio() {
    document.getElementById('formulario-visita-domiciliaria')?.classList.add('hidden');
}

function llenarSelectVerificadores(verificadores = [], seleccionado = '') {
    const select = document.getElementById('dom-verificador');
    if (!select) return;
    select.innerHTML = '<option value="">Seleccionar responsable</option>';
    verificadores.forEach(verificador => {
        select.add(new Option(verificador.nombre, verificador.codigo));
    });
    if (seleccionado && ![...select.options].some(option => String(option.value) === String(seleccionado))) {
        select.add(new Option('Responsable registrado', seleccionado));
    }
    select.value = seleccionado ? String(seleccionado) : '';
}

function aplicarDatosVisitaDomiciliaria(registro = {}) {
    const valores = {
        'dom-fecha': fechaParaInput(registro.fecha_emision),
        'dom-nombre': registro.nombres_apellidos || obtenerNombresYApellidos(personaSeleccionada || {}),
        'dom-documento': registro.documento || personaSeleccionada?.nroDoc || '',
        'dom-cargo': registro.cargo_personal || '',
        'dom-domicilio': registro.domicilio || '',
        'dom-ubicacion': registro.ubicacion || '',
        'dom-telefono': registro.telefono || '',
        'dom-estado-civil': registro.estado_civil || '',
        'dom-conyuge-nombre': registro.conyuge_nombre || '',
        'dom-conyuge-profesion': registro.conyuge_profesion || '',
        'dom-conyuge-empresa': registro.conyuge_empresa || '',
        'dom-conyuge-telefono': registro.conyuge_telefono || '',
        'dom-conyuge-salud': registro.conyuge_salud || '',
        'dom-entorno': registro.entorno || '',
        'dom-otros-acceso': registro.otros_acceso || '',
        'dom-casa-telefono': registro.casa_telefono || '',
        'dom-enfermos': registro.enfermos || '',
        'dom-discapacitado': registro.discapacitado || '',
        'dom-drogas': registro.drogas || '',
        'dom-televisores': registro.televisores ?? '',
        'dom-vehiculos': registro.vehiculos ?? '',
        'dom-comodidades': registro.comodidades || '',
        'dom-bienes-familia': registro.bienes_familia || '',
        'dom-lider-social': registro.lider_social || '',
        'dom-actividades': registro.actividades || '',
        'dom-gusto-familia': registro.gusto_familia || '',
        'dom-conclusion-fortalezas': registro.conclusion_fortalezas || '',
        'dom-conclusion-riesgo': registro.conclusion_riesgo || '',
        'dom-usuario-registro': registro.usuario_registro || '',
        'dom-fecha-registro': fechaHoraParaVista(registro.fecha_registro),
        'dom-usuario-modificacion': registro.usuario_modificacion || '',
        'dom-fecha-modificacion': fechaHoraParaVista(registro.fecha_modificacion),
    };
    Object.entries(valores).forEach(([id, valor]) => {
        const control = document.getElementById(id);
        if (control) control.value = valor;
    });

    [
        ['dom-conyuge-comparte', registro.conyuge_comparte_domicilio],
        ['dom-vias', registro.vias_acceso],
        ['dom-iluminacion', registro.iluminacion],
        ['dom-vigilancia', registro.vigilancia],
        ['dom-material', registro.casa_material],
        ['dom-seguridad', registro.casa_segura],
        ['dom-espacio', registro.casa_espacio],
        ['dom-cocina', registro.cocina],
        ['dom-conocido', registro.conocido_vecinos],
    ].forEach(([nombre, valor]) => marcarRadio(nombre, valor));

    document.getElementById('dom-nevera').checked = ['1', 'SI', 'S'].includes(String(registro.nevera || '').toUpperCase());
    document.getElementById('dom-loza').checked = ['1', 'SI', 'S'].includes(String(registro.loza || '').toUpperCase());
    llenarSelectVerificadores(registro.verificadores || [], registro.verificador_codigo);
}

function obtenerValoresVisitaDomiciliaria() {
    const valor = id => document.getElementById(id)?.value || '';
    const radio = name => document.querySelector(`input[name="${name}"]:checked`)?.value || null;
    return {
        fecha_emision: valor('dom-fecha'),
        nombres_apellidos: valor('dom-nombre'),
        documento: valor('dom-documento'),
        cargo_personal: valor('dom-cargo'),
        domicilio_personal: valor('dom-domicilio'),
        ubicacion_personal: valor('dom-ubicacion'),
        telefono_personal: valor('dom-telefono'),
        estado_civil_personal: valor('dom-estado-civil'),
        conyuge_nombre: valor('dom-conyuge-nombre'),
        conyuge_profesion: valor('dom-conyuge-profesion'),
        conyuge_empresa: valor('dom-conyuge-empresa'),
        conyuge_telefono: valor('dom-conyuge-telefono'),
        conyuge_salud: valor('dom-conyuge-salud'),
        conyuge_comparte_domicilio: radio('dom-conyuge-comparte'),
        vias_acceso: radio('dom-vias'),
        iluminacion: radio('dom-iluminacion'),
        entorno: valor('dom-entorno'),
        vigilancia: radio('dom-vigilancia'),
        otros_acceso: valor('dom-otros-acceso'),
        casa_material: radio('dom-material'),
        casa_segura: radio('dom-seguridad'),
        casa_telefono: valor('dom-casa-telefono'),
        casa_espacio: radio('dom-espacio'),
        enfermos: valor('dom-enfermos'),
        discapacitado: valor('dom-discapacitado'),
        drogas: valor('dom-drogas'),
        nevera: document.getElementById('dom-nevera').checked ? '1' : '0',
        loza: document.getElementById('dom-loza').checked ? '1' : '0',
        cocina: radio('dom-cocina'),
        televisores: valor('dom-televisores'),
        vehiculos: valor('dom-vehiculos'),
        comodidades: valor('dom-comodidades'),
        bienes_familia: valor('dom-bienes-familia'),
        lider_social: valor('dom-lider-social'),
        conocido_vecinos: radio('dom-conocido'),
        actividades: valor('dom-actividades'),
        gusto_familia: valor('dom-gusto-familia'),
        conclusion_fortalezas: valor('dom-conclusion-fortalezas'),
        conclusion_riesgo: valor('dom-conclusion-riesgo'),
        verificador_codigo: valor('dom-verificador'),
        usuario_registro: valor('dom-usuario-registro'),
        fecha_registro: valor('dom-fecha-registro'),
        usuario_modificacion: valor('dom-usuario-modificacion'),
        fecha_modificacion: valor('dom-fecha-modificacion'),
    };
}

function aplicarValoresVisitaDomiciliaria(valores = {}) {
    aplicarDatosVisitaDomiciliaria({
        ...valores,
        domicilio: valores.domicilio_personal,
        ubicacion: valores.ubicacion_personal,
        telefono: valores.telefono_personal,
        estado_civil: valores.estado_civil_personal,
    });
}

function mostrarFormularioDomicilio(nombreFolio) {
    const persona = personaSeleccionada || {};
    const usuario = usuarioActual?.usuario || usuarioActual?.USUARIO || '—';
    const hoy = new Date();
    document.getElementById('dom-legend').textContent = 'Verificación domiciliaria';
    document.querySelectorAll('#formulario-visita-domiciliaria input:not([readonly]), #formulario-visita-domiciliaria textarea')
        .forEach(control => {
            if (control.type === 'checkbox' || control.type === 'radio') control.checked = false;
            else control.value = '';
        });
    document.getElementById('dom-verificador').innerHTML = '<option value="">Seleccionar responsable</option>';
    document.getElementById('dom-folio-nombre').value = nombreFolio;
    aplicarDatosVisitaDomiciliaria({
        fecha_emision: hoy.toISOString().slice(0, 10),
        nombres_apellidos: obtenerNombresYApellidos(persona),
        documento: persona.nroDoc || '',
        usuario_registro: usuario,
        fecha_registro: hoy,
    });
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    establecerFormularioSoloLectura(false);
    ocultarFormularioCertificado();
    ocultarFormularioCUL();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
    document.getElementById('formulario-visita-domiciliaria').classList.remove('hidden');

    const codPersonal = persona.CODI_PERS || document.getElementById('codPersonal').value;
    axios.get(`${VITE_URL_APP}/api/cv-personal/${encodeURIComponent(codPersonal)}`)
        .then(({ data }) => {
            if (document.getElementById('formulario-visita-domiciliaria').classList.contains('hidden')
                || String(document.getElementById('codPersonal').value) !== String(codPersonal)) return;
            aplicarDatosVisitaDomiciliaria({
                ...Object.fromEntries(Object.entries({
                    fecha_emision: document.getElementById('dom-fecha').value,
                    usuario_registro: usuario,
                    fecha_registro: hoy,
                    verificadores: [],
                })),
                nombres_apellidos: data.nombres_apellidos,
                documento: data.documento,
                cargo_personal: data.cargo,
                domicilio: data.direccion,
                ubicacion: data.residencia,
                telefono: data.telefono,
                estado_civil: data.estado_civil,
                conyuge_nombre: data.conyuge_nombre,
            });
        })
        .catch(error => console.error('No se pudieron cargar los datos personales de la visita:', error));
    axios.get(`${VITE_URL_APP}/api/get-verificadores-domiciliarios`)
        .then(({ data }) => llenarSelectVerificadores(data || []))
        .catch(error => console.error('No se pudieron cargar los responsables de visita:', error));
}

function mostrarFormularioCV(nombreFolio) {
    const persona = personaSeleccionada || {};
    const usuario = usuarioActual?.usuario || usuarioActual?.USUARIO || '—';
    const hoy = new Date();
    const fechaNac = persona.FECH_NACI || persona.fechaNacimiento || persona.fecha_nacimiento;
    const edad = fechaNac ? Math.floor((hoy - new Date(fechaNac)) / 31557600000) : '';

    document.getElementById('cv-folio-nombre').value = nombreFolio;
    document.getElementById('cv-nombre-personal').value = obtenerNombresYApellidos(persona);
    document.getElementById('cv-documento-personal').value = persona.nroDoc || persona.NRO_DOCU_IDEN || '';
    document.getElementById('cv-edad').value = edad;
    document.getElementById('cv-residencia').value = [persona.DEPARTAMENTO, persona.PROVINCIA, persona.DISTRITO].filter(Boolean).join(' / ');
    document.getElementById('cv-direccion').value = persona.DIRECCION || persona.PERS_DIREC_DNI || '';
    document.getElementById('cv-telefono').value = persona.PERS_TELEFONO || '';
    document.querySelectorAll('input[name="cv-idiomas"]').forEach(input => input.checked = input.value === 'N');
    document.querySelectorAll('input[name="cv-computacion"]').forEach(input => input.checked = input.value === 'N');
    document.getElementById('cv-otras-capacidades').value = '';
    document.getElementById('cv-usuario-registro').value = usuario;
    document.getElementById('cv-usuario-modificacion').value = '';
    document.getElementById('cv-fecha-registro').value = hoy.toLocaleString('es-PE', { hour12: false });
    document.getElementById('cv-fecha-modificacion').value = '';
    renderizarColeccionCV('academica', []);
    renderizarColeccionCV('profesional', []);
    renderizarColeccionCV('experiencia', []);
    document.getElementById('titulo-modal-agregar-folio').textContent = 'Crear formulario';
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    snapshotEdicionFolio = null;
    document.getElementById('filtroTipoFolio').disabled = false;
    document.getElementById('filtroFolioTipo').disabled = false;
    establecerFormularioSoloLectura(false);
    ocultarFormularioCertificado();
    ocultarFormularioCUL();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    document.getElementById('formulario-cv').classList.remove('hidden');

    const codPersonal = persona.CODI_PERS || document.getElementById('codPersonal').value;
    axios.get(`${VITE_URL_APP}/api/cv-personal/${encodeURIComponent(codPersonal)}`)
        .then(({ data }) => {
            if (document.getElementById('formulario-cv').classList.contains('hidden')
                || String(document.getElementById('codPersonal').value) !== String(codPersonal)) return;
            document.getElementById('cv-nombre-personal').value = data.nombres_apellidos || document.getElementById('cv-nombre-personal').value;
            document.getElementById('cv-documento-personal').value = data.documento || document.getElementById('cv-documento-personal').value;
            document.getElementById('cv-edad').value = data.edad ?? '';
            document.getElementById('cv-residencia').value = data.residencia || '';
            document.getElementById('cv-direccion').value = data.direccion || '';
            document.getElementById('cv-telefono').value = data.telefono || '';
        })
        .catch(error => console.error('No se pudieron cargar los datos personales del CV:', error));
}

function actualizarMarcaVigenciaAntecedentes() {
    const fechaCaducidad = document.getElementById('antecedentes-fecha-caducidad').value;
    const vencido = fechaCaducidad && new Date(`${fechaCaducidad}T23:59:59`) < new Date();
    document.getElementById('antecedentes-no-vigente').classList.toggle('hidden', !vencido);
}

document.getElementById('antecedentes-fecha-caducidad')?.addEventListener('change', actualizarMarcaVigenciaAntecedentes);

function ocultarFormularioCertificado() {
    document.getElementById('formulario-certificado-examen-medico').classList.add('hidden');
}

function ocultarFormularioCUL() {
    document.getElementById('formulario-cul').classList.add('hidden');
}

function ocultarFormularioAntecedentes() {
    document.getElementById('formulario-antecedentes-policiales').classList.add('hidden');
}

function ocultarFormularioVacunaCovid() {
    document.getElementById('formulario-vacuna-covid').classList.add('hidden');
}

function ocultarFormularioCV() {
    document.getElementById('formulario-cv').classList.add('hidden');
}

function establecerFormularioSoloLectura(soloLectura) {
    document.querySelectorAll('#formulario-evaluacion-postulante input, #formulario-evaluacion-postulante select, #formulario-evaluacion-postulante textarea')
        .forEach(control => {
            if (['evaluacion-personal', 'evaluacion-documento', 'evaluacion-estado-civil', 'evaluacion-edad', 'evaluacion-hijos', 'evaluacion-lugar-nacimiento', 'evaluacion-telefono', 'evaluacion-puntaje', 'evaluacion-promedio', 'evaluacion-usuario-registro', 'evaluacion-fecha-registro', 'evaluacion-usuario-modificacion', 'evaluacion-fecha-modificacion'].includes(control.id)) return;
            if (control.type === 'radio' || control.tagName === 'SELECT') control.disabled = soloLectura;
            else control.readOnly = soloLectura;
        });
    document.querySelectorAll('#formulario-visita-domiciliaria input, #formulario-visita-domiciliaria select, #formulario-visita-domiciliaria textarea')
        .forEach(control => {
            if (['dom-nombre', 'dom-documento', 'dom-cargo', 'dom-domicilio', 'dom-ubicacion', 'dom-telefono', 'dom-estado-civil', 'dom-usuario-registro', 'dom-fecha-registro', 'dom-usuario-modificacion', 'dom-fecha-modificacion'].includes(control.id)) return;
            if (control.type === 'radio' || control.type === 'checkbox' || control.tagName === 'SELECT') control.disabled = soloLectura;
            else control.readOnly = soloLectura;
        });
    ['certificado-fecha-emision', 'certificado-fecha-caducidad', 'certificado-observacion']
        .forEach(id => {
            const control = document.getElementById(id);
            control.readOnly = soloLectura;
        });
    document.getElementById('certificado-entidad').disabled = soloLectura;
    actualizarVistaEntidad(document.getElementById('certificado-entidad'));
    document.querySelectorAll('input[name="certificado-apto"], input[name="certificado-positivo"]')
        .forEach(control => control.disabled = soloLectura);
    ['cul-fecha-emision', 'cul-fecha-caducidad', 'cul-observacion']
        .forEach(id => document.getElementById(id).readOnly = soloLectura);
    document.getElementById('cul-entidad').disabled = soloLectura;
    actualizarVistaEntidad(document.getElementById('cul-entidad'));
    document.querySelectorAll('input[name="cul-sin-observaciones"]')
        .forEach(control => control.disabled = soloLectura);
    ['antecedentes-fecha-emision', 'antecedentes-fecha-caducidad', 'antecedentes-observacion']
        .forEach(id => document.getElementById(id).readOnly = soloLectura);
    document.getElementById('antecedentes-entidad').disabled = soloLectura;
    actualizarVistaEntidad(document.getElementById('antecedentes-entidad'));
    document.querySelectorAll('input[name="antecedentes-registra"]')
        .forEach(control => control.disabled = soloLectura);
    ['covid-dosis-1', 'covid-dosis-2', 'covid-dosis-3', 'covid-dosis-4', 'covid-observacion']
        .forEach(id => document.getElementById(id).readOnly = soloLectura);
    document.getElementById('covid-entidad').disabled = soloLectura;
    actualizarVistaEntidad(document.getElementById('covid-entidad'));
    document.querySelectorAll('input[name="covid-apto"]')
        .forEach(control => control.disabled = soloLectura);
    ['cv-residencia', 'cv-direccion', 'cv-telefono', 'cv-otras-capacidades']
        .forEach(id => document.getElementById(id).readOnly = soloLectura);
    document.querySelectorAll('input[name="cv-idiomas"], input[name="cv-computacion"]')
        .forEach(control => control.disabled = soloLectura);
    establecerLecturaColeccionesCV(soloLectura);
}

function fechaParaInput(valor) {
    return valor ? String(valor).slice(0, 10) : '';
}

function formatearFechaTabla(valor) {
    if (!valor) return null;
    const [anio, mes, dia] = String(valor).slice(0, 10).split('-');
    return anio && mes && dia ? `${dia}/${mes}/${anio}` : valor;
}

function claveTemporalFolio(folio) {
    return [folio.codPersonal, folio.codFolio, folio.codRequisito].join('|');
}

function ocultarFormularioEvaluacion() {
    document.getElementById('formulario-evaluacion-postulante')?.classList.add('hidden');
}

function mostrarFormularioEvaluacion(nombreFolio) {
    ocultarFormularioCertificado();
    ocultarFormularioCUL();
    ocultarFormularioAntecedentes();
    ocultarFormularioVacunaCovid();
    ocultarFormularioCV();
    ocultarFormularioDomicilio();
    ocultarFormularioEvaluacion();

    const form = document.getElementById('formulario-evaluacion-postulante');
    form.querySelectorAll('input:not([readonly]), textarea, select').forEach(control => {
        if (control.type === 'radio') control.checked = false;
        else if (control.tagName === 'SELECT') control.selectedIndex = 0;
        else control.value = '';
    });
    const hoy = new Date();
    document.getElementById('evaluacion-fecha').value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
    document.getElementById('evaluacion-legend').textContent = 'Evaluación del postulante';
    ['evaluacion-usuario-registro', 'evaluacion-fecha-registro', 'evaluacion-usuario-modificacion', 'evaluacion-fecha-modificacion']
        .forEach(id => document.getElementById(id).value = '');
    document.getElementById('titulo-modal-agregar-folio').textContent = `Crear formulario: ${nombreFolio}`;
    document.getElementById('formulario-evaluacion-postulante').classList.remove('hidden');
    document.getElementById('btn-modificar-folio').classList.add('hidden');
    document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
    establecerFormularioSoloLectura(false);
    actualizarPuntajeEvaluacion();

    const persona = personaSeleccionada || {};
    const codPersonal = persona.CODI_PERS || document.getElementById('codPersonal')?.value;
    document.getElementById('evaluacion-personal').value = obtenerNombresYApellidos(persona);
    document.getElementById('evaluacion-documento').value = persona.nroDoc || persona.NRO_DOCU_IDEN || '';
    if (!codPersonal) return;
    axios.get(`${VITE_URL_APP}/api/evaluacion-postulante/personal/${encodeURIComponent(codPersonal)}`)
        .then(({ data }) => aplicarDatosPersonalesEvaluacion(data))
        .catch(error => Swal.fire({ title: 'No se pudieron cargar los datos personales', text: error.response?.data?.message || 'Intenta nuevamente.', icon: 'error' }));
}

function aplicarDatosPersonalesEvaluacion(datos = {}) {
    document.getElementById('evaluacion-personal').value = datos.nombres_apellidos || document.getElementById('evaluacion-personal').value;
    document.getElementById('evaluacion-documento').value = datos.documento || document.getElementById('evaluacion-documento').value;
    document.getElementById('evaluacion-estado-civil').value = datos.estado_civil || '';
    document.getElementById('evaluacion-edad').value = datos.edad ?? '';
    document.getElementById('evaluacion-hijos').value = datos.numero_hijos ?? '';
    document.getElementById('evaluacion-lugar-nacimiento').value = datos.lugar_nacimiento || '';
    document.getElementById('evaluacion-telefono').value = datos.telefono || '';
}

function actualizarPuntajeEvaluacion() {
    const ids = ['educacion', 'formacion', 'experiencia', 'habilidad'];
    const values = ids.map(campo => document.querySelector(`input[name="evaluacion-${campo}"]:checked`)?.value);
    const total = values.reduce((suma, valor) => suma + (Number(valor) || 0), 0);
    document.getElementById('evaluacion-puntaje').value = total;
    document.getElementById('evaluacion-promedio').value = (total / 4).toFixed(2);
}

document.querySelectorAll('#formulario-evaluacion-postulante input[type="radio"]').forEach(control => {
    control.addEventListener('change', actualizarPuntajeEvaluacion);
});

function fechaHoraParaVista(valor) {
    if (!valor) return '';
    const fecha = new Date(String(valor).replace(' ', 'T'));
    return Number.isNaN(fecha.getTime()) ? String(valor) : fecha.toLocaleString('es-PE', { hour12: false });
}

function marcarRadio(nombre, valor) {
    const normalizado = normalizarTexto(valor);
    const controles = [...document.querySelectorAll(`input[name="${nombre}"]`)];
    const coincidenciaExacta = controles.find(control => normalizarTexto(control.value) === normalizado);
    if (coincidenciaExacta) {
        coincidenciaExacta.checked = true;
        return;
    }
    const valorRadio = ['S', 'SI', 'POSITIVO', 'APTO'].includes(normalizado)
        ? 'SI'
        : ['N', 'NO', 'NEGATIVO', 'NO APTO'].includes(normalizado)
            ? 'NO'
            : normalizado;
    const radio = controles
        .find(control => normalizarTexto(control.value) === valorRadio);
    if (radio) radio.checked = true;
}

async function abrirFolioRegistrado(folio, fila = null) {
    const nombreFolio = normalizarTexto(folio.documento);
    const esMedico = nombreFolio === 'CERTIFICADO DE EXAMEN MEDICO';
    const esToxicologico = nombreFolio === 'CERTIFICADO DE EXAMEN TOXICOLOGICO EXTERNO';
    const esCUL = nombreFolio === 'CERTIFICADO UNICO LABORAL - CUL';
    const esAntecedentesPoliciales = nombreFolio === 'CERTIFICADO DE ANTECEDENTES POLICIALES';
    const esAntecedentesJudiciales = nombreFolio === 'CERTIFICADO DE ANTECEDENTES JUDICIALES';
    const esAntecedentesPenales = nombreFolio === 'CERTIFICADO DE ANTECEDENTES PENALES';
    const esAntecedentes = esAntecedentesPoliciales || esAntecedentesJudiciales || esAntecedentesPenales;
    const esVacunaCovid = nombreFolio === 'CARNET DE VACUNA COVID';
    const esCV = nombreFolio === 'CURRICULUM VITAE DOCUMENTADO';
    const esDomicilio = nombreFolio === 'VERIFICACION DOMICILIARIA';
    const esEvaluacion = nombreFolio === 'EVALUACION DEL POSTULANTE';

    if (!esMedico && !esToxicologico && !esCUL && !esAntecedentes && !esVacunaCovid && !esCV && !esDomicilio && !esEvaluacion) {
        Swal.fire({
            title: 'Formulario pendiente de configurar',
            text: `Todavía no está definido el formulario para ${folio.documento}.`,
            icon: 'info'
        });
        return;
    }

    if (!folio.codRequisito) {
        Swal.fire({ title: 'No se encontró la relación del folio', icon: 'info' });
        return;
    }

    try {
        const url = `${VITE_URL_APP}/api/get-folio-registrado/${encodeURIComponent(folio.codPersonal)}/${encodeURIComponent(folio.codFolio)}/${encodeURIComponent(folio.codRequisito)}`;
        const { data: registro } = await axios.get(url);
        const claveTemporal = claveTemporalFolio(folio);
        const cambiosTemporales = cambiosTemporalesFolios.get(claveTemporal);
        if (cambiosTemporales) Object.assign(registro, cambiosTemporales);

        // Reutiliza el modal de creación: no abre una ventana adicional.
        document.getElementById('btn-abrir-agregar-folio').click();
        filaFolioEnEdicion = fila;
        claveFolioEnEdicion = claveTemporal;
        const selectTipo = document.getElementById('filtroTipoFolio');
        selectTipo.value = registro.tipo_codigo;
        await actualizarOpcionesFoliosPorTipo(registro.cod_folio);

        const mostrarApto = esMedico;
        const mostrarResultado = esToxicologico;

        ocultarFormularioCertificado();
        ocultarFormularioCUL();
        ocultarFormularioAntecedentes();
        ocultarFormularioVacunaCovid();
        ocultarFormularioCV();
        ocultarFormularioDomicilio();
        ocultarFormularioEvaluacion();
        if (esCUL) {
            document.getElementById('cul-nombre-personal').value = obtenerNombresYApellidos(personaSeleccionada || {});
            document.getElementById('cul-documento-personal').value = personaSeleccionada?.nroDoc || '';
            document.getElementById('cul-folio-nombre').value = registro.folio_nombre || folio.documento;
            const selectEntidadCUL = document.getElementById('cul-entidad');
            const opcionEntidadCUL = [...selectEntidadCUL.options].find(opcion =>
                opcion.value == registro.entidad ||
                opcion.value == registro.cod_institucion ||
                normalizarTexto(opcion.textContent) === normalizarTexto(registro.entidad)
            );
            seleccionarEntidad('cul-entidad', opcionEntidadCUL?.value || '');
            document.getElementById('cul-fecha-emision').value = fechaParaInput(registro.fecha_emision);
            document.getElementById('cul-fecha-caducidad').value = fechaParaInput(registro.fecha_caducidad);
            document.getElementById('cul-observacion').value = registro.observacion || registro.nota || '';
            document.getElementById('cul-usuario-registro').value = registro.usuario_registro || '';
            document.getElementById('cul-fecha-registro').value = fechaHoraParaVista(registro.fecha_registro);
            document.getElementById('cul-usuario-modificacion').value = registro.usuario_modificacion || '';
            document.getElementById('cul-fecha-modificacion').value = fechaHoraParaVista(registro.fecha_modificacion);
            document.querySelectorAll('input[name="cul-sin-observaciones"]').forEach(control => control.checked = false);
            marcarRadio('cul-sin-observaciones', registro.sin_observaciones ?? registro.positivo ?? (registro.observacion || registro.nota ? 'NO' : 'SI'));
            document.getElementById('formulario-cul-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-cul').classList.remove('hidden');
        } else if (esAntecedentes) {
            document.getElementById('antecedentes-nombre-personal').value = obtenerNombresYApellidos(personaSeleccionada || {});
            document.getElementById('antecedentes-documento-personal').value = personaSeleccionada?.nroDoc || '';
            document.getElementById('antecedentes-folio-nombre').value = registro.folio_nombre || folio.documento;
            const selectEntidadAntecedentes = document.getElementById('antecedentes-entidad');
            const opcionEntidadAntecedentes = [...selectEntidadAntecedentes.options].find(opcion =>
                opcion.value == registro.entidad ||
                opcion.value == registro.cod_institucion ||
                normalizarTexto(opcion.textContent) === normalizarTexto(registro.entidad)
            );
            seleccionarEntidad('antecedentes-entidad', opcionEntidadAntecedentes?.value || '');
            document.getElementById('antecedentes-fecha-emision').value = fechaParaInput(registro.fecha_emision);
            document.getElementById('antecedentes-fecha-caducidad').value = fechaParaInput(registro.fecha_caducidad);
            actualizarMarcaVigenciaAntecedentes();
            document.getElementById('antecedentes-observacion').value = registro.observacion || registro.nota || '';
            document.getElementById('antecedentes-usuario-registro').value = registro.usuario_registro || '';
            document.getElementById('antecedentes-fecha-registro').value = fechaHoraParaVista(registro.fecha_registro);
            document.getElementById('antecedentes-usuario-modificacion').value = registro.usuario_modificacion || '';
            document.getElementById('antecedentes-fecha-modificacion').value = fechaHoraParaVista(registro.fecha_modificacion);
            document.querySelectorAll('input[name="antecedentes-registra"]').forEach(control => control.checked = false);
            marcarRadio('antecedentes-registra', registro.registra_antecedentes ?? registro.positivo);
            document.getElementById('formulario-antecedentes-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-antecedentes-policiales').classList.remove('hidden');
        } else if (esVacunaCovid) {
            document.getElementById('covid-nombre-personal').value = obtenerNombresYApellidos(personaSeleccionada || {});
            document.getElementById('covid-documento-personal').value = personaSeleccionada?.nroDoc || '';
            document.getElementById('covid-folio-nombre').value = registro.folio_nombre || folio.documento;
            const selectEntidadCovid = document.getElementById('covid-entidad');
            const opcionEntidadCovid = [...selectEntidadCovid.options].find(opcion =>
                opcion.value == registro.entidad || opcion.value == registro.cod_institucion
                || normalizarTexto(opcion.textContent) === normalizarTexto(registro.entidad)
            );
            seleccionarEntidad('covid-entidad', opcionEntidadCovid?.value || '');
            document.getElementById('covid-dosis-1').value = fechaParaInput(registro.dosis_1 || registro.fecha_dosis_1 || registro.fecha_emision);
            document.getElementById('covid-dosis-2').value = fechaParaInput(registro.dosis_2 || registro.fecha_dosis_2);
            document.getElementById('covid-dosis-3').value = fechaParaInput(registro.dosis_3 || registro.fecha_dosis_3);
            document.getElementById('covid-dosis-4').value = fechaParaInput(registro.dosis_4 || registro.fecha_dosis_4);
            document.getElementById('covid-observacion').value = registro.observacion || registro.nota || '';
            document.getElementById('covid-usuario-registro').value = registro.usuario_registro || '';
            document.getElementById('covid-fecha-registro').value = fechaHoraParaVista(registro.fecha_registro);
            document.getElementById('covid-usuario-modificacion').value = registro.usuario_modificacion || '';
            document.getElementById('covid-fecha-modificacion').value = fechaHoraParaVista(registro.fecha_modificacion);
            document.querySelectorAll('input[name="covid-apto"]').forEach(control => { control.checked = false; });
            marcarRadio('covid-apto', registro.apto ?? registro.positivo);
            document.getElementById('formulario-vacuna-covid-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-vacuna-covid').classList.remove('hidden');
        } else if (esDomicilio) {
            document.getElementById('dom-folio-nombre').value = registro.folio_nombre || folio.documento;
            aplicarDatosVisitaDomiciliaria(registro);
            document.getElementById('dom-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-visita-domiciliaria').classList.remove('hidden');
        } else if (esEvaluacion) {
            aplicarDatosPersonalesEvaluacion(registro);
            document.getElementById('evaluacion-fecha').value = fechaParaInput(registro.fecha_emision);
            document.getElementById('evaluacion-area').value = registro.area_codigo || '';
            document.getElementById('evaluacion-cargo').value = registro.cargo_codigo || '';
            document.getElementById('evaluacion-fortalezas').value = registro.fortalezas || '';
            document.getElementById('evaluacion-debilidades').value = registro.debilidades || '';
            document.getElementById('evaluacion-considera-razon').value = registro.motivo_otro_proceso || '';
            document.getElementById('evaluacion-evaluador-1').value = registro.evaluador_1 || '';
            document.getElementById('evaluacion-evaluador-2').value = registro.evaluador_2 || '';
            marcarRadio('evaluacion-vacante', registro.vacante);
            marcarRadio('evaluacion-educacion', registro.educacion);
            marcarRadio('evaluacion-formacion', registro.formacion);
            marcarRadio('evaluacion-experiencia', registro.experiencia);
            marcarRadio('evaluacion-habilidad', registro.habilidad);
            marcarRadio('evaluacion-apto', registro.apto);
            marcarRadio('evaluacion-considera', registro.considera_otro_proceso);
            document.getElementById('evaluacion-usuario-registro').value = registro.usuario_registro || '';
            document.getElementById('evaluacion-fecha-registro').value = fechaHoraParaVista(registro.fecha_registro);
            document.getElementById('evaluacion-usuario-modificacion').value = registro.usuario_modificacion || '';
            document.getElementById('evaluacion-fecha-modificacion').value = fechaHoraParaVista(registro.fecha_modificacion);
            document.getElementById('evaluacion-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-evaluacion-postulante').classList.remove('hidden');
            actualizarPuntajeEvaluacion();
        } else if (esCV) {
            document.getElementById('cv-folio-nombre').value = registro.folio_nombre || folio.documento;
            document.getElementById('cv-nombre-personal').value = registro.nombres_apellidos || obtenerNombresYApellidos(personaSeleccionada || {});
            document.getElementById('cv-documento-personal').value = registro.documento || personaSeleccionada?.nroDoc || '';
            document.getElementById('cv-edad').value = registro.edad ?? '';
            document.getElementById('cv-residencia').value = registro.residencia || '';
            document.getElementById('cv-direccion').value = registro.direccion || '';
            document.getElementById('cv-telefono').value = registro.telefono || '';
            document.getElementById('cv-otras-capacidades').value = registro.otras_capacidades || '';
            document.getElementById('cv-usuario-registro').value = registro.usuario_registro || '';
            document.getElementById('cv-usuario-modificacion').value = registro.usuario_modificacion || '';
            document.getElementById('cv-fecha-registro').value = fechaHoraParaVista(registro.fecha_registro);
            document.getElementById('cv-fecha-modificacion').value = fechaHoraParaVista(registro.fecha_modificacion);
            marcarRadio('cv-idiomas', registro.nivel_idiomas);
            marcarRadio('cv-computacion', registro.nivel_computacion);
            renderizarColeccionCV('academica', registro.formacion_academica || []);
            renderizarColeccionCV('profesional', registro.formacion_profesional || []);
            renderizarColeccionCV('experiencia', registro.experiencia_laboral || []);
            document.getElementById('formulario-cv-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-cv').classList.remove('hidden');
        } else {
            document.getElementById('certificado-nombre-personal').value = obtenerNombresYApellidos(personaSeleccionada || {});
            document.getElementById('certificado-documento-personal').value = personaSeleccionada?.nroDoc || '';
            document.getElementById('certificado-folio-nombre').value = registro.folio_nombre || folio.documento;
            seleccionarEntidad('certificado-entidad', registro.entidad || registro.cod_institucion || '');
            document.getElementById('certificado-fecha-emision').value = fechaParaInput(registro.fecha_emision);
            document.getElementById('certificado-fecha-caducidad').value = fechaParaInput(registro.fecha_caducidad);
            document.getElementById('certificado-observacion').value = registro.observacion || registro.nota || '';
            document.getElementById('certificado-usuario-registro').value = registro.usuario_registro || '';
            document.getElementById('certificado-fecha-registro').value = fechaHoraParaVista(registro.fecha_registro);
            document.getElementById('certificado-usuario-modificacion').value = registro.usuario_modificacion || '';
            document.getElementById('certificado-fecha-modificacion').value = fechaHoraParaVista(registro.fecha_modificacion);

            document.querySelectorAll('input[name="certificado-apto"], input[name="certificado-positivo"]')
                .forEach(control => control.checked = false);
            document.getElementById('certificado-apto-container').classList.toggle('hidden', !mostrarApto);
            document.getElementById('certificado-positivo-container').classList.toggle('hidden', !mostrarResultado);
            if (mostrarResultado) marcarRadio('certificado-positivo', registro.positivo);
            else marcarRadio('certificado-apto', registro.positivo);
            document.getElementById('formulario-folio-legend').textContent = 'Datos registrados';
            document.getElementById('formulario-certificado-examen-medico').classList.remove('hidden');
        }

        document.getElementById('titulo-modal-agregar-folio').textContent = `Folio registrado: ${registro.folio_nombre || folio.documento}`;
        document.getElementById('filtroTipoFolio').disabled = true;
        document.getElementById('filtroFolioTipo').disabled = true;
        establecerFormularioSoloLectura(true);
        document.getElementById('btn-guardar-cambios-folio').classList.add('hidden');
        document.getElementById('btn-volver-folio').classList.add('hidden');
    document.getElementById('btn-modificar-folio').classList.remove('hidden');
        snapshotEdicionFolio = null;
    } catch (error) {
        const mensaje = error.response?.data?.message || 'No se pudo cargar el folio registrado.';
        Swal.fire({ title: 'No se pudo abrir el folio', text: mensaje, icon: 'error' });
    }
}

function actualizarFormularioSeleccionado() {
    ocultarFormularioEvaluacion();
    const selectTipo = document.getElementById('filtroTipoFolio');
    const selectFolio = document.getElementById('filtroFolioTipo');
    const tipoTexto = normalizarTexto(selectTipo.selectedOptions[0]?.textContent);
    const folioTexto = normalizarTexto(selectFolio.selectedOptions[0]?.textContent);

    if (tipoTexto === 'DOCUMENTO' && folioTexto === 'CERTIFICADO UNICO LABORAL - CUL') {
        mostrarFormularioCUL(selectFolio.selectedOptions[0].textContent.trim());
        return;
    }

    if (tipoTexto === 'DOCUMENTO' && [
        'CERTIFICADO DE ANTECEDENTES POLICIALES',
        'CERTIFICADO DE ANTECEDENTES JUDICIALES',
        'CERTIFICADO DE ANTECEDENTES PENALES',
    ].includes(folioTexto)) {
        mostrarFormularioAntecedentes(selectFolio.selectedOptions[0].textContent.trim());
        return;
    }

    if (folioTexto === 'CARNET DE VACUNA COVID') {
        mostrarFormularioVacunaCovid(selectFolio.selectedOptions[0].textContent.trim());
        return;
    }

    if (['DOCUMENTO', 'FORMATO'].includes(tipoTexto) && folioTexto === 'CURRICULUM VITAE DOCUMENTADO') {
        mostrarFormularioCV(selectFolio.selectedOptions[0].textContent.trim());
        return;
    }

    if (['DOCUMENTO', 'FORMATO'].includes(tipoTexto) && folioTexto === 'EVALUACION DEL POSTULANTE') {
        mostrarFormularioEvaluacion(selectFolio.selectedOptions[0].textContent.trim());
        return;
    }

    if (['DOCUMENTO', 'FORMATO'].includes(tipoTexto) && folioTexto === 'VERIFICACION DOMICILIARIA') {
        mostrarFormularioDomicilio(selectFolio.selectedOptions[0].textContent.trim());
        return;
    }

    if (tipoTexto === 'CERTIFICADO' && [
        'CERTIFICADO DE EXAMEN MEDICO',
        'CERTIFICADO DE EXAMEN TOXICOLOGICO EXTERNO',
    ].includes(folioTexto)) {
        const esToxicologico = folioTexto === 'CERTIFICADO DE EXAMEN TOXICOLOGICO EXTERNO';
        mostrarFormularioCertificado(selectFolio.selectedOptions[0].textContent.trim(), esToxicologico);
    } else {
        ocultarFormularioCertificado();
        ocultarFormularioCUL();
        ocultarFormularioAntecedentes();
        ocultarFormularioVacunaCovid();
        ocultarFormularioCV();
        ocultarFormularioDomicilio();
        ocultarFormularioEvaluacion();
    }
}

function filterTableByTipoFolio() {
    const tipo = document.getElementById('selectTipoFolio').value;
    const busqueda = document.getElementById("buscarFolio").value.toLowerCase().trim();

    // 1. Limpiamos filtros anteriores para evitar conflictos
    tblDocs.clearFilter();

    // 2. Aplicamos el filtro de Prioridad (solo si no es "TODOS")
    if (tipo !== "TODOS") {
        const tipoFolio = tipo === "SI" ? "PRINCIPAL" : "ADICIONAL";
        tblDocs.addFilter("tipo_folio", "=", tipoFolio);
    }

    // 3. Aplicamos el filtro de texto (si el usuario escribió algo)
    if (busqueda !== "") {
        tblDocs.addFilter("documento", "like", busqueda);
    }

}

function actualizarOpcionesFoliosPorTipo(folioSeleccionado = '') {
    const selectTipo = document.getElementById('filtroTipoFolio');
    const selectFolio = document.getElementById('filtroFolioTipo');
    if (!selectTipo || !selectFolio) return Promise.resolve();

    const codTipo = selectTipo.value;
    selectFolio.innerHTML = '';

    if (!codTipo) {
        selectFolio.add(new Option('Selecciona un tipo primero', ''));
        selectFolio.disabled = true;
        selectFolio.dataset.loading = 'false';
        return Promise.resolve();
    }

    selectFolio.add(new Option('Cargando folios...', ''));
    selectFolio.disabled = true;
    selectFolio.dataset.loading = 'true';

    return axios.get(`${VITE_URL_APP}/api/get-folios-tipo/${encodeURIComponent(codTipo)}`)
        .then(response => {
            if (selectTipo.value !== codTipo) return;

            const folios = Array.isArray(response.data) ? response.data : [];
            selectFolio.innerHTML = '';
            if (!folios.length) {
                selectFolio.add(new Option('No hay folios de este tipo', ''));
                selectFolio.disabled = true;
                selectFolio.dataset.loading = 'false';
                return;
            }

            selectFolio.add(new Option('Todos los folios de este tipo', ''));
            folios.forEach(folio => selectFolio.add(new Option(folio.nombre, folio.codigo)));
            selectFolio.disabled = false;
            selectFolio.dataset.loading = 'false';
            if (folioSeleccionado && [...selectFolio.options].some(option => option.value === String(folioSeleccionado))) {
                selectFolio.value = String(folioSeleccionado);
            }
        })
        .catch(error => {
            if (selectTipo.value !== codTipo) return;
            selectFolio.innerHTML = '';
            selectFolio.add(new Option('Error al cargar los folios', ''));
            selectFolio.disabled = true;
            selectFolio.dataset.loading = 'false';
            console.error('Error al cargar folios por tipo:', error);
        });
}

function seleccionarPrimeraSucursalValida() {
    const select = document.getElementById("sucursal");
    if (!select) return;
    const opciones = [...select.options].filter(opt =>
        opt.value && opt.value !== "-Seleccionar-" && opt.value !== "— Seleccionar —" && !opt.disabled
    );
    const sucursalChimbote = opciones.find(opt => opt.textContent.trim().toUpperCase().includes("CHIMBOTE"));
    if (sucursalChimbote) {
        select.value = sucursalChimbote.value;
    } else if (opciones.length > 0) {
        select.value = opciones[0].value;
    }
}

function updateCardTitle(nombrePersona) {
    document.querySelector('.nombrePersDocs').textContent = `Folios de ${nombrePersona}`;
    document.querySelector('.nombrePersLeg').textContent = `Legajos para ${nombrePersona}`;
}

function abrirFoliosDesdeNotificacion(codPersonal, nombre) {
    document.getElementById('codPersonal').value = codPersonal;
    getDocsObligatorios(codPersonal);
    updateCardTitle(nombre);
    document.getElementById('btn-modal-folios').click();
    setTimeout(() => tblDocs.redraw(true), 300);
}

// ============================================================
// FUNCIONES — API (Axios)
// ============================================================
function getDocsObligatorios(codigo) {
    return axios.get(`${VITE_URL_APP}/get-documentos/${codigo}`)
        .then(response => {
            tblDocs.setData(response.data);
            filterTableByTipoFolio();
        })
        .catch(error => console.error("Error al obtener documentos:", error));
}

function getPersonal() {
    axios.get(`${VITE_URL_APP}/api/get-personal`)
        .catch(error => console.error("Error al obtener personal:", error));
}

function getLegajos() {
    tblLegajos.clearData();
    document.getElementById('tblDocsLegajo').classList.remove('hidden');
    axios.get(`${VITE_URL_APP}/api/get-legajos`, {
        params: {
            cliente: document.getElementById('clientes').value,
            cargo: document.getElementById('cargos').value,
            codigo: document.getElementById('codPersonal').value,
        }
    })
        .then(response => {
            tblLegajos.setData(response.data);
            // Pequeño ajuste para evitar desajustes visuales al renderizar dinámicamente
            setTimeout(() => tblLegajos.redraw(true), 150);
        })
        .catch(error => console.error("Error al obtener legajos:", error));
}

function getCargos(clienteLeg) {
    axios.get(`${VITE_URL_APP}/api/get-cargos`, { params: { cliente: clienteLeg } })
        .then(response => {
            const select = document.getElementById("cargos");
            select.innerHTML = '<option disabled selected>-Seleccionar-</option>';
            response.data.forEach(cargo => {
                const option = document.createElement("option");
                option.value = cargo.codigo;
                option.textContent = cargo.nombre;
                select.appendChild(option);
            });
        })
        .catch(error => console.error("Error al obtener cargos:", error));
}

function getCoincidencias(cliente, cargo) {
    axios.get(`${VITE_URL_APP}/api/get-coincidencias`, { params: { cliente, cargo } })
        .then(response => tblPersonasCN.setData(response.data))
        .catch(error => console.error("Error al obtener coincidencias:", error));
}

async function getUsuario() {
    try {
        const response = await axios.get(`${VITE_URL_APP}/usuario`);
        return response.data;
    } catch (error) {
        console.error('Error al obtener usuario:', error.message);
        return null;
    }
}

// ============================================================
// BIOMÉTRICO
// ============================================================
function verBiometrico(codigo, persona) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    axios.get(`${VITE_URL_APP}/api/get-biometrico/${codigo}`)
        .then(response => {
            Swal.close();
            const data = response.data;

            document.getElementById('modal-bio-title').textContent = persona;

            document.getElementById('bio-huella-antigua').innerHTML = renderImagen(data.huella_antigua);
            document.getElementById('bio-huella-nueva').innerHTML = renderImagen(data.huella_nueva);
            document.getElementById('bio-firma-antigua').innerHTML = renderImagen(data.firma_antigua);
            document.getElementById('bio-firma-nueva').innerHTML = renderImagen(data.firma_nueva);
            document.getElementById('bio-doc-dni-antiguo').innerHTML = renderImagen(data.dni_anverso_antigua, true, data.dni_reverso_antigua);
            document.getElementById('bio-doc-firma-nueva').innerHTML = renderImagen(data.firma_nueva);
            document.getElementById('bio-doc-huella-nueva').innerHTML = renderImagen(data.huella_nueva);

            bioSwitchTab('fh');
            window.HSOverlay?.open(document.getElementById('modal-biometrico'));
        })
        .catch(() => Swal.fire({ title: 'Error al obtener biométrico', icon: 'error' }));
}

window.bioSwitchTab = function (tab) {
    const esFH = tab === 'fh';
    document.getElementById('bio-panel-fh').style.display = esFH ? 'flex' : 'none';
    document.getElementById('bio-panel-doc').style.display = esFH ? 'none' : 'block';

    document.getElementById('bio-tab-fh').classList.toggle('border-indigo-500', esFH);
    document.getElementById('bio-tab-fh').classList.toggle('text-indigo-600', esFH);
    document.getElementById('bio-tab-fh').classList.toggle('border-transparent', !esFH);
    document.getElementById('bio-tab-fh').classList.toggle('text-gray-500', !esFH);

    document.getElementById('bio-tab-doc').classList.toggle('border-indigo-500', !esFH);
    document.getElementById('bio-tab-doc').classList.toggle('text-indigo-600', !esFH);
    document.getElementById('bio-tab-doc').classList.toggle('border-transparent', esFH);
    document.getElementById('bio-tab-doc').classList.toggle('text-gray-500', esFH);
};

// ============================================================
// RENDER DE IMÁGENES (biométrico)
// ============================================================
function renderImagen(img, esDni = false, reverso = null) {
    if (!img || typeof img !== 'string' || !img.startsWith('data:')) {
        return `
            <div style="width:100%;${esDni ? 'height:420px;' : 'height:180px;'}
                display:flex;flex-direction:column;align-items:center;justify-content:center;
                background:#f8fafc;border:1.5px dashed #e2e8f0;border-radius:12px;color:#94a3b8;gap:8px;">
                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="3"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <path d="m21 15-5-5L5 21"/>
                </svg>
                <span style="font-size:12px;font-weight:500;">Sin imagen</span>
            </div>`;
    }

    const id = 'img_' + Math.random().toString(36).substr(2, 9);
    let mostrandoReverso = false;

    const toggleBtn = esDni ? `
        <button onclick="toggleDni_${id}()" id="toggleBtn_${id}" style="
            width:100%;font-size:12px;padding:7px 0;background:#f1f5f9;border:none;
            border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px;
            cursor:pointer;color:#475569;font-weight:500;transition:background .15s;">
            <svg style="display:inline;vertical-align:-2px;margin-right:4px;" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
            </svg>Ver reverso
        </button>` : '';

    setTimeout(() => {
        if (esDni) {
            window[`toggleDni_${id}`] = function () {
                mostrandoReverso = !mostrandoReverso;
                document.getElementById(id).src = mostrandoReverso ? (reverso || img) : img;
                const badge = document.getElementById('badge_' + id);
                if (badge) badge.textContent = mostrandoReverso ? 'REVERSO' : 'ANVERSO';
                document.getElementById('toggleBtn_' + id).innerHTML = mostrandoReverso
                    ? `<svg style="display:inline;vertical-align:-2px;margin-right:4px;" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg> Ver anverso`
                    : `<svg style="display:inline;vertical-align:-2px;margin-right:4px;" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg> Ver reverso`;
            };
        }
        const btn = document.getElementById('toggleBtn_' + id);
        if (btn) {
            btn.onmouseover = () => btn.style.background = '#e2e8f0';
            btn.onmouseout = () => btn.style.background = '#f1f5f9';
        }
    }, 0);

    const btnAccion = esDni
        ? `<button onclick="abrirLightbox('${id}')" style="
                position:absolute;bottom:8px;right:8px;background:rgba(99,102,241,0.9);color:white;
                border:none;border-radius:8px;padding:5px 10px;font-size:11px;font-weight:500;
                cursor:pointer;z-index:11;display:flex;align-items:center;gap:5px;
                box-shadow:0 2px 8px rgba(99,102,241,0.4);transition:background .15s;"
                onmouseover="this.style.background='#4f46e5'" onmouseout="this.style.background='rgba(99,102,241,0.9)'">
                <svg width="12" height="12" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M15 3h6m0 0v6m0-6-7 7M9 21H3m0 0v-6m0 6 7-7"/>
                </svg>Ver
           </button>`
        : `<button onclick="toggleLupa('${id}')" id="lupaBtn_${id}" style="
                position:absolute;bottom:8px;right:8px;background:rgba(99,102,241,0.9);color:white;
                border:none;border-radius:50%;width:30px;height:30px;cursor:pointer;z-index:11;
                display:flex;align-items:center;justify-content:center;
                box-shadow:0 2px 8px rgba(99,102,241,0.4);transition:transform .15s,background .15s;"
                onmouseover="this.style.transform='scale(1.1)';this.style.background='#4f46e5'"
                onmouseout="this.style.transform='scale(1)';this.style.background='rgba(99,102,241,0.9)'"
                title="Activar lupa">
                <svg width="13" height="13" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
           </button>`;

    const lupaDiv = !esDni ? `
        <div id="lupa_${id}" style="
            display:none;position:absolute;width:130px;height:130px;border-radius:50%;
            border:2.5px solid #6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.15);
            pointer-events:none;background-repeat:no-repeat;z-index:10;"></div>` : '';

    return `
        <div style="border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,0.06);width:100%;">
            <div id="cont_${id}" class="m-0 p-0" style="position:relative;width:100%;
                ${esDni ? 'height:420px;' : 'height:180px;'}
                background:#f8fafc;overflow:hidden;
                display:flex;align-items:center;justify-content:center;
                ${!esDni ? 'cursor:crosshair;' : ''}">
                <img id="${id}" src="${img}"
                     style="max-width:100%;max-height:100%;width:95%;height:auto;object-fit:contain;display:block;cursor:${esDni ? 'zoom-in' : 'crosshair'};"
                     ${esDni ? `onclick="abrirLightbox('${id}')"` : ''}
                     onerror="this.parentElement.innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;flex-direction:column;gap:6px;\'><svg width=32 height=32 fill=none stroke=currentColor stroke-width=1.5 viewBox=\'0 0 24 24\'><rect x=3 y=3 width=18 height=18 rx=3/><circle cx=8.5 cy=8.5 r=1.5/><path d=\'m21 15-5-5L5 21\'/></svg>Sin imagen</div>'" />
                ${lupaDiv}
                ${esDni ? `<span id="badge_${id}" style="position:absolute;top:8px;left:8px;background:rgba(99,102,241,0.9);color:#fff;font-size:10px;font-weight:600;padding:3px 8px;border-radius:20px;letter-spacing:0.5px;z-index:5;">ANVERSO</span>` : ''}
                ${btnAccion}
            </div>
            ${toggleBtn}
        </div>`;
}

// ============================================================
// LUPA
// ============================================================
window.toggleLupa = function (id) {
    const lupa = document.getElementById('lupa_' + id);
    const img = document.getElementById(id);
    const cont = document.getElementById('cont_' + id);
    if (!lupa || !img || !cont) return;

    const activa = lupa.style.display === 'block';

    if (!activa) {
        lupa.style.display = 'block';
        cont.style.overflow = 'visible';

        cont.onmousemove = function (e) {
            const contRect = cont.getBoundingClientRect();
            const imgRect = img.getBoundingClientRect();
            const cx = e.clientX - contRect.left;
            const cy = e.clientY - contRect.top;
            const ix = e.clientX - imgRect.left;
            const iy = e.clientY - imgRect.top;
            const lw = lupa.offsetWidth, lh = lupa.offsetHeight, scale = 2.8;

            lupa.style.left = (cx - lw / 2) + 'px';
            lupa.style.top = (cy - lh / 2) + 'px';
            lupa.style.backgroundImage = `url('${img.src}')`;
            lupa.style.backgroundSize = `${imgRect.width * scale}px ${imgRect.height * scale}px`;
            lupa.style.backgroundPosition = `${-(ix * scale - lw / 2)}px ${-(iy * scale - lh / 2)}px`;
        };

        cont.onmouseleave = function () {
            lupa.style.display = 'none';
            cont.style.overflow = 'hidden';
            cont.onmousemove = cont.onmouseleave = null;
        };
    } else {
        lupa.style.display = 'none';
        cont.style.overflow = 'hidden';
        cont.onmousemove = cont.onmouseleave = null;
    }
};

// ============================================================
// LIGHTBOX
// ============================================================
(function () {
    const lb = document.createElement('div');
    lb.id = 'lb-overlay';
    lb.style.cssText = `display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.92);flex-direction:column;align-items:center;justify-content:center;`;

    lb.innerHTML = `
        <div style="width:100%;padding:10px 20px;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.1);">
            <span id="lb-titulo" style="color:#e5e7eb;font-size:13px;font-weight:500;">Vista de imagen</span>
            <button id="lb-close-top" style="background:rgba(220,38,38,0.7);border:1px solid rgba(220,38,38,0.5);color:white;border-radius:8px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                <svg width="14" height="14" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="lb-canvas" style="flex:1;width:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:grab;user-select:none;position:relative;">
            <img id="lb-img" style="max-width:90vw;max-height:75vh;transform-origin:center center;pointer-events:none;display:block;"/>
            <div style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,0.15);border-radius:12px;padding:8px 14px;display:flex;align-items:center;gap:8px;">
                <button id="lb-zout" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:7px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;">
                    <svg width="15" height="15" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M8 11h6"/></svg>
                </button>
                <span id="lb-zoom-label" style="color:#e5e7eb;font-size:12px;font-weight:600;min-width:40px;text-align:center;">100%</span>
                <button id="lb-zin" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:7px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;">
                    <svg width="15" height="15" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>
                </button>
                <div style="width:1px;height:24px;background:rgba(255,255,255,0.2);"></div>
                <button id="lb-reset" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:7px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;">
                    <svg width="14" height="14" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                </button>
                <div style="width:1px;height:24px;background:rgba(255,255,255,0.2);"></div>
                <button id="lb-fullscreen" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:7px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;">
                    <svg id="lb-fs-icon" width="14" height="14" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 3h6m0 0v6m0-6-7 7M9 21H3m0 0v-6m0 6 7-7"/></svg>
                </button>
            </div>
        </div>`;

    document.body.appendChild(lb);

    let scale = 1, posX = 0, posY = 0, dragging = false, startX = 0, startY = 0;
    const lbImg = document.getElementById('lb-img');
    const lbCanvas = document.getElementById('lb-canvas');
    const lbLabel = document.getElementById('lb-zoom-label');

    const applyTransform = () => {
        lbImg.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`;
        lbLabel.textContent = Math.round(scale * 100) + '%';
    };
    const resetView = () => { scale = 1; posX = 0; posY = 0; applyTransform(); };

    lbCanvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        scale = Math.min(Math.max(scale + (e.deltaY > 0 ? -0.15 : 0.15), 0.3), 8);
        applyTransform();
    }, { passive: false });

    lbCanvas.addEventListener('mousedown', (e) => {
        if (e.target.closest('button')) return;
        dragging = true; startX = e.clientX - posX; startY = e.clientY - posY;
        lbCanvas.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', (e) => {
        if (!dragging) return;
        posX = e.clientX - startX; posY = e.clientY - startY; applyTransform();
    });
    document.addEventListener('mouseup', () => { dragging = false; lbCanvas.style.cursor = 'grab'; });

    document.getElementById('lb-zin').onclick = () => { scale = Math.min(scale + 0.25, 8); applyTransform(); };
    document.getElementById('lb-zout').onclick = () => { scale = Math.max(scale - 0.25, 0.3); applyTransform(); };
    document.getElementById('lb-reset').onclick = resetView;
    document.getElementById('lb-close-top').onclick = cerrarLightbox;
    document.getElementById('lb-fullscreen').onclick = () => {
        if (!document.fullscreenElement) {
            lb.requestFullscreen?.();
            document.getElementById('lb-fs-icon').innerHTML = `<path d="M8 3H3m0 0v5m0-5 7 7M16 21h5m0 0v-5m0 5-7-7"/>`;
        } else {
            document.exitFullscreen?.();
            document.getElementById('lb-fs-icon').innerHTML = `<path d="M15 3h6m0 0v6m0-6-7 7M9 21H3m0 0v-6m0 6 7-7"/>`;
        }
    };

    document.addEventListener('keydown', (e) => {
        if (lb.style.display === 'none') return;
        if (e.key === 'Escape') cerrarLightbox();
        if (e.key === '+' || e.key === '=') { scale = Math.min(scale + 0.25, 8); applyTransform(); }
        if (e.key === '-') { scale = Math.max(scale - 0.25, 0.3); applyTransform(); }
        if (e.key === '0') resetView();
    });

    ['lb-zin', 'lb-zout', 'lb-reset', 'lb-fullscreen'].forEach(id => {
        const b = document.getElementById(id);
        b.onmouseover = () => b.style.background = 'rgba(255,255,255,0.25)';
        b.onmouseout = () => b.style.background = 'rgba(255,255,255,0.12)';
    });

    window.abrirLightbox = function (imgId) {
        const imgEl = document.getElementById(imgId);
        if (!imgEl) return;
        lbImg.src = imgEl.src;
        lb.style.display = 'flex';
        resetView();
    };

    function cerrarLightbox() {
        lb.style.display = 'none';
        lbImg.src = '';
        if (document.fullscreenElement) document.exitFullscreen?.();
    }
})();
