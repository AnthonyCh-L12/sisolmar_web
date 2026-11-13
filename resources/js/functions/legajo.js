import axios from 'axios';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';
import Swal from 'sweetalert2';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content;

let filtroTexto = "";
let filtroTipo = "TODOS";
let valorCliente, valorCargo;
let nombreCliente = '', nombreCargo = '';

let selectedCliente = null;
let selectedCargo = null;

// ── TABLA CLIENTES ──────────────────────────────────────────────
const tblCliente = new Tabulator("#tblCliente", {
    layout: "fitColumns",
    responsiveLayout: "collapse",
    //   pagination: true,
    //   paginationSize: 8,
    height: "320px",
    locale: "es",
    //langs: { es: { pagination: { first:"Primero", last:"Último", prev:"Anterior", next:"Siguiente", all:"Todo" }, data: { empty: "Sin datos" } } },
    langs: { es: { data: { empty: "Sin datos" } } },
    rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
    columns: [
        { title: "N°", formatter: "rownum", hozAlign: "center", width: 45, headerSort: false },
        { title: "Cliente", field: "razon_social", hozAlign: "left", widthGrow: 1 },
        {
            title: "", field: "acciones", hozAlign: "center", width: 45, headerSort: false,
            formatter: function (cell) {
                const cod = cell.getData().codigo;
                const razon = cell.getData().razon_social;
                return `<input class="form-radio text-primary radCliente" type="radio"
                      name="opCliente" value="${cod}" data-nombre="${razon}">`;
            },
        },
    ],

    rowFormatter: function (row) {
        row.getElement().style.backgroundColor =
            selectedCliente && String(row.getData().codigo) === String(selectedCliente)
                ? '#bfdbfe' : '';
    }
});

// ── TABLA CARGOS ─────────────────────────────────────────────────
const tblCargo = new Tabulator("#tblCargo", {
    layout: "fitColumns",
    responsiveLayout: "collapse",
    //pagination: true,
    //   paginationSize: 8,
    height: "320px",
    locale: "es",
    //langs: { es: { pagination: { first:"Primero", last:"Último", prev:"Anterior", next:"Siguiente", all:"Todo" }, data: { empty: "Sin datos" } } },
    langs: { es: { data: { empty: "Sin datos" } } },
    rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
    columns: [
        { title: "N°", formatter: "rownum", hozAlign: "center", width: 45, headerSort: false },
        {
            title: "Cargo", field: "nombre", hozAlign: "left", widthGrow: 1,
            formatter: function (cell) {
                const nombre = cell.getValue();
                const tipo = cell.getData().tipo;
                const badge = tipo === 'OPERATIVO'
                    ? `<span class="ms-1.5 px-1.5 py-0.5 rounded text-xs font-medium bg-info/20 text-info">OPER</span>`
                    : `<span class="ms-1.5 px-1.5 py-0.5 rounded text-xs font-medium bg-primary/20 text-primary">ADMIN</span>`;
                return nombre + badge;
            }
        },
        {
            title: "", field: "acciones", hozAlign: "center", width: 45, headerSort: false,
            formatter: function (cell) {
                const cod = cell.getData().codigo;
                const nombre = cell.getData().nombre;
                return `<input class="form-radio text-primary radCargo" type="radio"
                      name="opCargo" value="${cod}" data-nombre="${nombre}">`;
            },
        },
    ],
    rowFormatter: function (row) {
        row.getElement().style.backgroundColor =
            selectedCargo && String(row.getData().codigo) === String(selectedCargo)
                ? '#bfdbfe' : '';
    }
});

document.querySelector("#tblCargo").classList.add("disabled-table");

// ── TABLA FOLIOS ─────────────────────────────────────────────────
let datosFoliosGlobal = [];

// ── 1. TABLA FOLIOS (PRINCIPAL - SOLO SELECCIONADOS) ─────────────
const tblFolio = new Tabulator("#tblFolio", {
    layout: "fitColumns",
    responsiveLayout: "collapse",
    height: "400px",
    locale: "es",
    langs: { es: { data: { empty: "No hay folios en este legajo. Haga clic en 'Agregar más folios' abajo." } } },
    rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
    columns: [
        { title: "N°", formatter: "rownum", hozAlign: "center", width: 45, headerSort: false },
        {
            title: "Folio", field: "nombre", hozAlign: "left", widthGrow: 2,
            formatter: function (cell) {
                const nombre = cell.getData().nombre;
                const notif = cell.getData().notificacion;
                if (notif == '-1') return nombre;
                const badge = notif == '0'
                    ? `<span class="ms-2 inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-success/20 text-success"><i class="fa-solid fa-envelope"></i> Activar</span>`
                    : `<span class="ms-2 inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-danger/20 text-danger"><i class="fa-solid fa-envelope"></i> Desactivar</span>`;
                return nombre + badge;
            }
        },
        {
            title: "Tipo", hozAlign: "center", widthGrow: 1, headerSort: false,
            formatter: function (cell) {
                const obl = cell.getData().obligatorio;
                return obl == '0'
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-default-100 text-default-800">ADICIONAL</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/25 text-primary-800">OBLIGATORIO</span>`;
            }
        },
        {
            title: "Acción", hozAlign: "center", width: 70, headerSort: false,
            formatter: function () {
                return `<button type="button" class="text-danger hover:text-red-700 bg-red-100 hover:bg-red-200 px-2 py-1 rounded" title="Quitar folio"><i class="fa-solid fa-trash-can"></i></button>`;
            },
            cellClick: function (e, cell) {
                if (e.target.closest('button')) {
                    const cod = cell.getData().codigo;
                    const item = datosFoliosGlobal.find(f => f.codigo === cod);
                    if (item) item._selected = false;
                    actualizarTablasFolios();
                }
            }
        },
    ]
});
document.querySelector("#tblFolio").classList.add("disabled-table");

// ── 2. TABLA FOLIOS (MODAL - SOLO NO SELECCIONADOS) ──────────────
const tblFolioModal = new Tabulator("#tblFolioModal", {
    layout: "fitColumns",
    responsiveLayout: "collapse",
    height: "500px",
    locale: "es",
    langs: { es: { data: { empty: "¡Todos los folios ya han sido agregados a este legajo!" } } },
    rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
    columns: [
        {
            title: "", hozAlign: "center", width: 50, headerSort: false,
            formatter: function (cell) {
                const rowData = cell.getData();
                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.className = "form-checkbox rounded text-primary w-4 h-4 cursor-pointer";
                checkbox.checked = !!rowData._tempSelected;
                checkbox.addEventListener("change", function () {
                    rowData._tempSelected = this.checked;
                });
                return checkbox;
            },
        },
        { title: "N°", formatter: "rownum", hozAlign: "center", width: 45, headerSort: false },
        { title: "Folio", field: "nombre", hozAlign: "left", widthGrow: 2 },
        {
            title: "Tipo", hozAlign: "center", widthGrow: 1, headerSort: false,
            formatter: function (cell) {
                const obl = cell.getData().obligatorio;
                return obl == '0'
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-default-100 text-default-800">ADICIONAL</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/25 text-primary-800">OBLIGATORIO</span>`;
            }
        }
    ]
});

// ── 3. LÓGICA DE REPARTO DE FOLIOS ───────────────────────────────
function actualizarTablasFolios() {
    const ordenarPorTipo = (a, b) => b.obligatorio - a.obligatorio;

    const seleccionados = datosFoliosGlobal.filter(f => f._selected).sort(ordenarPorTipo);
    tblFolio.setData(seleccionados);

    const noSeleccionados = datosFoliosGlobal.filter(f => !f._selected).sort(ordenarPorTipo);
    noSeleccionados.forEach(f => f._tempSelected = false);
    tblFolioModal.setData(noSeleccionados);
}

document.getElementById('btnAgregarFoliosSeleccionados')?.addEventListener('click', () => {
    let agregados = 0;
    datosFoliosGlobal.forEach(f => {
        if (f._tempSelected && !f._selected) {
            f._selected = true;
            agregados++;
        }
    });

    if (agregados > 0) {
        actualizarTablasFolios();
        document.getElementById('modalAgregarFolios').classList.add('hidden');
    } else {
        Swal.fire({ title: "Atención", text: "No marcaste ningún folio.", icon: "warning" });
    }
});

// ── FILTROS CARGO ────────────────────────────────────────────────
function aplicarFiltros() {
    const filtros = [];
    if (filtroTexto) filtros.push({ field: "nombre", type: "like", value: filtroTexto });
    if (filtroTipo !== "TODOS") filtros.push({ field: "tipo", type: "=", value: filtroTipo });
    filtros.length ? tblCargo.setFilter(filtros) : tblCargo.clearFilter();
}

document.getElementById("buscarCargo").addEventListener("keyup", function () {
    filtroTexto = this.value.toLowerCase().trim();
    aplicarFiltros();
});

document.querySelectorAll('input[name="cargoFiltro"]').forEach(radio => {
    radio.addEventListener("change", function () {
        filtroTipo = this.value;
        aplicarFiltros();
    });
});

document.getElementById("buscarCliente").addEventListener("keyup", function () {
    const val = this.value.toLowerCase().trim();
    tblCliente.setFilter([[
        { field: "razon_social", type: "like", value: val },
        { field: "RUC", type: "like", value: val },
        { field: "abreviatura", type: "like", value: val },
    ]]);
});

document.getElementById("buscarFolio").addEventListener("keyup", function () {
    const val = this.value.toLowerCase().trim();
    val ? tblFolio.setFilter([{ field: "nombre", type: "like", value: val }])
        : tblFolio.clearFilter();
});

// ── CARGA DE DATOS ───────────────────────────────────────────────
function cargarCargos() {
    axios.get(`${VITE_URL_APP}/api/get-cargo`)
        .then(r => tblCargo.setData(r.data))
        .catch(e => console.error("Error cargando cargos:", e));
}

function cargarClientes() {
    axios.get(`${VITE_URL_APP}/api/get-clientes`)
        .then(r => tblCliente.setData(r.data))
        .catch(e => console.error("Error cargando clientes:", e));
}

function cargarFolios(codCliente, codCargo) {
    axios.get(`${VITE_URL_APP}/api/get-folios/${codCliente}/${codCargo}`)
        .then(r => {
            // Guardamos todos los folios en nuestra variable global "Cerebro"
            datosFoliosGlobal = r.data.map(f => {
                f._selected = (f.tiene != 0); // Si viene de BD con check, a la tabla principal
                f._tempSelected = false;      // Limpio para el modal
                return f;
            });
            
            // Repartimos datos (seleccionados vs no seleccionados)
            actualizarTablasFolios();

            const legajoValido = r.data.find(f => f.codigo_legajo != 0);
            document.getElementById('hidLegajo').value = legajoValido?.codigo_legajo ?? 0;
        })
        .catch(e => console.error("Error cargando folios:", e));
}

cargarCargos();
cargarClientes();

// ── SELECCIÓN CLIENTE / CARGO ────────────────────────────────────
function manejarCambioSeleccion(event) {
    if (!event.target.matches(".radCliente") && !event.target.matches(".radCargo")) return;

    const esCliente = event.target.matches(".radCliente");

    if (esCliente) {
        valorCliente = event.target.value;
        nombreCliente = event.target.dataset.nombre;
        selectedCliente = valorCliente;
        tblCliente.redraw(true);
        document.querySelector("#tblCargo").classList.remove("disabled-table");
    } else {
        valorCargo = event.target.value;
        nombreCargo = event.target.dataset.nombre;
        selectedCargo = valorCargo;
        tblCargo.redraw(true);
    }

    if (valorCliente && valorCargo) {
        const info = document.getElementById('infoSeleccion');
        info.innerHTML = `
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text font-medium bg-blue-100 text-blue-700">${nombreCliente}</span>
              <span class="text-gray-400 mx-1">›</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text font-medium bg-primary/20 text-primary">${nombreCargo}</span>
          `;
        info.className = 'flex items-center gap-1';
        document.getElementById('txtNombre').value = `${nombreCliente} — ${nombreCargo}`;
          document.querySelector("#tblFolio").classList.remove("disabled-table");
          document.getElementById("btnRegistrar").disabled = false;
          document.getElementById("btnAbrirModalFolios").classList.remove("hidden"); // 🔥 Muestra el botón
          cargarFolios(valorCliente, valorCargo);
    }
}

document.addEventListener("change", manejarCambioSeleccion);

// ── NOTIFICACIONES ───────────────────────────────────────────────
function formatFecha(fecha) {
    if (!fecha) return '';
    const d = new Date(fecha);
    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

function renderNotificaciones(data) {
    const badge = document.getElementById('notifBadge');
    const container = document.getElementById('notifContainer');

    if (data.length > 0) {
        badge.textContent = data.length;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }

    if (data.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 text-sm py-6">Sin notificaciones.</p>';
        return;
    }

    container.innerHTML = data.map(nf => `
          <div class="mb-3 p-3 bg-blue-50 border-l-4 border-blue-400 rounded-lg">
              <div class="flex gap-2">
                  <i class="fa-solid ${nf.tipo == 1 ? 'fa-trash-can text-red-500' : 'fa-plus text-green-500'} mt-0.5 flex-shrink-0"></i>
                  <div class="text-sm flex-1">
                        <p class="font-semibold">Solicitud de ${nf.tipo == 1 ? 'Desactivación' : 'Activación'}</p>
                      <p class="text-gray-600 mt-0.5 text-xs">
                          Folio: <b>${nf.folio}</b><br>
                          Cliente: <b>${nf.cliente}</b><br>
                          Cargo: <b>${nf.cargo}</b>
                      </p>
                      <div class="flex justify-between items-center mt-2">
                          <span class="text-xs text-gray-400">${formatFecha(nf.fecha)} — ${nf.hora}</span>
                          <button type="button"
                              class="btn btn-sm rounded-full bg-warning/25 text-warning hover:bg-warning hover:text-white text-xs"
                              onclick="quitarNotificacion('${nf.codigo}')">
                              Quitar
                          </button>
                      </div>
                  </div>
              </div>
          </div>
      `).join('');
}

function cargarNotificaciones() {
    axios.get(`${VITE_URL_APP}/api/get-notificaciones`)
        .then(r => renderNotificaciones(r.data))
        .catch(e => console.error("Error cargando notificaciones:", e));
}

cargarNotificaciones();
setInterval(cargarNotificaciones, 10000);

window.quitarNotificacion = function (valor) {
    Swal.fire({
        title: "¿Quitar esta notificación?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí",
        cancelButtonText: "No",
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(`${VITE_URL_APP}/api/delete-notif`, { codigo: valor })
            .then(() => {
                Swal.fire({ title: "Eliminado", icon: "success", timer: 1500, showConfirmButton: false })
                    .then(() => cargarNotificaciones());
            })
            .catch(e => console.error("Error eliminando notificación:", e));
    });
};

// ── GUARDAR LEGAJO ───────────────────────────────────────────────
window.guardarLegajo = function () {
    const data = tblFolio.getData();
    const selecFolios = data.filter(r => r._selected).map(r => r.codigo);

    if (selecFolios.length === 0) {
        Swal.fire({
            title: "Sin folios seleccionados",
            text: "Seleccione al menos un folio para guardar el legajo.",
            icon: "warning",
            confirmButtonText: "Entendido"
        });
        return;
    }

    Swal.fire({
        title: "¿Guardar legajo?",
        text: `${nombreCliente} — ${nombreCargo}`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(`${VITE_URL_APP}/api/save_legajo`, {
            folios: selecFolios,
            codCliente: valorCliente,
            codCargo: valorCargo,
            codLegajo: document.getElementById('hidLegajo').value,
            nombre: document.getElementById('txtNombre').value,
        })
            .then(() => {
                Swal.fire({ title: "Guardado", icon: "success" })
                    .then(() => cargarFolios(valorCliente, valorCargo));
            })
            .catch(e => console.error("Error guardando legajo:", e));
    });
};

// ── GUARDAR LEGAJO ───────────────────────────────────────────────
window.guardarLegajo = function () {
    // Sacamos todos los codigos directo de la tabla principal
    const selecFolios = tblFolio.getData().map(r => r.codigo);

    if (selecFolios.length === 0) {
        Swal.fire({
            title: "Sin folios seleccionados",
            text: "El legajo está vacío, agregue al menos un folio.",
            icon: "warning",
            confirmButtonText: "Entendido"
        });
        return;
    }

    Swal.fire({
        title: "¿Guardar legajo?",
        text: `${nombreCliente} — ${nombreCargo}`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(`${VITE_URL_APP}/api/save_legajo`, {
            folios: selecFolios,
            codCliente: valorCliente,
            codCargo: valorCargo,
            codLegajo: document.getElementById('hidLegajo').value,
            nombre: document.getElementById('txtNombre').value,
        })
            .then(() => {
                Swal.fire({ title: "Guardado", icon: "success" })
                    .then(() => cargarFolios(valorCliente, valorCargo));
            })
            .catch(e => console.error("Error guardando legajo:", e));
    });
};
