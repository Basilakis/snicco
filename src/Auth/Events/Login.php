<?php

declare(strict_types=1);

namespace Snicco\Auth\Events;

use WP_User;
use Snicco\Events\Event;
use BetterWpHooks\Traits\IsAction;

class Login extends Event
{
    
    use IsAction;
    
    public WP_User $user;
    public bool    $remember;
    
    public function __construct(WP_User $user, bool $remember)
    {
        do_action('wp_login', $user->user_login, $user);
        
        $this->user = $user;
        $this->remember = $remember;
    }
    
}