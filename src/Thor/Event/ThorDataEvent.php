<?php

namespace Cesurapp\ApiBundle\Thor\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThorDataEvent extends Event
{
    public function __construct(public array &$data)
    {
    }
}
