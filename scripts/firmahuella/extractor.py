"""Localiza marcos rectangulares en un PDF y exporta su contenido como JPG."""

from __future__ import annotations

import argparse
from pathlib import Path

import pymupdf as fitz
import cv2
import numpy as np
from PIL import Image

MM_PER_INCH = 25.4
PT_PER_INCH = 72
TARGETS_MM = ((114.25, 35.25), (76.25, 35.25))
OUTPUT_SIDE_INSET_MM = 2.0
OUTPUT_TOP_INSET_MM = 2.0
OUTPUT_BOTTOM_CUT_MM = 10.0


def mm_to_pt(value: float) -> float:
    return value * PT_PER_INCH / MM_PER_INCH


def find_frames(page: fitz.Page, tolerance_pt: float = 1.0) -> list[tuple[float, float, fitz.Rect]]:
    """Return rectangle paths matching either fixed frame size (in either orientation)."""
    matches: list[tuple[float, float, fitz.Rect]] = []
    seen: set[tuple[int, int, int, int]] = set()
    for drawing in page.get_drawings():
        for item in drawing["items"]:
            if item[0] != "re":
                continue
            rect = fitz.Rect(item[1])
            for width_mm, height_mm in TARGETS_MM:
                expected = ((mm_to_pt(width_mm), mm_to_pt(height_mm)),
                            (mm_to_pt(height_mm), mm_to_pt(width_mm)))
                if any(abs(rect.width - w) <= tolerance_pt and abs(rect.height - h) <= tolerance_pt
                       for w, h in expected):
                    key = tuple(round(v * 10) for v in (rect.x0, rect.y0, rect.x1, rect.y1))
                    if key not in seen:
                        seen.add(key)
                        matches.append((width_mm, height_mm, rect))
                    break
    # Some PDFs draw frames as independent horizontal and vertical line segments,
    # instead of a single rectangle path. Collect those edges and assemble boxes.
    horizontal: list[tuple[float, float, float]] = []  # y, x0, x1
    vertical: list[tuple[float, float, float]] = []  # x, y0, y1
    for drawing in page.get_drawings():
        for item in drawing["items"]:
            if item[0] == "l":
                p1, p2 = item[1], item[2]
                if abs(p1.y - p2.y) <= tolerance_pt:
                    horizontal.append(((p1.y + p2.y) / 2, min(p1.x, p2.x), max(p1.x, p2.x)))
                elif abs(p1.x - p2.x) <= tolerance_pt:
                    vertical.append(((p1.x + p2.x) / 2, min(p1.y, p2.y), max(p1.y, p2.y)))
            elif item[0] == "re":
                r = fitz.Rect(item[1])
                horizontal.extend(((r.y0, r.x0, r.x1), (r.y1, r.x0, r.x1)))
                vertical.extend(((r.x0, r.y0, r.y1), (r.x1, r.y0, r.y1)))

    def edge_exists(edges: list[tuple[float, float, float]], fixed: float,
                    start: float, end: float) -> bool:
        return any(abs(pos - fixed) <= tolerance_pt and lo <= start + tolerance_pt
                   and hi >= end - tolerance_pt for pos, lo, hi in edges)

    # Only long enough rules can belong to either target frame; this also keeps
    # table-cell geometry from creating an expensive all-pairs search.
    min_w = mm_to_pt(min(w for w, _ in TARGETS_MM)) - tolerance_pt
    min_h = mm_to_pt(min(h for _, h in TARGETS_MM)) - tolerance_pt
    horizontal = [edge for edge in horizontal if edge[2] - edge[1] >= min_w]
    vertical = [edge for edge in vertical if edge[2] - edge[1] >= min_h]
    xs = sorted({round(pos, 2) for pos, _, _ in vertical})
    ys = sorted({round(pos, 2) for pos, _, _ in horizontal})
    for width_mm, height_mm in TARGETS_MM:
        for expected_w, expected_h in ((mm_to_pt(width_mm), mm_to_pt(height_mm)),
                                       (mm_to_pt(height_mm), mm_to_pt(width_mm))):
            x_pairs = [(x0, x1) for x0 in xs for x1 in xs if x1 > x0
                       and abs((x1 - x0) - expected_w) <= tolerance_pt]
            y_pairs = [(y0, y1) for y0 in ys for y1 in ys if y1 > y0
                       and abs((y1 - y0) - expected_h) <= tolerance_pt]
            for x0, x1 in x_pairs:
                for y0, y1 in y_pairs:
                    if (edge_exists(horizontal, y0, x0, x1) and
                            edge_exists(horizontal, y1, x0, x1) and
                            edge_exists(vertical, x0, y0, y1) and
                            edge_exists(vertical, x1, y0, y1)):
                        rect = fitz.Rect(x0, y0, x1, y1)
                        key = tuple(round(v * 10) for v in (rect.x0, rect.y0, rect.x1, rect.y1))
                        if key not in seen:
                            seen.add(key)
                            matches.append((width_mm, height_mm, rect))
    return matches


def find_raster_frames(page: fitz.Page, dpi: int, tolerance_mm: float = 10.0,
                       height_tolerance_mm: float = 10.0) -> list[tuple[float, float, fitz.Rect]]:
    """Detect outlined frames on scanned pages and return their page-coordinate rectangles."""
    scale = dpi / PT_PER_INCH
    pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False)
    gray = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)[:, :, :3]
    gray = cv2.cvtColor(gray, cv2.COLOR_RGB2GRAY)
    # A gentle blur and adaptive threshold tolerate paper texture and scan noise.
    gray = cv2.GaussianBlur(gray, (3, 3), 0)
    binary = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_MEAN_C,
                                   cv2.THRESH_BINARY_INV, 31, 12)
    contours, _ = cv2.findContours(binary, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    result: list[tuple[float, float, fitz.Rect]] = []
    seen: set[tuple[int, int, int, int]] = set()
    for contour in contours:
        x, y, w, h = cv2.boundingRect(contour)
        width_pt, height_pt = w / scale, h / scale
        for width_mm, height_mm in TARGETS_MM:
            expected = ((mm_to_pt(width_mm), mm_to_pt(height_mm)),
                        (mm_to_pt(height_mm), mm_to_pt(width_mm)))
            if any(abs(width_pt - ew) <= mm_to_pt(tolerance_mm) and
                   abs(height_pt - eh) <= mm_to_pt(height_tolerance_mm) for ew, eh in expected):
                rect = fitz.Rect(x / scale, y / scale, (x + w) / scale, (y + h) / scale)
                # Normalize to unrotated page coordinates, matching vector detections.
                if page.rotation:
                    rect = rect * page.derotation_matrix
                key = tuple(round(v * 4) for v in (rect.x0, rect.y0, rect.x1, rect.y1))
                if key not in seen:
                    seen.add(key)
                    result.append((width_mm, height_mm, rect))
                break
    return result


def export_frame(page: fitz.Page, rect: fitz.Rect, output: Path, dpi: int) -> None:
    side_inset = mm_to_pt(OUTPUT_SIDE_INSET_MM)
    top_inset = mm_to_pt(OUTPUT_TOP_INSET_MM)
    bottom_cut = mm_to_pt(OUTPUT_BOTTOM_CUT_MM)
    crop = fitz.Rect(rect.x0 + side_inset, rect.y0 + top_inset,
                     rect.x1 - side_inset, rect.y1 - bottom_cut)
    pix = page.get_pixmap(matrix=fitz.Matrix(dpi / PT_PER_INCH, dpi / PT_PER_INCH),
                          clip=crop, alpha=False)
    image = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)

    # Preserve the crop's physical dimensions at the requested DPI.
    target_px = (max(1, round(crop.width / PT_PER_INCH * dpi)),
                 max(1, round(crop.height / PT_PER_INCH * dpi)))
    if image.size != target_px:
        image = image.resize(target_px, Image.Resampling.LANCZOS)
    image.save(output, format="JPEG", quality=95, dpi=(dpi, dpi), optimize=True)


def export_raster_frame(page: fitz.Page, rect: fitz.Rect, output: Path, dpi: int) -> None:
    """Crop from the rendered page so rotated scans use the detected pixel coordinates."""
    scale = dpi / PT_PER_INCH
    pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False)
    image = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
    side_inset_px = mm_to_pt(OUTPUT_SIDE_INSET_MM) * scale
    top_inset_px = mm_to_pt(OUTPUT_TOP_INSET_MM) * scale
    bottom_cut_px = mm_to_pt(OUTPUT_BOTTOM_CUT_MM) * scale
    rendered_rect = rect * page.rotation_matrix if page.rotation else rect
    box = (round(rendered_rect.x0 * scale + side_inset_px),
           round(rendered_rect.y0 * scale + top_inset_px),
           round(rendered_rect.x1 * scale - side_inset_px),
           round(rendered_rect.y1 * scale - bottom_cut_px))
    image = image.crop(box)
    target_px = (max(1, round((rendered_rect.width - 2 * mm_to_pt(OUTPUT_SIDE_INSET_MM)) / PT_PER_INCH * dpi)),
                 max(1, round((rendered_rect.height - mm_to_pt(OUTPUT_TOP_INSET_MM) - mm_to_pt(OUTPUT_BOTTOM_CUT_MM)) / PT_PER_INCH * dpi)))
    if image.size != target_px:
        image = image.resize(target_px, Image.Resampling.LANCZOS)
    image.save(output, format="JPEG", quality=95, dpi=(dpi, dpi), optimize=True)


def image_overflows_frame(page: fitz.Page, rect: fitz.Rect) -> bool:
    """Flag placed images that cross a frame edge and therefore will be clipped."""
    area = rect.width * rect.height
    for image_info in page.get_image_info(xrefs=True):
        image_rect = fitz.Rect(image_info["bbox"])
        # Ignore page scans and other large background images.
        if image_rect.width * image_rect.height > area * 3:
            continue
        intersection = image_rect & rect
        if intersection.is_empty or intersection.width * intersection.height < area * 0.03:
            continue
        if (image_rect.x0 < rect.x0 - 0.5 or image_rect.y0 < rect.y0 - 0.5 or
                image_rect.x1 > rect.x1 + 0.5 or image_rect.y1 > rect.y1 + 0.5):
            return True
    return False


def extract_pdf(pdf_path: Path, output_dir: Path, dpi: int = 300) -> list[Path]:
    if dpi <= 0:
        raise ValueError("La resolución (dpi) debe ser mayor que cero.")
    output_dir.mkdir(parents=True, exist_ok=True)
    outputs: list[Path] = []
    with fitz.open(pdf_path) as document:
        for page_index, page in enumerate(document, start=1):
            vector_frames = find_frames(page)
            raster_frames = find_raster_frames(page, dpi)
            frames = [(w, h, rect, False) for w, h, rect in vector_frames]
            for width_mm, height_mm, rect in raster_frames:
                duplicate = any(rect.intersects(existing_rect) and
                                (rect & existing_rect).get_area() /
                                min(rect.get_area(), existing_rect.get_area()) > 0.8
                                for _, _, existing_rect, _ in frames)
                if not duplicate:
                    frames.append((width_mm, height_mm, rect, True))
            for frame_index, (width_mm, height_mm, rect, is_raster) in enumerate(frames, start=1):
                tipo = "firma" if width_mm > 100 else "huella"
                filename = f"{tipo}.jpg"
                output = output_dir / filename
                if is_raster:
                    export_raster_frame(page, rect, output, dpi)
                else:
                    export_frame(page, rect, output, dpi)
                if image_overflows_frame(page, rect):
                    print(f"ADVERTENCIA: una imagen del PDF sobresale del recuadro y el recorte puede truncarla: {output}")
                outputs.append(output)
    return outputs


def main() -> None:
    parser = argparse.ArgumentParser(
        prog="firmahuella",
        description="Extrae de un PDF los contenidos dentro de recuadros de tamaños conocidos.",
    )
    parser.add_argument("pdf", type=Path, help="Archivo PDF de entrada")
    parser.add_argument("-o", "--salida", type=Path, help="Carpeta de salida (por defecto: junto al PDF)")
    parser.add_argument("--dpi", type=int, default=300, help="Resolución de las imágenes (predeterminado: 300)")
    args = parser.parse_args()

    if not args.pdf.is_file():
        parser.error(f"No existe el archivo: {args.pdf}")
    output_dir = args.salida or args.pdf.parent / f"{args.pdf.stem}_recortes"
    try:
        outputs = extract_pdf(args.pdf, output_dir, args.dpi)
    except (fitz.FileDataError, PermissionError, OSError, ValueError) as exc:
        parser.error(str(exc))
    if not outputs:
        parser.error("No se encontraron recuadros con los tamaños esperados. Verifica que estén delimitados por líneas visibles.")
    for output in outputs:
        print(output)
    print(f"Se guardaron {len(outputs)} imagen(es) en {output_dir}")


if __name__ == "__main__":
    main()
