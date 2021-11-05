<?php
/**
 * Created by PhpStorm.
 * User: lishuang
 * Date: 11/5/21
 * Time: 5:14 PM
 */

class FockProcess
{
    public $aConfig;
    public function __construct()
    {
        $this->aConfig = parse_ini_file('./config.ini');
        pcntl_async_signals(true);
    }

    public function forkProcess(){

        echo "begin-signal-进程池".PHP_EOL;

        cli_set_process_title("lis master process");
        pcntl_signal(SIGCHLD,  "sig_handler"); //信号处理

        $iForkNum = $this->aConfig['process_num'];

        if(!empty($iForkNum)){
            echo  '创建start'.PHP_EOL;
            for ($i=1; $i <= $iForkNum ; $i++) {
                self::getForkProcess();
            }
        }

        while(true){
            sleep(1);
        }
    }

    /** 信号处理 */
    function sig_handler($signo)
    {
        switch ($signo) {
            case SIGCHLD:
                echo "当子进程停止或退出时通知父进程".PHP_EOL;

                $iRes = pcntl_waitpid(-1, $status, WNOHANG);
                echo $iRes.'---子进程退出--'.PHP_EOL;
                self::getForkProcess();

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

            echo '执行创建子进程'.PHP_EOL;
            sleep(500);

            exit;
        }elseif($iPid >0){
            return $iPid;
        }
    }
}
