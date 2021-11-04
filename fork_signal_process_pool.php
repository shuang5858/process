<?php

/**
进程池
 */

echo "begin-signal-进程池".PHP_EOL;
pcntl_async_signals(true);

$aConfig = include('./config.php');
$iForkNum = $aConfig['process_num'];
$aChildPid = [];

cli_set_process_title("lis master process");

//信号处理
pcntl_signal(SIGCHLD,  "sig_handler");

if(!empty($iForkNum)){
	for ($i=1; $i <= $iForkNum ; $i++) {
		$iPid = getForkProcess();
	}
}

sleep(300000);

/** 信号处理 */
function sig_handler($signo)
{
	switch ($signo) {

        case SIGHUP:
            //处理SIGHUP信号
            echo '处理SIGHUP信号'.PHP_EOL;
            break;

        case SIGCHLD:
            echo "捕捉信号".PHP_EOL;

            while (pcntl_waitpid(0, $status) != -1) {
                echo '---子进程退出--'.PHP_EOL;;
                getForkProcess();
            }
            break;
	}
}

/** fork()进程 */
function getForkProcess()
{
    $iPid = pcntl_fork();
    if(-1 == $iPid){
        echo 'fock_error'.PHP_EOL;
    }
    if(0 == $iPid){

        echo '执行创建子进程'.PHP_EOL;
        sleep(500);

        exit;
    }elseif($iPid >0){
        return $iPid;
    }
}

