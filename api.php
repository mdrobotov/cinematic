<?php

require_once 'srv/api_handler.php';

$api = new ApiHandler();
header('Content-Type: application/json');
echo $api->handle();

?>