<?php
require_once "config.php";
header("Content-Type: application/json");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "guru") {
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$profil = getProfilSekolah();
echo json_encode($profil);
?>