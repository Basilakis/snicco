<?php


    declare(strict_types = 1);

    use WPEmerge\Auth\Controllers\AuthSessionController;
    use WPEmerge\Auth\Controllers\AuthConfirmationController;
    use WPEmerge\Auth\Controllers\ConfirmedAuthSessionController;
    use WPEmerge\Auth\Controllers\ForgotPasswordController;
    use WPEmerge\Auth\Controllers\LoginMagicLinkController;
    use WPEmerge\Auth\Controllers\RecoveryCodeController;
    use WPEmerge\Auth\Controllers\RegistrationLinkController;
    use WPEmerge\Auth\Controllers\ResetPasswordController;
    use WPEmerge\Auth\Controllers\TwoFactorAuthSessionController;
    use WPEmerge\Auth\TwoFactorAuthPreferenceController;
    use WPEmerge\Routing\Router;
    use WPEmerge\Auth\Controllers\ConfirmAuthMagicLinkController;

    /** @var Router $router */

    $router->middleware('secure')->group(function (Router $router) {

        // Login
        $router->get('/login', [AuthSessionController::class, 'create'])
               ->middleware('guest')
               ->name('login');

        $router->post('/login', [AuthSessionController::class, 'store'])
               ->middleware(['csrf', 'guest'])
               ->name('login');

        // login magic link creation
        $router->post('login/create-magic-link', [LoginMagicLinkController::class, 'store'])
               ->middleware('guest')->name('login.create-magic-link');

        $router->get('login/magic-link', [AuthSessionController::class, 'store'])
               ->middleware('guest')
               ->name('login.magic-link');

        // Logout
        $router->get('/logout/{user_id}', [AuthSessionController::class, 'destroy'])
               ->middleware('signed:absolute')
               ->name('logout')
               ->andNumber('user_id');


        if( AUTH_ENABLE_PASSWORD_RESETS ) {

            // forgot-password
            $router->get('/forgot-password', [ForgotPasswordController::class, 'create'])
                   ->middleware('guest')
                   ->name('forgot.password');

            $router->post('/forgot-password', [ForgotPasswordController::class, 'store'])
                   ->middleware(['csrf', 'guest'])
                   ->name('forgot.password');

            // reset-password
            $router->get('/reset-password', [ResetPasswordController::class, 'create'])
                   ->middleware('signed:absolute')
                   ->name('reset.password')
                   ->andNumber('user_id');

            $router->post('/reset-password', [ResetPasswordController::class, 'update'])
                   ->middleware(['csrf', 'signed:absolute'])
                   ->name('reset.password');

        }


        // Auth Confirmation
        $router->get('confirm', [ConfirmedAuthSessionController::class, 'create'])->middleware([
            'auth', 'auth.unconfirmed',
        ])->name('confirm');

        $router->post('confirm', [ConfirmedAuthSessionController::class, 'store'])->middleware(['auth', 'auth.unconfirmed', 'csrf']);

        $router->delete('confirm', [ConfirmedAuthSessionController::class, 'destroy'])->middleware(['auth','auth.confirmed', 'crsf']);

        $router->get('confirm/magic-link', [ConfirmedAuthSessionController::class, 'store'])
               ->middleware(['auth','auth.unconfirmed'])
               ->name('confirm.magic-link');


        if ( AUTH_ENABLE_TWO_FACTOR ) {

            $router->post('two-factor/preferences', [TwoFactorAuthPreferenceController::class, 'store'])
                   ->middleware(['auth', 'auth.confirmed'])
                   ->name('two-factor.preferences');

            $router->delete('two-factor/preferences', [TwoFactorAuthPreferenceController::class, 'destroy'])
                   ->middleware(['auth', 'auth.confirmed']);

            $router->get('two-factor/challenge', [TwoFactorAuthSessionController::class, 'create'])->name('2fa.challenge');

            // Does this need csrf protection?
            $router->get('two-factor/recovery-codes', [RecoveryCodeController::class, 'index'])
                   ->middleware(['auth', 'auth.confirmed', 'signed'])
                   ->name('2fa.recovery-codes');

            $router->put('two-factor/recovery-codes', [RecoveryCodeController::class, 'update'])
                   ->middleware(['auth', 'auth.confirmed', 'csrf:persist']);

        }

        if ( AUTH_ENABLE_REGISTRATION ) {

            $router->get('register', [RegistrationLinkController::class, 'create'])->middleware('guest')->name('register');
            $router->post('register', [RegistrationLinkController::class, 'store'])->middleware('guest');

        }

    });











