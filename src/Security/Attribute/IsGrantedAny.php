<?php

namespace Cesurapp\ApiBundle\Security\Attribute;

/**
 * Access is granted when at least one of the attributes is granted.
 *
 * Examples:
 *  - #[IsGrantedAny(['ROLE_ADMIN', 'ROLE_MANAGER'])]
 *  - #[IsGrantedAny('ROLE_ADMIN')]
 *  - #[IsGrantedAny(['POST_EDIT', 'ROLE_ADMIN'], subject: 'post')]
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
class IsGrantedAny
{
    /** @var list<string> */
    public array $attributes;

    /**
     * @param string|array<string> $attributes Permissions, one of them must be granted (e.g. ['ROLE_ADMIN', 'ROLE_EDITOR'])
     * @param mixed                $subject    Subject, or the name of a controller argument holding it
     * @param string               $message    Message to display when access is denied
     * @param int                  $statusCode HTTP status code
     */
    public function __construct(
        string|array $attributes,
        public mixed $subject = null,
        public string $message = 'Access Denied.',
        public int $statusCode = 403,
    ) {
        $this->attributes = array_values((array) $attributes);
    }
}
