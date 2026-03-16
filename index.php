<?php
/**
 * Root entry point.
 *
 * The web-server document root is the repository root.
 * This file simply forwards the browser to the login page inside public/.
 */
header('Location: public/login.php');
exit;
