<?php
/**
 * Created by PhpStorm.
 * User: lishuang
 * Date: 11/5/21
 * Time: 5:14 PM
 */

class ForkProcess
{
    public static $aConfig = array(
        'daemonize'            => false,
        'master_process_title' => 'master:process-pool',
        'worker_process_title' => 'worker:process-pool',
        'master_pid_file'      => "master.pid",
        'child_pid_file'       => "child.pid",
    );

    public function __construct()
    {
        $aConfigFile = parse_ini_file('./config.ini');

        self::$aConfig = array_merge(self::$aConfig, $aConfigFile );
        pcntl_async_signals(true);
    }
    public static $aChildPids = array();
    public static $sRoot      = "";
    public static $iMasterPid = 0;

    public function start()
    {

        $this->initDaemon();
        $this->initSignalHandler();
        $this->getForkProcess(self::$aConfig['process_num']);
        $this->initMaster();

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
                cli_set_process_title(self::$aConfig['worker_process_title']);
                file_put_contents(self::$aConfig['log_path'], '创建子进程'.PHP_EOL, FILE_APPEND);

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
            cli_set_process_title(self::$aConfig['worker_process_title']);
            file_put_contents(self::$aConfig['log_path'], '重新创建子进程'.PHP_EOL, FILE_APPEND);

            sleep(mt_rand(10, 20));
            exit;
        }
        if ($iNewChildPid > 0) {
            self::$aChildPids[] = $iExitChildPid;
        }
    }

    /** 信号处理 */
    function signalHandler($iSigno)
    {
        switch ($iSigno) {

            case SIGCHLD:
                file_put_contents(self::$aConfig['log_path'], '回收进程'.PHP_EOL, FILE_APPEND);
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
        umask(0);
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

        for ($i = 1; $i <= 5; $i++) {
            sleep(1);
            file_put_contents(self::$aConfig['log_path'], $i.PHP_EOL, FILE_APPEND);
        }
        chdir(self::$sRoot);
        return $iPid;

    }
    public function initMaster()
    {
        $iMasterPid = posix_getpid();
        self::$iMasterPid = $iMasterPid;
        cli_set_process_title(self::$aConfig['master_process_title']);

    }

}
