<?php session_start(); require "config/database.php"; echo json_encode($_SESSION["user"]); ?>
