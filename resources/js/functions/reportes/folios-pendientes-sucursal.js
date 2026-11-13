import axios from 'axios';
import Swal from 'sweetalert2';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
// import XLSX from 'xlsx-style';

let datos = null;

const ABREV = {
    'ACTA DE COMPROMISO': 'ACTA\nCOMP', 'ANTECEDENTE JUDICIAL': 'ANT\nJUD', 'ANTECEDENTE PENAL': 'ANT\nPENAL',
    'ANTECEDENTE POLICIAL': 'ANT\nPOL', 'BASICO DE MERCANCIAS PELIGROSAS': 'MERC\nPEL', 'BASICO DE SEGURIDAD PORTUARIA': 'SEG\nPORT',
    'BASICO I DEL CODIGO PBIP': 'PBIP\nI', 'BASICO II DEL CODIGO PBIP': 'PBIP\nII', 'BREVETE': 'BREV',
    'CARNET DE MARINERO DE BAHIA': 'CARN\nMAR', 'CARNET PATRON DE BAHIA': 'CARN\nPAT', 'CARNET SUCAMEC': 'SUCA\nMEC',
    'CERTIFICADO DE EXAMEN MEDICO': 'CERT\nMED', 'CERTIFICADO DE EXAMEN PSICOLOGICO': 'CERT\nPSICO',
    'CERTIFICADO DE EXAMEN TOXICOLOGICO EXTERNO': 'TOX\nEXT', 'CERTIFICADO DE VACUNACION': 'CERT\nVAC',
    'CERTIFICADO UNICO LABORAL - CUL': 'CUL', 'COMPROMISO DE CONFIABILIDAD': 'CONF',
    'DECLARACION JURADA DE CUMPLIMIENTO DE DISPOSICIONES': 'DJ\nDISP', 'DECLARACION JURADA DE TRABAJADORES (DJ)': 'DJ\nTRAB',
    'DJ DE BENEFICIARIO DE POLIZA VIDA LEY': 'DJ\nPOL', 'ESTUDIO DE SEGURIDAD DE TRABAJADORES': 'EST\nSEG',
    'EVALUACION DEL POSTULANTE': 'EVAL\nPOST', 'FOTOCONTROL': 'FOTO\nCONT', 'GESTION DE SEGURIDAD PORTUARIA': 'GEST\nSEG',
    'GESTION MERCANCIAS PELIGROSAS': 'GEST\nMERC', 'INDUCCION DE SEGURIDAD': 'IND\nSEG',
    'IPER - RAD N° 0025-2024 - APN-DIR': 'IPER', 'LICENCIA DE ARMAS': 'LIC\nARM',
    'POLIZA SCTR': 'SCTR', 'POLIZA SEGURO VIDA LEY': 'SEG\nVIDA', 'VERIFICACION DOMICILIARIA': 'VERIF\nDOM',
};

function abreviar(n) { const u = n?.toUpperCase().trim(); return ABREV[u] || u; }

export function init() {
    document.getElementById('btnGenerarFoliosPendientes').addEventListener('click', generar);
    document.getElementById('btnExportPdfPendientes').addEventListener('click', exportarPdf);
    document.getElementById('btnExportExcelPendientes').addEventListener('click', exportarExcel);
}

function generar() {
    const sucursal = document.getElementById('sucursal').value;
    if (!sucursal) { Swal.fire({ icon: 'warning', title: 'Sucursal requerida', text: 'Seleccione una sucursal' }); return; }

    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    axios.get(`${VITE_URL_APP}/api/reporte/folios-pendientes-sucursal`, { params: { sucursal } })
        .then(response => {
            if (!response.data.length) { Swal.fire({ icon: 'warning', title: 'Sin resultados', text: 'No hay folios pendientes' }); return; }
            datos = response.data;
            renderTabla(datos);
            Swal.close();
        })
        .catch(() => Swal.fire('Error', 'No se pudo cargar el reporte', 'error'));
}

function buildPorSucursal(data) {
    const r = {};
    data.forEach(g => {
        if (!r[g.sucursal]) r[g.sucursal] = [];
        g.personal.forEach(p => r[g.sucursal].push({ nombre: p.personal, docs: p.documentos }));
    });
    return r;
}

function buildTodosDocs(data) {
    const s = new Set();
    data.forEach(g => g.personal.forEach(p => p.documentos.forEach(d => s.add(d.documento?.toUpperCase().trim()))));
    return [...s];
}

function renderTabla(data) {
    const porSucursal = buildPorSucursal(data);
    const todosLosDocs = buildTodosDocs(data);
    let totalPersonas = 0, html = '';

    Object.entries(porSucursal).forEach(([suc, personas]) => {
        totalPersonas += personas.length;
        html += `<div class="mb-6">
              <div class="reporte-grupo-header">Sucursal: ${suc}</div>
              <div class="tabla-scroll"><table class="tabla-reporte">
                  <thead><tr>
                      <th style="min-width:160px;text-align:left">APELLIDOS Y NOMBRES</th>
                      ${todosLosDocs.map(d => `<th style="min-width:44px">${abreviar(d).replace(/\n/g, '<br>')}</th>`).join('')}
                  </tr></thead>
                  <tbody>${personas.map(p => {
            const set = new Set(p.docs.map(d => d.documento?.toUpperCase().trim()));
            const map = {}; p.docs.forEach(d => { map[d.documento?.toUpperCase().trim()] = d.tipo_folio?.toUpperCase().trim(); });
            return `<tr><td>${p.nombre}</td>${todosLosDocs.map(doc => {
                if (!set.has(doc)) return `<td class="celda-x">X</td>`;
                return map[doc] === 'PRINCIPAL' ? `<td class="celda-p">O</td>` : `<td class="celda-a">A</td>`;
            }).join('')}</tr>`;
        }).join('')}</tbody>
              </table></div></div>`;
    });

    document.getElementById('totalFoliosPendientes').textContent = `${totalPersonas} personas · ${Object.keys(porSucursal).length} sucursal(es)`;
    document.getElementById('tablaFoliosPendientes').innerHTML = html;
    document.getElementById('resultadosFoliosPendientes').classList.remove('hidden');
}

function exportarPdf() {
    if (!datos) return;
    const porSucursal = buildPorSucursal(datos);
    const todosLosDocs = buildTodosDocs(datos);
    const doc = new jsPDF({ orientation: 'landscape', format: 'a3' });
    const PW = doc.internal.pageSize.getWidth(), PH = doc.internal.pageSize.getHeight();
    const MARGEN = 8, BANNER_H = 50;
    const fecha = new Date().toLocaleDateString('es-PE', { year: 'numeric', month: 'long', day: 'numeric' });
    const hora = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
    const anchoNomb = 44;
    const anchoDoc = Math.max(9, (PW - MARGEN * 2 - anchoNomb) / todosLosDocs.length);
    const cabecera = ['APELLIDOS Y NOMBRES', ...todosLosDocs.map(d => abreviar(d))];
    const colStyles = { 0: { cellWidth: anchoNomb, overflow: 'linebreak' } };
    todosLosDocs.forEach((_, i) => { colStyles[i + 1] = { cellWidth: anchoDoc, halign: 'center' }; });

    function construirFilas(personas) {
        return personas.map(p => {
            const set = new Set(p.docs.map(d => d.documento?.toUpperCase().trim()));
            const map = {}; p.docs.forEach(d => { map[d.documento?.toUpperCase().trim()] = d.tipo_folio?.toUpperCase().trim(); });
            return [
                { content: p.nombre, styles: { fontSize: 6.5, cellPadding: { top: 2, bottom: 2, left: 2, right: 2 } } },
                ...todosLosDocs.map(doc => {
                    const tiene = set.has(doc), tipo = map[doc];
                    return {
                        content: tiene ? (tipo === 'PRINCIPAL' ? 'O' : 'A') : 'X',
                        styles: {
                            fillColor: tiene ? (tipo === 'PRINCIPAL' ? [34, 139, 34] : [41, 128, 185]) : [192, 57, 43],
                            textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center',
                            fontSize: 7, cellPadding: { top: 2, bottom: 2, left: 1, right: 1 }
                        }
                    };
                })
            ];
        });
    }

    function renderPdf(imgEl) {
        let primera = true;
        Object.entries(porSucursal).forEach(([suc, personas]) => {
            if (!primera) doc.addPage();
            const yTop = primera && imgEl ? BANNER_H + 3 : 12;
            if (primera && imgEl) doc.addImage(imgEl, 'JPEG', 0, 0, PW, BANNER_H);
            let y = yTop;
            doc.setFontSize(8); doc.setFont(undefined, 'bold');
            doc.setFillColor(6, 10, 81); doc.rect(MARGEN, y, PW - MARGEN * 2, 6.5, 'F');
            doc.setTextColor(255, 255, 255); doc.text(`Sucursal: ${suc}`, MARGEN + 2, y + 4.5); doc.setTextColor(0, 0, 0);
            y += 9;
            [{ color: [34, 139, 34], l: 'O', t: '= OBLIGATORIO' }, { color: [41, 128, 185], l: 'A', t: '= ADICIONAL' }, { color: [192, 57, 43], l: 'X', t: '= PENDIENTE' }].forEach((item, xi) => {
                const lx = MARGEN + xi * 50;
                doc.setFillColor(...item.color); doc.roundedRect(lx, y, 5, 4, 0.8, 0.8, 'F');
                doc.setTextColor(255, 255, 255); doc.setFont(undefined, 'bold'); doc.setFontSize(6.5); doc.text(item.l, lx + 2.5, y + 3, { align: 'center' });
                doc.setTextColor(60, 60, 60); doc.setFont(undefined, 'normal'); doc.text(item.t, lx + 7, y + 3);
            });
            y += 8;
            autoTable(doc, {
                startY: y, head: [cabecera], body: construirFilas(personas),
                styles: { fontSize: 6, cellPadding: { top: 2, bottom: 2, left: 1, right: 1 }, lineColor: [200, 200, 210], lineWidth: 0.15, overflow: 'linebreak', minCellHeight: 8 },
                headStyles: {
                    fillColor: [15, 23, 80], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center', fontSize: 5.5, cellPadding: { top: 2, bottom: 2, left: 1, right: 1 }, minCellHeight:
                        14, valign: 'middle'
                },
                alternateRowStyles: { fillColor: [248, 249, 252] }, columnStyles: colStyles, margin: { left: MARGEN, right: MARGEN }, theme: 'grid'
            });
            primera = false;
        });
        const pc = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pc; i++) {
            doc.setPage(i);
            doc.setDrawColor(210, 215, 230); doc.setLineWidth(0.4); doc.line(MARGEN, PH - 12, PW - MARGEN, PH - 12);
            doc.setFontSize(6.5); doc.setTextColor(150, 150, 160); doc.setFont(undefined, 'normal');
            doc.text('Sistema de Gestión de Recursos Humanos', MARGEN + 2, PH - 7);
            doc.text(`Generado el ${fecha} a las ${hora}`, PW / 2, PH - 7, { align: 'center' });
            doc.setFont(undefined, 'bold'); doc.setTextColor(80, 80, 100);
            doc.text(`Página ${i} de ${pc}`, PW - MARGEN - 2, PH - 7, { align: 'right' });
        }
        const link = document.createElement('a'); link.href = URL.createObjectURL(doc.output('blob')); link.download = 'folios_pendientes_sucursal.pdf'; link.click();
    }

    const img = new Image(); img.crossOrigin = 'anonymous';
    img.onload = () => renderPdf(img);
    img.onerror = () => renderPdf(null);
    img.src = `${VITE_URL_APP}/images/banners/banner_folios_pendientes.jpeg`;
}

function exportarExcel() {
    if (!datos) return;
    const porSucursal = buildPorSucursal(datos);
    const todosLosDocs = buildTodosDocs(datos);
    const fecha = new Date().toLocaleDateString('es-PE');

    const BASE = 'border:1px solid #d1d5db;padding:3px 4px;font-size:8pt;';
    const TH = 'background:#0f1750;color:#fff;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid #1e3a5f;padding:4px;font-size:7.5pt;white-space:nowrap';
    const TH_SUC = 'background:#dce1f5;color:#060a51;font-weight:bold;text-align:left;border:1px solid #b0b8d8;padding:4px;font-size:8.5pt;';

    const th = (txt) => `<th style="${TH}">${txt}</th>`;

    let html = `<html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
      <head><meta charset="UTF-8">
      <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets>
      <x:ExcelWorksheet><x:Name>Folios Pendientes</x:Name>
      <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
      </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
      </head><body>
      <table border="0" cellspacing="0" cellpadding="0">
      <tr><td colspan="${todosLosDocs.length + 2}" style="font-size:11pt;font-weight:bold;text-align:center;padding:6px;border:none">
          FOLIOS PENDIENTES POR SUCURSAL — ${fecha}
      </td></tr>
      <tr>${th('Sucursal')}${th('Apellidos y Nombres')}${todosLosDocs.map(d => th(abreviar(d).replace(/\n/g, ' '))).join('')}</tr>`;

    Object.entries(porSucursal).forEach(([suc, personas]) => {
        html += `<tr><td colspan="${todosLosDocs.length + 2}" style="${TH_SUC}">Sucursal: ${suc}</td></tr>`;

        personas.forEach((p, i) => {
            const set = new Set(p.docs.map(d => d.documento?.toUpperCase().trim()));
            const map = {}; p.docs.forEach(d => { map[d.documento?.toUpperCase().trim()] = d.tipo_folio?.toUpperCase().trim(); });
            const alt = i % 2 !== 0 ? 'background:#f8f9fc;' : '';
            const tdN = (v, left = false) => `<td style="${BASE}${left ? '' : 'text-align:center;'}${alt}">${v ?? ''}</td>`;

            const celdas = todosLosDocs.map(doc => {
                if (!set.has(doc))
                    return `<td style="${BASE}background:#c0392b;color:#fff;font-weight:bold;text-align:center">X</td>`;
                if (map[doc] === 'PRINCIPAL')
                    return `<td style="${BASE}background:#228b22;color:#fff;font-weight:bold;text-align:center">P</td>`;
                return `<td style="${BASE}background:#2980b9;color:#fff;font-weight:bold;text-align:center">A</td>`;
            }).join('');

            html += `<tr>${tdN(suc)}${tdN(p.nombre, true)}${celdas}</tr>`;
        });
    });

    html += `</table></body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'folios_pendientes_sucursal.xls';
    link.click();
}

function _descargar(wb, nombre) {
    const out = XLSX.write(wb, { bookType: 'xlsx', bookSST: true, type: 'binary' });
    const buf = new ArrayBuffer(out.length);
    const view = new Uint8Array(buf);
    for (let i = 0; i < out.length; i++) view[i] = out.charCodeAt(i) & 0xFF;
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([buf], { type: 'application/octet-stream' }));
    a.download = nombre;
    a.click();
}
