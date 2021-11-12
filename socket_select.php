<?php

$sIp ='127.0.0.1';
$iPort = 8080;

$rListen = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_set_option($rListen, SOL_SOCKET, SO_REUSEADDR, 1);
socket_set_option($rListen, SOL_SOCKET, SO_REUSEPORT, 1);

socket_set_nonblock($rListen);
socket_bind($rListen,$sIp,$iPort);
socket_listen($rListen, 1024);


$aReads   = [];
$aWrites  = [];
$aExps    = [];
$aClients = [];
$aClients[intval($rListen)] = $rListen;

while (true){

    $aReads = $aClients;

    $iNum = socket_select($aReads,$aWrites,$aExps,NULL);

    // 判断$aReads可读的socket中，是否包含了$rListen
    if (in_array($rListen, $aReads)) {
        $rNewClient = socket_accept($rListen);
        //var_dump($rNewClient);
        $aClients[intval($rNewClient)] = $rNewClient;
        unset($aReads[intval($rListen)]);
    }

    //var_dump($aReads);  //var_dump($aReads);
    foreach($aReads as $rReadItem) {
        $sContent = socket_read($rReadItem, 2048);
        //echo $sContent;
        foreach($aClients as $aClientItem) {
            if (intval($aClientItem) == intval($rListen)) {
                continue;
            }
            if (intval($rReadItem) == intval($aClientItem)) {
                continue;
            }
            socket_write($aClientItem, $sContent, strlen($sContent));
        }
    }

    sleep(1);
}
