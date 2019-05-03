<?php

namespace SerpScraper;

class Curl
{

    public $error = false;
    public $info = array();

    // curl handle
    private $options = array();

    // cookie stuff
    private $headers = array();
    private $handle;

    // list of user-agents for proxies to shuffle with
    private $cookie_dir = '';

    // last messages
    private $cookie_prefix = 'curl_cookie_';
    private $agents = array(
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.5 (KHTML, like Gecko) Chrome/19.0.1084.56 Safari/536.5",
        "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:13.0) Gecko/20100101 Firefox/13.0.1",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_4) AppleWebKit/534.57.2 (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2",
        "Opera/9.80 (Windows NT 5.1; U) Presto/2.10.229 Version/11.60",
        "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)",
        "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)",
        "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)",
        "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6",
        "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)",
        "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30)",
        "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)"
    );

    function __construct()
    {

        $curl = array(
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,

            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        );

        $this->options = $curl;

        $this->headers = array(
            'Accept' => '*/*',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'Keep-Alive'
        );

        $this->setCookieDir(sys_get_temp_dir());
        $this->setProfileId('default');
    }

    public function setCookieDir($cookie_dir, $cookie_prefix = '')
    {

        // validate cookie dir
        if (!is_dir($cookie_dir)) {
            throw new \InvalidArgumentException('Cookie directory is invalid or non-existant!');
        } else if (!is_writable($cookie_dir)) {
            throw new \InvalidArgumentException('Cookie directory: ' . $cookie_dir . ' is not writable! Chmod it to 777 and try again.');
        }

        // cookie_dir is valid?
        $this->cookie_dir = $cookie_dir;
    }

    public function setProfileId($id)
    {

        if (!is_string($id)) {
            $id = 'default';
        }

        // Windows does not like special characters in filenames...
        $id = str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '-', $id);

        // generate random user agent using profile_id as salt
        $hash = md5($id);

        // max at 7, otherwise it generates an INT beyond PHP_INT_MAX for that system
        $hash = substr($hash, 0, 7);

        $rand_index = hexdec($hash) % count($this->agents);

        // set it
        $this->headers['User-Agent'] = $this->agents[$rand_index];

        // construct a platform-neutral cookie path based on profile_id
        $cookie_file = $this->cookie_dir . DIRECTORY_SEPARATOR . $this->cookie_prefix . $id;

        // I think we need this?
        //$cookie_file = realpath($cookie_file);

        // TODO: if this cookie_file already exists and was created by another USER, then writing to it will fail and RuntimeException will be thrown by Guzzle
        if (!file_exists($cookie_file)) {
            file_put_contents($cookie_file, '');
        }

        // read from
        $this->options[CURLOPT_COOKIEFILE] = $cookie_file;

        // write to
        $this->options[CURLOPT_COOKIEJAR] = $cookie_file;
    }

    function setCurlOption($option, $value = false)
    {

        if (is_array($option)) {
            $this->options = array_replace($this->options, $option);
        } else {
            $this->options[$option] = $value;
        }
    }

    // each profile uses different cookie file and user-agent

    function setHeader($name, $value = false)
    {

        if (is_array($name)) {
            $this->headers = $name;
        } else {
            $this->headers[$name] = $value;
        }
    }

    // proxy must be in username:password@IP:Port format

    function setProxy($proxy, $new_profile = true)
    {

        if ($proxy == false) {
            $this->options[CURLOPT_PROXY] = null;
        } else {
            $this->options[CURLOPT_PROXY] = $proxy;
        }

        // do we want to use a different cookie profile for this proxy?
        if ($new_profile) {
            $this->setProfileId($proxy);
        }
    }

    public function post($url, $data)
    {

        $options = array();
        $options[CURLOPT_POST] = 1;
        $options[CURLOPT_POSTFIELDS] = $data;

        return $this->get($url, $options);
    }

    // TODO: rename this method to request
    public function get($url, $options = array())
    {

        // CURLOPT_HTTPGET
        $this->handle = curl_init($url);

        // merge defaults with request specific options
        $options = array_replace($options, $this->options);
        curl_setopt_array($this->handle, $options);

        $flat = array();
        foreach ($this->headers as $name => $value) {
            $flat[] = $name . ': ' . $value;
        }

        // we want the headers
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->handle, CURLOPT_HEADER, 1);
        curl_setopt($this->handle, CURLOPT_HTTPHEADER, $flat);

        // apparently curl will decode gzip automatically when this is empty
        curl_setopt($this->handle, CURLOPT_ENCODING, '');

        // entire headers + body will be stored here
        $ret = curl_exec($this->handle);

        // even if empty - mark it as last error
        $this->error = curl_error($this->handle);

        if ($ret == false) {
            // throw exception!
            return false;
        }

        $info = curl_getinfo($this->handle);
        $this->info = $info;

        if ($info == false) {
            // failed to get info!
            return false;
        }

        curl_close($this->handle);

        $rc = $info['redirect_count'];

        // last of parts will always be body
        $parts = explode("\r\n\r\n", $ret, $rc + 2);

        $headers = array_splice($parts, 0, $rc + 1);
        $body = $parts[count($parts) - 1];

        $ret = new CurlResponse($headers, $body, $info);

        return $ret;
    }
}

?>