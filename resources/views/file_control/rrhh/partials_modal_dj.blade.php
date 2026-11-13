{{-- Partial: _modal_dj.blade.php — Split View Comparación --}}
{{-- Catálogo de vínculos familiares para JS (filas dinámicas de familiares) --}}
<script>
    window.TIPOS_VINCULO = window.TIPOS_VINCULO || @json(array_map('trim', array_column($tiposVinculo ?? [], 'descripcion')));
</script>
@if(session('tipo_rol') == 9 || session('tipo_rol') == 8)
<style>
    #formDatos input:not([type="hidden"]),
    #formDatos textarea {
        pointer-events: none !important;
        background: #f3f4f6 !important;
        color: #6b7280 !important;
        cursor: not-allowed !important;
    }
    #formDatos select {
        pointer-events: none !important;
        background: #f3f4f6 !important;
        color: #6b7280 !important;
        cursor: not-allowed !important;
        appearance: none !important;
        -webkit-appearance: none !important;
    }
    #addFamilyMember,
    #btnSubirFoto,
    #btnEliminarFoto,
    .remove-family {
        display: none !important;
    }
</style>
@endif

@include('file_control.rrhh.modal_js_styles')

<div id="modalDjGestion"
    class="hs-overlay w-full h-full fixed top-0 left-0 z-[9999] transition-all duration-500 overflow-x-hidden overflow-y-auto hidden pointer-events-none"
    data-hs-overlay-keyboard="false">
    <div class="-translate-y-5 hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 opacity-0 ease-in-out transition-all duration-500 sm:w-full my-8 sm:mx-auto flex flex-col bg-white shadow-sm rounded"
        style="width:96%;max-width:1400px;">
        <div class="flex flex-col border border-default-200 shadow-sm rounded-lg pointer-events-auto">

            {{-- Header --}}
            <div
                style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid #e5e7eb;background:#fff;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span
                        style="font-size:13px;font-weight:700;color:#374151;letter-spacing:.02em;text-transform:uppercase;">Declaración Jurada</span>
                    {{-- <div id="splitModeBadge"
                        style="display:none;align-items:center;gap:6px;background:#fef3c7;border:1px solid #fde68a;padding:2px 10px;border-radius:20px;">
                        <span
                            style="width:7px;height:7px;background:#f59e0b;border-radius:50%;display:inline-block;"></span>
                        <span
                            style="font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.04em;">Modo
                            Comparación Activo</span>
                    </div> --}}
                </div>
                <button type="button" data-hs-overlay="#modalDjGestion"
                    style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px;line-height:1;padding:2px 4px;"
                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">&#x2715;</button>
            </div>

            {{-- Split Body --}}
            <div class="dj-split-wrapper no-backup" id="djSplitWrapper" style="overflow:hidden;max-height:80vh;">

                {{-- ===================== PANEL IZQUIERDO: BACKUP ===================== --}}
                {{-- ===================== PANEL IZQUIERDO: BACKUP (nueva estructura) ===================== --}}
                <div class="dj-panel-backup" id="panelBackup">

                    <div class="dj-panel-backup-header">
                        <svg style="width:14px;height:14px;stroke:#92400e;flex-shrink:0;" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>DJ Anterior — Solo lectura</span>
                        <span id="bkFechaModBadge"
                            style="font-size:9px;background:#fde68a;color:#78350f;padding:1px 7px;border-radius:20px;font-weight:600;margin-left:auto;"></span>
                    </div>

                    {{-- ① DATOS PERSONALES --}}
                    <div class="dj-group" style="border-color:#fde68a;">
                        <div class="dj-group-header" style="background:#92400e;">① Datos Personales</div>
                        <div class="dj-group-body" style="background:#fffbeb;">

                            {{-- Identidad --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5.121 17.804A9 9 0 1118.879 6.196 9 9 0 015.121 17.804zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Identidad y Datos Personales
                                </div>
                                <div class="dj-section-body">
                                    <div style="display:flex;gap:14px;align-items:flex-start;">
                                        <div style="flex:1;">
                                            <div class="dj-grid-2" style="margin-bottom:8px;">
                                                <div class="bk-field" data-bk="nombre1"><label>Primer
                                                        Nombre</label><span class="bk-val" data-field="NOMB_1"></span>
                                                </div>
                                                <div class="bk-field" data-bk="nombre2"><label>Segundo
                                                        Nombre</label><span class="bk-val" data-field="NOMB_2"></span>
                                                </div>
                                                <div class="bk-field" data-bk="apellido_paterno"><label>Apellido
                                                        Paterno</label><span class="bk-val" data-field="APEL_1"></span>
                                                </div>
                                                <div class="bk-field" data-bk="apellido_materno"><label>Apellido
                                                        Materno</label><span class="bk-val" data-field="APEL_2"></span>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Foto backup --}}
                                        <div style="position:relative; width:70px; height:85px; flex-shrink:0;">
                                            <!-- 1. El recuadro punteado (Placeholder) -->
                                            <div id="placeholderFotoBackup"
                                                style="position:absolute; inset:0; border:2px dashed #fbbf24; border-radius:6px; display:flex; align-items:center; justify-content:center; background:#fef9c3;">
                                                <span style="font-size:9px; color:#92400e; font-weight:600;">FOTO</span>
                                            </div>
                                            
                                            <!-- 2. La imagen real (Oculta por defecto) -->
                                            <img id="previewFotoBackup" class="hidden"
                                                style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:6px; border:1px solid #fbbf24; box-shadow:0 1px 2px rgba(0,0,0,0.05);"
                                                alt="Foto Anterior" />
                                        </div>
                                    </div>
                                    <hr class="dj-divider" style="border-color:#fde68a;">
                                    <div class="dj-grid-4">
                                        <div class="bk-field" data-bk="dni"><label>DNI</label><span class="bk-val"
                                                data-field="NRO_DOCU_IDEN"></span></div>
                                        <div class="bk-field" data-bk="caduca"><label>Caduca</label><span class="bk-val"
                                                data-field="PERS_FECHCADUCADNI"></span></div>
                                        <div class="bk-field" data-bk="estado_civil"><label>Estado Civil</label><span
                                                class="bk-val" data-field="ESCI_DESCRIPCION"></span></div>
                                        <div class="bk-field" data-bk="sexo"><label>Sexo</label><span class="bk-val"
                                                data-field="PERS_SEXO"></span></div>
                                        <div class="bk-field" data-bk="fecha_nacimiento"><label>Fecha de
                                                Nacimiento</label><span class="bk-val" data-field="FECH_NACI"></span>
                                        </div>
                                        <div class="bk-field" data-bk="ciudad_nacimiento"><label>País de
                                                Nacimiento</label><span class="bk-val"
                                                data-field="dj2026_ciudad_naci"></span></div>
                                        <div class="bk-field" data-bk="sabe_nadar" style="visibility:hidden;">
                                            <label>¿Sabe nadar?</label><span class="bk-val"
                                                data-field="PERS_SNADAR"></span></div>
                                    </div>
                                </div>
                            </div>



                            {{-- Contacto --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.372 4.115a1 1 0 01-.21.979l-2.073 2.073a11.05 11.05 0 005.293 5.293l2.073-2.073a1 1 0 01.979-.21l4.115 1.372a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    Información de Contacto
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="celular"><label>Celular</label><span
                                                class="bk-val" data-field="PERS_TELEFONO"></span></div>
                                        <div class="bk-field" data-bk="correo"><label>Correo electrónico</label><span
                                                class="bk-val" data-field="PERS_EMAIL"></span></div>
                                        <div class="bk-field" data-bk="whatsapp"><label>WhatsApp</label><span
                                                class="bk-val" data-field="PERS_WHATSAPP"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Médica --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v8m-4-4h8m7 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Información Médica
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="tipo_sangre"><label>Tipo de Sangre</label><span
                                                class="bk-val" data-field="tipo_sangr"></span></div>
                                        <div class="bk-field" data-bk="peso"><label>Peso (kg)</label><span
                                                class="bk-val" data-field="peso_kilo"></span></div>
                                        <div class="bk-field" data-bk="talla"><label>Talla (m)</label><span
                                                class="bk-val" data-field="tall_metr"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Previsional --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 1.343-3 3v7h6v-7c0-1.657-1.343-3-3-3zM5 13h14M5 17h14M9 21h6" />
                                    </svg>
                                    Información Previsional
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="sistema_previsional"><label>Sistema
                                                Previsional</label><span class="bk-val"
                                                data-field="DESC_SIST_PENS"></span></div>
                                        <div class="bk-field" data-bk="essalud"><label>ESSALUD Vida</label><span
                                                class="bk-val" data-field="ESSALUD"></span></div>
                                        <div class="bk-field" data-bk="pensionista"><label>Pensionista</label><span
                                                class="bk-val" data-field="PERS_PENSIONISTA"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Educación --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0a9 9 0 11-9-9" />
                                    </svg>
                                    Educación
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-4">
                                        <div class="bk-field" data-bk="grado_instruccion"><label>Grado de
                                                Instrucción</label><span class="bk-val"
                                                data-field="NIED_ABREVIADO"></span></div>
                                        <div class="bk-field"><label>Institución</label><span class="bk-val"
                                                data-field="IEDU_CODIGO"></span></div>
                                        <div class="bk-field"><label>Carrera</label><span class="bk-val"
                                                data-field="CARR_CODIGO"></span></div>
                                        <div class="bk-field" data-bk="anio_egreso"><label>Año de egreso</label><span
                                                class="bk-val" data-field="EGRESO_EDUCATIVO"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Adicional --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                    </svg>
                                    Información Adicional
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="embargos"><label>Embargos
                                                financieros</label><span class="bk-val"
                                                data-field="PERS_EMBARGO"></span></div>
                                        {{-- <div class="bk-field" data-bk="consumo_sustancias"><label>Consumo de
                                                sustancias</label><span class="bk-val" data-field="PERS_CONSMO"></span>
                                        </div> --}}
                                        <div class="bk-field"><label>Cuenta de Sueldo</label><span class="bk-val"
                                                data-field="dj2026_banco"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Direcciones --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3zm0 0c-4.418 0-8 3.582-8 8a8 8 0 0016 0c0-4.418-3.582-8-8-8z" />
                                    </svg>
                                    Direcciones
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-2">
                                        <div>
                                            <p
                                                style="font-size:10px;color:#78350f;font-weight:700;text-transform:uppercase;margin-bottom:6px;">
                                                — Dirección Actual</p>
                                            <div class="bk-field" data-bk="direccion_actual">
                                                <label>Descripción</label><span class="bk-val"
                                                    data-field="DIRECCION"></span></div>
                                        </div>
                                        <div>
                                            <p
                                                style="font-size:10px;color:#78350f;font-weight:700;text-transform:uppercase;margin-bottom:6px;">
                                                — Dirección DNI</p>
                                            <div class="bk-field" data-bk="direccion_dni">
                                                <label>Descripción</label><span class="bk-val"
                                                    data-field="PERS_DIREC_DNI"></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Emergencia --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                    </svg>
                                    Contacto de Emergencia
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="contacto_emergencia"><label>Llamar a</label><span
                                                class="bk-val" data-field="PERS_NOMCONTACTO"></span></div>
                                        <div class="bk-field" data-bk="celular_emergencia"><label>Celular</label><span
                                                class="bk-val" data-field="PERS_NROEMERGENCIA"></span></div>
                                        <div class="bk-field" data-bk="parentesco_emergencia">
                                            <label>Parentesco</label><span class="bk-val"
                                                data-field="PERS_EMERC_FAMILIAR"></span></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>{{-- /① --}}

                    {{-- ② DATOS LABORALES --}}
                    <div class="dj-group" style="border-color:#fde68a;">
                        <div class="dj-group-header" style="background:#92400e;">② Datos Laborales</div>
                        <div class="dj-group-body" style="background:#fffbeb;">

                            {{-- Solo administrativo --}}
                            <div class="dj-section bk-tipo-section" data-bk-tipo="administrativo"
                                style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Información Profesional
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="ocupacion_principal"><label>Profesión / Ocupación
                                                Principal</label><span class="bk-val"
                                                data-field="PERS_PROFESION"></span></div>
                                        <div class="bk-field" data-bk="experiencia_anios">
                                            <label>Experiencia</label>
                                            <div style="font-size: 12px; margin-top: 2px;">
                                                <span class="bk-val" data-field="dj2026_experiencia_anios"></span> Años y 
                                                <span class="bk-val" data-field="dj2026_experiencia_meses"></span> Meses
                                            </div>
                                        </div>
                                        <div class="bk-field"><label>¿Familiar en la empresa?</label><span
                                                class="bk-val" data-field="dj2026_familiar_empresa"></span></div>
                                    </div>
                                    <div class="dj-grid-2" style="margin-top:6px;">
                                        <div class="bk-field"><label>Nombre del familiar</label><span class="bk-val"
                                                data-field="dj2026_familiar_nombre"></span></div>
                                        <div class="bk-field"><label>Parentesco</label><span class="bk-val"
                                                data-field="dj2026_familiar_parentesco"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Solo operativo --}}
                            <div class="dj-section bk-tipo-section  bk-ope" data-bk-tipo="operativo"
                                style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0a9 9 0 11-9-9" />
                                    </svg>
                                    Curso SUCAMEC
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-2">
                                        <div class="bk-field" data-bk="curso_sucamec"><label>Curso SUCAMEC</label><span
                                                class="bk-val" data-field="PERS_CONDISCAMEC"></span></div>
                                        <div class="bk-field" data-bk="sucamec_obs"><label>Observación</label><span
                                                class="bk-val" data-field="PERS_NRODISCAMEC"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- SMO: ambos --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6H11l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                    </svg>
                                    Servicio Militar (S.M.O.)
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="smo"><label>S.M.O.</label><span class="bk-val"
                                                data-field="PERS_LUGARSMO"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Solo operativo --}}
                            <div class="dj-section bk-tipo-section" data-bk-tipo="operativo"
                                style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 13l6-6m0 0l6 6m-6-6v12" />
                                    </svg>
                                    Licencia y Tipo de Arma
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="licencia_arma"><label>Licencia de
                                                Arma</label><span class="bk-val" data-field="PERS_NROLICENCIA"></span>
                                        </div>
                                        <div class="bk-field" data-bk="tipo_arma"><label>Tipo de Arma</label><span
                                                class="bk-val" data-field="PERS_TIPOARMA"></span></div>
                                        <div class="bk-field" data-bk="arma_propia"><label>Arma Propia</label><span
                                                class="bk-val" data-field="PERS_CONARMAS"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Brevete: ambos --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 13l2-5h14l2 5M5 13v5h2v-2h10v2h2v-5M5 13h14" />
                                    </svg>
                                    Licencia de Conducir
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-4">
                                        <div class="bk-field" data-bk="brevete"><label>N° Brevete</label><span
                                                class="bk-val" data-field="PERS_BREVETE"></span></div>
                                        <div class="bk-field" data-bk="clase_brevete"><label>Clase</label><span
                                                class="bk-val" data-field="CLASE_BREVETE"></span></div>
                                        <div class="bk-field"><label>Tipo de Vehículo</label><span class="bk-val"
                                                data-field="PERS_TIPO_VEHICULO"></span></div>
                                        <div class="bk-field"><label>Vehículo Propio</label><span class="bk-val"
                                                data-field="PERS_VEHICULO_PROPIO"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Experiencia laboral: ambos --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 7V4h6v3m-9 4h12v9H6V11z" />
                                    </svg>
                                    Experiencia Laboral
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-3">
                                        <div class="bk-field" data-bk="empresa_anterior"><label>Empresa
                                                Anterior</label><span class="bk-val" data-field="PERS_CTRABANT"></span>
                                        </div>
                                        <div class="bk-field" data-bk="cargo_anterior"><label>Cargo</label><span
                                                class="bk-val" data-field="PERS_CARGOTRABANT"></span></div>
                                        <div class="bk-field"><label>Duración</label><span class="bk-val"
                                                data-field="PERS_DURACIONANT"></span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Ocupaciones alternas: ambos --}}
                            <div class="dj-section" style="border-color:#fde68a;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                    Profesión u Ocupación Alterna
                                </div>
                                <div class="dj-section-body">
                                    <div class="dj-grid-2">
                                        <div class="bk-field" data-bk="dj2026_laboral_1"><label>Ocupación Alterna
                                                1</label><span class="bk-val" data-field="dj2026_laboral_1"></span>
                                        </div>
                                        <div class="bk-field" data-bk="dj2026_laboral_2"><label>Ocupación Alterna
                                                2</label><span class="bk-val" data-field="dj2026_laboral_2"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>{{-- /② --}}

                    {{-- ③ DATOS FAMILIARES --}}
                    <div class="dj-group" style="border-color:#fde68a;margin-bottom:0;">
                        <div class="dj-group-header" style="background:#92400e;">③ Datos Familiares</div>
                        <div class="dj-group-body" style="background:#fffbeb;">
                            <div class="dj-section" style="border-color:#fde68a;margin-bottom:0;">
                                <div class="dj-section-header"
                                    style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 12a4 4 0 10-8 0 4 4 0 008 0zM12 14v7m-7-7a9 9 0 0118 0v7H5v-7z" />
                                    </svg>
                                    Registros Familiares
                                </div>
                                <div class="dj-section-body">
                                    <table class="bk-fam-table" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Parentesco</th>
                                                <th>Apellidos y Nombres</th>
                                                <th>Fecha Nac.</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bodyBackupFamiliares">
                                            <tr>
                                                <td colspan="3" style="padding:6px;color:#9ca3af;font-style:italic;">
                                                    Cargando...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>{{-- /③ --}}

                </div>{{-- /panel backup --}}
                {{-- ✅ AGREGAR ESTO --}}
                <div id="djResizer">
                    {{-- <div id="djResizerLabels">
                        <span class="lbl-ant">◀ ANTIGUO</span>
                        <span class="lbl-new">NUEVO ▶</span>
                    </div> --}}
                    <div id="djResizerBadge">
                        <span></span><span></span><span></span><span></span><span></span>
                    </div>
                </div>
                {{-- ===================== PANEL DERECHO: FORMULARIO ===================== --}}
                <div class="dj-panel-form" id="panelForm">

                    <div class="dj-panel-form-header">
                        <svg style="width:14px;height:14px;stroke:#166534;flex-shrink:0;" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>DJ Nueva — Datos actualizados</span>
                        <span id="contadorDiffs"
                            style="display:none;font-size:9px;background:#ef4444;color:#fff;padding:1px 8px;border-radius:20px;font-weight:700;margin-left:auto;"></span>
                    </div>

                    <form id="formDatos" method="POST">
                        @csrf
                        <input type="hidden" name="cod_postulante" id="cod_postulante">
                        <input type="hidden" id="tipo_personal" name="tipo_personal">
                        <input type="hidden" id="cargo" name="cargo">

                        <div id="cardTipoCargo" style="display:none;gap:12px;align-items:flex-end;flex-wrap:wrap;padding:12px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:14px;">
                            <div style="flex:1;min-width:240px;">
                                <label class="dj-label">Tipo de Personal</label>
                                <select id="tipo_personal_ui" class="dj-select">
                                    <option value="">— Seleccionar —</option>
                                </select>
                            </div>
                            <div style="flex:1;min-width:240px;">
                                <label class="dj-label">Cargo</label>
                                <select id="cargo_ui" class="dj-select">
                                    <option value="">— Seleccionar —</option>
                                </select>
                            </div>
                            <div id="wrap_sctr" style="display:none;min-width:130px;">
                                <label class="dj-label">&nbsp;</label>
                                <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#374151;cursor:pointer;padding:7px 0;">
                                    <input type="checkbox" id="autorizar_sctr" name="autorizar_sctr" value="1"
                                        style="width:14px;height:14px;accent-color:#25d366;cursor:pointer;">
                                    SCTR
                                </label>
                            </div>
                        </div>

                        {{-- ① DATOS PERSONALES --}}
                        <div class="dj-group">
                            <div class="dj-group-header">① Datos Personales</div>
                            <div class="dj-group-body">

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5.121 17.804A9 9 0 1118.879 6.196 9 9 0 015.121 17.804zM15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Identidad y Datos Personales
                                    </div>
                                    <div class="dj-section-body">
                                        <div style="display:flex;gap:14px;align-items:flex-start;">
                                            <div style="flex:1;">
                                                <input type="hidden" id="nombres_apellidos" name="nombres_apellidos">
                                                <div class="dj-grid-2" style="margin-bottom:10px;">
                                                    <div>
                                                        <label class="dj-label">Primer Nombre</label>
                                                        <input type="text" id="nombre1" name="nombre1" class="dj-input"
                                                            placeholder="Primer nombre"
                                                            style="text-transform:uppercase;" data-compare="nombre1">
                                                    </div>
                                                    <div>
                                                        <label class="dj-label">Segundo Nombre</label>
                                                        <input type="text" id="nombre2" name="nombre2" class="dj-input"
                                                            placeholder="Segundo nombre (opcional)"
                                                            style="text-transform:uppercase;" data-compare="nombre2">
                                                    </div>
                                                    <div>
                                                        <label class="dj-label">Apellido Paterno</label>
                                                        <input type="text" id="apellido_paterno" name="apellido_paterno"
                                                            class="dj-input" placeholder="Apellido paterno"
                                                            style="text-transform:uppercase;"
                                                            data-compare="apellido_paterno">
                                                    </div>
                                                    <div>
                                                        <label class="dj-label">Apellido Materno</label>
                                                        <input type="text" id="apellido_materno" name="apellido_materno"
                                                            class="dj-input" placeholder="Apellido materno"
                                                            style="text-transform:uppercase;"
                                                            data-compare="apellido_materno">
                                                    </div>
                                                </div>
                                                <a href="https://eldni.com/pe/buscar-datos-por-dni" target="_blank"
                                                    style="display:inline-block;margin-top:6px;font-size:11px;color:var(--color-primary,#6366f1);border:1px solid var(--color-primary,#6366f1);padding:3px 10px;border-radius:5px;text-decoration:none;">
                                                    Consultar DNI
                                                </a>
                                            </div>
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                                                <div class="dj-foto-wrap" id="placeholderFoto">
                                                    <svg style="width:28px;height:28px;stroke:#d1d5db;" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M3 7a4 4 0 014-4h10a4 4 0 014 4v10a4 4 0 01-4 4H7a4 4 0 01-4-4V7z" />
                                                    </svg>
                                                    <span
                                                        style="font-size:10px;color:#9ca3af;margin-top:3px;">FOTO</span>
                                                    <img id="previewFoto" class="hidden" />
                                                    <input type="file" id="inputFoto" accept="image/jpeg,.jpg,.jpeg" class="hidden" />
                                                </div>
                                                <div style="display:flex;flex-direction:column;gap:5px;align-items:center;">
                                                    <div style="display:flex;gap:5px;">
                                                        <button type="button" id="btnSubirFoto" class="dj-btn-sm"
                                                            style="background:#f3f4f6;color:#374151;border-radius:5px;">Subir
                                                            foto</button>
                                                        <button type="button" id="btnEliminarFoto"
                                                            class="dj-btn-sm dj-btn-danger hidden">Quitar</button>
                                                    </div>
                                                    <button type="button" id="btnSubirDni" class="dj-btn-sm"
                                                        style="background:#f3f4f6;color:#374151;border-radius:5px;">Subir DNI</button>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Modal Subir DNI: anverso/reverso con vista previa; se sube al presionar GUARDAR --}}
                                        <div id="modalDni" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
                                            <div style="background:#fff;border-radius:10px;padding:18px 20px;width:92%;max-width:460px;box-shadow:0 10px 30px rgba(0,0,0,.25);">
                                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                                    <strong style="font-size:14px;">Subir DNI</strong>
                                                    <button type="button" id="cerrarModalDni" title="Cerrar"
                                                        style="background:transparent;border:none;font-size:20px;cursor:pointer;color:#6b7280;line-height:1;">✕</button>
                                                </div>
                                                <div style="display:flex;gap:18px;justify-content:center;flex-wrap:wrap;">
                                                    <div style="text-align:center;">
                                                        <button type="button" id="btnDniAnverso" class="dj-btn-sm"
                                                            style="background:#f3f4f6;color:#374151;border-radius:5px;">DNI ANVERSO</button>
                                                        <input type="file" id="inputDniAnverso" accept="image/jpeg,.jpg,.jpeg" class="hidden" />
                                                        <div id="prevDniAnverso" style="display:none;margin-top:8px;position:relative;width:150px;">
                                                            <img id="imgDniAnverso" style="width:100%;border:1px solid #e5e7eb;border-radius:5px;display:block;" />
                                                            <button type="button" id="clearDniAnverso" title="Quitar"
                                                                style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;line-height:1;">✕</button>
                                                        </div>
                                                    </div>
                                                    <div style="text-align:center;">
                                                        <button type="button" id="btnDniReverso" class="dj-btn-sm"
                                                            style="background:#f3f4f6;color:#374151;border-radius:5px;">DNI REVERSO</button>
                                                        <input type="file" id="inputDniReverso" accept="image/jpeg,.jpg,.jpeg" class="hidden" />
                                                        <div id="prevDniReverso" style="display:none;margin-top:8px;position:relative;width:150px;">
                                                            <img id="imgDniReverso" style="width:100%;border:1px solid #e5e7eb;border-radius:5px;display:block;" />
                                                            <button type="button" id="clearDniReverso" title="Quitar"
                                                                style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;line-height:1;">✕</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="dj-divider">
                                        {{-- Modal DNI del hijo: anverso/reverso con vista previa; se sube al presionar GUARDAR --}}
                                        <div id="modalDniHijos" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
                                            <div style="background:#fff;border-radius:10px;padding:18px 20px;width:92%;max-width:460px;box-shadow:0 10px 30px rgba(0,0,0,.25);">
                                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                                    <strong style="font-size:14px;">DNI del hijo — <span id="dni_hijo_label"></span></strong>
                                                    <button type="button" id="cerrarModalDniHijos" title="Cerrar"
                                                        style="background:transparent;border:none;font-size:20px;cursor:pointer;color:#6b7280;line-height:1;">✕</button>
                                                </div>
                                                <div style="display:flex;gap:18px;justify-content:center;flex-wrap:wrap;">
                                                    <div style="text-align:center;">
                                                        <button type="button" id="btnDniHijoAnverso" class="dj-btn-sm"
                                                            style="background:#f3f4f6;color:#374151;border-radius:5px;">DNI ANVERSO</button>
                                                        <input type="file" id="inputDniHijoAnverso" accept="image/jpeg,.jpg,.jpeg" class="hidden" />
                                                        <div id="prevDniHijoAnverso" style="display:none;margin-top:8px;position:relative;width:150px;">
                                                            <img id="imgDniHijoAnverso" style="width:100%;border:1px solid #e5e7eb;border-radius:5px;display:block;" />
                                                            <button type="button" id="clearDniHijoAnverso" title="Quitar"
                                                                style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;line-height:1;">✕</button>
                                                        </div>
                                                    </div>
                                                    <div style="text-align:center;">
                                                        <button type="button" id="btnDniHijoReverso" class="dj-btn-sm"
                                                            style="background:#f3f4f6;color:#374151;border-radius:5px;">DNI REVERSO</button>
                                                        <input type="file" id="inputDniHijoReverso" accept="image/jpeg,.jpg,.jpeg" class="hidden" />
                                                        <div id="prevDniHijoReverso" style="display:none;margin-top:8px;position:relative;width:150px;">
                                                            <img id="imgDniHijoReverso" style="width:100%;border:1px solid #e5e7eb;border-radius:5px;display:block;" />
                                                            <button type="button" id="clearDniHijoReverso" title="Quitar"
                                                                style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;line-height:1;">✕</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dj-grid-4">
                                            <div><label class="dj-label">DNI</label><input type="text" id="dni"
                                                    name="dni" class="dj-input" placeholder="12345678"
                                                    data-compare="dni"></div>
                                            <div><label class="dj-label">Caduca</label><input type="date" id="caduca"
                                                    name="caduca" class="dj-input" data-compare="caduca">
                                                <label style="display:flex;align-items:center;gap:6px;margin-top:6px;font-size:13px;cursor:pointer;">
                                                    <input type="checkbox" id="no_caduca_dni" name="no_caduca_dni" style="width:16px;height:16px;cursor:pointer;">
                                                    No Caduca
                                                </label>
                                            </div>
                                            <div>
                                                <label class="dj-label">Estado Civil</label>
                                                <select id="estado_civil" name="estado_civil" class="dj-select"
                                                    data-compare="estado_civil">
                                                    <option value="" disabled>—</option>
                                                    {{-- <option value="2007000001">SOLTERO</option>
                                                    <option value="2007000002">CASADO</option>
                                                    <option value="2007000003">DIVORCIADO</option>
                                                    <option value="2007000004">VIUDO</option>
                                                    <option value="2007000008">CONVIVIENTE</option> --}}
                                                    {{-- <option value="S">SOLTERO</option>
                                                    <option value="C">CASADO</option>
                                                    <option value="D">DIVORCIADO</option>
                                                    <option value="V">VIUDO</option>
                                                    <option value="2007000008">CONVIVIENTE</option> --}}
                                                </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">Sexo</label>
                                                <select id="sexo" name="sexo" class="dj-select" data-compare="sexo">
                                                    <option value="" disabled>—</option>
                                                    <option value="M">Masculino</option>
                                                    <option value="F">Femenino</option>
                                                </select>
                                            </div>
                                            <div><label class="dj-label">Fecha de Nacimiento</label><input type="date"
                                                    id="fecha_nacimiento" name="fecha_nacimiento" class="dj-input"
                                                    data-compare="fecha_nacimiento"></div>
                                            <div>
                                                <label class="dj-label">Fecha Ingreso a Solmar</label>
                                                <input type="date" id="fecha_ingreso_solmar" name="fecha_ingreso_solmar"
                                                    class="dj-input" readonly
                                                    style="background:#f3f4f6;color:#6b7280;cursor:not-allowed;">
                                            </div>
                                            <div id="wrap_fecha_cese" style="display:none;">
                                                <label class="dj-label">Fecha de Cese</label>
                                                <input type="date" id="fecha_cese" name="fecha_cese"
                                                    class="dj-input" readonly
                                                    style="background:#f3f4f6;color:#6b7280;cursor:not-allowed;">
                                            </div>
                                            <input type="hidden" id="ciudad_nacimiento" name="ciudad_nacimiento">
                                            <div style="visibility:hidden;">
                                                <label class="dj-label">¿Sabe nadar?</label>
                                                <select id="sabe_nadar" name="sabe_nadar" class="dj-select"
                                                    data-compare="sabe_nadar">
                                                    <option value="" disabled>—</option>
                                                    <option value="SI">Sí</option>
                                                    <option value="NO">No</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        País de Nacimiento
                                    </div>
                                    <div class="dj-section-body">
                                        <div style="display:flex;gap:14px;align-items:flex-start;">
                                            <div style="flex:1;">
                                                <div class="dj-grid-4" style="margin-bottom:8px;">
                                                    <div>
                                                        <label class="dj-label">País</label>
                                                        <select id="aj_pais" name="nacionalidad" class="dj-select">
                                                            <option value="">— Seleccionar —</option>
                                                        </select>
                                                    </div>
                                                    <div id="aj_wrap_departamento_nac">
                                                        <label class="dj-label">Departamento</label>
                                                        <select id="departamento_nac" name="departamento_nac" class="dj-select">
                                                            <option value="" disabled selected>—</option>
                                                        </select>
                                                    </div>
                                                    <div id="aj_wrap_provincia_nac">
                                                        <label class="dj-label">Provincia</label>
                                                        <select id="provincia_nac" name="provincia_nac" class="dj-select">
                                                            <option value="" disabled selected>—</option>
                                                        </select>
                                                    </div>
                                                    <div id="aj_wrap_distrito_nac">
                                                        <label class="dj-label">Distrito</label>
                                                        <select id="distrito_nac" name="distrito_nac" class="dj-select">
                                                            <option value="" disabled selected>—</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.372 4.115a1 1 0 01-.21.979l-2.073 2.073a11.05 11.05 0 005.293 5.293l2.073-2.073a1 1 0 01.979-.21l4.115 1.372a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        Información de Contacto
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div><label class="dj-label">Celular</label><input type="text" id="celular"
                                                    name="celular" class="dj-input" placeholder="999 999 999"
                                                    data-compare="celular"></div>
                                            <div><label class="dj-label">Correo electrónico</label><input type="email"
                                                    id="correo" name="correo" class="dj-input"
                                                    placeholder="ejemplo@correo.com" data-compare="correo"></div>
                                            <div><label class="dj-label">WhatsApp</label><input type="text"
                                                    id="whatsapp" name="whatsapp" class="dj-input"
                                                    placeholder="999 999 999"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v8m-4-4h8m7 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Información Médica
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div>
                                                <label class="dj-label">Tipo de Sangre</label>
                                                <select id="tipo_sangre" name="tipo_sangre" class="dj-select"
                                                    data-compare="tipo_sangre">
                                                    <option value="" disabled>—</option>
                                                    <option value="O+">O+</option>
                                                    <option value="O-">O-</option>
                                                    <option value="A+">A+</option>
                                                    <option value="A-">A-</option>
                                                    <option value="B+">B+</option>
                                                    <option value="B-">B-</option>
                                                    <option value="AB+">AB+</option>
                                                    <option value="AB-">AB-</option>
                                                    <option value="RH">RH</option>
                                                </select>
                                            </div>
                                            <div><label class="dj-label">Peso (kg)</label><input type="number" id="peso"
                                                    name="peso" step="0.01" class="dj-input" placeholder="70"
                                                    data-compare="peso"></div>
                                            <div><label class="dj-label">Talla (m)</label><input type="number"
                                                    id="talla" name="talla" step="0.01" class="dj-input"
                                                    placeholder="1.75" data-compare="talla"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 1.343-3 3v7h6v-7c0-1.657-1.343-3-3-3zM5 13h14M5 17h14M9 21h6" />
                                        </svg>
                                        Información Previsional
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div>
                                                <label class="dj-label">Sistema Provisional</label>
                                                <select id="sistema_previsional" name="sistema_previsional"
                                                    class="dj-select" data-compare="sistema_previsional">
                                                    <option value="" disabled>—</option>
                                                    {{-- <option value="03">AFP PROFUTURO</option>
                                                    <option value="01">ONP</option>
                                                    <option value="02">AFP INTEGRA</option>
                                               
                                                    <option value="10">AFP PRIMA</option>
                                                    <option value="27">AFP HABITAT</option>
                                                    <option value="07">NO APORTACION</option> --}}
                                                </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">ESSALUD Vida</label>
                                                <select id="essalud" name="essalud" class="dj-select"
                                                    data-compare="essalud">
                                                    <option value="" disabled>—</option>
                                                    <option value="SI">Sí</option>
                                                    <option value="NO">No</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">Pensionista</label>
                                                <select id="pensionista" name="pensionista" class="dj-select"
                                                    data-compare="pensionista">
                                                    <option value="" disabled>—</option>
                                                    <option value="SI">Sí</option>
                                                    <option value="NO">No</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0a9 9 0 11-9-9" />
                                        </svg>
                                        Educación
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-4">
                                            <div>
                                                <label class="dj-label">Grado de Instrucción</label>
                                                <select id="grado_instruccion" name="grado_instruccion"
                                                    class="dj-select" data-compare="grado_instruccion">
                                                    <option value="" disabled>—</option>
                                                    @foreach ($grados as $grado)
                                                        <option value="{{ $grado->id }}">{{ $grado->text }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">Institución</label>
                                                <select id="institucion" name="institucion" class="dj-select">
                                                    <option value="" selected>—</option>
                                                    @foreach ($instituciones as $inst)
                                                        <option value="{{ $inst->id }}">{{ $inst->text }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">Carrera</label>
                                                <select id="carrera" name="carrera" class="dj-select">
                                                    <option value="" selected>—</option>
                                                    @foreach ($carreras as $carrera)
                                                        <option value="{{ $carrera->id }}">{{ $carrera->text }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div><label class="dj-label">Año de egreso</label><input type="number"
                                                    id="anio_egreso" name="anio_egreso" class="dj-input"
                                                    placeholder="2020" data-compare="anio_egreso"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                        </svg>
                                        Información Adicional
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div>
                                                <label class="dj-label">Sucursal</label>
                                                <select id="sucursal" name="sucursal" class="dj-select"
                                                    data-compare="sucursal" @if($tipoUsuario != 17) disabled style="opacity:0.5;cursor:not-allowed;" @endif>
                                                    <option value="">—</option>
                                                    @foreach ($sucursales ?? [] as $sucursal)
                                                        @if (!in_array(trim((string) $sucursal->codigo), ['', '0', '00'], true))
                                                            <option value="{{ trim((string) $sucursal->codigo) }}">{{ $sucursal->abreviatura }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="dj-label">Embargos financieros</label>
                                                <select id="embargos" name="embargos" class="dj-select"
                                                    data-compare="embargos">
                                                    <option value="" disabled>—</option>
                                                    <option value="SI">Sí</option>
                                                    <option value="NO">No</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="dj-label">Cuenta de Sueldo</label>
                                                <select id="cuenta_banco" name="cuenta_banco" class="dj-select">
                                                    <option value="" disabled>—</option>
                                                    @foreach ($bancos as $banco)
                                                        <option value="{{ $banco->id }}">{{ $banco->text }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3zm0 0c-4.418 0-8 3.582-8 8a8 8 0 0016 0c0-4.418-3.582-8-8-8z" />
                                        </svg>
                                        Direcciones
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-2">
                                            <div>
                                                <p class="dj-label"
                                                    style="color:#9ca3af;margin-bottom:6px;font-size:10px;">— Dirección
                                                    Actual</p>
                                                <div class="dj-grid-3" style="margin-bottom:8px;">
                                                    <div><label class="dj-label">Departamento</label><select
                                                            id="departamento_actual" name="departamento_actual"
                                                            class="dj-select">
                                                            <option value="" disabled>—</option>
                                                        </select></div>
                                                    <div><label class="dj-label">Provincia</label><select
                                                            id="provincia_actual" name="provincia_actual"
                                                            class="dj-select">
                                                            <option value="" disabled>—</option>
                                                        </select></div>
                                                    <div><label class="dj-label">Distrito</label><select
                                                            id="distrito_actual" name="distrito_actual"
                                                            class="dj-select">
                                                            <option value="" disabled>—</option>
                                                        </select></div>
                                                </div>
                                                <label class="dj-label">Descripción</label>
                                                <textarea id="direccion_actual" name="direccion_actual"
                                                    class="dj-textarea" placeholder="Dirección actual completa"
                                                    data-compare="direccion_actual"></textarea>
                                            </div>
                                            <div>
                                                <p class="dj-label"
                                                    style="color:#9ca3af;margin-bottom:6px;font-size:10px;">— Dirección
                                                    DNI</p>
                                                <div class="dj-grid-3" style="margin-bottom:8px;">
                                                    <div><label class="dj-label">Departamento</label><select
                                                            id="departamento_dni" name="departamento_dni"
                                                            class="dj-select">
                                                            <option value="" disabled>—</option>
                                                        </select></div>
                                                    <div><label class="dj-label">Provincia</label><select
                                                            id="provincia_dni" name="provincia_dni" class="dj-select">
                                                            <option value="" disabled>—</option>
                                                        </select></div>
                                                    <div><label class="dj-label">Distrito</label><select
                                                            id="distrito_dni" name="distrito_dni" class="dj-select">
                                                            <option value="" disabled>—</option>
                                                        </select></div>
                                                </div>
                                                <div class="dj-grid-2" style="margin-top:8px;">
                                                    <div>
                                                        <label class="dj-label">Tipo Zona</label>
                                                        <select id="tipo_zona_dni" name="tipo_zona_dni" class="dj-select"
                                                            data-compare="tipo_zona_dni">
                                                            <option value="">—</option>
                                                            @foreach ($tiposZona ?? [] as $tz)
                                                                <option value="{{ trim($tz->TIZO_CODIGO) }}">{{ $tz->TIZO_DESCRIPCION }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="dj-label">Nombre de Zona</label>
                                                        <input type="text" id="zona_dirdni" name="zona_dirdni"
                                                            class="dj-input" placeholder="Nombre de la zona"
                                                            maxlength="100" style="text-transform:uppercase;"
                                                            data-compare="zona_dirdni">
                                                    </div>
                                                </div>
                                                <label class="dj-label">Descripción</label>
                                                <textarea id="direccion_dni" name="direccion_dni" class="dj-textarea"
                                                    placeholder="Dirección registrada en el DNI"
                                                    data-compare="direccion_dni"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                        </svg>
                                        Contacto de Emergencia
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div><label class="dj-label">Llamar a</label><input type="text"
                                                    id="contacto_emergencia" name="contacto_emergencia" class="dj-input"
                                                    placeholder="Juan Pérez García" data-compare="contacto_emergencia">
                                            </div>
                                            <div><label class="dj-label">Celular</label><input type="text"
                                                    id="celular_emergencia" name="celular_emergencia" class="dj-input"
                                                    placeholder="999 999 999" data-compare="celular_emergencia"></div>
                                            <div>
                                                <label class="dj-label">Parentesco</label>
                                                <select id="parentesco_emergencia" name="parentesco_emergencia"
                                                        class="dj-select" data-compare="parentesco_emergencia">
                                                    <option value="">—</option>
                                                    @foreach(($tiposVinculo ?? []) as $tv)
                                                    <option value="{{ trim($tv->descripcion) }}">{{ trim($tv->descripcion) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>{{-- /① --}}

                        {{-- ② DATOS LABORALES --}}
                        <div class="dj-group">
                            <div class="dj-group-header">② Datos Laborales</div>
                            <div class="dj-group-body">

                                <div class="dj-section" data-tipo="administrativo">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        Información Profesional
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div><label class="dj-label">Profesión / Ocupación Principal</label><input
                                                    type="text" id="ocupacion_principal" name="ocupacion_principal"
                                                    class="dj-input" placeholder="Ej. Agente de Seguridad"
                                                    style="text-transform:uppercase;"
                                                    data-compare="ocupacion_principal"></div>
                                            <div>
                                                <label class="dj-label">EXPERIENCIA</label>
                                                <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                                                    <span style="font-size: 11px; font-weight: 600; color: #4b5563;">Años:</span>
                                                    <input type="number" id="experiencia_anios" name="experiencia_anios" class="dj-input" placeholder="0" min="0" style="width: 70px;">
                                                    
                                                    <span style="font-size: 11px; font-weight: 600; color: #4b5563;">Meses:</span>
                                                    <input type="number" id="experiencia_meses" name="experiencia_meses" class="dj-input" placeholder="0" min="0" max="11" style="width: 70px;">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="dj-label">¿Familiar en la empresa?</label>
                                                <select id="familiar_empresa" name="familiar_empresa" class="dj-select">
                                                    <option value="NO">No</option>
                                                    <option value="SI">Sí</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div id="div_familiar_interno" class="dj-subpanel hidden">
                                            <div class="dj-grid-2">
                                                <div><label class="dj-label">Nombre del familiar</label><input
                                                        type="text" id="familiar_nombre" name="familiar_nombre"
                                                        class="dj-input" placeholder="Nombre y apellidos"
                                                        style="text-transform:uppercase;"></div>
                                                <div>
                                                    <label class="dj-label">Parentesco</label>
                                                    <select id="familiar_parentesco" name="familiar_parentesco"
                                                        class="dj-select">
                                                        <option value="" disabled>—</option>
                                                    
                                                        <option value="PADRE">Padre</option>
                                                        <option value="MADRE">Madre</option>
                                                        <option value="CONYUGE">Conyuge</option>
                                                        <!-- <option value="ESPOSO">Esposo</option>
                                                        <option value="ESPOSA">Esposa</option> -->
                                                        <option value="HIJO">Hijo(a)</option>
                                                        <!-- <option value="HIJA">Hija</option> -->
                                                        <!-- <option value="HERMANO">Hermano</option>
                                                        <option value="HERMANA">Hermana</option>
                                                        <option value="ABUELO">Abuelo</option>
                                                        <option value="ABUELA">Abuela</option>
                                                        <option value="OTROS">OTROS</option> -->
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- SUCAMEC: solo operativo --}}
                                <div class="dj-section" data-tipo="operativo">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0a9 9 0 11-9-9" />
                                        </svg>
                                        Curso SUCAMEC
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-2">
                                            <div>
                                                <label class="dj-label">Curso SUCAMEC</label>
                                                <select id="curso_sucamec" name="curso_sucamec" class="dj-select"
                                                    data-compare="curso_sucamec">
                                                    <option value="NO">No</option>
                                                    <option value="SI">Sí</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- SMO: ambos tipos --}}
                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6H11l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                        </svg>
                                        Servicio Militar (S.M.O.)
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            {{-- <div>
                                                <label class="dj-label">S.M.O.</label>
                                                <select id="smo" name="smo" class="dj-select" data-compare="smo">
                                                    <option value="" disabled>—</option>
                                                    <option value="NO">No</option>
                                                    <option value="MARINA">Marina</option>
                                                    <option value="EJERCITO">Ejército</option>
                                                    <option value="AVIACION">Aviación</option>
                                                </select>
                                            </div> --}}
                                            <div>
                                                <label class="dj-label">Prestó S.M.O.</label>
                                                <select id="presto_smo" name="presto_smo"
                                                    class="dj-select" data-compare="presto_smo">
                                                    <option value="" disabled>Seleccionar...</option>
                                                    <option value="SI">SI</option>
                                                    <option value="NO">NO</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">Lugar de S.M.O.</label>
                                                <select id="lugar_smo" name="lugar_smo"
                                                    class="dj-select" data-compare="lugar_smo" disabled>
                                                    <option value="" disabled selected>Seleccionar...</option>
                                                    <option value="MG">MGP</option>
                                                    <option value="EP">EP</option>
                                                    <option value="FA">FAP</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section" data-tipo="operativo">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 13l6-6m0 0l6 6m-6-6v12" />
                                        </svg>
                                        Licencia y Tipo de Arma
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div><label class="dj-label">Licencia de Arma L4</label><input
                                                    id="licencia_arma" name="licencia_arma" class="dj-input"
                                                    placeholder="Nº de licencia..." data-compare="licencia_arma"></div>
                                            {{-- <div>
                                                <label class="dj-label">Tipo de Arma</label>
                                                <select id="tipo_arma" name="tipo_arma" class="dj-select"
                                                    data-compare="tipo_arma">
                                                    <option value="" disabled>—</option>
                                                    <option value="PISTOLA">Pistola</option>
                                                    <option value="REVOLVER">Revólver</option>
                                                    <option value="ESCOPETA">Escopeta</option>
                                                    <option value="RIFLE">Rifle</option>
                                                </select>
                                            </div> --}}
                                            <div>
                                                <label class="dj-label">Arma Propia</label>
                                                <select id="arma_propia" name="arma_propia" class="dj-select"
                                                    data-compare="arma_propia">
                                                    <option value="" disabled>—</option>
                                                    <option value="SI">Sí</option>
                                                    <option value="NO">No</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 13l2-5h14l2 5M5 13v5h2v-2h10v2h2v-5M5 13h14" />
                                        </svg>
                                        Licencia de Conducir
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-4">
                                            <div><label class="dj-label">N° Brevete</label><input type="text"
                                                    id="brevete" name="brevete" class="dj-input"
                                                    placeholder="Número de brevete" data-compare="brevete"></div>

                                            <div>
                                                <label class="dj-label">Clase</label>
                                                {{-- <input type="text"
                                                    id="clase_brevete" name="clase_brevete" class="dj-input"
                                                    placeholder="Ej: A-I, B-IIb" data-compare="clase_brevete"> --}}
                                                    <select name="clase_brevete" id="clase_brevete" data-compare="clase_brevete" class="dj-input" onchange="actualizarCategorias()">
                                                        <option value="" >Seleccionar...</option>
                                                        <option value="A">A</option>
                                                        <option value="B">B</option>
                                                    </select>
                                            </div>

                                            <div>
                                                <label class="dj-label">Tipo</label>
                                                {{-- <input type="text"
                                                    id="tipo_vehiculo" name="tipo_vehiculo" class="dj-input"
                                                    placeholder="Tipo de vehículo"> --}}
                                                    <select name="tipo_vehiculo" id="tipo_vehiculo" class="dj-input">

                                                    </select>
                                            </div>
                                            <div>
                                                <label class="dj-label">Vehículo Propio</label>
                                                <select id="vehiculo_propio" name="vehiculo_propio" class="dj-select">
                                                    <option value="" disabled>—</option>
                                                    <option value="SI">Sí</option>
                                                    <option value="NO">No</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 7V4h6v3m-9 4h12v9H6V11z" />
                                        </svg>
                                        Experiencia Laboral
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-3">
                                            <div><label class="dj-label">Empresa Anterior</label><input type="text"
                                                    id="empresa_anterior" name="empresa_anterior" class="dj-input"
                                                    placeholder="Nombre de la empresa" data-compare="empresa_anterior">
                                            </div>
                                            <div><label class="dj-label">Cargo</label><input type="text"
                                                    id="cargo_anterior" name="cargo_anterior" class="dj-input"
                                                    placeholder="Cargo" data-compare="cargo_anterior"></div>
                                            <div><label class="dj-label">Duración</label><input type="text"
        id="duracion_anterior" name="duracion_anterior"
        class="dj-input" placeholder="Ej. 1 año 7 meses" data-compare="duracion_anterior"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dj-section">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                        </svg>
                                        Profesión u Ocupación Alterna
                                    </div>
                                    <div class="dj-section-body">
                                        <div class="dj-grid-2">
                                            <div>
                                                <label class="dj-label">Ocupación Alterna 1</label>
                                                <input type="text" id="dj2026_laboral_1" name="dj2026_laboral_1"
                                                    class="dj-input" placeholder="Descripción ocupación alterna 1"
                                                    style="text-transform:uppercase;" data-compare="dj2026_laboral_1">
                                            </div>
                                            <div>
                                                <label class="dj-label">Ocupación Alterna 2</label>
                                                <input type="text" id="dj2026_laboral_2" name="dj2026_laboral_2"
                                                    class="dj-input" placeholder="Descripción ocupación alterna 2"
                                                    style="text-transform:uppercase;" data-compare="dj2026_laboral_2">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>{{-- /② --}}

                        {{-- ③ DATOS FAMILIARES --}}
                        <div class="dj-group" style="margin-bottom:0;">
                            <div class="dj-group-header">③ Datos Familiares</div>
                            <div class="dj-group-body">
                                <div class="dj-section" style="margin-bottom:0;">
                                    <div class="dj-section-header">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zM12 14v7m-7-7a9 9 0 0118 0v7H5v-7z" />
                                        </svg>
                                        Registros Familiares
                                    </div>
                                    <div class="dj-section-body">
                                        <div id="familyContainer" style="display:flex;flex-direction:column;gap:8px;">
                                            <div class="family-row"
                                                style="display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:8px;align-items:start;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;">
                                                <div>
                                                    <label class="dj-label">Parentesco</label>
                                                    <select name="parentesco[]" class="dj-select">
                                                        <option value="" disabled>—</option>
                                                        @foreach(($tiposVinculo ?? []) as $tv)
                                                        <option value="{{ trim($tv->descripcion) }}">{{ trim($tv->descripcion) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div><label class="dj-label">Apellidos y Nombres</label><input
                                                        type="text" name="apellidosNombres[]" class="dj-input"
                                                        placeholder="Apellidos y nombres completos"></div>
                                                <div class="family-date"><label class="dj-label">Fecha de Nacimiento</label><input
                                                        type="date" name="fechaNacimiento[]" class="dj-input">
                                                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:4px;">
                                                        <span class="family-age" style="display:none;font-size:11px;color:#2563eb;"></span>
                                                        <button type="button" class="btn-dni-hijo dj-btn-sm"
                                                            style="display:none;background:#eef2ff;color:#3730a3;border-radius:5px;">SUBIR DNI</button>
                                                        <span class="dni-hijo-estado" style="display:none;font-size:10px;"></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="dj-label" style="visibility:hidden;">.</label>
                                                    <button type="button" class="remove-family dj-btn-sm dj-btn-danger"
                                                        style="margin-bottom:1px;">Eliminar</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="margin-top:8px;">
                                            <button id="addFamilyMember" type="button"
                                                style="width:100%;padding:6px;font-size:12px;border:1px dashed #d1d5db;border-radius:6px;background:#f9fafb;color:#6b7280;cursor:pointer;">
                                                + Agregar Familiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>{{-- /③ --}}

                    </form>
                </div>{{-- /panel form --}}

            </div>{{-- /split wrapper --}}

            {{-- Footer --}}
            <div
                style="display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #e5e7eb;background:#fafafa;flex-shrink:0;">
                <button id="cerrarModal" type="button" data-hs-overlay="#modalDjGestion"
                    style="padding:7px 18px;font-size:12px;font-weight:600;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#374151;cursor:pointer;">
                    Cerrar
                </button>
                
                <button id="btnPrevisualizar" type="button"
                    style="padding:7px 18px;font-size:12px;font-weight:600;border-radius:6px;background:#64748b;color:#fff;cursor:pointer;border:none;">
                    Previsualizar PDF
                </button>
                @if (session('tipo_rol') != 9 && session('tipo_rol') != 8)
                <button id="btnGuardar" type="submit" form="formDatos" 
                    style="padding:7px 18px;font-size:12px;font-weight:600;border-radius:6px;background:var(--color-primary,#6366f1);color:#fff;cursor:pointer;border:none;">
                    Guardar
                </button>
                @endif
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('familiar_empresa')?.addEventListener('change', function () {
            document.getElementById('div_familiar_interno').classList.toggle('hidden', this.value !== 'SI');
        });
    });
</script>
