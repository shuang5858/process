<?php
/**
 * Created by PhpStorm.
 * User: lishuang
 * Date: 11/5/21
 * Time: 5:14 PM
 */

class ForkProcess
{
    public $aConfig;
    public function __construct()
    {
        $this->aConfig = parse_ini_file('./config.ini');
        pcntl_async_signals(true);
    }
    public static $aChildPids = array();

    public function start()
    {
        $this->initDaemon();
        cli_set_process_title("lis master process");
        $this->initSignalHandler();

        pcntl_signal(SIGCHLD, array($this, 'signalHandler'), true);

        $iForkNum = $this->aConfig['process_num'];
        $this->getForkProcess($iForkNum);

        while(true){
            sleep(1);
        }
    }

    /** 创建子进程 */
    public function getForkProcess($iNum)
    {
        for($i = 1; $i <= $iNum; $i++) {
            $iPid = pcntl_fork();
            if ($iPid < 0) {
                exit("err in fork".PHP_EOL);
            } else if (0 == $iPid) {
                cli_set_process_title('lis child process');
                echo "创建子进程".PHP_EOL;
                sleep(mt_rand(10, 20));
                exit;
            } else if ($iPid > 0) {
                self::$aChildPids[] = $iPid;
            }
        }
    }

    public function reForkProcess()
    {
        $iExitChildPid = pcntl_waitpid(0, $iStatus);
        foreach(self::$aChildPids as $iKey => $iChildPid) {
            if ($iChildPid == $iExitChildPid) {
                unset(self::$aChildPids[$iExitChildPid]);
            }
        }
        $iNewChildPid = pcntl_fork();
        if ($iNewChildPid < 0) {
            exit("err in fork".PHP_EOL);
        }
        if (0 == $iNewChildPid) {
            cli_set_process_title( "lis child process | new");
            echo "重新创建子进程".PHP_EOL;
            sleep(mt_rand(1, 10));
            exit;
        }
        if ($iNewChildPid > 0) {
            self::$aChildPids[] = $iExitChildPid;
        }
    }

    /** 信号处理 */
    function signalHandler($iSigno)
    {
        echo "信号".PHP_EOL;
        switch ($iSigno) {

            case SIGCHLD:
                echo "回收进程".PHP_EOL;
                $this->reForkProcess();
                break;
        }
    }

    public function initSignalHandler()
    {
        pcntl_signal(SIGCHLD, array($this, 'signalHandler'), true);
    }


    public function initDaemon()
    {
        $iPid = pcntl_fork();
        if ($iPid < 0) {
            exit("fork err".PHP_EOL);
        }
        if ($iPid > 0) {
            exit;
        }
        // fork-twice avoid SVR4 bug
        $iPid = pcntl_fork();
        if ($iPid < 0) {
            exit("fork err".PHP_EOL);
        }
        if ($iPid > 0) {
            exit;
        }
        $iRet = posix_setsid();
        if (-1 === $iRet) {
            exit("posix_setsid err".PHP_EOL);
        }

        for ($i = 1; $i <= 100; $i++) {
            sleep(1);
            file_put_contents('daemon.log', $i.PHP_EOL, FILE_APPEND);
        }
        return $iPid;

    }

}
