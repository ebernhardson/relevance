<?php

namespace WikiMedia\RelevanceScoring\Import;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

interface ResultGetterInterface {
    /**
     * @param string $wiki
     * @param string $query
     * @return PromiseInterface
     */
    function fetchAsync($wiki, $query);

    /**
     * @param ResponseInterface $response
     * @param string            $wiki
     * @param string            $query
     * @return array<ImportedResult>
     */
    function handleResponse(ResponseInterface $response, $wiki, $query);
}
