<?php

function write_to_log($msg)
{
    $log = date('Y-m-d H:i:s') . ': ' . print_r($msg, true) . "\r\n";
    file_put_contents('log.txt', $log, FILE_APPEND | LOCK_EX);
}

?>