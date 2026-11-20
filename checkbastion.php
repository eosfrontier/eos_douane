<?php
    $skipICDate = true;
    include('includes/include.php');

    $cDouane = new douane();
    $aPersonals = $cDouane->getAllPersonal();
    if (!is_array($aPersonals)) {
        $aPersonals = array();
    }

    $escape = function ($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };

    $normalizeText = function ($value) {
        $normalized = preg_replace('/\s+/', ' ', (string)$value);
        return trim($normalized === null ? (string)$value : $normalized);
    };

    $formatFactionLabel = function ($value) {
        if ($value === strtolower($value)) {
            return ucwords($value);
        }
        return $value;
    };

    $parseDateParts = function ($str) {
        $str = trim((string)$str);
        if ($str === '') {
            return null;
        }
        // Primary format: DD-MM-YYY (optional NT / N.T. suffix treated as the same year)
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{1,4})(?:\s*n\.?t\.?)?$/i', $str, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            if ($d >= 1 && $d <= 99 && $mo >= 1 && $mo <= 99) {
                return array(
                    'day' => $d,
                    'month' => $mo,
                    'year' => $y,
                    'key' => ($y * 10000) + ($mo * 100) + $d
                );
            }
        }
        // Fallback: ISO YYYY-MM-DD (optional NT / N.T. suffix)
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:\s*n\.?t\.?)?$/i', $str, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
                return array(
                    'day' => $d,
                    'month' => $mo,
                    'year' => $y,
                    'key' => ($y * 10000) + ($mo * 100) + $d
                );
            }
        }
        return null;
    };

    $parseDateKey = function ($str) use ($parseDateParts) {
        $parts = $parseDateParts($str);
        return $parts !== null ? $parts['key'] : null;
    };

    $aFactionMap = array();
    $aAccessMap = array();
    $aDateMap = array();
    $bHasEmptyFaction = false;
    $bHasEmptyAccess = false;

    foreach ($aPersonals as $aRow) {
        $sFaction = isset($aRow['faction']) ? $normalizeText($aRow['faction']) : '';
        if ($sFaction === '') {
            $bHasEmptyFaction = true;
        } else {
            $sKey = strtolower($sFaction);
            if (!isset($aFactionMap[$sKey])) {
                $aFactionMap[$sKey] = $sFaction;
            }
        }

        $sAccess = isset($aRow['douane_disposition']) ? $normalizeText($aRow['douane_disposition']) : '';
        if ($sAccess === '') {
            $bHasEmptyAccess = true;
        } else {
            $sKey = strtolower($sAccess);
            if (!isset($aAccessMap[$sKey])) {
                $aAccessMap[$sKey] = $sAccess;
            }
        }

        $sDate = isset($aRow['date']) ? $normalizeText($aRow['date']) : '';
        if ($sDate !== '') {
            $aDateParts = $parseDateParts($sDate);
            if ($aDateParts !== null) {
                // Deduplicate e.g. 5-4-246, 05-04-246, and 05-04-246NT into a single DD-MM-YYY option
                $sKey = 'd:' . $aDateParts['key'];
                if (!isset($aDateMap[$sKey])) {
                    $aDateMap[$sKey] = sprintf('%02d-%02d-%03d', $aDateParts['day'], $aDateParts['month'], $aDateParts['year']);
                }
            } else {
                $sKey = 'r:' . strtolower($sDate);
                if (!isset($aDateMap[$sKey])) {
                    $aDateMap[$sKey] = $sDate;
                }
            }
        }
    }

    $aFactionOptions = array_values($aFactionMap);
    natcasesort($aFactionOptions);

    $aAccessOptions = array_values($aAccessMap);
    natcasesort($aAccessOptions);

    $parseTimeKey = function ($str) {
        $str = trim((string)$str);
        if ($str === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*(am|pm))?$/i', $str, $m)) {
            return null;
        }
        $hours = (int)$m[1];
        $minutes = (int)$m[2];
        $seconds = (isset($m[3]) && $m[3] !== '') ? (int)$m[3] : 0;
        $meridiem = isset($m[4]) ? strtolower($m[4]) : '';
        if ($minutes > 59 || $seconds > 59) {
            return null;
        }
        if ($meridiem !== '') {
            if ($hours < 1 || $hours > 12) {
                return null;
            }
            if ($meridiem === 'pm' && $hours < 12) {
                $hours += 12;
            } elseif ($meridiem === 'am' && $hours === 12) {
                $hours = 0;
            }
        } elseif ($hours > 23) {
            return null;
        }
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    };

    $aDateOptions = array_values($aDateMap);
    usort($aDateOptions, function ($a, $b) use ($parseDateKey) {
        $pa = $parseDateKey($a);
        $pb = $parseDateKey($b);
        if ($pa !== null && $pb !== null && $pa !== $pb) {
            return ($pb < $pa) ? -1 : 1;
        }
        if ($pa !== null && $pb === null) {
            return -1;
        }
        if ($pa === null && $pb !== null) {
            return 1;
        }
        return strnatcasecmp($b, $a);
    });

    // Build indexed rows and apply the default sort: Date DESC, then Time DESC (empty values last)
    $aIndexedPersonals = array();
    foreach (array_values($aPersonals) as $iBaseIndex => $aRow) {
        $aIndexedPersonals[] = array(
            'baseIndex' => $iBaseIndex,
            'row' => $aRow
        );
    }

    usort($aIndexedPersonals, function ($a, $b) use ($normalizeText, $parseDateKey, $parseTimeKey) {
        $sDateA = isset($a['row']['date']) ? $normalizeText($a['row']['date']) : '';
        $sDateB = isset($b['row']['date']) ? $normalizeText($b['row']['date']) : '';
        if ($sDateA === '' && $sDateB !== '') {
            return 1;
        }
        if ($sDateA !== '' && $sDateB === '') {
            return -1;
        }
        if ($sDateA !== '' && $sDateB !== '') {
            $dA = $parseDateKey($sDateA);
            $dB = $parseDateKey($sDateB);
            if ($dA !== null && $dB !== null && $dA !== $dB) {
                return ($dA > $dB) ? -1 : 1;
            }
            if ($dA !== null && $dB === null) {
                return -1;
            }
            if ($dA === null && $dB !== null) {
                return 1;
            }
            if ($dA === null && $dB === null) {
                $cmp = strnatcasecmp($sDateB, $sDateA);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
        }

        $sTimeA = isset($a['row']['time']) ? $normalizeText($a['row']['time']) : '';
        $sTimeB = isset($b['row']['time']) ? $normalizeText($b['row']['time']) : '';
        if ($sTimeA === '' && $sTimeB !== '') {
            return 1;
        }
        if ($sTimeA !== '' && $sTimeB === '') {
            return -1;
        }
        if ($sTimeA !== '' && $sTimeB !== '') {
            $tA = $parseTimeKey($sTimeA);
            $tB = $parseTimeKey($sTimeB);
            if ($tA !== null && $tB !== null && $tA !== $tB) {
                return ($tA > $tB) ? -1 : 1;
            }
            if ($tA !== null && $tB === null) {
                return -1;
            }
            if ($tA === null && $tB !== null) {
                return 1;
            }
            if ($tA === null && $tB === null) {
                $cmp = strnatcasecmp($sTimeB, $sTimeA);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
        }

        return ($a['baseIndex'] < $b['baseIndex']) ? -1 : 1;
    });

    $iTotalPersonnel = count($aIndexedPersonals);
?>

    <?php
        include('includes/inc.header.php');
    ?>
    <div id="main">
        <div class="container">
            <div class="bastion-toolbar">
                <div class="welcome">
                    All personnel currently on the Bastion.
                </div>
                <div class="bastion-toolbar-controls">
                    <span id="bastion-count" class="bastion-count" aria-live="polite">
                        <?php echo $iTotalPersonnel; ?> <?php echo ($iTotalPersonnel === 1) ? 'person' : 'personnel'; ?>
                    </span>
                    <button type="button" id="bastion-clear-filters" class="bastion-clear-btn" disabled>
                        <i class="fas fa-times" aria-hidden="true"></i> Clear filters
                    </button>
                </div>
            </div>

            <div class="bastion-sort-bar">
                <div class="bastion-sort-bar-left">
                    <span class="bastion-sort-bar-label">Sort order:</span>
                    <div id="bastion-sort-pills" class="bastion-sort-pills" aria-live="polite">
                        <span class="bastion-sort-pill bastion-sort-pill-1" data-sort-col="2" data-sort-rank="1">
                            <span class="bastion-sort-pill-main">
                                <span class="bastion-pill-rank">1</span>
                                <span class="bastion-pill-label">Date</span>
                                <i class="fas fa-sort-down bastion-pill-icon" aria-hidden="true"></i>
                            </span>
                        </span>
                        <span class="bastion-sort-pill bastion-sort-pill-2" data-sort-col="3" data-sort-rank="2">
                            <span class="bastion-sort-pill-main">
                                <span class="bastion-pill-rank">2</span>
                                <span class="bastion-pill-label">Time</span>
                                <i class="fas fa-sort-down bastion-pill-icon" aria-hidden="true"></i>
                            </span>
                        </span>
                    </div>
                    <span class="bastion-sort-hint">(click headers: asc / desc / off)</span>
                </div>
                <div class="bastion-sort-bar-actions">
                    <button type="button" id="bastion-reset-sort" class="bastion-sort-action-btn" disabled>
                        <i class="fas fa-undo" aria-hidden="true"></i>Default
                    </button>
                    <button type="button" id="bastion-clear-sort" class="bastion-sort-action-btn">
                        <i class="fas fa-times" aria-hidden="true"></i>Clear sort
                    </button>
                </div>
            </div>

            <table id="bastion-table" class="bastion-table" width="100%" data-default-sort="2:desc,3:desc">
                <colgroup>
                    <col class="bastion-col-name" />
                    <col class="bastion-col-faction" />
                    <col class="bastion-col-date" />
                    <col class="bastion-col-time" />
                    <col class="bastion-col-access" />
                    <col class="bastion-col-reason" />
                    <col class="bastion-col-note" />
                </colgroup>
                <thead>
                    <tr class="bastion-sort-row">
                        <th scope="col" class="bastion-sort-cell" data-col="0" data-sort-type="text" aria-sort="none">
                            <button type="button" class="bastion-sort-btn" title="Sort by Name">
                                <span class="bastion-sort-label">Name</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true" hidden></span>
                                    <i class="fas fa-sort bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="bastion-sort-cell" data-col="1" data-sort-type="text" aria-sort="none">
                            <button type="button" class="bastion-sort-btn" title="Sort by Faction">
                                <span class="bastion-sort-label">Faction</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true" hidden></span>
                                    <i class="fas fa-sort bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="bastion-sort-cell" data-col="2" data-sort-type="date" aria-sort="descending" data-sort-rank="1">
                            <button type="button" class="bastion-sort-btn" title="Date: primary sort (descending). Click to remove from sort.">
                                <span class="bastion-sort-label">Date</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true">1</span>
                                    <i class="fas fa-sort-down bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="bastion-sort-cell" data-col="3" data-sort-type="time" aria-sort="descending" data-sort-rank="2">
                            <button type="button" class="bastion-sort-btn" title="Time: secondary sort (descending). Click to remove from sort.">
                                <span class="bastion-sort-label">Time</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true">2</span>
                                    <i class="fas fa-sort-down bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="bastion-sort-cell" data-col="4" data-sort-type="text" aria-sort="none">
                            <button type="button" class="bastion-sort-btn" title="Sort by Access">
                                <span class="bastion-sort-label">Access</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true" hidden></span>
                                    <i class="fas fa-sort bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="bastion-sort-cell" data-col="5" data-sort-type="text" aria-sort="none">
                            <button type="button" class="bastion-sort-btn" title="Sort by Reason">
                                <span class="bastion-sort-label">Reason</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true" hidden></span>
                                    <i class="fas fa-sort bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="bastion-sort-cell" data-col="6" data-sort-type="text" aria-sort="none">
                            <button type="button" class="bastion-sort-btn" title="Sort by Note">
                                <span class="bastion-sort-label">Note</span>
                                <span class="bastion-sort-indicator">
                                    <span class="bastion-sort-rank" aria-hidden="true" hidden></span>
                                    <i class="fas fa-sort bastion-sort-icon" aria-hidden="true"></i>
                                </span>
                            </button>
                        </th>
                    </tr>
                    <tr class="bastion-filter-row">
                        <th>
                            <div class="bastion-filter-wrap">
                                <input type="text"
                                       class="bastion-filter-input"
                                       data-filter-col="0"
                                       data-filter-mode="text"
                                       placeholder="Filter name..."
                                       aria-label="Filter by Name"
                                       autocomplete="off" />
                                <button type="button" class="bastion-filter-clear" aria-label="Clear Name filter" hidden>
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                        </th>
                        <th>
                            <select class="bastion-filter-select"
                                    data-filter-col="1"
                                    data-filter-mode="exact"
                                    aria-label="Filter by Faction">
                                <option value="">All</option>
                                <?php foreach ($aFactionOptions as $sFactionOption) { ?>
                                    <option value="<?php echo $escape($sFactionOption); ?>"><?php echo $escape($formatFactionLabel($sFactionOption)); ?></option>
                                <?php } ?>
                                <?php if ($bHasEmptyFaction) { ?>
                                    <option value="__EMPTY__">(None)</option>
                                <?php } ?>
                            </select>
                        </th>
                        <th>
                            <div class="bastion-filter-wrap">
                                <input type="text"
                                       class="bastion-filter-input"
                                       data-filter-col="2"
                                       data-filter-mode="date"
                                       list="bastion-date-options"
                                       placeholder="DD-MM-YYY..."
                                       aria-label="Filter by Date (DD-MM-YYY)"
                                       autocomplete="off" />
                                <button type="button" class="bastion-filter-clear" aria-label="Clear Date filter" hidden>
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                            <datalist id="bastion-date-options">
                                <?php foreach ($aDateOptions as $sDateOption) { ?>
                                    <option value="<?php echo $escape($sDateOption); ?>"></option>
                                <?php } ?>
                            </datalist>
                        </th>
                        <th>
                            <div class="bastion-filter-wrap">
                                <input type="text"
                                       class="bastion-filter-input"
                                       data-filter-col="3"
                                       data-filter-mode="text"
                                       placeholder="Filter time..."
                                       aria-label="Filter by Time"
                                       autocomplete="off" />
                                <button type="button" class="bastion-filter-clear" aria-label="Clear Time filter" hidden>
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                        </th>
                        <th>
                            <select class="bastion-filter-select"
                                    data-filter-col="4"
                                    data-filter-mode="exact"
                                    aria-label="Filter by Access">
                                <option value="">All</option>
                                <?php foreach ($aAccessOptions as $sAccessOption) { ?>
                                    <option value="<?php echo $escape($sAccessOption); ?>"><?php echo $escape($sAccessOption); ?></option>
                                <?php } ?>
                                <?php if ($bHasEmptyAccess) { ?>
                                    <option value="__EMPTY__">(None)</option>
                                <?php } ?>
                            </select>
                        </th>
                        <th>
                            <div class="bastion-filter-combo">
                                <select class="bastion-filter-select bastion-filter-presence"
                                        data-filter-col="5"
                                        data-filter-mode="presence"
                                        aria-label="Filter by Reason presence">
                                    <option value="">All</option>
                                    <option value="has">Has</option>
                                    <option value="empty">None</option>
                                </select>
                                <div class="bastion-filter-wrap">
                                    <input type="text"
                                           class="bastion-filter-input"
                                           data-filter-col="5"
                                           data-filter-mode="text"
                                           placeholder="Search..."
                                           aria-label="Filter by Reason text"
                                           autocomplete="off" />
                                    <button type="button" class="bastion-filter-clear" aria-label="Clear Reason filter" hidden>
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </th>
                        <th>
                            <div class="bastion-filter-combo">
                                <select class="bastion-filter-select bastion-filter-presence"
                                        data-filter-col="6"
                                        data-filter-mode="presence"
                                        aria-label="Filter by Note presence">
                                    <option value="">All</option>
                                    <option value="has">Has</option>
                                    <option value="empty">None</option>
                                </select>
                                <div class="bastion-filter-wrap">
                                    <input type="text"
                                           class="bastion-filter-input"
                                           data-filter-col="6"
                                           data-filter-mode="text"
                                           placeholder="Search..."
                                           aria-label="Filter by Note text"
                                           autocomplete="off" />
                                    <button type="button" class="bastion-filter-clear" aria-label="Clear Note filter" hidden>
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    foreach($aIndexedPersonals as $aEntry){
                        $iBaseIndex = $aEntry['baseIndex'];
                        $aPersonal = $aEntry['row'];
                        $sCharId = (isset($aPersonal["characterID"]) && $aPersonal["characterID"] !== '')
                            ? (string)$aPersonal["characterID"]
                            : (isset($aPersonal["character_id"]) ? (string)$aPersonal["character_id"] : '');
                        $sIccNumber = isset($aPersonal["ICC_number"]) ? $aPersonal["ICC_number"] : '';
                        $sName = isset($aPersonal["character_name"]) ? (string)$aPersonal["character_name"] : '';
                        $sFaction = isset($aPersonal["faction"]) ? (string)$aPersonal["faction"] : '';
                        $sDate = isset($aPersonal["date"]) ? (string)$aPersonal["date"] : '';
                        $sTime = isset($aPersonal["time"]) ? (string)$aPersonal["time"] : '';
                        $sAccess = isset($aPersonal["douane_disposition"]) ? (string)$aPersonal["douane_disposition"] : '';
                        $sReasonRaw = isset($aPersonal["reason"]) ? (string)$aPersonal["reason"] : '';
                        $sNoteRaw = isset($aPersonal["note"]) ? (string)$aPersonal["note"] : '';
                        $sReasonNorm = $normalizeText($sReasonRaw);
                        $sNoteNorm = $normalizeText($sNoteRaw);
                ?>
                <tr class="travel-line" data-base-index="<?php echo $iBaseIndex; ?>" data-character-id="<?php echo $escape($sCharId); ?>">
                    <td data-value="<?php echo $escape($normalizeText($sName)); ?>">
                        <form class="bastion-check-form" action="./check.php" method="post">
                            <input type="hidden" name="id" value="<?php echo $escape($sIccNumber); ?>" />
                            <input type="hidden" name="character_id" value="<?php echo $escape($sCharId); ?>" />
                            <input class="name-submit" type="submit" value="<?php echo $escape($sName); ?>" />
                        </form>
                    </td>
                    <td class="travel-line-faction" data-value="<?php echo $escape($normalizeText($sFaction)); ?>">
                        <?php echo $escape($sFaction); ?>
                    </td>
                    <td data-value="<?php echo $escape($normalizeText($sDate)); ?>">
                        <?php echo $escape($sDate); ?>
                    </td>
                    <td data-value="<?php echo $escape($normalizeText($sTime)); ?>">
                        <?php echo $escape($sTime); ?>
                    </td>
                    <td class="travel-line-access" data-value="<?php echo $escape($normalizeText($sAccess)); ?>">
                        <?php echo $escape($sAccess); ?>
                    </td>
                    <td class="bastion-icon-cell" data-value="<?php echo $escape($sReasonNorm); ?>">
                    <?php if($sReasonNorm !== ''){ ?>
                        <i class="fas fa-clipboard">
                            <div class="tooltip">
                                <?php echo nl2br($escape($sReasonRaw)); ?>
                            </div>
                        </i>
                    <?php } ?>
                    </td>
                    <td class="bastion-icon-cell" data-value="<?php echo $escape($sNoteNorm); ?>">
                    <?php if($sNoteNorm !== ''){ ?>
                        <i class="fas fa-sticky-note">
                            <div class="tooltip">
                                <?php echo nl2br($escape($sNoteRaw)); ?>
                            </div>
                        </i>
                    <?php } ?>
                    </td>
                </tr>
                <?php
                    }
                ?>
                <tr class="bastion-empty-row"<?php if ($iTotalPersonnel > 0) { ?> hidden<?php } ?>>
                    <td colspan="7">
                        <?php echo ($iTotalPersonnel === 0) ? 'No personnel currently on the Bastion.' : 'No personnel match the current filters.'; ?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
    <div id="bastion-modal" class="bastion-modal" role="dialog" aria-modal="true" aria-labelledby="bastion-modal-title" hidden>
        <div class="bastion-modal-backdrop" data-modal-close></div>
        <div class="bastion-modal-dialog" tabindex="-1">
            <div class="bastion-modal-header">
                <div class="bastion-modal-header-left">
                    <button type="button" id="bastion-modal-back" class="bastion-modal-back" hidden>
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
                    </button>
                    <div class="bastion-modal-heading">
                        <span id="bastion-modal-crumb" class="bastion-modal-crumb">Check Person</span>
                        <h2 id="bastion-modal-title" class="bastion-modal-title">Loading...</h2>
                    </div>
                </div>
                <button type="button" id="bastion-modal-close" class="bastion-modal-close" aria-label="Close dialog" title="Close (Esc)" data-modal-close>
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div id="bastion-modal-body" class="bastion-modal-body"></div>
        </div>
    </div>
    <?php
        include('includes/inc.footer.php');
    ?>
    <script src="scripts/checkbastion.js" type="text/javascript"></script>
</body>
</html>
