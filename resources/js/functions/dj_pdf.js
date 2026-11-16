// ============================================================
// dj_pdf.js — Generación de PDF para Declaración Jurada
// ============================================================

import axios from 'axios';
import Swal from 'sweetalert2';

const API_URL_PDF = `${VITE_URL_APP}/api`;
const FONT_FAMILY = "helvetica";

async function fetchBiometrico(codiPers) {
    if (!codiPers) return {};
    try {
        const resp = await axios.get(`${API_URL_PDF}/get-biometrico/${codiPers}`, { timeout: 8000 });
        return resp.data || {};
    } catch (e) {
        console.warn('No se pudo obtener datos biométricos:', e.message);
        return {};
    }
}

async function fetchFotoConRetry(codiPers, maxReintentos = 3) {
    for (let intento = 0; intento <= maxReintentos; intento++) {
        try {
            const resp = await axios.get(`${API_URL_PDF}/dj/proxy-foto`, {
                params: { codi_pers: codiPers }
            });
            if (resp.data?.success && resp.data?.base64) return resp.data.base64;
            return null; // foto no existe, no reintentar
        } catch (err) {
            if (err?.response?.status === 429 && intento < maxReintentos) {
                const retryAfter = err.response?.headers?.['retry-after'];
                const espera = retryAfter
                    ? parseInt(retryAfter) * 1000
                    : 1000 * Math.pow(2, intento);
                console.warn(`[429] proxy-foto ${codiPers} — reintento ${intento + 1}`);
                await new Promise(r => setTimeout(r, espera));
                continue;
            }
            console.warn('No se pudo obtener foto via proxy:', err);
            return null;
        }
    }
    return null;
}

function drawFotoBorder(pdf, x, y, w, h, leftWidth = 0.6, otherWidth = 0.20) {
    pdf.setDrawColor(0);

    pdf.setLineWidth(otherWidth);
    pdf.line(x, y, x + w, y);
    pdf.line(x + w, y, x + w, y + h);
    pdf.line(x, y + h, x + w, y + h);

    const leftX = x + 0.15;
    pdf.setLineWidth(leftWidth);
    pdf.line(leftX, y + 0.1, leftX, y + h - 0.1);
}

export async function drawFotoEnPDF(pdf, x, y, w, h) {
    let imageSrc = "";
    try {
        const preview = document.getElementById("previewFoto");
        if (preview && preview.src && !preview.classList.contains("hidden")) {
            const src = preview.src;
            if (src && src.startsWith("data:")) {
                imageSrc = src;
            } else if (src && (src.startsWith("http://") || src.startsWith("https://"))) {
                try {
                    const urlObj = new URL(src);
                    const pathParts = urlObj.pathname.split('/');
                    const filename = pathParts[pathParts.length - 1];
                    const codiPers = filename.replace('.jpg','').replace('.jpeg','').replace('.png','');
                    // const resp = await axios.get(`${API_URL_PDF}/dj/proxy-foto`, { params: { codi_pers: codiPers } });
                    // if (resp.data?.success && resp.data?.base64) imageSrc = resp.data.base64;
                    const base64 = await fetchFotoConRetry(codiPers);
if (base64) imageSrc = base64;
                } catch (proxyErr) {
                    console.warn('No se pudo obtener foto via proxy:', proxyErr);
                }
            }
        }

        if (!imageSrc) {
            drawFotoBorder(pdf, x, y, w, h, 0.4, 0.20);

            pdf.setFontSize(8);
            pdf.setFont(FONT_FAMILY, "normal");
            pdf.setTextColor(150);
            pdf.text("FOTO", x + w / 2, y + h / 2, { align: "center" });
            return;
        }

        pdf.setFillColor(255, 255, 255);
        pdf.rect(x, y, w, h, "F");

        const padding = 1;
        const maxW = w - padding * 2;
        const maxH = h - padding * 2;
        const props = pdf.getImageProperties(imageSrc);
        const ratio = Math.min(maxW / props.width, maxH / props.height);
        const finalW = props.width * ratio;
        const finalH = props.height * ratio;
        const offsetX = x + padding + (maxW - finalW) / 2;
        const offsetY = y + padding + (maxH - finalH) / 2;

        let format = "JPEG";
        if (imageSrc.startsWith("data:image/png")) format = "PNG";
        if (imageSrc.startsWith("data:image/webp")) format = "WEBP";

        pdf.addImage(imageSrc, format, offsetX, offsetY, finalW, finalH);

        drawFotoBorder(pdf, x, y, w, h, 0.4, 0.20);

    } catch (error) {
        console.error("Error dibujando foto en PDF:", error);

        drawFotoBorder(pdf, x, y, w, h, 0.4, 0.20);

        pdf.setFontSize(8);
        pdf.setFont(FONT_FAMILY, "normal");
        pdf.setTextColor(150);
        pdf.text("FOTO", x + w / 2, y + h / 2, { align: "center" });
    }
}

// export async function drawFotoEnPDF(pdf, x, y, w, h) {
//     let imageSrc = "";
//     try {
//         const preview = document.getElementById("previewFoto");
//         if (preview && preview.src && !preview.classList.contains("hidden")) {
//             const src = preview.src;
//             if (src && src.startsWith("data:")) {
//                 imageSrc = src;
//             } else if (src && (src.startsWith("http://") || src.startsWith("https://"))) {
//                 try {
//                     const urlObj   = new URL(src);
//                     const pathParts = urlObj.pathname.split('/');
//                     const filename  = pathParts[pathParts.length - 1];
//                     const codiPers  = filename.replace('.jpg','').replace('.jpeg','').replace('.png','');
//                     const resp = await axios.get(`${API_URL_PDF}/dj/proxy-foto`, { params: { codi_pers: codiPers } });
//                     if (resp.data?.success && resp.data?.base64) imageSrc = resp.data.base64;
//                 } catch (proxyErr) { console.warn('No se pudo obtener foto via proxy:', proxyErr); }
//             }
//         }

//         // pdf.setDrawColor(0); pdf.setLineWidth(0.20);
//         // pdf.rect(x, y, w, h);

//         // pdf.setDrawColor(0);
//         // pdf.setLineWidth(1.0); // ajusta: 0.8, 1.0, 1.2
//         // pdf.line(x, y, x, y + h);
//         drawFotoBorder(pdf, x, y, w, h, 1.0, 0.20);

//         if (!imageSrc) {
//             pdf.setFontSize(8); pdf.setFont(FONT_FAMILY, "normal"); pdf.setTextColor(150);
//             pdf.text("FOTO", x + w / 2, y + h / 2, { align: "center" });
//             return;
//         }

//         pdf.setFillColor(255, 255, 255);
//         pdf.rect(x, y, w, h, "F");

//         const padding = 1;
//         const maxW    = w - padding * 2;
//         const maxH    = h - padding * 2;
//         const props   = pdf.getImageProperties(imageSrc);
//         const ratio   = Math.min(maxW / props.width, maxH / props.height);
//         const finalW  = props.width  * ratio;
//         const finalH  = props.height * ratio;
//         const offsetX = x + padding + (maxW - finalW) / 2;
//         const offsetY = y + padding + (maxH - finalH) / 2;

//         let format = "JPEG";
//         if (imageSrc.startsWith("data:image/png"))  format = "PNG";
//         if (imageSrc.startsWith("data:image/webp")) format = "WEBP";
//         pdf.addImage(imageSrc, format, offsetX, offsetY, finalW, finalH);

//         // pdf.setDrawColor(0); pdf.setLineWidth(0.20);
//         // pdf.rect(x, y, w, h);

//         drawFotoBorder(pdf, x, y, w, h, 1.0, 0.20);

//     } catch (error) {
//         console.error("Error dibujando foto en PDF:", error);
//         // pdf.setDrawColor(0); pdf.setLineWidth(0.20); pdf.rect(x, y, w, h);
//         drawFotoBorder(pdf, x, y, w, h, 1.0, 0.20);
//         pdf.setFontSize(8); pdf.setFont(FONT_FAMILY, "normal"); pdf.setTextColor(150);
//         pdf.text("FOTO", x + w / 2, y + h / 2, { align: "center" });
//     }
// }

async function drawLogo(pdf, x, y, w, h) {
    if (!window.logoUrl) {
        pdf.setFontSize(8); pdf.setTextColor(0); pdf.setFont(FONT_FAMILY, "normal");
        pdf.text("SOLMAR", x + w / 2, y + h / 2, { align: "center" });
        return;
    }
    try {
        const response = await fetch(window.logoUrl);
        const blob     = await response.blob();
        const reader   = new FileReader();
        await new Promise(resolve => {
            reader.onload = (e) => {
                const imgData  = e.target.result;
                const props    = pdf.getImageProperties(imgData);
                const imgRatio = props.width / props.height;
                const padding  = 2;
                const maxW     = w - padding * 2;
                const maxH     = h - padding * 2;
                const boxRatio = maxW / maxH;
                let finalW, finalH;
                if (imgRatio > boxRatio) { finalW = maxW; finalH = maxW / imgRatio; }
                else                     { finalH = maxH; finalW = maxH * imgRatio; }
                pdf.addImage(imgData, "PNG", x + (w - finalW) / 2, y + (h - finalH) / 2, finalW, finalH);
                resolve();
            };
            reader.readAsDataURL(blob);
        });
    } catch (e) {
        console.error("error logo", e);
        pdf.text("SOLMAR", x + w / 2, y + h / 2, { align: "center" });
    }
}

function drawJustifiedText(pdf, lines, x, y, maxWidth, lineHeight) {
    lines.forEach((line, index) => {
        const trimmed    = line.trim();
        const isLastLine = index === lines.length - 1;
        if (isLastLine || pdf.getTextWidth(trimmed) < maxWidth * 0.4) {
            pdf.text(trimmed, x, y + index * lineHeight);
            return;
        }
        const words = trimmed.split(' ').filter(w => w.length > 0);
        if (words.length <= 1) { pdf.text(trimmed, x, y + index * lineHeight); return; }
        const totalWordsWidth = words.reduce((acc, w) => acc + pdf.getTextWidth(w), 0);
        const spaceWidth      = (maxWidth - totalWordsWidth) / (words.length - 1);
        let curX = x;
        words.forEach((word, wi) => {
            pdf.text(word, curX, y + index * lineHeight);
            curX += pdf.getTextWidth(word) + (wi < words.length - 1 ? spaceWidth : 0);
        });
    });
}

function getValue(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '--') : '--';
}

// Ajusta un texto dentro de un ancho de celda: primero reduce la fuente y,
// si aun asi no cabe, trunca con "..." para que nunca se superponga a la celda vecina.
// El caller debe fijar la fuente (setFont/setFontSize) antes de llamar.
function fitCellText(pdf, text, maxWidth, maxFontSize = 8, minFontSize = 5) {
    let str = String(text ?? "");
    if (!str || maxWidth <= 0) return "";
    let size = maxFontSize;
    pdf.setFontSize(size);
    while (pdf.getTextWidth(str) > maxWidth && size > minFontSize) {
        size -= 0.2;
        pdf.setFontSize(size);
    }
    if (pdf.getTextWidth(str) <= maxWidth) return str;
    while (pdf.getTextWidth(str + "...") > maxWidth && str.length > 1) str = str.slice(0, -1);
    return str + "...";
}

function getCleanSelectText(id) {
    const el = document.getElementById(id);
    if (!el) return "";
    const text = el.options[el.selectedIndex]?.text || "";
    if (text.toUpperCase().includes("SELECCIONAR") || text.toUpperCase().includes("SELECCIONE")) return "";
    return text;
}

function formatDateToDMY(fecha) {
    if (!fecha) return "";
    if (fecha instanceof Date) {
        return `${String(fecha.getDate()).padStart(2,'0')}/${String(fecha.getMonth()+1).padStart(2,'0')}/${fecha.getFullYear()}`;
    }
    const s = String(fecha);
    if (!s.includes("-")) return s;
    const p = s.split("-");
    return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : s;
}

export async function generarDeclaracionJuradaPDF(returnBlob = false) {
    try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ unit: "mm", format: "a4", compress: true });

        const pageWidth    = 210;
        const pageHeight   = 297;
        const marginLeft   = 10;
        const marginRight  = 10;
        const marginTop    = 10;
        const marginBottom = 10;
        const boxWidth     = pageWidth - marginLeft - marginRight;
        const boxX         = marginLeft;
        let   y            = marginTop;

        const rowH          = 6.0;
        const lineH         = 5.2;   // interlineado párrafo conformidad
        const declLineH     = 5.2;   // ← interlineado párrafo declaración — SUBE para más espacio, BAJA para menos
        const sectionTitleH = 5.5;
        const sectionGap    = 1.5;
        const paraTopPad    = 3.5;
        const paraBottomPad = 3.0;
        const firmaMinH     = 45;
        const footerRowH    = rowH;

        const tipoPers = (document.getElementById('tipo_personal')?.value || '').trim();
        const esOper   = tipoPers === '03';
        const esAdmin  = tipoPers === '05';

        const colors = {
            sectionBg:   [210, 210, 210],
            sectionText: [0, 0, 0],
            labelBg:     [220, 220, 220],
            labelText:   [0, 0, 0],
            inputText:   [0, 0, 0],
            borderColor: [0, 0, 0],
        };

        function fitText(text, maxWidth, initialFontSize = 8, minFontSize = 5) {
            pdf.setFontSize(initialFontSize);
            let sz = initialFontSize;
            while (pdf.getTextWidth(text) > maxWidth && sz > minFontSize) { sz -= 0.3; pdf.setFontSize(sz); }
            return sz;
        }

        function drawField(label, value, x, width, fieldY, inputHeight = rowH,
                           labelRatio = 0.35, alignValue = "left",
                           omitTop = false, omitRight = false, boldValue = false, boldLabel = false) {
            const labelWidth = width * labelRatio;
            const valueWidth = width * (1 - labelRatio);
            const valStr     = String(value || "").toUpperCase();

            pdf.setFillColor(...colors.labelBg);
            pdf.rect(x, fieldY, labelWidth, inputHeight, "F");
            pdf.setFillColor(255, 255, 255);
            pdf.rect(x + labelWidth, fieldY, valueWidth, inputHeight, "F");

            pdf.setDrawColor(...colors.borderColor); pdf.setLineWidth(0.20);
            if (!omitTop)   pdf.line(x, fieldY, x + width, fieldY);
            pdf.line(x, fieldY, x, fieldY + inputHeight);
            if (!omitRight) pdf.line(x + width, fieldY, x + width, fieldY + inputHeight);
            pdf.line(x, fieldY + inputHeight, x + width, fieldY + inputHeight);
            pdf.line(x + labelWidth, fieldY, x + labelWidth, fieldY + inputHeight);

            pdf.setFont(FONT_FAMILY, boldLabel ? "bold" : "normal"); pdf.setTextColor(...colors.labelText);
            const maxLabelW = labelWidth - 2;
            // Reducir fuente hasta entrar en una línea — nunca partir la etiqueta
            let lblSize = 7.5;
            pdf.setFontSize(lblSize);
            while (pdf.getTextWidth(label) > maxLabelW && lblSize > 5) {
                lblSize -= 0.2; pdf.setFontSize(lblSize);
            }
            // Si aun asi no cabe, truncar para no invadir la celda del valor
            let lblText = label;
            if (pdf.getTextWidth(lblText) > maxLabelW) {
                while (pdf.getTextWidth(lblText + "...") > maxLabelW && lblText.length > 1) lblText = lblText.slice(0, -1);
                lblText += "...";
            }
            pdf.text(lblText, x + 1, fieldY + inputHeight / 2 + 1, { align: "left" });

            pdf.setFont(FONT_FAMILY, boldValue ? "bold" : "normal"); pdf.setTextColor(...colors.inputText);
            const maxValW     = valueWidth - (alignValue === "center" ? 1 : 2);
            const valFontSize = fitText(valStr, maxValW, 8, 5);
            pdf.setFontSize(valFontSize);
            // Truncar con ... si aún no cabe
            let displayVal = valStr;
            if (pdf.getTextWidth(displayVal) > maxValW) {
                while (pdf.getTextWidth(displayVal + "...") > maxValW && displayVal.length > 1)
                    displayVal = displayVal.slice(0, -1);
                displayVal = displayVal + "...";
            }
            const valX = alignValue === "center" ? x + labelWidth + valueWidth / 2 : x + labelWidth + 1;
            pdf.text(displayVal, valX, fieldY + inputHeight / 2 + 1, { align: alignValue });
        }

        // ── Variante sin borde derecho (para filas junto a la foto) ──
        function drawFieldNoRight(label, value, x, width, fieldY, inputHeight = rowH, labelRatio = 0.35, alignValue = "left", boldValue = false, boldLabel = false) {
            drawField(label, value, x, width, fieldY, inputHeight, labelRatio, alignValue, false, true, boldValue, boldLabel);
            // Dibujar línea de cierre en boxX + boxWidth (borde exterior de la tabla)
            pdf.setDrawColor(...colors.borderColor); pdf.setLineWidth(0.20);
            pdf.line(boxX + boxWidth, fieldY, boxX + boxWidth, fieldY + inputHeight);
        }

        function drawSectionTitle(title, yPos) {
            pdf.setFillColor(...colors.sectionBg); pdf.rect(boxX, yPos, boxWidth, sectionTitleH, "F");
            pdf.setDrawColor(...colors.borderColor); pdf.setLineWidth(0.20); pdf.rect(boxX, yPos, boxWidth, sectionTitleH);
            pdf.setFontSize(8.5); pdf.setFont(FONT_FAMILY, "bold"); pdf.setTextColor(...colors.sectionText);
            pdf.text(title, boxX + boxWidth / 2, yPos + sectionTitleH / 2 + 1.2, { align: "center" });
        }

        function checkPageBreak(heightNeeded) {
            if (y + heightNeeded > pageHeight - marginBottom - 1) { pdf.addPage(); y = marginTop; return true; }
            return false;
        }

        const drawAutoFitField = (label, value, x, w, yPos, h, labelPct) => {
            const labelW = w * labelPct, valW = w - labelW;
            pdf.setFillColor(220); pdf.rect(x, yPos, labelW, h, "F");
            pdf.setFillColor(255); pdf.rect(x + labelW, yPos, valW, h, "F");
            pdf.setDrawColor(0); pdf.setLineWidth(0.20);
            pdf.rect(x, yPos, w, h);
            pdf.line(x + labelW, yPos, x + labelW, yPos + h);
            pdf.setFont(FONT_FAMILY, "normal"); pdf.setTextColor(0);
            const lblFontSize = fitText(label, labelW - 2, 7.5, 5);
            pdf.setFontSize(lblFontSize);
            pdf.text(fitCellText(pdf, label, labelW - 2, lblFontSize, 5), x + labelW / 2, yPos + h / 2 + 1, { align: "center" });
            pdf.setFont(FONT_FAMILY, "normal");
            if (!value) return;
            const maxValW = valW - 2;
            // Paso 1: reducir fuente hasta 6pt intentando que entre en 1 línea
            let fontSize = 8;
            pdf.setFontSize(fontSize);
            while (pdf.getTextWidth(value) > maxValW && fontSize > 6) {
                fontSize -= 0.2; pdf.setFontSize(fontSize);
            }
            // Paso 2: intentar en 2 líneas con la fuente actual
            const allLines = pdf.splitTextToSize(value, maxValW);
            if (allLines.length <= 2) {
                const vLineH  = fontSize * 0.3527 * 1.2;
                const vBlockH = allLines.length * vLineH;
                const vY      = yPos + (h - vBlockH) / 2 + vLineH;
                pdf.text(allLines, x + labelW + valW / 2, vY, { align: "center", lineHeightFactor: 1.2 });
            } else {
                // Paso 3: 2 líneas y truncar la 2da con ...
                const line1 = allLines[0];
                let   line2 = allLines[1];
                while (pdf.getTextWidth(line2 + "...") > maxValW && line2.length > 1)
                    line2 = line2.slice(0, -1);
                line2 += "...";
                const vLineH  = fontSize * 0.3527 * 1.2;
                const vBlockH = 2 * vLineH;
                const vY      = yPos + (h - vBlockH) / 2 + vLineH;
                pdf.text([line1, line2], x + labelW + valW / 2, vY, { align: "center", lineHeightFactor: 1.2 });
            }
        };

        // ════════════════════════════════════════════════════════
        // CABECERA
        // ════════════════════════════════════════════════════════
        const headerH = 20, logoW = 32, codeW = 22;
        const titleW = boxWidth - logoW - codeW, titleX = boxX + logoW, codeX = titleX + titleW;

        await drawLogo(pdf, boxX, y, logoW, headerH);
        pdf.setFontSize(10); pdf.setTextColor(200, 0, 0); pdf.setFont(FONT_FAMILY, "bold");
        pdf.text("SISTEMA INTEGRADO SOLMAR – SISOLMAR", titleX + titleW / 2, y + 7, { align: "center" });
        pdf.setFontSize(13); pdf.setTextColor(0, 0, 0); pdf.setFont(FONT_FAMILY, "bold");
        pdf.text("DECLARACION JURADA DEL TRABAJADOR", titleX + titleW / 2, y + 14, { align: "center" });
        pdf.setFillColor(255, 255, 255); pdf.rect(codeX, y, codeW, headerH, "F");
        pdf.setDrawColor(0); pdf.setLineWidth(0.2);
        pdf.rect(boxX, y, boxWidth, headerH);
        pdf.line(boxX + logoW, y, boxX + logoW, y + headerH);
        pdf.line(codeX, y, codeX, y + headerH);
        pdf.setFontSize(20); pdf.setFont(FONT_FAMILY, "bold"); pdf.setTextColor(0);
        pdf.text(esAdmin ? 'RH 02' : 'RH 01', codeX + codeW / 2, y + headerH / 2 + 3, { align: "center" });
        y += headerH;

        // ════════════════════════════════════════════════════════
        // DECLARACIÓN TEXTUAL
        // ════════════════════════════════════════════════════════
        const nombres  = getValue("nombres_apellidos").toUpperCase().trim();
        const dni      = getValue("dni").trim();
        const maxParaW = boxWidth - 4;

        const segments = [
            { text: "Yo,",          font: "normal" },
            ...nombres.split(/\s+/).map(w => ({ text: w, font: "bold" })),
            { text: ",",            font: "normal" },
            { text: "identificado", font: "normal" },
            { text: "con",          font: "normal" },
            { text: "DNI",          font: "normal" },
            ...dni.split(/\s+/).map(w => ({ text: w, font: "bold" })),
            { text: ",",            font: "normal" },
            ...("declaro bajo juramento que los datos personales, laborales y familiares que consigno en este documento son correctos, por lo que asumo la responsabilidad por su veracidad, cumplimiento y actualización, estando conforme con esta declaración jurada.")
                .split(/\s+/).map(w => ({ text: w, font: "normal" })),
        ].filter(t => t.text);

        pdf.setFontSize(8.5);
        let measureX = 0, lineCount = 1;
        for (let i = 0; i < segments.length; i++) {
            pdf.setFont(FONT_FAMILY, segments[i].font);
            const ww = pdf.getTextWidth(segments[i].text + " ");
            if (measureX + pdf.getTextWidth(segments[i].text) > maxParaW && measureX > 0) { lineCount++; measureX = ww; }
            else measureX += ww;
        }
        // declLineH controla el interlineado de este párrafo
        const declBoxH = lineCount * declLineH + paraTopPad + paraBottomPad;

        pdf.setDrawColor(0); pdf.setLineWidth(0.20); pdf.rect(boxX, y, boxWidth, declBoxH);
        pdf.setTextColor(0); pdf.setFontSize(8.5); pdf.setFont(FONT_FAMILY, "normal");

   
        // const fullText = `Yo, ${nombres}, identificado con DNI ${dni}, declaro bajo juramento que los datos personales, laborales y familiares que consigno en este documento son correctos, por lo que asumo la responsabilidad por su veracidad, cumplimiento y actualización, estando conforme con esta declaración jurada.`;
        // const declLines = pdf.splitTextToSize(fullText, maxParaW);
        // drawJustifiedText(pdf, declLines, boxX + 2, y + paraTopPad, maxParaW, declLineH);
        y += 2.11;
        const fullText = `Yo, ${nombres}, identificado con DNI ${dni}, declaro bajo juramento que los datos personales, laborales y familiares que consigno en este documento son correctos, por lo que asumo la responsabilidad por su veracidad, cumplimiento y actualización, estando conforme con esta declaración jurada.`;

        const declLines = pdf.splitTextToSize(fullText, maxParaW);

        // Altura total ocupada por las líneas
        const textBlockH = declLines.length * declLineH;

        // Y inicial centrado verticalmente dentro del recuadro
        const textStartY = y + (declBoxH - textBlockH) / 2 + 1;

        // Mantienes el texto justificado
        drawJustifiedText(pdf, declLines, boxX + 2, textStartY, maxParaW, declLineH);   
        y += declBoxH - 1.9;

        // ════════════════════════════════════════════════════════
        // SECCIÓN: DATOS PERSONALES
        // ════════════════════════════════════════════════════════
        drawSectionTitle("MIS DATOS PERSONALES", y);
        y += sectionTitleH;

        const colMain = boxWidth - 35;
        const colFoto = 35;
        const fotoH   = rowH * 6;

        // Fila 1: Nombres (colMain) + foto (colFoto)
        drawField("Nombres y Apellidos", nombres, boxX, colMain, y, rowH, 0.25);
        await drawFotoEnPDF(pdf, boxX + colMain, y, colFoto, fotoH);
        y += rowH;

        // Filas 2-5: igual que antes pero con línea de cierre derecha en boxX+boxWidth
        // FIX: drawFieldNoRight dibuja el campo hasta colMain y cierra con línea en boxWidth
        const w1 = colMain / 4;
        drawField("DNI",          dni,                               boxX,                     w1,                                   y, rowH, 0.3,    "left", false, false);
        drawField("Caduca",       formatDateToDMY(getValue("caduca")),                boxX + w1,                boxWidth * 0.381 - w1,                y, rowH, 0.461,  "left", false, false);
         const estadosCiviles = {
            '2007000001': 'SOLTERO', '2007000002': 'CASADO',
            '2007000003': 'DIVORCIADO', '2007000004': 'VIUDO', '2007000008': 'CONVIVIENTE'
        };
        const estadoCivilTexto = estadosCiviles[getValue("estado_civil").trim()] || getValue("estado_civil");

        drawField("Estado Civil", estadoCivilTexto,          boxX + boxWidth * 0.381,  boxWidth * 0.6279 - boxWidth * 0.381, y, rowH, 0.589,  "left", false, false);
        drawFieldNoRight("Sexo",  getValue("sexo"),                  boxX + boxWidth * 0.6279, colMain - boxWidth * 0.6279,          y, rowH, 0.55);
        y += rowH;

        drawField("Fecha Nacimiento", formatDateToDMY(getValue("fecha_nacimiento")), boxX, boxWidth * 0.381, y, rowH, 0.394);
        drawFieldNoRight("Ciudad", getCleanSelectText("provincia_actual"), boxX + boxWidth * 0.381, colMain - boxWidth * 0.381, y, rowH, 0.334);
        y += rowH;

        const w3 = colMain / 4;
        drawField("Tipo Sangre", getValue("tipo_sangre"), boxX,                     w3,                                    y, rowH, 0.735);
        drawField("Peso (Kg.)",  getValue("peso"),         boxX + w3,               boxWidth * 0.381 - w3,                 y, rowH, 0.461);
        drawField("Talla (Mt.)", getValue("talla"),        boxX + boxWidth * 0.381, boxWidth * 0.6279 - boxWidth * 0.381,  y, rowH, 0.589);
        drawFieldNoRight("Celular", getValue("celular"),   boxX + boxWidth * 0.6279, colMain - boxWidth * 0.6279,          y, rowH, 0.55, "left", false, true);
        y += rowH;

        drawField("Correo electrónico", getValue("correo"),   boxX,                     boxWidth * 0.6279,             y, rowH, 0.239, "left", false, false, false, true);
        drawFieldNoRight("WhatsApp",    getValue("whatsapp"), boxX + boxWidth * 0.6279, colMain - boxWidth * 0.6279,   y, rowH, 0.55, "left", false, true);
        y += rowH;

        // Fila 6 — AFP/ONP label (ancho completo)
        const row6LabelW = boxWidth * 0.5264;
        const row6InputW = colMain - row6LabelW;
        pdf.setFillColor(220); pdf.rect(boxX, y, row6LabelW, rowH, "F");
        pdf.setFillColor(255); pdf.rect(boxX + row6LabelW, y, row6InputW, rowH, "F");
        pdf.setDrawColor(0); pdf.setLineWidth(0.20);
        pdf.line(boxX, y, boxX + colMain, y);
        pdf.line(boxX, y, boxX, y + rowH);
        pdf.line(boxX + colMain, y, boxX + colMain, y + rowH);
        pdf.line(boxX, y + rowH, boxX + colMain, y + rowH);
        pdf.line(boxX + row6LabelW, y, boxX + row6LabelW, y + rowH);
        // Línea de cierre derecha
        pdf.line(boxX + boxWidth, y, boxX + boxWidth, y + rowH);
        pdf.setTextColor(0); pdf.setFont(FONT_FAMILY, "normal"); pdf.setFontSize(7.5);
        pdf.text("No estoy afiliado a ninguna AFP o ONP y deseo afiliarme a:", boxX + 2, y + rowH / 2 + 1);
        y += rowH;

        // Fila 7 — AFP/ONP checkboxes (ancho completo)
        const sysPrevCod = getValue("sistema_previsional").trim();
        const codigosAFP = ['02', '03', '10', '11', '27']; // AFP INTEGRA, AFP, AFP PRIMA, AFP EL ROBLE, AFP HABITAT
        const esAFP = codigosAFP.includes(sysPrevCod);
        const esONP = sysPrevCod === '01';
        drawField("Estoy afiliado a la AFP", esAFP ? "X" : "", boxX,                     boxWidth * 0.5264, y, rowH, 0.3875, "center");
        drawField("Estoy afiliado a la ONP", esONP ? "X" : "", boxX + boxWidth * 0.5264, boxWidth * 0.4736, y, rowH, 0.441,  "center");
      
        y += rowH;

        // Fila 8 — Educación
        const col1 = boxWidth * 0.285, col2 = boxWidth * 0.2414;
        const col3 = (boxWidth * 0.5264 + boxWidth * 0.4736 * 0.441) - col1 - col2;
        const col4 = boxWidth - (boxWidth * 0.5264 + boxWidth * 0.4736 * 0.441);
        const educRowH = rowH * 1.6; // más alto para permitir 2 líneas en institución/carrera
        drawAutoFitField("Grado de Instrucción", getCleanSelectText("grado_instruccion"), boxX,               col1, y, educRowH, 0.526);
        drawAutoFitField("Institución",          getCleanSelectText("institucion"),        boxX + col1,        col2, y, educRowH, 0.398);
        drawAutoFitField("Carrera",              getCleanSelectText("carrera"),            boxX + col1 + col2, col3, y, educRowH, 0.486);
        drawField("Año de egreso", getValue("anio_egreso"), boxX + col1 + col2 + col3, col4, y, educRowH, 0.50);
        y += educRowH;

        // Fila 9 — Embargos / Cuentas
        const embW    = boxWidth * 0.30;
        const cuentaW = boxWidth * 0.70;
        drawField("Embargos en instituciones financieras", getValue("embargos"), boxX, embW, y, rowH, 0.75);
        drawField("Cuenta sueldo", getCleanSelectText("cuenta_banco"), boxX + embW, cuentaW, y, rowH, 0.35);
        y += rowH;

        drawField("Dirección Actual", getValue("direccion_actual"), boxX, boxWidth, y, rowH, 0.15, "left", false, false, false, true); y += rowH;
        drawField("Dirección DNI",    getValue("direccion_dni"),    boxX, boxWidth, y, rowH, 0.15); y += rowH;
        drawField("En caso de Emergencia llamar a", getValue("contacto_emergencia"), boxX, boxWidth, y, rowH, 0.286); y += rowH;
        drawField("Número de celular", getValue("celular_emergencia"),    boxX,                     boxWidth * 0.5264, y, rowH, 0.403);
        drawField("Parentesco",        getValue("parentesco_emergencia"), boxX + boxWidth * 0.5264, boxWidth * 0.4736, y, rowH, 0.25);
        y += rowH;

        // ════════════════════════════════════════════════════════
        // SECCIÓN: DATOS LABORALES
        // ════════════════════════════════════════════════════════
        checkPageBreak(5 * rowH + sectionTitleH + sectionGap + 3);
        drawSectionTitle("MIS DATOS LABORALES", y);
        y += sectionTitleH;

        const wLab3 = boxWidth / 6;
        if (esOper || (!esOper && !esAdmin)) {
            drawField("Carne SUCAMEC", getValue('curso_sucamec') === 'SI' ? 'SÍ' : 'NO', boxX, boxWidth * 0.285, y, rowH, 0.42);
            drawField("S.M.O.",      getValue('presto_smo'),               boxX + boxWidth * 0.285, boxWidth * 0.381, y, rowH, 0.3);
            drawField("Lugar S.M.O.",  getValue('lugar_smo'),                boxX + boxWidth * 0.666, boxWidth * 0.334, y, rowH, 0.35);
            y += rowH;
            drawField("N° Licencia L4", getValue('licencia_arma'), boxX,                     boxWidth * 0.5264, y, rowH, 0.35);
            drawField("Arma Propia",    getValue('arma_propia'),    boxX + boxWidth * 0.5264, boxWidth * 0.4736, y, rowH, 0.35);
            y += rowH;
            drawField("N° Brevete",      getValue('brevete'),        boxX,                     boxWidth * 0.285,  y, rowH, 0.42);
            drawField("Clase",           getValue('clase_brevete'),  boxX + boxWidth * 0.285,  boxWidth * 0.2414, y, rowH, 0.35);
            drawField("Tipo",            getValue('tipo_vehiculo'),  boxX + boxWidth * 0.5264, boxWidth * 0.2296, y, rowH, 0.25);
            drawField("Vehículo Propio", getValue('vehiculo_propio'),boxX + boxWidth * 0.756,  boxWidth * 0.244,  y, rowH, 0.52);
            y += rowH;
        } else {
            // Tiempo de experiencia: "3 AÑOS 6 MESES" / "1 AÑO" / "6 MESES" / "---" si no tiene
            const expAnios = parseInt(getValue('experiencia_anios'), 10) || 0;
            const expMeses = parseInt(getValue('experiencia_meses'), 10) || 0;
            const partesExp = [];
            if (expAnios > 0) partesExp.push(`${expAnios} ${expAnios === 1 ? 'AÑO' : 'AÑOS'}`);
            if (expMeses > 0) partesExp.push(`${expMeses} ${expMeses === 1 ? 'MES' : 'MESES'}`);
            const tiempoExperiencia = partesExp.length ? partesExp.join(' ') : '---';
            drawField("Profesión u Ocupación Principal", getValue('ocupacion_principal'), boxX,                     boxWidth * 0.5264, y, rowH, 0.475);
            drawField("Tiempo Experiencia",              tiempoExperiencia,              boxX + boxWidth * 0.5264, boxWidth * 0.4736, y, rowH, 0.4);
            y += rowH;
            drawField("Familiar en la Empresa", getValue('familiar_empresa'),   boxX,                      boxWidth * 0.25,    y, rowH, 0.816);
            
            if(getValue('familiar_empresa') != 'NO'){
                drawField("Nombre Completo",  getValue('familiar_nombre'),    boxX + boxWidth * 0.25,    boxWidth * 0.46584, y, rowH, 0.3);
                drawField("Parentesco",  getValue('familiar_parentesco'),boxX + boxWidth * 0.71584, boxWidth * 0.28416, y, rowH, 0.4);
            }else{
                drawField("Nombre Completo",  '',   boxX + boxWidth * 0.25,   boxWidth * 0.46584, y, rowH, 0.3);
                drawField("Parentesco", '',boxX + boxWidth * 0.71584, boxWidth * 0.28416, y, rowH, 0.4);
            }

            y += rowH;
            // Anchos exactos — suman boxWidth
            const wSMO   = boxWidth * 0.08;
            const wInst  = boxWidth * 0.20;
            const wBrev  = boxWidth * 0.18;
            const wClas  = boxWidth * 0.12;
            const wTipo  = boxWidth * 0.12;
            const wVeh   = boxWidth * 0.30;
            drawField("SMO",             getValue('presto_smo') === 'SI' ? 'SI' : 'NO',               boxX,                              wSMO,  y, rowH, 0.4);
            let instEjer = '';
            if( getValue('lugar_smo') == 'EP'){
                instEjer = 'EJERCITO DEL PERU'
            }
            if( getValue('lugar_smo') == 'MG'){
                instEjer = 'MARINA DE GUERRA DEL PERU'
            }
            if( getValue('lugar_smo') == 'FA'){
                instEjer = 'FUERZA AEREA DEL PERU'
            }
            drawField("Institución",     instEjer ,boxX + wSMO,                      wInst, y, rowH, 0.35);
            drawField("N° Brevete",      getValue('brevete'),            boxX + wSMO + wInst,              wBrev, y, rowH, 0.45);
            drawField("Clase",           getValue('clase_brevete'),      boxX + wSMO + wInst + wBrev,      wClas, y, rowH, 0.38);
            drawField("Tipo",            getValue('tipo_vehiculo'),      boxX + wSMO + wInst + wBrev + wClas, wTipo, y, rowH, 0.3);
            drawField("Vehículo Propio", getValue('vehiculo_propio'),    boxX + wSMO + wInst + wBrev + wClas + wTipo, wVeh, y, rowH, 0.52);
            y += rowH;
        }
        drawField("Empresa Anterior", getValue('empresa_anterior'), boxX,                  boxWidth * 0.375, y, rowH, 0.40);
        drawField("Cargo",            getValue('cargo_anterior'),   boxX + boxWidth*0.375, boxWidth * 0.340, y, rowH, 0.25);
        drawField("Duración",         getValue('duracion_anterior'),boxX + boxWidth*0.715, boxWidth * 0.285, y, rowH, 0.35);
        y += rowH;
        drawField("Profesión u Ocupación Alterna 1", getValue('dj2026_laboral_1'), boxX,              boxWidth / 2, y, rowH, 0.45);
        drawField("Profesión u Ocupación Alterna 2", getValue('dj2026_laboral_2'), boxX + boxWidth/2, boxWidth / 2, y, rowH, 0.45);
        y += rowH;

        // ════════════════════════════════════════════════════════
        // SECCIÓN: DATOS FAMILIARES
        // ════════════════════════════════════════════════════════
        checkPageBreak(40);
 
        const fmC1 = boxWidth * 0.15, fmC2 = boxWidth * 0.70, fmC3 = boxWidth * 0.15;
        const fmHeaderH = rowH * 1.3;
 
        // Título sin borde inferior propio (lo cierra el header de familiares)
        pdf.setFillColor(...colors.sectionBg);
        pdf.rect(boxX, y, boxWidth, sectionTitleH, "F");
        pdf.setDrawColor(...colors.borderColor); pdf.setLineWidth(0.20);
        pdf.line(boxX,             y,                 boxX + boxWidth, y);                 // top
        pdf.line(boxX,             y,                 boxX,            y + sectionTitleH); // left
        pdf.line(boxX + boxWidth,  y,                 boxX + boxWidth, y + sectionTitleH); // right
        pdf.setFontSize(8.5); pdf.setFont(FONT_FAMILY, "bold"); pdf.setTextColor(...colors.sectionText);
        pdf.text("MIS DATOS FAMILIARES", boxX + boxWidth / 2, y + sectionTitleH / 2 + 1.2, { align: "center" });
        y += sectionTitleH;
 
        // Línea separadora entre título y header de familiares
        pdf.setDrawColor(...colors.borderColor); pdf.setLineWidth(0.20);
        pdf.line(boxX, y, boxX + boxWidth, y);
 
        // Header de columnas familiares
        pdf.setFillColor(...colors.labelBg);
        pdf.rect(boxX,             y, fmC1, fmHeaderH, "F");
        pdf.rect(boxX + fmC1,      y, fmC2, fmHeaderH, "F");
        pdf.rect(boxX + fmC1 + fmC2, y, fmC3, fmHeaderH, "F");
        pdf.setDrawColor(0); pdf.setLineWidth(0.20);
        pdf.line(boxX,             y, boxX,             y + fmHeaderH); // left
        pdf.line(boxX + boxWidth,  y, boxX + boxWidth,  y + fmHeaderH); // right
        pdf.line(boxX,             y + fmHeaderH, boxX + boxWidth, y + fmHeaderH); // bottom
        pdf.line(boxX + fmC1,      y, boxX + fmC1,      y + fmHeaderH);
        pdf.line(boxX + fmC1 + fmC2, y, boxX + fmC1 + fmC2, y + fmHeaderH);
        pdf.setFontSize(8); pdf.setFont(FONT_FAMILY, "normal");
        pdf.text("Parentesco",          boxX + fmC1 / 2,               y + fmHeaderH / 2 + 1, { align: "center" });
        pdf.text("Apellidos y Nombres", boxX + fmC1 + fmC2 / 2,       y + fmHeaderH / 2 + 1, { align: "center" });
        const fnLines = pdf.splitTextToSize("Fecha Nacimiento", fmC3 - 4);
        pdf.text(fnLines, boxX + fmC1 + fmC2 + fmC3 / 2, y + fmHeaderH / 2 - (fnLines.length > 1 ? 1.5 : 0) + 1, { align: "center" });
        y += fmHeaderH;
 
        // Filas de familiares
        const parentescos = document.getElementsByName("parentesco[]");
        const nombresFam  = document.getElementsByName("apellidosNombres[]");
        const fechasFam   = document.getElementsByName("fechaNacimiento[]");
        const rowCount    = Math.max(parentescos.length, 5);
 
        for (let i = 0; i < rowCount; i++) {
            checkPageBreak(rowH);
            const par = parentescos[i]?.value || "";
            const nom = nombresFam[i]?.value  || "";
            const fec = formatDateToDMY(fechasFam[i]?.value || "");
 
            pdf.setDrawColor(0); pdf.setLineWidth(0.20);
            pdf.rect(boxX, y, boxWidth, rowH);
            pdf.line(boxX + fmC1,         y, boxX + fmC1,         y + rowH);
            pdf.line(boxX + fmC1 + fmC2,  y, boxX + fmC1 + fmC2,  y + rowH);
            pdf.setFont(FONT_FAMILY, "normal"); pdf.setFontSize(8); pdf.setTextColor(0);
            // Encoger/truncar para que el texto nunca cruce los separadores de columna
            pdf.text(fitCellText(pdf, par.toUpperCase(), fmC1 - 4, 8, 5), boxX + 2,               y + rowH / 2 + 1);
            pdf.text(fitCellText(pdf, nom.toUpperCase(), fmC2 - 4, 8, 5), boxX + fmC1 + 2,        y + rowH / 2 + 1);
            pdf.text(fitCellText(pdf, fec,               fmC3 - 4, 8, 5), boxX + fmC1 + fmC2 + 2, y + rowH / 2 + 1);
            y += rowH;
        }
 
        // ════════════════════════════════════════════════════════
        // SALTO DE PÁGINA CONDICIONAL
        // Si hay 6 o más familiares (rowCount >= 6), forzar nueva
        // página antes de CONFORMIDAD + FIRMA para que no queden
        // aplastados al final de la hoja.
        // ════════════════════════════════════════════════════════
        // Solo salta de página cuando hay 6 o más familiares — sin condición adicional
        if (rowCount >= 6) {
            pdf.addPage();
            y = marginTop;
        }
 
        // ════════════════════════════════════════════════════════
        // SECCIÓN: CONFORMIDAD
        // ════════════════════════════════════════════════════════
        drawSectionTitle("MI CONFORMIDAD CON LA DECLARACION JURADA", y);
        y += sectionTitleH;
 
        pdf.setFontSize(8.5); pdf.setFont(FONT_FAMILY, "normal");
        
        const confTextBase = "De acuerdo con lo dispuesto por mi empleador por norma interna, cumpliré con mi obligación de actualizar cada 12 meses esta Declaración Jurada y también hacerlo, cuando varíe cualquiera de mis datos registrados, asumiendo la responsabilidad en caso de incumplimiento.";
        const confTextOper = " En mi Sistema de Información Personal SIP verificaré periódicamente la exactitud de la información que contiene mi Declaración Jurada.";
        const confLines = pdf.splitTextToSize(
            esOper ? confTextBase + confTextOper : confTextBase,
            boxWidth - 4
        );
        const confBoxH = confLines.length * lineH + paraTopPad + paraBottomPad;
        pdf.setDrawColor(0); pdf.setLineWidth(0.20);
        
        pdf.rect(boxX, y, boxWidth, confBoxH);
        pdf.setTextColor(0);
        drawJustifiedText(pdf, confLines, boxX + 2, y + paraTopPad + 2.8, boxWidth - 4, lineH);
        y += confBoxH;
 
        // ════════════════════════════════════════════════════════
        // TABLA: Firma / Huella + fila fecha/nombre
        // ════════════════════════════════════════════════════════
        const firmaW  = boxWidth * 0.6;
        const huellaW = boxWidth * 0.4;
 
        // Altura fija para la celda de firma/huella — no crece con el espacio restante
        const firmaH = firmaMinH; // 45mm definido arriba en las constantes
        const tablaH = firmaH + footerRowH;
 
        pdf.setDrawColor(0); pdf.setLineWidth(0.3);
        pdf.rect(boxX, y, boxWidth, tablaH);
        pdf.setLineWidth(0.20);
        pdf.line(boxX + firmaW, y, boxX + firmaW, y + firmaH);
        pdf.line(boxX, y + firmaH, boxX + boxWidth, y + firmaH);

        // ── Obtener firma y huella desde Biométrico ──
        const codiPersDJ = (document.getElementById('cod_postulante')?.value || '').trim();
        const bioData = await fetchBiometrico(codiPersDJ);

        function drawImageInBox(b64, x, boxY, w, h) {
            if (!b64 || b64.error) return false;
            try {
                const padding = 2;
                const maxW = w - padding * 2;
                const maxH = h - 12;
                const props = pdf.getImageProperties(b64);
                const ratio = Math.min(maxW / props.width, maxH / props.height);
                const finalW = props.width * ratio;
                const finalH = props.height * ratio;
                const offsetX = x + padding + (maxW - finalW) / 2;
                const offsetY = boxY + padding + (maxH - finalH) / 2;
                let format = "JPEG";
                if (b64.startsWith("data:image/png")) format = "PNG";
                if (b64.startsWith("data:image/webp")) format = "WEBP";
                pdf.addImage(b64, format, offsetX, offsetY, finalW, finalH);
                return true;
            } catch (err) {
                console.warn('Error dibujando imagen biométrica:', err);
                return false;
            }
        }

        drawImageInBox(bioData.firma_nueva || bioData.firma_antigua, boxX, y, firmaW, firmaH);
        drawImageInBox(bioData.huella_nueva || bioData.huella_antigua, boxX + firmaW, y, huellaW, firmaH);

        const firmaLabelY = y + firmaH - 6;
        pdf.setFont(FONT_FAMILY, "bold"); pdf.setFontSize(7.5); pdf.setTextColor(0);
        pdf.text("Firma Registrada",              boxX + firmaW / 2,           firmaLabelY,     { align: "center" });
        pdf.text("GRANDE Y CLARA SIMILAR AL DNI", boxX + firmaW / 2,           firmaLabelY + 3, { align: "center" });
        pdf.text("Huella Registrada",              boxX + firmaW + huellaW / 2, firmaLabelY,    { align: "center" });
        pdf.text("INDICE DERECHO",                 boxX + firmaW + huellaW / 2, firmaLabelY + 3, { align: "center" });
 
        const footerY          = y + firmaH;
        const fechaW           = boxWidth * 0.25;
        const fechaValW        = boxWidth * 0.15;
        const nombreLabelStart = boxX + fechaW + fechaValW;
        const nombreLabelEnd   = boxX + firmaW;
 
        pdf.setFillColor(...colors.labelBg); pdf.rect(boxX,              footerY, fechaW,                             footerRowH, "F");
        pdf.setFillColor(255);               pdf.rect(boxX + fechaW,     footerY, fechaValW,                          footerRowH, "F");
        pdf.setFillColor(...colors.labelBg); pdf.rect(nombreLabelStart,  footerY, nombreLabelEnd - nombreLabelStart,  footerRowH, "F");
        pdf.setFillColor(255);               pdf.rect(nombreLabelEnd,    footerY, (boxX + boxWidth) - nombreLabelEnd, footerRowH, "F");
 
        pdf.setDrawColor(0); pdf.setLineWidth(0.20);
        pdf.line(boxX + fechaW,    footerY, boxX + fechaW,    footerY + footerRowH);
        pdf.line(nombreLabelStart, footerY, nombreLabelStart, footerY + footerRowH);
        pdf.line(nombreLabelEnd,   footerY, nombreLabelEnd,   footerY + footerRowH);
 
        pdf.setLineWidth(0.3);
        pdf.rect(boxX, footerY, boxWidth, footerRowH);
 
        pdf.setTextColor(0); pdf.setFont(FONT_FAMILY, "normal"); pdf.setFontSize(7.5);
        pdf.text("Fecha de la declaración", boxX + 2,            footerY + footerRowH / 2 + 1);
        pdf.text(fitCellText(pdf, formatDateToDMY(new Date()), fechaValW - 4, 7.5, 5), boxX + fechaW + 2,  footerY + footerRowH / 2 + 1);
        pdf.text("Nombre",                   nombreLabelStart + 2, footerY + footerRowH / 2 + 1);
        pdf.text(fitCellText(pdf, nombres, (boxX + boxWidth) - nombreLabelEnd - 4, 7.5, 5), nombreLabelEnd + 2, footerY + footerRowH / 2 + 1);

        const f = new Date();
        const fechaHora = f.getFullYear() + String(f.getMonth()+1).padStart(2,'0') + String(f.getDate()).padStart(2,'0')
            + '_' + String(f.getHours()).padStart(2,'0') + String(f.getMinutes()).padStart(2,'0');
        const nombreArchivo = `DJ_${dni}_${nombres.replace(/ /g, "-")}_${fechaHora}.pdf`;
        if (returnBlob) return { blob: pdf.output('blob'), filename: nombreArchivo };
        const blob = pdf.output('blob');
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nombreArchivo;
        link.style.cssText = 'position:fixed;left:-9999px;pointer-events:none;';
        document.body.appendChild(link);
        link.click();
        setTimeout(() => { document.body.removeChild(link); URL.revokeObjectURL(url); }, 200);
        return nombreArchivo;

    } catch (error) {
        console.error("Error al generar PDF:", error);
        if (returnBlob) return null;
        Swal.fire({
            icon: 'error',
            title: 'Error de PDF',
            text: 'Hubo un error al generar el documento: ' + error.message,
            didOpen: (popup) => { popup.addEventListener('click', e => e.stopPropagation()); }
        });
        return null;
    }
}

export async function generarReporteFaltantesPDF(data, todosLosDatos = null) {
    const datosParaCards = todosLosDatos ?? data;
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'landscape', compress: true });

    const pageW = 297, pageH = 210, mL = 10, mR = 10, mT = 10, mB = 10;
    const usableW = pageW - mL - mR;
    let y = mT;

    try {
        const res  = await fetch(window.logoUrl);
        const blob = await res.blob();
        const b64  = await new Promise(r => { const rd = new FileReader(); rd.onload = e => r(e.target.result); rd.readAsDataURL(blob); });
        const logoH = 14, logoMaxW = 30;
        const props = pdf.getImageProperties(b64);
        const logoW = Math.min(logoMaxW, logoH * (props.width / props.height));
        pdf.addImage(b64, 'PNG', mL, y, logoW, logoH);
    } catch (_) {}

    pdf.setFontSize(10); pdf.setFont(FONT_FAMILY, 'bold'); pdf.setTextColor(180, 0, 0);
    pdf.text('SISTEMA INTEGRADO SOLMAR – SISOLMAR WEB', pageW / 2, y + 6, { align: 'center' });
    pdf.setFontSize(13); pdf.setTextColor(0);
    pdf.text(`REPORTE DE ACTUALIZACIÓN DE DATOS DE PERSONAL ${data[0].TIPO_PER}`, pageW / 2, y + 12, { align: 'center' });
    pdf.setFontSize(11);
    pdf.text(`SOL ${data[0].SUCURSAL}`, pageW / 2, y + 18, { align: 'center' });
    y += 5;

    const now = new Date();
    const fechaStr = `${String(now.getDate()).padStart(2,'0')}/${String(now.getMonth()+1).padStart(2,'0')}/${now.getFullYear()}  ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    pdf.setFontSize(7); pdf.setFont(FONT_FAMILY, 'normal'); pdf.setTextColor(100);
    pdf.text(`Generado: ${fechaStr}`, pageW - mR, y + 5, { align: 'right' });
    pdf.text(`Total registros: ${data.length}`, pageW - mR, y + 9, { align: 'right' });
    y += 18;

    const totalFalta   = datosParaCards.filter(d => d.SIP_CAMBIO === 'Falta').length;
    const totalMigrado = datosParaCards.filter(d => d.MIGRADO_CAMBIO === 'Verificado').length;
    const cards = [
        { label: 'Total',          val: document.getElementById('contadorTotal')?.textContent,   r:50,  g:50,  b:50  },
        { label: 'Sin actualizar', val: totalFalta,                                                r:180, g:0,   b:0   },
        { label: 'Actualizados',   val: document.getElementById('contadorFiltrado')?.textContent, r:0,   g:100, b:60  },
        { label: 'Verificados',    val: totalMigrado,                                              r:0,   g:80,  b:160 },
    ];
    const cardW = 36, cardH = 13, cardGap = 5; let cx = mL;
    cards.forEach(c => {
        pdf.setFillColor(c.r, c.g, c.b); pdf.roundedRect(cx, y, cardW, cardH, 2, 2, 'F');
        pdf.setFontSize(16); pdf.setFont(FONT_FAMILY, 'bold'); pdf.setTextColor(255);
        pdf.text(String(c.val), cx + cardW / 2, y + 8, { align: 'center' });
        pdf.setFontSize(7); pdf.setFont(FONT_FAMILY, 'normal');
        pdf.text(c.label, cx + cardW / 2, y + 11.5, { align: 'center' });
        cx += cardW + cardGap;
    });
    y += cardH + 5;

    const cols = [
        { header: 'N°',                  key: '__rownum__',     w: 12 },
        { header: 'Estado',              key: 'SIP_CAMBIO',     w: 18 },
        { header: 'Verificado',          key: 'MIGRADO_CAMBIO', w: 22 },
        { header: 'Nombre Completo',     key: 'NOMBRE',         w: 66 },
        { header: 'DNI',                 key: 'NRO_DOCU_IDEN',  w: 22 },
        { header: 'Fecha de ingreso',    key: 'FECH_INGRE',     w: 22 },
        { header: 'Fecha Actualización', key: 'SIP_CREACION',   w: 32 },
        { header: 'Fecha Verificado.',   key: 'MIGRADO_FECHA',  w: 32 },
    ];
    const rowH = 5.5, headerH = 7;

    function drawTableHeader() {
        pdf.setFillColor(40, 40, 40); pdf.rect(mL, y, usableW, headerH, 'F');
        pdf.setFontSize(7); pdf.setFont(FONT_FAMILY, 'bold'); pdf.setTextColor(255);
        let hx = mL + 1; cols.forEach(c => { pdf.text(fitCellText(pdf, c.header, c.w - 2, 7, 5), hx, y + 4.5); hx += c.w; });
        y += headerH;
    }
    drawTableHeader();

    data.forEach((row, idx) => {
        if (y + rowH > pageH - mB - 5) { pdf.addPage(); y = mT; drawTableHeader(); }
        if (idx % 2 === 0) { pdf.setFillColor(248, 248, 248); pdf.rect(mL, y, usableW, rowH, 'F'); }
        const esFalta = row.SIP_CAMBIO === 'Falta', esMigrado = row.MIGRADO_CAMBIO === 'Migrado';
        pdf.setFontSize(6.5); pdf.setFont(FONT_FAMILY, 'normal');
        let rx = mL + 1;
        cols.forEach(c => {
            let val = c.key === '__rownum__' ? String(idx + 1) : String(row[c.key] ?? '');
            const maxW = c.w - 2;
            if      (c.key === 'SIP_CAMBIO')    pdf.setTextColor(esFalta ? 180 : 0, esFalta ? 0 : 120, 0);
            else if (c.key === 'MIGRADO_CAMBIO') pdf.setTextColor(esMigrado ? 0 : 150, esMigrado ? 100 : 70, esMigrado ? 160 : 0);
            else                                  pdf.setTextColor(30, 30, 30);
            pdf.setFontSize(6.5);
            while (pdf.getTextWidth(val) > maxW && val.length > 1) val = val.slice(0, -1);
            pdf.text(val, rx, y + rowH - 1.5); rx += c.w;
        });
        pdf.setDrawColor(220); pdf.setLineWidth(0.1); pdf.line(mL, y + rowH, mL + usableW, y + rowH);
        y += rowH;
    });

    const totalPages = pdf.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        pdf.setPage(i);
        pdf.setFontSize(7); pdf.setFont(FONT_FAMILY, 'normal'); pdf.setTextColor(130);
        pdf.text(`Página ${i} de ${totalPages}`, pageW / 2, pageH - 4, { align: 'center' });
        pdf.text('SISOLMAR – Recursos Humanos', mL, pageH - 4);
        pdf.text(fechaStr, pageW - mR, pageH - 4, { align: 'right' });
    }

    const ts = `${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}_${String(now.getHours()).padStart(2,'0')}${String(now.getMinutes()).padStart(2,'0')}`;
    const nombreArchivo = `Reporte_Faltantes_DJ_${ts}.pdf`;
    const blob = pdf.output('blob');
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = nombreArchivo;
    link.style.display = 'none';
    link.addEventListener('click', e => e.stopPropagation(), true);
    document.body.appendChild(link);
    link.click();
    setTimeout(() => { document.body.removeChild(link); URL.revokeObjectURL(url); }, 200);
}
