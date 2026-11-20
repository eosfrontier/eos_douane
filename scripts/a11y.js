(function () {
    "use strict";

    var KEY_ENABLED = "douane_a11y";
    var KEY_THEME = "douane_theme";

    function readStorage(key, fallback) {
        try {
            var val = localStorage.getItem(key);
            return val === null ? fallback : val;
        } catch (e) {
            return fallback;
        }
    }

    function writeStorage(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {}
    }

    var initialized = false;

    function syncMovingBackground(enabled) {
        var bg = document.querySelector(".background");
        var moving = document.getElementById("moving-background");
        if (!moving) {
            return;
        }
        if (enabled) {
            moving.style.display = "none";
            moving.style.animationPlayState = "paused";
        } else {
            moving.style.display = "";
            moving.style.animationPlayState = "";
            if (bg) {
                moving.style.height = bg.offsetHeight + "px";
            }
        }
    }

    function initAccessibility() {
        if (initialized) {
            return;
        }
        var root = document.documentElement;
        var toggleBtn = document.getElementById("a11y-toggle");
        if (!toggleBtn) {
            return;
        }
        initialized = true;
        var themeGroup = document.getElementById("a11y-theme-group");
        var stateBadge = toggleBtn.querySelector(".a11y-toggle-state");
        var themeButtons = themeGroup
            ? Array.prototype.slice.call(themeGroup.querySelectorAll(".a11y-theme-btn[data-theme-choice]"))
            : [];

        var savedEnabled = readStorage(KEY_ENABLED, "0") === "1";
        var savedTheme = readStorage(KEY_THEME, "dark") === "light" ? "light" : "dark";

        function applyState(enabled, theme, persist) {
            var activeTheme = (theme === "light") ? "light" : "dark";

            if (enabled) {
                root.classList.add("a11y-enabled");
                if (activeTheme === "light") {
                    root.classList.add("theme-light");
                } else {
                    root.classList.remove("theme-light");
                }
            } else {
                root.classList.remove("a11y-enabled");
                root.classList.remove("theme-light");
            }

            if (toggleBtn) {
                toggleBtn.setAttribute("aria-pressed", enabled ? "true" : "false");
                if (enabled) {
                    toggleBtn.classList.add("is-active");
                } else {
                    toggleBtn.classList.remove("is-active");
                }
            }
            if (stateBadge) {
                stateBadge.textContent = enabled ? "ON" : "OFF";
            }
            if (themeGroup) {
                themeGroup.hidden = !enabled;
                themeGroup.style.display = enabled ? "" : "none";
            }

            themeButtons.forEach(function (btn) {
                var choice = btn.getAttribute("data-theme-choice");
                var selected = (choice === activeTheme);
                btn.setAttribute("aria-pressed", selected ? "true" : "false");
                if (selected) {
                    btn.classList.add("is-selected");
                } else {
                    btn.classList.remove("is-selected");
                }
            });

            if (persist) {
                writeStorage(KEY_ENABLED, enabled ? "1" : "0");
                writeStorage(KEY_THEME, activeTheme);
            }

            syncMovingBackground(enabled);
        }

        if (toggleBtn) {
            toggleBtn.addEventListener("click", function () {
                savedEnabled = !savedEnabled;
                applyState(savedEnabled, savedTheme, true);
            });
        }

        themeButtons.forEach(function (btn) {
            btn.addEventListener("click", function () {
                var choice = btn.getAttribute("data-theme-choice");
                savedTheme = (choice === "light") ? "light" : "dark";
                savedEnabled = true;
                applyState(savedEnabled, savedTheme, true);
            });
        });

        if (typeof window !== "undefined" && typeof window.addEventListener === "function") {
            window.addEventListener("storage", function (e) {
                if (!e || (e.key !== KEY_ENABLED && e.key !== KEY_THEME)) {
                    return;
                }
                savedEnabled = readStorage(KEY_ENABLED, "0") === "1";
                savedTheme = readStorage(KEY_THEME, "dark") === "light" ? "light" : "dark";
                applyState(savedEnabled, savedTheme, false);
            });
        }

        applyState(savedEnabled, savedTheme, false);
    }

    if (typeof document !== "undefined") {
        if (document.getElementById("a11y-toggle")) {
            initAccessibility();
        } else if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initAccessibility);
        } else {
            initAccessibility();
        }
    }
})();
