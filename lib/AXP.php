<?php
/*************************************************
 Advanced XML Parser (AXP)
 ----------------------------------------------------------------------
 Copyright (C) 2009 by Mohamed Elkholy.
 https://sourceforge.net/projects/php-axp/
 ----------------------------------------------------------------------

 * @copyright Mohamed ELkholy 2009
 * @link https://sourceforge.net/projects/php-axp/
 * @author Mohamed ELkholy <mkh117@gmail.com>
 * @desc   Advanced PHP library to pase xml documents to array with many advanced options.
 * @package Advanced XML Parser (AXP)
 * @version 1.0 released in 9 May 2009
 * @license LGPL

 *************************************************/

define('_AXP_FILE', 201);
define('_AXP_URL', 202);
define('_AXP_CONTENT', 203);

class AXP
{
    /**
     * enable / disable debug
     *
     * @var boolen
     * @access private
     * @default true
     */
    private $logger;
    /**
     * if intializing class successed
     *
     * @var boolen
     * @access private
     * @default false
     */
    private $init;
    /**
     * Contains Xml resource handle
     *
     * @var resource
     * @access private
     */
    private $parser;
    /**
     * Contains Xml source data
     *
     * @var resource
     * @access private
     */
    private $xml_data;
    /**
     * Contains Xml parser result array
     *
     * @var array
     * @access private
     */
    private $xml_result_array;
    /**
     * Contains Xml parser encoding
     *
     * @var string
     * @access private
     * @default UTF-8
     */
    private $encoding;
    /**
     * enable / disable parsing tags attributes
     *
     * @var boolen
     * @access private
     * @default true
     */
    private $parse_attr;
    /**
     * Contains source types
     *
     * @var string
     * @access private
     */
    private $xml_src_types;
    /**
     * If XML parser cause an error this set to true
     *
     * @var string
     * @access private
     * @default false
     */
    private $error;
    /**
     * enable / disable using file_get_contents() function in grabbing local files contents
     *
     * @var boolen
     * @access private
     * @default false
     */
    private $simple_file_content_grabber_local;
    /**
     * enable / disable using file_get_contents() function in grabbing remote files contents
     *
     * @var boolen
     * @access private
     * @default false
     */
    private $simple_file_content_grabber_remote;
    /**
     * enable / disable using cURL extension in grabbing remote files contents
     *
     * @var boolen
     * @access private
     * @default true
     */
    private $use_curl;
    /**
     * Contains the start time of the parser
     *
     * @var integar
     * @access private
     * @default 0
     */
    private $start_time;
    /**
     * Contains the end time of the parser
     *
     * @var integar
     * @access private
     */
    private $end_time;

    /**
     * Constructor for initializing the class with default values.
     *
     * @param boolent enable / disable debug
     * @return void
     * @access public
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->error = false;
        $this->start_time = microtime();
        $this->end_time = 0;
        $this->logger->LogDebug('initializing Xml_Parser class');
        $this->init = $this->initiate();
        if (!$this->init)
        {
            $this->error = true;
            $this->logger->LogError('Error while initializing Xml_Parser class');
        }
    }

    /**
     *  initializing the class with default values.
     *
     * @return void
     * @access private
     */
    private function initiate()
    {
        $this->encoding = 'UTF-8';
        $this->xml_data = '';
        $this->xml_result_array = array();
        $this->simple_file_content_grabber_local = false;
        $this->simple_file_content_grabber_remote = false;
        $this->use_curl = true;
        $this->parse_attr = true;
        $this->init = false;
        $this->xml_src_types = array(_AXP_FILE, _AXP_URL, _AXP_CONTENT);
        $xml_ext = $this->_checkExtension('xml', 'xml_parser_create');
        return $xml_ext;
    }

    /**
     *  check if a php extension is loaded (if not try to load it).
     *
     * @param string extension name
     * @param string example function from extension
     * @return boolen Match success
     * @access public
     */
    public function _checkExtension($ext, $ext_fuc = null)
    {
        if (!extension_loaded($ext))
        {
            $this->logger->LogDebug("loading $ext extension");
            $prefix = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
            $dl_ext = @ dl($prefix . $ext . '.' . PHP_SHLIB_SUFFIX);
            ($ext_fuc != null) ? $check_ext_func = function_exists($ext_fuc) : '';
            if ($ext_fuc == null)
            {
                $loaded = $dl_ext;
            }
            else
            {
                $loaded = $check_ext_func;
            }
            if ($loaded)
            {
                $this->logger->LogDebug("extension loaded");
                return true;
            }
            else
            {
                $this->logger->LogDebug("error while loading $ext extension");
                return false;
            }
        }
        return true;
    }

    /**
     *  Set class options.
     *
     * @param string option name
     * @param boolen option value
     * @return boolen option value
     * @access public
     */
    public function setOpt($opt, $value)
    {
        if (!property_exists(__class__, $opt))
        {
            return false;
        }
        $this->$opt = (bool) $value;
        return (bool) $value;
    }

    /**
     *  Set XML parser encoding.
     *
     * @param string enconding name
     * @return boolen
     * @access public
     */
    public function setEncoding($encoding = null)
    {
        if (!$this->init)
        {
            return fasle;
        }
        return ($encoding == '') ? false : $this->encoding = $encoding;
        $this->logger->LogDebug("encoding set to $encoding");
    }

    /**
     *  Set XML source data given data and type of it that can be (_AXP_FILE) for local files and (_AXP_URL) for remote files and (_AXP_CONTENT) for XML data string
     *
     * @param string source data
     * @param string type of source data (_AXP_FILE or _AXP_URL or _AXP_CONTENT)
     * @return boolen Match success
     * @access public
     */
    public function setXmlData($src, $typeof = _AXP_FILE)
    {
        if (!$this->init)
        {
            return fasle;
        }
        $this->logger->LogDebug("setting xml source data");
        if ($src == '')
        {
            $this->error = true;
            $this->logger->LogError('xml source data could not be empty');
            return false;
        }
        if (!in_array($typeof, $this->xml_src_types))
        {
            $this->error = true;
            $this->logger->LogError('Unknown xml source data type. It can be _AXP_FILE or _AXP_URL or _AXP_CONTENT');
            return false;
        }
        switch ($typeof)
        {

            case _AXP_FILE :
                $this->xml_data = $this->getXmlFile($src);
                break;

            case _AXP_URL :
                $this->xml_data = $this->getRemoteXml($src);
                break;

            case _AXP_CONTENT :
                $this->xml_data = $src;
                break;

            default :
                $this->xml_data = $src;
        }
        return true;
    }

    /**
     *  Get local file content.
     *
     * @param string file path
     * @return string XML file content
     * @access private
     */
    private function getXmlFile($path)
    {
        if ($this->simple_file_content_grabber_local)
        {
            return $this->getXmlFileSimple($path);
        }
        $this->logger->LogDebug("opening local file $path for parsing");
        $fop = @ fopen($path, 'r');
        if (!$fop)
        {
            $this->error = true;
            $this->logger->LogError('Xml file cant be opened', 'fopen');
            return false;
        }
        else
        {
            $data = null;
            while (!feof($fop))
            {
                $data .= fread($fop, 1024);
            }
            fclose($fop);
        }
        return $data;
    }

    /**
     *  Get remote file content.
     *
     * @param string remote file path
     * @return string XML file content
     * @access private
     */
    private function getRemoteXml($path)
    {
        if ($this->simple_file_content_grabber_remote)
        {
            return $this->getXmlFileSimple($path);
        }
        $this->logger->LogDebug("opening remote file $path for parsing");
        ($this->use_curl) ? $cUrl_ext = $this->_checkExtension('curl', 'curl_init') : $cUrl_ext = false;
        if ($cUrl_ext && $this->use_curl)
        {
            // create a new cURL resource
            $this->logger->LogDebug("create a new cURL resource");
            $ch = curl_init();
            // set URL and other appropriate options
            curl_setopt($ch, CURLOPT_URL, $path);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // grab URL contents
            $xmlData = curl_exec($ch);

            // Check if any error occured
            if (curl_errno($ch))
            {
                $this->error = true;
                $this->logger->LogError(curl_error($ch));
            }

            // close cURL resource, and free up system resources
            curl_close($ch);
            $this->logger->LogDebug("grab URL contents and close cURL resource");
            return $xmlData;
        }
        if (!$cUrl_ext || !$this->use_curl)
        {
            $url = parse_url($path);
            $host = $url['host'];
            $fp = @ fsockopen($path, 80, $errno, $errstr, 30);
            if (!$fp)
            {
                $this->error = true;
                $this->logger->LogError("$errstr ($errno)");
                return false;
            }
            $out = "GET / HTTP/1.1\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Connection: Close\r\n\r\n";
            fwrite($fp, $out);
            $xmlData = '';
            while (!feof($fp))
            {
                $xmlData .= fgets($fp, 128);
            }
            fclose($fp);
            return $xmlData;
        }
    }
    /**
     *  Get xml file content witn file_get_contents function.
     *
     * @param string file path (remote or local)
     * @return string XML file content
     * @access private
     */
    private function getXmlFileSimple($path)
    {
        $this->logger->LogDebug("opening file $path with simple mode");
        $fileDate = @file_get_contents($path);
        if (!$fileDate)
        {
            $this->error = true;
            $this->logger->LogError('File is empty or not exist', 'file_get_contents');
        }
        return $fileDate;
    }

    /**
     *  loop XML parser result array.
     *
     * @param array XML parser result array
     * @param integar referance to array index number
     * @return array XML final parsed array
     * @access private
     */
    private function _xml_to_array_loop($parser_result, &$i)
    {
        $child = array();
        if (isset ($parser_result[$i]['value']))
        array_push($child, $parser_result[$i]['value']);
        while ($i++< count($parser_result))
        {
            switch ($parser_result[$i]['type'])
            {

                case 'cdata' :
                    array_push($child, $parser_result[$i]['value']);
                    break;

                case 'complete' :
                    $name = $parser_result[$i]['tag'];
                    if (!empty ($name))
                    {
                        if (isset ($parser_result[$i]['attributes']) && $this->parse_attr)
                        {
                            $child[$name]['attr'] = $parser_result[$i]['attributes'];
                        }
                        $child[$name]['name'] = $name;
                        $child[$name]['value'] = (isset($parser_result[$i]['value'])) ? ($parser_result[$i]['value']) : '';
                    }
                    break;

                case 'open' :
                    $name = $parser_result[$i]['tag'];
                    $size = isset ($child[$name]) ? sizeof($child[$name]) : 0;
                    (isset ($parser_result[$i]['attributes']) && $this->parse_attr) ? $child[$name]['attr'] = $parser_result[$i]['attributes'] : '';
                    $child[$name][$size] = $this->_xml_to_array_loop($parser_result, $i);
                    break;

                case 'close' :
                    return $child;
                    break;
            }
        }
        return $child;
    }

    /**
     *  Parse XML data and return result array.
     *
     * @return array XML final parsed array
     * @access private
     */
    public function parseXml()
    {
        if (!$this->init)
        {
            return fasle;
        }
        $xml = $this->xml_data;
        if ($xml == '')
        {
            $this->error = true;
            $this->logger->LogError('Xml source data can not be empty');
            return false;
        }
        $parser_result = array();
        $index = array();
        $array= array();
        $this->parser = xml_parser_create();
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, $this->encoding);
        $_parse = xml_parse_into_struct($this->parser, $xml, $parser_result, $index);
        if (!$_parse)
        {
            $this->error = true;
            $this->logger->LogError(xml_error_string(xml_get_error_code($this->parser)));
            return false;
        }
        xml_parser_free($this->parser);
        $i = 0;
        $array[$i] = array();
        $array[$i]['name'] = $parser_result[$i]['tag'];
        (isset ($parser_result[$i]['attributes']) && $this->parse_attr) ? $array[$i]['attr'] = $parser_result[$i]['attributes'] : '';
        $__arrXml = $this->_xml_to_array_loop($parser_result, $i);
        $array[1] = $__arrXml;
        return $array;
    }

    /**
     *  Parse XML data and return result array and measure parsing period.
     *
     * @return void
     * @access public
     */
    public function parse()
    {
        $this->logger->LogDebug("Start parsing ...");
        $this->xml_result_array = $this->parseXml();
        $this->end_time = microtime();
        $this->logger->LogDebug("Parsing completed" . (($this->error) ? " with errors " : " without errors ") . "and took " . $this->getTime() . " sec..");
    }

    /**
     *  Get final XML parser array.
     *
     * @return void
     * @access public
     */
    public function getArray()
    {
        return $this->xml_result_array;
    }

    /**
     *  Unset final XML parser array.
     *
     * @return boolen
     * @access public
     */
    public function freeMemory()
    {
        $this->xml_result_array = array();
        return true;
    }

    /**
     *  return XML parser stats.
     *
     * @return void
     * @access public
     */
    function __toString()
    {
        return "Xml Parsing completed" . (($this->error) ? " with errors " : " without errors ") . "and took " . $this->getTime() . " sec.";
    }
    /**
     *  return XML parsing time.
     *
     * @return integar
     * @access public
     */
    public function getTime()
    {
        $start_timex = explode(' ',$this->start_time);

        $end_timex = explode(' ',$this->end_time);

        if(sizeof($start_timex) != 2 || sizeof($end_timex) != 2)
        {
            return 0;
        }

        $seconds = $end_timex[1] - $start_timex[1];

        $miceroseconds =  $end_timex[0] - $start_timex[0];

        $total = ($seconds + $end_timex[0]) - $start_timex[0];

        return round($total,3);
    }
}
?>
