<?php

declare(strict_types=1);

namespace Snicco\Component\Eloquent\Illuminate;

use Illuminate\Database\Eloquent\Model as IlluminateModel;

/**
 * @api
 */
class WPModel extends IlluminateModel
{
    
    public static string $factory_namespace;
    
}