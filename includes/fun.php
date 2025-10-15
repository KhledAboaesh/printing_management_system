<?php
// ملف includes/functions.php

require_once 'db.php';
require_once 'includes/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (isset($_POST['type'])) {
    if ($_POST['type'] == 001) {
        $jsonString = file_get_contents('jsons/users.json');
        $date = json_decode($jsonString, true);
        if (isset($date[$_POST['id_user']])) {
            print_r(json_encode($date[$_POST['id_user']]));
        } else {
            print_r(0);
        }
    }
}