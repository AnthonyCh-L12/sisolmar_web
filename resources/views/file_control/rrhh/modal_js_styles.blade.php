<style>
    /* Las alertas SweetAlert2 deben quedar por encima del modal de DJ (z-index 9999). */
    .swal2-container {
        z-index: 10050 !important;
    }

      /* ============================================================
   ESTILOS GENERALES DEL MODAL DJ
   ============================================================ */
    #modalDjGestion .dj-input,
    #modalDjGestion .dj-select,
    #modalDjGestion .dj-textarea {
        width: 100%;
        font-size: 13px;
        padding: 5px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
        color: #111827;
        transition: border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }

    #modalDjGestion .dj-input:focus,
    #modalDjGestion .dj-select:focus,
    #modalDjGestion .dj-textarea:focus {
        outline: none;
        border-color: var(--color-primary, #6366f1);
    }

    #modalDjGestion .dj-input::placeholder,
    #modalDjGestion .dj-textarea::placeholder {
        color: #9ca3af;
    }

    #modalDjGestion .dj-textarea {
        resize: vertical;
        min-height: 56px;
    }

    #modalDjGestion .dj-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .03em;
        margin-bottom: 3px;
    }

    #modalDjGestion .dj-section {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 12px;
    }

    #modalDjGestion .dj-section-header {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12px;
        font-weight: 700;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    #modalDjGestion .dj-section-header svg {
        width: 14px;
        height: 14px;
        stroke: var(--color-primary, #6366f1);
        flex-shrink: 0;
    }

    #modalDjGestion .dj-section-body {
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    #modalDjGestion .dj-divider {
        border: none;
        border-top: 1px solid #f3f4f6;
        margin: 4px 0;
    }

    #modalDjGestion .dj-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    #modalDjGestion .dj-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
    }

    #modalDjGestion .dj-grid-4 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 10px;
    }

    @media(max-width:768px) {

        #modalDjGestion .dj-grid-2,
        #modalDjGestion .dj-grid-3,
        #modalDjGestion .dj-grid-4 {
            grid-template-columns: 1fr;
        }
    }

    #modalDjGestion .dj-group {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 16px;
        border: 1px solid #e5e7eb;
    }

    #modalDjGestion .dj-group-header {
        padding: 8px 16px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #fff;
        background: var(--color-primary, #6366f1);
    }

    #modalDjGestion .dj-group-body {
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #fff;
    }

    #modalDjGestion .dj-foto-wrap {
        width: 110px;
        height: 130px;
        border: 2px dashed #d1d5db;
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
        background: #f9fafb;
    }

    #modalDjGestion .dj-foto-wrap img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    #modalDjGestion .dj-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    #modalDjGestion .dj-table thead tr {
        border-bottom: 1px solid #e5e7eb;
    }

    #modalDjGestion .dj-table thead th {
        padding: 5px 6px;
        font-size: 10px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        text-align: left;
    }

    #modalDjGestion .dj-table tbody tr {
        border-bottom: 1px solid #f3f4f6;
    }

    #modalDjGestion .dj-table tbody td {
        padding: 5px 6px;
        vertical-align: middle;
    }

    #modalDjGestion .dj-subpanel {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px 12px;
        margin-top: 6px;
    }

    #modalDjGestion .dj-btn-sm {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background .15s, color .15s;
    }

    #modalDjGestion .dj-btn-primary {
        background: rgba(99, 102, 241, .15);
        color: var(--color-primary, #6366f1);
    }

    #modalDjGestion .dj-btn-primary:hover {
        background: var(--color-primary, #6366f1);
        color: #fff;
    }

    #modalDjGestion .dj-btn-danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    #modalDjGestion .dj-btn-danger:hover {
        background: #fca5a5;
    }

    /* ============================================================
   SPLIT VIEW
   ============================================================ */
    .dj-split-wrapper {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        min-height: 0;
    }

    /* Panel izquierdo - BACKUP (solo lectura) */
    .dj-panel-backup {
        background: #fffbeb;
        border-right: 2px solid #fde68a;
        padding: 14px 16px;
        overflow-y: auto;
    }

    .dj-panel-backup-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #fef3c7;
        border: 1px solid #fde68a;
        border-radius: 8px;
        margin-bottom: 14px;
    }

    .dj-panel-backup-header span {
        font-size: 11px;
        font-weight: 700;
        color: #92400e;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .dj-panel-backup .bk-group {
        margin-bottom: 14px;
    }

    .dj-panel-backup .bk-group-title {
        font-size: 10px;
        font-weight: 700;
        color: #92400e;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: 5px 8px;
        background: #fde68a;
        border-radius: 5px;
        margin-bottom: 8px;
    }

    .dj-panel-backup .bk-field {
        margin-bottom: 8px;
        padding: 4px 6px;
        border-radius: 5px;
        transition: background .2s, box-shadow .2s;
        cursor: default;
    }

    .dj-panel-backup .bk-field label {
        display: block;
        font-size: 9px;
        font-weight: 700;
        color: #78350f;
        text-transform: uppercase;
        letter-spacing: .03em;
        margin-bottom: 2px;
    }

    .dj-panel-backup .bk-field .bk-val {
        font-size: 12px;
        color: #1f2937;
        display: block;
        min-height: 18px;
        word-break: break-word;
    }

    /* Estado: campo diferente en backup */
    .dj-panel-backup .bk-field.is-diff {
        background: #fef08a;
        border-left: 3px solid #f59e0b;
    }

    /* Estado: campo activo (cuando el usuario hace focus en el form) */
    .dj-panel-backup .bk-field.is-active {
        background: #fef08a !important; 
        border-left: 5px solid #ea580c !important; 
        box-shadow: 0 4px 10px rgba(234, 88, 12, 0.4), inset 0 0 0 1px #ea580c !important;
        transform: scale(1.03) translateX(4px) !important;
        transition: all 0.2s ease !important;
        border-radius: 6px !important;
        position: relative !important;
        z-index: 10 !important;
    }

    .dj-panel-backup .bk-field.is-active label {
        color: #9a3412 !important;
        font-weight: 900 !important;
    }

    /* Panel derecho - FORMULARIO NUEVO */
    .dj-panel-form {
        padding: 14px 16px;
        overflow-y: auto;
        background: #fff;
    }

    .dj-panel-form-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #f0fdf4;
        border: 1px solid #86efac;
        border-radius: 8px;
        margin-bottom: 14px;
    }

    .dj-panel-form-header span {
        font-size: 11px;
        font-weight: 700;
        color: #166534;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    /* Inputs con diferencia detectada */
    #modalDjGestion .dj-input.has-diff,
    #modalDjGestion .dj-select.has-diff,
    #modalDjGestion .dj-textarea.has-diff {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, .15);
        background: #fff5f5;
    }

    /* Badge CAMBIÓ */
    .badge-diff {
        display: inline-block;
        font-size: 8px;
        font-weight: 700;
        background: #ef4444;
        color: #fff;
        padding: 1px 5px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-left: 4px;
        vertical-align: middle;
    }

    /* Tabla familiares backup */
    .bk-fam-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        margin-top: 6px;
    }

    .bk-fam-table thead tr {
        background: #fde68a;
    }

    .bk-fam-table thead th {
        padding: 4px 6px;
        font-size: 9px;
        font-weight: 700;
        color: #78350f;
        text-transform: uppercase;
        text-align: left;
    }

    .bk-fam-table tbody tr:nth-child(even) {
        background: #fef9c3;
    }

    .bk-fam-table tbody tr:nth-child(odd) {
        background: #fffbeb;
    }

    .bk-fam-table tbody td {
        padding: 4px 6px;
        color: #1f2937;
        border-bottom: 1px solid #fde68a;
    }

    /* Scrollbar del split */
    .dj-panel-backup::-webkit-scrollbar,
    .dj-panel-form::-webkit-scrollbar {
        width: 5px;
    }

    .dj-panel-backup::-webkit-scrollbar-track {
        background: #fef9c3;
    }

    .dj-panel-backup::-webkit-scrollbar-thumb {
        background: #fbbf24;
        border-radius: 3px;
    }

    .dj-panel-form::-webkit-scrollbar-track {
        background: #f9fafb;
    }

    .dj-panel-form::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }

    /* Ocultar split cuando no hay backup */
    .dj-split-wrapper.no-backup .dj-panel-backup {
        display: none;
    }

    .dj-split-wrapper.no-backup {
        grid-template-columns: 1fr;
    }


    /* ============================================================
   MODAL TAMAÑO RESPONSIVO
   ============================================================ */
    #modalDjGestion>div {
        width: 94% !important;
        max-width: 1600px !important;
        margin: 16px auto !important;
    }

    @media (max-width: 1024px) {
        #modalDjGestion>div {
            width: 98% !important;
            margin: 8px auto !important;
        }
    }

    @media (max-width: 768px) {
        #modalDjGestion>div {
            width: 100% !important;
            margin: 0 !important;
            border-radius: 0 !important;
            height: 100vh !important;
        }

        .dj-split-wrapper {
            grid-template-columns: 1fr !important;
            flex-direction: column !important;
        }

        .dj-panel-backup {
            border-right: none !important;
            border-bottom: 2px solid #fde68a !important;
            max-height: 40vh !important;
        }
    }

    /* ============================================================
   SPLIT WRAPPER — altura fija con scroll independiente
   ============================================================ */
    .dj-split-wrapper {
        display: flex !important;
        flex-direction: row;
        overflow: hidden;
        height: calc(85vh - 100px);
        position: relative;
    }

    .dj-split-wrapper.no-backup {
        display: block !important;
        height: calc(85vh - 100px);
        overflow: hidden;
    }

    .dj-split-wrapper.no-backup .dj-panel-form {
        height: 100%;
        overflow-y: auto;
    }

    /* ============================================================
   PANELES CON SCROLL PROPIO
   ============================================================ */
    .dj-panel-backup {
        flex: 0 0 auto;
        width: 38%;
        min-width: 240px;
        max-width: 55%;
        overflow-y: auto;
        overflow-x: hidden;
        height: 100%;
        border-right: none !important;
        /* el divisor lo reemplaza */
        scroll-behavior: smooth;
    }

    .dj-panel-form {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        height: 100%;
        min-width: 0;
    }

    /* Scrollbars visibles y con estilo */
    .dj-panel-backup::-webkit-scrollbar,
    .dj-panel-form::-webkit-scrollbar {
        width: 7px;
    }

    .dj-panel-backup::-webkit-scrollbar-track {
        background: #fef9c3;
        border-radius: 4px;
    }

    .dj-panel-backup::-webkit-scrollbar-thumb {
        background: #fbbf24;
        border-radius: 4px;
    }

    .dj-panel-backup::-webkit-scrollbar-thumb:hover {
        background: #f59e0b;
    }

    .dj-panel-form::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .dj-panel-form::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 4px;
    }

    .dj-panel-form::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }

    /* Firefox */
    .dj-panel-backup {
        scrollbar-width: thin;
        scrollbar-color: #fbbf24 #fef9c3;
    }

    .dj-panel-form {
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
    }

    /* ============================================================
   DIVISOR ARRASTRABLE (RESIZER)
   ============================================================ */
    #djResizer {
        flex: 0 0 10px;
        width: 10px;
        background: #e5e7eb;
        cursor: col-resize;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: background .15s;
        user-select: none;
        z-index: 10;
    }

    #djResizer:hover,
    #djResizer.dragging {
        background: #fbbf24;
    }

    /* Línea decorativa vertical con label */
    #djResizer::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 2px;
        background: #d1d5db;
        border-radius: 2px;
        transition: background .15s;
    }

    #djResizer:hover::before,
    #djResizer.dragging::before {
        background: #f59e0b;
    }

    /* Badge central del divisor */
    #djResizerBadge {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 20px;
        padding: 6px 3px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        transition: border-color .15s, box-shadow .15s;
        cursor: col-resize;
        z-index: 11;
    }

    #djResizer:hover #djResizerBadge,
    #djResizer.dragging #djResizerBadge {
        border-color: #f59e0b;
        box-shadow: 0 2px 8px rgba(245, 158, 11, .25);
    }

    #djResizerBadge span {
        display: block;
        width: 3px;
        height: 3px;
        background: #9ca3af;
        border-radius: 50%;
        transition: background .15s;
    }

    #djResizer:hover #djResizerBadge span,
    #djResizer.dragging #djResizerBadge span {
        background: #f59e0b;
    }

    /* Label ANTIGUO / NUEVO encima del divisor */
    #djResizerLabels {
        position: absolute;
        top: 8px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        pointer-events: none;
        z-index: 12;
    }

    #djResizerLabels .lbl-ant,
    #djResizerLabels .lbl-new {
        font-size: 8px;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 3px;
        white-space: nowrap;
        letter-spacing: .03em;
    }

    #djResizerLabels .lbl-ant {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    #djResizerLabels .lbl-new {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #86efac;
    }

    /* En móviles ocultar divisor */
    @media (max-width: 768px) {
        #djResizer {
            display: none;
        }

        .dj-panel-backup {
            width: 100% !important;
            max-width: 100% !important;
        }
    }
</style>
