import axios from 'axios';
import Swal from 'sweetalert2';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';

// ============================================================
// ESTADO GLOBAL
// ============================================================
let usuarioActual = null;
let pageSizePersonas = 10;

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
                const persona = registro.personal;

                document.getElementById('codPersonal').value = codigo;
                updateCardTitle(persona);

                if (e.target.classList.contains('docs-btn')) {
                    // Reseteamos el filtro de prioridad y la búsqueda para que siempre inicie limpio
                    document.getElementById('selectTipoFolio').value = 'TODOS';
                    document.getElementById('buscarFolio').value = '';
                    document.getElementById('filtroCategoriaFolio').value = '';
                    actualizarOpcionesFoliosPorCategoria();

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
            title: "Prioridad", field: "tipo_folio", hozAlign: "center", width: '10%',
            formatter: function (cell) {
                const val = cell.getValue();
                if (!val) return '—';

                // Colores más vivos, diseño tipo píldora y sombra suave
                const isPrincipal = val.toUpperCase() === 'PRINCIPAL';
                const color = isPrincipal
                    ? 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'
                    // Aquí le subimos la intensidad al borde morado a 500 para que resalte
                    : 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm';

                const label = val.toUpperCase() === 'PRINCIPAL' ? 'OBLIGATORIO' : val;
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

                        archivoInput.setAttribute('accept', 'image/jpeg');
                        archivoInput.multiple = cantHojas > 1; // múltiple solo si requiere más de 1

                        console.log('CANTIDAD DE HOJAS mensaje ->  ', cantHojas > 1);
                        console.log('NPUT SUBIR ->  ', archivoInput);

                        if (cantHojas > 1) {
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
                                    visor.insertAdjacentHTML('beforeend', `
                                    <img src="http://${ruta}" class="w-full max-w-[700px] mb-3 rounded-md" />
                                `);
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

// Validación en tiempo real al seleccionar archivo
archivoInput.addEventListener('change', function () {
    const archivos = Array.from(this.files);
    if (!archivos.length) return;

    const codFolio = parseInt(document.getElementById('codFolioActual').value || '0');
    const esDJ = codFolio === 25;
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
                text: `"${archivo.name}" pesa ${(archivo.size / 1024 / 1024).toFixed(2)} MB. Límite: 1 MB.`,
                icon: 'warning'
            });
            limpiar(); return;
        }
    }

    // Validar cantidad (solo no-DJ)
    if (!esDJ && archivos.length !== cantEsperada) {
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
        if (!esDJ && a.type !== 'image/jpeg') {
            Swal.fire({ title: 'Solo se permiten imágenes JPG', icon: 'warning' }); return;
        }
        if (a.size > maxSize) {
            Swal.fire({ title: 'Archivo demasiado grande', text: `"${a.name}" pesa ${(a.size / 1024 / 1024).toFixed(2)} MB. Límite: 1 MB.`, icon: 'warning' }); return;
        }
    }

    // Validar cantidad (solo no-DJ)
    if (!esDJ && archivos.length !== cantEsperada) {
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

// Estos SÍ recalcularán las tarjetas:
document.getElementById("sucursal").addEventListener("change", () => {
    reloadTabla();
    cargarIndicadores();
});
document.getElementById("tipo_per")?.addEventListener("change", () => {
    reloadTabla();
    cargarIndicadores();
});

// Estos NO recalcularán las tarjetas (solo filtran la tabla visualmente):
document.getElementById("buscarPersonal").addEventListener("keyup", () => reloadTabla());
document.getElementById("filtroVigencia")?.addEventListener("change", () => reloadTabla());
document.getElementById("filtroDJ")?.addEventListener("change", () => reloadTabla());
// Filtros combinados para el Modal de Folios (Select + Búsqueda)
document.getElementById('selectTipoFolio')?.addEventListener('change', filterTableByTipoFolio);
document.getElementById('buscarFolio')?.addEventListener('keyup', filterTableByTipoFolio);
document.getElementById('filtroCategoriaFolio')?.addEventListener('change', () => {
    actualizarOpcionesFoliosPorCategoria();
    filterTableByTipoFolio();
});
document.getElementById('filtroFolioCategoria')?.addEventListener('change', filterTableByTipoFolio);

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

    // 🔥 SOLO filtramos por Sucursal y Tipo (ignoramos búsqueda, DJ y Vigencia)
    const params = {
        codSucursal: codSucursal,
        tipo_per: document.getElementById("tipo_per")?.value || "TODOS",
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

function filterTableByTipoFolio() {
    const tipo = document.getElementById('selectTipoFolio').value;
    const busqueda = document.getElementById("buscarFolio").value.toLowerCase().trim();
    const codFolio = document.getElementById('filtroFolioCategoria').value;

    // 1. Limpiamos filtros anteriores para evitar conflictos
    tblDocs.clearFilter();

    // 2. Aplicamos el filtro de Prioridad (solo si no es "TODOS")
    if (tipo !== "TODOS") {
        tblDocs.addFilter("tipo_folio", "=", tipo);
    }

    // 3. Aplicamos el filtro de texto (si el usuario escribió algo)
    if (busqueda !== "") {
        tblDocs.addFilter("documento", "like", busqueda);
    }

    if (codFolio !== "") {
        tblDocs.addFilter(data => String(data.codFolio) === String(codFolio));
    }
}

function actualizarOpcionesFoliosPorCategoria() {
    const selectCategoria = document.getElementById('filtroCategoriaFolio');
    const selectFolio = document.getElementById('filtroFolioCategoria');
    if (!selectCategoria || !selectFolio) return;

    const codCategoria = selectCategoria.value;
    selectFolio.innerHTML = '';

    if (!codCategoria) {
        selectFolio.add(new Option('Selecciona una categoría primero', ''));
        selectFolio.disabled = true;
        return;
    }

    selectFolio.add(new Option('Cargando folios...', ''));
    selectFolio.disabled = true;

    axios.get(`${VITE_URL_APP}/api/get-folios-categoria/${encodeURIComponent(codCategoria)}`)
        .then(response => {
            if (selectCategoria.value !== codCategoria) return;

            const folios = Array.isArray(response.data) ? response.data : [];
            selectFolio.innerHTML = '';
            if (!folios.length) {
                selectFolio.add(new Option('No hay folios en esta categoría', ''));
                return;
            }

            selectFolio.add(new Option('Todos los folios de la categoría', ''));
            folios.forEach(folio => selectFolio.add(new Option(folio.nombre, folio.codigo)));
            selectFolio.disabled = false;
        })
        .catch(error => {
            if (selectCategoria.value !== codCategoria) return;
            selectFolio.innerHTML = '';
            selectFolio.add(new Option('Error al cargar los folios', ''));
            console.error('Error al cargar folios por categoría:', error);
        });
}

function seleccionarPrimeraSucursalValida() {
    const select = document.getElementById("sucursal");
    if (!select) return;
    const opciones = [...select.options].filter(opt =>
        opt.value && opt.value !== "-Seleccionar-" && opt.value !== "— Seleccionar —" && !opt.disabled
    );
    if (opciones.length > 0) select.value = opciones[0].value;
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
    axios.get(`${VITE_URL_APP}/get-documentos/${codigo}`)
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
