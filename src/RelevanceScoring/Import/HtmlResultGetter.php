<?php

namespace WikiMedia\RelevanceScoring\Import;

use GuzzleHTTP\Client;
use GuzzleHttp\Promise\PromiseInterface;
use phpQuery;
use Psr\Http\Message\ResponseInterface;

class HtmlResultGetter implements ResultGetterInterface {
    private $http;
    private $wikis;
    private $source;
    private $url;
    private $selectors;
    private $extraQueryParams;

    /**
     * @param Client                $http
     * @param array<string, string> $wikis
     * @param string                $source
     * @param string                $url
     * @param array<string, string> $selectors
     */
    public function __construct(Client $http, array $wikis, $source, $url, array $selectors, array $extraQueryParams = array()) {
        $this->http = $http;
        $this->wikis = $wikis;
        $this->source = $source;
        $this->url = $url;
        $this->selectors = $selectors;
        $this->extraQueryParams = $extraQueryParams;
    }

    /**
     * @param string $wiki
     * @param string $query
     * @return PromiseInterface
     */
    public function fetchAsync($wiki, $query) {
        $domain = parse_url($this->wikis[$wiki], PHP_URL_HOST);
        return $this->http->requestAsync('GET', $this->url, [
            'query' => [
                'q' => "site:$domain $query",
            ] + $this->extraQueryParams,
        ]);
    }

    /**
     * @param ResponseInterface $response 
     * @param string            $wiki
     * @param string            $query
     * @return array<ImportedResult>
     */
    public function handleResponse(ResponseInterface $response, $wiki, $query) {
        if ($response->getStatusCode() !== 200) {
            var_dump( $response );
            throw new \Exception( 'Failed search' );
        }

        $doc = phpQuery::newDocumentHTML(
            (string)$response->getBody(),
            'utf8'
        );

        if ($doc[$this->selectors['is_valid']]->count() === 0) {
            throw new \Exception('No results section');
        }

        $results = [];
        foreach ($doc[$this->selectors['results']] as $result) {
            $pq = \pq($result);
            $results[] = new ImportedResult(
                $this->source,
                $this->extractTitle(
                    $pq[$this->selectors['url']]->attr('href')
                ),
                $pq[$this->selectors['snippet']]->html(),
                count($results)
            );
        }

        return $results;
    }

    /**
     * @param string $url
     * @return string MediaWiki title for given url
     */
    private function extractTitle($url) {
        $prefix = '/wiki/';
        $path = parse_url($url, PHP_URL_PATH);
        if (false === substr($path, 0, strlen($prefix))) {
            throw new \Exception("Invalid url: $url");
        }

        return substr($url, strlen($prefix));
    }
}
