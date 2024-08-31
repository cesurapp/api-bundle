<?php

namespace Cesurapp\ApiBundle\Thor\Attribute;

/**
 * Thor Api Documentation Generator.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
final class Thor
{
    public function __construct(
        protected string $stack = '',
        protected string $title = '',
        protected string $info = '',
        protected array $query = [],
        protected array $request = [],
        protected array $header = [],
        protected array $response = [],
        protected string $dto = '',
        protected array $roles = [],
        protected bool $isHidden = false,
        protected bool $isPaginate = false,
        protected bool $isAuth = false,
        protected int $order = 0,
        protected bool $isFile = false,
    ) {
    }
}
