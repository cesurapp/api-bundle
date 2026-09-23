<?php

namespace Cesurapp\ApiBundle\AbstractClass;

use Cesurapp\ApiBundle\Response\ApiResponse;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Provides shortcuts for API features in controllers.
 */
abstract class ApiController implements ServiceSubscriberInterface
{
    protected ?ContainerInterface $container = null;

    #[Required]
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'router' => '?'.RouterInterface::class,
            'request_stack' => '?'.RequestStack::class,
            'http_kernel' => '?'.HttpKernelInterface::class,
            'security.authorization_checker' => '?'.AuthorizationCheckerInterface::class,
            'security.token_storage' => '?'.TokenStorageInterface::class,
            'security.csrf.token_manager' => '?'.CsrfTokenManagerInterface::class,
            'parameter_bag' => '?'.ContainerBagInterface::class,
        ];
    }

    private function service(string $id): mixed
    {
        if (null === $this->container) {
            throw new \LogicException(sprintf('The container is not set on "%s": is it registered as a controller service?', static::class));
        }

        return $this->container->get($id);
    }

    /**
     * Gets a container parameter by its name.
     */
    protected function getParameter(string $name): array|bool|float|int|string|null
    {
        return $this->service('parameter_bag')->get($name);
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like Bundle\BlogBundle\Controller\PostController::indexAction)
     */
    protected function forward(string $controller, array $path = [], array $query = []): ApiResponse|Response
    {
        $request = $this->service('request_stack')->getCurrentRequest();
        $path['_controller'] = $controller;

        return $this->service('http_kernel')->handle($request->duplicate($query, null, $path), HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @see UrlGeneratorInterface
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->service('router')->generate($route, $parameters, $referenceType);
    }

    protected function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->service('security.authorization_checker')->isGranted($attribute, $subject);
    }

    protected function isGrantedDeny(mixed $attribute, mixed $subject = null): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            throw new AccessDeniedHttpException('Access Denied.');
        }
    }

    /**
     * Get a user from the Security Token Storage.
     */
    protected function getUser(): ?UserInterface
    {
        if (null === $token = $this->service('security.token_storage')->getToken()) {
            return null;
        }

        return $token->getUser();
    }

    /**
     * Checks the validity of a CSRF token.
     */
    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->service('security.csrf.token_manager')->isTokenValid(new CsrfToken($id, $token));
    }

    /**
     * Returns a NotFoundHttpException.
     *
     * This will result in a 404 response code. Usage example:
     *
     * throw $this->createNotFoundException('Page not found!');
     *
     * @param $message string #TranslationKey to translate the URL for
     */
    protected function createNotFoundException(string $message = 'Not Found', ?\Throwable $previous = null): NotFoundHttpException
    {
        return new NotFoundHttpException($message, $previous);
    }

    /**
     * Returns an AccessDeniedException.
     *
     * This will result in a 403 response code. Usage example:
     *
     * throw $this->createAccessDeniedException('Unable to access this page!');
     *
     * @param $message string #TranslationKey to translate the URL for
     */
    protected function createAccessDeniedException(string $message = 'Access Denied.', ?\Throwable $previous = null): AccessDeniedHttpException
    {
        return new AccessDeniedHttpException($message, $previous);
    }
}
