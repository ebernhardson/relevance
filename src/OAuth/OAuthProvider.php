<?php

namespace WikiMedia\OAuth;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class OAuthProvider implements ControllerProviderInterface, ServiceProviderInterface {
    private $app;

    public function connect(Application $app) {
        $controllers = $app['controllers_factory'];
        $controllers->get('/authorize', [$this, 'authorize'])
            ->bind('oauth_authorize');
        $controllers->get('/callback', [$this, 'callback']);

        return $controllers;
    }

    public function register(Application $app) {
        $app['oauth'] = function () use ($app) {
            return new MediaWiki([
                'identifier'   => $app['oauth.identifier'],
                'secret'       => $app['oauth.secret'],
                'callback_uri' => $app['oauth.callback_uri'],
                'baseUrl'      => $app['oauth.base_url'],
            ]);
        };
    }

    public function boot(Application $app) {
        $this->app = $app;
    }

    public function authorize() {
        $server = $this->app['oauth'];

        $temp = $server->getTemporaryCredentials();
        $this->app['session']->set('oauth.credentials.temp', $temp);

        return $this->app->redirect($server->getAuthorizationUrl($temp));
    }

    public function callback(Request $request) {
        $token = $request->query->get('oauth_token');
        $verifier = $request->query->get('oauth_verifier');
        if (!$token || !$verifier) {
            throw new \Exception('Invalid OAuth callback');
        }

        $session = $this->app['session'];    
        $temporaryCredentials = $session->get('oauth.credentials.temp');
        if (!$temporaryCredentials) {
            throw new \Exception('No credentials in session');
        }
    
        $tokenCredentials = $this->app['oauth']->getTokenCredentials(
            $temporaryCredentials,
            $token,
            $verifier
        );
        $session->set('oauth.credentials', $tokenCredentials);

        return $this->app->redirect($this->app->path(
            $this->app['oauth.login_complete_redirect']
        ));
    }
}
