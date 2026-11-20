<?php
include ('includes/include.php');
$post = 0;
if (isset($_POST["id"])) {
    $cDouane = new douane();
    $sId = $_POST["id"];
    $aRes = $cDouane->getChecking($sId);
    if ($aRes != "empty") {
        $post = 1;
        if (isset($aRes[2])) {
            $travels = $aRes[2];
        }
    }

}
?>

<?php
include ('includes/inc.header.php');
?>
<div id="main">
    <div class="container">
        <?php if ($post == 0) { ?>
            <div class="welcome">
                Please scan the identity badge.
                <form action="./checkinout.php" method="post">
                    <input name="id" type="text" class="badge-scan" autofocus />
                </form>
                <?php
                  if (isset($aRes) && $aRes == "empty") {
                    echo "<h5 style='color:red;'>Invalid Card ID or ICC Number. Please try again...</h5>";
                    }
                    ?>
            </div>
        <?php } ?>
        <?php if ($post == 1) { ?>
            <?php if ($aRes[0]["returning"] == "empty") {
                ?>
                <div class="logging-message">
                    <strong>This person hasn't been on the Bastion yet.</strong>
                </div>
                <?php
            }
            ?>
            <?php if ($aRes[1]["douane_disposition"] == "DETAIN") { ?>
                <div class="check-warning">
                    WARNING -- DETAIN -- WARNING
                </div>
            <?php } ?>
            <div class="checkinout-left">
                <?php
                if ($aRes[0]["access"] == 0) {
                    $value = "Check in";
                    $access = "1";
                    $success = "Checked in";
                } else {
                    $value = "Check out";
                    $access = "0";
                    $success = "Checked out";
                }
                ?>
                <?php
                $sDefaultDate = isset($ICDateString) ? htmlspecialchars((string)$ICDateString, ENT_QUOTES, 'UTF-8') : '';
                $bDateEmpty = ($sDefaultDate === '');
                ?>
                <form class="checking-form">
                    <div class="checking-field-header">
                        <label for="checkin-date">Current date</label>
                        <button type="button"
                                class="field-override-btn<?php echo $bDateEmpty ? ' is-active' : ''; ?>"
                                data-override-target="checkin-date"
                                aria-pressed="<?php echo $bDateEmpty ? 'true' : 'false'; ?>"
                                <?php if ($bDateEmpty) { ?>disabled="disabled"<?php } ?>
                                title="<?php echo $bDateEmpty ? 'No auto-filled date available; enter a date manually' : 'Unlock Current date to enter a manual value'; ?>">
                            <i class="fas <?php echo $bDateEmpty ? 'fa-lock-open' : 'fa-lock'; ?>" aria-hidden="true"></i>
                            <span><?php echo $bDateEmpty ? 'Manual' : 'Override'; ?></span>
                        </button>
                    </div>
                    <input id="checkin-date"
                           type="text"
                           required
                           name="date"
                           value="<?php echo $sDefaultDate; ?>"
                           data-default-value="<?php echo $sDefaultDate; ?>"
                           <?php if (!$bDateEmpty) { ?>readonly="readonly" class="is-locked"<?php } else { ?>class="is-overridden"<?php } ?> /><br />
                    <div class="checking-field-header">
                        <label for="time">Current time</label>
                        <button type="button"
                                class="field-override-btn"
                                data-override-target="time"
                                aria-pressed="false"
                                title="Unlock Current time to enter a manual value">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <span>Override</span>
                        </button>
                    </div>
                    <input id="time"
                           type="text"
                           required
                           name="time"
                           readonly="readonly"
                           class="is-locked" /><br />
                    <label>Reason</label><br />
                    <textarea name="reason" autofocus></textarea><br />
                    <label>Notes</label><br />
                    <textarea name="note"></textarea><br />
                    <input type="hidden" name="character_id" value="<?php echo $aRes[1]["characterID"]; ?>" />
                    <input type="hidden" name="access" value="<?php echo $access; ?>" />
                    <input type="hidden" name="xf" value="checkin" />
                    <input type="submit" class="checkinout-button" value="<?php echo $value; ?>" />
                </form>
                <div class="checking-success">
                    <strong><?php echo $aRes[1]["character_name"] ?> has been <?php echo $success; ?>.</strong><br />
                    Returning to scanner in 3 seconds.
                </div>
            </div>
            <div class="checkinout-right">

                <div class="check-image">
                    <?php
                    $status = $aRes[1]["status"];
                    if (str_contains($status, "figu")) {
                        $sImage = "./images/mugs/npc/" . $aRes[1]["figu_accountID"] . ".jpg";
                    } else {
                        $sImage = "./images/mugs/" . $aRes[1]["characterID"] . ".jpg";
                    }
                    if (file_exists($sImage)) {
                        echo '<img class="portrait" src="' . $sImage . '" />';
                    } else { ?>
                        <img class="portrait" src="./images/pending.png" />
                    <?php } ?>

                    <img src="./images/logos/<?php echo $aRes[1]["faction"] ?>.png" />
                </div>
                <div class="check-right">
                <div class="check-name check-doublecolumn">
                <?php echo $aRes[1]["character_name"] ?>
                </div>
                <table>
                    <tr>
                        <td>
                            <div class="check-faction">
                                <span class="check-subtitle">
                                    Faction:
                                </span>
                                <?php echo $aRes[1]["faction"] ?>
                            </div>
                            <?php if ($aRes[1]["rank"]) { ?>
                                <div class="check-faction">
                                    <span class="check-subtitle">
                                        Rank / Job:
                                    </span>
                                    <?php echo $aRes[1]["rank"] ?>
                                </div>
                            <?php } ?>
                            <div class="check-faction">
                                <span class="check-subtitle">
                                    Disposition:
                                </span>
                                <?php echo $aRes[1]["douane_disposition"] ?>
                            </div>
                            <div class="check-faction">
                                <span class="check-subtitle">
                                    Threat level:
                                </span>
                                <?php
                                $sLevel = 5;
                                $sDanger = $sLevel - $aRes[1]["threat_assessment"];
                                $i = 0;
                                while ($i < $aRes[1]["threat_assessment"]) {
                                    ?>
                                    <i class="fas fa-circle"></i>
                                    <?php
                                    $i++;
                                }
                                $i = 0;
                                while ($i < $sDanger) {
                                    ?>
                                    <i class="far fa-circle"></i>
                                    <?php
                                    $i++;
                                }
                                ?>
                            </div>
                            <div class="check-faction">
                                <span class="check-subtitle">
                                    Clearance level:
                                </span>
                                <?php
                                $sLevel = 3;
                                $sDanger = $sLevel - $aRes[1]["bastion_clearance"];
                                $i = 0;
                                while ($i < $aRes[1]["bastion_clearance"]) {
                                    ?>
                                    <i class="fas fa-circle"></i>
                                    <?php
                                    $i++;
                                }
                                $i = 0;
                                while ($i < $sDanger) {
                                    ?>
                                    <i class="far fa-circle"></i>
                                    <?php
                                    $i++;
                                }
                                ?>
                            </div>
                        </td>
                        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td>
                            <?php if ($aRes[1]["ic_birthday"]) { ?>
                                <div class="check-faction">
                                    <span class="check-subtitle">
                                        Date of Birth:
                                    </span>
                                    <?php echo $aRes[1]["ic_birthday"] ?>
                                </div>
                            <?php } ?>
                            <?php if ($aRes[1]["homeplanet"]) { ?>
                                <div class="check-faction">
                                    <span class="check-subtitle">
                                        Home Planet:
                                    </span>
                                    <?php echo $aRes[1]["homeplanet"] ?>
                                </div>
                            <?php } ?>
                            <?php if ($aRes[1]["ICC_number"]) { ?>
                                <div class="check-faction">
                                    <span class="check-subtitle">
                                        ICC ID:
                                    </span>
                                    <?php echo $aRes[1]["ICC_number"] ?>
                                </div>
                            <?php } ?>
                            <?php if ($aRes[1]["card_id"]) { ?>
                    <div class="check-faction">
                        <span class="check-subtitle">
                            Card ID:
                        </span>
                        <?php echo $aRes[1]["card_id"] ?>
                    </div>
                    <?php } ?>
                        </td>
                    </tr>
                <tr>
                <?php if ($aRes[1]["douane_notes"]) { ?>
                    <div class="check-faction check-doublecolumn">
                        <span class="check-subtitle">
                            Notes:
                        </span>
                        <?php echo nl2br($aRes[1]["douane_notes"]) ?>
                    </div>
                <?php } ?>
                </tr></table>
                <?php
                if (isset($travels)) {
                    ?>
                    <div class="check-faction check-doublecolumn">
                        <span class="check-subtitle">
                            Travel logs:
                        </span>
                        <table class="checking-table" width="100%">
                            <thead>
                                <td>
                                    <strong>Date</strong>
                                </td>
                                <td>
                                    <strong>Time</strong>
                                </td>
                                <td>
                                    <strong>Access</strong>
                                </td>
                                <td>
                                    <strong>Reason</strong>
                                </td>
                                <td>
                                    <strong>Note</strong>
                                </td>
                            </thead>

                            <?php
                            foreach ($travels as $travel) {
                                ?>
                                <tr class="travel-line">
                                    <td class="travel-line-date">
                                        <?php echo $travel["date"]; ?>
                                    </td>
                                    <td class="travel-line-time">
                                        <?php echo $travel["time"]; ?>
                                    </td>
                                    <td class="travel-line-access">
                                        <?php
                                        if ($travel["access"] == 1) {
                                            echo "Checked in";
                                        } else {
                                            echo "Checked out";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($travel["reason"])) { ?>

                                            <i class="fas fa-clipboard">
                                                <div class="tooltip">
                                                    <?php echo nl2br($travel["reason"]); ?>
                                                </div>
                                            </i>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($travel["note"])) { ?>
                                            <i class="fas fa-sticky-note">
                                                <div class="tooltip">
                                                    <?php echo nl2br($travel["note"]); ?>
                                                </div>
                                            </i>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                    </div>
                    <?php
                }
                ?>
            </div>
            </div>
        <?php } ?>
    </div>
    <div class="clear">

    </div>
</div>
</div>
<?php
include ('includes/inc.footer.php');
?>
<?php if ($post == 1) { ?>
    <script>
        (function () {
            var timeInput = document.getElementById('time');
            var timeTimer = null;
            var timeOverridden = false;

            function checkTime(i) {
                return (i < 10) ? ("0" + i) : String(i);
            }

            function updateClock() {
                if (timeOverridden || !timeInput) {
                    return;
                }
                var today = new Date();
                var h = checkTime(today.getHours());
                var m = checkTime(today.getMinutes());
                var s = checkTime(today.getSeconds());
                timeInput.value = h + ":" + m + ":" + s;
                timeTimer = setTimeout(updateClock, 1000);
            }

            function stopClock() {
                if (timeTimer !== null) {
                    clearTimeout(timeTimer);
                    timeTimer = null;
                }
            }

            function setButtonState(btn, unlocked, labelText) {
                var icon = btn.querySelector('i');
                var span = btn.querySelector('span');
                btn.setAttribute('aria-pressed', unlocked ? 'true' : 'false');
                if (unlocked) {
                    btn.classList.add('is-active');
                    btn.title = 'Re-lock ' + labelText + ' and restore auto-filled value';
                    if (icon) {
                        icon.classList.remove('fa-lock');
                        icon.classList.add('fa-lock-open');
                    }
                    if (span) {
                        span.textContent = 'Auto';
                    }
                } else {
                    btn.classList.remove('is-active');
                    btn.title = 'Unlock ' + labelText + ' to enter a manual value';
                    if (icon) {
                        icon.classList.remove('fa-lock-open');
                        icon.classList.add('fa-lock');
                    }
                    if (span) {
                        span.textContent = 'Override';
                    }
                }
            }

            var buttons = document.querySelectorAll('.checking-form .field-override-btn');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].addEventListener('click', function () {
                    var btn = this;
                    var targetId = btn.getAttribute('data-override-target');
                    var input = document.getElementById(targetId);
                    if (!input) {
                        return;
                    }
                    var labelEl = document.querySelector('label[for="' + targetId + '"]');
                    var labelText = labelEl ? labelEl.textContent.trim() : targetId;
                    var isCurrentlyLocked = input.readOnly;

                    if (isCurrentlyLocked) {
                        // Unlock for manual entry
                        input.readOnly = false;
                        input.removeAttribute('readonly');
                        input.classList.remove('is-locked');
                        input.classList.add('is-overridden');
                        if (targetId === 'time') {
                            timeOverridden = true;
                            stopClock();
                        }
                        setButtonState(btn, true, labelText);
                        input.focus();
                        if (typeof input.select === 'function') {
                            input.select();
                        }
                    } else {
                        // Re-lock and restore autofilled value
                        input.readOnly = true;
                        input.setAttribute('readonly', 'readonly');
                        input.classList.remove('is-overridden');
                        input.classList.add('is-locked');
                        if (targetId === 'time') {
                            timeOverridden = false;
                            stopClock();
                            updateClock();
                        } else {
                            var defaultVal = input.getAttribute('data-default-value');
                            if (defaultVal !== null) {
                                input.value = defaultVal;
                            }
                        }
                        setButtonState(btn, false, labelText);
                    }
                });
            }

            updateClock();
        })();
    </script>
<?php } ?>
</body>

</html>