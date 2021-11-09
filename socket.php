<?php

$sIp = '127.0.0.1';
$iPort = 6668;
// 1.one process on demond
// 2.apache pre-fork
$rSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//socket_set_option($rSocket, SOL_SOCKET, SO_REUSEADDR, 1);
//socket_set_nonblock($rSocket);
socket_bind($rSocket, $sIp, $iPort);
socket_listen($rSocket, 1024);
cli_set_process_title("haohaozhu http svr | master");

$iChildPid = [];
for($i = 1; $i <= 3; $i++) {
    $iPid = pcntl_fork();
    if (0 == $iPid) {
        cli_set_process_title("haohaozhu http svr | chilb");
        while(true) {
            $iConnection = socket_accept($rSocket);
            if (false === $iConnection) {
                sleep(1);
                echo "error | ".socket_strerror(socket_last_error()).PHP_EOL;
                continue;
            }
            $sData = socket_read($iConnection, 4096);
            //echo $sData;
            $sRespHeader = "HTTP/1.1 200 OK
Content-Type: text/html;charset=UTF-8
Content-Length: 12
Date: Wed, 06 Jun 2018 07:08:42 GMT
 
hello world!
";
            $iLength = strlen($sRespHeader);
            $mRet = socket_write($iConnection, $sRespHeader, $iLength);
            //var_dump($mRet);
            //sleep(1000);
            socket_close($iConnection);
        }
    }
    $iChildPid[] = $iPid;
}

echo "Haohaozhu Http Sever | ".json_encode($iChildPid).PHP_EOL;
while(true) {
    sleep(1);
}

while(true) {
    //$iPid = pcntl_fork();
    $iConnection = socket_accept($rSocket);
    $iPid = pcntl_fork();
    if (0 === $iPid) {
        cli_set_process_title("haohaozhu http svr | child");
        echo "有新的用户 | pid=".posix_getpid().PHP_EOL;
        //var_dump($iConnection);
        $sData = socket_read($iConnection, 4096);
        echo $sData;
        $sRespHeader = "HTTP/1.1 200 OK
Content-Type: text/html;charset=UTF-8
Content-Length: 12
Date: Wed, 06 Jun 2018 07:08:42 GMT
 
hello world!
";
        $iLength = strlen($sRespHeader);
        $mRet = socket_write($iConnection, $sRespHeader, $iLength);
        var_dump($mRet);
        //sleep(1000);
        socket_close($iConnection);
        echo "子进程退出".PHP_EOL;
        exit;
    }

}