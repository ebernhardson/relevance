<?php

namespace WikiMedia\RelevanceScoring;

use Silex\Application;
use Silex\ServiceProviderInterface;

class RelevanceScoringProvider implements ServiceProviderInterface {

    public function register(Application $app) {
        $app['search.repository.queries'] = function () use ($app) {
            return new Repository\QueriesRepository($app['db']);
        };
        $app['search.repository.results'] = function () use ($app) {
            return new Repository\ResultsRepository($app['db']);
        };
        $app['search.repository.scores'] = function () use ($app) {
            return new Repository\ScoresRepository($app['db']);
        };
        $app['search.repository.users'] = function () use ($app) {
            return new Repository\UsersRepository($app['db']);
        };

        $app['search.importer_limit'] = 25;
        $app['search.importer.bing'] = function () use ($app) {
            return new Import\HtmlResultGetter(
                $app['guzzle'],
                $app['search.wikis'],
                'bing',
                'https://www.bing.com/search',
                [
                    'is_valid' => '#b_results',
                    'results' => '#b_results .b_algo',
                    'url' => 'h2 a',
                    'snippet' => '.b_caption p'
                ],
                [
                    'count' => $app['search.importer_limit'],
                ]
            );
        };
        $app['search.importer.google'] = function () use ($app) {
            return new Import\HtmlResultGetter(
                $app['guzzle'],
                $app['search.wikis'],
                'google',
                'https://www.google.com/search',
                [
                    'is_valid' => '#ires',
                    'results' => '#ires .g',
                    'url' => 'h3 a',
                    'snippet' => '.st',
                ],
                [
                    // google whitelists a specific set of numbers
                    'num' => array_reduce([100,50,40,30,20,10], function ($a, $b) use ($app) {
                        if ($b > $app['search.importer_limit']) {
                            return $b;
                        } else {
                            return $a;
                        }
                    }, 100),
                ]
            );
        };
        $app['search.importer.ddg'] = function () use ($app) {
            return new Import\HtmlResultGetter(
                $app['guzzle'],
                $app['search.wikis'],
                'ddg',
                'https://duckduckgo.com/html/',
                [
                    'is_valid' => '#links',
                    'results' => '#links .web-result',
                    'url' => 'a',
                    'snippet' => '.snippet',
                ]
            );
        };
        $app['search.importer.mediawiki'] = function () use ($app) {
            return new Import\MediaWikiResultGetter(
                $app['guzzle'],
                $app['search.wikis'],
                $app['search.importer_limit']
            );
        };
        $app['search.importer'] = function() use ($app) {
            return new Import\Importer(
                $app['db'],
                $app['search.repository.queries'],
                $app['search.repository.results'],
                $app['search.wikis'],
                [
                    $app['search.importer.bing'],
                    $app['search.importer.google'],
                    $app['search.importer.ddg'],
                    $app['search.importer.mediawiki'],
                ]
            );
        };
    }

    public function boot(Application $app) {
    }
}
