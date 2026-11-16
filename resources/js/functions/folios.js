import axios from 'axios';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';
import Swal from 'sweetalert2';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';


document.addEventListener("DOMContentLoaded", () => {
    cargarFolios();
});

let modoEdicion = false;
let datosOriginales = null;

//Tabla de Folios
const tblFolios = new Tabulator("#tblFolios", {
    height: "100%",
    layout: "fitColumns",
    responsiveLayout: "collapse",
    pagination: true,
    paginationSize: 20,
    locale: "es",
    langs: {
        "es": {
            "pagination": {
                "first": "Primero",
                "first_title": "Primera Página",
                "last": "Último",
                "last_title": "Última Página",
                "prev": "Anterior",
                "prev_title": "Página Anterior",
                "next": "Siguiente",
                "next_title": "Página Siguiente",
                "all": "Todo"
            },
            "headerFilters": {
                "default": "Filtrar...",
            },
            "ajax": {
                "loading": "Cargando datos...",
                "error": "Error al cargar datos"
            },
            "data": {
                "empty": "No hay datos disponibles"
            }
        }
    },
    columns: [
        { 
            title: "N°", 
            field: "nro_fila_estatico", 
            formatter: function() { return ""; }, 
            hozAlign: "center", 
            width: 55, 
            headerSort: false, 
            responsive: false 
        },
        { title: "Folios", field: "nombre", hozAlign: "left", widthGrow: 3 },
        { title: "Categoría", field: "nombre_categoria", hozAlign: "center", widthGrow: 3 },
        {
            title: "Tipo", field: "tipoFolio", hozAlign: "center", widthGrow: 2,
            formatter: function (cell) {
                let tipo = cell.getValue() || '';
                let color = 'border-gray-400 bg-gray-100 text-gray-800 shadow-sm';

                if (tipo === "FORMATO") {
                    color = 'border-amber-500 bg-yellow-100 text-yellow-800 shadow-sm';
                } else if (tipo === "DOCUMENTO") {
                    // 🔥 Morado a 500 para que marque la diferencia
                    color = 'border-purple-500 bg-purple-100 text-purple-800 shadow-sm';
                } else if (tipo === "CERTIFICADO") {
                    // 🔥 Azul a 500 para emparejar la intensidad
                    color = 'border-blue-500 bg-blue-100 text-blue-800 shadow-sm';
                }

                return tipo ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 105px;">${tipo}</span>` : '—';
            }
        },
        {
            title: "Prioridad", field: "prioridad", hozAlign: "center", widthGrow: 2,
            formatter: function (cell) {
                let val = cell.getValue() || '';
                
                let color = 'border-teal-500 bg-teal-100 text-teal-800 shadow-sm'; 

                if (val === "PRINCIPAL") {
                    // 🔥 Índigo a 500 para que resalte bien contra el rosa
                    color = 'border-indigo-500 bg-indigo-100 text-indigo-800 shadow-sm';
                }

                const etiqueta = val === 'PRINCIPAL' ? 'OBLIGATORIO' : val;
                return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 95px;">${etiqueta}</span>` : '—';
            }
        },
        {
            title: "Caduca", field: "periodo", hozAlign: "center", widthGrow: 2,
            formatter: function (cell) {
                let val = cell.getValue() || '';
                
                let color = 'bg-gray-200 shadow-sm'; 
                // 🔥 TRUCO: Forzamos el color negro directo al HTML para que Tailwind no lo purgue
                let extraStyle = 'border-color: black; color: black;';

                if (val && val !== "NO VENCE") {
                    color = 'border-orange-500 bg-orange-100 text-orange-800 shadow-sm'; 
                    extraStyle = ''; // El naranja sí lo lee normal, lo dejamos vacío
                }

                // Inyectamos el extraStyle directamente en la etiqueta
                return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 130px; ${extraStyle}">${val}</span>` : '—';
            }
        },
        {
            title: "Acciones", field: "acciones", hozAlign: "center", width: '20%', headerSort: false,
            formatter: function (cell, formatterParams, onRendered) {
                var editBtn = `<button type="button" class="btn rounded-full edit-btn bg-info/25 text-info hover:bg-info hover:text-white" title="Editar"><i class="fa-solid fa-pen-to-square edit-btn"></i></button>`;

                var deleteBtn =
                    cell.getData().habilitado == '1' ?
                        `<button type="button" class="btn rounded-full delete-btn bg-danger/25 text-danger hover:bg-danger hover:text-white" title="Eliminar"><i class="fa-solid fa-trash-can delete-btn"></i></button>`
                        :
                        `<button type="button" class="btn rounded-full activar-btn bg-success/25 text-success hover:bg-success hover:text-white" title="activar">
                <i class="fa-solid fa-check activar-btn"></i></button>`
                    ;

                return editBtn + ' ' + deleteBtn;
            },
            cellClick: function (e, cell) {
                if (e.target.classList.contains('edit-btn')) {
                    modoEdicion = true;
                    const rowData = cell.getRow().getData();
                    datosOriginales = { ...rowData };

                    const submitButton = document.getElementById('submitButton');
                    submitButton.innerHTML = 'Guardar cambios <i class="fa-solid fa-floppy-disk"></i>';

                    document.querySelector('#codFolio').value = rowData.codigo;
                    document.querySelector('#nombre').value = rowData.nombre;
                    document.querySelector('#tipo').value = rowData.tipo;
                    document.querySelector('#responsable').value = rowData.cod_responsable ?? '';
                    document.querySelector('#categoria').value = rowData.cod_categoria ?? ''; // <-- AQUÍ CARGA LA CATEGORÍA, BESTIA

                    //Bloquear nombre
                    if (rowData.utilizado == 1) {
                        document.querySelector('#nombre').disabled = true;
                    } else {
                        document.querySelector('#nombre').disabled = false;
                    }

                    var institucionDiv = document.getElementById('institucionDiv');
                    if (rowData.tipo == 3) {
                        institucionDiv.classList.remove('hidden');
                    } else {
                        institucionDiv.classList.add('hidden');
                    }

                    if (rowData.obligatorio == 1) {
                        document.getElementById('radioPrin').checked = true;
                    } else {
                        document.getElementById('radioAdi').checked = true;
                    }

                    if (rowData.vencimiento == 1) {
                        document.querySelector('#switchVencimiento').checked = true;
                        document.getElementById('periodoDiv').classList.remove('hidden');
                        document.querySelector('#periodo').value = rowData.tipo_fecha;
                    } else {
                        document.querySelector('#switchVencimiento').checked = false;
                        document.getElementById('periodoDiv').classList.add('hidden');
                    }

                    if (rowData.solo_lectura == 1) {
                        document.querySelector('#switchLectura').checked = true;
            
                    } else {
                        document.querySelector('#switchLectura').checked = false;
                    }

                    var plataforma = rowData.plataforma;
                    var radioButtons = institucionDiv.querySelectorAll('input[type="radio"]');

                    var values = {
                        ICMA: 'ICMA',
                        AV: 'AV',
                        //OTROS: 'OTROS'
                    };

                    if (values[plataforma]) {
                        radioButtons.forEach(radio => {
                            radio.checked = radio.value === values[plataforma];
                        });
                    } else {
                        radioButtons.forEach(radio => {
                            radio.checked = false;
                        });
                    }

                    document.getElementById("txtMensajeNuevo").innerText = "Editando registro";
                    document.getElementById("txtMensajeNuevo").className = "inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-red-100 text-red-800";
                    
                    // Abrir el modal
                    document.getElementById('btn-modal-gestion').click();

                } else if (e.target.classList.contains('activar-btn')) {
                    const rowData = cell.getRow().getData();
                    document.querySelector('#codFolio').value = rowData.codigo;
                    Swal.fire({
                        title: `¿Está seguro de activar el folio: ${rowData.nombre}?`,
                        text: 'No podrás revertir esta acción',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, activalo',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            axios.post(`${VITE_URL_APP}/api/activar_folio`, {
                                codigo: rowData.codigo,
                                habilitado: 1
                            })
                                .then(response => {
                                    console.log(response);
                                    Swal.fire('Habilitado!', 'El folio ha sido activado.', 'success');
                                    cargarFolios();
                                    limpiarForm();
                                })
                                .catch(error => {
                                    Swal.fire('Error', 'Hubo un problema al activar el folio.', 'error');
                                });
                        }
                    });
                } else if (e.target.classList.contains('delete-btn')) {
                    const rowData = cell.getRow().getData();
                    document.querySelector('#codFolio').value = rowData.codigo;
                    Swal.fire({
                        title: `¿Está seguro de eliminar el folio: ${rowData.nombre}?`,
                        text: 'No podrás revertir esta acción',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminarlo',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            axios.post(`${VITE_URL_APP}/api/disabled_folio`, {
                                codigo: rowData.codigo,
                                habilitado: 0
                            })
                                .then(response => {
                                    Swal.fire('Eliminado!', 'El folio ha sido deshabilitado.', 'success');
                                    cargarFolios();
                                    limpiarForm();
                                })
                                .catch(error => {
                                    Swal.fire('Error', 'Hubo un problema al deshabilitar el folio.', 'error');
                                });
                        }
                    });
                }
            }
        },
    ],
    rowFormatter: function (row) {
        let data = row.getData();

        if (data.habilitado != "1") {
            row.getElement().style.backgroundColor = "#ffe9e9";
        }
    }
});

// =========================================================
// INYECCIÓN DE NUMERACIÓN ESTÁTICA
// =========================================================
tblFolios.on("renderComplete", function () {
    // Usamos 20 por defecto porque así lo definiste en paginationSize
    const page = this.getPage() || 1;
    const size = this.getPageSize() || 20; 
    const offset = (page - 1) * size;
    
    this.getRows("active").forEach((row, index) => {
        const cell = row.getCell("nro_fila_estatico");
        if (cell) {
            cell.getElement().innerHTML = `<span class="text-gray-700 font-medium">${offset + index + 1}</span>`;
        }
    });
});

//-------- Desactivar/Activar la institucion
function estadoInstitucion(activo) {
    var institucionDiv = document.getElementById('institucionDiv');
    if (activo == 1) {
        institucionDiv.classList.remove('hidden');
    } else {
        institucionDiv.classList.add('hidden');
    }
    var radioButtons = institucionDiv.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(radio => {
        radio.checked = false;
    });
}

window.aplicarFiltroEliminarFolio = (op) => {
    if (op === 0) { tblFolios.setFilter("habilitado", "=", "0"); } else {
        tblFolios.clearFilter();
    }

}

window.aplicarFiltroSoloActivos = (op) => {
    if (op === 1) { tblFolios.setFilter("habilitado", "=", "1"); } else {
        tblFolios.clearFilter();
    }

}

document.getElementById("page-size").addEventListener("change", function () {
    const size = parseInt(this.value);
    tblFolios.setPageSize(size);
});

// Función para CANCELAR
// document.getElementById("cancelButton").addEventListener("click", function () {

//     if (!modoEdicion) {
//         // Modo NUEVO: limpiar el formulario
//         limpiarForm();
//         document.getElementById("tipo").value = "";
//         document.querySelector('#tipo').dispatchEvent(new Event('change'));
//         return;
//     }

//     // Modo EDICIÓN: restaurar datos originales
//     document.querySelector('#codFolio').value = datosOriginales.codigo;
//     document.querySelector('#nombre').value = datosOriginales.nombre;
//     let tipoSelect = document.getElementById("tipo");
//     tipoSelect.value = datosOriginales.tipo;
//     tipoSelect.dispatchEvent(new Event('change'));

//     if (datosOriginales.tipo == 3) {
//         institucionDiv.classList.remove('hidden');
//     } else {
//         institucionDiv.classList.add('hidden');
//     }

//     if (datosOriginales.obligatorio == 1) {
//         document.getElementById('radioPrin').checked = true;
//     } else {
//         document.getElementById('radioAdi').checked = true;
//     }

//     if (datosOriginales.vencimiento == 1) {
//         document.querySelector('#switchVencimiento').checked = true;
//         document.getElementById('periodoDiv').classList.remove('hidden');
//         document.querySelector('#periodo').value = datosOriginales.tipo_fecha;
//     } else {
//         document.querySelector('#switchVencimiento').checked = false;
//         document.getElementById('periodoDiv').classList.add('hidden');
//     }

//     let radios = document.querySelectorAll('#institucionDiv input[type="radio"]');
//     radios.forEach(r => r.checked = (r.value === datosOriginales.plataforma));

//     document.getElementById("txtMensajeNuevo").innerText = "Editando registro";
//     document.getElementById("txtMensajeNuevo").className =
//         "inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-red-100 text-red-800";

//     submitButton.innerHTML = 'Guardar <i class="fa-solid fa-floppy-disk"></i>';

//     document.getElementById('soloEdicion').classList.add("flex");
// });


document.getElementById('tipo').addEventListener('change', function () {
    const selectedValue = this.value;

    if (selectedValue === "3") {
        estadoInstitucion(1);
    } else {
        institucionDiv.classList.add('hidden');
    }
});

document.getElementById('btnNuevoFolio')?.addEventListener('click', () => {
     limpiarForm();
     document.getElementById('btn-modal-gestion').click();
 });

  // Verificar nombre duplicado mientras escribe
  let checkNombreTimeout = null;
  document.getElementById('nombre').addEventListener('input', function () {
      clearTimeout(checkNombreTimeout);
      const aviso = document.getElementById('avisoNombreRepetidoFolio');
      const valor = this.value.trim();

      if (!valor) {
          aviso.classList.add('hidden');
          return;
      }

      checkNombreTimeout = setTimeout(() => {
          const excluir = document.getElementById('codFolio').value;
          const params  = new URLSearchParams({ nombre: valor });
          if (excluir) params.append('excluir', excluir);

           axios.get(`${ VITE_URL_APP }/api/check-folio-nombre?` + params.toString())
              .then(response => {
                  const btn = document.getElementById('submitButton');
                  if (response.data.existe) {
                      aviso.classList.remove('hidden');
                      btn.disabled = true;
                      btn.classList.add('opacity-50', 'cursor-not-allowed');
                  } else {
                      aviso.classList.add('hidden');
                      btn.disabled = false;
                      btn.classList.remove('opacity-50', 'cursor-not-allowed');
                  }
              })
              .catch(() => {
                  aviso.classList.add('hidden');
                  const btn = document.getElementById('submitButton');
                  btn.disabled = false;
                  btn.classList.remove('opacity-50', 'cursor-not-allowed');
              });

      }, 500);
  });
document.getElementById("buscar").addEventListener("keyup", function () {
    aplicarTodosFiltros();
});


// Función para actualizar la tabla con TODOS los filtros
function aplicarTodosFiltros() {
      const filtroTipos          = document.getElementById('filtroTipos')?.value;
      const filtroClasificacion  = document.getElementById('filtroClasificacion')?.value;
      const filtroCategoria      = document.getElementById('filtroCategoria')?.value;
      const vencimientoFiltro    = document.getElementById('vencimientoFiltro')?.value;
      const filtroEstado         = document.getElementById('filtroEstado')?.value; // <-- NUEVO
      const buscarValor          = document.getElementById('buscar')?.value.toLowerCase().trim() || '';

      const filtros = [];

      // Validamos el nuevo select de Estado
      if (filtroEstado === "1") {
          filtros.push({ field: "habilitado", type: "=", value: "1" });
      } else if (filtroEstado === "0") {
          filtros.push({ field: "habilitado", type: "=", value: "0" });
      }
      // Si es "TODOS", no empujamos nada, así Tabulator muestra ambos.

      if (filtroTipos && filtroTipos !== "TODOS") {
          filtros.push({ field: "prioridad", type: "=", value: filtroTipos });
      }

      if (filtroClasificacion && filtroClasificacion !== "TODOS") {
          filtros.push({ field: "tipoFolio", type: "=", value: filtroClasificacion });
      }

      if (filtroCategoria === "SIN_CATEGORIA") {
          filtros.push({ field: "nombre_categoria", type: "=", value: "SIN CATEGORÍA" });
      } else if (filtroCategoria && filtroCategoria !== "TODOS") {
          filtros.push({ field: "cod_categoria", type: "=", value: filtroCategoria });
      }

      if (vencimientoFiltro === "SI") {
          filtros.push({ field: "periodo", type: "!=", value: "NO VENCE" });
      } else if (vencimientoFiltro === "NO") {
          filtros.push({ field: "periodo", type: "=", value: "NO VENCE" });
      }

      if (buscarValor) {
          filtros.push([
              { field: "nombre", type: "like", value: buscarValor },
              { field: "prioridad", type: "like", value: buscarValor },
              { field: "tipoFolio", type: "like", value: buscarValor },
              { field: "nombre_categoria", type: "like", value: buscarValor },
              { field: "periodo", type: "like", value: buscarValor },
          ]);
      }

      if (filtros.length === 0) {
          tblFolios.clearFilter();
      } else {
          tblFolios.setFilter(filtros);
      }
  }

  // Listeners para los Selects
  document.getElementById('filtroTipos')?.addEventListener('change', aplicarTodosFiltros);
  document.getElementById('filtroClasificacion')?.addEventListener('change', aplicarTodosFiltros);
  document.getElementById('filtroCategoria')?.addEventListener('change', aplicarTodosFiltros);
  document.getElementById('vencimientoFiltro')?.addEventListener('change', aplicarTodosFiltros);
  document.getElementById('filtroEstado')?.addEventListener('change', aplicarTodosFiltros); // <-- NUEVO LISTENER


//Activar los periodos si hay VENCIMIENTO
document.getElementById('switchVencimiento').addEventListener('change', function () {
    document.getElementById('periodoDiv').classList.toggle('hidden', !this.checked);
});





//Función para limpia los campos del modal
 function limpiarForm() {
      document.getElementById("txtMensajeNuevo").innerText = "Nuevo registro";
      document.getElementById("txtMensajeNuevo").className = "inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-blue-100 text-blue-800";

      document.getElementById('nombre').value = "";
      document.getElementById('nombre').disabled = false;
      document.getElementById('tipo').value = '';
      document.getElementById('tipo').dispatchEvent(new Event('change'));
      document.getElementById('responsable').value = '';
      document.getElementById('categoria').value = '';
      document.getElementById('radioPrin').checked = true;
      document.getElementById('radioAdi').checked = false;
      document.querySelector('#switchVencimiento').checked = false;
      document.getElementById('periodoDiv').classList.add('hidden');

      estadoInstitucion(0);
      document.querySelector('#codFolio').value = "";
      modoEdicion = false;
      datosOriginales = null;
      document.getElementById('submitButton').innerHTML = 'Guardar <i class="fa-solid fa-floppy-disk"></i>';

      document.getElementById('avisoNombreRepetidoFolio').classList.add('hidden');
      document.getElementById('submitButton').disabled = false;
      document.getElementById('submitButton').classList.remove('opacity-50', 'cursor-not-allowed');
  }


//========================================== DATA CON AXIOS ==========================================//
// Función para obtener los folios
function cargarFolios() {
    axios.get(`${VITE_URL_APP}/api/get-folios`)
        .then(response => {
            const data = response.data || [];
            tblFolios.setData(data);
            aplicarTodosFiltros();

            // 🔥 Lógica de Indicadores
            const total = data.length;
            const activos = data.filter(f => f.habilitado == 1).length;
            const inactivos = total - activos;

            document.getElementById('countTotal').textContent = total;
            document.getElementById('countActivos').textContent = activos;
            document.getElementById('countInactivos').textContent = inactivos;
            const porcentajeActivos = total ? (activos / total) * 100 : 0;
            const porcentajeInactivos = total ? (inactivos / total) * 100 : 0;
            document.getElementById('barTotal').style.width = total ? '100%' : '0%';
            document.getElementById('barActivos').style.width = `${porcentajeActivos}%`;
            document.getElementById('barInactivos').style.width = `${porcentajeInactivos}%`;
        })
        .catch(error => {
            console.error("Error al obtener los datos:", error);
        });
};


//================================ GUARDAR LOS DATOS POR AXIOS ================================//
document.getElementById('formSaveFolio').addEventListener('submit', function (event) {
    event.preventDefault();
    var codigo = document.getElementById('codFolio').value;
    var nombre = document.getElementById('nombre').value;
    var tipo = document.getElementById('tipo').value;
    var tipoFolio = document.querySelector('input[name="tipo_folio"]:checked').value;
    var obligatorio = (tipoFolio === 'PRINCIPAL') ? 1 : 0;
    var switchVencimiento = document.getElementById('switchVencimiento');
    var vencimiento = switchVencimiento.checked ? 1 : 0;
    var periodo = document.getElementById('periodo').value;
    var responsable = document.getElementById('responsable').value;
    var categoria = document.getElementById('categoria').value; // <-- CAPTURAS EL DATO
    var institucion = document.querySelector('input[name="institucion"]:checked')?.value;
    var switchLectura = document.getElementById('switchLectura');
    var soloLectura = switchLectura.checked ? 1 : 0 ;

    if (vencimiento == 0) {
        periodo = null;
    }

    // <-- LE AGREGAS LA VALIDACIÓN PARA LA CATEGORÍA
    if (nombre && tipo && responsable && categoria) {
        axios.post(`${VITE_URL_APP}/api/save_folio`, {
            codigo: codigo,
            nombre: nombre,
            tipo: tipo,
            obligatorio: obligatorio,
            vencimiento: vencimiento,
            periodo: periodo,
            responsable: responsable,
            cod_categoria: categoria,
            plataforma: institucion,
            solo_lectura: soloLectura,
        })
            .then(function (response) {
                cargarFolios();
                limpiarForm();
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: response.data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                // Cerrar el modal tras guardar
                document.getElementById('btn-modal-gestion-close').click();
            })
            .catch(function (error) {
                console.error('Error al guardar las fechas:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un problema al guardar el folio.'
                });
            });
    } else {
        Swal.fire({
            icon: 'warning',
            title: 'Campos incompletos',
            text: 'Por favor, complete todos los campos obligatorios: Nombre, Tipo, Responsable y Categoría.', // <-- MENSAJE ACTUALIZADO
            confirmButtonColor: '#3085d6'
        });
    }
});


//----------------------------------------------------
// Función para generar el PDF desde los datos de la tabla
// Función mejorada para generar PDF profesional
document.getElementById('btnGenerarPDF').addEventListener('click', function () {
    const todosLosDatos = tblFolios.getData().filter(folio => folio.habilitado == "1");

    if (todosLosDatos.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin datos',
            text: 'No hay folios vigentes para generar el reporte'
        });
        return;
    }

    Swal.fire({
        title: 'Generando PDF...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    setTimeout(() => {
        const doc = new jsPDF();
        const fechaActual = new Date().toLocaleDateString('es-PE', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // ============= ENCABEZADO PRINCIPAL =============
        // Logo o espacio para logo (opcional)
        doc.setFillColor(41, 128, 185); // Azul corporativo
        doc.rect(0, 0, 210, 35, 'F');

        doc.setFontSize(22);
        doc.setFont(undefined, 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('REPORTE DE FOLIOS VIGENTES', 105, 15, { align: 'center' });

        doc.setFontSize(9);
        doc.setFont(undefined, 'normal');
        doc.text(`Fecha de emisión: ${fechaActual}`, 105, 22, { align: 'center' });

        // Resumen ejecutivo
        doc.setFontSize(10);
        doc.setFont(undefined, 'bold');
        doc.text(`Total de folios activos: ${todosLosDatos.length}`, 105, 28, { align: 'center' });

        doc.setTextColor(0, 0, 0);
        let yPosition = 42;

        // ============= SECCIÓN 1: CLASIFICACIÓN POR PRIORIDAD =============
        doc.setFontSize(13);
        doc.setFont(undefined, 'bold');
        doc.setFillColor(52, 73, 94); // Azul oscuro
        doc.rect(10, yPosition, 190, 7, 'F');
        doc.setTextColor(255, 255, 255);
        doc.text('1. CLASIFICACIÓN POR PRIORIDAD', 12, yPosition + 5);
        doc.setTextColor(0, 0, 0);
        yPosition += 10;

        const porPrioridad = {
            'PRINCIPAL': todosLosDatos.filter(f => f.prioridad === 'PRINCIPAL'),
            'ADICIONAL': todosLosDatos.filter(f => f.prioridad === 'ADICIONAL')
        };

        Object.keys(porPrioridad).forEach((prioridad, index) => {
            const folios = porPrioridad[prioridad];

            if (folios.length > 0) {
                // Subtítulo más compacto
                doc.setFontSize(10);
                doc.setFont(undefined, 'bold');
                doc.setFillColor(236, 240, 241);
                doc.rect(10, yPosition, 190, 6, 'F');
                doc.setTextColor(44, 62, 80);
                const etiquetaPrioridad = prioridad === 'PRINCIPAL' ? 'OBLIGATORIO' : prioridad;
                doc.text(`1.${index + 1} ${etiquetaPrioridad}`, 12, yPosition + 4);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(127, 140, 141);
                doc.text(`(${folios.length} ${folios.length === 1 ? 'folio' : 'folios'})`, 50, yPosition + 4);
                doc.setTextColor(0, 0, 0);
                yPosition += 8;

                // Tabla optimizada
                autoTable(doc, {
                    startY: yPosition,
                    head: [['N°', 'Nombre del Folio', 'Tipo', 'Vencimiento']],
                    body: folios.map((folio, idx) => [
                        idx + 1,
                        folio.nombre,
                        folio.tipoFolio,
                        folio.periodo || 'Sin vencimiento'
                    ]),
                    styles: {
                        fontSize: 8,
                        cellPadding: 2,
                        lineColor: [189, 195, 199],
                        lineWidth: 0.1
                    },
                    headStyles: {
                        fillColor: [149, 165, 166],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        halign: 'center',
                        fontSize: 9,
                        cellPadding: 3
                    },
                    alternateRowStyles: {
                        fillColor: [250, 250, 250]
                    },
                    columnStyles: {
                        0: { halign: 'center', cellWidth: 12 },
                        1: { cellWidth: 95 },
                        2: { halign: 'center', cellWidth: 35 },
                        3: { halign: 'center', cellWidth: 38 }
                    },
                    margin: { left: 10, right: 10 },
                    theme: 'grid'
                });

                yPosition = doc.lastAutoTable.finalY + 6;

                // Control de páginas
                if (yPosition > 260) {
                    doc.addPage();
                    yPosition = 20;
                }
            }
        });

        // Nueva página para la segunda clasificación
        if (yPosition > 150) {
            doc.addPage();
            yPosition = 20;
        } else {
            yPosition += 5;
        }

        // ============= SECCIÓN 2: CLASIFICACIÓN POR TIPO =============
        doc.setFontSize(13);
        doc.setFont(undefined, 'bold');
        doc.setFillColor(52, 73, 94);
        doc.rect(10, yPosition, 190, 7, 'F');
        doc.setTextColor(255, 255, 255);
        doc.text('2. CLASIFICACIÓN POR TIPO DE FOLIO', 12, yPosition + 5);
        doc.setTextColor(0, 0, 0);
        yPosition += 10;

        const porTipo = {
            'DOCUMENTO': todosLosDatos.filter(f => f.tipoFolio === 'DOCUMENTO'),
            'FORMATO': todosLosDatos.filter(f => f.tipoFolio === 'FORMATO'),
            'CERTIFICADO': todosLosDatos.filter(f => f.tipoFolio === 'CERTIFICADO')
        };

        // Colores por tipo
        const coloresTipo = {
            'DOCUMENTO': [155, 89, 182],
            'FORMATO': [241, 196, 15],
            'CERTIFICADO': [52, 152, 219]
        };

        Object.keys(porTipo).forEach((tipo, index) => {
            const folios = porTipo[tipo];

            if (folios.length > 0) {
                doc.setFontSize(10);
                doc.setFont(undefined, 'bold');
                doc.setFillColor(236, 240, 241);
                doc.rect(10, yPosition, 190, 6, 'F');

                // Indicador de color por tipo
                const color = coloresTipo[tipo];
                doc.setFillColor(color[0], color[1], color[2]);
                doc.circle(13, yPosition + 3, 1.5, 'F');

                doc.setTextColor(44, 62, 80);
                doc.text(`2.${index + 1} ${tipo}`, 17, yPosition + 4);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(127, 140, 141);
                doc.text(`(${folios.length} ${folios.length === 1 ? 'folio' : 'folios'})`, 55, yPosition + 4);
                doc.setTextColor(0, 0, 0);
                yPosition += 8;

                autoTable(doc, {
                    startY: yPosition,
                    head: [['N°', 'Nombre del Folio', 'Prioridad', 'Vencimiento']],
                    body: folios.map((folio, idx) => [
                        idx + 1,
                        folio.nombre,
                        folio.prioridad,
                        folio.periodo || 'Sin vencimiento'
                    ]),
                    styles: {
                        fontSize: 8,
                        cellPadding: 2,
                        lineColor: [189, 195, 199],
                        lineWidth: 0.1
                    },
                    headStyles: {
                        fillColor: [149, 165, 166],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        halign: 'center',
                        fontSize: 9,
                        cellPadding: 3
                    },
                    alternateRowStyles: {
                        fillColor: [250, 250, 250]
                    },
                    columnStyles: {
                        0: { halign: 'center', cellWidth: 12 },
                        1: { cellWidth: 95 },
                        2: { halign: 'center', cellWidth: 35 },
                        3: { halign: 'center', cellWidth: 38 }
                    },
                    margin: { left: 10, right: 10 },
                    theme: 'grid'
                });

                yPosition = doc.lastAutoTable.finalY + 6;

                if (yPosition > 260) {
                    doc.addPage();
                    yPosition = 20;
                }
            }
        });

        // ============= PIE DE PÁGINA EN TODAS LAS PÁGINAS =============
        const pageCount = doc.internal.getNumberOfPages();
        const ahora = new Date();
        const horaGeneracion = ahora.toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit'
        });

        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);

            // Línea separadora
            doc.setDrawColor(189, 195, 199);
            doc.setLineWidth(0.5);
            doc.line(10, 282, 200, 282);

            // Texto del pie
            doc.setFontSize(7);
            doc.setTextColor(127, 140, 141);
            doc.setFont(undefined, 'normal');
            doc.text(
                'Sistema de Gestión de Recursos Humanos',
                10,
                287
            );
            doc.text(
                `Generado el ${fechaActual} a las ${horaGeneracion}`,
                105,
                287,
                { align: 'center' }
            );
            doc.setFont(undefined, 'bold');
            doc.text(
                `Página ${i} de ${pageCount}`,
                200,
                287,
                { align: 'right' }
            );
        }

        // Abrir en nueva pestaña
        const pdfBlob = doc.output('blob');
        const pdfUrl = URL.createObjectURL(pdfBlob);
        window.open(pdfUrl, '_blank');

        Swal.close();

        Swal.fire({
            icon: 'success',
            title: '¡PDF generado exitosamente!',
            text: 'El reporte se ha abierto en una nueva pestaña',
            timer: 2000,
            showConfirmButton: false
        });
    }, 500);
});




