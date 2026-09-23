<?php

namespace Cesurapp\ApiBundle\Thor\Attribute;

/**
 * Thor Api Documentation Generator.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
final class Thor
{
    public function __construct(
        public readonly string $stack = '',
        public readonly string $title = '',
        public readonly string $info = '',
        public readonly array $query = [],
        public readonly array $request = [],
        public readonly array $header = [],
        public readonly array $response = [],
        public readonly string $dto = '',
        public readonly array $roles = [],
        public readonly bool $isHidden = false,
        public readonly bool $isPaginate = false,
        public readonly bool $isAuth = false,
        public readonly int $order = 0,
        public readonly bool $isFile = false,
    ) {
    }
}
