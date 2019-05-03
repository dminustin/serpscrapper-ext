<?php

namespace SerpScraper\Engine;

use SerpScraper\SearchResponse;

class BingSearch extends SearchEngine
{

    function __construct()
    {
        parent::__construct();

        $this->preferences['results_per_page'] = 10;
    }

    function setPreference($name, $value)
    {

        if ($name == 'search_market') {
            $this->setSearchMarket($value);
        }

        if ($name == 'results_per_page') {
            $this->setResultsPerPage($value);
        }

        parent::setPreference($name, $value);
    }

    // en-us, en-gb, it-IT, ru-RU...

    private function setSearchMarket($search_market)
    {

        try {

            $body = $this->client->get("http://www.bing.com/account/worldwide")->getBody();

            if (preg_match('/<a href="([^"]*setmkt=' . $search_market . '[^"]*)"/i', $body, $matches)) {

                $url = htmlspecialchars_decode($matches[1]);

                // this will set the session cookie
                $this->client->get($url);
            }

        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }

    // override

    private function setResultsPerPage($count)
    {

        $count_allowed = array(10, 15, 30, 50);

        if (!in_array($count, $count_allowed)) {
            throw new \InvalidArgumentException('Invalid number!');
        }

        try {

            // open up the bing options page
            $html_form = $this->client->get("http://www.bing.com/account/web")->getBody();

            // parse various session values from that page
            preg_match_all('/<input[^>]*name="\b(guid|sid|ru|uid)\b"[^>]*value="(.*?)"/i', $html_form, $matches, PREG_SET_ORDER);

            if ($matches) {

                // change some of them
                $options = array(
                    'rpp' => $count,
                    'pref_sbmt' => 1,
                );

                foreach ($matches as $match) {
                    $options[$match[1]] = $match[2];
                }

                // submit the form and get the cookie that determines the number of results per page
                $this->client->get("http://www.bing.com/account/web?" . http_build_query($options));
            }

        } catch (\Exception $ex) {
            echo($ex->getMessage());
        }
    }

    function search($query, $page = 1)
    {

        $sr = new SearchResponse();
        $start = ($page - 1) * $this->preferences['results_per_page'] + 1;

        $this->client->setCurlOption(CURLOPT_COOKIE, 'SRCHHPGUSR=CW=1905&CH=584&DPR=1&UTC=180&WTS=63692439432&NEWWND=0&SRCHLANG=&AS=1&ADLT=' . $this->adult . '&NNT=1&HAP=0; domain=.bing.com; expires=' . date('D, d-M-Y 00:00:00', strtotime('+3 years')) . ' GMT; path=/');


        try {
            $query = rawurlencode($query);
            $response = $this->client->get("http://www.bing.com/search?q={$query}&first={$start}");

            // get HTML body
            $body = $response->getBody();
            $sr->html = $body;

            $sr->results = $this->extractResults($body);

            $sr->has_next_page = preg_match("#div class=\"sw_next\">.*?</div#s", $body) !== false;

        } catch (\Exception $ex) {
            $sr->error = $ex->getMessage();
        }

        return $sr;
    }

    function extractResults($html)
    {

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $finder = new \DomXPath($dom);

        $elements = $finder->query('//li[contains(@class, \'b_algo\')]');

        $matches = array();
        foreach ($elements as $elm) {
            $title = $elm->getElementsByTagName('a')[0]->textContent;
            $href = $elm->getElementsByTagName('a')[0]->getAttribute("href");
            $p = $elm->getElementsByTagName('p');
            if (!empty($p)) {
                $p = $elm->getElementsByTagName('p')[0]->textContent;
            } else {
                $p = '';
            }
            $matches[] = array(
                'title' => $title,
                'href' => $href,
                'snippet' => $p
            );
        }

        return $matches;
    }
}
