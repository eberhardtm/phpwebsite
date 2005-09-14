<?php

if (!defined('PHPWS_SOURCE_DIR')) {
    include '../../config/core/404.html';
    exit();
}

if (isset($_REQUEST['wp_user'])) {
    PHPWS_Core::initModClass('webpage', 'User.php');
} elseif(isset($_REQUEST['wp_admin'])) {
    PHPWS_Core::initModClass('webpage', 'Admin.php');
    Webpage_Admin::main();
} elseif (Current_User::allow('webpage')) {
    PHPWS_Core::initModClass('webpage', 'Admin.php');
    Webpage_Admin::main();
} else {
    PHPWS_Core::errorPage('404');
}

?>