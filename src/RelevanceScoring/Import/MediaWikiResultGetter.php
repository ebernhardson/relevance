<?php

namespace WikiMedia\RelevanceScoring\Import;

use GuzzleHTTP\Client;
use Psr\Http\Message\ResponseInterface;
use WikiMedia\OAuth\User;

class MediaWikiResultGetter implements ResultGetterInterface {
    private $http;
    private $wikis;
    private $limit;

    /**
     * @param Client $http
     * @param array<string, string> $wikis
     * @param int $limit
     */
    public function __construct(Client $http, array $wikis, $limit) {
        $this->http = $http;
        $this->wikis = $wikis;
        $this->limit = $limit;
    }

    public function fetchAsync( $wiki, $query ) {
        return $this->http->requestAsync('GET', $this->wikis[$wiki], [
            'query' => [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $query,
                'srlimit' => $this->limit,
                'formatversion' => 2,
                'format' => 'json',
            ],
        ]);
    }

    public function handleResponse(ResponseInterface $response, $wiki, $query) {
        if ($response->getStatusCode() !== 200) {
            throw new \Exception( 'Failed search' );
        }

        $json = $response->getBody();
        $decoded = json_decode( (string) $json, true );
        if ( !isset( $decoded['query']['search'] ) ) {
            throw new \Exception( 'Invalid response: no .query.search' );
        }

        $results = [];
        foreach ($decoded['query']['search'] as $result ) {
            $results[] = new ImportedResult(
                $wiki,
                $this->buildUrl($wiki, $result['title']),
                $result['snippet'],
                count($results)
            );
        }

        return $results;
    }

    private function buildUrl($wiki, $title) {
        $domain = parse_url($this->wikis[$wiki], PHP_URL_HOST);
        return "https://$domain/wiki/" . strtr($title, ' ', '_');
    }
}
