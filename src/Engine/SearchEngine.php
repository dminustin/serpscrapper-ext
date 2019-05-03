<?php

namespace SerpScraper\Engine;

use SerpScraper\Curl;

abstract class SearchEngine
{

    /**
     * @var Curl
     */
    protected $client;
    protected $preferences = array();

    // default request options to be used with each client request
    protected $default_options = array();

    protected $adult = 'OFF';

    function __construct()
    {

        // we use it!
        $this->client = new Curl();

        // where should we store the cookies for this search client instance? get_current_user()
        $this->client->setCookieDir(sys_get_temp_dir());

        $headers = array(
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'Keep-Alive'
        );

        // let's put some timeouts in case of slow proxies
        $curl = array(
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            // sometimes google routes the connection through IPv6 which just makes this more difficult to deal with - force it to always use IPv4
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        );

        $this->client->setHeader($headers);
        $this->client->setCurlOption($curl);
    }

    function setAdult($value)
    {
        if (in_array($value, ['OFF', 'MEDIUM', 'STRICT'])) {
            $this->adult = $value;
        } else {
            throw new \Exception('Bad value');
        }
    }

    public function setProxy($proxy)
    {
        $this->client->setProxy($proxy);
    }

    public abstract function search($query, $page_num);

    public function setPreference($name, $value)
    {
        $this->preferences[$name] = $value;
    }

    /**
     * Search by classname
     * @param $element string - div, a, p etc
     * @param $className string
     * @param $docDocument string|\DOMDocument
     * @return array
     */
    public function findByClassName($element, $className, $docDocument)
    {
        if (is_string($docDocument)) {
            $txt = $docDocument;
            $docDocument = new \DOMDocument();
            $docDocument->loadHTML($txt);
        }

        $finder = new \DomXPath($docDocument);
        $elements = $finder->query('//' . $element . '[contains(@class, \'' . $className . '\')]');
        return $elements;
    }


}
