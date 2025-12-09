<?php

namespace Cesurapp\ApiBundle\EventListener;

use Cesurapp\ApiBundle\Security\Attribute\IsGrantedAny;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Handles custom IsGrantedAny attribute.
 * Allows access when at least one of the declared roles/attributes is granted.
 */
readonly class IsGrantedAnyAttributeListener implements EventSubscriberInterface
{
    public function __construct(private ?AuthorizationCheckerInterface $authChecker)
    {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (null === $this->authChecker) {
            return;
        }

        $isGrantedAnyAttributes = $event->getAttributes(IsGrantedAny::class);
        if (empty($isGrantedAnyAttributes)) {
            return;
        }

        $arguments = $event->getNamedArguments();

        foreach ($isGrantedAnyAttributes as $attribute) {
            $subject = $attribute->subject;

            if (is_string($subject) && array_key_exists($subject, $arguments)) {
                $subject = $arguments[$subject];
            }

            $accessGranted = false;
            foreach ($attribute->attributes as $permission) {
                if ($this->authChecker->isGranted($permission, $subject)) {
                    $accessGranted = true;
                    break;
                }
            }

            if (!$accessGranted) {
                $exception = new AccessDeniedException($attribute->message);

                throw new HttpException($attribute->statusCode, $attribute->message, $exception);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 20]];
    }
}
