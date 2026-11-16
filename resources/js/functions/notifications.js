document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    setInterval(loadNotifications, 30000); // 30 segundos
});

async function loadNotifications() {
    const list = document.getElementById('notif-list');
    const badge = document.getElementById('notif-count');
    if (!list || !badge) return;

    try {
        const response = await fetch(list.dataset.etapa2Url || '/api/notificaciones/pendientes-etapa2', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = await response.json();

        let hijosResult = { success: true, total: 0, avisos: { '30': [], '15': [] } };
        try {
            const hijosResponse = await fetch(list.dataset.mayoriaUrl || '/api/notificaciones/hijos-por-cumplir-mayoria-edad', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (hijosResponse.ok) hijosResult = await hijosResponse.json();
        } catch (error) {
            console.error('Error cargando avisos de mayoría de edad', error);
        }
        const hijosAvisos = hijosResult.avisos || { '30': [], '15': [] };
        const totalHijos = Number(hijosResult.total) || 0;
        const hijosHtml = totalHijos > 0 ? `
            <button type="button" onclick="window.abrirDetalleMayoriaEdad?.()" class="w-full text-left flex items-center justify-between px-4 py-4 hover:bg-slate-50 transition-colors border-b border-gray-100 group">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 text-orange-600 border border-orange-200 shadow-sm"><i class="bx bx-child text-xl"></i></div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-800 group-hover:text-primary">Asignación familiar próxima a vencer</span>
                        <span class="text-[11px] text-gray-500 leading-snug mt-1">Aviso inicial (hasta 30 días): ${(hijosAvisos['30'] || []).length} · Recordatorio (15 días o menos): ${(hijosAvisos['15'] || []).length}</span>
                        <span class="text-[11px] text-gray-500 leading-none mt-1">Haz clic para ver el detalle por sucursal</span>
                    </div>
                </div>
                <span class="flex-shrink-0 flex items-center justify-center min-w-[28px] h-7 bg-orange-100 text-orange-700 border border-orange-200 text-xs font-bold px-2 rounded-full">${totalHijos}</span>
            </button>` : '';

        // Elementos del Modal
        const modalList = document.getElementById('modal-etapa2-list');
        const modalTotal = document.getElementById('modal-etapa2-total');

        // ====================================================
        // PREVENIR BORRADO DE ALERTAS SIP DEMANDA
        // ====================================================
        const demandasVivas = Array.from(list.querySelectorAll('[id^="campana-demanda-"]')).map(el => el.outerHTML).join('');
        const cantidadDemandas = list.querySelectorAll('[id^="campana-demanda-"]').length;

        if (!result.success || Number(result.total) === 0) {
            // Mantener solicitudes SIP y avisos de mayoría de edad aunque Etapa 2 esté vacío.
            if (demandasVivas === '' && hijosHtml === '') {
                list.innerHTML = `
                    <div class="px-4 py-8 text-center flex flex-col items-center justify-center gap-2">
                        <i class="bx bx-check-shield text-4xl text-green-500"></i>
                        <span class="font-bold text-gray-700">¡Todo al día!</span>
                        <span class="text-xs text-gray-400">No hay notificaciones pendientes.</span>
                    </div>`;
                badge.classList.add('hidden');
            } else {
                list.innerHTML = demandasVivas + hijosHtml;
                badge.textContent = cantidadDemandas + (totalHijos > 0 ? 1 : 0);
                badge.classList.remove('hidden');
            }
            return;
        }

        // ====================================================
        // 1. UI DE LA CAMPANITA (Solo el resumen)
        // ====================================================
        list.innerHTML = demandasVivas + hijosHtml + `
            <button type="button" onclick="document.getElementById('btn-open-modal-etapa2').click()" class="w-full text-left flex items-center justify-between px-4 py-4 hover:bg-slate-50 transition-colors border-b border-gray-100 group">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 text-orange-600 group-hover:bg-orange-500 group-hover:text-white transition-colors border border-orange-200 shadow-sm">
                        <i class="bx bx-task text-xl"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-800 group-hover:text-primary transition-colors">Pendiente actualización Anual</span>
                        
                        <!-- Contenedor de Etiquetas (Submódulos) -->
                        <div class="flex flex-wrap items-center gap-1.5 mt-1 mb-0.5">
                            <span class="whitespace-nowrap bg-blue-100 text-blue-700 text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm">MÓDULO DJ</span>
                            <span class="whitespace-nowrap bg-teal-100 text-teal-700 text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm">ACTUALIZACIÓN</span>
                        </div>
                        
                        <!-- Texto de ayuda en su propia línea -->
                        <span class="text-[11px] text-gray-500 leading-none mt-0.5">Haz clic para ver el detalle</span>
                    </div>
                </div>
                
                <div class="flex-shrink-0 flex items-center justify-center min-w-[28px] h-7 bg-red-100 text-red-600 border border-red-200 text-xs font-bold px-2 rounded-full shadow-sm">
                    ${result.total}
                </div>
            </button>
        `;

        // ====================================================
        // 2. UI DEL MODAL (Detalle por sucursales)
        // ====================================================
        if (modalTotal) modalTotal.textContent = result.total;
        
        let modalHtml = [];
        for (const [sucursal, cantidad] of Object.entries(result.data)) {
            modalHtml.push(`
                <div class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
                            <i class="bx bx-store-alt text-lg"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700 uppercase">${sucursal}</span>
                    </div>
                    <span class="text-xs font-bold text-red-600 bg-red-50 px-2.5 py-1 rounded-full border border-red-100 shadow-sm">
                        ${cantidad} pend.
                    </span>
                </div>
            `);
        }
        if(modalList) modalList.innerHTML = modalHtml.join('');

        // Globito rojo de la campana (Sumamos Etapa 2 + Demandas)
        let totalFinal = result.total + cantidadDemandas + (totalHijos > 0 ? 1 : 0);
        badge.textContent = totalFinal > 99 ? '99+' : totalFinal;
        badge.classList.remove('hidden');

    } catch (error) {
        console.error('Error cargando notificaciones', error);
        list.innerHTML = `
            <div class="px-4 py-6 text-center text-red-500 flex flex-col items-center">
                <i class="bx bx-error text-3xl mb-2"></i>
                <span class="font-bold text-sm">Error de sincronización</span>
            </div>`;
    }
}
