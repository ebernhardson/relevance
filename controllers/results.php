<?php

use Symfony\Component\HttpFoundation\Request;

$controllers = $app['controllers_factory'];

$randomResult = function (Request $request, $wiki = null) use ($app) {
    $user = $app['session']->get('user');
    $maybeId = $app['search.repository.results']->getRandomId($user, $wiki);
    $params = [];
    if ($request->query->get('saved')) {
        $params['saved'] = 1;
    }
    if ($maybeId->isEmpty()) {
        return $app['twig']->render('all_scored.twig', $params);
    } else {
        $params['id'] = $maybeId->get();
        return $app->redirect($app->path('result_by_id', $params));
    }
};
$controllers->get('/', $randomResult)->bind('random_result');
$controllers->get('/wiki/{wiki}', $randomResult)->bind('random_result_by_wiki');

$controllers->match('/id/{id}', function (Request $request, $id) use ($app) {
    $stopwatch = isset($app['stopwatch']) ? $app['stopwatch'] : null;

    $maybeResult = $app['search.repository.results']->getQueryResult($id);
    if ($maybeResult->isEmpty()) {
        throw new \Exception('Query not found');
    }

    $stopwatch && $stopwatch->start('build form');

    $builder = $app['form.factory']->createBuilder('form')
        ->add('score', 'choice', [
            'expanded' => true,
            'multiple' => false,
            'choices' => [
                'Irrelevant',
                'Maybe Relevant',
                'Probably Relevant',
                'Relevant',
            ],
        ]);
    $stopwatch && $stopwatch->stop('build form');

    $stopwatch && $stopwatch->start('create form');
    $form = $builder->getForm();
    $stopwatch && $stopwatch->stop('create form');

    $stopwatch && $stopwatch->start('handle form');
    $form->handleRequest($request);

    if ($form->isValid()) {
        $data = $form->getData();
        $user = $app['session']->get('user');
        $app['search.repository.scores']->storeQueryScore($user, $id, $data['score']);
        $stopwatch && $stopwatch->stop('handle form');
        return $app->redirect($app->path('random_result', ['saved' => 1]));
    }
    $stopwatch && $stopwatch->stop('handle form');

    $result = $maybeResult->get();
    $parts = parse_url( $app['search.wikis'][$result['wiki']] );
    $baseUrl = $parts['scheme'] . '://' . $parts['host'] . '/wiki/';

    return $app['twig']->render('score_result.twig', [
        'result' => $result,
        'wikiBaseUrl' => $baseUrl,
        'form' => $form->createView(),
        'saved' => !!$request->query->get('saved'),
    ]);
})
->bind('result_by_id');

return $controllers;
