(function () {
    "use strict";

    var EMPTY_TOKEN = "__EMPTY__";
    var MAX_SORT_COLS = 2;

    var textCollator = (typeof Intl !== "undefined" && typeof Intl.Collator === "function")
        ? new Intl.Collator(undefined, { numeric: true, sensitivity: "base" })
        : null;

    function normalizeString(value) {
        return String(value == null ? "" : value).replace(/\s+/g, " ").trim();
    }

    function compareText(a, b) {
        if (textCollator) {
            return textCollator.compare(a, b);
        }
        var la = a.toLowerCase();
        var lb = b.toLowerCase();
        if (la < lb) { return -1; }
        if (la > lb) { return 1; }
        return 0;
    }

    /**
     * Parses DD-MM-YYY (treating optional NT / N.T. suffix as the same year),
     * with a fallback for ISO YYYY-MM-DD dates.
     */
    function parseDateParts(raw) {
        var str = normalizeString(raw);
        if (!str) {
            return null;
        }

        // Primary format: DD-MM-YYY (YYY and YYYNT treated as the same year)
        var dmy = /^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{1,4})(?:\s*n\.?t\.?)?$/i.exec(str);
        if (dmy) {
            var day = parseInt(dmy[1], 10);
            var month = parseInt(dmy[2], 10);
            var year = parseInt(dmy[3], 10);
            if (day >= 1 && day <= 99 && month >= 1 && month <= 99) {
                return {
                    day: day,
                    month: month,
                    year: year,
                    key: (year * 10000) + (month * 100) + day
                };
            }
        }

        // Fallback: ISO YYYY-MM-DD (optional NT / N.T. suffix)
        var iso = /^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:\s*n\.?t\.?)?$/i.exec(str);
        if (iso) {
            var isoYear = parseInt(iso[1], 10);
            var isoMonth = parseInt(iso[2], 10);
            var isoDay = parseInt(iso[3], 10);
            if (isoMonth >= 1 && isoMonth <= 12 && isoDay >= 1 && isoDay <= 31) {
                return {
                    day: isoDay,
                    month: isoMonth,
                    year: isoYear,
                    key: (isoYear * 10000) + (isoMonth * 100) + isoDay
                };
            }
        }

        return null;
    }

    function parseDateKey(raw) {
        var parts = parseDateParts(raw);
        return parts ? parts.key : null;
    }

    function stripYearNtSuffix(str) {
        return str.replace(/(\d)\s*n\.?t\.?(?=$|\s)/gi, "$1");
    }

    function pad2(n) {
        return n < 10 ? "0" + n : String(n);
    }

    function pad3(n) {
        if (n < 10) { return "00" + n; }
        if (n < 100) { return "0" + n; }
        return String(n);
    }

    function matchesLeadingBoundary(haystack, needle) {
        if (!needle) {
            return true;
        }
        var startsWithDigit = /^\d/.test(needle);
        var idx = haystack.indexOf(needle);
        while (idx !== -1) {
            if (!startsWithDigit || idx === 0 || !/\d/.test(haystack.charAt(idx - 1))) {
                return true;
            }
            idx = haystack.indexOf(needle, idx + 1);
        }
        return false;
    }

    /**
     * Filters DD-MM-YYY dates while treating YYY and YYYNT (and zero-padded
     * vs unpadded day/month segments) as equivalent.
     */
    function matchesDateFilter(rawCell, rawQuery) {
        var query = normalizeString(rawQuery).toLowerCase();
        if (!query) {
            return true;
        }
        var cell = normalizeString(rawCell).toLowerCase();
        if (!cell) {
            return false;
        }

        var parsedCell = parseDateParts(rawCell);
        var parsedQuery = parseDateParts(rawQuery);
        if (parsedCell && parsedQuery) {
            return parsedCell.key === parsedQuery.key;
        }

        // Normalize NT suffix attached to a year digit and date separators
        var queryCanon = stripYearNtSuffix(query).replace(/[/.]/g, "-");
        var cellCanon = stripYearNtSuffix(cell).replace(/[/.]/g, "-");

        if (queryCanon.indexOf("-") === -1) {
            if (cell.indexOf(query) !== -1 || cellCanon.indexOf(queryCanon) !== -1) {
                return true;
            }
        } else if (matchesLeadingBoundary(cell, query) || matchesLeadingBoundary(cellCanon, queryCanon)) {
            return true;
        }

        // Also match padded DD-MM-YYY and unpadded D-M-Y representations
        if (parsedCell) {
            var paddedCell = pad2(parsedCell.day) + "-" + pad2(parsedCell.month) + "-" + pad3(parsedCell.year);
            var unpaddedCell = parsedCell.day + "-" + parsedCell.month + "-" + parsedCell.year;
            if (matchesLeadingBoundary(paddedCell, queryCanon)) {
                return true;
            }
            var unpaddedQuery = queryCanon.split("-").map(function (part) {
                return (/^\d+$/.test(part) && parseInt(part, 10) > 0) ? String(parseInt(part, 10)) : part;
            }).join("-");
            if (matchesLeadingBoundary(unpaddedCell, unpaddedQuery)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parses HH:MM[:SS] (and optional AM/PM) into total seconds since midnight.
     */
    function parseTimeKey(raw) {
        var str = normalizeString(raw);
        if (!str) {
            return null;
        }

        var m = /^(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*(am|pm))?$/i.exec(str);
        if (!m) {
            return null;
        }

        var hours = parseInt(m[1], 10);
        var minutes = parseInt(m[2], 10);
        var seconds = m[3] !== undefined ? parseInt(m[3], 10) : 0;
        var meridiem = m[4] ? m[4].toLowerCase() : null;

        if (minutes > 59 || seconds > 59) {
            return null;
        }

        if (meridiem) {
            if (hours < 1 || hours > 12) {
                return null;
            }
            if (meridiem === "pm" && hours < 12) {
                hours += 12;
            } else if (meridiem === "am" && hours === 12) {
                hours = 0;
            }
        } else if (hours > 23) {
            return null;
        }

        return (hours * 3600) + (minutes * 60) + seconds;
    }

    function compareNullable(aVal, bVal, sortType, mul) {
        var aEmpty = aVal === "";
        var bEmpty = bVal === "";
        if (aEmpty && bEmpty) {
            return 0;
        }
        // Keep empty cells at the bottom regardless of sort direction
        if (aEmpty) {
            return 1;
        }
        if (bEmpty) {
            return -1;
        }

        if (sortType === "date") {
            var da = parseDateKey(aVal);
            var db = parseDateKey(bVal);
            if (da !== null && db !== null) {
                if (da !== db) {
                    return (da < db ? -1 : 1) * mul;
                }
                return 0;
            }
            if (da !== null) { return -1; }
            if (db !== null) { return 1; }
            return compareText(aVal, bVal) * mul;
        }

        if (sortType === "time") {
            var ta = parseTimeKey(aVal);
            var tb = parseTimeKey(bVal);
            if (ta !== null && tb !== null) {
                if (ta !== tb) {
                    return (ta < tb ? -1 : 1) * mul;
                }
                return 0;
            }
            if (ta !== null) { return -1; }
            if (tb !== null) { return 1; }
            return compareText(aVal, bVal) * mul;
        }

        return compareText(aVal, bVal) * mul;
    }

    function readCellValue(cell) {
        if (!cell) {
            return "";
        }
        var attr = cell.getAttribute("data-value");
        if (attr !== null) {
            return normalizeString(attr);
        }
        var submitInput = cell.querySelector("input[type='submit']");
        if (submitInput) {
            return normalizeString(submitInput.value);
        }
        var tooltip = cell.querySelector(".tooltip");
        if (tooltip) {
            return normalizeString(tooltip.textContent);
        }
        return normalizeString(cell.textContent);
    }

    function parseSortSpec(spec) {
        if (!spec) {
            return [];
        }
        var result = [];
        spec.split(",").forEach(function (part) {
            var trimmed = part.trim();
            if (!trimmed || result.length >= MAX_SORT_COLS) {
                return;
            }
            var pieces = trimmed.split(":");
            var col = parseInt(pieces[0], 10);
            if (isNaN(col)) {
                return;
            }
            var dir = (pieces[1] && pieces[1].toLowerCase() === "desc") ? "desc" : "asc";
            result.push({ col: col, dir: dir });
        });
        return result;
    }

    function sortOrdersEqual(a, b) {
        if (a.length !== b.length) {
            return false;
        }
        for (var i = 0; i < a.length; i++) {
            if (a[i].col !== b[i].col || a[i].dir !== b[i].dir) {
                return false;
            }
        }
        return true;
    }

    function updateBackgroundHeight() {
        var bg = document.querySelector(".background");
        var moving = document.getElementById("moving-background");
        if (bg && moving) {
            moving.style.height = bg.offsetHeight + "px";
        }
    }

    function initBastionTable() {
        var table = document.getElementById("bastion-table");
        if (!table) {
            return;
        }

        var tbody = table.querySelector("tbody");
        if (!tbody) {
            return;
        }

        var countEl = document.getElementById("bastion-count");
        var clearAllBtn = document.getElementById("bastion-clear-filters");
        var sortPillsEl = document.getElementById("bastion-sort-pills");
        var resetSortBtn = document.getElementById("bastion-reset-sort");
        var clearSortBtn = document.getElementById("bastion-clear-sort");
        var emptyRow = tbody.querySelector("tr.bastion-empty-row");
        var headerCells = Array.prototype.slice.call(table.querySelectorAll("th.bastion-sort-cell"));
        var filterControls = Array.prototype.slice.call(table.querySelectorAll("[data-filter-col]"));
        var rawRows = Array.prototype.slice.call(tbody.querySelectorAll("tr.travel-line"));

        var columnMeta = {};
        headerCells.forEach(function (th) {
            var col = parseInt(th.getAttribute("data-col"), 10);
            var labelEl = th.querySelector(".bastion-sort-label");
            if (!labelEl) {
                labelEl = th.querySelector(".bastion-sort-btn > span");
            }
            columnMeta[col] = {
                th: th,
                label: labelEl ? normalizeString(labelEl.textContent) : ("Col " + col),
                sortType: th.getAttribute("data-sort-type") || "text"
            };
        });

        var rowRecords = rawRows.map(function (tr, idx) {
            var baseAttr = tr.getAttribute("data-base-index");
            var baseIndex = (baseAttr !== null && !isNaN(parseInt(baseAttr, 10)))
                ? parseInt(baseAttr, 10)
                : idx;
            var cells = Array.prototype.slice.call(tr.children);
            var values = cells.map(readCellValue);
            var lowerValues = values.map(function (val) {
                return val.toLowerCase();
            });
            return {
                el: tr,
                baseIndex: baseIndex,
                values: values,
                lowerValues: lowerValues
            };
        });

        var defaultSortOrder = parseSortSpec(table.getAttribute("data-default-sort") || "2:desc,3:desc");
        var sortOrder = defaultSortOrder.map(function (item) {
            return { col: item.col, dir: item.dir };
        });

        function findSortIndex(col) {
            for (var i = 0; i < sortOrder.length; i++) {
                if (sortOrder[i].col === col) {
                    return i;
                }
            }
            return -1;
        }

        function updateHeaderIndicators() {
            headerCells.forEach(function (th) {
                var col = parseInt(th.getAttribute("data-col"), 10);
                var btn = th.querySelector(".bastion-sort-btn");
                var icon = th.querySelector(".bastion-sort-icon");
                var rankEl = th.querySelector(".bastion-sort-rank");
                var label = columnMeta[col] ? columnMeta[col].label : "column";
                var idx = findSortIndex(col);

                if (idx !== -1) {
                    var entry = sortOrder[idx];
                    var rank = idx + 1;
                    var isAsc = entry.dir === "asc";
                    var rankWord = rank === 1 ? "primary" : "secondary";

                    th.setAttribute("aria-sort", isAsc ? "ascending" : "descending");
                    th.setAttribute("data-sort-rank", String(rank));

                    if (icon) {
                        icon.classList.remove("fa-sort", "fa-sort-up", "fa-sort-down");
                        icon.classList.add(isAsc ? "fa-sort-up" : "fa-sort-down");
                    }
                    if (rankEl) {
                        rankEl.textContent = String(rank);
                        rankEl.hidden = false;
                    }
                    if (btn) {
                        btn.title = label + ": " + rankWord + " sort (" + (isAsc ? "ascending" : "descending") + "). Click to " + (isAsc ? "sort descending." : "remove from sort.");
                    }
                } else {
                    th.setAttribute("aria-sort", "none");
                    th.removeAttribute("data-sort-rank");

                    if (icon) {
                        icon.classList.remove("fa-sort", "fa-sort-up", "fa-sort-down");
                        icon.classList.add("fa-sort");
                    }
                    if (rankEl) {
                        rankEl.textContent = "";
                        rankEl.hidden = true;
                    }
                    if (btn) {
                        var nextSlotLabel;
                        if (sortOrder.length === 0) {
                            nextSlotLabel = "set as primary sort (ascending)";
                        } else if (sortOrder.length === 1) {
                            nextSlotLabel = "add as secondary sort (ascending)";
                        } else {
                            nextSlotLabel = "replace secondary sort (ascending)";
                        }
                        btn.title = "Sort by " + label + " — click to " + nextSlotLabel + ".";
                    }
                }
            });
        }

        function renderSortBar() {
            if (sortPillsEl) {
                while (sortPillsEl.firstChild) {
                    sortPillsEl.removeChild(sortPillsEl.firstChild);
                }

                if (sortOrder.length === 0) {
                    var emptySpan = document.createElement("span");
                    emptySpan.className = "bastion-sort-none";
                    emptySpan.textContent = "None (database order)";
                    sortPillsEl.appendChild(emptySpan);
                } else {
                    sortOrder.forEach(function (entry, idx) {
                        if (idx === 1) {
                            var swapBtn = document.createElement("button");
                            swapBtn.type = "button";
                            swapBtn.className = "bastion-sort-swap";
                            swapBtn.title = "Swap primary and secondary sort";
                            swapBtn.setAttribute("aria-label", "Swap primary and secondary sort");
                            swapBtn.innerHTML = '<i class="fas fa-exchange-alt" aria-hidden="true"></i>';
                            swapBtn.addEventListener("click", swapSortOrder);
                            sortPillsEl.appendChild(swapBtn);
                        }

                        var meta = columnMeta[entry.col];
                        var label = meta ? meta.label : ("Col " + entry.col);
                        var isAsc = entry.dir === "asc";
                        var rank = idx + 1;

                        var pill = document.createElement("span");
                        pill.className = "bastion-sort-pill bastion-sort-pill-" + rank;
                        pill.setAttribute("data-sort-col", String(entry.col));
                        pill.setAttribute("data-sort-rank", String(rank));

                        var cycleBtn = document.createElement("button");
                        cycleBtn.type = "button";
                        cycleBtn.className = "bastion-sort-pill-main";
                        cycleBtn.title = isAsc
                            ? ("Flip " + label + " to descending")
                            : ("Remove " + label + " from sort");

                        var rankBadge = document.createElement("span");
                        rankBadge.className = "bastion-pill-rank";
                        rankBadge.textContent = String(rank);

                        var textNode = document.createElement("span");
                        textNode.className = "bastion-pill-label";
                        textNode.textContent = label;

                        var dirIcon = document.createElement("i");
                        dirIcon.className = "fas " + (isAsc ? "fa-sort-up" : "fa-sort-down") + " bastion-pill-icon";
                        dirIcon.setAttribute("aria-hidden", "true");

                        cycleBtn.appendChild(rankBadge);
                        cycleBtn.appendChild(textNode);
                        cycleBtn.appendChild(dirIcon);
                        cycleBtn.addEventListener("click", (function (c) {
                            return function () { toggleColumnSort(c); };
                        })(entry.col));

                        var removeBtn = document.createElement("button");
                        removeBtn.type = "button";
                        removeBtn.className = "bastion-sort-pill-remove";
                        removeBtn.setAttribute("aria-label", "Remove " + label + " from sort");
                        removeBtn.title = "Remove " + label + " from sort";
                        removeBtn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
                        removeBtn.addEventListener("click", (function (c) {
                            return function () { removeColumnSort(c); };
                        })(entry.col));

                        pill.appendChild(cycleBtn);
                        pill.appendChild(removeBtn);
                        sortPillsEl.appendChild(pill);
                    });
                }
            }

            if (resetSortBtn) {
                resetSortBtn.disabled = sortOrdersEqual(sortOrder, defaultSortOrder);
            }
            if (clearSortBtn) {
                clearSortBtn.disabled = sortOrder.length === 0;
            }
        }

        function applySort() {
            rowRecords.sort(function (a, b) {
                for (var i = 0; i < sortOrder.length; i++) {
                    var entry = sortOrder[i];
                    var col = entry.col;
                    var mul = entry.dir === "asc" ? 1 : -1;
                    var sortType = columnMeta[col] ? columnMeta[col].sortType : "text";
                    var cmp = compareNullable(a.values[col] || "", b.values[col] || "", sortType, mul);
                    if (cmp !== 0) {
                        return cmp;
                    }
                }
                return a.baseIndex - b.baseIndex;
            });

            var frag = document.createDocumentFragment();
            rowRecords.forEach(function (rec) {
                frag.appendChild(rec.el);
            });
            if (emptyRow) {
                frag.appendChild(emptyRow);
            }
            tbody.appendChild(frag);

            updateHeaderIndicators();
            renderSortBar();
        }

        /**
         * 3-state click cycle per column header:
         *   unsorted -> asc -> desc -> removed
         * Keeps up to 2 active sort columns (primary & secondary).
         * Removing the primary column promotes the secondary column to primary.
         * Adding a 3rd column replaces the secondary sort slot.
         */
        function toggleColumnSort(col) {
            var idx = findSortIndex(col);
            if (idx !== -1) {
                if (sortOrder[idx].dir === "asc") {
                    sortOrder[idx].dir = "desc";
                } else {
                    sortOrder.splice(idx, 1);
                }
            } else if (sortOrder.length < MAX_SORT_COLS) {
                sortOrder.push({ col: col, dir: "asc" });
            } else {
                sortOrder[MAX_SORT_COLS - 1] = { col: col, dir: "asc" };
            }
            applySort();
        }

        function removeColumnSort(col) {
            var idx = findSortIndex(col);
            if (idx !== -1) {
                sortOrder.splice(idx, 1);
                applySort();
            }
        }

        function swapSortOrder() {
            if (sortOrder.length === 2) {
                var tmp = sortOrder[0];
                sortOrder[0] = sortOrder[1];
                sortOrder[1] = tmp;
                applySort();
            }
        }

        function resetDefaultSort() {
            sortOrder = defaultSortOrder.map(function (item) {
                return { col: item.col, dir: item.dir };
            });
            applySort();
        }

        function clearSort() {
            sortOrder = [];
            applySort();
        }

        function syncControlVisualState(control) {
            var hasVal = normalizeString(control.value) !== "";
            if (hasVal) {
                control.classList.add("is-active");
            } else {
                control.classList.remove("is-active");
            }

            var mode = control.getAttribute("data-filter-mode");
            if (mode === "text" || mode === "date") {
                var wrap = control.parentElement;
                var clearBtn = wrap ? wrap.querySelector(".bastion-filter-clear") : null;
                if (clearBtn) {
                    clearBtn.hidden = control.value === "";
                }
            }
        }

        function applyFilters() {
            var activeFilters = [];

            filterControls.forEach(function (control) {
                syncControlVisualState(control);
                var rawVal = control.value;
                var mode = control.getAttribute("data-filter-mode");
                var col = parseInt(control.getAttribute("data-filter-col"), 10);

                if (mode === "text") {
                    var query = normalizeString(rawVal).toLowerCase();
                    if (query !== "") {
                        activeFilters.push({ col: col, mode: mode, query: query });
                    }
                } else if (mode === "date") {
                    var dateQuery = normalizeString(rawVal);
                    if (dateQuery !== "") {
                        activeFilters.push({ col: col, mode: mode, query: dateQuery });
                    }
                } else if (mode === "exact") {
                    if (rawVal !== "") {
                        activeFilters.push({
                            col: col,
                            mode: mode,
                            value: rawVal === EMPTY_TOKEN ? EMPTY_TOKEN : normalizeString(rawVal).toLowerCase()
                        });
                    }
                } else if (mode === "presence") {
                    if (rawVal === "has" || rawVal === "empty") {
                        activeFilters.push({ col: col, mode: mode, value: rawVal });
                    }
                }
            });

            var visibleCount = 0;
            var totalCount = rowRecords.length;

            rowRecords.forEach(function (rec) {
                var matches = true;
                for (var i = 0; i < activeFilters.length; i++) {
                    var f = activeFilters[i];
                    var cellLower = rec.lowerValues[f.col] || "";

                    if (f.mode === "text") {
                        if (cellLower.indexOf(f.query) === -1) {
                            matches = false;
                            break;
                        }
                    } else if (f.mode === "date") {
                        if (!matchesDateFilter(rec.values[f.col] || "", f.query)) {
                            matches = false;
                            break;
                        }
                    } else if (f.mode === "exact") {
                        if (f.value === EMPTY_TOKEN) {
                            if (cellLower !== "") {
                                matches = false;
                                break;
                            }
                        } else if (cellLower !== f.value) {
                            matches = false;
                            break;
                        }
                    } else if (f.mode === "presence") {
                        if (f.value === "has" && cellLower === "") {
                            matches = false;
                            break;
                        }
                        if (f.value === "empty" && cellLower !== "") {
                            matches = false;
                            break;
                        }
                    }
                }

                rec.el.hidden = !matches;
                rec.el.style.display = matches ? "" : "none";
                if (matches) {
                    visibleCount++;
                }
            });

            if (emptyRow) {
                var showEmpty = (totalCount === 0) || (visibleCount === 0);
                emptyRow.hidden = !showEmpty;
                emptyRow.style.display = showEmpty ? "" : "none";
                var emptyCell = emptyRow.querySelector("td");
                if (emptyCell) {
                    emptyCell.textContent = totalCount === 0
                        ? "No personnel currently on the Bastion."
                        : "No personnel match the current filters.";
                }
            }

            var hasActiveFilters = activeFilters.length > 0;
            if (countEl) {
                var noun = totalCount === 1 ? "person" : "personnel";
                if (hasActiveFilters) {
                    countEl.textContent = "Showing " + visibleCount + " of " + totalCount + " " + noun;
                } else {
                    countEl.textContent = totalCount + " " + noun;
                }
            }

            if (clearAllBtn) {
                clearAllBtn.disabled = !hasActiveFilters;
                clearAllBtn.innerHTML = hasActiveFilters
                    ? '<i class="fas fa-times" aria-hidden="true"></i> Clear filters (' + activeFilters.length + ")"
                    : '<i class="fas fa-times" aria-hidden="true"></i> Clear filters';
            }

            updateBackgroundHeight();
        }

        // Bind sort header clicks
        headerCells.forEach(function (th) {
            var btn = th.querySelector(".bastion-sort-btn");
            if (!btn) {
                return;
            }
            btn.addEventListener("click", function () {
                var col = parseInt(th.getAttribute("data-col"), 10);
                toggleColumnSort(col);
            });
        });

        if (resetSortBtn) {
            resetSortBtn.addEventListener("click", resetDefaultSort);
        }
        if (clearSortBtn) {
            clearSortBtn.addEventListener("click", clearSort);
        }

        // Bind per-column filter controls
        filterControls.forEach(function (control) {
            var mode = control.getAttribute("data-filter-mode");
            var col = control.getAttribute("data-filter-col");

            if (mode === "text" || mode === "date") {
                control.addEventListener("input", function () {
                    // If a paired presence dropdown is set to "empty", reset it so typing immediately works
                    var pairedPresence = table.querySelector('select[data-filter-col="' + col + '"][data-filter-mode="presence"]');
                    if (pairedPresence && pairedPresence.value === "empty" && normalizeString(control.value) !== "") {
                        pairedPresence.value = "";
                    }
                    applyFilters();
                });

                control.addEventListener("keydown", function (e) {
                    if (e.key === "Escape" && control.value !== "") {
                        control.value = "";
                        applyFilters();
                        e.stopPropagation();
                    }
                });

                var wrap = control.parentElement;
                var clearBtn = wrap ? wrap.querySelector(".bastion-filter-clear") : null;
                if (clearBtn) {
                    clearBtn.addEventListener("click", function () {
                        control.value = "";
                        applyFilters();
                        control.focus();
                    });
                }
            } else if (mode === "presence") {
                control.addEventListener("change", function () {
                    // Switching to "None" clears the paired text filter to avoid empty impossible queries
                    if (control.value === "empty") {
                        var pairedText = table.querySelector('input[data-filter-col="' + col + '"][data-filter-mode="text"]');
                        if (pairedText && pairedText.value !== "") {
                            pairedText.value = "";
                        }
                    }
                    applyFilters();
                });
            } else {
                control.addEventListener("change", applyFilters);
            }
        });

        if (clearAllBtn) {
            clearAllBtn.addEventListener("click", function () {
                filterControls.forEach(function (control) {
                    control.value = "";
                });
                applyFilters();
            });
        }

        // Expose live row update for the AJAX edit modal so Access stays current without reloading
        function updateRowAccess(rowEl, newDisposition) {
            if (!rowEl) {
                return;
            }
            var normalized = normalizeString(newDisposition);
            for (var i = 0; i < rowRecords.length; i++) {
                if (rowRecords[i].el === rowEl) {
                    var accessCell = rowEl.children[4];
                    if (accessCell) {
                        accessCell.setAttribute("data-value", normalized);
                        accessCell.textContent = normalized;
                    }
                    rowRecords[i].values[4] = normalized;
                    rowRecords[i].lowerValues[4] = normalized.toLowerCase();
                    break;
                }
            }

            // Add the new disposition to the Access filter dropdown if not already present
            var accessSelect = table.querySelector('select[data-filter-col="4"][data-filter-mode="exact"]');
            if (accessSelect && normalized !== "") {
                var targetLower = normalized.toLowerCase();
                var exists = false;
                var opts = accessSelect.options || [];
                for (var j = 0; j < opts.length; j++) {
                    if (normalizeString(opts[j].value).toLowerCase() === targetLower) {
                        exists = true;
                        break;
                    }
                }
                if (!exists && typeof document.createElement === "function") {
                    var opt = document.createElement("option");
                    opt.value = normalized;
                    opt.textContent = normalized;
                    var inserted = false;
                    for (var k = 1; k < opts.length; k++) {
                        if (opts[k].value === EMPTY_TOKEN || compareText(normalized, opts[k].value) < 0) {
                            accessSelect.insertBefore(opt, opts[k]);
                            inserted = true;
                            break;
                        }
                    }
                    if (!inserted) {
                        accessSelect.appendChild(opt);
                    }
                }
            }

            applySort();
            applyFilters();
        }

        // Apply default sort (Date desc, then Time desc) and initialize filter state
        applySort();
        applyFilters();

        initBastionModal(table, updateRowAccess);
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function sendRequest(url, options, onSuccess, onError) {
        var method = options.method || "GET";
        var body = options.body || null;
        var signal = options.signal || null;

        if (typeof fetch === "function") {
            var fetchOpts = {
                method: method,
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            };
            if (body !== null) {
                fetchOpts.headers["Content-Type"] = "application/x-www-form-urlencoded; charset=UTF-8";
                fetchOpts.body = body;
            }
            if (signal) {
                fetchOpts.signal = signal;
            }
            fetch(url, fetchOpts)
                .then(function (resp) {
                    if (!resp.ok) {
                        throw new Error("Server returned HTTP " + resp.status);
                    }
                    return resp.text();
                })
                .then(onSuccess)
                .catch(function (err) {
                    if (err && err.name === "AbortError") {
                        return;
                    }
                    onError(err);
                });
            return;
        }

        if (typeof XMLHttpRequest !== "undefined") {
            var xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            if (body !== null) {
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
            }
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    onSuccess(xhr.responseText);
                } else {
                    onError(new Error("Server returned HTTP " + xhr.status));
                }
            };
            xhr.onerror = function () {
                onError(new Error("Network error while communicating with the server."));
            };
            xhr.send(body);
            return;
        }

        onError(new Error("AJAX requests are not supported in this environment."));
    }

    function serializeFormUrlEncoded(form) {
        var pairs = [];
        var elements = form.elements || [];
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || el.disabled) {
                continue;
            }
            var type = (el.type || "").toLowerCase();
            if (type === "file" || type === "submit" || type === "button" || type === "reset") {
                continue;
            }
            if ((type === "radio" || type === "checkbox") && !el.checked) {
                continue;
            }
            pairs.push(encodeURIComponent(el.name) + "=" + encodeURIComponent(el.value == null ? "" : el.value));
        }
        return pairs.join("&");
    }

    function initBastionModal(table, onDispositionUpdated) {
        var modal = document.getElementById("bastion-modal");
        if (!modal) {
            return;
        }

        var dialog = modal.querySelector(".bastion-modal-dialog");
        var bodyEl = document.getElementById("bastion-modal-body");
        var titleEl = document.getElementById("bastion-modal-title");
        var crumbEl = document.getElementById("bastion-modal-crumb");
        var backBtn = document.getElementById("bastion-modal-back");
        var closeBtn = document.getElementById("bastion-modal-close");

        var state = {
            open: false,
            view: "check",
            iccId: "",
            characterId: "",
            displayName: "",
            activeRow: null,
            lastFocused: null,
            requestToken: 0,
            abortController: null,
            refreshTimer: null
        };

        function cancelPending() {
            state.requestToken++;
            if (state.abortController) {
                try { state.abortController.abort(); } catch (e) {}
                state.abortController = null;
            }
            if (state.refreshTimer) {
                clearTimeout(state.refreshTimer);
                state.refreshTimer = null;
            }
        }

        function setLoading(crumb, title, showBack) {
            if (crumbEl) { crumbEl.textContent = crumb; }
            if (titleEl) { titleEl.textContent = title; }
            if (backBtn) { backBtn.hidden = !showBack; }
            if (dialog) { dialog.classList.remove("is-detain"); }
            if (bodyEl) {
                bodyEl.innerHTML =
                    '<div class="bastion-modal-loading">' +
                    '<i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>' +
                    '<span>Loading ' + escapeHtml(title) + '...</span>' +
                    "</div>";
            }
        }

        function showError(message) {
            if (!bodyEl) { return; }
            bodyEl.innerHTML =
                '<div class="bastion-modal-error">' +
                '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>' +
                '<span>' + escapeHtml(message) + "</span>" +
                "</div>";
        }

        function openModal(triggerEl) {
            state.lastFocused = triggerEl || document.activeElement || null;
            state.open = true;
            modal.hidden = false;
            modal.style.display = "";
            if (document.body) {
                document.body.classList.add("bastion-modal-open");
            }
            if (dialog) {
                dialog.scrollTop = 0;
                if (typeof dialog.focus === "function") {
                    dialog.focus();
                }
            }
        }

        function closeModal() {
            if (!state.open) {
                return;
            }
            cancelPending();
            state.open = false;
            modal.hidden = true;
            modal.style.display = "none";
            if (dialog) {
                dialog.classList.remove("is-detain");
            }
            if (bodyEl) {
                bodyEl.innerHTML = "";
            }
            if (document.body) {
                document.body.classList.remove("bastion-modal-open");
            }
            if (state.lastFocused && typeof state.lastFocused.focus === "function") {
                try { state.lastFocused.focus(); } catch (e) {}
            }
            state.lastFocused = null;
            state.activeRow = null;
        }

        function loadCheckView(flashNotice) {
            cancelPending();
            var token = state.requestToken;
            var signal = null;
            if (typeof AbortController === "function") {
                state.abortController = new AbortController();
                signal = state.abortController.signal;
            }
            state.view = "check";
            setLoading("Check Person", state.displayName || "Personnel Dossier", false);

            var payload = "modal=1&id=" + encodeURIComponent(state.iccId) +
                "&character_id=" + encodeURIComponent(state.characterId);

            sendRequest(
                "./check.php",
                { method: "POST", body: payload, signal: signal },
                function (html) {
                    if (!state.open || token !== state.requestToken) {
                        return;
                    }
                    state.abortController = null;
                    bodyEl.innerHTML =
                        (flashNotice
                            ? '<div class="bastion-modal-banner" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i><span>' + escapeHtml(flashNotice) + "</span></div>"
                            : "") +
                        html;

                    var profileEl = bodyEl.querySelector(".bastion-modal-profile");
                    if (profileEl) {
                        var loadedName = profileEl.getAttribute("data-character-name");
                        var loadedId = profileEl.getAttribute("data-character-id");
                        var loadedIcc = profileEl.getAttribute("data-icc-number");
                        var disposition = profileEl.getAttribute("data-disposition") || "";
                        if (loadedName) {
                            state.displayName = loadedName;
                            if (titleEl) { titleEl.textContent = loadedName; }
                        }
                        if (loadedId) { state.characterId = loadedId; }
                        if (loadedIcc) { state.iccId = loadedIcc; }
                        if (dialog) {
                            if (disposition.toUpperCase() === "DETAIN") {
                                dialog.classList.add("is-detain");
                            } else {
                                dialog.classList.remove("is-detain");
                            }
                        }
                    }
                    if (bodyEl) { bodyEl.scrollTop = 0; }
                },
                function (err) {
                    if (!state.open || token !== state.requestToken) {
                        return;
                    }
                    state.abortController = null;
                    showError("Could not load character dossier (" + (err && err.message ? err.message : "unknown error") + ").");
                }
            );
        }

        function loadEditView(characterId) {
            cancelPending();
            var token = state.requestToken;
            var signal = null;
            if (typeof AbortController === "function") {
                state.abortController = new AbortController();
                signal = state.abortController.signal;
            }
            var targetId = characterId || state.characterId;
            state.characterId = targetId;
            state.view = "edit";
            setLoading("Check Person › Edit Character", state.displayName || "Edit Character", true);

            var url = "./edit_character.php?id=" + encodeURIComponent(targetId) + "&modal=1";
            sendRequest(
                url,
                { method: "GET", signal: signal },
                function (html) {
                    if (!state.open || token !== state.requestToken) {
                        return;
                    }
                    state.abortController = null;
                    bodyEl.innerHTML = html;

                    var editEl = bodyEl.querySelector(".bastion-modal-edit");
                    if (editEl) {
                        var loadedName = editEl.getAttribute("data-character-name");
                        var disposition = editEl.getAttribute("data-disposition") || "";
                        if (loadedName) {
                            state.displayName = loadedName;
                            if (titleEl) { titleEl.textContent = loadedName; }
                        }
                        if (dialog) {
                            if (disposition.toUpperCase() === "DETAIN") {
                                dialog.classList.add("is-detain");
                            } else {
                                dialog.classList.remove("is-detain");
                            }
                        }
                    }
                    if (bodyEl) { bodyEl.scrollTop = 0; }
                    var firstInput = bodyEl.querySelector('input[name="rank"]');
                    if (firstInput && typeof firstInput.focus === "function") {
                        firstInput.focus();
                    }
                },
                function (err) {
                    if (!state.open || token !== state.requestToken) {
                        return;
                    }
                    state.abortController = null;
                    showError("Could not load character editor (" + (err && err.message ? err.message : "unknown error") + ").");
                }
            );
        }

        function handleEditSubmit(form) {
            var submitBtn = form.querySelector(".edit-submit");
            var errEl = form.querySelector(".bastion-modal-form-error");
            var origLabel = submitBtn ? submitBtn.value : "UPDATE";

            if (errEl) {
                errEl.hidden = true;
                errEl.textContent = "";
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.value = "UPDATING...";
            }

            var serialized = serializeFormUrlEncoded(form);
            var selectedDispositionEl = form.querySelector('input[name="douane_disposition"]:checked');
            var newDisposition = selectedDispositionEl ? selectedDispositionEl.value : null;

            cancelPending();
            var token = state.requestToken;
            var signal = null;
            if (typeof AbortController === "function") {
                state.abortController = new AbortController();
                signal = state.abortController.signal;
            }

            sendRequest(
                "./xf.php",
                { method: "POST", body: serialized, signal: signal },
                function (respText) {
                    if (!state.open || token !== state.requestToken) {
                        return;
                    }
                    state.abortController = null;
                    if (normalizeString(respText) === "success") {
                        if (newDisposition !== null && state.activeRow && typeof onDispositionUpdated === "function") {
                            onDispositionUpdated(state.activeRow, newDisposition);
                        }
                        var successBox = bodyEl.querySelector(".checking-success");
                        form.hidden = true;
                        form.style.display = "none";
                        if (successBox) {
                            successBox.style.display = "block";
                        }
                        var updatedLabel = (state.displayName || "Character") + " has been updated.";
                        state.refreshTimer = setTimeout(function () {
                            state.refreshTimer = null;
                            if (state.open) {
                                loadCheckView(updatedLabel);
                            }
                        }, 800);
                    } else {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.value = origLabel;
                        }
                        if (errEl) {
                            errEl.textContent = "Update failed. Unexpected server response.";
                            errEl.hidden = false;
                        }
                    }
                },
                function (err) {
                    if (!state.open || token !== state.requestToken) {
                        return;
                    }
                    state.abortController = null;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.value = origLabel;
                    }
                    if (errEl) {
                        errEl.textContent = "Update failed: " + (err && err.message ? err.message : "network error.");
                        errEl.hidden = false;
                    }
                }
            );
        }

        // Intercept name form submits inside the bastion table to open check.php in the modal
        table.addEventListener("submit", function (e) {
            var form = e.target;
            if (!form || !form.classList || !form.classList.contains("bastion-check-form")) {
                return;
            }
            if (e.preventDefault) {
                e.preventDefault();
            }
            var idInput = form.querySelector('input[name="id"]');
            var charIdInput = form.querySelector('input[name="character_id"]');
            var submitInput = form.querySelector(".name-submit");
            var row = form.parentElement;
            while (row && row.tagName !== "TR") {
                row = row.parentElement;
            }

            state.iccId = idInput ? idInput.value : "";
            state.characterId = charIdInput ? charIdInput.value : (row ? (row.getAttribute("data-character-id") || "") : "");
            state.displayName = submitInput ? submitInput.value : "Personnel Dossier";
            state.activeRow = row || null;

            openModal(submitInput);
            loadCheckView(null);
        });

        // Delegate clicks inside the modal body for edit link and Cancel/Back buttons
        if (bodyEl) {
            bodyEl.addEventListener("click", function (e) {
                var node = e.target;
                while (node && node !== bodyEl) {
                    if (node.tagName === "A" && node.classList && node.classList.contains("bastion-edit-link")) {
                        // Allow opening in a new tab on Ctrl/Cmd/Shift/Alt/middle-click
                        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || (typeof e.button === "number" && e.button !== 0)) {
                            return;
                        }
                        if (e.preventDefault) {
                            e.preventDefault();
                        }
                        var targetCharId = node.getAttribute("data-character-id") || state.characterId;
                        loadEditView(targetCharId);
                        return;
                    }
                    if (node.getAttribute && node.getAttribute("data-modal-back") !== null) {
                        if (e.preventDefault) {
                            e.preventDefault();
                        }
                        loadCheckView(null);
                        return;
                    }
                    node = node.parentElement;
                }
            });

            bodyEl.addEventListener("submit", function (e) {
                var form = e.target;
                if (form && form.classList && form.classList.contains("character-edit-form")) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                    handleEditSubmit(form);
                }
            });
        }

        // Back button inside header
        if (backBtn) {
            backBtn.addEventListener("click", function () {
                loadCheckView(null);
            });
        }

        // Backdrop & close button clicks
        modal.addEventListener("click", function (e) {
            var node = e.target;
            while (node && node !== modal) {
                if (node.getAttribute && node.getAttribute("data-modal-close") !== null) {
                    closeModal();
                    return;
                }
                node = node.parentElement;
            }
        });
        if (closeBtn) {
            closeBtn.addEventListener("click", closeModal);
        }

        // Escape key: return from edit view back to check dossier, or close check dossier
        if (typeof document.addEventListener === "function") {
            document.addEventListener("keydown", function (e) {
                if (!state.open) {
                    return;
                }
                if (e.key === "Escape" || e.keyCode === 27) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                    if (state.view === "edit") {
                        loadCheckView(null);
                    } else {
                        closeModal();
                    }
                }
            });
        }
    }

    if (typeof document !== "undefined") {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initBastionTable);
        } else {
            initBastionTable();
        }
    }
})();
