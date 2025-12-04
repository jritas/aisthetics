<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Front controller για Plesk: προωθεί όλα τα requests
// στο κανονικό public/index.php της εφαρμογής.
require __DIR__ . '/public/index.php';
