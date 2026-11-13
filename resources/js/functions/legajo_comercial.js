import axios from 'axios';
  import { TabulatorFull as Tabulator } from 'tabulator-tables';
  import 'tabulator-tables/dist/css/tabulator_simple.min.css';
  import Swal from 'sweetalert2';

  axios.defaults.withCredentials = true;
  axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content;

  let valorCliente, valorCargo;
  let nombreCliente = '', nombreCargo = '';
  let selectedCliente = null, selectedCargo = null;

  // ── TABLA CLIENTES ───────────────────────────────────────────────
  const tblCliente = new Tabulator("#tblCliente", {
      layout: "fitColumns",
      responsiveLayout: "collapse",
      height: "320px",
      locale: "es",
      langs: { es: { data: { empty: "Sin datos" } } },
      rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
      columns: [
          { title: "N°", formatter: "rownum", hozAlign: "center", width: 45, headerSort: false },
          { title: "Cliente", field: "razon_social", hozAlign: "left", widthGrow: 1 },
          { title: "", hozAlign: "center", width: 45, headerSort: false,
              formatter: function(cell) {
                  const cod    = cell.getData().codigo;
                  const razon  = cell.getData().razon_social;
                  return `<input class="form-radio text-primary radCliente" type="radio"
                      name="opCliente" value="${cod}" data-nombre="${razon}">`;
              },
          },
      ],
      rowFormatter: function(row) {
          row.getElement().style.backgroundColor =
              selectedCliente && String(row.getData().codigo) === String(selectedCliente)
                  ? '#bfdbfe' : '';
      }
  });

  // ── TABLA CARGOS ─────────────────────────────────────────────────
  const tblCargo = new Tabulator("#tblCargo", {
      layout: "fitColumns",
      responsiveLayout: "collapse",
      height: "320px",
      locale: "es",
      langs: { es: { data: { empty: "Sin datos" } } },
      rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
      columns: [
          { title: "N°", formatter: "rownum", hozAlign: "center", width: 45, headerSort: false },
          { title: "Cargo", field: "nombre", hozAlign: "left", widthGrow: 1,
              formatter: function(cell) {
                  const nombre = cell.getValue();
                  const tipo   = cell.getData().tipo;
                  const badge  = tipo === 'OPERATIVO'
                      ? `<span class="ms-1.5 px-1.5 py-0.5 rounded text-xs font-medium bg-info/20 text-info">OPER</span>`
                      : `<span class="ms-1.5 px-1.5 py-0.5 rounded text-xs font-medium bg-primary/20 text-primary">ADMIN</span>`;
                  return nombre + badge;
              }
          },
          { title: "", hozAlign: "center", width: 45, headerSort: false,
              formatter: function(cell) {
                  const cod    = cell.getData().codigo;
                  const nombre = cell.getData().nombre;
                  return `<input class="form-radio text-primary radCargo" type="radio"
                      name="opCargo" value="${cod}" data-nombre="${nombre}">`;
              },
          },
      ],
      rowFormatter: function(row) {
          row.getElement().style.backgroundColor =
              selectedCargo && String(row.getData().codigo) === String(selectedCargo)
                  ? '#bfdbfe' : '';
      }
  });

  document.querySelector("#tblCargo").classList.add("disabled-table");

  // ── TABLA FOLIOS ─────────────────────────────────────────────────
  const tblFolio = new Tabulator("#tblFolio", {
      layout: "fitColumns",
      responsiveLayout: "collapse",
      locale: "es",
      height: "660px",
      langs: { es: { data: { empty: "Seleccione cliente y cargo" } } },
      rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
      columns: [
          { title: "N°", formatter: "rownum", hozAlign: "center", width: 30, headerSort: false },
          { title: "Folio", field: "nombre", hozAlign: "left", widthGrow: 1 },
          { title: "Tipo", hozAlign: "center", widthGrow: 1, headerSort: false,
              formatter: function(cell) {
                  const obl = cell.getData().obligatorio;
                  return obl == '0'
                      ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-default-100 text-default-800">ADICIONAL</span>`
                      : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/25 text-primary-800">OBLIGATORIO</span>`;
              }
          },
          { title: "Solicitar", hozAlign: "center", widthGrow: 1, headerSort: false,
              formatter: function(cell) {
                  const sol = cell.getData().solicitud;
                  if (sol == '0') {
                      return `<button class="btn bg-primary text-white rounded-full btn-enviar">
                          <i class="fa-solid fa-paper-plane btn-enviar"></i>
                      </button>`;
                  }
                  const tipoSol = cell.getData().tipoSolicitar;
                  const color   = tipoSol == '0' ? 'text-green-600' : 'text-red-500';
                  const texto   = tipoSol == '0' ? 'Para activación' : 'Para desactivación';
                  return `<span class="text-xs font-medium ${color}">${texto}</span>`;
              },
              cellClick: function(e, cell) {
                    if (!e.target.classList.contains("btn-enviar")) return;
                    const cod   = cell.getData().codigo;
                    const tiene = cell.getData().tiene;
                    Swal.fire({
                        title: `Enviar solicitud de ${tiene != 0 ? 'desactivación' : 'activación'}`,
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33",
                        confirmButtonText: "Sí",
                        cancelButtonText: "Cancelar",
                    }).then(result => {
                        if (!result.isConfirmed) return;
                        axios.post(`${VITE_URL_APP}/api/save-solicitud`, {
                            tiene:   tiene,
                            codigo:  cod,
                            cargo:   valorCargo,
                            cliente: valorCliente,
                        })
                        .then(() => {
                            Swal.fire({ title: "Enviado", icon: "success", timer: 1500, showConfirmButton: false })
                                .then(() => cargarFolios(valorCliente, valorCargo));
                        })
                        .catch(e => console.error("Error al enviar solicitud:", e));
                    });
                },
          },
        //   { title: "", hozAlign: "center", width: 50, headerSort: false,
        //       formatter: function(cell) {
        //           const cod  = cell.getData().codigo;
        //           const tiene = cell.getData().tiene;
        //           return `<input type="checkbox" disabled class="form-checkbox rounded text-primary"
        //               value="${cod}" ${tiene != 0 ? 'checked' : ''}>`;
        //       },
        //   },
           { title: "Estado", hozAlign: "center", width: 90, headerSort: false,
                formatter: function(cell) {
                    const tiene = cell.getData().tiene;
                    return tiene != 0
                        ? `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">ACTIVO</span>`
                        : `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-600">DESACTIVADO</span>`;
                }
            },
      ],
      rowFormatter: function(row) {
          row.getElement().style.backgroundColor =
              row.getData().obligatorio != '0' ? '#E0EEFF' : '#f1f5f9';
      }
  });

  document.querySelector("#tblFolio").classList.add("disabled-table");

  // ── BUSCADORES ───────────────────────────────────────────────────
  document.getElementById("buscarCliente").addEventListener("keyup", function() {
      const val = this.value.toLowerCase().trim();
      tblCliente.setFilter([[
          { field: "razon_social", type: "like", value: val },
          { field: "RUC",          type: "like", value: val },
      ]]);
  });

  document.getElementById("buscarCargo").addEventListener("keyup", function() {
      const val = this.value.toLowerCase().trim();
      val ? tblCargo.setFilter([{ field: "nombre", type: "like", value: val }])
          : tblCargo.clearFilter();
  });

  document.getElementById("buscarFolio").addEventListener("keyup", function() {
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
      axios.get(`${VITE_URL_APP}/api/get-folios-comercial/${codCliente}/${codCargo}`)
          .then(r => {
              tblFolio.setData(r.data);
              const legajoValido = r.data.find(f => f.codigo_legajo != 0);
              document.getElementById('hidLegajo').value = legajoValido?.codigo_legajo ?? 0;
          })
          .catch(e => console.error("Error cargando folios:", e));
  }

  cargarCargos();
  cargarClientes();

  // ── SELECCIÓN CLIENTE / CARGO ────────────────────────────────────
  document.addEventListener("change", function(event) {
      if (!event.target.matches(".radCliente") && !event.target.matches(".radCargo")) return;

      const esCliente = event.target.matches(".radCliente");

      if (esCliente) {
          valorCliente    = event.target.value;
          nombreCliente   = event.target.dataset.nombre;
          selectedCliente = valorCliente;
          tblCliente.redraw(true);
          document.querySelector("#tblCargo").classList.remove("disabled-table");
      } else {
          valorCargo    = event.target.value;
          nombreCargo   = event.target.dataset.nombre;
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
          cargarFolios(valorCliente, valorCargo);
      }
  });
