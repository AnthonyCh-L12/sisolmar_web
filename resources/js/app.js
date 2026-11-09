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
        var pageUrl = window.location.href.split(/[?#]/)[0];
        document
            .querySelectorAll("ul.admin-menu .menu-item a")
            .forEach((element) => {
                if (element.href === pageUrl) {
                    element.classList.add("active");

                    let parentMenuItem = element.closest(".menu-item");
                    parentMenuItem.classList.add("active");

                    let parentMenu =
                        element.parentElement.parentElement.parentElement
                            .parentElement;
                    if (
                        parentMenu &&
                        parentMenu.classList.contains("menu-item")
                    ) {
                        const collapseElement = parentMenu.querySelector(
                            ".hs-accordion-toggle",
                        );

                        if (collapseElement) {
                            // collapseElement.classList.add("active");
                            collapseElement.classList.add("open");
                            parentMenu.classList.add("active");
                            const nextE = collapseElement.nextElementSibling;
                            if (nextE) {
                                nextE.classList.remove("hidden");
                            }
                        }
                    }
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
