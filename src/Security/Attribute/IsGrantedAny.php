<?php

namespace Cesurapp\ApiBundle\Security\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class IsGrantedAny
{
    /**
     * Accepts multiple attributes as variadic parameters or a single array.
     *
     * Examples:
     *  - new IsGrantedAny('ROLE_ADMIN', 'ROLE_MANAGER')
     *  - new IsGrantedAny(['ROLE_ADMIN', 'ROLE_MANAGER'])
     */
    /**
     * @param array  $attributes Check permission (örn: ['ROLE_ADMIN', 'ROLE_EDITOR'])
     * @param mixed  $subject    Subject
     * @param string $message    Message to display when access is denied
     * @param int    $statusCode HTTP status code
     */
    public function __construct(
        public array $attributes,
        public mixed $subject = null,
        public string $message = 'Access Denied.',
        public int $statusCode = 403,
    ) {
    }
}
