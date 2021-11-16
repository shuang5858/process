<?php

pcntl_async_signals(true);

class ForkProcess
{
    public static $aConfig = array(
        'daemonize'            => true,
        'master_process_title' => 'master:process-pool',
        'worker_process_title' => 'worker:process-pool',
        'master_pid_file'      => "master.pid",
        'child_pid_file'       => "child.pid",
    );

    public static $aChildPids = array();
    public static $sRoot      = "";
    public static $iMasterPid = 0;

    public function __construct()
    {
        $aConfigFile = parse_ini_file('./config.ini');

        self::$aConfig = array_merge(self::$aConfig, $aConfigFile);

        self::$sRoot = __DIR__;
    }

    public function start()
    {
        $bDaemon = self::$aConfig['daemonize'];
        if (1 == $bDaemon || true == $bDaemon) {
            $iDaemonPid =  $this->initDaemon();
            file_put_contents(self::$aConfig['log_path'], 'iDaemonPid'.$iDaemonPid.PHP_EOL, FILE_APPEND);
        }

        $this->initSignalHandler();
        $this->ForkProcess(self::$aConfig['process_num']);
        $this->initMaster();

        if (1 == $bDaemon || true == $bDaemon) {
            $this->flushData2FileAfterAllOver();
        }

        while(true){
            sleep(1);
        }
    }

    /** 创建子进程 */
    public function ForkProcess($iNum)
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

            case SIGTERM: //daemon终止前需要做一些清理工作 init发完SIGTERM 5秒后会发送一个SIGKILL信号
                $this->stopAll();
                break;

        }
    }

    public function initSignalHandler()
    {
        pcntl_signal(SIGCHLD, array($this, 'signalHandler'), true);
        pcntl_signal(SIGTERM, array($this, 'signalHandler'), true);
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

    public function getStatus()
    {
        $sChildPidFile  = self::$sRoot.DIRECTORY_SEPARATOR.self::$aConfig['child_pid_file'];
        $sChildPidFileDir  = dirname($sChildPidFile);
        if (!is_dir($sChildPidFileDir)) {
            mkdir($sChildPidFileDir, 0777, true);
        }
        file_put_contents($sChildPidFile, json_encode(self::$aChildPids));
        print_r(self::$iMasterPid);
        print_r(self::$aChildPids);
    }

    public function stop()
    {
        $sConfigFile = './config.ini';
        if (is_file($sConfigFile)) {
            $aConfigFromFile = parse_ini_file("./config.ini");
        }
        self::$aConfig = array_merge(self::$aConfig, $aConfigFromFile);
        $sMasterPidFile = __DIR__.DIRECTORY_SEPARATOR.self::$aConfig['master_pid_file'];
        $iMasterPid = file_get_contents($sMasterPidFile);
        posix_kill($iMasterPid, SIGTERM);
    }

    public function stopAll()
    {
        $sMasterPidFile = self::$sRoot.DIRECTORY_SEPARATOR.self::$aConfig['master_pid_file'];
        $sChildPidFile  = self::$sRoot.DIRECTORY_SEPARATOR.self::$aConfig['child_pid_file'];
        @unlink($sMasterPidFile);
        @unlink($sChildPidFile);
        $aChildPids = self::$aChildPids;
        $iMasterPid = self::$iMasterPid;
        posix_kill($iMasterPid, SIGKILL);
        sleep(1);
        while (count($aChildPids) > 0) {
            foreach($aChildPids as $iChildPid) {
                posix_kill($iChildPid, SIGKILL);
            }
        }
    }

    public function flushData2FileAfterAllOver()
    {
        $sMasterPidFile = self::$sRoot.DIRECTORY_SEPARATOR.self::$aConfig['master_pid_file'];
        $sChildPidFile  = self::$sRoot.DIRECTORY_SEPARATOR.self::$aConfig['child_pid_file'];
        $sMasterPidFileDir = dirname($sMasterPidFile);
        $sChildPidFileDir  = dirname($sChildPidFile);
        if (!is_dir($sMasterPidFileDir)) {
            mkdir($sMasterPidFileDir, 0777, true);
        }
        if (!is_dir($sChildPidFileDir)) {
            mkdir($sChildPidFileDir, 0777, true);
        }
        file_put_contents($sMasterPidFile, self::$iMasterPid);
    }
}
