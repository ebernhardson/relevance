<?php

use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ .'/vendor/autoload.php';


class Application extends Silex\Application {
    use Silex\Application\UrlGeneratorTrait;
    use Silex\Application\TwigTrait;
}
$app = new Application();

$app->register(new Silex\Provider\SessionServiceProvider());

$oauthProvider = new WikiMedia\OAuth\OAuthProvider();
$app->register($oauthProvider, [
    'oauth.callback_uri' => 'oob',
    'oauth.login_complete_redirect' => 'random_result',
]);
$app->mount('/oauth', $oauthProvider);

$app->register(new Silex\Provider\DoctrineServiceProvider());

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/views',
    'twig.options' => [
        'cache' => __DIR__ . '/cache/twig',
    ],
));

$app->register(new Silex\Provider\FormServiceProvider());

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.domains' => array(),
));

$app['guzzle'] = function () {
    $stack = new GuzzleHttp\HandlerStack(GuzzleHttp\choose_handler());
    // cache requests for 24 hours, makes debugging easier without repeatedly
    // hitting the search engines and getting blocked
    $stack->push(new Kevinrob\GuzzleCache\CacheMiddleware(
        new Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy(
            new Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage(
                new Doctrine\Common\Cache\FilesystemCache(__DIR__ . '/cache/guzzle')
            ),
            24 * 60 * 60
        )
    ));

    return new GuzzleHttp\Client([
        'timeout' => 2.0,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36',
        ],
        'handler' => $stack,
    ]);
};

$app->register(new WikiMedia\RelevanceScoring\RelevanceScoringProvider(), [
    'search.wikis' => [
        'enwiki' => 'https://en.wikipedia.org/w/api.php',
    ],
]);

$ini = parse_ini_file(__DIR__.'/app.ini');
foreach ( $ini as $key => $value ) {
    $app[$key] = $value;
}

if ($app['debug']) {
    $app->register(new Silex\Provider\HttpFragmentServiceProvider());
    $app->register(new Silex\Provider\ServiceControllerServiceProvider());
    $app->register(new Silex\Provider\WebProfilerServiceProvider(), [
        'profiler.cache_dir' => __DIR__ . '/cache/profiler',
    ]);
    $app->register(new Sorien\Provider\DoctrineProfilerServiceProvider());
}

// bare bones authentication / firewall
$app->before(function (Request $request) use ($app) {
    $start = microtime(true);
    $uri = $request->getRequestUri();
    if ($uri === '/login' || substr($uri, 0, 7) === '/oauth/') {
        return;
    }
    $session = $app['session'];
    $cred = $session->get('oauth.credentials');
    if ($cred === null) {
        return $app->redirect($app->path('login'));
    }
    $user = $session->get('user');
    // reauthorize the token every 24 hours
    $timeout = 24 * 60 * 60;
    if ($user === null || $user->extra['issued'] + $timeout < time() ) {
        try {
            $user = $app['oauth']->getUserDetails($cred);
        } catch (\Firebase\JWT\BeforeValidException $e) {
            throw new RuntimeException("Bad timestamp on JWT token. Clock out of sync?", 0, $e);
        } catch (\Exception $e) {
            die($e);
            $session->remove('user');
            $session->remove('oauth.credentials');

            return $app->redirect($app->path('oauth_authorize'));
        }
        $user->extra['last_authorized'] = time();
        $app['search.repository.users']->updateUser($user);
        $session->set('user', $user);
    }

    $app['twig']->addGlobal('user', $user);
});

$app->get('/', function () use ($app) {
    return $app->redirect($app->path('random_result'));
})
->bind('root');

$app->get('/login', function () use ($app) {
    return $app['twig']->render('splash.twig', [
        'domain' => parse_url($app['oauth.base_url'], PHP_URL_HOST),
    ]);
})
->bind('login');
$app->get('/logout', function () use ($app) {
    $session = $app['session'];
    $session->remove('user');
    $session->remove('oauth.credentials');
    return $app->redirect('/');
})
->bind('logout');

$app->mount('/result', require __DIR__ . '/controllers/results.php');
$app->mount('/scores', require __DIR__ . '/controllers/scores.php');
$app->mount('/import', require __DIR__ . '/controllers/import.php');

return $app;
