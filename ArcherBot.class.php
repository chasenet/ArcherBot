<?php namespace Pentest\ArcherBot;

require_once './vendor/autoload.php';
require_once './SimpleCrawler.class.php';

class ArcherBot {

    protected $objGoutte;
    protected $strTarget    = '';
    protected $strUserAgent = '';
    protected $arrChecks    = array();
    protected $arrFindings  = array();

    public function __construct($strTarget) {

        if(!class_exists('DOMDocument')) {

            throw new \Exception('DOMDocument is required to use this script');
        }

        $this->objGoutte = new \Goutte\Client();

        $this->arrChecks = include('./config.php');

        // Initally set the target
        $this->strTarget = (string)$strTarget;

        $this->parseOutFiles();
    }

    public function setTarget($strTarget) {

        if(!is_string($strTarget) || empty($strTarget)) {

            throw new \Exception('Failed to correctly set target - Ensure target is a valid string');
        }

        return ($this->strTarget = $strTarget);
    }

    public function parseOutFiles() {

        $objSimpleCrawler = new \SimpleCrawler($this->strTarget, 5);

        try {

            $objSimpleCrawler->traverse();

            $arrLinkDetails = $objSimpleCrawler->getLinksInfo();

        } catch(\Exception $e) {

            throw new \Exception('Could not get link info');
        }

        $this->arrFindings['count'] = 0;

        if(is_array($arrLinkDetails) && !empty($arrLinkDetails)) {

            foreach($arrLinkDetails as $arrLinks) {

                if(!isset($arrLinks['original_urls']) || empty($arrLinks['original_urls'])) {

                    continue;
                }

                $strLink = (is_array($arrLinks['original_urls']) && !empty($arrLinks['original_urls']) ? array_shift($arrLinks['original_urls']) : $arrLinks['original_urls']);

                try {
                    $objCrawler = $this->objGoutte->request('GET', $strLink);
                } catch (GuzzleHttp\Exception\RequestException $e) {
                    throw new \Exception('Could not query target.');
                }

                if (($objCrawler instanceof \Symfony\Component\DomCrawler\Crawler) === false) {
                    throw new \Exception('No DOM was returned.');
                }

                if ($this->objGoutte->getResponse()->getStatus() != '200') {
                    throw new \Exception('Request Status was not 200.');
                }

                if ($objCrawler->filter('script')->count() == 0) {
                    return false;
                }

                ++$this->arrFindings['count'];

                $objCrawler->filter('script')->each(function($objNode){

                    $mxdSrc = $objNode->attr('src');

                    // Make a copy
                    $strUrl = $mxdSrc;

                    // Check if it's relative or absolute path
                    if((substr($strUrl, 0, 7) !== 'http://') && (substr($strUrl, 0, 8) !== 'https://') && (substr($strUrl, 0, 2) !== '//')) {

                        // explode on slash
                        $arrUrlParts = explode('/', $this->strTarget);

                        // remove last part
                        array_pop($arrUrlParts);

                        $strUrl = implode($arrUrlParts, '/');

                        $strUrl = $strUrl . '/' . $mxdSrc;

                    } elseif((substr($strUrl, 0, 2) === '//')) {

                        $strUrl = 'http:' . $strUrl;
                    }

                    $this->performChecks($strUrl);
                });
            }
        }
    }

    public function performChecks($strUrl) {

        $strFileContents = $this->getFileContents($strUrl);

        if(false !== $strFileContents) {

            foreach($this->arrChecks as $strKey => $arrCheck){

                if(is_array($arrCheck) && !empty($arrCheck)) {

                    foreach($arrCheck as $strCheck) {

                        if(false !== stristr($strFileContents, $strCheck)) {

                            $this->arrFindings[$strKey]['url'] = $strUrl;

                            $this->arrFindings[$strKey]['vulnerability'] = $strCheck;
                        }
                    }
                }
            }
        }

        return $this->arrFindings;
    }

    public function getFileContents($strUrl) {

        $ch = curl_init($strUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $strResult = curl_exec($ch);

        curl_close($ch);

        return $strResult;
    }

    public function crawlSite() {

    }

    protected function checkIfCrawlable($strUrl) {
        if (empty($strUrl)) {
            return false;
        }

        // Returned deadlinks
        $arrStopLinks = array(
            '@^javascript\:void\(0\)$@',
            '@^#.*@',
        );

        foreach ($arrStopLinks as $strPattern) {
            if (preg_match($strPattern, $strUrl)) {
                return false;
            }
        }

        return true;
    }


    public function generateReport() {

        $strOut = '';

        $strOut .= sprintf("%d files searched\r\n", $this->arrFindings['count']);

        unset($this->arrFindings['count']);

        if(empty($this->arrFindings)) {

            $strOut .= 'Sorry, we couldn\'t find anything in the target specified.';

        } else {

            foreach($this->arrFindings as $strKey => $arrValues) {

                $strOut .= sprintf("Case: %s has %d vulnerabilities:\r\n%s", $strKey, count($arrValues), print_r($arrValues, true));
            }
        }

        return $strOut;
    }
}