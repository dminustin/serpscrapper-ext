<?php

namespace SerpScraper\Engine;

use SerpScraper\SearchResponse;

/**
 * This class used Bing Image Search
 * Class BingImageSearch
 * @package SerpScraper\Engine
 */
class BingImageSearch extends BingSearch
{
    function search($query, $page = 1)
    {

        $sr = new SearchResponse();
        $start = ($page - 1) * $this->preferences['results_per_page'] + 1;

        $this->client->setCurlOption(CURLOPT_COOKIE, 'SRCHHPGUSR=CW=1905&CH=584&DPR=1&UTC=180&WTS=63692439432&NEWWND=0&SRCHLANG=&AS=1&ADLT=' . $this->adult . '&NNT=1&HAP=0; domain=.bing.com; expires=' . date('D, d-M-Y 00:00:00', strtotime('+3 years')) . ' GMT; path=/');

        try {
            $query = rawurlencode($query);

            $response = $this->client->get("http://www.bing.com/images/search?q={$query}&first={$start}");

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

        $elements = $this->findByClassName('div', 'item', $dom);

        $matches = array();
        foreach ($elements as $elm) {
            $title = $elm->getElementsByTagName('a')[1]->textContent;
            $href = $elm->getElementsByTagName('a')[0]->getAttribute("href");
            $p = $this->findByClassName('div', 'des', $dom->saveHTML($elm));
            if (!empty($p)) {
                $p = $p[0]->textContent;
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