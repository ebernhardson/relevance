<?php

namespace WikiMedia\RelevanceScoring\Import;

use Doctrine\DBAL\Connection;
use GuzzleHTTP\Client;
use WikiMedia\RelevanceScoring\Repository\QueriesRepository;
use WikiMedia\RelevanceScoring\Repository\ResultsRepository;
use WikiMedia\OAuth\User;

class Importer {
    private $db;
    private $queriesRepo;
    private $resultsRepo;
    private $http;
    private $wikis;
    private $resultsPerSource = 25;

    public function __construct(Connection $db, QueriesRepository $queriesRepo, ResultsRepository $resultsRepo, array $wikis, array $getters) {
        $this->db = $db;
        $this->queriesRepo = $queriesRepo;
        $this->resultsRepo = $resultsRepo;
        $this->wikis = $wikis;
        $this->getters = $getters;
    }

    public function import(User $user, $wiki, $query) {
        if ( !isset( $this->wikis[$wiki] ) ) {
            throw new \Exception( "Unknown wiki: $wiki" );
        }

        $maybeQueryId = $this->queriesRepo->findQueryId( $wiki, $query );
        if ($maybeQueryId->nonEmpty()) {
            throw new \Exception( 'Query already imported' );
        } 
        $results = $this->performSearch( $wiki, $query );

        $this->db->transactional(function () use ($user, $wiki, $query, $results) {
            $queryId = $this->queriesRepo->createQuery($user, $wiki, $query, 'imported');
            $this->resultsRepo->storeResults($user, $queryId, $results);
        });

        return count($results);
    }

    public function  importPending($limit, $userId=null) {
        $queries = $this->queriesRepo->getPendingQueries($limit, $userId);
        $imported = 0;
        foreach ($queries as $query) {
            $results = $this->performSearch($query['wiki'], $query['query']);
            $this->db->transactional(function () use ($query, $results) {
                $this->resultsRepo->storeResults($query['user_id'], $query['id'], $results);
                $this->queriesRepo->markQueryImported($query['id']);
            });
            $imported += count($results);
        }
        return [count($queries), $imported];
    }

    private function performSearch( $wiki, $query ) {
        $promises = [];
        foreach ($this->getters as $key => $getter) {
            echo "Making request from $key\n";
            $promises[$key] = $getter->fetchAsync($wiki, $query);
        }
        $responses = \GuzzleHttp\Promise\unwrap($promises);

        $results = [];
        foreach ($responses as $key => $response) {
            $newResults = $this->getters[$key]->handleResponse($response, $wiki, $query);
            $newResults = array_slice($newResults, 0, $this->resultsPerSource);
            echo "Merging " . count( $newResults ) . "results from $key\n";
            $results = array_merge($results, $newResults);
        }

        return $results;
    }
}
