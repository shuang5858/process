<?php

/**
进程池
*/

echo "begin".PHP_EOL;

$iForkNum = $aConfig['process_num'] = 2;
$aChildPid = [];

cli_set_process_title("lis master process");

if(!empty($iForkNum)){

	for ($i=1; $i <= $iForkNum ; $i++) {
		$iPid = getForkProcess();
        if($iPid >0 )
        { 
        	$aChildPid[] = $iPid;
        }
	}

	while (count($aChildPid) > 0 ){
		foreach($aChildPid as $iKey => $iPid) {
			$iRet = pcntl_waitpid($iPid, $iStatus, WNOHANG);
		    if ($iRet > 0) {

		        unset($aChildPid[$iKey]);

		        $iPid = getForkProcess();
		        if($iPid >0 )
		        { 
		        	$aChildPid[] = $iPid;
		        }

				echo json_encode($aChildPid).PHP_EOL;
		    }
		}
	}
}

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

