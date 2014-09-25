<?php namespace Pentest\ArcherBot;

require_once './vendor/autoload.php';

class ArcherBot {

    protected $objGoutte;
    protected $strTarget    = '';
    protected $strUserAgent = '';
    protected $arrChecks    = array();
    protected $arrFindings  = array();

    public function __construct($strTarget) {

        $this->objGoutte = new \Goutte\Client();

        $this->arrChecks = include('./config.php');

        // Initally set the target
        $this->strTarget = (string)$strTarget;
    }

    public function setTarget($strTarget) {

        if(!is_string($strTarget) || empty($strTarget)) {

            throw new \Exception('Failed to correctly set target - Ensure target is a valid string');
        }

        return ($this->strTarget = $strTarget);
    }

    public function parseOutFiles() {

        $objCrawler = $this->objGoutte->request('GET', $this->strTarget);

        $objCrawler->filter('script')->each(function($objNode){

            $mxdSrc = $objNode->attr('src');

            if(is_null($mxdSrc)) {

                // continue;
                return false;
            }

            // Make a copy
            $strUrl = $mxdSrc;

            // Check if it's relative or absolute path
            if((substr($strUrl, 0, 7) !== 'http://') && (substr($strUrl, 0, 8) !== 'https://') && (substr($strUrl, 0, 2) !== '//')) {

                $strUrl = $this->strTarget . '/' . $mxdSrc;

            } elseif((substr($strUrl, 0, 2) === '//')) {

                $strUrl = 'http:' . $strUrl;
            }

            $mxdJavascriptFile = file_get_contents($strUrl);

            if(false !== $mxdJavascriptFile) {

                foreach($this->arrChecks as $strKey => $arrCheck){

                    if(is_array($arrCheck) && !empty($arrCheck)) {

                        foreach($arrCheck as $strCheck) {

                            if(false !== stristr($mxdJavascriptFile, $strCheck)) {

                                $this->arrFindings[$strKey] = $strCheck;
                            }
                        }
                    }
                }
            }
        });

        return $this->generateReport();
    }

    public function generateReport() {

        $strOut = '';

        if(empty($this->arrFindings)) {

            $strOut = 'Sorry, we couldn\'t find anything in the target specified.';
        } else {

            foreach($this->arrFindings as $strKey => $arrValues) {
                $strOut .= sprintf("Case: %1$s has %2$d vulnerabilities:\r\n%3$s", $strKey, count($arrValues), print_r($arrValues, true));
            }
        }

        return $strOut;
    }
}

if(count($argv) >= 2) {

    $archer = new ArcherBot($argv[1]);

    $archer->generateReport();
}
