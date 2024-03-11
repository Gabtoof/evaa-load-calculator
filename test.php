<?php
require_once('/var/www/html/wp-load.php'); // Adjust the path to wp-load.php as necessary
//require_once('/mnt/user/appdata/wordpress/wp-load.php'); 

if (defined('GITHUB_PAT')) {
    $PAT = constant('GITHUB_PAT');
    echo "Value of GITHUB_PAT: $PAT";
} else {
    echo "GITHUB_PAT is not defined";
}

if (defined('GITHUB_PAT')) {
    $PAT = constant('GITHUB_PAT');
    echo "Value of GITHUB_PAT: $PAT";
} else {
    echo "GITHUB_PAT is not defined";
}
?>