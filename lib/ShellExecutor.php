<?php

class ShellExecutor {

    function background($Command, $job, $Priority = 0) {
        $OSType = strtolower(php_uname());
        //regex get filenane no ext \/bushfire-traffic\/(.+?)\.php$
        //$stateLog = preg_match('/\/bushfire-traffic\/(.+?)\.php$/', $Command, $stateLog);

        if($Priority) {
            if (strpos($OSType, "darwin") !== false) {
                // It's Mac
                $PID = shell_exec("$Command & echo $!");
            } 
            else {
                $PID = shell_exec("nohup nice -n $Priority $Command > /var/www/html/bushfire-traffic/log/log_output_" . $job . ".log & echo $!");
            }
        }
        else {
            if (strpos($OSType, "darwin") !== false) {
                // It's Mac
                $PID = shell_exec("$Command & echo $!");
            } 
            else {
                // !Mac
                $PID = shell_exec("nohup $Command > /var/www/html/bushfire-traffic/log/log_output_" . $job . ".log & echo $!");
            }
        }
        return(trim($PID));
    }

    function andWait($Command, $job, $Priority = 0) {
        $OSType = strtolower(php_uname());
        if($Priority) {
            if (strpos($OSType, "darwin") !== false) {
                // It's Mac
                shell_exec("$Command");
            } 
            else {

                shell_exec("nohup nice -n $Priority $Command > /var/www/html/bushfire-traffic/log/log_output_" . $job . ".log & echo $!");
            }
        }
        else {
            if (strpos($OSType, "darwin") !== false) {
                // It's Mac
                shell_exec("$Command");
            } 
            else {
                // !Mac
                shell_exec("nohup $Command > /var/www/html/bushfire-traffic/log/log_output_" . $job . ".log & echo $!");
            }
        }
        return null;
    }

    function is_running($PID) {
        exec("ps $PID", $ProcessState);
        return(count($ProcessState) >= 2);
    }

    function kill($PID) {
        if(ShellExecutor::is_running($PID)) {
            exec("kill -KILL $PID");
            return true;
        }
        else {
            return false;
        }
    }
};