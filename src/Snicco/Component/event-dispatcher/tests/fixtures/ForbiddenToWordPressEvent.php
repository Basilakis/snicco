<?php

declare(strict_types=1);

namespace Snicco\Component\EventDispatcher\Tests\fixtures;

use Snicco\Component\EventDispatcher\ClassAsName;
use Snicco\Component\EventDispatcher\ClassAsPayload;
use Snicco\Component\EventDispatcher\Contracts\Event;
use Snicco\Component\EventDispatcher\Contracts\Mutable;
use Snicco\Component\EventDispatcher\Contracts\IsForbiddenToWordPress;

class ForbiddenToWordPressEvent implements Event, Mutable, IsForbiddenToWordPress
{
    
    use ClassAsName;
    use ClassAsPayload;
    
    public $val;
    
    public function __construct($val)
    {
        $this->val = $val;
    }
    
}