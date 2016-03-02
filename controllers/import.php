<?php

use Symfony\Component\HttpFoundation\Request;

$controllers = $app['controllers_factory'];

$controllers->match('/', function () use ($app) {
    return $app->redirect($app->path('import_query'));
})
->bind('import');

$controllers->match('/query', function (Request $request) use ($app) {
    // @todo add validation constraints
    $wikis = array_keys( $app['search.wikis'] );
    $form = $app['form.factory']->createBuilder('form')
        ->add('wiki', 'choice', [
            'choices' => array_combine( $wikis, $wikis ),
        ])
        ->add('query')
        ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
        $data = $form->getData();
        $app['search.repository.queries']->createQuery(
            $app['session']->get('user'),
            $data['wiki'],
            $data['query']
        );

        return $app->redirect($app->path('import_query', ['saved' => 1]));
    }

    return $app['twig']->render('import_query.twig', array(
        'form' => $form->createView(),
        'saved' => $request->query->get('saved'),
    ));
})
->bind('import_query');

return $controllers;
