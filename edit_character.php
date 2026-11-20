<?php
$bIsModal = (isset($_REQUEST["modal"]) && $_REQUEST["modal"] == "1");
if ($bIsModal) {
    $skipICDate = true;
}
include ('includes/include.php');

$escape = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$post = 0;
$aRes = null;
if (isset($_GET["id"]) && trim((string)$_GET["id"]) !== "") {
    $cDouane = new douane();
    $sId = $_GET["id"];
    $aRes = $cDouane->getEditById($sId);
    if (is_array($aRes)) {
        $post = 1;
        if (isset($aRes[2])) {
            $travels = $aRes[2];
        }
    }
}

$renderEditForm = function ($char, $bModalMode) use ($escape) {
    $sCharId = isset($char["characterID"]) ? (string)$char["characterID"] : '';
    $sCharName = isset($char["character_name"]) ? (string)$char["character_name"] : '';
    $sFaction = isset($char["faction"]) ? (string)$char["faction"] : '';
    $sRank = isset($char["rank"]) ? (string)$char["rank"] : '';
    $sStatus = isset($char["status"]) ? (string)$char["status"] : '';
    $sDisposition = isset($char["douane_disposition"]) ? (string)$char["douane_disposition"] : '';
    $sLevel = isset($char["threat_assessment"]) ? (string)$char["threat_assessment"] : '0';
    $sClear = isset($char["bastion_clearance"]) ? (string)$char["bastion_clearance"] : '0';
    $sNotes = isset($char["douane_notes"]) ? (string)$char["douane_notes"] : '';

    if (str_contains($sStatus, "figu")) {
        $sFiguId = isset($char["figu_accountID"]) ? (string)$char["figu_accountID"] : '';
        $sImage = "./images/mugs/npc/" . $sFiguId . ".jpg";
    } else {
        $sImage = "./images/mugs/" . $sCharId . ".jpg";
    }
    ?>
        <div class="check-image">
            <?php if (file_exists($sImage)) { ?>
                <img class="portrait" src="<?php echo $escape($sImage); ?>" alt="<?php echo $escape($sCharName); ?>" />
            <?php } else { ?>
                <img class="portrait" src="./images/pending.png" alt="Pending portrait" />
            <?php } ?>

            <?php if ($sFaction !== '') { ?>
                <img class="faction-logo" src="./images/logos/<?php echo $escape($sFaction); ?>.png" alt="<?php echo $escape($sFaction); ?>" />
            <?php } ?>
        </div>
        <div class="check-right">
            <form class="character-edit-form" method="post" action="./xf.php">
                <div class="check-name">
                    <?php echo $escape($sCharName); ?>
                </div>
                <div class="check-faction">
                    <span class="check-subtitle">
                        Rank:
                    </span>
                    <input class="input-pretty" name="rank" value="<?php echo $escape($sRank); ?>" />
                </div>
                <div class="check-faction small">
                    <span class="check-subtitle">
                        Disposition:
                    </span>
                    <?php if ($sDisposition != "ICC VETTED" && $sDisposition != "DECEASED" && $sDisposition != "AWOL" && $sDisposition != "MIA") { ?>
                        <input type="radio" id="des-pending" name="douane_disposition" value="ACCESS PENDING" <?php if ($sDisposition == "ACCESS PENDING") { ?>checked<?php } ?> /> <label for="des-pending">Access
                            pending</label><br />
                        <input type="radio" id="des-granted" name="douane_disposition" value="ACCESS GRANTED" <?php if ($sDisposition == "ACCESS GRANTED") { ?>checked<?php } ?> /> <label for="des-granted">Access
                            granted</label><br />
                        <input type="radio" id="des-detain" name="douane_disposition" value="DETAIN" <?php if ($sDisposition == "DETAIN") { ?>checked<?php } ?> /> <label for="des-detain">Detain</label><br />
                        <input type="radio" id="des-awol" name="douane_disposition" value="AWOL" <?php if ($sDisposition == "AWOL") { ?>checked<?php } ?> /> <label for="des-awol">AWOL</label><br />
                        <input type="radio" id="des-mia" name="douane_disposition" value="MIA" <?php if ($sDisposition == "MIA") { ?>checked<?php } ?> /> <label for="des-mia">MIA</label><br />
                    <?php } ?>
                    <?php if ($sDisposition == "DECEASED") { ?>
                        <input type="radio" id="des-deceased" name="douane_disposition" value="DECEASED" checked /> <label
                            for="des-deceased">Deceased</label><br />
                    <?php } ?>
                    <?php if ($sDisposition == "ICC VETTED") { ?>
                        <input type="radio" id="des-vetted" name="douane_disposition" value="ICC VETTED" checked /> <label for="des-vetted">ICC
                            vetted</label><br />
                    <?php } ?>
                    <?php if ($sDisposition == "AWOL") { ?>
                        <input type="radio" id="des-awol" name="douane_disposition" value="AWOL" checked /> <label for="des-awol">AWOL</label><br />
                    <?php } ?>
                    <?php if ($sDisposition == "MIA") { ?>
                        <input type="radio" id="des-mia" name="douane_disposition" value="MIA" checked /> <label for="des-mia">MIA</label><br />
                    <?php } ?>
                </div>
                <div class="check-faction small">
                    <span class="check-subtitle">
                        Threat level:
                    </span>
                    <input type="radio" id="thr-0" name="threat_assessment" value="0" <?php if ($sLevel == "0") { ?>checked<?php } ?> /> <label for="thr-0">0</label><br />
                    <input type="radio" id="thr-1" name="threat_assessment" value="1" <?php if ($sLevel == "1") { ?>checked<?php } ?> /> <label for="thr-1">1</label><br />
                    <input type="radio" id="thr-2" name="threat_assessment" value="2" <?php if ($sLevel == "2") { ?>checked<?php } ?> /> <label for="thr-2">2</label><br />
                    <input type="radio" id="thr-3" name="threat_assessment" value="3" <?php if ($sLevel == "3") { ?>checked<?php } ?> /> <label for="thr-3">3</label><br />
                    <input type="radio" id="thr-4" name="threat_assessment" value="4" <?php if ($sLevel == "4") { ?>checked<?php } ?> /> <label for="thr-4">4</label><br />
                    <input type="radio" id="thr-5" name="threat_assessment" value="5" <?php if ($sLevel == "5") { ?>checked<?php } ?> /> <label for="thr-5">5</label><br />
                </div>
                <div class="check-faction small">
                    <span class="check-subtitle">
                        Clearance level:
                    </span>
                    <input type="radio" id="clr-0" name="bastion_clearance" value="0" <?php if ($sClear == "0") { ?>checked<?php } ?> /> <label for="clr-0">0</label><br />
                    <input type="radio" id="clr-1" name="bastion_clearance" value="1" <?php if ($sClear == "1") { ?>checked<?php } ?> /> <label for="clr-1">1</label><br />
                    <input type="radio" id="clr-2" name="bastion_clearance" value="2" <?php if ($sClear == "2") { ?>checked<?php } ?> /> <label for="clr-2">2</label><br />
                    <input type="radio" id="clr-3" name="bastion_clearance" value="3" <?php if ($sClear == "3") { ?>checked<?php } ?> /> <label for="clr-3">3</label><br />
                </div>
                <div class="check-faction clear">
                    <span class="check-subtitle">
                        Notes:
                    </span>
                    <textarea class="textarea-pretty"
                        name="douane_notes"><?php echo $escape($sNotes); ?></textarea>
                </div>
                <div class="bastion-edit-actions">
                    <input class="edit-submit" type="submit" value="UPDATE" />
                    <?php if ($bModalMode) { ?>
                        <button type="button" class="bastion-edit-cancel" data-modal-back>CANCEL</button>
                    <?php } ?>
                </div>
                <div class="bastion-modal-form-error" role="alert" hidden></div>
                <input name="characterID" type="hidden" value="<?php echo $escape($sCharId); ?>" />
                <input type="hidden" name="xf" value="editCharacter" />
            </form>
            <div class="checking-success">
                <strong><?php echo $escape($sCharName); ?> has been updated.</strong><br />
                <?php echo $bModalMode ? 'Returning to character dossier...' : 'Returning to "Check Person" in 3 seconds.'; ?>
            </div>
        </div>
        <div class="clear"></div>
    <?php
};

if ($bIsModal) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    if ($post == 1) {
        $char = $aRes[1];
        $sCharName = isset($char["character_name"]) ? (string)$char["character_name"] : '';
        $sCharId = isset($char["characterID"]) ? (string)$char["characterID"] : '';
        $sDisposition = isset($char["douane_disposition"]) ? (string)$char["douane_disposition"] : '';
        ?>
        <div class="bastion-modal-edit"
             data-character-name="<?php echo $escape($sCharName); ?>"
             data-character-id="<?php echo $escape($sCharId); ?>"
             data-disposition="<?php echo $escape($sDisposition); ?>">
            <?php $renderEditForm($char, true); ?>
        </div>
        <?php
    } else {
        ?>
        <div class="bastion-modal-error">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <span>Unable to load character for editing. Invalid or missing Character ID.</span>
        </div>
        <?php
    }
    return;
}
?>

<?php
include ('includes/inc.header.php');
?>

<div id="main">

    <div class="container">
        <?php if ($post == 1) { ?>
            <?php $renderEditForm($aRes[1], false); ?>
        <?php } else { ?>
            <div class="welcome">
                <h5 style="color:red;">Invalid or missing Character ID.</h5>
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
</body>

</html>
