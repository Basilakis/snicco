<?php

declare(strict_types=1);

namespace Snicco\Component\Templating\ViewComposer;

use Closure;
use Snicco\Component\Templating\Exception\BadViewComposer;

/**
 * @api
 */
interface ViewComposerFactory
{

    /**
     * @param class-string<ViewComposer>|Closure $composer
     *
     * @throws BadViewComposer
     */
    public function create($composer): ViewComposer;

}