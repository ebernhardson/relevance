<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$controllers = $app['controllers_factory'];

$controllers->get('/', function (Request $request) use ($app) {
    $supported = array(
        'html' => function ($scores) use ($app) {
            return $app['twig']->render('scores.twig', [
                'scores' => $scores
            ]);
        },
        'json' => function ($scores) {
            return new Response(
                json_encode($scores),
                Response::HTTP_OK,
                array(
                    'Content-Type' => 'application/json',
                )
            );
        }
    );
    $default = 'html';
    $accepted = $request->getAcceptableContentTypes();
    if (count($accepted) === 1 && $accepted[0] === '*/*') {
        $accepted = array();
    }

    if ($request->query->get('json')) {
        array_unshift($accepted, 'application/json');
    }

    $scores = $app['search.repository.scores']->getAll();
    foreach ($accepted as $type) {
        $format = $request->getFormat($type);
        if (isset($supported[$format])) {
            return $supported[$format]($scores);
        }
    }

    return $supported['html']($scores);
})
->bind('scores');

return $controllers;
