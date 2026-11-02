/* De Gouden Lepel — kleine, afhankvrije progressive enhancements.
   Alle logica is opt-out: zonder JS werken alle links en formulieren gewoon. */
(function () {
    "use strict";

    // --- Mobiele navigatie ---------------------------------------------------
    var header = document.getElementById("siteHeader");
    var toggle = document.getElementById("navToggle");
    if (header && toggle) {
        var closeNav = function () {
            header.classList.remove("is-open");
            toggle.setAttribute("aria-expanded", "false");
        };
        toggle.addEventListener("click", function (e) {
            e.stopPropagation();
            var open = header.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", String(open));
        });
        // Sluit na selectie van een item (vooral op mobiel).
        header.addEventListener("click", function (e) {
            if (e.target.closest("#primaryNav a")) {
                closeNav();
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closeNav();
        });
    }

    // --- Flash-meldingen sluiten --------------------------------------------
    document.querySelectorAll(".flash").forEach(function (el) {
        var dismiss = function () {
            el.style.transition = "opacity .2s ease, transform .2s ease";
            el.style.opacity = "0";
            el.style.transform = "translateY(-.5rem)";
            setTimeout(function () { el.remove(); }, 220);
        };
        var close = el.querySelector(".flash__close");
        if (close) close.addEventListener("click", dismiss);
        setTimeout(dismiss, 6000);
    });

    // --- Menu-categorie filter ----------------------------------------------
    var filterBar = document.querySelector(".menu-filter");
    var sections = Array.prototype.slice.call(document.querySelectorAll(".menu-section"));
    if (filterBar && sections.length) {
        filterBar.hidden = false;
        var buttons = filterBar.querySelectorAll(".menu-filter__btn");
        buttons.forEach(function (btn) {
            btn.addEventListener("click", function () {
                var filter = btn.getAttribute("data-filter") || "all";
                buttons.forEach(function (b) {
                    b.classList.remove("is-active");
                    b.setAttribute("aria-pressed", "false");
                });
                btn.classList.add("is-active");
                btn.setAttribute("aria-pressed", "true");
                sections.forEach(function (sec) {
                    var cat = sec.getAttribute("data-category") || "__none__";
                    sec.hidden = filter !== "all" && cat !== filter;
                });
            });
        });
    }

    // --- Bevestigingsvraag vóór status-wijziging ----------------------------
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (e) {
            var msg = form.getAttribute("data-confirm");
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // --- Automatisch jaartal in de footer -----------------------------------
    var yearEl = document.getElementById("year");
    if (yearEl) yearEl.textContent = String(new Date().getFullYear());
})();
