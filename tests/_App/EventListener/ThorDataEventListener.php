<?php

namespace Cesurapp\ApiBundle\Tests\_App\EventListener;

use Cesurapp\ApiBundle\Thor\Event\ThorDataEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ThorDataEvent::class, method: 'onData')]
class ThorDataEventListener
{
    public function onData(ThorDataEvent $event): void
    {
        // $event->data['_enums'] = ['acmeEnum'];
    }
}
