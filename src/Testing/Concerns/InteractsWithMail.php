<?php

declare(strict_types=1);

namespace Snicco\Testing\Concerns;

use LogicException;
use Snicco\Events\Event;
use Snicco\Events\PendingMail;
use PHPUnit\Framework\Assert as PHPUnit;
use BetterWpHooks\Testing\FakeDispatcher;
use Snicco\Contracts\ViewFactoryInterface;
use Snicco\Testing\Assertable\AssertableMail;

trait InteractsWithMail
{
    
    protected function mailFake() :self
    {
        Event::fake([PendingMail::class]);
        
        return $this;
    }
    
    protected function clearSentMails() :self
    {
        $fake_dispatcher = Event::dispatcher();
        $fake_dispatcher->clearDispatchedEvents();
        
        return $this;
    }
    
    protected function assertMailSent(string $mailable) :AssertableMail
    {
        $fake_dispatcher = Event::dispatcher();
        
        $this->checkMailWasFaked($fake_dispatcher);
        
        $fake_dispatcher->assertDispatched(
            PendingMail::class,
            function (PendingMail $event) use ($mailable) {
                return $event->mail instanceof $mailable;
            },
            "The mail [$mailable] was not sent."
        );
        
        $events = $fake_dispatcher->allOfType(PendingMail::class);
        
        PHPUnit::assertSame(
            1,
            $actual = count($events),
            "The mail [$mailable] was sent [$actual] times."
        );
        
        return new AssertableMail($events[0], $this->app->resolve(ViewFactoryInterface::class));
    }
    
    protected function assertMailNotSent(string $mailable)
    {
        $fake_dispatcher = Event::dispatcher();
        
        $this->checkMailWasFaked($fake_dispatcher);
        
        $fake_dispatcher->assertNotDispatched(
            PendingMail::class,
            function (PendingMail $event) use ($mailable) {
                return $event->mail instanceof $mailable;
            },
            "The mail [$mailable] was not supposed to be sent."
        );
    }
    
    private function checkMailWasFaked($fake_dispatcher)
    {
        if ( ! $fake_dispatcher instanceof FakeDispatcher) {
            throw new LogicException(
                'Mails were not faked. Did you forget to call [$this->mailFake()]?'
            );
        }
    }
    
}