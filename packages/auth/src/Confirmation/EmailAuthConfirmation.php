<?php

declare(strict_types=1);

namespace Snicco\Auth\Confirmation;

use Snicco\Core\Http\Psr7\Request;
use Snicco\Core\Contracts\MagicLink;
use Snicco\Auth\Contracts\AuthConfirmation;
use Snicco\Core\Routing\Internal\Generator;
use Snicco\Auth\Contracts\AbstractEmailAuthConfirmationView;

class EmailAuthConfirmation implements AuthConfirmation
{
    
    private MagicLink                         $magic_link;
    private AbstractEmailAuthConfirmationView $response;
    private Generator                         $url;
    
    public function __construct(MagicLink $magic_link, AbstractEmailAuthConfirmationView $response, Generator $url)
    {
        $this->magic_link = $magic_link;
        $this->response = $response;
        $this->url = $url;
    }
    
    public function confirm(Request $request) :bool
    {
        $valid = $this->magic_link->hasValidSignature($request, true);
        
        if ( ! $valid) {
            return false;
        }
        
        $this->magic_link->invalidate($request->fullUrl());
        
        return true;
    }
    
    public function view(Request $request)
    {
        return $this->response->toView($request)->with(
            'send_email_route',
            $this->url->toRoute('auth.confirm.email')
        )->toString();
    }
    
}