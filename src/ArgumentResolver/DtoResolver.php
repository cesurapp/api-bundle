<?php

namespace Cesurapp\ApiBundle\ArgumentResolver;

use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Controller Resolve DTO Request Object.
 */
readonly class DtoResolver implements ValueResolverInterface
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!is_subclass_of($argument->getType(), ApiDto::class)) {
            return [];
        }

        $dto = $argument->getType();

        yield new $dto($request, $this->validator);
    }
}
