// ============================================================
// MÓDULO: Modal Reporte — Datos Generales del Personal
// Requiere: axios, Swal, jspdf + jspdf-autotable (ya en la vista)
// ============================================================
import axios from 'axios';
import Swal from 'sweetalert2';

(function initModalReporte() {

    // ── Referencias DOM ───────────────────────────────────────
    const modal        = document.getElementById('modalReporte');
    const btnBuscar    = document.getElementById('btnBuscarReporte');
    const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltrosReporte');
    const resultados   = document.getElementById('rptResultados');
    const inputBusquedaPreview = document.getElementById('rptBuscarPreview');
    const modalPreview = document.getElementById('modalVistaPreviaReporte');
    const tituloPreview = document.getElementById('tituloVistaPreviaReporte');
    const subtituloPreview = document.getElementById('subtituloVistaPreviaReporte');
    const contenidoPreview = document.getElementById('contenidoVistaPreviaReporte');
    const tbody        = document.getElementById('rptTbody');
    const cbAll        = document.getElementById('rptCbAll');
    const selInfo      = document.getElementById('rptSelInfo');
    const pagInfo      = document.getElementById('rptPagInfo');
    const pageNum      = document.getElementById('rptPageNum');
    const btnPrev      = document.getElementById('rptBtnPrev');
    const btnNext      = document.getElementById('rptBtnNext');
    const pageSizeSel  = document.getElementById('rptPageSize');
    const btnDescFoto  = document.getElementById('btnRptDescFoto');
    const btnDescDNI   = document.getElementById('btnRptDescDNI');
    const btnDescCUL   = document.getElementById('btnRptDescCUL');
    const btnExportPDF = document.getElementById('btnRptExportPDF');
    const btnExportXLS = document.getElementById('btnRptExportExcel');
    const selSucursal  = document.getElementById('rptSucursal');
    const selTipo      = document.getElementById('rptTipoPer');

    if (!modal) return;

    const contenedorOriginalResultados = resultados?.parentElement;
    const siguienteHermanoResultados = resultados?.nextSibling ?? null;

    function cerrarVistaPreviaGeneral() {
        if (contenedorOriginalResultados && resultados) {
            contenedorOriginalResultados.insertBefore(resultados, siguienteHermanoResultados);
            resultados.classList.add('hidden');
        }
        contenidoPreview.replaceChildren();
    }

    window.addEventListener('dj:cerrar-vista-previa', cerrarVistaPreviaGeneral);

    // ── Estado ────────────────────────────────────────────────
    let filtrados       = [];
    let paginaActual    = 1;
    let tamanoPagina    = 20;
    const seleccionados = new Set();

    // ── Constantes ────────────────────────────────────────────
    const TITULO_REPORTE  = 'DATOS BASICOS DEL PERSONAL';
    const EMPRESA_REPORTE = 'Sol Security S.A.C.';

    // Columnas para exportar PDF/Excel (sin checkbox)
    const COLUMNAS_EXPORT = [
        { label: 'It.',              key: '_it' },
        { label: 'Sucursal',         key: 'sucursal' },
        { label: 'Cód.',             key: 'codPersonal' },
        { label: 'Apellidos y Nombres', key: 'nombre' },
        { label: 'País',             key: 'nacionalidad' },
        { label: 'Tipo Doc.',        key: 'tipoDoc' },
        { label: 'Doc. Iden.',       key: 'nroDocIden' },
        { label: 'Caduca Doc.',      key: 'cadDni' },
        { label: 'Sexo',             key: 'sexo' },
        { label: 'Edad',             key: 'edad' },
        { label: 'Email',            key: 'email' },
        { label: 'Teléfonos',        key: 'telefono' },
        { label: 'Dirección Actual', key: 'direccion' },
        { label: 'Fecha Ingreso',    key: 'fechIngreso' },
        { label: 'Cargo',            key: 'cargo' },
        { label: 'Tipo Pers.',       key: 'tipoPer' },
        { label: 'Caduca EMO',       key: 'caducaEmo' },
        { label: 'Fin Contrato',     key: 'finContrato' },
    ];

    // ── Fetch ─────────────────────────────────────────────────
    async function fetchPersonal() {
        const codSucursal  = selSucursal?.value.trim()  ?? '';
        const tipoPer      = selTipo?.value.trim()      ?? '';
        const vigente      = document.getElementById('rptVigente')?.value ?? '';
        btnBuscar.disabled = true;
        tituloPreview.textContent = 'Vista previa: Reporte General';
        subtituloPreview.textContent = 'Aplicando los filtros seleccionados…';
        contenidoPreview.innerHTML = '<p class="text-sm text-slate-500">Consultando datos del personal…</p>';
        contenidoPreview.classList.remove('text-center', 'text-sm', 'text-red-600');
        modalPreview.classList.remove('hidden');
        modalPreview.classList.remove('flex');
        modalPreview.style.display = 'flex';
        document.body.classList.add('overflow-hidden');

        try {
            const response = await axios.get(`${VITE_URL_APP}/api/dj/reporte-personal-datos-generales`, {
                params: { codSucursal, tipoPer, vigente }
            });

            const raw = response.data?.data ?? response.data;

            filtrados = raw.map((r, i) => ({
                _it:          i + 1,
                codPersonal:  trim(r.codPersonal  ?? r.CODI_PERS    ?? ''),
                codSucursal:  trim(r.codSucursal  ?? r.SUCU_CODIGO  ?? ''),
                sucursal:     trim(r.sucursal     ?? '—'),
                nombre:       r.nombre ?? trim(
                    `${r.NOMB_1??''} ${r.NOMB_2??''} ${r.APEL_1??''} ${r.APEL_2??''}`
                ),
                nacionalidad: trim(r.nacionalidad ?? r.NACIONALIDAD ?? 'PERU'),
                tipoDoc:      trim(r.tipoDoc      ?? r.CODI_TIPO_DOCU ?? 'DNI'),
                nroDocIden:   trim(r.nroDocIden   ?? r.NRO_DOCU_IDEN  ?? '—'),
                cadDni:       r.cadDni      ?? formatFecha(r.PERS_FECHCADUCADNI),
                sexo:         trim(r.sexo         ?? r.PERS_SEXO  ?? '—'),
                edad:         r.edad        ?? calcularEdad(r.FECH_NACI),
                email:        trim(r.email        ?? r.PERS_EMAIL ?? '—'),
                telefono:     trim(r.telefono     ?? r.PERS_TELEFONO ?? '—'),
                direccion:    trim(r.direccion    ?? r.DIRECCION  ?? '—'),
                fechIngreso:  r.fechIngreso  ?? formatFecha(r.FECH_INGRE),
                cargo:        trim(r.cargo        ?? r.CODI_CARG  ?? '—'),
                tipoPer:      r.tipoPer      ?? mapTipoTexto(r.PERS_TIPOTRAB),
                caducaEmo:    r.caducaEmo    ?? formatFecha(r.VCMTO) ?? '—',
                finContrato:  r.finContrato  ?? formatFecha(r.FECH_CESE) ?? '—',
            }));

            // Reasignar _it tras filtrado
            filtrados.forEach((r, i) => r._it = i + 1);

            paginaActual = 1;
            seleccionados.clear();
            if (inputBusquedaPreview) inputBusquedaPreview.value = '';
            renderTabla();
            resultados?.classList.remove('hidden');
            subtituloPreview.textContent = `${filtrados.length.toLocaleString('es-PE')} registros encontrados con estos filtros.`;
            contenidoPreview.replaceChildren(resultados);

        } catch (err) {
            console.error('Error cargando reporte:', err);
            subtituloPreview.textContent = 'No se pudo consultar el reporte.';
            contenidoPreview.textContent = err.message || 'No se pudo cargar el reporte.';
            contenidoPreview.classList.add('text-center', 'text-sm', 'text-red-600');
        } finally {
            btnBuscar.disabled = false;
        }
    }

    // ── Helpers ───────────────────────────────────────────────
    function trim(val) { return String(val ?? '').trim(); }

    function filasVisibles() {
        const busqueda = trim(inputBusquedaPreview?.value).toLocaleLowerCase('es')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        if (!busqueda) return filtrados;
        return filtrados.filter(registro => `${registro.nombre} ${registro.nroDocIden}`
            .toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .includes(busqueda));
    }

    function formatFecha(valor) {
        if (!valor) return '—';
        const str = String(valor);
        const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : str.split('T')[0];
    }

    function calcularEdad(fechNaci) {
        if (!fechNaci) return '—';
        const naci = new Date(fechNaci);
        const hoy  = new Date();
        let edad   = hoy.getFullYear() - naci.getFullYear();
        const m    = hoy.getMonth() - naci.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < naci.getDate())) edad--;
        return isNaN(edad) ? '—' : edad;
    }

    function mapTipoTexto(val) {
        const v = String(val ?? '').trim().toUpperCase();
        if (v === 'OP' || v === '03') return 'OPERATIVO';
        if (v === 'AD' || v === '05') return 'ADMINISTRATIVO';
        return v || '—';
    }

    // ── Render tabla ──────────────────────────────────────────
    function renderTabla() {
        const filas = filasVisibles();
        const inicio = (paginaActual - 1) * tamanoPagina;
        const slice  = filas.slice(inicio, inicio + tamanoPagina);

        if (!slice.length) {
            tbody.innerHTML = `<tr><td colspan="19" class="text-center py-12 text-gray-400 italic text-sm">No se encontraron registros con esa búsqueda.</td></tr>`;
            cbAll.checked = false;
            cbAll.indeterminate = false;
            actualizarFooter(0, 0, filas.length);
            actualizarSelInfo();
            return;
        }

        tbody.innerHTML = slice.map((r, i) => {
            const chk    = seleccionados.has(r.codPersonal) ? 'checked' : '';
            const tipoBadge = (() => {
                const t = String(r.tipoPer ?? '').toUpperCase();
                if (t.includes('OPERATIVO') && t.includes('4'))
                    return `<span class="inline-flex rounded-full border border-blue-200 bg-blue-50 text-blue-600 px-1.5 py-0.5 text-[9px] font-medium whitespace-nowrap">OPER 4°</span>`;
                if (t.includes('OPERATIVO') && t.includes('5'))
                    return `<span class="inline-flex rounded-full border border-blue-300 bg-blue-100 text-blue-800 px-1.5 py-0.5 text-[9px] font-medium whitespace-nowrap">OPER 5°</span>`;
                if (t.includes('ADMINISTRATIVO') && t.includes('4'))
                    return `<span class="inline-flex rounded-full border border-purple-200 bg-purple-50 text-purple-600 px-1.5 py-0.5 text-[9px] font-medium whitespace-nowrap">ADM 4°</span>`;
                if (t.includes('ADMINISTRATIVO') && t.includes('5'))
                    return `<span class="inline-flex rounded-full border border-purple-300 bg-purple-100 text-purple-800 px-1.5 py-0.5 text-[9px] font-medium whitespace-nowrap">ADM 5°</span>`;
                if (t.includes('ESPECIAL'))
                    return `<span class="inline-flex rounded-full border border-amber-300 bg-amber-100 text-amber-800 px-1.5 py-0.5 text-[9px] font-medium whitespace-nowrap">ESP</span>`;
                return `<span class="inline-flex rounded-full border border-gray-300 bg-gray-100 text-gray-600 px-1.5 py-0.5 text-[9px] font-medium whitespace-nowrap">${r.tipoPer ?? '—'}</span>`;
            })();

            return `<tr class="border-b border-gray-100 hover:bg-blue-50/40 transition-colors ${seleccionados.has(r.codPersonal) ? 'bg-blue-50' : ''}">
                <td class="py-1 px-1 text-center">
                    <input type="checkbox" class="rpt-cb-row w-3.5 h-3.5 accent-primary cursor-pointer" data-cod="${r.codPersonal}" ${chk}>
                </td>
                <td class="py-1 px-1 text-center text-gray-400">${inicio + i + 1}</td>
                <td class="py-1 px-1 font-medium whitespace-nowrap">${r.sucursal}</td>
                <td class="py-1 px-1 text-gray-500 whitespace-nowrap">${r.codPersonal}</td>
                <td class="py-1 px-1 font-medium whitespace-nowrap" title="${r.nombre}">${r.nombre}</td>
                <td class="py-1 px-1 text-gray-600 whitespace-nowrap">${r.nacionalidad}</td>
                <td class="py-1 px-1 text-gray-600 whitespace-nowrap">${r.tipoDoc}</td>
                <td class="py-1 px-1 whitespace-nowrap">${r.nroDocIden}</td>
                <td class="py-1 px-1 whitespace-nowrap">${r.cadDni}</td>
                <td class="py-1 px-1 text-center whitespace-nowrap">${r.sexo}</td>
                <td class="py-1 px-1 text-center whitespace-nowrap">${r.edad}</td>
                <td class="py-1 px-1 text-gray-600 max-w-[130px] truncate" title="${r.email}">${r.email}</td>
                <td class="py-1 px-1 whitespace-nowrap">${r.telefono}</td>
                <td class="py-1 px-1 text-gray-600 max-w-[150px] truncate" title="${r.direccion}">${r.direccion}</td>
                <td class="py-1 px-1 whitespace-nowrap">${r.fechIngreso}</td>
                <td class="py-1 px-1 text-gray-600 max-w-[120px] truncate" title="${r.cargo}">${r.cargo}</td>
                <td class="py-1 px-1">${tipoBadge}</td>
                <td class="py-1 px-1 whitespace-nowrap">${r.caducaEmo}</td>
                <td class="py-1 px-1 whitespace-nowrap">${r.finContrato}</td>
            </tr>`;
        }).join('');

        actualizarFooter(inicio + 1, Math.min(inicio + tamanoPagina, filas.length), filas.length);
        actualizarCbAll();
        actualizarSelInfo();
        bindCheckboxes();
    }

    function actualizarFooter(desde, hasta, total) {
        pagInfo.textContent = total > 0 ? `Mostrando ${desde}–${hasta} de ${total}` : 'Sin resultados';
        pageNum.textContent = paginaActual;
        btnPrev.disabled    = paginaActual <= 1;
        btnNext.disabled    = paginaActual >= Math.ceil(total / tamanoPagina);
    }

    function actualizarCbAll() {
        const filas = filasVisibles();
        const slice = filas.slice((paginaActual - 1) * tamanoPagina, paginaActual * tamanoPagina);
        cbAll.checked       = slice.length > 0 && slice.every(r => seleccionados.has(r.codPersonal));
        cbAll.indeterminate = !cbAll.checked && slice.some(r => seleccionados.has(r.codPersonal));
    }

    function actualizarSelInfo() {
        selInfo.textContent = `${seleccionados.size} seleccionado${seleccionados.size === 1 ? '' : 's'}`;
    }

    function bindCheckboxes() {
        tbody.querySelectorAll('.rpt-cb-row').forEach(cb => {
            cb.addEventListener('change', function () {
                this.checked ? seleccionados.add(this.dataset.cod) : seleccionados.delete(this.dataset.cod);
                this.closest('tr').classList.toggle('bg-blue-50', this.checked);
                actualizarCbAll();
                actualizarSelInfo();
            });
        });
    }

    cbAll?.addEventListener('change', function () {
        filasVisibles().slice((paginaActual - 1) * tamanoPagina, paginaActual * tamanoPagina)
            .forEach(r => this.checked ? seleccionados.add(r.codPersonal) : seleccionados.delete(r.codPersonal));
        renderTabla();
    });

    inputBusquedaPreview?.addEventListener('input', () => {
        paginaActual = 1;
        renderTabla();
    });

    // ── Paginación ────────────────────────────────────────────
    btnPrev?.addEventListener('click', () => { if (paginaActual > 1) { paginaActual--; renderTabla(); } });
    btnNext?.addEventListener('click', () => {
        if (paginaActual < Math.ceil(filasVisibles().length / tamanoPagina)) { paginaActual++; renderTabla(); }
    });
    pageSizeSel?.addEventListener('change', function () { tamanoPagina = parseInt(this.value); paginaActual = 1; renderTabla(); });

    // ── Buscar ────────────────────────────────────────────────
    btnBuscar?.addEventListener('click', fetchPersonal);
    btnLimpiarFiltros?.addEventListener('click', () => {
        const resetSelect = (select, defaultValue = '') => {
            if (!select) return;
            const tieneDefault = [...select.options].some(option => option.value === defaultValue);
            select.value = tieneDefault ? defaultValue : (select.options[0]?.value ?? '');
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        resetSelect(selSucursal);
        resetSelect(selTipo);
        resetSelect(document.getElementById('rptVigente'), '1');
        resetSelect(document.getElementById('rptDescCon'), 'doc');
    });

    // ── Descargas archivos ────────────────────────────────────
    const BASE_URL = 'http://190.116.178.163/Biblioteca_Grafica';

    const URLS_TIPO = {
        foto: cod => [`${BASE_URL}/Fotos/${cod}.jpg`],
        dni:  cod => [
            `${BASE_URL}/DNI1_1/${cod}.jpg`,
            `${BASE_URL}/DNI1_2/${cod}.jpg`,
        ],
        cul:  cod => [`${BASE_URL}/CERTIFICADOS/CUL/${cod}.jpg`],
    };

    function getSeleccionados() { return filtrados.filter(r => seleccionados.has(r.codPersonal)); }
    function getModoDescarga()  { return document.getElementById('rptDescCon')?.value ?? 'doc'; }
    function alertarSinSel()    { Swal.fire({ icon: 'warning', title: 'Sin selección', text: 'Marca al menos un registro.' }); }

    function getNombreBase(reg) {
        return getModoDescarga() === 'doc' ? reg.nroDocIden : reg.codPersonal;
    }

    function triggerDownload(blob, nombre) {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href     = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Proxy Laravel para evitar CORS
    async function fetchImagen(urlRemota) {
        try {
            const proxyUrl = `${VITE_URL_APP}/api/dj/reporte/proxy-imagen?url=${encodeURIComponent(urlRemota)}`;
            const res = await fetch(proxyUrl, { signal: AbortSignal.timeout(12000) });
            if (!res.ok) return null;
            const blob = await res.blob();
            if (!blob || blob.size === 0) return null;
            return blob;
        } catch (e) {
            console.error('fetchImagen error:', e);
            return null;
        }
    }

    function nombreUnico(nombre, ext, usedNames) {
        let candidato = `${nombre}.${ext}`;
        let contador  = 1;
        while (usedNames.has(candidato)) {
            candidato = `${nombre}_${contador}.${ext}`;
            contador++;
        }
        usedNames.add(candidato);
        return candidato;
    }

    async function descargarArchivos(tipo) {
        const regs = getSeleccionados();
        if (!regs.length) { alertarSinSel(); return; }

        const etiquetas = { foto: 'Foto', dni: 'DNI', cul: 'CUL' };
        const label     = etiquetas[tipo];
        const esMultiple = regs.length > 1;

        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            title: `Descargar ${label}`,
            html: esMultiple
                ? `Se generará un <b>ZIP</b> con los archivos de <b>${regs.length}</b> registro(s) seleccionado(s).`
                : `Se descargará el archivo de <b>1</b> registro seleccionado.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, descargar',
            cancelButtonText:  'Cancelar',
        });
        if (!isConfirmed) return;

        Swal.fire({
            title: esMultiple ? `Generando ZIP de ${label}s...` : `Descargando ${label}...`,
            html: `Procesando <b>0</b> de <b>${regs.length}</b>`,
            allowOutsideClick: false,
            allowEscapeKey:    false,
            didOpen: () => Swal.showLoading(),
        });

        const usedNames = new Set();
        let ok = 0, sinArchivo = 0, errores = [];

        // ZIP solo si hay más de 1 registro
        const zip = esMultiple ? new JSZip() : null;

        for (let i = 0; i < regs.length; i++) {
            const reg  = regs[i];
            const cod  = reg.codPersonal;
            const base = getNombreBase(reg);
            const urls = URLS_TIPO[tipo](cod);

            Swal.update({
                html: `Procesando <b>${i + 1}</b> de <b>${regs.length}</b><br>
                       <small class="text-gray-400">${reg.nombre}</small>`
            });

            let descargadoAlgo = false;

            for (let u = 0; u < urls.length; u++) {
                const blob = await fetchImagen(urls[u]);
                if (!blob) continue;

                const sufijo = tipo === 'dni'
                    ? (u === 0 ? '_anverso' : '_reverso')
                    : '';

                const nombreFinal = nombreUnico(`${base}${sufijo}`, 'jpg', usedNames);

                if (zip) {
                    // Múltiples: agregar al ZIP
                    const buffer = await blob.arrayBuffer();
                    zip.file(nombreFinal, buffer);
                } else {
                    // Solo 1: descarga directa
                    triggerDownload(blob, nombreFinal);
                }

                ok++;
                descargadoAlgo = true;
                await new Promise(r => setTimeout(r, 200));
            }

            if (!descargadoAlgo) {
                sinArchivo++;
                errores.push(reg.nombre);
            }
        }

        // Si es múltiple, generar y descargar el ZIP
        if (zip && ok > 0) {
            Swal.update({ html: 'Comprimiendo archivos...' });
            try {
                const f   = new Date();
                const ts  = f.getFullYear()
                    + String(f.getMonth() + 1).padStart(2, '0')
                    + String(f.getDate()).padStart(2, '0')
                    + '_' + String(f.getHours()).padStart(2, '0')
                    + String(f.getMinutes()).padStart(2, '0');
                const zipBlob = await zip.generateAsync({ type: 'blob' });
                triggerDownload(zipBlob, `${label}_${ts}.zip`);
            } catch (e) {
                console.error('Error generando ZIP:', e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el ZIP.' });
                return;
            }
        }

        const hayErrores = errores.length > 0;
        Swal.fire({
            icon:  hayErrores && ok === 0 ? 'error' : hayErrores ? 'warning' : 'success',
            title: 'Descarga completada',
            html: `
                <div style="text-align:left;font-size:13px;line-height:1.8">
                    <div>✅ Archivos: <b>${ok}</b>${esMultiple ? ' — descargados en ZIP' : ''}</div>
                    ${sinArchivo > 0 ? `
                    <div>⚠️ Sin archivo: <b>${sinArchivo}</b></div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
                        ${errores.map(n => `• ${n}`).join('<br>')}
                    </div>` : ''}
                </div>
            `,
        });
    }

    btnDescFoto?.addEventListener('click', () => descargarArchivos('foto'));
    btnDescDNI?.addEventListener('click',  () => descargarArchivos('dni'));
    btnDescCUL?.addEventListener('click',  () => descargarArchivos('cul'));

    // ── EXPORTAR PDF ──────────────────────────────────────────
    btnExportPDF?.addEventListener('click', async function () {
        const registrosSeleccionados = getSeleccionados();
        if (!registrosSeleccionados.length) { alertarSinSel(); return; }

        try {
            const { jsPDF } = window.jspdf;
            // Oficio landscape (216 x 356 mm) — más ancho que A4 para que entren las 18 columnas
            const doc   = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [216, 356] });
            const pageW = doc.internal.pageSize.getWidth();
            const marginX = 6;
            let logoBase64 = null;
            if (window.logoUrl) {
                try {
                    const response = await fetch(window.logoUrl);
                    if (response.ok) {
                        const logoBlob = await response.blob();
                        const logoData = await new Promise(resolve => {
                            const reader = new FileReader();
                            reader.onloadend = () => resolve(reader.result);
                            reader.readAsDataURL(logoBlob);
                        });
                        doc.addImage(logoData, 'PNG', marginX, 4, 34, 16);
                        logoBase64 = logoData;
                    }
                } catch (logoError) {
                    console.warn('No se pudo incluir el logo en el PDF:', logoError);
                }
            }
            const sucursalTexto = selSucursal?.selectedOptions[0]?.textContent.trim() || 'Todas las sucursales';
            const tipoTexto = selTipo?.selectedOptions[0]?.textContent.trim() || 'Todos los tipos';
            const estadoTexto = document.getElementById('rptVigente')?.selectedOptions[0]?.textContent.trim() || 'Todos';
            const titulo = `REPORTE GENERAL | ${sucursalTexto} | ${tipoTexto} | ${estadoTexto}`.toUpperCase();
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(18, 59, 89);
            const lineasTitulo = doc.splitTextToSize(titulo, pageW - marginX * 2);
            doc.text(lineasTitulo, pageW / 2, 11, { align: 'center' });

            const yTituloFin = 11 + lineasTitulo.length * 5.2;
            const datosResumen = [[
                'Generado', 'Personal seleccionado', 'Sucursal', 'Tipo de personal', 'Estado',
            ], [
                new Date().toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }),
                String(registrosSeleccionados.length), sucursalTexto, tipoTexto, estadoTexto,
            ]];
            doc.autoTable({
                startY: yTituloFin + 2,
                head: [datosResumen[0]],
                body: [datosResumen[1]],
                theme: 'grid',
                margin: { left: marginX, right: marginX },
                styles: { font: 'helvetica', fontSize: 7, cellPadding: 2, halign: 'center', valign: 'middle', lineColor: [209, 213, 219], lineWidth: 0.2 },
                headStyles: { fillColor: [229, 231, 235], textColor: [55, 65, 81], fontStyle: 'bold', fontSize: 6.5 },
                bodyStyles: { fillColor: [229, 231, 235], textColor: [17, 24, 39], fontStyle: 'bold' },
            });

            const head = [COLUMNAS_EXPORT.map(c => c.label)];
            const body = registrosSeleccionados.map((r, i) =>
                COLUMNAS_EXPORT.map(c => c.key === '_it' ? String(i + 1) : String(r[c.key] ?? '—'))
            );

            doc.autoTable({
                startY: doc.lastAutoTable.finalY + 4,
                head,
                body,
                theme: 'grid',
                styles: {
                    fontSize:    5.5,
                    cellPadding: 1.5,
                    overflow:    'linebreak',
                    halign:      'center',
                    valign:      'middle',
                    lineColor:   [203, 213, 225],
                    lineWidth:   0.15,
                },
                headStyles: {
                    fillColor:  [51, 65, 85],
                    textColor:  255,
                    fontStyle:  'bold',
                    fontSize:   6.5,
                    halign:     'center',
                    valign:     'middle',
                },
                alternateRowStyles: { fillColor: [241, 245, 249] },
                margin: { left: marginX, right: marginX, bottom: 12 },
                columnStyles: {
                    0:  { cellWidth: 5,  halign: 'center' }, // It
                    1:  { cellWidth: 12, halign: 'center' }, // Sucursal
                    2:  { cellWidth: 9,  halign: 'center' }, // Cód
                    3:  { cellWidth: 40, halign: 'center' }, // Nombres
                    4:  { cellWidth: 12, halign: 'center' }, // País
                    5:  { cellWidth: 8,  halign: 'center' }, // Tipo Doc
                    6:  { cellWidth: 18, halign: 'center' }, // Doc Iden
                    7:  { cellWidth: 16, halign: 'center' }, // Caduca Doc
                    8:  { cellWidth: 7,  halign: 'center' }, // Sexo
                    9:  { cellWidth: 7,  halign: 'center' }, // Edad
                    10: { cellWidth: 40, halign: 'center' }, // Email
                    11: { cellWidth: 20, halign: 'center' }, // Teléfono
                    12: { cellWidth: 48, halign: 'center' }, // Dirección
                    13: { cellWidth: 16, halign: 'center' }, // F. Ingreso
                    14: { cellWidth: 28, halign: 'center' }, // Cargo
                    15: { cellWidth: 14, halign: 'center' }, // Tipo Pers
                    16: { cellWidth: 16, halign: 'center' }, // Caduca EMO
                    17: { cellWidth: 16, halign: 'center' }, // Fin Contrato
                },
                didDrawPage: data => {
                    const total = doc.internal.getNumberOfPages();
                    doc.setFontSize(7);
                    doc.setTextColor(100, 116, 139);
                    doc.text(
                        `Página ${data.pageNumber} de ${total}`,
                        pageW - marginX,
                        doc.internal.pageSize.getHeight() - 6,
                        { align: 'right' }
                    );
                    doc.setTextColor(0);
                },
            });

            doc.save(`DatosPersonal_${timestamp()}.pdf`);

        } catch (err) {
            console.error('Error exportando PDF:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo generar el PDF. Verifique que jsPDF esté cargado.' });
        }
    });

    // ── EXPORTAR EXCEL ────────────────────────────────────────
    btnExportXLS?.addEventListener('click', async function () {
        const registrosSeleccionados = getSeleccionados();
        if (!registrosSeleccionados.length) { alertarSinSel(); return; }

        try {
            // ExcelJS ya forma parte de las dependencias del proyecto y conserva
            // estilos al generar el archivo en el navegador.
            const ExcelJS = (await import('exceljs')).default;
            const cols = COLUMNAS_EXPORT.length;
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Datos Personal', { views: [{ showGridLines: false }] });

            // Encabezado igual al formato utilizado en los demás reportes.
            if (window.logoUrl) {
                try {
                    const response = await fetch(window.logoUrl);
                    if (response.ok) {
                        const logoBlob = await response.blob();
                        const logoBase64 = await new Promise(resolve => {
                            const reader = new FileReader();
                            reader.onloadend = () => resolve(reader.result);
                            reader.readAsDataURL(logoBlob);
                        });
                        const imageId = workbook.addImage({ base64: logoBase64, extension: 'png' });
                        worksheet.addImage(imageId, { tl: { col: 0, row: 0 }, ext: { width: 130, height: 62 } });
                    }
                } catch (logoError) {
                    console.warn('No se pudo incluir el logo en el Excel:', logoError);
                }
            }

            const ultimaColumna = worksheet.getColumn(cols).letter;
            const sucursalTexto = selSucursal?.selectedOptions[0]?.textContent.trim() || 'Todas';
            const tipoTexto = selTipo?.selectedOptions[0]?.textContent.trim() || 'Todos';
            const estadoTexto = document.getElementById('rptVigente')?.selectedOptions[0]?.textContent.trim() || 'Todos';
            worksheet.getRow(1).height = 64;
            worksheet.mergeCells(`C1:${ultimaColumna}1`);
            worksheet.getCell('C1').value = `REPORTE GENERAL | ${sucursalTexto.toUpperCase()} | ${tipoTexto.toUpperCase()} | ${estadoTexto.toUpperCase()}`;
            worksheet.getCell('C1').font = { bold: true, color: { argb: 'FF990000' }, size: 11 };
            worksheet.getCell('C1').alignment = { horizontal: 'left', vertical: 'center', wrapText: true };
            worksheet.mergeCells(`C2:${ultimaColumna}2`);
            worksheet.getRow(4).height = 6;

            const bloques = [
                { start: 1, end: 2, label: 'Generado', value: new Date().toLocaleString('es-PE', {
                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false,
                }) },
                { start: 3, end: 6, label: 'Personal seleccionado', value: registrosSeleccionados.length },
                { start: 7, end: 9, label: 'Sucursal', value: sucursalTexto },
                { start: 10, end: 12, label: 'Tipo de personal', value: tipoTexto },
                { start: 13, end: cols, label: 'Estado', value: estadoTexto },
            ];
            bloques.forEach(({ start, end, label, value }) => {
                worksheet.mergeCells(6, start, 6, end);
                worksheet.mergeCells(5, start, 5, end);
                const etiqueta = worksheet.getCell(5, start);
                etiqueta.value = label;
                etiqueta.font = { bold: true, size: 9, color: { argb: 'FF374151' } };
                etiqueta.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE5E7EB' } };
                const dato = worksheet.getCell(6, start);
                dato.value = value;
                dato.font = { bold: true, size: 10, color: { argb: 'FF111827' } };
                dato.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE5E7EB' } };
                [etiqueta, dato].forEach(cell => {
                    cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
                    cell.border = {
                        top: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                        bottom: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                        left: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                        right: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                    };
                });
            });

            worksheet.getRow(7).height = 6;
            const headerRowNumber = 8;
            const header = worksheet.getRow(headerRowNumber);
            header.values = COLUMNAS_EXPORT.map(c => c.label);
            header.height = 28;
            header.eachCell(cell => {
                cell.font = { bold: true, size: 9, color: { argb: 'FFFFFFFF' } };
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF4B5563' } };
                cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
                cell.border = {
                    top: { style: 'thin', color: { argb: 'FF4B5563' } },
                    bottom: { style: 'thin', color: { argb: 'FF4B5563' } },
                    left: { style: 'thin', color: { argb: 'FF4B5563' } },
                    right: { style: 'thin', color: { argb: 'FF4B5563' } },
                };
            });

            registrosSeleccionados.forEach((registro, index) => {
                const row = worksheet.addRow(COLUMNAS_EXPORT.map(c => c.key === '_it' ? index + 1 : (registro[c.key] ?? '')));
                row.eachCell(cell => {
                    cell.font = { size: 9 };
                    cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFF2CC' } };
                    cell.border = {
                        top: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                        bottom: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                        left: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                        right: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                    };
                });
            });

            worksheet.columns = [
                { width: 6 }, { width: 16 }, { width: 10 }, { width: 42 },
                { width: 12 }, { width: 12 }, { width: 15 }, { width: 15 },
                { width: 8 }, { width: 8 }, { width: 30 }, { width: 16 },
                { width: 40 }, { width: 14 }, { width: 30 }, { width: 16 },
                { width: 15 }, { width: 15 },
            ];
            worksheet.autoFilter = { from: { row: headerRowNumber, column: 1 }, to: { row: headerRowNumber, column: cols } };
            worksheet.views = [{ state: 'frozen', ySplit: headerRowNumber, showGridLines: false }];

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `DatosPersonal_${timestamp()}.xlsx`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);

        } catch (err) {
            console.error('Error exportando Excel:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: `No se pudo generar el Excel: ${err.message || 'error desconocido'}` });
        }
    });

    // ── Timestamp ─────────────────────────────────────────────
    function timestamp() {
        const f = new Date();
        return f.getFullYear()
            + String(f.getMonth() + 1).padStart(2, '0')
            + String(f.getDate()).padStart(2, '0')
            + '_'
            + String(f.getHours()).padStart(2, '0')
            + String(f.getMinutes()).padStart(2, '0');
    }

})();
