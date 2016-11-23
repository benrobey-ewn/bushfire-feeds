<?php
class CurlService {
    public function executeCurl($url, $state, $postfields=array()) {
        $rootDir = basename(dirname($_SERVER['PHP_SELF']));
        $tempDir = $rootDir . "/temp/";

        $chHeader = curl_init($url);
        curl_setopt($chHeader, CURLOPT_HEADER, 1);
        curl_setopt($chHeader, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($chHeader, CURLOPT_MAXREDIRS, 10);    
        
        curl_exec($chHeader);

        $contentType = curl_getinfo($chHeader, CURLINFO_CONTENT_TYPE);

        $tempContentFileType = ".tmp";
        if (preg_match('/(xml)/', $contentType)) {
            $tempContentFileType  = ".xml";
        }
        else if (preg_match('/(kmz)/', $contentType)) {
            $tempContentFileType  = ".kmz";
        }
        else if (preg_match('/(json)/', $contentType)) {
            $tempContentFileType  = ".json";
        }
        else if (preg_match('/(javascript)/', $contentType)) {
            $tempContentFileType = ".js";
        }
        else if (preg_match('/(html)/', $contentType)) {
            $tempContentFileType = '.html';
        }

        if (curl_errno($chHeader)) {
           throw new Exception("Unable to get headers:" . curl_error($chHeader));
        }
        $tempDir = $tempDir . $state . $tempContentFileType;
        curl_close($chHeader);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

        if ($postfields) {
            curl_setopt($ch, CURLOPT_POST, 1);
            $postfieldsStrParts = array();
            foreach ($postfields as $key=>$value) {
                $postfieldsStrParts[] = "$key=$value";
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $postfieldsStrParts));
        }
        set_time_limit(600);
        $data = curl_exec($ch);

        if (curl_errno($ch)) {
           throw new Exception("Error curling $url:" . curl_error($ch));
        }
        curl_close($ch);

        file_put_contents($tempDir, $data);
        return $data;
        /*
                $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

        if($postfields){
            curl_setopt($ch, CURLOPT_POST, 1);

            $postfieldsStrParts = array();

            foreach($postfields as $key=>$value){
                $postfieldsStrParts[] = "$key=$value";
            }

            curl_setopt($ch,
                        CURLOPT_POSTFIELDS,
                        implode('&', $postfieldsStrParts));
        }
        //curl_setopt($ch, CURLOPT_PROXY, 'proxy.softservecom.com:8080');
        set_time_limit(600);
        $data = curl_exec($ch);

       if(curl_errno($ch)){
           throw new Exception("Error curling $url:" . curl_error($ch));
       }

        #$xml = mb_convert_encoding($xml,"UTF-8","auto");
        curl_close($ch);
        #echo $xml;

        return $data;
        */
    }
}
?>