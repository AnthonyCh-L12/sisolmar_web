import axios from 'axios';
import Swal from 'sweetalert2';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import 'tabulator-tables/dist/css/tabulator_simple.min.css';
import Tagify from '@yaireo/tagify';
import '@yaireo/tagify/dist/tagify.css';

import { generarDeclaracionJuradaPDF, generarReporteFaltantesPDF } from './dj_pdf.js';

const API_URL = `${VITE_URL_APP}/api`;
let registroSeleccionado = null;
let generadosCache = {};
let generadosCacheLoaded = false;
let fotoSeleccionada = null;   // ← archivo de foto seleccionado por el usuario
let codiPersActual = '';       // ← codi_pers de la DJ abierta actualmente

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

const PAUSA_ENTRE_REGISTROS = 200;

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

    cargarGeneradosCache();

    // === LÓGICA DEL MODAL SUBIR DJ (Migrado) ===
    const archivoDJ_E4C = document.getElementById('archivoDJ_E4C');
    const zonaDropDJ_E4C = document.getElementById('zonaDropDJ_E4C');
    const listaArchivosDJ_E4C = document.getElementById('listaArchivosDJ_E4C');
    const btnVistaPreviaDJ_E4C = document.getElementById('btn-vista-previa-dj_E4C');
    const iframeVistaPreviaDJ_E4C = document.getElementById('iframe-vista-previa-dj_E4C');
    const nombreVistaPreviaDJ_E4C = document.getElementById('nombre-archivo-vista-previa-dj_E4C');
    const btnExtraerFirmaHuellaDJ_E4C = document.getElementById('btn-extraer-firmahuella-dj_E4C');
    const panelFirmaHuellaDJ_E4C = document.getElementById('panel-resultados-firmahuella-dj_E4C');
    const imagenFirmaExtraidaDJ_E4C = document.getElementById('imagen-firma-extraida-dj_E4C');
    const imagenHuellaExtraidaDJ_E4C = document.getElementById('imagen-huella-extraida-dj_E4C');
    const placeholderFirmaDJ_E4C = document.getElementById('placeholder-firma-extraida-dj_E4C');
    const placeholderHuellaDJ_E4C = document.getElementById('placeholder-huella-extraida-dj_E4C');
    const btnVistaPreviaFirmaExtraidaDJ_E4C = document.getElementById('btn-vista-previa-firma-extraida-dj_E4C');
    const btnVistaPreviaHuellaExtraidaDJ_E4C = document.getElementById('btn-vista-previa-huella-extraida-dj_E4C');
    const modalVistaPreviaBiometrico_E4C = document.getElementById('modal-vista-previa-biometrico_E4C');
    const btnCerrarVistaPreviaBiometricoX_E4C = document.getElementById('btn-cerrar-vista-previa-biometrico-x_E4C');
    const btnCerrarVistaPreviaBiometrico_E4C = document.getElementById('btn-cerrar-vista-previa-biometrico_E4C');
    const imagenVistaPreviaBiometrico_E4C = document.getElementById('imagen-vista-previa-biometrico_E4C');
    const tituloVistaPreviaBiometrico_E4C = document.getElementById('titulo-vista-previa-biometrico_E4C');
    const btnGuardarDjBiometricos_E4C = document.getElementById('btn-guardar-dj-biometricos_E4C');
    let urlVistaPreviaDJ_E4C = null;
    let botonVistaPreviaBiometricoOrigen_E4C = null;

    let ultimoContextoMenu = null; // Guardará los datos para poder regresar al menú

    zonaDropDJ_E4C?.addEventListener('click', () => archivoDJ_E4C.click());

    // Abstracción del menú SweetAlert
    window.abrirMenuOpcionesArchivo = function(codiPers, nombre, tieneDJ) {
        Swal.fire({
            title: 'Opciones de Archivo',
            html: `
                <div class="flex flex-col gap-3 mt-4">
                    <button id="swal-btn-subir" class="btn border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors font-medium text-sm shadow-sm">
                        <i class="bx bx-upload text-xl"></i> Subir DJ
                    </button>
                    ${tieneDJ ? `
                    <button id="swal-btn-ver" class="btn border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors font-medium text-sm shadow-sm">
                        <i class="bx bx-show text-xl"></i> Ver DJ Escaneado
                    </button>
                    <button id="swal-btn-bio" class="btn border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white px-4 py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors font-medium text-sm shadow-sm">
                        <i class="bx bx-fingerprint text-xl"></i> Validación Biométrica
                    </button>
                    ` : ''}
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            width: '320px',
            customClass: { popup: 'rounded-2xl', title: 'text-lg font-bold text-gray-700 border-b pb-3' },
            didOpen: () => {
                // Evento: Subir DJ
                document.getElementById('swal-btn-subir').addEventListener('click', () => {
                    Swal.close();
                    if (typeof abrirModalSubirDJ_E4C === 'function') {
                        abrirModalSubirDJ_E4C(codiPers, nombre, tieneDJ);
                    }
                });
                
                // Evento: Ver DJ Escaneado
                const btnVer = document.getElementById('swal-btn-ver');
                if (btnVer) {
                    btnVer.addEventListener('click', () => {
                        Swal.close();
                        window.open(`${VITE_URL_APP}/ver-dj/${codiPers}`, '_blank');
                    });
                }

                // Evento: Validación Biométrica (Nuevo)
                const btnBio = document.getElementById('swal-btn-bio');
                if (btnBio) {
                    btnBio.addEventListener('click', () => {
                        Swal.close();
                        // Disparamos el CustomEvent que ya existe abajo en el código
                        const event = new CustomEvent('solicitarBiometrico', {
                            detail: { codigo: codiPers, persona: nombre }
                        });
                        window.dispatchEvent(event);
                    });
                }
            }
        });
    };

    window.abrirModalSubirDJ_E4C = function(codigo, nombre, tieneDJ = true) {
        ultimoContextoMenu = { codigo, nombre, tieneDJ }; // Guardamos el contexto
        document.getElementById('codPersonalDJ_E4C').value = codigo;
        document.querySelector('.nombre-personal_E4C').textContent = nombre ?? '';
        limpiarModal_E4C();
        document.getElementById('btn-modal-dj_E4C').click();
    };

    // Eventos para regresar al menú si cancelan o cierran el modal (El timeout es para esperar que cierre la ventana actual)
    document.getElementById('btn-cancelar-dj_E4C')?.addEventListener('click', () => {
        if (ultimoContextoMenu) {
            setTimeout(() => { window.abrirMenuOpcionesArchivo(ultimoContextoMenu.codigo, ultimoContextoMenu.nombre, ultimoContextoMenu.tieneDJ); }, 350);
        }
    });
    
    // (Opcional) Si también le dan clic a la 'X' superior del modal
    document.getElementById('btn-modal-dj-close_E4C')?.addEventListener('click', () => {
        if (ultimoContextoMenu) {
            setTimeout(() => { window.abrirMenuOpcionesArchivo(ultimoContextoMenu.codigo, ultimoContextoMenu.nombre, ultimoContextoMenu.tieneDJ); }, 350);
        }
    });

    function limpiarModal_E4C() {
        const f = new Date();
        const hoy = `${f.getFullYear()}-${String(f.getMonth() + 1).padStart(2, '0')}-${String(f.getDate()).padStart(2, '0')}`;
        const el = document.getElementById('fecha_emision_dj_E4C');
        if (el) {
            el.value = hoy;
            el.readOnly = true;
            el.classList.add('bg-gray-100', 'cursor-not-allowed');
        }
        if (urlVistaPreviaDJ_E4C) {
            URL.revokeObjectURL(urlVistaPreviaDJ_E4C);
            urlVistaPreviaDJ_E4C = null;
        }
        if (iframeVistaPreviaDJ_E4C) iframeVistaPreviaDJ_E4C.src = 'about:blank';
        if (nombreVistaPreviaDJ_E4C) nombreVistaPreviaDJ_E4C.textContent = '';
        if (btnVistaPreviaDJ_E4C) btnVistaPreviaDJ_E4C.disabled = true;
        if (btnExtraerFirmaHuellaDJ_E4C) btnExtraerFirmaHuellaDJ_E4C.disabled = true;
        if (btnGuardarDjBiometricos_E4C) btnGuardarDjBiometricos_E4C.disabled = true;
        if (panelFirmaHuellaDJ_E4C) panelFirmaHuellaDJ_E4C.classList.add('hidden');
        if (imagenFirmaExtraidaDJ_E4C) { imagenFirmaExtraidaDJ_E4C.src = ''; imagenFirmaExtraidaDJ_E4C.classList.add('hidden'); }
        if (imagenHuellaExtraidaDJ_E4C) { imagenHuellaExtraidaDJ_E4C.src = ''; imagenHuellaExtraidaDJ_E4C.classList.add('hidden'); }
        if (btnVistaPreviaFirmaExtraidaDJ_E4C) btnVistaPreviaFirmaExtraidaDJ_E4C.disabled = true;
        if (btnVistaPreviaHuellaExtraidaDJ_E4C) btnVistaPreviaHuellaExtraidaDJ_E4C.disabled = true;
        if (imagenVistaPreviaBiometrico_E4C) imagenVistaPreviaBiometrico_E4C.src = '';
        if (placeholderFirmaDJ_E4C) placeholderFirmaDJ_E4C.classList.remove('hidden');
        if (placeholderHuellaDJ_E4C) placeholderHuellaDJ_E4C.classList.remove('hidden');
        if (archivoDJ_E4C) archivoDJ_E4C.value = '';
        if (listaArchivosDJ_E4C) listaArchivosDJ_E4C.innerHTML = '';
    }

    archivoDJ_E4C?.addEventListener('change', function () {
        const archivos = Array.from(this.files);
        if (urlVistaPreviaDJ_E4C) {
            URL.revokeObjectURL(urlVistaPreviaDJ_E4C);
            urlVistaPreviaDJ_E4C = null;
        }
        if (btnVistaPreviaDJ_E4C) btnVistaPreviaDJ_E4C.disabled = true;
        if (btnExtraerFirmaHuellaDJ_E4C) btnExtraerFirmaHuellaDJ_E4C.disabled = true;
        if (btnGuardarDjBiometricos_E4C) btnGuardarDjBiometricos_E4C.disabled = true;
        if (panelFirmaHuellaDJ_E4C) panelFirmaHuellaDJ_E4C.classList.add('hidden');
        if (imagenFirmaExtraidaDJ_E4C) { imagenFirmaExtraidaDJ_E4C.src = ''; imagenFirmaExtraidaDJ_E4C.classList.add('hidden'); }
        if (imagenHuellaExtraidaDJ_E4C) { imagenHuellaExtraidaDJ_E4C.src = ''; imagenHuellaExtraidaDJ_E4C.classList.add('hidden'); }
        if (btnVistaPreviaFirmaExtraidaDJ_E4C) btnVistaPreviaFirmaExtraidaDJ_E4C.disabled = true;
        if (btnVistaPreviaHuellaExtraidaDJ_E4C) btnVistaPreviaHuellaExtraidaDJ_E4C.disabled = true;
        if (imagenVistaPreviaBiometrico_E4C) imagenVistaPreviaBiometrico_E4C.src = '';
        if (placeholderFirmaDJ_E4C) placeholderFirmaDJ_E4C.classList.remove('hidden');
        if (placeholderHuellaDJ_E4C) placeholderHuellaDJ_E4C.classList.remove('hidden');
        if (!archivos.length) {
            listaArchivosDJ_E4C.innerHTML = '';
            return;
        }
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

        urlVistaPreviaDJ_E4C = URL.createObjectURL(archivos[0]);
        if (btnVistaPreviaDJ_E4C) btnVistaPreviaDJ_E4C.disabled = false;
        if (btnExtraerFirmaHuellaDJ_E4C) btnExtraerFirmaHuellaDJ_E4C.disabled = false;
    });

    // La vista previa trabaja solo con el archivo local del navegador: no hace POST ni lo guarda.
    document.getElementById('formSubirDJ_E4C')?.addEventListener('submit', e => e.preventDefault());

    btnVistaPreviaDJ_E4C?.addEventListener('click', () => {
        const archivo = archivoDJ_E4C?.files?.[0];
        if (!archivo || !urlVistaPreviaDJ_E4C) return;
        if (nombreVistaPreviaDJ_E4C) nombreVistaPreviaDJ_E4C.textContent = archivo.name;
        if (iframeVistaPreviaDJ_E4C) iframeVistaPreviaDJ_E4C.src = `${urlVistaPreviaDJ_E4C}#zoom=page-fit`;
        document.getElementById('btn-modal-vista-previa-dj_E4C')?.click();
    });

    document.getElementById('btn-cerrar-vista-previa-dj_E4C')?.addEventListener('click', () => {
        if (iframeVistaPreviaDJ_E4C) iframeVistaPreviaDJ_E4C.src = 'about:blank';
    });

    function abrirVistaPreviaBiometrico_E4C(imagen, etiqueta) {
        const src = imagen?.src;
        if (!src?.startsWith('data:image/')) return;
        if (tituloVistaPreviaBiometrico_E4C) tituloVistaPreviaBiometrico_E4C.textContent = `Vista previa de ${etiqueta}`;
        if (imagenVistaPreviaBiometrico_E4C) {
            imagenVistaPreviaBiometrico_E4C.src = src;
            imagenVistaPreviaBiometrico_E4C.alt = `${etiqueta} completa`;
        }
        botonVistaPreviaBiometricoOrigen_E4C = document.activeElement;
        modalVistaPreviaBiometrico_E4C?.classList.remove('hidden');
        btnCerrarVistaPreviaBiometricoX_E4C?.focus();
    }

    function cerrarVistaPreviaBiometrico_E4C() {
        modalVistaPreviaBiometrico_E4C?.classList.add('hidden');
        if (imagenVistaPreviaBiometrico_E4C) imagenVistaPreviaBiometrico_E4C.src = '';
        botonVistaPreviaBiometricoOrigen_E4C?.focus();
        botonVistaPreviaBiometricoOrigen_E4C = null;
    }

    btnVistaPreviaFirmaExtraidaDJ_E4C?.addEventListener('click', () => {
        abrirVistaPreviaBiometrico_E4C(imagenFirmaExtraidaDJ_E4C, 'firma');
    });
    btnVistaPreviaHuellaExtraidaDJ_E4C?.addEventListener('click', () => {
        abrirVistaPreviaBiometrico_E4C(imagenHuellaExtraidaDJ_E4C, 'huella');
    });
    btnCerrarVistaPreviaBiometricoX_E4C?.addEventListener('click', cerrarVistaPreviaBiometrico_E4C);
    btnCerrarVistaPreviaBiometrico_E4C?.addEventListener('click', cerrarVistaPreviaBiometrico_E4C);
    modalVistaPreviaBiometrico_E4C?.addEventListener('click', event => {
        if (event.target === modalVistaPreviaBiometrico_E4C) cerrarVistaPreviaBiometrico_E4C();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modalVistaPreviaBiometrico_E4C && !modalVistaPreviaBiometrico_E4C.classList.contains('hidden')) {
            event.stopImmediatePropagation();
            cerrarVistaPreviaBiometrico_E4C();
        }
    }, true);

    btnExtraerFirmaHuellaDJ_E4C?.addEventListener('click', async () => {
        const archivo = archivoDJ_E4C?.files?.[0];
        if (!archivo || btnExtraerFirmaHuellaDJ_E4C.disabled) return;

        const etiquetaOriginal = '<i class="bx bx-fingerprint text-lg"></i> Extraer Firma y Huella';
        btnExtraerFirmaHuellaDJ_E4C.disabled = true;
        btnExtraerFirmaHuellaDJ_E4C.innerHTML = '<i class="bx bx-loader-alt bx-spin text-lg"></i> Extrayendo...';
        panelFirmaHuellaDJ_E4C?.classList.remove('hidden');
        imagenFirmaExtraidaDJ_E4C?.classList.add('hidden');
        imagenHuellaExtraidaDJ_E4C?.classList.add('hidden');
        if (btnVistaPreviaFirmaExtraidaDJ_E4C) btnVistaPreviaFirmaExtraidaDJ_E4C.disabled = true;
        if (btnVistaPreviaHuellaExtraidaDJ_E4C) btnVistaPreviaHuellaExtraidaDJ_E4C.disabled = true;
        placeholderFirmaDJ_E4C?.classList.remove('hidden');
        placeholderHuellaDJ_E4C?.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('pdf', archivo);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await axios.post(`${VITE_URL_APP}/dj/extraer-firma-huella`, formData, {
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });

            if (!response.data?.success || !response.data?.data?.firma || !response.data?.data?.huella) {
                throw new Error(response.data?.message || 'No se pudieron extraer ambos recuadros.');
            }

            imagenFirmaExtraidaDJ_E4C.src = response.data.data.firma;
            imagenHuellaExtraidaDJ_E4C.src = response.data.data.huella;
            imagenFirmaExtraidaDJ_E4C.classList.remove('hidden');
            imagenHuellaExtraidaDJ_E4C.classList.remove('hidden');
            if (btnVistaPreviaFirmaExtraidaDJ_E4C) btnVistaPreviaFirmaExtraidaDJ_E4C.disabled = false;
            if (btnVistaPreviaHuellaExtraidaDJ_E4C) btnVistaPreviaHuellaExtraidaDJ_E4C.disabled = false;
            placeholderFirmaDJ_E4C.classList.add('hidden');
            placeholderHuellaDJ_E4C.classList.add('hidden');
            if (btnGuardarDjBiometricos_E4C) btnGuardarDjBiometricos_E4C.disabled = false;
        } catch (error) {
            // El botón permanece visible para indicar el flujo de guardado, pero sigue
            // deshabilitado hasta que ambos recortes estén listos.
            if (btnGuardarDjBiometricos_E4C) btnGuardarDjBiometricos_E4C.disabled = true;
            Swal.fire({
                icon: 'warning',
                title: 'Cargar Nuevo Escaneo e Inténtelo de Nuevo',
                text: 'No se pudo recortar correctamente la firma, la huella o ambas. Cargue un nuevo escaneo e inténtelo de nuevo.',
            });
        } finally {
            btnExtraerFirmaHuellaDJ_E4C.disabled = !archivoDJ_E4C?.files?.length;
            btnExtraerFirmaHuellaDJ_E4C.innerHTML = etiquetaOriginal;
        }
    });

    btnGuardarDjBiometricos_E4C?.addEventListener('click', async () => {
        const pdf = archivoDJ_E4C?.files?.[0];
        const codigo = document.getElementById('codPersonalDJ_E4C')?.value?.trim();
        const firmaData = imagenFirmaExtraidaDJ_E4C?.src;
        const huellaData = imagenHuellaExtraidaDJ_E4C?.src;
        if (!pdf || !codigo || !firmaData?.startsWith('data:image/') || !huellaData?.startsWith('data:image/')) return;

        const aArchivo = dataUrl => {
            const [cabecera, contenido] = dataUrl.split(',');
            const tipo = cabecera.match(/data:(.*?);base64/)?.[1] || 'image/jpeg';
            const binario = atob(contenido);
            const bytes = new Uint8Array(binario.length);
            for (let i = 0; i < binario.length; i++) bytes[i] = binario.charCodeAt(i);
            return new Blob([bytes], { type: tipo });
        };

        const botonOriginal = '<i class="bx bx-save text-lg"></i> Guardar DJ y biométricos';
        btnGuardarDjBiometricos_E4C.disabled = true;
        btnGuardarDjBiometricos_E4C.innerHTML = '<i class="bx bx-loader-alt bx-spin text-lg"></i> Guardando...';

        try {
            const formData = new FormData();
            formData.append('codPersonal', codigo);
            formData.append('fecha_emision', document.getElementById('fecha_emision_dj_E4C')?.value || '');
            formData.append('pdf', pdf, pdf.name);
            formData.append('firma', aArchivo(firmaData), `${codigo}.jpg`);
            formData.append('huella', aArchivo(huellaData), `${codigo}.jpg`);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await axios.post(`${VITE_URL_APP}/dj/guardar-escaneada-y-biometricos`, formData, {
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });

            if (!response.data?.success) throw new Error(response.data?.message || 'No se pudieron guardar los archivos.');

            if (ultimoContextoMenu) ultimoContextoMenu.tieneDJ = true;
            document.getElementById('btn-cerrar-vista-previa-dj_E4C')?.click();
            limpiarModal_E4C();
            document.getElementById('btn-modal-dj-close_E4C')?.click();
            getPersonal();
            Swal.fire({ icon: 'success', title: 'DJ y biométricos guardados', text: response.data.message, timer: 2500, showConfirmButton: false });
        } catch (error) {
            const mensaje = error.response?.data?.message || error.message || 'No se pudieron guardar los archivos.';
            Swal.fire({ icon: 'error', title: 'Error al guardar', text: mensaje });
        } finally {
            btnGuardarDjBiometricos_E4C.innerHTML = botonOriginal;
            btnGuardarDjBiometricos_E4C.disabled = !imagenFirmaExtraidaDJ_E4C?.src?.startsWith('data:image/')
                || !imagenHuellaExtraidaDJ_E4C?.src?.startsWith('data:image/');
        }
    });

    // === FIN LÓGICA DEL MODAL SUBIR DJ ===

    // Cache stale-while-revalidate para las vistas de personal
    // Cache stale-while-revalidate para las vistas de personal
    const _personalCache = { SI: null, NO: null, TODOS: null, _tsSI: 0, _tsNO: 0, _tsTODOS: 0 };
    const _CACHE_TTL = 90_000; // 90 segundos

    // === INICIO LÓGICA CUSTOM SELECT CARGO ===
    let cargosDisponibles = [];

    function renderCustomCargoOptions(cargosLista) {
        const contenedor = document.getElementById('listaCargosPEN');
        if (!contenedor) return;
        contenedor.innerHTML = '';

        const optTodos = document.createElement('div');
        optTodos.className = 'px-3 py-1.5 cursor-pointer text-gray-700 hover-select-nativo text-sm';
        optTodos.textContent = 'Todos';
        optTodos.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('filtroCargoPEN').value = '';
            contenedor.classList.add('hidden');
            aplicarFiltrosPEN();
        });
        contenedor.appendChild(optTodos);

        cargosLista.forEach(cargo => {
            const div = document.createElement('div');
            // Cambiamos "truncate" por "whitespace-nowrap" para que el texto no se corte
            div.className = 'px-3 py-1.5 cursor-pointer text-gray-700 hover-select-nativo text-sm whitespace-nowrap';
            div.textContent = cargo;
            div.title = cargo;
            div.addEventListener('click', (e) => {
                e.stopPropagation();
                document.getElementById('filtroCargoPEN').value = cargo;
                contenedor.classList.add('hidden');
                aplicarFiltrosPEN();
            });
            contenedor.appendChild(div);
        });
    }
    // === FIN LÓGICA CUSTOM SELECT CARGO ===

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

    // ── Tabla 1: Pendientes/Listos ───────────────────────────
    const tblPersonas = new Tabulator("#tblPersonas", {
        height: "100%",
        layout: "fitColumns",
        responsiveLayout: "collapse",
        pagination: true,
        paginationSize: 20,
        selectable: true,
        rowHeader: { formatter: "responsiveCollapse", width: 30, minWidth: 30, hozAlign: "center", resizable: false, headerSort: false },
        locale: "es",
        rowFormatter: function(row) {
            const data = row.getData();
            const cesado = !!(data.vigencia && data.vigencia.toString().trim().toUpperCase() === 'NO');
            const el = row.getElement();

            // Estado visual: blanco por defecto, rojo si esta cesado.
            el.classList.toggle('rep-cesado', cesado);

            // Limpia fondos inline heredados (el antiguo "hack" de pintar cada celda)
            el.style.removeProperty('background-color');
            row.getCells().forEach(function(cell) {
                cell.getElement().style.removeProperty('background-color');
            });
        },

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
                title: "",
                formatter: "rowSelection",
                titleFormatter: "rowSelection",
                hozAlign: "center",
                headerSort: false,
                width: 40,
            },
            { 
                title: "N°", 
                field: "nro_fila_estatico", 
                formatter: function() { return ""; }, 
                hozAlign: "center", 
                width: 60, 
                headerSort: false, 
                responsive: false 
            },
            { title: "Codigo", field: "codPersonal", hozAlign: "center", width: 80 },
            {
                title: "Apellidos", field: "apellidos", hozAlign: "left", widthGrow: 2,
                formatter: cell => { 
                    const d = cell.getData(); 
                    const apellidos = `${d.apellido1 ?? ''} ${d.apellido2 ?? ''}`.trim();
                    return apellidos ? capitalizeWords(apellidos) : ''; 
                }
            },
            {
                title: "Nombres", field: "NOMB_1", hozAlign: "left", widthGrow: 2,
                formatter: cell => { 
                    const d = cell.getData(); 
                    const nombres = `${d.NOMB_1 ?? ''} ${d.NOMB_2 ?? ''}`.trim();
                    return nombres ? capitalizeWords(nombres) : ''; 
                }
            },

            { title: "DNI", field: "dni", hozAlign: "center", widthGrow: 2 },
            { title: "Sucursal", field: "sucursal", hozAlign: "center", widthGrow: 2 },
            { title: "Cargo", field: "cargo", hozAlign: "left", widthGrow: 2 }, // 🔥 NUEVA COLUMNA 🔥
            {
                title: "Tipo", field: "tipoPer", hozAlign: "center", minWidth: 150, widthGrow: 2,
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

                    return val ? `<span class="inline-flex items-center justify-center rounded-full border ${color} px-3 py-1 text-[11px] font-bold tracking-wider whitespace-nowrap uppercase" style="min-width: 125px;">${val}</span>` : '—';
                }
            },
            {
                title: "Ultimo Cambio", field: "cambio", hozAlign: "center", widthGrow: 3,
                formatter: cell => {
                    const d = cell.getData();
                    if (d.cambio != null) {
                        return `<div class="flex items-center justify-center gap-3 text-sm text-gray-700 whitespace-nowrap">
                            <span class="flex items-center gap-1"><i class='bx bx-calendar text-blue-500'></i> <span>${formatearFechaHora(d.cambio).fecha}</span></span>
                            <span class="flex items-center gap-1"><i class='bx bx-time-five text-orange-500'></i> <span>${formatearFechaHora(d.cambio).hora}</span></span>
                        </div>`.trim();
                    }
                    return `${d.cambio ?? 'Sin cambios'}`.trim();
                }
            },
            {
                title: "Acciones", field: "acciones", hozAlign: "left", headerSort: false, width: 120, minWidth: 120, maxWidth: 120, widthGrow: 0, responsive: false,
                formatter: cell => {
                const btnDJ = `<button type="button" class="btn rounded-full form-btn border border-emerald-400 bg-success/25 text-success hover:bg-success hover:text-white hover:border-emerald-600 px-3 py-1 text-xs whitespace-nowrap flex items-center justify-center">DJ</button>`;

                let html = `<div class="flex items-center gap-2 flex-nowrap">
                    ${btnDJ}
                    <button type="button" class="btn rounded-full bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white px-3 py-1 text-xs flex items-center justify-center border border-blue-500 hover:border-blue-700 shadow-sm transition-colors folder-menu-btn" title="Opciones de Archivo">
                        <i class="bx bx-folder-open text-lg pointer-events-none"></i>
                    </button>
                </div>`;
                
                return html;
            },
            cellClick: (e, cell) => {
                const rowData = cell.getRow().getData();
                const codiPers = rowData.codPersonal || rowData.CODI_PERS || rowData.id;
                const nombre = rowData.personal || rowData.NOMB_1 ? `${rowData.NOMB_1 ?? ''} ${rowData.APEL_1 ?? ''}`.trim() : 'Personal';
                const tieneDJ = true; // Forzado

                // Lógica Menú Flotante: Si hacen clic en la carpetita
                if (e.target.closest('.folder-menu-btn')) {
                    window.abrirMenuOpcionesArchivo(codiPers, nombre, tieneDJ);
                    return;
                }

                    // Lógica Original: Botón DJ
                    const btn = e.target.closest('.form-btn');
                    if (!btn) return;
                    
                    if (rowData.vigencia && rowData.vigencia.toString().trim().toUpperCase() === 'NO') {
                        window.NuevaDJ?.abrirRecontratacion(codiPers, { OBS_CESE: rowData.OBS_CESE, FECH_CESE: rowData.FECH_CESE });
                        return;
                    }

                    registroSeleccionado = rowData;
                    registroSeleccionado._sinSplit = true;
                    personalDataCache.delete(`${codiPers}_pendiente`);
                    personalDataCache.delete(`${codiPers}_migracion`);
                    abrirFormularioDJ(codiPers, 'pendiente');
                }
            },
        ],
    });

    // 🔥 ELIMINAMOS/COMENTAMOS ESTO PARA QUE NO BORRE LA INYECCIÓN
    /* function reformatNums(table) {
        function rf() { table.getRows("active").forEach(r => r.reformat()); }
        table.on("dataLoaded", rf);
        table.on("pageLoaded", rf);
        table.on("dataSorted", () => { table.setPage(1); rf(); });
        table.on("dataFiltered", () => { table.setPage(1); rf(); });
    }
    reformatNums(tblPersonas); */

    tblPersonas.on("renderComplete", function () {
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

    tblPersonas.on("rowClick", function (e, row) {
        if (e.target.closest('.form-btn') || e.target.tagName === 'INPUT') return;
        const data = row.getData();
        if (data.vigencia && data.vigencia.toString().trim().toUpperCase() === 'NO') {
            const codiPers = data.codPersonal || data.CODI_PERS;
            window.NuevaDJ?.abrirRecontratacion(codiPers, { OBS_CESE: data.OBS_CESE, FECH_CESE: data.FECH_CESE });
        }
    });

    tblPersonas.on("rowSelectionChanged", function () {
        const sel = this.getSelectedRows().length;
        const btn = document.getElementById('btnGenerarSeleccionadosPEN');
        const count = document.getElementById('countSelPEN');
        if (count) count.textContent = sel;
        if (btn) {
            btn.disabled = !sel;
            btn.className = sel
                ? 'ml-auto inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors bg-indigo-600 text-white border border-indigo-600 hover:bg-indigo-700 hover:border-indigo-700'
                : 'ml-auto inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors bg-indigo-100 text-indigo-400 border border-indigo-200 cursor-not-allowed';
        }
    });

    // ── Tabla coincidencias ──────────────────────────────────
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
        fotoSeleccionada = null;
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

        fotoSeleccionada = null;
        codiPersActual = '';
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

    const RESALTAR_SKIP_FIELDS = new Set(['migrado', 'estado', 'tipoPer', 'cambio', 'codPersonal']);
    function resaltarTexto(tabla, valor) {
        tabla.getRows().forEach(row => {
            row.getElement().querySelectorAll(".tabulator-cell").forEach((cell, i, cells) => {
                const field = cell.getAttribute('tabulator-field');
                // Saltar: sin field (checkbox, N°), última columna (acciones) y columnas con HTML propio
                if (!field || i === cells.length - 1 || RESALTAR_SKIP_FIELDS.has(field)) return;
                const text = cell.textContent || '';
                if (valor && text.toLowerCase().includes(valor)) {
                    const escaped = valor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    cell.innerHTML = text.replace(new RegExp(`(${escaped})`, "gi"), "<span class='bg-warning/25'>$1</span>");
                } else {
                    cell.innerHTML = text;
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
        selectProv.disabled = true;
        selectDist.disabled = true;
        if (!departamentoId) return;
        try {
            const provincias = await getUbicacionCached({ type: 'prov', dept: departamentoId });
            provincias.forEach(prov => selectProv.add(new Option(prov.text, prov.id)));
            if (selectedProvincia) {
                selectProv.value = selectedProvincia;
                await cargarDistritos(selectDist, selectedProvincia, selectedDistrito);
            }
        } catch (error) {
            console.error("Error cargando provincias:", error);
        } finally {
            selectProv.disabled = false;
            selectDist.disabled = !selectProv.value;
        }
    }

    async function cargarDistritos(selectDist, provinciaId, selectedDistrito = null) {
        if (!selectDist) return;
        selectDist.innerHTML = '<option value="">Seleccionar</option>';
        selectDist.disabled = true;
        if (!provinciaId) return;
        try {
            const distritos = await getUbicacionCached({ type: 'dist', prov: provinciaId });
            distritos.forEach(dist => selectDist.add(new Option(dist.text, dist.id)));
            if (selectedDistrito) selectDist.value = selectedDistrito;
        } catch (error) {
            console.error("Error cargando distritos:", error);
        } finally {
            selectDist.disabled = false;
        }
    }

    if (departamentoSelect && departamentoSelectDni && departamentoSelectNac) {
        getUbicacionCached({ type: 'dept' })
            .then(departamentos => {
                departamentos.forEach(dep => {
                    departamentoSelect.add(new Option(dep.text, dep.id));
                    departamentoSelectDni.add(new Option(dep.text, dep.id));
                    departamentoSelectNac.add(new Option(dep.text, dep.id));
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
    function _aplicarDatosTabla(datosTabla) {
        tblPersonas.setData(datosTabla);
        
        // Guardamos los cargos en la variable global
        cargosDisponibles = [...new Set(datosTabla.map(d => d.cargo).filter(Boolean))].sort();
        
        const inputCargo = document.getElementById('filtroCargoPEN');
        if (inputCargo) {
            const valorPrevio = inputCargo.value;
            
            // Renderizar la lista base
            renderCustomCargoOptions(cargosDisponibles);

            // Validar si el texto actual sigue siendo válido en la nueva lista
            if (valorPrevio && !cargosDisponibles.includes(valorPrevio)) {
                inputCargo.value = '';
            }
        }
        
        aplicarFiltrosPEN();
    }

    async function _fetchPersonal(vigencia) {
        if (!vigencia) return [];
        const res = await axios.get(`${VITE_URL_APP}/get-personal-dj-2026`, { params: { vigencia } });
        const datos = res.data;
        _personalCache[vigencia] = datos;
        _personalCache[`_ts${vigencia}`] = Date.now();
        return datos;
    }

    async function _cargarTodosLosDatos() {
        const [datosSI, datosNO] = await Promise.all([
            _personalCache.SI ? Promise.resolve(_personalCache.SI) : _fetchPersonal('SI'),
            _personalCache.NO ? Promise.resolve(_personalCache.NO) : _fetchPersonal('NO')
        ]);
        return [...(datosSI || []), ...(datosNO || [])];
    }

    function getPersonal(silent = false) {
        const selectVigencia = document.getElementById('filtroVigenciaPEN');
        const vigenciaVal = selectVigencia ? selectVigencia.value : ''; // '' = Todos, 'SI' = Activos, 'NO' = Cesados
        const ahora = Date.now();

        if (vigenciaVal === 'SI' || vigenciaVal === 'NO') {
            const cacheValido = _personalCache[vigenciaVal] && (ahora - _personalCache[`_ts${vigenciaVal}`]) < _CACHE_TTL;
            if (cacheValido) {
                _aplicarDatosTabla(_personalCache[vigenciaVal]);
                _fetchPersonal(vigenciaVal).then(datos => _aplicarDatosTabla(datos)).catch(console.error);
            } else {
                if (!silent) tblPersonas.alert("Buscando datos...", "msg");
                _fetchPersonal(vigenciaVal)
                    .then(datos => _aplicarDatosTabla(datos))
                    .catch(error => console.error("Hubo un error:", error))
                    .finally(() => tblPersonas.clearAlert());
            }
        } else {
            // Caso 'Todos' ('')
            const cacheSIValido = _personalCache.SI && (ahora - _personalCache._tsSI) < _CACHE_TTL;
            const cacheNOValido = _personalCache.NO && (ahora - _personalCache._tsNO) < _CACHE_TTL;

            if (cacheSIValido && cacheNOValido) {
                const todos = [...(_personalCache.SI || []), ...(_personalCache.NO || [])];
                _aplicarDatosTabla(todos);
                _cargarTodosLosDatos().then(todosFrescos => _aplicarDatosTabla(todosFrescos)).catch(console.error);
            } else {
                if (!silent) tblPersonas.alert("Buscando datos...", "msg");
                _cargarTodosLosDatos()
                    .then(todos => _aplicarDatosTabla(todos))
                    .catch(error => console.error("Hubo un error:", error))
                    .finally(() => tblPersonas.clearAlert());
            }
        }
    }


    window.getPersonalSoloDJ = function () {

        axios.get(`${VITE_URL_APP}/get-personal-dj-2026`)
            .then(response => {
                const datosTabla = response.data;
                tblPersonas.setData(datosTabla);

                // 🔥 LLENADO DINÁMICO DEL CUSTOM SELECT DE CARGOS 🔥
                cargosDisponibles = [...new Set(datosTabla.map(d => d.cargo).filter(Boolean))].sort();
                renderCustomCargoOptions(cargosDisponibles);

                aplicarFiltrosPEN();
            })
            .catch(console.error);
    };

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
            (`${data.NOMB_1 ?? ''} ${data.NOMB_2 ?? ''}`).toLowerCase(),
            (data.apellido1 ?? '').toLowerCase(),
            (data.apellido2 ?? '').toLowerCase(),
            (data.dni ?? '').toLowerCase(),
        ];
        return palabras.every(palabra => campos.some(campo => campo.includes(palabra)));
    }

    // ============================================================
    // FILTROS
    // ============================================================
    function aplicarFiltrosPEN() {
        const sucursal = document.getElementById('filtroSucursalPEN')?.value ?? '';
        const tipoPer = document.getElementById('filtroTipoPerPEN')?.value ?? '';
        const cargo = document.getElementById('filtroCargoPEN')?.value ?? ''; // 🔥 Capturar cargo
        const texto = buscarPersonalInput?.value.toLowerCase().trim() ?? '';

        tblPersonas.setFilter((data) => {
            if (sucursal && data.codSucursal !== sucursal) return false;
            if (tipoPer && data.tipoPer !== tipoPer) return false;

            // 🔥 CAMBIO AQUÍ: Filtrado parcial para que funcione como autocomplete
            if (cargo && !(data.cargo || '').toLowerCase().includes(cargo.toLowerCase())) return false;

            if (texto && !matchBusqueda(data, texto)) return false;
            return true;
        });

        // =========================================================================
        // 🔥 LÓGICA DE INDICADORES: Calculamos en base a la data cargada actual
        // =========================================================================
        const todaLaData = tblPersonas.getData();

        // Filtramos solo por los selects (ignoramos la búsqueda de texto para que los totales no se alteren al buscar un nombre)
        const dataParaTarjetas = todaLaData.filter(d => {
            const cumpleSucursal = (sucursal === '') || (d.codSucursal === sucursal);
            const cumpleTipo = (tipoPer === '') || (d.tipoPer === tipoPer);
            const cumpleCargo = (cargo === '') || ((d.cargo || '').toLowerCase().includes(cargo.toLowerCase()));
            return cumpleSucursal && cumpleTipo && cumpleCargo;
        });

        const total = dataParaTarjetas.length;
        const vigentes = dataParaTarjetas.filter(d => (d.vigencia || '').toUpperCase() === 'SI').length;
        const noVigentes = total - vigentes;

        if (document.getElementById('countTotalPen')) document.getElementById('countTotalPen').textContent = total;
        if (document.getElementById('countVigentesPen')) document.getElementById('countVigentesPen').textContent = vigentes;
        if (document.getElementById('countNoVigentesPen')) document.getElementById('countNoVigentesPen').textContent = noVigentes;

        // Barras de proporcion respecto al total
        const pctBarra = n => (total > 0 ? Math.max(n > 0 ? 4 : 0, Math.round((n / total) * 100)) : 0);
        const barTot = document.getElementById('barTotalPen');
        const barVig = document.getElementById('barVigentesPen');
        const barNo  = document.getElementById('barNoVigentesPen');
        if (barTot) barTot.style.width = total > 0 ? '100%' : '0%';
        if (barVig) barVig.style.width = pctBarra(vigentes) + '%';
        if (barNo)  barNo.style.width  = pctBarra(noVigentes) + '%';
    }

    document.getElementById('filtroSucursalPEN')?.addEventListener('change', aplicarFiltrosPEN);
    document.getElementById('filtroTipoPerPEN')?.addEventListener('change', aplicarFiltrosPEN);
    document.getElementById('filtroVigenciaPEN')?.addEventListener('change', getPersonal);

    // Eventos del Custom Select Cargo
    const inputCargo = document.getElementById('filtroCargoPEN');
    const contenedorCargos = document.getElementById('listaCargosPEN');
    const wrapCargo = document.getElementById('custom-select-cargo');

    if (inputCargo && contenedorCargos && wrapCargo) {
        
        // Función auxiliar para renderizar filtrando por el valor actual del input
        const mostrarOpcionesFiltradas = () => {
            const val = inputCargo.value.toLowerCase();
            contenedorCargos.classList.remove('hidden');
            const filtrados = cargosDisponibles.filter(c => c.toLowerCase().includes(val));
            renderCustomCargoOptions(filtrados);
        };

        // Mostrar lista al hacer click o enfocar, respetando lo que ya está escrito
        inputCargo.addEventListener('focus', mostrarOpcionesFiltradas);
        inputCargo.addEventListener('click', mostrarOpcionesFiltradas);

        // Filtrar lista al escribir y actualizar tabla
        inputCargo.addEventListener('input', (e) => {
            mostrarOpcionesFiltradas();
            aplicarFiltrosPEN(); 
        });

        // Ocultar al hacer click afuera
        document.addEventListener('click', (e) => {
            if (!wrapCargo.contains(e.target)) {
                contenedorCargos.classList.add('hidden');
            }
        });
    }

    // Carga inicial (como 'Todos' es el por defecto, traerá activos y cesados juntos)
    // Se espera a 'tableBuilt' porque Tabulator 6 crea la tabla con setTimeout interno:
    // llamar getPersonal() (y su tblPersonas.alert) antes provoca el warning
    // "Table Not Initialized - Calling the alert function before the table is initialized".
    tblPersonas.on('tableBuilt', () => getPersonal());

    document.getElementById('btnGenerarSeleccionadosPEN')?.addEventListener('click', async function () {
        const seleccionadas = tblPersonas.getSelectedRows();
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
        await _generarUnificado(filas, 'DJ_Seleccionados', 'pendiente');
        tblPersonas.deselectRow();
    });

    // ============================================================
    // PESTAÑAS
    // ============================================================
    let tabActiva = 'pendiente';

    // ============================================================
    // BÚSQUEDA Y RESALTADO
    // ============================================================
    let _buscarTimer = null;
    buscarPersonalInput?.addEventListener("input", function () {
        const valor = this.value.toLowerCase().trim();
        clearTimeout(_buscarTimer);
        if (tabActiva === 'pendiente') {
            aplicarFiltrosPEN();
            _buscarTimer = setTimeout(() => resaltarTexto(tblPersonas, valor), 200);
            tblPersonas._ultimoFiltro = valor;
        } else {
            aplicarFiltrosMigracion();
            _buscarTimer = setTimeout(() => resaltarTexto(tblPersonasMigrado, valor), 200);
            tblPersonasMigrado._ultimoFiltro = valor;
        }
    });

    // renderComplete solo re-resalta si hay filtro activo (sin debounce extra porque ya filtramos arriba)
    tblPersonas.on("renderComplete", () => {
        if (tblPersonas._ultimoFiltro) setTimeout(() => resaltarTexto(tblPersonas, tblPersonas._ultimoFiltro), 0);
    });

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
            fotoSeleccionada = file;  // ← guardar referencia
            const reader = new FileReader();
            reader.onload = e => {
                if (preview) { preview.src = e.target.result; preview.classList.remove("hidden"); }
                placeholder?.classList.add("hidden");
                btnEliminar?.classList.remove("hidden");
            };
            reader.readAsDataURL(file);
        }
    });
    btnEliminar?.addEventListener("click", () => { fotoSeleccionada = null; limpiarPreviewFoto(); });

    // Page size
    pageSizeSelect?.addEventListener("change", function () { tblPersonas.setPageSize(parseInt(this.value)); });

    // ── Indicador "Mostrando X–Y de Z" bajo la tabla ─────────────────────
    const tblInfoPEN = document.getElementById('tblInfoPEN');
    let tblInfoPENTimer = null;
    function actualizarInfoFilasPEN() {
        if (!tblInfoPEN) return;
        clearTimeout(tblInfoPENTimer);
        tblInfoPENTimer = setTimeout(function () {
            if (!tblInfoPEN) return;
            const activas = tblPersonas.getRows('active').length;
            if (!activas) { tblInfoPEN.innerHTML = '<b>0</b> filas'; return; }
            const pagina = tblPersonas.getPage();
            if (pagina === 'all') { tblInfoPEN.innerHTML = 'Mostrando <b>' + activas + '</b> filas'; return; }
            const size     = tblPersonas.getPageSize() || 20;
            const totalPag = Math.ceil(activas / size);
            const desde    = ((pagina || 1) - 1) * size + 1;
            const hasta    = Math.min((pagina || 1) * size, activas);
            tblInfoPEN.innerHTML = 'Mostrando <b>' + desde + '–' + hasta + '</b> de <b>' + activas + '</b> filas' +
                (totalPag > 1 ? ' · Página <b>' + pagina + '</b>/' + totalPag : '');
        }, 0);
    }
    ['dataLoaded', 'pageLoaded', 'dataFiltered', 'pageSizeChanged', 'renderComplete'].forEach(function (ev) {
        tblPersonas.on(ev, actualizarInfoFilasPEN);
    });

    // ── Botón "Limpiar búsqueda" ─────────────────────────────────────────
    const btnLimpiarBusquedaPEN = document.getElementById('btnLimpiarBusquedaPEN');
    if (btnLimpiarBusquedaPEN && buscarPersonalInput) {
        const alternarLimpiar = () => btnLimpiarBusquedaPEN.classList.toggle('hidden', !buscarPersonalInput.value);
        buscarPersonalInput.addEventListener('input', alternarLimpiar);
        btnLimpiarBusquedaPEN.addEventListener('click', function () {
            buscarPersonalInput.value = '';
            btnLimpiarBusquedaPEN.classList.add('hidden');
            buscarPersonalInput.dispatchEvent(new Event('input'));
            buscarPersonalInput.focus();
        });
    }

    btnPrevisualizar?.addEventListener("click", async function (e) {
        e.preventDefault();
        e.stopPropagation();
        const camposObligatorios = [{ input: nombreDJtxt, nombre: 'Nombre' }, { input: dniDJtxt, nombre: 'DNI' }];
        const campoFaltante = camposObligatorios.find(c => !c.input || !String(c.input.value ?? '').trim());
        if (campoFaltante) {
            Swal.fire({ icon: 'warning', title: 'Campos obligatorios', text: `Falta completar: ${campoFaltante.nombre}` });
            campoFaltante.input?.focus();
            return;
        }
        try {
            Swal.fire({
                title: 'Generando PDF…',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            const ok = await generarDeclaracionJuradaPDF();
            // Solo cerrar el loading si fue éxito (si hubo error, su popup ya reemplazó al loading)
            if (ok) Swal.close();
        } catch (err) {
            console.error('[Previsualizar] Error generando PDF:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF: ' + (err?.message || err) });
        }
    });

    // País de nacimiento: sincronizar el hidden al escribir en el input
    // (el select de país ya guarda el código directamente)

    // ============================================================
    // GUARDAR FORMULARIO
    // ============================================================
    if (form) {
        console.log('✅ form encontrado, registrando listener submit');

        form.addEventListener('submit', async (e) => {
            console.log('🔥 submit disparado');
            e.preventDefault();
            e.stopPropagation(); // ← AGREGAR ESTO

            console.log('🔍 form element:', form);
            console.log('🔍 form action:', form.action);

            const btnGuardar = document.getElementById('btnGuardar');
            if (btnGuardar) btnGuardar.disabled = true;

            try {
                // Habilitar sucursal temporalmente para que su valor se envíe en el FormData
                const sucursalEl = document.getElementById('sucursal');
                if (sucursalEl) sucursalEl.disabled = false;

                const formData = new FormData(form);

                // Restaurar estado disabled si el usuario no es Admins RRHH
                if (sucursalEl && window.tipoUsuario != 17) sucursalEl.disabled = true;

                // Verificar que formData tiene datos
                console.log('📋 FormData entries:');
                for (let [key, val] of formData.entries()) {
                    console.log(`  ${key}:`, val);
                }

                const data = Object.fromEntries(formData.entries());
                console.log('📦 data object:', data);

                // Construir los arrays familiares por fila evita que nombres,
                // parentescos, fechas e IDs pierdan su correspondencia entre sí.
                const filasFamiliaresPayload = [...document.querySelectorAll('#familyContainer .family-row')];
                const familiaresPayload = {
                    FAM_PARENTESCO: filasFamiliaresPayload.map(fila => fila.querySelector('select[name="parentesco[]"]')?.value ?? ''),
                    FAM_NOMBRES: filasFamiliaresPayload.map(fila => fila.querySelector('input[name="apellidosNombres[]"]')?.value ?? ''),
                    FAM_FECHA_NACI: filasFamiliaresPayload.map(fila => fila.querySelector('input[name="fechaNacimiento[]"]')?.value ?? ''),
                    FAM_ID: filasFamiliaresPayload.map(fila => fila.querySelector('input[name="familiar_id[]"]')?.value ?? ''),
                };

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

                // La decisión de desactivar se solicita antes de guardar cuando un
                // hijo registrado como menor ya cumplió 18 años.
                let desactivarAsignacionFamiliar = false;
                let mantenerAsignacionFamiliar = false;
                const hijosMayorEdad = filasHijosDj().filter(fila =>
                    fila.querySelector('select[name="parentesco[]"]')?.value?.trim().toUpperCase() === 'HIJO MENOR DE EDAD'
                    && !esMenorDeEdadDj(fila.querySelector('input[name="fechaNacimiento[]"]')?.value)
                    && !!fila.querySelector('input[name="fechaNacimiento[]"]')?.value
                );
                if (hijosMayorEdad.length) {
                    const decision = await Swal.fire({
                        icon: 'warning',
                        title: 'El hijo ya cumplió la mayoría de edad',
                        text: '¿Desactivar asignación familiar?',
                        showDenyButton: true,
                        confirmButtonText: 'Sí',
                        denyButtonText: 'No',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                    });
                    desactivarAsignacionFamiliar = decision.isConfirmed;
                    mantenerAsignacionFamiliar = decision.isDenied;
                    if (mantenerAsignacionFamiliar) {
                        for (const fila of hijosMayorEdad) {
                            const st = estadoDniHijo(fila);
                            if (!st.anverso || !st.reverso) {
                                Swal.fire({ icon: 'warning', title: 'DNI del hijo obligatorio', text: 'Para conservar la asignación familiar, vuelva a subir el anverso y el reverso del DNI de cada hijo mayor de edad.', confirmButtonText: 'Entendido' });
                                abrirModalDniHijosDj(fila);
                                if (btnGuardar) btnGuardar.disabled = false;
                                return;
                            }
                        }
                    }
                }
                const payload = {
                    ...data,
                    source: tabActiva,
                    desactivar_asignacion_familiar: desactivarAsignacionFamiliar ? '1' : '0',
                    mantener_asignacion_familiar: mantenerAsignacionFamiliar ? '1' : '0',
                    no_caduca_dni: data.no_caduca_dni === 'on' ? '1' : '0',
                    ...familiaresPayload,
                    // Señala que el listado completo fue enviado, incluso si el usuario
                    // quitó todos los familiares y los arrays quedan vacíos.
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
                const dniAdjuntoGest = { anverso: djDniFiles.anverso || null, reverso: djDniFiles.reverso || null };
                const fotoAdjuntaGest = fotoSeleccionada || null;
                const formDataSave = new FormData();
                Object.keys(payload).forEach(k => {
                    const v = payload[k];
                    if (v === undefined || v === null) return;
                    if (Array.isArray(v)) v.forEach(item => formDataSave.append(`${k}[]`, item ?? ''));
                    else formDataSave.append(k, v);
                });
                if (fotoAdjuntaGest)        formDataSave.append('foto', fotoAdjuntaGest);
                if (dniAdjuntoGest.anverso)  formDataSave.append('dni_anverso', dniAdjuntoGest.anverso);
                if (dniAdjuntoGest.reverso) formDataSave.append('dni_reverso', dniAdjuntoGest.reverso);
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
                if (dniAdjuntoGest.anverso || dniAdjuntoGest.reverso) limpiarDniDj();
                console.log('✅ Response:', response);

                if (response.status === 200 || response.status === 201) {
                    // Subir foto y DNI si fueron seleccionados
                    const fotoFile = fotoSeleccionada;
                    const codiPersFoto = response.data.codi_pers || data.cod_postulante || codiPersActual || '';
                    console.log('[GestionDJ] fotoSeleccionada:', fotoSeleccionada, '| fotoFile:', fotoFile);
                    console.log('[GestionDJ] codiPersFoto:', codiPersFoto, '| codiPersActual:', codiPersActual);
                    const resDniDj0 = await subirDniDj(codiPersFoto);
                    const resDniHijos = await subirDniHijosDj(codiPersFoto);
                    const resDniDj = (!resDniDj0.sinDni && !resDniDj0.ok) ? resDniDj0
                        : ((!resDniHijos.sinDni && !resDniHijos.ok) ? resDniHijos : { ok: true, sinDni: true, pendiente: !!(response.data.dni_pendiente || response.data.foto_pendiente) });
                    if (fotoFile && codiPersFoto && !fotoAdjuntaGest) {
                        try {
                            const fdFoto = new FormData();
                            fdFoto.append('foto', fotoFile);
                            fdFoto.append('codi_pers', codiPersFoto);
                            console.log('[GestionDJ] Enviando foto...', fotoFile.name, fotoFile.size, 'bytes', fotoFile.type);
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                            const resFoto = await axios.post(`${VITE_URL_APP}/api/dj/upload-foto-personal`, fdFoto, {
                                headers: { 'X-CSRF-TOKEN': csrfToken },
                            });
                            console.log('[GestionDJ] Respuesta foto:', resFoto.data);
                            if (resFoto.data.success) {
                                mensajeGuardadoDj(resDniDj, 'La Declaración Jurada y la foto se guardaron correctamente.');
                            } else {
                                Swal.fire({ icon: 'warning', title: 'DJ guardada, pero foto falló', text: resFoto.data.message || 'No se pudo actualizar la foto.' });
                            }
                        } catch (e) {
                            console.error('[GestionDJ] Error subiendo foto:', e.response?.status, e.response?.data);
                            const errMsg = e.response?.data?.message
                                || (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join('<br>') : null)
                                || e.message;
                            Swal.fire({ icon: 'warning', title: 'DJ guardada, pero foto falló', html: errMsg });
                        }
                    } else {
                        console.warn('[GestionDJ] No se sube foto nueva.');
                        mensajeGuardadoDj(resDniDj, 'La Declaración Jurada se guardó correctamente.');
                    }

                    const modal = document.getElementById('modalDjGestion');
                    if (modal) {
                        if (window.HSOverlay) { try { HSOverlay.close(modal); } catch (e) { } }
                        modal.classList.add('hidden');
                        modal.classList.remove('hs-overlay-open');
                        document.querySelectorAll('.hs-overlay-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('overflow-hidden');
                        document.body.style.overflow = '';
                    }

                    // Invalidar cache para que la recarga traiga datos frescos
                    _personalCache.SI = null; _personalCache.NO = null; _personalCache.TODOS = null;
                    const codiPersGuardado = String(response.data.codi_pers || data.cod_postulante || codiPersActual || '').trim();
                    if (codiPersGuardado) {
                        ['pendiente', 'migracion'].forEach(source => {
                            const cacheKey = `${codiPersGuardado}_${source}`;
                            personalDataCache.delete(cacheKey);
                            personalDataPromise.delete(cacheKey);
                        });
                    }
                    getPersonal();
                }
            } catch (error) {
                console.error('❌ Error completo:', error);
                console.error('❌ error.response:', error.response);
                console.error('❌ error.message:', error.message);

                let msg = 'Hubo un error al guardar los datos.';
                if (error.response?.data?.message) msg = error.response.data.message;
                else if (error.response?.data?.errors) msg = Object.values(error.response.data.errors).flat().join('<br>');

                const dataErrGest = error.response?.data || {};
                const faltaDocsGest = dataErrGest.code === 'falta_dni' || dataErrGest.code === 'falta_foto';
                if (faltaDocsGest) {
                    if (dataErrGest.falta_foto) {
                        const inpFotoGest = document.getElementById('inputFoto');
                        if (inpFotoGest) inpFotoGest.classList.add('ring-2', 'ring-red-500');
                    }
                    if (dataErrGest.falta_anverso || dataErrGest.falta_reverso) {
                        ['inputDniAnverso', 'inputDniReverso'].forEach(id => document.getElementById(id)?.classList.add('ring-2', 'ring-red-500'));
                        const modalDniGest = document.getElementById('modalDni');
                        if (modalDniGest) modalDniGest.style.display = 'flex';
                    }
                }

                Swal.fire({
                    icon: 'error',
                    title: faltaDocsGest ? String(dataErrGest.message || 'Falta ingresar foto').replace(/\.$/, '') : 'Error',
                    html: faltaDocsGest ? 'Debe completar los documentos requeridos para poder guardar la DJ.' : msg,
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
                html: `Procesando <b>${i + 1}</b> de <b>${filas.length}</b><br><small>${fila.NOMB_1 ? `${fila.NOMB_1} ${fila.NOMB_2 ?? ''}`.trim() : codiPers}</small>`
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
    // TIPO DE TRABAJADOR
    // ============================================================
    const tipoTrabajadorUi = document.getElementById('tipo_personal_ui');

    // Cargar tipos de personal
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

    // Cambio de tipo de personal → sincroniza hidden y visibilidad
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

    // País de nacimiento: reevalúa la regla de Dep/Prov/Dist
    // (DNI + país ≠ PERU → se ocultan). limpiarCampos = true porque es
    // un cambio manual del usuario.
    document.getElementById('aj_pais')?.addEventListener('change', function () {
        aplicarUbigeoNacimiento(true);
    });

    }); // fin DOMContentLoaded

// ============================================================
// FUNCIONES GLOBALES (fuera del DOMContentLoaded)
// ============================================================

// ── Foto pendiente ───────────────────────────────────────────
// Global (fuera del DOMContentLoaded) para poder llamarla desde
// abrirFormularioDJ()/llenarFormulario(). Sin esto, la foto elegida por el
// usuario se arrastraba de una persona a otra al cambiar de fila: el
// servidor la guardaba con el CODI de la persona nueva y quedaba registrada
// la foto de la persona anterior. (El DNI ya tenía su "pizarra limpia".)
function limpiarFotoPendienteDj() {
    fotoSeleccionada = null;
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

            if (source === 'migracion') await cargarDatosBackup(codiPers);
            else limpiarSplitView();
        }

    } catch (error) {
        console.error('Error:', error);
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el formulario: ' + error.message });
    }
}

// ── Catálogos ────────────────────────────────────────────────
let catalogosCache = null;
let catalogosPromise = null;

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
        const res = await axios.post(`${VITE_URL_APP}/api/dj/upload-dni-personal`, fd, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
        const ok = !!res.data?.success;
        // Limpiar selección tras subir: nunca arrastrar el DNI de una persona a otra
        if (ok) limpiarDniDj();
        return { ok, message: res.data?.message };
    } catch (err) {
        console.error('[GestionDJ] Error subiendo DNI:', err?.response?.data || err);
        return { ok: false, message: err?.response?.data?.message || 'Error al subir el DNI.' };
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

// ── Países de nacimiento (select desde ADMI_PAIS) ────────────
let paisesDataGest = [];
let paisesPromiseGest = null;

async function cargarPaisesGest() {
    if (paisesDataGest.length) return;
    if (paisesPromiseGest) return paisesPromiseGest;

    paisesPromiseGest = axios.get(`${API_URL}/dj/get-paises/`)
        .then(res => {
            paisesDataGest = res.data?.paises ?? [];
            const sel = document.getElementById('aj_pais');
            if (sel) {
                sel.innerHTML = '<option value="">— Seleccionar —</option>';
                paisesDataGest.forEach(p => {
                    const o = document.createElement('option');
                    o.value = p.id;
                    o.textContent = p.text;
                    sel.appendChild(o);
                });
            }
        })
        .catch(err => console.error('[GestionDJ] Error cargando países:', err))
        .finally(() => { paisesPromiseGest = null; });

    return paisesPromiseGest;
}

async function setPaisGest(codigo) {
    const sel = document.getElementById('aj_pais');
    if (!sel) return;
    // Autoguarantía: si el catálogo de países no llegó, cargarlo ahora
    if (!paisesDataGest.length) await cargarPaisesGest();
    sel.value = String(codigo ?? '').trim();
}

// ── Regla de Dep/Prov/Dist de la card "País de Nacimiento" ──
// Se ocultan si:
//   • Tipo de documento = Carnet de Extranjería (0035), o
//   • Tipo de documento = DNI (0034) y el País de Nacimiento NO es PERU.
const DJ_COD_DNI       = '0034';          // si_solm.dbo.TIPO_DOCUMENTO → DNI
const DJ_COD_CE        = '0035';          // si_solm.dbo.TIPO_DOCUMENTO → CARNET EXTRANJERIA
const DJ_PAIS_PERU     = '2007000156';    // si_solm.dbo.ADMI_PAIS → PERU
let   codTipoDocDjActual = '';

// limpiarCampos = true solo cuando el usuario cambia el país a mano
// (no al cargar datos existentes, para no perder lo guardado).
function aplicarUbigeoNacimiento(limpiarCampos = false) {
    const pais = String(document.getElementById('aj_pais')?.value ?? '').trim();

    const esExtranjeria   = codTipoDocDjActual === DJ_COD_CE;
    const esDniExtranjero = codTipoDocDjActual === DJ_COD_DNI && pais !== '' && pais !== DJ_PAIS_PERU;
    const ocultar         = esExtranjeria || esDniExtranjero;

    ['aj_wrap_departamento_nac', 'aj_wrap_provincia_nac', 'aj_wrap_distrito_nac'].forEach(id => {
        const w = document.getElementById(id);
        if (w) w.style.display = ocultar ? 'none' : '';
    });

    if (ocultar && limpiarCampos) {
        ['departamento_nac', 'provincia_nac', 'distrito_nac'].forEach(id => {
            const s = document.getElementById(id);
            if (s) s.value = '';
        });
    }
}

// Se llama al cargar los datos de la DJ o al resetear el modal.
// Guarda el tipo de documento (el modal de edición no tiene ese select,
// así que viene en data.CODI_TIPO_DOCU) y aplica la regla.
function aplicarExtranjeriaNacimiento(codTipoDoc) {
    codTipoDocDjActual = String(codTipoDoc ?? '').trim();
    aplicarUbigeoNacimiento(false);
}

async function cargarCatalogos(source = 'migracion') {
    if (catalogosCache) return catalogosCache;
    if (catalogosPromise) return catalogosPromise;

    catalogosPromise = axios.get(`${API_URL}/dj/get-catalogs`)
        .then(async response => {
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
            await cargarCargosDj();
            await cargarPaisesGest();
            return response.data;
        })
        .finally(() => {
            catalogosPromise = null;
        });

    return catalogosPromise;
}

// ── Cargos DJ ───────────────────────────────────────────────
async function cargarCargosDj() {
    try {
        const response = await axios.get(`${VITE_URL_APP}/api/dj/get-cargos-dj`);
        if (response.data?.success && response.data.data) {
            window.allCargosDj = response.data.data;
        }
    } catch (err) {
        console.error('[GestionDJ] Error cargando cargos:', err);
        window.allCargosDj = [];
    }
}

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
    limpiarDniDj();       // pizarra limpia: no arrastrar DNI de otra persona
    limpiarFotoPendienteDj();   // pizarra limpia: no arrastrar la FOTO de otra persona
    codiPersActual = data.CODI_PERS || '';
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

    // País de nacimiento (columna NACIONALIDAD de PERSONAL → datalist de países)
    await cargarPaisesGest();
    await setPaisGest(data.NACIONALIDAD ? String(data.NACIONALIDAD).trim() : '');
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
    const institucionEducativa = String(data.IEDU_CODIGO ?? '').trim();
    const carreraEducativa = String(data.CARR_CODIGO ?? '').trim();
    const institucionNoEspecificada = ['', '999999', '199999999', '299999999'].includes(institucionEducativa);
    const carreraNoEspecificada = ['', '999999'].includes(carreraEducativa);
    const institucionValida = institucionNoEspecificada ? '' : institucionEducativa;
    const carreraValida = (institucionValida && !carreraNoEspecificada) ? carreraEducativa : '';

    setValue('#institucion', institucionValida);
    const selCarrera = document.getElementById('carrera');
    if (selCarrera) {
        selCarrera.innerHTML = '<option value="" selected>—</option>';
        if (institucionValida && window.allCarreras) {
            window.allCarreras
                .filter(c => c.IEDU_CODIGO === institucionValida && String(c.id).trim() !== '999999')
                .forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.text;
                    selCarrera.appendChild(opt);
                });
        }
        selCarrera.value = carreraValida;
    }
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

    await Promise.all([
        cargarUbicaciones('actual', data.PERS_DEPT_ACT?.trim() ?? '', data.PERS_PROV_ACT?.trim() ?? '', data.PERS_DIST_ACT?.trim() ?? ''),
        cargarUbicaciones('dni', data.PERS_DPTO_DIRDNI?.trim() ?? '', data.PERS_PROV_DIRDNI?.trim() ?? '', data.PERS_DIST_DIRDNI?.trim() ?? ''),
        cargarUbicaciones('nac', data.DEPA_CODIGO_NACI?.trim() ?? '', data.PROVI_CODIGO_NACI?.trim() ?? '', data.DIST_NACI?.trim() ?? ''),
    ]);


    setValue('#ocupacion_principal', data.dj2026_ocupacion_principal);
    setValue('#experiencia_anios', data.dj2026_experiencia_anios !== null && data.dj2026_experiencia_anios !== undefined ? String(data.dj2026_experiencia_anios).replace(/[^0-9]/g, '') : '');
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

}

// ── Familiares ───────────────────────────────────────────────
// ── DNI de hijos: {CODI}_H{N}.jpg → DNI1_HIJOS (anverso) / DNI2_HIJOS (reverso) ──
// N = par por hijo en orden de fila: hijo 1 → H1/H2, hijo 2 → H3/H4, ...
let djDniHijosRows = new WeakMap();
let djDniHijoActivo = null;

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
    const codi = (codiPersActual || '').trim();

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
        console.warn('[GestionDJ] Error consultando DNI de hijos:', e);
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
        const res = await axios.post(`${VITE_URL_APP}/api/dj/upload-dni-hijos`, fd, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
        if (res.data?.success) {
            filasH.forEach(fila => {
                const st = estadoDniHijo(fila);
                if (st.anverso) { st.anverso = null; st.tieneAnverso = true; }
                if (st.reverso) { st.reverso = null; st.tieneReverso = true; }
                actualizarEstadoDniHijo(fila);
            });
        }
        return { ok: !!res.data?.success, message: res.data?.message };
    } catch (err) {
        console.error('[GestionDJ] Error subiendo DNI de hijos:', err?.response?.data || err);
        return { ok: false, message: err?.response?.data?.message || 'Error al subir el DNI de hijos.' };
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
    consultarDniHijosDj((codiPersActual || '').trim());
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

    const ubicacionApi = `${API_URL}/ubicacion`;
    const url = params.type === 'dept'
        ? `${ubicacionApi}/departamentos`
        : params.type === 'prov'
            ? `${ubicacionApi}/provincias/${encodeURIComponent(params.dept)}`
            : `${ubicacionApi}/distritos/${encodeURIComponent(params.prov)}`;

    const req = axios.get(url)
        .then(res => {
            const data = (res.data || []).map(item => {
                if (params.type === 'dept') {
                    return { id: item.depa_codigo, text: item.depa_descripcion };
                }
                if (params.type === 'prov') {
                    return { id: item.provi_codigo, text: item.provi_descripcion };
                }
                return { id: item.dist_codigo, text: item.dist_descripcion };
            });
            ubicacionCache.set(key, data);
            return data;
        })
        .finally(() => {
            ubicacionPromise.delete(key);
        });

    ubicacionPromise.set(key, req);
    return req;
}


async function cargarUbicaciones(tipo, dept, prov, dist) {
    const prefix = tipo === 'actual' ? '_actual' : tipo === 'dni' ? '_dni' : '_nac';
    const selectProv = document.querySelector(`#provincia${prefix}`);
    const selectDist = document.querySelector(`#distrito${prefix}`);
    if (selectProv) selectProv.innerHTML = '<option value="">Seleccionar...</option>';
    if (selectDist) selectDist.innerHTML = '<option value="">Seleccionar...</option>';
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
    'contacto_emergencia': 'PERS_NOMCONTACTO', 'celular_emergencia': 'PERS_NROEMERGENCIA',
    'parentesco_emergencia': 'PERS_EMERC_FAMILIAR', 'ocupacion_principal': 'PERS_PROFESION',
    'experiencia_anios': 'dj2026_experiencia_anios', 'experiencia_meses': 'dj2026_experiencia_meses',
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

    // ✅ Limpiar/resetear panel antes de cargar
    wrapper.classList.remove('no-backup');
    if (panelBk) panelBk.style.display = 'block';
    document.querySelectorAll('#panelBackup .bk-val').forEach(el => el.textContent = '—');

    try {
        const response = await axios.get(`${API_URL}/dj/get-backup-data`, { params: { codi_pers: codiPers } });

        if (!response.data.success) {
            console.warn('[BackupDJ] Sin backup:', response.data.message);
            _backupData = null;
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
        console.warn('[BackupDJ] Error:', err);
        // No ocultar — el panel es esencial en DJ
        _backupData = {};
        wrapper.classList.remove('no-backup');
        if (panelBk) panelBk.style.display = 'block';
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
    let codTipoPer;
    if(tipoPer === 'OPERATIVO 4°'){
        codTipoPer = '01';
    }
    if(tipoPer === 'OPERATIVO 5°'){
        codTipoPer = '03';
    }
    if(tipoPer === 'ADMINISTRATIVO 5°'){
        codTipoPer = '05';
    }
    if(tipoPer === 'ADMINISTRATIVO 4°'){
        codTipoPer = '02';
    }
     if(tipoPer === 'ESPECIAL'){
        codTipoPer = '06';
    }
   
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

// ── Reporte de documentos faltantes (foto / DNI) de todos los vigentes ─────
// Cada clic vuelve a verificar los archivos en el servidor de imágenes
// (siempre actualizado, ~10-20 s). Shift + click reinicia el escaneo.
document.getElementById('btnDocsPendientes')?.addEventListener('click', async function (ev) {
    const refrescar = ev.shiftKey;

    Swal.fire({
        title: 'Generando reporte de documentos…',
        text: 'Verificando la foto y el DNI de todo el personal vigente en el servidor de archivos (~10-20 s).',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await axios.get(`${VITE_URL_APP}/api/dj/reporte/personal-documentos`, {
            params: refrescar ? { refrescar: 1, solo_faltantes: 1 } : { solo_faltantes: 1 },
            timeout: 0
        });

        const data = response.data;
        if (!data?.url) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Respuesta inesperada del servidor.' });
            return;
        }

        // Descarga directa navegando a la URL (evita problemas de AJAX con respuestas binarias)
        const a = document.createElement('a');
        a.href = data.url;
        a.download = data.nombre || `Reporte_Documentos_Personal_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(a);
        a.click();
        a.remove();

        Swal.close();
    } catch {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el reporte. Inténtalo de nuevo.' });
    }
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
        confirmButtonColor: '#ef4444',
    });

    if (!isConfirmed) return;

    await resetearGeneradosAPI();
    tblPersonasMigrado.redraw(true);

    Swal.fire({ icon: 'success', title: 'Listo', text: 'Todas las marcas fueron eliminadas.', timer: 1800, showConfirmButton: false });
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
        
        // Reset Cargo and Tipo de Personal selects
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
// BIOMÉTRICO (Migrado de actualizar_dj.js)
// ============================================================
// 🔥 Limpieza previa del evento para evitar ventanas dobles al recargar Vite
if (window._bioHandler) {
    window.removeEventListener('solicitarBiometrico', window._bioHandler);
}

window._bioHandler = function (e) {
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

            // Inyectamos la columna de la foto dinámicamente
            const dniDiv = document.getElementById('bio-doc-dni-antiguo');
            if (dniDiv) {
                const gridContainer = dniDiv.parentElement.parentElement;
                gridContainer.style.gridTemplateColumns = '2fr 1fr 1fr';

                let cajaFoto = document.getElementById('caja-foto-inyectada');
                if (!cajaFoto) {
                    cajaFoto = document.createElement('div');
                    cajaFoto.id = 'caja-foto-inyectada';
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

            // Abrir el modal primero
            document.getElementById('btn-modal-biometrico').click();

            const forzarVistaLimpia = () => {
                // 1. Ocultar estrictamente SOLO el contenedor de las pestañas (NAV)
                const tabFh = document.getElementById('bio-tab-fh');
                if (tabFh) {
                    const navContainer = tabFh.closest('nav') || tabFh.parentElement;
                    if (navContainer) {
                        navContainer.style.setProperty('display', 'none', 'important');
                        
                        // Limpiamos los bordes del div padre sin ocultarlo
                        if (navContainer.parentElement && navContainer.parentElement.tagName === 'DIV') {
                            navContainer.parentElement.style.setProperty('border', 'none', 'important');
                            navContainer.parentElement.style.setProperty('margin', '0', 'important');
                            navContainer.parentElement.style.setProperty('padding', '0', 'important');
                        }
                    }
                }

                // 2. Ocultar forzosamente el panel de "Firmas y Huellas" (el que tapa todo)
                const panelFh = document.getElementById('bio-panel-fh');
                if (panelFh) {
                    panelFh.style.setProperty('display', 'none', 'important');
                    panelFh.classList.add('hidden');
                    panelFh.classList.remove('active');
                }

                // 3. Mostrar forzosamente el panel "DOC" (donde están el DNI y la Foto)
                const panelDoc = document.getElementById('bio-panel-doc');
                if (panelDoc) {
                    panelDoc.style.setProperty('display', 'block', 'important');
                    panelDoc.classList.remove('hidden');
                    panelDoc.classList.add('active');
                }
            };

            // Ejecutamos en ráfaga para ganarle al framework sin matar las imágenes
            forzarVistaLimpia();
            setTimeout(forzarVistaLimpia, 50);
            setTimeout(forzarVistaLimpia, 300);
            setTimeout(forzarVistaLimpia, 600);
        })
        .catch(() => Swal.fire({ title: 'Error al obtener biométrico', icon: 'error' }));
};

// 🔥 Volvemos a registrar el evento blindado
window.addEventListener('solicitarBiometrico', window._bioHandler);

window.bioSwitchTab = function (tab) {
    const esFH = tab === 'fh';
    const panelFh = document.getElementById('bio-panel-fh');
    const panelDoc = document.getElementById('bio-panel-doc');

    // Gestionamos la clase 'active' para evitar que el framework superponga las vistas
    if (panelFh) {
        if (esFH) {
            panelFh.style.setProperty('display', 'block', 'important');
            panelFh.classList.remove('hidden');
            panelFh.classList.add('active'); // Mantiene compatibilidad con el framework
        } else {
            panelFh.style.setProperty('display', 'none', 'important');
            panelFh.classList.add('hidden');
            panelFh.classList.remove('active'); // Evita que el framework la fuerce a visible
        }
    }
    
    if (panelDoc) {
        if (!esFH) {
            panelDoc.style.setProperty('display', 'block', 'important');
            panelDoc.classList.remove('hidden');
            panelDoc.classList.add('active');
        } else {
            panelDoc.style.setProperty('display', 'none', 'important');
            panelDoc.classList.add('hidden');
            panelDoc.classList.remove('active');
        }
    }

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
                     onerror="this.parentElement.innerHTML='<div style=\\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;flex-direction:column;gap:6px;\\'><svg width=32 height=32 fill=none stroke=currentColor stroke-width=1.5 viewBox=\\'0 0 24 24\\'><rect x=3 y=3 width=18 height=18 rx=3/><circle cx=8.5 cy=8.5 r=1.5/><path d=\\'m21 15-5-5L5 21\\'/></svg>Sin imagen</div>'" />
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
