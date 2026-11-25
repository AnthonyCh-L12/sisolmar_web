import "dropzone/dist/dropzone-min";

import "preline/dist/index.js";
import "jquery";
import "simplebar";
import "boxicons/css/boxicons.min.css";
import "./functions/capacitacion/reportes_capacitaciones.js";
import "./functions/capacitacion/planes_capacitaciones.js";
import "./functions/capacitacion/planes_capacitaciones_new.js";
import "./functions/capacitacion/exportar_examenes.js";
import "./functions/capacitacion/cursos_externos.js";
import Waves from "node-waves";
import Alpine from "alpinejs";
import "vanilla-datatables/dist/vanilla-dataTables.min.css"; // Import Styles
import Swal from "sweetalert2";

window.Swal = Swal;
window.Alpine = Alpine;
Alpine.start();

class App {
    constructor() {
        this.html = document.getElementsByTagName("html")[0];
        this.config = {};
        this.defaultConfig = window.config;
    }

    initComponents() {
        // Wave Effect
        Waves.init();
    }

    initSidenav() {
        var self = this;

        // Normaliza una URL a solo su ruta, sin query, hash ni barra final.
        function normalizePath(value) {
            try {
                const url = new URL(value, window.location.origin);
                const path = decodeURIComponent(url.pathname).replace(
                    /\/+$/,
                    "",
                );
                return path === "" ? "/" : path;
            } catch (_) {
                return "";
            }
        }

        const currentPath = normalizePath(
            window.location.href.split(/[?#]/)[0],
        );

        const menuLinks = Array.from(
            document.querySelectorAll("ul.admin-menu .menu-item a[href]"),
        ).filter((link) => {
            const href = link.getAttribute("href") || "";
            return href !== "" && !href.startsWith("javascript:");
        });

        // Coincidencia exacta; si la vista actual es de tercer nivel
        // (modulo/vista/subvista) se usa la ruta más específica que la contenga.
        const exactMatch = menuLinks.find(
            (link) => normalizePath(link.href) === currentPath,
        );
        const prefixMatches = menuLinks
            .filter((link) => {
                const linkPath = normalizePath(link.href);
                return linkPath !== "/" && currentPath.startsWith(linkPath + "/");
            })
            .sort(
                (a, b) =>
                    normalizePath(b.href).length - normalizePath(a.href).length,
            );

        const activeLinks = exactMatch ? [exactMatch] : prefixMatches.slice(0, 1);

        activeLinks.forEach((element) => {
            element.classList.add("active");

            const parentMenuItem = element.closest(".menu-item");
            if (parentMenuItem) {
                parentMenuItem.classList.add("active");
            }

            // Abre TODOS los acordeones padre (grupo > módulo). Antes solo se
            // abría el nivel inmediato, por lo que al navegar el grupo quedaba
            // cerrado. Se usa la clase `active` que espera Preline y no `open`.
            let accordion = element.closest(".hs-accordion");
            while (accordion) {
                accordion.classList.add("active");

                const toggle = accordion.querySelector(
                    ":scope > .hs-accordion-toggle",
                );
                const content = accordion.querySelector(
                    ":scope > .hs-accordion-content",
                );

                if (toggle) {
                    toggle.setAttribute("aria-expanded", "true");
                }

                if (content) {
                    content.classList.remove("hidden");
                    content.style.display = "block";
                    content.style.height = "";
                }

                accordion = accordion.parentElement
                    ? accordion.parentElement.closest(".hs-accordion")
                    : null;
            }
        });

        setTimeout(function () {
            var activatedItem = document.querySelector(
                "ul.admin-menu .menu-item.active a.active",
            );
            if (activatedItem != null) {
                var simplebarContent = document.querySelector(
                    "#app-menu .simplebar-content-wrapper",
                );

                var offset = activatedItem.offsetTop - 300;
                if (simplebarContent && offset > 100) {
                    scrollTo(simplebarContent, offset, 600);
                }
            }
        }, 200);

        // scrollTo (Sidenav Active Menu)
        function easeInOutQuad(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return (c / 2) * t * t + b;
            t--;
            return (-c / 2) * (t * (t - 2) - 1) + b;
        }

        function scrollTo(element, to, duration) {
            var start = element.scrollTop,
                change = to - start,
                currentTime = 0,
                increment = 20;
            var animateScroll = function () {
                currentTime += increment;
                var val = easeInOutQuad(currentTime, start, change, duration);
                element.scrollTop = val;
                if (currentTime < duration) {
                    setTimeout(animateScroll, increment);
                }
            };
            animateScroll();
        }
    }

    reverseQuery(element, query) {
        while (element) {
            if (element.parentElement) {
                if (element.parentElement.querySelector(query) === element)
                    return element;
            }
            element = element.parentElement;
        }
        return null;
    }

    // Topbar Fullscreen Button
    initfullScreenListener() {
        var self = this;
        var fullScreenBtn = document.querySelector(
            '[data-toggle="fullscreen"]',
        );

        if (fullScreenBtn) {
            fullScreenBtn.addEventListener("click", function (e) {
                e.preventDefault();
                document.body.classList.toggle("group-fullscreen");
                if (
                    !document.fullscreenElement &&
                    !document.mozFullScreenElement &&
                    !document.webkitFullscreenElement
                ) {
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                    } else if (document.documentElement.mozRequestFullScreen) {
                        document.documentElement.mozRequestFullScreen();
                    } else if (
                        document.documentElement.webkitRequestFullscreen
                    ) {
                        document.documentElement.webkitRequestFullscreen(
                            Element.ALLOW_KEYBOARD_INPUT,
                        );
                    }
                } else {
                    if (document.cancelFullScreen) {
                        document.cancelFullScreen();
                    } else if (document.mozCancelFullScreen) {
                        document.mozCancelFullScreen();
                    } else if (document.webkitCancelFullScreen) {
                        document.webkitCancelFullScreen();
                    }
                }
            });
        }
    }

    // Dark Mode Toggle
    initThemeToggle() {
        const themeToggle = document.getElementById("theme-toggle");
        const iconLight = document.getElementById("theme-icon-light");
        const iconDark = document.getElementById("theme-icon-dark");

        if (themeToggle && iconLight && iconDark) {
            // Sincronizar iconos con el estado actual
            const updateIcons = () => {
                const isDark =
                    document.documentElement.classList.contains("dark");
                if (isDark) {
                    iconLight.classList.add("hidden");
                    iconDark.classList.remove("hidden");
                } else {
                    iconLight.classList.remove("hidden");
                    iconDark.classList.add("hidden");
                }
            };

            // Inicializar iconos
            updateIcons();

            themeToggle.addEventListener("click", function () {
                const html = document.documentElement;

                if (html.classList.contains("dark")) {
                    html.classList.remove("dark");
                    localStorage.setItem("theme", "light");
                } else {
                    html.classList.add("dark");
                    localStorage.setItem("theme", "dark");
                }

                updateIcons();
            });
        }
    }

    init() {
        this.initComponents();
        this.initSidenav();
        this.initfullScreenListener();
        this.initThemeToggle();
    }
}

document.addEventListener("DOMContentLoaded", function () {
    new App().init();
});
