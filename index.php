<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application();


error_reporting(E_ALL);
ini_set('display_errors', 1);

//Chaves geradas a partir do console.google
define('GOOGLE_API_KEY', '44277468567-nv8lff9kl6rleoglgodi1o1af6u0omlr.apps.googleusercontent.com');
define('GOOGLE_API_SECRET', 'st-sgPkIkgK6xwLpWsrYj_eP');


$app['debug'] = true;

$app->register(new Gigablah\Silex\OAuth\OAuthServiceProvider(), array(
    'oauth.services' => array(
        'Google' => array(
            'key' => GOOGLE_API_KEY,
            'secret' => GOOGLE_API_SECRET,
            'scope' => array(
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile'
            ),
            'user_endpoint' => 'https://www.googleapis.com/oauth2/v1/userinfo',
            'user_callback' => function ($token, $userInfo, $service) {

                $token->setUser($userInfo['name']);
                $token->setEmail($userInfo['email']);
                $token->setUid($userInfo['id']);

                file_put_contents(__DIR__ . "/tmp/" . $userInfo['id'], print_r($userInfo, true), FILE_APPEND);

            }
        )
    )
));

// Provides URL generation

//$app->register(new \Symfony\Component\Routing\Generator\UrlGenerator());
// Provides CSRF token generation
$app->register(new Silex\Provider\FormServiceProvider());
// Provides session storage

$app->register(new Silex\Provider\SessionServiceProvider(), array(
    'session.storage.save_path' => __DIR__ . '/tmp'
));
// Provides Twig template engine
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__
));

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern' => '^/',
            'anonymous' => true,
            'oauth' => array(
                'failure_path' => '/',
                'with_csrf' => true
            ),
            'logout' => array(
                'logout_path' => '/logout',
                'with_csrf' => true
            ),
            'users' => new Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider()
        )
    ),
    'security.access_rules' => array(
        array('^/dashboard', 'ROLE_USER'),
    )
));

$app->before(function (Symfony\Component\HttpFoundation\Request $request) use ($app) {
    if (isset($app['security.token_storage'])) {
        $token = $app['security.token_storage']->getToken();
    } else {
        $token = $app['security']->getToken();
    }

    $app['user'] = null;

    if ($token && !$app['security.trust_resolver']->isAnonymous($token)) {
        $app['user'] = $token->getUser();
    }
});


//------------------------------------------------------------------------------------
//----------- ROTAS ------------------------------------------------------------------
//------------------------------------------------------------------------------------


//------- Acesso só mediante a login (definido na security.access_rules)  -----------------
$app->get('/dashboard', function () use ($app) {

    return $app['twig']->render('dashboard.twig', array(
        'login_paths' => $app['oauth.login_paths'],
        'logout_path' => $app['url_generator']->generate('logout', array(
            '_csrf_token' => $app['oauth.csrf_token']('logout')
        ))
    ));

});
//-----------------------------------------------------



$app->get('/', function () use ($app) {

    if ($app['user']) {
        return $app->redirect('/dashboard', 301);
    }

    return $app['twig']->render('index.twig', array(
        'login_paths' => $app['oauth.login_paths'],
        'logout_path' => $app['url_generator']->generate('logout', array(
            '_csrf_token' => $app['oauth.csrf_token']('logout')
        ))
    ));
});

$app->get("/login", function () use ($app) {
    return $app->redirect('/', 301);
});

$app->match('/logout', function () {
})->bind('logout');


$app->run();