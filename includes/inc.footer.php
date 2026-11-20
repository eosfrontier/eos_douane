<?php
    $sScriptsJsPath = __DIR__ . '/../scripts/scripts.js';
    $sA11yJsPath = __DIR__ . '/../scripts/a11y.js';
    $sScriptsJsVer = file_exists($sScriptsJsPath) ? (string)filemtime($sScriptsJsPath) : '1';
    $sA11yJsVer = file_exists($sA11yJsPath) ? (string)filemtime($sA11yJsPath) : '1';
?>
<script src="scripts/scripts.js?v=<?php echo $sScriptsJsVer; ?>" type="text/javascript"></script>
<script data-cfasync="false" src="scripts/a11y.js?v=<?php echo $sA11yJsVer; ?>" type="text/javascript"></script>
