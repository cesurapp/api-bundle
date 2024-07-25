<?php

namespace Cesurapp\ApiBundle\Tests\_App\Resources;

use Cesurapp\ApiBundle\Response\ApiResourceInterface;

class AcmeResource implements ApiResourceInterface
{
    public function toArray(object $item, mixed $optional = null): array
    {
        return [
            'acme' => 'test',
        ];
    }

    public function toResource(): array
    {
        return [
            'acme' => 'string',
        ];
    }
}
