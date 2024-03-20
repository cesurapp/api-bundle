<?php

namespace Cesurapp\ApiBundle\Thor\Attribute;

/**
 * Thor Api Resource Documentation.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
final class ThorResource
{
    // @phpstan-ignore-next-line
    public function __construct(protected array $data = [], callable|string|null $callback = null)
    {
    }
}
