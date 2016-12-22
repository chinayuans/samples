<?php 

function downloadFiles($data){
        @set_time_limit(0); //设置脚本最大执行时间,最大的执行时间，单位为秒。如果设置为0（零），没有时间方面的限制。
        
        $fancyName = $data["zipPkgName"];
        
        $fancyName = preg_replace('/^.+[\\\\\\/]/', '', $fancyName);
        
        $dirsAndFiles = $data["fileLists"];
        
        header('Content-Type: application/octet-stream');
        $user_agent = $_SERVER["HTTP_USER_AGENT"];
        $encoded_filename = urlencode($fancyName);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        
        //解决文件名中文乱码以及含有空格所导致的下载问题，不同浏览器不同的做法
        if (preg_match("/MSIE/", $user_agent)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox/", $user_agent)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $fancyName . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $fancyName . '"');
        }
        
        header('Content-Transfer-Encoding: binary');
        ob_clean();
        flush();
        
        $parentPath = $data["parentPath"];
        
        /**
         * -c 执行打包命令
         * -z 打包的同时执行gzip压缩命令执行压缩
         * 
         * 注意事项:
         * 1. 没有指定打包文件名，这样就避免将输出流定向到包文件名上去，实现了流的传递;
         * 2. "2>/dev/null" 语句的重要性:
         *    "2>" 表示将标准错误输出重定向; 
         *     /dev/null可以避开众多无用出错信息的干扰,避免输出任何无关的东西到输出流上去,
         *     仅仅输出有效的压缩字节流: echo fread($fp, 8192);
         *     没有2>/dev/null, 压缩中会出现下列错误：
         *     tar: removing leading '/' from member names
         *     tar: /IDE0/oam/cli/oam_xmlrpc_exception.dat: No such file or directory
         *     tar: /IDE0/s5log: No such file or directory
         *     tar: /IDE0/core/IDE0/brscap_*: No such file or directory
         *     tar: error exit delayed from previous errors
         *     
         *     将来解压tar文件的时候，又会出现下面的错误，导致无法成功解压:
         *     “tar: Skipping to next header”
         *     “tar: Exiting with failure status due to previous errors”
         * 3. 降低CPU占用率:
         *     方法一： 非实时程序
         *     If you wish to run a command which typically uses a lot of CPU (for example, 
         *     running tar on a large file), then you probably don’t want to bog down your whole system 
         *     with it. Linux systems provide the nice command to control your process priority at runtime, 
         *     or renice to change the priority of an already running process. 
         *     The full manpage has help, but the command if very easy to use:
         *     $ nice -n prioritylevel /command/to/run
         *     
         *    The priority level runs from -20 (top priority) to 19 (lowest). 
         *    For example, to run tar and gzip at a the lowest priority level:
         *    $ nice -n 19 tar -czvf file.tar.gz bigfiletocompress
         *
         *    方法二: 实时程序 
         *    chrt [options] prio command [arg]... 
         *    chrt [options] -p [prio] pid
         *     -o    set policy scheduling policy to SCHED_OTHER
         *     -f    set scheduling policy to SCHED_FIFO
         *     -r    set scheduling policy to SCHED_RR (the default)
         *    
         *    ~ # chrt -m ls 
         *    SCHED_FIFO min/max priority     : 1/99
         *    SCHED_RR min/max priority       : 1/99
         *    SCHED_OTHER min/max priority    : 0/0
         *
         *    tss 之下发现tar命令采用的是fifo策略
         */        
        $cmd = 'cd "' . $parentPath . '"; chrt -o 0 tar -cz ' . $dirsAndFiles . ' 2>/dev/null'; //chrt -o 0为更改当前进程为普通进程，不是实时进程
        $fp = popen($cmd, 'r'); //popen: 打开进程文件指针

        while (!feof($fp)) {
            proc_nice(19);  //更改普通进程的nice级别到优先级最低
            echo fread($fp, 8192);
            ob_flush();
            flush();
        }

        pclose($fp);

}

/**
 * 要压缩的目录以及文件列表:
 * "/IDE0/EXCINFO",
 * "/IDE0/Exc_Server.txt",
 * "/IDE0/dbms.log",
 * "/IDE0/dbms_bak.log",
 * "/IDE0/.command_history",
 * "/IDE0/oam/cli/oam_xmlrpc_history.dat",
 * "/IDE0/oam/cli/oam_xmlrpc_exception.dat",
 * "/IDE0/corosync.log",
 * "/IDE0/log",
 * 内核态core：/IDE0/s5log下面
 * 用户态core：/IDE0下面core开头的文件
 * /IDE0 # ls -l /IDE0/brscap_*  * 
 */

$file_path = $_GET['fpath'];
$file_name = $_GET['fname'];

if( !isset($file_path) || empty($file_path) ) {
    //如果变量没赋值或者为空值
    $file_path = "/IDE0";
}

if( !isset($file_name) || empty($file_name) ) {
    //如果变量没赋值或者为空值
    $file_name = 'syslog-' . time() . '.tar.gz';
}


//入参准备
$data = array(
    "zipPkgName" => $file_name,
    "parentPath" => $file_path, //"/IDE0",
    "fileLists" => '/IDE0/EXCINFO' .
        ' /IDE0/Exc_Server.txt' .
        ' /IDE0/dbms.log' .
        ' /IDE0/dbms_bak.log' .
        ' /IDE0/.command_history' .
        ' /IDE0/oam/cli/oam_xmlrpc_history.dat' .
        ' /IDE0/oam/cli/oam_xmlrpc_exception.dat' .
        ' /IDE0/corosync.log' .
        ' /IDE0/log' .
        ' /IDE0/s5log' .
        ' /IDE0/core_*' .
        ' /IDE0/brscap_*'
);

downloadFiles($data);

?>
