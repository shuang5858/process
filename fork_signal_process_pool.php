<?php

/**
进程池
 */

echo "begin-signal-进程池".PHP_EOL;
pcntl_async_signals(true);

$aConfig = include('./config.php');
$iForkNum = $aConfig['process_num'] = 3;
$aChildPid = [];

cli_set_process_title("lis master process");

//信号处理
pcntl_signal(SIGCHLD,  "sig_handler");

if(!empty($iForkNum)){

	for ($i=1; $i <= $iForkNum ; $i++) {
		$iPid = getForkProcess();
        if($iPid >0 )
        { 
        	$aChildPid[] = $iPid;
        }
	}


}

sleep(300000);

/** 信号处理 */
function sig_handler($signo)
{
	switch ($signo) {
		 case SIGHUP:
             //处理SIGHUP信号
		 	 echo '处理SIGHUP信号';
             break;

		case SIGCHLD:
      
             echo "捕捉到子进程推出...信号回调处理完毕--回收旧的进程，并拉起新的进程补充进程池数量".PHP_EOL;
             getForkProcess();
             sleep(3);

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
		cli_set_process_title("lis child process");

		echo '子进程执行'.PHP_EOL;
		sleep(5);

		exit;
	}elseif($iPid >0){
		return $iPid;
	}
}
