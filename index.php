<?php
require __DIR__ . '/loader.php';
require __DIR__ . '/custom/LincettoCustomServer.php';
$server = new LincettoCustomServer();
$server->handleRequest();