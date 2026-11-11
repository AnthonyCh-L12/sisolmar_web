import Swal from 'sweetalert2';
(function () {
    'use strict';

    const $ = id => document.getElementById(id);

    const docInput = $('ndj_nro_documento');
    const tipoDocInput = $('ndj_tipo_documento');
    const btnGuardar = $('ndj_btnGuardar');

    let dniValido = false;
    let coincidenciasValidadas = false;
    let hayCoincidencias = false;
    let reglasEdad = { minima: 18, maxima: 55 };
    let fechaEdadAlertada = '';
    let excepcionEdadValidada = false;
    let fechaExcepcionAutorizada = '';

    // ── Estado de recontratación ─────────────────────────────
    let modoRecontratacion = false;
    let codiPersRecontratacion = null;

    const nombre1 = $('ndj_nombre1');
    const nombre2 = $('ndj_nombre2');
    const apellido1 = $('ndj_apellido_paterno');
    const apellido2 = $('ndj_apellido_materno');
    let coincidenciasDiv = document.createElement('div');
    coincidenciasDiv.style.fontSize = '12px';
    coincidenciasDiv.style.marginTop = '4px';
    if (apellido2?.parentNode) apellido2.parentNode.appendChild(coincidenciasDiv);

    let docErrorMsg = document.createElement('div');
    docErrorMsg.style.color = '#ef4444';
    docErrorMsg.style.fontSize = '12px';
    docErrorMsg.style.marginTop = '2px';
    if (docInput?.parentNode) docInput.parentNode.appendChild(docErrorMsg);

    const alertTipoPersonal = $('ndj_alert_tipo_personal');
    const tipoPersonalSelect = $('ndj_sel_tipo_personal');

    async function ndj_cargarReglasEdad() {
        try {
            const res = await fetch(`${VITE_URL_APP}/api/dj/reglas-edad/`);
            const json = await res.json();
            if (json.success) reglasEdad = json.data;
        } catch (error) {
            console.warn('[NuevaDJ] No se pudieron cargar las reglas de edad:', error);
        }
    }

    function ndj_calcularEdad(fecha) {
        const nacimiento = new Date(`${fecha}T00:00:00`);
        const hoy = new Date();
        let edad = hoy.getFullYear() - nacimiento.getFullYear();
        const aniversario = new Date(hoy.getFullYear(), nacimiento.getMonth(), nacimiento.getDate());
        if (hoy < aniversario) edad--;
        return edad;
    }

    function ndj_fechaCompleta(fecha) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) return false;
        const anio = Number(fecha.slice(0, 4));
        const anioActual = new Date().getFullYear();
        if (anio < 1900 || anio > anioActual) return false;
        const nacimiento = new Date(`${fecha}T00:00:00`);
        return !Number.isNaN(nacimiento.getTime()) && nacimiento.toISOString().slice(0, 10) === fecha;
    }

    function ndj_abrirModalExcepcionEdad() {
        const fechaNacimiento = $('ndj_fecha_nacimiento')?.value;
        const edad = fechaNacimiento && ndj_fechaCompleta(fechaNacimiento)
            ? ndj_calcularEdad(fechaNacimiento)
            : null;

        if (edad === null || (edad >= reglasEdad.minima && edad <= reglasEdad.maxima)) return;

        const modal = $('modalExcepcionEdad');
        if (!modal) return;

        // La cuenta a validar es la de un ADMIN RRHH, que suele ser OTRA persona
        // distinta a la que está registrando la DJ: no se pre-carga nada.
        $('ndj_usuario_excepcion').value = '';
        $('ndj_clave_excepcion').value = '';

        if (window.HSOverlay) HSOverlay.open(modal);
        else modal.classList.remove('hidden');

        setTimeout(() => $('ndj_usuario_excepcion')?.focus(), 100);
    }

    function ndj_cerrarModalExcepcionEdad() {
        const modal = $('modalExcepcionEdad');
        if (!modal) return;

        if (window.HSOverlay) {
            try { HSOverlay.close(modal); } catch (e) { /* cierre manual de respaldo */ }
        } else {
            modal.classList.add('hidden');
            modal.classList.remove('open', 'opened', 'hs-overlay-open');
            document.getElementById(`${modal.id}-backdrop`)?.remove();
        }

        $('ndj_usuario_excepcion').value = '';
        $('ndj_clave_excepcion').value = '';
    }

    function ndj_limpiarEstadoEdad() {
        fechaEdadAlertada = '';
        excepcionEdadValidada = false;
        fechaExcepcionAutorizada = '';

        const fechaNacimiento = $('ndj_fecha_nacimiento');
        fechaNacimiento?.classList.remove(
            'border-red-500', 'bg-red-50', 'ring-red-500',
            'border-green-500', 'bg-green-50', 'ring-green-500', 'ring-1'
        );
        fechaNacimiento?.style.removeProperty('border-color');
        fechaNacimiento?.style.removeProperty('box-shadow');

        $('ndj_alerta_edad')?.remove();
        $('ndj_usuario_excepcion').value = '';
        $('ndj_clave_excepcion').value = '';
    }

    function ndj_marcarEdadInvalida(input, invalida) {
        if (input && !document.getElementById('ndj_alerta_edad')) {
            const alerta = document.createElement('div');
            alerta.id = 'ndj_alerta_edad';
            alerta.style.cssText = 'display:none;align-items:center;gap:8px;margin-top:4px;';

            const mensaje = document.createElement('small');
            mensaje.id = 'ndj_mensaje_edad';
            mensaje.textContent = 'Fecha fuera de rango permitido';
            mensaje.style.cssText = 'color:#ef4444;font-size:12px;';

            const boton = document.createElement('button');
            boton.id = 'ndj_btnRegistrarExcepcionEdad';
            boton.type = 'button';
            boton.textContent = 'Registrar Excepción';
            boton.style.cssText = 'padding:3px 8px;border:1px solid #f59e0b;border-radius:4px;background:#fffbeb;color:#b45309;font-size:11px;font-weight:600;cursor:pointer;';
            boton.addEventListener('click', ndj_abrirModalExcepcionEdad);

            alerta.append(mensaje, boton);
            input.insertAdjacentElement('afterend', alerta);
        }

        input?.classList.toggle('border-red-500', invalida);
        input?.classList.toggle('bg-red-50', invalida);
        input?.classList.toggle('ring-1', invalida);
        input?.classList.toggle('ring-red-500', invalida);
        input?.classList.remove('border-green-500', 'bg-green-50', 'ring-green-500');
        const alerta = document.getElementById('ndj_alerta_edad');
        if (alerta) alerta.style.display = invalida ? 'flex' : 'none';

        const mensaje = $('ndj_mensaje_edad');
        const boton = $('ndj_btnRegistrarExcepcionEdad');
        if (mensaje) {
            mensaje.textContent = 'Fecha fuera de rango permitido';
            mensaje.style.color = '#ef4444';
        }
        if (boton) boton.style.display = '';

        if (input) {
            if (invalida) {
                input.style.setProperty('border-color', '#ef4444', 'important');
                input.style.setProperty('box-shadow', '0 0 0 1px #ef4444', 'important');
            } else {
                input.style.removeProperty('border-color');
                input.style.removeProperty('box-shadow');
            }
        }
    }

    function ndj_marcarEdadAutorizada(input) {
        if (!input) return;

        // Reutiliza el contenedor de alerta y cambia su estado visual a aprobado.
        ndj_marcarEdadInvalida(input, true);

        input.classList.remove('border-red-500', 'bg-red-50', 'ring-red-500');
        input.classList.add('border-green-500', 'bg-green-50', 'ring-1', 'ring-green-500');
        input.style.setProperty('border-color', '#22c55e', 'important');
        input.style.setProperty('box-shadow', '0 0 0 1px #22c55e', 'important');

        const mensaje = $('ndj_mensaje_edad');
        if (mensaje) {
            mensaje.textContent = 'Excepción de edad autorizada';
            mensaje.style.color = '#16a34a';
        }

        const boton = $('ndj_btnRegistrarExcepcionEdad');
        if (boton) boton.style.display = 'none';
    }

    async function ndj_validarEdadNacimiento(input) {
        if (!input?.value) return true;
        if (!ndj_fechaCompleta(input.value)) return true;

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const nacimiento = new Date(`${input.value}T00:00:00`);
        if (nacimiento >= hoy) return true;

        if (!reglasEdad) await ndj_cargarReglasEdad();
        if (!reglasEdad) return true;

        const edad = ndj_calcularEdad(input.value);
        if (edad >= reglasEdad.minima && edad <= reglasEdad.maxima) {
            fechaEdadAlertada = '';
            excepcionEdadValidada = false;
            fechaExcepcionAutorizada = '';
            ndj_marcarEdadInvalida(input, false);
            return true;
        }

        if (excepcionEdadValidada && fechaExcepcionAutorizada === input.value) {
            ndj_marcarEdadAutorizada(input);
            return true;
        }

        if (fechaExcepcionAutorizada !== input.value) {
            excepcionEdadValidada = false;
            fechaExcepcionAutorizada = '';
        }

        ndj_marcarEdadInvalida(input, true);
        if (fechaEdadAlertada === input.value) return false;
        fechaEdadAlertada = input.value;

        await Swal.fire({
            icon: 'warning',
            title: 'Edad fuera del rango permitido',
            text: `La edad calculada es ${edad} años. El rango permitido es de ${reglasEdad.minima} a ${reglasEdad.maxima} años.`,
            confirmButtonText: 'Entendido',
        });
        input.focus();
        return false;
    }

    // ── Categorías brevete ───────────────────────────────────
    const NDJ_CAT = {
        'A': [
            { val: 'A-I',    text: 'A-I: Particulares' },
            { val: 'A-IIa',  text: 'A-IIa: Taxi / Ambulancia' },
            { val: 'A-IIb',  text: 'A-IIb: Microbús / Pickup' },
            { val: 'A-IIIa', text: 'A-IIIa: Ómnibus' },
            { val: 'A-IIIb', text: 'A-IIIb: Camiones' },
            { val: 'A-IIIc', text: 'A-IIIc: Todos los anteriores' },
        ],
        'B': [
            { val: 'B-IIa', text: 'B-IIa: Bicimotos' },
            { val: 'B-IIb', text: 'B-IIb: Motocicletas' },
            { val: 'B-IIc', text: 'B-IIc: Mototaxis' },
        ],
    };

    let ndj_allGrados        = [];
    let ndj_allInstituciones = [];
    let ndj_allCarreras      = [];
    let ndj_allCargos        = [];
    let ndj_paisesData       = [];
    let ndj_catalogoListo    = false;
    let ndj_abriendo         = false;

    const ndj_ubigeoCache = new Map();

    // ============================================================
    // MODO RECONTRATACIÓN
    // ============================================================
    const NDJ_READONLY_IDS = ['ndj_tipo_documento', 'ndj_nro_documento'];
    const NDJ_ALERT_IDS    = [];

    // Códigos fijos de la regla de Dep/Prov/Dist de la card "País de Nacimiento"
    const NDJ_COD_DNI   = '0034';          // si_solm.dbo.TIPO_DOCUMENTO → DNI
    const NDJ_COD_CE    = '0035';          // si_solm.dbo.TIPO_DOCUMENTO → CARNET EXTRANJERIA
    const NDJ_PAIS_PERU = '2007000156';    // si_solm.dbo.ADMI_PAIS → PERU

    // Regla cambio Tipo de Personal (Operativo ↔ Administrativo)
    const NDJ_TIPO_GRUPO_OPERATIVO      = ['01', '03'];
    const NDJ_TIPO_GRUPO_ADMINISTRATIVO = ['02', '05'];
    const NDJ_TIPO_CODIGO_ESPECIALES    = '06';
    let ndj_tiposPersonalCatalogo = [];

    function ndj_poblarTiposPersonal(items) {
        const sel = $('ndj_sel_tipo_personal');
        if (!sel) return;
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        (items || []).forEach(t => {
            const o = document.createElement('option');
            o.value = t.codigo;
            o.textContent = t.nombre;
            sel.appendChild(o);
        });
    }

    // Regla: Operativo (01/03) solo puede cambiar a Administrativo (02/05) y viceversa.
    // Especiales (06) queda deshabilitado sin posibilidad de cambio.
    function ndj_aplicarReglaTipoPersonal(tipotrab) {
        const sel = $('ndj_sel_tipo_personal');
        if (!sel) return;
        const catalogo = ndj_tiposPersonalCatalogo || [];

        if (!tipotrab) { ndj_poblarTiposPersonal(catalogo); return; }

        if (tipotrab === NDJ_TIPO_CODIGO_ESPECIALES) {
            ndj_poblarTiposPersonal(catalogo.filter(t => String(t.codigo).trim() === NDJ_TIPO_CODIGO_ESPECIALES));
            sel.value = tipotrab;
            sel.disabled = true;
            sel.style.opacity = '0.5';
            sel.style.cursor  = 'not-allowed';
            return;
        }

        const esOperativo = NDJ_TIPO_GRUPO_OPERATIVO.includes(tipotrab);
        const esAdmin     = NDJ_TIPO_GRUPO_ADMINISTRATIVO.includes(tipotrab);
        if (!esOperativo && !esAdmin) { ndj_poblarTiposPersonal(catalogo); sel.value = tipotrab; return; }

        const grupoOpuesto = esOperativo ? NDJ_TIPO_GRUPO_ADMINISTRATIVO : NDJ_TIPO_GRUPO_OPERATIVO;
        ndj_poblarTiposPersonal(catalogo.filter(t => {
            const cod = String(t.codigo).trim();
            return cod === tipotrab || grupoOpuesto.includes(cod);
        }));
        sel.value = tipotrab;
    }

    function activarModoRecontratacion(codiPers, tipotrab = '') {
        modoRecontratacion     = true;
        codiPersRecontratacion = codiPers;

        // Fecha de Ingreso a Solmar: VACÍA y obligatoria.
        // Es el NUEVO ingreso: no se hereda la fecha antigua que precarga la carga de datos.
        ndj_setVal('ndj_fecha_ingreso_solmar', '');
        if (btnGuardar) {
            btnGuardar.textContent = 'Recontratar';
            btnGuardar.style.background = '#f59e0b';
        }
        const badge = $('ndj_tipo_badge');
        if (badge) {
            badge.textContent      = 'MODO RECONTRATACIÓN';
            badge.style.display    = 'inline-block';
            badge.style.background = '#fef3c7';
            badge.style.color      = '#92400e';
        }

        NDJ_READONLY_IDS.forEach(id => {
            const el = $(id);
            if (!el) return;
            el.disabled = true;
            el.style.opacity = '0.5';
            el.style.cursor  = 'not-allowed';
        });

        // El select de tipo de personal queda editable con la regla Operativo ↔ Administrativo
        ndj_aplicarReglaTipoPersonal(tipotrab);

        NDJ_ALERT_IDS.forEach(id => {
            const el = $(id);
            if (!el) return;
            el.style.borderColor = '#f59e0b';
            el.style.boxShadow   = '0 0 0 2px rgba(245,158,11,0.2)';
        });

        const grid = $('ndj_filtroSucursal')?.closest('.dj-grid-4');
        if (grid && !document.getElementById('ndj_vigencia_field')) {
            const div = document.createElement('div');
            div.id = 'ndj_vigencia_field';
            div.innerHTML = `
                <label class="dj-label">Vigencia</label>
                <div style="padding:6px 10px;background:#f0fdf4;border:1px solid #86efac;border-radius:5px;color:#15803d;font-weight:600;font-size:13px;line-height:1.6;">SI</div>
            `;
            grid.appendChild(div);
        }
    }

    function desactivarModoRecontratacion() {
        modoRecontratacion     = false;
        codiPersRecontratacion = null;
        if (btnGuardar) {
            btnGuardar.textContent  = 'Guardar';
            btnGuardar.style.background = 'var(--color-primary,#6366f1)';
        }

        NDJ_READONLY_IDS.forEach(id => {
            const el = $(id);
            if (!el) return;
            el.disabled = false;
            el.style.opacity = '';
            el.style.cursor  = '';
        });

        // Restaurar el select de tipo de personal al catálogo completo
        const selTipoRestaurar = $('ndj_sel_tipo_personal');
        if (selTipoRestaurar) {
            selTipoRestaurar.disabled = false;
            selTipoRestaurar.style.opacity = '';
            selTipoRestaurar.style.cursor  = '';
        }
        ndj_poblarTiposPersonal(ndj_tiposPersonalCatalogo);

        NDJ_ALERT_IDS.forEach(id => {
            const el = $(id);
            if (!el) return;
            el.style.borderColor = '';
            el.style.boxShadow   = '';
        });

        document.getElementById('ndj_cese_info')?.remove();
        document.getElementById('ndj_vigencia_field')?.remove();
    }

    // ============================================================
    // AUTOCOMPLETAR FORMULARIO
    // ============================================================
    async function autocompletarDesdePersonal(codiPers) {
        Swal.fire({ title: 'Cargando datos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            if (!ndj_catalogoListo) {
                await Promise.all([
                    ndj_cargarTipoDoc(), ndj_cargarTipoPer(),
                    ndj_cargarEstadoCivil(), ndj_cargarSistemaPrev(),
                    ndj_cargarDepartamentos(), ndj_cargarEducacion(),
                    ndj_cargarCargos(), ndj_cargarPaises(),
                ]);
            }

            const res  = await fetch(`${VITE_URL_APP}/api/dj/get-personal-data?codi_pers=${encodeURIComponent(codiPers)}&source=pendiente`);
            const json = await res.json();

            if (!json.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.message || 'No se pudo obtener los datos.' });
                return;
            }

            const data      = json.data      || {};
            const familiares = json.familiares || {};

            ndj_setVal('ndj_cod_postulante',       data.CODI_PERS            || '');
            ndj_setVal('ndj_tipo_documento',        data.CODI_TIPO_DOCU?.trim() || '0034');
            ndj_setVal('ndj_nro_documento',         data.NRO_DOCU_IDEN?.trim() || '');
            $('ndj_nro_documento').disabled = false;
            ndj_setVal('ndj_nombre1',               data.NOMB_1?.trim()        || '');
            ndj_setVal('ndj_nombre2',               data.NOMB_2?.trim()        || '');
            ndj_setVal('ndj_apellido_paterno',      data.APEL_1?.trim()        || '');
            ndj_setVal('ndj_apellido_materno',      data.APEL_2?.trim()        || '');
            ndj_setVal('ndj_caduca',                ndj_fmtDate(data.PERS_FECHCADUCADNI));
            ndj_setVal('ndj_estado_civil',          data.ESCI_CODIGO?.trim()   || '');
            ndj_setVal('ndj_sexo',                  data.PERS_SEXO?.trim()     || data.SEXO?.trim() || '');
            ndj_setVal('ndj_fecha_nacimiento',      ndj_fmtDate(data.FECH_NACI));
            ndj_setVal('ndj_fecha_ingreso_solmar',  ndj_fmtDate(data.FECH_INGRE));
            ndj_setVal('ndj_celular',               data.PERS_TELEFONO?.trim() || '');
            ndj_setVal('ndj_correo',                data.PERS_EMAIL?.trim()    || '');
            ndj_setVal('ndj_whatsapp',              data.PERS_WHATSAPP?.trim() || '');
            ndj_setVal('ndj_tipo_sangre',           data.tipo_sangr?.trim()    || '');
            ndj_setVal('ndj_peso',                  data.peso_kilo?.trim()     || '');
            ndj_setVal('ndj_talla',                 data.tall_metr?.trim()     || '');
            ndj_setVal('ndj_sistema_previsional',   data.CODI_SIST_PENS?.trim()|| '');
            ndj_setVal('ndj_essalud',               data.ESSALUD?.trim()       || '');
            ndj_setVal('ndj_pensionista',           data.PERS_PENSIONISTA?.trim() || '');
            ndj_setVal('ndj_grado_instruccion',     data.PERS_GRADO_INSTRUCCION?.trim() || '');
            ndj_setVal('ndj_anio_egreso',           data.EGRESO_EDUCATIVO?.trim() || '');
            ndj_setVal('ndj_embargos',              data.PERS_EMBARGO?.trim()  || '');
            ndj_setVal('ndj_cuenta_banco',          data.dj2026_banco?.trim()  || '');
            ndj_setVal('ndj_direccion_actual',      data.DIRECCION?.trim()     || '');
            ndj_setVal('ndj_direccion_dni',         data.PERS_DIREC_DNI?.trim()|| '');
            ndj_setVal('ndj_tipo_zona',            data.TIZO_CODIGO?.trim()       || '');
            ndj_setVal('ndj_zona_dirdni',          data.PERS_ZONA_DIRDNI?.trim()  || '');
            ndj_setVal('ndj_contacto_emergencia',   data.PERS_NOMCONTACTO?.trim()    || '');
            ndj_setVal('ndj_celular_emergencia',    data.PERS_NROEMERGENCIA?.trim()  || '');
            ndj_setVal('ndj_parentesco_emergencia', data.PERS_EMERC_FAMILIAR?.trim() || '');
            ndj_setVal('ndj_ocupacion_principal',   data.dj2026_ocupacion_principal?.trim() || '');
            ndj_setVal('ndj_experiencia_anios',     data.dj2026_experiencia_anios !== null && data.dj2026_experiencia_anios !== undefined ? String(data.dj2026_experiencia_anios).replace(/[^0-9]/g,'') : '');
            ndj_setVal('ndj_experiencia_meses',     data.dj2026_experiencia_meses !== null && data.dj2026_experiencia_meses !== undefined ? String(data.dj2026_experiencia_meses).replace(/[^0-9]/g,'') : '');
            ndj_setVal('ndj_familiar_empresa',      data.dj2026_familiar_empresa?.trim()    || '');
            ndj_setVal('ndj_familiar_nombre',       data.dj2026_familiar_nombre?.trim()     || '');
            ndj_setVal('ndj_familiar_parentesco',   data.dj2026_familiar_parentesco?.trim() || '');
            ndj_setVal('ndj_curso_sucamec',         data.PERS_CONDISCAMEC?.trim() || '');
            ndj_setVal('ndj_presto_smo',            data.PERS_CONSMO?.trim()     || '');
            ndj_setVal('ndj_lugar_smo',             data.PERS_LUGARSMO?.trim()   || '');
            const ndjPrestoSmo = document.getElementById('ndj_presto_smo');
            const ndjLugarSmo = document.getElementById('ndj_lugar_smo');
            if (ndjPrestoSmo && ndjLugarSmo) {
                ndjLugarSmo.disabled = ndjPrestoSmo.value !== 'SI';
                if (ndjLugarSmo.disabled) ndjLugarSmo.value = '';
            }
            ndj_setVal('ndj_licencia_arma',         data.PERS_NROLICENCIA?.trim() || '');
            ndj_setVal('ndj_arma_propia',           data.PERS_CONARMAS?.trim()    || '');
            ndj_setVal('ndj_brevete',               data.PERS_BREVETE?.trim()     || '');
            ndj_setVal('ndj_vehiculo_propio',       data.PERS_VEHICULO_PROPIO?.trim() || '');
            ndj_setVal('ndj_empresa_anterior',      data.PERS_CTRABANT?.trim()    || '');
            ndj_setVal('ndj_cargo_anterior',        data.PERS_CARGOTRABANT?.trim()|| '');
            ndj_setVal('ndj_duracion_anterior',     data.PERS_DURACIONANT?.trim() || '');
            ndj_setVal('ndj_laboral_1',             data.dj2026_laboral_1?.trim() || '');
            ndj_setVal('ndj_laboral_2',             data.dj2026_laboral_2?.trim() || '');
            ndj_setVal('ndj_filtroSucursal',        data.SUCU_CODIGO?.trim()      || '');

            const tipotrab = data.PERS_TIPOTRAB?.trim() || '';
            ndj_setVal('ndj_sel_tipo_personal', tipotrab);
            ndj_setVal('ndj_tipo_personal',     tipotrab);
            ndj_aplicarTipo(tipotrab);
            ndj_aplicarSctr(tipotrab, data.SCRT ?? null);
            // Llenar el select de Cargo filtrado por el tipo cargado (antes solo se
            // llenaba al cambiar el tipo manualmente) y preseleccionar su cargo actual
            await ndj_filtrarCargos(tipotrab);
            ndj_setVal('ndj_sel_cargo', data.CODI_CARG?.trim() || '');
            ndj_bloquearCampos(false);

            if (data.CLASE_BREVETE) {
                ndj_setVal('ndj_clase_brevete', data.CLASE_BREVETE.trim());
                const selTipo = $('ndj_tipo_vehiculo');
                if (selTipo) {
                    selTipo.innerHTML = '<option value="">-- Seleccione --</option>';
                    (NDJ_CAT[data.CLASE_BREVETE.trim()] || []).forEach(item => {
                        const o = document.createElement('option');
                        o.value = item.val; o.textContent = item.text;
                        selTipo.appendChild(o);
                    });
                    ndj_setVal('ndj_tipo_vehiculo', data.CATEGORIA_BREVETE?.trim() || '');
                }
            }

            if (data.IEDU_CODIGO) {
                ndj_setVal('ndj_institucion', data.IEDU_CODIGO.trim());
                ndj_poblarCarreras(data.IEDU_CODIGO.trim());
                await new Promise(r => setTimeout(r, 80));
                ndj_setVal('ndj_carrera', data.CARR_CODIGO?.trim() || '999999');
            }

            if (data.dj2026_familiar_empresa === 'SI') $('ndj_div_familiar_interno')?.classList.remove('hidden');

            await ndj_cargarUbigeosCascada('ndj_departamento_actual','ndj_provincia_actual','ndj_distrito_actual', data.PERS_DEPT_ACT?.trim(),   data.PERS_PROV_ACT?.trim(),    data.PERS_DIST_ACT?.trim());
            await ndj_cargarUbigeosCascada('ndj_departamento_dni',   'ndj_provincia_dni',   'ndj_distrito_dni',    data.PERS_DPTO_DIRDNI?.trim(), data.PERS_PROV_DIRDNI?.trim(), data.PERS_DIST_DIRDNI?.trim());
            await ndj_cargarUbigeosCascada('ndj_departamento_nac',   'ndj_provincia_nac',   'ndj_distrito_nac',    data.DEPA_CODIGO_NACI?.trim(), data.PROVI_CODIGO_NACI?.trim(),data.DIST_NACI?.trim());
            await ndj_setPais(data.NACIONALIDAD?.trim() || '');
            ndj_toggleUbigeoNacimiento(false);

            const fc = $('ndj_familyContainer');
            if (fc) {
                fc.innerHTML = '';
                const allFam = [...(familiares.padres||[]),...(familiares.madre||[]),...(familiares.hijos||[]),...(familiares.conyugue||[]),...(familiares.otros||[])];
                if (allFam.length === 0) fc.appendChild(ndj_crearFila());
                else allFam.forEach(f => fc.appendChild(ndj_crearFilaConDatos(f)));
                // Consultar qué DNI de hijos ya existen (para no volverlos a pedir)
                await ndj_consultarDniHijos((data.CODI_PERS || $('ndj_cod_postulante')?.value || '').trim());
            }

            if (data.FOTO_PATH) {
                const prv = $('ndj_previewFoto'), ph = $('ndj_placeholderFoto');
                if (prv) {
                    // FOTO_PATH es una URL que se arma SIEMPRE, exista o no la imagen.
                    // Si el servidor de archivos no la tiene (404) volvemos al
                    // placeholder: así el usuario ve que DEBE subirla y no se
                    // encuentra con un 422 "falta_foto" habiendo visto una foto.
                    prv.onerror = () => {
                        prv.removeAttribute('src');
                        prv.classList.add('hidden');
                        ph?.querySelector('i')?.classList.remove('hidden');
                        ph?.querySelector('span')?.classList.remove('hidden');
                        $('ndj_btnEliminarFoto')?.classList.add('hidden');
                    };
                    prv.src = data.FOTO_PATH + '?v=' + (Math.floor(Math.random() * 900) + 100);
                    prv.classList.remove('hidden');
                }
                if (ph)  ph.classList.add('hidden');
                $('ndj_btnEliminarFoto')?.classList.remove('hidden');
            }

            Swal.close();
            ndj_mostrarCeseInfo(data.OBS_CESE, data.FECH_CESE);
            activarModoRecontratacion(codiPers, tipotrab);
            dniValido              = true;
            coincidenciasValidadas = true;
            actualizarEstadoGuardar();

        } catch (err) {
            console.error('[NuevaDJ] Error autocompletando:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los datos del personal.' });
        }
    }

    // ── Ubigeo cascada ───────────────────────────────────────
    async function ndj_cargarUbigeosCascada(idDept, idProv, idDist, dept, prov, dist) {
        if (!dept) return;
        ndj_setVal(idDept, dept);
        if (!prov) return;
        await ndj_cargarProvincias(dept, idProv, idDist);
        ndj_setVal(idProv, prov);
        if (!dist) return;
        await ndj_cargarDistritos(prov, idDist);
        ndj_setVal(idDist, dist);
    }

    // ── Fila familiar con datos ──────────────────────────────
    function ndj_crearFilaConDatos(f = {}) {
        let fechaFormateada = '';
        if (f.FECH_NACI) {
            const s = String(f.FECH_NACI);
            fechaFormateada = (s.length >= 8 && !s.includes('-') && !s.includes('/'))
                ? `${s.substring(0,4)}-${s.substring(4,6)}-${s.substring(6,8)}`
                : ndj_fmtDate(s);
        }
        const div    = ndj_crearFila();
        const selPar = div.querySelector('select[name="ndj_parentesco[]"]');
        const inpNom = div.querySelector('input[name="ndj_apellidosNombres[]"]');
        const inpFec = div.querySelector('input[name="ndj_fechaNacimiento[]"]');
        const inpId  = div.querySelector('input[name="ndj_familiar_id[]"]');
        if (selPar) {
            const vPar = (f.TIPO_RELA || '').trim();
            if (vPar && ![...selPar.options].some(o => o.value === vPar)) selPar.add(new Option(vPar, vPar));
            selPar.value = vPar;
        }
        if (inpNom) inpNom.value = f.Nombres   || '';
        if (inpFec) inpFec.value = fechaFormateada;
        if (inpId) inpId.value = f.CODI_DERE_HABI || '';
        ndj_actualizarFechaFamiliar(div);
        return div;
    }

    function ndj_setVal(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        const v = value || '';
        // Si es select y el valor guardado no está en las opciones (dato legado), añadirlo
        if (el.tagName === 'SELECT' && v && ![...el.options].some(o => o.value === v)) {
            el.add(new Option(v, v));
        }
        el.value = v;
    }

    function ndj_fmtDate(val) {
        if (!val) return '';
        if (typeof val === 'string' && val.includes('T')) return val.split('T')[0];
        if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
        if (typeof val === 'string' && val.includes(' ')) return val.split(' ')[0];
        if (typeof val === 'string' && /^\d{2}[-/]\d{2}[-/]\d{4}$/.test(val)) {
            const [d, m, y] = val.split(/[-/]/);
            return `${y}-${m}-${d}`;
        }
        return '';
    }

    // ============================================================
    // BANNER INFO CESE
    // ============================================================
    function ndj_mostrarCeseInfo(obsCese, fechCese) {
        document.getElementById('ndj_cese_info')?.remove();
        const ancla = $('ndj_alert_tipo_personal');
        if (!ancla) return;
        const fechaRaw = fechCese ? ndj_fmtDate(String(fechCese)) : null;
        const fecha = fechaRaw ? fechaRaw.split('-').reverse().join('/') : 'Sin registro';
        const obs   = obsCese  ? String(obsCese).trim()               : 'Sin registro';
        const div = document.createElement('div');
        div.id = 'ndj_cese_info';
        div.style.cssText = 'background:#fff7ed;border:1px solid #fdba74;border-radius:6px;padding:10px 14px;margin-bottom:10px;font-size:12.5px;color:#7c2d12;display:block;';
        div.innerHTML =
            '<span style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">📋 Información de cese</span>' +
            '<div style="display:flex;gap:24px;margin-top:6px;flex-wrap:wrap;">' +
                '<span><strong>Fecha de cese:</strong> ' + fecha + '</span>' +
                '<span><strong>Motivo:</strong> ' + obs + '</span>' +
            '</div>';
        ancla.parentElement.insertBefore(div, ancla);
    }

    // ============================================================
    // ABRIR EN MODO RECONTRATACIÓN (desde fila inactiva)
    // ============================================================
    async function ndj_abrirRecontratacion(codiPers) {
        if (!codiPers) return;
        const modal = $('modalNuevaDJ');
        if (!modal) return;

        ndj_reset();

        if (window.HSOverlay) HSOverlay.open(modal);
        else modal.classList.remove('hidden');

        await autocompletarDesdePersonal(codiPers);
    }

    // ============================================================
    // ABRIR
    // ============================================================
    async function ndj_abrir() {
        if (ndj_abriendo) return;
        ndj_abriendo = true;

        if (alertTipoPersonal) alertTipoPersonal.style.display = 'block';

        const modal = $('modalNuevaDJ');
        if (!modal) { ndj_abriendo = false; return; }

        ndj_reset();

        const necesitaCarga = !ndj_catalogoListo;
        if (necesitaCarga && typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Preparando formulario...',
                html: '<span style="font-size:13px;color:#6b7280;">Cargando catálogos y ubigeos</span>',
                allowOutsideClick: false, allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });
        }

        try {
            if (necesitaCarga) {
                await Promise.all([
                    ndj_cargarTipoDoc(), ndj_cargarTipoPer(),
                    ndj_cargarEstadoCivil(), ndj_cargarSistemaPrev(),
                    ndj_cargarDepartamentos(), ndj_cargarEducacion(),
                    ndj_cargarCargos(), ndj_cargarPaises(),
                ]);
            }
        } catch (e) {
            console.error('[NuevaDJ] Error al cargar catálogos:', e);
        } finally {
            if (necesitaCarga && typeof Swal !== 'undefined') {
                Swal.close();
                await new Promise(r => setTimeout(r, 80));
            }
            ndj_abriendo = false;
        }

        if (window.HSOverlay) HSOverlay.open(modal);
        else modal.classList.remove('hidden');
    }

    function actualizarEstadoGuardar() {
        if (dniValido && (coincidenciasValidadas || !hayCoincidencias)) {
            btnGuardar.disabled      = false;
            btnGuardar.style.display = 'block';
        } else {
            btnGuardar.disabled      = true;
            btnGuardar.style.display = 'none';
        }
    }

    async function buscarCoincidencias() {
        const n1 = nombre1.value.trim(), n2 = nombre2.value.trim();
        const a1 = apellido1.value.trim(), a2 = apellido2.value.trim();

        if ((!n1 && !n2 && !a1 && !a2) || (n1.length === 0 || a1.length === 0)) {
            coincidenciasDiv.innerHTML = '';
            hayCoincidencias = false; coincidenciasValidadas = true;
            actualizarEstadoGuardar(); return;
        }

        try {
            const res  = await fetch(`${VITE_URL_APP}/api/dj/buscar-coincidencias?nomb1=${encodeURIComponent(n1)}&nomb2=${encodeURIComponent(n2)}&apel1=${encodeURIComponent(a1)}&apel2=${encodeURIComponent(a2)}`);
            const data = await res.json();

            if (data.data.length === 0) {
                hayCoincidencias = false; coincidenciasValidadas = true;
                coincidenciasDiv.innerHTML = ''; actualizarEstadoGuardar(); return;
            }

            hayCoincidencias = true; coincidenciasValidadas = false;
            coincidenciasDiv.innerHTML = `
                <b>Coincidencias encontradas:</b>
                <ul style="list-style:none;padding:0;">
                    ${data.data.map((p, idx) => `
                        <li style="padding-bottom:8px;">
                            ${p.NOMB_1} ${p.NOMB_2} ${p.APEL_1} ${p.APEL_2} - ${p.NRO_DOCU_IDEN}<br>
                            <span class="text-danger">${p.PERS_VIGENCIA != 'SI' ? '(NO VIGENTE)' : ''}</span>
                            ${idx < data.data.length - 1 ? '<hr style="margin:8px 0;border:0;border-top:1px solid #e5e7eb;">' : ''}
                        </li>`).join('')}
                </ul>
                <button id="ndj_btnEntendido" type="button" style="margin-top:8px;padding:6px 18px;font-size:13px;font-weight:600;border-radius:6px;background:#fbbf24;color:#fff;cursor:pointer;border:none;">
                    Entendido, deseo continuar
                </button>`;

            document.getElementById('ndj_btnEntendido')?.addEventListener('click', function () {
                coincidenciasValidadas = true;
                coincidenciasDiv.innerHTML = '';
                actualizarEstadoGuardar();
            });
            actualizarEstadoGuardar();

        } catch (e) {
            coincidenciasDiv.innerHTML = 'Error buscando coincidencias.';
            hayCoincidencias = false; coincidenciasValidadas = false;
            actualizarEstadoGuardar();
        }
    }

    function ndj_bloquearCampos(bloquear) {
        document.querySelectorAll('#modalNuevaDJ input:not(#ndj_filtroSucursal):not(#ndj_tipo_personal):not(#ndj_usuario):not(#ndj_cod_postulante), #modalNuevaDJ select:not(#ndj_filtroSucursal):not(#ndj_tipo_personal), #modalNuevaDJ textarea')
            .forEach(el => el.disabled = bloquear);
        // btnGuardar solo existe donde se incluye el modal (Gestión DJ).
        // En Actualizar DJ este archivo se carga sin el modal → botón null.
        if (btnGuardar) {
            btnGuardar.disabled = bloquear;
            if (bloquear) btnGuardar.style.display = 'none';
        }
        const _lugar = $('ndj_lugar_smo');
        const _presto = $('ndj_presto_smo');
        if (!bloquear && _lugar && _presto) {
            _lugar.disabled = _presto.value !== 'SI';
        }
    }

    // ============================================================
    // CERRAR
    // ============================================================
    function ndj_cerrar() {
        ndj_abriendo = false;
        const modal = $('modalNuevaDJ');
        if (!modal) return;
        if (window.HSOverlay) { try { HSOverlay.close(modal); } catch (e) { } }
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('hs-overlay-open');
            document.querySelectorAll('.hs-overlay-backdrop').forEach(el => el.remove());
            document.body.classList.remove('overflow-hidden');
            document.body.style.overflow = '';
            ndj_reset();
        }, 300);
    }

    // ============================================================
    // RESET
    // ============================================================
    function ndj_reset() {
        $('formNuevaDJ')?.reset();
        ndj_limpiarEstadoEdad();
        desactivarModoRecontratacion();
        dniValido = false; coincidenciasValidadas = false; hayCoincidencias = false;
        docErrorMsg.innerHTML = ''; coincidenciasDiv.innerHTML = '';

        // Reset No Caduca checkbox y restore caduca
        const caducaInput = $('ndj_caduca');
        const noCaduca = $('ndj_no_caduca');
        if (noCaduca) noCaduca.checked = false;
        if (caducaInput) {
            caducaInput.disabled = false;
            caducaInput.value = '';
            caducaInput.style.background = '';
            caducaInput.style.color = '';
        }

        // Reset Nro Documento: deshabilitado hasta que seleccione tipo doc
        const nroDoc = $('ndj_nro_documento');
        if (nroDoc) { nroDoc.disabled = true; nroDoc.value = ''; nroDoc.placeholder = 'Seleccione tipo de documento primero'; }

        // Reset S.M.O. selects
        const ndjPrestoSmo = $('ndj_presto_smo');
        const ndjLugarSmo = $('ndj_lugar_smo');
        if (ndjPrestoSmo) ndjPrestoSmo.value = '';
        if (ndjLugarSmo) {
            ndjLugarSmo.disabled = true;
            ndjLugarSmo.value = '';
        }

        const badge = $('ndj_tipo_badge');
        if (badge) badge.style.display = 'none';

        document.querySelectorAll('#modalNuevaDJ [data-ndj-tipo]').forEach(el => { el.style.display = ''; });
        $('ndj_div_familiar_interno')?.classList.add('hidden');

        // Reset búsqueda país
        const paisInput = $('ndj_pais');
        if (paisInput) paisInput.value = '';

        ndj_limpiarFoto();

        const cargoSel = $('ndj_sel_cargo');
        if (cargoSel) cargoSel.innerHTML = '<option value="">— Seleccionar —</option>';

        const tipoUi = $('ndj_sel_tipo_personal');
        if (tipoUi) {
            tipoUi.value = '';
            tipoUi.disabled = false;
            tipoUi.style.background = '';
            tipoUi.style.color = '';
        }
        ndj_aplicarSctr('');
        ndj_limpiarDni();
        ndj_limpiarDniHijos();

        ['ndj_provincia_actual','ndj_distrito_actual','ndj_provincia_dni','ndj_distrito_dni','ndj_provincia_nac','ndj_distrito_nac']
            .forEach(id => { const s = $(id); if (s) s.innerHTML = '<option value="">—</option>'; });

        // Restaurar visibilidad de ubigeo de nacimiento
        ['ndj_wrap_departamento_nac', 'ndj_wrap_provincia_nac', 'ndj_wrap_distrito_nac']
            .forEach(id => { const w = $(id); if (w) w.style.display = ''; });

        const fc = $('ndj_familyContainer');
        if (fc) { fc.innerHTML = ''; fc.appendChild(ndj_crearFila()); }
    }

    // ============================================================
    // CATÁLOGOS
    // ============================================================
    async function ndj_fetchSelect(selectId, url, valorKey, textoKey, placeholder = '— Seleccionar —') {
        const sel = $(selectId);
        if (!sel) return [];
        sel.innerHTML = `<option value="">Cargando...</option>`;
        sel.disabled  = true;
        let items = [];
        try {
            const res   = await fetch(url);
            const json  = await res.json();
            items = Array.isArray(json) ? json : (json.data ?? []);
            sel.innerHTML = `<option value="">${placeholder}</option>`;
            items.forEach(item => { const o = document.createElement('option'); o.value = item[valorKey]; o.textContent = item[textoKey]; sel.appendChild(o); });
        } catch (err) {
            console.error(`[NuevaDJ] Error cargando ${selectId}:`, err);
            sel.innerHTML = `<option value="">Error al cargar</option>`;
        } finally { sel.disabled = false; }
        return items;
    }

    function ndj_cargarTipoDoc()    { return ndj_fetchSelect('ndj_tipo_documento',      `${VITE_URL_APP}/api/dj/get-tipo-doc/`,     'codigo','nombre'); }
    async function ndj_cargarTipoPer() {
        const items = await ndj_fetchSelect('ndj_sel_tipo_personal', `${VITE_URL_APP}/api/dj/get-tipo-per/`, 'codigo','nombre');
        ndj_tiposPersonalCatalogo = items;
        return items;
    }
    function ndj_cargarEstadoCivil(){ return ndj_fetchSelect('ndj_estado_civil',        `${VITE_URL_APP}/api/dj/get-estado-civil/`, 'codigo','nombre'); }
    function ndj_cargarSistemaPrev(){ return ndj_fetchSelect('ndj_sistema_previsional', `${VITE_URL_APP}/api/dj/get-sistema-prev/`, 'codigo','nombre'); }

    async function ndj_cargarPaises() {
        const sel = $('ndj_pais');
        if (!sel) return;
        try {
            const res = await fetch(`${VITE_URL_APP}/api/dj/get-paises/`);
            const json = await res.json();
            const items = json.paises ?? [];
            ndj_paisesData = items;
            sel.innerHTML = '<option value="">— Seleccionar —</option>';
            items.forEach(item => {
                const o = document.createElement('option');
                o.value = item.id;
                o.textContent = item.text;
                sel.appendChild(o);
            });
        } catch (err) {
            console.error('[NuevaDJ] Error cargando países:', err);
        }
    }

    async function ndj_setPais(codigo) {
        const sel = $('ndj_pais');
        if (!sel) return;
        // Autoguarantía: si el catálogo aún no cargó (p. ej. modo recontratación), cargarlo primero
        if (!ndj_paisesData.length) await ndj_cargarPaises();
        sel.value = codigo || '';
    }

    // ── Regla de Dep/Prov/Dist de la card "País de Nacimiento" ────────────
    // Se ocultan si:
    //   • Tipo de documento = Carnet de Extranjería (0035), o
    //   • Tipo de documento = DNI (0034) y el País de Nacimiento NO es PERU.
    // Como quedan ocultos, la validación de camposReq los salta sola
    // (offsetParent === null) → dejan de ser obligatorios.
    //
    // limpiarCampos = true solo cuando el usuario cambia el select manualmente
    // (no limpiar al cargar datos existentes para no perder info guardada).
    function ndj_toggleUbigeoNacimiento(limpiarCampos = false) {
        const tipoDoc = String($('ndj_tipo_documento')?.value ?? '').trim();
        const pais    = String($('ndj_pais')?.value ?? '').trim();

        const esExtranjeria   = tipoDoc === NDJ_COD_CE;
        const esDniExtranjero = tipoDoc === NDJ_COD_DNI && pais !== '' && pais !== NDJ_PAIS_PERU;
        const ocultar         = esExtranjeria || esDniExtranjero;

        ['ndj_wrap_departamento_nac', 'ndj_wrap_provincia_nac', 'ndj_wrap_distrito_nac'].forEach(id => {
            const wrap = $(id);
            if (wrap) wrap.style.display = ocultar ? 'none' : '';
        });
        if (ocultar && limpiarCampos) {
            ['ndj_departamento_nac', 'ndj_provincia_nac', 'ndj_distrito_nac'].forEach(id => {
                const s = $(id);
                if (s) s.value = '';
            });
        }
        // Apellido Materno: OPCIONAL para extranjeros (hay personal con 1 solo apellido).
        // Se guarda como NULL si se deja vacío.
        const apMat = $('ndj_apellido_materno');
        if (apMat) {
            apMat.placeholder = esExtranjeria ? 'Apellido materno (opcional)' : 'Apellido materno';
        }
    }

    async function ndj_cargarCargos() {
        try {
            const res  = await fetch(`${VITE_URL_APP}/api/dj/get-cargos-dj`);
            const json = await res.json();
            ndj_allCargos = Array.isArray(json) ? json : (json.data ?? []);
        } catch (e) {
            console.error('[NuevaDJ] Error cargando cargos:', e);
        }
    }

    async function ndj_filtrarCargos(tipoPersonal) {
        const sel = $('ndj_sel_cargo');
        if (!sel) return;
        // Autoguarantía: si el catálogo de cargos no llegó, cargarlo ahora
        if (!ndj_allCargos || !ndj_allCargos.length) await ndj_cargarCargos();
        const operativos = ['01', '03', '06'];
        const admin      = ['02', '05'];
        const cargoTipo = operativos.includes(tipoPersonal) ? '01'
                        : admin.includes(tipoPersonal)      ? '02'
                        : null;
        sel.innerHTML = '<option value="">— Seleccionar —</option>';
        if (!cargoTipo) return;
        ndj_allCargos
            .filter(c => String(c.tipo ?? '').trim() === cargoTipo)
            .forEach(c => {
                const o = document.createElement('option');
                o.value = c.codigo;
                o.textContent = c.nombre;
                sel.appendChild(o);
            });
    }

    // ============================================================
    // EDUCACIÓN
    // ============================================================
    async function ndj_cargarEducacion() {
        if (ndj_catalogoListo) { ndj_poblarGrados(); ndj_poblarInstituciones(); ndj_poblarCarreras(''); return; }
        try {
            const res  = await fetch(`${VITE_URL_APP}/api/dj/get-catalogs`);
            const data = await res.json();
            ndj_allGrados        = data.grados        || [];
            ndj_allInstituciones = data.instituciones || [];
            ndj_allCarreras      = data.carreras      || [];
            ndj_poblarGrados(); ndj_poblarInstituciones(); ndj_poblarCarreras('');
            ndj_catalogoListo = true;
        } catch (e) { console.error('[NuevaDJ] Error catálogos educación:', e); }
    }

    function ndj_poblarGrados() {
        const sel = $('ndj_grado_instruccion'); if (!sel) return;
        sel.innerHTML = '<option value="" disabled selected>—</option>';
        ndj_allGrados.forEach(g => { const o = document.createElement('option'); o.value = g.id; o.textContent = g.text; sel.appendChild(o); });
    }
    function ndj_poblarInstituciones() {
        const sel = $('ndj_institucion'); if (!sel) return;
        sel.innerHTML = '<option value="" selected>—</option>';
        ndj_allInstituciones.forEach(i => { const o = document.createElement('option'); o.value = i.id; o.textContent = i.text; sel.appendChild(o); });
    }
    function ndj_poblarCarreras(ieduCodigo) {
        const sel = $('ndj_carrera'); if (!sel) return;
        sel.innerHTML = '<option value="999999">NO ESPECIFICA</option>';
        const lista = ieduCodigo ? ndj_allCarreras.filter(c => c.IEDU_CODIGO === ieduCodigo) : ndj_allCarreras;
        lista.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.text; sel.appendChild(o); });
    }

    // ============================================================
    // UBIGEOS
    // ============================================================
    const NDJ_UBI = `${VITE_URL_APP}/api/ubicacion`;

    async function ndj_fetchUbi(url) {
        if (ndj_ubigeoCache.has(url)) return ndj_ubigeoCache.get(url);
        const res = await fetch(url); const data = await res.json();
        ndj_ubigeoCache.set(url, data); return data;
    }
    function ndj_poblarUbiSelect(id, items, valKey, txtKey, ph = '—') {
        const sel = $(id); if (!sel) return;
        sel.innerHTML = `<option value="">${ph}</option>`;
        items.forEach(item => { const o = document.createElement('option'); o.value = item[valKey]; o.textContent = item[txtKey]; sel.appendChild(o); });
    }
    async function ndj_cargarDepartamentos() {
        try {
            const data = await ndj_fetchUbi(`${NDJ_UBI}/departamentos`);
            ['ndj_departamento_actual','ndj_departamento_dni','ndj_departamento_nac']
                .forEach(id => ndj_poblarUbiSelect(id, data, 'depa_codigo', 'depa_descripcion', '— Departamento —'));
        } catch (e) { console.error('[NuevaDJ] Error departamentos:', e); }
    }
    async function ndj_cargarProvincias(deptCod, provId, distId) {
        const sp = $(provId), sd = $(distId);
        if (sp) sp.innerHTML = '<option value="">— Provincia —</option>';
        if (sd) sd.innerHTML = '<option value="">— Distrito —</option>';
        if (!deptCod) return;
        try {
            const data = await ndj_fetchUbi(`${NDJ_UBI}/provincias/${deptCod}`);
            ndj_poblarUbiSelect(provId, data, 'provi_codigo', 'provi_descripcion', '— Provincia —');
        } catch (e) { console.error('[NuevaDJ] Error provincias:', e); }
    }
    async function ndj_cargarDistritos(provCod, distId) {
        const sd = $(distId);
        if (sd) sd.innerHTML = '<option value="">— Distrito —</option>';
        if (!provCod) return;
        try {
            const data = await ndj_fetchUbi(`${NDJ_UBI}/distritos/${provCod}`);
            ndj_poblarUbiSelect(distId, data, 'dist_codigo', 'dist_descripcion', '— Distrito —');
        } catch (e) { console.error('[NuevaDJ] Error distritos:', e); }
    }

    // ============================================================
    // VISIBILIDAD POR TIPO
    // ============================================================
    // ── SCTR: visible solo para Administrativo (02/05) ─────────
    // OP (01/03)  → sin checkbox, SCTR='SI' automático (backend)
    // ADMIN       → checkbox "SCTR": marcado='SI', sin marcar='NO'
    let ndjSctrTipoAnterior = '';
    function ndj_aplicarSctr(tipoCod, scrt = null) {
        const wrap = $('ndj_wrap_sctr');
        const chk  = $('ndj_autorizar_sctr');
        if (!wrap || !chk) return;
        const tipo     = String(tipoCod || '').trim();
        const esAdmin  = ['02', '05'].includes(tipo);
        const eraAdmin = ['02', '05'].includes(ndjSctrTipoAnterior);
        wrap.style.display = esAdmin ? '' : 'none';
        if (!esAdmin) {
            chk.checked = false;
        } else if (scrt !== null && scrt !== undefined) {
            chk.checked = ['SI', '1'].includes(String(scrt).trim().toUpperCase());
        } else if (!eraAdmin) {
            chk.checked = false; // op → admin: aparece sin marcar
        }
        ndjSctrTipoAnterior = tipo;
    }

    function ndj_aplicarTipo(tipoCod) {
        const el = $('ndj_tipo_personal');
        if (el) el.value = tipoCod;
        const esOpe = ['01','03'].includes(tipoCod);
        const esAdm = ['02','05','06'].includes(tipoCod);
        document.querySelectorAll('#modalNuevaDJ [data-ndj-tipo="operativo"]').forEach(s => { s.style.display = esAdm ? 'none' : ''; });
        document.querySelectorAll('#modalNuevaDJ [data-ndj-tipo="administrativo"]').forEach(s => { s.style.display = esOpe ? 'none' : ''; });
        if (modoRecontratacion) return;
        const badge = $('ndj_tipo_badge'); if (!badge) return;
        const cfg = {
            '01':{ texto:'Operativo 4°',     bg:'#dbeafe', color:'#1e40af' },
            '03':{ texto:'Operativo 5°',      bg:'#dbeafe', color:'#1e40af' },
            '02':{ texto:'Administrativo 4°', bg:'#d1fae5', color:'#065f46' },
            '05':{ texto:'Administrativo 5°', bg:'#d1fae5', color:'#065f46' },
            '06':{ texto:'Especial',           bg:'#fef3c7', color:'#92400e' },
        };
        const c = cfg[tipoCod];
        if (c) { badge.textContent = c.texto; badge.style.display = 'inline-block'; badge.style.background = c.bg; badge.style.color = c.color; }
        else   { badge.style.display = 'none'; }
    }

    // ============================================================
    // FOTO
    // ============================================================
    function ndj_limpiarFoto() {
        const inp = $('ndj_inputFoto'), prv = $('ndj_previewFoto'), btn = $('ndj_btnEliminarFoto');
        if (inp) inp.value = '';
        if (prv) { prv.onerror = null; prv.src = ''; prv.classList.add('hidden'); }
        // Restaurar el ícono/etiqueta del placeholder (se ocultan al elegir archivo)
        $('ndj_placeholderFoto')?.querySelector('i')?.classList.remove('hidden');
        $('ndj_placeholderFoto')?.querySelector('span')?.classList.remove('hidden');
        if (btn) btn.classList.add('hidden');
    }

    // ============================================================
    // FILAS FAMILIAR
    // ============================================================
    function ndj_actualizarFechaFamiliar(fila) {
        const parentesco = fila.querySelector('select[name="ndj_parentesco[]"]')?.value;
        const contenedorFecha = fila.querySelector('.ndj-family-date');
        const inputFecha = fila.querySelector('input[name="ndj_fechaNacimiento[]"]');
        const esHijo = /^HIJ/i.test(parentesco);

        if (contenedorFecha) contenedorFecha.style.display = esHijo ? '' : 'none';
        if (inputFecha) {
            inputFecha.required = esHijo;
            if (!esHijo) inputFecha.value = '';
        }
        // Edad del hijo + estado del DNI (obligatorio para hijos)
        ndj_actualizarExtrasFilaHijo(fila);
    }

    // ── Opciones del select de Parentesco (catálogo TIPO_VINCULO_FAMILIAR) ──
    // Solo catálogo para filas nuevas; si se carga un dato legado fuera del
    // catálogo se añade como opción seleccionada para que se muestre como debe.
    function ndj_opcionesVinculo(selected = '') {
        const cats = (window.TIPOS_VINCULO || []).map(v => String(v).trim()).filter(Boolean);
        const sel  = String(selected || '').trim();
        let html = sel
            ? '<option value="">—</option>'
            : '<option value="" disabled selected>—</option>';
        for (const v of cats) {
            html += `<option value="${v}"${v === sel ? ' selected' : ''}>${v}</option>`;
        }
        if (sel && !cats.includes(sel)) {
            html += `<option value="${sel}" selected>${sel}</option>`;
        }
        return html;
    }

    function ndj_crearFila() {
        const div = document.createElement('div');
        div.className  = 'ndj-family-row';
        div.style.cssText = 'display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:8px;align-items:start;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;';
        div.innerHTML = `
            <input type="hidden" name="ndj_familiar_id[]" value="">
            <div><label class="dj-label">Parentesco</label>
                <select name="ndj_parentesco[]" class="dj-select">
                    ${ndj_opcionesVinculo('')}
                </select></div>
            <div><label class="dj-label">Apellidos y Nombres</label>
                <input type="text" name="ndj_apellidosNombres[]" class="dj-input" placeholder="Apellidos y nombres completos" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+"></div>
            <div class="ndj-family-date"><label class="dj-label">Fecha de Nacimiento</label>
                <input type="date" name="ndj_fechaNacimiento[]" class="dj-input">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:4px;">
                    <span class="ndj-family-age" style="display:none;font-size:11px;color:#2563eb;"></span>
                    <button type="button" class="ndj-btn-dni-hijo dj-btn-sm" style="display:none;background:#eef2ff;color:#3730a3;border-radius:5px;">SUBIR DNI</button>
                    <span class="ndj-dni-hijo-estado" style="display:none;font-size:10px;"></span>
                </div>
            </div>
            <div><label class="dj-label" style="visibility:hidden;">.</label>
                <button type="button" class="ndj-remove-family dj-btn-sm dj-btn-danger" style="margin-bottom:1px;">Eliminar</button></div>`;
        div.querySelector('.ndj-remove-family').addEventListener('click', () => div.remove());
        ndj_actualizarFechaFamiliar(div);
        return div;
    }

    // ============================================================
    // VALIDAR DOCUMENTO (DNI)
    // ============================================================
    async function validarDocumento() {
        const tipo   = tipoDocInput.value;
        const numero = docInput.value.trim();

        docErrorMsg.innerHTML = '';
        // En modo recontratación el documento es de SOLO LECTURA (NDJ_READONLY_IDS)
        // y jamás debe desactivarse el modo aquí: si ocurriera, el guardado se
        // enviaría a save-nueva-dj en vez de a save-recontratacion.
        if (modoRecontratacion && !tipoDocInput?.disabled) {
            modoRecontratacion = false;
            desactivarModoRecontratacion();
        }
        dniValido = false;
        actualizarEstadoGuardar();

        if (!tipo || !numero) return;

        if (tipo === '0034' && !/^\d{8}$/.test(numero)) {
            docErrorMsg.textContent = 'El DNI debe tener 8 dígitos numéricos.'; return;
        }

        try {
            const res     = await fetch(`${VITE_URL_APP}/api/dj/validar-documento?tipo=${tipo}&numero=${numero}`);
            const data    = await res.json();
            const dataper = data.data;

            if (!dataper || dataper.length === 0) {
                docErrorMsg.innerHTML = ''; dniValido = true; actualizarEstadoGuardar(); return;
            }

            const p = dataper[0];

            if (p.PERS_VIGENCIA === 'SI') {
                docErrorMsg.innerHTML = `
                    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;padding:8px 12px;margin-top:4px;">
                        <span style="font-weight:700;color:#b91c1c;">⚠ Personal YA REGISTRADO:</span><br>
                        <span style="color:#1d4ed8;font-weight:600;">${p.NOMB_1} ${p.NOMB_2} ${p.APEL_1} ${p.APEL_2}</span>
                        <span style="color:#64748b;">(${p.NRO_DOCU_IDEN})</span>
                        <br><span style="font-size:11px;color:#b91c1c;">No se puede crear un nuevo registro para este personal.</span>
                    </div>`;
                dniValido = false; actualizarEstadoGuardar(); return;
            }

            const codiPers = p.codigo || p.CODI_PERS || '';
            docErrorMsg.innerHTML = `
                <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:8px 12px;margin-top:4px;">
                    <span style="font-weight:700;color:#92400e;">ℹ Personal Existente encontrado:</span><br>
                    <span style="color:#1d4ed8;font-weight:600;">${p.NOMB_1} ${p.NOMB_2} ${p.APEL_1} ${p.APEL_2}</span>
                    <span style="color:#64748b;">(${p.NRO_DOCU_IDEN})</span><br>
                    <button id="ndj_btnRecontratacion" type="button" data-codi-pers="${codiPers}"
                        style="margin-top:8px;padding:6px 18px;font-size:13px;font-weight:700;border-radius:6px;
                               background:#f59e0b;color:#fff;cursor:pointer;border:none;box-shadow:0 1px 4px rgba(0,0,0,.15);">
                        🔄 Recontratación — Cargar datos
                    </button>
                </div>`;

            document.getElementById('ndj_btnRecontratacion')?.addEventListener('click', async function () {
                const cp = this.getAttribute('data-codi-pers');
                if (!cp) { Swal.fire({ icon:'error', title:'Error', text:'No se pudo obtener el código de personal.' }); return; }
                await autocompletarDesdePersonal(cp);
            });

            dniValido = false; actualizarEstadoGuardar();

        } catch (e) {
            docErrorMsg.textContent = 'Error validando documento.';
            dniValido = false; actualizarEstadoGuardar();
        }
    }

    // ============================================================
    // ── DNI Anverso / Reverso: vista previa + adjunto al GUARDAR ───────────
    // Los archivos NO se suben aparte: viajan en la misma petición de guardado
    // (igual que la foto) para que el servidor pueda exigirlos antes de guardar.
    const ndjDniFiles = { anverso: null, reverso: null };

    function ndj_configDniInputs() {
        // Modal "Subir DNI" (abrir/cerrar)
        const modalDni = $('ndj_modalDni');
        $('ndj_btnSubirDni')?.addEventListener('click', () => { if (modalDni) modalDni.style.display = 'flex'; });
        $('ndj_cerrarModalDni')?.addEventListener('click', () => { if (modalDni) modalDni.style.display = 'none'; });
        const pares = [
            ['ndj_btnDniAnverso', 'ndj_dni_anverso', 'ndj_prev_dni_anverso', 'ndj_img_dni_anverso', 'ndj_clear_dni_anverso', 'anverso'],
            ['ndj_btnDniReverso', 'ndj_dni_reverso', 'ndj_prev_dni_reverso', 'ndj_img_dni_reverso', 'ndj_clear_dni_reverso', 'reverso'],
        ];
        pares.forEach(([btnId, inpId, prevId, imgId, clearId, lado]) => {
            const btn = $(btnId), inp = $(inpId), prev = $(prevId), img = $(imgId), clear = $(clearId);
            if (!btn || !inp) return;
            btn.addEventListener('click', () => inp.click());
            inp.addEventListener('change', () => {
                const f = inp.files?.[0];
                if (!f) return;
                ndjDniFiles[lado] = f;
                if (img)  img.src = URL.createObjectURL(f);
                if (prev) prev.style.display = 'block';
            });
            if (clear) clear.addEventListener('click', () => {
                ndjDniFiles[lado] = null;
                inp.value = '';
                if (prev) prev.style.display = 'none';
                if (img)  img.removeAttribute('src');
            });
        });
    }

    function ndj_limpiarDni() {
        ndjDniFiles.anverso = null;
        ndjDniFiles.reverso = null;
        ['ndj_dni_anverso', 'ndj_dni_reverso'].forEach(id => { const i = $(id); if (i) i.value = ''; });
        ['ndj_prev_dni_anverso', 'ndj_prev_dni_reverso'].forEach(id => { const p = $(id); if (p) p.style.display = 'none'; });
        const m = $('ndj_modalDni');
        if (m) m.style.display = 'none';
    }

    // ============================================================
    // DNI DE HIJOS: {CODI}_H{N}.jpg → DNI1_HIJOS (anverso) / DNI2_HIJOS (reverso)
    // N = par por hijo en orden de fila: hijo 1 → H1/H2, hijo 2 → H3/H4, ...
    // ============================================================
    let ndjDniHijosRows = new WeakMap();   // fila → {anverso, reverso, tieneAnverso, tieneReverso}
    let ndjDniHijoActivo = null;

    function ndj_calcEdad(fecha) {
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

    function ndj_filasHijos() {
        return [...document.querySelectorAll('#ndj_familyContainer .ndj-family-row')]
            .filter(f => (f.querySelector('select[name="ndj_parentesco[]"]')?.value || '').startsWith('HIJO'));
    }

    function ndj_estadoDniHijo(fila) {
        let st = ndjDniHijosRows.get(fila);
        if (!st) { st = { anverso: null, reverso: null, tieneAnverso: false, tieneReverso: false }; ndjDniHijosRows.set(fila, st); }
        return st;
    }

    // En nueva DJ: DNI obligatorio para hijo menor y mayor con discapacidad permanente.
    function ndj_requiereDniHijo(fila) {
        const parentesco = (fila?.querySelector('select[name="ndj_parentesco[]"]')?.value || '').trim().toUpperCase();
        return parentesco === 'HIJO MENOR DE EDAD'
            || (parentesco.startsWith('HIJO MAYOR DE EDAD')
                && /INCAPACITADO|DISCAPACIDAD/.test(parentesco)
                && parentesco.includes('PERMANENT'));
    }

    function ndj_actualizarEstadoDniHijo(fila) {
        const span = fila.querySelector('.ndj-dni-hijo-estado');
        if (!span) return;
        const parentesco = fila.querySelector('select[name="ndj_parentesco[]"]')?.value || '';
        if (!/^HIJ/i.test(parentesco)) { span.style.display = 'none'; return; }
        const st = ndj_estadoDniHijo(fila);
        const okA = !!(st.anverso || st.tieneAnverso);
        const okR = !!(st.reverso || st.tieneReverso);
        span.style.display = '';
        if (okA && okR) {
            span.textContent = '✓ DNI completo';
            span.style.color = '#16a34a';
            return;
        }
        const obligatorio = ndj_requiereDniHijo(fila);
        const detalle = (okA || okR) ? 'falta ' + (okA ? 'reverso' : 'anverso') : 'sin DNI';
        if (obligatorio) {
            span.textContent = '⚠ ' + detalle + ' (obligatorio)';
            span.style.color = '#dc2626';
        } else {
            span.textContent = '○ ' + detalle + ' (opcional)';
            span.style.color = '#9ca3af';
        }
    }

    function ndj_actualizarExtrasFilaHijo(fila) {
        if (!fila) return;
        const parentesco = fila.querySelector('select[name="ndj_parentesco[]"]')?.value || '';
        const esHijo = /^HIJ/i.test(parentesco);
        const inputFecha = fila.querySelector('input[name="ndj_fechaNacimiento[]"]');
        const edadSpan = fila.querySelector('.ndj-family-age');
        if (edadSpan) {
            const txt = esHijo ? ndj_calcEdad(inputFecha?.value) : '';
            edadSpan.textContent = txt;
            edadSpan.style.display = txt ? '' : 'none';
        }
        const btnDni = fila.querySelector('.ndj-btn-dni-hijo');
        if (btnDni) btnDni.style.display = esHijo ? '' : 'none';
        ndj_actualizarEstadoDniHijo(fila);
    }

    function ndj_configDniHijos() {
        const modal = $('ndj_modalDniHijos');
        $('ndj_cerrarModalDniHijos')?.addEventListener('click', () => { if (modal) modal.style.display = 'none'; });

        const pares = [
            ['ndj_btnDniHijoAnverso', 'ndj_inputDniHijoAnverso', 'ndj_prev_dni_hijo_anverso', 'ndj_img_dni_hijo_anverso', 'ndj_clear_dni_hijo_anverso', 'anverso'],
            ['ndj_btnDniHijoReverso', 'ndj_inputDniHijoReverso', 'ndj_prev_dni_hijo_reverso', 'ndj_img_dni_hijo_reverso', 'ndj_clear_dni_hijo_reverso', 'reverso'],
        ];
        pares.forEach(([btnId, inpId, prevId, imgId, clearId, lado]) => {
            const btn = $(btnId), inp = $(inpId), prev = $(prevId), img = $(imgId), clear = $(clearId);
            if (!btn || !inp) return;
            btn.addEventListener('click', () => inp.click());
            inp.addEventListener('change', () => {
                const f = inp.files?.[0];
                if (!f || !ndjDniHijoActivo) return;
                const st = ndj_estadoDniHijo(ndjDniHijoActivo);
                st[lado] = f;
                if (img)  img.src = URL.createObjectURL(f);
                if (prev) prev.style.display = 'block';
                ndj_actualizarEstadoDniHijo(ndjDniHijoActivo);
            });
            if (clear) clear.addEventListener('click', () => {
                if (!ndjDniHijoActivo) return;
                const st = ndj_estadoDniHijo(ndjDniHijoActivo);
                st[lado] = null;
                if (lado === 'anverso') st.tieneAnverso = false; else st.tieneReverso = false;
                inp.value = '';
                if (prev) prev.style.display = 'none';
                if (img)  img.removeAttribute('src');
                ndj_actualizarEstadoDniHijo(ndjDniHijoActivo);
            });
        });

        // Delegación: botón DNI por fila y edad en vivo
        const cont = $('ndj_familyContainer');
        if (cont) {
            cont.addEventListener('click', (e) => {
                const btn = e.target.closest('.ndj-btn-dni-hijo');
                if (btn) ndj_abrirModalDniHijos(btn.closest('.ndj-family-row'));
            });
            cont.addEventListener('change', (e) => {
                if (e.target.matches('input[name="ndj_fechaNacimiento[]"], select[name="ndj_parentesco[]"]')) {
                    ndj_actualizarFechaFamiliar(e.target.closest('.ndj-family-row'));
                    ndj_actualizarExtrasFilaHijo(e.target.closest('.ndj-family-row'));
                }
            });
        }
    }

    function ndj_abrirModalDniHijos(fila) {
        if (!fila) return;
        ndjDniHijoActivo = fila;
        const modal = $('ndj_modalDniHijos');
        const idx = ndj_filasHijos().indexOf(fila);
        const label = $('ndj_dni_hijo_label');
        if (label) label.textContent = 'Hijo ' + (idx + 1);
        const st = ndj_estadoDniHijo(fila);
        const codi = ($('ndj_cod_postulante')?.value || '').trim();

        const mostrar = (lado, prevId, imgId, inpId, carpeta) => {
            const prev = $(prevId), img = $(imgId), inp = $(inpId);
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
        mostrar('anverso', 'ndj_prev_dni_hijo_anverso', 'ndj_img_dni_hijo_anverso', 'ndj_inputDniHijoAnverso', 'DNI1_HIJOS');
        mostrar('reverso', 'ndj_prev_dni_hijo_reverso', 'ndj_img_dni_hijo_reverso', 'ndj_inputDniHijoReverso', 'DNI2_HIJOS');

        if (modal) modal.style.display = 'flex';
    }

    function ndj_limpiarDniHijos() {
        ndjDniHijosRows = new WeakMap();
        ndjDniHijoActivo = null;
        const m = $('ndj_modalDniHijos'); if (m) m.style.display = 'none';
        const l = $('ndj_dni_hijo_label'); if (l) l.textContent = '';
    }

    async function ndj_consultarDniHijos(codiPers) {
        if (!codiPers) return;
        const filasH = ndj_filasHijos();
        if (!filasH.length) return;
        const nombres = [];
        filasH.forEach((fila, i) => {
            nombres.push(`${codiPers}_H${i * 2 + 1}.jpg`);
            nombres.push(`${codiPers}_H${i * 2 + 2}.jpg`);
        });
        try {
            const qs = nombres.map(n => 'nombres[]=' + encodeURIComponent(n)).join('&');
            const resp = await fetch(`${VITE_URL_APP}/api/dj/get-dni-hijos?${qs}`, {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            });
            const json = await resp.json();
            const map = json.data || {};
            filasH.forEach((fila, i) => {
                const st = ndj_estadoDniHijo(fila);
                st.tieneAnverso = !!map[`${codiPers}_H${i * 2 + 1}.jpg`];
                st.tieneReverso = !!map[`${codiPers}_H${i * 2 + 2}.jpg`];
                ndj_actualizarEstadoDniHijo(fila);
            });
        } catch (e) {
            console.warn('[NuevaDJ] Error consultando DNI de hijos:', e);
        }
    }

    async function ndj_subirDniHijos(codiPers) {
        if (!codiPers) return { ok: true, sinDni: true };
        const filasH = ndj_filasHijos();
        const fd = new FormData();
        let n = 0;
        fd.append('codi_pers', codiPers);
        filasH.forEach((fila, i) => {
            const st = ndj_estadoDniHijo(fila);
            if (st.anverso) { fd.append('archivos[]', st.anverso); fd.append('metas[]', `DNI1_HIJOS|${codiPers}_H${i * 2 + 1}.jpg`); n++; }
            if (st.reverso) { fd.append('archivos[]', st.reverso); fd.append('metas[]', `DNI2_HIJOS|${codiPers}_H${i * 2 + 2}.jpg`); n++; }
        });
        if (!n) return { ok: true, sinDni: true };
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(`${VITE_URL_APP}/api/dj/upload-dni-hijos`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body:   fd,
            });
            const json = await res.json();
            if (json.success) {
                filasH.forEach(fila => {
                    const st = ndj_estadoDniHijo(fila);
                    if (st.anverso) { st.anverso = null; st.tieneAnverso = true; }
                    if (st.reverso) { st.reverso = null; st.tieneReverso = true; }
                    ndj_actualizarEstadoDniHijo(fila);
                });
            }
            return { ok: !!json.success, message: json.message };
        } catch (err) {
            console.warn('[NuevaDJ] Error subiendo DNI de hijos:', err);
            return { ok: false, message: 'Error de conexión al subir el DNI de hijos.' };
        }
    }

    // ============================================================
    // GUARDAR
    // ============================================================
    async function ndj_guardar() {

        const currentUser = (window.currentUser || '').toString().trim().toUpperCase();
        const exentoValidaciones = (window.funcionalidadesSISOL || []).includes('exento_validaciones');

        if (!exentoValidaciones) {

        // ── Validaciones previas ──────────────────────────────
        const hoy    = new Date(); hoy.setHours(0,0,0,0);
        const manana = new Date(hoy); manana.setDate(hoy.getDate() + 1);

        const caduca = $('ndj_caduca')?.value;
        if (caduca && new Date(caduca + 'T00:00:00') < manana) {
            Swal.fire({ icon:'warning', title:'Fecha inválida', text:'La caducidad debe ser desde mañana en adelante.', confirmButtonText:'Entendido' });
            $('ndj_caduca')?.focus(); return;
        }
        const fechaNac = $('ndj_fecha_nacimiento')?.value;
        if (fechaNac && ndj_fechaCompleta(fechaNac) && new Date(fechaNac + 'T00:00:00') >= hoy) {
            Swal.fire({ icon:'warning', title:'Fecha inválida', text:'La fecha de nacimiento debe ser anterior a hoy.', confirmButtonText:'Entendido' });
            $('ndj_fecha_nacimiento')?.focus(); return;
        }
        if (fechaNac && ndj_fechaCompleta(fechaNac) && reglasEdad) {
            const edad = ndj_calcularEdad(fechaNac);
            if (edad < reglasEdad.minima || edad > reglasEdad.maxima) {
                if (excepcionEdadValidada && fechaExcepcionAutorizada === fechaNac) {
                    ndj_marcarEdadAutorizada($('ndj_fecha_nacimiento'));
                } else {
                ndj_marcarEdadInvalida($('ndj_fecha_nacimiento'), true);
                if (fechaEdadAlertada !== fechaNac) {
                    fechaEdadAlertada = fechaNac;
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Edad fuera del rango permitido',
                        text: `La edad calculada es ${edad} años. El rango permitido es de ${reglasEdad.minima} a ${reglasEdad.maxima} años.`,
                        confirmButtonText: 'Entendido',
                    });
                }
                $('ndj_fecha_nacimiento')?.focus();
                return;
                }
            } else {
                ndj_marcarEdadInvalida($('ndj_fecha_nacimiento'), false);
            }
        }
        const celular = $('ndj_celular')?.value?.trim();
        if (celular && !/^\d{9}$/.test(celular)) {
            Swal.fire({ icon:'warning', title:'Celular inválido', text:'El celular debe tener exactamente 9 dígitos.', confirmButtonText:'Entendido' });
            $('ndj_celular')?.focus(); return;
        }
        const wsp = $('ndj_whatsapp')?.value?.trim();
        if (wsp && !/^\d{9}$/.test(wsp)) {
            Swal.fire({ icon:'warning', title:'WhatsApp inválido', text:'El WhatsApp debe tener exactamente 9 dígitos.', confirmButtonText:'Entendido' });
            $('ndj_whatsapp')?.focus(); return;
        }
        const contactoEmergencia = $('ndj_contacto_emergencia')?.value?.trim();
        if (contactoEmergencia && !/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/.test(contactoEmergencia)) {
            Swal.fire({ icon:'warning', title:'Contacto inválido', text:'El campo Llamar a solo admite letras y espacios.', confirmButtonText:'Entendido' });
            $('ndj_contacto_emergencia')?.focus(); return;
        }
        const celEmer = $('ndj_celular_emergencia')?.value?.trim();
        if (celEmer && !/^\d{9}$/.test(celEmer)) {
            Swal.fire({ icon:'warning', title:'Celular emergencia inválido', text:'Debe tener exactamente 9 dígitos.', confirmButtonText:'Entendido' });
            $('ndj_celular_emergencia')?.focus(); return;
        }
        const correo = $('ndj_correo')?.value?.trim();
        if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            Swal.fire({ icon:'warning', title:'Correo inválido', text:'Ingrese un correo válido (ejemplo@dominio.com).', confirmButtonText:'Entendido' });
            $('ndj_correo')?.focus(); return;
        }
        const peso = $('ndj_peso')?.value?.trim();
        if (peso && !/^\d{1,3}$/.test(peso)) {
            Swal.fire({ icon:'warning', title:'Peso inválido', text:'El peso debe contener como máximo 3 dígitos.', confirmButtonText:'Entendido' });
            $('ndj_peso')?.focus(); return;
        }
        const talla = $('ndj_talla')?.value?.trim();
        if (talla && !/^\d\.\d{2}$/.test(talla)) {
            Swal.fire({ icon:'warning', title:'Talla inválida', text:'Ingrese la talla con el formato M.cm, por ejemplo: 1.70.', confirmButtonText:'Entendido' });
            $('ndj_talla')?.focus(); return;
        }
        const anioEgreso = $('ndj_anio_egreso')?.value?.trim();
        if (anioEgreso && !/^\d{4}$/.test(anioEgreso)) {
            Swal.fire({ icon:'warning', title:'Año de egreso inválido', text:'El año de egreso debe tener exactamente 4 dígitos.', confirmButtonText:'Entendido' });
            $('ndj_anio_egreso')?.focus(); return;
        }
        const ocupacionPrincipal = $('ndj_ocupacion_principal')?.value?.trim();
        if (ocupacionPrincipal && !/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/.test(ocupacionPrincipal)) {
            Swal.fire({ icon:'warning', title:'Profesión inválida', text:'Profesión / Ocupación Principal solo admite letras y espacios.', confirmButtonText:'Entendido' });
            $('ndj_ocupacion_principal')?.focus(); return;
        }
        const experienciaAnios = $('ndj_experiencia_anios')?.value?.trim();
        const experienciaMeses = $('ndj_experiencia_meses')?.value?.trim();
        if ((experienciaAnios && !/^\d{1,2}$/.test(experienciaAnios)) || (experienciaMeses && !/^\d{1,2}$/.test(experienciaMeses))) {
            Swal.fire({ icon:'warning', title:'Experiencia inválida', text:'Los años y meses de experiencia admiten como máximo 2 dígitos.', confirmButtonText:'Entendido' });
            (experienciaAnios ? $('ndj_experiencia_anios') : $('ndj_experiencia_meses'))?.focus(); return;
        }

        // Campos obligatorios
            const camposReq = [
                { id:'ndj_filtroSucursal',        nombre:'Sucursal' },
                { id:'ndj_sel_tipo_personal',     nombre:'Tipo de Personal' },
                { id:'ndj_tipo_documento',        nombre:'Tipo de Documento' },
                { id:'ndj_nro_documento',         nombre:'Número de Documento' },
                { id:'ndj_fecha_ingreso_solmar',  nombre:'Fecha de Ingreso a Solmar' },
                { id:'ndj_nombre1',               nombre:'Primer Nombre' },
                { id:'ndj_apellido_paterno',      nombre:'Apellido Paterno' },
                { id:'ndj_apellido_materno',      nombre:'Apellido Materno' },
                { id:'ndj_sel_cargo',              nombre:'Cargo' },
                { id:'ndj_caduca',                nombre:'Caducidad del Documento' },
                { id:'ndj_estado_civil',          nombre:'Estado Civil' },
                { id:'ndj_sexo',                  nombre:'Sexo' },
                { id:'ndj_fecha_nacimiento',      nombre:'Fecha de Nacimiento' },
                { id:'ndj_pais',                   nombre:'País de Nacimiento' },
                { id:'ndj_departamento_nac',      nombre:'Departamento (Nacimiento)' },
                { id:'ndj_provincia_nac',         nombre:'Provincia (Nacimiento)' },
                { id:'ndj_distrito_nac',          nombre:'Distrito (Nacimiento)' },
                { id:'ndj_celular',               nombre:'Celular' },
                { id:'ndj_correo',                nombre:'Correo Electrónico' },
                { id:'ndj_whatsapp',              nombre:'WhatsApp' },
                { id:'ndj_tipo_sangre',           nombre:'Tipo de Sangre' },
                { id:'ndj_peso',                  nombre:'Peso' },
                { id:'ndj_talla',                 nombre:'Talla' },
                { id:'ndj_sistema_previsional',   nombre:'Sistema Previsional' },
                { id:'ndj_essalud',               nombre:'Essalud' },
                { id:'ndj_pensionista',           nombre:'Pensionista' },
                { id:'ndj_grado_instruccion',     nombre:'Grado de Instrucción' },
                { id:'ndj_institucion',           nombre:'Institución Educativa' },
                { id:'ndj_carrera',               nombre:'Carrera' },
                { id:'ndj_anio_egreso',           nombre:'Año de Egreso' },
                { id:'ndj_embargos',              nombre:'Embargos' },
                { id:'ndj_cuenta_banco',          nombre:'Cuenta de Sueldo' },
                { id:'ndj_departamento_actual',   nombre:'Departamento (Dir. Actual)' },
                { id:'ndj_provincia_actual',      nombre:'Provincia (Dir. Actual)' },
                { id:'ndj_distrito_actual',       nombre:'Distrito (Dir. Actual)' },
                { id:'ndj_direccion_actual',      nombre:'Dirección Actual' },
                { id:'ndj_departamento_dni',      nombre:'Departamento (Dir. DNI)' },
                { id:'ndj_provincia_dni',         nombre:'Provincia (Dir. DNI)' },
                { id:'ndj_distrito_dni',          nombre:'Distrito (Dir. DNI)' },
                { id:'ndj_direccion_dni',         nombre:'Dirección DNI' },
                { id:'ndj_contacto_emergencia',   nombre:'Contacto de Emergencia' },
                { id:'ndj_celular_emergencia',    nombre:'Celular de Emergencia' },
                { id:'ndj_parentesco_emergencia', nombre:'Parentesco de Emergencia' },
            ];
            const faltantes = camposReq.filter(c => {
                const el = $(c.id);
                if (!el) return true;
                // Regla Carnet de Extranjería (0035): Apellido Materno es OPCIONAL
                // (hay personal extranjero con 1 solo apellido; se guarda NULL si está vacío)
                if (c.id === 'ndj_apellido_materno' && ($('ndj_tipo_documento')?.value || '').trim() === '0035') return false;
                if (el.type !== 'hidden' && el.offsetParent === null) return false;
                if (el.type === 'checkbox') return !el.checked;
                return !el.value?.trim();
            });
            if (faltantes.length > 0) {
                const lista = faltantes.map(f => `• ${f.nombre}`).join('<br>');
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos obligatorios',
                    html: `Los siguientes campos son requeridos:<br><br>${lista}`,
                    confirmButtonText: 'Entendido',
                    width: 500,
                });
                const primerFaltante = $(faltantes[0].id);
                if (primerFaltante) primerFaltante.focus();
                return;
            }

        const hijoSinFecha = [...document.querySelectorAll('#ndj_familyContainer .ndj-family-row')]
            .find(fila => {
                const parentesco = fila.querySelector('select[name="ndj_parentesco[]"]')?.value;
                const fecha = fila.querySelector('input[name="ndj_fechaNacimiento[]"]')?.value;
                return /^HIJ/i.test(parentesco) && !fecha;
            });
        if (hijoSinFecha) {
            Swal.fire({
                icon: 'warning',
                title: 'Fecha obligatoria',
                text: 'Ingrese la fecha de nacimiento para el familiar Hijo(a).',
                confirmButtonText: 'Entendido',
            });
            hijoSinFecha.querySelector('input[name="ndj_fechaNacimiento[]"]')?.focus();
            return;
        }

        const filasHijosDni = ndj_filasHijos();
        for (let i = 0; i < filasHijosDni.length; i++) {
            if (!ndj_requiereDniHijo(filasHijosDni[i])) continue;
            const stH = ndj_estadoDniHijo(filasHijosDni[i]);
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
                return;
            }
        }

        const familiarConNombreInvalido = [...document.querySelectorAll('#ndj_familyContainer input[name="ndj_apellidosNombres[]"]')]
            .find(input => input.value.trim() && !/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/.test(input.value.trim()));
        if (familiarConNombreInvalido) {
            Swal.fire({
                icon: 'warning',
                title: 'Nombre de familiar inválido',
                text: 'Apellidos y Nombres solo admite letras y espacios.',
                confirmButtonText: 'Entendido',
            });
            familiarConNombreInvalido.focus();
            return;
        }

        // La foto del personal ahora es OBLIGATORIA: se adjunta en la misma
        // petición de guardado y el servidor valida antes de crear el personal
        // (respuesta 422 "falta_foto"). Ya no se pregunta si se desea continuar
        // sin foto.

        } // fin exentoValidaciones
        // ── Fin validaciones ─────────────────────────────────

        const fd      = new FormData($('formNuevaDJ'));
        const payload = Object.fromEntries(fd.entries());
        payload.ndj_parentesco       = fd.getAll('ndj_parentesco[]');
        payload.ndj_apellidosNombres = fd.getAll('ndj_apellidosNombres[]');
        payload.ndj_fechaNacimiento  = fd.getAll('ndj_fechaNacimiento[]');
        payload.ndj_familiar_id     = fd.getAll('ndj_familiar_id[]');

        const body = {
            ...payload,
            tipo_personal:       payload.ndj_tipo_personal        || payload.ndj_sel_tipo_personal,
            autorizar_sctr:      payload.ndj_autorizar_sctr === '1' ? '1' : '0',
            cargo:               payload.ndj_cargo                 || '',
            cod_postulante:      payload.ndj_cod_postulante        || '',
            tipo_documento:      payload.ndj_tipo_documento        || '0034',
            dni:                 payload.ndj_nro_documento,
            nombre1:             payload.ndj_nombre1,
            nombre2:             payload.ndj_nombre2,
            apellido_paterno:    payload.ndj_apellido_paterno,
            apellido_materno:    payload.ndj_apellido_materno,
            caduca:              payload.ndj_caduca,
            no_caduca_dni:       payload.ndj_no_caduca === 'on' ? '1' : '0',
            estado_civil:        payload.ndj_estado_civil,
            sexo:                payload.ndj_sexo,
            fecha_nacimiento:    payload.ndj_fecha_nacimiento,
            celular:             payload.ndj_celular,
            correo:              payload.ndj_correo,
            whatsapp:            payload.ndj_whatsapp,
            tipo_sangre:         payload.ndj_tipo_sangre,
            peso:                payload.ndj_peso,
            talla:               payload.ndj_talla,
            sistema_previsional: payload.ndj_sistema_previsional,
            essalud:             payload.ndj_essalud,
            pensionista:         payload.ndj_pensionista,
            grado_instruccion:   payload.ndj_grado_instruccion,
            institucion:         payload.ndj_institucion,
            carrera:             payload.ndj_carrera,
            anio_egreso:         payload.ndj_anio_egreso,
            embargos:            payload.ndj_embargos,
            cuenta_banco:        payload.ndj_cuenta_banco,
            direccion_actual:    payload.ndj_direccion_actual,
            direccion_dni:       payload.ndj_direccion_dni,
            tipo_zona_dni:       payload.ndj_tipo_zona,
            zona_dirdni:         payload.ndj_zona_dirdni,
            departamento_actual: payload.ndj_departamento_actual,
            provincia_actual:    payload.ndj_provincia_actual,
            distrito_actual:     payload.ndj_distrito_actual,
            departamento_dni:    payload.ndj_departamento_dni,
            provincia_dni:       payload.ndj_provincia_dni,
            distrito_dni:        payload.ndj_distrito_dni,
            departamento_nac:    payload.ndj_departamento_nac,
            provincia_nac:       payload.ndj_provincia_nac,
            distrito_nac:        payload.ndj_distrito_nac,
            nacionalidad:          payload.ndj_pais                || '',
            contacto_emergencia:   payload.ndj_contacto_emergencia,
            celular_emergencia:    payload.ndj_celular_emergencia,
            parentesco_emergencia: payload.ndj_parentesco_emergencia,
            ocupacion_principal:   payload.ndj_ocupacion_principal,
            experiencia_anios:     payload.ndj_experiencia_anios,
            experiencia_meses:     payload.ndj_experiencia_meses,
            familiar_empresa:      payload.ndj_familiar_empresa,
            familiar_nombre:       payload.ndj_familiar_nombre,
            familiar_parentesco:   payload.ndj_familiar_parentesco,
            curso_sucamec:         payload.ndj_curso_sucamec,
            presto_smo:            payload.ndj_presto_smo,
            lugar_smo:             payload.ndj_lugar_smo,
            licencia_arma:         payload.ndj_licencia_arma,
            arma_propia:           payload.ndj_arma_propia,
            brevete:               payload.ndj_brevete,
            clase_brevete:         payload.ndj_clase_brevete,
            tipo_vehiculo:         payload.ndj_tipo_vehiculo,
            vehiculo_propio:       payload.ndj_vehiculo_propio,
            empresa_anterior:      payload.ndj_empresa_anterior,
            cargo_anterior:        payload.ndj_cargo_anterior,
            duracion_anterior:     payload.ndj_duracion_anterior,
            dj2026_laboral_1:      payload.ndj_dj2026_laboral_1,
            dj2026_laboral_2:      payload.ndj_dj2026_laboral_2,
            FAM_PARENTESCO:        payload.ndj_parentesco,
            FAM_NOMBRES:           payload.ndj_apellidosNombres,
            FAM_FECHA_NACI:        payload.ndj_fechaNacimiento,
            FAM_ID:                payload.ndj_familiar_id,
            // El envío explícito permite guardar también una lista totalmente vacía.
            FAM_PRESENTE:          '1',
            sucursal:              payload.ndj_filtroSucursal,
            fecha_ingreso_solmar:  payload.ndj_fecha_ingreso_solmar,
            usuario:               payload.ndj_usuario,
            PERS_CONTRATADO:       0,
        };

        const esRecontratacion = modoRecontratacion && codiPersRecontratacion;
        const url = esRecontratacion
            ? `${VITE_URL_APP}/api/dj/save-recontratacion`
            : `${VITE_URL_APP}/api/dj/save-nueva-dj`;
        if (esRecontratacion) body.cod_postulante = codiPersRecontratacion;

        // Foto + DNI (anverso y reverso) viajan ADJUNTOS en esta misma petición,
        // tanto al crear una DJ nueva como en recontratación: así el servidor los
        // exige antes de guardar (422 "falta_foto" / "falta_dni").
        const csrfTokenSave = document.querySelector('[name=_token]')?.value || '';
        const fdGuardado = new FormData();
        Object.keys(body).forEach(k => {
            const v = body[k];
            if (v === undefined || v === null) return;
            if (Array.isArray(v)) v.forEach(item => fdGuardado.append(`${k}[]`, item ?? ''));
            else fdGuardado.append(k, v);
        });
        const fotoAdjuntaNueva = $('ndj_inputFoto')?.files?.[0] || null;
        if (fotoAdjuntaNueva)    fdGuardado.append('foto', fotoAdjuntaNueva);
        if (ndjDniFiles.anverso) fdGuardado.append('dni_anverso', ndjDniFiles.anverso);
        if (ndjDniFiles.reverso) fdGuardado.append('dni_reverso', ndjDniFiles.reverso);
        ndj_filasHijos().forEach((fila, i) => {
            const st = ndj_estadoDniHijo(fila);
            if (st.anverso) fdGuardado.append(`dni_hijos_anverso[${i + 1}]`, st.anverso);
            if (st.reverso) fdGuardado.append(`dni_hijos_reverso[${i + 1}]`, st.reverso);
        });
        const opciones = {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': csrfTokenSave, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body:    fdGuardado,
        };

        try {
            btnGuardar.disabled = true;
            const res  = await fetch(url, opciones);
            const json = await res.json();

            if (json.success) {
                // ── Subir el DNI de los hijos si hay archivos nuevos ──
                // (la foto y el DNI del personal ya viajaron adjuntos dentro de
                //  esta misma petición, igual en DJ nueva y en recontratación)
                const codiPers  = json.codi_pers || body.cod_postulante || '';
                const resDniH   = await ndj_subirDniHijos(codiPers);
                const falloDniH = !resDniH.sinDni && !resDniH.ok;

                if (falloDniH) {
                    // Recontratación/DJ guardada OK pero el DNI de los hijos falló
                    const msjFallo = resDniH.message || 'Error al conectar con el servidor de imágenes';
                    Swal.fire({
                        icon:  'warning',
                        title: 'Datos guardados, pero falló el DNI de los hijos',
                        html:  `<p style="color:#4b5563; font-size: 14px;">La Declaración Jurada se guardó correctamente, pero ocurrió un problema con el DNI de los hijos.</p>
                               <div style="background: #fff7ed; border: 1px solid #fdba74; padding: 10px; border-radius: 6px; margin-top: 10px;">
                                   <span style="color:#b45309; font-weight: 600;">⚠️ ${msjFallo}</span>
                               </div>`,
                    });
                } else if (json.dni_pendiente || json.foto_pendiente) {
                    // La DJ se guardó, pero el servidor de archivos no recibió algún archivo
                    Swal.fire({
                        icon:  'warning',
                        title: 'Guardado, pendiente de subir',
                        text:  json.message || 'La Declaración Jurada se guardó correctamente, pero quedaron archivos pendientes de subir.',
                    });
                } else {
                    Swal.fire({
                        icon:  'success',
                        title: modoRecontratacion ? '¡Recontratación exitosa!' : '¡Guardado!',
                        text:  json.message || 'La Declaración Jurada se guardó correctamente.',
                    });
                }

                ndj_cerrar();
                if (window.getPersonalSoloDJ)          window.getPersonalSoloDJ();
                if (window.getPersonalSoloDJMigracion) window.getPersonalSoloDJMigracion();

            } else {
                const faltaDocs = json.code === 'falta_dni' || json.code === 'falta_foto';
                if (faltaDocs) {
                    // Marcar en rojo lo que falte y abrir el modal de DNI si aplica
                    if (json.falta_foto) $('ndj_inputFoto')?.classList.add('ring-2', 'ring-red-500');
                    if (json.falta_anverso || json.falta_reverso) {
                        ['ndj_dni_anverso', 'ndj_dni_reverso'].forEach(id => $(id)?.classList.add('ring-2', 'ring-red-500'));
                        const modalDni = $('ndj_modalDni');
                        if (modalDni) modalDni.style.display = 'flex';
                    }
                }
                Swal.fire({
                    icon:  'error',
                    title: faltaDocs ? String(json.message || 'Falta ingresar foto').replace(/\.$/, '') : 'Error',
                    text:  faltaDocs ? 'Debe completar los documentos requeridos para poder guardar la DJ.' : (json.message || 'Error al guardar.'),
                });
            }
        } catch (err) {
            console.error('[NuevaDJ] Error al guardar:', err);
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión al guardar.' });
        } finally {
            btnGuardar.disabled = false;
        }
    }

    // ============================================================
    // BIND DE EVENTOS
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {

        ndj_cargarReglasEdad();
        ndj_configDniInputs();
        ndj_configDniHijos();
        ndj_bloquearCampos(true);
        if (alertTipoPersonal) alertTipoPersonal.style.display = 'block';

        $('ndj_filtroSucursal')?.addEventListener('change', function () {
            if (this.value && alertTipoPersonal) alertTipoPersonal.style.display = 'none';
            else if (alertTipoPersonal)          alertTipoPersonal.style.display = 'block';
            ndj_bloquearCampos(!this.value);
        });

        // ── Validaciones de campos ────────────────────────────

        // Caduca: limitar año a 4 dígitos
        $('ndj_caduca')?.setAttribute('maxlength', '10');
        $('ndj_caduca')?.addEventListener('input', function () {
            const anioExcedido = this.value.match(/^(\d{5,})(-\d{2}-\d{2})$/);
            if (anioExcedido) {
                this.value = `${anioExcedido[1].slice(0, 4)}${anioExcedido[2]}`;
            }
        });

        // Caduca: desde mañana
        $('ndj_caduca')?.addEventListener('blur', function () {
            if (!this.value) return;
            const hoy = new Date(); hoy.setHours(0,0,0,0);
            const man = new Date(hoy); man.setDate(hoy.getDate() + 1);

            if (new Date(this.value + 'T00:00:00') < man) {
                Swal.fire({ icon:'warning', title:'Fecha inválida', text:'La fecha de caducidad debe ser desde mañana en adelante.', confirmButtonText:'Entendido' });
                this.value = '';
            }
        });

        // No Caduca checkbox: bloquear/desbloquear caduca
        $('ndj_no_caduca')?.addEventListener('change', function () {
            const caducaInput = $('ndj_caduca');
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

        // Prestó S.M.O.: habilitar/deshabilitar Lugar de S.M.O.
        const ndjLugarSmoInit = $('ndj_lugar_smo');
        const ndjPrestoSmoInit = $('ndj_presto_smo');
        if (ndjLugarSmoInit && (!ndjPrestoSmoInit || ndjPrestoSmoInit.value !== 'SI')) {
            ndjLugarSmoInit.disabled = true;
        }
        $('ndj_presto_smo')?.addEventListener('change', function () {
            const lugarSmo = $('ndj_lugar_smo');
            if (!lugarSmo) return;
            if (this.value === 'SI') {
                lugarSmo.disabled = false;
            } else {
                lugarSmo.disabled = true;
                lugarSmo.value = '';
            }
        });

        // Fecha nacimiento: anterior a hoy
        $('ndj_fecha_nacimiento')?.setAttribute('maxlength', '10');
        $('ndj_fecha_nacimiento')?.addEventListener('input', function () {
            const anioExcedido = this.value.match(/^(\d{5,})(-\d{2}-\d{2})$/);
            if (anioExcedido) {
                this.value = `${anioExcedido[1].slice(0, 4)}${anioExcedido[2]}`;
            }
        });

        const validarEdadAlIngresar = function () {
            if (this.value && !ndj_fechaCompleta(this.value)) {
                return;
            }
            ndj_validarEdadNacimiento(this);
        };

        $('ndj_fecha_nacimiento')?.addEventListener('blur', validarEdadAlIngresar);
        $('ndj_fecha_nacimiento')?.addEventListener('change', validarEdadAlIngresar);

        $('ndj_fecha_nacimiento')?.addEventListener('blur', function () {
            if (!this.value || !ndj_fechaCompleta(this.value)) return;
            const hoy = new Date(); hoy.setHours(0,0,0,0);
            
            if (new Date(this.value + 'T00:00:00') >= hoy) {
                Swal.fire({ icon:'warning', title:'Fecha inválida', text:'La fecha de nacimiento debe ser anterior a hoy.', confirmButtonText:'Entendido' });
                this.value = '';
            }
        });

        // Celular
        $('ndj_celular')?.addEventListener('input', function () { this.value = this.value.replace(/\D/g,'').slice(0,9); });
        $('ndj_celular')?.addEventListener('blur',  function () {
            if (this.value && this.value.length !== 9) {
                Swal.fire({ icon:'warning', title:'Celular inválido', text:'Debe tener exactamente 9 dígitos.', confirmButtonText:'Entendido' });
                this.value = '';
            }
        });

        // WhatsApp
        $('ndj_whatsapp')?.addEventListener('input', function () { this.value = this.value.replace(/\D/g,'').slice(0,9); });
        $('ndj_whatsapp')?.addEventListener('blur',  function () {
            if (this.value && this.value.length !== 9) {
                Swal.fire({ icon:'warning', title:'WhatsApp inválido', text:'Debe tener exactamente 9 dígitos.', confirmButtonText:'Entendido' });
                this.value = '';
            }
        });

        // Peso: solamente números enteros, máximo tres dígitos.
        $('ndj_peso')?.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 3);
        });

        // Talla: formato M.cm (un dígito, punto y dos decimales), por ejemplo 1.70.
        $('ndj_talla')?.addEventListener('input', function () {
            const partes = this.value.replace(/[^0-9.]/g, '').split('.');
            const metros = partes.shift().slice(0, 1);
            const centimetros = partes.join('').slice(0, 2);
            this.value = metros ? `${metros}${this.value.includes('.') ? `.${centimetros}` : ''}` : '';
        });
        $('ndj_talla')?.addEventListener('blur', function () {
            if (this.value && !/^\d\.\d{2}$/.test(this.value)) {
                Swal.fire({ icon:'warning', title:'Talla inválida', text:'Use el formato M.cm, por ejemplo: 1.70.', confirmButtonText:'Entendido' });
                this.value = '';
            }
        });

        // Año de egreso: solo cuatro dígitos.
        $('ndj_anio_egreso')?.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });

        // Profesión y experiencia: letras para profesión; dos dígitos para cada periodo.
        $('ndj_ocupacion_principal')?.addEventListener('input', function () {
            this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, '');
        });
        ['ndj_experiencia_anios', 'ndj_experiencia_meses'].forEach(id => {
            $(id)?.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 2);
            });
        });

        // Contacto de emergencia: solo letras y espacios.
        $('ndj_contacto_emergencia')?.addEventListener('input', function () {
            this.value = this.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, '');
        });

        // Celular emergencia: solo números, máximo nueve dígitos.
        $('ndj_celular_emergencia')?.addEventListener('input', function () { this.value = this.value.replace(/\D/g,'').slice(0,9); });
        $('ndj_celular_emergencia')?.addEventListener('blur',  function () {
            if (this.value && this.value.length !== 9) {
                Swal.fire({ icon:'warning', title:'Celular emergencia inválido', text:'Debe tener exactamente 9 dígitos.', confirmButtonText:'Entendido' });
                this.value = '';
            }
        });

        // Correo
        $('ndj_correo')?.addEventListener('blur', function () {
            const val = this.value.trim();
            if (!val) return;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                Swal.fire({ icon:'warning', title:'Correo inválido', text:'Ingrese un correo válido (ejemplo@dominio.com).', confirmButtonText:'Entendido' });
                this.value = ''; this.focus();
            }
        });

        // Nombres/apellidos: solo letras
        ['ndj_nombre1','ndj_nombre2','ndj_apellido_paterno','ndj_apellido_materno'].forEach(id => {
            $(id)?.addEventListener('input', function () {
                this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '');
            });
        });

        // ── Foto: solo JPG, máx 1MB ───────────────────────────
        $('ndj_btnSubirFoto')?.addEventListener('click', () => $('ndj_inputFoto')?.click());
        $('ndj_inputFoto')?.addEventListener('change', function () {
            const file = this.files?.[0];
            if (!file) return;

            // Validar tipo: solo JPG/JPEG
            if (!['image/jpeg','image/jpg'].includes(file.type)) {
                Swal.fire({ icon:'warning', title:'Formato no permitido', text:'Solo se permiten imágenes en formato JPG/JPEG.', confirmButtonText:'Entendido' });
                this.value = ''; return;
            }

            // Validar tamaño: máx 1 MB
            if (file.size > 1 * 1024 * 1024) {
                Swal.fire({ icon:'warning', title:'Archivo muy grande', text:`La foto no debe superar 1 MB. El archivo pesa ${(file.size/1024/1024).toFixed(2)} MB.`, confirmButtonText:'Entendido' });
                this.value = ''; return;
            }

            // Preview
            const r = new FileReader();
            r.onload = e => {
                const prev = $('ndj_previewFoto');
                if (prev) { prev.src = e.target.result; prev.classList.remove('hidden'); }
                $('ndj_placeholderFoto')?.querySelector('i')?.classList.add('hidden');
                $('ndj_placeholderFoto')?.querySelector('span')?.classList.add('hidden');
                $('ndj_btnEliminarFoto')?.classList.remove('hidden');
            };
            r.readAsDataURL(file);
        });
        $('ndj_btnEliminarFoto')?.addEventListener('click', ndj_limpiarFoto);

        // Cerrar
        $('ndj_btnCerrar')?.addEventListener('click',  ndj_cerrar);
        $('ndj_btnCerrarX')?.addEventListener('click', ndj_cerrar);
        $('ndj_btnCerrarExcepcionEdad')?.addEventListener('click', ndj_cerrarModalExcepcionEdad);
        $('ndj_btnCancelarExcepcionEdad')?.addEventListener('click', ndj_cerrarModalExcepcionEdad);
        $('ndj_btnValidarExcepcionEdad')?.addEventListener('click', async function () {
            const usuario = $('ndj_usuario_excepcion')?.value?.trim();
            const clave = $('ndj_clave_excepcion')?.value || '';
            const fechaNacimiento = $('ndj_fecha_nacimiento')?.value || '';
            const boton = this;

            if (!usuario || !clave) {
                Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Ingrese usuario y contraseña.', confirmButtonText: 'Entendido' });
                return;
            }

            boton.disabled = true;
            try {
                const response = await fetch(`${VITE_URL_APP}/api/dj/validar-excepcion-edad`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('[name=_token]')?.value || '',
                    },
                    body: JSON.stringify({ usuario, clave, fecha_nacimiento: fechaNacimiento }),
                });
                const json = await response.json();

                if (!json.success) {
                    const titulosError = {
                        datos_incompletos: 'Datos incompletos',
                        clave_invalida: 'Contraseña incorrecta',
                        sin_rol_admin_rrhh: 'Sin autorización',
                        fecha_nacimiento_invalida: 'Fecha inválida',
                        reglas_edad_no_disponibles: 'Configuración de edad',
                        excepcion_no_requerida: 'No requiere excepción',
                    };
                    Swal.fire({
                        icon: 'error',
                        title: titulosError[json.code] || 'No autorizado',
                        text: json.message || 'No se pudieron validar las credenciales.',
                        confirmButtonText: 'Entendido',
                    });
                    return;
                }

                excepcionEdadValidada = true;
                fechaExcepcionAutorizada = fechaNacimiento;
                ndj_marcarEdadAutorizada($('ndj_fecha_nacimiento'));
                ndj_cerrarModalExcepcionEdad();
                await Swal.fire({
                    icon: 'success',
                    title: 'Autorización de ADMIN RRHH registrada',
                    text: `Autorizado por ${usuario}. Ahora puede guardar la DJ con la excepción de edad.`,
                    timer: 2200,
                    showConfirmButton: false,
                });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo validar la autorización.', confirmButtonText: 'Entendido' });
            } finally {
                boton.disabled = false;
            }
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !$('modalExcepcionEdad')?.classList.contains('hidden')) return;
            if (e.key === 'Escape' && !$('modalNuevaDJ')?.classList.contains('hidden')) ndj_cerrar();
        });

        // Tipo documento
        $('ndj_tipo_documento')?.addEventListener('change', function () {
            const inp = $('ndj_nro_documento'); if (!inp) return;
            inp.value = '';
            if (!this.value) { inp.disabled = true; inp.placeholder = 'Ingrese el número'; return; }
            inp.disabled = false;
            const map = {
                '0034':{ max:8,  ph:'Ej: 12345678',           mode:'numeric' },
                '0035':{ max:12, ph:'Ej: 000123456',           mode:'text'   },
                '0037':{ max:12, ph:'Ej: A1234567',            mode:'text'   },
                '0038':{ max:12, ph:'Ingrese doc. provisional',mode:'text'   },
                '0215':{ max:12, ph:'Ingrese cédula',          mode:'text'   },
                '0216':{ max:12, ph:'Ingrese carnet temporal', mode:'text'   },
            };
            const c = map[this.value] ?? { max:20, ph:'Ingrese el número', mode:'text' };
            inp.maxLength = c.max; inp.placeholder = c.ph; inp.inputMode = c.mode;
            ndj_toggleUbigeoNacimiento(true);
        });

        $('ndj_nro_documento')?.addEventListener('input', function () {
            if ($('ndj_tipo_documento')?.value === '0034') this.value = this.value.replace(/\D/g,'');
            else this.value = this.value.toUpperCase();
        });

        // Tipo personal
        $('ndj_sel_tipo_personal')?.addEventListener('change', function () { ndj_aplicarTipo(this.value); ndj_filtrarCargos(this.value); ndj_aplicarSctr(this.value); });

        // Institución → carrera
        $('ndj_institucion')?.addEventListener('change', function () { ndj_poblarCarreras(this.value); });

        // Ubigeos
        $('ndj_departamento_actual')?.addEventListener('change', function () { ndj_cargarProvincias(this.value,'ndj_provincia_actual','ndj_distrito_actual'); });
        $('ndj_provincia_actual')?.addEventListener('change',    function () { ndj_cargarDistritos(this.value,'ndj_distrito_actual'); });
        $('ndj_departamento_dni')?.addEventListener('change',    function () { ndj_cargarProvincias(this.value,'ndj_provincia_dni','ndj_distrito_dni'); });
        $('ndj_provincia_dni')?.addEventListener('change',       function () { ndj_cargarDistritos(this.value,'ndj_distrito_dni'); });
        $('ndj_departamento_nac')?.addEventListener('change',    function () { ndj_cargarProvincias(this.value,'ndj_provincia_nac','ndj_distrito_nac'); });
        $('ndj_provincia_nac')?.addEventListener('change',       function () { ndj_cargarDistritos(this.value,'ndj_distrito_nac'); });

        // País de nacimiento: reevalúa la regla de Dep/Prov/Dist
        // (DNI + país ≠ PERU → se ocultan y dejan de ser obligatorios).
        // limpiarCampos = true porque es un cambio manual del usuario.
        $('ndj_pais')?.addEventListener('change', function () {
            ndj_toggleUbigeoNacimiento(true);
        });

        // Familiar empresa / SUCAMEC / Clase brevete
        $('ndj_familiar_empresa')?.addEventListener('change', function () { $('ndj_div_familiar_interno')?.classList.toggle('hidden', this.value !== 'SI'); });
        $('ndj_clase_brevete')?.addEventListener('change', function () {
            const sel = $('ndj_tipo_vehiculo'); if (!sel) return;
            sel.innerHTML = '<option value="">-- Seleccione --</option>';
            (NDJ_CAT[this.value] || []).forEach(item => { const o = document.createElement('option'); o.value = item.val; o.textContent = item.text; sel.appendChild(o); });
        });

        // Familiares
        $('ndj_addFamilyMember')?.addEventListener('click', () => { $('ndj_familyContainer')?.appendChild(ndj_crearFila()); });
        $('ndj_familyContainer')?.addEventListener('click', e => {
            const btn = e.target.closest('.ndj-remove-family');
            if (btn) btn.closest('.ndj-family-row')?.remove();
        });
        $('ndj_familyContainer')?.addEventListener('change', e => {
            if (e.target.matches('select[name="ndj_parentesco[]"]')) {
                ndj_actualizarFechaFamiliar(e.target.closest('.ndj-family-row'));
            }
        });
        $('ndj_familyContainer')?.addEventListener('input', e => {
            if (e.target.matches('input[name="ndj_apellidosNombres[]"]')) {
                e.target.value = e.target.value.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, '');
            }
        });
        document.querySelectorAll('#ndj_familyContainer .ndj-family-row')
            .forEach(ndj_actualizarFechaFamiliar);

        // DNI y coincidencias
        tipoDocInput?.addEventListener('change', validarDocumento);
        docInput?.addEventListener('input',      validarDocumento);
        [nombre1, nombre2, apellido1, apellido2].forEach(input => { input?.addEventListener('input', buscarCoincidencias); });

        // Guardar
        $('ndj_btnGuardar')?.addEventListener('click', ndj_guardar);

        // API pública
        window.NuevaDJ = { abrir: ndj_abrir, cerrar: ndj_cerrar, abrirRecontratacion: ndj_abrirRecontratacion };
    });

})();
