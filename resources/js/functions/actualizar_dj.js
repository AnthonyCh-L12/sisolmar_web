// ============================================================
// gestion_dj.js — Lógica principal del módulo Gestión DJ
// ============================================================
// PDF separado en: ./dj_pdf.js
// ============================================================
import axios from 'axios';
import Swal from 'sweetalert2';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';
import Tagify from '@yaireo/tagify';
import '@yaireo/tagify/dist/tagify.css';
import ExcelJS from 'exceljs'

import { generarDeclaracionJuradaPDF, generarReporteFaltantesPDF } from './dj_pdf.js';
import { generarPDF } from './chargefile/reporteAvancesPDF.js';
import { generarExcel } from './chargefile/reporteAvancesExcel.js';
import { obtenerDatos } from './chargefile/reporteAvances.js';

const API_URL = `${VITE_URL_APP}/api`;
let registroSeleccionado = null;
let generadosCache = {};
let generadosCacheLoaded = false;

async function cargarGeneradosCache(codPeriodo = '2026') {
    try {
        const res = await axios.get(`${API_URL}/dj/get-check-pdf`, { params: { codPeriodo } });
        if (res.data.success) {
            generadosCache = res.data.data || {};
            generadosCacheLoaded = true;
        }
    } catch (e) {
        console.warn('Error cargando generadosCache:', e);
        generadosCache = {};
        generadosCacheLoaded = true;
    }
}

function estaGenerado(codPersonal, fechaCambioActual) {
    if (!generadosCacheLoaded) return false;
    const reg = generadosCache[codPersonal];
    if (!reg) return false;
    return true;
}

function getGeneradosSet() {
    return new Set(Object.keys(generadosCache));
}

async function marcarGeneradosAPI(items, codPeriodo = '2026') {
    const codigos = items.map(i => String(i.codPersonal || i.CODI_PERS || i.id));
    if (!codigos.length) return;
    try {
        const res = await axios.post(`${API_URL}/dj/update-check-pdf`, { codigos, codPeriodo });
        if (res.data.success) {
            codigos.forEach(c => { generadosCache[c] = { generado: true }; });
        }
    } catch (e) {
        console.error('Error marcando generados:', e);
    }
}

async function resetearGeneradosAPI(codigos = null, codPeriodo = '2026') {
    try {
        const res = await axios.post(`${API_URL}/dj/reset-check-pdf`, { codigos, codPeriodo });
        if (res.data.success) {
            if (codigos && codigos.length) {
                codigos.forEach(c => delete generadosCache[c]);
            } else {
                generadosCache = {};
            }
        }
    } catch (e) {
        console.error('Error reseteando generados:', e);
    }
}

const categoriasSe = {
    'A': [
        { val: 'A-I', text: 'A-I: Particulares' },
        { val: 'A-IIa', text: 'A-IIa: Taxi / Ambulancia' },
        { val: 'A-IIb', text: 'A-IIb: Microbús / Pickup' },
        { val: 'A-IIIa', text: 'A-IIIa: Ómnibus' },
        { val: 'A-IIIb', text: 'A-IIIb: Camiones' },
        { val: 'A-IIIc', text: 'A-IIIc: Todos los anteriores' }
    ],
    'B': [
        { val: 'B-IIa', text: 'B-IIa: Bicimotos' },
        { val: 'B-IIb', text: 'B-IIb: Motocicletas' },
        { val: 'B-IIc', text: 'B-IIc: Mototaxis' }
    ]
};

const PAUSA_ENTRE_REGISTROS = 800;

async function esperarConBackoff(intento, baseMs = 1000) {
    const espera = baseMs * Math.pow(2, intento); // 1s, 2s, 4s, 8s...
    const jitter = Math.random() * 300;           // evita que todo reintente al mismo tiempo
    await new Promise(r => setTimeout(r, espera + jitter));
}

// ③ Wrapper para obtener datos con retry ante 429
async function obtenerDatosConRetry(codiPers, source = 'migracion', maxReintentos = 4) {
    for (let intento = 0; intento <= maxReintentos; intento++) {
        try {
            const response = await axios.get(`${API_URL}/dj/get-personal-data`, {
                params: { codi_pers: codiPers, source }
            });
            return response.data;
        } catch (err) {
            const status = err?.response?.status;

            if (status === 429 && intento < maxReintentos) {
                // Leer el header Retry-After si el servidor lo manda
                const retryAfter = err.response?.headers?.['retry-after'];
                const esperaMs = retryAfter
                    ? parseInt(retryAfter) * 1000
                    : null;

                console.warn(`[429] ${codiPers} — reintento ${intento + 1}/${maxReintentos}`);

                if (esperaMs) {
                    await new Promise(r => setTimeout(r, esperaMs + 200));
                } else {
                    await esperarConBackoff(intento);
                }
                continue;
            }

            // Si no es 429 o se agotaron reintentos, propagar el error
            throw err;
        }
    }
}


function actualizarCategorias() {
    const claseSel = document.getElementById('clase_brevete').value;
    const catSelect = document.getElementById('tipo_vehiculo');

    // Limpiar opciones previas
    catSelect.innerHTML = '<option value="">-- Seleccione Categoría --</option>';

    if (claseSel && categoriasSe[claseSel]) {
        categoriasSe[claseSel].forEach(item => {
            let opt = document.createElement('option');
            opt.value = item.val;
            opt.textContent = item.text;
            catSelect.appendChild(opt);
        });
    }
}


function marcarDJGeneradosBatch(items) {
    marcarGeneradosAPI(items);
}

// ============================================================
// DOCUMENT READY
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('clase_brevete').addEventListener('change', actualizarCategorias);

    cargarGeneradosCache();

    // ============================================================
    // MACRO TABS: ANUAL VS DEMANDA
    // ============================================================
    window.modoDJ = 'anual'; // Estado global
    const btnModoAnual = document.getElementById('btnModoAnual');
    const btnModoDemanda = document.getElementById('btnModoDemanda');
    const tabBtnEtapa1 = document.querySelector('.tab-btn[data-target="etapa1"]');
    const tabBtnEtapa2 = document.querySelector('.tab-btn[data-target="etapa2"]');

    function toggleModoDJ(modo) {
        window.modoDJ = modo;
        
        const divAnio = document.getElementById('contenedorFiltroAnio');
        const navDest1 = document.getElementById('nav-dest-etapa1');
        const navDest2 = document.getElementById('nav-dest-etapa2');
        
        if (modo === 'anual') {
            // MOSTRAR Año y FORZAR PESTAÑAS ABAJO (w-full order-last mt-4)
            if (divAnio) divAnio.style.display = 'flex';
            if (navDest1) navDest1.className = 'flex justify-center transition-all w-full order-last mt-4';
            if (navDest2) navDest2.className = 'flex justify-center transition-all w-full order-last mt-4';

            // Estilos de botones
            btnModoAnual.classList.add('active', 'bg-white', 'text-blue-900', 'shadow-md');
            btnModoAnual.classList.remove('text-gray-500', 'hover:text-blue-700');
            btnModoDemanda.classList.remove('active', 'bg-white', 'text-blue-900', 'shadow-md');
            btnModoDemanda.classList.add('text-gray-500', 'hover:text-blue-700');
            
            // Mostrar Etapa 1
            if(tabBtnEtapa1) tabBtnEtapa1.style.display = 'inline-flex';
            
            // Ir a Etapa 1 por defecto al cambiar a Anual
            if(tabBtnEtapa1 && !tabBtnEtapa1.classList.contains('active')) {
                tabBtnEtapa1.click();
            }
        } else {
            // OCULTAR Año y MANTENER PESTAÑAS AL CENTRO (flex-1)
            if (divAnio) divAnio.style.display = 'none';
            if (navDest1) navDest1.className = 'flex-1 flex justify-center transition-all';
            if (navDest2) navDest2.className = 'flex-1 flex justify-center transition-all';

            // Estilos de botones
            btnModoDemanda.classList.add('active', 'bg-white', 'text-blue-900', 'shadow-md');
            btnModoDemanda.classList.remove('text-gray-500', 'hover:text-blue-700');
            btnModoAnual.classList.remove('active', 'bg-white', 'text-blue-900', 'shadow-md');
            btnModoAnual.classList.add('text-gray-500', 'hover:text-blue-700');
            
            // Ocultar Etapa 1
            if(tabBtnEtapa1) tabBtnEtapa1.style.display = 'none';
            
            // Forzar navegación a Etapa 2
            if(tabBtnEtapa2 && !tabBtnEtapa2.classList.contains('active')) {
                tabBtnEtapa2.click();
            }
        }
        
        // Refrescar las tablas si ya están instanciadas para aplicar el filtro
        if (typeof tblPersonasVerificado !== 'undefined') aplicarFiltrosE2(); 
    }

    if (btnModoAnual && btnModoDemanda) {
        btnModoAnual.addEventListener('click', () => toggleModoDJ('anual'));
        btnModoDemanda.addEventListener('click', () => toggleModoDJ('demanda'));
    }
    if (new URLSearchParams(window.location.search).get('modo') === 'demanda') {
        setTimeout(() => btnModoDemanda?.click(), 100);
    }

    // ============================================================
    // TIMELINE TABS (NUEVO)
    // ============================================================
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.classList.contains('cursor-not-allowed')) return;

            document.querySelectorAll('.tab-btn').forEach(b => {
                const inactive = (b.dataset.inactive || 'bg-white text-gray-500 border border-gray-200').split(' ');
                b.classList.remove(...(b.dataset.active || 'bg-primary text-white shadow-lg').split(' '), 'active');
                b.classList.add(...inactive);
                b.classList.remove('shadow-lg');
            });
            document.querySelectorAll('.tab-content').forEach(c => {
                c.classList.add('hidden');
                c.classList.remove('active');
            });

            const active = (btn.dataset.active || 'bg-primary text-white shadow-lg').split(' ');
            const inactive = (btn.dataset.inactive || 'bg-white text-gray-500 border border-gray-200').split(' ');
            btn.classList.remove(...inactive);
            btn.classList.add(...active, 'active');

            const targetId = btn.getAttribute('data-target');
            document.getElementById(targetId).classList.remove('hidden');
            document.getElementById(targetId).classList.add('active');

            // --- TELETRANSPORTACIÓN DEL DOM DE LAS PESTAÑAS ---
            const navTabs = document.getElementById('dj-timeline-tabs');
            const destContainer = document.getElementById(`nav-dest-${targetId}`);
            if (navTabs && destContainer) {
                destContainer.appendChild(navTabs);
            }
            // ----------------------------------------------------

            if (targetId === 'etapa1' && typeof tblEtapa1 !== 'undefined') cargarDatosEtapa1();
            if (targetId === 'etapa2' && typeof tblPersonasVerificado !== 'undefined') cargarDatosEtapa2();
            if (targetId === 'etapa3' && typeof tblPersonasEtapa3 !== 'undefined') cargarDatosEtapa3();
            if (targetId === 'etapa_carga' && typeof tblPersonas_E4C !== 'undefined') {
                seleccionarPrimeraSucursalValida_E4C();
                recargarTodo_E4C();
            }
            if (targetId === 'etapa4' && typeof tblEtapa4 !== 'undefined') cargarDatosEtapa4();
        });
    });

    // ⬇️ NUEVO: Auto-abrir pestaña si viene en la URL (Deep Linking) ⬇️
    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    if (tabFromUrl) {
        const tabBtn = document.querySelector(`.tab-btn[data-target="${tabFromUrl}"]`);
        if (tabBtn && !tabBtn.classList.contains('cursor-not-allowed')) {
            // Un pequeño setTimeout garantiza que todo el DOM y Tabulator estén listos
            setTimeout(() => {
                tabBtn.click();
            }, 100);
        }
    }
    // ⬆️ FIN NUEVO ⬆️

    function reformatNums(table) {
        function rf() { table.getRows("active").forEach(r => r.reformat()); }
        table.on("dataLoaded", rf);
        table.on("pageLoaded", rf);
        table.on("dataSorted", rf);
        table.on("dataFiltered", () => {
            table.setPage(1);
            rf();
        });
    }

    // NUEVO EVENTO PARA RECARGAR DATOS CUANDO CAMBIE EL AÑO
    document.getElementById('filtroAnio')?.addEventListener('change', () => {
        if (document.getElementById('etapa1').classList.contains('active')) {
            cargarDatosEtapa1();
        } else if (document.getElementById('etapa2').classList.contains('active')) {
            cargarDatosEtapa2();
        }
    });

    const tblEtapa1 = new Tabulator("#tblEtapa1", {
        height: "550px",
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: true,
        paginationSize: 20,
        rowFormatter: function (row) {
            const d = row.getData();
            const el = row.getElement();
            el.classList.toggle('rep-cesado', d.SIP_CAMBIO !== 'Ok');
            el.style.removeProperty('background-color');
        },
        locale: "es",
        // --- AQUÍ TRADUCIMOS EL PAGINADOR ---
        langs: {
            "es": {
                "pagination": {
                    "first": "Primero",
                    "prev": "Anterior",
                    "next": "Siguiente",
                    "last": "Último"
                }
            }
        },
        // ------------------------------------
        columns: [
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 60, 
                headerSort: false, 
                responsive: false 
            },
            {
                title: "Actualizado", field: "SIP_CAMBIO", hozAlign: "center", width: 140,
                formatter: cell => {
                    const val = cell.getValue();
                    const color = val === 'Ok' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300';
                    return `<span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold tracking-wider ${color}">${val === 'Ok' ? 'SI' : 'NO'}</span>`;
                }
            },
            {
                title: "Apellidos", hozAlign: "left", widthGrow: 2,
                formatter: cell => {
                    const d = cell.getData();
                    return `${d.APEL_1 ?? d.apellido1 ?? ''} ${d.APEL_2 ?? d.apellido2 ?? ''}`.trim() || '—';
                }
            },
            {
                title: "Nombres", hozAlign: "left", widthGrow: 1.5,
                formatter: cell => {
                    const d = cell.getData();
                    return `${d.NOMB_1 ?? d.nombres ?? ''} ${d.NOMB_2 ?? ''}`.trim() || '—';
                }
            },
            { title: "DNI", field: "NRO_DOCU_IDEN", hozAlign: "center", width: 110 },
            { title: "Sucursal", field: "SUCURSAL", hozAlign: "center", widthGrow: 1 },
            {
                title: "Tipo", field: "TIPO_PER", hozAlign: "center", widthGrow: 2,
                formatter: cell => {
                    const val = cell.getValue() ?? '';
                    let color = 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm';
                    
                    if (val.toUpperCase().includes('OPERATIVO')) { 
                        color = 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ADMINISTRATIVO')) { 
                        // Borde resaltado para Administrativo igual que en la otra tabla
                        color = 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ESPECIAL')) { 
                        color = 'bg-orange-100 border-orange-500 text-orange-800 shadow-sm'; 
                    }

                    return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 125px;">${val}</span>` : '—';
                }
            },
            {
                title: "Fecha de Ingreso",
                field: "FECH_INGRE",
                hozAlign: "center",
                widthGrow: 3,
                formatter: cell => {
                    const val = cell.getValue();
                    if (val && val !== 'sin cambios') {
                        const f = formatearFechaHora(val);
                        return `<div class="flex items-center justify-center text-sm text-gray-700 whitespace-nowrap">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar text-blue-500'></i> <span>${f.fecha}</span></span>
                        </div>`;
                    }
                    return '—';
                }
            },
            {
                title: "Fecha de Actualización",
                field: "SIP_CREACION",
                hozAlign: "center",
                widthGrow: 3,
                formatter: cell => {
                    const val = cell.getValue();
                    if (val && val !== 'sin cambios') {
                        const f = formatearFechaHora(val);
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700 whitespace-nowrap">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar text-blue-500'></i> <span>${f.fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five text-orange-500'></i> <span>${f.hora}</span></span>
                        </div>`;
                    }
                    return '—';
                }
            },
            // {
            //     title: "Usuario", hozAlign: "left", widthGrow: 1.5, headerSort: false,
            //     formatter: cell => {
            //         const d = cell.getData();
            //         return d.generadoPor || d.actualizadoPor || '—';
            //     }
            // }
        ],
    });
    
    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    // reformatNums(tblEtapa1); 

    //  NUEVA FUNCIÓN DE FILTRADO LOCAL
    function aplicarFiltrosLocalesE1() {
        const texto = document.getElementById('buscarPersonalE1')?.value.toLowerCase().trim() || '';
        const radioVal = document.getElementById('filtroEstadoE1')?.value || 'null';

        let filtros = [];

        if (texto) {
            filtros.push([
                { field: "APEL_1", type: "like", value: texto },
                { field: "APEL_2", type: "like", value: texto },
                { field: "NOMB_1", type: "like", value: texto },
                { field: "NOMB_2", type: "like", value: texto },
                { field: "NRO_DOCU_IDEN", type: "like", value: texto },
                { field: "NOMBRE", type: "like", value: texto }
            ]);
        }

        // Filtramos localmente por el Radio Button
        if (radioVal === '0') {
            filtros.push({ field: "SIP_CAMBIO", type: "=", value: "Ok" });
        } else if (radioVal === '1') {
            filtros.push({ field: "SIP_CAMBIO", type: "=", value: "Falta" });
        }

        tblEtapa1.setFilter(filtros);
        tblEtapa1.setPage(1);
        // Las cards ya no se actualizan aquí para que queden estáticas al filtrar
    }

    function cargarDatosEtapa1() {
        const codSucursal = document.getElementById('filtroSucursalE1')?.value || '00';
        const codTipoPer = document.getElementById('filtroTipoE1')?.value || '00';
        const anio = (window.modoDJ === 'anual') ? (document.getElementById('filtroAnio')?.value || '') : '';

        // Ya no enviamos el filtro del radio button a la BD (mandamos null) para traer SIEMPRE todo
        axios.get(`${VITE_URL_APP}/api/reporte-personal-sin-migracion-v2`, { params: { codSucursal, codTipoPer, tipo: null, anio: anio } })
            .then(response => {
                if (!response.data.success) return;

                const datosLimpios = response.data.data.filter(d => {
                    const tipoPersonal = d.TIPO_PER ? d.TIPO_PER.toUpperCase() : '';
                    return !tipoPersonal.includes('ESPECIAL');
                });

                // 1. Calculamos y fijamos las cards con LA DATA TOTAL (sin importar el filtro local)
                document.getElementById('countTotalE1').textContent = datosLimpios.length;
                document.getElementById('countActualizadosE1').textContent = datosLimpios.filter(d => d.SIP_CAMBIO === 'Ok').length;
                document.getElementById('countSinActualizarE1').textContent = datosLimpios.filter(d => d.SIP_CAMBIO === 'Falta').length;

                // 2. Mandamos la data a la tabla
                tblEtapa1.setData(datosLimpios);

                // 3. Aplicamos filtros locales por si hay un radio button o texto ya seleccionado
                aplicarFiltrosLocalesE1();
            });
    }

    document.getElementById('filtroSucursalE1')?.addEventListener('change', cargarDatosEtapa1);
    document.getElementById('filtroTipoE1')?.addEventListener('change', cargarDatosEtapa1);
    document.getElementById('filtroEstadoE1')?.addEventListener('change', aplicarFiltrosLocalesE1);

    document.getElementById('page-size-etapa1')?.addEventListener('change', function () {
        tblEtapa1.setPageSize(parseInt(this.value));
    });

    // 🔥 EVENTOS DEL BUSCADOR: Filtrar y Resaltar texto
    document.getElementById('buscarPersonalE1')?.addEventListener('keyup', function () {
        const valor = this.value.toLowerCase().trim();
        tblEtapa1._ultimoFiltro = valor; // Guardamos estado para re-renderizado
        aplicarFiltrosLocalesE1();
        setTimeout(() => resaltarTexto(tblEtapa1, valor), 10);
    });

    // Mantiene el resaltado amarillo si cambias de página en Tabulator
    tblEtapa1.on("renderComplete", function () {
        if (this._ultimoFiltro) resaltarTexto(this, this._ultimoFiltro);

        // =========================================================
        // INYECCIÓN DE NUMERACIÓN ESTÁTICA
        // =========================================================
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
    // ============================================================
    // EXPORTACIÓN EXCEL Y PDF (PERSONALIZADO TIPO SISOLMAR)
    function getFiltrosTexto() {
        const selSucursal = document.getElementById('filtroSucursalE1');
        const selTipo = document.getElementById('filtroTipoE1');
        const txtSucursal = selSucursal.options[selSucursal.selectedIndex]?.text?.toUpperCase() || '';
        const txtTipo = selTipo.options[selTipo.selectedIndex]?.text?.toUpperCase() || '';

        return {
            sucursal: txtSucursal === 'TODAS' ? 'TODAS LAS SUCURSALES' : txtSucursal,
            tipo: txtTipo === 'TODOS' ? '' : txtTipo
        };
    }

    // EXPORTAR EXCEL PERSONALIZADO (Con ExcelJS - Incluyendo contadores)
    document.getElementById("btnExportExcelE1")?.addEventListener("click", async () => {
        let data = tblEtapa1.getData("active");
        if (!data.length) return Swal.fire('Sin datos', 'No hay datos para exportar', 'warning');

        // 🔥 FILTRO DE SEGURIDAD: Garantizamos que no pase ningún "ESPECIAL" al Excel
        data = data.filter(d => {
            const tipoPersonal = d.TIPO_PER ? d.TIPO_PER.toUpperCase() : '';
            return !tipoPersonal.includes('ESPECIAL');
        });

        // --- Calcular totales exactos basados estrictamente en la data a imprimir ---
        const totalRegistros = data.length;
        const totalActualizados = data.filter(d => d.SIP_CAMBIO === 'Ok').length;
        const totalSinActualizar = totalRegistros - totalActualizados;

        const filtros = getFiltrosTexto();
        const f = new Date();
        const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;
        const radioVal = document.getElementById('filtroEstadoE1')?.value || 'null';
        let baseTitle = "ETAPA N°1: REPORTE COMPLETO DE ACTUALIZACIÓN POR SIP";
        let estadoArchivo = "Todos";

        if (radioVal === '1') {
            baseTitle = "ETAPA N°1: REPORTE DE PENDIENTES DE ACTUALIZACIÓN POR SIP";
            estadoArchivo = "Sin_Actualizar";
        } else if (radioVal === '0') {
            baseTitle = "ETAPA N°1: REPORTE DE ACTUALIZADOS DEL SIP";
            estadoArchivo = "Actualizados";
        }
        
        const tituloReporte = `${baseTitle} ${filtros.tipo}`.trim();
        const tipoArchivo = filtros.tipo === '' ? 'Todos' : filtros.tipo.replace(/ /g, '_');

        Swal.fire({ title: 'Generando Excel...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("1° Etapa");

            // 1. INCORPORAR LOGO
            if (window.logoUrl) {
                try {
                    const response = await fetch(window.logoUrl);
                    const blob = await response.blob();
                    const base64 = await new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.readAsDataURL(blob);
                    });
                    const imageId = workbook.addImage({ base64: base64, extension: 'png' });
                    worksheet.addImage(imageId, { tl: { col: 0, row: 0 }, ext: { width: 170, height: 50 } });
                } catch (e) { console.warn("No se pudo cargar logo", e); }
            }

            // 2. TÍTULOS
            worksheet.mergeCells('A1:G1');
            const title1 = worksheet.getCell('A1');
            title1.value = "SISTEMA INTEGRADO SOLMAR – SISOL WEB";
            title1.font = { bold: true, color: { argb: 'FF990000' }, size: 11 };
            title1.alignment = { horizontal: 'center', vertical: 'middle' };

            worksheet.mergeCells('A2:G2');
            const title2 = worksheet.getCell('A2');
            title2.value = tituloReporte;
            title2.font = { bold: true, size: 14 };
            title2.alignment = { horizontal: 'center', vertical: 'middle' };

            worksheet.mergeCells('A3:G3');
            const title3 = worksheet.getCell('A3');
            title3.value = filtros.sucursal === 'TODAS LAS SUCURSALES' ? '' : `Sol ${capitalizeWords(filtros.sucursal)}`;
            title3.font = { bold: true, size: 12 };
            title3.alignment = { horizontal: 'center', vertical: 'middle' };

            // 3. ESTADÍSTICAS HORIZONTALES (Encima de la tabla)
            const statsHeaders = ['A6', 'B6', 'C6', 'D6'];
            const statsValues = ['A7', 'B7', 'C7', 'D7'];

            worksheet.getCell('A6').value = "Generado";
            worksheet.getCell('B6').value = "Total";
            worksheet.getCell('C6').value = "Actualizados";
            worksheet.getCell('D6').value = "Sin Actualizar";

            worksheet.getCell('A7').value = fechaStr;
            worksheet.getCell('B7').value = totalRegistros;
            worksheet.getCell('C7').value = totalActualizados;
            worksheet.getCell('D7').value = totalSinActualizar;

            // Estilos para las cabeceras de estadísticas (Fondo gris claro)
            statsHeaders.forEach(cell => {
                const c = worksheet.getCell(cell);
                c.font = { bold: true, color: { argb: 'FF000000' } };
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE5E7EB' } };
                c.alignment = { horizontal: 'center', vertical: 'middle' };
                c.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            // Estilos para los valores numéricos/fecha
            statsValues.forEach(cell => {
                const c = worksheet.getCell(cell);
                c.font = { bold: true };
                c.alignment = { horizontal: 'center', vertical: 'middle' };
                c.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            // 4. CABECERAS TABLA (Fila 10)
            const headers = ["N°", "Estado", "Nombres", "DNI", "Sucursal", "Tipo", "Fecha de Ingreso", "Fecha de Actualización"];
            const headerRow = worksheet.getRow(10);
            headerRow.values = headers;
            headerRow.eachCell((cell) => {
                cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF4B5563' } };
                cell.alignment = { horizontal: 'center', vertical: 'middle' };
                cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            // 5. DATOS
            data.forEach((d, index) => { // 🔥 Agregamos 'index' aquí
                const row = worksheet.addRow([
                    index + 1, // 🔥 Reemplazamos d.NRO por index + 1
                    d.SIP_CAMBIO === 'Ok' ? 'ACTUALIZADO' : 'SIN ACTUALIZAR',
                    d.NOMBRE, d.NRO_DOCU_IDEN, d.SUCURSAL, d.TIPO_PER,
                    d.FECH_INGRE !== 'sin cambios' ? d.FECH_INGRE.split(' ')[0] : '—',
                    (d.SIP_CREACION && d.SIP_CREACION !== 'sin cambios') ? d.SIP_CREACION : '—'
                ]);
                row.eachCell((cell, colNumber) => {
                    cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
                    cell.alignment = { vertical: 'middle', horizontal: (colNumber === 3 ? 'left' : 'center') };
                });
                const estadoCell = row.getCell(2);
                estadoCell.font = { color: { argb: d.SIP_CAMBIO === 'Ok' ? 'FF15803D' : 'FFB91C1C' }, bold: true };
            });

            worksheet.columns = [{ width: 8 }, { width: 18 }, { width: 45 }, { width: 14 }, { width: 15 }, { width: 22 }, { width: 18 }, { width: 22 }];
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
            link.download = `Etapa1_Actualizacion_por_SIP_${estadoArchivo}_${tipoArchivo}_${filtros.sucursal.replace(/ /g, '_')}_${fStr}.xlsx`;
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            Swal.close();
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Problema al generar el Excel.', 'error');
        }
    });

    // EXPORTAR PDF PERSONALIZADO (Con jsPDF y autotable manual)
    document.getElementById("btnExportPdfE1")?.addEventListener("click", async () => {
        let data = tblEtapa1.getData("active");
        if (!data.length) return Swal.fire('Sin datos', 'No hay datos para exportar', 'warning');

        // 🔥 FILTRO DE SEGURIDAD: Garantizamos que no pase ningún "ESPECIAL" al PDF
        data = data.filter(d => {
            const tipoPersonal = d.TIPO_PER ? d.TIPO_PER.toUpperCase() : '';
            return !tipoPersonal.includes('ESPECIAL');
        });

        // --- 1. Calcular totales para las cards del PDF ---
        const totalRegistros = data.length;
        const totalActualizados = data.filter(d => d.SIP_CAMBIO === 'Ok').length;
        const totalSinActualizar = totalRegistros - totalActualizados;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape'); // Horizontal para que quepa bien
        const totalWidth = doc.internal.pageSize.getWidth();

        const filtros = getFiltrosTexto();
        const f = new Date();
        const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;
        const radioVal = document.getElementById('filtroEstadoE1')?.value || 'null';
        let baseTitle = "ETAPA N°1: REPORTE COMPLETO DE ACTUALIZACIÓN POR SIP";
        let estadoArchivo = "Todos";

        if (radioVal === '1') {
            baseTitle = "ETAPA N°1: REPORTE DE PENDIENTES DE ACTUALIZACIÓN POR SIP";
            estadoArchivo = "Sin_Actualizar";
        } else if (radioVal === '0') {
            baseTitle = "ETAPA N°1: REPORTE DE ACTUALIZADOS DEL SIP";
            estadoArchivo = "Actualizados";
        }
        
        const tituloReporte = `${baseTitle} ${filtros.tipo}`.trim();
        const tipoArchivo = filtros.tipo === '' ? 'Todos' : filtros.tipo.replace(/ /g, '_');

        // Convertir logo de Blade a Base64
        let logoBase64 = null;
        try {
            if (window.logoUrl) {
                const response = await fetch(window.logoUrl);
                const blob = await response.blob();
                logoBase64 = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.readAsDataURL(blob);
                });
            }
        } catch (e) {
            console.warn("No se pudo cargar el logo para el PDF", e);
        }

        doc.autoTable({
            startY: 60, // 2. Bajamos el inicio de la tabla a 60 para dar espacio a las cards
            theme: 'grid',
            headStyles: { fillColor: [243, 244, 246], textColor: [55, 65, 81], fontStyle: 'bold', halign: 'center' },
            bodyStyles: { fontSize: 8 },
            columnStyles: { 0: { halign: 'center' } }, // Centrar los números de la nueva columna
            // 3. Agregamos "N°" a la cabecera
            head: [["N°", "Estado", "Nombres", "DNI", "Sucursal", "Tipo", "Fecha Ingreso", "Fecha Actualización"]],
            // 4. Mapeamos el index para enumerar
            body: data.map((d, index) => [ // 🔥 Agregamos 'index' aquí
                index + 1, // 🔥 Reemplazamos d.NRO por index + 1
                d.SIP_CAMBIO === 'Ok' ? 'ACTUALIZADO' : 'SIN ACTUALIZAR',
                d.NOMBRE,
                d.NRO_DOCU_IDEN,
                d.SUCURSAL,
                d.TIPO_PER,
                d.FECH_INGRE !== 'sin cambios' ? d.FECH_INGRE.split(' ')[0] : '—',
                (d.SIP_CREACION && d.SIP_CREACION !== 'sin cambios') ? d.SIP_CREACION : '—'
            ]),
            didDrawPage: function (dataPage) {
                if (dataPage.pageNumber !== 1) {
                    return;
                }

                // Dibujar el Logo
                if (logoBase64) {
                    doc.addImage(logoBase64, 'PNG', 14, 10, 40, 12);
                }

                // Títulos Centrales
                doc.setFontSize(10);
                doc.setTextColor(180, 0, 0); // Rojo tipo corporativo
                doc.setFont("helvetica", "bold");
                doc.text("SISTEMA INTEGRADO SOLMAR – SISOL WEB", totalWidth / 2, 14, { align: "center" });

                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0); // Negro
                doc.text(tituloReporte, totalWidth / 2, 20, { align: "center" });

                const subtitulo = filtros.sucursal === 'TODAS LAS SUCURSALES' ? '' : `Sol ${capitalizeWords(filtros.sucursal)}`;
                if (subtitulo !== '') {
                    doc.text(subtitulo, totalWidth / 2, 26, { align: "center" });
                }

                // Texto Derecha (Generado)
                doc.setFontSize(8);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 100, 100); // Gris
                doc.text(`Generado: ${fechaStr}`, totalWidth - 14, 14, { align: "right" });

                // --- 5. DIBUJAR CARDS ---
                const cardW = 45;
                const cardH = 18;
                const gap = 10;
                const totalCardsW = (cardW * 3) + (gap * 2);
                const startX = (totalWidth - totalCardsW) / 2;
                const cardY = 32; // Posición Y de las cards debajo del título

                const cards = [
                    { title: "Total", value: totalRegistros, color: [75, 85, 99] }, // Gris oscuro
                    { title: "Sin actualizar", value: totalSinActualizar, color: [185, 28, 28] }, // Rojo
                    { title: "Actualizados", value: totalActualizados, color: [4, 120, 87] } // Verde
                ];

                cards.forEach((card, i) => {
                    const x = startX + (i * (cardW + gap));

                    // Fondo de la card (Bordes redondeados)
                    doc.setFillColor(...card.color);
                    doc.roundedRect(x, cardY, cardW, cardH, 2, 2, 'F');

                    // Valor numérico (Grande)
                    doc.setTextColor(255, 255, 255); // Blanco
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(16);
                    doc.text(String(card.value), x + (cardW / 2), cardY + 10, { align: "center" });

                    // Etiqueta (Pequeña)
                    doc.setFontSize(8);
                    doc.setFont("helvetica", "normal");
                    doc.text(card.title, x + (cardW / 2), cardY + 15, { align: "center" });
                });
            }
        });

        const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
        doc.save(`Etapa1_Actualizacion_por_SIP_${estadoArchivo}_${tipoArchivo}_${filtros.sucursal.replace(/ /g, '_')}_${fStr}.pdf`);
    });

    cargarDatosEtapa1();

    // ============================================================
    // FIN 1° ETAPA / LÓGICA LEGACY (5° ETAPA) A CONTINUACIÓN
    // ============================================================

    // ============================================================
    // 2° ETAPA: TABLA DE COMPARACIÓN (VERIFICADOS)
    // ============================================================
    const tblPersonasVerificado = new Tabulator("#tblPersonasVerificado", {
        height: "550px",
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: true,
        paginationSize: 20,
        rowFormatter: function (row) {
            const d = row.getData();
            const el = row.getElement();
            const verificado = String(d.migrado || '').toUpperCase().trim() === 'SI';
            el.classList.toggle('rep-cesado', !verificado);
            el.classList.toggle('rep-alerta', Boolean(d.es_solicitud_nueva) && !verificado);
            el.style.removeProperty('background-color');
        },
        locale: "es",
        langs: { "es": { "pagination": { "first": "Primero", "prev": "Anterior", "next": "Siguiente", "last": "Último" } } },
        columns: [
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 60, 
                headerSort: false, 
                responsive: false 
            },
            {
                title: "Verificado", field: "migrado", hozAlign: "center", widthGrow: 1.2,
                formatter: cell => {
                    const valor = (cell.getValue() ?? '').toUpperCase().trim();
                    const esVerificado = valor === 'SI';
                    const esSolicitudSip = Boolean(cell.getData().es_solicitud_nueva);

                    // Si viene otra cosa que no sea SI o NO, lo mostramos tal cual, sino usamos SI/NO
                    const texto = (valor === 'SI' || valor === 'NO') ? valor : (valor || '—');

                    const color = esVerificado
                        ? 'border-success bg-success text-white'
                        : esSolicitudSip
                            ? 'border-rose-300 bg-rose-50 text-rose-700'
                            : 'border-yellow-300 bg-yellow-50 text-yellow-800';

                    return `<span class="inline-flex items-center rounded-full border ${color} px-4 py-0.5 text-[11px] font-bold tracking-wider whitespace-nowrap">${texto}</span>`;
                }
            },
            {
                title: "Apellidos", field: "apellidos", hozAlign: "left", widthGrow: 2,
                formatter: cell => { const d = cell.getData(); return `${d.apellido1 ?? d.APEL_1 ?? ''} ${d.apellido2 ?? d.APEL_2 ?? ''}`.trim(); }
            },
            {
                title: "Nombres", field: "nombres", hozAlign: "left", widthGrow: 1.5,
                formatter: cell => { const d = cell.getData(); return `${d.nombres ?? d.NOMB_1 ?? ''} ${d.NOMB_2 ?? ''}`.trim(); }
            },
            { title: "DNI", field: "dni", hozAlign: "center", width: 110 },
            { title: "Sucursal", field: "sucursal", hozAlign: "center", widthGrow: 1 },
            {
                title: "Tipo Act.", field: "tipo_actualizacion", hozAlign: "center", widthGrow: 1.5,
                formatter: cell => {
                    const d = cell.getData();
                    // Busca las posibles llaves que retorne tu SP
                    const valor = d.tipo_actualizacion || d.TIPO_ACTUALIZACION || d.tipoActualizacion || 'ANUAL';
                    let color = valor.toUpperCase().includes('DEMANDA') ? 'bg-orange-100 text-orange-800 border-orange-300' : 'bg-blue-100 text-blue-800 border-blue-300';
                    return `<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase ${color}">${valor}</span>`;
                }
            },
            { 
                title: "Tipo", field: "tipoPer", hozAlign: "center", widthGrow: 2,
                formatter: cell => {
                    const val = cell.getValue() ?? '';
                    let color = 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm';
                    
                    if (val.toUpperCase().includes('OPERATIVO')) { 
                        color = 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ADMINISTRATIVO')) { 
                        color = 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ESPECIAL')) { 
                        color = 'bg-orange-100 border-orange-500 text-orange-800 shadow-sm'; 
                    }

                    return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 125px;">${val}</span>` : '—';
                }
            },
            {
                title: "Fecha Verificado",
                field: "cambio",
                hozAlign: "center",
                widthGrow: 2,
                formatter: cell => {
                    const val = cell.getValue();
                    if (val && val !== 'sin cambios') {
                        const f = formatearFechaHora(val);
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700 whitespace-nowrap">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar text-blue-500'></i> <span>${f.fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five text-orange-500'></i> <span>${f.hora}</span></span>
                        </div>`;
                    }
                    return '—';
                }
            },
            // {
            //     title: "Usuario", hozAlign: "left", widthGrow: 1.5, headerSort: false,
            //     formatter: cell => {
            //         const d = cell.getData();
            //         return d.generadoPor || d.verificadoPor || '—';
            //     }
            // },
            {
                title: "Acciones", hozAlign: "center", headerSort: false, widthGrow: 1,
                formatter: cell => {
                    const d = cell.getData();
                    const esSolicitudSip = Boolean(d.es_solicitud_nueva);
                    // Verificamos si el estado es 'SI' (Verificado)
                    const esVerificado = d.migrado === 'SI';

                    const disabled = esVerificado ? 'disabled' : '';
                    const opacityClass = esVerificado ? 'opacity-50 cursor-not-allowed' : 'hover:bg-success hover:text-white';

                    // Si está verificado, le quitamos el atributo del modal para que no se abra accidentalmente
                    const modalAttr = esVerificado || esSolicitudSip ? '' : 'data-hs-overlay="#modalDjGestion"';

                    const etiqueta = esSolicitudSip ? 'Revisar' : 'DJ';
                    const claseColor = esSolicitudSip ? 'bg-blue-100 text-blue-700 hover:bg-blue-600 hover:text-white' : 'bg-success/25 text-success';
                    return `<button ${disabled} type="button" class="btn rounded-full form-btn-verificado ${claseColor} ${opacityClass}" ${modalAttr}>${etiqueta}</button>`;
                },
                cellClick: (e, cell) => {
                    const btn = e.target.closest('.form-btn-verificado');
                    if (!btn) return;

                    // Si el botón está deshabilitado, evitamos la ejecución del JS
                    if (btn.hasAttribute('disabled')) return;

                    const rowData = cell.getRow().getData();
                    if (rowData.es_solicitud_nueva && rowData.solicitud_id) {
                        abrirRevisionSolicitudSip(rowData.solicitud_id);
                        return;
                    }
                    const codiPers = rowData.codPersonal || rowData.id;
                    abrirFormularioDJ(codiPers, 'migracion');
                }
            }
        ],
    });
    
    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    // reformatNums(tblPersonasVerificado);

    tblPersonasVerificado.on("renderComplete", function () {
        if (this._ultimoFiltro) resaltarTexto(this, this._ultimoFiltro);

        // =========================================================
        // INYECCIÓN DE NUMERACIÓN ESTÁTICA
        // =========================================================
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

    // ============================================================
    // EXPORTAR EXCEL PERSONALIZADO ETAPA 2 (Diseño Mejorado)
    // ============================================================
    document.getElementById("btnExportExcelE2")?.addEventListener("click", async () => {
        let data = tblPersonasVerificado.getData("active");
        if (!data.length) return Swal.fire('Sin datos', 'No hay datos para exportar', 'warning');

        // Calcular estadísticas con la corrección del 'SI'
        const totalRegistros = data.length;
        const totalVerificados = data.filter(d => {
            const estado = d.VERIFICADO_CAMBIO ? d.VERIFICADO_CAMBIO.toUpperCase().trim() : (d.migrado || '');
            return estado === 'SI' || estado === 'VERIFICADO';
        }).length;
        const totalSinVerificar = totalRegistros - totalVerificados;

        // Textos para filtros
        const selSucursal = document.getElementById('filtroSucursalE2');
        const txtSucursal = selSucursal.options[selSucursal.selectedIndex]?.text?.toUpperCase() || 'TODAS LAS SUCURSALES';
        
        const selTipo = document.getElementById('filtroTipoPerE2');
        const txtTipo = selTipo.options[selTipo.selectedIndex]?.text?.toUpperCase() || 'TODOS';
        const tipoFiltroTexto = txtTipo === 'TODOS' ? '' : txtTipo;

        const f = new Date();
        const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;

        // 🔥 LÓGICA DE TÍTULOS DINÁMICOS
        const radioVal = document.getElementById('filtroEstadoE2')?.value || 'null';
        let baseTitle = "ETAPA N°2: REPORTE COMPLETO DE VERIFICACIÓN";
        let estadoArchivo = "Todos";

        if (radioVal === '1') {
            baseTitle = "ETAPA N°2: REPORTE DE PENDIENTES POR VERIFICAR";
            estadoArchivo = "Sin_Verificar";
        } else if (radioVal === '0') {
            baseTitle = "ETAPA N°2: REPORTE DE VERIFICADOS";
            estadoArchivo = "Verificados";
        }
        
        const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
        const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

        Swal.fire({ title: 'Generando Excel...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const workbook = new ExcelJS.Workbook();
            // 🔥 DISEÑO: Ocultar líneas de cuadrícula para un look más limpio
            const worksheet = workbook.addWorksheet("Etapa 2 - Verificados", {
                views: [{ showGridLines: false }]
            });

            // 1. INCORPORAR LOGO
            if (window.logoUrl) {
                try {
                    const response = await fetch(window.logoUrl);
                    const blob = await response.blob();
                    const base64 = await new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.readAsDataURL(blob);
                    });
                    const imageId = workbook.addImage({ base64: base64, extension: 'png' });
                    worksheet.addImage(imageId, { tl: { col: 0, row: 0 }, ext: { width: 170, height: 50 } });
                } catch (e) { console.warn("No se pudo cargar logo", e); }
            }

            // 2. TÍTULOS (Aplicando fuentes Arial del nuevo diseño)
            worksheet.mergeCells('A1:G1');
            const title1 = worksheet.getCell('A1');
            title1.value = "SOL SECURITY";
            title1.font = { name: 'Arial', size: 16, bold: true, color: { argb: 'FF990000' } };
            title1.alignment = { horizontal: 'center', vertical: 'middle' };

            worksheet.mergeCells('A2:G2');
            const title2 = worksheet.getCell('A2');
            title2.value = "SISTEMA INTEGRADO SOLMAR - SISOL WEB";
            title2.font = { name: 'Arial', size: 12, bold: true };
            title2.alignment = { horizontal: 'center', vertical: 'middle' };

            worksheet.mergeCells('A3:G3');
            const title3 = worksheet.getCell('A3');
            const subtituloSucursal = txtSucursal === 'TODAS LAS SUCURSALES' ? '' : ` | Sol ${capitalizeWords(txtSucursal)}`;
            title3.value = `${tituloReporte}${subtituloSucursal}`;
            title3.font = { name: 'Arial', size: 11, bold: true };
            title3.alignment = { horizontal: 'center', vertical: 'middle' };

            // 3. ESTADÍSTICAS HORIZONTALES (Mantenemos tu formato de cards, pero con Arial)
            const statsHeaders = ['A6', 'B6', 'C6', 'D6'];
            const statsValues = ['A7', 'B7', 'C7', 'D7'];

            worksheet.getCell('A6').value = "Generado";
            worksheet.getCell('B6').value = "Total";
            worksheet.getCell('C6').value = "Sin Verificar";
            worksheet.getCell('D6').value = "Verificados";

            worksheet.getCell('A7').value = fechaStr;
            worksheet.getCell('B7').value = totalRegistros;
            worksheet.getCell('C7').value = totalSinVerificar;
            worksheet.getCell('D7').value = totalVerificados;

            statsHeaders.forEach(cell => {
                const c = worksheet.getCell(cell);
                c.font = { name: 'Arial', bold: true, color: { argb: 'FF000000' } };
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE5E7EB' } };
                c.alignment = { horizontal: 'center', vertical: 'middle' };
                c.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            statsValues.forEach(cell => {
                const c = worksheet.getCell(cell);
                c.font = { name: 'Arial', bold: true };
                c.alignment = { horizontal: 'center', vertical: 'middle' };
                c.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            // 4. CABECERAS TABLA (Fila 10) - 🔥 DISEÑO AZUL OSCURO
            const headers = ["N°", "Verificado", "Nombres", "DNI", "Sucursal", "Tipo", "Fecha Verificado"];
            const headerRow = worksheet.getRow(10);
            headerRow.values = headers;
            headerRow.height = 25; // Altura de la cabecera

            headerRow.eachCell((cell) => {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E78' } }; // Azul oscuro
                cell.font = { color: { argb: 'FFFFFFFF' }, bold: true, name: 'Arial', size: 10 };
                cell.alignment = { horizontal: 'center', vertical: 'middle' };
                cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            // 5. DATOS DE LA TABLA - 🔥 BORDES GRISES SUTILES Y COLORES CONDICIONALES
            data.forEach((d, index) => {
                const estado = d.VERIFICADO_CAMBIO ? d.VERIFICADO_CAMBIO.toUpperCase().trim() : (d.migrado || '');
                const verificadoTxt = (estado === 'SI' || estado === 'VERIFICADO') ? 'SI' : 'NO';
                const nombreCompleto = d.NOMBRE || d.PERSONAL || `${d.nombres ?? d.NOMB_1 ?? ''} ${d.apellido1 ?? d.APEL_1 ?? ''} ${d.apellido2 ?? d.APEL_2 ?? ''}`.trim();

                let fechaVerif = '—';
                if (d.VERIFICADO_FECHA && d.VERIFICADO_FECHA !== 'sin cambios') {
                    fechaVerif = d.VERIFICADO_FECHA.replace('T', ' ').substring(0, 16);
                } else if (d.cambio && d.cambio !== 'sin cambios') {
                    fechaVerif = d.cambio.replace('T', ' ').substring(0, 16);
                }

                const row = worksheet.addRow([
                    d.NRO || index + 1,
                    verificadoTxt,
                    nombreCompleto,
                    d.dni ?? d.NRO_DOCU_IDEN ?? '',
                    d.sucursal ?? d.SUCURSAL ?? '',
                    d.tipoPer ?? d.TIPO_PER ?? '',
                    fechaVerif
                ]);

                row.eachCell((cell, colNumber) => {
                    cell.font = { name: 'Arial', size: 9 };
                    cell.border = {
                        top: { style: 'thin', color: { argb: 'FFBFBFBF' } }, // Bordes grises sutiles
                        left: { style: 'thin', color: { argb: 'FFBFBFBF' } },
                        bottom: { style: 'thin', color: { argb: 'FFBFBFBF' } },
                        right: { style: 'thin', color: { argb: 'FFBFBFBF' } }
                    };

                    // Alineación
                    if (colNumber === 3) {
                        cell.alignment = { vertical: 'middle', horizontal: 'left' };
                    } else {
                        cell.alignment = { vertical: 'middle', horizontal: 'center' };
                    }

                    // Condicional de color para "Verificado"
                    if (colNumber === 2) {
                        if (verificadoTxt === 'NO') {
                            cell.font = { color: { argb: 'FFFF0000' }, bold: true, size: 9, name: 'Arial' }; // Rojo
                        } else {
                            cell.font = { color: { argb: 'FF00B050' }, bold: true, size: 9, name: 'Arial' }; // Verde
                        }
                    }
                });
            });

            // 6. ANCHOS DE COLUMNA EXACTOS DEL DISEÑO
            worksheet.columns = [
                { width: 5 },  // N°
                { width: 12 }, // Verificado
                { width: 45 }, // Nombres
                { width: 15 }, // DNI
                { width: 15 }, // Sucursal
                { width: 20 }, // Tipo
                { width: 20 }  // Fecha Verificado
            ];

            // 7. DESCARGAR ARCHIVO NATIVAMENTE
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
            link.download = `Etapa2_Verificacion_${estadoArchivo}_${tipoArchivo}_${txtSucursal.replace(/ /g, '_')}_${fStr}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            Swal.close();

        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Problema al generar el Excel.', 'error');
        }
    });

    function cargarDatosEtapa2() {
        const anio = (window.modoDJ === 'anual') ? (document.getElementById('filtroAnio')?.value || '') : '';

        return axios.get(`${VITE_URL_APP}/api/reporte-personal-sin-migracion`, { params: { anio: anio } })
            .then(response => {
                if (!response.data.success) return;

                // 🔥 FILTRO DE SEGURIDAD: Excluimos al personal "ESPECIAL" de la data base
                const datosLimpios = response.data.data.filter(d => {
                    const tipoPersonal = d.tipoPer ? d.tipoPer.toUpperCase() : (d.TIPO_PER ? d.TIPO_PER.toUpperCase() : '');
                    return !tipoPersonal.includes('ESPECIAL');
                });

                // 1. Calculamos y fijamos las cards con LA DATA TOTAL LIMPIA
                const total = datosLimpios.length;
                const verificados = datosLimpios.filter(d => d.migrado === 'SI').length;
                const sinVerificar = total - verificados;

                document.getElementById('contadorTotalE2').textContent = total;
                document.getElementById('contadorFiltradoE2').textContent = verificados;
                document.getElementById('contadorSinVerificarE2').textContent = sinVerificar;

                // 2. Mandamos la data limpia a la tabla
                return tblPersonasVerificado.setData(datosLimpios).then(() => aplicarFiltrosE2());
            });
    }

    function aplicarFiltrosE2() {
        const codSucursal = document.getElementById('filtroSucursalE2')?.value || '00';
        const codTipoPer = document.getElementById('filtroTipoPerE2')?.value || '00';
        const texto = document.getElementById('buscarPersonalE2')?.value.toLowerCase().trim() || '';
        const radioVal = document.getElementById('filtroEstadoE2')?.value || 'null';

        let tipoTxt = '';
        if (codTipoPer !== '00') {
            if (codTipoPer === '01') tipoTxt = 'OPERATIVO 4';
            if (codTipoPer === '03') tipoTxt = 'OPERATIVO 5';
            if (codTipoPer === '02') tipoTxt = 'ADMINISTRATIVO 4';
            if (codTipoPer === '05') tipoTxt = 'ADMINISTRATIVO 5';
        }

        tblPersonasVerificado.clearFilter();

        // 🔥 FILTRO UNIFICADO Y FLEXIBLE
        tblPersonasVerificado.setFilter(function (data) {
            let matchSucursal = true;
            let matchTipo = true;
            let matchTexto = true;
            let matchRadio = true;

            // Filtro Sucursal
            if (codSucursal !== '00') matchSucursal = (data.codSucursal === codSucursal);
            
            // Filtro Tipo
            if (tipoTxt !== '') {
                const valTipo = String(data.tipoPer || data.TIPO_PER || '').toUpperCase();
                matchTipo = valTipo.includes(tipoTxt);
            }

            // Filtro Búsqueda (simplificado para buscar en todo el string a la vez)
            if (texto) {
                const searchStr = `${data.nombres || data.NOMB_1 || ''} ${data.apellido1 || data.APEL_1 || ''} ${data.apellido2 || data.APEL_2 || ''} ${data.dni || data.NRO_DOCU_IDEN || ''}`.toLowerCase();
                matchTexto = searchStr.includes(texto);
            }

            // Filtro Verificado
            if (radioVal !== 'null') {
                const estado = String(data.migrado || '').toUpperCase().trim();
                const esVerificado = (estado === 'SI');
                if (radioVal === '0') matchRadio = esVerificado;
                if (radioVal === '1') matchRadio = !esVerificado;
            }

            // 🔥 LA CLAVE ESTÁ AQUÍ: Usamos .includes() para atrapar variaciones como "A Demanda"
            const valAct = String(data.tipo_actualizacion || data.TIPO_ACTUALIZACION || data.tipoActualizacion || '').toLowerCase();
            const esDemanda = valAct.includes('demanda'); 
            
            const matchModo = (window.modoDJ === 'demanda') ? esDemanda : !esDemanda;

            return matchSucursal && matchTipo && matchTexto && matchRadio && matchModo;
        });

        tblPersonasVerificado.setPage(1);

        // =========================================================================
        // 🔥 LÓGICA DE INDICADORES (Sincronizada con el nuevo includes)
        // =========================================================================
        const todaLaData = tblPersonasVerificado.getData();

        const dataParaTarjetas = todaLaData.filter(d => {
            const cumpleSucursal = (codSucursal === '00') || (d.codSucursal === codSucursal);
            const valTipo = String(d.tipoPer || d.TIPO_PER || '').toUpperCase();
            const cumpleTipo = (codTipoPer === '00') || (tipoTxt !== '' && valTipo.includes(tipoTxt));
            
            const valAct = String(d.tipo_actualizacion || d.TIPO_ACTUALIZACION || d.tipoActualizacion || '').toLowerCase();
            const esDemanda = valAct.includes('demanda');
            const cumpleModo = (window.modoDJ === 'demanda') ? esDemanda : !esDemanda;

            return cumpleSucursal && cumpleTipo && cumpleModo;
        });

        const total = dataParaTarjetas.length;
        const verificados = dataParaTarjetas.filter(d => String(d.migrado).toUpperCase().trim() === 'SI').length;
        const sinVerificar = total - verificados;

        document.getElementById('contadorTotalE2').textContent = total;
        document.getElementById('contadorFiltradoE2').textContent = verificados;
        const elSinVerificar = document.getElementById('contadorSinVerificarE2');
        if (elSinVerificar) elSinVerificar.textContent = sinVerificar;
    }

    document.getElementById('filtroSucursalE2')?.addEventListener('change', aplicarFiltrosE2);
    document.getElementById('filtroTipoPerE2')?.addEventListener('change', aplicarFiltrosE2);
    document.getElementById('buscarPersonalE2')?.addEventListener('keyup', function () {
        const valor = this.value.toLowerCase().trim();
        tblPersonasVerificado._ultimoFiltro = valor;
        aplicarFiltrosE2();
        setTimeout(() => resaltarTexto(tblPersonasVerificado, valor), 10);
    });
    // Escuchando los radio buttons
    document.getElementById('filtroEstadoE2')?.addEventListener('change', aplicarFiltrosE2);

    document.getElementById('page-size-verificado')?.addEventListener('change', function () {
        tblPersonasVerificado.setPageSize(parseInt(this.value));
    });

    cargarDatosEtapa2();

    // Las notificaciones SIP enlazan directamente con esta revisión en Actualizar DJ.
    const solicitudSipUrl = new URLSearchParams(window.location.search).get('solicitud_id');
    if (solicitudSipUrl) {
        setTimeout(async () => {
            btnModoDemanda?.click();

            // El enlace puede venir con filtros guardados o valores por defecto
            // (por ejemplo, Operativo 5°) que oculten a quien hizo la solicitud.
            const sucursal = document.getElementById('filtroSucursalE2');
            const tipoPersonal = document.getElementById('filtroTipoPerE2');
            const estado = document.getElementById('filtroEstadoE2');
            const busqueda = document.getElementById('buscarPersonalE2');
            if (sucursal && [...sucursal.options].some(option => option.value === '00')) sucursal.value = '00';
            if (tipoPersonal && [...tipoPersonal.options].some(option => option.value === '00')) tipoPersonal.value = '00';
            if (estado) estado.value = 'null';
            if (busqueda) busqueda.value = '';

            try {
                await cargarDatosEtapa2();
                aplicarFiltrosE2();
            } catch (error) {
                console.error('No se pudo actualizar la lista de solicitudes SIP:', error);
            }
            abrirRevisionSolicitudSip(solicitudSipUrl);
        }, 150);
    }

    const modalSolicitudSip = document.getElementById('modalSolicitudSip');
    let solicitudSipActual = null;
    let solicitudSipRenderSeq = 0;
    const escaparHtml = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[caracter]));
    const etiquetasDatosSolicitud = {
        parentesco: 'Parentesco', nombres: 'Nombres', nombre: 'Nombres', ocupacion: 'Ocupación',
        fech_naci: 'Fecha de nacimiento', FECH_NACI: 'Fecha de nacimiento',
        TIPO_RELA: 'Parentesco', OCUPACION: 'Ocupación',
        NOMB_1: 'Primer nombre', NOMB_2: 'Segundo nombre', APEL_1: 'Apellido paterno', APEL_2: 'Apellido materno',
        CODI_DERE_HABI: null
    };
    const valorSolicitudLegible = (valor, profundidad = 0) => {
        if (valor === null || valor === undefined || valor === '') return '<span class="text-slate-400">Sin dato</span>';
        if (typeof valor === 'string') {
            const limpio = valor.trim();
            if ((limpio.startsWith('{') && limpio.endsWith('}')) || (limpio.startsWith('[') && limpio.endsWith(']'))) {
                try { return valorSolicitudLegible(JSON.parse(limpio), profundidad); } catch (_) {}
            }
            return escaparHtml(valor);
        }
        if (typeof valor !== 'object') return escaparHtml(valor);
        if (profundidad > 3) return '<span class="text-slate-500">Información registrada</span>';

        const elementos = Array.isArray(valor) ? valor : [valor];
        if (!elementos.length) return '<span class="text-slate-400">Sin registros</span>';
        return `<div class="space-y-2">${elementos.map(elemento => {
            if (!elemento || typeof elemento !== 'object') {
                return `<div class="rounded-md bg-slate-50 px-3 py-2">${valorSolicitudLegible(elemento, profundidad + 1)}</div>`;
            }
            const nombreCompleto = elemento.nombres || elemento.Nombres || [elemento.NOMB_1, elemento.NOMB_2, elemento.APEL_1, elemento.APEL_2].filter(Boolean).join(' ');
            const parentesco = elemento.parentesco || elemento.TIPO_RELA;
            const ocupacion = elemento.ocupacion || elemento.OCUPACION;
            const fechaNacimiento = elemento.fech_naci || elemento.FECH_NACI;
            if (nombreCompleto || parentesco || ocupacion || fechaNacimiento) {
                const datosFamiliares = [
                    ['Parentesco', parentesco], ['Nombres', nombreCompleto],
                    ['Ocupación', ocupacion], ['Fecha de nacimiento', fechaNacimiento]
                ].filter(([, dato]) => dato !== null && dato !== undefined && dato !== '');
                return `<dl class="grid grid-cols-1 gap-x-4 gap-y-2 rounded-md bg-slate-50 p-3 sm:grid-cols-2">${datosFamiliares.map(([etiqueta, dato]) => `<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">${etiqueta}</dt><dd class="mt-1 text-sm text-slate-800">${valorSolicitudLegible(dato, profundidad + 1)}</dd></div>`).join('')}</dl>`;
            }
            const campos = Object.entries(elemento).filter(([clave, dato]) =>
                etiquetasDatosSolicitud[clave] !== null && dato !== null && dato !== undefined && dato !== ''
            );
            if (!campos.length) return '';
            return `<dl class="grid grid-cols-1 gap-x-4 gap-y-2 rounded-md bg-slate-50 p-3 sm:grid-cols-2">${campos.map(([clave, dato]) => {
                const etiqueta = etiquetasDatosSolicitud[clave] || clave.replace(/_/g, ' ').toLowerCase().replace(/(^|\s)\S/g, letra => letra.toUpperCase());
                return `<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escaparHtml(etiqueta)}</dt><dd class="mt-1 text-sm text-slate-800">${valorSolicitudLegible(dato, profundidad + 1)}</dd></div>`;
            }).join('')}</dl>`;
        }).join('')}</div>`;
    };
    const textoSolicitud = valor => valorSolicitudLegible(valor);
    const valorCambioSolicitudLegible = (valor, campo) => {
        const unidad = campo === 'PERS_PESO' ? ' kg' : (campo === 'PERS_TALLA' ? ' m' : '');
        if (!unidad || valor === null || valor === undefined || String(valor).trim() === '') return textoSolicitud(valor);
        if (campo === 'PERS_TALLA' && Number.isFinite(Number(valor))) {
            return `${Number(valor).toFixed(2)}${escaparHtml(unidad)}`;
        }
        return `${textoSolicitud(valor)}${escaparHtml(unidad)}`;
    };
    const decodificarValorSolicitud = valor => {
        if (typeof valor !== 'string') return valor;
        const texto = valor.trim();
        if (!texto) return '';
        if ((texto.startsWith('{') && texto.endsWith('}')) || (texto.startsWith('[') && texto.endsWith(']'))) {
            try { return JSON.parse(texto); } catch (_) {}
        }
        return valor;
    };
    const valorSolicitudComparable = (valor, campo) => {
        valor = decodificarValorSolicitud(valor);
        if (valor && typeof valor === 'object' && !Array.isArray(valor) && Object.prototype.hasOwnProperty.call(valor, 'date')) valor = valor.date;
        if (valor === null || valor === undefined) return '';
        const texto = String(valor).trim();
        if (!texto) return '';
        if (['PERS_PESO', 'PERS_TALLA', 'dj2026_experiencia_anios'].includes(campo) && Number.isFinite(Number(texto))) return String(Number(texto));
        if (campo.toLowerCase().includes('fecha') || campo.toLowerCase().includes('fech')) {
            const iso = texto.match(/^(\d{4}-\d{2}-\d{2})/);
            if (iso) return iso[1];
            const local = texto.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
            if (local) return `${local[3]}-${local[2]}-${local[1]}`;
        }
        if (campo === 'PERS_BANCO') {
            const banco = texto.toUpperCase();
            return ({ 'BCP': '01', 'BANCO DE CREDITO DEL PERU': '01', 'INTERBANK': '03', 'BANCO INTERNACIONAL DEL PERU - INTERBANK': '03' })[banco] || banco;
        }
        if ([
            'PERS_ESTADO_CIVIL', 'PERS_SIST_PREV', 'PERS_GRUP_SANGRE', 'PERS_ESSALUD', 'PERS_PENSIONISTA',
            'PERS_EMBARGO', 'LAB_SUCAMEC', 'LAB_ARMA_PROPIA', 'LAB_SMO', 'PERS_VEHICULO_PROPIO',
            'CLASE_BREVETE', 'PERS_TIPO_VEHICULO', 'PERS_PARENT_EME', 'dj2026_familiar_empresa', 'dj2026_familiar_parentesco'
        ].includes(campo)) return texto.toUpperCase();
        return texto;
    };
    const listaFamiliaresSolicitud = valor => {
        const datos = decodificarValorSolicitud(valor);
        const lista = Array.isArray(datos) ? datos : (datos && typeof datos === 'object' ? [datos] : []);
        return lista.filter(item => item && typeof item === 'object').map(item => ({
            TIPO_RELA: item.TIPO_RELA || item.parentesco || '',
            Nombres: item.Nombres || item.nombres || [item.NOMB_1, item.NOMB_2, item.APEL_1, item.APEL_2].filter(Boolean).join(' '),
            OCUPACION: item.OCUPACION || item.ocupacion || '',
            FECH_NACI: item.FECH_NACI?.date || item.FECH_NACI || item.fech_naci?.date || item.fech_naci || ''
        }));
    };
    const familiaresSolicitudComparables = familiares => familiares.map(item => {
        const normalizarTexto = valor => String(valor || '').trim().replace(/\s+/g, ' ').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
        const parentesco = normalizarTexto(item.TIPO_RELA);
        const nombre = normalizarTexto(item.Nombres);
        const fecha = parentesco === 'HIJO MENOR DE EDAD'
            ? valorSolicitudComparable(item.FECH_NACI, 'FECH_NACI')
            : '';
        return [parentesco, nombre, fecha].join('|');
    }).sort();

    async function abrirRevisionSolicitudSip(id) {
        const renderSeq = ++solicitudSipRenderSeq;
        solicitudSipActual = id;
        document.getElementById('solicitudSipPersona').textContent = 'Cargando solicitud…';
        document.getElementById('solicitudSipEstado').textContent = '';
        document.getElementById('solicitudSipComparacion').replaceChildren();
        document.getElementById('solicitudSipDatosPersona').replaceChildren();
        document.getElementById('solicitudSipFamiliares').replaceChildren();
        modalSolicitudSip.classList.remove('hidden');
        modalSolicitudSip.classList.add('flex');
        try {
            const { data } = await axios.get(`${API_URL}/dj/solicitudes-sip/${encodeURIComponent(id)}`);
            if (renderSeq !== solicitudSipRenderSeq || solicitudSipActual !== id) return;
            if (!data.success) throw new Error(data.message || 'No se pudo cargar la solicitud.');
            const persona = data.personal || {};
            const nombrePersona = [persona.NOMB_1, persona.NOMB_2, persona.APEL_1, persona.APEL_2].filter(Boolean).join(' ');
            document.getElementById('solicitudSipPersona').textContent =
                `${nombrePersona} · DNI ${persona.NRO_DOCU_IDEN || '—'} · Solicitud #${id}`;
            const datosPersonales = [
                ['Personal', nombrePersona], ['DNI', persona.NRO_DOCU_IDEN], ['Sucursal', persona.SUCU_ABREVIATURA],
                ['Tipo de trabajador', persona.TIPE_DESCRIPCION], ['Cargo', persona.DESC_CARGO]
            ];
            document.getElementById('solicitudSipDatosPersona').innerHTML = datosPersonales.map(([etiqueta, valor]) =>
                `<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escaparHtml(etiqueta)}</dt><dd class="mt-1 text-sm text-slate-800">${escaparHtml(valor || '—')}</dd></div>`
            ).join('');
            const pendiente = data.solicitud?.SOLI_ESTADO === 'PENDIENTE';
            const puedeResolver = Boolean(window.puedeResolverSolicitudSip);
            document.getElementById('solicitudSipEstado').textContent = pendiente
                ? (puedeResolver
                    ? 'Revisa solo los cambios solicitados y los datos personales y familiares.'
                    : 'Pendiente de revisión. Solo Admins RRHH y Super Usuario pueden aprobar o rechazar esta solicitud.')
                : `Esta solicitud ya fue ${data.solicitud?.SOLI_ESTADO || 'atendida'}.`;
            document.getElementById('aprobarSolicitudSip').disabled = !pendiente || !puedeResolver;
            document.getElementById('rechazarSolicitudSip').disabled = !pendiente || !puedeResolver;
            const tbody = document.getElementById('solicitudSipComparacion');
            const etiquetas = {
                DJ_FAMILIARES: 'Datos familiares', FECH_CADUCA_DNI: 'Caducidad del DNI',
                PERS_ESTADO_CIVIL: 'Estado civil', PERS_TELEFONO: 'Celular', PERS_EMAIL: 'Correo electrónico',
                PERS_WHATSAPP: 'WhatsApp', SABE_NADAR: 'Sabe nadar', PERS_GRUP_SANGRE: 'Tipo de sangre',
                PERS_PESO: 'Peso (kg)', PERS_TALLA: 'Talla (m)', PERS_SIST_PREV: 'Sistema previsional',
                PERS_ESSALUD: 'EsSalud Vida', PERS_PENSIONISTA: 'Pensionista', PERS_GRADO_INST: 'Grado de instrucción',
                PERS_INSTITUCION: 'Institución', PERS_CARRERA: 'Carrera', PERS_ANIO_EGRESO: 'Año de egreso',
                PERS_EMBARGO: 'Embargos financieros', PERS_BANCO: 'Cuenta de sueldo', DIRECCION: 'Dirección actual',
                DIRECCION_DNI: 'Dirección del DNI', PERS_NOMCONTACTO: 'Contacto de emergencia',
                PERS_NROEMERGENCIA: 'Celular de emergencia', PERS_PARENT_EME: 'Parentesco de emergencia',
                DEPA_CODIGO_NACI: 'Departamento de nacimiento', PROVI_CODIGO_NACI: 'Provincia de nacimiento',
                DIST_NACI: 'Distrito de nacimiento', PERS_DEPT_ACT: 'Departamento actual',
                PERS_PROV_ACT: 'Provincia actual', PERS_DIST_ACT: 'Distrito actual', PERS_DEPT_DNI: 'Departamento del DNI',
                PERS_PROV_DNI: 'Provincia del DNI', PERS_DIST_DNI: 'Distrito del DNI', LAB_SUCAMEC: 'Curso SUCAMEC',
                LAB_SUCAMEC_OBS: 'Detalle SUCAMEC', LAB_LIC_ARMA: 'Licencia L4', LAB_ARMA_PROPIA: 'Arma propia',
                LAB_SMO: 'Servicio militar', PERS_BREVETE: 'Brevete', CLASE_BREVETE: 'Clase de brevete',
                PERS_TIPO_VEHICULO: 'Categoría de brevete', PERS_VEHICULO_PROPIO: 'Vehículo propio',
                PERS_ULTIMA_EMP: 'Empresa anterior', PERS_ULTIMO_CARGO: 'Cargo anterior',
                PERS_ULTIMA_DURACION: 'Duración en cargo anterior', dj2026_ocupacion_principal: 'Ocupación principal',
                dj2026_experiencia_anios: 'Experiencia (años)', dj2026_familiar_empresa: 'Familiar en la empresa',
                dj2026_familiar_nombre: 'Nombre del familiar', dj2026_familiar_parentesco: 'Parentesco del familiar',
                dj2026_descripcion: 'Ocupaciones adicionales', dj2026_ciudad_naci: 'Ciudad de nacimiento'
            };
            const detalles = data.detalles || [];
            tbody.replaceChildren();
            const detalleFamiliares = detalles.find(item => item.SOLD_CAMPO === 'DJ_FAMILIARES');
            const familiaresAnteriores = detalleFamiliares
                ? listaFamiliaresSolicitud(detalleFamiliares.SOLD_VALOR_ANTERIOR)
                : listaFamiliaresSolicitud(data.familiares_actuales);
            const familiaresSolicitados = detalleFamiliares
                ? listaFamiliaresSolicitud(detalleFamiliares.SOLD_VALOR_PROPUESTO)
                : familiaresAnteriores;
            const familiaresSinCambios = JSON.stringify(familiaresSolicitudComparables(familiaresAnteriores))
                === JSON.stringify(familiaresSolicitudComparables(familiaresSolicitados));
            const familiaresHtml = familiares => familiares.length
                ? textoSolicitud(familiares)
                : '<p class="text-sm text-slate-400">Sin familiares registrados</p>';
            const contenedorFamiliares = document.getElementById('solicitudSipFamiliares');
            contenedorFamiliares.className = familiaresSinCambios
                ? 'grid grid-cols-1 gap-4'
                : 'grid grid-cols-1 gap-4 md:grid-cols-2';
            contenedorFamiliares.innerHTML = familiaresSinCambios
                ? `<div><p class="mb-2 text-sm text-slate-500">Sin cambios solicitados. Datos familiares registrados:</p>${familiaresHtml(familiaresAnteriores)}</div>`
                : [
                    ['Registrados', familiaresAnteriores], ['Solicitados', familiaresSolicitados]
                ].map(([titulo, familiares]) =>
                    `<div><h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">${titulo}</h4>${familiaresHtml(familiares)}</div>`
                ).join('');

            let totalCambios = 0;
            const camposRenderizados = new Set();
            detalles.filter(item => !['DJ_DATOS_ACTUALES', 'DJ_PROPUESTA_COMPLETA', 'DJ_FAMILIARES'].includes(item.SOLD_CAMPO))
                .filter(item => valorSolicitudComparable(item.SOLD_VALOR_ANTERIOR, item.SOLD_CAMPO) !== valorSolicitudComparable(item.SOLD_VALOR_PROPUESTO, item.SOLD_CAMPO))
                .filter(item => {
                    if (camposRenderizados.has(item.SOLD_CAMPO)) return false;
                    camposRenderizados.add(item.SOLD_CAMPO);
                    return true;
                })
                .forEach(item => {
                    totalCambios++;
                    const fila = document.createElement('tr');
                    fila.innerHTML = `<td class="border p-3 font-semibold text-slate-700">${escaparHtml(etiquetas[item.SOLD_CAMPO] || item.SOLD_CAMPO)}</td><td class="border p-3 align-top">${valorCambioSolicitudLegible(item.SOLD_VALOR_ANTERIOR, item.SOLD_CAMPO)}</td><td class="border p-3 align-top">${valorCambioSolicitudLegible(item.SOLD_VALOR_PROPUESTO, item.SOLD_CAMPO)}</td>`;
                    tbody.appendChild(fila);
                });
            if (detalleFamiliares && !familiaresSinCambios) totalCambios++;
            if (totalCambios === 0) {
                const fila = document.createElement('tr');
                fila.innerHTML = '<td colspan="3" class="border p-4 text-center text-sm text-slate-500">No hay cambios personales adicionales; revisa los datos familiares mostrados abajo.</td>';
                tbody.appendChild(fila);
            }
        } catch (error) {
            document.getElementById('solicitudSipEstado').textContent = error.response?.data?.message || error.message || 'Error cargando solicitud.';
        }
    }
    window.abrirRevisionSolicitudSip = abrirRevisionSolicitudSip;

    function cerrarRevisionSolicitudSip() {
        solicitudSipRenderSeq++;
        modalSolicitudSip.classList.add('hidden');
        modalSolicitudSip.classList.remove('flex');
        solicitudSipActual = null;
    }
    ['cerrarModalSolicitudSip', 'cerrarModalSolicitudSip2'].forEach(id => document.getElementById(id)?.addEventListener('click', cerrarRevisionSolicitudSip));
    modalSolicitudSip?.addEventListener('click', event => { if (event.target === modalSolicitudSip) cerrarRevisionSolicitudSip(); });

    async function resolverSolicitudSip(decision) {
        if (!solicitudSipActual) return;
        if (!window.puedeResolverSolicitudSip) {
            return Swal.fire('Acceso restringido', 'Solo Admins RRHH y Super Usuario pueden aprobar o rechazar solicitudes DJ.', 'warning');
        }
        const confirmacion = await Swal.fire({
            title: decision === 'APROBADA' ? '¿Aprobar la solicitud?' : '¿Rechazar la solicitud?',
            text: decision === 'APROBADA' ? 'Los cambios se guardarán en los datos del personal en SISOL.' : 'La solicitud quedará registrada como rechazada.',
            icon: 'question', showCancelButton: true, confirmButtonText: decision === 'APROBADA' ? 'Aprobar' : 'Rechazar', cancelButtonText: 'Volver'
        });
        if (!confirmacion.isConfirmed) return;
        try {
            const { data } = await axios.post(`${API_URL}/dj/solicitudes-sip/${encodeURIComponent(solicitudSipActual)}/resolver`, { decision });
            await Swal.fire('Listo', data.message, 'success');
            cerrarRevisionSolicitudSip();
            cargarDatosEtapa2();
        } catch (error) {
            Swal.fire('No se pudo resolver', error.response?.data?.message || error.message || 'Intenta nuevamente.', 'error');
        }
    }
    document.getElementById('aprobarSolicitudSip')?.addEventListener('click', () => resolverSolicitudSip('APROBADA'));
    document.getElementById('rechazarSolicitudSip')?.addEventListener('click', () => resolverSolicitudSip('RECHAZADA'));

    // ============================================================
    // 3° ETAPA: CONTROL DE IMPRESIONES (MISMO SP + BOTONES ACTIVOS)
    // ============================================================
    const tblPersonasEtapa3 = new Tabulator("#tblPersonasEtapa3", {
        height: "550px",
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: true,
        paginationSize: 20,
        selectable: true,
        rowFormatter: function (row) {
            const d = row.getData();
            const el = row.getElement();
            const gen = d.generado === 1 || d.generado === true || d.generado === 'SI' || d.generado === '1';
            el.classList.toggle('rep-cesado', !gen);
            el.style.removeProperty('background-color');
        },
        locale: "es",
        langs: { "es": { "pagination": { "first": "Primero", "prev": "Anterior", "next": "Siguiente", "last": "Último" } } },
        columns: [
            {
                title: "",
                formatter: "rowSelection",
                titleFormatter: "rowSelection",
                hozAlign: "center",
                headerSort: false,
                width: 50,
            },
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 50, 
                headerSort: false, 
                responsive: false 
            },
            // {
            //     title: "Verificado", field: "migrado", hozAlign: "center", widthGrow: 1.5,
            //     formatter: cell => {
            //         const esVerificado = cell.getValue() === 'SI';
            //         const texto = esVerificado ? 'VERIFICADO' : 'SIN VERIFICAR';
            //         const color = esVerificado ? 'border-success bg-success text-white' : 'border-yellow-300 bg-yellow-50 text-yellow-800';
            //         return `<span class="inline-flex items-center rounded-full border ${color} px-3 py-1 text-[10px] font-bold tracking-wider whitespace-nowrap">${texto}</span>`;
            //     }
            // },
            {
                title: "Generado", field: "generado", hozAlign: "center", width: 90,
                headerSort: false,
                formatter: cell => {
                    const d = cell.getData();
                    const gen = estaGenerado(d.codPersonal || d.id, d.cambio);
                    const color = gen ? 'text-green-700 bg-green-50 border-green-300' : 'text-red-600 bg-red-50 border-red-200';
                    return `<span class="inline-flex items-center rounded-full border ${color} px-3 py-0.5 text-xs font-semibold">${gen ? 'SI' : 'NO'}</span>`;
                },
                cellClick: (e, cell) => {
                    const d = cell.getData();
                    const cod = d.codPersonal || d.id;
                    if (!estaGenerado(cod, d.cambio)) return;
                    Swal.fire({
                        icon: 'question',
                        title: '¿Resetear marca en Etapa 3?',
                        text: 'Se marcará este registro como pendiente de generar PDF.',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, resetear',
                        cancelButtonText: 'Cancelar',
                    }).then(async r => {
                        if (!r.isConfirmed) return;
                        await desmarcarDJGenerado(cod);
                        cargarDatosEtapa3();
                    });
                }
            },
            {
                title: "Apellidos", field: "apellidos", hozAlign: "left", widthGrow: 2,
                formatter: cell => { const d = cell.getData(); return `${d.apellido1 ?? d.APEL_1 ?? ''} ${d.apellido2 ?? d.APEL_2 ?? ''}`.trim(); }
            },
            {
                title: "Nombres", field: "nombres", hozAlign: "left", widthGrow: 1.5,
                formatter: cell => { const d = cell.getData(); return `${d.nombres ?? d.NOMB_1 ?? ''} ${d.NOMB_2 ?? ''}`.trim(); }
            },
            { title: "DNI", field: "dni", hozAlign: "center", width: 110 },
            { title: "Sucursal", field: "sucursal", hozAlign: "center", widthGrow: 1 },
            { 
                title: "Tipo", field: "tipoPer", hozAlign: "center", widthGrow: 2,
                formatter: cell => {
                    const val = cell.getValue() ?? '';
                    let color = 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm';
                    
                    if (val.toUpperCase().includes('OPERATIVO')) { 
                        color = 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ADMINISTRATIVO')) { 
                        color = 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ESPECIAL')) { 
                        color = 'bg-orange-100 border-orange-500 text-orange-800 shadow-sm'; 
                    }

                    return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 125px;">${val}</span>` : '—';
                }
            },
            {
                title: "Fecha Generado",
                field: "fechaGenerado",
                hozAlign: "center",
                widthGrow: 2,
                formatter: cell => {
                    const val = cell.getValue();
                    if (val && val !== 'sin cambios') {
                        const f = formatearFechaHora(val);
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700 whitespace-nowrap">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar text-blue-500'></i> <span>${f.fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five text-orange-500'></i> <span>${f.hora}</span></span>
                        </div>`;
                    }
                    return '—';
                }
            },
            {
                title: "Usuario", hozAlign: "left", widthGrow: 1.5, headerSort: false,
                formatter: cell => {
                    const d = cell.getData();
                    return d.generadoPor || '—';
                }
            },
            {
                title: "Acciones", hozAlign: "center", headerSort: false, widthGrow: 1,
                formatter: cell => {
                    // 🔥 Le metemos un diseño más acorde a un PDF (Rojito y con icono)
                    return `<button type="button" class="btn rounded-full btn-export-pdf-e3 hover:bg-danger hover:text-white bg-danger/10 text-danger px-3 py-1 flex items-center justify-center gap-1 mx-auto" title="Exportar PDF"><i class='bx bxs-file-pdf text-lg'></i> PDF</button>`;
                },
                cellClick: async (e, cell) => {
                    const btn = e.target.closest('.btn-export-pdf-e3');
                    if (!btn) return;

                    const rowData = cell.getRow().getData();
                    const dni = rowData.dni ?? rowData.NRO_DOCU_IDEN ?? 'Desconocido';

                    // 🔥 Disparamos la generación individual usando tu función global masiva
                    const resultadoGen = await _generarUnificado([rowData], `DJ_${dni}`, 'migracion');

                    // Si todo salió bien, guardamos la marca de "impreso" y recargamos la tabla
                    if (resultadoGen?.ok && resultadoGen.generadosOk.length) {
                        marcarDJGeneradosBatch(
                            resultadoGen.generadosOk.map(f => ({
                                codPersonal: f.codPersonal || f.id,
                                fechaCambio: f.cambio
                            }))
                        );
                        cargarDatosEtapa3();
                    }
                }
            }
        ],
    });
    
    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    // reformatNums(tblPersonasEtapa3);

    tblPersonasEtapa3.on("renderComplete", function () {
        if (this._ultimoFiltro) resaltarTexto(this, this._ultimoFiltro);

        // =========================================================
        // INYECCIÓN DE NUMERACIÓN ESTÁTICA
        // =========================================================
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

    tblPersonasEtapa3.on("rowSelectionChanged", function () {
        const sel = this.getSelectedRows().length;
        const btnGen = document.getElementById('btnGenerarSeleccionadosE3');
        const btnQuitar = document.getElementById('btnQuitarMarcaE3');
        if (btnGen) {
            btnGen.disabled = !sel;
            btnGen.className = sel
                ? 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors bg-indigo-600 text-white border border-indigo-600 hover:bg-indigo-700 hover:border-indigo-700'
                : 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors bg-indigo-100 text-indigo-400 border border-indigo-200 cursor-not-allowed';
        }
        if (btnQuitar) {
            btnQuitar.disabled = !sel;
            btnQuitar.className = sel
                ? 'flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors'
                : 'flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-orange-400 text-white rounded-lg cursor-not-allowed opacity-50 transition-colors';
        }
    });

    function cargarDatosEtapa3() {
        axios.get(`${VITE_URL_APP}/api/reporte-personal-sin-migracion`)
            .then(response => {
                if (!response.data.success) return;

                // 🔥 Filtramos en duro: Verificados (migrado='SI') y Ocultamos 'ESPECIAL'
                const datosVerificados = response.data.data.filter(d => {
                    const tipoPersonal = d.tipoPer ? d.tipoPer.toUpperCase() : (d.TIPO_PER ? d.TIPO_PER.toUpperCase() : '');
                    return d.migrado === 'SI' && !tipoPersonal.includes('ESPECIAL');
                });

                tblPersonasEtapa3.setData(datosVerificados);
                aplicarFiltrosE3();
            });
    }

    function aplicarFiltrosE3() {
        const codSucursal = document.getElementById('filtroSucursalE3')?.value || '00';
        const codTipoPer = document.getElementById('filtroTipoPerE3')?.value || '00';
        const texto = document.getElementById('buscarPersonalE3')?.value.toLowerCase().trim() || '';
        const radioVal = document.getElementById('filtroEstadoE3')?.value || 'null';

        let tipoTxt = '';
        if (codTipoPer !== '00') {
            if (codTipoPer === '01') tipoTxt = 'OPERATIVO 4°';
            if (codTipoPer === '03') tipoTxt = 'OPERATIVO 5°';
            if (codTipoPer === '02') tipoTxt = 'ADMINISTRATIVO 4°';
            if (codTipoPer === '05') tipoTxt = 'ADMINISTRATIVO 5°';
        }

        // Limpiamos filtros anteriores
        tblPersonasEtapa3.clearFilter();

        // Aplicamos un filtro personalizado que evalúa todas las condiciones a la vez
        tblPersonasEtapa3.setFilter(function (data) {
            let matchSucursal = true;
            let matchTipo = true;
            let matchTexto = true;
            let matchRadio = true;

            if (codSucursal !== '00') matchSucursal = data.codSucursal === codSucursal;
            if (codTipoPer !== '00') matchTipo = data.tipoPer === tipoTxt;

            if (texto) {
                const nombre = (data.nombres || '').toLowerCase();
                const ape1 = (data.apellido1 || '').toLowerCase();
                const ape2 = (data.apellido2 || '').toLowerCase();
                const dni = (data.dni || '').toLowerCase();
                matchTexto = nombre.includes(texto) || ape1.includes(texto) || ape2.includes(texto) || dni.includes(texto);
            }

            // Lógica para saber si está generado o pendiente leyendo de tu localStorage (estaGenerado)
            if (radioVal !== 'null') {
                const cod = data.codPersonal || data.id;
                const gen = estaGenerado(cod, data.cambio);
                if (radioVal === '0') matchRadio = gen === true;  // Generados
                if (radioVal === '1') matchRadio = gen === false; // Pendientes
            }

            return matchSucursal && matchTipo && matchTexto && matchRadio;
        });
        tblPersonasEtapa3.setPage(1);

        // =========================================================================
        // 🔥 LÓGICA DE INDICADORES: Calculamos SOLO en base a Sucursal y Tipo
        // =========================================================================
        const todaLaData = tblPersonasEtapa3.getData();

        const dataParaTarjetas = todaLaData.filter(d => {
            const cumpleSucursal = (codSucursal === '00') || (d.codSucursal === codSucursal);
            const cumpleTipo = (codTipoPer === '00') || (d.tipoPer === tipoTxt);
            return cumpleSucursal && cumpleTipo;
        });

        const total = dataParaTarjetas.length;
        let generados = 0;

        // Contamos cuántos están generados
        dataParaTarjetas.forEach(d => {
            const cod = d.codPersonal || d.id;
            if (estaGenerado(cod, d.cambio)) generados++;
        });

        const pendientes = total - generados;

        // Pintamos los números en las Cards
        document.getElementById('contadorTotalE3').textContent = total;

        const elGenerados = document.getElementById('contadorGeneradosE3');
        if (elGenerados) elGenerados.textContent = generados;

        const elPendientes = document.getElementById('contadorPendientesE3');
        if (elPendientes) elPendientes.textContent = pendientes;
    }

    // Event Listeners
    document.getElementById('filtroSucursalE3')?.addEventListener('change', aplicarFiltrosE3);
    document.getElementById('filtroTipoPerE3')?.addEventListener('change', aplicarFiltrosE3);
    document.getElementById('buscarPersonalE3')?.addEventListener('keyup', function () {
        const valor = this.value.toLowerCase().trim();
        tblPersonasEtapa3._ultimoFiltro = valor;
        aplicarFiltrosE3();
        setTimeout(() => resaltarTexto(tblPersonasEtapa3, valor), 10);
    });
    document.getElementById('filtroEstadoE3')?.addEventListener('change', aplicarFiltrosE3);

    document.getElementById('page-size-etapa3')?.addEventListener('change', function () {
        tblPersonasEtapa3.setPageSize(parseInt(this.value));
    });

    // ============================================================
    // EXPORTAR EXCEL PERSONALIZADO ETAPA 3 (Clonado Formato Etapa 2)
    // ============================================================
    document.getElementById("btnExportExcelE3")?.addEventListener("click", async () => {
        let data = tblPersonasEtapa3.getData("active");
        if (!data.length) return Swal.fire('Sin datos', 'No hay datos para exportar', 'warning');

        const totalRegistros = data.length;
        const totalGenerados = data.filter(d => estaGenerado(d.codPersonal || d.id, d.cambio)).length;
        const totalSinGenerar = totalRegistros - totalGenerados;

        const selSucursal = document.getElementById('filtroSucursalE3');
        const txtSucursal = selSucursal.options[selSucursal.selectedIndex]?.text?.toUpperCase() || 'TODAS LAS SUCURSALES';
        
        const selTipo = document.getElementById('filtroTipoPerE3');
        const txtTipo = selTipo.options[selTipo.selectedIndex]?.text?.toUpperCase() || 'TODOS';
        const tipoFiltroTexto = txtTipo === 'TODOS' ? '' : txtTipo;

        const f = new Date();
        const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;

        const radioVal = document.getElementById('filtroEstadoE3')?.value || 'null';
        let baseTitle = "ETAPA N°3: REPORTE COMPLETO DE GENERACIÓN DJ";
        let estadoArchivo = "Todos";

        if (radioVal === '1') {
            baseTitle = "ETAPA N°3: REPORTE DE PENDIENTES DE GENERACIÓN DJ";
            estadoArchivo = "Sin_Generar";
        } else if (radioVal === '0') {
            baseTitle = "ETAPA N°3: REPORTE DE GENERADOS DJ";
            estadoArchivo = "Generados";
        }
        
        const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
        const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

        Swal.fire({ title: 'Generando Excel...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Etapa 3 - Generados", { views: [{ showGridLines: false }] });

            if (window.logoUrl) {
                try {
                    const response = await fetch(window.logoUrl);
                    const blob = await response.blob();
                    const base64 = await new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.readAsDataURL(blob);
                    });
                    const imageId = workbook.addImage({ base64: base64, extension: 'png' });
                    worksheet.addImage(imageId, { tl: { col: 0, row: 0 }, ext: { width: 170, height: 50 } });
                } catch (e) { console.warn("No se pudo cargar logo", e); }
            }

            worksheet.mergeCells('A1:G1');
            const title1 = worksheet.getCell('A1');
            title1.value = "SOL SECURITY";
            title1.font = { name: 'Arial', size: 16, bold: true, color: { argb: 'FF990000' } };
            title1.alignment = { horizontal: 'center', vertical: 'middle' };

            worksheet.mergeCells('A2:G2');
            const title2 = worksheet.getCell('A2');
            title2.value = "SISTEMA INTEGRADO SOLMAR - SISOL WEB";
            title2.font = { name: 'Arial', size: 12, bold: true };
            title2.alignment = { horizontal: 'center', vertical: 'middle' };

            worksheet.mergeCells('A3:G3');
            const title3 = worksheet.getCell('A3');
            const subtituloSucursal = txtSucursal === 'TODAS LAS SUCURSALES' ? '' : ` | Sol ${capitalizeWords(txtSucursal)}`;
            title3.value = `${tituloReporte}${subtituloSucursal}`;
            title3.font = { name: 'Arial', size: 11, bold: true };
            title3.alignment = { horizontal: 'center', vertical: 'middle' };

            const statsHeaders = ['A6', 'B6', 'C6', 'D6'];
            const statsValues = ['A7', 'B7', 'C7', 'D7'];

            worksheet.getCell('A6').value = "Generado";
            worksheet.getCell('B6').value = "Total";
            worksheet.getCell('C6').value = "Sin Generar";
            worksheet.getCell('D6').value = "Generados";

            worksheet.getCell('A7').value = fechaStr;
            worksheet.getCell('B7').value = totalRegistros;
            worksheet.getCell('C7').value = totalSinGenerar;
            worksheet.getCell('D7').value = totalGenerados;

            statsHeaders.forEach(cell => {
                const c = worksheet.getCell(cell);
                c.font = { name: 'Arial', bold: true, color: { argb: 'FF000000' } };
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE5E7EB' } };
                c.alignment = { horizontal: 'center', vertical: 'middle' };
                c.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            statsValues.forEach(cell => {
                const c = worksheet.getCell(cell);
                c.font = { name: 'Arial', bold: true };
                c.alignment = { horizontal: 'center', vertical: 'middle' };
                c.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            const headers = ["N°", "Generado", "Nombres", "DNI", "Sucursal", "Tipo", "Fecha Generado"];
            const headerRow = worksheet.getRow(10);
            headerRow.values = headers;
            headerRow.height = 25;

            headerRow.eachCell((cell) => {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E78' } };
                cell.font = { color: { argb: 'FFFFFFFF' }, bold: true, name: 'Arial', size: 10 };
                cell.alignment = { horizontal: 'center', vertical: 'middle' };
                cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
            });

            data.forEach((d, index) => {
                const genBool = estaGenerado(d.codPersonal || d.id, d.cambio);
                const generadoTxt = genBool ? 'SI' : 'NO';
                const nombreCompleto = d.NOMBRE || d.PERSONAL || `${d.nombres ?? d.NOMB_1 ?? ''} ${d.apellido1 ?? d.APEL_1 ?? ''} ${d.apellido2 ?? d.APEL_2 ?? ''}`.trim();

                let fechaGen = '—';
                if (d.fechaGenerado && d.fechaGenerado !== 'sin cambios') {
                    fechaGen = d.fechaGenerado.replace('T', ' ').substring(0, 16);
                }

                const row = worksheet.addRow([
                    index + 1,
                    generadoTxt,
                    nombreCompleto,
                    d.dni ?? d.NRO_DOCU_IDEN ?? '',
                    d.sucursal ?? d.SUCURSAL ?? '',
                    d.tipoPer ?? d.TIPO_PER ?? '',
                    fechaGen
                ]);

                row.eachCell((cell, colNumber) => {
                    cell.font = { name: 'Arial', size: 9 };
                    cell.border = {
                        top: { style: 'thin', color: { argb: 'FFBFBFBF' } },
                        left: { style: 'thin', color: { argb: 'FFBFBFBF' } },
                        bottom: { style: 'thin', color: { argb: 'FFBFBFBF' } },
                        right: { style: 'thin', color: { argb: 'FFBFBFBF' } }
                    };

                    if (colNumber === 3) {
                        cell.alignment = { vertical: 'middle', horizontal: 'left' };
                    } else {
                        cell.alignment = { vertical: 'middle', horizontal: 'center' };
                    }

                    if (colNumber === 2) {
                        if (generadoTxt === 'NO') {
                            cell.font = { color: { argb: 'FFFF0000' }, bold: true, size: 9, name: 'Arial' };
                        } else {
                            cell.font = { color: { argb: 'FF00B050' }, bold: true, size: 9, name: 'Arial' };
                        }
                    }
                });
            });

            worksheet.columns = [
                { width: 5 },  // N°
                { width: 12 }, // Generado
                { width: 45 }, // Nombres
                { width: 15 }, // DNI
                { width: 15 }, // Sucursal
                { width: 20 }, // Tipo
                { width: 20 }  // Fecha Generado
            ];

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
            link.download = `Etapa3_Generacion_DJ_${estadoArchivo}_${tipoArchivo}_${txtSucursal.replace(/ /g, '_')}_${fStr}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            Swal.close();
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Problema al generar el Excel.', 'error');
        }
    });

    // ============================================================
    // EXPORTAR PDF PERSONALIZADO ETAPA 3 (Clonado Formato Etapa 2)
    // ============================================================
    document.getElementById("btnExportPdfE3")?.addEventListener("click", async () => {
        let data = tblPersonasEtapa3.getData("active");
        if (!data.length) return Swal.fire('Sin datos', 'No hay datos para exportar', 'warning');

        const totalRegistros = data.length;
        const totalGenerados = data.filter(d => estaGenerado(d.codPersonal || d.id, d.cambio)).length;
        const totalSinGenerar = totalRegistros - totalGenerados;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        const totalWidth = doc.internal.pageSize.getWidth();

        const selSucursal = document.getElementById('filtroSucursalE3');
        const txtSucursal = selSucursal?.options[selSucursal.selectedIndex]?.text?.toUpperCase() || 'TODAS LAS SUCURSALES';
        
        const selTipo = document.getElementById('filtroTipoPerE3');
        const txtTipo = selTipo?.options[selTipo.selectedIndex]?.text?.toUpperCase() || 'TODOS';
        const tipoFiltroTexto = txtTipo === 'TODOS' ? '' : txtTipo;

        const f = new Date();
        const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;

        const radioVal = document.getElementById('filtroEstadoE3')?.value || 'null';
        let baseTitle = "ETAPA N°3: REPORTE COMPLETO DE GENERACIÓN DJ";
        let estadoArchivo = "Todos";

        if (radioVal === '1') {
            baseTitle = "ETAPA N°3: REPORTE DE PENDIENTES DE GENERACIÓN DJ";
            estadoArchivo = "Sin_Generar";
        } else if (radioVal === '0') {
            baseTitle = "ETAPA N°3: REPORTE DE GENERADOS DJ";
            estadoArchivo = "Generados";
        }
        
        const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
        const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

        let logoBase64 = null;
        try {
            if (window.logoUrl) {
                const res = await fetch(window.logoUrl);
                const blob = await res.blob();
                logoBase64 = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.readAsDataURL(blob);
                });
            }
        } catch (e) { console.warn("No se pudo cargar el logo", e); }

        doc.autoTable({
            startY: 60,
            theme: 'grid',
            headStyles: { fillColor: [243, 244, 246], textColor: [55, 65, 81], fontStyle: 'bold', halign: 'center' },
            bodyStyles: { fontSize: 8 },
            columnStyles: { 0: { halign: 'center' }, 1: { halign: 'center' } },
            head: [["N°", "Generado", "Nombres", "DNI", "Sucursal", "Tipo", "Fecha Generado"]],
            body: data.map((d, index) => {
                const nombreCompleto = d.NOMBRE || d.PERSONAL || `${d.nombres ?? d.NOMB_1 ?? ''} ${d.apellido1 ?? d.APEL_1 ?? ''} ${d.apellido2 ?? d.APEL_2 ?? ''}`.trim();
                const genBool = estaGenerado(d.codPersonal || d.id, d.cambio);
                const generadoTxt = genBool ? 'SI' : 'NO';
                const fechaGen = d.fechaGenerado && d.fechaGenerado !== 'sin cambios'
                    ? d.fechaGenerado.replace('T', ' ').substring(0, 16)
                    : '—';

                return [
                    index + 1,
                    generadoTxt,
                    nombreCompleto,
                    d.dni ?? d.NRO_DOCU_IDEN ?? '',
                    d.sucursal ?? d.SUCURSAL ?? '',
                    d.tipoPer ?? d.TIPO_PER ?? '',
                    fechaGen
                ];
            }),
            didDrawPage: function (dataPage) {
                if (dataPage.pageNumber !== 1) return;

                if (logoBase64) doc.addImage(logoBase64, 'PNG', 14, 10, 40, 12);

                doc.setFontSize(10);
                doc.setTextColor(180, 0, 0);
                doc.setFont("helvetica", "bold");
                doc.text("SISTEMA INTEGRADO SOLMAR – SISOL WEB", totalWidth / 2, 14, { align: "center" });

                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text(tituloReporte, totalWidth / 2, 20, { align: "center" });

                const subtitulo = txtSucursal === 'TODAS LAS SUCURSALES' ? '' : `Sol ${txtSucursal}`;
                if (subtitulo !== '') doc.text(subtitulo, totalWidth / 2, 26, { align: "center" });

                doc.setFontSize(8);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 100, 100);
                doc.text(`Generado: ${fechaStr}`, totalWidth - 14, 14, { align: "right" });

                const cardW = 45; const cardH = 18; const gap = 10;
                const totalCardsW = (cardW * 3) + (gap * 2);
                const startX = (totalWidth - totalCardsW) / 2;
                const cardY = 32;

                const cards = [
                    { title: "Total", value: totalRegistros, color: [75, 85, 99] },
                    { title: "Sin generar", value: totalSinGenerar, color: [202, 138, 4] },
                    { title: "Generados", value: totalGenerados, color: [4, 120, 87] }
                ];

                cards.forEach((card, i) => {
                    const x = startX + (i * (cardW + gap));
                    doc.setFillColor(...card.color);
                    doc.roundedRect(x, cardY, cardW, cardH, 2, 2, 'F');
                    doc.setTextColor(255, 255, 255);
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(16);
                    doc.text(String(card.value), x + (cardW / 2), cardY + 10, { align: "center" });
                    doc.setFontSize(8);
                    doc.setFont("helvetica", "normal");
                    doc.text(card.title, x + (cardW / 2), cardY + 15, { align: "center" });
                });
            }
        });

        const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
        doc.save(`Etapa3_Generacion_DJ_${estadoArchivo}_${tipoArchivo}_${txtSucursal.replace(/ /g, '_')}_${fStr}.pdf`);
    });
    
    // 🔥 ACCIÓN: DJ UNIFICADO PARA ETAPA 3
    document.getElementById('btnDJUnificadoE3')?.addEventListener('click', async function () {
        const todasEtapa3 = tblPersonasEtapa3.getData("active");
        if (!todasEtapa3.length) {
            Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No hay registros visibles en la tabla.' });
            return;
        }

        const pendientes = todasEtapa3.filter(f => !estaGenerado(f.codPersonal || f.id, f.cambio));
        const yaGenerados = todasEtapa3.filter(f => estaGenerado(f.codPersonal || f.id, f.cambio));

        const { value: opcion, isConfirmed } = await Swal.fire({
            title: 'DJ Masivo — Etapa 3',
            html: `
            <div style="display:flex;flex-direction:column;gap:10px;text-align:left;font-size:13px;padding:4px 0;">
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;" id="lbl-pend-e3">
                    <input type="radio" name="djopcion_e3" value="pendientes" ${pendientes.length ? '' : 'disabled'} style="width:16px;height:16px;cursor:pointer;accent-color:#6366f1;">
                    <div>
                        <div style="font-weight:600;color:${pendientes.length ? '#111827' : '#9ca3af'};">
                            Solo pendientes <span style="margin-left:6px;background:${pendientes.length ? '#dcfce7' : '#f3f4f6'};color:${pendientes.length ? '#16a34a' : '#9ca3af'};font-size:11px;padding:1px 8px;border-radius:20px;font-weight:700;">${pendientes.length}</span>
                        </div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;" id="lbl-todos-e3">
                    <input type="radio" name="djopcion_e3" value="todos" style="width:16px;height:16px;cursor:pointer;accent-color:#6366f1;">
                    <div>
                        <div style="font-weight:600;color:#111827;">
                            Todos los registros <span style="margin-left:6px;background:#dbeafe;color:#1e40af;font-size:11px;padding:1px 8px;border-radius:20px;font-weight:700;">${todasEtapa3.length}</span>
                        </div>
                    </div>
                </label>
            </div>`,
            showCancelButton: true, confirmButtonText: 'Generar PDF', cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const sel = document.querySelector('input[name="djopcion_e3"]:checked');
                if (!sel) { Swal.showValidationMessage('Selecciona una opción.'); return false; }
                return sel.value;
            }
        });

        if (!isConfirmed) return;
        const filasFinales = opcion === 'pendientes' ? pendientes : todasEtapa3;

        const confirmacion = await Swal.fire({
            icon: 'question', title: 'Confirmar generación',
            html: `Se generará <b>1 PDF</b> con <b>${filasFinales.length}</b> declaración(es).<br>¿Desea continuar?`,
            showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;

        // 🔥 Ahora el archivo se llamará DJ_Masivo_Etapa3
        const resultadoGen = await _generarUnificado(filasFinales, 'DJ_Masivo_Etapa3', 'migracion');
        if (resultadoGen?.ok && resultadoGen.generadosOk.length) {
            marcarDJGeneradosBatch(
                resultadoGen.generadosOk.map(f => ({
                    codPersonal: f.codPersonal || f.id,
                    fechaCambio: f.cambio
                }))
            );
        }
        cargarDatosEtapa3();
    });

    // 🔥 ACCIÓN: RESETEAR MARCAS PARA ETAPA 3
    document.getElementById('btnResetearDJsE3')?.addEventListener('click', async function () {
        const totalMarcados = Object.keys(generadosCache).length;
        if (totalMarcados === 0) {
            Swal.fire({ icon: 'info', title: 'Sin marcas', text: 'No hay registros marcados como generados.' });
            return;
        }

        const { isConfirmed } = await Swal.fire({
            icon: 'warning', title: 'Resetear marcas en Etapa 3',
            html: `Se eliminarán las marcas ✅ de <b>${totalMarcados}</b> registro(s).`,
            showCancelButton: true, confirmButtonText: 'Sí, resetear todo', cancelButtonText: 'Cancelar', confirmButtonColor: '#ef4444',
        });

        if (!isConfirmed) return;
        await resetearGeneradosAPI();
        cargarDatosEtapa3();
    });

    // 🔥 ACCIÓN: GENERAR SELECCIONADOS
    document.getElementById('btnGenerarSeleccionadosE3')?.addEventListener('click', async function () {
        const seleccionadas = tblPersonasEtapa3.getSelectedRows();
        if (!seleccionadas.length) {
            Swal.fire({ icon: 'info', title: 'Sin selección', text: 'Selecciona al menos una persona con el checkbox.' });
            return;
        }

        const filas = seleccionadas.map(r => r.getData());

        const { isConfirmed } = await Swal.fire({
            icon: 'question', title: 'Generar DJ seleccionados',
            html: `Se generará <b>1 PDF</b> con <b>${filas.length}</b> declaración(es).<br>¿Desea continuar?`,
            showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed) return;

        const resultadoGen = await _generarUnificado(filas, 'DJ_Seleccionados_E3', 'migracion');
        if (resultadoGen?.ok && resultadoGen.generadosOk.length) {
            marcarDJGeneradosBatch(
                resultadoGen.generadosOk.map(f => ({
                    codPersonal: f.codPersonal || f.id,
                    fechaCambio: f.cambio
                }))
            );
        }
        tblPersonasEtapa3.deselectRow();
        cargarDatosEtapa3();
    });

    // 🔥 ACCIÓN: QUITAR MARCA DE SELECCIONADOS
    document.getElementById('btnQuitarMarcaE3')?.addEventListener('click', async function () {
        const seleccionadas = tblPersonasEtapa3.getSelectedRows();
        if (!seleccionadas.length) {
            Swal.fire({ icon: 'info', title: 'Sin selección', text: 'Selecciona al menos una persona con el checkbox.' });
            return;
        }

        const filas = seleccionadas.map(r => r.getData());
        const conMarca = filas.filter(f => estaGenerado(f.codPersonal || f.id, f.cambio));

        if (!conMarca.length) {
            Swal.fire({ icon: 'info', title: 'Sin marcas', text: 'Ninguno de los seleccionados tiene marca de generado.' });
            return;
        }

        const { isConfirmed } = await Swal.fire({
            icon: 'warning', title: '¿Quitar marca?',
            html: `Se quitará la marca ✅ de <b>${conMarca.length}</b> seleccionado(s).`,
            showCancelButton: true, confirmButtonText: 'Sí, quitar marca', cancelButtonText: 'Cancelar', confirmButtonColor: '#ef4444',
        });

        if (!isConfirmed) return;

        const codigos = conMarca.map(f => f.codPersonal || f.id);
        await resetearGeneradosAPI(codigos);
        tblPersonasEtapa3.deselectRow();
        cargarDatosEtapa3();
    });

    // 🔥 ACCIÓN: REPORTE DE AVANCES PARA ETAPA 3
    document.getElementById('btnReporteAvanceE3')?.addEventListener('click', async function () {
        const codSucursal = document.getElementById('filtroSucursalE3')?.value || '00';
        const codTipoPerRaw = document.getElementById('filtroTipoPerE3')?.value || '00';

        // Mapeamos el UI al formato que espera tu SP ('OPER', 'ADMIN', '00')
        let tipoMapped = '00';
        if (['01', '03'].includes(codTipoPerRaw)) tipoMapped = 'OPER';
        if (['02', '05'].includes(codTipoPerRaw)) tipoMapped = 'ADMIN';

        Swal.fire({ title: 'Generando reporte...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const response = await axios.get(`${VITE_URL_APP}/api/reporte-avances-dj`, {
                params: { sucursal: codSucursal, tipo: tipoMapped }
            });

            if (!response.data.success || !response.data.data.length) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros en el reporte.' });
                return;
            }

            let datos = response.data.data;

            // Filtro local por si usaron el input de búsqueda
            const textoBusqueda = document.getElementById('buscarPersonalE3')?.value.toLowerCase().trim() || '';
            if (textoBusqueda) {
                datos = datos.filter(d => {
                    const str = `${d.nombreCompleto} ${d.doc}`.toLowerCase();
                    return str.includes(textoBusqueda);
                });
            }

            if (!datos.length) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros que coincidan con la búsqueda.' });
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            const totalWidth = doc.internal.pageSize.getWidth();

            // 5. Texto para el subtítulo (Sucursal y Tipo)
            const selSucursal = document.getElementById('filtroSucursalE3');
            const txtSucursal = selSucursal?.options[selSucursal.selectedIndex]?.text?.toUpperCase() || 'TODAS LAS SUCURSALES';
            
            const selTipo = document.getElementById('filtroTipoPerE3');
            const txtTipo = selTipo?.options[selTipo.selectedIndex]?.text?.toUpperCase() || 'TODOS';
            const tipoFiltroTexto = txtTipo === 'TODOS' ? '' : txtTipo;

            const f = new Date();
            const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;

            // 🔥 LÓGICA DE TÍTULOS DINÁMICOS
            const radioVal = document.getElementById('filtroEstadoE3')?.value || 'null';
            let baseTitle = "ETAPA N°3: REPORTE COMPLETO DE GENERADOS DJ";
            let estadoArchivo = "Todos";

            if (radioVal === '1') {
                baseTitle = "ETAPA N°3: REPORTES PENDIENTES DE GENERACIÓN DJ";
                estadoArchivo = "Sin_Generar";
            } else if (radioVal === '0') {
                baseTitle = "ETAPA N°3: REPORTE DE GENERADOS DJ";
                estadoArchivo = "Generados";
            }
            
            const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
            const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

            let logoBase64 = null;
            try {
                if (window.logoUrl) {
                    const res = await fetch(window.logoUrl);
                    const blob = await res.blob();
                    logoBase64 = await new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.readAsDataURL(blob);
                    });
                }
            } catch (e) { }

            doc.autoTable({
                startY: 40,
                theme: 'grid',
                headStyles: { fillColor: [243, 244, 246], textColor: [55, 65, 81], fontStyle: 'bold', halign: 'center' },
                bodyStyles: { fontSize: 8 },
                columnStyles: { 0: { halign: 'center' }, 5: { halign: 'center' }, 6: { halign: 'center' }, 7: { halign: 'center' } },
                // 🔥 AQUÍ SE OMITEN "Firma Act." y "Huella Act."
                head: [["N°", "Nombres", "DNI", "Sucursal", "Tipo", "DJ Subido", "Estado", "Última Act."]],
                body: datos.map((d, index) => {
                    const fechaFormat = d.fechaAct ? d.fechaAct.replace('T', ' ').substring(0, 16) : '—';
                    return [
                        index + 1,
                        d.nombreCompleto || '',
                        d.doc || '',
                        d.sucursal || '',
                        d.tipo || '',
                        d.djSubido || 'NO',
                        d.estado || '',
                        fechaFormat
                    ];
                }),
                didDrawPage: function (dataPage) {
                    if (dataPage.pageNumber !== 1) return;

                    if (logoBase64) doc.addImage(logoBase64, 'PNG', 14, 10, 40, 12);

                    doc.setFontSize(10);
                    doc.setTextColor(180, 0, 0);
                    doc.setFont("helvetica", "bold");
                    doc.text("SISTEMA INTEGRADO SOLMAR – SISOL WEB", totalWidth / 2, 14, { align: "center" });

                    doc.setFontSize(12);
                    doc.setTextColor(0, 0, 0);
                    doc.text(tituloReporte, totalWidth / 2, 20, { align: "center" });

                    const subtitulo = txtSucursal === 'TODAS LAS SUCURSALES' ? '' : `Sol ${txtSucursal}`;
                    if (subtitulo !== '') doc.text(subtitulo, totalWidth / 2, 26, { align: "center" });

                    doc.setFontSize(8);
                    doc.setFont("helvetica", "normal");
                    doc.setTextColor(100, 100, 100);
                    doc.text(`Generado: ${fechaStr}`, totalWidth - 14, 14, { align: "right" });
                }
            });

            const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
            doc.save(`Etapa3_Generados_DJ_${estadoArchivo}_${tipoArchivo}_${txtSucursal.replace(/ /g, '_')}_${fStr}.pdf`);
            Swal.close();

        } catch (error) {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Problema al generar el reporte de avances.' });
        }
    });

    cargarDatosEtapa3();


    // ============================================================
    // NUEVA 4° ETAPA: CARGA DE DJ
    // ============================================================
    let pageSizePersonas_E4C = 20;

    const archivoDJ_E4C = document.getElementById('archivoDJ_E4C');
    const zonaDropDJ_E4C = document.getElementById('zonaDropDJ_E4C');
    const listaArchivosDJ_E4C = document.getElementById('listaArchivosDJ_E4C');

    zonaDropDJ_E4C?.addEventListener('click', () => archivoDJ_E4C.click());

    // Cargar datos la primera vez que se hace clic en la pestaña (lo gestiona el tab handler)

    // Si la pestaña ya está activa al cargar (ej. RRHH), disparar carga inicial
    const etapaCargaBtn = document.querySelector('button[data-target="etapa_carga"]');
    if (etapaCargaBtn && etapaCargaBtn.classList.contains('active')) {
        etapaCargaBtn.click();
    }

    const tblPersonas_E4C = new Tabulator('#tblPersonas_E4C', {
        height: '550px',
        layout: 'fitColumns',
        responsiveLayout: 'collapse',
        pagination: true,
        paginationSize: pageSizePersonas_E4C,
        locale: 'es',
        langs: { es: { pagination: { first: 'Primero', prev: 'Anterior', next: 'Siguiente', last: 'Último' } } },

        rowFormatter: function (row) {
            const d   = row.getData();
            const el  = row.getElement();
            const mal = d.tiene_folio_25 != 1;
            const noV = d.PERS_VIGENCIA !== 'SI';
            el.classList.toggle('rep-cesado', mal);
            el.classList.toggle('rep-alerta', !mal && noV);
            el.style.removeProperty('background-color');
            el.style.removeProperty('color');
        },

        columns: [
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 60, 
                headerSort: false, 
                responsive: false 
            },
            {
                title: 'Escaneo', field: 'tiene_folio_25', hozAlign: 'center', minWidth: 100, widthGrow: 1.2, responsive: false, headerSort: true,
                formatter: function (cell) {
                    const esSi = cell.getValue() == 1;
                    const color = esSi ? 'text-green-700 bg-green-50 border-green-300' : 'text-red-600 bg-red-50 border-red-200';
                    const icono = esSi ? 'bxs-check-circle' : 'bx-time';
                    return `<span class="inline-flex items-center gap-1.5 rounded-full border ${color} px-3 py-0.5 text-xs font-semibold cursor-pointer"><i class="bx ${icono}"></i> ${esSi ? 'SI' : 'NO'}</span>`;
                },
                cellClick: function (e, cell) {
                    const data = cell.getRow().getData();
                    abrirModalSubirDJ_E4C(data.CODI_PERS, data.personal);
                }
            },
            { title: 'Cód.', field: 'CODI_PERS', hozAlign: 'center', minWidth: 60, widthGrow: 0.5, responsive: false },
            { title: 'Apellidos', field: 'apellidos', hozAlign: 'left', minWidth: 120, widthGrow: 2, responsive: false },
            { title: 'Nombres', field: 'nombres', hozAlign: 'left', minWidth: 120, widthGrow: 2, responsive: false },
            { title: 'Nro Doc.', field: 'nroDoc', hozAlign: 'center', minWidth: 90, widthGrow: 0.8, responsive: false },
            { title: 'Sucursal', field: 'sucursal', hozAlign: 'center', minWidth: 80, widthGrow: 0.8, responsive: 0 },
            {
                title: 'Tipo', field: 'TIPOTRAB2', hozAlign: 'center', minWidth: 120, widthGrow: 1.2, responsive: false,
                formatter: function (cell) {
                    let val = cell.getValue() || '';
                    val = val.replace('OPER', 'OPERATIVO').replace('ADMIN', 'ADMINISTRATIVO');
                    
                    let color = 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm';
                    
                    if (val.toUpperCase().includes('OPERATIVO')) { 
                        color = 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ADMINISTRATIVO')) { 
                        color = 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ESPECIAL')) { 
                        color = 'bg-orange-100 border-orange-500 text-orange-800 shadow-sm'; 
                    }

                    return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 125px;">${val}</span>` : '—';
                }
            },
            {
                title: 'Acciones', field: 'acciones', minWidth: 200, widthGrow: 0,
                hozAlign: 'left', headerSort: false, responsive: false,
                formatter: function (cell) {
                    const tieneDJ = cell.getRow().getData().tiene_folio_25 == 1;
                    let html = `<div class="flex items-center gap-1.5 flex-nowrap">
                        <button type="button" class="btn rounded-full bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white text-xs px-3 py-1 flex items-center justify-center gap-1 subir-dj-btn-e4c whitespace-nowrap">
                            <i class="bx bx-upload text-base subir-dj-btn-e4c"></i> Subir DJ
                        </button>`;
                    if (tieneDJ) {
                        html += `<button type="button" class="btn rounded-full bg-cyan-100 text-cyan-600 hover:bg-cyan-600 hover:text-white text-xs px-2 py-1 flex items-center justify-center gap-1 ver-dj-btn-e4c whitespace-nowrap" title="Ver DJ">
                                    <i class="bx bx-show text-base ver-dj-btn-e4c"></i> Ver
                                </button>`;
                    }
                    html += `</div>`;
                    return html;
                },
                cellClick: function (e, cell) {
                    const data = cell.getRow().getData();
                    const codigo = data.CODI_PERS;
                    const nombre = data.personal;

                    if (e.target.closest('.subir-dj-btn-e4c')) {
                        abrirModalSubirDJ_E4C(codigo, nombre);
                    }

                    if (e.target.closest('.ver-dj-btn-e4c')) {
                        window.open(`${VITE_URL_APP}/ver-dj/${codigo}`, '_blank');
                    }
                }
            },
        ],
    });
    
    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    // reformatNums(tblPersonas_E4C);

    tblPersonas_E4C.on("renderComplete", function () {
        if (this._ultimoFiltro) {
            resaltarTexto(this, this._ultimoFiltro);
        }

        // =========================================================
        // INYECCIÓN DE NUMERACIÓN ESTÁTICA
        // =========================================================
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

    function mostrarInfoTabla_E4C() {
    }

    tblPersonas_E4C.on('dataLoaded', mostrarInfoTabla_E4C);
    tblPersonas_E4C.on('pageLoaded', mostrarInfoTabla_E4C);

    function abrirModalSubirDJ_E4C(codigo, nombre) {
        document.getElementById('codPersonalDJ_E4C').value = codigo;
        document.querySelector('.nombre-personal_E4C').textContent = nombre ?? '';
        limpiarModal_E4C();
        document.getElementById('btn-modal-dj_E4C').click();
    }

    function limpiarModal_E4C() {
        const f = new Date();
        const hoy = `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
        const el = document.getElementById('fecha_emision_dj_E4C');
        if (el) {
            el.value = hoy;
            el.readOnly = true;
            el.classList.add('bg-gray-100', 'cursor-not-allowed');
        }
        if (archivoDJ_E4C) archivoDJ_E4C.value = '';
        if (listaArchivosDJ_E4C) listaArchivosDJ_E4C.innerHTML = '';
    }

    archivoDJ_E4C?.addEventListener('change', function () {
        const archivos = Array.from(this.files);
        if (!archivos.length) return;
        const maxSize = 1.2 * 1024 * 1024;

        for (const archivo of archivos) {
            if (archivo.type !== 'application/pdf') {
                Swal.fire({ title: 'Solo se permite PDF para el DJ', icon: 'warning' });
                this.value = ''; listaArchivosDJ_E4C.innerHTML = ''; return;
            }
            if (archivo.size > maxSize) {
                Swal.fire({ title: 'Archivo demasiado grande', text: `"${archivo.name}" pesa ${(archivo.size / 1024 / 1024).toFixed(2)} MB. Límite: 1 MB.`, icon: 'warning' });
                this.value = ''; listaArchivosDJ_E4C.innerHTML = ''; return;
            }
        }

        listaArchivosDJ_E4C.innerHTML = archivos.map(a => `
            <li class="flex items-center gap-2 text-sm text-gray-700">
                <i class="bx bxs-file-pdf text-red-500 text-lg"></i>
                <span>${a.name}</span>
                <span class="text-gray-400">(${(a.size / 1024).toFixed(1)} KB)</span>
            </li>
        `).join('');
    });

    document.getElementById('formSubirDJ_E4C')?.addEventListener('submit', function (e) {
        e.preventDefault();

        const fechaEmision = document.getElementById('fecha_emision_dj_E4C').value;
        const codPersonal = document.getElementById('codPersonalDJ_E4C').value;
        const archivo = archivoDJ_E4C?.files?.[0];
        const maxSize = 1.2 * 1024 * 1024;

        if (!fechaEmision) { Swal.fire({ title: 'Ingrese la fecha de emisión', icon: 'warning' }); return; }
        if (!archivo) { Swal.fire({ title: 'Seleccione un archivo PDF', icon: 'warning' }); return; }
        if (archivo.type !== 'application/pdf') { Swal.fire({ title: 'Solo se permite PDF', icon: 'warning' }); return; }
        if (archivo.size > maxSize) { Swal.fire({ title: 'El archivo supera 1 MB', icon: 'warning' }); return; }

        const btnGuardar = document.getElementById('btn-guardar-dj_E4C');
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = 'Guardando...';

        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
        formData.append('fecha_emision', fechaEmision);
        formData.append('codPersonal', codPersonal);
        formData.append('pdf', archivo);

        axios.post(`${VITE_URL_APP}/save-dj-folio-2`, formData, { headers: { 'Accept': 'application/json' } })
            .then(() => {
                document.getElementById('btn-modal-dj-close_E4C').click();
                limpiarModal_E4C();
                reloadTabla_E4C();
                Swal.fire({ title: 'DJ subida correctamente', icon: 'success', timer: 2000, showConfirmButton: false });
            })
            .catch(error => {
                const msg = error.response?.data?.error || error.response?.data?.message || 'Error al guardar el DJ';
                Swal.fire({ title: msg, icon: 'error' });
            })
            .finally(() => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="bx bx-upload text-lg me-1"></i> Subir DJ';
            });
    });

    document.getElementById('page-size-personas_E4C')?.addEventListener('change', function () {
        pageSizePersonas_E4C = parseInt(this.value);
        tblPersonas_E4C.setPageSize(pageSizePersonas_E4C);
        tblPersonas_E4C.setPage(1);
    });

    function recargarTodo_E4C() {
        reloadTabla_E4C();
        cargarIndicadores_E4C();
    }

    document.getElementById('buscarPersonal_E4C')?.addEventListener('keyup', function () {
        const valor = this.value.toLowerCase().trim();
        tblPersonas_E4C._ultimoFiltro = valor;
        reloadTabla_E4C();
    });
    document.getElementById('sucursal_E4C')?.addEventListener('change', recargarTodo_E4C);
    document.getElementById('tipo_per_E4C')?.addEventListener('change', recargarTodo_E4C);
    document.getElementById('filtroDJ_E4C')?.addEventListener('change', reloadTabla_E4C);
    document.querySelectorAll('input[name="vigencia_E4C"]').forEach(r => r.addEventListener('change', recargarTodo_E4C));

    function reloadTabla_E4C() {
        let codSucursal = document.getElementById('sucursal_E4C').value;
        if (!codSucursal || codSucursal === '— Seleccionar —' || codSucursal === '00') codSucursal = '0';

        const params = {
            codSucursal,
            search: document.getElementById('buscarPersonal_E4C').value.trim(),
            tipo_per: document.getElementById('tipo_per_E4C')?.value || 'TODOS',
            vigencia: document.querySelector('input[name="vigencia_E4C"]:checked')?.value || '',
            size: 99999,
            page: 1,
        };

        const filtroDJ = document.getElementById('filtroDJ_E4C')?.value || 'TODOS';
        if (filtroDJ === 'SI') params.tiene_folio_25 = '1';
        else if (filtroDJ === 'NO') params.tiene_folio_25 = '0';

        axios.get(`${VITE_URL_APP}/get-personal-total`, { params })
            .then(response => {
                const data = response.data.data || [];
                tblPersonas_E4C.setData(data);
                tblPersonas_E4C.setPage(1);
            });
    }

    function cargarIndicadores_E4C() {
        let codSucursal = document.getElementById('sucursal_E4C').value;
        if (!codSucursal || codSucursal === '— Seleccionar —' || codSucursal === '00') codSucursal = '0';

        const params = {
            codSucursal,
            tipo_per: document.getElementById('tipo_per_E4C')?.value || 'TODOS',
            vigencia: document.querySelector('input[name="vigencia_E4C"]:checked')?.value || '',
            size: 99999,
            page: 1,
        };

        axios.get(`${VITE_URL_APP}/get-personal-total`, { params })
            .then(response => {
                const data = response.data.data || [];
                const total = data.length;
                const escaneados = data.filter(d => d.tiene_folio_25 == 1).length;
                const pendientes = total - escaneados;

                document.getElementById('countTotalE4C').textContent = total;
                document.getElementById('countEscaneadosE4C').textContent = escaneados;
                document.getElementById('countPendientesE4C').textContent = pendientes;
            });
    }

    function seleccionarPrimeraSucursalValida_E4C() {
        const select = document.getElementById('sucursal_E4C');
        if (!select) return;
        const val = select.value;
        if (val && val !== '— Seleccionar —' && val !== '00') return;
        const opciones = [...select.options].filter(opt => opt.value && opt.value !== '— Seleccionar —' && !opt.disabled);
        if (opciones.length > 0) select.value = opciones[0].value;
    }

    // 🔥 LÓGICA DE EXPORTACIÓN DIRECTA PARA ETAPA 4 (CARGA DJ)
    document.getElementById('btnExportPdfE4C')?.addEventListener('click', async () => generarReporteE4C('pdf'));
    document.getElementById('btnExportExcelE4C')?.addEventListener('click', async () => generarReporteE4C('excel'));

    async function generarReporteE4C(formato) {
        const selectSucursal = document.getElementById('sucursal_E4C');
        let codSucursal = selectSucursal.value;
        if (!codSucursal || codSucursal === '— Seleccionar —' || codSucursal === '00') codSucursal = '00';
        const txtSucursal = selectSucursal.options[selectSucursal.selectedIndex]?.text || 'TODAS LAS SUCURSALES';

        const selectTipo = document.getElementById('tipo_per_E4C');
        let tipoFiltro = selectTipo?.value || 'TODOS';
        if (tipoFiltro === 'TODOS') tipoFiltro = '00';
        else if (tipoFiltro === 'OPER_5') tipoFiltro = '03';
        else if (tipoFiltro === 'ADMIN_5') tipoFiltro = '05';

        Swal.fire({ title: `Generando ${formato.toUpperCase()}...`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            let datos = await obtenerDatos(codSucursal, tipoFiltro);

            if (!datos || datos.length === 0) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros.' });
                return;
            }

            // Filtrar estado de DJ de la interfaz
            const filtroDJ = document.getElementById('filtroDJ_E4C')?.value || 'TODOS';
            if (filtroDJ === 'SI') datos = datos.filter(d => d.dj_subido == true);
            else if (filtroDJ === 'NO') datos = datos.filter(d => d.dj_subido == false);

            // Filtrar por texto del buscador
            const textoBusqueda = document.getElementById('buscarPersonal_E4C')?.value.toLowerCase().trim() || '';
            if (textoBusqueda) {
                datos = datos.filter(d => {
                    const str = `${d.nombres} ${d.doc}`.toLowerCase();
                    return str.includes(textoBusqueda);
                });
            }

            if (!datos.length) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros que coincidan con la búsqueda.' });
                return;
            }

            datos = datos.filter(d => {
                const tipoTrabajador = (d.tipo || d.TIPO || d.tipoPer || d.TIPO_PER || d.tipotrab2 || '').toUpperCase();
                return !tipoTrabajador.includes('ESPECIAL');
            });

            // 🔥 LÓGICA DE TÍTULOS DINÁMICOS ETAPA 4
            let baseTitle = "ETAPA N°4: REPORTE COMPLETO DE ESCANEOS DE DJ";
            let estadoArchivo = "Todos";

            if (filtroDJ === 'NO') {
                baseTitle = "ETAPA N°4: REPORTE DE PENDIENTES DE ESCANEO";
                estadoArchivo = "Sin_Escanear";
            } else if (filtroDJ === 'SI') {
                baseTitle = "ETAPA N°4: REPORTE DE ESCANEADOS";
                estadoArchivo = "Escaneados";
            }

            const textoTipo = selectTipo?.options[selectTipo.selectedIndex]?.text || 'Todos';
            const tipoFiltroTexto = textoTipo === 'Todos' ? '' : textoTipo;
            const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
            const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

            const meta = {
                sucursal: txtSucursal.toUpperCase() === '— SELECCIONAR —' || txtSucursal.toUpperCase() === 'TODAS LAS SUCURSALES' ? 'Todas' : txtSucursal,
                tipo: textoTipo,
                fecha: new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }),
                origen: 'etapa4', // 🔥 BANDERITA
                tituloReporte: tituloReporte,
                estadoArchivo: estadoArchivo,
                tipoArchivo: tipoArchivo
            };

            if (formato === 'pdf') await generarPDF(datos, meta);
            else if (formato === 'excel') await generarExcel(datos, meta);

            Swal.close();
        } catch (error) {
            console.error('[Error de Reporte E4C]', error);
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Error al generar el reporte.' });
        }
    }


    // ============================================================
    // 4° ETAPA: ESCANEO DJ (Paginación Local - Estilo Etapa 1, 2 y 3)
    // ============================================================
    const tblEtapa4 = new Tabulator("#tblPersonasEtapa4", {
        height: "550px",
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: true,
        paginationSize: 20,
        rowFormatter: function (row) {
            const d = row.getData();
            const dj = d.djSubido || d.djsubido || d.DJSUBIDO;
            const el = row.getElement();
            el.classList.toggle('rep-cesado', dj !== 'SI');
            el.style.removeProperty('background-color');
        },
        locale: "es",
        langs: { "es": { "pagination": { "first": "Primero", "prev": "Anterior", "next": "Siguiente", "last": "Último" } } },
        columns: [
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 60, 
                headerSort: false, 
                responsive: false 
            },
            {
                title: "Escaneo DJ",
                field: "djSubido",
                hozAlign: "center",
                widthGrow: 2,
                headerSort: false,
                formatter: cell => {
                    const valor = cell.getValue() || cell.getData().djsubido || cell.getData().DJSUBIDO;
                    const esSi = valor === 'SI';
                    const color = esSi ? 'text-green-700 bg-green-50 border-green-300' : 'text-red-600 bg-red-50 border-red-200';
                    const icono = esSi ? 'bxs-check-circle' : 'bx-time';
                    return `<span class="inline-flex items-center gap-1.5 rounded-full border ${color} px-3 py-0.5 text-xs font-semibold"><i class="bx ${icono}"></i> ${esSi ? 'SI' : 'NO'}</span>`;
                }
            },
            {
                title: "Fecha Subida",
                field: "ultima_actualizacion",
                hozAlign: "center",
                widthGrow: 2,
                headerSort: false,
                formatter: cell => {
                    const d = cell.getData();
                    const fecha = d.ultima_actualizacion || d.fechaAct || d.FECHA_ACT || d.fechaSubida || null;
                    if (fecha) {
                        const f = formatearFechaHora(fecha);
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700 whitespace-nowrap">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar text-blue-500'></i> <span>${f.fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five text-orange-500'></i> <span>${f.hora}</span></span>
                        </div>`.trim();
                    }
                    return '—';
                }
            },
            {
                title: "Apellidos", field: "apellidos", hozAlign: "left", widthGrow: 2,
                formatter: cell => {
                    const d = cell.getData();
                    if (d.APEL_1 || d.apellido1) return `${d.apellido1 ?? d.APEL_1 ?? ''} ${d.apellido2 ?? d.APEL_2 ?? ''}`.trim();
                    // Fallback si el SP solo trae el nombre concatenado
                    const partes = (d.PERSONAL || d.personal || '').split(' ');
                    return partes.length >= 3 ? `${partes[0]} ${partes[1]}` : (partes[0] || '');
                }
            },
            {
                title: "Nombres", field: "nombres", hozAlign: "left", widthGrow: 1.5,
                formatter: cell => {
                    const d = cell.getData();
                    if (d.NOMB_1 || d.nombres) return `${d.nombres ?? d.NOMB_1 ?? ''} ${d.NOMB_2 ?? ''}`.trim();
                    // Fallback si el SP solo trae el nombre concatenado
                    const partes = (d.PERSONAL || d.personal || '').split(' ');
                    return partes.length >= 3 ? partes.slice(2).join(' ') : (partes.slice(1).join(' ') || '');
                }
            },
            { title: "Nro Doc", field: "nroDoc", hozAlign: "center", width: 110, formatter: (cell) => cell.getValue() || cell.getData().NRODOC },
            { title: "Sucursal", field: "sucursal", hozAlign: "center", widthGrow: 1, formatter: (cell) => cell.getValue() || cell.getData().SUCURSAL },
            {
                title: "Tipo",
                field: "TIPOTRAB2",
                hozAlign: "center",
                widthGrow: 1.5,
                formatter: (cell) => {
                    let val = cell.getValue() || cell.getData().tipotrab2 || '';

                    // Reemplazamos la abreviatura por la palabra completa sin tocar la Base de Datos
                    val = val.replace('OPER', 'OPERATIVO').replace('ADMIN', 'ADMINISTRATIVO');

                    let color = 'bg-gray-100 border-gray-300 text-gray-800 shadow-sm';
                    
                    if (val.toUpperCase().includes('OPERATIVO')) { 
                        color = 'bg-blue-100 border-blue-400 text-blue-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ADMINISTRATIVO')) { 
                        color = 'bg-purple-100 border-purple-500 text-purple-800 shadow-sm'; 
                    }
                    else if (val.toUpperCase().includes('ESPECIAL')) { 
                        color = 'bg-orange-100 border-orange-500 text-orange-800 shadow-sm'; 
                    }

                    return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap" style="min-width: 125px;">${val}</span>` : '—';
                }
            },
            // 🔥 COLUMNA ACCIONES
            {
                title: "Acciones", field: "acciones", hozAlign: "center", widthGrow: 1, headerSort: false,
                formatter: function (cell) {
                    const d = cell.getData();
                    const dj = d.djSubido || d.djsubido || d.DJSUBIDO;

                    if (dj === 'SI') {
                        return `<button type="button" class="btn rounded-full bio-btn bg-info/25 text-info hover:bg-info hover:text-white" title="Validación Huella / Firma">
                        <i class="bx bx-fingerprint text-xl bio-btn"></i>
                    </button>`;
                    }
                    return ``;
                },
                cellClick: function (e, cell) {
                    // closest captura el click aunque se haga en el padding del botón o en el SVG
                    const btn = e.target.closest('.bio-btn');
                    if (btn) {
                        const d = cell.getData();
                        // Cubrimos cualquier forma en que SQL Server envíe la columna
                        const codPersonal = d.CODI_PERS || d.codPersonal || d.nroDoc || d.NRODOC || d.NRO_DOCU_IDEN || '';
                        const personal = d.personal || d.PERSONAL || '';

                        if (!codPersonal) {
                            console.error("No se encontró un código válido para el biométrico:", d);
                            return;
                        }

                        const event = new CustomEvent('solicitarBiometrico', {
                            detail: { codigo: codPersonal, persona: personal }
                        });
                        window.dispatchEvent(event);
                    }
                }
            }
        ],
    });
    
    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    // reformatNums(tblEtapa4);

    tblEtapa4.on("renderComplete", function () {
        if (this._ultimoFiltro) {
            resaltarTexto(this, this._ultimoFiltro);
        }

        // =========================================================
        // INYECCIÓN DE NUMERACIÓN ESTÁTICA
        // =========================================================
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

    // Función para traer datos una sola vez con Axios
    function cargarDatosEtapa4() {
        const codSucursal = document.getElementById('filtroSucursalE4')?.value || '00';

        Swal.fire({ title: 'Cargando datos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        axios.get(`${VITE_URL_APP}/api/reporte-etapa4-dj`, { params: { sucursal: codSucursal } })
            .then(response => {
                Swal.close();
                if (!response.data.success) return;
                const datos = response.data.data;

                // 🔥 NUEVO: Filtramos para EXCLUIR a los "ESPECIALES" antes de pintar la tabla
                const dataSinEspeciales = datos.filter(d => {
                    const tipo = (d.TIPOTRAB2 || d.tipotrab2 || '').toUpperCase();
                    return !tipo.includes('ESPECIAL');
                });

                tblEtapa4.setData(dataSinEspeciales); // Inyectamos la data limpia (Paginación local instantánea)
                aplicarFiltrosE4();
            })
            .catch(error => {
                Swal.close();
                console.error("Error etapa 4:", error);
                Swal.fire('Error', 'No se pudieron cargar los datos.', 'error');
            });
    }

    // Buscador y Filtros combinados en tiempo real (milisegundos)
    function aplicarFiltrosE4() {
        const texto = document.getElementById('buscarPersonalE4')?.value.toLowerCase().trim() || '';
        const codTipoPer = document.getElementById('filtroTipoPerE4')?.value || '00';
        const radioVal = document.getElementById('filtroEstadoE4')?.value || 'null';

        let tipoTxt = '';
        if (codTipoPer === '03') tipoTxt = 'OPER 5°';
        if (codTipoPer === '05') tipoTxt = 'ADMIN 5°';

        // Filtro visual en Tabulator
        tblEtapa4.setFilter(function (data) {
            let matchTipo = true;
            let matchTexto = true;
            let matchRadio = true;

            // 1. Evaluamos el select de Tipo
            if (tipoTxt) {
                const valTipo = data.TIPOTRAB2 || data.tipotrab2 || '';
                matchTipo = (valTipo === tipoTxt);
            }

            // 2. Evaluamos el buscador de texto
            if (texto) {
                const valPersonal = String(data.personal || data.PERSONAL || '').toLowerCase();
                const valDoc = String(data.nroDoc || data.NRODOC || data.NRO_DOCU_IDEN || '').toLowerCase();
                const valApe1 = String(data.apellido1 || data.APEL_1 || '').toLowerCase();
                const valApe2 = String(data.apellido2 || data.APEL_2 || '').toLowerCase();
                const valNom1 = String(data.nombres || data.NOMB_1 || '').toLowerCase();
                const valNom2 = String(data.NOMB_2 || '').toLowerCase();
                matchTexto = valPersonal.includes(texto) || valDoc.includes(texto)
                    || valApe1.includes(texto) || valApe2.includes(texto)
                    || valNom1.includes(texto) || valNom2.includes(texto);
            }

            // 3. Evaluamos los Radio Buttons
            if (radioVal !== 'null') {
                const dj = data.djSubido || data.djsubido || data.DJSUBIDO;
                const esEscaneado = (dj === 'SI');
                if (radioVal === '0') matchRadio = esEscaneado;
                if (radioVal === '1') matchRadio = !esEscaneado;
            }

            // Mostrar solo si cumple las condiciones
            return matchTipo && matchTexto && matchRadio;
        });
        tblEtapa4.setPage(1);

        // =========================================================================
        // 🔥 LÓGICA DE INDICADORES: Se calcula en base a la Sucursal (API) y Tipo
        // =========================================================================
        const todaLaData = tblEtapa4.getData();

        const dataParaTarjetas = todaLaData.filter(d => {
            const valTipo = d.TIPOTRAB2 || d.tipotrab2 || '';
            const cumpleTipo = (codTipoPer === '00') || (valTipo === tipoTxt);
            return cumpleTipo;
        });

        // Matemáticas para los indicadores
        const total = dataParaTarjetas.length;
        const escaneados = dataParaTarjetas.filter(d => {
            const dj = d.djSubido || d.djsubido || d.DJSUBIDO;
            return dj === 'SI';
        }).length;
        const pendientes = total - escaneados;

        // Mandar los números a la vista
        if (document.getElementById('contadorTotalE4')) document.getElementById('contadorTotalE4').textContent = total;
        if (document.getElementById('contadorEscaneadosE4')) document.getElementById('contadorEscaneadosE4').textContent = escaneados;
        if (document.getElementById('contadorPendientesE4')) document.getElementById('contadorPendientesE4').textContent = pendientes;
    }

    // Eventos
    document.getElementById('filtroSucursalE4')?.addEventListener('change', cargarDatosEtapa4);
    document.getElementById('filtroTipoPerE4')?.addEventListener('change', aplicarFiltrosE4);

    // Escuchando los radio buttons de estado para E4
    document.getElementById('filtroEstadoE4')?.addEventListener('change', aplicarFiltrosE4);

    document.getElementById('buscarPersonalE4')?.addEventListener('keyup', function () {
        const valor = this.value.toLowerCase().trim();
        tblEtapa4._ultimoFiltro = valor;
        aplicarFiltrosE4();
        setTimeout(() => resaltarTexto(tblEtapa4, valor), 10);
    });

    document.getElementById('page-size-etapa4')?.addEventListener('change', function () {
        tblEtapa4.setPageSize(parseInt(this.value));
    });

    // Cargar datos cada vez que se hace clic en la pestaña 4 (lo gestiona el tab handler)

    // ============================================================
    // MODAL REPORTE DE AVANCES (ETAPA 4) — USANDO LA API DEL COMPAÑERO
    // ============================================================
   document.getElementById('btnExportPdfE4')?.addEventListener('click', async () => generarReporteDirectoE4('pdf'));
    document.getElementById('btnExportExcelE4')?.addEventListener('click', async () => generarReporteDirectoE4('excel'));

    async function generarReporteDirectoE4(formato) {
        // Leemos directamente de los filtros de la interfaz
        const selectSucursal = document.getElementById('filtroSucursalE4');
        const codSucursal = selectSucursal?.value || '00';
        const txtSucursal = selectSucursal?.options[selectSucursal.selectedIndex]?.text || 'TODAS';

        const selectTipo = document.getElementById('filtroTipoPerE4');
        const tipoFiltro = selectTipo?.value || '00';

        Swal.fire({ title: `Generando ${formato.toUpperCase()}...`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            // 1. Obtenemos la data original con todas las columnas (huella, firma, etc)
            let datos = await obtenerDatos(codSucursal, tipoFiltro);

            if (!datos || datos.length === 0) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros.' });
                return;
            }

            // 2. Filtramos para matar a los "Especiales" y que cuadre a 396
            datos = datos.filter(d => {
                const tipoTrabajador = (d.tipo || d.TIPO || d.tipoPer || d.TIPO_PER || d.tipotrab2 || '').toUpperCase();
                return !tipoTrabajador.includes('ESPECIAL');
            });

            // 🔥 FILTRAMOS POR ESTADO (Select) Y TEXTO PARA QUE CUADRE CON LA VISTA
            const radioVal = document.getElementById('filtroEstadoE4')?.value || 'null';
            if (radioVal !== 'null') {
                datos = datos.filter(d => {
                    const dj = d.dj_subido || d.djSubido || d.djsubido || d.DJSUBIDO;
                    const esEscaneado = (dj === 'SI' || dj === true || dj === 1);
                    if (radioVal === '0') return esEscaneado;
                    if (radioVal === '1') return !esEscaneado;
                    return true;
                });
            }

            const textoBusqueda = document.getElementById('buscarPersonalE4')?.value.toLowerCase().trim() || '';
            if (textoBusqueda) {
                datos = datos.filter(d => {
                    const str = `${d.nombres || ''} ${d.doc || d.nroDoc || ''} ${d.sucursal || ''}`.toLowerCase();
                    return str.includes(textoBusqueda);
                });
            }

            if (!datos.length) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros que coincidan con la búsqueda.' });
                return;
            }

            // 3. Metadatos y Títulos Dinámicos
            const tipos = {
                '01': 'OPERATIVO 4º',
                '02': 'ADMINISTRATIVO 4º',
                '03': 'OPERATIVO 5º',
                '05': 'ADMINISTRATIVO 5º',
                '06': 'ESPECIAL'
            };

            const tipoTexto = tipos[tipoFiltro] || 'Todos';
            
            // Lógica de títulos Etapa 5
            let baseTitle = "ETAPA N°5: REPORTE COMPLETO DE VALIDACIÓN DE IMÁGENES";
            let estadoArchivo = "Todos";

            if (radioVal === '1') {
                baseTitle = "ETAPA N°5: REPORTE DE PENDIENTES DE VALIDACIÓN DE IMAGENES";
                estadoArchivo = "Sin_Validar";
            } else if (radioVal === '0') {
                baseTitle = "ETAPA N°5: REPORTE DE IMÁGENES VALIDADAS";
                estadoArchivo = "Validados";
            }

            const tipoFiltroTexto = tipoTexto === 'Todos' ? '' : tipoTexto;
            const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
            const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

            const meta = {
                sucursal: txtSucursal.toUpperCase() === 'TODAS' || txtSucursal.toUpperCase() === 'TODAS LAS SUCURSALES' ? 'Todas' : txtSucursal,
                tipo: tipoTexto,
                fecha: new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }),
                origen: 'etapa5', // 🔥 BANDERITA PARA ETAPA 5
                tituloReporte: tituloReporte,
                estadoArchivo: estadoArchivo,
                tipoArchivo: tipoArchivo
            };

            // 4. 🔥 LA CLAVE: AMBOS DEBEN RECIBIR LA VARIABLE "datos"
            if (formato === 'pdf') {
                await generarPDF(datos, meta);
            } else if (formato === 'excel') {
                await generarExcel(datos, meta);
            }

            Swal.close();

        } catch (error) {
            console.error('[Error de Reporte]', error);
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Error al generar el reporte.' });
        }
    }
    // ============================================================
    // FIN 1°, 2°, 3° Y 4° ETAPA / LÓGICA LEGACY (5° ETAPA) A CONTINUACIÓN
    // ============================================================

    document.getElementById('clase_brevete').addEventListener('change', actualizarCategorias);

    // ── Referencias DOM ──────────────────────────────────────
    const modalDjGestion = document.getElementById('modalDjGestion');
    const form = document.getElementById('formDatos');
    const buscarPersonalInput = document.getElementById("buscarPersonal");
    const btnNuevaDJ = document.getElementById('btnNuevaDJ');
    const cerrarModalBtn = document.getElementById('cerrarModal');
    const btnPrevisualizar = document.getElementById("btnPrevisualizar");
    const pageSizeSelect = document.getElementById("page-size");
    const pageSizeMigradoSelect = document.getElementById("page-size-migrado");

    // Pestañas
    const tabBtnPendiente = document.getElementById('tabBtnPendiente');
    const tabBtnMigrado = document.getElementById('tabBtnMigrado');
    const panelPendiente = document.getElementById('panelPendiente');
    const panelMigrado = document.getElementById('panelMigrado');

    // Familia
    const container = document.getElementById('familyContainer');
    const addBtn = document.getElementById('addFamilyMember');

    // Foto
    const inputFoto = document.getElementById("inputFoto");
    const preview = document.getElementById("previewFoto");
    const placeholder = document.getElementById("placeholderFoto");
    const btnSubir = document.getElementById("btnSubirFoto");
    const btnEliminar = document.getElementById("btnEliminarFoto");

    // Ubigeos
    const departamentoSelect = document.getElementById("departamento_actual");
    const provinciaSelect = document.getElementById("provincia_actual");
    const distritoSelect = document.getElementById("distrito_actual");

    const departamentoSelectDni = document.getElementById("departamento_dni");
    const provinciaSelectDni = document.getElementById("provincia_dni");
    const distritoSelectDni = document.getElementById("distrito_dni");

    const departamentoSelectNac = document.getElementById("departamento_nac");
    const provinciaSelectNac = document.getElementById("provincia_nac");
    const distritoSelectNac = document.getElementById("distrito_nac");

    // Campos validación PDF
    const nombreDJtxt = document.getElementById("nombres_apellidos");
    const dniDJtxt = document.getElementById("dni");

    // Tagify licencia
    const inputLicencia = document.getElementById("licencia_arma");
    // const tagifyLicencia = inputLicencia ? new Tagify(inputLicencia, { maxTags: 2 }) : null;

    const API_BASE = `${VITE_URL_APP}/api/ubicacion`;

    // ============================================================
    // TABLAS TABULATOR
    // ============================================================

    // ── Tabla 2: Migración ───────────────────────────────────
    const tblPersonasMigrado = new Tabulator("#tblPersonasMigrado", {
        height: "100%",
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: true,
        paginationSize: 20,
        rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
        locale: "es",
        langs: {
            "es": {
                pagination: { first: "Primero", first_title: "Primera Página", last: "Final", last_title: "Última Página", prev: "<", prev_title: "Página Anterior", next: ">", next_title: "Página Siguiente", all: "Todo" },
                headerFilters: { default: "Filtrar..." },
                ajax: { loading: "Cargando datos...", error: "Error al cargar datos" },
                data: { empty: "No hay datos disponibles" }
            }
        },
        columns: [
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 60, 
                headerSort: false, 
                responsive: false 
            },
            {
                title: "Apellidos", field: "apellidos", hozAlign: "left", widthGrow: 2,
                formatter: cell => { const d = cell.getData(); return `${d.apellido1 ?? ''} ${d.apellido2 ?? ''}`.trim(); }
            },
            {
                title: "Nombres", field: "nombres", hozAlign: "left", widthGrow: 2,
                formatter: cell => { const d = cell.getData(); return `${d.nombres ?? ''} `.trim(); }
            },
            { title: "DNI", field: "dni", hozAlign: "center", widthGrow: 2 },
            { title: "Sucursal", field: "sucursal", hozAlign: "center", widthGrow: 2 },
            {
                title: "Tipo", field: "tipoPer", hozAlign: "center", widthGrow: 2,
                formatter: cell => {
                    const val = cell.getValue() ?? '';
                    let color = 'border-gray-300 bg-gray-100 text-gray-800'; // Color por defecto

                    // Usamos .includes() para que agarre tanto 4° como 5°
                    if (val.toUpperCase().includes('OPERATIVO')) {
                        color = 'border-blue-300 bg-blue-100 text-blue-800';
                    } else if (val.toUpperCase().includes('ADMINISTRATIVO')) {
                        color = 'border-purple-300 bg-purple-100 text-purple-800';
                    } else if (val.toUpperCase().includes('ESPECIAL')) {
                        color = 'border-orange-300 bg-orange-100 text-orange-800';
                    }

                    return val ? `<span class="inline-flex items-center rounded-full border ${color} px-3 py-1 text-sm font-medium whitespace-nowrap">${capitalizeWords(val)}</span>` : '';
                }
            },
            {
                title: "Migrado", field: "migrado", hozAlign: "center", widthGrow: 2,
                formatter: cell => {
                    const d = cell.getData();
                    // Ahora la BD sí enviará correctamente 'Migrado' o 'Sin Migrar'
                    const estado = d.migrado || 'NO';

                    const color = estado === 'SI'
                        ? 'border-success bg-success text-white'
                        : 'border-dark-100 bg-dark-100 text-yellow-800';

                    return `<span class="inline-flex items-center rounded-full border ${color} px-3 py-1 text-sm font-medium whitespace-nowrap">${estado}</span>`;
                }
            },

            // 🔥 NUEVA COLUMNA: FECHA DE CREACIÓN 🔥
            {
                title: "Creación",
                field: "fechaCreacionDJSip",
                hozAlign: "center",
                widthGrow: 3,
                formatter: cell => {
                    const d = cell.getData();
                    if (d.fechaCreacionDJSip != null) {
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar-plus'></i> <span>${formatearFechaHora(d.fechaCreacionDJSip).fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five'></i> <span>${formatearFechaHora(d.fechaCreacionDJSip).hora}</span></span>
                        </div>`.trim();
                    }
                    return '—';
                }
            },
            {
                title: "Ultimo Cambio", field: "cambio", hozAlign: "center", widthGrow: 3,
                formatter: cell => {
                    const d = cell.getData();
                    if (d.cambio != null) {
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar'></i> <span>${formatearFechaHora(d.cambio).fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five'></i> <span>${formatearFechaHora(d.cambio).hora}</span></span>
                        </div>`.trim();
                    }
                    return `${d.cambio ?? 'Sin cambios'}`.trim();
                }
            },
            // ── NUEVA COLUMNA ──────────────────────────────────────
            {
                title: "PDF", field: "pdf_generado", hozAlign: "center", widthGrow: 1,
                headerSort: false,
                formatter: cell => {
                    const d = cell.getData();
                    const cod = d.codPersonal || d.CODI_PERS || d.id;
                    const gen = estaGenerado(cod, d.cambio);
                    const titulo = gen ? 'Generado — click para resetear' : 'Pendiente';
                    const color = gen
                        ? 'color:#16a34a;font-size:18px;cursor:pointer;'
                        : 'color:#d1d5db;font-size:18px;cursor:default;';
                    return `<span title="${titulo}" style="${color}" data-pdf-cod="${cod}" data-pdf-cambio="${d.cambio || ''}">
                                ${gen ? '✅' : '○'}
                            </span>`;
                },
                cellClick: (e, cell) => {
                    const span = e.target.closest('[data-pdf-cod]');
                    if (!span) return;
                    const cod = span.getAttribute('data-pdf-cod');
                    if (!estaGenerado(cod, span.getAttribute('data-pdf-cambio'))) return;

                    Swal.fire({
                        icon: 'question',
                        title: '¿Resetear marca?',
                        text: 'Se marcará este registro como pendiente de generar PDF.',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, resetear',
                        cancelButtonText: 'Cancelar',
                    }).then(r => {
                        if (!r.isConfirmed) return;
                        desmarcarDJGenerado(cod);
                        cell.getTable().updateOrAddData([{ ...cell.getData() }]);
                        // Forzar re-render de la fila
                        cell.getRow().reformat();
                    });
                }
            },
            // ── FIN NUEVA COLUMNA ──────────────────────────────────
            {
                title: "Acciones", field: "acciones", hozAlign: "center", headerSort: false, widthGrow: 2,
                formatter: cell => {
                    const d = cell.getData();
                    const estado = d.migrado ? String(d.migrado).toUpperCase() : 'NO';

                    // Bloqueamos el botón si el estado es 'SI'
                    const disabled = estado === 'SI' ? 'disabled' : '';

                    // Agregamos clases de opacidad para que visualmente se note que está bloqueado
                    const opacityClass = disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-success hover:text-white';

                    return `<button ${disabled} type="button" class="btn rounded-full form-btn-migrado bg-success/25 text-success ${opacityClass}" data-hs-overlay="#modalDjGestion">DJ</button>`;
                },
                cellClick: (e, cell) => {
                    const btn = e.target.closest('.form-btn-migrado');
                    if (!btn) return;
                    // Si el botón está deshabilitado, evitamos que haga clic
                    if (btn.hasAttribute('disabled')) return;

                    const rowData = cell.getRow().getData();
                    const codiPers = rowData.codPersonal || rowData.CODI_PERS || rowData.id;

                    personalDataCache.delete(`${codiPers}_pendiente`);
                    personalDataCache.delete(`${codiPers}_migracion`);

                    abrirFormularioDJ(codiPers, 'migracion');
                }
            },
        ],
    });
    
    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    // reformatNums(tblPersonasMigrado);

    tblPersonasMigrado.on("renderComplete", function () {
        if (this._ultimoFiltro) resaltarTexto(this, this._ultimoFiltro);

        // =========================================================
        // INYECCIÓN DE NUMERACIÓN ESTÁTICA
        // =========================================================
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

    // ============================================================
    // HELPERS INTERNOS
    // ============================================================
    function getValue(id) {
        const el = document.getElementById(id);
        return el ? (el.value || '') : '';
    }

    function formatearFechaHora(fechaStr) {
        const fecha = new Date(fechaStr);
        return {
            fecha: `${String(fecha.getDate()).padStart(2, '0')}/${String(fecha.getMonth() + 1).padStart(2, '0')}/${fecha.getFullYear()}`,
            hora: `${String(fecha.getHours()).padStart(2, '0')}:${String(fecha.getMinutes()).padStart(2, '0')}`
        };
    }

    function capitalizeWords(texto) {
        return texto.toLowerCase().split(" ").map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(" ");
    }

    function limpiarPreviewFoto() {
        if (inputFoto) inputFoto.value = "";
        if (preview) { preview.src = ""; preview.classList.add("hidden"); }
        if (placeholder) placeholder.classList.remove("hidden");
        if (btnEliminar) btnEliminar.classList.add("hidden");
    }

    function makeFamilyRow() {
        return `
        <div class="family-row grid grid-cols-1 md:grid-cols-3 gap-4 p-4 border rounded-lg relative" data-familia-row>
            <input type="hidden" name="familiar_id[]" value="">
            <div>
                <label class="text-sm font-medium inline-block mb-2">Parentesco</label>
                <select name="parentesco[]" class="form-select w-full">
                    ${opcionesVinculoHTML('', 'Seleccionar')}
                </select>
            </div>
            <div>
                <label class="text-sm font-medium inline-block mb-2">Apellidos y Nombres</label>
                <input type="text" name="apellidosNombres[]" class="form-input w-full" placeholder="Apellidos y nombres completos">
            </div>
            <div>
                <label class="text-sm font-medium inline-block mb-2">Fecha Nacimiento</label>
                <div class="flex gap-2 items-center">
                    <div class="family-date flex-1">
                        <input type="date" name="fechaNacimiento[]" class="form-input w-full">
                    </div>
                    <button type="button" class="remove-family px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 whitespace-nowrap">Eliminar</button>
                </div>
                <div class="flex gap-2 items-center flex-wrap mt-2">
                    <span class="family-age" style="display:none;font-size:11px;color:#2563eb;"></span>
                    <button type="button" class="btn-dni-hijo px-3 py-1 rounded" style="display:none;background:#eef2ff;color:#3730a3;">SUBIR DNI</button>
                    <span class="dni-hijo-estado" style="display:none;font-size:10px;"></span>
                </div>
            </div>
        </div>`;
        // return `
        // <div class="family-row grid grid-cols-1 md:grid-cols-3 gap-4 p-4 border rounded-lg relative" data-familia-row>
        //     <div>
        //         <label class="text-sm font-medium inline-block mb-2">Parentesco</label>
        //         <select name="parentesco[]" class="form-select w-full">
        //             <option value="">Seleccionar</option>
        //             <option value="PADRE">Padre</option>    <option value="MADRE">Madre</option>
        //             <option value="ESPOSO">Esposo</option>  <option value="ESPOSA">Esposa</option>
        //             <option value="HIJO">Hijo</option>      <option value="HIJA">Hija</option>
        //             <option value="HERMANO">Hermano</option><option value="HERMANA">Hermana</option>
        //             <option value="ABUELO">Abuelo</option>  <option value="ABUELA">Abuela</option>
        //         </select>
        //     </div>
        //     <div>
        //         <label class="text-sm font-medium inline-block mb-2">Apellidos y Nombres</label>
        //         <input type="text" name="apellidosNombres[]" class="form-input w-full" placeholder="Apellidos y nombres completos">
        //     </div>
        //     <div class="flex gap-2 items-end">
        //         <div class="flex-1">
        //             <label class="text-sm font-medium inline-block mb-2">Fecha Nacimiento</label>
        //             <input type="date" name="fechaNacimiento[]" class="form-input w-full">
        //         </div>
        //         <button type="button" class="remove-family self-end px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200">Eliminar</button>
        //     </div>
        // </div>`;
    }

    function limpiarFormulario() {
        if (form) form.reset();
        //if (tagifyLicencia) tagifyLicencia.removeAllTags();

        limpiarPreviewFoto();

        if (container) { container.innerHTML = ''; container.insertAdjacentHTML('beforeend', makeFamilyRow()); }
        if (institucionContainer) institucionContainer.classList.add("hidden");
        if (institucionInput) institucionInput.value = "";

        if (provinciaSelect) provinciaSelect.innerHTML = '<option value="">Seleccionar</option>';
        if (distritoSelect) distritoSelect.innerHTML = '<option value="">Seleccionar</option>';

        if (provinciaSelectDni) provinciaSelectDni.innerHTML = '<option value="">Seleccionar</option>';
        if (distritoSelectDni) distritoSelectDni.innerHTML = '<option value="">Seleccionar</option>';

        if (provinciaSelectNac) provinciaSelectNac.innerHTML = '<option value="">Seleccionar</option>';
        if (distritoSelectNac) distritoSelectNac.innerHTML = '<option value="">Seleccionar</option>';

        setValue('dj2026_laboral_1', '');
        setValue('dj2026_laboral_2', '');
        limpiarSplitView();

        document.querySelectorAll('[data-tipo]').forEach(el => { el.style.display = ''; });
        const badgeLimp = document.getElementById('tipoBadgeModal');
        if (badgeLimp) badgeLimp.textContent = '';
    }

    function resaltarTexto(tabla, valor) {
        if (!valor) return;
        tabla.getRows().forEach(row => {
            row.getElement().querySelectorAll(".tabulator-cell").forEach((cell, i, cells) => {
                const field = cell.getAttribute('tabulator-field');
                if (i === cells.length - 1 || field === 'migrado' || field === 'estado' || field === 'tipoPer' || field === 'cambio') return;
                const text = cell.textContent || '';
                if (text.toLowerCase().includes(valor)) {
                    const escaped = valor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    cell.innerHTML = text.replace(new RegExp(`(${escaped})`, "gi"), "<span style='background-color: #fef08a;'>$1</span>");
                }
            });
        });
    }

    // ============================================================
    // UBIGEOS
    // ============================================================
    async function cargarProvincias(selectProv, selectDist, departamentoId, selectedProvincia = null, selectedDistrito = null) {
        if (!selectProv || !selectDist) return;
        selectProv.innerHTML = '<option value="">Seleccionar</option>';
        selectDist.innerHTML = '<option value="">Seleccionar</option>';
        if (!departamentoId) return;
        try {
            const response = await axios.get(`${API_BASE}/provincias/${departamentoId}`);
            response.data.forEach(prov => selectProv.add(new Option(prov.provi_descripcion, prov.provi_codigo)));
            if (selectedProvincia) {
                selectProv.value = selectedProvincia;
                await cargarDistritos(selectDist, selectedProvincia, selectedDistrito);
            }
        } catch (error) { console.error("Error cargando provincias:", error); }
    }

    async function cargarDistritos(selectDist, provinciaId, selectedDistrito = null) {
        if (!selectDist) return;
        selectDist.innerHTML = '<option value="">Seleccionar</option>';
        if (!provinciaId) return;
        try {
            const response = await axios.get(`${API_BASE}/distritos/${provinciaId}`);
            response.data.forEach(dist => selectDist.add(new Option(dist.dist_descripcion, dist.dist_codigo)));
            if (selectedDistrito) selectDist.value = selectedDistrito;
        } catch (error) { console.error("Error cargando distritos:", error); }
    }

    if (departamentoSelect && departamentoSelectDni && departamentoSelectNac) {
        axios.get(`${API_BASE}/departamentos`)
            .then(response => {
                response.data.forEach(dep => {
                    departamentoSelect.add(new Option(dep.depa_descripcion, dep.depa_codigo));
                    departamentoSelectDni.add(new Option(dep.depa_descripcion, dep.depa_codigo));
                    departamentoSelectNac.add(new Option(dep.depa_descripcion, dep.depa_codigo));
                });
            })
            .catch(error => console.error("Error cargando departamentos:", error));
    }



    departamentoSelect?.addEventListener("change", async function () { await cargarProvincias(provinciaSelect, distritoSelect, this.value); });
    provinciaSelect?.addEventListener("change", async function () { await cargarDistritos(distritoSelect, this.value); });

    departamentoSelectDni?.addEventListener("change", async function () { await cargarProvincias(provinciaSelectDni, distritoSelectDni, this.value); });
    provinciaSelectDni?.addEventListener("change", async function () { await cargarDistritos(distritoSelectDni, this.value); });

    departamentoSelectNac?.addEventListener("change", async function () { await cargarProvincias(provinciaSelectNac, distritoSelectNac, this.value); });
    provinciaSelectNac?.addEventListener("change", async function () { await cargarDistritos(distritoSelectNac, this.value); });

    // ============================================================
    // CARGA DE DATOS (API)
    // ============================================================
    function getPersonalMigracion() {
        axios.get(`${VITE_URL_APP}/api/get-personal-dj-migracion`)
            .then(response => {
                const datosTabla = response.data;
                tblPersonasMigrado.setData(datosTabla);
                // const sucursales = [...new Map(datosTabla.filter(d => d.sucursal).map(d => [d.codSucursal, { cod: d.codSucursal, nombre: d.sucursal }])).values()];
                // const filtroSucursal = document.getElementById('filtroSucursal');
                // if (filtroSucursal) {
                //     filtroSucursal.innerHTML = '<option value="">Todas</option>';
                //     sucursales.sort((a, b) => a.nombre.localeCompare(b.nombre)).forEach(s => filtroSucursal.add(new Option(s.nombre, s.cod)));
                // }
                aplicarFiltrosMigracion();  // ← agrega esta línea al final
                actualizarCardDesdeSP();
            })
            .catch(error => console.error("Hubo un error:", error));
    }


    window.getPersonalSoloDJMigracion = function () {

        axios.get(`${VITE_URL_APP}/api/get-personal-dj-migracion`)
            .then(response => {
                const datosTabla = response.data;
                tblPersonasMigrado.setData(datosTabla);
                // const sucursales = [...new Map(datosTabla.filter(d => d.sucursal).map(d => [d.codSucursal, { cod: d.codSucursal, nombre: d.sucursal }])).values()];
                // const filtroSucursal = document.getElementById('filtroSucursal');
                // if (filtroSucursal) {
                //     filtroSucursal.innerHTML = '<option value="">Todas</option>';
                //     sucursales.sort((a, b) => a.nombre.localeCompare(b.nombre)).forEach(s => filtroSucursal.add(new Option(s.nombre, s.cod)));
                // }
                aplicarFiltrosMigracion();  // ← agrega esta línea al final
                actualizarCardDesdeSP();
            })
            .catch(error => console.error("Hubo un error:", error));
    }


    function actualizarCardDesdeSP(sucursal = '', tipoPer = '') {
        const codSucursal = sucursal || '00';
        let codTipoPer = '00';

        if (tipoPer === 'OPERATIVO 4°') codTipoPer = '01';
        else if (tipoPer === 'OPERATIVO 5°') codTipoPer = '03';
        else if (tipoPer === 'ADMINISTRATIVO 4°') codTipoPer = '02';
        else if (tipoPer === 'ADMINISTRATIVO 5°') codTipoPer = '05';
        else if (tipoPer === 'ESPECIAL') codTipoPer = '06';

        axios.get(`${VITE_URL_APP}/api/reporte-personal-sin-migracion`, { params: { codSucursal, codTipoPer } })
            .then(response => {
                if (!response.data.success) return;
                const datos = response.data.data;
                const listos = datos.filter(d => d.SIP_CAMBIO === 'Ok').length;
                const total = datos.length;
                const elFilt = document.getElementById('contadorFiltrado');
                const elTot = document.getElementById('contadorTotal');
                animarContador(elFilt, parseInt(elFilt?.textContent.replace(/,/g, '')) || 0, listos);
                animarContador(elTot, parseInt(elTot?.textContent.replace(/,/g, '')) || 0, total);
            })
            .catch(err => console.error('Error card SP:', err));
    }


    function matchBusqueda(data, texto) {
        const palabras = texto.toLowerCase().split(/\s+/).filter(p => p);
        const campos = [
            (data.nombres ?? '').toLowerCase(),
            (data.apellido1 ?? '').toLowerCase(),
            (data.apellido2 ?? '').toLowerCase(),
            (data.dni ?? '').toLowerCase(),
        ];
        return palabras.every(palabra => campos.some(campo => campo.includes(palabra)));
    }

    // ============================================================
    // FILTROS
    // ============================================================
    function aplicarFiltrosMigracion() {
        const sucursal = document.getElementById('filtroSucursal')?.value ?? '';
        const tipoPer = document.getElementById('filtroTipoPer')?.value ?? '';
        const filtros = [];

        if (sucursal) filtros.push({ field: "codSucursal", type: "=", value: sucursal });
        if (tipoPer) filtros.push({ field: "tipoPer", type: "=", value: tipoPer });

        const texto = buscarPersonalInput?.value.toLowerCase().trim() ?? '';
        if (texto) {
            // Se manda un array interno para que Tabulator lo interprete como "OR"
            filtros.push([
                { field: "nombres", type: "like", value: texto },
                { field: "apellido1", type: "like", value: texto },
                { field: "apellido2", type: "like", value: texto },
                { field: "dni", type: "like", value: texto }
            ]);
        }

        tblPersonasMigrado.setFilter(filtros);
        tblPersonasMigrado.setPage(1);
        actualizarCardDesdeSP(sucursal, tipoPer);
    }

    document.getElementById('filtroSucursal')?.addEventListener('change', aplicarFiltrosMigracion);
    document.getElementById('filtroTipoPer')?.addEventListener('change', aplicarFiltrosMigracion);
    document.getElementById('filtroMigrado')?.addEventListener('change', aplicarFiltrosMigracion); // ← Asegúrate de tener este

    getPersonalMigracion();

    // ============================================================
    // ESTADO ACTIVO (Migración)
    // ============================================================
    let tabActiva = 'migrado';

    // ============================================================
    // BÚSQUEDA Y RESALTADO
    // ============================================================
    buscarPersonalInput?.addEventListener("keyup", function () {
        const valor = this.value.toLowerCase().trim();
        tblPersonasMigrado._ultimoFiltro = valor;
        aplicarFiltrosMigracion();
        setTimeout(() => resaltarTexto(tblPersonasMigrado, valor), 10);
    });

    tblPersonasMigrado.on("renderComplete", () => { if (tblPersonasMigrado._ultimoFiltro) resaltarTexto(tblPersonasMigrado, tblPersonasMigrado._ultimoFiltro); });

    // ============================================================
    // BOTONES MODAL
    // ============================================================
    // btnNuevaDJ?.addEventListener('click', async function () {
    //     const { value: tipoCod, isConfirmed } = await Swal.fire({
    //         title: 'Nueva Declaración Jurada',
    //         text: 'Selecciona el tipo de personal:',
    //         input: 'radio',
    //         inputOptions: {
    //             '03': 'Operativo',
    //             '05': 'Administrativo',
    //         },
    //         inputValidator: (value) => !value && 'Debes seleccionar un tipo.',
    //         showCancelButton: true,
    //         confirmButtonText: 'Continuar',
    //         cancelButtonText: 'Cancelar',
    //     });

    //     if (!isConfirmed || !tipoCod) return;

    //     // Abrir modal con catálogos cargados
    //     await abrirFormularioDJ(null);

    //     // Setear tipo DESPUÉS de que el modal esté abierto
    //     setTimeout(() => {
    //         setValue('tipo_personal', tipoCod);
    //         aplicarVisibilidadPorTipo(tipoCod);
    //     }, 150);
    // });

    btnNuevaDJ?.addEventListener('click', function () {
        if (window.NuevaDJ) window.NuevaDJ.abrir();

    });


    cerrarModalBtn?.addEventListener('click', function () { registroSeleccionado = null; });

    // Familia
    addBtn?.addEventListener('click', e => { e.preventDefault(); if (container) container.insertAdjacentHTML('beforeend', makeFamilyRow()); });
    container?.addEventListener('click', e => {
        const btn = e.target.closest('button.remove-family');
        if (!btn) return;
        e.preventDefault(); e.stopPropagation();
        btn.closest('.family-row')?.remove();
    });
    container?.addEventListener('change', e => {
        if (e.target.matches('select[name="parentesco[]"]')) {
            actualizarFechaFamiliar(e.target.closest('.family-row'));
        }
    });
    document.querySelectorAll('#familyContainer .family-row').forEach(actualizarFechaFamiliar);

    // Foto
    btnSubir?.addEventListener("click", () => inputFoto?.click());
    inputFoto?.addEventListener("change", () => {
        const file = inputFoto.files?.[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                if (preview) { preview.src = e.target.result; preview.classList.remove("hidden"); }
                placeholder?.classList.add("hidden");
                btnEliminar?.classList.remove("hidden");
            };
            reader.readAsDataURL(file);
        }
    });
    btnEliminar?.addEventListener("click", () => limpiarPreviewFoto());

    // Page size
    pageSizeMigradoSelect?.addEventListener("change", function () { tblPersonasMigrado.setPageSize(parseInt(this.value)); });

    // ============================================================
    // PREVISUALIZAR PDF
    // ============================================================
    btnPrevisualizar?.addEventListener("click", async function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        const camposObligatorios = [{ input: nombreDJtxt, nombre: 'Nombre' }, { input: dniDJtxt, nombre: 'DNI' }];
        const campoFaltante = camposObligatorios.find(c => !c.input || !String(c.input.value ?? '').trim());
        if (campoFaltante) {
            Swal.fire({ icon: 'warning', title: 'Campos obligatorios',  text: `Falta completar: ${campoFaltante.nombre}` });
            campoFaltante.input?.focus();
            return;
        }
        try {
            await generarDeclaracionJuradaPDF();
        } catch (err) {
            console.error('[Previsualizar] Error generando PDF:', err);
        }
    });

    // ============================================================
    // TIPO DE TRABAJADOR
    // ============================================================
    const tipoTrabajadorUi = document.getElementById('tipo_personal_ui');

    (function cargarTiposPersonal() {
        axios.get(`${VITE_URL_APP}/api/dj/get-tipo-per/`)
            .then(r => {
                const items = Array.isArray(r.data) ? r.data : (r.data?.data ?? []);
                window.allTiposPersonalDj = items;
                if (tipoTrabajadorUi) {
                    items.forEach(t => {
                        const o = document.createElement('option');
                        o.value = t.codigo;
                        o.textContent = t.nombre;
                        tipoTrabajadorUi.appendChild(o);
                    });
                }
            })
            .catch(() => { });
    })();

    tipoTrabajadorUi?.addEventListener('change', function () {
        const hidden = document.getElementById('tipo_personal');
        if (hidden) hidden.value = this.value;
        aplicarVisibilidadPorTipo(this.value);
        aplicarSctr(this.value);
        // Refrescar CARGO según el nuevo tipo y limpiar la selección previa
        filtrarCargos(this.value);
        const cargoSel = document.getElementById('cargo_ui');
        if (cargoSel) cargoSel.value = '';
        const cargoHidden = document.getElementById('cargo');
        if (cargoHidden) cargoHidden.value = '';
    });

    // CARGO: se guarda por código (hidden "cargo"); solo Admins RRHH puede editarlo
    document.getElementById('cargo_ui')?.addEventListener('change', function () {
        const hidden = document.getElementById('cargo');
        if (hidden) hidden.value = this.value;
    });

    // ============================================================
    // GUARDAR FORMULARIO
    // ============================================================
    if (form) {
        console.log('✅ form encontrado, registrando listener submit');

        // ============================================================
        // SEGUIMIENTO DE CAMBIOS EN EL FORMULARIO DJ
        // ============================================================
        let estadoInicialForm = {};

        window.estadoInicialForm = {};

        window.capturarEstadoInicial = function() {
            const fd = new FormData(form);
            window.estadoInicialForm = {};
            for (let [key, value] of fd.entries()) {
                if (!window.estadoInicialForm[key]) {
                    window.estadoInicialForm[key] = value;
                } else {
                    if (!Array.isArray(window.estadoInicialForm[key])) {
                        window.estadoInicialForm[key] = [window.estadoInicialForm[key]];
                    }
                    window.estadoInicialForm[key].push(value);
                }
            }
        };

        function obtenerResumenCambios() {
            let cambios = [];

            // 🔥 ESTRATEGIA PRINCIPAL: Comparar contra la "DJ ANTERIOR" (_backupData)
            // Esto sincroniza el modal exactamente con tu alerta de "6 CAMPOS DIFERENTES"
            if (typeof _backupData !== 'undefined' && _backupData) {
                
                // Recalculamos las diferencias para asegurar que lean lo último que haya escrito el usuario
                marcarDiferencias(); 

                // Capturamos todos los campos que el sistema ya detectó como diferentes (.has-diff)
                const camposModificados = form.querySelectorAll('.has-diff');
                
                const nombresPersonalizados = {
                    'dj2026_laboral_1': 'OCUPACIÓN ALTERNA 1',
                    'dj2026_laboral_2': 'OCUPACIÓN ALTERNA 2'
                };

                camposModificados.forEach(input => {
                    const formId = input.id;
                    const bkField = CAMPO_MAP[formId]; // Mapeamos con tu diccionario existente
                    
                    if (bkField) {
                        let valForm = String(input.value ?? '').trim();
                        let valBk = String(_backupData[bkField] ?? '').trim();

                        if (FECHA_FIELDS_BK.includes(bkField) && valBk) {
                            valBk = valBk.replace('T', ' ').split(' ')[0];
                        }

                        // El Formulario: si es un combo (select), extraemos el texto legible que ve el usuario
                        let textoActual = valForm;
                        if (input.tagName.toLowerCase() === 'select') {
                            if (valForm) {
                                const opt = input.querySelector(`option[value="${valForm}"]`);
                                if (opt) textoActual = opt.textContent.trim();
                            }
                        }

                        // El Backup: la Base de Datos ya te devuelve el nombre legible para los selects mapeados
                        let textoInicial = valBk;
                        
                        // Obtener el nombre del Label visualmente
                        let labelText = nombresPersonalizados[formId] || formId;
                        if (!nombresPersonalizados[formId]) {
                            const label = input.parentElement?.querySelector('label') || input.closest('div')?.querySelector('label');
                            if (label && label.textContent) {
                                labelText = label.textContent.replace('*', '').trim();
                            } else {
                                labelText = formId.replace(/_/g, ' '); 
                            }
                        }

                        // Limpieza de formato
                        let vI = (textoInicial || '(Vacío)').toString().toUpperCase();
                        let vA = (textoActual || '(Vacío)').toString().toUpperCase();

                        // 🔥 Mejora: Formatear fechas YYYY-MM-DD a DD/MM/YYYY si aplica
                        const regexFecha = /^\d{4}-\d{2}-\d{2}$/;
                        if (regexFecha.test(vI) || regexFecha.test(vA)) {
                            const formatear = (d) => {
                                if (!regexFecha.test(d)) return d;
                                const [y, m, d_] = d.split('-');
                                return `${d_}/${m}/${y}`;
                            };
                            vI = formatear(vI);
                            vA = formatear(vA);
                        }
                        labelText = labelText.toUpperCase();

                        cambios.push(`<li class="mb-2 text-left" style="font-size:13px; border-bottom: 1px dashed #e5e7eb; padding-bottom: 4px;">
                            <b class="text-gray-800 block">${labelText}:</b> 
                            <span class="text-red-500 line-through mr-1">${vI}</span> 
                            <i class='bx bx-right-arrow-alt text-gray-400'></i> 
                            <span class="text-green-600 font-medium ml-1">${vA}</span>
                        </li>`);
                    }
                });
            } 
            // 🔥 PLAN B: Si es una DJ 100% NUEVA (no existe DJ Anterior)
            else {
                const fd = new FormData(form);
                const estadoActual = {};
                for (let [key, value] of fd.entries()) {
                    if (!estadoActual[key]) estadoActual[key] = value;
                    else {
                        if (!Array.isArray(estadoActual[key])) estadoActual[key] = [estadoActual[key]];
                        estadoActual[key].push(value);
                    }
                }

                // Protegido para funcionar con o sin la variable global
                const estadoIni = typeof window.estadoInicialForm !== 'undefined' ? window.estadoInicialForm : (typeof estadoInicialForm !== 'undefined' ? estadoInicialForm : {});
                const todasLasLlaves = new Set([...Object.keys(estadoIni), ...Object.keys(estadoActual)]);

                const ignorarCampos = [
                    'parentesco[]', 'apellidosNombres[]', 'fechaNacimiento[]', 
                    '_token', 'cod_postulante', 'source', 'codPeriodo', 
                    'nombres_apellidos', 'tipo_personal',
                    'sabe_nadar', 'ciudad_nacimiento'
                ];

                for (let key of todasLasLlaves) {
                    if (ignorarCampos.includes(key)) continue;

                    let valInicial = estadoIni[key] || '';
                    let valActual = estadoActual[key] || '';

                    if (JSON.stringify(valInicial) !== JSON.stringify(valActual)) {
                        let labelText = key;
                        const input = form.querySelector(`[name="${key}"]`);
                        let textoInicial = valInicial;
                        let textoActual = valActual;

                        if (input) {
                            const label = input.parentElement?.querySelector('label') || input.closest('div')?.querySelector('label');
                            if (label && label.textContent) labelText = label.textContent.replace('*', '').trim();
                            else labelText = key.replace(/_/g, ' '); 

                            if (input.tagName.toLowerCase() === 'select') {
                                if (valActual && !Array.isArray(valActual)) {
                                    const optActual = input.querySelector(`option[value="${valActual}"]`);
                                    if (optActual) textoActual = optActual.textContent.trim();
                                }
                                if (valInicial && !Array.isArray(valInicial)) {
                                    const optInicial = input.querySelector(`option[value="${valInicial}"]`);
                                    if (optInicial) textoInicial = optInicial.textContent.trim();
                                }
                            }
                        }

                        let vI = Array.isArray(textoInicial) ? textoInicial.join(', ') : textoInicial;
                        let vA = Array.isArray(textoActual) ? textoActual.join(', ') : textoActual;

                        vI = (vI || '(Vacío)').toString().toUpperCase();
                        vA = (vA || '(Vacío)').toString().toUpperCase();
                        labelText = labelText.toUpperCase();

                        cambios.push(`<li class="mb-2 text-left" style="font-size:13px; border-bottom: 1px dashed #e5e7eb; padding-bottom: 4px;">
                            <b class="text-gray-800 block">${labelText}:</b> 
                            <span class="text-red-500 line-through mr-1">${vI}</span> 
                            <i class='bx bx-right-arrow-alt text-gray-400'></i> 
                            <span class="text-green-600 font-medium ml-1">${vA}</span>
                        </li>`);
                    }
                }
            }
            return cambios;
        }

        form.addEventListener('submit', async (e) => {
            console.log('🔥 submit disparado');
            e.preventDefault();

            // === NUEVA LÓGICA DE CONFIRMACIÓN DE CAMBIOS ===
            const cambios = obtenerResumenCambios();
            let htmlCambios = '';

            if (cambios.length === 0) {
                htmlCambios = '<p class="text-sm text-gray-500 text-center py-2">No se han detectado cambios en el formulario.</p>';
            } else {
                htmlCambios = `<ul class="list-none p-0 m-0 max-h-60 overflow-y-auto">
                    ${cambios.join('')}
                </ul>`;
            }

            const confirmacion = await Swal.fire({
                title: 'Confirmar guardado',
                html: `
                    <div class="text-sm text-gray-700 text-left mb-3">Se guardarán los siguientes cambios en la DJ:</div>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        ${htmlCambios}
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Confirmar guardado',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981', // Verde
                cancelButtonColor: '#ef4444',  // Rojo
                customClass: {
                    popup: 'rounded-xl'
                }
            });

            if (!confirmacion.isConfirmed) {
                return; // Si cancela, detiene por completo la ejecución y no guarda
            }
            // === FIN LÓGICA DE CONFIRMACIÓN ===

            console.log('🔍 form element:', form);
            console.log('🔍 form action:', form.action);

            const btnGuardar = document.getElementById('btnGuardar');
            if (btnGuardar) btnGuardar.disabled = true;

            try {
                const formData = new FormData(form);

                // Verificar que formData tiene datos
                console.log('📋 FormData entries:');
                for (let [key, val] of formData.entries()) {
                    console.log(`  ${key}:`, val);
                }

                const data = Object.fromEntries(formData.entries());
                console.log('📦 data object:', data);

                const tabActiva = document.querySelector('.tab-btn.border-b-white')?.dataset?.tab ?? 'pendiente';

                const hijoSinFecha = [...document.querySelectorAll('#familyContainer .family-row')]
                    .find(fila => {
                        const parentesco = fila.querySelector('select[name="parentesco[]"]')?.value;
                        const fecha = fila.querySelector('input[name="fechaNacimiento[]"]')?.value;
                        return /^HIJ/i.test(parentesco) && !fecha;
                    });
                if (hijoSinFecha) {
                    Swal.fire({ icon: 'warning', title: 'Fecha obligatoria', text: 'Ingrese la fecha de nacimiento para el familiar Hijo(a).', confirmButtonText: 'Entendido' });
                    hijoSinFecha.querySelector('input[name="fechaNacimiento[]"]')?.focus();
                    if (btnGuardar) btnGuardar.disabled = false;
                    return;
                }

                const filasHijosDni = filasHijosDj();
                for (let i = 0; i < filasHijosDni.length; i++) {
                    if (!requiereDniHijoDj(filasHijosDni[i])) continue;
                    const stH = estadoDniHijo(filasHijosDni[i]);
                    const okA = !!(stH.anverso || stH.tieneAnverso);
                    const okR = !!(stH.reverso || stH.tieneReverso);
                    if (!okA || !okR) {
                        const falta = (!okA && !okR) ? 'anverso y reverso' : (!okA ? 'anverso' : 'reverso');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Falta el DNI del hijo ' + (i + 1),
                            text: 'Debe subir el ' + falta + ' del DNI para continuar.',
                            confirmButtonText: 'Entendido',
                        });
                        if (btnGuardar) btnGuardar.disabled = false;
                        return;
                    }
                }

                const payload = {
                    ...data,
                    cambios: cambios,
                    // Discrimina esta pantalla de Gestión DJ: el backend archiva la
                    // notificación SIP solo cuando el guardado viene de aquí.
                    origen: 'actualizar_dj',
                    source: tabActiva,
                    no_caduca_dni: data.no_caduca_dni === 'on' ? '1' : '0',
                    FAM_PARENTESCO: formData.getAll('parentesco[]'),
                    FAM_NOMBRES: formData.getAll('apellidosNombres[]'),
                    FAM_FECHA_NACI: formData.getAll('fechaNacimiento[]'),
                    FAM_ID: formData.getAll('familiar_id[]'),
                    FAM_PRESENTE: '1',
                    dj2026_descripcion: formData.getAll('ocupacion_alterna[]')
                };

                console.log('📤 Payload completo:', payload);
                console.log('🌐 URL:', `${VITE_URL_APP}/api/dj/save-dj-completo`);

                const esNuevaDJ = !payload.cod_postulante || String(payload.cod_postulante).trim() === '';

                const url = esNuevaDJ
                    ? `${VITE_URL_APP}/api/dj/save-nueva-dj`      // ← endpoint para nueva
                    : `${VITE_URL_APP}/api/dj/save-dj-completo`;  // ← endpoint para existente

                console.log(esNuevaDJ ? '🆕 Nueva DJ' : '✏️ DJ Existente', payload);


                // El DNI (anverso + reverso) viaja ADJUNTO en esta misma petición,
                // para que el servidor lo exija antes de crear/actualizar el personal.
                const dniAdjuntoAdj = { anverso: djDniFiles.anverso || null, reverso: djDniFiles.reverso || null };
                const fotoAdjuntaAdj = inputFoto?.files?.[0] || null;
                const formDataSave = new FormData();
                Object.keys(payload).forEach(k => {
                    const v = payload[k];
                    if (v === undefined || v === null) return;
                    if (Array.isArray(v)) v.forEach(item => formDataSave.append(`${k}[]`, item ?? ''));
                    else formDataSave.append(k, v);
                });
                if (fotoAdjuntaAdj)         formDataSave.append('foto', fotoAdjuntaAdj);
                if (dniAdjuntoAdj.anverso)  formDataSave.append('dni_anverso', dniAdjuntoAdj.anverso);
                if (dniAdjuntoAdj.reverso) formDataSave.append('dni_reverso', dniAdjuntoAdj.reverso);
                filasHijosDj().forEach((fila, i) => {
                    const st = estadoDniHijo(fila);
                    if (st.anverso) formDataSave.append(`dni_hijos_anverso[${i + 1}]`, st.anverso);
                    if (st.reverso) formDataSave.append(`dni_hijos_reverso[${i + 1}]`, st.reverso);
                });

                const csrfTokenSave = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await axios.post(url, formDataSave, {
                    headers: { 'X-CSRF-TOKEN': csrfTokenSave, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });

                // El DNI ya viajó con el guardado: se limpia la selección para no volver a subirlo
                if (dniAdjuntoAdj.anverso || dniAdjuntoAdj.reverso) limpiarDniDj();
                console.log('✅ Response:', response);

                if (response.status === 200 || response.status === 201) {
                    if (response.data?.asig_fami_desactivada) {
                        await Swal.fire({
                            icon: 'warning',
                            title: 'Asignación familiar desactivada',
                            text: 'Se desactivó la asignación familiar, porque el hijo ya cumplió la mayoría de edad',
                            confirmButtonText: 'Entendido',
                        });
                    }
                    // Subir foto y DNI si fueron seleccionados
                    const fotoFile = inputFoto?.files?.[0];
                    const codiPersFoto = response.data.codi_pers || data.cod_postulante || '';
                    const resDniDj0 = await subirDniDj(codiPersFoto);
                    const resDniHijos = await subirDniHijosDj(codiPersFoto);
                    const resDniDj = (!resDniDj0.sinDni && !resDniDj0.ok) ? resDniDj0
                        : ((!resDniHijos.sinDni && !resDniHijos.ok) ? resDniHijos : { ok: true, sinDni: true, pendiente: !!(response.data.dni_pendiente || response.data.foto_pendiente) });
                    if (fotoFile && codiPersFoto && !fotoAdjuntaAdj) {
                        try {
                            const fdFoto = new FormData();
                            fdFoto.append('foto', fotoFile);
                            fdFoto.append('codi_pers', codiPersFoto);
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                            const resFoto = await fetch(`${VITE_URL_APP}/api/dj/upload-foto-personal`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                                credentials: 'same-origin',
                                body: fdFoto
                            });
                            const jsonFoto = await resFoto.json();
                            if (!jsonFoto.success) {
                                console.warn('[ActualizarDJ] Foto no guardada:', jsonFoto.message);
                            }
                        } catch (e) {
                            console.warn('[ActualizarDJ] Error subiendo foto:', e);
                        }
                    }

                    mensajeGuardadoDj(resDniDj, 'La Declaración Jurada se guardó correctamente.');

                    const modal = document.getElementById('modalDjGestion');
                    if (modal) {
                        if (window.HSOverlay) { try { HSOverlay.close(modal); } catch (e) { } }
                        modal.classList.add('hidden');
                        modal.classList.remove('hs-overlay-open');
                        document.querySelectorAll('.hs-overlay-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('overflow-hidden');
                        document.body.style.overflow = '';
                    }

                    getPersonalMigracion();
                }
            } catch (error) {
                console.error('❌ Error completo:', error);
                console.error('❌ error.response:', error.response);
                console.error('❌ error.message:', error.message);

                let msg = 'Hubo un error al guardar los datos.';
                if (error.response?.data?.message) msg = error.response.data.message;
                else if (error.response?.data?.errors) msg = Object.values(error.response.data.errors).flat().join('<br>');

                const dataErrAdj = error.response?.data || {};
                const faltaDocsAdj = dataErrAdj.code === 'falta_dni' || dataErrAdj.code === 'falta_foto';
                if (faltaDocsAdj) {
                    if (dataErrAdj.falta_foto) {
                        const inpFotoAdj = document.getElementById('inputFoto');
                        if (inpFotoAdj) inpFotoAdj.classList.add('ring-2', 'ring-red-500');
                    }
                    if (dataErrAdj.falta_anverso || dataErrAdj.falta_reverso) {
                        ['inputDniAnverso', 'inputDniReverso'].forEach(id => document.getElementById(id)?.classList.add('ring-2', 'ring-red-500'));
                        const modalDniAdj = document.getElementById('modalDni');
                        if (modalDniAdj) modalDniAdj.style.display = 'flex';
                    }
                }

                Swal.fire({
                    icon: 'error',
                    title: faltaDocsAdj ? String(dataErrAdj.message || 'Falta ingresar foto').replace(/\.$/, '') : 'Error',
                    html: faltaDocsAdj ? 'Debe completar los documentos requeridos para poder guardar la DJ.' : msg,
                    zIndex: 99999,
                });
            } finally {
                if (btnGuardar) btnGuardar.disabled = false;
            }
        });
    }

    // ============================================================
    // DESCARGA MASIVA (ZIP)
    // ============================================================
    const btnDescargarDJs = document.getElementById('btnDescargarDJs');

    btnDescargarDJs?.addEventListener('click', async function () {
        const filasVisibles = tblPersonasMigrado.getData("active");
        if (!filasVisibles || filasVisibles.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No hay registros visibles para descargar.' });
            return;
        }

        const confirmacion = await Swal.fire({
            icon: 'question', title: 'Descarga masiva de DJ\'s',
            html: `Se generarán <b>${filasVisibles.length}</b> PDF(s) en un archivo ZIP.<br>¿Desea continuar?`,
            showCancelButton: true, confirmButtonText: 'Sí, descargar', cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;

        const zip = new JSZip();
        let generados = 0, errores = 0;

        Swal.fire({ title: 'Generando PDFs...', html: `Procesando <b>0</b> de <b>${filasVisibles.length}</b>`, allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });

        try { await cargarCatalogos(); } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los catálogos.' }); return; }

        for (let i = 0; i < filasVisibles.length; i++) {
            const fila = filasVisibles[i];
            const codiPers = fila.codPersonal || fila.CODI_PERS || fila.id;
            Swal.update({ html: `Procesando <b>${i + 1}</b> de <b>${filasVisibles.length}</b><br><small>${fila.nombres || codiPers}</small>` });
            try {
                await cargarDatosPersonales(codiPers);
                await new Promise(resolve => setTimeout(resolve, 600));
                const resultado = await generarDeclaracionJuradaPDF(true);
                if (resultado?.blob) { zip.file(resultado.filename, resultado.blob); generados++; } else errores++;
            } catch (err) { console.error(`Error generando PDF para ${codiPers}:`, err); errores++; }
        }

        if (generados === 0) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar ningún PDF.' }); return; }

        Swal.update({ html: 'Comprimiendo archivos...' });
        try {
            const contenidoZip = await zip.generateAsync({ type: 'blob' });
            const f = new Date();
            const ts = f.getFullYear() + String(f.getMonth() + 1).padStart(2, '0') + String(f.getDate()).padStart(2, '0') + '_' + String(f.getHours()).padStart(2, '0') + String(f.getMinutes()).padStart(2, '0');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(contenidoZip);
            link.download = `DJ_Masivo_${ts}.zip`;
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
            Swal.fire({ icon: 'success', title: 'Descarga completada', html: `Se generaron <b>${generados}</b> PDF(s) correctamente.` + (errores > 0 ? `<br><small class="text-red-500">${errores} con error.</small>` : '') });
        } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'Hubo un error al generar el archivo ZIP.' }); }
    });

    // ============================================================
    // DJ UNIFICADO (un solo PDF — Migración)
    // ============================================================
    const btnDJUnificado = document.getElementById('btnDJUnificado');
    const btnDJUnificadoMigrado = document.getElementById('btnDJUnificadoMigrado');

    btnDJUnificado?.addEventListener('click', async function () {
        const filasVisibles = tblPersonasMigrado.getData("active");
        if (!filasVisibles || filasVisibles.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No hay registros visibles para descargar.' });
            return;
        }

        const confirmacion = await Swal.fire({
            icon: 'question', title: 'DJ Unificado',
            html: `Se generará <b>1 PDF</b> con las <b>${filasVisibles.length}</b> declaraciones juradas.<br>¿Desea continuar?`,
            showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;

        await _generarUnificado(filasVisibles, 'DJ_Unificado');
    });


    btnDJUnificadoMigrado?.addEventListener('click', async function () {

        const todasMigradas = tblPersonasMigrado.getData("active");

        if (!todasMigradas.length) {
            Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No hay registros visibles en la tabla.' });
            return;
        }

        const pendientes = todasMigradas.filter(f => !estaGenerado(f.codPersonal || f.CODI_PERS || f.id, f.cambio));
        const yaGenerados = todasMigradas.filter(f => estaGenerado(f.codPersonal || f.CODI_PERS || f.id, f.cambio));

        const { value: opcion, isConfirmed } = await Swal.fire({
            title: 'DJ Unificado — Migrados',
            html: `
            <div style="display:flex;flex-direction:column;gap:10px;text-align:left;font-size:13px;padding:4px 0;">
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;" id="lbl-pend">
                    <input type="radio" name="djopcion" value="pendientes" ${pendientes.length ? '' : 'disabled'}
                        style="width:16px;height:16px;cursor:pointer;accent-color:#6366f1;">
                    <div>
                        <div style="font-weight:600;color:${pendientes.length ? '#111827' : '#9ca3af'};">
                            Solo pendientes
                            <span style="margin-left:6px;background:${pendientes.length ? '#dcfce7' : '#f3f4f6'};color:${pendientes.length ? '#16a34a' : '#9ca3af'};font-size:11px;padding:1px 8px;border-radius:20px;font-weight:700;">
                                ${pendientes.length}
                            </span>
                        </div>
                        <div style="font-size:11px;color:#6b7280;margin-top:2px;">
                            Registros sin ✅ o con cambios nuevos desde la última generación
                        </div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;" id="lbl-todos">
                    <input type="radio" name="djopcion" value="todos"
                        style="width:16px;height:16px;cursor:pointer;accent-color:#6366f1;">
                    <div>
                        <div style="font-weight:600;color:#111827;">
                            Todos los migrados
                            <span style="margin-left:6px;background:#dbeafe;color:#1e40af;font-size:11px;padding:1px 8px;border-radius:20px;font-weight:700;">
                                ${todasMigradas.length}
                            </span>
                        </div>
                        <div style="font-size:11px;color:#6b7280;margin-top:2px;">
                            Incluye los ${yaGenerados.length} ya generados anteriormente
                        </div>
                    </div>
                </label>
            </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Generar PDF',
            cancelButtonText: 'Cancelar',
            didOpen: () => {
                const radios = document.querySelectorAll('input[name="djopcion"]');
                radios.forEach(r => {
                    r.addEventListener('change', () => {
                        document.getElementById('lbl-pend').style.borderColor = r.value === 'pendientes' && r.checked ? '#6366f1' : '#e5e7eb';
                        document.getElementById('lbl-todos').style.borderColor = r.value === 'todos' && r.checked ? '#6366f1' : '#e5e7eb';
                    });
                });
                const def = pendientes.length ? 'pendientes' : 'todos';
                const defRadio = document.querySelector(`input[name="djopcion"][value="${def}"]`);
                if (defRadio) {
                    defRadio.checked = true;
                    document.getElementById(def === 'pendientes' ? 'lbl-pend' : 'lbl-todos').style.borderColor = '#6366f1';
                }
            },
            preConfirm: () => {
                const sel = document.querySelector('input[name="djopcion"]:checked');
                if (!sel) { Swal.showValidationMessage('Selecciona una opción.'); return false; }
                return sel.value;
            }
        });

        if (!isConfirmed) return;

        const filasSeleccionadas = opcion === 'pendientes' ? pendientes : todasMigradas;

        if (!filasSeleccionadas.length) {
            Swal.fire({ icon: 'info', title: 'Sin pendientes', text: 'Todos los registros ya fueron generados. Usa "Todos" para regenerar.' });
            return;
        }

        const confirmacion = await Swal.fire({
            icon: 'question',
            title: 'Confirmar generación',
            html: `Se generará <b>1 PDF</b> con <b>${filasSeleccionadas.length}</b> declaración(es).<br>¿Desea continuar?`,
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;

        // Se usa 'pendiente' como source para que el endpoint devuelva los datos más frescos
        const resultadoGen = await _generarUnificado(filasSeleccionadas, 'DJ_Unificado_Migrados', 'pendiente');

        if (resultadoGen?.ok && resultadoGen.generadosOk.length) {
            const marcados = resultadoGen.generadosOk.map(fila1 => {
                return {
                    codPersonal: fila1.codPersonal || fila1.CODI_PERS || fila1.id,
                    fechaCambio: fila1.cambio
                };
            }).filter(x => x.codPersonal);

            marcarDJGeneradosBatch(marcados);
        }

        tblPersonasMigrado.redraw(true);
    });

    // btnDJUnificadoMigrado?.addEventListener('click', async function () {

    //     // Todas las filas migradas visibles en la tabla
    //     const todasMigradas = tblPersonasMigrado.getData("active")
    //         .filter(fila => fila.migrado === 'Migrado');

    //     if (!todasMigradas.length) {
    //         Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No hay registros migrados visibles.' });
    //         return;
    //     }

    //     // Separar pendientes vs ya generados
    //     const pendientes   = todasMigradas.filter(f => !estaGenerado(f.codPersonal || f.CODI_PERS || f.id, f.cambio));
    //     const yaGenerados  = todasMigradas.filter(f =>  estaGenerado(f.codPersonal || f.CODI_PERS || f.id, f.cambio));

    //     // Elegir qué generar
    //     const { value: opcion, isConfirmed } = await Swal.fire({
    //         title: 'DJ Unificado — Migrados',
    //         html: `
    //             <div style="display:flex;flex-direction:column;gap:10px;text-align:left;font-size:13px;padding:4px 0;">
    //                 <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;" id="lbl-pend">
    //                     <input type="radio" name="djopcion" value="pendientes" ${pendientes.length ? '' : 'disabled'}
    //                         style="width:16px;height:16px;cursor:pointer;accent-color:#6366f1;">
    //                     <div>
    //                         <div style="font-weight:600;color:${pendientes.length ? '#111827' : '#9ca3af'};">
    //                             Solo pendientes
    //                             <span style="margin-left:6px;background:${pendientes.length ? '#dcfce7' : '#f3f4f6'};color:${pendientes.length ? '#16a34a' : '#9ca3af'};font-size:11px;padding:1px 8px;border-radius:20px;font-weight:700;">
    //                                 ${pendientes.length}
    //                             </span>
    //                         </div>
    //                         <div style="font-size:11px;color:#6b7280;margin-top:2px;">
    //                             Registros sin ✅ o con cambios nuevos desde la última generación
    //                         </div>
    //                     </div>
    //                 </label>
    //                 <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;" id="lbl-todos">
    //                     <input type="radio" name="djopcion" value="todos"
    //                         style="width:16px;height:16px;cursor:pointer;accent-color:#6366f1;">
    //                     <div>
    //                         <div style="font-weight:600;color:#111827;">
    //                             Todos los migrados
    //                             <span style="margin-left:6px;background:#dbeafe;color:#1e40af;font-size:11px;padding:1px 8px;border-radius:20px;font-weight:700;">
    //                                 ${todasMigradas.length}
    //                             </span>
    //                         </div>
    //                         <div style="font-size:11px;color:#6b7280;margin-top:2px;">
    //                             Incluye los ${yaGenerados.length} ya generados anteriormente
    //                         </div>
    //                     </div>
    //                 </label>
    //             </div>
    //         `,
    //         showCancelButton: true,
    //         confirmButtonText: 'Generar PDF',
    //         cancelButtonText: 'Cancelar',
    //         didOpen: () => {
    //             // Seleccionar por defecto "pendientes" si hay, sino "todos"
    //             const radios = document.querySelectorAll('input[name="djopcion"]');
    //             radios.forEach(r => {
    //                 r.addEventListener('change', () => {
    //                     document.getElementById('lbl-pend').style.borderColor  = r.value === 'pendientes' && r.checked ? '#6366f1' : '#e5e7eb';
    //                     document.getElementById('lbl-todos').style.borderColor = r.value === 'todos'      && r.checked ? '#6366f1' : '#e5e7eb';
    //                 });
    //             });
    //             const def = pendientes.length ? 'pendientes' : 'todos';
    //             const defRadio = document.querySelector(`input[name="djopcion"][value="${def}"]`);
    //             if (defRadio) {
    //                 defRadio.checked = true;
    //                 document.getElementById(def === 'pendientes' ? 'lbl-pend' : 'lbl-todos').style.borderColor = '#6366f1';
    //             }
    //         },
    //         preConfirm: () => {
    //             const sel = document.querySelector('input[name="djopcion"]:checked');
    //             if (!sel) { Swal.showValidationMessage('Selecciona una opción.'); return false; }
    //             return sel.value;
    //         }
    //     });

    //     if (!isConfirmed) return;

    //     const filasFinales = opcion === 'pendientes' ? pendientes : todasMigradas;

    //     if (!filasFinales.length) {
    //         Swal.fire({ icon: 'info', title: 'Sin pendientes', text: 'Todos los registros ya fueron generados. Usa "Todos" para regenerar.' });
    //         return;
    //     }

    //     const confirmacion = await Swal.fire({
    //         icon: 'question',
    //         title: 'Confirmar generación',
    //         html: `Se generará <b>1 PDF</b> con <b>${filasFinales.length}</b> declaración(es).<br>¿Desea continuar?`,
    //         showCancelButton: true,
    //         confirmButtonText: 'Sí, generar',
    //         cancelButtonText: 'Cancelar'
    //     });
    //     if (!confirmacion.isConfirmed) return;

    //     // Generar y marcar al terminar
    //     // await _generarUnificado(filasFinales, 'DJ_Unificado_Migrados');

    //     // // Marcar todos los incluidos como generados
    //     // filasFinales.forEach(fila => {
    //     //     const cod = fila.codPersonal || fila.CODI_PERS || fila.id;
    //     //     marcarDJGenerado(cod, fila.cambio);
    //     // });

    //     const resultadoGen = await _generarUnificado(filasFinales, 'DJ_Unificado_Migrados');

    //     // if (resultadoGen?.ok) {
    //     //     resultadoGen.generadosOk.forEach(fila => {
    //     //         const cod = fila.codPersonal || fila.CODI_PERS || fila.id;
    //     //         marcarDJGenerado(cod, fila.cambio);
    //     //     });
    //     // }
    //     if (resultadoGen?.ok && resultadoGen.generadosOk.length) {
    //         marcarDJGeneradosBatch(
    //             resultadoGen.generadosOk.map(fila => ({
    //                 codPersonal: fila.codPersonal || fila.CODI_PERS || fila.id,
    //                 fechaCambio: fila.cambio
    //             }))
    //         );
    //     }

    //     tblPersonasMigrado.redraw(true);

    //     // Refrescar columna PDF en la tabla
    //     tblPersonasMigrado.redraw(true);
    // });

    // ============================================================
    // DJ UNIFICADO — Pendientes
    // ============================================================
    const btnDJUnificado_PEN = document.getElementById('btnDJUnificado_PEN');

    btnDJUnificado_PEN?.addEventListener('click', async function () {
        const filasVisibles = tblPersonas.getData("active");
        if (!filasVisibles || filasVisibles.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No hay registros visibles para descargar.' });
            return;
        }

        const confirmacion = await Swal.fire({
            icon: 'question', title: 'DJ Unificado — Pendientes',
            html: `Se generará <b>1 PDF</b> con las <b>${filasVisibles.length}</b> declaraciones.<br>¿Desea continuar?`,
            showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'Cancelar'
        });
        if (!confirmacion.isConfirmed) return;

        await _generarUnificado(filasVisibles, 'DJ_Unificado_Pendientes', 'pendiente');
    });

    async function obtenerDatosPersonales(codiPers, source = 'migracion') {
        const response = await axios.get(`${API_URL}/dj/get-personal-data`, {
            params: { codi_pers: codiPers, source }
        });
        return response.data;
    }


    // Helper interno para unificar PDFs
    // async function _generarUnificado(filas, nombreBase, source = 'migracion') {
    //     const pdfBlobs = [];
    //     let errores = 0;

    //     Swal.fire({ 
    //         title: 'Generando DJ Unificado...', 
    //         html: `Procesando <b>0</b> de <b>${filas.length}</b>`, allowOutsideClick: false, showCloseButton: false, showConfirmButton: false,
    //         allowEscapeKey: false, didOpen: () => Swal.showLoading() });

    //     try { await cargarCatalogos(); } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los catálogos.' }); return; }

    //     for (let i = 0; i < filas.length; i++) {
    //         const fila = filas[i];
    //         const codiPers = fila.codPersonal || fila.CODI_PERS || fila.id;

    //         Swal.update({
    //             html: `Procesando <b>${i + 1}</b> de <b>${filas.length}</b><br><small>${fila.nombres || codiPers}</small>`
    //         });

    //         try {
    //             const payload = await obtenerDatosPersonales(codiPers, source);
    //             await llenarFormulario(payload.data);
    //             renderFamiliares(payload.familiares);

    //             const resultado = await generarDeclaracionJuradaPDF(true);
    //             if (resultado?.blob) {
    //                 pdfBlobs.push(await resultado.blob.arrayBuffer());
    //             } else {
    //                 errores++;
    //             }
    //         } catch (err) {
    //             console.error(`Error generando PDF para ${codiPers}:`, err);
    //             errores++;
    //         }
    //         await new Promise(resolve => setTimeout(resolve, 250));
    //     }

    //     if (pdfBlobs.length === 0) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar ningún PDF.' }); return; }

    //     Swal.update({ html: 'Unificando documentos...' });
    //     try {
    //         const { PDFDocument } = PDFLib;
    //         const mergedPdf = await PDFDocument.create();
    //         for (const buf of pdfBlobs) {
    //             const donor = await PDFDocument.load(buf);
    //             const pages = await mergedPdf.copyPages(donor, donor.getPageIndices());
    //             pages.forEach(p => mergedPdf.addPage(p));
    //         }
    //         const mergedBytes = await mergedPdf.save();
    //         const f  = new Date();
    //         const ts = f.getFullYear() + String(f.getMonth()+1).padStart(2,'0') + String(f.getDate()).padStart(2,'0') + '_' + String(f.getHours()).padStart(2,'0') + String(f.getMinutes()).padStart(2,'0');
    //         const blob = new Blob([mergedBytes], { type: 'application/pdf' });
    //         const link = document.createElement('a');
    //         link.href = URL.createObjectURL(blob);
    //         link.download = `${nombreBase}_${ts}.pdf`;
    //         document.body.appendChild(link); link.click(); document.body.removeChild(link);
    //         URL.revokeObjectURL(link.href);
    //         Swal.fire({ icon: 'success', title: 'DJ Unificado generado', html: `Se unificaron <b>${pdfBlobs.length}</b> declaraciones.` + (errores > 0 ? `<br><small class="text-red-500">${errores} con error.</small>` : '') });
    //     } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'Hubo un error al unificar los documentos.' }); }
    // }
    async function _generarUnificado(filas, nombreBase, source = 'migracion') {
        const pdfBlobs = [];
        const generadosOk = [];
        const generadosError = [];

        Swal.fire({
            title: 'Generando DJ Unificado...',
            html: `Procesando <b>0</b> de <b>${filas.length}</b>`,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            await cargarCatalogos();
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los catálogos.' });
            return { ok: false, generadosOk, generadosError };
        }

        for (let i = 0; i < filas.length; i++) {
            const fila = filas[i];
            const codiPers = fila.codPersonal || fila.CODI_PERS || fila.id;

            Swal.update({
                html: `Procesando <b>${i + 1}</b> de <b>${filas.length}</b><br><small>${fila.nombres || codiPers}</small>`
            });

            try {
                //const payload = await obtenerDatosPersonales(codiPers, source);
                const payload = await obtenerDatosConRetry(codiPers, source);
                await llenarFormulario(payload.data);
                renderFamiliares(payload.familiares);

                const resultado = await generarDeclaracionJuradaPDF(true);

                if (!resultado?.blob) {
                    generadosError.push({ fila, motivo: 'No se obtuvo blob' });
                    continue;
                }

                const buffer = await resultado.blob.arrayBuffer();

                if (!buffer || buffer.byteLength === 0) {
                    generadosError.push({ fila, motivo: 'PDF vacío' });
                    continue;
                }

                pdfBlobs.push(buffer);
                generadosOk.push(fila);

            } catch (err) {
                console.error(`Error generando PDF para ${codiPers}:`, err);
                generadosError.push({ fila, motivo: err?.message || 'Error desconocido' });
            }

            // await new Promise(resolve => setTimeout(resolve, 250));
            // await new Promise(r => setTimeout(r, PAUSA_ENTRE_REGISTROS));
            const pausa = filas.length > 30
                ? PAUSA_ENTRE_REGISTROS * 1.5
                : PAUSA_ENTRE_REGISTROS;
            await new Promise(r => setTimeout(r, pausa));
        }

        if (pdfBlobs.length === 0) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar ningún PDF.' });
            return { ok: false, generadosOk, generadosError };
        }

        Swal.update({ html: 'Unificando documentos...' });

        try {
            const { PDFDocument } = PDFLib;
            const mergedPdf = await PDFDocument.create();

            for (const buf of pdfBlobs) {
                const donor = await PDFDocument.load(buf);
                const pages = await mergedPdf.copyPages(donor, donor.getPageIndices());
                pages.forEach(p => mergedPdf.addPage(p));
            }

            const mergedBytes = await mergedPdf.save();

            if (!mergedBytes || mergedBytes.length === 0) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'El PDF unificado se generó vacío.' });
                return { ok: false, generadosOk: [], generadosError: filas.map(f => ({ fila: f, motivo: 'PDF unificado vacío' })) };
            }

            const f = new Date();
            const ts = f.getFullYear()
                + String(f.getMonth() + 1).padStart(2, '0')
                + String(f.getDate()).padStart(2, '0')
                + '_'
                + String(f.getHours()).padStart(2, '0')
                + String(f.getMinutes()).padStart(2, '0');

            const blob = new Blob([mergedBytes], { type: 'application/pdf' });

            if (blob.size === 0) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'El archivo final quedó vacío.' });
                return { ok: false, generadosOk: [], generadosError: filas.map(f => ({ fila: f, motivo: 'Blob final vacío' })) };
            }

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${nombreBase}_${ts}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);

            Swal.fire({
                icon: generadosError.length > 0 ? 'warning' : 'success',
                title: 'Resultado de generación',
                html: `
                    <div style="text-align:left; font-size:14px; line-height:1.6;">
                        <div>
                            <span style="font-weight:600;">Generados:</span>
                            <span style="color:#15803d; font-weight:700;">${generadosOk.length} / ${filas.length}</span>
                        </div>
                        <div>
                            <span style="font-weight:600;">Fallidos:</span>
                            <span style="color:#b91c1c; font-weight:700;">${generadosError.length}</span>
                        </div>

                        ${generadosError.length > 0
                        ? `
                                <div style="margin-top:10px; font-size:12px; color:#b91c1c;">
                                    <b>Detalle de fallas:</b><br>
                                    ${generadosError.map(x => {
                            const fila = x.fila || {};
                            const nombre = fila.nombres || fila.CODI_PERS || fila.id || 'Registro';
                            return `• ${nombre}: ${x.motivo}`;
                        }).join('<br>')}
                                </div>
                                `
                        : ''
                    }
                    </div>
                `
            });

            return { ok: true, generadosOk, generadosError };

        } catch (err) {
            console.error('Error unificando documentos:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Hubo un error al unificar los documentos.' });
            return { ok: false, generadosOk: [], generadosError: filas.map(f => ({ fila: f, motivo: 'Fallo en unificación' })) };
        }
    }

    // ============================================================
    // RESIZER SPLIT VIEW
    // ============================================================
    (function initResizer() {
        const wrapper = document.getElementById('djSplitWrapper');
        const resizer = document.getElementById('djResizer');
        const panelBk = document.getElementById('panelBackup');
        if (!wrapper || !resizer || !panelBk) return;

        let isResizing = false, startX = 0, startW = 0;

        resizer.addEventListener('mousedown', e => {
            isResizing = true; startX = e.clientX; startW = panelBk.offsetWidth;
            resizer.classList.add('dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });
        document.addEventListener('mousemove', e => {
            if (!isResizing) return;
            const newW = Math.max(220, Math.min(startW + (e.clientX - startX), wrapper.offsetWidth - 280));
            panelBk.style.width = newW + 'px'; panelBk.style.flexBasis = newW + 'px';
        });
        document.addEventListener('mouseup', () => {
            if (!isResizing) return;
            isResizing = false; resizer.classList.remove('dragging');
            document.body.style.cursor = ''; document.body.style.userSelect = '';
        });
        resizer.addEventListener('touchstart', e => {
            isResizing = true; startX = e.touches[0].clientX; startW = panelBk.offsetWidth;
            e.preventDefault();
        }, { passive: false });
        document.addEventListener('touchmove', e => {
            if (!isResizing) return;
            const newW = Math.max(220, Math.min(startW + (e.touches[0].clientX - startX), wrapper.offsetWidth - 280));
            panelBk.style.width = newW + 'px'; panelBk.style.flexBasis = newW + 'px';
        });
        document.addEventListener('touchend', () => { isResizing = false; });
        resizer.addEventListener('dblclick', () => { panelBk.style.width = '38%'; panelBk.style.flexBasis = '38%'; });
    })();

    // ============================================================
    // TIPO DE TRABAJADOR + CARGO + VERIFICACIÓN CONTRATO
    // ============================================================
    const cargoUi = document.getElementById('cargo_ui');

    // Cargar cargos al abrir modal
    async function cargarCargosDj() {
        try {
            const response = await axios.get(`${VITE_URL_APP}/api/dj/get-cargos-dj`);
            if (response.data?.success && response.data.data) {
                window.allCargosDj = response.data.data;
            }
        } catch (err) {
            console.error('[ActualizarDJ] Error cargando cargos:', err);
            window.allCargosDj = [];
        }
    }

    // Cargar cargos al cargar catálogos
    (async () => {
        await cargarCargosDj();
    })();

    // No Caduca checkbox: bloquear/desbloquear caduca
    const noCaducaDni = document.getElementById('no_caduca_dni');
    const caducaInput = document.getElementById('caduca');
    noCaducaDni?.addEventListener('change', function () {
        if (!caducaInput) return;
        if (this.checked) {
            caducaInput.disabled = true;
            caducaInput.value = '0000-00-00';
            caducaInput.style.background = '#f3f4f6';
            caducaInput.style.color = '#9ca3af';
        } else {
            caducaInput.disabled = false;
            caducaInput.value = '';
            caducaInput.style.background = '';
            caducaInput.style.color = '';
        }
    });

    // Caduca: limitar año a 4 dígitos
    caducaInput?.setAttribute('maxlength', '10');
    caducaInput?.addEventListener('input', function () {
        const anioExcedido = this.value.match(/^(\d{5,})(-\d{2}-\d{2})$/);
        if (anioExcedido) {
            this.value = `${anioExcedido[1].slice(0, 4)}${anioExcedido[2]}`;
        }
    });

    // Prestó S.M.O.: habilitar/deshabilitar Lugar de S.M.O.
    const prestoSmoEl = document.getElementById('presto_smo');
    const lugarSmoEl = document.getElementById('lugar_smo');
    if (lugarSmoEl && (!prestoSmoEl || prestoSmoEl.value !== 'SI')) {
        lugarSmoEl.disabled = true;
    }
    prestoSmoEl?.addEventListener('change', function () {
        if (!lugarSmoEl) return;
        if (this.value === 'SI') {
            lugarSmoEl.disabled = false;
        } else {
            lugarSmoEl.disabled = true;
            lugarSmoEl.value = '';
        }
    });

// ── Sistema visual: barras de las cards, indicadores de filas y limpiar búsqueda ──
(function () {
    // 1) Barras de proporción de las 3 tarjetas de contadores
    const gruposCards = [
        ['countTotalE1', 'countActualizadosE1', 'countSinActualizarE1', 'barTotalE1', 'barActualizadosE1', 'barSinActualizarE1'],
        ['contadorTotalE2', 'contadorFiltradoE2', 'contadorSinVerificarE2', 'barTotalE2', 'barVerificadosE2', 'barSinVerificarE2'],
        ['contadorTotalE3', 'contadorGeneradosE3', 'contadorPendientesE3', 'barTotalE3', 'barGeneradosE3', 'barPendientesE3'],
    ];
    const leerNum = id => {
        const el = document.getElementById(id);
        return el ? (parseInt(String(el.textContent).replace(/[^\d]/g, ''), 10) || 0) : 0;
    };
    const ponerBarra = (id, w) => { const el = document.getElementById(id); if (el) el.style.width = w; };
    const pintarBarras = () => {
        gruposCards.forEach(g => {
            const total = leerNum(g[0]);
            const pct = n => (total > 0 ? Math.max(n > 0 ? 4 : 0, Math.round((n / total) * 100)) : 0);
            ponerBarra(g[3], total > 0 ? '100%' : '0%');
            ponerBarra(g[4], pct(leerNum(g[1])) + '%');
            ponerBarra(g[5], pct(leerNum(g[2])) + '%');
        });
    };
    gruposCards.forEach(g => {
        [g[0], g[1], g[2]].forEach(id => {
            const el = document.getElementById(id);
            if (!el || typeof MutationObserver === 'undefined') return;
            new MutationObserver(pintarBarras).observe(el, { childList: true, characterData: true, subtree: true });
        });
    });
    pintarBarras();

    // 2) Indicador "Mostrando X–Y de Z" bajo cada tabla
    const mapaInfo = {
        '#tblEtapa1': 'tblInfoE1',
        '#tblPersonasVerificado': 'tblInfoE2',
        '#tblPersonasEtapa3': 'tblInfoE3',
        '#tblPersonas_E4C': 'tblInfoE4C',
        '#tblPersonasEtapa4': 'tblInfoE4',
        '#tblPersonasMigrado': 'tblInfoMIG',
    };
    Object.keys(mapaInfo).forEach(sel => {
        const info = document.getElementById(mapaInfo[sel]);
        if (!info || typeof Tabulator.findTable !== 'function') return;
        const instancias = Tabulator.findTable(sel);
        const tbl = instancias && instancias[0];
        if (!tbl) return;
        let timer = null;
        const pintarInfo = () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const activas = tbl.getRows('active').length;
                if (!activas) { info.innerHTML = '<b>0</b> filas'; return; }
                const pagina = tbl.getPage();
                if (pagina === 'all') { info.innerHTML = 'Mostrando <b>' + activas + '</b> filas'; return; }
                const size = tbl.getPageSize() || 20;
                const totalPag = Math.ceil(activas / size);
                const desde = ((pagina || 1) - 1) * size + 1;
                const hasta = Math.min((pagina || 1) * size, activas);
                info.innerHTML = 'Mostrando <b>' + desde + '–' + hasta + '</b> de <b>' + activas + '</b> filas' +
                    (totalPag > 1 ? ' · Página <b>' + pagina + '</b>/' + totalPag : '');
            }, 0);
        };
        ['dataLoaded', 'pageLoaded', 'dataFiltered', 'pageSizeChanged', 'renderComplete'].forEach(ev => {
            tbl.on(ev, pintarInfo);
        });
        pintarInfo();
    });

    // 3) Botones "Limpiar búsqueda" de las etapas
    [['btnLimpiarBusquedaE1', 'buscarPersonalE1'], ['btnLimpiarBusquedaE2', 'buscarPersonalE2'], ['btnLimpiarBusquedaE3', 'buscarPersonalE3']]
        .forEach(par => {
            const btn = document.getElementById(par[0]);
            const inp = document.getElementById(par[1]);
            if (!btn || !inp) return;
            const alternar = () => btn.classList.toggle('hidden', !inp.value);
            inp.addEventListener('input', alternar);
            btn.addEventListener('click', () => {
                inp.value = '';
                btn.classList.add('hidden');
                inp.dispatchEvent(new Event('input'));
                inp.focus();
            });
            alternar();
        });
})();

}); // fin DOMContentLoaded

// ============================================================
// FUNCIONES GLOBALES (fuera del DOMContentLoaded)
// ============================================================

// ── Filtrar cargos según tipo de personal ──────────────────
async function filtrarCargos(tipoPersonal) {
    const sel = document.getElementById('cargo_ui');
    if (!sel) return;
    // Solo el rol ADMINS RRHH (tipo 17) puede editar el cargo
    sel.disabled = String(window.tipoUsuario ?? '').trim() !== '17';
    // Autoguarantía: si el catálogo de cargos no llegó, cargarlo ahora
    if (!window.allCargosDj || !window.allCargosDj.length) await cargarCargosDj();
    const operativos = ['01', '03', '06'];
    const admin = ['02', '05'];
    const cargoTipo = operativos.includes(tipoPersonal) ? '01'
                    : admin.includes(tipoPersonal) ? '02'
                    : null;
    sel.innerHTML = '<option value="">— Seleccionar —</option>';
    if (!cargoTipo || !window.allCargosDj) return;
    window.allCargosDj
        .filter(c => String(c.tipo ?? '').trim() === cargoTipo)
        .forEach(c => {
            const o = document.createElement('option');
            o.value = c.codigo;
            o.textContent = c.nombre;
            sel.appendChild(o);
        });
}

// ── Foto pendiente ───────────────────────────────────────────
// Global (fuera del DOMContentLoaded) para poder llamarla desde
// abrirFormularioDJ()/llenarFormulario(). Sin esto, el archivo elegido en
// <input type="file" id="inputFoto"> se arrastraba de una persona a otra al
// cambiar de fila: al guardar, el servidor lo guardaba con el CODI de la
// persona nueva y quedaba registrada la foto de la persona anterior.
// (El DNI ya tenía su "pizarra limpia" en limpiarDniDj().)
function limpiarFotoPendienteDj() {
    const inp = document.getElementById('inputFoto');
    if (inp) inp.value = '';
    const prv = document.getElementById('previewFoto');
    if (prv) { prv.src = ''; prv.classList.add('hidden'); }
    const ph = document.getElementById('placeholderFoto');
    if (ph) ph.classList.remove('hidden');
    const btn = document.getElementById('btnEliminarFoto');
    if (btn) btn.classList.add('hidden');
}

// ── Abrir modal DJ ──────────────────────────────────────────
async function abrirFormularioDJ(codiPers = null, source = 'migracion') {
    try {
        const modal = document.getElementById('modalDjGestion');

        if (codiPers == null) {
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            // En lugar de limpiarFormulario() — reset manual con lo que sí es global
            limpiarSplitView();
            setValue('cod_postulante', '');
            setValue('tipo_personal', '');
            tipoPersonalEsEspecial = false;
            poblarSelectTiposPersonal(window.allTiposPersonalDj || []);
            setValue('#tipo_personal_ui', '');
            aplicarSctr(''); cargarFechasIngresoCese({}); aplicarExtranjeriaNacimiento(''); limpiarDniDj(); limpiarDniHijosDj();
            limpiarFotoPendienteDj();   // pizarra limpia: no arrastrar la FOTO de otra persona

            await cargarCatalogos();
            await cargarPaises();

            // Cargar departamentos para los 3 ubigeos
            const depts = await getUbicacionCached({ type: 'dept' });
            populateSelect('#departamento_actual', depts);
            populateSelect('#departamento_dni', depts);
            populateSelect('#departamento_nac', depts);

            Swal.close();

            if (modal) {
                if (window.HSOverlay) HSOverlay.open(modal);
                else modal.classList.remove('hidden');
            }

            setTimeout(() => {
                const tipo = document.getElementById('tipo_personal')?.value?.trim() ?? '';
                aplicarVisibilidadPorTipo(tipo);
                const _presto = document.getElementById('presto_smo');
                const _lugar = document.getElementById('lugar_smo');
                if (_lugar && (!_presto || _presto.value !== 'SI')) {
                    _lugar.disabled = true;
                }
            }, 80);

        } else {
            // ← DJ existente: flujo normal sin cambios
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            await cargarCatalogos(source);
            await cargarPaises();
            await cargarDatosPersonales(codiPers, source);

            Swal.close();

            if (modal) {
                if (window.HSOverlay) HSOverlay.open(modal);
                else modal.classList.remove('hidden');
            }

            setTimeout(() => {
                const _presto = document.getElementById('presto_smo');
                const _lugar = document.getElementById('lugar_smo');
                if (_lugar && (!_presto || _presto.value !== 'SI')) {
                    _lugar.disabled = true;
                }
            }, 80);

            if (source === 'migracion' || source === 'pendiente') await cargarDatosBackup(codiPers);
            else limpiarSplitView();
        }

    } catch (error) {
        console.error('Error:', error);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el formulario: ' + error.message });
    }
}

// ── Catálogos ────────────────────────────────────────────────
// ── DNI Anverso / Reverso: vista previa + subida al GUARDAR ─────────
const djDniFiles = { anverso: null, reverso: null };

function configDniInputsDj() {
    // Modal "Subir DNI" (abrir/cerrar)
    const modalDni = document.getElementById('modalDni');
    document.getElementById('btnSubirDni')?.addEventListener('click', () => { if (modalDni) modalDni.style.display = 'flex'; });
    document.getElementById('cerrarModalDni')?.addEventListener('click', () => { if (modalDni) modalDni.style.display = 'none'; });
    const pares = [
        ['btnDniAnverso', 'inputDniAnverso', 'prevDniAnverso', 'imgDniAnverso', 'clearDniAnverso', 'anverso'],
        ['btnDniReverso', 'inputDniReverso', 'prevDniReverso', 'imgDniReverso', 'clearDniReverso', 'reverso'],
    ];
    pares.forEach(([btnId, inpId, prevId, imgId, clearId, lado]) => {
        const btn = document.getElementById(btnId), inp = document.getElementById(inpId),
              prev = document.getElementById(prevId), img = document.getElementById(imgId),
              clear = document.getElementById(clearId);
        if (!btn || !inp) return;
        btn.addEventListener('click', () => inp.click());
        inp.addEventListener('change', () => {
            const f = inp.files?.[0];
            if (!f) return;
            djDniFiles[lado] = f;
            if (img)  img.src = URL.createObjectURL(f);
            if (prev) prev.style.display = 'block';
        });
        if (clear) clear.addEventListener('click', () => {
            djDniFiles[lado] = null;
            inp.value = '';
            if (prev) prev.style.display = 'none';
            if (img)  img.removeAttribute('src');
        });
    });
}
document.addEventListener('DOMContentLoaded', configDniInputsDj);

function limpiarDniDj() {
    djDniFiles.anverso = null;
    djDniFiles.reverso = null;
    ['inputDniAnverso', 'inputDniReverso'].forEach(id => { const i = document.getElementById(id); if (i) i.value = ''; });
    ['prevDniAnverso', 'prevDniReverso'].forEach(id => { const p = document.getElementById(id); if (p) p.style.display = 'none'; });
    const m = document.getElementById('modalDni');
    if (m) m.style.display = 'none';
}

async function subirDniDj(codiPers) {
    if (!codiPers) return { ok: true, sinDni: true };
    if (!djDniFiles.anverso && !djDniFiles.reverso) return { ok: true, sinDni: true };
    try {
        const fd = new FormData();
        fd.append('codi_pers', codiPers);
        if (djDniFiles.anverso) fd.append('dni_anverso', djDniFiles.anverso);
        if (djDniFiles.reverso) fd.append('dni_reverso', djDniFiles.reverso);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch(`${VITE_URL_APP}/api/dj/upload-dni-personal`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: fd,
        });
        const json = await res.json();
        // Limpiar selección tras subir: nunca arrastrar el DNI de una persona a otra
        if (json.success) limpiarDniDj();
        return { ok: !!json.success, message: json.message };
    } catch (err) {
        console.warn('[ActualizarDJ] Error subiendo DNI:', err);
        return { ok: false, message: 'Error de conexión al subir el DNI.' };
    }
}

function mensajeGuardadoDj(resDni, textoOk) {
    // La DJ se guardó pero el servidor de archivos no recibió el DNI
    if (resDni && resDni.pendiente) {
        Swal.fire({ icon: 'warning', title: 'Guardado, DNI pendiente', text: 'La Declaración Jurada se guardó correctamente, pero el DNI quedó pendiente de subir.' });
        return;
    }
    if (resDni && !resDni.sinDni && !resDni.ok) {
        Swal.fire({ icon: 'warning', title: 'DJ guardada, pero falló el DNI', text: resDni.message || 'No se pudo subir el DNI.' });
        return;
    }
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: textoOk });
}

let aj_paisesData = [];

async function cargarPaises() {
    const sel = document.getElementById('aj_pais');
    if (!sel) return;
    try {
        const res = await axios.get(`${API_URL}/dj/get-paises/`);
        const items = res.data.paises ?? [];
        aj_paisesData = items;
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = item.id;
            o.textContent = item.text;
            sel.appendChild(o);
        });
    } catch (err) {
        console.error('[ActualizarDJ] Error cargando países:', err);
    }
}

async function setAjPais(codigo) {
    const sel = document.getElementById('aj_pais');
    if (!sel) return;
    // Autoguarantía: si el catálogo de países no llegó, cargarlo ahora
    if (!aj_paisesData.length) await cargarPaises();
    sel.value = String(codigo ?? '').trim();
}

// ── Regla: Carnet de Extranjería (0035) → oculta Dep/Prov/Dist de Nacimiento ──
function aplicarExtranjeriaNacimiento(codTipoDoc) {
    const esExtranjeria = String(codTipoDoc ?? '').trim() === '0035';
    ['aj_wrap_departamento_nac', 'aj_wrap_provincia_nac', 'aj_wrap_distrito_nac'].forEach(id => {
        const w = document.getElementById(id);
        if (w) w.style.display = esExtranjeria ? 'none' : '';
    });
}

let catalogosCache = null;
let catalogosPromise = null;

async function cargarCatalogos(source = 'migracion') {
    if (catalogosCache) return catalogosCache;
    if (catalogosPromise) return catalogosPromise;

    catalogosPromise = axios.get(`${API_URL}/dj/get-catalogs`)
        .then(response => {
            const { grados, carreras, instituciones, sangre, estados_civiles, tipos_arma } = response.data;

            populateSelect('#selGrado', grados);
            populateSelect('#selCarrera', carreras);
            populateSelect('#selInstitucion', instituciones);
            populateSelect('#PERS_GRUP_SANGRE', sangre);
            populateSelect('#PERS_ESTADO_CIVIL', estados_civiles);
            populateSelect('#LAB_TIPO_ARMA', tipos_arma);
            // Poblar estado civil del modal original
            populateSelect('#estado_civil', response.data.estados_civiles ?? []);

            // Poblar sistema previsional del modal original
            populateSelect('#sistema_previsional', response.data.sistemas_previsionales ?? []);

            window.allCarreras = carreras;
            catalogosCache = response.data;
            return response.data;
        })
        .finally(() => {
            catalogosPromise = null;
        });

    return catalogosPromise;
}

// ── Datos personales ─────────────────────────────────────────
const personalDataCache = new Map();
const personalDataPromise = new Map();

async function cargarDatosPersonales(codiPers, source = 'migracion') {
    const key = `${codiPers}_${source}`;

    if (personalDataCache.has(key)) {
        const cached = personalDataCache.get(key);
        await llenarFormulario(cached.data);
        renderFamiliares(cached.familiares);
        return cached;
    }

    if (personalDataPromise.has(key)) {
        const pending = await personalDataPromise.get(key);
        await llenarFormulario(pending.data);
        renderFamiliares(pending.familiares);
        return pending;
    }

    const req = axios.get(`${API_URL}/dj/get-personal-data`, {
        params: { codi_pers: codiPers, source }
    }).then(response => {
        personalDataCache.set(key, response.data);
        return response.data;
    }).finally(() => {
        personalDataPromise.delete(key);
    });

    personalDataPromise.set(key, req);

    const result = await req;
    await llenarFormulario(result.data);
    renderFamiliares(result.familiares);
    return result;
}

// ── Llenar formulario ────────────────────────────────────────
async function llenarFormulario(data) {
    limpiarDniDj();             // pizarra limpia: no arrastrar DNI de otra persona
    limpiarFotoPendienteDj();   // pizarra limpia: no arrastrar la FOTO de otra persona
    setValue('cod_postulante', data.CODI_PERS);

    const tipotrab = data.PERS_TIPOTRAB ? String(data.PERS_TIPOTRAB).trim() : '';

    setValue('tipo_personal', tipotrab);
    aplicarReglaTipoPersonal(tipotrab);
    setValue('#tipo_personal_ui', tipotrab);
    aplicarVisibilidadPorTipo(tipotrab);
    aplicarSctr(tipotrab, data.SCRT ?? null);

    // Mostrar la sección Tipo de Personal / Cargo
    const cardTC = document.getElementById('cardTipoCargo');
    if (cardTC) cardTC.style.display = 'flex';

    setValue('#nombres_apellidos', `${data.NOMB_1 || ''} ${data.NOMB_2 || ''} ${data.APEL_1 || ''} ${data.APEL_2 || ''}`);
    setValue('#nombre1', data.NOMB_1 || '');
    setValue('#nombre2', data.NOMB_2 || '');
    setValue('#apellido_paterno', data.APEL_1 || '');
    setValue('#apellido_materno', data.APEL_2 || '');
    setValue('#dni', data.NRO_DOCU_IDEN ? data.NRO_DOCU_IDEN.trim() : '');
    setValue('#caduca', formatDateForInput(data.PERS_FECHCADUCADNI) ? formatDateForInput(data.PERS_FECHCADUCADNI) : '');

    // No Caduca checkbox
    const noCaduca = document.getElementById('no_caduca_dni');
    const caducaInput = document.getElementById('caduca');
    const noCaducaVal = data.NO_CADUCA_DNI;
    if (noCaduca) {
        noCaduca.checked = (noCaducaVal == 1 || noCaducaVal === '1' || noCaducaVal === true);
        if (noCaduca.checked && caducaInput) {
            caducaInput.disabled = true;
            caducaInput.value = '0000-00-00';
            caducaInput.style.background = '#f3f4f6';
            caducaInput.style.color = '#9ca3af';
        } else if (caducaInput) {
            caducaInput.disabled = false;
            caducaInput.style.background = '';
            caducaInput.style.color = '';
        }
    }

    setValue('#estado_civil', data.ESCI_CODIGO ? data.ESCI_CODIGO.trim() : '');
    setValue('#sexo', data.PERS_SEXO ? data.PERS_SEXO.trim() : data.SEXO ? data.SEXO.trim() : '');
    setValue('#fecha_nacimiento', formatDateForInput(data.FECH_NACI));
    cargarFechasIngresoCese(data);
    setValue('#sabe_nadar', data.PERS_SNADAR ? data.PERS_SNADAR.trim() : '');
    setValue('#ciudad_nacimiento', data.dj2026_ciudad_naci ? data.dj2026_ciudad_naci.trim() : '');
    setAjPais(data.NACIONALIDAD ? data.NACIONALIDAD.trim() : (data.dj2026_ciudad_naci ? data.dj2026_ciudad_naci.trim() : ''));
    aplicarExtranjeriaNacimiento(data.CODI_TIPO_DOCU ? String(data.CODI_TIPO_DOCU).trim() : '');

    // setValue('#departamento_nac',data.DEPA_CODIGO_NACI ? data.DEPA_CODIGO_NACI.trim() : '');
    // setValue('#provincia_nac',data.PROVI_CODIGO_NACI ? data.PROVI_CODIGO_NACI.trim() : '');
    // setValue('#distrito_nac',data.DIST_NACI ? data.DIST_NACI.trim() : '');

    setValue('#dj2026_laboral_1', data.dj2026_laboral_1 ? data.dj2026_laboral_1.trim() : '');
    setValue('#dj2026_laboral_2', data.dj2026_laboral_2 ? data.dj2026_laboral_2.trim() : '');

    setValue('#celular', data.PERS_TELEFONO ? data.PERS_TELEFONO.trim() : '');
    setValue('#correo', data.PERS_EMAIL ? data.PERS_EMAIL.trim() : '');
    setValue('#whatsapp', data.PERS_WHATSAPP ? data.PERS_WHATSAPP.trim() : '');

    setValue('#tipo_sangre', data.tipo_sangr ? data.tipo_sangr.trim() : '');
    setValue('#peso', data.peso_kilo ? data.peso_kilo.trim() : '');
    setValue('#talla', data.tall_metr ? data.tall_metr.trim() : '');

    setValue('#sistema_previsional', data.CODI_SIST_PENS ? data.CODI_SIST_PENS.trim() : '');
    setValue('#essalud', data.ESSALUD ? data.ESSALUD.trim() : '');
    setValue('#pensionista', data.PERS_PENSIONISTA ? data.PERS_PENSIONISTA.trim() : '');

    setValue('#grado_instruccion', data.PERS_GRADO_INSTRUCCION ? data.PERS_GRADO_INSTRUCCION.trim() : '');
    if (data.CARR_CODIGO == '999999') {
        setValue('#institucion', data.IEDU_CODIGO ? data.IEDU_CODIGO.trim() : '299999999');
        if (data.IEDU_CODIGO && window.allCarreras) {
            const selCarrera = document.getElementById('carrera');
            if (selCarrera) {
                selCarrera.innerHTML = '<option value="">—</option>';
                window.allCarreras
                    .filter(c => c.IEDU_CODIGO === data.IEDU_CODIGO.trim())
                    .forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.text;
                        selCarrera.appendChild(opt);
                    });
                selCarrera.value = data.CARR_CODIGO ? data.CARR_CODIGO.trim() : '';
            }
        }

    } else {
        setValue('#institucion', data.IEDU_CODIGO ? data.IEDU_CODIGO.trim() : '999999');
        if (data.IEDU_CODIGO && window.allCarreras) {
            const selCarrera = document.getElementById('carrera');
            if (selCarrera) {
                selCarrera.innerHTML = '<option value="">—</option>';
                window.allCarreras
                    .filter(c => c.IEDU_CODIGO === data.IEDU_CODIGO.trim())
                    .forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.text;
                        selCarrera.appendChild(opt);
                    });
                selCarrera.value = data.CARR_CODIGO ? data.CARR_CODIGO.trim() : '';
            }
        }
    }

    setValue('#carrera', data.CARR_CODIGO ? data.CARR_CODIGO.trim() : '999999');
    setValue('#anio_egreso', data.EGRESO_EDUCATIVO ? data.EGRESO_EDUCATIVO.trim() : '');

    setValue('#embargos', data.PERS_EMBARGO ? data.PERS_EMBARGO.trim() : '');
    setValue('#presto_smo', data.PERS_CONSMO ? data.PERS_CONSMO.trim() : '');
    setValue('#lugar_smo', data.PERS_LUGARSMO ? data.PERS_LUGARSMO.trim() : '');
    const prestoSmoEl = document.getElementById('presto_smo');
    const lugarSmoEl = document.getElementById('lugar_smo');
    if (prestoSmoEl && lugarSmoEl) {
        lugarSmoEl.disabled = prestoSmoEl.value !== 'SI';
        if (lugarSmoEl.disabled) lugarSmoEl.value = '';
    }
    setValue('#cuenta_banco', data.dj2026_banco ? data.dj2026_banco.trim() : '');
    setValue('#sucursal', data.SUCU_CODIGO ? data.SUCU_CODIGO.trim() : '');
    filtrarCargos(data.PERS_TIPOTRAB ? String(data.PERS_TIPOTRAB).trim() : '');
    setValue('#cargo_ui', data.CODI_CARG ? data.CODI_CARG.trim() : '');
    setValue('#cargo', data.CODI_CARG ? data.CODI_CARG.trim() : '');

    setValue('#direccion_actual', data.DIRECCION ? data.DIRECCION.trim() : '');
    setValue('#direccion_dni', data.PERS_DIREC_DNI ? data.PERS_DIREC_DNI.trim() : '');
    setValue('#tipo_zona_dni', data.TIZO_CODIGO ? data.TIZO_CODIGO.trim() : '');
    setValue('#zona_dirdni', data.PERS_ZONA_DIRDNI ? data.PERS_ZONA_DIRDNI.trim() : '');

    await cargarUbicaciones('actual', data.PERS_DEPT_ACT?.trim() ?? '', data.PERS_PROV_ACT?.trim() ?? '', data.PERS_DIST_ACT?.trim() ?? '');
    await cargarUbicaciones('dni', data.PERS_DPTO_DIRDNI?.trim() ?? '', data.PERS_PROV_DIRDNI?.trim() ?? '', data.PERS_DIST_DIRDNI?.trim() ?? '');
    await cargarUbicaciones('nac', data.DEPA_CODIGO_NACI?.trim() ?? '', data.PROVI_CODIGO_NACI?.trim() ?? '', data.DIST_NACI?.trim() ?? '');


    setValue('#ocupacion_principal', data.dj2026_ocupacion_principal);
    setValue('#experiencia_anios', data.dj2026_experiencia_anios ? String(data.dj2026_experiencia_anios).replace(/[^0-9]/g, '') : '');
    setValue('#experiencia_meses', data.dj2026_experiencia_meses !== null && data.dj2026_experiencia_meses !== undefined ? String(data.dj2026_experiencia_meses).replace(/[^0-9]/g, '') : '');
    setValue('#familiar_empresa', data.dj2026_familiar_empresa ? data.dj2026_familiar_empresa.trim() : '');
    setValue('#familiar_nombre', data.dj2026_familiar_nombre ? data.dj2026_familiar_nombre.trim() : '');
    setValue('#familiar_parentesco', data.dj2026_familiar_parentesco ? data.dj2026_familiar_parentesco.trim() : '');

    setValue('#curso_sucamec', data.PERS_CONDISCAMEC ? data.PERS_CONDISCAMEC.trim() : '');
    // setValue('#smo', data.PERS_SMO ? data.PERS_SMO.trim() : '');
    setValue('#licencia_arma', data.PERS_NROLICENCIA ? data.PERS_NROLICENCIA.trim() : '');
    setValue('#tipo_arma', data.PERS_TIPOARMA ? data.PERS_TIPOARMA.trim() : '');
    setValue('#arma_propia', data.PERS_CONARMAS ? data.PERS_CONARMAS.trim() : '');
    setValue('#brevete', data.PERS_BREVETE ? data.PERS_BREVETE.trim() : '');
    setValue('#clase_brevete', data.CLASE_BREVETE ? data.CLASE_BREVETE.trim() : '');
    actualizarCategorias();  // ← puebla el select tipo_vehiculo según la clase
    setValue('#tipo_vehiculo', data.CATEGORIA_BREVETE ?? data.PERS_TIPO_VEHICULO ?? '');
    setValue('#vehiculo_propio', data.PERS_VEHICULO_PROPIO ? data.PERS_VEHICULO_PROPIO.trim() : '');

    setValue('#empresa_anterior', data.PERS_CTRABANT ? data.PERS_CTRABANT.trim() : '');
    setValue('#cargo_anterior', data.PERS_CARGOTRABANT ? data.PERS_CARGOTRABANT.trim() : '');
    setValue('#duracion_anterior', data.PERS_DURACIONANT ? data.PERS_DURACIONANT.trim() : '');

    setValue('#contacto_emergencia', data.PERS_NOMCONTACTO ? data.PERS_NOMCONTACTO.trim() : '');
    setValue('#celular_emergencia', data.PERS_NROEMERGENCIA ? data.PERS_NROEMERGENCIA.trim() : '');
    setValue('#parentesco_emergencia', data.PERS_EMERC_FAMILIAR ? data.PERS_EMERC_FAMILIAR.trim() : '');

    if (data.FOTO_PATH) {
        const img = document.getElementById('previewFoto');
        const placeholderEl = document.getElementById('placeholderFoto');
        if (img) {
            img.src = data.FOTO_PATH + '?v=' + (Math.floor(Math.random() * 900) + 100);
            img.classList.remove('hidden');
            if (placeholderEl) placeholderEl.classList.add('hidden');
            document.getElementById('btnEliminarFoto')?.classList.remove('hidden');
        }
    }

    // Tomar fotografía de los datos iniciales luego de cargados
    setTimeout(() => {
        if (typeof window.capturarEstadoInicial === 'function') {
            window.capturarEstadoInicial();
        }
    }, 1500); // 🔥 Subido a 1.5 segundos para garantizar que Axios trajo todos los Distritos

} // <--- 🚀 AQUÍ: Agregamos esta llave para cerrar la función llenarFormulario()

// ── Familiares ───────────────────────────────────────────────
// ── DNI de hijos: {CODI}_H{N}.jpg → DNI1_HIJOS (anverso) / DNI2_HIJOS (reverso) ──
// N = par por hijo en orden de fila: hijo 1 → H1/H2, hijo 2 → H3/H4, ...
let djDniHijosRows = new WeakMap();
let djDniHijoActivo = null;

function codiPersDniHijos() {
    return (typeof codiPersActual !== 'undefined' && codiPersActual)
        ? String(codiPersActual).trim()
        : ((document.getElementById('cod_postulante')?.value) || '').trim();
}

function calcEdadHijo(fecha) {
    if (!fecha) return '';
    const nac = new Date(String(fecha).slice(0, 10) + 'T00:00:00');
    if (isNaN(nac)) return '';
    const hoy = new Date();
    let anios = hoy.getFullYear() - nac.getFullYear();
    let meses = hoy.getMonth() - nac.getMonth();
    if (hoy.getDate() < nac.getDate()) meses--;
    if (meses < 0) { anios--; meses += 12; }
    if (anios < 0) return '';
    return `Edad: ${anios} año${anios === 1 ? '' : 's'}` + (meses ? `, ${meses} mes${meses === 1 ? '' : 'es'}` : '');
}

function filasHijosDj() {
    return [...document.querySelectorAll('#familyContainer .family-row')]
        .filter(f => (f.querySelector('select[name="parentesco[]"]')?.value || '').startsWith('HIJO'));
}

function estadoDniHijo(fila) {
    let st = djDniHijosRows.get(fila);
    if (!st) { st = { anverso: null, reverso: null, tieneAnverso: false, tieneReverso: false }; djDniHijosRows.set(fila, st); }
    return st;
}

function requiereDniHijoDj(fila) {
    const parentesco = (fila?.querySelector('select[name="parentesco[]"]')?.value || '').trim().toUpperCase();
    const discapacitadoPermanente = parentesco.startsWith('HIJO MAYOR DE EDAD')
        && /INCAPACITADO|DISCAPACIDAD/.test(parentesco)
        && parentesco.includes('PERMANENT');
    return discapacitadoPermanente
        || (parentesco === 'HIJO MENOR DE EDAD' && esMenorDeEdadDj(fila.querySelector('input[name="fechaNacimiento[]"]')?.value));
}

function esMenorDeEdadDj(fecha) {
    if (!fecha) return false;
    const nac = new Date(String(fecha).slice(0, 10) + 'T00:00:00');
    if (isNaN(nac)) return false;
    const hoy = new Date();
    let anios = hoy.getFullYear() - nac.getFullYear();
    const m = hoy.getMonth() - nac.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) anios--;
    return anios < 18;
}

function actualizarEstadoDniHijo(fila) {
    const span = fila.querySelector('.dni-hijo-estado');
    if (!span) return;
    const parentesco = fila.querySelector('select[name="parentesco[]"]')?.value || '';
    if (!/^HIJ/i.test(parentesco)) { span.style.display = 'none'; return; }
    const st = estadoDniHijo(fila);
    const okA = !!(st.anverso || st.tieneAnverso);
    const okR = !!(st.reverso || st.tieneReverso);
    span.style.display = '';
    if (okA && okR) {
        span.textContent = '✓ DNI completo';
        span.style.color = '#16a34a';
        return;
    }
    const obligatorio = requiereDniHijoDj(fila);
    const detalle = (okA || okR) ? 'falta ' + (okA ? 'reverso' : 'anverso') : 'sin DNI';
    if (obligatorio) {
        span.textContent = '⚠ ' + detalle + ' (obligatorio)';
        span.style.color = '#dc2626';
    } else {
        span.textContent = '○ ' + detalle + ' (opcional)';
        span.style.color = '#9ca3af';
    }
}

function actualizarExtrasFilaHijo(fila) {
    if (!fila) return;
    const parentesco = fila.querySelector('select[name="parentesco[]"]')?.value || '';
    const esHijo = /^HIJ/i.test(parentesco);
    const inputFecha = fila.querySelector('input[name="fechaNacimiento[]"]');
    const edadSpan = fila.querySelector('.family-age');
    if (edadSpan) {
        const txt = esHijo ? calcEdadHijo(inputFecha?.value) : '';
        edadSpan.textContent = txt;
        edadSpan.style.display = txt ? '' : 'none';
    }
    const btnDni = fila.querySelector('.btn-dni-hijo');
    if (btnDni) btnDni.style.display = esHijo ? '' : 'none';
    actualizarEstadoDniHijo(fila);
}

function abrirModalDniHijosDj(fila) {
    if (!fila) return;
    djDniHijoActivo = fila;
    const modal = document.getElementById('modalDniHijos');
    const idx = filasHijosDj().indexOf(fila);
    const label = document.getElementById('dni_hijo_label');
    if (label) label.textContent = 'Hijo ' + (idx + 1);
    const st = estadoDniHijo(fila);
    const codi = codiPersDniHijos();

    const mostrar = (lado, prevId, imgId, inpId, carpeta) => {
        const prev = document.getElementById(prevId), img = document.getElementById(imgId), inp = document.getElementById(inpId);
        if (inp) inp.value = '';
        const archivo = lado === 'anverso' ? st.anverso : st.reverso;
        const tiene   = lado === 'anverso' ? st.tieneAnverso : st.tieneReverso;
        const nro     = idx * 2 + (lado === 'anverso' ? 1 : 2);
        if (archivo) {
            if (img) img.src = URL.createObjectURL(archivo);
            if (prev) prev.style.display = 'block';
        } else if (tiene && codi) {
            if (img) img.src = `http://190.116.178.163/Biblioteca_Grafica/${carpeta}/${codi}_H${nro}.jpg?v=${Date.now()}`;
            if (prev) prev.style.display = 'block';
        } else {
            if (prev) prev.style.display = 'none';
            if (img)  img.removeAttribute('src');
        }
    };
    mostrar('anverso', 'prevDniHijoAnverso', 'imgDniHijoAnverso', 'inputDniHijoAnverso', 'DNI1_HIJOS');
    mostrar('reverso', 'prevDniHijoReverso', 'imgDniHijoReverso', 'inputDniHijoReverso', 'DNI2_HIJOS');

    if (modal) modal.style.display = 'flex';
}

function configDniHijosDj() {
    const modal = document.getElementById('modalDniHijos');
    document.getElementById('cerrarModalDniHijos')?.addEventListener('click', () => { if (modal) modal.style.display = 'none'; });

    const pares = [
        ['btnDniHijoAnverso', 'inputDniHijoAnverso', 'prevDniHijoAnverso', 'imgDniHijoAnverso', 'clearDniHijoAnverso', 'anverso'],
        ['btnDniHijoReverso', 'inputDniHijoReverso', 'prevDniHijoReverso', 'imgDniHijoReverso', 'clearDniHijoReverso', 'reverso'],
    ];
    pares.forEach(([btnId, inpId, prevId, imgId, clearId, lado]) => {
        const btn = document.getElementById(btnId), inp = document.getElementById(inpId),
              prev = document.getElementById(prevId), img = document.getElementById(imgId),
              clear = document.getElementById(clearId);
        if (!btn || !inp) return;
        btn.addEventListener('click', () => inp.click());
        inp.addEventListener('change', () => {
            const f = inp.files?.[0];
            if (!f || !djDniHijoActivo) return;
            const st = estadoDniHijo(djDniHijoActivo);
            st[lado] = f;
            if (img)  img.src = URL.createObjectURL(f);
            if (prev) prev.style.display = 'block';
            actualizarEstadoDniHijo(djDniHijoActivo);
        });
        if (clear) clear.addEventListener('click', () => {
            if (!djDniHijoActivo) return;
            const st = estadoDniHijo(djDniHijoActivo);
            st[lado] = null;
            if (lado === 'anverso') st.tieneAnverso = false; else st.tieneReverso = false;
            inp.value = '';
            if (prev) prev.style.display = 'none';
            if (img)  img.removeAttribute('src');
            actualizarEstadoDniHijo(djDniHijoActivo);
        });
    });

    // Delegación: botón DNI por fila + edad en vivo
    const cont = document.getElementById('familyContainer');
    if (cont) {
        cont.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-dni-hijo');
            if (btn) abrirModalDniHijosDj(btn.closest('.family-row'));
        });
        cont.addEventListener('change', (e) => {
            if (e.target.matches('input[name="fechaNacimiento[]"], select[name="parentesco[]"]')) {
                actualizarExtrasFilaHijo(e.target.closest('.family-row'));
            }
        });
    }
}
document.addEventListener('DOMContentLoaded', configDniHijosDj);

function limpiarDniHijosDj() {
    djDniHijosRows = new WeakMap();
    djDniHijoActivo = null;
    const m = document.getElementById('modalDniHijos'); if (m) m.style.display = 'none';
    const l = document.getElementById('dni_hijo_label'); if (l) l.textContent = '';
}

async function consultarDniHijosDj(codiPers) {
    if (!codiPers) return;
    const filasH = filasHijosDj();
    if (!filasH.length) return;
    const nombres = [];
    filasH.forEach((fila, i) => {
        nombres.push(`${codiPers}_H${i * 2 + 1}.jpg`);
        nombres.push(`${codiPers}_H${i * 2 + 2}.jpg`);
    });
    try {
        const qs = nombres.map(n => 'nombres[]=' + encodeURIComponent(n)).join('&');
        const resp = await axios.get(`${VITE_URL_APP}/api/dj/get-dni-hijos?${qs}`);
        const map = resp.data?.data || {};
        filasH.forEach((fila, i) => {
            const st = estadoDniHijo(fila);
            st.tieneAnverso = !!map[`${codiPers}_H${i * 2 + 1}.jpg`];
            st.tieneReverso = !!map[`${codiPers}_H${i * 2 + 2}.jpg`];
            actualizarEstadoDniHijo(fila);
        });
    } catch (e) {
        console.warn('[ActualizarDJ] Error consultando DNI de hijos:', e);
    }
}

async function subirDniHijosDj(codiPers) {
    if (!codiPers) return { ok: true, sinDni: true };
    const filasH = filasHijosDj();
    const fd = new FormData();
    let n = 0;
    fd.append('codi_pers', codiPers);
    filasH.forEach((fila, i) => {
        const st = estadoDniHijo(fila);
        if (st.anverso) { fd.append('archivos[]', st.anverso); fd.append('metas[]', `DNI1_HIJOS|${codiPers}_H${i * 2 + 1}.jpg`); n++; }
        if (st.reverso) { fd.append('archivos[]', st.reverso); fd.append('metas[]', `DNI2_HIJOS|${codiPers}_H${i * 2 + 2}.jpg`); n++; }
    });
    if (!n) return { ok: true, sinDni: true };
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch(`${VITE_URL_APP}/api/dj/upload-dni-hijos`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: fd,
        });
        const json = await res.json();
        if (json.success) {
            filasH.forEach(fila => {
                const st = estadoDniHijo(fila);
                if (st.anverso) { st.anverso = null; st.tieneAnverso = true; }
                if (st.reverso) { st.reverso = null; st.tieneReverso = true; }
                actualizarEstadoDniHijo(fila);
            });
        }
        return { ok: !!json.success, message: json.message };
    } catch (err) {
        console.warn('[ActualizarDJ] Error subiendo DNI de hijos:', err);
        return { ok: false, message: 'Error de conexión al subir el DNI de hijos.' };
    }
}

function actualizarFechaFamiliar(fila) {
    const parentesco = fila.querySelector('select[name="parentesco[]"]')?.value;
    const contenedorFecha = fila.querySelector('.family-date');
    const inputFecha = fila.querySelector('input[name="fechaNacimiento[]"]');
    const esHijo = /^HIJ/i.test(parentesco);

    if (contenedorFecha) contenedorFecha.style.display = esHijo ? '' : 'none';
    if (inputFecha) {
        inputFecha.required = esHijo;
        if (!esHijo) inputFecha.value = '';
    }
    // Edad del hijo + estado del DNI (obligatorio para hijos)
    actualizarExtrasFilaHijo(fila);
}

function renderFamiliares(familiares) {
    const container = document.getElementById('familyContainer');
    if (!container) return;
    container.innerHTML = '';

    const allFam = [
        ...(familiares.padres || []),
        ...(familiares.madre || []),
        ...(familiares.hijos || []),
        ...(familiares.conyugue || []),
        ...(familiares.otros || [])
    ];

    if (allFam.length === 0) addFamiliarRow({}, container);
    else allFam.forEach(f => addFamiliarRow(f, container));
    // Consultar qué DNI de hijos ya existen (para no volverlos a pedir)
    consultarDniHijosDj(codiPersDniHijos());
}

// ── Opciones del select de Parentesco (catálogo TIPO_VINCULO_FAMILIAR) ──
// Solo catálogo para filas nuevas; si se carga un dato legado fuera del
// catálogo se añade como opción seleccionada para que se muestre como debe.
function opcionesVinculoHTML(selected = '', emptyLabel = '—') {
    const cats = (window.TIPOS_VINCULO || []).map(v => String(v).trim()).filter(Boolean);
    const sel  = String(selected || '').trim();
    let html = `<option value=""${sel ? '' : ' selected'}>${emptyLabel}</option>`;
    for (const v of cats) {
        html += `<option value="${v}"${v === sel ? ' selected' : ''}>${v}</option>`;
    }
    if (sel && !cats.includes(sel)) {
        html += `<option value="${sel}" selected>${sel}</option>`;
    }
    return html;
}

function addFamiliarRow(data = {}, container = null) {
    if (!container) container = document.getElementById('familyContainer');
    if (!container) return;

    let fechaFormateada = '';
    if (data.FECH_NACI) {
        const f = String(data.FECH_NACI);
        fechaFormateada = (f.length >= 8 && !f.includes('-') && !f.includes('/'))
            ? `${f.substring(0, 4)}-${f.substring(4, 6)}-${f.substring(6, 8)}`
            : formatDateForInput(f);
    }

    const row = document.createElement('div');
    row.className = 'family-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:8px;align-items:start;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;';
    row.innerHTML = `
        <input type="hidden" name="familiar_id[]" value="${data.CODI_DERE_HABI || ''}">
        <div>
            <label class="dj-label">Parentesco</label>
            <select name="parentesco[]" class="dj-select">
                ${opcionesVinculoHTML(data.TIPO_RELA || '', '—')}
            </select>
        </div>
        <div>
            <label class="dj-label">Apellidos y Nombres</label>
            <input type="text" name="apellidosNombres[]" class="dj-input" value="${data.Nombres || ''}" placeholder="Apellidos y nombres completos">
        </div>
        <div class="family-date">
            <label class="dj-label">Fecha de Nacimiento</label>
            <input type="date" name="fechaNacimiento[]" class="dj-input" value="${fechaFormateada}">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:4px;">
                <span class="family-age" style="display:none;font-size:11px;color:#2563eb;"></span>
                <button type="button" class="btn-dni-hijo dj-btn-sm" style="display:none;background:#eef2ff;color:#3730a3;border-radius:5px;">SUBIR DNI</button>
                <span class="dni-hijo-estado" style="display:none;font-size:10px;"></span>
            </div>
        </div>
        <div>
            <label class="dj-label" style="visibility:hidden;">.</label>
            <button type="button" class="remove-family dj-btn-sm dj-btn-danger" style="margin-bottom:1px;">Eliminar</button>
        </div>`;
    // row.innerHTML = `
    // <div>
    //     <label class="dj-label">Parentesco</label>
    //     <select name="parentesco[]" class="dj-select">
    //         <option value="">—</option>
    //         ${['PADRE','MADRE','ESPOSO','ESPOSA','CONYUGE','HIJO','HIJA','HERMANO','HERMANA','ABUELO','ABUELA']
    //             .map(p => `<option value="${p}" ${data.TIPO_RELA===p?'selected':''}>${p.charAt(0)+p.slice(1).toLowerCase()}</option>`).join('')}
    //     </select>
    // </div>
    // <div>
    //     <label class="dj-label">Apellidos y Nombres</label>
    //     <input type="text" name="apellidosNombres[]" class="dj-input" value="${data.Nombres||''}" placeholder="Apellidos y nombres completos">
    // </div>
    // <div>
    //     <label class="dj-label">Fecha de Nacimiento</label>
    //     <input type="date" name="fechaNacimiento[]" class="dj-input" value="${fechaFormateada}">
    // </div>
    // <div>
    //     <button type="button" class="remove-family dj-btn-sm dj-btn-danger" style="margin-bottom:1px;">Eliminar</button>
    // </div>`;

    container.appendChild(row);
    row.querySelector('.remove-family')?.addEventListener('click', () => row.remove());
    actualizarFechaFamiliar(row);
}

// ── Ubicaciones cascada ──────────────────────────────────────
const ubicacionCache = new Map();
const ubicacionPromise = new Map();

async function getUbicacionCached(params) {
    const key = JSON.stringify(params);

    if (ubicacionCache.has(key)) return ubicacionCache.get(key);
    if (ubicacionPromise.has(key)) return ubicacionPromise.get(key);

    const req = axios.get(`${API_URL}/dj/get-ubicacion`, { params })
        .then(res => {
            ubicacionCache.set(key, res.data);
            return res.data;
        })
        .finally(() => {
            ubicacionPromise.delete(key);
        });

    ubicacionPromise.set(key, req);
    return req;
}


async function cargarUbicaciones(tipo, dept, prov, dist) {
    const prefix = tipo === 'actual' ? '_actual' : tipo === 'dni' ? '_dni' : '_nac';
    if (!dept) return;

    const depts = await getUbicacionCached({ type: 'dept' });
    populateSelect(`#departamento${prefix}`, depts);
    setValue(`#departamento${prefix}`, dept);

    if (!prov) return;

    const provs = await getUbicacionCached({ type: 'prov', dept });
    populateSelect(`#provincia${prefix}`, provs);
    setValue(`#provincia${prefix}`, prov);

    if (!dist) return;

    const dists = await getUbicacionCached({ type: 'dist', prov });
    populateSelect(`#distrito${prefix}`, dists);
    setValue(`#distrito${prefix}`, dist);
}

// ── Helpers de DOM ───────────────────────────────────────────
function populateSelect(selector, data) {
    const sel = document.querySelector(selector);
    if (!sel) return;
    sel.innerHTML = '<option value="">Seleccionar...</option>';
    data.forEach(item => { const opt = document.createElement('option'); opt.value = item.id; opt.textContent = item.text; sel.appendChild(opt); });
}

function setValue(selector, value) {
    const id = selector.startsWith('#') ? selector : `#${selector}`;
    const el = document.querySelector(id);
    if (!el) return;
    const v = value || '';
    // Si es select y el valor guardado no está en las opciones (dato legado), añadirlo
    if (el.tagName === 'SELECT' && v && ![...el.options].some(o => o.value === v)) {
        el.add(new Option(v, v));
    }
    el.value = v;
}

// ── Regla cambio Tipo de Personal (Operativo ↔ Administrativo) ─────────────
const TIPO_GRUPO_OPERATIVO = ['01', '03'];
const TIPO_GRUPO_ADMINISTRATIVO = ['02', '05'];
const TIPO_CODIGO_ESPECIALES = '06';
let tipoPersonalEsEspecial = false;

function userPuedeCambiarTipoPersonal() {
    return (window.funcionalidadesSISOL || []).includes('cambiar_tipo_personal');
}

function poblarSelectTiposPersonal(items) {
    const ui = document.getElementById('tipo_personal_ui');
    if (!ui) return;
    ui.innerHTML = '<option value="">— Seleccionar —</option>';
    (items || []).forEach(t => {
        const o = document.createElement('option');
        o.value = t.codigo;
        o.textContent = t.nombre;
        ui.appendChild(o);
    });
    if (!userPuedeCambiarTipoPersonal()) {
        ui.disabled = true;
        ui.style.background = '#f3f4f6';
        ui.style.color = '#9ca3af';
    }
}

// Regla: Operativo (01/03) solo puede cambiar a Administrativo (02/05) y viceversa.
// Admins RRHH pueden cambiar a cualquier tipo excepto Especial (06).
// Especiales (06) queda deshabilitado sin posibilidad de cambio.
// ── SCTR: visible solo para Administrativo (02/05) ─────────────
// OP (01/03)  → sin checkbox, SCTR='SI' automático (backend)
// ADMIN       → checkbox "SCTR": marcado='SI', sin marcar='NO'
let sctrTipoAnterior = '';
function aplicarSctr(tipoCod, scrt = null) {
    const wrap = document.getElementById('wrap_sctr');
    const chk  = document.getElementById('autorizar_sctr');
    if (!wrap || !chk) return;
    const tipo     = String(tipoCod || '').trim();
    const esAdmin  = ['02', '05'].includes(tipo);
    const eraAdmin = ['02', '05'].includes(sctrTipoAnterior);
    wrap.style.display = esAdmin ? '' : 'none';
    if (!esAdmin) {
        chk.checked = false;
    } else if (scrt !== null && scrt !== undefined) {
        chk.checked = ['SI', '1'].includes(String(scrt).trim().toUpperCase());
    } else if (!eraAdmin) {
        chk.checked = false; // op → admin: aparece sin marcar
    }
    sctrTipoAnterior = tipo;
}

function aplicarReglaTipoPersonal(tipotrab) {
    const catalogo = window.allTiposPersonalDj || [];
    tipoPersonalEsEspecial = false;

    if (!tipotrab) { poblarSelectTiposPersonal(catalogo); return; }

    if (tipotrab === TIPO_CODIGO_ESPECIALES) {
        poblarSelectTiposPersonal(catalogo.filter(t => String(t.codigo).trim() === TIPO_CODIGO_ESPECIALES));
        tipoPersonalEsEspecial = true;
        const ui = document.getElementById('tipo_personal_ui');
        if (ui) { ui.disabled = true; ui.style.background = '#f3f4f6'; ui.style.color = '#9ca3af'; }
        return;
    }

    // Admins RRHH: mostrar todos excepto Especial
    if (userPuedeCambiarTipoPersonal()) {
        poblarSelectTiposPersonal(catalogo.filter(t => String(t.codigo).trim() !== TIPO_CODIGO_ESPECIALES));
        return;
    }

    const esOperativo = TIPO_GRUPO_OPERATIVO.includes(tipotrab);
    const esAdmin = TIPO_GRUPO_ADMINISTRATIVO.includes(tipotrab);
    if (!esOperativo && !esAdmin) { poblarSelectTiposPersonal(catalogo); return; }

    const grupoOpuesto = esOperativo ? TIPO_GRUPO_ADMINISTRATIVO : TIPO_GRUPO_OPERATIVO;
    poblarSelectTiposPersonal(catalogo.filter(t => {
        const cod = String(t.codigo).trim();
        return cod === tipotrab || grupoOpuesto.includes(cod);
    }));
}


// ── Fechas Ingreso Solmar / Cese (DJ existentes) ──────────────
// Ingreso: editable SOLO para Admins RRHH (rol 17) — el readonly lo pone el blade
// Cese:    visible solo si es recontratado (tiene FECH_CESE) y siempre bloqueado
function cargarFechasIngresoCese(data = {}) {
    const fi   = document.getElementById('fecha_ingreso_solmar');
    const fc   = document.getElementById('fecha_cese');
    const wrap = document.getElementById('wrap_fecha_cese');
    if (fi) fi.value = formatDateForInput(data.FECH_INGRE) || '';
    const esRecontratado = !!(data.FECH_CESE && String(data.FECH_CESE).trim() !== '');
    if (wrap) wrap.style.display = esRecontratado ? '' : 'none';
    if (fc) fc.value = esRecontratado ? (formatDateForInput(data.FECH_CESE) || '') : '';
}

function formatDateForInput(dateValue) {
    if (!dateValue) return '';

    // Si tiene T, extraer solo la parte YYYY-MM-DD directamente sin parsear
    if (typeof dateValue === 'string' && dateValue.includes('T')) {
        return dateValue.split('T')[0];
    }

    if (typeof dateValue === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateValue)) return dateValue;
    if (typeof dateValue === 'string' && dateValue.includes(' ')) return dateValue.split(' ')[0];
    if (typeof dateValue === 'string' && /^\d{2}[-/]\d{2}[-/]\d{4}$/.test(dateValue)) {
        const [dia, mes, anio] = dateValue.split(/[-/]/);
        return `${anio}-${mes}-${dia}`;
    }
    if (dateValue instanceof Date) {
        return `${dateValue.getFullYear()}-${String(dateValue.getMonth() + 1).padStart(2, '0')}-${String(dateValue.getDate()).padStart(2, '0')}`;
    }
    return '';
}

function animarContador(el, desde, hasta, duracion = 400) {
    if (!el) return;
    const inicio = performance.now();
    const diff = hasta - desde;
    function tick(ahora) {
        const t = Math.min((ahora - inicio) / duracion, 1);
        el.textContent = Math.round(desde + diff * (1 - Math.pow(1 - t, 3))).toLocaleString();
        if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

// ============================================================
// SPLIT VIEW — Backup, diferencias, interactividad
// ============================================================
const CAMPO_MAP = {
    'apellido_paterno': 'APEL_1', 'apellido_materno': 'APEL_2',
    'nombre1': 'NOMB_1', 'nombre2': 'NOMB_2',
    'dni': 'NRO_DOCU_IDEN', 'caduca': 'PERS_FECHCADUCADNI',
    'estado_civil': 'ESCI_DESCRIPCION', 'sexo': 'PERS_SEXO',
    'fecha_nacimiento': 'FECH_NACI', 'sabe_nadar': 'PERS_SNADAR',
    'celular': 'PERS_TELEFONO', 'correo': 'PERS_EMAIL',
    'tipo_sangre': 'tipo_sangr', 'peso': 'peso_kilo',
    'talla': 'tall_metr', 'sistema_previsional': 'DESC_SIST_PENS',
    'essalud': 'ESSALUD', 'pensionista': 'PERS_PENSIONISTA',
    'grado_instruccion': 'NIED_ABREVIADO', 'anio_egreso': 'EGRESO_EDUCATIVO',
    'embargos': 'PERS_EMBARGO', 'presto_smo': 'PERS_CONSMO', 'lugar_smo': 'PERS_LUGARSMO',
    'direccion_actual': 'DIRECCION', 'direccion_dni': 'PERS_DIREC_DNI',
    'tipo_zona_dni': 'TIZO_CODIGO', 'zona_dirdni': 'PERS_ZONA_DIRDNI',
    'contacto_emergencia': 'PERS_NOMCONTACTO', 'celular_emergencia': 'PERS_NROEMERGENCIA',
    'parentesco_emergencia': 'PERS_EMERC_FAMILIAR', 'ocupacion_principal': 'PERS_PROFESION',
    'curso_sucamec': 'PERS_CONDISCAMEC', 'licencia_arma': 'PERS_NROLICENCIA',
    'tipo_arma': 'PERS_TIPOARMA', 'arma_propia': 'PERS_CONARMAS',
    'brevete': 'PERS_BREVETE', 'clase_brevete': 'CLASE_BREVETE',
    'empresa_anterior': 'PERS_CTRABANT', 'cargo_anterior': 'PERS_CARGOTRABANT',
};

const FECHA_FIELDS_BK = ['FECH_NACI', 'PERS_FECHCADUCADNI', 'FECH_INGRE', 'FECH_CESE'];

let _backupData = null;

async function cargarDatosBackup(codiPers) {
    const wrapper = document.getElementById('djSplitWrapper');
    const panelBk = document.getElementById('panelBackup');
    const badgeSplit = document.getElementById('splitModeBadge');
    const contDiffs = document.getElementById('contadorDiffs');
    if (!wrapper) return;

    // ✅ Limpiar panel antes de cargar
    wrapper.classList.remove('no-backup');
    if (panelBk) panelBk.style.display = 'block';
    document.querySelectorAll('#panelBackup .bk-val').forEach(el => el.textContent = '—');

    try {
        const response = await axios.get(`${API_URL}/dj/get-backup-data`, { params: { codi_pers: codiPers } });

        if (!response.data.success) {
            console.warn('[BackupDJ] Sin backup para', codiPers, response.data.message ?? '');
            _backupData = {};
            return;
        }

        _backupData = response.data.data;
        wrapper.classList.remove('no-backup');
        if (panelBk) panelBk.style.display = 'block';
        if (badgeSplit) badgeSplit.style.display = 'flex';

        // Badge fecha mod
        const badge = document.getElementById('bkFechaModBadge');
        if (badge && _backupData.USUA_FECHA_MOD) {
            const f = new Date(_backupData.USUA_FECHA_MOD);
            if (!isNaN(f)) badge.textContent = `Últ. mod: ${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()}`;
        }

        // Reset visibilidad secciones tipo (fuera del forEach)
        // document.querySelectorAll('.bk-tipo-section').forEach(el => { el.style.display = ''; });

        const tipoActual = document.getElementById('tipo_personal')?.value?.trim() ?? '';
        aplicarVisibilidadBackup(tipoActual); // ← aplica DESPUÉS de que el backup ya está renderizado

        activarInteractividad();

        // Llenar campos backup
        wrapper.querySelectorAll('.bk-val[data-field]').forEach(el => {
            const field = el.getAttribute('data-field');
            let val = _backupData[field] ?? '';
            // if (FECHA_FIELDS_BK.includes(field) && val) {
            //     const d = new Date(val);
            //     if (!isNaN(d)) val = `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
            // }
            if (FECHA_FIELDS_BK.includes(field) && val) {
                const valStr = String(val);
                // Extraer YYYY-MM-DD sin crear Date (evita timezone)
                const match = valStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (match) {
                    val = `${match[3]}/${match[2]}/${match[1]}`; // DD/MM/YYYY
                }
            }
            el.textContent = val ? String(val).toUpperCase().trim() : '—';
        });

        if (window.allCarreras) {
            // Institución
            const bkInstEl = wrapper.querySelector('.bk-val[data-field="IEDU_CODIGO"]');
            if (bkInstEl && _backupData.IEDU_CODIGO) {
                const carreraMatch = window.allCarreras.find(c => c.IEDU_CODIGO === _backupData.IEDU_CODIGO?.trim());
                // La descripción de institución no está en allCarreras directamente,
                // pero sí podemos buscar en el select
                const selInst = document.getElementById('institucion');
                const optInst = selInst?.querySelector(`option[value="${_backupData.IEDU_CODIGO?.trim()}"]`);
                if (optInst) bkInstEl.textContent = optInst.textContent;
            }

            // Carrera
            const bkCarrEl = wrapper.querySelector('.bk-val[data-field="CARR_CODIGO"]');
            if (bkCarrEl && _backupData.CARR_CODIGO) {
                const carrera = window.allCarreras.find(c => c.id === _backupData.CARR_CODIGO?.trim());
                if (carrera) bkCarrEl.textContent = carrera.text;
            }
        }

        // Familiares backup
        // Quitar botón de previsualizar
        const btnPrev = document.getElementById("btnPrevisualizar");
        if (btnPrev) btnPrev.style.display = 'none';

        // // Foto en DJ Antiguo (Panel Backup)
        let imgBk = document.getElementById('previewFotoBackup');
        let placeholderBk = document.getElementById('placeholderFotoBackup');

        if (imgBk) {
            // Como el backend de backup no trae FOTO_PATH, armamos la ruta directa con el codiPers
            const fotoUrl = `http://190.116.178.163/Biblioteca_Grafica/Fotos/${codiPers}.jpg`;

            // Asignamos la imagen
            imgBk.src = fotoUrl + '?v=' + (Math.floor(Math.random() * 900) + 100);

            // Si carga bien, mostramos la foto y ocultamos el placeholder
            imgBk.onload = function () {
                imgBk.classList.remove('hidden');
                if (placeholderBk) placeholderBk.classList.add('hidden');
            };

            // Si no hay foto en el servidor, dejamos el placeholder visible
            imgBk.onerror = function () {
                imgBk.classList.add('hidden');
                if (placeholderBk) placeholderBk.classList.remove('hidden');
            };
        } else {
            // Fallback por si acaso no pusiste los IDs en el blade
            const panelInfo = document.querySelector('#panelBackup');
            if (panelInfo) {
                const fotoUrl = `http://190.116.178.163/Biblioteca_Grafica/Fotos/${codiPers}.jpg`;
                panelInfo.insertAdjacentHTML('afterbegin', `<div class="mb-4 text-center mt-4"><img src="${fotoUrl}" class="rounded-lg mx-auto border border-gray-300 shadow-sm" style="max-height: 140px;" onerror="this.style.display='none'" /></div>`);
            }
        }

        // Familiares backup
        const tbody = document.getElementById('bodyBackupFamiliares');
        if (tbody) {
            const familiares = response.data.familiares ?? [];
            tbody.innerHTML = familiares.length === 0
                ? `<tr><td colspan="3" style="padding:6px 8px;color:#9ca3af;font-style:italic;font-size:11px;">Sin familiares registrados</td></tr>`
                : familiares.map(f => `<tr><td>${f.TIPO_RELA ?? '—'}</td><td>${f.Nombres ? f.Nombres.toUpperCase().trim() : '—'}</td><td>${f.FECH_NACI ?? '—'}</td></tr>`).join('');
        }

        // Diferencias (con delay para que el form esté lleno)
        setTimeout(() => {
            const diffs = marcarDiferencias();
            if (contDiffs) {
                if (diffs > 0) { contDiffs.style.display = 'inline-block'; contDiffs.textContent = `${diffs} campo${diffs > 1 ? 's' : ''} diferente${diffs > 1 ? 's' : ''}`; }
                else contDiffs.style.display = 'none';
            }
        }, 300);

        activarInteractividad();

    } catch (err) {
        console.warn('[BackupDJ] Error al cargar backup:', err);
        // No ocultar el panel — mostrar placeholder de "sin datos" si aplica
        _backupData = {};
        wrapper.classList.remove('no-backup');
        if (panelBk) panelBk.style.display = '';
    }
}

function marcarDiferencias() {
    if (!_backupData) return 0;
    let totalDiffs = 0;

    document.querySelectorAll('[data-compare]').forEach(el => el.classList.remove('has-diff'));
    document.querySelectorAll('.bk-field').forEach(el => el.classList.remove('is-diff'));

    Object.entries(CAMPO_MAP).forEach(([formId, bkField]) => {
        const inputEl = document.getElementById(formId);
        if (!inputEl) return;

        let valForm = String(inputEl.value ?? '').trim();
        let valBk = String(_backupData[bkField] ?? '').trim();

        if (FECHA_FIELDS_BK.includes(bkField) && valBk) {
            valBk = valBk.replace('T', ' ').split(' ')[0];
        }

        let normForm = valForm.toUpperCase();
        let normBk = valBk.toUpperCase();

        console.log('a ', normForm);
        console.log('b ', normBk);
        console.log('c ', formId);

        if (formId === 'estado_civil') {
            const estadosCiviles = {
                '2007000001': 'SOLTERO',
                '2007000002': 'CASADO',
                '2007000003': 'DIVORCIADO',
                '2007000004': 'VIUDO',
                '2007000008': 'CONVIVIENTE'
            };

            normForm = (estadosCiviles[valForm] || '').toUpperCase();
        }

        if (formId === 'sistema_previsional') {
            const sistemasPensiones = {
                '01': 'SISTEMA NACIONAL DE PENSIONES',
                '02': 'AFP INTEGRA',
                '03': 'PROFUTURO AFP',
                '04': 'AFP HORIZONTE',
                '05': 'AFP UNION VIDA',
                '06': 'CAJA DE BENEFICIO DEL PESCADOR',
                '07': 'NO APORTACION',
                '10': 'AFP PRIMA',
                '11': 'AFP EL ROBLE',
                '27': 'AFP HABITAT'
            };

            normForm = (sistemasPensiones[valForm] || '').toUpperCase();
        }

        if (formId === 'grado_instruccion') {
            const gradosInstruccion = {
                '01': 'SIN EDUCACIÓN FORMAL',
                '02': 'ESPECIAL INCOMPLETA',
                '03': 'ESPECIAL COMPLETA',
                '04': 'PRIMARIA INCOMPLETA',
                '05': 'PRIMARIA COMPLETA',
                '06': 'SECUNDARIA INCOMPLETA',
                '07': 'SECUNDARIA COMPLETA',
                '08': 'TÉCNICA INCOMPLETA',
                '09': 'TÉCNICA COMPLETA',
                '10': 'SUPERIOR INCOMPLETA (INSTIT. SUPER)',
                '11': 'SUPERIOR COMPLETA (INSTIT SUPER)',
                '12': 'UNIVERSITARIA INCOMPLETA',
                '13': 'UNIVERSITARIA COMPLETA',
                '14': 'GRADO DE BACHILLER',
                '15': 'TITULADO',
                '16': 'ESTUD. MAESTRÍA INCOMPLETA',
                '17': 'ESTUD. MAESTRÍA COMPLETA',
                '18': 'GRADO DE MAESTRÍA',
                '19': 'ESTUD. DOCTORADO INCOMPLETO',
                '20': 'ESTUD. DOCTORADO COMPLETO',
                '21': 'GRADO DE DOCTOR'
            };

            normForm = (gradosInstruccion[valForm] || '').toUpperCase();
        }


        if (normForm && normBk && normForm !== normBk) {
            totalDiffs++;
            inputEl.classList.add('has-diff');
            document.querySelector(`.bk-field[data-bk="${formId}"]`)?.classList.add('is-diff');
        }
    });

    return totalDiffs;
}

function activarInteractividad() {
    const panelForm = document.getElementById('panelForm');
    if (!panelForm) return;

    if (panelForm._splitFocusIn) panelForm.removeEventListener('focusin', panelForm._splitFocusIn);
    if (panelForm._splitFocusOut) panelForm.removeEventListener('focusout', panelForm._splitFocusOut);

    panelForm._splitFocusIn = e => {
        const compareId = e.target.getAttribute('data-compare') || e.target.id;
        if (!compareId) return;
        document.querySelectorAll('.bk-field.is-active').forEach(el => el.classList.remove('is-active'));
        const bkEl = document.querySelector(`.bk-field[data-bk="${compareId}"]`);
        if (bkEl) {
            bkEl.classList.add('is-active');
            const panelBk = document.getElementById('panelBackup');
            if (panelBk) panelBk.scrollTop += (bkEl.getBoundingClientRect().top - panelBk.getBoundingClientRect().top) - 80;
        }
    };
    panelForm._splitFocusOut = () => {
        setTimeout(() => {
            if (!panelForm.contains(document.activeElement))
                document.querySelectorAll('.bk-field.is-active').forEach(el => el.classList.remove('is-active'));
        }, 150);
    };

    panelForm.addEventListener('focusin', panelForm._splitFocusIn);
    panelForm.addEventListener('focusout', panelForm._splitFocusOut);
}

function aplicarVisibilidadBackup(tipoCod) {
    const esOperativo = tipoCod == '03' || tipoCod == '01';
    const esAdministrativo = tipoCod == '05' || tipoCod == '02';

    document.querySelectorAll('.bk-tipo-section[data-bk-tipo="operativo"]')
        .forEach(el => { el.style.display = esAdministrativo ? 'none' : ''; });
    document.querySelectorAll('.bk-tipo-section[data-bk-tipo="administrativo"]')
        .forEach(el => { el.style.display = esOperativo ? 'none' : ''; });
}

// ── La función original queda sin los querySelectorAll del backup ──
function aplicarVisibilidadPorTipo(tipoCod) {
    const esOperativo = tipoCod == '03' || tipoCod == '01';
    const esAdministrativo = tipoCod == '05' || tipoCod == '02';
    const esEspecial = tipoCod == '06';

    // Solo afecta el panel del formulario (derecho)
    document.querySelectorAll('[data-tipo="operativo"]')
        .forEach(el => { el.style.display = esAdministrativo ? 'none' : ''; });
    document.querySelectorAll('[data-tipo="administrativo"]')
        .forEach(el => { el.style.display = esOperativo ? 'none' : ''; });

    let badge = document.getElementById('tipoBadgeModal');
    if (!badge) {
        badge = document.createElement('span');
        badge.id = 'tipoBadgeModal';
        badge.style.cssText = 'font-size:10px;font-weight:700;padding:2px 10px;border-radius:20px;margin-left:8px;letter-spacing:.04em;text-transform:uppercase;display:inline-block;';
        const headerTitle = document.querySelector('#modalDjGestion [style*="font-size:13px"]');
        if (headerTitle) headerTitle.parentNode.insertBefore(badge, headerTitle.nextSibling);
    }

    if (esOperativo) { badge.textContent = 'Operativo — RH 01'; badge.style.background = '#dbeafe'; badge.style.color = '#1e40af'; }
    else if (esAdministrativo) { badge.textContent = 'Administrativo — RH 02'; badge.style.background = '#d1fae5'; badge.style.color = '#065f46'; }
    else { badge.textContent = ''; }
}

function limpiarSplitView() {
    _backupData = null;
    const wrapper = document.getElementById('djSplitWrapper');
    const panelBk = document.getElementById('panelBackup');
    const badgeSplit = document.getElementById('splitModeBadge');
    const contDiffs = document.getElementById('contadorDiffs');

    if (wrapper) wrapper.classList.add('no-backup');
    if (panelBk) panelBk.style.display = 'none';
    if (badgeSplit) badgeSplit.style.display = 'none';
    if (contDiffs) contDiffs.style.display = 'none';

    document.querySelectorAll('.bk-val').forEach(el => el.textContent = '—');
    document.querySelectorAll('.bk-field').forEach(el => el.classList.remove('is-diff', 'is-active'));
    document.querySelectorAll('[data-compare]').forEach(el => el.classList.remove('has-diff'));
    document.querySelectorAll('.bk-tipo-section').forEach(el => { el.style.display = ''; });

    const tbody = document.getElementById('bodyBackupFamiliares');
    if (tbody) tbody.innerHTML = `<tr><td colspan="3" style="padding:6px;color:#9ca3af;font-style:italic;">Cargando...</td></tr>`;

    const badge = document.getElementById('bkFechaModBadge');
    if (badge) badge.textContent = '';

    // Reset tipo de personal
    const tipoUi = document.getElementById('tipo_personal_ui');
    if (tipoUi) {
        tipoUi.value = '';
        tipoPersonalEsEspecial = false;
        poblarSelectTiposPersonal(window.allTiposPersonalDj || []);
    }
    aplicarSctr(''); cargarFechasIngresoCese({}); aplicarExtranjeriaNacimiento(''); limpiarDniDj(); limpiarDniHijosDj();

}

// ============================================================
// BOTONES REPORTES
// ============================================================
document.getElementById('btnReporteFaltantes')?.addEventListener('click', async function () {
    const sucursal = document.getElementById('filtroSucursal')?.value ?? '00';
    const tipoPer = document.getElementById('filtroTipoPer')?.value ?? '00';

    // Nuevo mapeo para los tipos de personal V2
    let codTipoPer = '00';
    if (tipoPer === 'OPERATIVO 4°') codTipoPer = '01';
    else if (tipoPer === 'OPERATIVO 5°') codTipoPer = '03';
    else if (tipoPer === 'ADMINISTRATIVO 4°') codTipoPer = '02';
    else if (tipoPer === 'ADMINISTRATIVO 5°') codTipoPer = '05';
    else if (tipoPer === 'ESPECIAL') codTipoPer = '06';

    const codSucursal = sucursal || '00';

    Swal.fire({ title: 'Generando reporte...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const response = await axios.get(`${VITE_URL_APP}/api/reporte-personal-sin-migracion-v2`, { params: { codSucursal, codTipoPer, tipo: 1 } });

        if (!response.data.success || !response.data.data.length) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros.' });
            return;
        }

        const todosLosDatos = response.data.data;
        const soloFaltantes = todosLosDatos.filter(d => d.SIP_CAMBIO === 'Falta');
        Swal.close();

        if (!soloFaltantes.length) {
            Swal.fire({ icon: 'info', title: 'Sin faltantes', text: 'Todo el personal está actualizado.' });
            return;
        }

        generarReporteFaltantesPDF(soloFaltantes, todosLosDatos);
    } catch {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo obtener los datos.' });
    }
});

document.getElementById('btnReporteActualizacion')?.addEventListener('click', async function () {
    const sucursal = document.getElementById('filtroSucursal')?.value ?? '00';
    const tipoPer = document.getElementById('filtroTipoPer')?.value ?? '00';
    const codTipoPer = tipoPer === 'OPERATIVO' ? '03' : tipoPer === 'ADMINISTRATIVO' ? '05' : '00';
    const codSucursal = sucursal || '00';

    Swal.fire({ title: 'Generando reporte...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const response = await axios.get(`${VITE_URL_APP}/api/reporte-personal-sin-migracion-v2`, { params: { codSucursal, codTipoPer, tipo: null } });
        if (!response.data.success || !response.data.data.length) { Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros.' }); return; }
        const todosLosDatos = response.data.data;
        const soloActualizados = todosLosDatos.filter(d => d.SIP_CAMBIO === 'Ok');
        Swal.close();
        if (!soloActualizados.length) { Swal.fire({ icon: 'info', title: 'Sin actualizados', text: 'No hay personal actualizado aún.' }); return; }
        generarReporteFaltantesPDF(soloActualizados, todosLosDatos);
    } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo obtener los datos.' }); }
});

// Toggle colapsable DJ Anterior
document.getElementById('headerDJAnterior')?.addEventListener('click', function () {
    const cuerpo = document.getElementById('cuerpoDJAnterior');
    const icono = document.getElementById('iconoDJAnterior');
    if (!cuerpo) return;
    const abierto = cuerpo.style.display !== 'none';
    cuerpo.style.display = abierto ? 'none' : 'block';
    if (icono) icono.textContent = abierto ? '▼' : '▲';
});

// ── Filtrar carreras según institución seleccionada ──
document.getElementById('institucion')?.addEventListener('change', function () {
    const ieduCodigo = this.value;
    const selCarrera = document.getElementById('carrera');
    if (!selCarrera) return;

    selCarrera.innerHTML = '<option value="">—</option>';

    if (!ieduCodigo || !window.allCarreras) return;

    const carrerasFiltradas = window.allCarreras.filter(c => c.IEDU_CODIGO === ieduCodigo);

    carrerasFiltradas.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.text;
        selCarrera.appendChild(opt);
    });
});


// ============================================================
// DJ GENERADOS — API helpers
// ============================================================

function limpiarDJGenerados() {
    generadosCache = {};
}

async function desmarcarDJGenerado(codPersonal) {
    await resetearGeneradosAPI([codPersonal]);
}

document.getElementById('btnResetearDJs')?.addEventListener('click', async function () {
    const totalMarcados = Object.keys(generadosCache).length;

    if (totalMarcados === 0) {
        Swal.fire({ icon: 'info', title: 'Sin marcas', text: 'No hay registros marcados como generados.' });
        return;
    }

    const { isConfirmed } = await Swal.fire({
        icon: 'warning',
        title: 'Resetear todas las marcas',
        html: `Se eliminarán las marcas ✅ de <b>${totalMarcados}</b> registro(s).<br>
               <span style="font-size:12px;color:#6b7280;">Todos volverán a aparecer como pendientes.</span>`,
        showCancelButton: true,
        confirmButtonText: 'Sí, resetear todo',
        cancelButtonText: 'Cancelar',
    });

    if (isConfirmed) {
        await resetearGeneradosAPI();
        if (typeof tblPersonasMigrado !== 'undefined') tblPersonasMigrado.redraw(true);
    }
});

// ============================================================
// EXTRAER FIRMA Y HUELLA — Extracción desde PDF DJ
// ============================================================
(function initExtractorFirmaHuella() {

    let _pdfDoc = null;
    let _lastPageCvs = null;

    const el = id => document.getElementById(id);

    // ── Conversión mm → px ────────────────────────────────────
    // A4 = 210mm × 297mm. El canvas renderizado tiene canvas.width = 210 * mmX
    const mmX = cvs => cvs.width / 210;   // px por mm horizontal
    const mmY = cvs => cvs.height / 297;   // px por mm vertical

    // ── Detectar borde horizontal en el canvas por píxeles ───────
    // Escanea filas horizontales buscando líneas oscuras (bordes del PDF).
    // Devuelve la Y en px del ÚLTIMO borde encontrado en [fromMm, toMm].
    // Funciona con cualquier escala de renderizado.
    function findHorizontalBorder(cvs, fromMm, toMm) {
        const scY = cvs.height / 297;
        const scX = cvs.width / 210;
        const yS = Math.round(fromMm * scY);
        const yE = Math.round(toMm * scY);
        // Muestrear solo la franja interior del box (interior a los márgenes)
        const xL = Math.round(12 * scX);
        const xR = Math.round(198 * scX);
        const sw = xR - xL;

        const { data } = cvs.getContext('2d').getImageData(xL, yS, sw, yE - yS);
        let last = -1, inBdr = false;

        for (let r = 0; r < yE - yS; r++) {
            let dark = 0;
            for (let c = 0; c < sw; c++) {
                const p = (r * sw + c) * 4;
                if ((data[p] + data[p + 1] + data[p + 2]) / 3 < 80) dark++;
            }
            if (dark / sw > 0.14 && !inBdr) { last = yS + r; inBdr = true; }
            else if (dark / sw <= 0.07) { inBdr = false; }
        }
        return last >= 0 ? last : null;
    }

    // ── Calcular región de recorte exacta ─────────────────────
    // Usa detección de píxeles para encontrar el borde superior de la
    // celda firma/huella — funciona igual para PDFs de 1 o 2 páginas.
    function getCropRegion(cvs) {
        const scX = mmX(cvs);
        const scY = mmY(cvs);
        const n = _pdfDoc?.numPages ?? 1;

        let firmaTopPx;
        if (n >= 2) {
            // Pág 2: titulo(5.5mm) + conformidad(17-28mm) → firma entre 22mm y 52mm
            firmaTopPx = findHorizontalBorder(cvs, 22, 52) ?? Math.round(32 * scY);
        } else {
            // Pág 1: firma al final — zona entre 205mm y 258mm
            firmaTopPx = findHorizontalBorder(cvs, 205, 258) ?? Math.round(236 * scY);
        }

        // Y de recorte: saltar el grosor del borde de la celda
        // La celda mide 45mm; excluir últimos 9mm de etiquetas → 36mm de contenido
        const inYTop = 2.0 * scY;   // clearance del borde (~0.3mm dibujado en PDF)
        const inYBot = 0.5 * scY;
        const yTop = firmaTopPx + inYTop;
        const yBottom = firmaTopPx + (45 - 9) * scY - inYBot;

        // X fijos (marginLeft=10, firmaW=114mm, huellaW=76mm), inset 3mm c/lado
        const firmaX = Math.round((10 + 3) * scX);   // → 108mm de ancho neto
        const firmaW = Math.round((114 - 6) * scX);
        const huellaX = Math.round((124 + 3) * scX);   // → 70mm de ancho neto
        const huellaW = Math.round((76 - 6) * scX);

        return {
            yTop: Math.max(0, Math.round(yTop)),
            yBottom: Math.min(cvs.height, Math.round(yBottom)),
            firmaX,
            firmaW,
            huellaX,
            huellaW,
        };
    }

    // ── Reiniciar modal ───────────────────────────────────────
    function resetModal() {
        el('fhStep1')?.classList.remove('hidden');
        el('fhStep2')?.classList.add('hidden');
        el('fhStep3')?.classList.add('hidden');
        el('fhFileInfo')?.classList.add('hidden');
        const inp = el('fhInputPdf');
        if (inp) inp.value = '';
        const cont = el('fhPagesContainer');
        if (cont) cont.innerHTML = '';
        _pdfDoc = null;
        _lastPageCvs = null;
    }

    // ── Abrir / cerrar modal ──────────────────────────────────
    function abrirModal() {
        resetModal();
        const modal = document.getElementById('modalExtFirmaHuella');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function cerrarModal() {
        const modal = document.getElementById('modalExtFirmaHuella');
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        resetModal();
        
        // Reset Tipo de Personal select
        const tipoUi = document.getElementById('tipo_personal_ui');
        if (tipoUi) {
            tipoUi.value = '';
            tipoPersonalEsEspecial = false;
            poblarSelectTiposPersonal(window.allTiposPersonalDj || []);
        }
        aplicarSctr(''); cargarFechasIngresoCese({}); aplicarExtranjeriaNacimiento(''); limpiarDniDj(); limpiarDniHijosDj();

        // Reset No Caduca checkbox y restore caduca
        const noCaducaReset = document.getElementById('no_caduca_dni');
        const caducaReset = document.getElementById('caduca');
        if (noCaducaReset) noCaducaReset.checked = false;
        if (caducaReset) {
            caducaReset.disabled = false;
            caducaReset.value = '';
            caducaReset.style.background = '';
            caducaReset.style.color = '';
        }

        // Reset S.M.O. selects
        const prestoSmoReset = document.getElementById('presto_smo');
        const lugarSmoReset = document.getElementById('lugar_smo');
        if (prestoSmoReset) prestoSmoReset.value = '';
        if (lugarSmoReset) {
            lugarSmoReset.disabled = true;
            lugarSmoReset.value = '';
        }
    }

    // ── Cargar y validar PDF ──────────────────────────────────
    async function cargarPDF(file) {
        if (!file || file.type !== 'application/pdf') {
            Swal.fire({ icon: 'warning', title: 'Archivo inválido', text: 'Selecciona un archivo PDF.' });
            return;
        }
        if (!window.pdfjsLib) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'PDF.js no está disponible. Recarga la página.' });
            return;
        }
        try {
            const buf = await file.arrayBuffer();
            const pdfDoc = await pdfjsLib.getDocument({ data: buf }).promise;

            if (pdfDoc.numPages > 2) {
                Swal.fire({
                    icon: 'warning', title: 'Más de 2 páginas',
                    text: `El PDF tiene ${pdfDoc.numPages} páginas. Solo se procesará la última (donde está la firma).`,
                });
            }

            _pdfDoc = pdfDoc;
            const pages = Math.min(pdfDoc.numPages, 2);
            el('fhFileName').textContent = file.name;
            el('fhPageCount').textContent = `${pages} página${pages > 1 ? 's' : ''}`;
            el('fhFileInfo')?.classList.remove('hidden');
        } catch (err) {
            console.error('Error cargando PDF:', err);
            Swal.fire({ icon: 'error', title: 'Error al leer el PDF', text: err.message });
        }
    }

    // ── Renderizar páginas del PDF ────────────────────────────
    async function previsualizarPDF() {
        if (!_pdfDoc) return;

        const container = el('fhPagesContainer');
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-10 text-gray-400">
                <i class='bx bx-loader-alt bx-spin text-3xl'></i>
                <p class="text-sm mt-2">Renderizando documento...</p>
            </div>`;

        el('fhStep2')?.classList.remove('hidden');
        el('fhStep3')?.classList.add('hidden');

        await new Promise(r => setTimeout(r, 60));
        container.innerHTML = '';

        const numPages = Math.min(_pdfDoc.numPages, 2);
        const scale = 1.4;

        for (let i = 1; i <= numPages; i++) {
            const page = await _pdfDoc.getPage(i);
            const viewport = page.getViewport({ scale });

            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'display:flex;flex-direction:column;align-items:center;';

            const label = document.createElement('div');
            label.style.cssText = 'font-size:11px;color:#9ca3af;margin-bottom:4px;';
            label.textContent = `Página ${i} de ${numPages}`;
            wrapper.appendChild(label);

            const canvasBox = document.createElement('div');
            canvasBox.style.cssText = 'position:relative;display:inline-block;max-width:100%;';

            const canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.cssText = 'display:block;max-width:100%;border:1px solid #d1d5db;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08);';

            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            canvasBox.appendChild(canvas);

            // Overlay solo en la última página
            if (i === numPages) {
                _lastPageCvs = canvas;
                const r = getCropRegion(canvas);
                const cW = canvas.width;
                const cH = canvas.height;

                // Zona firma (amarillo)
                const mkZone = (x, w, label) => {
                    const z = document.createElement('div');
                    z.style.cssText = `
                        position:absolute;
                        left:${x / cW * 100}%;
                        width:${w / cW * 100}%;
                        top:${r.yTop / cH * 100}%;
                        height:${(r.yBottom - r.yTop) / cH * 100}%;
                        border:2px dashed #f59e0b;
                        border-radius:2px;
                        background:rgba(245,158,11,.07);
                        pointer-events:none;
                        box-sizing:border-box;
                    `;
                    const b = document.createElement('div');
                    b.style.cssText = 'position:absolute;bottom:2px;left:50%;transform:translateX(-50%);font-size:9px;color:#d97706;white-space:nowrap;font-weight:700;';
                    b.textContent = label;
                    z.appendChild(b);
                    return z;
                };

                canvasBox.appendChild(mkZone(r.firmaX, r.firmaW, '✏ Firma'));
                canvasBox.appendChild(mkZone(r.huellaX, r.huellaW, '● Huella'));
            }

            wrapper.appendChild(canvasBox);
            container.appendChild(wrapper);
        }
    }

    // ── Extraer firma y huella del canvas ────────────────────
    function extraerImagenes() {
        if (!_lastPageCvs) {
            Swal.fire({ icon: 'warning', title: 'Sin previsualización', text: 'Presiona "Previsualizar PDF" primero.' });
            return;
        }

        const src = _lastPageCvs;
        const r = getCropRegion(src);
        const cH = r.yBottom - r.yTop;

        // Firma
        const cvF = el('fhCanvasFirma');
        cvF.width = r.firmaW;
        cvF.height = cH;
        cvF.getContext('2d').drawImage(src, r.firmaX, r.yTop, r.firmaW, cH, 0, 0, r.firmaW, cH);

        // Huella
        const cvH = el('fhCanvasHuella');
        cvH.width = r.huellaW;
        cvH.height = cH;
        cvH.getContext('2d').drawImage(src, r.huellaX, r.yTop, r.huellaW, cH, 0, 0, r.huellaW, cH);

        el('fhStep2')?.classList.add('hidden');
        el('fhStep3')?.classList.remove('hidden');
    }

    // ── Descargar canvas como PNG ─────────────────────────────
    function descargarCanvas(canvas, nombre) {
        if (!canvas || canvas.width === 0) return;
        const a = document.createElement('a');
        a.href = canvas.toDataURL('image/png');
        a.download = nombre + '.png';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // ── Event listeners ───────────────────────────────────────
    const inputPdf = el('fhInputPdf');
    const dropZone = el('fhDropZone');

    inputPdf?.addEventListener('change', e => {
        const f = e.target.files?.[0];
        if (f) cargarPDF(f);
    });

    dropZone?.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('fh-drag-over'); });
    dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('fh-drag-over'));
    dropZone?.addEventListener('drop', e => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('fh-drag-over');
        const f = e.dataTransfer.files?.[0];
        if (f) cargarPDF(f);
    });

    el('fhBtnCambiar')?.addEventListener('click', e => {
        e.stopPropagation();
        el('fhFileInfo')?.classList.add('hidden');
        el('fhStep2')?.classList.add('hidden');
        el('fhStep3')?.classList.add('hidden');
        if (inputPdf) inputPdf.value = '';
        _pdfDoc = null;
        _lastPageCvs = null;
    });

    el('fhBtnPreview')?.addEventListener('click', previsualizarPDF);
    el('fhBtnExtract')?.addEventListener('click', extraerImagenes);
    el('fhBtnReintentar')?.addEventListener('click', () => {
        el('fhStep2')?.classList.remove('hidden');
        el('fhStep3')?.classList.add('hidden');
    });

    el('fhBtnDownloadFirma')?.addEventListener('click', () => descargarCanvas(el('fhCanvasFirma'), 'firma_registrada'));
    el('fhBtnDownloadHuella')?.addEventListener('click', () => descargarCanvas(el('fhCanvasHuella'), 'huella_registrada'));

    el('btnExtFirmaHuella')?.addEventListener('click', abrirModal);
    el('btnCerrarFHModal')?.addEventListener('click', cerrarModal);
    document.getElementById('modalExtFirmaHuella')?.addEventListener('click', function (e) {
        if (e.target === this) cerrarModal();
    });

})();

// ============================================================
// EXPORTACIÓN PDF (ETAPA 2)
// ============================================================
document.getElementById("btnReporteVerificados")?.addEventListener("click", async () => {
    // 1. Rescatamos los filtros actuales de la etapa 2
    const codSucursal = document.getElementById('filtroSucursalE2')?.value || '00';
    const codTipoPer = document.getElementById('filtroTipoPerE2')?.value || '00';
    const textoBusqueda = document.getElementById('buscarPersonalE2')?.value.toLowerCase().trim() || '';

    Swal.fire({ title: 'Generando reporte...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        // 2. Le pegamos al nuevo endpoint que ejecuta el SP SW_REPORTE_LISTAR_PERSONAL_SIN_MIGRACION_2_ETAPA
        const response = await axios.get(`${VITE_URL_APP}/api/reporte-personal-etapa2`, {
            params: { codSucursal, codTipoPer }
        });

        if (!response.data.success || !response.data.data.length) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros en el reporte.' });
            return;
        }

        let datos = response.data.data;

        // 3. Filtro local adicional por si el usuario escribió algo en el buscador
        if (textoBusqueda) {
            datos = datos.filter(d => {
                const nombreCompleto = d.NOMBRE || d.PERSONAL || `${d.nombres ?? ''} ${d.apellido1 ?? ''} ${d.apellido2 ?? ''}`.trim();
                const documento = d.dni || d.NRO_DOCU_IDEN || '';
                const str = `${nombreCompleto} ${documento}`.toLowerCase();
                return str.includes(textoBusqueda);
            });
        }

        if (!datos.length) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay registros que coincidan con la búsqueda.' });
            return;
        }

        // 4. Cálculos para las Cards (Total, Verificados, Sin verificar)
        const totalRegistros = datos.length;
        const totalVerificados = datos.filter(d => {
            const estado = d.VERIFICADO_CAMBIO ? d.VERIFICADO_CAMBIO.toUpperCase().trim() : '';
            return estado === 'SI' || estado === 'VERIFICADO';
        }).length;
        const totalSinVerificar = totalRegistros - totalVerificados;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        const totalWidth = doc.internal.pageSize.getWidth();

        // 5. Texto para el subtítulo (Sucursal)
        // 5. Texto para el subtítulo (Sucursal y Tipo)
        const selSucursal = document.getElementById('filtroSucursalE2');
        const txtSucursal = selSucursal?.options[selSucursal.selectedIndex]?.text?.toUpperCase() || 'TODAS LAS SUCURSALES';
        
        const selTipo = document.getElementById('filtroTipoPerE2');
        const txtTipo = selTipo?.options[selTipo.selectedIndex]?.text?.toUpperCase() || 'TODOS';
        const tipoFiltroTexto = txtTipo === 'TODOS' ? '' : txtTipo;

        const f = new Date();
        const fechaStr = `${String(f.getDate()).padStart(2, '0')}/${String(f.getMonth() + 1).padStart(2, '0')}/${f.getFullYear()} ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`;

        // 🔥 LÓGICA DE TÍTULOS DINÁMICOS
        const radioVal = document.getElementById('filtroEstadoE2')?.value || 'null';
        let baseTitle = "ETAPA N°2: REPORTE COMPLETO DE VERIFICACIÓN";
        let estadoArchivo = "Todos";

        if (radioVal === '1') {
            baseTitle = "ETAPA N°2: REPORTE DE PENDIENTES POR VERIFICAR";
            estadoArchivo = "Sin_Verificar";
        } else if (radioVal === '0') {
            baseTitle = "ETAPA N°2: REPORTE DE VERIFICADOS";
            estadoArchivo = "Verificados";
        }
        
        const tituloReporte = `${baseTitle} ${tipoFiltroTexto}`.trim();
        const tipoArchivo = tipoFiltroTexto === '' ? 'Todos' : tipoFiltroTexto.replace(/ /g, '_');

        // 6. Cargar Logo de Sol Security
        let logoBase64 = null;
        try {
            if (window.logoUrl) {
                const res = await fetch(window.logoUrl);
                const blob = await res.blob();
                logoBase64 = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.readAsDataURL(blob);
                });
            }
        } catch (e) { console.warn("No se pudo cargar el logo", e); }

        // 7. Dibujar la tabla PDF
        doc.autoTable({
            startY: 60,
            theme: 'grid',
            headStyles: { fillColor: [243, 244, 246], textColor: [55, 65, 81], fontStyle: 'bold', halign: 'center' },
            bodyStyles: { fontSize: 8 },
            columnStyles: { 0: { halign: 'center' } },
            // Estructura de cabeceras basada en la 2da foto
            head: [["N°", "Verificado", "Nombres", "DNI", "Sucursal", "Tipo", "Fecha Verificado"]],
            body: datos.map((d, index) => {
                // Hacemos un mapeo seguro dependiendo de lo que devuelva el SP
                const nombreCompleto = d.NOMBRE || d.PERSONAL || `${d.nombres ?? ''} ${d.apellido1 ?? ''} ${d.apellido2 ?? ''}`.trim();

                // 🔥 Usamos directamente VERIFICADO_CAMBIO y VERIFICADO_FECHA de tu SP
                const verificado = d.VERIFICADO_CAMBIO ? d.VERIFICADO_CAMBIO.toUpperCase() : 'SIN VERIFICAR';
                const fechaVerif = d.VERIFICADO_FECHA && d.VERIFICADO_FECHA !== 'sin cambios'
                    ? d.VERIFICADO_FECHA.replace('T', ' ').substring(0, 16)
                    : '—';

                return [
                    d.NRO || index + 1, // Prioriza el NRO del SP
                    verificado,
                    nombreCompleto,
                    d.dni ?? d.NRO_DOCU_IDEN ?? '',
                    d.sucursal ?? d.SUCURSAL ?? '',
                    d.tipoPer ?? d.TIPO_PER ?? '',
                    fechaVerif
                ];
            }),
            didDrawPage: function (dataPage) {
                if (dataPage.pageNumber !== 1) return; // Solo dibuja cards en la primera página

                if (logoBase64) doc.addImage(logoBase64, 'PNG', 14, 10, 40, 12);

                // Títulos
                doc.setFontSize(10);
                doc.setTextColor(180, 0, 0);
                doc.setFont("helvetica", "bold");
                doc.text("SISTEMA INTEGRADO SOLMAR – SISOL WEB", totalWidth / 2, 14, { align: "center" });

                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text(tituloReporte, totalWidth / 2, 20, { align: "center" });

                const subtitulo = txtSucursal === 'TODAS LAS SUCURSALES' ? '' : `Sol ${txtSucursal}`;
                if (subtitulo !== '') doc.text(subtitulo, totalWidth / 2, 26, { align: "center" });

                doc.setFontSize(8);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 100, 100);
                doc.text(`Generado: ${fechaStr}`, totalWidth - 14, 14, { align: "right" });

                // Dibujando las 3 Cards superiores
                const cardW = 45; const cardH = 18; const gap = 10;
                const totalCardsW = (cardW * 3) + (gap * 2);
                const startX = (totalWidth - totalCardsW) / 2;
                const cardY = 32;

                const cards = [
                    { title: "Total", value: totalRegistros, color: [75, 85, 99] }, // Gris
                    { title: "Sin verificar", value: totalSinVerificar, color: [202, 138, 4] }, // Amarillo
                    { title: "Verificados", value: totalVerificados, color: [4, 120, 87] } // Verde
                ];

                cards.forEach((card, i) => {
                    const x = startX + (i * (cardW + gap));
                    doc.setFillColor(...card.color);
                    doc.roundedRect(x, cardY, cardW, cardH, 2, 2, 'F');
                    doc.setTextColor(255, 255, 255);
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(16);
                    doc.text(String(card.value), x + (cardW / 2), cardY + 10, { align: "center" });
                    doc.setFontSize(8);
                    doc.setFont("helvetica", "normal");
                    doc.text(card.title, x + (cardW / 2), cardY + 15, { align: "center" });
                });
            }
        });

        const fStr = `${String(f.getDate()).padStart(2, '0')}_${String(f.getMonth() + 1).padStart(2, '0')}_${f.getFullYear()}`;
        doc.save(`Etapa2_Verificacion_${estadoArchivo}_${tipoArchivo}_${txtSucursal.replace(/ /g, '_')}_${fStr}.pdf`);
        Swal.close();

    } catch (error) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'Error', text: 'Problema al generar el reporte PDF de la etapa 2.' });
    }
}); // <--- AQUÍ CERRAMOS CORRECTAMENTE EL EVENTO CLICK DEL REPORTE

// ============================================================
// BIOMÉTRICO (Idéntico a chargeFile.js)
// ============================================================
window.addEventListener('solicitarBiometrico', function (e) {
    const { codigo, persona } = e.detail;

    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    axios.get(`${VITE_URL_APP}/api/get-biometrico/${codigo}`)
        .then(response => {
            Swal.close();
            const data = response.data;
            document.getElementById('modal-bio-title').textContent = persona;

            // Renderizamos DNI, huellas y firmas
            document.getElementById('bio-huella-antigua').innerHTML = renderImagen(data.huella_antigua);
            document.getElementById('bio-huella-nueva').innerHTML = renderImagen(data.huella_nueva);
            document.getElementById('bio-firma-antigua').innerHTML = renderImagen(data.firma_antigua);
            document.getElementById('bio-firma-nueva').innerHTML = renderImagen(data.firma_nueva);
            document.getElementById('bio-doc-dni-antiguo').innerHTML = renderImagen(data.dni_anverso_antigua, true, data.dni_reverso_antigua);
            document.getElementById('bio-doc-firma-nueva').innerHTML = renderImagen(data.firma_nueva);
            document.getElementById('bio-doc-huella-nueva').innerHTML = renderImagen(data.huella_nueva);

            // === AQUÍ INYECTAMOS LA COLUMNA DE LA FOTO DINÁMICAMENTE ===
            const dniDiv = document.getElementById('bio-doc-dni-antiguo');
            if (dniDiv) {
                // Seleccionamos la grilla que contiene el DNI y las Huellas
                const gridContainer = dniDiv.parentElement.parentElement;

                // Le cambiamos el diseño de 2 a 3 columnas (DNI más ancho, Foto y Huellas iguales)
                gridContainer.style.gridTemplateColumns = '2fr 1fr 1fr';

                // Verificamos si ya creamos la caja antes para no duplicarla si hacen click varias veces
                let cajaFoto = document.getElementById('caja-foto-inyectada');
                if (!cajaFoto) {
                    cajaFoto = document.createElement('div');
                    cajaFoto.id = 'caja-foto-inyectada';
                    // Lo insertamos justo en el medio, antes de la columna de las huellas
                    gridContainer.insertBefore(cajaFoto, gridContainer.lastElementChild);
                }

                const fotoUrl = `http://190.116.178.163/Biblioteca_Grafica/Fotos/${codigo}.jpg?v=${new Date().getTime()}`;

                cajaFoto.innerHTML = `
                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                        <i class="fa fa-user" style="color:#6366f1; font-size:12px;"></i>
                        <span style="font-size:12px; font-weight:600; color:#374151;">FOTO</span>
                        <span style="font-size:10px; color:#9ca3af; font-weight:500; margin-left:2px;">ROSTRO</span>
                    </div>
                    <div style="border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,0.06);width:100%;">
                        <div style="position:relative;width:100%;height:420px;background:#f8fafc;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                            <img id="foto_rostro_${codigo}" src="${fotoUrl}" 
                                 style="max-width:100%;max-height:100%;width:95%;height:auto;object-fit:contain;display:block;cursor:zoom-in;" 
                                 onclick="if(window.abrirLightbox) abrirLightbox('foto_rostro_${codigo}')"
                                 onerror="this.parentElement.innerHTML='<div style=\\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;flex-direction:column;gap:6px;\\'><svg width=32 height=32 fill=none stroke=currentColor stroke-width=1.5 viewBox=\\'0 0 24 24\\'><rect x=3 y=3 width=18 height=18 rx=3/><circle cx=8.5 cy=8.5 r=1.5/><path d=\\'m21 15-5-5L5 21\\'/></svg>Sin foto en servidor</div>'" />
                        </div>
                    </div>
                `;
            }

            window.bioSwitchTab('doc');

            // Ocultar menú superior de pestañas
            const menuPestañas = document.getElementById('bio-tab-fh')?.parentElement;
            if (menuPestañas) {
                menuPestañas.style.display = 'none';
            }

            document.getElementById('btn-modal-biometrico').click();
        })
        .catch(() => Swal.fire({ title: 'Error al obtener biométrico', icon: 'error' }));
});

window.bioSwitchTab = function (tab) {
    const esFH = tab === 'fh';
    const panelFh = document.getElementById('bio-panel-fh');
    const panelDoc = document.getElementById('bio-panel-doc');

    if (panelFh) panelFh.style.display = esFH ? 'flex' : 'none';
    if (panelDoc) panelDoc.style.display = esFH ? 'none' : 'block';

    const tabFh = document.getElementById('bio-tab-fh');
    const tabDoc = document.getElementById('bio-tab-doc');

    if (tabFh) {
        tabFh.classList.toggle('border-indigo-500', esFH);
        tabFh.classList.toggle('text-indigo-600', esFH);
        tabFh.classList.toggle('border-transparent', !esFH);
        tabFh.classList.toggle('text-gray-500', !esFH);
    }

    if (tabDoc) {
        tabDoc.classList.toggle('border-indigo-500', !esFH);
        tabDoc.classList.toggle('text-indigo-600', !esFH);
        tabDoc.classList.toggle('border-transparent', esFH);
        tabDoc.classList.toggle('text-gray-500', esFH);
    }
};
// ============================================================
// RENDER DE IMÁGENES (biométrico) hola
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

    // (Aquí va el mismo innerHTML larguísimo del Lightbox que ya tenías, no le borres nada)
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
