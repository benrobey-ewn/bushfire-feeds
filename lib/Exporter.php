<?php

class Exporter{

    public function export($hours){

        $exportAfterTimeStamp = time() - $hours * 60 * 60;


        $sql = "SELECT *
                    FROM  `" . DB_BASE . "`.`" . INCIDENTS_TABLE . "`
                    WHERE `unixtimestamp` >= $exportAfterTimeStamp";

        $rows = Db::getInstance()->getRows($sql);

        $handle = @fopen(EXPORT_PATH, 'w');

        if($handle == FALSE){
            throw new PermissionDeniedFeedException("Permission denied to open " . EXPORT_PATH);
        }

        foreach($rows as $row){

            $date = date('Y-m-d H:i \G\M\T', $row['unixtimestamp']);

            $tline = '%s,%s,"%s",%s,"%s"';

            $line = sprintf($tline, $date, $row['state'],
            $row['title'], $row['category'],
            str_replace("\r", "", str_replace("\n", "", $row['description'])));

            fwrite($handle, "$line\n");
        }

        fclose($handle);
    }
}