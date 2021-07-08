<?php


    declare(strict_types = 1);


    namespace BetterWP\Auth;

    use BetterWP\Auth\Authenticators\MagicLinkAuthenticator;
    use BetterWP\Auth\Authenticators\PasswordAuthenticator;
    use BetterWP\Auth\Authenticators\RedirectIf2FaAuthenticable;
    use BetterWP\Auth\Authenticators\TwoFactorAuthenticator;
    use BetterWP\Auth\Confirmation\EmailAuthConfirmation;
    use BetterWP\Auth\Confirmation\TwoFactorAuthConfirmation;
    use BetterWP\Auth\Contracts\AuthConfirmation;
    use BetterWP\Auth\Contracts\TwoFactorAuthenticationProvider;
    use BetterWP\Auth\Controllers\AuthSessionController;
    use BetterWP\Auth\Controllers\ConfirmedAuthSessionController;
    use BetterWP\Auth\Events\GenerateLoginUrl;
    use BetterWP\Auth\Events\GenerateLogoutUrl;
    use BetterWP\Auth\Events\SettingAuthCookie;
    use BetterWP\Auth\Listeners\GenerateNewAuthCookie;
    use BetterWP\Auth\Listeners\RefreshAuthCookies;
    use BetterWP\Auth\Listeners\WpLoginLinkGenerator;
    use BetterWP\Auth\Middleware\AuthenticateSession;
    use BetterWP\Auth\Middleware\AuthUnconfirmed;
    use BetterWP\Auth\Middleware\ConfirmAuth;
    use BetterWP\Auth\Responses\EmailRegistrationViewResponse;
    use BetterWP\Auth\Responses\Google2FaChallengeResponse;
    use BetterWP\Auth\Contracts\LoginResponse;
    use BetterWP\Auth\Contracts\LoginViewResponse;
    use BetterWP\Auth\Responses\PasswordLoginView;
    use BetterWP\Auth\Responses\RedirectToDashboardResponse;
    use BetterWP\Auth\Contracts\RegistrationViewResponse;
    use BetterWP\Auth\Contracts\TwoFactorChallengeResponse;
    use BetterWP\Contracts\EncryptorInterface;
    use BetterWP\Contracts\ServiceProvider;
    use BetterWP\Events\WpInit;
    use BetterWP\Support\WP;
    use BetterWP\Http\Psr7\Request;
    use BetterWP\Http\ResponseFactory;
    use BetterWP\Middleware\Secure;
    use BetterWP\Session\Events\SessionRegenerated;
    use BetterWP\Session\Contracts\SessionDriver;
    use BetterWP\Session\Middleware\StartSessionMiddleware;
    use BetterWP\Session\SessionManager;
    use BetterWP\Session\Contracts\SessionManagerInterface;

    class AuthServiceProvider extends ServiceProvider
    {

        public function register() : void
        {

            $this->bindConfig();

            $this->bindAuthPipeline();

            $this->extendRoutes(__DIR__.DIRECTORY_SEPARATOR.'routes');

            $this->extendViews(__DIR__.DIRECTORY_SEPARATOR.'views');
            $this->extendViews(__DIR__.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR .'email');
            $this->extendViews(__DIR__.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR .'partials');

            $this->bindEvents();

            $this->bindControllers();

            $this->bindAuthConfirmation();

            $this->bindWpSessionToken();

            $this->bindAuthSessionManager();

            $this->bindLoginViewResponse();

            $this->bindLoginResponse();

            $this->bindTwoFactorProvider();

            $this->bindTwoFactorChallengeResponse();

            $this->bindRegistrationViewResponse();

        }

        public function bootstrap() : void
        {

            $this->bindSessionManagerInterface();

        }

        private function bindEvents()
        {

            $this->config->extend('events.listeners', [
                GenerateLoginUrl::class => [

                    [WpLoginLinkGenerator::class, 'loginUrl'],

                ],
                GenerateLogoutUrl::class => [
                    [WpLoginLinkGenerator::class, 'logoutUrl'],
                ],
                SettingAuthCookie::class => [
                    GenerateNewAuthCookie::class,
                ],
                SessionRegenerated::class => [
                    RefreshAuthCookies::class,
                ],
            ]);

            $this->config->extend('events.mapped', [

                'auth_cookie' => [
                    [SettingAuthCookie::class, 999],
                ],

            ]);

            $this->config->extend('events.last', [
                'login_url' => GenerateLoginUrl::class,
                'logout_url' => GenerateLogoutUrl::class,
            ]);

            // This filter is very misleading from WordPress. It does not filter the expire value in
            // "setcookie()" function, but filters the expiration fragment in the cookie hash.
            // The maximum value can only ever be our session absolute timeout.
            add_filter('auth_cookie_expiration', function () {

                return $this->config->get('session.lifetime');

            }, 10, 3);


        }

        private function bindControllers()
        {

            $this->container->singleton(ConfirmedAuthSessionController::class, function () {
                return new ConfirmedAuthSessionController(
                    $this->container->make(AuthConfirmation::class),
                    $this->config->get('auth.confirmation.duration')
                );
            });

            $this->container->singleton(AuthSessionController::class, function () {

                return new AuthSessionController(
                    $this->config->get('auth')
                );

            });

        }

        private function bindConfig()
        {

            // Authentication
            $this->config->extend('auth.confirmation.duration', SessionManager::HOUR_IN_SEC * 3);
            $this->config->extend('auth.idle', SessionManager::HOUR_IN_SEC / 2);
            $this->config->extend('auth.authenticator', 'password');
            $this->config->extend('auth.features.remember_me', false);
            $this->config->extend('auth.features.password-resets', false);
            $this->config->extend('auth.features.2fa', false);
            $this->config->extend('auth.features.registration', false);

            // Middleware
            $this->config->extend('middleware.aliases', [
                'auth.confirmed' => ConfirmAuth::class,
                'auth.unconfirmed' => AuthUnconfirmed::class,
            ]);
            $this->config->extend('middleware.groups.global', [
                Secure::class,
                StartSessionMiddleware::class,
                AuthenticateSession::class,
            ]);
            $this->config->extend('middleware.priority', [
                StartSessionMiddleware::class,
                AuthenticateSession::class,
            ]);

            // Endpoints
            $this->config->extend('auth.endpoints.prefix', 'auth');
            $this->config->extend('auth.endpoints.login', 'login');
            $this->config->extend('auth.endpoints.magic-link', 'magic-link');
            $this->config->extend('auth.endpoints.confirm', 'confirm');
            $this->config->extend('auth.endpoints.2fa', 'two-factor');
            $this->config->extend('auth.endpoints.challenge', 'challenge');
            $this->config->extend('auth.endpoints.register', 'register');
            $this->config->extend('auth.endpoints.forgot-password', 'forgot-password');
            $this->config->extend('auth.endpoints.reset-password', 'reset-password');
            $this->config->extend('auth.endpoints.accounts', 'accounts');
            $this->config->extend('auth.endpoints.accounts_create', 'create');
            $this->config->extend('routing.api.endpoints', [

                'auth' => $this->config->get('auth.endpoints.prefix'),
                // Needed so we can respond to request to wp-login.php.
                'wp-login.php' => '/wp-login.php'

            ]);


        }

        private function bindWpSessionToken()
        {

            add_filter('session_token_manager', function () {

                $manager = $this->container->make(SessionManagerInterface::class);

                // Ugly hack. But there is no other way to get this instance to the class that
                // extends WP_SESSION_TOKENS because of Wordpress not using interfaces or DI:
                // These globals are immediately unset and can not be used anywhere else.
                global $session_manager;
                $session_manager = $manager;

                global $__request;
                $__request = $this->container->make(Request::class);

                return WpAuthSessionToken::class;

            });

        }

        private function bindSessionManagerInterface()
        {

            $this->container->singleton(SessionManagerInterface::class, function () {

                return $this->container->make(AuthSessionManager::class);

            });

        }

        private function bindAuthSessionManager()
        {

            $this->container->singleton(AuthSessionManager::class, function () {

                return new AuthSessionManager(
                    $this->container->make(SessionManager::class),
                    $this->container->make(SessionDriver::class),
                    $this->config->get('auth')
                );

            });
        }

        private function bindLoginViewResponse()
        {

            $this->container->singleton(LoginViewResponse::class, function () {

                $response = $this->config->get('auth.primary_view', PasswordLoginView::class);

                return $this->container->make($response);

            });
        }

        private function bindAuthPipeline()
        {

            $pipeline = $this->config->get('auth.through', []);

            if ( ! count($pipeline) ) {

                $primary = ( $this->config->get('auth.authenticator') === 'email' )
                    ? MagicLinkAuthenticator::class
                    : PasswordAuthenticator::class;

                $two_factor = $this->config->get('auth.features.2fa');

                $this->config->set('auth.through', array_values(array_filter([

                    $two_factor ? TwoFactorAuthenticator::class : null,
                    $two_factor ? RedirectIf2FaAuthenticable::class : null,
                    $primary

                ])));

            }


        }

        private function bindLoginResponse()
        {
            $this->container->singleton(LoginResponse::class, function () {

                return $this->container->make(RedirectToDashboardResponse::class);

            });
        }

        private function bindTwoFactorProvider()
        {
            $this->container->singleton(TwoFactorAuthenticationProvider::class, function () {

                return $this->container->make(Google2FaAuthenticationProvider::class);

            });
        }

        private function bindTwoFactorChallengeResponse()
        {

            $this->container->singleton(TwoFactorChallengeResponse::class, function () {

                return $this->container->make(Google2FaChallengeResponse::class);

            });

        }

        private function bindRegistrationViewResponse()
        {
             $this->container->singleton(RegistrationViewResponse::class, function () {

                return $this->container->make(EmailRegistrationViewResponse::class);

            });
        }

        private function bindAuthConfirmation()
        {
            $this->container->singleton(AuthConfirmation::class, function () {

                if ( $this->config->get('auth.features.2fa') ) {

                    return new TwoFactorAuthConfirmation(
                        $this->container->make(EmailAuthConfirmation::class),
                        $this->container->make(TwoFactorAuthenticationProvider::class),
                        $this->container->make(ResponseFactory::class),
                        $this->container->make(EncryptorInterface::class),
                        WP::currentUser()
                    );

                }

                return $this->container->make(EmailAuthConfirmation::class);

            });
        }


    }