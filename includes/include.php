<?php

if($_SERVER["REMOTE_ADDR"] == "94.208.177.161"){
error_reporting(E_ALL);
ini_set('display_errors', 1);
}

    function __autoload($classname) {
        include("classes/class.$classname.php");
    }

?>
