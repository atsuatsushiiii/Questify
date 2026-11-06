/*
 * Code © 2026 Kevin Cyril Maraño, MIT license.
 * Progressive enhancements for the Questify static showcase.
 */

(function () {
    "use strict";

    var header = document.querySelector(".site-header");
    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    function updateHeader() {
        if (!header) {
            return;
        }

        header.classList.toggle("is-scrolled", window.scrollY > 8);
    }

    updateHeader();
    window.addEventListener("scroll", updateHeader, { passive: true });

    var revealItems = Array.prototype.slice.call(document.querySelectorAll(".reveal"));

    if (!("IntersectionObserver" in window) || reduceMotion.matches) {
        revealItems.forEach(function (item) {
            item.classList.add("is-visible");
        });
    } else {
        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                });
            },
            { rootMargin: "0px 0px -8% 0px", threshold: 0.06 }
        );

        revealItems.forEach(function (item) {
            observer.observe(item);
        });
    }

    var lightbox = document.getElementById("lightbox");
    var lightboxImage = document.getElementById("lightbox-image");
    var lightboxCaption = document.getElementById("lightbox-caption");
    var closeButton = lightbox ? lightbox.querySelector("[data-lightbox-close]") : null;
    var lastFocused = null;

    function closeLightbox() {
        if (lightbox && lightbox.open) {
            lightbox.close();
        }
    }

    if (lightbox && lightboxImage && lightboxCaption) {
        document.querySelectorAll(".shot[data-full]").forEach(function (button) {
            button.addEventListener("click", function () {
                var image = button.querySelector("img");
                lastFocused = button;
                lightboxImage.src = button.getAttribute("data-full");
                lightboxImage.alt = image ? image.alt : "Questify screenshot";
                lightboxCaption.textContent = button.getAttribute("data-caption") || "";
                lightbox.showModal();
            });
        });

        if (closeButton) {
            closeButton.addEventListener("click", closeLightbox);
        }

        lightbox.addEventListener("click", function (event) {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        lightbox.addEventListener("close", function () {
            lightboxImage.removeAttribute("src");
            if (lastFocused) {
                lastFocused.focus();
                lastFocused = null;
            }
        });
    }
})();
